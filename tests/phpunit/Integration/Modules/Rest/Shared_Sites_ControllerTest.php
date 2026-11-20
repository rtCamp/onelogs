<?php
/**
 * Tests for the /shared-sites routes on a governing site.
 *
 * @package OneLogs\Tests\Integration\Modules\Rest
 */

declare(strict_types = 1);

namespace OneLogs\Tests\Integration\Modules\Rest;

use OneLogs\Modules\Rest\Abstract_REST_Controller;
use OneLogs\Modules\Rest\Remote_Client;
use OneLogs\Modules\Rest\Shared_Sites_Controller;
use OneLogs\Modules\Settings\Settings;
use OneLogs\Tests\TestCase;
use OneLogs\Utils;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class - Shared_Sites_ControllerTest
 */
#[CoversClass( Shared_Sites_Controller::class )]
#[CoversClass( Remote_Client::class )]
#[CoversClass( Abstract_REST_Controller::class )]
#[CoversClass( Settings::class )]
#[CoversClass( Utils::class )]
final class Shared_Sites_ControllerTest extends TestCase {
	/**
	 * The REST server.
	 *
	 * @var \WP_REST_Server
	 */
	private \WP_REST_Server $server;

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

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_GOVERNING );
		delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$this->server = $this->set_up_rest_server( [ new Shared_Sites_Controller() ] );
	}

	/**
	 * {@inheritDoc}
	 */
	protected function tearDown(): void {
		delete_option( Settings::OPTION_SITE_TYPE );
		delete_option( Settings::OPTION_GOVERNING_SHARED_SITES );

		parent::tearDown();
	}

	/**
	 * Ensures the routes exist only on governing sites.
	 */
	public function test_routes_are_only_registered_on_governing_sites(): void {
		$this->assertArrayHasKey( '/onelogs/v1/shared-sites', $this->server->get_routes() );

		update_option( Settings::OPTION_SITE_TYPE, Settings::SITE_TYPE_CONSUMER );
		$server = $this->set_up_rest_server( [ new Shared_Sites_Controller() ] );

		$this->assertArrayNotHasKey( '/onelogs/v1/shared-sites', $server->get_routes() );
	}

	/**
	 * Ensures only admins can save brand sites.
	 */
	public function test_saving_requires_manage_options(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$response = $this->save_sites( [ $this->site() ] );

		$this->assertSame( 403, $response->get_status() );
	}

	/**
	 * Ensures a new site is health-checked on the brand site by the server, and saved without its key leaving the server (TC-028).
	 */
	public function test_saving_a_new_site_health_checks_it_and_hides_keys(): void {
		$this->mock_remote_requests( static fn () => self::http_response( 200, [ 'message' => 'Connected' ] ), $this->requests );

		$response = $this->save_sites( [ $this->site() ] );

		$this->assertSame( 200, $response->get_status() );

		// The health check went to the brand site, signed with its key and this site's URL.
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'https://brand.test/wp-json/onelogs/v1/governing-site', $this->requests[0]['url'] );
		$this->assertSame( 'POST', $this->requests[0]['args']['method'] );
		$this->assertSame( 'brand-key', $this->requests[0]['args']['headers']['X-OneLogs-Token'] );
		$this->assertSame( Utils::get_current_site_url(), $this->requests[0]['args']['headers']['X-OneLogs-Site-URL'] );

		// The site is stored with its key, but the response has no keys.
		$this->assertSame( 'brand-key', Settings::get_shared_site_by_url( 'https://brand.test' )['api_key'] ?? null );
		$data = $response->get_data();
		$this->assertCount( 1, $data['shared_sites'] );
		$this->assertArrayNotHasKey( 'api_key', $data['shared_sites'][0] );
		$this->assertNotEmpty( $data['shared_sites'][0]['id'] );
	}

	/**
	 * Ensures non-http URLs are rejected with the validation message (TC-033).
	 */
	public function test_saving_rejects_non_http_urls(): void {
		$this->mock_remote_requests( static fn () => self::http_response( 200 ), $this->requests );

		foreach ( [ 'ftp://brand.test', 'javascript:alert(1)', 'abc' ] as $url ) {
			$response = $this->save_sites( [ $this->site( [ 'url' => $url ] ) ] );

			$this->assertSame( 400, $response->get_status(), $url );
			$this->assertSame( 'Enter a valid URL (must start with http or https).', $response->get_data()['message'], $url );
		}

		$this->assertSame( [], $this->requests );
		$this->assertSame( [], Settings::get_shared_sites() );
	}

	/**
	 * Ensures different spellings of the same URL are duplicates (TC-036, TC-037).
	 */
	public function test_saving_rejects_duplicate_url_variants(): void {
		$this->mock_remote_requests( static fn () => self::http_response( 200 ), $this->requests );

		foreach ( [ 'https://brand.test', 'https://brand.test/', 'HTTPS://brand.test/', 'https://Brand.TEST' ] as $variant ) {
			$response = $this->save_sites(
				[
					$this->site(),
					$this->site(
						[
							'name' => 'Again',
							'url'  => $variant,
						]
					),
				]
			);

			$this->assertSame( 400, $response->get_status(), $variant );
			$this->assertSame( 'Brand Site already exists.', $response->get_data()['message'], $variant );
		}

		$this->assertSame( [], $this->requests );
	}

	/**
	 * Ensures a key the brand site rejects isn't saved, and the reason is returned (TC-034).
	 */
	public function test_saving_reports_a_rejected_key(): void {
		$this->mock_remote_requests( static fn () => self::http_response( 401, [ 'code' => 'rest_forbidden' ] ), $this->requests );

		$response = $this->save_sites( [ $this->site() ] );

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'onelogs_bad_key', $response->get_data()['code'] );
		$this->assertSame( [], Settings::get_shared_sites() );
	}

	/**
	 * Ensures a brand site already connected elsewhere isn't saved (TC-039).
	 */
	public function test_saving_reports_a_brand_site_connected_elsewhere(): void {
		$this->mock_remote_requests( static fn () => self::http_response( 409, [ 'code' => 'onelogs_already_connected' ] ), $this->requests );

		$response = $this->save_sites( [ $this->site() ] );

		$this->assertSame( 409, $response->get_status() );
		$this->assertSame( 'onelogs_already_connected', $response->get_data()['code'] );
		$this->assertSame( [], Settings::get_shared_sites() );
	}

	/**
	 * Ensures an unreachable site isn't saved, and the 502 keeps its message on VIP (TC-035).
	 */
	public function test_saving_reports_an_unreachable_site(): void {
		$this->mock_remote_requests( static fn () => new \WP_Error( 'http_request_failed', 'cURL error 6' ), $this->requests );

		$response = $this->save_sites( [ $this->site() ] );

		$this->assertSame( 502, $response->get_status() );
		$this->assertSame( 'onelogs_health_check_failed', $response->get_data()['code'] );
		$this->assertSame( 'true', $response->get_headers()['x-vip-reached-origin'] ?? null );
		$this->assertSame( [], Settings::get_shared_sites() );
	}

	/**
	 * Ensures editing a site's name with a blank key keeps the stored key, without a new health check (TC-040).
	 */
	public function test_editing_without_a_key_keeps_the_stored_key(): void {
		$stored = $this->store_site();
		$this->mock_remote_requests( static fn () => self::http_response( 200 ), $this->requests );

		$response = $this->save_sites(
			[
				[
					'id'      => $stored['id'],
					'name'    => 'Renamed',
					'url'     => $stored['url'],
					'api_key' => '',
				],
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], $this->requests );

		$site = Settings::get_shared_site_by_url( 'https://brand.test' );
		$this->assertSame( 'Renamed', $site['name'] ?? null );
		$this->assertSame( 'brand-key', $site['api_key'] ?? null );
	}

	/**
	 * Ensures a new key is health-checked before it replaces the stored one (TC-042).
	 */
	public function test_editing_with_a_new_key_health_checks_it(): void {
		$stored = $this->store_site();
		$this->mock_remote_requests( static fn () => self::http_response( 200 ), $this->requests );

		$response = $this->save_sites(
			[
				[
					'id'      => $stored['id'],
					'name'    => $stored['name'],
					'url'     => $stored['url'],
					'api_key' => 'new-key',
				],
			]
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'new-key', $this->requests[0]['args']['headers']['X-OneLogs-Token'] );
		$this->assertSame( 'new-key', Settings::get_shared_site_by_url( 'https://brand.test' )['api_key'] ?? null );
	}

	/**
	 * Ensures a changed URL needs a key, since the stored key belongs to the old site.
	 */
	public function test_editing_the_url_requires_a_key(): void {
		$stored = $this->store_site();
		$this->mock_remote_requests( static fn () => self::http_response( 200 ), $this->requests );

		$response = $this->save_sites(
			[
				[
					'id'      => $stored['id'],
					'name'    => $stored['name'],
					'url'     => 'https://other-brand.test',
					'api_key' => '',
				],
			]
		);

		$this->assertSame( 400, $response->get_status() );
		$this->assertSame( 'onelogs_missing_api_key', $response->get_data()['code'] );
		$this->assertSame( [], $this->requests );
	}

	/**
	 * Ensures deleting a site removes it and tells the brand site to disconnect (TC-045).
	 */
	public function test_deleting_a_site_removes_it_and_notifies_the_brand_site(): void {
		$stored = $this->store_site();
		$this->mock_remote_requests( static fn () => self::http_response( 200 ), $this->requests );

		$response = $this->server->dispatch( new \WP_REST_Request( 'DELETE', '/onelogs/v1/shared-sites/' . $stored['id'] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertTrue( $response->get_data()['governing_site_notified'] );
		$this->assertSame( [], $response->get_data()['shared_sites'] );
		$this->assertSame( [], Settings::get_shared_sites() );

		$this->assertCount( 1, $this->requests );
		$this->assertSame( 'https://brand.test/wp-json/onelogs/v1/governing-site', $this->requests[0]['url'] );
		$this->assertSame( 'DELETE', $this->requests[0]['args']['method'] );
	}

	/**
	 * Ensures a site is still removed when the brand site can't be told, with a message saying so.
	 */
	public function test_deleting_a_site_reports_an_unreachable_brand_site(): void {
		$stored = $this->store_site();
		$this->mock_remote_requests( static fn () => new \WP_Error( 'http_request_failed', 'timeout' ), $this->requests );

		$response = $this->server->dispatch( new \WP_REST_Request( 'DELETE', '/onelogs/v1/shared-sites/' . $stored['id'] ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertFalse( $response->get_data()['governing_site_notified'] );
		$this->assertStringContainsString( 'Brand One was removed', $response->get_data()['message'] );
		$this->assertSame( [], Settings::get_shared_sites() );
	}

	/**
	 * Ensures deleting an unknown site fails.
	 */
	public function test_deleting_an_unknown_site_returns_404(): void {
		$response = $this->server->dispatch( new \WP_REST_Request( 'DELETE', '/onelogs/v1/shared-sites/unknown-id' ) );

		$this->assertSame( 404, $response->get_status() );
	}

	/**
	 * Builds a brand site for the request.
	 *
	 * @param array<string,string> $overrides Fields to change.
	 *
	 * @return array<string,string>
	 */
	private function site( array $overrides = [] ): array {
		return $overrides + [
			'name'    => 'Brand One',
			'url'     => 'https://brand.test',
			'api_key' => 'brand-key',
		];
	}

	/**
	 * Stores a brand site directly.
	 *
	 * @return array{api_key: string, id: string, name: string, url: string}
	 */
	private function store_site(): array {
		Settings::set_shared_sites( [ $this->site( [ 'id' => 'site-one' ] ) ] );

		$site = Settings::get_shared_site_by_url( 'https://brand.test' );
		$this->assertNotNull( $site );

		return $site;
	}

	/**
	 * Saves brand sites through the route.
	 *
	 * @param array<int,array<string,string>> $sites The sites.
	 */
	private function save_sites( array $sites ): \WP_REST_Response {
		$request = new \WP_REST_Request( 'POST', '/onelogs/v1/shared-sites' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body( (string) wp_json_encode( [ 'sites_data' => $sites ] ) );

		return $this->server->dispatch( $request );
	}
}
