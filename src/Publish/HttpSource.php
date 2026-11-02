<?php
/**
 * Read-only diagnostics against docs host / publish tree.
 *
 * HEAD only; never stores response bodies or Markdown in options.
 * Probe failures are recorded on the status envelope and do not throw.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Publish;

use Wpdocs\Compat\Legacy;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HttpSource {

	public static function base_url(): string {
		if ( defined( 'WPDOCS_BASE_URL' ) && WPDOCS_BASE_URL ) {
			return untrailingslashit( (string) WPDOCS_BASE_URL );
		}
		$s = Legacy::get_option( 'wpdocs_settings', array() );
		if ( is_array( $s ) && ! empty( $s['base_url'] ) ) {
			return untrailingslashit( (string) $s['base_url'] );
		}
		return 'https://docs.cocobook.com';
	}

	/**
	 * Publish-tree HEAD target. Empty string skips the tree probe.
	 *
	 * Override: WPDOCS_TREE_URL, then wpdocs_settings.tree_url.
	 * Missing override → {base_url}/books.
	 */
	public static function tree_url(): string {
		$constant = ( defined( 'WPDOCS_TREE_URL' ) ) ? (string) WPDOCS_TREE_URL : '';
		$setting  = null;
		$s        = Legacy::get_option( 'wpdocs_settings', array() );
		if ( is_array( $s ) && array_key_exists( 'tree_url', $s ) ) {
			$setting = (string) $s['tree_url'];
		}
		return Probe::resolve_tree_url( self::base_url(), $constant, $setting );
	}

	/**
	 * GET /status `publish` object. Never throws; never writes options.
	 *
	 * @return array<string,mixed>
	 */
	public static function status(): array {
		try {
			$base = self::base_url();
			$tree = self::tree_url();
			return Probe::status_payload(
				$base,
				$tree,
				self::probe( Probe::join_url( $base, '/' ) ),
				self::probe( Probe::sitemap_url( $base ) ),
				self::probe( $tree )
			);
		} catch ( Throwable $e ) {
			return Probe::failed_status( $e->getMessage() );
		}
	}

	/**
	 * HEAD probe. Empty URL is skipped. Transport errors become code=0.
	 *
	 * @return array{url:string,method:string,code:int,ms:int,error:string,ok:bool,skipped:bool}
	 */
	private static function probe( string $url ): array {
		$url = trim( $url );
		if ( '' === $url ) {
			return Probe::skipped( '' );
		}

		$t0 = microtime( true );
		try {
			$r = wp_remote_head(
				$url,
				array(
					'timeout'     => Probe::TIMEOUT_SEC,
					'redirection' => 2,
					'user-agent'  => 'Wpdocs/' . ( defined( 'WPDOCS_VERSION' ) ? WPDOCS_VERSION : '0' ),
				)
			);
		} catch ( Throwable $e ) {
			$ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
			return Probe::result( $url, Probe::METHOD, 0, $ms, $e->getMessage() );
		}

		$ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		if ( is_wp_error( $r ) ) {
			return Probe::result( $url, Probe::METHOD, 0, $ms, $r->get_error_message() );
		}

		return Probe::result(
			$url,
			Probe::METHOD,
			(int) wp_remote_retrieve_response_code( $r ),
			$ms,
			''
		);
	}
}
