<?php
/**
 * Provide a base class for all unit tests by extending WP_UnitTestCase.
 *
 * @package OneLogs\Tests
 */

declare( strict_types = 1 );

namespace OneLogs\Tests;

use WP_UnitTestCase;

/**
 * Class - TestCase
 */
abstract class TestCase extends WP_UnitTestCase {
	/**
	 * {@inheritDoc}
	 *
	 * Prevents wp-phpunit failures with PHPUnit 11.5.
	 *
	 * @return array<string, array<string, list<string>>>
	 */
	public function getAnnotations(): array { // phpcs:ignore WordPress.NamingConventions.ValidFunctionName.MethodNameInvalid -- Required compatibility method name.
		$class_reflection  = new \ReflectionClass( static::class );
		$method_name       = $this->name();
		$method_reflection = $class_reflection->hasMethod( $method_name )
			? $class_reflection->getMethod( $method_name )
			: null;

		return [
			'class'  => self::parse_docblock_annotations( $class_reflection->getDocComment() ?: '' ),
			'method' => self::parse_docblock_annotations( $method_reflection?->getDocComment() ?: '' ),
		];
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $docblock Source docblock.
	 *
	 * @return array<string, list<string>>
	 */
	private static function parse_docblock_annotations( string $docblock ): array {
		if ( '' === trim( $docblock ) ) {
			return [];
		}

		$annotations = [];
		$tags        = [
			'ticket',
			'group',
			'expectedDeprecated',
			'expectedIncorrectUsage',
		];

		foreach ( $tags as $tag ) {
			$matches = [];
			preg_match_all( '/^[ \\t\\*]*@' . preg_quote( $tag, '/' ) . '\\s+([^\\r\\n\\*]+)/mi', $docblock, $matches );

			if ( ! empty( $matches[1] ) ) {
				$annotations[ $tag ] = array_values(
					array_filter(
						array_map( 'trim', $matches[1] ),
						static fn ( string $value ): bool => '' !== $value
					)
				);
			}
		}

		return $annotations;
	}

	/**
	 * Prevent inheriting doc-block
	 *
	 * @deprecated
	 */
	protected function checkRequirements(): void { // phpcs:ignore Generic.CodeAnalysis.UselessOverridingMethod.Found
		parent::checkRequirements();
	}

	/**
	 * Replaces the REST server with one that only has the given controllers' routes.
	 *
	 * The plugin only loads with Stream active, so tests register the routes they need. Set the site type first, since
	 * routes are registered by site type.
	 *
	 * @param \OneLogs\Modules\Rest\Abstract_REST_Controller[] $controllers The controllers to register.
	 */
	protected function set_up_rest_server( array $controllers ): \WP_REST_Server {
		global $wp_rest_server;

		// Avoid registering core and other plugin routes.
		remove_all_actions( 'rest_api_init' );

		// The settings are read through /wp/v2/settings in some flows, and are registered on rest_api_init.
		add_action( 'rest_api_init', [ new \OneLogs\Modules\Settings\Settings(), 'register_settings' ] );

		foreach ( $controllers as $controller ) {
			add_action( 'rest_api_init', [ $controller, 'register_routes' ] );
		}

		$wp_rest_server = new \WP_REST_Server(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		do_action( 'rest_api_init', $wp_rest_server ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- Core hook.

		return $wp_rest_server;
	}

	/**
	 * Answers outgoing HTTP requests with a callback instead of the network, and records them.
	 *
	 * @param callable $responder Returns the response for a URL and request arguments.
	 * @param ?array   $requests  Collects the requests.
	 *
	 * @phpstan-param callable(string, array<string,mixed>): (array<string,mixed>|\WP_Error) $responder
	 * @phpstan-param ?array<int,array{url: string, args: array<string,mixed>}> $requests
	 * @param-out array<int,array{url: string, args: array<string,mixed>}> $requests
	 */
	protected function mock_remote_requests( callable $responder, ?array &$requests = null ): void {
		$requests = [];

		add_filter(
			'pre_http_request',
			static function ( $preempt, $args, $url ) use ( $responder, &$requests ) { // phpcs:ignore SlevomatCodingStandard.Functions.UnusedParameter.UnusedParameter -- The filter's signature.
				$requests[] = [
					'url'  => (string) $url,
					'args' => (array) $args,
				];

				return $responder( (string) $url, (array) $args );
			},
			10,
			3
		);
	}

	/**
	 * Builds a fake HTTP response like `wp_remote_request()` returns.
	 *
	 * @param int                 $code The status code.
	 * @param array<mixed>|string $body The body. Arrays are JSON-encoded.
	 *
	 * @return array<string,mixed>
	 */
	protected static function http_response( int $code, array|string $body = [] ): array {
		return [
			'headers'  => [],
			'body'     => is_array( $body ) ? (string) wp_json_encode( $body ) : $body,
			'response' => [
				'code'    => $code,
				'message' => get_status_header_desc( $code ),
			],
			'cookies'  => [],
			'filename' => null,
		];
	}
}
