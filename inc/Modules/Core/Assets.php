<?php
/**
 * Registers plugin assets.
 *
 * @package OneLogs
 */

declare( strict_types = 1 );

namespace OneLogs\Modules\Core;

use OneLogs\Contracts\Interfaces\Registrable;
use OneLogs\Modules\Plugin_Configs\Constants;

/**
 * Class Assets
 */
final class Assets implements Registrable {
	/**
	 * Script handles.
	 */
	public const SETTINGS_SCRIPT_HANDLE         = 'onelogs-settings';
	public const PLUGIN_SETUP_SCRIPT_HANDLE     = 'onelogs-plugin-setup';
	public const ONBOARDING_STYLE_HANDLE        = 'onelogs-plugin-onboarding';
	public const SHARED_COMPONENTS_STYLE_HANDLE = 'onelogs-shared-components';

	/**
	 * The relative to the built assets directory.
	 * No preceding or trailing slashes.
	 */
	private const ASSETS_DIR = 'build';

	/**
	 * Plugin directory path.
	 *
	 * @var string
	 */
	private string $plugin_dir;

	/**
	 * Plugin URL.
	 *
	 * @var string
	 */
	private string $plugin_url;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->plugin_dir = (string) ONELOGS_DIR;
		$this->plugin_url = (string) ONELOGS_URL;
	}

	/**
	 * {@inheritDoc}
	 *
	 * Assets are only registered globally. Enqueuing is handled in specific that needs them.
	 */
	public function register_hooks(): void {
		// Assets are always registered. They can be enqueued later as needed.
		add_action( 'wp_enqueue_scripts', [ $this, 'register_assets' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'register_assets' ] );

		// Add defer attribute to certain plugin bundles to improve admin load performance.
		add_filter( 'script_loader_tag', [ $this, 'defer_scripts' ], 10, 2 );
		add_filter( 'admin_body_class', [ $this, 'add_body_class_for_modal' ] );
	}

	/**
	 * Create global variable onelogs_sites with site info.
	 *
	 * @param string $classes Existing body classes.
	 *
	 * @return string
	 */
	public function add_body_class_for_modal( $classes ): string {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'plugins' !== $current_screen->base ) {
			return $classes;
		}

		// get onelogs_site_type_transient transient to check if site type is set.
		$site_type_transient = get_transient( Constants::ONELOGS_SITE_TYPE_TRANSIENT );
			// If transient is false, it means site type is not set.
		if ( $site_type_transient ) {
			// If site type is already set, do not show the modal.
			return $classes;
		}

		// add onelogs-site-selection-modal class to body.
		$classes .= ' onelogs-site-selection-modal ';
		return $classes;
	}

	/**
	 * Register all scripts ands and styles.
	 */
	public function register_assets(): void {
		// JS.
		$this->register_script( self::PLUGIN_SETUP_SCRIPT_HANDLE, 'plugin' );
		$this->register_script( self::SETTINGS_SCRIPT_HANDLE, 'settings' );

		// Localize the setup script.
		wp_localize_script(
			self::PLUGIN_SETUP_SCRIPT_HANDLE,
			'OneLogsSettings',
			[
				'restUrl'   => esc_url( home_url( '/wp-json' ) ),
				'apiKey'    => get_option( 'onelogs_api_key', '' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'setupUrl'  => admin_url( 'admin.php?page=onelogs-settings' ),
			]
		);

		$this->register_style(
			self::ONBOARDING_STYLE_HANDLE,
			'logs-setup',
			[ 'wp-components' ]
		);
	}

	/**
	 * Add defer attribute to certain plugin bundle scripts to improve loading performance.
	 *
	 * @param string $tag    The script tag.
	 * @param string $handle The script handle.
	 * @return string Modified script tag.
	 */
	public function defer_scripts( string $tag, string $handle ): string {
		$defer_handles = [
			self::SETTINGS_SCRIPT_HANDLE,
		];

		// Bail if we dont need to defer.
		if ( ! in_array( $handle, $defer_handles, true ) || false !== strpos( $tag, ' defer' ) ) {
			return $tag;
		}

		return str_replace( ' src', ' defer src', $tag );
	}

	/**
	 * Register a script.
	 *
	 * @param string   $handle    Name of the script. Should be unique.
	 * @param string   $filename  Path of the script relative to js directory.
	 *                            excluding the .js extension.
	 * @param string[] $deps      Optional. An array of registered script handles this script depends on. If not set, the dependencies will be inherited from the asset file.
	 * @param ?string  $ver       Optional. String specifying script version number, if not set, the version will be inherited from the asset file.
	 * @param bool     $in_footer Optional. Whether to enqueue the script before </body> instead of in the <head>.
	 */
	private function register_script( string $handle, string $filename, array $deps = [], $ver = null, bool $in_footer = true ): bool {
		$asset_file = sprintf( '%s/js/%s.asset.php', trailingslashit( $this->plugin_dir ) . untrailingslashit( self::ASSETS_DIR ), $filename );

			// Bail if the asset file does not exist. Log error and optionally show admin notice.
		if ( ! file_exists( $asset_file ) ) {
			return false;
		}

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- The file is checked for existence above.
		$asset = require_once $asset_file;

		$version   = $ver ?? ( $asset['version'] ?? filemtime( $asset_file ) );
		$asset_src = sprintf( '%s/js/%s.js', trailingslashit( $this->plugin_url ) . untrailingslashit( self::ASSETS_DIR ), $filename );

		return wp_register_script(
			$handle,
			$asset_src,
			$deps ?: $asset['dependencies'],
			$version ?: false,
			$in_footer
		);
	}

	/**
	 * Register a CSS stylesheet
	 *
	 * @param string   $handle    Name of the stylesheet. Should be unique.
	 * @param string   $filename  Path of the stylesheet relative to the css directory,
	 *                            excluding the .css extension.
	 * @param string[] $deps      Optional. An array of registered stylesheet handles this stylesheet depends on. Default empty array.
	 * @param ?string  $ver       Optional. String specifying style version number, if not set, the version will be inherited from the asset file.
	 *
	 * @param string   $media     Optional. The media for which this stylesheet has been defined.
	 *                            Default 'all'. Accepts media types like 'all', 'print' and 'screen', or media queries like
	 *                            '(orientation: portrait)' and '(max-width: 640px)'.
	 */
	private function register_style( string $handle, string $filename, array $deps = [], $ver = null, string $media = 'all' ): bool {
		// CSS doesnt have a PHP assets file so we infer from the file itself.
		$asset_file = sprintf( '%s/css/%s.css', trailingslashit( $this->plugin_dir ) . untrailingslashit( self::ASSETS_DIR ), $filename );

		// Bail if the asset file does not exist.
		if ( ! file_exists( $asset_file ) ) {
			return false;
		}

		$version   = $ver ?? (string) filemtime( $asset_file );
		$asset_src = sprintf( '%s/css/%s.css', trailingslashit( $this->plugin_url ) . untrailingslashit( self::ASSETS_DIR ), $filename );

		// Register as a style.
		return wp_register_style(
			$handle,
			$asset_src,
			$deps,
			$version ?: false,
			$media
		);
	}
}
