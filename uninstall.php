<?php
/**
 * This will be executed when the plugin is uninstalled.
 *
 * @package OneLogs
 */

declare( strict_types = 1 );

namespace OneLogs;

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Multisite loop for uninstalling from all sites.
 */
function multisite_uninstall(): void {
	if ( ! is_multisite() ) {
		uninstall();
		return;
	}

	$site_ids = get_sites(
		[
			'fields' => 'ids',
			'number' => 0,
		]
	) ?: [];

	foreach ( $site_ids as $site_id ) {
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog
		if ( ! switch_to_blog( (int) $site_id ) ) {
			continue;
		}

		uninstall();
		restore_current_blog();
	}
}

/**
 * The (site-specific) uninstall function.
 */
function uninstall(): void {
	delete_plugin_data();
}

/**
 * Deletes meta, options, transients, etc.
 */
function delete_plugin_data(): void {

	$options = [
		// Common site options.
		'onelogs_site_type',
		'onelogs_show_onboarding',

		// Governing site options.
		'onelogs_shared_sites',

		// Brand site options.
		'onelogs_parent_site_url',
		'onelogs_consumer_api_key',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

// Run the uninstaller.
multisite_uninstall();
