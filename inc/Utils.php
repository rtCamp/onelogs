<?php
/**
 * Shared utilities functions for internal plugin use.
 *
 * Utilities are a domain antipattern - where ever possible, colocate methods with their related classes.
 *
 * @package OneLogs
 */

declare(strict_types = 1);

namespace OneLogs;

/**
 * Class - Utils
 */
final class Utils {
	/**
	 * Normalize a URL so different spellings of the same site URL compare equal.
	 *
	 * Trims whitespace, lowercases the scheme and host (which are case-insensitive), and ensures a trailing slash.
	 *
	 * @param string $url The URL to normalize.
	 */
	public static function normalize_url( string $url ): string {
		$url = trim( $url );

		// Lowercase everything up to the path, e.g. `HTTPS://Example.COM/Path` => `https://example.com/Path`.
		$url = (string) preg_replace_callback(
			'#^([a-z][a-z0-9+.-]*://[^/?\#]*)#i',
			static fn ( array $matches ): string => strtolower( $matches[1] ),
			$url
		);

		return (string) trailingslashit( $url );
	}

	/**
	 * Gets the normalized URL that identifies the current site across the network.
	 *
	 * Uses `get_option( 'home' )` since `get_home_url()` changes the scheme per the current request.
	 */
	public static function get_current_site_url(): string {
		return self::normalize_url( (string) get_option( 'home' ) );
	}

	/**
	 * Logs a message to the PHP error log when debugging is enabled.
	 *
	 * @param string $message The message to log. Will be prefixed with the plugin name.
	 */
	public static function log( string $message ): void {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug-only logging.
		error_log( 'OneLogs: ' . $message );
	}
}
