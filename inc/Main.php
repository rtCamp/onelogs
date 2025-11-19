<?php
/**
 * The main plugin file.
 *
 * @package OneLogs
 */

declare( strict_types=1 );

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
		Modules\Core\Settings::class,
		Modules\Admin\Admin::class,
		Modules\Admin\Logs_REST_Controller::class,
		Modules\Admin\API_Key_REST_Controller::class,
		Modules\Plugin_Configs\Secret_Key::class, // Secret_Key should be called before Basic_Options.
		Modules\Admin\Basic_Options::class,
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
		// Loop through all the classes, instantiate them, and register any hooks.
		foreach ( self::REGISTRABLE_CLASSES as $class_name ) {
			/**
			 * If it's a singleton, we can use the instance method. Otherwise we instantiate it directly.
			 */
			$instance = method_exists( $class_name, 'instance' ) ? $class_name::instance() : new $class_name();

			// Hooks should be registered outside of the constructor.
			if ( ! $instance instanceof Contracts\Interfaces\Registrable ) {
				continue;
			}

			$instance->register_hooks();
		}
	}
}
