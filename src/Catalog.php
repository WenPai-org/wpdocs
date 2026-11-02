<?php
/**
 * Catalog: maps brand-site posts to BookStack pages.
 *
 * @package Wpdocs
 */

namespace Wpdocs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds and queries the page index.
 *
 * Matching order for a WP post (legacy URLs must stay byte-for-byte):
 *   1. page tag `wp-id`   == post ID
 *   2. page tag `wp-path` == post permalink path
 *   3. page tag `wp-slug` == post_name
 *
 * @since 0.1.0
 */
final class Catalog {

	private const KEY = 'catalog_v1';

	/**
	 * Memoized catalog for this request.
	 *
	 * @var array<string,mixed>|null
	 */
	private static ?array $memo = null;

	/**
	 * Get catalog (cached, stale on failure).
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function get() {
		if ( null !== self::$memo ) {
			return self::$memo;
		}
		$cat = Client::remember( self::KEY, (int) Settings::get( 'page_ttl' ), array( self::class, 'build' ) );
		if ( ! is_wp_error( $cat ) ) {
			self::$memo = $cat;
		}
		return $cat;
	}

	/** Force a rebuild now. Returns the catalog or error. */
	public static function rebuild() {
		self::$memo = null;
		Client::mark_stale( self::KEY );
		Client::$last_error = null;
		$cat = self::get();
		if ( ! is_wp_error( $cat ) && Client::$last_error ) {
			$cat = Client::$last_error; // The old catalog stays in use; report the failure.
		}
		if ( is_wp_error( $cat ) ) {
			Client::log( 'error', 'Sync failed: ' . $cat->get_error_message() );
		} else {
			Client::log(
				'info',
				sprintf(
					'Synced %d books, %d pages (%d with wp-id, %d with wp-path).',
					count( $cat['books'] ),
					count( $cat['pages'] ),
					count( $cat['by_wp_id'] ),
					count( $cat['by_wp_path'] )
				)
			);
		}
		return $cat;
	}

	/**
	 * Resolve the book IDs to index.
	 *
	 * @return array<int,array<string,mixed>>|\WP_Error id => book row.
	 */
	public static function selected_books() {
		$all = Client::list_all( 'books' );
		if ( is_wp_error( $all ) ) {
			return $all;
		}
		$by_id = array();
		foreach ( $all as $b ) {
			if ( ! empty( $b['id'] ) ) {
				$by_id[ (int) $b['id'] ] = $b;
			}
		}
		$picked = Settings::books();
		if ( ! $picked ) {
			$site = (string) Settings::get( 'site_tag' );
			if ( '' === $site ) {
				return array();
			}
			$hits = self::search_all( '{type:book} [site=' . $site . ']' );
			if ( is_wp_error( $hits ) ) {
				return $hits;
			}
			foreach ( $hits as $h ) {
				$picked[] = (int) $h['id'];
			}
		}
		$out = array();
		foreach ( $picked as $id ) {
			if ( isset( $by_id[ $id ] ) ) {
				$out[ $id ] = $by_id[ $id ];
			}
		}
		return $out;
	}

	/**
	 * Build the catalog from the API.
	 *
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function build() {
		$books = self::selected_books();
		if ( is_wp_error( $books ) ) {
			return $books;
		}
		$cat = array(
			'built'      => gmdate( 'c' ),
			'books'      => array(),
			'pages'      => array(),
			'by_wp_id'   => array(),
			'by_wp_path' => array(),
			'by_wp_slug' => array(),
			'by_slug'    => array(),
			'conflicts'  => array(),
		);
		if ( ! $books ) {
			return $cat;
		}

		// Tags for every tagged page in one search sweep (list endpoint has no tags).
		$tags_by_page = array();
		$hits         = self::search_all( '{type:page} [wp-id]' );
		if ( is_wp_error( $hits ) ) {
			return $hits;
		}
		$more = self::search_all( '{type:page} [wp-path]' );
		if ( ! is_wp_error( $more ) ) {
			$hits = array_merge( $hits, $more );
		}
		foreach ( $hits as $h ) {
			$tags_by_page[ (int) $h['id'] ] = self::tag_map( $h['tags'] ?? array() );
		}

		foreach ( $books as $bid => $book ) {
			$pages = Client::list_all( 'pages', array( 'filter[book_id]' => $bid ) );
			if ( is_wp_error( $pages ) ) {
				return $pages;
			}
			$cat['books'][ $bid ] = array(
				'id'   => $bid,
				'slug' => (string) ( $book['slug'] ?? '' ),
				'name' => (string) ( $book['name'] ?? '' ),
			);
			foreach ( $pages as $pg ) {
				$pid = (int) ( $pg['id'] ?? 0 );
				if ( $pid < 1 || ! empty( $pg['draft'] ) || ! empty( $pg['template'] ) ) {
					continue;
				}
				$tags  = $tags_by_page[ $pid ] ?? array();
				$entry = array(
					'id'         => $pid,
					'name'       => (string) ( $pg['name'] ?? '' ),
					'slug'       => (string) ( $pg['slug'] ?? '' ),
					'book_id'    => $bid,
					'chapter_id' => (int) ( $pg['chapter_id'] ?? 0 ),
					'priority'   => (int) ( $pg['priority'] ?? 0 ),
					'updated_at' => (string) ( $pg['updated_at'] ?? '' ),
					'wp_id'      => isset( $tags['wp-id'] ) ? absint( $tags['wp-id'] ) : 0,
					'wp_path'    => isset( $tags['wp-path'] ) ? self::normalize_path( $tags['wp-path'] ) : '',
					'wp_slug'    => isset( $tags['wp-slug'] ) ? sanitize_title( $tags['wp-slug'] ) : '',
					'source'     => (string) ( $tags['source'] ?? '' ),
				);
				$cat['pages'][ $pid ] = $entry;
				self::index( $cat, 'by_wp_id', (string) $entry['wp_id'], $pid, '0' );
				self::index( $cat, 'by_wp_path', $entry['wp_path'], $pid, '' );
				self::index( $cat, 'by_wp_slug', $entry['wp_slug'], $pid, '' );
				self::index( $cat, 'by_slug', $entry['slug'], $pid, '' );
			}
		}
		return $cat;
	}

	/**
	 * Add to an index; first wins, later duplicates are recorded as conflicts.
	 *
	 * @param array<string,mixed> $cat   Catalog (by ref).
	 * @param string              $index Index name.
	 * @param string              $key   Key.
	 * @param int                 $pid   Page ID.
	 * @param string              $empty Value meaning "no key".
	 */
	private static function index( array &$cat, string $index, string $key, int $pid, string $empty ): void {
		if ( '' === $key || $empty === $key ) {
			return;
		}
		if ( isset( $cat[ $index ][ $key ] ) && $cat[ $index ][ $key ] !== $pid ) {
			$cat['conflicts'][] = array( $index, $key, $cat[ $index ][ $key ], $pid );
			return;
		}
		$cat[ $index ][ $key ] = $pid;
	}

