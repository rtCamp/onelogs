<?php
/**
 * Tests for the shared utilities.
 *
 * @package OneLogs\Tests\Integration
 */

declare(strict_types = 1);

namespace OneLogs\Tests\Integration;

use OneLogs\Tests\TestCase;
use OneLogs\Utils;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Class - UtilsTest
 */
#[CoversClass( Utils::class )]
final class UtilsTest extends TestCase {
	/**
	 * Different spellings of the same site URL.
	 *
	 * @return array<string,array{string,string}>
	 */
	public static function url_provider(): array {
		return [
			'adds a trailing slash'           => [ 'https://brand.test', 'https://brand.test/' ],
			'keeps a trailing slash'          => [ 'https://brand.test/', 'https://brand.test/' ],
			'trims whitespace'                => [ '  https://brand.test ', 'https://brand.test/' ],
			'lowercases the scheme'           => [ 'HTTPS://brand.test/', 'https://brand.test/' ],
			'lowercases the host'             => [ 'https://Brand.TEST', 'https://brand.test/' ],
			'keeps the port'                  => [ 'https://Brand.test:8443', 'https://brand.test:8443/' ],
			'keeps the case of the path'      => [ 'HTTPS://Brand.test/SubSite', 'https://brand.test/SubSite/' ],
			'leaves strings without a scheme' => [ 'Brand.test', 'Brand.test/' ],
		];
	}

	/**
	 * Ensures URLs are normalized so different spellings compare equal (QA TC-037).
	 *
	 * @param string $url      The URL to normalize.
	 * @param string $expected The normalized URL.
	 */
	#[DataProvider( 'url_provider' )]
	public function test_normalize_url( string $url, string $expected ): void {
		$this->assertSame( $expected, Utils::normalize_url( $url ) );
	}

	/**
	 * Ensures the current site URL comes from the home option, normalized.
	 */
	public function test_get_current_site_url(): void {
		// wp-env defines WP_HOME, which wins over the stored option.
		add_filter( 'option_home', static fn (): string => 'HTTP://Governing.example', 20 );

		$this->assertSame( 'http://governing.example/', Utils::get_current_site_url() );
	}
}
