/// <reference types="@wordpress/jest-console" />

/**
 * External dependencies
 */
import '@testing-library/jest-dom';

const fetchMock = jest.fn<
	ReturnType< typeof fetch >,
	Parameters< typeof fetch >
>();

Object.defineProperty( global, 'fetch', {
	value: fetchMock,
	writable: true,
} );

Object.defineProperty( window, 'OneLogsSettings', {
	value: {
		apiKey: 'api-key',
		restNamespace: 'onelogs/v1',
		setupUrl: '/wp-admin/admin.php?page=onelogs-settings',
		sharedSites: [],
		siteType: 'governing-site',
		siteName: 'Example Site',
	},
	writable: true,
} );

Object.defineProperty( navigator, 'clipboard', {
	value: {
		writeText: jest.fn().mockResolvedValue( undefined ),
	},
	configurable: true,
} );

/**
 * Jest test setup for OneLogs.
 *
 * @package
 */

beforeEach( () => {
	jest.clearAllMocks();
	fetchMock.mockReset();
} );
