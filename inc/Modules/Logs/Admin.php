<?php

declare(strict_types = 1);

/**
 * Admin class to handle all the admin functionalities related to logs.
 *
 * @package OneLogs\Modules\Post_Types;
 */

namespace OneLogs\Modules\Logs;

use OneLogs\Contracts\Interfaces\Registrable;
use OneLogs\Modules\Core\Assets;
use OneLogs\Modules\Settings\Admin as Settings_Admin;
use OneLogs\Modules\Settings\Settings;

/**
 * Class Admin
 */
class Admin implements Registrable {
	/**
	 * The screen ID for the settings page.
	 *
	 * We use the settings menu slug, so it's the default screen.
	 */
	public const SCREEN_ID = Settings_Admin::MENU_SLUG;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_submenu' ], 20 ); // 20 priority to make sure settings page respect its position.
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20, 1 );
	}

	/**
	 * Register submenu pages.
	 */
	public function add_submenu(): void {

		// Only add plugin-specific submenu pages if sites have been connecting.
		if ( Settings::is_governing_site() && empty( Settings::get_shared_sites() ) ) {
			return;
		}

		add_submenu_page(
			Settings_Admin::MENU_SLUG,
			__( 'Logs', 'onelogs' ),
			__( 'Logs', 'onelogs' ),
			'manage_options',
			self::SCREEN_ID,
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
		if ( str_contains( $hook, 'onelogs' ) === false ) {
			return;
		}

		$current_screen = get_current_screen();

		if ( ! $current_screen instanceof \WP_Screen || str_contains( $current_screen->id, Settings_Admin::MENU_SLUG ) === false ) {
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
	 * Callback for the screen content.
	 */
	public function screen_callback(): void {
		?>
		<div class="wrap">
			<div id="onelogs-logs-dashboard" class="onelogs-logs-dashboard"></div>
		</div>
		<?php
	}
}
