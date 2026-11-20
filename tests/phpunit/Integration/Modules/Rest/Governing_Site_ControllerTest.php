<?php
/**
 * Tests for the /governing-site routes on a brand site, and the /brand-site route on a governing site.
 *
 * @package OneLogs\Tests\Integration\Modules\Rest
 */

declare(strict_types = 1);

namespace OneLogs\Tests\Integration\Modules\Rest;

use OneLogs\Modules\Rest\Abstract_REST_Controller;
use OneLogs\Modules\Rest\Brand_Site_Controller;
use OneLogs\Modules\Rest\Governing_Site_Controller;
use OneLogs\Modules\Rest\Remote_Client;
use OneLogs\Modules\Rest\Secret_Key_Controller;
use OneLogs\Modules\Settings\Settings;
use OneLogs\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class - Governing_Site_ControllerTest
 */
#[CoversClass( Governing_Site_Controller::class )]
#[CoversClass( Brand_Site_Controller::class )]
#[CoversClass( Secret_Key_Controller::class )]
#[CoversClass( Remote_Client::class )]
#[CoversClass( Abstract_REST_Controller::class )]
final class Governing_Site_ControllerTest extends TestCase {
	private const GOVERNING_URL = 'https://governing.test/';

	/**
	 * The recorded outgoing requests.
	 *
	 * @var array<int,array{url: string, args: array<string,mixed>}>
	 */
	private array $requests = [];