	/**
	 * Find the page entry for a WP post.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string,mixed>|null
	 */
	public static function for_post( \WP_Post $post ): ?array {
		$cat = self::get();
		if ( is_wp_error( $cat ) ) {
			return null;
		}
		$pid = $cat['by_wp_id'][ (string) $post->ID ] ?? 0;
		if ( ! $pid ) {
			$path = self::normalize_path( (string) wp_parse_url( (string) get_permalink( $post ), PHP_URL_PATH ) );
			$pid  = $cat['by_wp_path'][ $path ] ?? 0;
		}
		if ( ! $pid && '' !== $post->post_name ) {
			$pid = $cat['by_wp_slug'][ $post->post_name ] ?? 0;
		}
		return $pid ? ( $cat['pages'][ $pid ] ?? null ) : null;
	}

	/**
	 * Full page (HTML + tags), cached.
	 *
	 * @param int $page_id Page ID.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function page( int $page_id ) {
		return Client::remember(
			'page_' . $page_id,
			(int) Settings::get( 'page_ttl' ),
			static function () use ( $page_id ) {
				return Client::get( 'pages/' . $page_id );
			}
		);
	}

	/**
	 * Invalidate one page (and the catalog, so renames/moves show up).
	 *
	 * @param int $page_id Page ID.
	 */
	public static function invalidate( int $page_id ): void {
		if ( $page_id > 0 ) {
			Client::mark_stale( 'page_' . $page_id );
		}
		Client::mark_stale( self::KEY );
		self::$memo = null;
	}

	/**
	 * All results of a BookStack search.
	 *
	 * @param string $query Search query.
	 * @return array<int,array<string,mixed>>|\WP_Error
	 */
	private static function search_all( string $query ) {
		$out = array();
		for ( $page = 1; $page <= 50; $page++ ) {
			$res = Client::get(
				'search',
				array(
					'query' => $query,
					'page'  => $page,
					'count' => 100,
				)
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$data = isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : array();
			foreach ( $data as $row ) {
				if ( ! empty( $row['id'] ) ) {
					$out[] = $row;
				}
			}
			$total = isset( $res['total'] ) ? (int) $res['total'] : 0;
			if ( ! $data || count( $out ) >= $total ) {
				break;
			}
		}
		return $out;
	}

	/**
	 * Tag list to lowercase-name map.
	 *
	 * @param mixed $tags Tags array.
	 * @return array<string,string>
	 */
	public static function tag_map( $tags ): array {
		$out = array();
		if ( ! is_array( $tags ) ) {
			return $out;
		}
		foreach ( $tags as $t ) {
			if ( is_array( $t ) && ! empty( $t['name'] ) ) {
				$out[ strtolower( (string) $t['name'] ) ] = (string) ( $t['value'] ?? '' );
			}
		}
		return $out;
	}

	/**
	 * Normalize a URL path: leading slash, trailing slash, decoded, lowercase host-less.
	 *
	 * @param string $path Path or URL.
	 */
	public static function normalize_path( string $path ): string {
		$path = trim( $path );
		if ( '' === $path ) {
			return '';
		}
		if ( preg_match( '#^https?://#i', $path ) ) {
			$path = (string) wp_parse_url( $path, PHP_URL_PATH );
		}
		$path = '/' . trim( rawurldecode( $path ), '/' );
		return '/' === $path ? '/' : $path . '/';
	}
}
