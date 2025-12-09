<?php
/**
 * Admin class to handle all the admin functionalities related to logs.
 *
 * @package OneLogs\Modules\Post_Types;
 */

namespace OneLogs\Modules\Logs;

use OneLogs\Contracts\Interfaces\Registrable;
use OneLogs\Modules\Core\Assets;
use OneLogs\Modules\Settings\Admin as SettingsAdmin;
use OneLogs\Modules\Settings\Settings;

/**
 * Class Admin
 */
class Admin implements Registrable {
	/**
	 * The menu slug for the admin menu.
	 */
	private const MENU_SLUG = SettingsAdmin::MENU_SLUG;

	/**
	 * Asset handles
	 */
	public const LOGS_DASHBOARD_SCRIPT_HANDLE = Assets::LOGS_DASHBOARD_SCRIPT_HANDLE;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_logs_page' ], 20 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20, 1 );
	}

	/**
	 * Add a logs page.
	 *
	 * @return void
	 */
	public function add_logs_page(): void {
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
			[ $this, 'screen_callback' ],
			1
		);
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {

		$screen = get_current_screen();

		if ( ! $screen instanceof \WP_Screen ) {
			return;
		}

		if ( strpos( $hook, 'onelogs' ) === false && 'onelogs' !== $screen->id ) {
			return;
		}

		wp_localize_script(
			self::LOGS_DASHBOARD_SCRIPT_HANDLE,
			'OneLogsData',
			Assets::get_localized_data(),
		);

		wp_enqueue_script( self::LOGS_DASHBOARD_SCRIPT_HANDLE );

		wp_enqueue_style( self::LOGS_DASHBOARD_SCRIPT_HANDLE );
	}

	/**
	 * Logs page content callback.
	 */
	public function screen_callback(): void {
		?>
		<div class="wrap">
			<div id="onelogs-logs-dashboard" class="onelogs-logs-dashboard"></div>
		</div>
		<?php
	}
}
