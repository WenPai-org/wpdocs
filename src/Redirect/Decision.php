<?php
/**
 * Pure redirect plan (no hits, no inserts). Used by REST dry-run and tests.
 *
 * Behaviour vs cocobook-docs Redirects.php (backup 2026-10-28):
 * - Dry-run is wpdocs-only (old had no REST preview); never writes hits / 404 rows.
 * - Matched plans expose page_id; URL built at request time (never baked absolute).
 * - 301/410 parity with old maybe_redirect for matched/gone.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Redirect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Decision {

	/**
	 * Preview the action for a normalized path + optional table row.
	 *
	 * Always sets hits_written=false. Callers must not persist on this path.
	 *
	 * @param object|null $row Redirects table row.
	 * @return array<string,mixed>
	 */
	public static function preview( string $path, $row ): array {
		$path = Path::norm_path( $path );
		$base = array(
			'path'         => $path,
			'hits_written' => false,
		);
		if ( Path::is_reserved( $path ) ) {
			return $base + array( 'action' => 'reserved' );
		}
		if ( ! $row ) {
			return $base + array( 'action' => 'none' );
		}
		$id      = (int) ( $row->id ?? 0 );
		$page_id = (int) ( $row->page_id ?? 0 );
		$status  = (string) ( $row->status ?? '' );
		if ( Repository::GONE === $status ) {
			return $base + array(
				'action'  => '410',
				'id'      => $id,
				'page_id' => $page_id,
			);
		}
		if ( Repository::MATCHED === $status && $page_id > 0 ) {
			return $base + array(
				'action'  => '301',
				'id'      => $id,
				'page_id' => $page_id,
			);
		}
		return $base + array(
			'action'  => 'skip',
			'status'  => $status,
			'id'      => $id,
			'page_id' => $page_id,
		);
	}
}
