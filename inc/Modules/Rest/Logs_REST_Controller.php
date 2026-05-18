<?php

declare(strict_types = 1);

/**
 * REST API Controller for managing WordPress Stream logs.
 *
 * This file contains the Logs_REST_Controller class which provides REST API endpoints
 * to access and filter WordPress Stream plugin logs. It supports pagination, filtering,
 * searching, and individual log retrieval with metadata.
 *
 * @package OneLogs\Modules\Admin
 * @since   1.0.0
 */

namespace OneLogs\Modules\Rest;

use OneLogs\Utils;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API Controller for managing WordPress Stream logs.
 *
 * This class provides REST API endpoints to access and filter WordPress Stream plugin logs.
 * It supports pagination, filtering, searching, and individual log retrieval with metadata.
 *
 * @package Onelogs\Modules\Admin
 * @since   1.0.0
 */
final class Logs_REST_Controller extends Abstract_REST_Controller {
	/**
	 * Maximum number of results per page.
	 *
	 * @var int
	 */
	private const MAX_PER_PAGE = 100;

	/**
	 * Default number of results per page.
	 *
	 * @var int
	 */
	private const DEFAULT_PER_PAGE = 20;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rest_base = 'logs';
	}

	/**
	 * Register WordPress hooks.
	 */
	public function register_hooks(): void {
		add_action( 'rest_api_init', [ $this, 'register_routes' ] );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/' . $this->rest_base,
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_logs' ],
					'permission_callback' => [ $this, 'check_api_permissions' ],
					'args'                => $this->get_logs_args_schema(),
				],
			]
		);

		register_rest_route(
			self::NAMESPACE,
			'/' . $this->rest_base . '/(?P<id>\d+)',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_log' ],
					'permission_callback' => [ $this, 'check_api_permissions' ],
					'args'                => [
						'id' => [
							'type'     => 'integer',
							'required' => true,
							'minimum'  => 1,
						],
					],
				],
			]
		);

		// Endpoint for available contexts.
		register_rest_route(
			self::NAMESPACE,
			'/' . $this->rest_base . '/contexts',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_contexts' ],
					'permission_callback' => [ $this, 'check_api_permissions' ],
				],
			]
		);

		// Endpoint for available connectors.
		register_rest_route(
			self::NAMESPACE,
			'/' . $this->rest_base . '/connectors',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_connectors' ],
					'permission_callback' => [ $this, 'check_api_permissions' ],
				],
			]
		);

		// Endpoint for users.
		register_rest_route(
			self::NAMESPACE,
			'/' . $this->rest_base . '/users',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_users' ],
					'permission_callback' => [ $this, 'check_api_permissions' ],
					'args'                => [
						'search' => [
							'type'              => 'string',
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => [ $this, 'validate_search_term' ],
						],
					],
				],
			]
		);

		// Endpoint for available actions.
		register_rest_route(
			self::NAMESPACE,
			'/' . $this->rest_base . '/actions',
			[
				[
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => [ $this, 'get_actions' ],
					'permission_callback' => [ $this, 'check_api_permissions' ],
				],
			]
		);
	}

	/**
	 * Get the schema for GET /logs endpoint arguments.
	 *
	 * @return array Argument schema.
	 */
	private function get_logs_args_schema(): array {
		return [
			'page'                 => [
				'type'              => 'integer',
				'default'           => 1,
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => [ $this, 'validate_positive_integer' ],
			],
			'per_page'             => [
				'type'              => 'integer',
				'default'           => self::DEFAULT_PER_PAGE,
				'minimum'           => 1,
				'maximum'           => self::MAX_PER_PAGE,
				'sanitize_callback' => 'absint',
				'validate_callback' => [ $this, 'validate_positive_integer' ],
			],
			'orderby'              => [
				'type'              => 'string',
				'default'           => 'created',
				'enum'              => [
					'ID',
					'site_id',
					'blog_id',
					'user_id',
					'object_id',
					'connector',
					'context',
					'action',
					'summary',
					'created',
					'ip',
				],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'order'                => [
				'type'              => 'string',
				'default'           => 'desc',
				'enum'              => [ 'asc', 'desc' ],
				'sanitize_callback' => 'sanitize_text_field',
			],
			'site_id'              => [
				'type'              => 'integer',
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => [ $this, 'validate_positive_integer' ],
			],
			'blog_id'              => [
				'type'              => 'integer',
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => [ $this, 'validate_positive_integer' ],
			],
			'user_id'              => [
				'type'              => 'integer',
				'minimum'           => 1,
				'sanitize_callback' => 'absint',
				'validate_callback' => [ $this, 'validate_positive_integer' ],
			],
			'connector'            => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ $this, 'validate_connector' ],
			],
			'context'              => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'site_url'             => [
				'type'              => 'string',
				'sanitize_callback' => 'esc_url_raw',
				'validate_callback' => [ $this, 'validate_url' ],
			],
			'action'               => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
			],
			'search'               => [
				'type'              => 'string',
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => [ $this, 'validate_search_term' ],
			],
			'include_shared_sites' => [
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			],
			'exclude_current_site' => [
				'type'              => 'boolean',
				'default'           => false,
				'sanitize_callback' => 'rest_sanitize_boolean',
			],
			'date_from'            => [
				'type'              => 'string',
				'format'            => 'date-time',
				'sanitize_callback' => [ $this, 'sanitize_datetime' ],
				'validate_callback' => [ $this, 'validate_datetime' ],
			],
			'date_to'              => [
				'type'              => 'string',
				'format'            => 'date-time',
				'sanitize_callback' => [ $this, 'sanitize_datetime' ],
				'validate_callback' => [ $this, 'validate_datetime' ],
			],
		];
	}

	/**
	 * Validate positive integer values.
	 *
	 * @param mixed $value The value to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_positive_integer( $value ): bool {
		return is_numeric( $value ) && (int) $value > 0;
	}

	/**
	 * Validate connector value.
	 *
	 * @param mixed $value The value to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_connector( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$result = [];

		global $wpdb;
		$stream_table = $wpdb->prefix . 'stream';

		$sql = $wpdb->prepare(
			'SELECT DISTINCT connector FROM %i WHERE connector = %s LIMIT 1',
			$stream_table,
			$value
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_var( $sql );

		return $result === $value;
	}

	/**
	 * Validate context value.
	 *
	 * @param mixed $value The value to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_context( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$result = [];

		global $wpdb;
		$stream_table = $wpdb->prefix . 'stream';

		$sql = $wpdb->prepare(
			'SELECT DISTINCT context FROM %i WHERE context = %s LIMIT 1',
			$stream_table,
			$value
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_var( $sql );

		return $result === $value;
	}

	/**
	 * Validate action value.
	 *
	 * @param mixed $value The value to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_action( $value ): bool {
		if ( ! is_string( $value ) ) {
			return false;
		}

		$result = [];

		global $wpdb;
		$stream_table = $wpdb->prefix . 'stream';

		$sql = $wpdb->prepare(
			'SELECT DISTINCT action FROM %i WHERE action = %s LIMIT 1',
			$stream_table,
			$value
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_var( $sql );

		return $result === $value;
	}

	/**
	 * Validate URL value.
	 *
	 * @param mixed $value The value to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_url( $value ): bool {
		return is_string( $value ) && ! empty( $value ) && filter_var( $value, FILTER_VALIDATE_URL );
	}

	/**
	 * Validate search term.
	 *
	 * @param mixed $value The value to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_search_term( $value ): bool {
		return is_string( $value ) && strlen( trim( $value ) ) > 0 && strlen( $value ) <= 255;
	}

	/**
	 * Validate datetime string.
	 *
	 * @param mixed $value The value to validate.
	 *
	 * @return bool True if valid, false otherwise.
	 */
	public function validate_datetime( $value ): bool {
		$timestamp = rest_parse_date( $value );

		return false !== $timestamp;
	}

	/**
	 * Sanitize datetime string.
	 *
	 * @param string $value The datetime string to sanitize.
	 *
	 * @return string Sanitized datetime string.
	 */
	public function sanitize_datetime( $value ): string {
		$value     = sanitize_text_field( $value );
		$timestamp = rest_parse_date( $value );

		if ( false === $timestamp ) {
			return '';
		}

		return gmdate( 'Y-m-d H:i:s', $timestamp );
	}

	/**
	 * Build WHERE clauses for SQL query.
	 *
	 * @param array $args     The query arguments.
	 * @param array $sql_args Reference to SQL arguments array.
	 *
	 * @return array WHERE clauses.
	 */
	private function build_where_clauses( array $args, array &$sql_args ): array {
		$where_clauses = [];

		if ( ! empty( $args['site_id'] ) ) {
			$where_clauses[] = 'site_id = %d';
			$sql_args[]      = $args['site_id'];
		}

		if ( ! empty( $args['blog_id'] ) ) {
			$where_clauses[] = 'blog_id = %d';
			$sql_args[]      = $args['blog_id'];
		}

		if ( ! empty( $args['user_id'] ) ) {
			$where_clauses[] = 'user_id = %d';
			$sql_args[]      = $args['user_id'];
		}

		if ( ! empty( $args['connector'] ) ) {
			$where_clauses[] = 'connector = %s';
			$sql_args[]      = $args['connector'];
		}

		if ( ! empty( $args['context'] ) ) {
			$where_clauses[] = 'context = %s';
			$sql_args[]      = $args['context'];
		}

		if ( ! empty( $args['action'] ) ) {
			$where_clauses[] = 'action = %s';
			$sql_args[]      = $args['action'];
		}

		if ( ! empty( $args['search'] ) ) {
			$where_clauses[] = '(summary LIKE %s OR object_id LIKE %s)';
			global $wpdb; // Add global $wpdb to access esc_like function.
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$sql_args[]  = $search_term;
			$sql_args[]  = $search_term;
		}

		if ( ! empty( $args['date_from'] ) ) {
			$where_clauses[] = 'created >= %s';
			$sql_args[]      = $args['date_from'];
		}

		if ( ! empty( $args['date_to'] ) ) {
			$where_clauses[] = 'created <= %s';
			$sql_args[]      = $args['date_to'];
		}

		return $where_clauses;
	}

	/**
	 * Format log data for API response.
	 *
	 * @param object{ID: int, site_id: int, blog_id: int, user_id: int, user_role: string, object_id: int|null, connector: string, context: string, action: string, summary: string, created: string, ip: string} $result Database result object.
	 * @param array                                                                                                                                                                                               $meta   Optional metadata array.
	 *
	 * @return array Formatted log data.
	 */
	private function format_log_data( $result, array $meta = [] ): array {

		$user_data = null;

		if ( ! empty( $result->user_id ) ) {
			$user = get_userdata( (int) $result->user_id );

			if ( $user ) {
				$user_data = [
					'id'           => (int) $user->ID,
					'display_name' => $user->display_name,
					'user_email'   => $user->user_email,
					'avatar_url'   => get_avatar_url( $user->ID ),
					'roles'        => $user->roles,
				];
			}
		}
		// Suppress object data for trashed items of trashable contexts.
		$trashable_contexts          = [ 'post', 'page', 'attachment' ];
		$should_populate_object_data = ! ( in_array( $result->context, $trashable_contexts, true ) && 'trashed' === $result->action );

		$object_type  = $should_populate_object_data ? $this->get_custom_object_type( $result->connector, $result->context ) : '';
		$object_data  = $should_populate_object_data ? $this->get_object_data( $object_type, $result->object_id, $result->connector, $result->context ) : null;
		$action_title = $should_populate_object_data ? $this->get_action_title( $result->action, $object_type, $result->context ) : '';

		return [
			'ID'           => (int) $result->ID,
			'site_id'      => (int) $result->site_id,
			'blog_id'      => (int) $result->blog_id,
			'user_id'      => (int) $result->user_id,
			'user_role'    => $result->user_role,
			'object_id'    => $result->object_id ? (int) $result->object_id : null,
			'connector'    => $result->connector,
			'context'      => $result->context,
			'action'       => $result->action,
			'summary'      => wp_kses_post( html_entity_decode( $result->summary, ENT_QUOTES, 'UTF-8' ) ),
			'created'      => $result->created,
			'ip'           => $result->ip,
			'meta'         => $meta,
			'user'         => $user_data,
			'object_type'  => $object_type,
			'object_data'  => $object_data,
			'action_title' => $action_title,
		];
	}

	/**
	 * Get object type from connector and context
	 *
	 * @param string $connector The connector name.
	 * @param string $context   The context name.
	 */
	private function get_custom_object_type( string $connector, string $context ): string {
		// Map connector/context to object types.
		$type_map = [
			'posts'      => 'post',
			'pages'      => 'page',
			'media'      => 'media',
			'comments'   => 'comment',
			'users'      => 'user',
			'taxonomies' => 'term',
			'widgets'    => 'widget',
			'menus'      => 'menu',
			'plugins'    => 'plugin',
			'themes'     => 'theme',
			'settings'   => 'setting',
		];

		return $type_map[ $connector ] ?? $connector;
	}

	/**
	 * Get object data including edit and view links
	 *
	 * @param string   $object_type The type of the object.
	 * @param int|null $object_id   The ID of the object.
	 * @param string   $connector   The connector name.
	 * @param string   $context     The context name.
	 */
	private function get_object_data( string $object_type, int|null $object_id, string $connector, string $context ): ?array {
		if ( ! $object_id ) {
			return null;
		}

		$data = [
			'id'             => $object_id,
			'edit_link'      => null,
			'view_link'      => null,
			'edit_link_text' => null,
			'view_link_text' => null,
			'title'          => null,
			'status'         => null,
		];

		switch ( $object_type ) {
			case 'post':
				$post = get_post( $object_id );
				if ( $post ) {
					$data['title']          = $post->post_title;
					$data['status']         = $post->post_status;
					$data['edit_link']      = get_edit_post_link( $object_id, 'raw' );
					$data['view_link']      = get_permalink( $object_id );
					$data['edit_link_text'] = 'Edit Post';
					$data['view_link_text'] = 'View Post';

					// Fallback for edit link.
					if ( empty( $data['edit_link'] ) ) {
						$data['edit_link'] = admin_url( 'post.php?post=' . $object_id . '&action=edit' );
					}
				}
				break;

			case 'page':
				$post = get_post( $object_id );
				if ( $post ) {
					$data['title']          = $post->post_title;
					$data['status']         = $post->post_status;
					$data['edit_link']      = get_edit_post_link( $object_id, 'raw' );
					$data['view_link']      = get_permalink( $object_id );
					$data['edit_link_text'] = 'Edit Page';
					$data['view_link_text'] = 'View Page';

					// Fallback for edit link.
					if ( empty( $data['edit_link'] ) ) {
						$data['edit_link'] = admin_url( 'post.php?post=' . $object_id . '&action=edit' );
					}
				}
				break;

			case 'media':
				$post = get_post( $object_id );
				if ( $post && 'attachment' === $post->post_type ) {
					$data['title']          = $post->post_title;
					$data['edit_link']      = get_edit_post_link( $object_id, 'raw' );
					$data['view_link']      = wp_get_attachment_url( $object_id );
					$data['edit_link_text'] = 'Edit Media';
					$data['view_link_text'] = 'View Media';

					// Fallback for edit link.
					if ( empty( $data['edit_link'] ) ) {
						$data['edit_link'] = admin_url( 'post.php?post=' . $object_id . '&action=edit' );
					}
				}
				break;

			case 'comment':
				$comment = get_comment( $object_id );
				if ( $comment ) {
					$data['title']          = wp_trim_words( $comment->comment_content, 10 );
					$data['status']         = $comment->comment_approved;
					$data['edit_link']      = get_edit_comment_link( $object_id );
					$data['view_link']      = get_comment_link( $object_id );
					$data['edit_link_text'] = 'Edit Comment';
					$data['view_link_text'] = 'View Comment';

					// Fallback for edit link.
					if ( empty( $data['edit_link'] ) ) {
						$data['edit_link'] = admin_url( 'comment.php?action=editcomment&c=' . $object_id );
					}
				}
				break;

			case 'user':
				$user = get_userdata( $object_id );
				if ( $user ) {
					$data['title']          = $user->display_name;
					$data['edit_link']      = get_edit_user_link( $object_id );
					$data['view_link']      = get_author_posts_url( $object_id );
					$data['edit_link_text'] = 'Edit User';
					$data['view_link_text'] = 'View User';

					// Fallback for edit link.
					if ( empty( $data['edit_link'] ) ) {
						$data['edit_link'] = admin_url( 'user-edit.php?user_id=' . $object_id );
					}
				}
				break;

			case 'term':
				$term = get_term( $object_id );
				if ( $term && ! is_wp_error( $term ) ) {
					$data['title']          = $term->name;
					$data['edit_link']      = get_edit_term_link( $object_id, $term->taxonomy );
					$data['view_link']      = get_term_link( $object_id );
					$data['edit_link_text'] = 'Edit Term';
					$data['view_link_text'] = 'View Term';

					// Fallback for edit link.
					if ( empty( $data['edit_link'] ) || is_wp_error( $data['edit_link'] ) ) {
						$data['edit_link'] = admin_url( 'term.php?taxonomy=' . $term->taxonomy . '&tag_ID=' . $object_id );
					}

					// Fallback for view link.
					if ( empty( $data['view_link'] ) || is_wp_error( $data['view_link'] ) ) {
						$data['view_link'] = null;
					}
				}
				break;

			case 'menu':
				$menu = wp_get_nav_menu_object( $object_id );
				if ( $menu ) {
					$data['title']          = $menu->name;
					$data['edit_link']      = admin_url( 'nav-menus.php?action=edit&menu=' . $object_id );
					$data['edit_link_text'] = 'Edit Menu';
				}
				break;

			case 'plugin':
				$data['title']          = $context;
				$data['edit_link']      = admin_url( 'plugins.php' );
				$data['edit_link_text'] = 'View Plugins';
				break;

			case 'theme':
				$data['title']          = $context;
				$data['edit_link']      = admin_url( 'themes.php' );
				$data['edit_link_text'] = 'View Themes';
				break;

			case 'widget':
				$data['edit_link']      = admin_url( 'widgets.php' );
				$data['edit_link_text'] = 'Edit Widgets';
				break;

			case 'setting':
				$data['title']          = $context;
				$data['edit_link']      = admin_url( 'options-general.php' );
				$data['edit_link_text'] = 'Edit Settings';
				break;
		}

		return $data;
	}

	/**
	 * Get human-readable action title
	 *
	 * @param string $action      The action performed.
	 * @param string $object_type The type of the object.
	 * @param string $context     The context of the action.
	 */
	private function get_action_title( string $action, string $object_type, string $context ): string {
		// Action verb mapping.
		$action_verbs = [
			'created'      => 'Created',
			'updated'      => 'Updated',
			'deleted'      => 'Deleted',
			'trashed'      => 'Trashed',
			'restored'     => 'Restored',
			'published'    => 'Published',
			'drafted'      => 'Drafted',
			'approved'     => 'Approved',
			'unapproved'   => 'Unapproved',
			'spammed'      => 'Marked as Spam',
			'unspammed'    => 'Unmarked as Spam',
			'activated'    => 'Activated',
			'deactivated'  => 'Deactivated',
			'installed'    => 'Installed',
			'uninstalled'  => 'Uninstalled',
			'logged_in'    => 'Logged In',
			'logged_out'   => 'Logged Out',
			'failed_login' => 'Failed Login',
		];

		// Object type labels.
		$object_labels = [
			'post'    => 'Post',
			'page'    => 'Page',
			'media'   => 'Media',
			'comment' => 'Comment',
			'user'    => 'User',
			'term'    => 'Term',
			'widget'  => 'Widget',
			'menu'    => 'Menu',
			'plugin'  => 'Plugin',
			'theme'   => 'Theme',
			'setting' => 'Setting',
		];

		$action_verb  = $action_verbs[ $action ] ?? ucfirst( str_replace( '_', ' ', $action ) );
		$object_label = $object_labels[ $object_type ] ?? ucfirst( $object_type );

		// Special cases where we don't need object type.
		$standalone_actions = [ 'logged_in', 'logged_out', 'failed_login' ];
		if ( in_array( $action, $standalone_actions, true ) ) {
			return $action_verb;
		}

		return sprintf( '%s %s', $action_verb, $object_label );
	}

	/**
	 * Get logs from database with filtering and pagination.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response Response object or error.
	 */
	public function get_logs( WP_REST_Request $request ) {
		$params = $request->get_params();

		$page                 = filter_var(
			$params['page'] ?? 1,
			FILTER_VALIDATE_INT,
			[
				'options' => [
					'default'   => 1,
					'min_range' => 1,
				],
			]
		);
		$per_page             = filter_var(
			$params['per_page'] ?? self::DEFAULT_PER_PAGE,
			FILTER_VALIDATE_INT,
			[
				'options' => [
					'default'   => self::DEFAULT_PER_PAGE,
					'min_range' => 1,
					'max_range' => 100,
				],
			]
		);
		$include_shared_sites = filter_var( $params['include_shared_sites'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$include_current_site = filter_var( $params['current_site_logs'] ?? false, FILTER_VALIDATE_BOOLEAN );
		$brand_site           = filter_var( $params['site_url'] ?? '', FILTER_VALIDATE_URL ) ?: '';

		$all_logs    = [];
		$errors      = [];
		$total_count = 0;

		if ( $include_current_site ) {
			$local_logs_result = $this->get_local_logs( $request, false );

			if ( is_wp_error( $local_logs_result ) ) {
				$errors[] = [
					'site'  => __( 'Current Site', 'onelogs' ),
					'error' => $local_logs_result->get_error_message(),
				];
			} else {
				$all_logs     = array_merge( $all_logs, $local_logs_result['logs'] );
				$total_count += (int) $local_logs_result['total'];
			}
		}

		if ( $include_shared_sites ) {
			$request_params                         = $params;
			$request_params['include_shared_sites'] = false;
			$request_params['current_site_logs']    = true;
			$response                               = $this->onelogs_remote_request( $brand_site, 'onelogs/v1/logs', $request_params );

			foreach ( $response['data'] as &$log ) {
				$site_info        = Utils::get_shared_site_data_by_url( $brand_site );
				$log['site_name'] = $site_info['name'] ?? $brand_site;
				$log['site_url']  = $site_info['name'] ?? $brand_site;
				$log['is_remote'] = true;
			}

			return new WP_REST_Response( $response, 200 );
		}

		usort(
			$all_logs,
			static function ( $a, $b ) {
				return strtotime( $b['created'] ?? '' ) <=> strtotime( $a['created'] ?? '' );
			}
		);

		$total_pages = max( 1, ceil( $total_count / $per_page ) );

		$response = [
			'status'  => 'success',
			'data'    => $all_logs,
			'meta'    => [
				'total'       => (int) $total_count,
				'page'        => $page,
				'per_page'    => $per_page,
				'total_pages' => $total_pages,
				'errors'      => $errors,
			],
			'message' => __( 'Logs fetched successfully.', 'onelogs' ),
		];

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Get local logs from current site.
	 *
	 * @param \WP_REST_Request $request      The request object.
	 * @param bool             $return_count Whether to return count or just logs.
	 *
	 * @return array|\WP_Error Response array or error.
	 */
	private function get_local_logs( WP_REST_Request $request, bool $return_count = true ): array|\WP_Error {
		global $wpdb;

		$params = $request->get_params();

		$page     = filter_var(
			$params['page'] ?? 1,
			FILTER_VALIDATE_INT,
			[
				'options' => [
					'default'   => 1,
					'min_range' => 1,
				],
			]
		);
		$per_page = filter_var(
			$params['per_page'] ?? self::DEFAULT_PER_PAGE,
			FILTER_VALIDATE_INT,
			[
				'options' => [
					'default'   => self::DEFAULT_PER_PAGE,
					'min_range' => 1,
					'max_range' => 100,
				],
			]
		);
		$orderby  = sanitize_text_field( $params['orderby'] ?? 'created' );
		$order    = strtoupper( sanitize_text_field( $params['order'] ?? 'DESC' ) );

		$stream_table = $wpdb->prefix . 'stream';
		$sql          = "SELECT * FROM {$stream_table} WHERE 1=1";
		$sql_args     = [];

		$where_clauses = $this->build_where_clauses( $params, $sql_args );

		if ( ! empty( $where_clauses ) ) {
			$sql .= ' AND ' . implode( ' AND ', $where_clauses );
		}

		$allowed_fields  = [
			'ID',
			'site_id',
			'blog_id',
			'user_id',
			'object_id',
			'connector',
			'context',
			'action',
			'summary',
			'created',
			'ip',
		];
		$orderby_field   = in_array( $orderby, $allowed_fields, true ) ? $orderby : 'created';
		$order_direction = 'ASC' === $order ? 'ASC' : 'DESC';

		$sql .= " ORDER BY {$orderby_field} {$order_direction}";

		$count_sql = "SELECT COUNT(*) FROM {$stream_table} WHERE 1=1";
		if ( ! empty( $where_clauses ) ) {
			$count_sql .= ' AND ' . implode( ' AND ', $where_clauses );
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$total = (int) $wpdb->get_var( $wpdb->prepare( $count_sql, $sql_args ) );

		if ( ! $return_count ) {
			$offset     = ( $page - 1 ) * $per_page;
			$sql       .= ' LIMIT %d OFFSET %d';
			$sql_args[] = $per_page;
			$sql_args[] = $offset;
		}

		/**
		 * We require to perform the direct query here to get distinct logs. Stream does not have a dedicated method for this.
		 * We have not added caching here as logs can change frequently.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$results = $wpdb->get_results( $wpdb->prepare( $sql, $sql_args ) );

		if ( false === $results ) {
			return new \WP_Error(
				'database_error',
				__( 'Failed to retrieve logs from database.', 'onelogs' ),
				[ 'status' => 500 ]
			);
		}

		$logs = array_map( [ $this, 'format_log_data' ], $results );

		return [
			'logs'  => $logs,
			'total' => $total,
		];
	}

	/**
	 * Get a single log by ID.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object or error.
	 */
	public function get_log( WP_REST_Request $request ) {
		global $wpdb;

		$log_id = filter_var( $request->get_param( 'id' ), FILTER_VALIDATE_INT );

		if ( ! $this->validate_positive_integer( $log_id ) ) {
			return new \WP_Error(
				'invalid_log_id',
				__( 'Invalid log ID provided.', 'onelogs' ),
				[ 'status' => 400 ]
			);
		}

		$stream_table      = $wpdb->prefix . 'stream';
		$stream_meta_table = $wpdb->prefix . 'stream_meta';

		$sql = $wpdb->prepare(
			'SELECT * FROM %i WHERE ID = %d',
			$stream_table,
			$log_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$result = $wpdb->get_row( $sql );

		if ( ! $result ) {
			return new \WP_Error(
				'log_not_found',
				__( 'Log not found.', 'onelogs' ),
				[ 'status' => 404 ]
			);
		}

		// Get metadata.
		$meta_sql = $wpdb->prepare(
			'SELECT meta_key, meta_value FROM %i WHERE record_id = %d',
			$stream_meta_table,
			$log_id
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$meta_results = $wpdb->get_results( $meta_sql );
		$meta         = [];

		if ( $meta_results ) {
			foreach ( $meta_results as $meta_row ) {
				$meta[ $meta_row->meta_key ] = maybe_unserialize( $meta_row->meta_value );
			}
		}

		$log_data = $this->format_log_data( $result, $meta );

		return new WP_REST_Response( $log_data, 200 );
	}

	/**
	 * Get all available contexts from the database.
	 *
	 * @param \WP_REST_Request $request The request object.
	 */
	public function get_contexts( WP_REST_Request $request ): WP_REST_Response {
		$params     = $request->get_params();
		$contexts   = [];
		$brand_site = filter_var( $params['site_url'] ?? '', FILTER_VALIDATE_URL ) ?: '';

		if ( $brand_site ) {
			$response = $this->onelogs_remote_request( $brand_site, 'onelogs/v1/logs/contexts' );

			return new WP_REST_Response( $response, 200 );
		}

		global $wpdb;
		$stream_table = $wpdb->prefix . 'stream';

		/**
		 * We require to perform the direct query to fetch contexts. Stream does not have a dedicated method for this.
		 * We have not added caching here as user context can change frequently with user activity.
		 */
		$sql = $wpdb->prepare(
			"SELECT DISTINCT context FROM %i WHERE context IS NOT NULL AND context != '' ORDER BY context ASC",
			$stream_table
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$contexts = $wpdb->get_col( $sql );

		if ( false === $contexts ) {
			$contexts = [];
		}

		$response = [
			'status'  => 'success',
			'data'    => $contexts,
			'meta'    => [],
			'message' => 'Local data fetched successfully',
		];

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Get all available connectors from the database.
	 *
	 * @param \WP_REST_Request $request The request object.
	 */
	public function get_connectors( WP_REST_Request $request ): WP_REST_Response {
		$connectors = [];

		global $wpdb;
		$stream_table = $wpdb->prefix . 'stream';

		/**
		 * We require to perform the direct query to fetch contexts. Stream does not have a dedicated method for this.
		 * We have not added caching here as connectors can change frequently with user activity.
		 */
		$sql = $wpdb->prepare(
			"SELECT DISTINCT connector FROM %i WHERE connector IS NOT NULL AND connector != '' ORDER BY connector ASC",
			$stream_table
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$connectors = $wpdb->get_col( $sql );

		if ( false === $connectors ) {
			$connectors = [];
		}

		return new WP_REST_Response( $connectors, 200 );
	}

	/**
	 * Get all available actions from the database.
	 *
	 * @param \WP_REST_Request $request The request object.
	 */
	public function get_actions( WP_REST_Request $request ): WP_REST_Response {
		$params  = $request->get_params();
		$actions = [];

		$brand_site = filter_var( $params['site_url'] ?? '', FILTER_VALIDATE_URL ) ?: '';

		if ( $brand_site ) {
			$response = $this->onelogs_remote_request( $brand_site, 'onelogs/v1/logs/actions' );

			return new WP_REST_Response( $response, 200 );
		}

		global $wpdb;
		$stream_table = $wpdb->prefix . 'stream';

		/**
		 * We require to perform the direct query to fetch actions. Stream does not have a dedicated method for this.
		 * We have not added caching here as actions can change frequently with user activity.
		 */
		$sql = $wpdb->prepare(
			"SELECT DISTINCT action FROM %i WHERE action IS NOT NULL AND action != '' ORDER BY action ASC",
			$stream_table
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.NotPrepared
		$actions = $wpdb->get_col( $sql );

		if ( false === $actions ) {
			$actions = [];
		}

		$response = [
			'status'  => 'success',
			'data'    => $actions,
			'meta'    => [],
			'message' => 'Local data fetched successfully',
		];

		return new WP_REST_Response( $response, 200 );
	}

	/**
	 * Get all users who have logs, with optional search.
	 *
	 * @param \WP_REST_Request $request The request object.
	 *
	 * @return \WP_REST_Response|\WP_Error Response object with users list or error.
	 */
	public function get_users( WP_REST_Request $request ) {
		global $wpdb;

		$params     = $request->get_params();
		$search     = trim( wp_strip_all_tags( $params['search'] ?? '' ) );
		$brand_site = filter_var( $params['site_url'] ?? '', FILTER_VALIDATE_URL ) ?: '';

		// If brand site is provided, fetch remote users.
		if ( $brand_site ) {
			$response = $this->onelogs_remote_request( $brand_site, 'onelogs/v1/logs/users' );

			return rest_ensure_response( $response );
		}

		$stream_table = $wpdb->prefix . 'stream';
		$table_name   = esc_sql( $stream_table );

		// Fetch distinct user IDs from the stream table.

		/**
		 * We require to perform the direct query here to get distinct user IDs. Stream does not have a dedicated method for this.
		 * We have not added caching here as user IDs can change frequently with user deletions/additions.
		 */
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$results = $wpdb->get_col( $wpdb->prepare( "SELECT DISTINCT s.user_id FROM {$table_name} AS s WHERE s.user_id > %d", 0 ) );

		if ( false === $results ) {
			return new \WP_Error(
				'database_error',
				__( 'Failed to retrieve users from the database.', 'onelogs' ),
				[ 'status' => 500 ]
			);
		}

		if ( empty( $results ) ) {
			return rest_ensure_response(
				[
					'status'  => 'success',
					'data'    => [],
					'meta'    => [],
					'message' => __( 'No users found in stream.', 'onelogs' ),
				]
			);
		}

		// Prepare query for get_users().
		$args = [
			'include' => $results,
			'orderby' => 'display_name',
			'order'   => 'ASC',
			'fields'  => [ 'ID', 'display_name', 'user_login', 'user_email' ],
		];

		if ( ! empty( $search ) ) {
			$args['search']         = '*' . esc_attr( $search ) . '*';
			$args['search_columns'] = [ 'user_login', 'user_email', 'display_name' ];
		}

		$users_query = get_users( $args );

		$users = [];
		foreach ( $users_query as $user ) {
			$users[] = [
				'id'           => (int) $user->ID,
				'display_name' => $user->display_name ?: __( 'Unknown User', 'onelogs' ),
				'user_login'   => $user->user_login,
				'user_email'   => $user->user_email,
				'avatar_url'   => get_avatar_url( $user->ID ),
			];
		}

		$response = [
			'status'  => 'success',
			'data'    => $users,
			'meta'    => [ 'total' => count( $users ) ],
			'message' => __( 'Local data fetched successfully.', 'onelogs' ),
		];

		return rest_ensure_response( $response );
	}

	/**
	 * This function is wrapper for wp_remote_request to make authenticated requests to remote OnePress sites.
	 * Wrapped to ensure consistent return format, and simplify usage.
	 *
	 * @param string $site_url The base URL of the remote site.
	 * @param string $path     The REST API endpoint path.
	 * @param array  $args     Request arguments (query parameters or body data).
	 * @param string $method   HTTP method (GET, POST, etc.). Default is 'GET'.
	 */
	private function onelogs_remote_request( string $site_url, string $path, array $args = [], string $method = 'GET' ): array {
		$api_key  = Utils::get_shared_site_api_key_by_url( $site_url );
		$endpoint = trailingslashit( $site_url ) . 'wp-json/' . ltrim( $path, '/' );

		$request_args = [
			'method'  => $method,
			'headers' => [
				'X-OneLogs-Token' => $api_key,
			],
		];

		// Add query/body depending on method.
		if ( 'GET' === strtoupper( $method ) && ! empty( $args ) ) {
			$endpoint = add_query_arg( $args, $endpoint );
		} elseif ( in_array( strtoupper( $method ), [ 'POST', 'PUT', 'PATCH' ], true ) ) {
			$body = wp_json_encode( $args );

			if ( false !== $body ) {
				$request_args['body'] = $body;
			}
			$request_args['headers']['Content-Type'] = 'application/json';
		}

		$response = wp_remote_request( $endpoint, $request_args );

		if ( is_wp_error( $response ) ) {
			return [
				'status'  => 'error',
				'data'    => null,
				'message' => $response->get_error_message(),
			];
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Ensure consistent unified return format.
		if ( 200 !== $code || empty( $body ) ) {
			return [
				'status'  => 'error',
				'data'    => null,
				'message' => $body['message'] ?? 'Remote request failed.',
			];
		}

		return [
			'status'  => $body['status'] ?? 'success',
			'data'    => $body['data'] ?? [],
			'meta'    => $body['meta'] ?? [],
			'message' => 'Remote data fetched successfully.',
		];
	}
}
