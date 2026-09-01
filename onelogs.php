<?php
/**
 * OneLogs
 *
 * @package           OneLogs
 * @author            rtCamp
 * @copyright         2025 rtCamp
 * @license           GPL-2.0-or-later
 *
 * Plugin Name:       OneLogs
 * Plugin URI:        https://github.com/rtCamp/onelogs
 * Description:       Provides a unified activity log dashboard across OnePress connected sites, extending Stream to display logs from governing and brand sites in one place.
 * Author:            rtCamp
 * Author URI:        https://rtcamp.com
 * Update URI:        https://github.com/rtCamp/onelogs
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       onelogs
 * Domain Path:       /languages
 * x-release-please-start-version
 * Version:           1.0.3
 * x-release-please-end
 * Requires PHP:      8.2
 * Requires at least: 6.8
 * Tested up to:      6.9
 */

declare( strict_types = 1 );

namespace OneLogs;

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Define the plugin constants.
 */
function constants(): void {
	/**
	 * File path to the plugin's main file.
	 */
	define( 'ONELOGS_FILE', __FILE__ );

	/**
	 * Version of the plugin.
	 */
	define( 'ONELOGS_VERSION', '1.0.3' ); // x-release-please-version.

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

// If autoloader fails, we cannot proceed.
require_once __DIR__ . '/inc/Autoloader.php';
if ( ! \OneLogs\Autoloader::autoload() ) {
	return;
}

// Load the plugin.
if ( class_exists( '\OneLogs\Main' ) ) {
	\OneLogs\Main::instance();
}
