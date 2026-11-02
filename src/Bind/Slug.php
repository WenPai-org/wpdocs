<?php
/**
 * Pure slug helpers for Bind source=slug matching (no $wpdb).
 *
 * Exact: post_name === docs_page_slug (optional book filter).
 * Book+page: post_name === "{book}/{page}" or "{book}-{page}".
 * Fuzzy (prefix / Levenshtein / title): stubbed empty — never invents a weak bind.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Bind;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Slug {

	public const MATCH_EXACT     = 'exact';
	public const MATCH_BOOK_PAGE = 'book_page';
	public const MATCH_AMBIGUOUS = 'ambiguous';
	public const MATCH_NONE      = 'none';

	/**
	 * ASCII slug: lowercase, whitespace → hyphen, keep a-z 0-9 / -.
	 *
	 * Intentionally does not call sanitize_title() so dry-run tests run without WP.
	 * Callers should send already-slugified post_name / catalog slugs.
	 */
	public static function norm( string $slug ): string {
		$slug = strtolower( trim( $slug ) );
		if ( '' === $slug ) {
			return '';
		}
		$slug = str_replace( array( '\\', '_' ), array( '/', '-' ), $slug );
		$ws   = preg_replace( '/\s+/', '-', $slug );
		$slug = is_string( $ws ) ? $ws : $slug;
		$cut  = preg_replace( '/[^a-z0-9\/-]+/', '', $slug );
		$slug = is_string( $cut ) ? $cut : '';
		$hyph = preg_replace( '/-+/', '-', $slug );
		$slug = is_string( $hyph ) ? $hyph : $slug;
		$sl   = preg_replace( '#/+#', '/', $slug );
		$slug = is_string( $sl ) ? $sl : $slug;
		return trim( $slug, '-/' );
	}

	/**
	 * Normalize a catalog page row. Accepts docs_* names and short aliases.
	 *
	 * @param array<string,mixed> $page
	 * @return array{docs_page_id:int,docs_book_slug:string,docs_page_slug:string}
	 */
	public static function page_fields( array $page ): array {
		return array(
			'docs_page_id'   => (int) ( $page['docs_page_id'] ?? $page['page_id'] ?? 0 ),
			'docs_book_slug' => self::norm( (string) ( $page['docs_book_slug'] ?? $page['book_slug'] ?? '' ) ),
			'docs_page_slug' => self::norm( (string) ( $page['docs_page_slug'] ?? $page['page_slug'] ?? $page['slug'] ?? '' ) ),
		);
	}

	/**
	 * Normalize a WP post row used as a match source.
	 *
	 * @param array<string,mixed> $post
	 * @return array{wp_post_id:int,post_name:string,post_type:string,book_slug:string,external_id:string}
	 */
	public static function post_fields( array $post ): array {
		$name = (string) ( $post['post_name'] ?? $post['slug'] ?? '' );
		$book = (string) ( $post['book_slug'] ?? $post['docs_book_slug'] ?? '' );
		$type = self::norm_key( (string) ( $post['post_type'] ?? 'post' ) );
		return array(
			'wp_post_id'  => (int) ( $post['wp_post_id'] ?? $post['ID'] ?? 0 ),
			'post_name'   => self::norm( $name ),
			'post_type'   => '' !== $type ? $type : 'post',
			'book_slug'   => self::norm( $book ),
			'external_id' => trim( (string) ( $post['external_id'] ?? '' ) ),
		);
	}

	/**
	 * Ranked candidates for one post_name against a catalog.
	 *
	 * @param array<int, array<string,mixed>> $pages Catalog rows.
	 * @return array{match:string,candidates:array<int,array{docs_page_id:int,docs_book_slug:string,docs_page_slug:string}>}
	 */
	public static function candidates( string $post_name, array $pages, string $book_filter = '' ): array {
		$name        = self::norm( $post_name );
		$book_filter = self::norm( $book_filter );
		$pool        = array();
		foreach ( $pages as $page ) {
			if ( ! is_array( $page ) ) {
				continue;
			}
			$row = self::page_fields( $page );
			if ( '' === $row['docs_page_slug'] ) {
				continue;
			}
			if ( '' !== $book_filter && $row['docs_book_slug'] !== $book_filter ) {
				continue;
			}
			$pool[] = $row;
		}
		$pool = self::unique_pages( $pool );
		if ( '' === $name ) {
			return array(
				'match'      => self::MATCH_NONE,
				'candidates' => array(),
			);
		}

		$exact = array();
		foreach ( $pool as $row ) {
			if ( $row['docs_page_slug'] === $name ) {
				$exact[] = $row;
			}
		}
		if ( $exact ) {
			return array(
				'match'      => ( 1 === count( $exact ) ) ? self::MATCH_EXACT : self::MATCH_AMBIGUOUS,
				'candidates' => $exact,
			);
		}

		$book_page = array();
		foreach ( $pool as $row ) {
			if ( self::is_book_page_name( $name, $row ) ) {
				$book_page[] = $row;
			}
		}
		if ( $book_page ) {
			return array(
				'match'      => ( 1 === count( $book_page ) ) ? self::MATCH_BOOK_PAGE : self::MATCH_AMBIGUOUS,
				'candidates' => $book_page,
			);
		}

		$fuzzy = self::fuzzy_candidates( $name, $pool );
		if ( $fuzzy ) {
			return array(
				'match'      => ( 1 === count( $fuzzy ) ) ? 'fuzzy' : self::MATCH_AMBIGUOUS,
				'candidates' => $fuzzy,
			);
		}

		return array(
			'match'      => self::MATCH_NONE,
			'candidates' => array(),
		);
	}

	/**
	 * Fuzzy / prefix / Levenshtein / title matching.
	 *
	 * Stub: always empty. Do not guess a bind from a nearby slug this round.
	 *
	 * @param array<int, array{docs_page_id:int,docs_book_slug:string,docs_page_slug:string}> $pages
	 * @return array<int, array{docs_page_id:int,docs_book_slug:string,docs_page_slug:string}>
	 */
	public static function fuzzy_candidates( string $post_name, array $pages ): array {
		unset( $post_name, $pages );
		return array();
	}

	/**
	 * @param array{docs_page_id:int,docs_book_slug:string,docs_page_slug:string} $page
	 */
	public static function is_book_page_name( string $name, array $page ): bool {
		$book = $page['docs_book_slug'] ?? '';
		$slug = $page['docs_page_slug'] ?? '';
		if ( '' === $book || '' === $slug ) {
			return false;
		}
		return $name === $book . '/' . $slug || $name === $book . '-' . $slug;
	}

	/**
	 * @param array<int, array{docs_page_id:int,docs_book_slug:string,docs_page_slug:string}> $pages
	 * @return array<int, array{docs_page_id:int,docs_book_slug:string,docs_page_slug:string}>
	 */
	public static function unique_pages( array $pages ): array {
		$out  = array();
		$seen = array();
		foreach ( $pages as $page ) {
			$id  = (int) ( $page['docs_page_id'] ?? 0 );
			$key = $id > 0
				? 'id:' . $id
				: 'slug:' . ( $page['docs_book_slug'] ?? '' ) . '/' . ( $page['docs_page_slug'] ?? '' );
			if ( isset( $seen[ $key ] ) ) {
				continue;
			}
			$seen[ $key ] = true;
			$out[]        = $page;
		}
		return $out;
	}

	/** Lowercase a-z0-9_- only (sanitize_key stand-in without WP). */
	public static function norm_key( string $key ): string {
		if ( function_exists( 'sanitize_key' ) ) {
			return sanitize_key( $key );
		}
		$key = strtolower( $key );
		$cut = preg_replace( '/[^a-z0-9_\-]/', '', $key );
		return is_string( $cut ) ? $cut : '';
	}
}
