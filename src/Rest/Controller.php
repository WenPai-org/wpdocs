<?php
/**
 * REST: /wpdocs/v1
 *
 * @package Wpdocs
 */

namespace Wpdocs\Rest;

use Wpdocs\Bind\Repository as BindRepo;
use Wpdocs\Publish\HttpSource;
use Wpdocs\Redirect\Listener;
use Wpdocs\Redirect\Repository as RedirectRepo;
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
		return new WP_REST_Response(
			array(
				'version'   => WPDOCS_VERSION,
				'bindings'  => BindRepo::count(),
				'redirects' => RedirectRepo::count(),
				'publish'   => HttpSource::status(),
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

	public static function upsert_binding( WP_REST_Request $req ): WP_REST_Response {
		$id = BindRepo::upsert( (array) $req->get_json_params() );
		return new WP_REST_Response( array( 'id' => $id, 'row' => BindRepo::find_by_post( (int) $req['wp_post_id'] ) ), $id ? 200 : 400 );
	}

	public static function resolve_binding( WP_REST_Request $req ): WP_REST_Response {
		$post_id = (int) $req->get_param( 'wp_post_id' );
		$page_id = (int) $req->get_param( 'docs_page_id' );
		$row     = $post_id ? BindRepo::find_by_post( $post_id ) : ( $page_id ? BindRepo::find_by_docs_page( $page_id ) : null );
		return new WP_REST_Response( array( 'row' => $row ) );
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
		$id = RedirectRepo::upsert( (array) $req->get_json_params() );
		return new WP_REST_Response( array( 'id' => $id ), $id ? 200 : 400 );
	}

	public static function dry_run( WP_REST_Request $req ): WP_REST_Response {
		$path = (string) $req->get_param( 'path' );
		return new WP_REST_Response( Listener::dry_run( $path ) );
	}
}
