<?php
/**
 * Base REST controller class.
 *
 * Includes the shared namespace, version and hook registration.
 *
 * @package OneLogs\Modules\Rest
 */

declare( strict_types = 1 );

namespace OneLogs\Modules\Rest;

use OneLogs\Contracts\Interfaces\Registrable;
use OneLogs\Modules\Settings\Settings;
use OneLogs\Utils;
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
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
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
	 * Whether the request is from a brand site of this governing site, signed with that brand's API key.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public function is_brand_request( $request ): bool {
		if ( ! Settings::is_governing_site() ) {
			return false;
		}

		$shared_sites = Settings::get_shared_sites();
		$site_url     = $this->get_requesting_site_url( $request );

		return $this->has_api_key( $request, $shared_sites[ $site_url ]['api_key'] ?? '' );
	}

	/**
	 * Whether the request is from the governing site this brand site is connected to, signed with this site's API key.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 */
	public function is_governing_request( $request ): bool {
		if ( ! Settings::is_consumer_site() ) {
			return false;
		}

		$governing_site_url = Settings::get_parent_site_url();
		$governing_site_url = ! empty( $governing_site_url ) ? Utils::normalize_url( $governing_site_url ) : null;

		return $governing_site_url === $this->get_requesting_site_url( $request )
			&& $this->has_api_key( $request, Settings::get_api_key() );
	}

	/**
	 * Gets the normalized URL of the site that sent the request, from its X-OneLogs-Site-URL header.
	 *
	 * @param \WP_REST_Request<array<string,mixed>>|\WP_REST_Request<array{}> $request Request.
	 *
	 * @return string The normalized URL, or an empty string if the header is missing or invalid.
	 */
	protected function get_requesting_site_url( $request ): string {
		$header   = $request->get_header( 'X-OneLogs-Site-URL' );
		$site_url = ! empty( $header ) ? esc_url_raw( wp_unslash( $header ) ) : '';
		$parsed   = wp_parse_url( $site_url );

		return ! empty( $parsed['scheme'] ) && ! empty( $parsed['host'] ) ? Utils::normalize_url( $site_url ) : '';
	}

	/**
	 * Whether the request's X-OneLogs-Token is the given API key.
	 *
	 * @param \WP_REST_Request<array<string,mixed>> $request Request.
	 * @param string                                $api_key The API key the sender should hold.
	 */
	protected function has_api_key( $request, string $api_key ): bool {
		$token = (string) $request->get_header( 'X-OneLogs-Token' );

		return '' !== $api_key && '' !== $token && hash_equals( $api_key, $token );
	}

	/**
	 * Converts an error from another site into a response for the browser.
	 *
	 * VIP's edge swaps a 502 body for its error page unless the response says it came from the site, which would hide the message.
	 *
	 * @see https://docs.wpvip.com/infrastructure/edge-servers/custom-error-pages/
	 *
	 * @param \WP_Error $error The error.
	 */
	protected function remote_error_response( \WP_Error $error ): \WP_REST_Response {
		$response = rest_convert_error_to_response( $error );

		if ( 502 === $response->get_status() ) {
			$response->header( 'x-vip-reached-origin', 'true' );
		}

		return $response;
	}
}
