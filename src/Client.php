<?php
/**
 * BookStack API client with file cache and stale fallback.
 *
 * @package Wpdocs
 */

namespace Wpdocs;

use Wpdocs\Compat\Legacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read-only BookStack client.
 *
 * Page bodies are cached as files under wp-content/cache/wpdocs/ so
 * large HTML never lands in wp_options. The last good copy is never deleted:
 * the webhook, the daily check and a manual sync only mark entries stale, and a
 * stale entry is replaced only after a successful fetch. If CocoBook can't be
 * reached (e.g. expired token) visitors keep seeing the last good copy, retries
 * back off for BACKOFF seconds and admins get a notice. A TTL of 0 means entries
 * never go stale on their own.
 *
 * @since 0.1.0
 */
final class Client {

	public const LOG_OPTION    = 'wpdocs_log';
	public const LOG_MAX       = 50;
	public const HEALTH_OPTION = 'wpdocs_health';
	public const BACKOFF       = 5 * MINUTE_IN_SECONDS;

	/** @var bool Skip the failure backoff (cron, manual sync). */
	public static bool $force = false;

	/** @var \WP_Error|null Last API error for the configured token in this request. */
	public static ?\WP_Error $last_error = null;

	/**
	 * GET an API path.
	 *
	 * @param string              $path  Path below /api/, e.g. `pages/69`.
	 * @param array<string,mixed> $query Query args.
	 * @param string              $base  Optional base override (connection test).
	 * @param string              $token Optional token override (connection test).
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get( string $path, array $query = array(), string $base = '', string $token = '' ) {
		$own   = '' === $token; // Health tracks the configured token only.
		$base  = '' !== $base ? untrailingslashit( $base ) : Settings::base_url();
		$token = '' !== $token ? $token : Settings::token_pair();
		if ( '' === $base || '' === $token ) {
			return new \WP_Error( 'cocobook_docs_unconfigured', __( '文档站地址或 API Token 未配置。', 'wpdocs' ) );
		}
		$url = $base . '/api/' . ltrim( $path, '/' );
		if ( $query ) {
			$url = add_query_arg( array_map( 'rawurlencode', array_map( 'strval', $query ) ), $url );
		}
		$res = wp_remote_get(
			$url,
			array(
				'timeout'    => 10,
				'headers'    => array(
					'Authorization' => 'Token ' . $token,
					'Accept'        => 'application/json',
				),
				'user-agent' => 'wpdocs/' . WPDOCS_VERSION . '; ' . home_url( '/' ),
			)
		);
		if ( is_wp_error( $res ) ) {
			return $own ? self::note( $res ) : $res;
		}
		$code = (int) wp_remote_retrieve_response_code( $res );
		$body = json_decode( (string) wp_remote_retrieve_body( $res ), true );
		if ( $code < 200 || $code >= 300 || ! is_array( $body ) ) {
			$msg = is_array( $body ) && isset( $body['error']['message'] ) ? (string) $body['error']['message'] : 'HTTP ' . $code;
			$err = new \WP_Error( 'cocobook_docs_http', $msg, array( 'status' => $code ) );
			return $own ? self::note( $err ) : $err;
		}
		if ( $own ) {
			self::note( null );
		}
		return $body;
	}

	/**
	 * Fetch all items of a list endpoint (handles BookStack paging).
	 *
	 * @param string              $path   e.g. `pages`.
	 * @param array<string,mixed> $filter Extra query.
	 * @return array<int,array<string,mixed>>|\WP_Error
	 */
	public static function list_all( string $path, array $filter = array() ) {
		$out    = array();
		$offset = 0;
		$count  = 500;
		do {
			$res = self::get( $path, array_merge( $filter, array( 'count' => $count, 'offset' => $offset ) ) );
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$data  = isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : array();
			$total = isset( $res['total'] ) ? (int) $res['total'] : count( $data );
			$out   = array_merge( $out, $data );
			$offset += $count;
		} while ( $data && $offset < $total && $offset < 10000 );
		return $out;
	}

