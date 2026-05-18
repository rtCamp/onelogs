<?php
/**
 * Autoloader unit tests.
 *
 * @package OneLogs\Tests\Unit
 */

declare( strict_types = 1 );

namespace OneLogs\Tests\Unit;

use OneLogs\Autoloader;
use OneLogs\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class AutoloaderTest
 */
#[CoversClass( \OneLogs\Autoloader::class )]
final class AutoloaderTest extends TestCase {
	/**
	 * Reset static state before each test.
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->reset_autoloader();
	}

	/**
	 * Clean up hooks and static state.
	 */
	protected function tearDown(): void {
		$this->reset_autoloader();

		parent::tearDown();
	}

	/**
	 * Ensures autoload succeeds when both Composer autoloaders exist.
	 */
	public function test_autoload_returns_true_when_autoloader_exists(): void {
		$this->assertTrue( Autoloader::autoload() );
		$property = new \ReflectionProperty( Autoloader::class, 'is_loaded' );
		$this->assertTrue( $property->getValue() );
		$this->assertTrue( Autoloader::autoload(), 'Autoload should return true on subsequent calls' );
	}

	/**
	 * Ensures missing autoloader notice registers on admin_notices hook.
	 */
	public function test_missing_autoloader_notice_fires_on_admin_notices(): void {
		$method = new \ReflectionMethod( Autoloader::class, 'missing_autoloader_notice' );
		$method->invoke( null );

		$this->expectOutputRegex( '/OneLogs: The Composer autoloader was not found./' );
		do_action( 'admin_notices' );
	}

	/**
	 * Ensures missing autoloader notice registers on network_admin_notices hook.
	 */
	public function test_missing_autoloader_notice_fires_on_network_admin_notices(): void {
		$method = new \ReflectionMethod( Autoloader::class, 'missing_autoloader_notice' );
		$method->invoke( null );

		$this->expectOutputRegex( '/OneLogs: The Composer autoloader was not found./' );
		do_action( 'network_admin_notices' );
	}

	/**
	 * Reset the Autoloader.
	 */
	private function reset_autoloader(): void {
		$property = new \ReflectionProperty( Autoloader::class, 'is_loaded' );
		$property->setValue( null, false );
	}
}
