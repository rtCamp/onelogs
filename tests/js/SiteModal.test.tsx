/**
 * External dependencies
 */
import { useState } from 'react';
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import SiteModal from '@/components/SiteModal';
import { defaultBrandSite, type BrandSite } from '@/admin/settings/page';

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

const baseFormData: BrandSite = {
	...defaultBrandSite,
	name: 'Brand Site',
	url: 'https://brand.example.com',
	api_key: 'secret-key',
};

function SiteModalHarness( {
	initialData = baseFormData,
	editing = false,
	sites = [],
	originalData,
	onSubmit = jest.fn().mockResolvedValue( true ),
}: {
	initialData?: BrandSite;
	editing?: boolean;
	sites?: BrandSite[];
	originalData?: BrandSite;
	onSubmit?: jest.Mock< Promise< boolean | string >, [] >;
} ) {
	const [ formData, setFormData ] = useState( initialData );

	return (
		<SiteModal
			formData={ formData }
			setFormData={ setFormData }
			onSubmit={ onSubmit }
			onClose={ jest.fn() }
			editing={ editing }
			sites={ sites }
			originalData={ originalData }
		/>
	);
}

const clickAdd = () =>
	fireEvent.click( screen.getByRole( 'button', { name: 'Add Site' } ) );

describe( 'SiteModal', () => {
	it( 'disables submit when editing without changes', () => {
		// The key field starts empty when editing.
		const stored = { ...baseFormData, api_key: '' };

		render(
			<SiteModalHarness
				editing
				initialData={ stored }
				originalData={ stored }
			/>
		);

		expect(
			screen.getByRole( 'button', { name: 'Update Site' } )
		).toBeDisabled();
	} );

	it( 'disables submit for a new site until an API key is typed', () => {
		render(
			<SiteModalHarness
				initialData={ { ...baseFormData, api_key: '' } }
			/>
		);

		expect(
			screen.getByRole( 'button', { name: 'Add Site' } )
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

		clickAdd();

		// WordPress renders notices in both accessibility region and visible content
		expect(
			await screen.findAllByText(
				'Site Name must be under 20 characters.'
			)
		).toHaveLength( 2 );
	} );

	it( 'requires a site name even when it is only whitespace', async () => {
		const onSubmit = jest.fn().mockResolvedValue( true );

		render(
			<SiteModalHarness
				onSubmit={ onSubmit }
				initialData={ { ...baseFormData, name: '   ' } }
			/>
		);

		clickAdd();

		expect(
			await screen.findAllByText( 'Site Name is required.' )
		).toHaveLength( 2 );
		expect( onSubmit ).not.toHaveBeenCalled();
	} );

	it.each( [ 'ftp://example.com', 'javascript:alert(1)', 'abc' ] )(
		'rejects %s with the http/https message (QA TC-032, TC-033)',
		async ( url ) => {
			const onSubmit = jest.fn().mockResolvedValue( true );

			render(
				<SiteModalHarness
					onSubmit={ onSubmit }
					initialData={ { ...baseFormData, url } }
				/>
			);

			clickAdd();

			expect(
				await screen.findAllByText(
					'Enter a valid URL (must start with http or https).'
				)
			).toHaveLength( 2 );
			expect( onSubmit ).not.toHaveBeenCalled();
		}
	);

	it.each( [
		'https://brand.example.com/',
		'https://brand.example.com',
		'HTTPS://Brand.Example.com/',
	] )(
		'treats %s as a duplicate of an existing site (QA TC-036, TC-037)',
		async ( url ) => {
			const onSubmit = jest.fn().mockResolvedValue( true );

			render(
				<SiteModalHarness
					onSubmit={ onSubmit }
					initialData={ { ...baseFormData, url } }
					sites={ [
						{
							id: 'existing',
							name: 'Existing',
							url: 'https://brand.example.com/',
						},
					] }
				/>
			);

			clickAdd();

			expect(
				await screen.findAllByText( 'Brand Site already exists.' )
			).toHaveLength( 2 );
			expect( onSubmit ).not.toHaveBeenCalled();
		}
	);

	it( 'shows an error when saving the site fails', async () => {
		const onSubmit = jest.fn().mockResolvedValue( false );

		render( <SiteModalHarness onSubmit={ onSubmit } /> );

		clickAdd();

		// WordPress renders notices in both accessibility region and visible content
		expect(
			await screen.findAllByText(
				'An error occurred while saving the site. Please try again.'
			)
		).toHaveLength( 2 );
		expect( onSubmit ).toHaveBeenCalled();
	} );

	it( 'shows the server message when saving fails', async () => {
		const onSubmit = jest
			.fn()
			.mockResolvedValue(
				"The brand site rejected this API key. Copy the key from the brand site's OneLogs settings and try again."
			);

		render( <SiteModalHarness onSubmit={ onSubmit } /> );

		clickAdd();

		expect(
			await screen.findAllByText(
				"The brand site rejected this API key. Copy the key from the brand site's OneLogs settings and try again."
			)
		).toHaveLength( 2 );
	} );

	it( 'handles a submit handler that throws', async () => {
		const onSubmit = jest.fn().mockRejectedValue( new Error( 'boom' ) );

		render( <SiteModalHarness onSubmit={ onSubmit } /> );

		clickAdd();

		expect(
			await screen.findAllByText(
				'An unexpected error occurred. Please try again.'
			)
		).toHaveLength( 2 );
		expect(
			screen.getByRole( 'button', { name: 'Add Site' } )
		).not.toBeDisabled();
	} );

	it( 'submits without calling the brand site, which the server checks on save', async () => {
		const onSubmit = jest.fn().mockResolvedValue( true );

		render( <SiteModalHarness onSubmit={ onSubmit } /> );

		clickAdd();

		await waitFor( () => {
			expect( onSubmit ).toHaveBeenCalled();
		} );

		expect( mockedApiFetch ).not.toHaveBeenCalled();
		expect( global.fetch ).not.toHaveBeenCalled();
		expect( screen.queryByRole( 'alert' ) ).not.toBeInTheDocument();
	} );

	it( 'saves an edit without a new key', async () => {
		const onSubmit = jest.fn().mockResolvedValue( true );
		const stored = { ...baseFormData, id: 'one', api_key: '' };

		render(
			<SiteModalHarness
				editing
				onSubmit={ onSubmit }
				initialData={ { ...stored, name: 'Renamed' } }
				originalData={ stored }
				sites={ [ stored ] }
			/>
		);

		expect(
			screen.getByText( 'Leave blank to keep the current key.' )
		).toBeInTheDocument();

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Update Site' } )
		);

		await waitFor( () => {
			expect( onSubmit ).toHaveBeenCalled();
		} );
	} );

	it( 'allows keeping the original url when editing', async () => {
		const onSubmit = jest.fn().mockResolvedValue( true );
		const stored = {
			...baseFormData,
			id: 'one',
			url: 'https://brand.example.com/',
			api_key: '',
		};

		render(
			<SiteModalHarness
				editing
				onSubmit={ onSubmit }
				initialData={ { ...stored, name: 'Renamed' } }
				originalData={ stored }
				sites={ [ stored ] }
			/>
		);

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Update Site' } )
		);

		await waitFor( () => {
			expect( onSubmit ).toHaveBeenCalled();
		} );
		expect(
			screen.queryByText( 'Brand Site already exists.' )
		).not.toBeInTheDocument();
	} );

	it( 'needs the key again when an edit changes the URL', () => {
		const stored = { ...baseFormData, id: 'one', api_key: '' };

		render(
			<SiteModalHarness
				editing
				initialData={ {
					...stored,
					url: 'https://moved.example.com',
				} }
				originalData={ stored }
				sites={ [ stored ] }
			/>
		);

		expect(
			screen.getByRole( 'button', { name: 'Update Site' } )
		).toBeDisabled();
		expect( screen.getByText( 'API Key*' ) ).toBeInTheDocument();
	} );

	it( 'does not need the key again when an edit only changes the URL spelling', () => {
		const stored = {
			...baseFormData,
			id: 'one',
			url: 'https://brand.example.com/',
			api_key: '',
		};

		render(
			<SiteModalHarness
				editing
				initialData={ {
					...stored,
					url: 'HTTPS://Brand.example.com',
				} }
				originalData={ stored }
				sites={ [ stored ] }
			/>
		);

		expect(
			screen.getByRole( 'button', { name: 'Update Site' } )
		).not.toBeDisabled();
	} );
} );
