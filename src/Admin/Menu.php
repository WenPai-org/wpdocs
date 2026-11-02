<?php
/**
 * Admin menus (wenpai-admin-ui shaped).
 *
 * @package Wpdocs
 */

namespace Wpdocs\Admin;

use Wpdocs\Bind\Repository as BindRepo;
use Wpdocs\Publish\HttpSource;
use Wpdocs\Redirect\Repository as RedirectRepo;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Menu {

	public static function register(): void {
		add_action( 'admin_menu', array( self::class, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'assets' ) );
		add_action( 'admin_init', array( self::class, 'handle_post' ) );
		add_filter( 'manage_posts_columns', array( self::class, 'post_column' ) );
		add_action( 'manage_posts_custom_column', array( self::class, 'post_column_content' ), 10, 2 );
	}

	public static function menu(): void {
		add_menu_page(
			__( '文派文档', 'wpdocs' ),
			__( '文派文档', 'wpdocs' ),
			'manage_options',
			'wpdocs',
			array( self::class, 'render_status' ),
			'dashicons-book-alt',
			58
		);
		add_submenu_page( 'wpdocs', __( '状态', 'wpdocs' ), __( '状态', 'wpdocs' ), 'manage_options', 'wpdocs', array( self::class, 'render_status' ) );
		add_submenu_page( 'wpdocs', __( '绑定', 'wpdocs' ), __( '绑定', 'wpdocs' ), 'manage_options', 'wpdocs-bindings', array( self::class, 'render_bindings' ) );
		add_submenu_page( 'wpdocs', __( '跳转', 'wpdocs' ), __( '跳转', 'wpdocs' ), 'manage_options', 'wpdocs-redirects', array( self::class, 'render_redirects' ) );
	}

	public static function assets( string $hook ): void {
		if ( ! str_contains( $hook, 'wpdocs' ) ) {
			return;
		}
		wp_enqueue_style( 'wpdocs-admin', WPDOCS_URL . 'assets/admin/admin.css', array(), WPDOCS_VERSION );
		wp_enqueue_script( 'wpdocs-admin', WPDOCS_URL . 'assets/admin/admin.js', array(), WPDOCS_VERSION, true );
	}

	public static function handle_post(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( isset( $_POST['wpdocs_bind_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdocs_bind_nonce'] ) ), 'wpdocs_bind' ) ) {
			BindRepo::upsert(
				array(
					'wp_post_id'     => (int) ( $_POST['wp_post_id'] ?? 0 ),
					'wp_post_type'   => sanitize_key( (string) ( $_POST['wp_post_type'] ?? 'post' ) ),
					'docs_page_id'   => (int) ( $_POST['docs_page_id'] ?? 0 ),
					'docs_book_slug' => sanitize_title( (string) ( $_POST['docs_book_slug'] ?? '' ) ),
					'docs_page_slug' => sanitize_title( (string) ( $_POST['docs_page_slug'] ?? '' ) ),
					'external_id'    => sanitize_text_field( (string) ( $_POST['external_id'] ?? '' ) ),
					'source'         => 'manual',
					'status'         => 'active',
				)
			);
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-bindings&updated=1' ) );
			exit;
		}
		if ( isset( $_POST['wpdocs_redirect_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdocs_redirect_nonce'] ) ), 'wpdocs_redirect' ) ) {
			RedirectRepo::upsert(
				array(
					'wp_path'    => (string) ( $_POST['wp_path'] ?? '' ),
					'page_id'    => (int) ( $_POST['page_id'] ?? 0 ),
					'status'     => sanitize_key( (string) ( $_POST['status'] ?? 'matched' ) ),
					'source'     => 'manual',
					'wp_title'   => sanitize_text_field( (string) ( $_POST['wp_title'] ?? '' ) ),
				)
			);
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-redirects&updated=1' ) );
			exit;
		}
	}

	public static function render_status(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$st = HttpSource::status();
		include WPDOCS_DIR . 'src/Admin/views/status.php';
	}

	public static function render_bindings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$items = BindRepo::list( 100 );
		include WPDOCS_DIR . 'src/Admin/views/bindings.php';
	}

	public static function render_redirects(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$items = RedirectRepo::list( '', 100 );
		include WPDOCS_DIR . 'src/Admin/views/redirects.php';
	}

	/** @param array<string,string> $cols */
	public static function post_column( array $cols ): array {
		$cols['wpdocs_bind'] = __( '文档绑定', 'wpdocs' );
		return $cols;
	}

	public static function post_column_content( string $col, int $post_id ): void {
		if ( 'wpdocs_bind' !== $col ) {
			return;
		}
		$row = BindRepo::find_by_post( $post_id );
		if ( ! $row ) {
			echo '<span class="wpdocs-muted">—</span>';
			return;
		}
		echo esc_html( $row->docs_book_slug . '/' . $row->docs_page_slug );
	}
}
