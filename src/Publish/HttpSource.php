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
use Wpdocs\Settings;
use Wpdocs\Http\BookStackClient;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HttpSource {

	/** Transient holding last probe envelope (not body content). */
	public const STATUS_CACHE_KEY = 'wpdocs_publish_status_v1';

	/** Seconds. Admin open should hit cache; 「重新探测」forces fresh. */
	public const STATUS_CACHE_TTL = 300;

	public static function base_url(): string {
		return Settings::base_url();
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
	 * Drop cached publish status (settings save / explicit refresh).
	 */
	public static function bust_status_cache(): void {
		delete_transient( self::STATUS_CACHE_KEY );
	}

	/**
	 * GET /status `publish` object. Never throws; never writes options.
	 *
	 * @param bool $fresh When true, skip transient and re-probe.
	 * @return array<string,mixed>
	 */
	public static function status( bool $fresh = false ): array {
		if ( ! $fresh ) {
			$cached = get_transient( self::STATUS_CACHE_KEY );
			if ( is_array( $cached ) && isset( $cached['home'] ) && is_array( $cached['home'] ) ) {
				return $cached;
			}
		}

		try {
			$base = self::base_url();
			$tree = self::tree_url();
			$urls = array(
				'home'    => Probe::join_url( $base, '/' ),
				'sitemap' => Probe::sitemap_url( $base ),
				'tree'    => $tree,
			);
			$rows = self::probe_many( $urls );
			$payload = Probe::status_payload(
				$base,
				$tree,
				$rows['home'],
				$rows['sitemap'],
				$rows['tree']
			);
			// Optional Token API ping (skipped when WPDOCS_TOKEN_* absent).
			$payload['api'] = BookStackClient::health_row(
				BookStackClient::from_config(
					array(
						'base_url' => $base,
						'timeout'  => Probe::TIMEOUT_SEC,
					)
				)
			);
			set_transient( self::STATUS_CACHE_KEY, $payload, self::STATUS_CACHE_TTL );
			return $payload;
		} catch ( Throwable $e ) {
			return Probe::failed_status( $e->getMessage() );
		}
	}

	/**
	 * Parallel HEAD when curl_multi is available; otherwise sequential wp_remote_head.
	 *
	 * @param array<string,string> $urls Keyed URL map (empty string → skipped row).
	 * @return array<string,array{url:string,method:string,code:int,ms:int,error:string,ok:bool,skipped:bool}>
	 */
	private static function probe_many( array $urls ): array {
		$out      = array();
		$pending  = array();
		foreach ( $urls as $key => $url ) {
			$url = trim( (string) $url );
			if ( '' === $url ) {
				$out[ $key ] = Probe::skipped( '' );
				continue;
			}
			$pending[ $key ] = $url;
		}

		if ( array() === $pending ) {
			return $out;
		}

		if ( function_exists( 'curl_multi_init' ) && count( $pending ) > 1 ) {
			$parallel = self::probe_many_curl( $pending );
			foreach ( $parallel as $key => $row ) {
				$out[ $key ] = $row;
			}
			return $out;
		}

		foreach ( $pending as $key => $url ) {
			$out[ $key ] = self::probe( $url );
		}
		return $out;
	}

	/**
	 * @param array<string,string> $urls Non-empty keyed URLs.
	 * @return array<string,array{url:string,method:string,code:int,ms:int,error:string,ok:bool,skipped:bool}>
	 */
	private static function probe_many_curl( array $urls ): array {
		$mh      = curl_multi_init();
		$handles = array();
		$started = array();
		$ua      = 'Wpdocs/' . ( defined( 'WPDOCS_VERSION' ) ? WPDOCS_VERSION : '0' );
		$t0      = microtime( true );

		foreach ( $urls as $key => $url ) {
			$ch = curl_init( $url );
			curl_setopt_array(
				$ch,
				array(
					CURLOPT_NOBODY         => true,
					CURLOPT_RETURNTRANSFER => true,
					CURLOPT_FOLLOWLOCATION => true,
					CURLOPT_MAXREDIRS      => 2,
					CURLOPT_TIMEOUT        => Probe::TIMEOUT_SEC,
					CURLOPT_USERAGENT      => $ua,
					CURLOPT_PROTOCOLS      => CURLPROTO_HTTP | CURLPROTO_HTTPS,
					CURLOPT_REDIR_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
				)
			);
			curl_multi_add_handle( $mh, $ch );
			$handles[ $key ] = $ch;
			$started[ $key ] = $t0;
		}

		$running = null;
		do {
			$mrc = curl_multi_exec( $mh, $running );
			if ( $running ) {
				curl_multi_select( $mh, 0.2 );
			}
		} while ( $running && CURLM_OK === $mrc );

		$out = array();
		$ms_all = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		foreach ( $handles as $key => $ch ) {
			$url   = $urls[ $key ];
			$errno = curl_errno( $ch );
			$code  = (int) curl_getinfo( $ch, CURLINFO_RESPONSE_CODE );
			$err   = $errno ? (string) curl_error( $ch ) : '';
			if ( $errno && 0 === $code ) {
				$out[ $key ] = Probe::result( $url, Probe::METHOD, 0, $ms_all, $err );
			} else {
				$out[ $key ] = Probe::result( $url, Probe::METHOD, $code, $ms_all, $err );
			}
			curl_multi_remove_handle( $mh, $ch );
			curl_close( $ch );
		}
		curl_multi_close( $mh );
		return $out;
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
