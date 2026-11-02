<?php
/**
 * Pure URL / status-shape helpers for Publish diagnostics (no HTTP, no options).
 *
 * @package Wpdocs
 */

namespace Wpdocs\Publish;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Probe {

	/** HTTP method for all docs/tree probes. Never GET a body. */
	public const METHOD = 'HEAD';

	/** Seconds. Short so GET /status cannot stall the admin. */
	public const TIMEOUT_SEC = 3;

	/** Sitemap path relative to docs base_url. */
	public const SITEMAP_PATH = '/cocobook/sitemap.xml';

	/** Default publish-tree path (BookStack catalog) when no override is set. */
	public const TREE_PATH = '/books';

	/**
	 * Join a docs base URL with a path. Empty base yields ''.
	 */
	public static function join_url( string $base, string $path ): string {
		$base = rtrim( trim( $base ), '/' );
		if ( '' === $base ) {
			return '';
		}
		$path = trim( $path );
		if ( '' === $path || '/' === $path ) {
			return $base . '/';
		}
		return $base . '/' . ltrim( $path, '/' );
	}

	public static function sitemap_url( string $base ): string {
		return self::join_url( $base, self::SITEMAP_PATH );
	}

	public static function default_tree_url( string $base ): string {
		return self::join_url( $base, self::TREE_PATH );
	}

	/**
	 * Resolve the publish-tree HEAD target.
	 *
	 * Constant wins. A provided setting (including '') overrides the default:
	 * empty string skips the probe. Null setting means "key absent" → default.
	 *
	 * @param string      $base           Docs base_url.
	 * @param string      $from_constant  WPDOCS_TREE_URL or ''.
	 * @param string|null $from_setting   wpdocs_settings.tree_url, or null if unset.
	 */
	public static function resolve_tree_url( string $base, string $from_constant, ?string $from_setting ): string {
		$from_constant = trim( $from_constant );
		if ( '' !== $from_constant ) {
			return rtrim( $from_constant, '/' );
		}
		if ( null !== $from_setting ) {
			$from_setting = trim( $from_setting );
			return '' === $from_setting ? '' : rtrim( $from_setting, '/' );
		}
		return self::default_tree_url( $base );
	}

	public static function is_ok_code( int $code ): bool {
		return $code >= 200 && $code < 300;
	}

	/**
	 * One probe row. Never includes body / markdown / content.
	 *
	 * @return array{url:string,method:string,code:int,ms:int,error:string,ok:bool,skipped:bool}
	 */
	public static function result( string $url, string $method, int $code, int $ms, string $error, bool $skipped = false ): array {
		return array(
			'url'     => $url,
			'method'  => $method,
			'code'    => $code,
			'ms'      => $ms,
			'error'   => $error,
			'ok'      => ( ! $skipped && self::is_ok_code( $code ) ),
			'skipped' => $skipped,
		);
	}

	/**
	 * @return array{url:string,method:string,code:int,ms:int,error:string,ok:bool,skipped:bool}
	 */
	public static function skipped( string $url ): array {
		return self::result( $url, self::METHOD, 0, 0, '', true );
	}

	/**
	 * GET /status `publish` object. `ok` follows home only.
	 *
	 * @param array<string,mixed> $home
	 * @param array<string,mixed> $sitemap
	 * @param array<string,mixed> $tree
	 * @return array<string,mixed>
	 */
	public static function status_payload( string $base_url, string $tree_url, array $home, array $sitemap, array $tree, string $error = '' ): array {
		return array(
			'base_url' => $base_url,
			'tree_url' => $tree_url,
			'timeout'  => self::TIMEOUT_SEC,
			'home'     => $home,
			'sitemap'  => $sitemap,
			'tree'     => $tree,
			'ok'       => (bool) ( $home['ok'] ?? false ),
			'error'    => $error,
		);
	}

	/**
	 * Envelope used when the HTTP layer throws; no request is issued.
	 *
	 * @return array<string,mixed>
	 */
	public static function failed_status( string $error ): array {
		$skip = self::skipped( '' );
		return self::status_payload( '', '', $skip, $skip, $skip, $error );
	}
}
