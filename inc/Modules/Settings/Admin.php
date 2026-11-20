<?php
/**
 * Registers the Admin menu and settings screen.
 *
 * @package OneLogs\Modules\Settings
 */

declare(strict_types = 1);

namespace OneLogs\Modules\Settings;

use OneLogs\Contracts\Interfaces\Registrable;
use OneLogs\Modules\Core\Assets;

/**
 * Class - Admin
 */
final class Admin implements Registrable {
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
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
		add_action( 'admin_notices', [ $this, 'render_notices' ] );

		add_filter( 'plugin_action_links_' . ONELOGS_PLUGIN_BASENAME, [ $this, 'add_action_links' ], 2 );
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
			'__return_null',
			self::SVG_LOGO_PATH,
			2
		);
	}

	/**
	 * Register the settings page.
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
	}

	/**
	 * Remove the default submenu added by WordPress.
	 */
	public function remove_default_submenu(): void {
		if ( Settings::is_governing_site() ) {
			return;
		}

		remove_submenu_page( self::MENU_SLUG, self::MENU_SLUG );
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
	 * Enqueue the settings screen's scripts, only on that screen.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( string $hook ): void {
		// The hook is `{parent}_page_onelogs-settings`, where the parent part comes from the (translatable) menu title.
		if ( ! str_ends_with( $hook, '_page_' . self::SCREEN_ID ) ) {
			return;
		}

		wp_localize_script( Assets::SETTINGS_SCRIPT_HANDLE, 'OneLogsSettings', Assets::get_localized_data() );
		wp_enqueue_script( Assets::SETTINGS_SCRIPT_HANDLE );
		wp_enqueue_style( Assets::ADMIN_STYLES_HANDLE );
	}

	/**
	 * Renders the setup admin notice.
	 */
	public function render_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// The settings page shows the site type picker itself.
		if ( $this->needs_setup() && in_array( get_current_screen()?->base, [ 'plugins', 'dashboard' ], true ) ) {
			wp_admin_notice(
				sprintf(
					/* translators: %s: URL of the OneLogs settings page. */
					__( 'OneLogs needs to be set up. <a href="%s">Choose whether this is a governing or a brand site</a> to get started.', 'onelogs' ),
					esc_url( admin_url( sprintf( 'admin.php?page=%s', self::SCREEN_ID ) ) )
				),
				[
					'type'        => 'info',
					'dismissible' => true,
				]
			);
		}
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
			_doing_it_wrong( __METHOD__, esc_html__( 'Expected an array.', 'onelogs' ), '1.0.0' );

			$links = [];
		}

		$links[] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( sprintf( 'admin.php?page=%s', self::SCREEN_ID ) ) ),
			__( 'Settings', 'onelogs' )
		);

		return $links;
	}

	/**
	 * Whether the site still has to choose its type.
	 */
	private function needs_setup(): bool {
		return empty( Settings::get_site_type() );
	}
}
