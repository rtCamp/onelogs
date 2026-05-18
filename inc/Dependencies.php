<?php

declare(strict_types = 1);

/**
 * Dependency checker for OneLogs.
 *
 * @package OneLogs
 */

namespace OneLogs;

/**
 * Class Dependencies
 */
final class Dependencies {
	/**
	 * Required plugins.
	 *
	 * @var array<string, array{
	 *   class: string,
	 *   url:string
	 * }>
	 */
	private const REQUIRED_PLUGINS = [
		'Stream' => [
			'class' => 'WP_Stream\Plugin',
			'url'   => 'https://wordpress.org/plugins/stream/',
			// @todo Add minimum version check.
		],
	];

	/**
	 * Check if all required plugins are installed and active.
	 */
	public static function is_ready(): bool {

		include_once ABSPATH . 'wp-admin/includes/plugin.php';

		$ready = true;

		// Check each dependency.
		foreach ( self::REQUIRED_PLUGINS as $name => $data ) {
			if ( class_exists( $data['class'] ) ) {
				continue;
			}

			$ready = false;
			// Always register the notice.
			self::show_admin_notice( $name, $data['url'] );
		}

		return $ready;
	}

	/**
	 * Display admin notice if dependency missing.
	 *
	 * @param string $plugin_name Plugin name.
	 * @param string $plugin_url  Plugin URL.
	 */
	private static function show_admin_notice( string $plugin_name, string $plugin_url ): void {

		$hooks = [
			'admin_notices',
			'network_admin_notices',
		];

		foreach ( $hooks as $hook ) {
			add_action(
				$hook,
				static function () use ( $plugin_name, $plugin_url ): void {
					$screen = get_current_screen();

					// Only show notice on dashboard and plugins page.
					if ( ! $screen || ! in_array( $screen->id, [ 'dashboard', 'plugins', 'plugins-network' ], true ) ) {
						return;
					}

					$error_message = sprintf(
					/* translators: 1: Plugin name, 2: Plugin URL */
						__( 'OneLogs requires the <a href="%2$s" target="_blank">%1$s</a> plugin to be installed and active to work. Please install and activate it.', 'onelogs' ),
						esc_html( $plugin_name ),
						esc_url( $plugin_url )
					);

					wp_admin_notice(
						$error_message,
						[
							'type'        => 'error',
							'dismissible' => true,
						]
					);
				}
			);
		}
	}
}
