/**
 * External dependencies
 */
import { useState } from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

import SiteModal from '@/components/SiteModal';

type SiteData = { name: string; url: string; api_key: string };

const baseFormData: SiteData = {
	name: 'Brand Site',
	url: 'https://brand.example.com',
	api_key: 'secret-key',
};

const okResponse = () =>
	( {
		ok: true,
		json: jest.fn().mockResolvedValue( {} ),
	} ) as unknown as Response;

const errorResponse = (
	message = 'An error occurred while saving the site. Please try again.'
) =>
	( {
		ok: false,
		json: jest.fn().mockResolvedValue( { message } ),
	} ) as unknown as Response;

function SiteModalHarness( {
	initialData = baseFormData,
	editing = false,
	originalData,
	onSubmit = jest.fn().mockResolvedValue( okResponse() ),
}: {
	initialData?: SiteData;
	editing?: boolean;
	originalData?: SiteData;
	onSubmit?: jest.Mock< Promise< Response >, [] >;
} ) {
	const [ formData, setFormData ] = useState( initialData );

	return (
		<SiteModal
			formData={ formData }
			setFormData={ setFormData }
			onSubmit={ onSubmit }
			onClose={ jest.fn() }
			editing={ editing }
			originalData={ originalData }
		/>
	);
}

describe( 'SiteModal', () => {
	it( 'disables submit when editing without changes', () => {
		render(
			<SiteModalHarness
				editing
				initialData={ baseFormData }
				originalData={ baseFormData }
			/>
		);

		expect(
			screen.getByRole( 'button', { name: 'Update Site' } )
		).toBeDisabled();
	} );

	it( 'shows validation feedback for invalid input', async () => {
		render(
			<SiteModalHarness
				initialData={ {
					name: 'A very long site name beyond',
					url: 'invalid-url',
					api_key: 'secret-key',
				} }
			/>
		);

		fireEvent.click( screen.getByRole( 'button', { name: 'Add Site' } ) );

		// WordPress renders notices in both accessibility region and visible content
		expect(
			await screen.findAllByText(
				'Site Name must be under 20 characters.'
			)
		).toHaveLength( 2 );
	} );

	it( 'shows a health check error when the remote site rejects the credentials', async () => {
		const onSubmit = jest.fn().mockResolvedValue( okResponse() );
		global.fetch = jest.fn().mockResolvedValue( {
			json: jest.fn().mockResolvedValue( { success: false } ),
		} ) as typeof fetch;

		render( <SiteModalHarness onSubmit={ onSubmit } /> );

		fireEvent.click( screen.getByRole( 'button', { name: 'Add Site' } ) );

		// WordPress renders notices in both accessibility region and visible content
		expect(
			await screen.findAllByText(
				'Health check failed. Please ensure the site is accessible and the api key is correct.'
			)
		).toHaveLength( 2 );
		expect( onSubmit ).not.toHaveBeenCalled();
	} );

	it( 'shows an error when saving the site fails', async () => {
		const onSubmit = jest.fn().mockResolvedValue( errorResponse() );
		global.fetch = jest.fn().mockResolvedValue( {
			json: jest.fn().mockResolvedValue( { success: true } ),
		} ) as typeof fetch;

		render( <SiteModalHarness onSubmit={ onSubmit } /> );

		fireEvent.click( screen.getByRole( 'button', { name: 'Add Site' } ) );

		// WordPress renders notices in both accessibility region and visible content
		expect(
			await screen.findAllByText(
				'An error occurred while saving the site. Please try again.'
			)
		).toHaveLength( 2 );
		expect( onSubmit ).toHaveBeenCalled();
	} );

	it( 'handles unexpected fetch failures gracefully', async () => {
		global.fetch = jest
			.fn()
			.mockRejectedValue( new Error( 'network failed' ) ) as typeof fetch;

		render( <SiteModalHarness /> );

		fireEvent.click( screen.getByRole( 'button', { name: 'Add Site' } ) );

		// WordPress renders notices in both accessibility region and visible content
		expect(
			await screen.findAllByText(
				'An unexpected error occurred. Please try again.'
			)
		).toHaveLength( 2 );
	} );

	it( 'submits after a successful health check', async () => {
		const onSubmit = jest.fn().mockResolvedValue( okResponse() );
		global.fetch = jest.fn().mockResolvedValue( {
			json: jest.fn().mockResolvedValue( { success: true } ),
		} ) as typeof fetch;

		render( <SiteModalHarness onSubmit={ onSubmit } /> );

		fireEvent.click( screen.getByRole( 'button', { name: 'Add Site' } ) );

		await waitFor( () => {
			expect( global.fetch ).toHaveBeenCalledWith(
				'https://brand.example.com/wp-json/onelogs/v1/health-check',
				expect.objectContaining( {
					method: 'GET',
					headers: expect.objectContaining( {
						'X-OneLogs-Token': 'secret-key',
					} ),
				} )
			);
		} );

		expect( onSubmit ).toHaveBeenCalled();
		expect( screen.queryByRole( 'alert' ) ).not.toBeInTheDocument();
	} );
} );
