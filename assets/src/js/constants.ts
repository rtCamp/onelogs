/**
 * PHP consts for JS usage.
 *
 * @package
 */

/**
 * Internal dependencies
 */
import type {
	OneLogsSettings,
	OneLogsSharedSite,
	SiteType,
} from '@/types/global';

// The settings screen and the logs dashboard localize the same data under different names.
const settings: Partial< OneLogsSettings > =
	window.OneLogsSettings ?? window.OneLogsData ?? {};

/**
 * REST namespace used by the OneLogs plugin, e.g. `onelogs/v1`.
 */
const REST_NAMESPACE = settings.restNamespace || 'onelogs/v1';

/**
 * This brand site's own API key. Empty on governing sites.
 */
const API_KEY = settings.apiKey ?? '';

const SITE_TYPE: SiteType = settings.siteType ?? '';
const SITE_NAME = settings.siteName ?? '';

/**
 * The governing site's brand sites, without their API keys.
 */
const SHARED_SITES: OneLogsSharedSite[] = settings.sharedSites ?? [];

export { REST_NAMESPACE, API_KEY, SITE_TYPE, SITE_NAME, SHARED_SITES };