	/**
	 * {@inheritDoc}
	 */
	protected function setUp(): void {
		parent::setUp();

		delete_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL );
		delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		delete_option( Settings::OPTION_SITE_TYPE );
		delete_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL );
		delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );

		parent::tearDown();
	}

	/**
	 * Ensures a governing site with this brand site's key connects it (TC-021, TC-028).
	 */
	public function test_governing_site_with_the_key_connects(): void {
		$server = $this->brand_server();

		$response = $server->dispatch( $this->signed( 'POST', '/onelogs/v1/governing-site', Settings::get_api_key() ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( untrailingslashit( self::GOVERNING_URL ), Settings::get_parent_site_url() );
	}

	/**
	 * Ensures a wrong key can't connect, and doesn't set the governing site (TC-034).
	 */
	public function test_wrong_key_cannot_connect(): void {
		$server = $this->brand_server();

		$response = $server->dispatch( $this->signed( 'POST', '/onelogs/v1/governing-site', 'wrong-key' ) );

		$this->assertContains( $response->get_status(), [ 401, 403 ] );
		$this->assertNull( Settings::get_parent_site_url() );
	}

	/**
	 * Ensures a request without the sending site's URL can't connect, which used to trust the Origin header.
	 */
	public function test_missing_site_url_cannot_connect(): void {
		$server  = $this->brand_server();
		$request = new \WP_REST_Request( 'POST', '/onelogs/v1/governing-site' );
		$request->set_header( 'X-OneLogs-Token', Settings::get_api_key() );
		$request->set_header( 'Origin', self::GOVERNING_URL );

		$response = $server->dispatch( $request );

		$this->assertContains( $response->get_status(), [ 401, 403 ] );
		$this->assertNull( Settings::get_parent_site_url() );
	}

	/**
	 * Ensures a connected brand site can't be claimed by another governing site (TC-039).
	 */
	public function test_connected_brand_site_rejects_another_governing_site(): void {
		$server = $this->brand_server();
		Settings::set_parent_site_url( self::GOVERNING_URL );

		$response = $server->dispatch( $this->signed( 'POST', '/onelogs/v1/governing-site', Settings::get_api_key(), 'https://other-governing.test/' ) );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( untrailingslashit( self::GOVERNING_URL ), Settings::get_parent_site_url() );
	}

	/**
	 * Ensures the connected governing site can reconnect, however its URL is spelled.
	 */
	public function test_connected_governing_site_can_reconnect(): void {
		$server = $this->brand_server();
		Settings::set_parent_site_url( self::GOVERNING_URL );

		$response = $server->dispatch( $this->signed( 'POST', '/onelogs/v1/governing-site', Settings::get_api_key(), 'HTTPS://Governing.test' ) );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Ensures the governing site can disconnect this site without being called back.
	 */
	public function test_governing_site_disconnects_brand_site(): void {
		$server = $this->brand_server();
		Settings::set_parent_site_url( self::GOVERNING_URL );
		$this->mock_remote_requests( static fn () => self::http_response( 200 ), $this->requests );

		$response = $server->dispatch( $this->signed( 'DELETE', '/onelogs/v1/governing-site', Settings::get_api_key() ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['governing_site_notified'] );
		$this->assertNull( Settings::get_parent_site_url() );
		$this->assertSame( [], $this->requests );
	}

	/**
	 * Ensures an admin disconnecting tells the governing site, which removes the brand site (TC-024, TC-025).
	 */
	public function test_admin_disconnect_deregisters_from_the_governing_site(): void {
		$server = $this->brand_server();
		Settings::set_parent_site_url( self::GOVERNING_URL );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->mock_remote_requests( static fn () => self::http_response( 200 ), $this->requests );

		$response = $server->dispatch( new \WP_REST_Request( 'DELETE', '/onelogs/v1/governing-site' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['governing_site_notified'] );
		$this->assertNull( Settings::get_parent_site_url() );

		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'https://governing.test/wp-json/onelogs/v1/brand-site', $this->requests[0]['url'] );
		$this->assertSame( 'DELETE', $this->requests[0]['args']['method'] );
		$this->assertSame( Settings::get_api_key(), $this->requests[0]['args']['headers']['X-OneLogs-Token'] );
	}

	/**
	 * Ensures disconnecting still works when the governing site can't be told, with a message saying so.
	 */
	public function test_admin_disconnect_reports_an_unreachable_governing_site(): void {
		$server = $this->brand_server();
		Settings::set_parent_site_url( self::GOVERNING_URL );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->mock_remote_requests( static fn () => new \WP_Error( 'http_request_failed', 'timeout' ), $this->requests );

		$response = $server->dispatch( new \WP_REST_Request( 'DELETE', '/onelogs/v1/governing-site' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['governing_site_notified'] );
		$this->assertStringContainsString( 'could not be notified', $response->get_data()['message'] );
		$this->assertNull( Settings::get_parent_site_url() );
	}

	/**
	 * Ensures an anonymous request can't disconnect the site.
	 */
	public function test_anonymous_request_cannot_disconnect(): void {
		$server = $this->brand_server();
		Settings::set_parent_site_url( self::GOVERNING_URL );

		$response = $server->dispatch( new \WP_REST_Request( 'DELETE', '/onelogs/v1/governing-site' ) );

		$this->assertSame( 401, $response->get_status() );
		$this->assertNotNull( Settings::get_parent_site_url() );
	}

	/**
	 * Ensures regenerating the key returns a new one that replaces the old one (TC-019, TC-020).
	 */
	public function test_admin_regenerates_the_key(): void {
		$server  = $this->brand_server();
		$old_key = Settings::get_api_key();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$response = $server->dispatch( new \WP_REST_Request( 'POST', '/onelogs/v1/secret-key' ) );

		$this->assertSame( 200, $response->get_status() );
		$new_key = $response->get_data()['secret_key'];
		$this->assertSame( 128, strlen( $new_key ) );
		$this->assertNotSame( $old_key, $new_key );
		$this->assertSame( $new_key, Settings::get_api_key() );
	}

	/**
	 * Ensures the brand site routes aren't on governing sites, and the governing site routes aren't on brand sites.
	 */
	public function test_routes_follow_the_site_type(): void {
		$brand_routes = $this->brand_server()->get_routes();
		$this->assertArrayHasKey( '/onelogs/v1/governing-site', $brand_routes );
		$this->assertArrayHasKey( '/onelogs/v1/secret-key', $brand_routes );
		$this->assertArrayNotHasKey( '/onelogs/v1/brand-site', $brand_routes );

		$governing_routes = $this->governing_server()->get_routes();
		$this->assertArrayHasKey( '/onelogs/v1/brand-site', $governing_routes );
		$this->assertArrayNotHasKey( '/onelogs/v1/governing-site', $governing_routes );
		$this->assertArrayNotHasKey( '/onelogs/v1/secret-key', $governing_routes );
	}

	/**
	 * Ensures a brand site can remove itself from its governing site with its own key.
	 */
	public function test_brand_site_removes_itself_from_the_governing_site(): void {
		$server = $this->governing_server();
		Settings::set_shared_sites( [ $this->brand_site() ] );

		$response = $server->dispatch( $this->signed( 'DELETE', '/onelogs/v1/brand-site', 'brand-key', 'https://brand.test' ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], Settings::get_shared_sites() );
	}

	/**
	 * Ensures a brand site can't remove itself with the wrong key, or remove another site.
	 */
	public function test_brand_site_needs_its_own_key_to_remove_itself(): void {
		$server = $this->governing_server();
		Settings::set_shared_sites( [ $this->brand_site() ] );

		$wrong_key = $server->dispatch( $this->signed( 'DELETE', '/onelogs/v1/brand-site', 'wrong-key', 'https://brand.test' ) );
		$wrong_url = $server->dispatch( $this->signed( 'DELETE', '/onelogs/v1/brand-site', 'brand-key', 'https://other-brand.test' ) );

		$this->assertContains( $wrong_key->get_status(), [ 401, 403 ] );
		$this->assertContains( $wrong_url->get_status(), [ 401, 403 ] );
		$this->assertCount( 1, Settings::get_shared_sites() );
	}

	/**
	 * Builds a REST server for a brand site.
	 */
	private function brand_server(): \WP_REST_Server {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );

		return $this->set_up_rest_server( [ new Governing_Site_Controller(), new Brand_Site_Controller(), new Secret_Key_Controller() ] );
	}

	/**
	 * Builds a REST server for a governing site.
	 */
	private function governing_server(): \WP_REST_Server {
		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );

		return $this->set_up_rest_server( [ new Governing_Site_Controller(), new Brand_Site_Controller(), new Secret_Key_Controller() ] );
	}

	/**
	 * A brand site of the governing site.
	 *
	 * @return array<string,string>
	 */
	private function brand_site(): array {
		return [
			'id'      => 'site-one',
			'name'    => 'Brand One',
			'url'     => 'https://brand.test',
			'api_key' => 'brand-key',
		];
	}

	/**
	 * Builds a request signed like another site sends it.
	 *
	 * @param string $method   The method.
	 * @param string $route    The route.
	 * @param string $api_key  The key the sender holds.
	 * @param string $site_url The sender's URL.
	 *
	 * @return \WP_REST_Request<array<string,mixed>>
	 */
	private function signed( string $method, string $route, string $api_key, string $site_url = self::GOVERNING_URL ): \WP_REST_Request {
		$request = new \WP_REST_Request( $method, $route );
		$request->set_header( 'X-OneLogs-Token', $api_key );
		$request->set_header( 'X-OneLogs-Site-URL', $site_url );

		return $request;
	}
}
