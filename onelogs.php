<?php
/**
 * Plugin Name:       OneLogs
 * Description:       Provides a unified activity log dashboard across OnePress connected sites, extending Stream to display logs from governing and brand sites in one place.
 * Author:            rtCamp
 * Plugin URI:        https://rtcamp.com
 * Author URI:        https://rtcamp.com
 * License:           GPL2+
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       onelogs
 * Version:           1.0.0-beta.1
 * Requires PHP:      8.0
 * Requires at least: 6.8
 * Tested up to:      6.8.2
 *
 * @package OneLogs
 */

declare ( strict_types=1 );

namespace OneLogs;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit();

/**
 * Define the plugin constants.
 */
function constants(): void {
	/**
	 * Version of the plugin.
	 */
	define( 'ONELOGS_VERSION', '0.1.0' );

	/**
	 * Root path to the plugin directory.
	 */
	define( 'ONELOGS_DIR', plugin_dir_path( __FILE__ ) );

	/**
	 * Root URL to the plugin directory.
	 */
	define( 'ONELOGS_URL', plugin_dir_url( __FILE__ ) );

	/**
	 * The plugin basename.
	 */
	define( 'ONELOGS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

constants();
require_once __DIR__ . '/inc/Autoloader.php';

// If autoloader failed, we cannot proceed.
if ( ! \OneLogs\Autoloader::autoload() ) {
	return;
}

// Load the plugin.
if ( class_exists( 'OneLogs\Main' ) ) {
	// This is wrapped in 'plugins_loaded' to ensure ALL plugins are loaded before we initialize.
	add_action(
		'plugins_loaded',
		static function (): void {
			Main::instance();
		}
	);
}

// Activation Hooks.
register_activation_hook(
	__FILE__,
	static function (): void {
		// @todo onboarding should be it's own class.
		// Show onboarding on first admin load after activation.
		if ( get_option( 'onelogs_show_onboarding' ) ) {
			return;
		}

		add_option( 'onelogs_show_onboarding', '1', '', false );
	}
);

// Deactivation Hooks.
register_deactivation_hook(
	__FILE__,
	static function (): void {
		// @todo Call the deactivation class.
	}
);
