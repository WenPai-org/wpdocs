<?php
/**
 * Normalize BookStack search hits into bind form candidates (no HTTP).
 *
 * @package Wpdocs
 */

namespace Wpdocs\Bind;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Suggest {

	/**
	 * Flatten one BookStack `/api/search` row.
	 *
	 * @param array<string,mixed> $row Raw hit.
	 * @return array{type:string,id:int,name:string,slug:string,url:string,book:?array{id:int,slug:string,name:string}}|null
	 */
	public static function normalize_search_row( array $row ): ?array {
		$type = (string) ( $row['type'] ?? '' );
		if ( '' === $type && isset( $row['_type'] ) ) {
			$type = (string) $row['_type'];
		}
		$id = (int) ( $row['id'] ?? 0 );
		if ( $id < 1 && '' === $type ) {
			return null;
		}
		$book = null;
		if ( isset( $row['book'] ) && is_array( $row['book'] ) ) {
			$book = array(
				'id'   => (int) ( $row['book']['id'] ?? 0 ),
				'slug' => (string) ( $row['book']['slug'] ?? '' ),
				'name' => (string) ( $row['book']['name'] ?? '' ),
			);
		}
		return array(
			'type' => $type,
			'id'   => $id,
			'name' => (string) ( $row['name'] ?? $row['title'] ?? '' ),
			'slug' => (string) ( $row['slug'] ?? '' ),
			'url'  => (string) ( $row['url'] ?? '' ),
			'book' => $book,
		);
	}

	/**
	 * Bind-shaped candidates (pages only by default).
	 *
	 * @param array<int,mixed> $raw Search `data` list.
	 * @param string           $type_filter Empty = all types; default `page`.
	 * @return array<int,array{docs_page_id:int,docs_page_slug:string,docs_book_slug:string,title:string,url:string,type:string}>
	 */
	public static function bind_candidates( array $raw, string $type_filter = 'page' ): array {
		$out = array();
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$n = self::normalize_search_row( $row );
			if ( null === $n ) {
				continue;
			}
			if ( '' !== $type_filter && $type_filter !== $n['type'] ) {
				continue;
			}
			$book_slug = '';
			if ( is_array( $n['book'] ) ) {
				$book_slug = (string) ( $n['book']['slug'] ?? '' );
			}
			$out[] = array(
				'docs_page_id'   => (int) $n['id'],
				'docs_page_slug' => (string) $n['slug'],
				'docs_book_slug' => $book_slug,
				'title'          => (string) $n['name'],
				'url'            => (string) $n['url'],
				'type'           => (string) $n['type'],
			);
		}
		return $out;
	}
}
