<?php
/**
 * Frontend: body swap, comments alignment, SEO.
 *
 * @package Wpdocs
 */

namespace Wpdocs;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders CocoBook pages on existing brand-site URLs.
 *
 * - URL, template, sidebar of the WP post stay untouched; only the body changes.
 * - Comments use `cocobook:page:{id}` so the brand site, the docs site and the
 *   forum share one Discourse topic.
 * - Canonical stays self-referencing (each site is its own canonical).
 *
 * @since 0.1.0
 */
final class Frontend {

	/**
	 * Per-request decision cache.
	 *
	 * @var array<int,array<string,mixed>|false>
	 */
	private static array $decided = array();

	/** Register hooks. */
	public static function register(): void {
		add_filter( 'the_content', array( self::class, 'filter_content' ), 20 );
		add_filter( 'wpcy_pc_external_id', array( self::class, 'filter_external_id' ), 10, 2 );
		add_filter( 'wpcy_pc_forum_fallback', array( self::class, 'filter_forum_fallback' ), 10, 2 );
		add_filter( 'render_block', array( self::class, 'filter_comments_block' ), 25, 2 );
		add_filter( 'slim_seo_canonical_url', array( self::class, 'filter_canonical' ), 20, 2 );
		add_filter( 'slim_seo_meta_description', array( self::class, 'filter_description' ), 20, 2 );
		add_action( 'wp_head', array( self::class, 'print_head' ), 2 );
		add_action( 'send_headers', array( self::class, 'send_headers' ) );
	}

	/**
	 * Page entry if this post is taken over, else null.
	 *
	 * @param int|\WP_Post|null $post Post.
	 * @return array<string,mixed>|null
	 */
	public static function entry_for( $post = null ): ?array {
		$post = get_post( $post );
		if ( ! $post instanceof \WP_Post ) {
			return null;
		}
		if ( isset( self::$decided[ $post->ID ] ) ) {
			return self::$decided[ $post->ID ] ?: null;
		}
		$entry = null;
		$mode  = Settings::mode();
		if (
			Settings::MODE_OFF !== $mode
			&& Settings::is_configured()
			&& 'publish' === $post->post_status
			&& in_array( $post->post_type, Settings::post_types(), true )
			&& ( Settings::MODE_ALL === $mode || in_array( (int) $post->ID, Settings::pilot_post_ids(), true ) )
		) {
			$entry = Catalog::for_post( $post );
			if ( null === $entry ) {
				$entry = self::entry_from_binding( $post );
			}
		}
		/**
		 * Filter whether a post is rendered from CocoBook.
		 *
		 * @param array|null $entry Catalog entry or null.
		 * @param \WP_Post   $post  Post.
		 */
		$entry                     = apply_filters( 'cocobook_docs_entry', $entry, $post );
		self::$decided[ $post->ID ] = is_array( $entry ) ? $entry : false;
		return is_array( $entry ) ? $entry : null;
	}

	/**
	 * Fallback: an active Bind row maps this post to a CocoBook page when the
	 * catalog tags do not. Catalog tags stay the preferred source.
	 *
	 * @param \WP_Post $post Post.
	 * @return array<string,mixed>|null
	 */
	private static function entry_from_binding( \WP_Post $post ): ?array {
		$row = Bind\Repository::find_by_post( (int) $post->ID );
		if ( ! $row || 'active' !== (string) ( $row->status ?? '' ) ) {
			return null;
		}
		$pid = (int) ( $row->docs_page_id ?? 0 );
		if ( $pid < 1 ) {
			return null;
		}
		return array(
			'id'         => $pid,
			'name'       => '',
			'slug'       => (string) ( $row->docs_page_slug ?? '' ),
			'book_id'    => 0,
			'chapter_id' => 0,
			'priority'   => 0,
			'updated_at' => '',
			'wp_id'      => (int) $post->ID,
			'wp_path'    => '',
			'wp_slug'    => (string) $post->post_name,
			'source'     => 'binding',
		);
	}

	/** Entry for the main queried post on singular views. */
	private static function current_entry(): ?array {
		if ( is_admin() || ! is_singular() ) {
			return null;
		}
		return self::entry_for( get_queried_object_id() );
	}

	/** External thread ID for a page. */
	public static function external_id( array $entry ): string {
		return 'cocobook:page:' . (int) $entry['id'];
	}

