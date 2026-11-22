<?php
/**
 * WPMind ecosystem integration (GEO llms.txt, Markdown Feed, module registry).
 *
 * Soft integration only: if WPMind is not installed or active, WordPress simply
 * never invokes these filters and WPDocs incurs zero overhead or errors.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Integration;

use Wpdocs\Admin\Menu;
use Wpdocs\Bind\Repository as BindRepo;
use Wpdocs\Catalog;
use Wpdocs\Client;
use Wpdocs\Frontend;
use Wpdocs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Connects WPDocs to WPMind GEO (`llms.txt` & Markdown Feed) and ecosystem status.
 *
 * @since 0.3.1
 */
final class WPMind {

	/** Default maximum number of documentation links exported to llms.txt. */
	public const LLMS_MAX_ITEMS = 50;

	/** Post meta keys that may hold raw Markdown source for a doc post. */
	private const RAW_MARKDOWN_META_KEYS = array(
		'_wpdocs_raw_markdown',
		'_wpdocs_markdown',
		'_giw_raw_markdown',
	);

	/** Post meta keys that mark a post as managed/imported by WPDocs or Git it Write. */
	private const DOC_MARKER_META_KEYS = array(
		'_wpdocs_raw_markdown',
		'_wpdocs_markdown',
		'_giw_raw_markdown',
		'_wpdocs_imported_from',
		'github_url',
		'_source_url',
	);

	/** Generic WordPress post types that are only treated as docs when bound or marked. */
	private const GENERIC_POST_TYPES = array( 'post', 'page', 'attachment', 'revision', 'nav_menu_item' );

	/** @var bool */
	private static bool $registered = false;

	/**
	 * Register WPMind integration filters (idempotent).
	 */
	public static function register(): void {
		if ( self::$registered || ! function_exists( 'add_filter' ) ) {
			return;
		}
		self::$registered = true;

		add_filter( 'wpmind_llms_txt_sections', array( self::class, 'filter_llms_txt_sections' ), 20, 2 );
		add_filter( 'wpmind_raw_markdown_for_post', array( self::class, 'filter_raw_markdown_for_post' ), 20, 2 );
		add_filter( 'wpmind_ecosystem_integrations', array( self::class, 'filter_ecosystem_integrations' ), 20, 2 );
	}

	/**
	 * Reset registration guard (for unit tests).
	 */
	public static function reset(): void {
		self::$registered = false;
	}

	/**
	 * Filter `wpmind_llms_txt_sections`: append a structured documentation section.
	 *
	 * Matches WPMind `LlmsTxtGenerator::render_structured_section()` schema:
	 * `['title' => string, 'items' => [['title' => string, 'url' => string, 'desc' => string], ...]]`.
	 *
	 * @param mixed  $sections Existing llms.txt sections.
	 * @param string $mode     Generation mode (e.g. 'default', 'full').
	 * @return array<string,mixed>
	 */
	public static function filter_llms_txt_sections( $sections, string $mode = 'default' ): array {
		$sections = is_array( $sections ) ? $sections : array();

		$limit = self::LLMS_MAX_ITEMS;
		if ( function_exists( 'apply_filters' ) ) {
			$filtered_limit = (int) apply_filters( 'wpdocs_wpmind_llms_max_items', $limit, $mode );
			if ( $filtered_limit > 0 ) {
				$limit = $filtered_limit;
			}
		}

		$items = self::collect_llms_items( $limit );
		if ( empty( $items ) ) {
			return $sections;
		}

		$sections['wpdocs'] = array(
			'title' => __( 'Documentation', 'wpdocs' ),
			'items' => $items,
		);

		return $sections;
	}

