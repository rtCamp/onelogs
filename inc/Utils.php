<?php
/**
 * Shared static utility functions.
 *
 * @package OneLogs
 */

namespace OneLogs;

use OneLogs\Modules\Settings\Settings;

/**
 * Class Utils
 */
final class Utils {

	/**
	 * Get the current site type.
	 */
	public static function get_current_site_type(): string {
		return (string) get_option( Settings::OPTION_SITE_TYPE, '' );
	}

	/**
	 * Check if the current site is a brand site.
	 */
	public static function is_brand_site(): bool {
		return 'brand-site' === self::get_current_site_type();
	}

	/**
	 * Check if the current site is a governing site.
	 */
	public static function is_governing_site(): bool {
		return 'governing-site' === self::get_current_site_type();
	}

	/**
	 * Check if two URLs belong to the same domain.
	 *
	 * @param string $url1 First URL.
	 * @param string $url2 Second URL.
	 *
	 * @return bool True if both URLs belong to the same domain, false otherwise.
	 */
	public static function is_same_domain( string $url1, string $url2 ): bool {
		$parsed_url1 = wp_parse_url( $url1 );
		$parsed_url2 = wp_parse_url( $url2 );

		if ( ! isset( $parsed_url1['host'] ) || ! isset( $parsed_url2['host'] ) ) {
			return false;
		}

		return $parsed_url1['host'] === $parsed_url2['host'];
	}

	/**
	 * Validate API key for general request.
	 */
	public static function onelogs_validate_api_key(): bool {
		return self::onelogs_key_validation( false );
	}

	/**
	 * Validate API key for health check.
	 */
	public static function onelogs_validate_api_key_health_check(): bool {
		return self::onelogs_key_validation( true );
	}

	/**
	 * Validate API key.
	 *
	 * @param bool $is_health_check Whether the request is for health check or not.
	 */
	public static function onelogs_key_validation( $is_health_check ): bool {
		// check if the request is from same site.
		if ( self::is_governing_site() ) {
			return current_user_can( 'manage_options' );
		}

		// check X-OneLogs-Token header.
		if ( isset( $_SERVER['HTTP_X_ONELOGS_TOKEN'] ) && ! empty( $_SERVER['HTTP_X_ONELOGS_TOKEN'] ) ) {
			$token = sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_ONELOGS_TOKEN'] ) );
			// Get the api key from options.
			$api_key = get_option( Settings::OPTION_CONSUMER_API_KEY, 'default_api_key' );

			// governing site url.
			$governing_site_url = get_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL, '' );

			// check if governing site is set and matches with request origin.
			$request_origin = '';

			if ( ! empty( $_SERVER['HTTP_ORIGIN'] ) ) {
				$request_origin = esc_url_raw( wp_unslash( $_SERVER['HTTP_ORIGIN'] ) );
			} elseif ( ! empty( $_SERVER['HTTP_REFERER'] ) ) {
				$request_origin = esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) );
			}

			$current_site_url = get_site_url();
			$user_agent       = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : ''; // phpcs:ignore WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__HTTP_USER_AGENT__ -- this is to know requesting user domain for request which are generated from server.
			$is_token_valid   = hash_equals( $token, $api_key );
			$is_same_domain   = ! empty( $request_origin ) && self::is_same_domain( $current_site_url, $request_origin );

			// if token is valid and from same domain return true.
			if ( self::is_brand_site() && $is_same_domain && $is_token_valid ) {
				return true;
			}

			// if token is valid and request is from different domain then save it as governing site.
			if ( self::is_brand_site() && ! $is_same_domain && $is_token_valid && empty( $governing_site_url ) && $is_health_check ) {
				update_option( Settings::OPTION_CONSUMER_PARENT_SITE_URL, $request_origin, false );

				return true;
			}

			// if token is valid and request is from different domain then check if it matches governing site url.
			if ( self::is_brand_site() && ! $is_same_domain && $is_token_valid && ! empty( $governing_site_url ) && ( self::is_same_domain( $governing_site_url, $request_origin ) || false !== strpos( $user_agent, $governing_site_url ) ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Get the API key of a shared site by its URL.
	 *
	 * @param string $url The URL to match (can be with/without http/https or trailing slash).
	 *
	 * @return string|false The API key if found, or false if no match.
	 */
	public static function get_shared_site_api_key_by_url( $url ) {
		if ( empty( $url ) ) {
			return false;
		}

		$url = untrailingslashit( esc_url_raw( $url ) );
		if ( empty( $url ) ) {
			return false;
		}

		$shared_sites = get_option( 'onelogs_shared_sites', [] );

		if ( empty( $shared_sites ) || ! is_array( $shared_sites ) ) {
			return false;
		}

		foreach ( $shared_sites as $site ) {
			if (
				! is_array( $site ) ||
				empty( $site['url'] ) ||
				empty( $site['api_key'] )
			) {
				continue;
			}

			$site_url = untrailingslashit( esc_url_raw( $site['url'] ) );

			if ( $site_url && $site_url === $url ) {
				return $site['api_key'];
			}
		}

		return false;
	}

	/**
	 * Get the shared site data by its URL.
	 *
	 * @param string $url The URL to match (can be with/without http/https or trailing slash).
	 *
	 * @return array|false The shared site data if found, or false if no match.
	 */
	public static function get_shared_site_data_by_url( string $url ): bool|array {
		// Get shared sites from options.
		$shared_sites = get_option( 'onelogs_shared_sites', [] );
		if ( empty( $shared_sites ) || ! is_array( $shared_sites ) ) {
			return false;
		}

		// Normalize the input URL for comparison.
		$normalized_input = preg_replace( '#^https?://#', '', trim( strtolower( $url ) ) );
		$normalized_input = untrailingslashit( $normalized_input );

		foreach ( $shared_sites as $site ) {
			if ( empty( $site['siteUrl'] ) ) {
				continue;
			}

			$normalized_site = preg_replace( '#^https?://#', '', trim( strtolower( $site['siteUrl'] ) ) );
			$normalized_site = untrailingslashit( $normalized_site );

			if ( $normalized_site === $normalized_input ) {
				return $site;
			}
		}

		return false;
	}
}
