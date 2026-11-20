/**
 * External dependencies
 */
import {
	act,
	fireEvent,
	render,
	screen,
	waitFor,
} from '@testing-library/react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import type {
	OneLogsSettings,
	OneLogsSharedSite,
	SiteType,
} from '@/types/global';

// The constants are read when the page renders, so each test can set its own site type and brand sites.
jest.mock( '@/js/constants', () => ( {
	__esModule: true,
	REST_NAMESPACE: 'onelogs/v1',
	API_KEY: 'api-key',
	SITE_NAME: 'Example Site',
	get SITE_TYPE() {
		return globalThis.window.OneLogsSettings?.siteType ?? '';
	},
	get SHARED_SITES() {
		return globalThis.window.OneLogsSettings?.sharedSites ?? [];
	},
} ) );

// Mock child components to isolate SettingsPage behavior
const mockSubmitResults: unknown[] = [];

jest.mock( '@/components/SiteTable', () => ( {
	__esModule: true,
	default: ( {
		sites,
		onEdit,
		onDelete,
		setFormData,
		setShowModal,
	}: {
		sites: Array< { name: string; url: string } >;
		onEdit: ( index: number ) => void;
		onDelete: ( index: number | null ) => void;
		setFormData: ( data: {
			name: string;
			url: string;
			api_key: string;
		} ) => void;
		setShowModal: ( show: boolean ) => void;
	} ) => (
		<div data-testid="site-table">
			<span data-testid="site-count">{ sites.length }</span>
			<button
				type="button"
				onClick={ () => {
					onEdit( 0 );
					setFormData( {
						name: 'Edited Brand Site',
						url: 'https://edited.example.com/',
						api_key: 'edited-key',
					} );
				} }
			>
				Edit Site
			</button>
			<button type="button" onClick={ () => setShowModal( true ) }>
				Open Modal
			</button>
			<button type="button" onClick={ () => onDelete( 0 ) }>
				Delete Site
			</button>
		</div>
	),
} ) );

jest.mock( '@/components/SiteModal', () => ( {
	__esModule: true,
	default: ( {
		onSubmit,
		onClose,
	}: {
		onSubmit: () => Promise< boolean | string >;
		onClose: () => void;
	} ) => (
		<div data-testid="site-modal">
			<button
				type="button"
				onClick={ () =>
					void onSubmit().then( ( result ) =>
						mockSubmitResults.push( result )
					)
				}
			>
				Submit Modal
			</button>
			<button type="button" onClick={ onClose }>
				Close Modal
			</button>
		</div>
	),
} ) );

jest.mock( '@/components/SiteSettings', () => ( {
	__esModule: true,
	default: () => <div data-testid="site-settings">Brand Site Settings</div>,
} ) );

import SettingsPage from '@/admin/settings/page';

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

const baseSettings = window.OneLogsSettings as OneLogsSettings;

const setSettings = (
	siteType: SiteType,
	sharedSites: OneLogsSharedSite[] = []
) => {
	window.OneLogsSettings = { ...baseSettings, siteType, sharedSites };
};

const brandSite: OneLogsSharedSite = {
	id: 'one',
	name: 'Brand Site',
	url: 'https://brand.example.com/',
};

const remainingSite: OneLogsSharedSite = {
	id: 'two',
	name: 'Remaining Site',
	url: 'https://remaining.example.com/',
};

const openAndSubmitModal = async () => {
	fireEvent.click(
		await screen.findByRole( 'button', { name: 'Open Modal' } )
	);
	// The save resolves in a microtask, so let its state updates finish inside act().
	await act( async () => {
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Submit Modal' } )
		);
	} );
};