	/**
	 * Filter `wpmind_raw_markdown_for_post`: return raw or clean Markdown for WPDocs posts.
	 *
	 * Non-doc posts are returned untouched (`$raw`) so WPMind's default pipeline runs as usual.
	 *
	 * @param mixed $raw  Existing raw Markdown string or null.
	 * @param mixed $post Post object (`\WP_Post` or compatible object).
	 * @return mixed
	 */
	public static function filter_raw_markdown_for_post( $raw, $post ) {
		if ( is_string( $raw ) && '' !== trim( $raw ) ) {
			return $raw;
		}
		if ( ! is_object( $post ) || empty( $post->ID ) ) {
			return $raw;
		}

		$post_id = (int) $post->ID;

		// 1. Explicit raw Markdown stored in post meta always wins.
		$meta_md = self::read_raw_markdown_meta( $post_id );
		if ( null !== $meta_md ) {
			return $meta_md;
		}

		// Check whether this post belongs to WPDocs before doing any conversion.
		$cocobook_page_id = self::resolve_cocobook_page_id( $post );
		$is_doc           = $cocobook_page_id > 0 || self::is_marked_doc_post( $post );
		if ( ! $is_doc ) {
			return $raw;
		}

		// 2. Local Git Markdown file when safely located under configured git_base_directory.
		$local_md = self::read_local_git_markdown( $post_id );
		if ( null !== $local_md ) {
			return $local_md;
		}

		// 3. CocoBook / BookStack page (prefer native `markdown`, fallback to cleaned `html`).
		if ( $cocobook_page_id > 0 && self::settings_configured() ) {
			$remote_md = self::read_cocobook_markdown( $cocobook_page_id );
			if ( null !== $remote_md ) {
				return $remote_md;
			}
		}

		// 4. Clean Markdown conversion of the doc post's own content.
		$content = isset( $post->post_content ) ? (string) $post->post_content : '';
		$md      = self::html_to_markdown( $content );
		if ( '' !== $md ) {
			return $md;
		}

		return $raw;
	}

	/**
	 * Filter `wpmind_ecosystem_integrations`: register or enrich the WPDocs entry in place.
	 *
	 * @param mixed $items     Existing ecosystem integration entries.
	 * @param mixed $by_caller Usage stats keyed by caller slug.
	 * @return array<int,array<string,mixed>>
	 */
	public static function filter_ecosystem_integrations( $items, $by_caller = array() ): array {
		$items     = is_array( $items ) ? array_values( $items ) : array();
		$by_caller = is_array( $by_caller ) ? $by_caller : array();

		$context = array(
			'version'      => defined( 'WPDOCS_VERSION' ) ? (string) WPDOCS_VERSION : '',
			'settings_url' => self::settings_url(),
			'content_mode' => self::current_content_mode(),
			'doc_count'    => self::doc_count(),
		);

		$found = false;
		foreach ( $items as $idx => $entry ) {
			if ( is_array( $entry ) && 'wpdocs' === (string) ( $entry['slug'] ?? '' ) ) {
				$items[ $idx ] = self::build_ecosystem_entry( $entry, $by_caller, $context );
				$found         = true;
				break;
			}
		}

		if ( ! $found ) {
			$items[] = self::build_ecosystem_entry( array(), $by_caller, $context );
		}

		return array_values( $items );
	}

	/**
	 * Pure helper to construct/enrich a normalized WPDocs ecosystem entry.
	 *
	 * @param array<string,mixed> $existing  Existing entry if pre-populated by WPMind.
	 * @param array<string,mixed> $by_caller Usage stats keyed by caller slug.
	 * @param array<string,mixed> $context   Live WPDocs context (version, settings_url, content_mode, doc_count).
	 * @return array<string,mixed>
	 */
	public static function build_ecosystem_entry( array $existing, array $by_caller, array $context ): array {
		$caller = isset( $by_caller['wpdocs'] ) && is_array( $by_caller['wpdocs'] )
			? $by_caller['wpdocs']
			: array();

		$doc_count    = max( 0, (int) ( $context['doc_count'] ?? 0 ) );
		$content_mode = (string) ( $context['content_mode'] ?? Settings::CONTENT_COCOBOOK );
		$mode_labels  = array(
			Settings::CONTENT_COCOBOOK => __( '文档站读取', 'wpdocs' ),
			Settings::CONTENT_GIT      => __( 'Git 写入', 'wpdocs' ),
			Settings::CONTENT_KEEP     => __( '保留正文', 'wpdocs' ),
		);
		$mode_label   = $mode_labels[ $content_mode ] ?? $content_mode;

		$desc = $doc_count > 0
			? sprintf(
				/* translators: 1: doc count, 2: content mode label */
				__( '文档库已接入文派心思 GEO（llms.txt / Markdown Feed），当前可导出 %1$d 篇文档（%2$s）。', 'wpdocs' ),
				$doc_count,
				$mode_label
			)
			: sprintf(
				/* translators: %s: content mode label */
				__( '文档库已联动文派心思 GEO（llms.txt / Markdown Feed），当前模式：%s。', 'wpdocs' ),
				$mode_label
			);

		$version = (string) ( $context['version'] ?? '' );
		if ( '' === $version ) {
			$version = (string) ( $existing['version'] ?? '' );
		}

		$settings_url = (string) ( $context['settings_url'] ?? '' );
		if ( '' === $settings_url ) {
			$settings_url = (string) ( $existing['settings_url'] ?? '' );
		}

		$requests = isset( $existing['requests'] )
			? (int) $existing['requests']
			: (int) ( $caller['requests'] ?? 0 );
		$tokens   = isset( $existing['tokens'] )
			? (int) $existing['tokens']
			: (int) ( $caller['tokens'] ?? 0 );

		return array(
			'slug'         => 'wpdocs',
			'name'         => __( '文派文档（WPDocs）', 'wpdocs' ),
			'desc'         => $desc,
			'feature'      => __( 'GEO / 文档库导出', 'wpdocs' ),
			'icon'         => 'doc',
			'version'      => $version,
			'settings_url' => $settings_url,
			'requests'     => $requests,
			'tokens'       => $tokens,
			'doc_count'    => $doc_count,
			'active'       => true,
		);
	}

