<?php
/**
 * REST controller to handle Settings related endpoints.
 *
 * @package OneLogs
 */

declare(strict_types = 1);

namespace OneLogs\Modules\Admin;

use OneLogs\Modules\Core\Settings;
use OneLogs\Rest\Abstract_REST_Controller;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Class - API_Key_REST_Controller
 */
final class API_Key_REST_Controller extends Abstract_REST_Controller {

	/**
	 * {@inheritDoc}
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/api-key',
			[
				[
					'methods'             => [ WP_REST_Server::READABLE, 'OPTIONS' ],
					'callback'            => [ $this, 'get_item' ],
					'permission_callback' => static fn () => current_user_can( 'manage_options' ),
				],
				[
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => [ $this, 'create_item' ],
					'permission_callback' => static fn () => current_user_can( 'manage_options' ),
				],
			],
		);
	}

	/**
	 * {@inheritDoc}
	 * Gets the API key, generating a new one if it doesn't exist.
	 *
	 * @param \WP_REST_Request<array{}> $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function get_item( $request ) {
		$api_key = Settings::get_api_key();

		return new WP_REST_Response(
			[
				'api_key' => $api_key,
			],
			200
		);
	}

	/**
	 * {@inheritDoc}
	 * Regenerates the API key.
	 *
	 * @param \WP_REST_Request<array{}> $request Request.
	 *
	 * @return \WP_REST_Response
	 */
	public function create_item( $request ) {
		$api_key = Settings::regenerate_api_key();

		return new WP_REST_Response(
			[
				'api_key' => $api_key,
				'message' => __( 'API key regenerated successfully.', 'onelogs' ),
			],
			200
		);
	}
}
