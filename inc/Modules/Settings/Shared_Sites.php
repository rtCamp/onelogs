<?php
/**
 * This file is to create admin page.
 *
 * @package OneLogs
 */

namespace OneLogs\Modules\Settings;

use OneLogs\Contracts\Interfaces\Registrable;
use OneLogs\Utils;
use function OneLogs\Settings\add_action;
use function OneLogs\Settings\add_menu_page;
use function OneLogs\Settings\add_submenu_page;
use function OneLogs\Settings\current_user_can;
use function OneLogs\Settings\esc_html_e;
use function OneLogs\Settings\remove_submenu_page;
use function OneLogs\Settings\wp_die;
use function OneLogs\Settings\wp_get_current_user;

/**
 * Class Shared_Sites
 */
class Shared_Sites implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
	}

	/**
	 * Add admin menu under media
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		add_menu_page(
			__( 'OneLogs', 'onelogs' ),
			__( 'OneLogs', 'onelogs' ),
			'manage_options',
			'onelogs',
			'__return_null',
			'',
			2
		);

		// Add sub menu under forms inspector - this will rename the first submenu item.
		if ( Utils::is_governing_site() ) {
			add_submenu_page(
				'onelogs',
				__( 'Manage Users', 'onelogs' ),
				'<span class="onelogs-manage-user-page">' . __( 'Manage Users', 'onelogs' ) . '</span>',
				'manage_options',
				'onelogs',
				[ $this, 'render_onelogs_user_manager' ]
			);
		}

		// Add your other submenu page.
		add_submenu_page(
			'onelogs',
			__( 'Settings', 'onelogs' ),
			__( 'Settings', 'onelogs' ),
			'manage_options',
			'onelogs-settings',
			[ $this, 'render_onelogs_settings_page' ]
		);

		// Remove the duplicate top-level menu item.
		if ( ! Utils::is_brand_site() ) {
			return;
		}

		remove_submenu_page( 'onelogs', 'onelogs' );
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_onelogs_user_manager(): void {
		// Check if the user has permission to manage options.
		$current_user = wp_get_current_user();
		if ( ! current_user_can( 'manage_options' ) || ! Utils::is_governing_site() || ! in_array( 'network_admin', $current_user->roles, true ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'onelogs' ) );
		}
		?>
		<div class="wrap">
			<h1 class="onelogs-heading"><?php esc_html_e( 'Manage Users', 'onelogs' ); ?></h1>
			<div id="onelogs-manage-user"></div>
		</div>
		<?php
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_onelogs_settings_page(): void {

		// Check if the user has permission to manage options.
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'onelogs' ) );
		}

		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Settings', 'onelogs' ); ?></h1>
			<div id="onelogs-settings-page"></div>
		</div>
		<?php
	}
}
