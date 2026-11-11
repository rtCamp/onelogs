<?php
/**
 * Asset registration unit tests.
 *
 * @package OneLogs\Tests\Integration\Modules\Core
 */

declare( strict_types = 1 );

namespace OneLogs\Tests\Integration\Modules\Core;

use OneLogs\Modules\Core\Assets;
use OneLogs\Modules\Settings\Settings;
use OneLogs\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for asset registration and localization.
 */
#[CoversClass( \OneLogs\Modules\Core\Assets::class )]
final class AssetsTest extends TestCase {
	/**
	 * Tests no errors on class instantiation.
	 */
	public function test_assets_class_instantiation(): void {
		$assets = new Assets();

		$assets->register_hooks();
		$assets->register_assets();
		$assets->enqueue_scripts();

		$this->assertTrue( true );
	}

	/**
	 * Ensures plugin scripts receive a defer attribute.
	 */
	public function test_defer_scripts_adds_defer_for_plugin_handles(): void {
		$assets = new Assets();
		$tag    = '<script src="https://example.com/script.js"></script>';

		$actual = $assets->defer_scripts( $tag, Assets::SETTINGS_SCRIPT_HANDLE );

		$this->assertSame( '<script defer src="https://example.com/script.js"></script>', $actual );
	}

	/**
	 * Ensures unrelated script handles are unchanged.
	 */
	public function test_defer_scripts_does_not_modify_other_handles(): void {
		$assets = new Assets();
		$tag    = '<script src="https://example.com/script.js"></script>';

		$this->assertSame( $tag, $assets->defer_scripts( $tag, 'other-handle' ) );
	}

	/**
	 * Ensures defer is not duplicated on existing script tags.
	 */
	public function test_defer_scripts_does_not_duplicate_defer_attribute(): void {
		$assets = new Assets();
		$tag    = '<script defer src="https://example.com/script.js"></script>';

		$this->assertSame( $tag, $assets->defer_scripts( $tag, Assets::SETTINGS_SCRIPT_HANDLE ) );
	}

	/**
	 * Ensures localized data exposes the expected keys, and a governing site gets no keys at all.
	 */
	public function test_get_localized_data_returns_expected_keys(): void {
		$this->reset_localized_data();
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING, false );
		Settings::set_shared_sites(
			[
				[
					'id'      => 'site-one',
					'name'    => 'Brand One',
					'url'     => 'https://brand.test',
					'api_key' => 'brand-key',
				],
			]
		);

		$data = Assets::get_localized_data();

		$this->assertSame( [ 'apiKey', 'restNamespace', 'setupUrl', 'sharedSites', 'siteName', 'siteType' ], array_keys( $data ) );
		$this->assertSame( 'onelogs/v1', $data['restNamespace'] );
		$this->assertSame( Settings::SITE_TYPE_GOVERNING, $data['siteType'] );

		// Governing sites don't have a key of their own, and the brand sites' keys never reach the browser.
		$this->assertSame( '', $data['apiKey'] );
		$this->assertSame(
			[
				[
					'id'   => 'site-one',
					'name' => 'Brand One',
					'url'  => 'https://brand.test/',
				],
			],
			$data['sharedSites']
		);
		$this->assertStringNotContainsString( 'brand-key', (string) wp_json_encode( $data ) );

		delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );
	}

	/**
	 * Ensures a brand site gets its own key, which its settings screen shows.
	 */
	public function test_get_localized_data_gives_brand_sites_their_key(): void {
		$this->reset_localized_data();
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER, false );

		$data = Assets::get_localized_data();

		$this->assertSame( Settings::get_api_key(), $data['apiKey'] );
		$this->assertSame( [], $data['sharedSites'] );
	}

	/**
	 * Clears the cached localized data, so each test builds it for its own settings.
	 */
	private function reset_localized_data(): void {
		$property = new \ReflectionProperty( Assets::class, 'localized_data' );
		$property->setValue( null, [] );
	}
}
