<?php
/**
 * Singleton trait unit tests.
 *
 * @package OneLogs\Tests\Integration\Contracts\Traits
 */

declare( strict_types = 1 );

namespace OneLogs\Tests\Integration\Contracts\Traits;

use OneLogs\Contracts\Traits\Singleton;
use OneLogs\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Test double for the singleton trait.
 */
final class SingletonTestDouble {
	use Singleton;

	/**
	 * Resets the singleton instance.
	 */
	public static function reset_instance(): void {
		self::$instance = null;
	}
}

/**
 * Class SingletonTest
 */
#[CoversClass( \OneLogs\Contracts\Traits\Singleton::class )]
final class SingletonTest extends TestCase {
	/**
	 * Tests that instance returns the same object.
	 */
	public function test_instance_returns_singleton_instance(): void {
		$this->assertSame( SingletonTestDouble::instance(), SingletonTestDouble::instance() );
	}

	/**
	 * Tests clone protection.
	 *
	 * @expectedIncorrectUsage __clone
	 */
	public function test_clone_triggers_doing_it_wrong(): void {
		$fixture = SingletonTestDouble::instance();

		$fixture->__clone();

		$this->assertTrue( true );
	}

	/**
	 * Tests wakeup protection.
	 *
	 * @expectedIncorrectUsage __wakeup
	 */
	public function test_wakeup_triggers_doing_it_wrong(): void {
		$fixture = SingletonTestDouble::instance();

		$fixture->__wakeup();

		$this->assertTrue( true );
	}
}
