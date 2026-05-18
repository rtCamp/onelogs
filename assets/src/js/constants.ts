/**
 * PHP consts for JS usage.
 *
 * @package
 */

let settings: {
	restUrl?: string;
	restNonce?: string;
	apiKey?: string;
	settingsLink?: string;
	siteType?: string;
	siteName?: string;
} = {};

if (typeof window.OneLogsSettings !== 'undefined') {
	settings = window.OneLogsSettings;
} else if (typeof window.OneLogsData !== 'undefined') {
	settings = window.OneLogsData;
}

const ONELOGS_REST_NAME = 'onelogs';
const ONELOGS_REST_VERSION = 'v1';

const API_NAMESPACE = settings?.restUrl
	? settings.restUrl + `/${ONELOGS_REST_NAME}/${ONELOGS_REST_VERSION}`
	: '';
const NONCE = settings?.restNonce ? settings.restNonce : '';
const API_KEY = settings?.apiKey ? settings.apiKey : '';
const SITE_TYPE = settings?.siteType ? settings.siteType : '';
const SITE_NAME = settings?.siteName ? settings.siteName : '';

export { API_NAMESPACE, NONCE, API_KEY, SITE_TYPE, SITE_NAME };
