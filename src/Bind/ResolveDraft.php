<?php
/**
 * Pure batch resolve plan for Bind source=slug (no $wpdb, no upsert).
 *
 * REST dry-run returns this JSON as-is with applied=false. Persistence happens
 * only in Rest\Controller when apply=true (slug drafts, never from this class).
 *
 * @package Wpdocs
 */

namespace Wpdocs\Bind;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ResolveDraft {

	public const SOURCE = 'slug';

	public const ACTION_BIND          = 'bind';
	public const ACTION_SKIP_EXISTING = 'skip_existing';
	public const ACTION_UNMATCHED     = 'unmatched';
	public const ACTION_AMBIGUOUS     = 'ambiguous';
	public const ACTION_INVALID       = 'invalid';

	/** Max posts and max pages processed in one plan. Extra rows are dropped. */
	public const MAX_ITEMS = 500;

	/**
	 * True only for explicit apply tokens. Default (missing/false/"") is dry-run.
	 *
	 * @param mixed $value
	 */
	public static function wants_apply( $value ): bool {
		return true === $value
			|| 1 === $value
			|| '1' === $value
			|| 'true' === $value;
	}

	/**
	 * Build a dry-run plan. Never writes to the database.
	 *
	 * `applied` is always false here even if the request sets apply=true.
	 *
	 * @param array<string,mixed> $request
	 * @return array<string,mixed>
	 */
	public static function plan( array $request ): array {
		$posts_in = self::as_list( $request['posts'] ?? array() );
		$pages_in = self::as_list( $request['pages'] ?? array() );
		$capped   = count( $posts_in ) > self::MAX_ITEMS || count( $pages_in ) > self::MAX_ITEMS;
		$posts_in = array_slice( $posts_in, 0, self::MAX_ITEMS );
		$pages_in = array_slice( $pages_in, 0, self::MAX_ITEMS );

		$pages = array();
		foreach ( $pages_in as $page ) {
			if ( is_array( $page ) ) {
				$pages[] = Slug::page_fields( $page );
			}
		}
		$pages = Slug::unique_pages( $pages );

		$book_slug = Slug::norm( (string) ( $request['book_slug'] ?? '' ) );
		$existing  = self::existing_post_ids( $request['existing'] ?? array() );

		$items   = array();
		$summary = array(
			'posts'         => 0,
			'pages'         => count( $pages ),
			'bind'          => 0,
			'ambiguous'     => 0,
			'unmatched'     => 0,
			'skip_existing' => 0,
			'invalid'       => 0,
			'capped'        => $capped,
		);

		foreach ( $posts_in as $raw ) {
			if ( ! is_array( $raw ) ) {
				continue;
			}
			$item = self::plan_item( $raw, $pages, $book_slug, $existing );
			$items[] = $item;
			++$summary['posts'];
			$action = (string) $item['action'];
			if ( isset( $summary[ $action ] ) && is_int( $summary[ $action ] ) ) {
				++$summary[ $action ];
			}
		}

		return array(
			'source'       => self::SOURCE,
			'apply'        => self::wants_apply( $request['apply'] ?? false ),
			'applied'      => false,
			'applied_ids'  => array(),
			'book_slug'    => $book_slug,
			'summary'      => $summary,
			'items'        => $items,
		);
	}

	/**
	 * Draft rows REST may persist when apply=true (action=bind only).
	 *
	 * @param array<string,mixed> $plan
	 * @return array<int, array<string,mixed>>
	 */
	public static function bind_drafts( array $plan ): array {
		$out = array();
		foreach ( $plan['items'] ?? array() as $item ) {
			if ( ! is_array( $item ) ) {
				continue;
			}
			if ( self::ACTION_BIND !== ( $item['action'] ?? '' ) ) {
				continue;
			}
			if ( empty( $item['draft'] ) || ! is_array( $item['draft'] ) ) {
				continue;
			}
			$out[] = $item['draft'];
		}
		return $out;
	}

	/**
	 * @param array<string,mixed> $raw
	 * @param array<int, array{docs_page_id:int,docs_book_slug:string,docs_page_slug:string}> $pages
	 * @param array<int, true> $existing
	 * @return array<string,mixed>
	 */
	private static function plan_item( array $raw, array $pages, string $book_slug, array $existing ): array {
		$post = Slug::post_fields( $raw );
		$filter = '' !== $post['book_slug'] ? $post['book_slug'] : $book_slug;
		$base   = array(
			'wp_post_id'  => $post['wp_post_id'],
			'post_name'   => $post['post_name'],
			'post_type'   => $post['post_type'],
			'book_slug'   => $filter,
			'match'       => Slug::MATCH_NONE,
			'action'      => self::ACTION_INVALID,
			'reason'      => '',
			'candidates'  => array(),
			'draft'       => null,
		);

		if ( $post['wp_post_id'] <= 0 ) {
			$base['reason'] = 'missing_wp_post_id';
			return $base;
		}
		if ( '' === $post['post_name'] ) {
			$base['reason'] = 'missing_post_name';
			return $base;
		}

		$found               = Slug::candidates( $post['post_name'], $pages, $filter );
		$base['match']       = $found['match'];
		$base['candidates']  = $found['candidates'];

		$draft = null;
		if ( Slug::MATCH_EXACT === $found['match'] || Slug::MATCH_BOOK_PAGE === $found['match'] ) {
			$hit   = $found['candidates'][0];
			$draft = array(
				'wp_post_id'     => $post['wp_post_id'],
				'wp_post_type'   => $post['post_type'],
				'docs_page_id'   => $hit['docs_page_id'],
				'docs_book_slug' => $hit['docs_book_slug'],
				'docs_page_slug' => $hit['docs_page_slug'],
				'external_id'    => $post['external_id'],
				'source'         => self::SOURCE,
				'status'         => 'active',
			);
			$base['draft'] = $draft;
		}

		if ( isset( $existing[ $post['wp_post_id'] ] ) ) {
			$base['action'] = self::ACTION_SKIP_EXISTING;
			$base['reason'] = 'already_bound';
			return $base;
		}

		if ( Slug::MATCH_AMBIGUOUS === $found['match'] ) {
			$base['action'] = self::ACTION_AMBIGUOUS;
			$base['reason'] = 'multiple_candidates';
			return $base;
		}

		if ( null !== $draft ) {
			$base['action'] = self::ACTION_BIND;
			$base['reason'] = $found['match'];
			return $base;
		}

		$base['action'] = self::ACTION_UNMATCHED;
		$base['reason'] = 'no_candidate';
		return $base;
	}

	/**
	 * @param mixed $value
	 * @return array<int, mixed>
	 */
	private static function as_list( $value ): array {
		return is_array( $value ) ? array_values( $value ) : array();
	}

	/**
	 * @param mixed $existing List of ints, or rows with wp_post_id.
	 * @return array<int, true>
	 */
	private static function existing_post_ids( $existing ): array {
		$ids = array();
		if ( ! is_array( $existing ) ) {
			return $ids;
		}
		foreach ( $existing as $row ) {
			if ( is_int( $row ) || ( is_string( $row ) && is_numeric( $row ) ) ) {
				$id = (int) $row;
			} elseif ( is_array( $row ) ) {
				$id = (int) ( $row['wp_post_id'] ?? 0 );
			} elseif ( is_object( $row ) && isset( $row->wp_post_id ) ) {
				$id = (int) $row->wp_post_id;
			} else {
				$id = 0;
			}
			if ( $id > 0 ) {
				$ids[ $id ] = true;
			}
		}
		return $ids;
	}
}
