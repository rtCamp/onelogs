<?php
/**
 * Plugin Name:         OneLogs
 * Description:         Provides a unified activity log dashboard across OnePress connected sites, extending Stream to display logs from governing and brand sites in one place.
 * Author:              rtCamp
 * Author URI:          https://rtcamp.com
 * Plugin URI:          https://github.com/rtCamp/OneLogs/
 * Update URI:          https://github.com/rtCamp/OneLogs/
 * License:             GPL2+
 * License URI:         https://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:         onelogs
 * Domain Path:         /languages
 * Version:             1.0.0-beta.1
 * Requires PHP:        8.0
 * Requires at least:   6.8
 * Tested up to:        6.8.2
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
	define( 'ONELOGS_VERSION', '1.0.0-beta.1' );

	/**
	 * Root path to the plugin directory.
	 */
	define( 'ONELOGS_DIR', plugin_dir_path( __FILE__ ) );

	/**
	 * Root URL to the plugin directory.
	 */
	define( 'ONELOGS_URL', plugin_dir_url( __FILE__ ) );

	/**
	 * Plugin basename.
	 */
	define( 'ONELOGS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
}

constants();

// If autoloader failed, we cannot proceed.
require_once __DIR__ . '/inc/Autoloader.php';
if ( ! \OneLogs\Autoloader::autoload() ) {
	return;
}

// Load the plugin.
if ( class_exists( 'OneLogs\Main' ) ) {
	add_action(
		'plugins_loaded',
		static function (): void {
			\OneLogs\Main::instance();

			//phpcs:ignore PluginCheck.CodeAnalysis.DiscouragedFunctions.load_plugin_textdomainFound -- @todo remove before submitting to .org.
			load_plugin_textdomain( 'onelogs', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/' );
		}
	);
}
