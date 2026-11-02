<?php
/**
 * Redirects table (new name; can import from legacy cocobook_redirects).
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

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpdocs_redirects';
	}

	/** @return string[] */
	public static function statuses(): array {
		return array( self::MATCHED, self::PENDING, self::UNMATCHED, self::GONE );
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
		$path = preg_replace( '/[?#].*$/', '', $path );
		$path = '/' . ltrim( (string) $path, '/' );
		return ( '/' === $path ) ? '/' : rtrim( $path, '/' );
	}

	public static function hit( int $id ): void {
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
	 * @param array<string,mixed> $data
	 */
	public static function upsert( array $data ): int {
		global $wpdb;
		$path = self::norm_path( (string) ( $data['wp_path'] ?? '' ) );
		if ( '' === $path || '/' === $path ) {
			return 0;
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
}
