/**
 * External dependencies
 */
import { act, fireEvent, render, screen } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import type { OneLogsSettings } from '@/types/global';

// The constants are read when they're used, so each test can set its own brand sites.
jest.mock( '@/js/constants', () => ( {
	__esModule: true,
	REST_NAMESPACE: 'onelogs/v1',
	API_KEY: '',
	SITE_NAME: 'Governing Site Name',
	get SITE_TYPE() {
		return globalThis.window.OneLogsSettings?.siteType ?? '';
	},
	get SHARED_SITES() {
		return globalThis.window.OneLogsSettings?.sharedSites ?? [];
	},
} ) );

import LogsDashboard from '@/admin/logs/components/LogsDashboard';

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

const BRAND_URL = 'https://brand-one.example.com/';
const UNREACHABLE =
	'Could not reach Brand One. Check that the site is online and OneLogs is active there.';

const baseSettings = window.OneLogsSettings as OneLogsSettings;

const localLog = {
	ID: 1,
	created: '2026-11-10T10:00:00Z',
	summary: 'Local post updated',
	context: 'post',
	action: 'updated',
	ip: '127.0.0.1',
};

const remoteLog = {
	...localLog,
	ID: 2,
	summary: 'Brand post updated',
	site_name: 'Brand One',
	site_url: BRAND_URL,
	is_remote: true,
};

/**
 * Answers the dashboard's requests like the server, with the brand site's routes answering `brand`.
 *
 * @param brand    What a selected brand site's routes answer: its logs, or the error the governing site returns.
 * @param onlyLogs Whether only the logs route fails, while the filter routes answer.
 */
const mockServer = (
	brand: { logs: unknown[] } | { error: { code: string; message: string } },
	onlyLogs = false
) =>
	mockedApiFetch.mockImplementation( ( async ( {
		path = '',
	}: {
		path?: string;
	} ) => {
		const url = new URL( path, 'https://governing.example.com' );
		const isBrand = url.searchParams.has( 'site_url' );

		if (
			isBrand &&
			'error' in brand &&
			( ! onlyLogs || url.pathname === '/onelogs/v1/logs' )
		) {
			throw brand.error;
		}

		if ( url.pathname === '/onelogs/v1/logs' ) {
			const logs = isBrand && 'logs' in brand ? brand.logs : [ localLog ];

			return {
				status: 'success',
				data: logs,
				meta: { total: logs.length, total_pages: 1, errors: [] },
			};
		}

		if ( url.pathname === '/onelogs/v1/logs/connectors' ) {
			return [ 'posts' ];
		}

		return { status: 'success', data: [], meta: {} };
	} ) as unknown as typeof apiFetch );

const renderDashboard = async () => {
	// The dashboard loads its data in effects, so let those requests settle inside act().
	await act( async () => {
		render( <LogsDashboard /> );
	} );
};

const selectSite = async ( value: string ) => {
	await act( async () => {
		fireEvent.change( screen.getByLabelText( 'Site' ), {
			target: { value },
		} );
	} );
};

describe( 'LogsDashboard', () => {
	beforeEach( () => {
		mockedApiFetch.mockReset();
		window.OneLogsSettings = {
			...baseSettings,
			siteType: 'governing-site',
			sharedSites: [ { id: 'one', name: 'Brand One', url: BRAND_URL } ],
		};
	} );

	afterEach( () => {
		window.OneLogsSettings = baseSettings;
	} );

	it( "lists the governing site's own logs and its brand sites", async () => {
		mockServer( { logs: [ remoteLog ] } );

		await renderDashboard();

		expect( screen.getByText( 'Local post updated' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'option', { name: 'Brand One' } )
		).toHaveValue( BRAND_URL );
	} );

	it( "links a brand site's logs to the brand site's URL (QA TC-052)", async () => {
		mockServer( { logs: [ remoteLog ] } );

		await renderDashboard();
		await selectSite( BRAND_URL );

		expect( screen.getByText( 'Brand post updated' ) ).toBeInTheDocument();
		expect(
			screen.getByRole( 'link', { name: 'Brand One' } )
		).toHaveAttribute( 'href', BRAND_URL );
	} );

	it( "shows the server's message instead of a blank screen when a brand site can't be reached (QA TC-054, TC-020)", async () => {
		mockServer( {
			error: {
				code: 'onelogs_brand_site_unreachable',
				message: UNREACHABLE,
			},
		} );

		await renderDashboard();
		expect( screen.getByText( 'Local post updated' ) ).toBeInTheDocument();

		await selectSite( BRAND_URL );

		expect( screen.getByText( UNREACHABLE ) ).toBeInTheDocument();
		// The governing site's logs are cleared, rather than shown under the brand site's error.
		expect(
			screen.queryByText( 'Local post updated' )
		).not.toBeInTheDocument();
		expect(
			screen.getByText( 'No logs found matching the current filters.' )
		).toBeInTheDocument();
		// The rest of the dashboard still works.
		expect( screen.getByLabelText( 'Site' ) ).toHaveValue( BRAND_URL );
	} );

	it( 'shows the rejected-key message when a brand site key was regenerated', async () => {
		const message =
			'Brand One rejected the API key. If its key was regenerated, edit the site in OneLogs > Settings and paste the new key.';
		mockServer( { error: { code: 'onelogs_bad_key', message } } );

		await renderDashboard();
		await selectSite( BRAND_URL );

		expect( screen.getByText( message ) ).toBeInTheDocument();
	} );

	it( 'recovers when switching back to the governing site', async () => {
		mockServer( {
			error: {
				code: 'onelogs_brand_site_unreachable',
				message: UNREACHABLE,
			},
		} );

		await renderDashboard();
		await selectSite( BRAND_URL );
		expect( screen.getByText( UNREACHABLE ) ).toBeInTheDocument();

		await selectSite( 'governing-site' );

		expect( screen.queryByText( UNREACHABLE ) ).not.toBeInTheDocument();
		expect( screen.getByText( 'Local post updated' ) ).toBeInTheDocument();
	} );

	it( 'falls back to a generic message when the error has none', async () => {
		mockServer( { error: { code: 'unknown', message: '' } }, true );

		await renderDashboard();
		await selectSite( BRAND_URL );

		expect(
			screen.getByText( 'An error occurred while fetching logs' )
		).toBeInTheDocument();
	} );
} );
