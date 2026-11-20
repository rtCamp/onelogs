<?php
/**
 * Sends the requests between a governing site and its brand sites.
 *
 * @package OneLogs\Modules\Rest
 */

declare(strict_types = 1);

namespace OneLogs\Modules\Rest;

use OneLogs\Modules\Settings\Settings;
use OneLogs\Utils;

/**
 * Class - Remote_Client
 */
class Remote_Client {
	/**
	 * Asks the governing site to remove this brand site from its shared sites.
	 *
	 * @return true|\WP_Error True if the governing site removed this site.
	 */
	public static function deregister_from_governing_site(): true|\WP_Error {
		// Only call on brand sites.
		if ( ! Settings::is_consumer_site() ) {
			return new \WP_Error(
				'onelogs_unauthorized_site',
				__( 'The requesting site is not a shared brand site.', 'onelogs' ),
			);
		}

		$parent_url = Settings::get_parent_site_url();
		if ( empty( $parent_url ) ) {
			return new \WP_Error(
				'onelogs_no_parent',
				__( 'No governing site is configured.', 'onelogs' ),
			);
		}

		// Child authenticating to the governing site.
		$our_public_key = Settings::get_api_key();
		if ( empty( $our_public_key ) ) {
			return new \WP_Error(
				'onelogs_no_key',
				__( 'No API key is configured.', 'onelogs' ),
			);
		}

		$response = self::request(
			$parent_url,
			'brand-site',
			$our_public_key,
			[
				'method' => \WP_REST_Server::DELETABLE,
			]
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$code = wp_remote_retrieve_response_code( $response );

		// The governing site only authenticates brand sites it lists with a matching key.
		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error(
				'onelogs_unrecognized_site',
				__( 'The governing site does not recognize this site.', 'onelogs' ),
				[ 'status' => $code ]
			);
		}

		if ( 200 !== $code ) {
			return new \WP_Error(
				'onelogs_rest_failed_to_connect',
				__( 'Failed to connect to the governing site.', 'onelogs' ),
				[
					'status' => $code,
					'body'   => wp_remote_retrieve_body( $response ),
				]
			);
		}

		return true;
	}

	/**
	 * Tells a brand site to forget this governing site, so it drops the connection.
	 *
	 * Failures are logged, since a brand site that can't be reached can still be disconnected from its own settings.
	 *
	 * @param array{url: string, api_key: string} $site_data The brand site, with its stored key.
	 *
	 * @return bool True if the brand site confirmed it.
	 */
	public static function disconnect_brand_site( array $site_data ): bool {
		if ( empty( $site_data['url'] ) || empty( $site_data['api_key'] ) ) {
			return false;
		}

		$response = self::request(
			$site_data['url'],
			'governing-site',
			$site_data['api_key'],
			[
				'method'  => \WP_REST_Server::DELETABLE,
				'timeout' => 5, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- Only runs when an admin removes a brand site or uninstalls the plugin.
			]
		);

		$code = is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response );

		if ( 200 !== $code ) {
			Utils::log( sprintf( 'Could not disconnect the brand site %s: %s', $site_data['url'], is_wp_error( $response ) ? $response->get_error_message() : 'HTTP ' . $code ) );
			return false;
		}

