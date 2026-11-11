<?php
/**
 * Admin settings screen unit tests.
 *
 * @package OneLogs\Tests\Integration\Modules\Settings
 */

declare(strict_types = 1);

namespace OneLogs\Tests\Integration\Modules\Settings;

use OneLogs\Modules\Core\Assets;
use OneLogs\Modules\Settings\Admin;
use OneLogs\Modules\Settings\Settings;
use OneLogs\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the settings admin screen.
 */
#[CoversClass( \OneLogs\Modules\Settings\Admin::class )]
final class AdminTest extends TestCase {
	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		set_current_screen( 'dashboard' );

		delete_option( Settings::OPTION_SITE_TYPE );
		delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		delete_option( Settings::OPTION_SITE_TYPE );
		delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );

		$this->remove_menu_entries();

		parent::tearDown();
	}

	/**
	 * Ensures the class can be instantiated and hook methods can be called without error.
	 */
	public function test_class_instantiation(): void {
		$admin = new Admin();

		$admin->register_hooks();
		$admin->enqueue_scripts( Admin::SCREEN_ID );

		// If we made it this far with no errors, we are good.
		$this->assertTrue( true );
	}

	/**
	 * Ensures the top-level menu page is registered.
	 */
	public function test_add_admin_menu_registers_menu_page(): void {
		( new Admin() )->add_admin_menu();

		$this->assertTrue( $this->menu_contains_slug( Admin::MENU_SLUG ) );
	}

	/**
	 * Ensures the settings submenu page is registered.
	 */
	public function test_add_submenu_registers_submenu_page(): void {
		$admin = new Admin();

		$admin->add_admin_menu();
		$admin->add_submenu();

		$this->assertArrayHasKey( Admin::MENU_SLUG, $GLOBALS['submenu'] );
		$this->assertTrue( $this->submenu_contains_slug( Admin::MENU_SLUG, Admin::SCREEN_ID ) );
	}

	/**
	 * Tests that the default submenu is removed when conditions are met.
	 */
	public function test_remove_default_submenu_removes_submenu_when_conditions_met(): void {
		$admin = new Admin();
		$admin->add_admin_menu();
		$admin->add_submenu();

		// Test return early if governing site with shared sites, as submenu should not be removed.
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		update_option(
			Settings::OPTION_GOVERNING_SHARED_SITES,
			[
				[
					'url'     => 'https://example.com',
					'name'    => 'Test',
					'api_key' => '',
				],
			]
		);
		$admin->remove_default_submenu();

		$this->assertTrue( $this->submenu_contains_slug( Admin::MENU_SLUG, Admin::MENU_SLUG ) );

		// Test with no shared sites.
		delete_option( Settings::OPTION_SITE_TYPE );
		delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );

		$admin->remove_default_submenu();

		$this->assertArrayHasKey( Admin::MENU_SLUG, $GLOBALS['submenu'] );
		$this->assertFalse( $this->submenu_contains_slug( Admin::MENU_SLUG, Admin::MENU_SLUG ) );
	}

	/**
	 * Ensures the screen callback renders the settings mount point.
	 */
	public function test_screen_callback_outputs_expected_html(): void {
		ob_start();
		( new Admin() )->screen_callback();
		$output = (string) ob_get_clean();

		$this->assertStringContainsString( 'onelogs-settings-page', $output );
		$this->assertStringContainsString( 'wrap', $output );
	}

	/**
	 * Ensures the plugin action links include a settings link.
	 */
	public function test_add_action_links_appends_settings_link(): void {
		$links = ( new Admin() )->add_action_links( [] );

		$this->assertCount( 1, $links );
		$this->assertStringContainsString( 'Settings', $links[0] );
		$this->assertStringContainsString( 'admin.php?page=' . Admin::SCREEN_ID, $links[0] );

		// Test that the method does not error when given invalid input.
		$this->setExpectedIncorrectUsage( Admin::class . '::add_action_links' );

		( new Admin() )->add_action_links( 'invalid' );
	}

	/**
	 * Ensures the settings script only loads on the settings screen.
	 */
	public function test_enqueue_scripts_only_loads_on_the_settings_screen(): void {
		( new Assets() )->register_assets();
		$admin = new Admin();

		$admin->enqueue_scripts( 'plugins.php' );
		$this->assertFalse( wp_script_is( Assets::SETTINGS_SCRIPT_HANDLE, 'enqueued' ) );

		$admin->enqueue_scripts( 'toplevel_page_' . Admin::MENU_SLUG );
		$this->assertFalse( wp_script_is( Assets::SETTINGS_SCRIPT_HANDLE, 'enqueued' ) );

		$admin->enqueue_scripts( Admin::MENU_SLUG . '_page_' . Admin::SCREEN_ID );
		$this->assertTrue( wp_script_is( Assets::SETTINGS_SCRIPT_HANDLE, 'enqueued' ) );

		wp_dequeue_script( Assets::SETTINGS_SCRIPT_HANDLE );
	}

	/**
	 * Ensures the setup notice links to the settings page while the site type isn't chosen (QA TC-011).
	 */
	public function test_render_notices_shows_setup_notice_until_the_site_type_is_set(): void {
		set_current_screen( 'plugins' );

		$output = $this->render_notices();
		$this->assertStringContainsString( 'OneLogs needs to be set up.', $output );
		$this->assertStringContainsString( 'admin.php?page=' . Admin::SCREEN_ID, $output );

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		$this->assertSame( '', $this->render_notices() );
	}

	/**
	 * Ensures the setup notice is only shown on the plugins and dashboard screens.
	 */
	public function test_render_notices_only_shows_on_plugins_and_dashboard(): void {
		set_current_screen( 'dashboard' );
		$this->assertStringContainsString( 'OneLogs needs to be set up.', $this->render_notices() );

		// The settings page shows the site type picker itself.
		set_current_screen( Admin::MENU_SLUG . '_page_' . Admin::SCREEN_ID );
		$this->assertSame( '', $this->render_notices() );
	}

	/**
	 * Ensures users who can't set the site type don't see the setup notice.
	 */
	public function test_render_notices_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		set_current_screen( 'plugins' );

		$this->assertSame( '', $this->render_notices() );
	}

	/**
	 * Renders the admin notices.
	 */
	private function render_notices(): string {
		ob_start();
		( new Admin() )->render_notices();

		return trim( (string) ob_get_clean() );
	}

	/**
	 * Checks whether a menu slug is present in the admin menu.
	 *
	 * @param string $slug Menu slug.
	 */
	private function menu_contains_slug( string $slug ): bool {
		foreach ( (array) $GLOBALS['menu'] as $menu_item ) {
			if ( isset( $menu_item[2] ) && $slug === $menu_item[2] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Checks whether a submenu slug is present for a menu slug.
	 *
	 * @param string $menu_slug    Parent menu slug.
	 * @param string $submenu_slug Submenu slug.
	 */
	private function submenu_contains_slug( string $menu_slug, string $submenu_slug ): bool {
		foreach ( (array) ( $GLOBALS['submenu'][ $menu_slug ] ?? [] ) as $submenu_item ) {
			if ( isset( $submenu_item[2] ) && $submenu_slug === $submenu_item[2] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Removes menu entries registered by this test.
	 */
	private function remove_menu_entries(): void {
		foreach ( (array) ( $GLOBALS['menu'] ?? [] ) as $index => $menu_item ) {
			if ( isset( $menu_item[2] ) && Admin::MENU_SLUG === $menu_item[2] ) {
				unset( $GLOBALS['menu'][ $index ] );
			}
		}

		unset( $GLOBALS['submenu'][ Admin::MENU_SLUG ] );
	}
}
