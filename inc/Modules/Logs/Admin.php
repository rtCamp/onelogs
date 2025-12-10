<?php
/**
 * Admin class to handle all the admin functionalities related to logs.
 *
 * @package OneLogs\Modules\Post_Types;
 */

namespace OneLogs\Modules\Logs;

use OneLogs\Contracts\Interfaces\Registrable;
use OneLogs\Modules\Core\Assets;
use OneLogs\Modules\Settings\Settings;

/**
 * Class Admin
 */
class Admin implements Registrable {
	/**
	 * The menu slug for the admin menu.
	 *
	 * @todo replace with a cross-plugin menu.
	 */
	public const MENU_SLUG = 'onelogs';

	/**
	 * The screen ID for the settings page.
	 */
	public const SCREEN_ID = self::MENU_SLUG . '-settings';

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
		add_action( 'admin_menu', [ $this, 'add_submenu' ], 20 ); // 20 priority to make sure settings page respect its position.
		add_action( 'admin_menu', [ $this, 'remove_default_submenu' ], 999 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20, 1 );

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20, 1 );
	}

	/**
	 * Add settings page.
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'OneLogs', 'onelogs' ),
			__( 'OneLogs', 'onelogs' ),
			'manage_options',
			self::MENU_SLUG,
			'__return_null',
			self::SVG_LOGO_PATH,
			2
		);
	}

	/**
	 * Register submenu pages.
	 */
	public function add_submenu(): void {

		// Add the settings submenu page.
		add_submenu_page(
			self::MENU_SLUG,
			__( 'Settings', 'onelogs' ),
			__( 'Settings', 'onelogs' ),
			'manage_options',
			self::SCREEN_ID,
			[ $this, 'screen_callback' ],
			999
		);

		// Only add plugin-specific submenu pages if sites have been connecting.
		if ( Settings::is_governing_site() && empty( Settings::get_shared_sites() ) ) {
			return;
		}

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Logs', 'onelogs' ),
			__( 'Logs', 'onelogs' ),
			'manage_options',
			self::MENU_SLUG,
			[ $this, 'logs_screen_callback' ],
			1
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
		$current_screen = get_current_screen();

		if ( ! $current_screen instanceof \WP_Screen ) {
			return;
		}

		if ( ( 'plugins.php' === $hook || str_contains( $hook, 'plugins' ) || str_contains( $hook, 'onelogs' ) ) ) {
			// Enqueue the onboarding modal.
			$this->enqueue_onboarding_scripts();
		}

		if ( strpos( $hook, 'onelogs-settings' ) !== false ) {
			$this->enqueue_settings_scripts();
		}

		if ( strpos( $hook, 'onelogs' ) === false && 'onelogs' !== $current_screen->id ) {
			return;
		}

		wp_localize_script(
			Assets::LOGS_DASHBOARD_SCRIPT_HANDLE,
			'OneLogsData',
			Assets::get_localized_data(),
		);

		wp_enqueue_script( Assets::LOGS_DASHBOARD_SCRIPT_HANDLE );

		wp_enqueue_style( Assets::LOGS_DASHBOARD_SCRIPT_HANDLE );
	}

	/**
	 * Admin page content callback.
	 */
	public function screen_callback(): void {
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'onelogs' ); ?></h1>
			<div id="onelogs-settings-page"></div>
		</div>
		<?php
	}

	/**
	 * Logs page content callback.
	 */
	public function logs_screen_callback(): void {
		?>
		<div class="wrap">
			<div id="onelogs-logs-dashboard" class="onelogs-logs-dashboard"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue the scripts and styles for the settings screen.
	 */
	public function enqueue_settings_scripts(): void {
		wp_localize_script(
			Assets::SETTINGS_SCRIPT_HANDLE,
			'OneLogsSettings',
			array_merge(
				Assets::get_localized_data()
			)
		);

		wp_enqueue_script( Assets::SETTINGS_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::SETTINGS_SCRIPT_HANDLE );

		// only load media uploader in governing site settings page.
		if ( ! Settings::is_governing_site() ) {
			return;
		}

		wp_enqueue_media();
	}

	/**
	 * Enqueue scripts and styles for the onboarding modal.
	 */
	private function enqueue_onboarding_scripts(): void {
		// Bail if the site type is already set.
		if ( ! empty( Settings::get_site_type() ) ) {
			return;
		}

		wp_localize_script(
			Assets::ONBOARDING_SCRIPT_HANDLE,
			'OneLogsSettings',
			array_merge(
				[
					'site_type' => Settings::get_site_type(),
				],
				Assets::get_localized_data()
			)
		);

		wp_enqueue_script( Assets::ONBOARDING_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::ONBOARDING_SCRIPT_HANDLE );
	}
}
