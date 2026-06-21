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
		restUrl: 'https://example.com/wp-json',
		nonce: 'nonce',
		apiKey: 'api-key',
		siteType: 'governing-site',
		siteName: 'Example Site',
		setupUrl: '/wp-admin/admin.php?page=onelogs-settings',
	},
	writable: true,
} );

Object.defineProperty( window, 'OneLogsOnboarding', {
	value: {
		nonce: 'onboarding-nonce',
		siteType: '',
		setupUrl: '',
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
