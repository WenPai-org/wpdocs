<?php
/**
 * Front-end redirect on template_redirect.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Redirect;

use Wpdocs\Bind\Repository as BindRepo;
use Wpdocs\Publish\HttpSource;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Listener {

	/** Paths that must never redirect. */
	private const RESERVED = array(
		'/wp-admin',
		'/wp-login.php',
		'/wp-json',
		'/xmlrpc.php',
		'/wp-cron.php',
	);

	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_redirect' ), 1 );
	}

	public static function maybe_redirect(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
			return;
		}
		$path = Repository::norm_path( (string) ( $_SERVER['REQUEST_URI'] ?? '' ) );
		foreach ( self::RESERVED as $r ) {
			if ( $path === $r || str_starts_with( $path, $r . '/' ) ) {
				return;
			}
		}

		$row = Repository::find( $path );
		if ( ! $row ) {
			return;
		}
		if ( Repository::GONE === $row->status ) {
			Repository::hit( (int) $row->id );
			status_header( 410 );
			nocache_headers();
			wp_die( esc_html__( '此文档已移除。', 'wpdocs' ), esc_html__( '已删除', 'wpdocs' ), array( 'response' => 410 ) );
		}
		if ( Repository::MATCHED !== $row->status || (int) $row->page_id <= 0 ) {
			return;
		}

		$url = self::target_url( (int) $row->page_id );
		if ( ! $url ) {
			return;
		}
		Repository::hit( (int) $row->id );
		wp_redirect( $url, 301 );
		exit;
	}

	public static function target_url( int $page_id ): string {
		$bind = BindRepo::find_by_docs_page( $page_id );
		if ( $bind && $bind->docs_book_slug && $bind->docs_page_slug ) {
			$base = HttpSource::base_url();
			return trailingslashit( $base ) . 'books/' . rawurlencode( $bind->docs_book_slug ) . '/page/' . rawurlencode( $bind->docs_page_slug );
		}
		// Fallback: BookStack /link/{id}
		$base = HttpSource::base_url();
		return $base ? ( trailingslashit( $base ) . 'link/' . $page_id ) : '';
	}

	/** Dry-run helper for REST. */
	public static function dry_run( string $path ): array {
		$path = Repository::norm_path( $path );
		$row  = Repository::find( $path );
		if ( ! $row ) {
			return array( 'path' => $path, 'action' => 'none' );
		}
		if ( Repository::GONE === $row->status ) {
			return array( 'path' => $path, 'action' => '410', 'id' => (int) $row->id );
		}
		if ( Repository::MATCHED === $row->status && (int) $row->page_id > 0 ) {
			return array(
				'path'   => $path,
				'action' => '301',
				'id'     => (int) $row->id,
				'target' => self::target_url( (int) $row->page_id ),
			);
		}
		return array( 'path' => $path, 'action' => 'skip', 'status' => $row->status, 'id' => (int) $row->id );
	}
}
