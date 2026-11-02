<?php
/**
 * REST: /wpdocs/v1
 *
 * @package Wpdocs
 */

namespace Wpdocs\Rest;

use Wpdocs\Bind\Repository as BindRepo;
use Wpdocs\Bind\ResolveDraft;
use Wpdocs\Publish\HttpSource;
use Wpdocs\Publish\Probe;
use Wpdocs\Redirect\Listener;
use Wpdocs\Redirect\Repository as RedirectRepo;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Controller {

	public static function register(): void {
		$ns = 'wpdocs/v1';
		register_rest_route(
			$ns,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'status' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			$ns,
			'/bindings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'list_bindings' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'upsert_binding' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			$ns,
			'/bindings/resolve',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'resolve_binding' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			$ns,
			'/bindings/resolve-batch',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'resolve_batch' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
		register_rest_route(
			$ns,
			'/redirects',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( self::class, 'list_redirects' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( self::class, 'upsert_redirect' ),
					'permission_callback' => array( self::class, 'can_manage' ),
				),
			)
		);
		register_rest_route(
			$ns,
			'/redirects/dry-run',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'dry_run' ),
				'permission_callback' => array( self::class, 'can_manage' ),
			)
		);
	}

	public static function can_manage(): bool {
		return current_user_can( 'manage_options' );
	}

	public static function status(): WP_REST_Response {
		try {
			$publish = HttpSource::status();
		} catch ( Throwable $e ) {
			$publish = Probe::failed_status( $e->getMessage() );
		}
		if ( ! is_array( $publish ) ) {
			$publish = Probe::failed_status( 'invalid publish status' );
		}
		return new WP_REST_Response(
			array(
				'version'   => WPDOCS_VERSION,
				'bindings'  => BindRepo::count(),
				'redirects' => RedirectRepo::count(),
				'publish'   => $publish,
			)
		);
	}

	public static function list_bindings( WP_REST_Request $req ): WP_REST_Response {
		$page = max( 1, (int) $req->get_param( 'page' ) );
		$per  = max( 1, min( 200, (int) ( $req->get_param( 'per_page' ) ?: 50 ) ) );
		return new WP_REST_Response(
			array(
				'total' => BindRepo::count(),
				'items' => BindRepo::list( $per, ( $page - 1 ) * $per ),
			)
		);
	}

	/**
	 * JSON body preferred; form / query params fill gaps (admin + wp eval).
	 *
	 * @return array<string,mixed>
	 */
	private static function request_payload( WP_REST_Request $req ): array {
		$json = $req->get_json_params();
		$out  = is_array( $json ) ? $json : array();
		foreach ( (array) $req->get_body_params() as $k => $v ) {
			if ( ! array_key_exists( $k, $out ) ) {
				$out[ $k ] = $v;
			}
		}
		foreach ( (array) $req->get_query_params() as $k => $v ) {
			if ( ! array_key_exists( $k, $out ) ) {
				$out[ $k ] = $v;
			}
		}
		return $out;
	}

	public static function upsert_binding( WP_REST_Request $req ): WP_REST_Response {
		$data = self::request_payload( $req );
		$id   = BindRepo::upsert( $data );
		$post = (int) ( $data['wp_post_id'] ?? 0 );
		return new WP_REST_Response(
			array(
				'id'  => $id,
				'row' => $post ? BindRepo::find_by_post( $post ) : null,
			),
			$id ? 200 : 400
		);
	}

	public static function resolve_binding( WP_REST_Request $req ): WP_REST_Response {
		$post_id = (int) $req->get_param( 'wp_post_id' );
		$page_id = (int) $req->get_param( 'docs_page_id' );
		$row     = $post_id ? BindRepo::find_by_post( $post_id ) : ( $page_id ? BindRepo::find_by_docs_page( $page_id ) : null );
		return new WP_REST_Response( array( 'row' => $row ) );
	}

	/**
	 * POST /bindings/resolve-batch — source=slug drafts.
	 *
	 * Default is dry-run (no table writes). Upsert only when apply=true.
	 *
	 * @return WP_REST_Response|WP_Error
	 */
	public static function resolve_batch( WP_REST_Request $req ) {
		$payload = self::request_payload( $req );
		if ( isset( $payload['posts'] ) && ! is_array( $payload['posts'] ) ) {
			return new WP_Error( 'wpdocs_bad_batch', 'posts must be an array', array( 'status' => 400 ) );
		}
		if ( isset( $payload['pages'] ) && ! is_array( $payload['pages'] ) ) {
			return new WP_Error( 'wpdocs_bad_batch', 'pages must be an array', array( 'status' => 400 ) );
		}

		$payload = self::hydrate_batch_payload( $payload );
		$plan    = ResolveDraft::plan( $payload );

		$apply_raw   = array_key_exists( 'apply', $payload ) ? $payload['apply'] : $req->get_param( 'apply' );
		$apply       = ResolveDraft::wants_apply( $apply_raw );
		$applied_ids = array();
		if ( $apply ) {
			$applied_ids = BindRepo::apply_slug_drafts( ResolveDraft::bind_drafts( $plan ) );
		}
		$plan['apply']       = $apply;
		$plan['applied']     = array() !== $applied_ids;
		$plan['applied_ids'] = $applied_ids;
		return new WP_REST_Response( $plan );
	}

	/**
	 * Fill post_name from WP when only wp_post_id is sent; load existing rows if omitted.
	 *
	 * Catalog `pages` stay caller-supplied (this endpoint does not fetch BookStack).
	 *
	 * @param array<string,mixed> $payload
	 * @return array<string,mixed>
	 */
	private static function hydrate_batch_payload( array $payload ): array {
		$posts = isset( $payload['posts'] ) && is_array( $payload['posts'] ) ? $payload['posts'] : array();
		$out   = array();
		$ids   = array();
		foreach ( $posts as $post ) {
			if ( ! is_array( $post ) ) {
				continue;
			}
			$id   = (int) ( $post['wp_post_id'] ?? $post['ID'] ?? 0 );
			$name = (string) ( $post['post_name'] ?? $post['slug'] ?? '' );
			$type = (string) ( $post['post_type'] ?? '' );
			if ( '' === $name && $id > 0 && function_exists( 'get_post' ) ) {
				$wp = get_post( $id );
				if ( $wp ) {
					$name = (string) $wp->post_name;
					if ( '' === $type ) {
						$type = (string) $wp->post_type;
					}
				}
			}
			$row = array(
				'wp_post_id' => $id,
				'post_name'  => $name,
				'post_type'  => '' !== $type ? $type : 'post',
			);
			if ( isset( $post['book_slug'] ) ) {
				$row['book_slug'] = (string) $post['book_slug'];
			} elseif ( isset( $post['docs_book_slug'] ) ) {
				$row['book_slug'] = (string) $post['docs_book_slug'];
			}
			if ( isset( $post['external_id'] ) ) {
				$row['external_id'] = (string) $post['external_id'];
			}
			$out[] = $row;
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		$payload['posts'] = $out;

		if ( ! array_key_exists( 'existing', $payload ) ) {
			$payload['existing'] = self::existing_bindings_payload( $ids );
		}

		return $payload;
	}

	/**
	 * @param int[] $post_ids
	 * @return array<int, array<string,mixed>>
	 */
	private static function existing_bindings_payload( array $post_ids ): array {
		$rows = BindRepo::find_by_posts( $post_ids );
		$out  = array();
		foreach ( $rows as $row ) {
			$out[] = array(
				'wp_post_id'     => (int) $row->wp_post_id,
				'docs_page_id'   => (int) $row->docs_page_id,
				'docs_book_slug' => (string) $row->docs_book_slug,
				'docs_page_slug' => (string) $row->docs_page_slug,
				'source'         => (string) $row->source,
				'status'         => (string) $row->status,
			);
		}
		return $out;
	}

	public static function list_redirects( WP_REST_Request $req ): WP_REST_Response {
		$status = (string) ( $req->get_param( 'status' ) ?: '' );
		$page   = max( 1, (int) $req->get_param( 'page' ) );
		$per    = max( 1, min( 200, (int) ( $req->get_param( 'per_page' ) ?: 50 ) ) );
		return new WP_REST_Response(
			array(
				'total' => RedirectRepo::count( $status ),
				'items' => RedirectRepo::list( $status, $per, ( $page - 1 ) * $per ),
			)
		);
	}

	public static function upsert_redirect( WP_REST_Request $req ): WP_REST_Response {
		$id = RedirectRepo::upsert( self::request_payload( $req ) );
		return new WP_REST_Response( array( 'id' => $id ), $id ? 200 : 400 );
	}

	public static function dry_run( WP_REST_Request $req ): WP_REST_Response {
		$path = (string) $req->get_param( 'path' );
		return new WP_REST_Response( Listener::dry_run( $path ) );
	}
}
