<?php
/**
 * This will be executed when the plugin is uninstalled.
 *
 * @package OneLogs
 */

declare( strict_types = 1 );

namespace OneLogs;

// If uninstall not called from WordPress, exit.
defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

const PLUGIN_PREFIX = 'onelogs_';

/**
 * Multisite loop for uninstalling from all sites.
 */
function run_uninstaller(): void {
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
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog -- The state doesn't matter during uninstall.
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
		PLUGIN_PREFIX . 'site_type',
		PLUGIN_PREFIX . 'show_onboarding',

		// Governing site options.
		PLUGIN_PREFIX . 'shared_sites',

		// Brand site options.
		PLUGIN_PREFIX . 'parent_site_url',
		PLUGIN_PREFIX . 'consumer_api_key',
	];

	foreach ( $options as $option ) {
		delete_option( $option );
	}
}

// Run the uninstaller.
run_uninstaller();
