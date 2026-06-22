<?php
/**
 * Rest unit tests.
 *
 * @package OneLogs\Tests\Integration\Modules\Core
 */

declare( strict_types = 1 );

namespace OneLogs\Tests\Integration\Modules\Core;

use OneLogs\Modules\Core\Rest;
use OneLogs\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Tests for the Rest core module.
 */
#[CoversClass( \OneLogs\Modules\Core\Rest::class )]
final class RestTest extends TestCase {
	/**
	 * Tests no errors on class instantiation.
	 */
	public function test_class_instantiation(): void {
		$rest = new Rest();

		$rest->register_hooks();

		$this->assertTrue( true );
	}

	/**
	 * Tests that the OneLogs token header is added once.
	 */
	public function test_allowed_cors_headers_adds_onelogs_token_once(): void {
		$rest = new Rest();

		$this->assertSame(
			[ 'X-WP-Nonce', 'X-OneLogs-Token' ],
			$rest->allowed_cors_headers( [ 'X-WP-Nonce' ] ),
			'Token should be added to headers'
		);

		$this->assertSame(
			[ 'X-OneLogs-Token' ],
			$rest->allowed_cors_headers( [ 'X-OneLogs-Token' ] ),
			'Token should not be readded'
		);
	}
}
