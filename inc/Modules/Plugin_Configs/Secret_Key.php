<?php
/**
 * Create a secret key for OneLogs site communication.
 *
 * @package OneLogs
 */

namespace OneLogs\Modules\Plugin_Configs;

use OneLogs\Contracts\Interfaces\Registrable;
use WP_REST_Response;

/**
 * Class - Secret_Key
 */
class Secret_Key implements Registrable {

	/**
	 * Setup WordPress hooks
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ self::class, 'generate_secret_key' ] );
	}

	/**
	 * Generate a secret key for the site.
	 *
	 * @param bool $should_regenerate Whether to regenerate the key or not.
	 *
	 * @return string The generated secret key.
	 */
	public static function generate_secret_key( bool $should_regenerate = false ): string {
		$secret_key = get_option( Settings::OPTION_CONSUMER_API_KEY );

		if ( empty( $secret_key ) || $should_regenerate ) {
			$secret_key = self::generate_key();
			// Store the secret key in the database.
			$is_key_updated = update_option( Settings::OPTION_CONSUMER_API_KEY, $secret_key, false );

			if ( ! $is_key_updated ) {
				return '';
			}
		}

		return $secret_key;
	}

	/**
	 * Get the secret key.
	 */
	public static function get_secret_key(): WP_REST_Response|\WP_Error {
		$secret_key = get_option( Settings::OPTION_CONSUMER_API_KEY );
		if ( empty( $secret_key ) ) {
			$secret_key = self::generate_secret_key();
		}

		return new WP_REST_Response(
			[
				'success'    => true,
				'secret_key' => $secret_key,
			]
		);
	}

	/**
	 * Regenerate the secret key.
	 */
	public static function regenerate_secret_key(): WP_REST_Response|\WP_Error {

		$regenerated_key = self::generate_secret_key( true );

		return new WP_REST_Response(
			[
				'success'    => true,
				'message'    => __( 'Secret key regenerated successfully.', 'onelogs' ),
				'secret_key' => $regenerated_key,
			]
		);
	}

	/**
	 * Generate a random key.
	 *
	 * @return string The generated key.
	 */
	private static function generate_key() {
		return wp_generate_password( 128, false, false );
	}
}