	/**
	 * Swap the post body for the CocoBook page HTML.
	 *
	 * @param string $content Original content.
	 */
	public static function filter_content( $content ) {
		if ( is_admin() || ! is_singular() || ! in_the_loop() || ! is_main_query() ) {
			return $content;
		}
		$entry = self::entry_for( get_the_ID() );
		if ( ! $entry ) {
			return $content;
		}
		$page = Catalog::page( (int) $entry['id'] );
		if ( is_wp_error( $page ) || empty( $page['html'] ) ) {
			return $content; // Keep the WP copy rather than an empty page.
		}
		$html  = self::prepare_html( (string) $page['html'] );
		$out   = '<div class="wpdocs-body" data-cocobook-page="' . esc_attr( (string) $entry['id'] ) . '">' . $html . '</div>';
		$out  .= self::source_note( $entry, $page );
		if ( Settings::comments_enabled() && ! function_exists( 'wpcy_pc_render_mount' ) && comments_open() ) {
			$out .= self::comments_mount( $entry );
		}
		return $out;
	}

	/**
	 * Clean BookStack HTML for the brand site.
	 *
	 * @param string $html Raw page HTML.
	 */
	public static function prepare_html( string $html ): string {
		$base = Settings::base_url();
		// Drop import footnotes ("导入自 …") left by the WP → BookStack importer.
		$html = (string) preg_replace( '#<p[^>]*>\s*(?:<[^>]+>\s*)*导入自[\s\S]*?</p>#u', '', $html );
		// Absolutize root-relative links and images to the docs site.
		$html = (string) preg_replace_callback(
			'#\s(href|src)="(/[^/"][^"]*)"#',
			static function ( array $m ) use ( $base ): string {
				return ' ' . $m[1] . '="' . esc_url( $base . $m[2] ) . '"';
			},
			$html
		);
		$html = wp_kses_post( $html );
		/**
		 * Filter the prepared CocoBook body HTML.
		 *
		 * @param string $html Body HTML.
		 */
		return (string) apply_filters( 'cocobook_docs_body_html', $html );
	}

	/**
	 * "Synced from CocoBook" footnote.
	 *
	 * @param array<string,mixed> $entry Entry.
	 * @param array<string,mixed> $page  Page.
	 */
	private static function source_note( array $entry, array $page ): string {
		$url     = Settings::base_url() . '/link/' . (int) $entry['id'];
		$updated = ! empty( $page['updated_at'] ) ? mysql2date( get_option( 'date_format' ), get_date_from_gmt( gmdate( 'Y-m-d H:i:s', strtotime( (string) $page['updated_at'] ) ) ) ) : '';
		$text    = $updated
			/* translators: %s: date */
			? sprintf( __( '本文同步自 文派文档，更新于 %s', 'wpdocs' ), esc_html( $updated ) )
			: __( '本文同步自 文派文档', 'wpdocs' );
		return '<p class="wpdocs-source"><a href="' . esc_url( $url ) . '" rel="noopener">' . $text . '</a></p>';
	}

	/**
	 * Own comments mount (sites without wpcy-product-comments).
	 *
	 * @param array<string,mixed> $entry Entry.
	 */
	public static function comments_mount( array $entry ): string {
		$post = get_post();
		return self::mount_for_page(
			(int) $entry['id'],
			$post ? wp_strip_all_tags( get_the_title( $post ) ) : '',
			$post ? (string) get_permalink( $post ) : ''
		);
	}

	/**
	 * Comments mount bound to a CocoBook page (`cocobook:page:{id}`).
	 * Shared by body-swap mode, the [cocobook_comments] shortcode and the block.
	 * Renders at most once per request.
	 *
	 * @param int    $page_id CocoBook page ID.
	 * @param string $title   Title used by notifications.
	 * @param string $url     URL of the discussion.
	 */
	public static function mount_for_page( int $page_id, string $title = '', string $url = '' ): string {
		static $done = false;
		if ( $done || $page_id <= 0 ) {
			return '';
		}
		$done = true;
		$api  = Settings::bff_url();
		$eid  = self::external_id( array( 'id' => $page_id ) );
		$sort = 'desc' === strtolower( (string) get_option( 'comment_order', 'asc' ) ) ? 'desc' : 'asc';
		$html = '<section id="comments" class="wpdocs-comments" aria-label="' . esc_attr__( '参与讨论', 'wpdocs' ) . '">'
			. '<div data-product-comments data-pc-mode="inline"'
			. ' data-external-id="' . esc_attr( $eid ) . '"'
			. ' data-sort="' . esc_attr( $sort ) . '"'
			. ' data-page-title="' . esc_attr( $title ) . '"'
			. ' data-page-url="' . esc_url( $url ) . '"></div>'
			. '</section>';
		wp_enqueue_script( 'wpdocs-comments-widget', $api . '/static/widget.js', array(), WPDOCS_VERSION, true );
		add_filter(
			'script_loader_tag',
			static function ( string $tag, string $handle ) use ( $api, $sort, $eid ): string {
				if ( 'wpdocs-comments-widget' !== $handle ) {
					return $tag;
				}
				return str_replace( ' src=', ' data-api="' . esc_attr( $api ) . '" data-mode="inline" data-sort="' . esc_attr( $sort ) . '" data-external-id="' . esc_attr( $eid ) . '" defer src=', $tag );
			},
			10,
			2
		);
		return $html;
	}

