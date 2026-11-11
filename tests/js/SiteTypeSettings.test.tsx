/**
 * External dependencies
 */
import { fireEvent, render, screen, waitFor } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import SiteTypeSettings from '@/components/SiteTypeSettings';

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

const selectButton = () =>
	screen.getByRole( 'button', { name: 'Select Current Site Type' } );

describe( 'SiteTypeSettings', () => {
	it( 'keeps the select button disabled until a site type is chosen (QA TC-012)', () => {
		render( <SiteTypeSettings /> );

		expect( selectButton() ).toBeDisabled();

		fireEvent.change( screen.getByLabelText( 'Site Type' ), {
			target: { value: 'brand-site' },
		} );

		expect( selectButton() ).not.toBeDisabled();
	} );

	it( 'says the site type cannot be changed later (QA TC-016)', () => {
		render( <SiteTypeSettings /> );

		expect(
			screen.getByText( /This setting cannot be changed later/ )
		).toBeInTheDocument();
	} );

	it( 'saves the chosen site type through the core settings route and reloads', async () => {
		mockedApiFetch.mockResolvedValueOnce( {
			onelogs_site_type: 'governing-site',
		} );

		render( <SiteTypeSettings /> );

		fireEvent.change( screen.getByLabelText( 'Site Type' ), {
			target: { value: 'governing-site' },
		} );
		fireEvent.click( selectButton() );

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenCalledWith( {
				path: '/wp/v2/settings',
				method: 'POST',
				data: { onelogs_site_type: 'governing-site' },
			} );
		} );

		// jsdom can't reload, so the reload shows up as its "not implemented" error.
		await waitFor( () => {
			expect( console ).toHaveErrored();
		} );
		expect( screen.queryByText( 'Error setting site type.' ) ).toBeNull();
	} );

	it( 'shows an error notice when saving fails', async () => {
		mockedApiFetch.mockRejectedValueOnce( new Error( 'save failed' ) );

		render( <SiteTypeSettings /> );

		fireEvent.change( screen.getByLabelText( 'Site Type' ), {
			target: { value: 'brand-site' },
		} );
		fireEvent.click( selectButton() );

		// WordPress renders notices in both accessibility region and visible content.
		// The region keeps the previous test's message, so wait for the visible notice too.
		await waitFor( () => {
			expect(
				screen.getAllByText( 'Error setting site type.' )
			).toHaveLength( 2 );
		} );
	} );

	it( 'shows an error notice when the response has no site type', async () => {
		mockedApiFetch.mockResolvedValueOnce( {} );

		render( <SiteTypeSettings /> );

		fireEvent.change( screen.getByLabelText( 'Site Type' ), {
			target: { value: 'brand-site' },
		} );
		fireEvent.click( selectButton() );

		// WordPress renders notices in both accessibility region and visible content.
		// The region keeps the previous test's message, so wait for the visible notice too.
		await waitFor( () => {
			expect(
				screen.getAllByText( 'Error setting site type.' )
			).toHaveLength( 2 );
		} );
	} );
} );
