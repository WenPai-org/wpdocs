<?php
/**
 * Redirects table (new name; can import from legacy cocobook_redirects).
 *
 * Behaviour vs cocobook-docs Redirects.php (contract; old tree not cloned this round):
 * - Table is wpdocs_redirects; matched stores page_id, never an absolute docs URL.
 * - Unmatched 404 inserts are capped (UNMATCHED_LOG_CAP / WPDOCS_UNMATCHED_LOG_CAP).
 * - Reserved paths are rejected on upsert and skipped on 404 log.
 * - Legacy import is one-shot when this table is empty (does not overwrite existing rows).
 * TODO: confirm old column set and whether cocobook_docs_404 option still needs a one-shot import.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Redirect;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Repository {

	public const DB_VERSION = '1';
	public const DB_OPTION  = 'wpdocs_redirects_db';

	public const MATCHED   = 'matched';
	public const PENDING   = 'pending';
	public const UNMATCHED = 'unmatched';
	public const GONE      = 'gone';

	/**
	 * Max unmatched/404 rows that may be inserted. Existing rows are kept.
	 * Override with WPDOCS_UNMATCHED_LOG_CAP (0 disables new 404 inserts).
	 */
	public const UNMATCHED_LOG_CAP = 500;

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpdocs_redirects';
	}

	/** @return string[] */
	public static function statuses(): array {
		return array( self::MATCHED, self::PENDING, self::UNMATCHED, self::GONE );
	}

	public static function unmatched_log_cap(): int {
		if ( defined( 'WPDOCS_UNMATCHED_LOG_CAP' ) ) {
			return max( 0, (int) WPDOCS_UNMATCHED_LOG_CAP );
		}
		$cap = self::UNMATCHED_LOG_CAP;
		if ( function_exists( 'apply_filters' ) ) {
			$cap = (int) apply_filters( 'wpdocs_unmatched_log_cap', $cap );
		}
		return max( 0, $cap );
	}

	/** Whether a new unmatched row may be inserted given the current unmatched count. */
	public static function can_insert_unmatched( ?int $current = null ): bool {
		$cap = self::unmatched_log_cap();
		if ( $cap <= 0 ) {
			return false;
		}
		if ( null === $current ) {
			$current = self::count( self::UNMATCHED );
		}
		return $current < $cap;
	}

	public static function install( bool $force = false ): void {
		if ( ! $force && self::DB_VERSION === get_option( self::DB_OPTION ) ) {
			return;
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$t       = self::table();
		$charset = $wpdb->get_charset_collate();
		dbDelta(
			"CREATE TABLE {$t} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				wp_path varchar(191) NOT NULL,
				wp_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				wp_title text NULL,
				page_id bigint(20) unsigned NOT NULL DEFAULT 0,
				status varchar(20) NOT NULL DEFAULT 'unmatched',
				source varchar(20) NOT NULL DEFAULT 'auto',
				hits bigint(20) unsigned NOT NULL DEFAULT 0,
				last_hit datetime NULL DEFAULT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY wp_path (wp_path),
				KEY status (status),
				KEY page_id (page_id)
			) {$charset};"
		);
		update_option( self::DB_OPTION, self::DB_VERSION, false );
		self::maybe_import_legacy();
	}

	/** One-shot copy from {$prefix}cocobook_redirects if new table empty. */
	private static function maybe_import_legacy(): void {
		global $wpdb;
		$new = self::table();
		$old = $wpdb->prefix . 'cocobook_redirects';
		$n   = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$new}" );
		if ( $n > 0 ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$exists = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $old ) );
		if ( $exists !== $old ) {
			return;
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "INSERT INTO {$new} (wp_path, wp_post_id, wp_title, page_id, status, source, hits, last_hit, created_at, updated_at)
			SELECT wp_path, wp_post_id, wp_title, page_id, status, source, hits, last_hit, created_at, updated_at FROM {$old}" );
	}

	/** @return object|null */
	public static function find( string $path ) {
		global $wpdb;
		$path = self::norm_path( $path );
		if ( '' === $path ) {
			return null;
		}
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE wp_path = %s', $path )
		);
	}

	public static function norm_path( string $path ): string {
		return Path::norm_path( $path );
	}

	public static function hit( int $id ): void {
		if ( $id <= 0 ) {
			return;
		}
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'UPDATE ' . self::table() . ' SET hits = hits + 1, last_hit = %s WHERE id = %d',
				current_time( 'mysql' ),
				$id
			)
		);
	}

	/**
	 * Insert or bump an unmatched 404 row. Never called from dry-run.
	 *
	 * @return int Row id, or 0 when skipped (reserved, noisy asset, or cap).
	 */
	public static function log_unmatched( string $path ): int {
		$path = Path::norm_path( $path );
		if ( Path::should_skip_404_log( $path ) ) {
			return 0;
		}
		$existing = self::find( $path );
		if ( $existing ) {
			if ( in_array( $existing->status, array( self::UNMATCHED, self::PENDING ), true ) ) {
				self::hit( (int) $existing->id );
			}
			return (int) $existing->id;
		}
		if ( ! self::can_insert_unmatched() ) {
			return 0;
		}
		return self::upsert(
			array(
				'wp_path' => $path,
				'status'  => self::UNMATCHED,
				'source'  => '404',
				'page_id' => 0,
			)
		);
	}

	/**
	 * @param array<string,mixed> $data
	 */
	public static function upsert( array $data ): int {
		global $wpdb;
		$path = self::norm_path( (string) ( $data['wp_path'] ?? '' ) );
		if ( '' === $path || '/' === $path || Path::is_reserved( $path ) ) {
			return 0;
		}
		if ( isset( $data['target_url'] ) || isset( $data['url'] ) ) {
			// Matched rows store page_id only; refuse baked-in absolute URLs.
			unset( $data['target_url'], $data['url'] );
		}
		$now = current_time( 'mysql' );
		$row = array(
			'wp_path'    => $path,
			'wp_post_id' => (int) ( $data['wp_post_id'] ?? 0 ),
			'wp_title'   => sanitize_text_field( (string) ( $data['wp_title'] ?? '' ) ),
			'page_id'    => (int) ( $data['page_id'] ?? 0 ),
			'status'     => sanitize_key( (string) ( $data['status'] ?? self::UNMATCHED ) ),
			'source'     => sanitize_key( (string) ( $data['source'] ?? 'manual' ) ),
			'updated_at' => $now,
		);
		if ( ! in_array( $row['status'], self::statuses(), true ) ) {
			$row['status'] = self::UNMATCHED;
		}
		if ( self::MATCHED === $row['status'] && $row['page_id'] <= 0 ) {
			$row['status'] = self::UNMATCHED;
		}
		$existing = self::find( $path );
		if ( $existing ) {
			$wpdb->update( self::table(), $row, array( 'id' => (int) $existing->id ) );
			return (int) $existing->id;
		}
		$row['created_at'] = $now;
		$row['hits']       = 0;
		$wpdb->insert( self::table(), $row );
		return (int) $wpdb->insert_id;
	}

	/** @return object[] */
	public static function list( string $status = '', int $limit = 100, int $offset = 0 ): array {
		global $wpdb;
		$limit  = max( 1, min( 500, $limit ) );
		$offset = max( 0, $offset );
		if ( $status && in_array( $status, self::statuses(), true ) ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . self::table() . ' WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d',
					$status,
					$limit,
					$offset
				)
			);
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM " . self::table() . " ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}" );
	}

	public static function count( string $status = '' ): int {
		global $wpdb;
		if ( $status && in_array( $status, self::statuses(), true ) ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::table() . ' WHERE status = %s', $status )
			);
		}
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
	}

	/** @return object|null */
	public static function find_by_id( int $id ) {
		if ( $id <= 0 ) {
			return null;
		}
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE id = %d', $id )
		);
	}

	public static function delete( int $id ): bool {
		if ( $id <= 0 ) {
			return false;
		}
		global $wpdb;
		$n = $wpdb->delete( self::table(), array( 'id' => $id ), array( '%d' ) );
		return false !== $n && $n > 0;
	}
}
