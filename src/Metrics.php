<?php
/**
 * Ops metrics: bind coverage, unmatched trend, probe fail rate.
 *
 * Pure helpers are unit-tested without WordPress. Live snapshot/record use options.
 *
 * @package Wpdocs
 */

namespace Wpdocs;

use Wpdocs\Bind\Repository as BindRepo;
use Wpdocs\Redirect\Repository as RedirectRepo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Compact counters for GET /status and the overview card.
 * History is a 14-day ring in option wpdocs_metrics_v1 (counts only, no bodies).
 */
final class Metrics {

	public const OPTION      = 'wpdocs_metrics_v1';
	public const HISTORY_MAX = 14;

	/**
	 * Coverage = active bindings / eligible posts.
	 *
	 * @return array{active:int,eligible:int,ratio:?float}
	 */
	public static function coverage( int $active, int $eligible ): array {
		$active   = max( 0, $active );
		$eligible = max( 0, $eligible );
		return array(
			'active'   => $active,
			'eligible' => $eligible,
			'ratio'    => $eligible > 0 ? round( $active / $eligible, 4 ) : null,
		);
	}

	/**
	 * Fail rate from a list of home-probe ok flags (oldest → newest).
	 *
	 * @param list<bool> $oks
	 */
	public static function probe_fail_rate( array $oks ): ?float {
		$n = count( $oks );
		if ( $n < 1 ) {
			return null;
		}
		$fails = 0;
		foreach ( $oks as $ok ) {
			if ( ! $ok ) {
				++$fails;
			}
		}
		return round( $fails / $n, 4 );
	}

	/**
	 * Replace same-day point and keep the last $max days.
	 *
	 * @param list<array<string,mixed>> $history
	 * @param array<string,mixed>       $point
	 * @return list<array<string,mixed>>
	 */
	public static function merge_day( array $history, string $day, array $point, int $max = self::HISTORY_MAX ): array {
		$day = preg_replace( '/[^0-9\-]/', '', $day ) ?: '';
		if ( '' === $day ) {
			return array_values( $history );
		}
		$max = max( 1, min( 90, $max ) );
		$out = array();
		foreach ( $history as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			if ( (string) ( $row['day'] ?? '' ) === $day ) {
				continue;
			}
			$out[] = $row;
		}
		$point['day'] = $day;
		$out[]        = $point;
		if ( count( $out ) > $max ) {
			$out = array_slice( $out, -$max );
		}
		return array_values( $out );
	}

	/**
	 * Assemble the public metrics object from already-resolved numbers (pure).
	 *
	 * @param array{
	 *   active?:int,
	 *   disabled?:int,
	 *   total?:int,
	 *   eligible?:int,
	 *   redirects?:array<string,int>,
	 *   home_ok?:bool|null,
	 *   history?:list<array<string,mixed>>
	 * } $in
	 * @return array<string,mixed>
	 */
	public static function summarize( array $in ): array {
		$active    = (int) ( $in['active'] ?? 0 );
		$disabled  = (int) ( $in['disabled'] ?? 0 );
		$total     = (int) ( $in['total'] ?? ( $active + $disabled ) );
		$eligible  = (int) ( $in['eligible'] ?? 0 );
		$redir     = isset( $in['redirects'] ) && is_array( $in['redirects'] ) ? $in['redirects'] : array();
		$history   = isset( $in['history'] ) && is_array( $in['history'] ) ? $in['history'] : array();
		$home_ok   = array_key_exists( 'home_ok', $in ) ? $in['home_ok'] : null;

		$oks = array();
		foreach ( $history as $row ) {
			if ( ! is_array( $row ) || ! array_key_exists( 'home_ok', $row ) ) {
				continue;
			}
			$oks[] = (bool) $row['home_ok'];
		}

		$cov = self::coverage( $active, $eligible );

		$unmatched_series = array();
		foreach ( $history as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$d = (string) ( $row['day'] ?? '' );
			if ( '' === $d ) {
				continue;
			}
			$unmatched_series[] = array(
				'day'       => $d,
				'unmatched' => (int) ( $row['unmatched'] ?? 0 ),
			);
		}

		return array(
			'bindings'  => array(
				'active'   => $cov['active'],
				'disabled' => $disabled,
				'total'    => $total,
				'eligible' => $cov['eligible'],
				'ratio'    => $cov['ratio'],
			),
			'redirects' => array(
				'unmatched' => (int) ( $redir['unmatched'] ?? 0 ),
				'matched'   => (int) ( $redir['matched'] ?? 0 ),
				'gone'      => (int) ( $redir['gone'] ?? 0 ),
				'total'     => (int) ( $redir['total'] ?? 0 ),
			),
			'probe'     => array(
				'home_ok'       => null === $home_ok ? null : (bool) $home_ok,
				'fail_rate_14d' => self::probe_fail_rate( $oks ),
				'samples'       => count( $oks ),
			),
			'history'   => array(
				'unmatched' => $unmatched_series,
				'days'      => count( $history ),
			),
		);
	}

