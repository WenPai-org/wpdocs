<?php
/**
 * Embeds: [cocobook_comments] shortcode and the wpdocs/comments block.
 *
 * Both mount a cyberforums discussion bound to a CocoBook page
 * (`cocobook:page:{id}`), on any post or page, independent of body-swap mode.
 *
 * @package Wpdocs
 */

namespace Wpdocs;

use Wpdocs\Redirect\Path;

defined( 'ABSPATH' ) || exit;

final class Embed {

	const SHORTCODE = 'cocobook_comments';
	const BLOCK     = 'cocobook-docs/comments';
	const HANDLE    = 'wpdocs-comments-block';

	public static function register(): void {
		add_shortcode( self::SHORTCODE, array( self::class, 'shortcode' ) );
		add_shortcode( 'wpdocs_comments', array( self::class, 'shortcode' ) );
		add_action( 'init', array( self::class, 'register_block' ) );
	}

	/** Dynamic block with a no-build editor script. */
	public static function register_block(): void {
		if ( ! function_exists( 'register_block_type' ) ) {
			return;
		}
		wp_register_script(
			self::HANDLE,
			plugins_url( 'assets/comments-block.js', WPDOCS_FILE ),
			array( 'wp-blocks', 'wp-element', 'wp-components', 'wp-block-editor' ),
			WPDOCS_VERSION,
			true
		);
		register_block_type(
			self::BLOCK,
			array(
				'api_version'     => 2,
				'editor_script'   => self::HANDLE,
				'attributes'      => array(
					'page' => array(
						'type'    => 'string',
						'default' => '',
					),
				),
				'supports'        => array(
					'html'     => false,
					'multiple' => false,
				),
				'render_callback' => array( self::class, 'render_block' ),
			)
		);
	}

	/**
	 * [cocobook_comments page="123|https://docs/.../page/slug" title="" url=""]
	 *
	 * @param array<string,string>|string $atts Attributes.
	 */
	public static function shortcode( $atts ): string {
		$atts = shortcode_atts(
			array(
				'page'  => '',
				'title' => '',
				'url'   => '',
			),
			is_array( $atts ) ? $atts : array(),
			self::SHORTCODE
		);
		return self::render( (string) $atts['page'], (string) $atts['title'], (string) $atts['url'] );
	}

	/**
	 * @param array<string,mixed> $attrs Block attributes.
	 */
	public static function render_block( $attrs ): string {
		return self::render( (string) ( $attrs['page'] ?? '' ) );
	}

	/**
	 * Resolve the page and print the mount.
	 *
	 * @param string $page  Page ID or CocoBook URL; empty = this post's page.
	 * @param string $title Title for notifications; default = post title.
	 * @param string $url   Discussion URL; default = post permalink.
	 */
	public static function render( string $page, string $title = '', string $url = '' ): string {
		if ( is_admin() || is_feed() || ! Settings::get( 'comments' ) ) {
			return '';
		}
		$id = self::resolve_page( $page );
		if ( $id <= 0 ) {
			return current_user_can( 'edit_posts' )
				? '<p class="wpdocs-notice">' . esc_html__( 'CocoBook 评论：未找到要绑定的页面，请填写页面 ID 或链接（仅编辑可见）。', 'wpdocs' ) . '</p>'
				: '';
		}
		$post  = get_post();
		$title = '' !== $title ? $title : ( $post ? wp_strip_all_tags( get_the_title( $post ) ) : '' );
		$url   = '' !== $url ? $url : ( $post ? (string) get_permalink( $post ) : '' );
		return Frontend::mount_for_page( $id, $title, $url );
	}

	/**
	 * @param string $page Page ID or URL; empty = current post's catalog entry.
	 */
	public static function resolve_page( string $page ): int {
		$page = trim( $page );
		if ( '' !== $page ) {
			return (int) Path::page_id_from( $page );
		}
		$post = get_post();
		if ( ! $post ) {
			return 0;
		}
		$entry = Frontend::entry_for( $post );
		if ( ! $entry ) {
			$entry = Catalog::for_post( $post );
		}
		return is_array( $entry ) ? (int) ( $entry['id'] ?? 0 ) : 0;
	}
}