	/**
	 * Cached fetch: fresh within $ttl or until marked stale; last good copy on failure.
	 *
	 * @param string   $key     Cache key.
	 * @param int      $ttl     Fresh TTL seconds.
	 * @param callable $fetcher Returns array|WP_Error.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function remember( string $key, int $ttl, callable $fetcher ) {
		$hit = self::cache_read( $key );
		if ( $hit && ! $hit['s'] && ( $ttl <= 0 || ( time() - $hit['t'] ) < $ttl ) ) {
			return $hit['d'];
		}
		if ( ! self::$force && self::backing_off() ) {
			// CocoBook failed moments ago: don't stall every page view on it.
			return $hit ? $hit['d'] : ( self::$last_error ?? new \WP_Error( 'cocobook_docs_backoff', '文档站暂时不可达，稍后重试。' ) );
		}
		$fresh = $fetcher();
		if ( ! is_wp_error( $fresh ) && is_array( $fresh ) ) {
			self::cache_write( $key, $fresh );
			return $fresh;
		}
		if ( $hit ) {
			return $hit['d']; // Last good copy, kept until a fetch succeeds.
		}
		return is_wp_error( $fresh ) ? $fresh : new \WP_Error( 'cocobook_docs_empty', 'Empty response' );
	}

	/** Cache directory (created on demand). */
	public static function cache_dir(): string {
		$dir = trailingslashit( WP_CONTENT_DIR ) . 'cache/wpdocs/';
		if ( ! is_dir( $dir ) ) {
			wp_mkdir_p( $dir );
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			@file_put_contents( $dir . 'index.php', "<?php // Silence.\n" );
		}
		return $dir;
	}

	/**
	 * Cache file path for a key.
	 *
	 * @param string $key Key.
	 */
	private static function cache_path( string $key ): string {
		$safe = preg_replace( '/[^a-z0-9_\-]/', '_', strtolower( $key ) );
		return self::cache_dir() . $safe . '-' . substr( md5( $key . wp_salt( 'auth' ) ), 0, 10 ) . '.json';
	}

	/**
	 * Read cache entry.
	 *
	 * @param string $key Key.
	 * @return array{t:int,d:array<string,mixed>}|null
	 */
	private static function cache_read( string $key ): ?array {
		return self::file_read( self::cache_path( $key ) );
	}

