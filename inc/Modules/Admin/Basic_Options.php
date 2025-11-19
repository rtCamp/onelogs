<?php
/**
 * Class Basic_Options which contains basic rest routes for the plugin.
 *
 * @package OneLogs
 */

namespace OneLogs\Modules\Admin;

use OneLogs\Modules\Plugin_Configs\Constants;
use OneLogs\Modules\Plugin_Configs\Secret_Key;
use OneLogs\Rest\Abstract_REST_Controller;
use OneLogs\Utils;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class Basic_Options
 */
class Basic_Options extends Abstract_REST_Controller {

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {

		/**
		 * Register a route to get site type and set site type.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/site-type',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_site_type' ],
					'permission_callback' => [ self::class, 'check_user_permissions' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_site_type' ],
					'permission_callback' => [ self::class, 'check_user_permissions' ],
					'args'                => [
						'site_type' => [
							'required'          => true,
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
						],
					],
				],
			]
		);

		/**
		 * Register a route to get onelogs_child_site_api_key option.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/secret-key',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ Secret_Key::class, 'get_secret_key' ],
					'permission_callback' => [ self::class, 'check_user_permissions' ],
				],
				[
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => [ Secret_Key::class, 'regenerate_secret_key' ],
					'permission_callback' => [ self::class, 'check_user_permissions' ],
				],
			]
		);

		/**
		 * Register a route which will store array of sites data like site name, site url, its GitHub repo and api key.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/shared-sites',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_shared_sites' ],
					'permission_callback' => [ self::class, 'check_user_permissions' ],
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'set_shared_sites' ],
					'permission_callback' => [ self::class, 'check_user_permissions' ],
					'args'                => [
						'sites_data' => [
							'required'          => true,
							'type'              => 'array',
							'sanitize_callback' => static function ( $value ) {
								return is_array( $value );
							},
						],
					],
				],
			]
		);

		/**
		 * Register a route for health-check.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/health-check',
			[
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => [ $this, 'health_check' ],
				'permission_callback' => [ Utils::class, 'onelogs_validate_api_key_health_check' ],
			]
		);

		/**
		 * Register a route to manage governing site.
		 */
		register_rest_route(
			self::NAMESPACE,
			'/governing-site',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_governing_site' ],
					'permission_callback' => [ self::class, 'check_user_permissions' ],
				],
				[
					'methods'             => WP_REST_Server::DELETABLE,
					'callback'            => [ $this, 'remove_governing_site' ],
					'permission_callback' => [ self::class, 'check_user_permissions' ],
				],
			],
		);
	}

	/**
	 * Permission callback to check user capabilities.
	 *
	 * @return bool
	 */
	public static function check_user_permissions(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get governing site url.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_governing_site(): WP_REST_Response|\WP_Error {
		$governing_site_url = get_option( Constants::ONELOGS_GOVERNING_SITE_URL, '' );
		return new WP_REST_Response(
			[
				'success'            => true,
				'governing_site_url' => $governing_site_url,
			]
		);
	}

	/**
	 * Remove governing site url.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function remove_governing_site(): WP_REST_Response|\WP_Error {
		update_option( Constants::ONELOGS_GOVERNING_SITE_URL, '', false );
		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Governing site removed successfully.', 'onelogs' ),
			]
		);
	}

	/**
	 * Health check endpoint.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function health_check(): WP_REST_Response|\WP_Error {
		return new WP_REST_Response(
			[
				'success' => true,
				'message' => __( 'Health check passed successfully.', 'onelogs' ),
			]
		);
	}

	/**
	 * Get the site type.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_site_type(): WP_REST_Response|\WP_Error {

		$site_type = Utils::get_current_site_type();

		return new WP_REST_Response(
			[
				'success'   => true,
				'site_type' => $site_type,
			]
		);
	}

	/**
	 * Set the site type.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_site_type( WP_REST_Request $request ): WP_REST_Response|\WP_Error {

		$site_type = sanitize_text_field( $request->get_param( 'site_type' ) );

		update_option( Constants::ONELOGS_SITE_TYPE, $site_type, false );

		// set transient to indicating that site type has been set for infinite time.
		set_transient( Constants::ONELOGS_SITE_TYPE_TRANSIENT, true, 0 );

		return new WP_REST_Response(
			[
				'success'   => true,
				'site_type' => $site_type,
			]
		);
	}

	/**
	 * Get shared sites data.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function get_shared_sites(): WP_REST_Response|\WP_Error {
		$shared_sites = get_option( Constants::ONELOGS_SHARED_SITES, [] );
		return new WP_REST_Response(
			[
				'success'      => true,
				'shared_sites' => $shared_sites,
			]
		);
	}

	/**
	 * Set shared sites data.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function set_shared_sites( WP_REST_Request $request ): WP_REST_Response|\WP_Error {

		$body         = $request->get_body();
		$decoded_body = json_decode( $body, true );
		$sites_data   = $decoded_body['sites_data'] ?? [];

		// check if same url exists more than once or not.
		$urls = [];
		foreach ( $sites_data as $site ) {
			if ( isset( $site['siteUrl'] ) && in_array( $site['siteUrl'], $urls, true ) ) {
				return new \WP_Error( 'duplicate_site_url', __( 'Brand Site already exists.', 'onelogs' ), [ 'status' => 400 ] );
			}
			$urls[] = $site['siteUrl'] ?? '';
		}

		update_option( Constants::ONELOGS_SHARED_SITES, $sites_data, false );

		return new WP_REST_Response(
			[
				'success'    => true,
				'sites_data' => $sites_data,
			]
		);
	}
}
