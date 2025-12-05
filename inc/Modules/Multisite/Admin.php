<?php
/**
 * This file is to handle OneLogs Multisite related functionality.
 *
 * @package OneLogs
 */

namespace OneLogs\Modules\Multisite;

use OneLogs\Contracts\Interfaces\Registrable;
use OneLogs\Modules\Core\Assets;
use OneLogs\Modules\Multisite\Settings as MU_Settings;

/**
 * Class Admin
 */
class Admin implements Registrable {

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		// check if current site setup is multisite or not.
		if ( ! is_multisite() ) {
			return;
		}

		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ], 20, 1 );

		// add governing site selection modal on network admin plugins page.
		add_action( 'admin_footer', [ $this, 'render_governing_site_modal' ] );

		// add admin_body_class class of onelogs-multisite-selection-modal on network admin plugins page.
		add_filter( 'admin_body_class', [ $this, 'add_admin_body_class' ] );
	}

	/**
	 * Enqueue admin scripts.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		$current_screen = get_current_screen();

		if ( ! $current_screen instanceof \WP_Screen || 'plugins-network' !== $current_screen->id || MU_Settings::is_governing_site_selected() ) {
			return;
		}

		wp_localize_script(
			Assets::MULTISITE_SETUP_SCRIPT_HANDLE,
			'OneLogsMultiSiteSettings',
			array_merge(
				Assets::get_localized_data(),
				[
					'multisites' => MU_Settings::get_all_multisites_info(),
				]
			)
		);

		wp_enqueue_script( Assets::MULTISITE_SETUP_SCRIPT_HANDLE );

		// @todo Move other scripts from Assets to here.
	}

	/**
	 * Render governing site selection modal.
	 *
	 * @return void
	 */
	public function render_governing_site_modal(): void {

		if ( ! is_network_admin() ) {
			return;
		}

		$current_screen = get_current_screen();

		if ( ! $current_screen instanceof \WP_Screen || 'plugins-network' !== $current_screen->id ) {
			return;
		}

		if ( MU_Settings::is_governing_site_selected() ) {
			return;
		}

		?>
		<div class="wrap">
			<div id="onelogs-multisite-selection-modal" class="onelogs-modal"></div>
		</div>
		<?php
	}

	/**
	 * Add admin body class for governing site selection modal.
	 *
	 * @param string $classes Existing admin body classes.
	 * @return string Modified admin body classes.
	 */
	public function add_admin_body_class( string $classes ): string {

		if ( MU_Settings::is_governing_site_selected() ) {
			return $classes;
		}

		$current_screen = get_current_screen();

		if ( is_network_admin() && $current_screen instanceof \WP_Screen && 'plugins-network' === $current_screen->id ) {
			$classes .= ' onelogs-multisite-selection-modal ';
		}
		return $classes;
	}
}
