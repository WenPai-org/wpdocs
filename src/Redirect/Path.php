<?php
/**
 * Path helpers for redirects (no $wpdb).
 *
 * Behaviour vs cocobook-docs Redirects.php (backup 2026-10-28):
 * - wp_path is host-free, query-free, no trailing slash except `/`.
 * - Old plugin skipped `/wp-*` prefix + asset extensions in option 404 log; wpdocs uses
 *   explicit RESERVED list (same intent, clearer) + SKIP_404_EXTENSIONS.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Redirect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Path {

	/**
	 * Paths that must never be hijacked (exact or as a prefix followed by `/`).
	 *
	 * @var string[]
	 */
	public const RESERVED = array(
		'/wp-admin',
		'/wp-login.php',
		'/wp-json',
		'/xmlrpc.php',
		'/wp-cron.php',
		'/wp-signup.php',
		'/wp-register.php',
		'/wp-activate.php',
		'/wp-comments-post.php',
		'/wp-trackback.php',
		'/wp-mail.php',
		'/wp-links-opml.php',
		'/wp-content',
		'/wp-includes',
	);

	/**
	 * Asset/bot suffixes skipped when inserting unmatched 404 rows (cap still applies).
	 *
	 * @var string[]
	 */
	public const SKIP_404_EXTENSIONS = array(
		'avif',
		'css',
		'eot',
		'gif',
		'ico',
		'jpeg',
		'jpg',
		'js',
		'map',
		'png',
		'svg',
		'ttf',
		'webp',
		'woff',
		'woff2',
	);

	/**
	 * Normalize a request path or dry-run `path` argument.
	 *
	 * Strips scheme/host, query, hash; forces a leading `/`; drops a trailing
	 * slash except for the site root.
	 */
	public static function norm_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			return '/';
		}
		if ( preg_match( '#^[a-z][a-z0-9+.-]*://#i', $path ) ) {
			$parsed = parse_url( $path );
			$path   = ( is_array( $parsed ) && isset( $parsed['path'] ) && '' !== $parsed['path'] )
				? (string) $parsed['path']
				: '/';
		} else {
			$stripped = preg_replace( '/[?#].*$/', '', $path );
			$path     = is_string( $stripped ) ? $stripped : '/';
		}
		$path = '/' . ltrim( $path, '/' );
		$collapsed = preg_replace( '#/+#', '/', $path );
		$path      = is_string( $collapsed ) ? $collapsed : '/';
		if ( '/' === $path ) {
			return '/';
		}
		return rtrim( $path, '/' );
	}

	/**
	 * Strip a subdirectory home prefix from REQUEST_URI (e.g. `/blog/wp-admin` → `/wp-admin`).
	 */
	public static function strip_home_prefix( string $uri, string $home_path ): string {
		$home_path = rtrim( $home_path, '/' );
		if ( '' === $home_path || '/' === $home_path ) {
			return $uri;
		}
		if ( $uri === $home_path || str_starts_with( $uri, $home_path . '/' ) || str_starts_with( $uri, $home_path . '?' ) ) {
			$rest = substr( $uri, strlen( $home_path ) );
			return ( false === $rest || '' === $rest ) ? '/' : $rest;
		}
		return $uri;
	}

	/** Current request path relative to home, normalized. */
	public static function from_request(): string {
		$uri = (string) ( $_SERVER['REQUEST_URI'] ?? '' );
		if ( function_exists( 'home_url' ) && function_exists( 'wp_parse_url' ) ) {
			$home = wp_parse_url( home_url( '/' ), PHP_URL_PATH );
			if ( is_string( $home ) ) {
				$uri = self::strip_home_prefix( $uri, $home );
			}
		}
		return self::norm_path( $uri );
	}

	/** @return string[] */
	public static function reserved_paths(): array {
		$paths = self::RESERVED;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered = apply_filters( 'wpdocs_reserved_paths', $paths );
			if ( is_array( $filtered ) ) {
				$paths = array();
				foreach ( $filtered as $item ) {
					if ( is_string( $item ) && '' !== $item ) {
						$paths[] = self::norm_path( $item );
					}
				}
			}
		}
		return $paths;
	}

	public static function is_reserved( string $path ): bool {
		$lower = strtolower( self::norm_path( $path ) );
		foreach ( self::reserved_paths() as $reserved ) {
			$reserved = strtolower( self::norm_path( (string) $reserved ) );
			if ( $lower === $reserved || str_starts_with( $lower, $reserved . '/' ) ) {
				return true;
			}
		}
		return false;
	}

	/** True when a 404 must not create a new unmatched row. */
	public static function should_skip_404_log( string $path ): bool {
		$path = self::norm_path( $path );
		if ( '' === $path || '/' === $path ) {
			return true;
		}
		if ( self::is_reserved( $path ) ) {
			return true;
		}
		$file = basename( $path );
		$dot  = strrpos( $file, '.' );
		if ( false === $dot ) {
			return false;
		}
		$ext = strtolower( substr( $file, $dot + 1 ) );
		return in_array( $ext, self::SKIP_404_EXTENSIONS, true );
	}
}