	/**
	 * Align wpcy-product-comments thread ID with CocoBook.
	 *
	 * @param string $id      Default `wpcy:post:{ID}`.
	 * @param int    $post_id Post ID.
	 */
	public static function filter_external_id( $id, $post_id = 0 ) {
		if ( ! Settings::comments_enabled() ) {
			return $id;
		}
		$entry = self::entry_for( (int) $post_id );
		return $entry ? self::external_id( $entry ) : $id;
	}

	/**
	 * Forum fallback link: the docs category stays as configured upstream.
	 *
	 * @param string $url     Default URL.
	 * @param int    $post_id Post ID.
	 */
	public static function filter_forum_fallback( $url, $post_id = 0 ) {
		return $url;
	}

	/**
	 * Replace the core/comments block with our mount on sites without wpcy-pc.
	 *
	 * @param string               $content Block HTML.
	 * @param array<string,mixed>  $block   Block.
	 */
	public static function filter_comments_block( $content, $block ) {
		if ( 'core/comments' !== ( $block['blockName'] ?? '' ) || function_exists( 'wpcy_pc_render_mount' ) || ! Settings::comments_enabled() ) {
			return $content;
		}
		$entry = self::current_entry();
		if ( ! $entry ) {
			return $content;
		}
		return comments_open( get_queried_object_id() ) ? self::comments_mount( $entry ) : '';
	}

	/**
	 * Canonical = this post's own permalink.
	 *
	 * @param string $url Canonical.
	 * @param int    $id  Post ID.
	 */
	public static function filter_canonical( $url, $id = 0 ) {
		$pid = $id ? (int) $id : (int) get_queried_object_id();
		return self::entry_for( $pid ) ? (string) get_permalink( $pid ) : $url;
	}

	/**
	 * Meta description from the CocoBook body when the post has no excerpt.
	 *
	 * @param string $desc Description.
	 * @param int    $id   Post ID.
	 */
	public static function filter_description( $desc, $id = 0 ) {
		$pid   = $id ? (int) $id : (int) get_queried_object_id();
		$entry = self::entry_for( $pid );
		if ( ! $entry || has_excerpt( $pid ) ) {
			return $desc;
		}
		$page = Catalog::page( (int) $entry['id'] );
		if ( is_wp_error( $page ) || empty( $page['html'] ) ) {
			return $desc;
		}
		$text = trim( preg_replace( '/\s+/u', ' ', wp_strip_all_tags( (string) $page['html'] ) ) );
		return '' !== $text ? mb_substr( $text, 0, 150 ) : $desc;
	}

	/** Minimal body styles + alternate link to the docs page. */
	public static function print_head(): void {
		$entry = self::current_entry();
		if ( ! $entry ) {
			return;
		}
		echo '<link rel="alternate" href="' . esc_url( Settings::base_url() . '/link/' . (int) $entry['id'] ) . '" title="CocoBook" />' . "\n";
		echo '<style id="wpdocs-body-css">.wpdocs-body img{max-width:100%;height:auto}.wpdocs-body pre{overflow:auto}.wpdocs-body table{display:block;overflow-x:auto;border-collapse:collapse}.wpdocs-body td,.wpdocs-body th{border:1px solid rgba(127,127,127,.25);padding:.4em .6em}.wpdocs-source{margin-top:2em;font-size:.85em;opacity:.7}</style>' . "\n";
	}

	/** Debug header so pilots are easy to verify with curl. */
	public static function send_headers(): void {
		if ( ! is_singular() ) {
			return;
		}
		$entry = self::current_entry();
		if ( $entry ) {
			header( 'X-Wpdocs-Page: ' . (int) $entry['id'] );
		}
	}
}
