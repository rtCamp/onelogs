/**
 * Internal dependencies
 */
import {
	API_KEY,
	REST_NAMESPACE,
	SHARED_SITES,
	SITE_NAME,
	SITE_TYPE,
} from '@/js/constants';
import {
	formatDate,
	getErrorMessage,
	isValidUrl,
	normalizeUrl,
} from '@/js/utils';

describe( 'utils', () => {
	it( 'validates well-formed urls', () => {
		expect( isValidUrl( 'https://example.com/path' ) ).toBe( true );
		expect( isValidUrl( 'not-a-url' ) ).toBe( false );
	} );

	it( 'only accepts http and https urls', () => {
		expect( isValidUrl( 'http://localhost:8890' ) ).toBe( true );
		expect( isValidUrl( 'HTTPS://example.com' ) ).toBe( true );
		expect( isValidUrl( 'ftp://example.com' ) ).toBe( false );
		expect( isValidUrl( 'javascript:alert(1)' ) ).toBe( false );
		expect( isValidUrl( 'mailto:a@b.c' ) ).toBe( false );
	} );

	it( 'normalizes urls so different spellings of a site match', () => {
		expect( normalizeUrl( '' ) ).toBe( '' );
		expect( normalizeUrl( '   ' ) ).toBe( '' );
		expect( normalizeUrl( 'https://brand.example.com' ) ).toBe(
			'https://brand.example.com/'
		);
		expect( normalizeUrl( ' HTTPS://Brand.Example.COM/ ' ) ).toBe(
			'https://brand.example.com/'
		);
		// Only the scheme and host are case-insensitive.
		expect( normalizeUrl( 'https://Example.com/Sub/Path' ) ).toBe(
			'https://example.com/Sub/Path/'
		);
	} );

	it( 'gets the message from rest errors and falls back otherwise', () => {
		expect(
			getErrorMessage(
				{ code: 'onelogs_bad_key', message: 'Key rejected.' },
				'Fallback'
			)
		).toBe( 'Key rejected.' );
		expect( getErrorMessage( new Error( 'Boom' ), 'Fallback' ) ).toBe(
			'Boom'
		);
		expect( getErrorMessage( { message: '' }, 'Fallback' ) ).toBe(
			'Fallback'
		);
		expect( getErrorMessage( 'oops', 'Fallback' ) ).toBe( 'Fallback' );
		expect( getErrorMessage( null, 'Fallback' ) ).toBe( 'Fallback' );
	} );

	it( 'formats date strings into readable format', () => {
		const formatted = formatDate( '2024-01-15T10:30:00Z' );
		expect( formatted ).toBeTruthy();
		expect( typeof formatted ).toBe( 'string' );
	} );
} );

// Lets the tests unset the optional globals, which `exactOptionalPropertyTypes` won't allow directly.
const globals = window as unknown as {
	OneLogsSettings: unknown;
	OneLogsData: unknown;
};

describe( 'constants', () => {
	it( 'reads the settings localized for the settings screen', () => {
		expect( API_KEY ).toBe( 'api-key' );
		expect( REST_NAMESPACE ).toBe( 'onelogs/v1' );
		expect( SITE_TYPE ).toBe( 'governing-site' );
		expect( SITE_NAME ).toBe( 'Example Site' );
		expect( SHARED_SITES ).toEqual( [] );
	} );

	it( 'falls back to the data localized for the logs dashboard', () => {
		const settings = window.OneLogsSettings;
		globals.OneLogsSettings = undefined;
		globals.OneLogsData = {
			apiKey: '',
			restNamespace: 'onelogs/v1',
			setupUrl: '',
			sharedSites: [
				{ id: 'one', name: 'Brand One', url: 'https://one.test/' },
			],
			siteName: 'Dashboard Site',
			siteType: 'brand-site',
		};

		try {
			jest.isolateModules( () => {
				const constants =
					require( '@/js/constants' ) as typeof import('@/js/constants');

				expect( constants.SITE_TYPE ).toBe( 'brand-site' );
				expect( constants.SITE_NAME ).toBe( 'Dashboard Site' );
				expect( constants.API_KEY ).toBe( '' );
				expect( constants.SHARED_SITES ).toHaveLength( 1 );
			} );
		} finally {
			globals.OneLogsSettings = settings;
			globals.OneLogsData = undefined;
		}
	} );

	it( 'uses defaults when nothing is localized', () => {
		const settings = window.OneLogsSettings;
		globals.OneLogsSettings = undefined;

		try {
			jest.isolateModules( () => {
				const constants =
					require( '@/js/constants' ) as typeof import('@/js/constants');

				expect( constants.REST_NAMESPACE ).toBe( 'onelogs/v1' );
				expect( constants.SITE_TYPE ).toBe( '' );
				expect( constants.API_KEY ).toBe( '' );
				expect( constants.SHARED_SITES ).toEqual( [] );
			} );
		} finally {
			globals.OneLogsSettings = settings;
		}
	} );
} );
