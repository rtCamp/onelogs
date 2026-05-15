/**
 * External dependencies
 */
import {
	API_KEY,
	API_NAMESPACE,
	NONCE,
	SITE_TYPE,
	SITE_NAME,
} from '@/js/constants';
import { isValidUrl, formatDate } from '@/js/utils';

describe( 'utils', () => {
	it( 'validates well-formed urls', () => {
		expect( isValidUrl( 'https://example.com/path' ) ).toBe( true );
		expect( isValidUrl( 'not-a-url' ) ).toBe( false );
	} );

	it( 'formats date strings into readable format', () => {
		const formatted = formatDate( '2024-01-15T10:30:00Z' );
		expect( formatted ).toBeTruthy();
		expect( typeof formatted ).toBe( 'string' );
	} );

	it( 'reads wordpress settings constants from the global config', () => {
		expect( API_NAMESPACE ).toBe(
			'https://example.com/wp-json/onelogs/v1'
		);
		expect( API_KEY ).toBe( 'api-key' );
		expect( NONCE ).toBe( 'nonce' );
		expect( SITE_TYPE ).toBe( 'governing-site' );
		expect( SITE_NAME ).toBe( '' );
	} );
} );
