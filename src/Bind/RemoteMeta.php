<?php
/**
 * Enrich bindings with remote BookStack page title / broken state.
 *
 * Short-TTL transient cache per page id. Never stores HTML bodies.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Bind;

use Wpdocs\Http\BookStackClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class RemoteMeta {

	public const TTL = 300;
	public const CAP = 20;

	public const STATE_OK        = 'ok';
	public const STATE_BROKEN    = 'broken';
	public const STATE_STALE     = 'stale';
	public const STATE_FORBIDDEN = 'forbidden';
	public const STATE_SKIPPED   = 'skipped';
	public const STATE_UNKNOWN   = 'unknown';

	/**
	 * Map docs_page_id → remote summary for a binding list.
	 *
	 * @param object[]            $rows   Binding rows (need docs_page_id, docs_page_slug).
	 * @param BookStackClient|null $client Injected or from_config().
	 * @param int                 $ttl    Transient seconds.
	 * @param int                 $cap    Max unique page ids to probe this request.
	 * @return array<int, array{state:string,title:string,slug:string,updated_at:string,error:string,slug_match:bool,attachments:int}>
	 */
	public static function for_rows( array $rows, ?BookStackClient $client = null, int $ttl = self::TTL, int $cap = self::CAP ): array {
		$ids = array();
		$slug_by_id = array();
		foreach ( $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$pid = (int) ( $row->docs_page_id ?? 0 );
			if ( $pid < 1 ) {
				continue;
			}
			$ids[ $pid ] = $pid;
			if ( ! isset( $slug_by_id[ $pid ] ) ) {
				$slug_by_id[ $pid ] = (string) ( $row->docs_page_slug ?? '' );
			}
		}
		$ids = array_values( $ids );
		if ( ! $ids ) {
			return array();
		}

		$cap = max( 1, min( 100, $cap ) );
		$ids = array_slice( $ids, 0, $cap );
		$ttl = max( 30, $ttl );

		if ( null === $client ) {
			$client = BookStackClient::from_config();
		}

		$out        = array();
		$infra_fail = 0;
		foreach ( $ids as $pid ) {
			$stored_slug = (string) ( $slug_by_id[ $pid ] ?? '' );
			// Circuit breaker: after repeated transport/auth failures the API is
			// down or misconfigured — stop probing so admin renders stay fast.
			if ( $infra_fail >= 3 ) {
				$out[ $pid ] = self::circuit_open();
				continue;
			}
			$meta = self::for_page( $pid, $client, $stored_slug, $ttl );
			$out[ $pid ] = $meta;
			$err         = (string) ( $meta['error'] ?? '' );
			if ( in_array( $err, array( BookStackClient::ERR_HTTP, BookStackClient::ERR_CONFIG, BookStackClient::ERR_AUTH, BookStackClient::ERR_RATE ), true ) ) {
				++$infra_fail;
			} else {
				$infra_fail = 0;
			}
		}
		return $out;
	}

	/** Placeholder row when the circuit breaker skips remaining probes. */
	private static function circuit_open(): array {
		return array(
			'state'       => self::STATE_UNKNOWN,
			'title'       => '',
			'slug'        => '',
			'updated_at'  => '',
			'error'       => 'circuit_open',
			'slug_match'  => true,
			'attachments' => 0,
		);
	}

	/**
	 * Probe one page (cache-aware).
	 *
	 * @return array{state:string,title:string,slug:string,updated_at:string,error:string,slug_match:bool,attachments:int}
	 */
	public static function for_page( int $page_id, ?BookStackClient $client, string $stored_slug = '', int $ttl = self::TTL ): array {
		$empty = array(
			'state'       => self::STATE_SKIPPED,
			'title'       => '',
			'slug'        => '',
			'updated_at'  => '',
			'error'       => '',
			'slug_match'  => true,
			'attachments' => 0,
		);
		if ( $page_id < 1 ) {
			return $empty;
		}
		if ( null === $client ) {
			$empty['error'] = BookStackClient::ERR_CONFIG;
			return $empty;
		}

		$key    = self::cache_key( $page_id );
		$cached = get_transient( $key );
		if ( is_array( $cached ) && isset( $cached['state'] ) ) {
			return self::normalize( $cached, $stored_slug );
		}

		$res   = $client->page( $page_id );
		$meta  = self::from_response( $res );
		$meta  = self::normalize( $meta, $stored_slug );
		set_transient( $key, $meta, max( 30, $ttl ) );
		return $meta;
	}

	/**
	 * @param array{ok?:bool,error?:string,data?:array<string,mixed>} $res Client response.
	 * @return array{state:string,title:string,slug:string,updated_at:string,error:string,slug_match:bool,attachments:int}
	 */
	public static function from_response( array $res ): array {
		$base = array(
			'state'       => self::STATE_UNKNOWN,
			'title'       => '',
			'slug'        => '',
			'updated_at'  => '',
			'error'       => (string) ( $res['error'] ?? '' ),
			'slug_match'  => true,
			'attachments' => 0,
		);
		if ( ! empty( $res['ok'] ) && isset( $res['data'] ) && is_array( $res['data'] ) ) {
			$data = $res['data'];
			$title = '';
			if ( isset( $data['name'] ) && is_scalar( $data['name'] ) ) {
				$title = (string) $data['name'];
			} elseif ( isset( $data['title'] ) && is_scalar( $data['title'] ) ) {
				$title = (string) $data['title'];
			}
			$slug = isset( $data['slug'] ) && is_scalar( $data['slug'] ) ? (string) $data['slug'] : '';
			$upd  = '';
			foreach ( array( 'updated_at', 'updated_at_date', 'updated' ) as $k ) {
				if ( isset( $data[ $k ] ) && is_scalar( $data[ $k ] ) && '' !== (string) $data[ $k ] ) {
					$upd = (string) $data[ $k ];
					break;
				}
			}
			$att = 0;
			if ( isset( $data['attachments'] ) && is_array( $data['attachments'] ) ) {
				$att = count( $data['attachments'] );
			} elseif ( isset( $data['attachment_count'] ) && is_numeric( $data['attachment_count'] ) ) {
				$att = (int) $data['attachment_count'];
			}
			$base['state']       = self::STATE_OK;
			$base['title']       = $title;
			$base['slug']        = $slug;
			$base['updated_at']  = $upd;
			$base['error']       = '';
			$base['attachments'] = max( 0, $att );
			return $base;
		}
		$err = (string) ( $res['error'] ?? '' );
		if ( BookStackClient::ERR_NOT_FOUND === $err ) {
			$base['state'] = self::STATE_BROKEN;
			$base['error'] = $err;
			return $base;
		}
		if ( BookStackClient::ERR_AUTH === $err ) {
			$base['state'] = self::STATE_FORBIDDEN;
			$base['error'] = $err;
			return $base;
		}
		$base['state'] = self::STATE_UNKNOWN;
		$base['error'] = '' !== $err ? $err : BookStackClient::ERR_HTTP;
		return $base;
	}

	/**
	 * @param array<string,mixed> $meta
	 * @return array{state:string,title:string,slug:string,updated_at:string,error:string,slug_match:bool,attachments:int}
	 */
	public static function normalize( array $meta, string $stored_slug = '' ): array {
		$state = (string) ( $meta['state'] ?? self::STATE_UNKNOWN );
		$slug  = (string) ( $meta['slug'] ?? '' );
		$match = true;
		if ( '' !== $stored_slug && '' !== $slug && self::STATE_OK === $state ) {
			$match = sanitize_title( $stored_slug ) === sanitize_title( $slug );
			if ( ! $match ) {
				$state = self::STATE_STALE;
			}
		}
		return array(
			'state'       => $state,
			'title'       => (string) ( $meta['title'] ?? '' ),
			'slug'        => $slug,
			'updated_at'  => (string) ( $meta['updated_at'] ?? '' ),
			'error'       => (string) ( $meta['error'] ?? '' ),
			'slug_match'  => $match,
			'attachments' => max( 0, (int) ( $meta['attachments'] ?? 0 ) ),
		);
	}

	public static function cache_key( int $page_id ): string {
		return 'wpdocs_page_meta_' . $page_id;
	}

	public static function bust( int $page_id ): void {
		if ( $page_id > 0 ) {
			delete_transient( self::cache_key( $page_id ) );
		}
	}
}
