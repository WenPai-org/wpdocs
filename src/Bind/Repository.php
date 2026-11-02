<?php
/**
 * Bindings table: WP post ↔ BookStack page.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Bind;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Repository {

	public const DB_VERSION = '1';
	public const DB_OPTION  = 'wpdocs_bindings_db';

	public static function table(): string {
		global $wpdb;
		return $wpdb->prefix . 'wpdocs_bindings';
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
				wp_post_id bigint(20) unsigned NOT NULL DEFAULT 0,
				wp_post_type varchar(32) NOT NULL DEFAULT 'post',
				docs_page_id bigint(20) unsigned NOT NULL DEFAULT 0,
				docs_book_slug varchar(191) NOT NULL DEFAULT '',
				docs_page_slug varchar(191) NOT NULL DEFAULT '',
				external_id varchar(191) NOT NULL DEFAULT '',
				source varchar(32) NOT NULL DEFAULT 'manual',
				status varchar(20) NOT NULL DEFAULT 'active',
				notes text NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY wp_post (wp_post_id),
				KEY docs_page (docs_page_id),
				KEY docs_slugs (docs_book_slug, docs_page_slug),
				KEY external_id (external_id)
			) {$charset};"
		);
		update_option( self::DB_OPTION, self::DB_VERSION, false );
	}

	/** @return object|null */
	public static function find_by_post( int $post_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE wp_post_id = %d', $post_id )
		);
	}

	/** @return object|null */
	public static function find_by_docs_page( int $page_id ) {
		global $wpdb;
		return $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::table() . ' WHERE docs_page_id = %d AND status = %s', $page_id, 'active' )
		);
	}

	/**
	 * Load binding rows for a set of WP post ids.
	 *
	 * @param int[] $post_ids
	 * @return object[]
	 */
	public static function find_by_posts( array $post_ids ): array {
		global $wpdb;
		$ids = array();
		foreach ( $post_ids as $id ) {
			$id = (int) $id;
			if ( $id > 0 ) {
				$ids[ $id ] = $id;
			}
		}
		$ids = array_values( $ids );
		if ( ! $ids ) {
			return array();
		}
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );
		$sql          = $wpdb->prepare(
			'SELECT * FROM ' . self::table() . ' WHERE wp_post_id IN (' . $placeholders . ')',
			...$ids
		);
		if ( ! is_string( $sql ) ) {
			return array();
		}
		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $sql is $wpdb->prepare() output.
		$rows = $wpdb->get_results( $sql );
		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * Upsert source=slug drafts. REST calls this only when apply=true.
	 *
	 * @param array<int, array<string,mixed>> $drafts
	 * @return int[] Binding row ids.
	 */
	public static function apply_slug_drafts( array $drafts ): array {
		$ids = array();
		foreach ( $drafts as $draft ) {
			if ( ! is_array( $draft ) ) {
				continue;
			}
			$draft['source'] = 'slug';
			if ( empty( $draft['status'] ) ) {
				$draft['status'] = 'active';
			}
			$id = self::upsert( $draft );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}
		return $ids;
	}

	/**
	 * Upsert a binding.
	 *
	 * @param array<string,mixed> $data
	 */
	public static function upsert( array $data ): int {
		global $wpdb;
		$now  = current_time( 'mysql' );
		$row  = array(
			'wp_post_id'      => (int) ( $data['wp_post_id'] ?? 0 ),
			'wp_post_type'    => sanitize_key( (string) ( $data['wp_post_type'] ?? 'post' ) ),
			'docs_page_id'    => (int) ( $data['docs_page_id'] ?? 0 ),
			'docs_book_slug'  => sanitize_title( (string) ( $data['docs_book_slug'] ?? '' ) ),
			'docs_page_slug'  => sanitize_title( (string) ( $data['docs_page_slug'] ?? '' ) ),
			'external_id'     => sanitize_text_field( (string) ( $data['external_id'] ?? '' ) ),
			'source'          => sanitize_key( (string) ( $data['source'] ?? 'manual' ) ),
			'status'          => sanitize_key( (string) ( $data['status'] ?? 'active' ) ),
			'notes'           => sanitize_textarea_field( (string) ( $data['notes'] ?? '' ) ),
			'updated_at'      => $now,
		);
		$existing = $row['wp_post_id'] ? self::find_by_post( $row['wp_post_id'] ) : null;
		if ( $existing ) {
			$wpdb->update( self::table(), $row, array( 'id' => (int) $existing->id ) );
			return (int) $existing->id;
		}
		$row['created_at'] = $now;
		$wpdb->insert( self::table(), $row );
		return (int) $wpdb->insert_id;
	}

	/** @return object[] */
	public static function list( int $limit = 100, int $offset = 0 ): array {
		global $wpdb;
		$limit  = max( 1, min( 500, $limit ) );
		$offset = max( 0, $offset );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results( "SELECT * FROM " . self::table() . " ORDER BY id DESC LIMIT {$limit} OFFSET {$offset}" );
	}

	public static function count(): int {
		global $wpdb;
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::table() );
	}
}
