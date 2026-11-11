/**
 * Global type declarations for OneLogs.
 *
 * These types describe the window globals injected by WordPress PHP code.
 */

export type SiteType = 'governing-site' | 'brand-site' | '';

/**
 * A brand site as the browser sees it. Its API key never leaves the server.
 */
export interface OneLogsSharedSite {
	id: string;
	name: string;
	url: string;
}

export interface OneLogsSettings {
	// Only set on brand sites, which use a key of their own.
	apiKey: string;
	restNamespace: string;
	setupUrl: string;
	sharedSites: OneLogsSharedSite[];
	siteName: string;
	siteType: SiteType | null;
}

declare global {
	interface Window {
		// Localized for the settings screen.
		OneLogsSettings?: OneLogsSettings;
		// Localized for the logs dashboard.
		OneLogsData?: OneLogsSettings;
	}
}
