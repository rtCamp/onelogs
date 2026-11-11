<?php
/**
 * Tests for who can read logs, and how a governing site reads its brand sites' logs.
 *
 * @package OneLogs\Tests\Integration\Modules\Rest
 */

declare(strict_types = 1);

namespace OneLogs\Tests\Integration\Modules\Rest;

use OneLogs\Modules\Rest\Abstract_REST_Controller;
use OneLogs\Modules\Rest\Logs_REST_Controller;
use OneLogs\Modules\Rest\Remote_Client;
use OneLogs\Modules\Settings\Settings;
use OneLogs\Tests\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Class - Logs_REST_ControllerTest
 */
#[CoversClass( Logs_REST_Controller::class )]
#[CoversClass( Remote_Client::class )]
#[CoversClass( Abstract_REST_Controller::class )]
final class Logs_REST_ControllerTest extends TestCase {
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

		global $wpdb;

		// Stream's log table, with the columns OneLogs reads. The test case makes it a temporary table.
		// phpcs:disable WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query(
			"CREATE TABLE IF NOT EXISTS {$wpdb->prefix}stream (
				ID bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				site_id bigint(20) unsigned NOT NULL DEFAULT 1,
				blog_id bigint(20) unsigned NOT NULL DEFAULT 1,
				object_id bigint(20) unsigned NULL,
				user_id bigint(20) unsigned NOT NULL DEFAULT 0,
				user_role varchar(50) NOT NULL DEFAULT '',
				summary longtext NOT NULL,
				created datetime NOT NULL,
				connector varchar(100) NOT NULL,
				context varchar(100) NOT NULL,
				action varchar(100) NOT NULL,
				ip varchar(39) NULL,
				PRIMARY KEY (ID)
			)"
		);
		// phpcs:enable

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		$wpdb->insert(
			$wpdb->prefix . 'stream',
			[
				'summary'   => 'Local log',
				// The database returns IDs as strings, which strict types rejected.
				'object_id' => 42,
				'created'   => '2026-11-01 10:00:00',
				'connector' => 'posts',
				'context'   => 'post',
				'action'    => 'updated',
				'ip'        => '127.0.0.1',
			]
		);

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
	 * Ensures anonymous requests can't read logs.
	 */
	public function test_anonymous_requests_cannot_read_logs(): void {
		$server = $this->server_for( Settings::SITE_TYPE_CONSUMER );

		$response = $server->dispatch( new \WP_REST_Request( 'GET', '/onelogs/v1/logs' ) );

		$this->assertSame( 401, $response->get_status() );
	}

	/**
	 * Ensures a brand site's own admin can read its logs (TC-056).
	 */
	public function test_admin_reads_local_logs(): void {
		$server = $this->server_for( Settings::SITE_TYPE_CONSUMER );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		$response = $server->dispatch( $this->logs_request() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'Local log' ], array_column( $response->get_data()['data'], 'summary' ) );
	}

	/**
	 * Ensures the connected governing site can read the brand site's logs with its key.
	 */
	public function test_connected_governing_site_reads_logs(): void {
		$server = $this->server_for( Settings::SITE_TYPE_CONSUMER );
		Settings::set_parent_site_url( self::GOVERNING_URL );

		$response = $server->dispatch( $this->logs_request( [], Settings::get_api_key() ) );

		$this->assertSame( 200, $response->get_status() );
	}

	/**
	 * Ensures the key alone isn't enough: the request has to come from the connected governing site (TC-025).
	 */
	public function test_other_sites_cannot_read_logs_with_the_key(): void {
		$server = $this->server_for( Settings::SITE_TYPE_CONSUMER );

		// Not connected yet.
		$not_connected = $server->dispatch( $this->logs_request( [], Settings::get_api_key() ) );

		// Connected to a different governing site.
		Settings::set_parent_site_url( 'https://other-governing.test' );
		$other_site = $server->dispatch( $this->logs_request( [], Settings::get_api_key() ) );

		// Connected, with an old key (TC-020).
		Settings::set_parent_site_url( self::GOVERNING_URL );
		$old_key = $server->dispatch( $this->logs_request( [], 'old-key' ) );

		$this->assertSame( 401, $not_connected->get_status() );
		$this->assertSame( 401, $other_site->get_status() );
		$this->assertSame( 401, $old_key->get_status() );
	}

	/**
	 * Ensures a brand site never fetches other sites' logs for a `site_url` parameter (TC-058).
	 */
	public function test_brand_site_ignores_site_url(): void {
		$server = $this->server_for( Settings::SITE_TYPE_CONSUMER );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );
		$this->mock_remote_requests( static fn () => self::http_response( 200, [ 'data' => [ [ 'summary' => 'Remote log' ] ] ] ), $this->requests );

		$response = $server->dispatch(
			$this->logs_request(
				[
					'site_url'             => 'https://another-site.test',
					'include_shared_sites' => 'true',
					'current_site_logs'    => 'true',
				]
			)
		);

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], $this->requests );
		$this->assertSame( [ 'Local log' ], array_column( $response->get_data()['data'], 'summary' ) );
	}

	/**
	 * Ensures a governing site only fetches logs from its own brand sites.
	 */
	public function test_governing_site_rejects_unknown_site_url(): void {
		$server = $this->governing_server();
		$this->mock_remote_requests( static fn () => self::http_response( 200 ), $this->requests );

		$response = $server->dispatch( $this->brand_logs_request( 'https://not-a-brand.test' ) );

		$this->assertSame( 404, $response->get_status() );
		$this->assertSame( [], $this->requests );
	}

	/**
	 * Ensures a brand site's logs are fetched with its key, and labeled with its name and URL (TC-051, TC-052).
	 */
	public function test_governing_site_reads_brand_site_logs(): void {
		$server = $this->governing_server();
		$this->mock_remote_requests(
			static fn () => self::http_response(
				200,
				[
					'status' => 'success',
					'data'   => [
						[
							'ID'      => 7,
							'summary' => 'Remote log',
						],
					],
					'meta'   => [
						'total'       => 1,
						'total_pages' => 1,
					],
				]
			),
			$this->requests
		);

		// The site is found however its URL is spelled.
		$response = $server->dispatch( $this->brand_logs_request( 'https://Brand.test', [ 'search' => 'Remote' ] ) );

		$this->assertSame( 200, $response->get_status() );

		$this->assertCount( 1, $this->requests );
		$this->assertStringStartsWith( 'https://brand.test/wp-json/onelogs/v1/logs?', $this->requests[0]['url'] );
		$this->assertSame( 'brand-key', $this->requests[0]['args']['headers']['X-OneLogs-Token'] );

		parse_str( (string) wp_parse_url( $this->requests[0]['url'], PHP_URL_QUERY ), $query );
		$this->assertArrayNotHasKey( 'site_url', $query );
		$this->assertSame( 'Remote', $query['search'] ?? null );
		$this->assertSame( '1', $query['current_site_logs'] ?? null );

		$log = $response->get_data()['data'][0];
		$this->assertSame( 'Remote log', $log['summary'] );
		$this->assertSame( 'Brand One', $log['site_name'] );
		$this->assertSame( 'https://brand.test/', $log['site_url'] );
		$this->assertTrue( $log['is_remote'] );
		$this->assertSame( 1, $response->get_data()['meta']['total'] );
	}

	/**
	 * Ensures an unreachable brand site gives a clear error instead of an empty 200 (TC-054).
	 */
	public function test_unreachable_brand_site_returns_an_error(): void {
		$server = $this->governing_server();
		$this->mock_remote_requests( static fn () => new \WP_Error( 'http_request_failed', 'cURL error 28' ), $this->requests );

		$response = $server->dispatch( $this->brand_logs_request( 'https://brand.test' ) );

		$this->assertSame( 502, $response->get_status() );
		$this->assertSame( 'onelogs_brand_site_unreachable', $response->get_data()['code'] );
		$this->assertStringContainsString( 'Could not reach Brand One', $response->get_data()['message'] );
		$this->assertSame( 'true', $response->get_headers()['x-vip-reached-origin'] ?? null );
	}

	/**
	 * Ensures a brand site rejecting the stored key says so (TC-020, TC-054).
	 */
	public function test_rejected_key_returns_an_error(): void {
		$server = $this->governing_server();
		$this->mock_remote_requests( static fn () => self::http_response( 401, [ 'code' => 'rest_forbidden' ] ), $this->requests );

		foreach ( [ 'logs', 'logs/contexts', 'logs/actions', 'logs/users' ] as $route ) {
			$request = new \WP_REST_Request( 'GET', '/onelogs/v1/' . $route );
			$request->set_query_params(
				[
					'site_url'             => 'https://brand.test',
					'include_shared_sites' => 'true',
				]
			);

			$response = $server->dispatch( $request );

			$this->assertSame( 502, $response->get_status(), $route );
			$this->assertSame( 'onelogs_bad_key', $response->get_data()['code'], $route );
			$this->assertStringContainsString( 'Brand One rejected the API key', $response->get_data()['message'], $route );
		}
	}

	/**
	 * Ensures a brand site's own error message is passed on.
	 */
	public function test_brand_site_error_message_is_passed_on(): void {
		$server = $this->governing_server();
		$this->mock_remote_requests( static fn () => self::http_response( 500, [ 'message' => '<p>There has been a critical error on this website.</p>' ] ), $this->requests );

		$response = $server->dispatch( $this->brand_logs_request( 'https://brand.test' ) );

		$this->assertSame( 502, $response->get_status() );
		$this->assertSame( 'Brand One returned an error: There has been a critical error on this website.', $response->get_data()['message'] );
	}

	/**
	 * Ensures the governing site's own logs don't call out.
	 */
	public function test_governing_site_reads_its_own_logs(): void {
		$server = $this->governing_server();
		$this->mock_remote_requests( static fn () => self::http_response( 200 ), $this->requests );

		$response = $server->dispatch( $this->logs_request() );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [], $this->requests );
		$this->assertSame( [ 'Local log' ], array_column( $response->get_data()['data'], 'summary' ) );
	}

	/**
	 * Builds a REST server with the logs routes for a site type.
	 *
	 * @param string $site_type The site type.
	 */
	private function server_for( string $site_type ): \WP_REST_Server {
		update_option( Settings::OPTION_SITE_TYPE, $site_type );

		return $this->set_up_rest_server( [ new Logs_REST_Controller() ] );
	}

	/**
	 * Builds a REST server for a governing site with one brand site, and logs in its admin.
	 */
	private function governing_server(): \WP_REST_Server {
		$server = $this->server_for( Settings::SITE_TYPE_GOVERNING );

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
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'administrator' ] ) );

		return $server;
	}

	/**
	 * Builds a request for this site's logs.
	 *
	 * @param array<string,string> $query   Query arguments.
	 * @param ?string              $api_key Sign it like the governing site, with this key.
	 *
	 * @return \WP_REST_Request<array<string,mixed>>
	 */
	private function logs_request( array $query = [], ?string $api_key = null ): \WP_REST_Request {
		$request = new \WP_REST_Request( 'GET', '/onelogs/v1/logs' );
		$request->set_query_params( $query + [ 'current_site_logs' => 'true' ] );

		if ( null !== $api_key ) {
			$request->set_header( 'X-OneLogs-Token', $api_key );
			$request->set_header( 'X-OneLogs-Site-URL', self::GOVERNING_URL );
		}

		return $request;
	}

	/**
	 * Builds a request for a brand site's logs, like the dashboard sends it.
	 *
	 * @param string               $site_url The brand site's URL.
	 * @param array<string,string> $query    More query arguments.
	 *
	 * @return \WP_REST_Request<array<string,mixed>>
	 */
	private function brand_logs_request( string $site_url, array $query = [] ): \WP_REST_Request {
		$request = new \WP_REST_Request( 'GET', '/onelogs/v1/logs' );
		$request->set_query_params(
			$query + [
				'site_url'             => $site_url,
				'include_shared_sites' => 'true',
				'current_site_logs'    => 'false',
			]
		);

		return $request;
	}
}