	/**
	 * Live snapshot for REST / overview.
	 *
	 * @param array<string,mixed>|null $publish Optional publish status (avoids re-probe).
	 * @return array<string,mixed>
	 */
	public static function snapshot( ?array $publish = null ): array {
		$active   = BindRepo::count( 'active' );
		$disabled = BindRepo::count( 'disabled' );
		$total    = BindRepo::count();
		$eligible = self::eligible_post_count();

		$redir = array(
			'unmatched' => RedirectRepo::count( RedirectRepo::UNMATCHED ),
			'matched'   => RedirectRepo::count( RedirectRepo::MATCHED ),
			'gone'      => RedirectRepo::count( RedirectRepo::GONE ),
			'total'     => RedirectRepo::count(),
		);

		$stored  = self::stored();
		$history = isset( $stored['history'] ) && is_array( $stored['history'] ) ? $stored['history'] : array();

		$home_ok = null;
		if ( is_array( $publish ) ) {
			$home_ok = ! empty( $publish['ok'] ) || ! empty( $publish['home']['ok'] );
		}

		return self::summarize(
			array(
				'active'    => $active,
				'disabled'  => $disabled,
				'total'     => $total,
				'eligible'  => $eligible,
				'redirects' => $redir,
				'home_ok'   => $home_ok,
				'history'   => $history,
			)
		);
	}

	/**
	 * Append today's point (idempotent per UTC day). Called from daily_check.
	 *
	 * @param array<string,mixed>|null $publish Optional publish status.
	 */
	public static function record_daily( ?array $publish = null ): void {
		$day = gmdate( 'Y-m-d' );
		$stored = self::stored();
		$history = isset( $stored['history'] ) && is_array( $stored['history'] ) ? $stored['history'] : array();

		$home_ok = true;
		if ( is_array( $publish ) ) {
			$home_ok = ! empty( $publish['ok'] ) || ! empty( $publish['home']['ok'] );
		} elseif ( class_exists( '\Wpdocs\Publish\HttpSource', false ) ) {
			try {
				$st = \Wpdocs\Publish\HttpSource::status( false );
				$home_ok = is_array( $st ) && ( ! empty( $st['ok'] ) || ! empty( $st['home']['ok'] ) );
			} catch ( \Throwable $e ) {
				$home_ok = false;
			}
		}

		$point = array(
			'day'       => $day,
			'unmatched' => RedirectRepo::count( RedirectRepo::UNMATCHED ),
			'bindings'  => BindRepo::count( 'active' ),
			'eligible'  => self::eligible_post_count(),
			'home_ok'   => $home_ok,
		);

		$stored['history']   = self::merge_day( $history, $day, $point );
		$stored['updated']   = time();
		$stored['version']   = 1;
		update_option( self::OPTION, $stored, false );
	}

	/**
	 * Posts that "should" have docs when rollout is on.
	 * Pilot → configured pilot IDs; otherwise published count of selected types.
	 */
	public static function eligible_post_count(): int {
		if ( ! class_exists( '\Wpdocs\Settings', false ) ) {
			return 0;
		}
		if ( Settings::MODE_PILOT === Settings::mode() && Settings::CONTENT_COCOBOOK === Settings::content_mode() ) {
			return count( Settings::pilot_post_ids() );
		}
		$types = Settings::post_types();
		if ( empty( $types ) ) {
			$types = array( 'post' );
		}
		$n = 0;
		foreach ( $types as $pt ) {
			if ( ! is_string( $pt ) || '' === $pt || ! post_type_exists( $pt ) ) {
				continue;
			}
			$counts = wp_count_posts( $pt );
			if ( is_object( $counts ) && isset( $counts->publish ) ) {
				$n += (int) $counts->publish;
			}
		}
		return $n;
	}

	/**
	 * @return array<string,mixed>
	 */
	private static function stored(): array {
		$raw = get_option( self::OPTION, array() );
		return is_array( $raw ) ? $raw : array();
	}
}
