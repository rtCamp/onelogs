<?php
/**
 * The main plugin file.
 *
 * @package OneLogs
 */

declare( strict_types = 1 );

namespace OneLogs;

use OneLogs\Contracts\Traits\Singleton;

/**
 * Class - Main
 */
final class Main {
	use Singleton;

	/**
	 * Registrable classes are entrypoints that "hook" into WordPress.
	 * They should implement the Registrable interface.
	 *
	 * @var class-string<\OneLogs\Contracts\Interfaces\Registrable>[]
	 */
	private const REGISTRABLE_CLASSES = [
		Modules\Core\Assets::class,
		Modules\Core\Rest::class,
		Modules\Settings\Admin::class,
		Modules\Settings\Settings::class,
		Modules\Rest\Basic_Options_Controller::class,
		Modules\Rest\Logs_REST_Controller::class,
		Modules\Logs\Admin::class,
	];

	/**
	 * {@inheritDoc}
	 */
	public static function instance(): self {
		if ( ! isset( self::$instance ) ) {
			self::$instance = new self();
			self::$instance->setup();
		}

		return self::$instance;
	}

	/**
	 * Setup the plugin.
	 */
	private function setup(): void {
		// Ensure all dependencies are met before loading any functionality.
		$dependencies_ready = Dependencies::is_ready();
		if ( ! $dependencies_ready ) {
			return;
		}

		// Load the plugin classes.
		$this->load();

		// Do other stuff here like dep-checking, telemetry, etc.
	}

	/**
	 * Load the plugin classes.
	 */
	private function load(): void {
		foreach ( self::REGISTRABLE_CLASSES as $class_name ) {
			$instance = new $class_name();
			$instance->register_hooks();
		}

		// Do other generalizable stuff here.
	}
}