describe( 'SettingsPage', () => {
	// A test that fails while on fake timers would leave them on for the next ones.
	afterEach( () => {
		jest.useRealTimers();
		window.OneLogsSettings = baseSettings;
	} );

	beforeEach( () => {
		mockedApiFetch.mockReset();
		setSettings( 'governing-site' );
		mockSubmitResults.length = 0;
	} );

	it( 'shows neither settings view until the site type is chosen in the setup modal', () => {
		setSettings( '' );

		render( <SettingsPage /> );

		expect( screen.queryByTestId( 'site-table' ) ).not.toBeInTheDocument();
		expect(
			screen.queryByTestId( 'site-settings' )
		).not.toBeInTheDocument();
	} );

	it( 'shows the brand site settings on brand sites', () => {
		setSettings( 'brand-site' );

		render( <SettingsPage /> );

		expect( screen.getByTestId( 'site-settings' ) ).toBeInTheDocument();
		expect( screen.queryByTestId( 'site-table' ) ).not.toBeInTheDocument();
	} );

	it( 'starts from the localized brand sites without fetching them', () => {
		setSettings( 'governing-site', [ brandSite ] );

		render( <SettingsPage /> );

		expect( screen.getByTestId( 'site-count' ) ).toHaveTextContent( '1' );
		expect( mockedApiFetch ).not.toHaveBeenCalled();
		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	it( 'saves site changes through apiFetch and shows success feedback', async () => {
		setSettings( 'governing-site', [ brandSite ] );
		mockedApiFetch.mockResolvedValueOnce( {
			shared_sites: [ brandSite, remainingSite ],
		} );

		render( <SettingsPage /> );
		await openAndSubmitModal();

		await waitFor( () => {
			expect( mockSubmitResults ).toEqual( [ true ] );
		} );
		expect( mockedApiFetch ).toHaveBeenCalledWith( {
			path: '/onelogs/v1/shared-sites',
			method: 'POST',
			data: {
				sites_data: [ brandSite, { name: '', url: '', api_key: '' } ],
			},
		} );
		expect( screen.getByTestId( 'site-count' ) ).toHaveTextContent( '2' );
		expect( screen.queryByTestId( 'site-modal' ) ).not.toBeInTheDocument();
		expect(
			screen.getAllByText( 'Brand Site saved successfully.' ).length
		).toBeGreaterThanOrEqual( 1 );
		expect( global.fetch ).not.toHaveBeenCalled();
	} );

	it( 'reloads after the first brand site is added, so the Logs menu appears (QA TC-028)', async () => {
		mockedApiFetch.mockResolvedValueOnce( {
			shared_sites: [ brandSite ],
		} );

		render( <SettingsPage /> );
		await openAndSubmitModal();

		await waitFor( () => {
			expect( mockSubmitResults ).toEqual( [ true ] );
		} );

		// jsdom can't reload, so the reload shows up as its "not implemented" error.
		expect( console ).toHaveErrored();
	} );

	it( 'keeps the modal open and returns the server message when saving fails', async () => {
		mockedApiFetch.mockRejectedValueOnce( {
			code: 'onelogs_bad_key',
			message: 'The brand site rejected this API key.',
		} );

		render( <SettingsPage /> );
		await openAndSubmitModal();

		await waitFor( () => {
			expect( mockSubmitResults ).toEqual( [
				'The brand site rejected this API key.',
			] );
		} );
		expect( screen.getByTestId( 'site-modal' ) ).toBeInTheDocument();
	} );

	it( 'returns false when saving fails without a message', async () => {
		mockedApiFetch.mockRejectedValueOnce( {} );

		render( <SettingsPage /> );
		await openAndSubmitModal();

		await waitFor( () => {
			expect( mockSubmitResults ).toEqual( [ false ] );
		} );
		expect( screen.getByTestId( 'site-modal' ) ).toBeInTheDocument();
	} );

	it( 'keeps the modal open when the response lacks shared sites', async () => {
		setSettings( 'governing-site', [ brandSite ] );
		mockedApiFetch.mockResolvedValueOnce( {} );

		render( <SettingsPage /> );
		await openAndSubmitModal();

		await waitFor( () => {
			expect( mockSubmitResults ).toEqual( [ false ] );
		} );
		expect( screen.getByTestId( 'site-modal' ) ).toBeInTheDocument();
	} );

	it( 'submits edit changes correctly', async () => {
		setSettings( 'governing-site', [ brandSite ] );
		mockedApiFetch.mockResolvedValueOnce( {
			shared_sites: [ { ...brandSite, name: 'Edited Brand Site' } ],
		} );

		render( <SettingsPage /> );
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Edit Site' } )
		);
		await openAndSubmitModal();

		await waitFor( () => {
			expect( mockedApiFetch ).toHaveBeenLastCalledWith(
				expect.objectContaining( {
					method: 'POST',
					data: {
						sites_data: [
							{
								name: 'Edited Brand Site',
								url: 'https://edited.example.com/',
								api_key: 'edited-key',
							},
						],
					},
				} )
			);
		} );
	} );

	it( 'closes the modal via close action', async () => {
		render( <SettingsPage /> );
		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Open Modal' } )
		);
		expect( screen.getByTestId( 'site-modal' ) ).toBeInTheDocument();

		fireEvent.click(
			screen.getByRole( 'button', { name: 'Close Modal' } )
		);

		expect( screen.queryByTestId( 'site-modal' ) ).not.toBeInTheDocument();
	} );

	it( 'removes a brand site through the server, which disconnects it', async () => {
		setSettings( 'governing-site', [ brandSite, remainingSite ] );
		mockedApiFetch.mockResolvedValueOnce( {
			governing_site_notified: true,
			message: 'Brand Site deleted successfully.',
			shared_sites: [ remainingSite ],
		} );

		render( <SettingsPage /> );

		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Delete Site' } )
		);

		await waitFor( () => {
			expect( screen.getByTestId( 'site-count' ) ).toHaveTextContent(
				'1'
			);
		} );
		expect( mockedApiFetch ).toHaveBeenLastCalledWith( {
			path: '/onelogs/v1/shared-sites/one',
			method: 'DELETE',
		} );
		expect( global.fetch ).not.toHaveBeenCalled();
		expect(
			( await screen.findAllByText( 'Brand Site deleted successfully.' ) )
				.length
		).toBeGreaterThanOrEqual( 1 );
		expect(
			screen.queryByText( /was removed, but/, {
				selector: '.components-notice__content',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'drops the site locally when the response has no list', async () => {
		setSettings( 'governing-site', [ brandSite, remainingSite ] );
		mockedApiFetch.mockResolvedValueOnce( {
			governing_site_notified: true,
		} );

		render( <SettingsPage /> );

		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Delete Site' } )
		);

		await waitFor( () => {
			expect( screen.getByTestId( 'site-count' ) ).toHaveTextContent(
				'1'
			);
		} );
		// Without a server message, the default one is shown.
		expect(
			( await screen.findAllByText( 'Brand Site deleted successfully.' ) )
				.length
		).toBeGreaterThanOrEqual( 1 );
	} );

	it( 'reloads after the last brand site is deleted, so the Logs menu disappears (QA TC-046)', async () => {
		setSettings( 'governing-site', [ brandSite ] );
		mockedApiFetch.mockResolvedValueOnce( {
			governing_site_notified: true,
			message: 'Brand Site deleted successfully.',
			shared_sites: [],
		} );

		render( <SettingsPage /> );

		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Delete Site' } )
		);

		await waitFor( () => {
			expect( screen.getByTestId( 'site-count' ) ).toHaveTextContent(
				'0'
			);
		} );

		// jsdom can't reload, so the reload shows up as its "not implemented" error.
		expect( console ).toHaveErrored();
	} );

	it( 'warns, and does not reload, when the deleted last brand site could not be notified', async () => {
		setSettings( 'governing-site', [ brandSite ] );
		mockedApiFetch.mockResolvedValueOnce( {
			governing_site_notified: false,
			message: 'Brand Site was removed, but it could not be notified.',
			shared_sites: [],
		} );

		render( <SettingsPage /> );

		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Delete Site' } )
		);

		const message = 'Brand Site was removed, but it could not be notified.';
		expect(
			await screen.findByText( message, {
				selector: '.components-notice__content',
			} )
		).toBeInTheDocument();
		expect( screen.getByTestId( 'site-count' ) ).toHaveTextContent( '0' );
		// No reload, so the "not implemented" navigation error never happened.
		expect( console ).not.toHaveErrored();

		// The admin can dismiss it once they have acted on it.
		fireEvent.click( screen.getByRole( 'button', { name: 'Close' } ) );
		expect(
			screen.queryByText( message, {
				selector: '.components-notice__content',
			} )
		).not.toBeInTheDocument();
	} );

	it( 'shows the server error and leaves the brand site alone when deleting fails', async () => {
		setSettings( 'governing-site', [ brandSite ] );
		mockedApiFetch.mockRejectedValueOnce( {
			code: 'onelogs_site_not_found',
			message: 'The brand site was not found.',
		} );

		render( <SettingsPage /> );

		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Delete Site' } )
		);

		expect(
			( await screen.findAllByText( 'The brand site was not found.' ) )
				.length
		).toBeGreaterThanOrEqual( 1 );
		expect( screen.getByTestId( 'site-count' ) ).toHaveTextContent( '1' );
	} );

	it( 'falls back to a generic message when deleting fails without one', async () => {
		setSettings( 'governing-site', [ brandSite ] );
		mockedApiFetch.mockRejectedValueOnce( {} );

		render( <SettingsPage /> );

		fireEvent.click(
			await screen.findByRole( 'button', { name: 'Delete Site' } )
		);

		expect(
			(
				await screen.findAllByText(
					'Failed to delete Brand site. Please try again.'
				)
			).length
		).toBeGreaterThanOrEqual( 1 );
		expect( screen.getByTestId( 'site-count' ) ).toHaveTextContent( '1' );
	} );

	it( 'gives each notice its own snackbar timer', async () => {
		jest.useFakeTimers();
		setSettings( 'governing-site', [ brandSite ] );
		mockedApiFetch.mockRejectedValueOnce( {} ).mockResolvedValueOnce( {
			shared_sites: [ brandSite, remainingSite ],
		} );

		render( <SettingsPage /> );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Delete Site' } )
		);
		await screen.findAllByText(
			'Failed to delete Brand site. Please try again.'
		);

		// The second notice arrives just before the first one's timer ends.
		act( () => {
			jest.advanceTimersByTime( 9000 );
		} );
		fireEvent.click( screen.getByRole( 'button', { name: 'Open Modal' } ) );
		fireEvent.click(
			screen.getByRole( 'button', { name: 'Submit Modal' } )
		);
		await screen.findAllByText( 'Brand Site saved successfully.' );

		act( () => {
			jest.advanceTimersByTime( 2000 );
		} );

		expect(
			document.querySelector( '.components-snackbar' )
		).toHaveTextContent( 'Brand Site saved successfully.' );
	} );
} );
