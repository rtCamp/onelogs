/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import {
	fetchActions,
	fetchContexts,
	fetchLogs,
	fetchSharedSites,
	fetchUsers,
} from '@/admin/logs/apiService';
import type { FilterOptions } from '@/admin/logs/types';

const mockedApiFetch = apiFetch as jest.MockedFunction< typeof apiFetch >;

const BRAND_URL = 'https://brand-one.example.com/';

/**
 * Gets the path and query of the nth apiFetch call.
 *
 * @param call The call index.
 */
const requestOf = ( call = 0 ) => {
	const options = mockedApiFetch.mock.calls[ call ]?.[ 0 ] as {
		path: string;
		method: string;
	};
	const url = new URL( options.path, 'https://governing.example.com' );

	return {
		method: options.method,
		pathname: url.pathname,
		query: Object.fromEntries( url.searchParams.entries() ),
	};
};

const baseFilters: FilterOptions = { page: 1, per_page: 20 };

describe( 'logs apiService', () => {
	beforeEach( () => {
		mockedApiFetch.mockReset();
	} );

	it( "fetches the governing site's own logs without a site_url", async () => {
		mockedApiFetch.mockResolvedValueOnce( {
			data: [ { ID: 1 } ],
			meta: { total: 1, total_pages: 1, errors: [] },
		} );

		const result = await fetchLogs( {
			...baseFilters,
			site_url: 'governing-site',
			date_from: '2026-11-01',
			site_id: 1,
		} );

		const { method, pathname, query } = requestOf();
		expect( method ).toBe( 'GET' );
		expect( pathname ).toBe( '/onelogs/v1/logs' );
		expect( query ).toEqual(
			expect.objectContaining( {
				page: '1',
				per_page: '20',
				current_site_logs: 'true',
				include_shared_sites: 'false',
				date_from: '2026-11-01 00:00:00',
				site_id: '1',
			} )
		);
		expect( query ).not.toHaveProperty( 'site_url' );
		expect( result ).toEqual( {
			logs: [ { ID: 1 } ],
			total: 1,
			pages: 1,
			errors: null,
		} );
	} );

	it( "asks the governing site for a brand site's logs by its URL", async () => {
		mockedApiFetch.mockResolvedValueOnce( {
			data: [ { ID: 7, is_remote: true } ],
			meta: { total: 41, total_pages: 3, errors: [] },
		} );

		const result = await fetchLogs( {
			...baseFilters,
			site_url: BRAND_URL,
			date_to: '2026-11-10',
			site_id: 1,
		} );

		const { pathname, query } = requestOf();
		expect( pathname ).toBe( '/onelogs/v1/logs' );
		expect( query ).toEqual(
			expect.objectContaining( {
				site_url: BRAND_URL,
				current_site_logs: 'false',
				include_shared_sites: 'true',
				date_to: '2026-11-10 23:59:59',
			} )
		);
		// The governing site's own site id means nothing to the brand site.
		expect( query ).not.toHaveProperty( 'site_id' );
		expect( result ).toEqual( {
			logs: [ { ID: 7, is_remote: true } ],
			total: 41,
			pages: 3,
			errors: [],
		} );
	} );

	it.each( [
		[ 'null data', { data: null, meta: { total: 0, total_pages: 0 } } ],
		[ 'missing data and meta', {} ],
		[ 'no body', null ],
	] )(
		'returns no logs instead of crashing for %s (QA TC-054)',
		async ( _label, response ) => {
			mockedApiFetch.mockResolvedValueOnce( response );

			const result = await fetchLogs( {
				...baseFilters,
				site_url: BRAND_URL,
			} );

			expect( result.logs ).toEqual( [] );
			expect( result.total ).toBe( 0 );
			expect( result.pages ).toBe( 1 );
		}
	);

	it( 'rejects with the REST error, so the dashboard can show its message', async () => {
		const error = {
			code: 'onelogs_brand_site_unreachable',
			message: 'Could not reach Brand One.',
		};
		mockedApiFetch.mockRejectedValueOnce( error );

		await expect(
			fetchLogs( { ...baseFilters, site_url: BRAND_URL } )
		).rejects.toBe( error );
	} );

	it( 'passes an encoded site_url to the filter routes for a brand site', async () => {
		mockedApiFetch
			.mockResolvedValueOnce( { data: [ 'posts' ] } )
			.mockResolvedValueOnce( { data: [ 'updated' ] } )
			.mockResolvedValueOnce( { data: [], meta: { total: 0 } } );

		const brandUrl = 'https://brand-one.example.com/sub?x=1&y=2';
		const filters = { ...baseFilters, site_url: brandUrl };

		await expect( fetchContexts( filters ) ).resolves.toEqual( [
			'posts',
		] );
		await expect( fetchActions( filters ) ).resolves.toEqual( [
			'updated',
		] );
		await fetchUsers( filters );

		expect( requestOf( 0 ) ).toEqual( {
			method: 'GET',
			pathname: '/onelogs/v1/logs/contexts',
			query: { site_url: brandUrl },
		} );
		expect( requestOf( 1 ).pathname ).toBe( '/onelogs/v1/logs/actions' );
		expect( requestOf( 1 ).query ).toEqual( { site_url: brandUrl } );
		expect( requestOf( 2 ).pathname ).toBe( '/onelogs/v1/logs/users' );
		expect( requestOf( 2 ).query ).toEqual( { site_url: brandUrl } );
	} );

	it( "doesn't pass a site_url to the filter routes for the governing site", async () => {
		mockedApiFetch.mockResolvedValueOnce( { data: [ 'posts' ] } );

		await fetchContexts( { ...baseFilters, site_url: 'governing-site' } );

		expect( requestOf().pathname ).toBe( '/onelogs/v1/logs/contexts' );
		expect( requestOf().query ).toEqual( {} );
	} );

	it( 'returns an empty list when the response lacks the requested key', async () => {
		mockedApiFetch.mockResolvedValueOnce( {} );

		await expect( fetchContexts( baseFilters ) ).resolves.toEqual( [] );
	} );

	it( 'gets the brand sites from the page, without a request', async () => {
		// The settings localized in setup.ts have no brand sites.
		await expect( fetchSharedSites() ).resolves.toEqual( [] );
		expect( mockedApiFetch ).not.toHaveBeenCalled();
	} );

	it( 'never adds apiFetch middleware or its own token header', async () => {
		mockedApiFetch.mockResolvedValue( {
			data: [],
			meta: { total: 0, total_pages: 1 },
		} );

		await fetchLogs( { ...baseFilters, site_url: BRAND_URL } );
		await fetchLogs( { ...baseFilters, site_url: 'governing-site' } );
		await fetchContexts( baseFilters );

		// WordPress sets up apiFetch's root and nonce, so registering middleware per request would stack it up.
		expect( apiFetch.use ).not.toHaveBeenCalled();
		for ( const [ options ] of mockedApiFetch.mock.calls ) {
			expect( options ).not.toHaveProperty( 'headers' );
		}
		expect( global.fetch ).not.toHaveBeenCalled();
	} );
} );
