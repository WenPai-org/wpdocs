<?php
/**
 * Front-end redirect on template_redirect.
 *
 * Behaviour vs cocobook-docs Redirects.php (backup 2026-10-28):
 * - Live 404s: table rows (status=unmatched, source=404) + cap; old wrote option cocobook_docs_404 (max 200).
 * - Existing unmatched/pending bumps hits (parity with option counter++).
 * - Reserved WP paths never 301/410 and are never logged.
 * - Matched targets store page_id; URL resolved at request time (Bind slug or /link/{id}).
 * - GET dry-run never writes hits and never inserts 404 rows (old had no dry-run API).
 * See tests/redirect-vs-cocobook-docs.md.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Redirect;

use Wpdocs\Bind\Repository as BindRepo;
use Wpdocs\Publish\HttpSource;
use Wpdocs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Listener {

	public static function register(): void {
		add_action( 'template_redirect', array( self::class, 'maybe_redirect' ), 1 );
	}

	public static function maybe_redirect(): void {
		if ( ! Settings::redirects_enabled() ) {
			return;
		}
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return;
		}
		if ( isset( $_GET['wpdocs_no_redirect'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( isset( $_GET['cocobook_no_redirect'] ) && current_user_can( 'manage_options' ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		if ( ! in_array( $_SERVER['REQUEST_METHOD'] ?? 'GET', array( 'GET', 'HEAD' ), true ) ) {
			return;
		}

		$path = Path::from_request();
		if ( Path::is_reserved( $path ) ) {
			return;
		}

		$row = Repository::find( $path );
		if ( $row && Repository::GONE === $row->status ) {
			Repository::hit( (int) $row->id );
			status_header( 410 );
			nocache_headers();
			wp_die( esc_html__( '此文档已移除。', 'wpdocs' ), esc_html__( '已删除', 'wpdocs' ), array( 'response' => 410 ) );
		}
		if ( $row && Repository::MATCHED === $row->status && (int) $row->page_id > 0 ) {
			$url = self::target_url( (int) $row->page_id );
			if ( $url ) {
				Repository::hit( (int) $row->id );
				wp_redirect( $url, 301 );
				exit;
			}
			return;
		}

		if ( function_exists( 'is_404' ) && is_404() ) {
			Repository::log_unmatched( $path );
		}
	}

	/**
	 * Resolve a live URL from page_id. Never reads a stored absolute URL from the row.
	 */
	public static function target_url( int $page_id ): string {
		if ( $page_id <= 0 ) {
			return '';
		}
		$bind = BindRepo::find_by_docs_page( $page_id );
		$base = HttpSource::base_url();
		if ( ! $base ) {
			return '';
		}
		$base = trailingslashit( $base );
		if ( $bind && $bind->docs_book_slug && $bind->docs_page_slug ) {
			return $base . 'books/' . rawurlencode( $bind->docs_book_slug ) . '/page/' . rawurlencode( $bind->docs_page_slug );
		}
		return $base . 'link/' . $page_id;
	}

	/**
	 * Dry-run helper for REST. Must not write hits or unmatched 404 rows.
	 *
	 * @return array<string,mixed>
	 */
	public static function dry_run( string $path ): array {
		$path = Path::norm_path( $path );
		$row  = Path::is_reserved( $path ) ? null : Repository::find( $path );
		$out  = Decision::preview( $path, $row );
		if ( '301' === ( $out['action'] ?? '' ) && ! empty( $out['page_id'] ) ) {
			$out['target'] = self::target_url( (int) $out['page_id'] );
		}
		return $out;
	}
}
