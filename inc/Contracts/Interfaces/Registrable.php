<?php
/**
 * Interface for Registrable classes.
 *
 * Registrable classes are those that register hooks (actions/filters) with WordPress.
 *
 * @package OneLogs
 */

declare( strict_types=1 );

namespace OneLogs\Contracts\Interfaces;

/**
 * Interface - Registrable
 */
interface Registrable {

	/**
	 * Registers class methods to WordPress.
	 *
	 * WordPress actions/filters should be included here.
	 */
	public function register_hooks(): void;
}