	/**
	 * Format a single structured llms.txt item (`title`, `url`, `desc`).
	 *
	 * @param string $title   Document title.
	 * @param string $url     Document permalink.
	 * @param string $excerpt Document excerpt or body snippet.
	 * @return array{title:string,url:string,desc:string}|null
	 */
	public static function format_llms_item( string $title, string $url, string $excerpt = '' ): ?array {
		$title = self::collapse_plain_text( $title );
		$url   = trim( $url );
		if ( '' === $title || '' === $url ) {
			return null;
		}

		$desc = self::collapse_plain_text( $excerpt );
		if ( '' !== $desc && self::str_len( $desc ) > 160 ) {
			$desc = rtrim( self::str_sub( $desc, 0, 157 ) ) . '...';
		}

		return array(
			'title' => $title,
			'url'   => $url,
			'desc'  => $desc,
		);
	}

	/**
	 * Convert HTML or Gutenberg post content to clean Markdown.
	 *
	 * @param string $html Raw HTML or block markup.
	 * @return string Clean Markdown text (empty string if no content).
	 */
	public static function html_to_markdown( string $html ): string {
		$html = trim( $html );
		if ( '' === $html ) {
			return '';
		}

		// Strip WordPress block comments and non-content elements.
		$html = (string) preg_replace( '/<!--\s*\/?wp:[^>]*-->/s', '', $html );
		$html = (string) preg_replace( '#<(script|style|noscript)\b[^>]*>[\s\S]*?</\1>#i', '', $html );
		// Drop BookStack import footnotes ("导入自 ...").
		$html = (string) preg_replace( '#<p[^>]*>\s*(?:<[^>]+>\s*)*导入自[\s\S]*?</p>#u', '', $html );

		// Protect fenced code blocks before inline/block replacements.
		$code_blocks = array();
		$html        = (string) preg_replace_callback(
			'#<pre\b[^>]*>\s*(?:<code\b([^>]*)>)?([\s\S]*?)(?:</code>)?\s*</pre>#i',
			static function ( array $m ) use ( &$code_blocks ): string {
				$attrs = (string) ( $m[1] ?? '' );
				$lang  = '';
				if ( preg_match( '/class=["\'][^"\']*(?:language-|lang-)([a-z0-9_+\-#]+)/i', $attrs, $lm ) ) {
					$lang = strtolower( $lm[1] );
				}
				$code  = html_entity_decode( (string) ( $m[2] ?? '' ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$code  = str_replace( array( "\r\n", "\r" ), "\n", $code );
				$code  = trim( $code, "\n" );
				$token = '@@WPDOCS_CODE_BLOCK_' . count( $code_blocks ) . '@@';
				$code_blocks[ $token ] = "\n\n```{$lang}\n{$code}\n```\n\n";
				return $token;
			},
			$html
		);

		// Inline code (protected from downstream strip_tags).
		$html = (string) preg_replace_callback(
			'#<code\b[^>]*>([\s\S]*?)</code>#i',
			static function ( array $m ) use ( &$code_blocks ): string {
				$text = html_entity_decode( strip_tags( (string) $m[1] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
				$text = trim( (string) preg_replace( '/\s+/u', ' ', $text ) );
				if ( '' === $text ) {
					return '';
				}
				$token                 = '@@WPDOCS_INLINE_CODE_' . count( $code_blocks ) . '@@';
				$code_blocks[ $token ] = '`' . $text . '`';
				return $token;
			},
			$html
		);

		// Headings h1-h6.
		$html = (string) preg_replace_callback(
			'#<h([1-6])\b[^>]*>([\s\S]*?)</h\1>#i',
			static function ( array $m ): string {
				$level = (int) $m[1];
				$text  = self::collapse_plain_text( (string) $m[2] );
				return '' !== $text ? "\n\n" . str_repeat( '#', $level ) . ' ' . $text . "\n\n" : '';
			},
			$html
		);

		// Horizontal rules.
		$html = (string) preg_replace( '#<hr\b[^>]*\/?>#i', "\n\n---\n\n", $html );

		// Images before links so linked images convert cleanly.
		$html = (string) preg_replace_callback(
			'#<img\b[^>]*>#i',
			static function ( array $m ): string {
				$tag = $m[0];
				$src = '';
				$alt = '';
				if ( preg_match( '/\bsrc=["\']([^"\']+)["\']/i', $tag, $sm ) ) {
					$src = trim( html_entity_decode( $sm[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				}
				if ( preg_match( '/\balt=["\']([^"\']*)["\']/i', $tag, $am ) ) {
					$alt = self::collapse_plain_text( $am[1] );
				}
				return '' !== $src ? "![{$alt}]({$src})" : $alt;
			},
			$html
		);

		// Links.
		$html = (string) preg_replace_callback(
			'#<a\b[^>]*href=["\']([^"\']+)["\'][^>]*>([\s\S]*?)</a>#i',
			static function ( array $m ): string {
				$href = trim( html_entity_decode( $m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				$text = trim( (string) preg_replace( '/\s+/u', ' ', strip_tags( (string) $m[2] ) ) );
				if ( '' === $text ) {
					return $href;
				}
				return '' !== $href ? "[{$text}]({$href})" : $text;
			},
			$html
		);

		// Strong / emphasis.
		$html = (string) preg_replace( '#<(?:strong|b)\b[^>]*>([\s\S]*?)</(?:strong|b)>#i', '**$1**', $html );
		$html = (string) preg_replace( '#<(?:em|i)\b[^>]*>([\s\S]*?)</(?:em|i)>#i', '*$1*', $html );

		// Blockquotes.
		$html = (string) preg_replace_callback(
			'#<blockquote\b[^>]*>([\s\S]*?)</blockquote>#i',
			static function ( array $m ): string {
				$body  = (string) preg_replace( '#</p>\s*<p\b[^>]*>#i', "\n", $m[1] );
				$body  = (string) preg_replace( '#<br\s*/?>#i', "\n", $body );
				$inner = trim( strip_tags( $body ) );
				if ( '' === $inner ) {
					return '';
				}
				$lines = preg_split( '/\r?\n/', $inner );
				$out   = array();
				foreach ( is_array( $lines ) ? $lines : array() as $line ) {
					$line = trim( $line );
					if ( '' !== $line ) {
						$out[] = '> ' . $line;
					}
				}
				return "\n\n" . implode( "\n", $out ) . "\n\n";
			},
			$html
		);

		// Ordered lists.
		$html = (string) preg_replace_callback(
			'#<ol\b[^>]*>([\s\S]*?)</ol>#i',
			static function ( array $m ): string {
				$idx = 0;
				$out = (string) preg_replace_callback(
					'#<li\b[^>]*>([\s\S]*?)</li>#i',
					static function ( array $li ) use ( &$idx ): string {
						++$idx;
						$text = trim( (string) preg_replace( '/\s+/u', ' ', strip_tags( (string) $li[1] ) ) );
						return '' !== $text ? "{$idx}. {$text}\n" : '';
					},
					$m[1]
				);
				return "\n\n" . trim( $out ) . "\n\n";
			},
			$html
		);

		// Unordered list items.
		$html = (string) preg_replace_callback(
			'#<li\b[^>]*>([\s\S]*?)</li>#i',
			static function ( array $m ): string {
				$text = trim( (string) preg_replace( '/\s+/u', ' ', strip_tags( (string) $m[1] ) ) );
				return '' !== $text ? "- {$text}\n" : '';
			},
			$html
		);

		// Line breaks & block boundaries.
		$html = (string) preg_replace( '#<br\s*/?>#i', "  \n", $html );
		$html = (string) preg_replace( '#</(?:p|div|section|article|ul|table|tr|figure|figcaption)>#i', "\n\n", $html );
		$html = (string) preg_replace( '#<(?:td|th)\b[^>]*>([\s\S]*?)</(?:td|th)>#i', '$1 | ', $html );

		// Strip remaining HTML tags and decode entities.
		$text = strip_tags( $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

		// Restore protected fenced code blocks.
		if ( ! empty( $code_blocks ) ) {
			$text = strtr( $text, $code_blocks );
		}

		return self::clean_markdown( $text );
	}

	/**
	 * Strip leading YAML frontmatter (`--- ... ---`) from a raw Markdown document.
	 *
	 * @param string $markdown Raw file contents.
	 */
	public static function strip_frontmatter( string $markdown ): string {
		$markdown = str_replace( array( "\r\n", "\r" ), "\n", $markdown );
		$markdown = ltrim( $markdown, "\xEF\xBB\xBF" );
		if ( preg_match( '/\A---\n[\s\S]*?\n---\n*/', $markdown ) ) {
			$markdown = (string) preg_replace( '/\A---\n[\s\S]*?\n---\n*/', '', $markdown, 1 );
		}
		return self::clean_markdown( $markdown );
	}

	/**
	 * Collect published WPDocs entries formatted for llms.txt.
	 *
	 * @param int $limit Max items to return.
	 * @return array<int,array{title:string,url:string,desc:string}>
	 */
	private static function collect_llms_items( int $limit ): array {
		$posts = self::collect_published_doc_posts( $limit );
		$items = array();

		foreach ( $posts as $post ) {
			if ( count( $items ) >= $limit ) {
				break;
			}
			if ( self::is_post_noai( $post ) ) {
				continue;
			}
			$title = function_exists( 'get_the_title' )
				? (string) get_the_title( $post )
				: (string) ( $post->post_title ?? '' );
			$url   = function_exists( 'get_permalink' )
				? (string) get_permalink( $post )
				: '';

			$excerpt = self::extract_post_summary( $post );
			$item    = self::format_llms_item( $title, $url, $excerpt );
			if ( null !== $item ) {
				$items[] = $item;
			}
		}

		return $items;
	}

	/**
	 * Collect unique published documentation posts across WPDocs sources.
	 *
	 * Never initiates blocking remote HTTP calls.
	 *
	 * @param int $limit Max posts to collect.
	 * @return array<int,object>
	 */
	private static function collect_published_doc_posts( int $limit ): array {
		$collected = array();

		$add_post = static function ( $post ) use ( &$collected, $limit ): void {
			if ( count( $collected ) >= $limit ) {
				return;
			}
			if ( ! is_object( $post ) || empty( $post->ID ) ) {
				return;
			}
			$id = (int) $post->ID;
			if ( $id < 1 || isset( $collected[ $id ] ) ) {
				return;
			}
			if ( 'publish' !== (string) ( $post->post_status ?? '' ) ) {
				return;
			}
			if ( self::is_post_noai( $post ) ) {
				return;
			}
			$collected[ $id ] = $post;
		};

		// 1. Active bindings from wpdocs_bindings table.
		if ( function_exists( 'get_post' ) && self::bindings_available() ) {
			try {
				$rows = BindRepo::list( min( 500, $limit * 2 ) );
				foreach ( is_array( $rows ) ? $rows : array() as $row ) {
					if ( count( $collected ) >= $limit ) {
						break;
					}
					if ( 'active' !== (string) ( $row->status ?? '' ) ) {
						continue;
					}
					$pid = (int) ( $row->wp_post_id ?? 0 );
					if ( $pid > 0 ) {
						$add_post( get_post( $pid ) );
					}
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		// 2. Cached CocoBook catalog links (file cache peek only — zero network).
		if ( count( $collected ) < $limit && function_exists( 'get_post' ) && class_exists( Client::class ) && self::settings_configured() ) {
			try {
				$cached = Client::peek( 'catalog_v1' );
				$by_id  = is_array( $cached ) && isset( $cached['d']['by_wp_id'] ) && is_array( $cached['d']['by_wp_id'] )
					? $cached['d']['by_wp_id']
					: array();
				foreach ( array_keys( $by_id ) as $wp_id ) {
					if ( count( $collected ) >= $limit ) {
						break;
					}
					$wp_id = (int) $wp_id;
					if ( $wp_id > 0 && ! isset( $collected[ $wp_id ] ) ) {
						$add_post( get_post( $wp_id ) );
					}
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		// 3. Dedicated doc post types (configured CPTs other than generic post/page).
		if ( count( $collected ) < $limit && function_exists( 'get_posts' ) ) {
			$dedicated = self::dedicated_doc_post_types();
			if ( ! empty( $dedicated ) ) {
				$cpt_posts = get_posts(
					array(
						'post_type'      => $dedicated,
						'post_status'    => 'publish',
						'posts_per_page' => $limit,
						'orderby'        => 'modified',
						'order'          => 'DESC',
						'no_found_rows'  => true,
					)
				);
				foreach ( is_array( $cpt_posts ) ? $cpt_posts : array() as $post ) {
					$add_post( $post );
				}
			}
		}

		// 4. Posts explicitly marked by WPDocs / Git it Write post meta.
		if ( count( $collected ) < $limit && function_exists( 'get_posts' ) ) {
			$meta_query = array( 'relation' => 'OR' );
			foreach ( self::DOC_MARKER_META_KEYS as $meta_key ) {
				$meta_query[] = array(
					'key'     => $meta_key,
					'value'   => '',
					'compare' => '!=',
				);
			}
			$marked_posts = get_posts(
				array(
					'post_type'      => self::queryable_post_types(),
					'post_status'    => 'publish',
					'posts_per_page' => $limit,
					'orderby'        => 'modified',
					'order'          => 'DESC',
					'no_found_rows'  => true,
					'meta_query'     => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
				)
			);
			foreach ( is_array( $marked_posts ) ? $marked_posts : array() as $post ) {
				$add_post( $post );
			}
		}

		return array_values( $collected );
	}

	/**
	 * Lightweight count of docs currently managed/exportable by WPDocs.
	 */
	private static function doc_count(): int {
		$count = 0;

		if ( self::bindings_available() ) {
			try {
				$count = max( $count, BindRepo::count( 'active' ) );
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		if ( function_exists( 'wp_count_posts' ) ) {
			$cpt_total = 0;
			foreach ( self::dedicated_doc_post_types() as $pt ) {
				if ( function_exists( 'post_type_exists' ) && ! post_type_exists( $pt ) ) {
					continue;
				}
				$counts = wp_count_posts( $pt );
				if ( is_object( $counts ) && isset( $counts->publish ) ) {
					$cpt_total += (int) $counts->publish;
				}
			}
			$count = max( $count, $cpt_total );
		}

		if ( 0 === $count && class_exists( Client::class ) && self::settings_configured() ) {
			try {
				$cached = Client::peek( 'catalog_v1' );
				if ( is_array( $cached ) && isset( $cached['d']['by_wp_id'] ) && is_array( $cached['d']['by_wp_id'] ) ) {
					$count = count( $cached['d']['by_wp_id'] );
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		if ( 0 === $count && function_exists( 'get_posts' ) ) {
			$count = count( self::collect_published_doc_posts( self::LLMS_MAX_ITEMS ) );
		}

		return $count;
	}

	/**
	 * Extract a concise plain-text summary for a doc post.
	 *
	 * @param object $post Post object.
	 */
	private static function extract_post_summary( object $post ): string {
		$excerpt = isset( $post->post_excerpt ) ? trim( (string) $post->post_excerpt ) : '';
		if ( '' !== $excerpt ) {
			return $excerpt;
		}

		$content = isset( $post->post_content ) ? trim( (string) $post->post_content ) : '';
		if ( '' !== $content ) {
			$content = (string) preg_replace( '/<!--\s*\/?wp:[^>]*-->/s', '', $content );
			return $content;
		}

		// Fallback to cached CocoBook page body if WP post_content is empty (zero network).
		$page_id = self::resolve_cocobook_page_id( $post );
		if ( $page_id > 0 && class_exists( Client::class ) ) {
			try {
				$cached = Client::peek( 'page_' . $page_id );
				if ( is_array( $cached ) && isset( $cached['d'] ) && is_array( $cached['d'] ) ) {
					$body = (string) ( $cached['d']['html'] ?? $cached['d']['markdown'] ?? '' );
					if ( '' !== trim( $body ) ) {
						return $body;
					}
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		if ( function_exists( 'get_the_excerpt' ) ) {
			return (string) get_the_excerpt( $post );
		}

		return '';
	}

	/**
	 * Read explicitly stored raw Markdown from post meta.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function read_raw_markdown_meta( int $post_id ): ?string {
		if ( ! function_exists( 'get_post_meta' ) ) {
			return null;
		}
		foreach ( self::RAW_MARKDOWN_META_KEYS as $key ) {
			$val = get_post_meta( $post_id, $key, true );
			if ( is_string( $val ) && '' !== trim( $val ) ) {
				return self::strip_frontmatter( $val );
			}
		}
		return null;
	}

	/**
	 * Read local Markdown file for Git-synced posts if safely inside git_base_directory.
	 *
	 * @param int $post_id Post ID.
	 */
	private static function read_local_git_markdown( int $post_id ): ?string {
		if ( ! function_exists( 'get_post_meta' ) || ! class_exists( Settings::class ) ) {
			return null;
		}
		$source = get_post_meta( $post_id, '_source_url', true );
		if ( ! is_string( $source ) || '' === trim( $source ) ) {
			return null;
		}
		$base_dir = (string) Settings::get( 'git_base_directory' );
		if ( '' === trim( $base_dir ) ) {
			return null;
		}
		$real_base = realpath( $base_dir );
		$real_file = realpath( $source );
		if ( false === $real_base || false === $real_file || ! is_file( $real_file ) || ! is_readable( $real_file ) ) {
			return null;
		}
		$base_prefix = rtrim( $real_base, DIRECTORY_SEPARATOR ) . DIRECTORY_SEPARATOR;
		if ( 0 !== strpos( $real_file, $base_prefix ) ) {
			return null;
		}
		if ( ! preg_match( '/\.md$/i', $real_file ) ) {
			return null;
		}
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$contents = file_get_contents( $real_file );
		if ( ! is_string( $contents ) || '' === trim( $contents ) ) {
			return null;
		}
		$md = self::strip_frontmatter( $contents );
		return '' !== $md ? $md : null;
	}

	/**
	 * Fetch Markdown from a linked CocoBook / BookStack page.
	 *
	 * @param int $page_id BookStack page ID.
	 */
	private static function read_cocobook_markdown( int $page_id ): ?string {
		if ( $page_id < 1 || ! class_exists( Catalog::class ) ) {
			return null;
		}
		try {
			$page = Catalog::page( $page_id );
		} catch ( \Throwable $e ) {
			return null;
		}
		if ( ! is_array( $page ) ) {
			return null;
		}
		if ( ! empty( $page['markdown'] ) && is_string( $page['markdown'] ) ) {
			$md = self::clean_markdown( $page['markdown'] );
			if ( '' !== $md ) {
				return $md;
			}
		}
		if ( ! empty( $page['html'] ) && is_string( $page['html'] ) ) {
			$md = self::html_to_markdown( $page['html'] );
			if ( '' !== $md ) {
				return $md;
			}
		}
		return null;
	}

	/**
	 * Resolve linked CocoBook page ID for a post (0 if not linked).
	 *
	 * @param object $post Post object.
	 */
	private static function resolve_cocobook_page_id( object $post ): int {
		$post_id = (int) ( $post->ID ?? 0 );
		if ( $post_id < 1 ) {
			return 0;
		}

		// 1. Live Frontend decision (handles catalog tags + bindings + rollout scope).
		if ( class_exists( Frontend::class ) && $post instanceof \WP_Post ) {
			try {
				$entry = Frontend::entry_for( $post );
				if ( is_array( $entry ) && ! empty( $entry['id'] ) ) {
					return (int) $entry['id'];
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		// 2. Active binding row directly (covers keep mode / direct lookups).
		if ( self::bindings_available() ) {
			try {
				$row = BindRepo::find_by_post( $post_id );
				if ( $row && 'active' === (string) ( $row->status ?? '' ) && ! empty( $row->docs_page_id ) ) {
					return (int) $row->docs_page_id;
				}
			} catch ( \Throwable $e ) {
				unset( $e );
			}
		}

		// 3. One-shot CocoBook import marker (`cocobook:{page_id}`).
		if ( function_exists( 'get_post_meta' ) ) {
			$imported = get_post_meta( $post_id, '_wpdocs_imported_from', true );
			if ( is_string( $imported ) && preg_match( '/^cocobook:(\d+)$/', trim( $imported ), $m ) ) {
				return (int) $m[1];
			}
		}

		return 0;
	}

	/**
	 * Whether a post is a WPDocs documentation post via post meta or dedicated doc CPT.
	 *
	 * @param object $post Post object.
	 */
	private static function is_marked_doc_post( object $post ): bool {
		$post_id   = (int) ( $post->ID ?? 0 );
		$post_type = (string) ( $post->post_type ?? '' );

		if ( '' !== $post_type && in_array( $post_type, self::dedicated_doc_post_types(), true ) ) {
			return true;
		}

		if ( $post_id > 0 && function_exists( 'get_post_meta' ) ) {
			foreach ( self::DOC_MARKER_META_KEYS as $key ) {
				$val = get_post_meta( $post_id, $key, true );
				if ( is_string( $val ) && '' !== trim( $val ) ) {
					return true;
				}
			}
			if ( Settings::CONTENT_GIT === self::current_content_mode() ) {
				$sha = get_post_meta( $post_id, 'sha', true );
				if ( is_string( $sha ) && '' !== trim( $sha ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check whether a post is excluded from AI indexing by WPMind GEO flags.
	 *
	 * @param object $post Post object.
	 */
	private static function is_post_noai( object $post ): bool {
		$post_id = (int) ( $post->ID ?? 0 );
		if ( $post_id > 0 && function_exists( 'get_post_meta' ) ) {
			if ( 'noai' === get_post_meta( $post_id, '_wpmind_ai_indexing', true ) ) {
				return true;
			}
			if ( '1' === (string) get_post_meta( $post_id, '_wpmind_ai_noindex', true ) ) {
				return true;
			}
			if ( '1' === (string) get_post_meta( $post_id, '_wpmind_ai_nollm', true ) ) {
				return true;
			}
		}

		if ( function_exists( 'get_option' ) ) {
			$excluded = get_option( 'wpmind_ai_excluded_post_types', array() );
			$pt       = (string) ( $post->post_type ?? '' );
			if ( '' !== $pt && is_array( $excluded ) && in_array( $pt, $excluded, true ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Configured WPDocs / Git post types excluding generic WordPress types (`post`, `page`).
	 *
	 * @return string[]
	 */
	private static function dedicated_doc_post_types(): array {
		$types = array();
		foreach ( self::configured_post_types() as $pt ) {
			if ( ! in_array( $pt, self::GENERIC_POST_TYPES, true ) ) {
				$types[ $pt ] = $pt;
			}
		}
		return array_values( $types );
	}

	/**
	 * All configured post types WPDocs may query for meta-marked docs.
	 *
	 * @return string[]
	 */
	private static function queryable_post_types(): array {
		$types = self::configured_post_types();
		if ( empty( $types ) ) {
			return array( 'post', 'page' );
		}
		return $types;
	}

	/**
	 * Post types referenced across WPDocs settings and Git repository configs.
	 *
	 * @return string[]
	 */
	private static function configured_post_types(): array {
		if ( ! class_exists( Settings::class ) ) {
			return array();
		}
		$types = array();
		foreach ( Settings::post_types() as $pt ) {
			if ( '' !== $pt ) {
				$types[ $pt ] = $pt;
			}
		}
		$git_default = (string) Settings::get( 'git_default_post_type' );
		if ( '' !== $git_default ) {
			$types[ $git_default ] = $git_default;
		}
		foreach ( Settings::git_repositories() as $repo ) {
			if ( is_array( $repo ) && ! empty( $repo['post_type'] ) && is_string( $repo['post_type'] ) ) {
				$pt           = $repo['post_type'];
				$types[ $pt ] = $pt;
			}
		}
		return array_values( $types );
	}

	/** Whether wpdocs_bindings queries can safely run. */
	private static function bindings_available(): bool {
		return isset( $GLOBALS['wpdb'] ) && is_object( $GLOBALS['wpdb'] ) && class_exists( BindRepo::class );
	}

	/** Safe wrapper for Settings::is_configured(). */
	private static function settings_configured(): bool {
		return class_exists( Settings::class ) && Settings::is_configured();
	}

	/** Safe wrapper for Settings::content_mode(). */
	private static function current_content_mode(): string {
		return class_exists( Settings::class ) ? Settings::content_mode() : Settings::CONTENT_COCOBOOK;
	}

	/** Safe wrapper for Menu::url(). */
	private static function settings_url(): string {
		if ( class_exists( Menu::class ) && function_exists( 'admin_url' ) ) {
			return Menu::url();
		}
		return '';
	}

	/** Collapse HTML / whitespace into a clean single-line string. */
	private static function collapse_plain_text( string $text ): string {
		$text = strip_tags( $text );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = (string) preg_replace( '/\s+/u', ' ', $text );
		return trim( $text );
	}

	/** Normalize line endings and excessive blank lines in Markdown. */
	private static function clean_markdown( string $markdown ): string {
		$markdown = str_replace( array( "\r\n", "\r" ), "\n", $markdown );
		$markdown = (string) preg_replace( "/\n{3,}/", "\n\n", $markdown );
		return trim( $markdown );
	}

	/** Multibyte-safe string length. */
	private static function str_len( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	/** Multibyte-safe substring. */
	private static function str_sub( string $text, int $start, int $length ): string {
		return function_exists( 'mb_substr' ) ? mb_substr( $text, $start, $length, 'UTF-8' ) : substr( $text, $start, $length );
	}
}