	/**
	 * Read a cache file.
	 *
	 * @param string $file Path.
	 * @return array{t:int,s:bool,d:array<string,mixed>}|null
	 */
	private static function file_read( string $file ): ?array {
		if ( ! is_readable( $file ) ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$raw  = file_get_contents( $file );
		$data = is_string( $raw ) ? json_decode( $raw, true ) : null;
		if ( ! is_array( $data ) || ! isset( $data['t'], $data['d'] ) || ! is_array( $data['d'] ) ) {
			return null;
		}
		return array(
			't' => (int) $data['t'],
			's' => ! empty( $data['s'] ),
			'd' => $data['d'],
		);
	}

	/**
	 * Write cache entry atomically.
	 *
	 * @param string              $key  Key.
	 * @param array<string,mixed> $data Data.
	 */
	private static function cache_write( string $key, array $data ): void {
		self::file_write( self::cache_path( $key ), array( 't' => time(), 's' => false, 'd' => $data ) );
	}

	/**
	 * Write a cache file atomically.
	 *
	 * @param string              $file  Path.
	 * @param array<string,mixed> $entry Entry {t,s,d}.
	 */
	private static function file_write( string $file, array $entry ): void {
		$tmp  = $file . '.' . wp_generate_password( 6, false ) . '.tmp';
		$json = wp_json_encode( $entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false !== @file_put_contents( $tmp, $json, LOCK_EX ) ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.rename_rename
			@rename( $tmp, $file );
		}
	}

	/**
	 * Drop one cache key.
	 *
	 * @param string $key Key.
	 */
	public static function forget( string $key ): void {
		$file = self::cache_path( $key );
		if ( is_file( $file ) ) {
			wp_delete_file( $file );
		}
	}

	/**
	 * Mark one key stale: refetched on next use, old copy kept until that succeeds.
	 *
	 * @param string $key Key.
	 * @return bool Whether an entry existed.
	 */
	public static function mark_stale( string $key ): bool {
		return self::stale_file( self::cache_path( $key ) );
	}

	/**
	 * Mark every entry whose key starts with $prefix stale.
	 *
	 * @param string $prefix e.g. `page_`.
	 * @return int Entries marked.
	 */
	public static function mark_stale_prefix( string $prefix ): int {
		$n     = 0;
		$safe  = preg_replace( '/[^a-z0-9_\-]/', '_', strtolower( $prefix ) );
		$files = glob( self::cache_dir() . $safe . '*.json' );
		foreach ( is_array( $files ) ? $files : array() as $file ) {
			if ( self::stale_file( $file ) ) {
				++$n;
			}
		}
		return $n;
	}

	/**
	 * Flag a cache file stale, keeping its data.
	 *
	 * @param string $file Cache file.
	 */
	private static function stale_file( string $file ): bool {
		$hit = self::file_read( $file );
		if ( ! $hit ) {
			return false;
		}
		if ( ! $hit['s'] ) {
			$hit['s'] = true;
			self::file_write( $file, $hit );
		}
		return true;
	}

	/**
	 * API health for the configured token.
	 *
	 * @return array{ok_at:int,err_since:int,err_at:int,err:string,fails:int,down:bool}
	 */
	public static function health(): array {
		$h = Legacy::get_option( self::HEALTH_OPTION, array() );
		$h = is_array( $h ) ? $h : array();
		return array(
			'ok_at'     => (int) ( $h['ok_at'] ?? 0 ),
			'err_since' => (int) ( $h['err_since'] ?? 0 ),
			'err_at'    => (int) ( $h['err_at'] ?? 0 ),
			'err'       => (string) ( $h['err'] ?? '' ),
			'fails'     => (int) ( $h['fails'] ?? 0 ),
			'down'      => ! empty( $h['down'] ),
		);
	}

	/** Whether the latest API call for the configured token failed. */
	public static function failing(): bool {
		return self::health()['down'];
	}

	/** Recently failed: skip new attempts for BACKOFF seconds. */
	private static function backing_off(): bool {
		$h = self::health();
		return $h['down'] && ( time() - $h['err_at'] ) < self::BACKOFF;
	}

	/**
	 * Record an API result for the configured token.
	 *
	 * @param \WP_Error|null $err Error, or null on success.
	 * @return \WP_Error|null The same error.
	 */
	private static function note( ?\WP_Error $err ): ?\WP_Error {
		$h   = self::health();
		$now = time();
		$was = $h['down'];
		if ( null === $err ) {
			self::$last_error = null;
			// Write only on recovery or once an hour.
			if ( $was || ( $now - $h['ok_at'] ) > HOUR_IN_SECONDS ) {
				if ( $was ) {
					self::log( 'info', '文档站已恢复。' );
				}
				$h['ok_at'] = $now;
				$h['fails'] = 0;
				$h['down']  = false;
				Legacy::update_option( self::HEALTH_OPTION, $h );
			}
			return null;
		}
		self::$last_error = $err;
		if ( ! $was ) {
			$h['err_since'] = $now;
			self::log( 'error', '文档站不可达: ' . $err->get_error_message() );
		}
		$h['err_at'] = $now;
		$h['err']    = mb_substr( $err->get_error_message(), 0, 200 );
		++$h['fails'];
		$h['down']   = true;
		Legacy::update_option( self::HEALTH_OPTION, $h );
		return $err;
	}

	/**
	 * Append to the sync log (kept small, autoload off).
	 *
	 * @param string $level   info|warn|error.
	 * @param string $message Message.
	 */
	public static function log( string $level, string $message ): void {
		$log   = Legacy::get_option( self::LOG_OPTION, array() );
		$log   = is_array( $log ) ? $log : array();
		$log[] = array(
			't' => time(),
			'l' => $level,
			'm' => mb_substr( $message, 0, 500 ),
		);
		if ( count( $log ) > self::LOG_MAX ) {
			$log = array_slice( $log, - self::LOG_MAX );
		}
		Legacy::update_option( self::LOG_OPTION, $log );
	}
}
