<?php
/**
 * One-shot: write CocoBook page HTML into bound WP post_content (保留→导入).
 *
 * @package Wpdocs
 */

namespace Wpdocs\Import;

use Wpdocs\Bind\Repository as BindRepo;
use Wpdocs\Catalog;
use Wpdocs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Imports live CocoBook HTML into WordPress posts so content is owned by WP.
 */
final class CocoBook {

	/**
	 * Import all active bindings (or a single post).
	 *
	 * @param int $only_post_id 0 = all active bindings.
	 * @return array{ok:int,fail:int,skipped:int,errors:string[]}
	 */
	public static function run( int $only_post_id = 0 ): array {
		$stats = array(
			'ok'      => 0,
			'fail'    => 0,
			'skipped' => 0,
			'errors'  => array(),
		);

		if ( ! Settings::is_configured() ) {
			$stats['errors'][] = __( '文档站未配置。', 'wpdocs' );
			return $stats;
		}

		// BindRepo::list() caps at 100 per call; page through so large binding
		// sets are not silently truncated.
		$rows   = array();
		$offset = 0;
		do {
			$batch   = BindRepo::list( 100, $offset );
			$rows    = array_merge( $rows, $batch );
			$offset += 100;
		} while ( count( $batch ) === 100 );
		foreach ( $rows as $row ) {
			$post_id = (int) ( $row->wp_post_id ?? 0 );
			$page_id = (int) ( $row->docs_page_id ?? 0 );
			$status  = (string) ( $row->status ?? 'active' );
			if ( $only_post_id > 0 && $post_id !== $only_post_id ) {
				continue;
			}
			if ( 'active' !== $status || $post_id < 1 || $page_id < 1 ) {
				++$stats['skipped'];
				continue;
			}
			$res = self::import_one( $post_id, $page_id );
			if ( is_wp_error( $res ) ) {
				++$stats['fail'];
				$stats['errors'][] = sprintf( '#%d: %s', $post_id, $res->get_error_message() );
			} else {
				++$stats['ok'];
			}
		}

		return $stats;
	}

	/**
	 * @return true|\WP_Error
	 */
	public static function import_one( int $post_id, int $page_id ) {
		$post = get_post( $post_id );
		if ( ! $post instanceof \WP_Post ) {
			return new \WP_Error( 'wpdocs_import_no_post', __( '文章不存在。', 'wpdocs' ) );
		}

		$page = Catalog::page( $page_id );
		if ( is_wp_error( $page ) ) {
			return $page;
		}
		$html = '';
		if ( is_array( $page ) ) {
			$html = (string) ( $page['html'] ?? $page['body'] ?? $page['content'] ?? '' );
		}
		if ( '' === trim( $html ) ) {
			return new \WP_Error( 'wpdocs_import_empty', __( '文档页正文为空。', 'wpdocs' ) );
		}

		$update = array(
			'ID'           => $post_id,
			'post_content' => $html,
		);
		$title = is_array( $page ) ? (string) ( $page['name'] ?? $page['title'] ?? '' ) : '';
		if ( '' !== $title ) {
			$update['post_title'] = $title;
		}

		$r = wp_update_post( $update, true );
		if ( is_wp_error( $r ) ) {
			return $r;
		}

		update_post_meta( $post_id, '_wpdocs_imported_from', 'cocobook:' . $page_id );
		update_post_meta( $post_id, '_wpdocs_imported_at', time() );

		return true;
	}
}
