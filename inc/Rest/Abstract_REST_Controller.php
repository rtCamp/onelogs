<?php
/**
 * Base REST controller class.
 *
 * Includes the shared namespace, version and hook registration.
 *
 * @package OneLogs
 */

declare(strict_types=1);

namespace OneLogs\Rest;

use OneLogs\Contracts\Interfaces\Registrable;
use OneLogs\Modules\Core\Settings;
use WP_REST_Controller;

/**
 * Class - Abstract_REST_Controller
 */
abstract class Abstract_REST_Controller extends WP_REST_Controller implements Registrable {
	/**
	 * The namespace for the REST API.
	 */
	public const NAMESPACE = 'onelogs/v1';

	/**
	 * {@inheritDoc}
	 *
	 * Reuses the namespace constant.
	 *
	 * @var string
	 */
	protected $namespace = self::NAMESPACE;

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		// Add cors headers to the response headers before the response is sent.
		add_filter( 'rest_allowed_cors_headers', [ $this, 'add_cors_headers' ] );

		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Add CORS headers to REST API responses.
	 *
	 * @param array<int, string> $headers Existing headers.
	 *
	 * @return array<int, string> Modified headers.
	 */
	public function add_cors_headers( $headers ): array {
		// If X-OneLogs-Token is present in $headers array, skip adding any headers.
		if ( isset( $headers['X-OneLogs-Token'] ) ) {
			return $headers;
		}

		return array_merge( $headers, [ 'X-OneLogs-Token' ] );
	}

	/**
	 * {@inheritDoc}
	 *
	 * We throw an exception here to force the child class to implement this method.
	 *
	 * @throws \Exception If method not implemented.
	 *
	 * @codeCoverageIgnore
	 */
	public function register_routes(): void {
		throw new \Exception( __FUNCTION__ . ' Method not implemented.' );
	}

	/**
	 * Checks for an Authorization header with a Bearer token matching the stored API key.
	 *
	 * @todo this should be on a hook.
	 *
	 * @param \WP_REST_Request<array{}> $request Request.
	 * @return bool
	 */
	public function check_api_permissions( $request ) {
		$auth_header = $request->get_header( 'authorization' );

		if ( ! $auth_header || ! str_starts_with( $auth_header, 'Bearer ' ) ) {
			return false;
		}

		// Extract the token from the header.
		$api_key = trim( str_replace( 'Bearer', '', $auth_header ) );
		if ( empty( $api_key ) ) {
			return false;
		}

		$stored_key = get_option( Settings::OPTION_OWN_API_KEY, '' );

		return hash_equals( $stored_key, $api_key );
	}
}
