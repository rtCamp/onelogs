<?php
/**
 * Registers the plugin's settings and options
 *
 * @package OneLogs
 */

declare(strict_types = 1);

namespace OneLogs\Modules\Core;

use OneLogs\Contracts\Interfaces\Registrable;

/**
 * Class = Admin
 */
final class Settings implements Registrable {
	/**
	 * The setting prefix.
	 */
	private const SETTING_PREFIX = 'onelogs_';

	/**
	 * The setting group.
	 */
	public const SETTING_GROUP = self::SETTING_PREFIX . 'settings';

	/**
	 * Setting keys
	 */
	public const OPTION_SITE_TYPE   = self::SETTING_PREFIX . 'site_type';
	public const OPTION_BRAND_SITES = self::SETTING_PREFIX . 'brand_sites';
	public const OPTION_OWN_API_KEY = self::SETTING_PREFIX . 'brand_site_public_key';

	/**
	 * {@inheritDoc}
	 */
	public function register_hooks(): void {
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'rest_api_init', [ $this, 'register_settings' ] );
		add_action( 'update_option_' . self::OPTION_SITE_TYPE, [ $this, 'on_site_type_change' ], 10, 2 );
	}

	/**
	 * Register settings.
	 */
	public function register_settings(): void {

		$settings = [
			// The `site_type` setting.
			self::OPTION_SITE_TYPE   => [
				'type'              => 'string',
				'label'             => __( 'Site Type', 'onelogs' ),
				'description'       => __( 'Defines whether this site is a governing or a brand site.', 'onelogs' ),
				'sanitize_callback' => 'sanitize_text_field',
				'show_in_rest'      => [
					'schema' => [
						'enum' => [ 'consumer', 'dashboard' ],
					],
				],
			],
			// The `brand_sites` setting.
			self::OPTION_BRAND_SITES => [
				'type'              => 'array',
				'label'             => __( 'Brand Sites', 'onelogs' ),
				'description'       => __( 'An array of brand sites connected to this governing site.', 'onelogs' ),
				'sanitize_callback' => [ self::class, 'sanitize_brand_sites' ],
				'show_in_rest'      => [
					'schema' => [
						'type'  => 'array',
						'items' => [
							'type'       => 'object',
							'properties' => [
								'id'      => [
									'type' => 'string',
								],
								'name'    => [
									'type' => 'string',
								],
								'url'     => [
									'type'   => 'string',
									'format' => 'uri',
								],
								'logo'    => [
									'type'   => 'string',
									'format' => 'uri',
								],
								'api_key' => [
									'type' => 'string',
								],
							],
						],
					],
				],
			],
		];

		$existing_settings = get_registered_settings();

		foreach ( $settings as $setting_key => $args ) {
			// Do not register settings if they already exist.
			if ( array_key_exists( $setting_key, $existing_settings ) ) {
				continue;
			}

			register_setting(
				self::SETTING_GROUP,
				$setting_key,
				$args
			);
		}
	}

	/**
	 * Sanitize the `brand_sites` option.
	 *
	 * @param mixed $input The input value.
	 *
	 * @return array{
	 * id: string,
	 * name: string,
	 * url: string,
	 * logo: string,
	 * api_key: string
	 * }[]
	 */
	public static function sanitize_brand_sites( $input ): array {
		if ( ! is_array( $input ) || empty( $input ) ) {
			return [];
		}

		$sanitized = [];

		foreach ( $input as $site_data ) {
			if ( ! is_array( $site_data ) ) {
				continue;
			}

			$site_id      = isset( $site_data['id'] ) ? sanitize_text_field( $site_data['id'] ) : '';
			$site_name    = isset( $site_data['name'] ) ? sanitize_text_field( $site_data['name'] ) : '';
			$site_url     = isset( $site_data['url'] ) ? esc_url_raw( $site_data['url'] ) : '';
			$site_logo    = isset( $site_data['logo'] ) ? esc_url_raw( $site_data['logo'] ) : '';
			$site_api_key = isset( $site_data['api_key'] ) ? sanitize_text_field( $site_data['api_key'] ) : '';

			// Only save if required fields are filled.
			if ( empty( $site_name ) || empty( $site_url ) ) {
				continue;
			}

			$sanitized[] = [
				'id'      => $site_id ?: wp_generate_uuid4(),
				'name'    => $site_name,
				'url'     => $site_url,
				'logo'    => $site_logo,
				'api_key' => $site_api_key,
			];
		}

		return $sanitized;
	}

	/**
	 * Ensures the API key is generated when the site type changes to 'consumer'.
	 *
	 * @param mixed $old_value The old value.
	 * @param mixed $new_value The new value.
	 */
	public function on_site_type_change( $old_value, $new_value ): void {
		if ( 'consumer' !== $new_value ) {
			return;
		}

		// By getting the API key, it will be generated if it doesn't exist.
		self::get_api_key();
	}

	/**
	 * Gets the API key, generating a new one if it doesn't exist.
	 */
	public static function get_api_key(): string {
		$api_key = get_option( self::OPTION_OWN_API_KEY, '' );

		if ( empty( $api_key ) ) {
			$api_key = self::generate_api_key();
			update_option( self::OPTION_OWN_API_KEY, $api_key );
		}

		return $api_key;
	}

	/**
	 * Regenerates the API key.
	 */
	public static function regenerate_api_key(): string {
		$api_key = self::generate_api_key();
		update_option( self::OPTION_OWN_API_KEY, $api_key );

		return $api_key;
	}

	/**
	 * Generate a random API key.
	 *
	 * @return string API key prefixed with 'token_'.
	 */
	private static function generate_api_key(): string {
		return 'token_' . wp_generate_password( 32, false );
	}
}
