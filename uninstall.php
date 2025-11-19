<?php
/**
 * This will be executed when the plugin is uninstalled.
 *
 * @package OneLogs
 */

declare( strict_types=1 );

namespace OneLogs;

// If uninstall not called from WordPress, exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Function to delete plugin options.
 *
 * @return void
 */
function delete_plugin_data(): void {
	$options_to_delete = [
		// Common site options.
		'onelogs_site_type',
		'onelogs_show_onboarding',

		// Governing site options.
		'onelogs_shared_sites',

		// Brand site options.
		'onelogs_governing_site_url',
		'onelogs_child_site_api_key',
	];

	foreach ( $options_to_delete as $option ) {
		delete_option( $option );
	}

	delete_transient( 'onelogs_site_type_transient' );
}

/**
 * Function to clean up options when the plugin is uninstalled.
 *
 * @return void
 */
function multisite_uninstall(): void {
	// if it's multisite, delete site options as well.
	if ( ! is_multisite() ) {
		delete_plugin_data();
		return;
	}

	// for each site delete options.
	$all_sites = get_sites( [ 'fields' => 'ids' ] );

	foreach ( $all_sites as $site_id ) {
		/** Safe usage: We only switch DB context to delete options & posts.
		 * No hooks, filters, or theme/plugin code required.
		 */
		// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.switch_to_blog_switch_to_blog
		if ( ! switch_to_blog( (int) $site_id ) ) {
			continue;
		}

		delete_plugin_data();

		restore_current_blog();
	}
}

/**
 * Uninstall the plugin and clean up options.
 */
multisite_uninstall();
