/**
 * PHP consts for JS usage.
 *
 * @package
 */

let settings = {};

if ( typeof window.OneLogsSettings !== 'undefined' ) {
	settings = window.OneLogsSettings;
} else if ( typeof window.patternSyncData !== 'undefined' ) {
	settings = window.patternSyncData;
} else if ( typeof window.TemplateLibraryData !== 'undefined' ) {
	settings = window.TemplateLibraryData;
} else if ( typeof window.OneLogsMultiSiteSettings !== 'undefined' ) {
	settings = window.OneLogsMultiSiteSettings;
}

const ONELOGS_REST_NAME = 'onelogs';
const ONELOGS_REST_VERSION = 'v1';

const API_NAMESPACE = settings?.restUrl ? settings.restUrl + `/${ ONELOGS_REST_NAME }/${ ONELOGS_REST_VERSION }` : '';
const NONCE = settings?.restNonce ? settings.restNonce : '';
const API_KEY = settings?.apiKey ? settings.apiKey : '';
const SETTINGS_LINK = settings?.settingsLink ? settings.settingsLink : '';
const PER_PAGE = 9;
const MULTISITES = settings?.multisites || [];
const IS_MULTISITE = settings?.isMultisite || false;
const IS_GOVERNING_SITE_SELECTED = settings?.isGoverningSiteSelected || false;
const CURRENT_SITE_ID = String( settings?.currentSiteId ) || '';

export {
	API_NAMESPACE,
	NONCE,
	API_KEY,
	SETTINGS_LINK,
	PER_PAGE,
	MULTISITES,
	IS_MULTISITE,
	IS_GOVERNING_SITE_SELECTED,
	CURRENT_SITE_ID,
};
