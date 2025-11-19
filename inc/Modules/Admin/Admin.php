<?php
/**
 * Registers the Admin menu and settings screen.
 *
 * @package OneLogs
 */

declare( strict_types=1 );

namespace OneLogs\Modules\Admin;

use OneLogs\Contracts\Interfaces\Registrable;
use OneLogs\Modules\Core\Assets;
use OneLogs\Modules\Core\Settings;
use OneLogs\Modules\Plugin_Configs\Constants;
use OneLogs\Rest\Abstract_REST_Controller;
use OneLogs\Utils;
use WP_Screen;

/**
 * Class = Admin
 */
final class Admin implements Registrable {
	/**
	 * The menu slug for the admin menu.
	 *
	 * @todo replace with a cross-plugin menu.
	 */
	public const MENU_SLUG = 'onelogs-main';

	/**
	 * The screen ID for the settings page.
	 */
	public const SETTINGS_SCREEN_ID = 'onelogs-settings';

	/**
	 * The screen ID for the logs page.
	 */
	public const LOGS_SCREEN_ID = 'onelogs';

	/**
	 * Path to the SVG logo for the menu.
	 *
	 * @todo Replace with actual logo.
	 * @var string
	 */
	private const SVG_LOGO_PATH = '';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
		add_action( 'admin_menu', [ $this, 'remove_default_submenu' ], 999 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20, 1 );
		add_action( 'admin_footer', [ $this, 'inject_site_selection_modal' ] );