		return true;
	}

	/**
	 * Runs a brand site's health check, which also connects it to this governing site.
	 *
	 * @param string $url     The brand site's URL.
	 * @param string $api_key The brand site's API key.
	 *
	 * @return true|\WP_Error True if the brand site accepted the key.
	 */
	public static function check_brand_site( string $url, string $api_key ): true|\WP_Error {
		$response = self::request(
			$url,
			'governing-site',
			$api_key,
			[
				'method'  => \WP_REST_Server::CREATABLE,
				'timeout' => 5, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- An admin is waiting for the answer.
			]
		);

		$code = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );

		if ( 200 === $code ) {
			return true;
		}

		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error( 'onelogs_bad_key', __( 'The brand site rejected this API key. Copy the key from the brand site\'s OneLogs settings and try again.', 'onelogs' ), [ 'status' => 400 ] );
		}

		if ( 409 === $code ) {
			return new \WP_Error( 'onelogs_already_connected', __( 'This brand site is already connected to a different governing site. Disconnect it there first, then try again.', 'onelogs' ), [ 'status' => 409 ] );
		}

		return new \WP_Error( 'onelogs_health_check_failed', __( 'Could not reach the brand site. Check the URL, and that OneLogs is active there, then try again.', 'onelogs' ), [ 'status' => 502 ] );
	}

	/**
	 * Gets data from a OneLogs route on a brand site, like its logs or filter options.
	 *
	 * @param array{url: string, api_key: string, name?: string} $site_data The brand site, with its stored key.
	 * @param string                                             $route     The route, relative to the OneLogs namespace.
	 * @param array<string,mixed>                                $query     Query arguments to pass on.
	 *
	 * @return array<mixed>|\WP_Error The decoded response body, or an error explaining why the brand site couldn't answer.
	 */
	public static function get_from_brand_site( array $site_data, string $route, array $query = [] ): array|\WP_Error {
		$site_name = ! empty( $site_data['name'] ) ? $site_data['name'] : $site_data['url'];

		if ( empty( $site_data['url'] ) || empty( $site_data['api_key'] ) ) {
			return new \WP_Error(
				'onelogs_missing_site_data',
				/* translators: %s: brand site name. */
				sprintf( __( 'The URL or API key of %s is missing. Remove the site and add it again.', 'onelogs' ), $site_name ),
				[ 'status' => 500 ]
			);
		}

		$response = self::request(
			$site_data['url'],
			$route . ( ! empty( $query ) ? '?' . http_build_query( $query ) : '' ),
			$site_data['api_key'],
			[
				'method'  => \WP_REST_Server::READABLE,
				'timeout' => 10, // phpcs:ignore WordPressVIPMinimum.Performance.RemoteRequestTimeout.timeout_timeout -- An admin is waiting for the logs.
			]
		);

		if ( is_wp_error( $response ) ) {
			Utils::log( sprintf( 'Could not reach the brand site %s: %s', $site_data['url'], $response->get_error_message() ) );

			return new \WP_Error(
				'onelogs_brand_site_unreachable',
				/* translators: %s: brand site name. */
				sprintf( __( 'Could not reach %s. Check that the site is online and OneLogs is active there.', 'onelogs' ), $site_name ),
				[ 'status' => 502 ]
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error(
				'onelogs_bad_key',
				/* translators: %s: brand site name. */
				sprintf( __( '%s rejected the API key. If its key was regenerated, edit the site in OneLogs > Settings and paste the new key.', 'onelogs' ), $site_name ),
				[ 'status' => 502 ]
			);
		}

		if ( 200 !== $code ) {
			return new \WP_Error(
				'onelogs_rest_failed_to_connect',
				is_array( $body ) && is_string( $body['message'] ?? null )
					/* translators: 1: brand site name, 2: the brand site's error message. */
					? sprintf( __( '%1$s returned an error: %2$s', 'onelogs' ), $site_name, wp_strip_all_tags( $body['message'] ) )
					/* translators: 1: brand site name, 2: HTTP status code. */
					: sprintf( __( '%1$s answered with error %2$d.', 'onelogs' ), $site_name, $code ),
				[ 'status' => 502 ]
			);
		}

		if ( ! is_array( $body ) ) {
			return new \WP_Error(
				'onelogs_rest_invalid_response',
				/* translators: %s: brand site name. */
				sprintf( __( '%s returned an invalid response.', 'onelogs' ), $site_name ),
				[ 'status' => 502 ]
			);
		}

		return $body;
	}

	/**
	 * Sends a request to a OneLogs route on another site, signed with the API key that site checks.
	 *
	 * @param string              $site_url The other site's URL.
	 * @param string              $route    The route, relative to the OneLogs namespace.
	 * @param string              $api_key  The API key the other site checks.
	 * @param array<string,mixed> $args     Extra request arguments, like the method and timeout.
	 *
	 * @return array<string,mixed>|\WP_Error The response.
	 */
	private static function request( string $site_url, string $route, string $api_key, array $args = [] ): array|\WP_Error {
		return wp_safe_remote_request(
			sprintf( '%s/wp-json/%s/%s', untrailingslashit( $site_url ), Abstract_REST_Controller::NAMESPACE, $route ),
			$args + [
				'headers' => [
					'Accept'             => 'application/json',
					'Content-Type'       => 'application/json',
					'X-OneLogs-Token'    => $api_key,
					'X-OneLogs-Site-URL' => Utils::get_current_site_url(),
				],
			]
		);
	}
}
