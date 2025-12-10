<?php
/**
 * Shared static utility functions.
 *
 * @package OneLogs
 */

namespace OneLogs;

/**
 * Class Utils
 */
final class Utils {

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
			if ( empty( $site['url'] ) ) {
				continue;
			}

			$site_url = untrailingslashit( esc_url_raw( $site['url'] ) );

			if ( $site_url && $site_url === $url ) {
				return $site;
			}
		}

		return false;
	}
}