		add_filter( 'plugin_action_links_' . ONELOGS_PLUGIN_BASENAME, [ $this, 'add_action_links' ], 2 );
		add_filter( 'admin_body_class', [ $this, 'add_body_classes' ] );
	}

	/**
	 * Add admin menu.
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'OneLogs', 'onelogs' ),
			__( 'OneLogs', 'onelogs' ),
			'manage_options',
			self::MENU_SLUG,
			// Redirect to settings page.
			static function () {
				wp_safe_redirect( admin_url( 'admin.php?page=' . self::SETTINGS_SCREEN_ID ) );
				exit;
			},
			self::SVG_LOGO_PATH,
			3
		);

		$shared_sites = get_option( Constants::ONELOGS_SHARED_SITES, [] );

		$show_logs_menu = ! Utils::is_governing_site() || ( is_array( $shared_sites ) && count( $shared_sites ) > 0 );

		// We only add the Logs submenu if there are shared sites configured.
		if ( $show_logs_menu ) {
			add_submenu_page(
				self::MENU_SLUG,
				__( 'Logs', 'onelogs' ),
				__( 'Logs', 'onelogs' ),
				'manage_options',
				self::LOGS_SCREEN_ID,
				[ $this, 'screen_callback' ]
			);
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'onelogs' ),
			__( 'Settings', 'onelogs' ),
			'manage_options',
			self::SETTINGS_SCREEN_ID,
			[ $this, 'screen_callback' ]
		);
	}

	/**
	 * Remove the default submenu added by WordPress.
	 */
	public function remove_default_submenu(): void {
		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		// Always load styles for OneLogs admin pages.
		$this->enqueue_logs_styles();

		if ( str_contains( $hook, 'onelogs' ) ) {
			// Enqueue different scripts based on the submenu page.
			if ( str_contains( $hook, self::SETTINGS_SCREEN_ID ) ) {
				$this->enqueue_settings_scripts();
			} else {
				$this->enqueue_logs_scripts();
			}
		} elseif ( 'plugins.php' === $hook || str_contains( $hook, 'plugins' ) ) {
			// Enqueue plugin modal script on the Plugins admin screen.
			$this->enqueue_plugin_scripts();
		}
	}

	/**
	 * Inject site selection modal into the admin footer.
	 */
	public function inject_site_selection_modal(): void {
		$current_screen = get_current_screen();
		if ( ! $current_screen || 'plugins' !== $current_screen->base ) {
			return;
		}

		// Bail if the site type is already set.
		if ( get_option( Settings::OPTION_SITE_TYPE ) ) {
			return;
		}

		?>
		<div class="wrap">
			<div id="onelogs-site-selection-modal" class="onelogs-modal"></div>
		</div>
		<?php
	}

	/**
	 * Add action links to the settings on the plugins page.
	 *
	 * @param string[] $links Existing links.
	 *
	 * @return string[]
	 */
	public function add_action_links( $links ): array {
		// Defense against other plugins.
		if ( ! is_array( $links ) ) {
			_doing_it_wrong( __METHOD__, esc_html__( 'Expected an array.', 'onelogs' ), 'n.e.x.t' );

			$links = [];
		}

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( sprintf( 'admin.php?page=%s', self::SETTINGS_SCREEN_ID ) ) ),
			__( 'Settings', 'onelogs' )
		);

		return $links;
	}

	/**
	 * Admin page content callback.
	 */
	public function screen_callback(): void {
		// Get the current page from the query string.
		$current_page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';

		// Verify nonce for form processing.
		if ( isset( $_POST['_wpnonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'onelogs_settings' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'onelogs' ) );
		}

		if ( self::LOGS_SCREEN_ID === $current_page ) {
			?>
			<div class="wrap">
				<div id="onelogs-logs-dashboard" class="onelogs-logs-dashboard"></div>
			</div>
			<?php
		} elseif ( self::SETTINGS_SCREEN_ID === $current_page ) {
			?>
			<div class="wrap">
				<h1><?php esc_html_e( 'OneLogs Settings', 'onelogs' ); ?></h1>
				<div id="onelogs-settings-page" class="onelogs-settings-page"></div>
			</div>
			<?php
		}
	}

	/**
	 * Add body classes for the admin area.
	 *
	 * @param string $classes Existing body classes.
	 */
	public function add_body_classes( $classes ): string {
		$current_screen = get_current_screen();

		if ( ! $current_screen ) {
			return $classes;
		}

		// Cast to string in case it's null.
		$classes = $this->add_body_class_for_modal( (string) $classes, $current_screen );

		return $classes;
	}

	/**
	 * Enqueue scripts and styles for the plugin settings page.
	 */
	private function enqueue_settings_scripts(): void {
		wp_localize_script(
			Assets::SETTINGS_SCRIPT_HANDLE,
			'OneLogsSettings',
			[
				'restUrl'   => esc_url( home_url( '/wp-json' ) ),
				'apiKey'    => get_option( 'onelogs_api_key', 'default_api_key' ),
				'restNonce' => wp_create_nonce( 'wp_rest' ),
				'setupUrl'  => admin_url( 'admin.php?page=' . self::SETTINGS_SCREEN_ID ),
			]
		);

		wp_enqueue_script( Assets::SETTINGS_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::SHARED_COMPONENTS_STYLE_HANDLE );
		wp_enqueue_style( Assets::SETTINGS_SCRIPT_HANDLE );
	}

	/**
	 * Enqueue scripts and styles for the logs dashboard page.
	 */
	private function enqueue_logs_scripts(): void {
		// Register the logs dashboard script handle.
		$logs_script_handle = 'onelogs-logs-dashboard';

		// Register the logs dashboard script.
		$asset_file = trailingslashit( ONELOGS_DIR ) . 'build/js/logs-dashboard.asset.php';

		if ( file_exists( $asset_file ) ) {
			$asset      = require $asset_file; // phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- Other areas of OnePress modules use this pattern.
			$version    = $asset['version'] ?? filemtime( $asset_file );
			$script_src = sprintf( '%s/build/js/%s.js', trailingslashit( ONELOGS_URL ), 'logs-dashboard' );

			wp_register_script(
				$logs_script_handle,
				$script_src,
				$asset['dependencies'] ?? [],
				$version,
				true
			);
		} else {
			// Fallback if asset file doesn't exist.
			wp_register_script(
				$logs_script_handle,
				sprintf( '%s/build/js/%s.js', trailingslashit( ONELOGS_URL ), 'logs-dashboard' ),
				[ 'wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch' ],
				ONELOGS_VERSION,
				true
			);
		}

		// Localize script with necessary data.
		wp_localize_script(
			$logs_script_handle,
			'OneLogsData',
			[
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'rest_url'       => untrailingslashit( rest_url() ),
				'apiKey'         => get_option( Constants::ONELOGS_API_KEY, 'default_api_key' ),
				'rest_namespace' => 'onelogs/v1',
			]
		);

		wp_enqueue_script( $logs_script_handle );
	}

	/**
	 * Enqueue styles for the logs dashboard.
	 *
	 * @return void
	 */
	private function enqueue_logs_styles(): void {
		// Register the logs dashboard style.
		$style_src     = sprintf( '%s/build/css/%s.css', trailingslashit( ONELOGS_URL ), 'logs-dashboard' );
		$style_version = filemtime( sprintf( '%s/build/css/%s.css', trailingslashit( ONELOGS_DIR ), 'logs-dashboard' ) ) ?: ONELOGS_VERSION;

		wp_register_style(
			'onelogs-logs-dashboard-style',
			$style_src,
			[ 'wp-components' ],
			$style_version
		);

		wp_enqueue_style( 'onelogs-logs-dashboard-style' );
	}

	/**
	 * Enqueue scripts and styles for the plugin onboarding screen.
	 */
	private function enqueue_plugin_scripts(): void {
		wp_localize_script(
			Assets::PLUGIN_SETUP_SCRIPT_HANDLE,
			'OneLogsPluginGlobal',
			[
				// This script is only used in admin, so we can rely on wp-rest for nonce validation.
				'nonce'          => wp_create_nonce( 'wp_rest' ),
				'rest_url'       => untrailingslashit( rest_url() ),
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'setup_url'      => admin_url( sprintf( 'admin.php?page=%s', self::SETTINGS_SCREEN_ID ) ),
				'rest_namespace' => Abstract_REST_Controller::NAMESPACE,
			]
		);

		wp_enqueue_script( Assets::PLUGIN_SETUP_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::ONBOARDING_STYLE_HANDLE );
	}

	/**
	 * Add body class if the modal is going to be shown.
	 *
	 * @param string     $classes Existing body classes.
	 * @param \WP_Screen $current_screen Current screen object.
	 */
	private function add_body_class_for_modal( string $classes, WP_Screen $current_screen ): string {
		if ( 'plugins' !== $current_screen->base ) {
			return $classes;
		}

		// Bail if the site type is already set.
		if ( get_option( Settings::OPTION_SITE_TYPE ) ) {
			return $classes;
		}

		// Add onelogs-site-selection-modal class to body.
		$classes .= ' onelogs-site-selection-modal ';

		return $classes;
	}
}
