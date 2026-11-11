<?php
/**
 * The /shared-sites routes, which store the brand sites of a governing site and remove them.
 *
 * @package OneLogs\Modules\Rest
 */

declare(strict_types = 1);

namespace OneLogs\Modules\Rest;

use OneLogs\Modules\Settings\Settings;
use OneLogs\Utils;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class - Shared_Sites_Controller
 */
class Shared_Sites_Controller extends Abstract_REST_Controller {
	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		// Only governing sites have brand sites.
		if ( ! Settings::is_governing_site() ) {
			return;
		}

		/**
		 * Register a route which stores the brand sites: their name, URL and API key.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/shared-sites',
			[
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_shared_sites' ],
					'permission_callback' => static fn () => current_user_can( 'manage_options' ),
					'args'                => [
						'sites_data' => [
							'required'          => true,
							'type'              => 'array',
							'validate_callback' => 'rest_validate_request_arg',
						],
					],
				],
			]
		);

		/**
		 * Register a route to remove a brand site, and tell it.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/shared-sites/(?P<id>[A-Za-z0-9-]+)',
			[
				'methods'             => WP_REST_Server::DELETABLE,
				'callback'            => [ $this, 'delete_shared_site' ],
				'permission_callback' => static fn () => current_user_can( 'manage_options' ),
			]
		);
	}

	/**
	 * Set shared sites data.
	 *
	 * New sites, and sites whose URL or key changed, are health-checked on the brand site before anything is saved.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @phpstan-param \WP_REST_Request<array<string,mixed>> $request
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_shared_sites( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$sites_data = $request->get_param( 'sites_data' );
		$sites_data = is_array( $sites_data ) ? array_values( array_filter( $sites_data, 'is_array' ) ) : [];

		$stored_sites = array_column( Settings::get_shared_sites(), null, 'id' );

		/** @var array{api_key: string, id: string, name: string, url: string}[] $sites */
		$sites    = [];
		$urls     = [];
		$to_check = [];
		foreach ( $sites_data as $site_data ) {
			$name = is_string( $site_data['name'] ?? null ) ? sanitize_text_field( $site_data['name'] ) : '';
			if ( '' === $name ) {
				return new \WP_Error( 'onelogs_missing_site_name', __( 'Site Name is required.', 'onelogs' ), [ 'status' => 400 ] );
			}

			$url        = is_string( $site_data['url'] ?? null ) ? trim( $site_data['url'] ) : '';
			$parsed_url = wp_parse_url( $url );

			if ( empty( $parsed_url['host'] ) || ! in_array( strtolower( $parsed_url['scheme'] ?? '' ), [ 'http', 'https' ], true ) ) {
				return new \WP_Error( 'onelogs_invalid_site_url', __( 'Enter a valid URL (must start with http or https).', 'onelogs' ), [ 'status' => 400 ] );
			}

			// Compare normalized URLs, so `https://brand.test` and `HTTPS://Brand.test/` are the same site.
			$normalized_url = Utils::normalize_url( $url );
			if ( in_array( $normalized_url, $urls, true ) ) {
				return new \WP_Error( 'duplicate_site_url', __( 'Brand Site already exists.', 'onelogs' ), [ 'status' => 400 ] );
			}
			$urls[] = $normalized_url;

			// Add a unique ID if it doesn't exist.
			$id = is_string( $site_data['id'] ?? null ) && '' !== $site_data['id'] ? sanitize_text_field( $site_data['id'] ) : wp_generate_uuid4();

			// The browser never gets the keys, so a site saved without one keeps its stored key. Not once its URL changes, since the key belongs to the old site.
			$api_key  = is_string( $site_data['api_key'] ?? null ) ? trim( $site_data['api_key'] ) : '';
			$stored   = $stored_sites[ $id ] ?? null;
			$same_url = null !== $stored && Utils::normalize_url( $stored['url'] ) === $normalized_url;
			if ( $same_url && '' === $api_key ) {
				$api_key = $stored['api_key'];
			}

			if ( '' === $api_key ) {
				return new \WP_Error( 'onelogs_missing_api_key', __( 'API Key is required.', 'onelogs' ), [ 'status' => 400 ] );
			}

			$site = [
				'api_key' => $api_key,
				'id'      => $id,
				'name'    => $name,
				'url'     => $url,
			];

			// New sites, and sites whose URL or key changed, are health-checked before they're saved, which also connects them.
			if ( ! $same_url || $api_key !== $stored['api_key'] ) {
				$to_check[] = $site;
			}

			$sites[] = $site;
		}

		foreach ( $to_check as $site ) {
			$checked = Remote_Client::check_brand_site( $site['url'], $site['api_key'] );

			if ( is_wp_error( $checked ) ) {
				return $this->remote_error_response( $checked );
			}
		}

		Settings::set_shared_sites( $sites );

		// Return what was stored, so the browser has the ids it removes sites by.
		return rest_ensure_response(
			[
				'shared_sites' => Settings::get_shared_sites_without_keys(),
			]
		);
	}

	/**
	 * Removes a brand site, and then tells it to disconnect from this governing site.
	 *
	 * @param \WP_REST_Request $request The request object.
	 * @phpstan-param \WP_REST_Request<array<string,mixed>> $request
	 */
	public function delete_shared_site( WP_REST_Request $request ): WP_REST_Response|\WP_Error {
		$site_id = (string) $request['id'];
		$site    = null;

		foreach ( Settings::get_shared_sites() as $stored_site ) {
			if ( $stored_site['id'] === $site_id ) {
				$site = $stored_site;
				break;
			}
		}

		if ( null === $site ) {
			return new \WP_Error( 'onelogs_site_not_found', __( 'The brand site was not found.', 'onelogs' ), [ 'status' => 404 ] );
		}

		if ( ! Settings::remove_shared_site( $site['url'] ) ) {
			return new \WP_Error( 'onelogs_remove_site_failed', __( 'The brand site could not be removed.', 'onelogs' ), [ 'status' => 500 ] );
		}

		$remaining_sites = Settings::get_shared_sites_without_keys();

		if ( Remote_Client::disconnect_brand_site( $site ) ) {
			return rest_ensure_response(
				[
					'governing_site_notified' => true,
					'message'                 => __( 'Brand Site deleted successfully.', 'onelogs' ),
					'shared_sites'            => $remaining_sites,
				]
			);
		}

		return rest_ensure_response(
			[
				'governing_site_notified' => false,
				'message'                 => sprintf(
					/* translators: %s: brand site name. */
					__( '%s was removed, but it could not be notified. If it still shows this site under Governing Site Connection, click Disconnect Governing Site in its OneLogs settings.', 'onelogs' ),
					$site['name']
				),
				'shared_sites'            => $remaining_sites,
			]
		);
	}
}
