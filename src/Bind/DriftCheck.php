<?php
/**
 * Periodic sample of bindings vs remote BookStack pages (slug drift / missing).
 *
 * Stores a compact summary transient only — never page HTML.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Bind;

use Wpdocs\Http\BookStackClient;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class DriftCheck {

	public const TRANSIENT = 'wpdocs_bind_drift_v1';
	public const TTL       = DAY_IN_SECONDS;

	/**
	 * Aggregate RemoteMeta rows into a compact report.
	 *
	 * @param array<int, array{state?:string,title?:string,slug?:string}> $meta Map page_id → meta.
	 * @param object[]                                                    $rows Binding rows (for counts).
	 * @return array{checked:int,ok:int,broken:int,stale:int,forbidden:int,unknown:int,skipped:int,at:int,samples:array<int,array{wp_post_id:int,docs_page_id:int,state:string,title:string}>}
	 */
	public static function summarize( array $meta, array $rows ): array {
		$by_page = array();
		foreach ( $rows as $row ) {
			if ( ! is_object( $row ) ) {
				continue;
			}
			$pid = (int) ( $row->docs_page_id ?? 0 );
			if ( $pid < 1 ) {
				continue;
			}
			$by_page[ $pid ] = (int) ( $row->wp_post_id ?? 0 );
		}

		$out = array(
			'checked'   => 0,
			'ok'        => 0,
			'broken'    => 0,
			'stale'     => 0,
			'forbidden' => 0,
			'unknown'   => 0,
			'skipped'   => 0,
			'at'        => time(),
			'samples'   => array(),
		);

		foreach ( $meta as $page_id => $m ) {
			if ( ! is_array( $m ) ) {
				continue;
			}
			$state = (string) ( $m['state'] ?? RemoteMeta::STATE_UNKNOWN );
			++$out['checked'];
			if ( isset( $out[ $state ] ) && is_int( $out[ $state ] ) ) {
				++$out[ $state ];
			} else {
				++$out['unknown'];
			}
			if ( in_array( $state, array( RemoteMeta::STATE_BROKEN, RemoteMeta::STATE_STALE, RemoteMeta::STATE_FORBIDDEN ), true ) && count( $out['samples'] ) < 10 ) {
				$out['samples'][] = array(
					'wp_post_id'   => (int) ( $by_page[ (int) $page_id ] ?? 0 ),
					'docs_page_id' => (int) $page_id,
					'state'        => $state,
					'title'        => (string) ( $m['title'] ?? '' ),
				);
			}
		}
		return $out;
	}

	/**
	 * Compare active bindings against catalog tag mappings (local data only).
	 *
	 * binding_only: bound post has no wp-id tag on any catalog page.
	 * tag_only:     catalog wp-id tag exists but no active binding row.
	 * conflict:     binding and tag point to different docs pages.
	 *
	 * @return array{checked:int,both:int,binding_only:int,tag_only:int,conflict:int}
	 */
	public static function consistency(): array {
		$out = array(
			'checked'      => 0,
			'both'         => 0,
			'binding_only' => 0,
			'tag_only'     => 0,
			'conflict'     => 0,
		);
		$cat = \Wpdocs\Catalog::get();
		if ( is_wp_error( $cat ) || ! isset( $cat['by_wp_id'] ) || ! is_array( $cat['by_wp_id'] ) ) {
			return $out;
		}
		$bound = array();
		foreach ( Repository::list( 500 ) as $row ) {
			if ( ! is_object( $row ) || 'active' !== (string) ( $row->status ?? '' ) ) {
				continue;
			}
			$pid  = (int) ( $row->wp_post_id ?? 0 );
			$page = (int) ( $row->docs_page_id ?? 0 );
			if ( $pid < 1 || $page < 1 ) {
				continue;
			}
			$bound[ (string) $pid ] = $page;
			++$out['checked'];
		}
		foreach ( $bound as $pid => $page ) {
			$tagged = $cat['by_wp_id'][ $pid ] ?? 0;
			if ( ! $tagged ) {
				++$out['binding_only'];
			} elseif ( (int) $tagged === $page ) {
				++$out['both'];
			} else {
				++$out['conflict'];
			}
		}
		foreach ( $cat['by_wp_id'] as $pid => $page ) {
			if ( ! isset( $bound[ (string) $pid ] ) ) {
				++$out['tag_only'];
			}
		}
		return $out;
	}

	/**
	 * Probe up to RemoteMeta::CAP bindings and cache the summary.
	 *
	 * @return array<string,mixed>
	 */
	public static function run( ?BookStackClient $client = null ): array {
		$rows = Repository::list( RemoteMeta::CAP, 0 );
		$meta = RemoteMeta::for_rows( $rows, $client, RemoteMeta::TTL, RemoteMeta::CAP );
		$report = self::summarize( $meta, $rows );
		$report['consistency'] = self::consistency();
		set_transient( self::TRANSIENT, $report, self::TTL );
		return $report;
	}

	/**
	 * Last report, or empty shape.
	 *
	 * @return array<string,mixed>
	 */
	public static function last(): array {
		$cached = get_transient( self::TRANSIENT );
		if ( is_array( $cached ) && isset( $cached['checked'] ) ) {
			return $cached;
		}
		return array(
			'checked'      => 0,
			'ok'           => 0,
			'broken'       => 0,
			'stale'        => 0,
			'forbidden'    => 0,
			'unknown'      => 0,
			'skipped'      => 0,
			'at'           => 0,
			'samples'      => array(),
			'consistency'  => array(
				'checked'      => 0,
				'both'         => 0,
				'binding_only' => 0,
				'tag_only'     => 0,
				'conflict'     => 0,
			),
		);
	}
}
