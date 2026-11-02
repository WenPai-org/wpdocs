<?php
/**
 * Admin menus — wenpai-admin-ui 档 A.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Admin;

use Wpdocs\Bind\Repository as BindRepo;
use Wpdocs\Http\BookStackClient;
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
		add_filter( 'admin_title', array( self::class, 'help_admin_title' ), 10, 2 );
		add_filter( 'manage_posts_columns', array( self::class, 'post_column' ) );
		add_action( 'manage_posts_custom_column', array( self::class, 'post_column_content' ), 10, 2 );
	}

	/**
	 * null-parent 帮助页没有 submenu 标题；补进 document title。
	 *
	 * @param string $admin_title Full title (already includes site name).
	 * @param string $title       Page title segment (may be empty).
	 */
	public static function help_admin_title( $admin_title, $title ) {
		if ( ! isset( $_GET['page'] ) || 'wpdocs-help' !== $_GET['page'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return $admin_title;
		}
		if ( is_string( $title ) && '' !== $title ) {
			return $admin_title;
		}
		$help = __( '帮助', 'wpdocs' );
		return is_string( $admin_title ) ? ( $help . $admin_title ) : $help;
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
		// 空 parent：不进左侧菜单（帮助不占第四 Tab）；标题靠 help_admin_title。
		add_submenu_page( '', __( '帮助', 'wpdocs' ), __( '帮助', 'wpdocs' ), 'manage_options', 'wpdocs-help', array( self::class, 'render_help' ) );
	}

	public static function assets( string $hook ): void {
		if ( ! str_contains( $hook, 'wpdocs' ) ) {
			return;
		}
		if ( class_exists( 'Wenpai_Admin_Loader', false ) ) {
			\Wenpai_Admin_Loader::enqueue( array( 'full' => true ) );
		}
		$css = WPDOCS_DIR . 'assets/admin/admin.css';
		$js  = WPDOCS_DIR . 'assets/admin/admin.js';
		wp_enqueue_style(
			'wpdocs-admin',
			WPDOCS_URL . 'assets/admin/admin.css',
			array( 'wenpai-admin-ui' ),
			is_readable( $css ) ? (string) filemtime( $css ) : WPDOCS_VERSION
		);
		wp_enqueue_script(
			'wpdocs-admin',
			WPDOCS_URL . 'assets/admin/admin.js',
			array( 'wenpai-admin-ui' ),
			is_readable( $js ) ? (string) filemtime( $js ) : WPDOCS_VERSION,
			true
		);
		wp_localize_script(
			'wpdocs-admin',
			'wpdocsAdmin',
			array(
				'root'  => esc_url_raw( rest_url( 'wpdocs/v1' ) ),
				'nonce' => wp_create_nonce( 'wp_rest' ),
			)
		);
	}

	/** @return array<string,mixed> */
	private static function shell_args( string $current ): array {
		$icon = static function ( string $name ): string {
			return View::icon( $name );
		};
		$mark = '<svg viewBox="0 0 28 28" width="28" height="28" aria-hidden="true"><path fill="currentColor" d="M8 3.5h7.4L21.5 9.6V23a1.5 1.5 0 0 1-1.5 1.5H8A1.5 1.5 0 0 1 6.5 23V5A1.5 1.5 0 0 1 8 3.5zm7.2 1.3v5.1h5.1z"/></svg>';

		return array(
			'slug'               => 'wpdocs',
			'name'               => __( '文派文档', 'wpdocs' ),
			'mark_html'          => $mark,
			'home_url'           => admin_url( 'admin.php?page=wpdocs' ),
			'current'            => $current,
			'tabs'               => array(
				array(
					'id'        => 'status',
					'label'     => __( '状态', 'wpdocs' ),
					'url'       => admin_url( 'admin.php?page=wpdocs' ),
					'icon_html' => $icon( 'status' ),
				),
				array(
					'id'        => 'bindings',
					'label'     => __( '绑定', 'wpdocs' ),
					'url'       => admin_url( 'admin.php?page=wpdocs-bindings' ),
					'icon_html' => $icon( 'link' ),
				),
				array(
					'id'        => 'redirects',
					'label'     => __( '跳转', 'wpdocs' ),
					'url'       => admin_url( 'admin.php?page=wpdocs-redirects' ),
					'icon_html' => $icon( 'share' ),
				),
			),
			'help_url'           => admin_url( 'admin.php?page=wpdocs-help' ),
			'help_label'         => __( '帮助', 'wpdocs' ),
			'help_icon_html'     => $icon( 'help' ),
			'feedback_url'       => 'https://wpcy.com/support',
			'feedback_label'     => __( '反馈', 'wpdocs' ),
			'feedback_icon_html' => $icon( 'feedback' ),
			'version'            => 'wpdocs ' . WPDOCS_VERSION,
			'doc_url'            => 'https://wpcy.com/document',
			'doc_label'          => __( '文档', 'wpdocs' ),
		);
	}

	private static function open_shell( string $current ): void {
		$args = self::shell_args( $current );
		if ( class_exists( 'Wenpai_Admin_Shell', false ) ) {
			\Wenpai_Admin_Shell::open( $args );
			return;
		}
		echo '<div class="wrap"><h1>' . esc_html__( '文派文档', 'wpdocs' ) . '</h1>';
	}

	private static function close_shell( string $current = 'status' ): void {
		$args = self::shell_args( $current );
		if ( class_exists( 'Wenpai_Admin_Shell', false ) ) {
			\Wenpai_Admin_Shell::close( $args );
			return;
		}
		echo '</div>';
	}

	public static function handle_post(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['wpdocs_bind_row_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdocs_bind_row_nonce'] ) ), 'wpdocs_bind_row' ) ) {
			$row_id = (int) ( $_POST['wpdocs_bind_row_id'] ?? 0 );
			$action = sanitize_key( (string) ( $_POST['wpdocs_bind_row_action'] ?? '' ) );
			$ok     = false;
			if ( 'delete' === $action ) {
				$ok = BindRepo::delete( $row_id );
			} elseif ( 'disable' === $action ) {
				$ok = BindRepo::set_status( $row_id, 'disabled' );
			} elseif ( 'enable' === $action ) {
				$ok = BindRepo::set_status( $row_id, 'active' );
			}
			$q    = $ok ? 'updated=1' : 'error=1';
			$mode = isset( $_POST['wpdocs_mode'] ) && 'advanced' === sanitize_key( (string) wp_unslash( $_POST['wpdocs_mode'] ) ) ? '&mode=advanced' : '';
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-bindings&' . $q . $mode ) );
			exit;
		}

		if ( isset( $_POST['wpdocs_redir_row_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdocs_redir_row_nonce'] ) ), 'wpdocs_redir_row' ) ) {
			$row_id = (int) ( $_POST['wpdocs_redir_row_id'] ?? 0 );
			$action = sanitize_key( (string) ( $_POST['wpdocs_redir_row_action'] ?? '' ) );
			$ok     = false;
			if ( 'delete' === $action ) {
				$ok = RedirectRepo::delete( $row_id );
			} elseif ( in_array( $action, RedirectRepo::statuses(), true ) ) {
				$row = RedirectRepo::find_by_id( $row_id );
				if ( $row ) {
					$ok = (bool) RedirectRepo::upsert(
						array(
							'wp_path'  => (string) $row->wp_path,
							'page_id'  => (int) $row->page_id,
							'status'   => $action,
							'source'   => (string) ( $row->source ?? 'manual' ),
							'wp_title' => (string) ( $row->wp_title ?? '' ),
						)
					);
				}
			}
			$q    = $ok ? 'updated=1' : 'error=1';
			$mode = isset( $_POST['wpdocs_mode'] ) && 'advanced' === sanitize_key( (string) wp_unslash( $_POST['wpdocs_mode'] ) ) ? '&mode=advanced' : '';
			$filt = sanitize_key( (string) ( $_POST['wpdocs_redir_filter'] ?? '' ) );
			if ( $filt && in_array( $filt, RedirectRepo::statuses(), true ) ) {
				$q .= '&status=' . rawurlencode( $filt );
			}
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-redirects&' . $q . $mode ) );
			exit;
		}

		if ( isset( $_POST['wpdocs_bind_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdocs_bind_nonce'] ) ), 'wpdocs_bind' ) ) {
			$source = sanitize_key( (string) ( $_POST['source'] ?? 'manual' ) );
			$status = sanitize_key( (string) ( $_POST['status'] ?? 'active' ) );
			if ( ! in_array( $source, array( 'manual', 'slug', 'import' ), true ) ) {
				$source = 'manual';
			}
			if ( ! in_array( $status, array( 'active', 'disabled' ), true ) ) {
				$status = 'active';
			}
			$page_id = (int) ( $_POST['docs_page_id'] ?? 0 );
			$mode    = isset( $_POST['wpdocs_mode'] ) && 'advanced' === sanitize_key( (string) wp_unslash( $_POST['wpdocs_mode'] ) ) ? '&mode=advanced' : '';
			// When Token is configured and page id is set, refuse dirty rows (page missing / auth fail).
			if ( $page_id > 0 ) {
				$client = BookStackClient::from_config(
					array(
						'base_url' => HttpSource::base_url(),
					)
				);
				if ( $client ) {
					$probe = $client->page( $page_id );
					if ( empty( $probe['ok'] ) ) {
						wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-bindings&error=1' . $mode ) );
						exit;
					}
					$data = isset( $probe['data'] ) && is_array( $probe['data'] ) ? $probe['data'] : array();
					if ( empty( $_POST['docs_book_slug'] ) && ! empty( $data['book_slug'] ) ) {
						$_POST['docs_book_slug'] = (string) $data['book_slug'];
					}
					if ( empty( $_POST['docs_page_slug'] ) && ! empty( $data['slug'] ) ) {
						$_POST['docs_page_slug'] = (string) $data['slug'];
					}
				}
			}
			$id = BindRepo::upsert(
				array(
					'wp_post_id'     => (int) ( $_POST['wp_post_id'] ?? 0 ),
					'wp_post_type'   => sanitize_key( (string) ( $_POST['wp_post_type'] ?? 'post' ) ),
					'docs_page_id'   => $page_id,
					'docs_book_slug' => sanitize_title( (string) ( $_POST['docs_book_slug'] ?? '' ) ),
					'docs_page_slug' => sanitize_title( (string) ( $_POST['docs_page_slug'] ?? '' ) ),
					'external_id'    => sanitize_text_field( (string) ( $_POST['external_id'] ?? '' ) ),
					'source'         => $source,
					'status'         => $status,
				)
			);
			$q = $id ? 'updated=1' : 'error=1';
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-bindings&' . $q . $mode ) );
			exit;
		}
		if ( isset( $_POST['wpdocs_redir_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdocs_redir_nonce'] ) ), 'wpdocs_redir' ) ) {
			$page_id = (int) ( $_POST['docs_page_id'] ?? $_POST['page_id'] ?? 0 );
			$id      = RedirectRepo::upsert(
				array(
					'wp_path'  => (string) ( $_POST['wp_path'] ?? '' ),
					'page_id'  => $page_id,
					'status'   => sanitize_key( (string) ( $_POST['status'] ?? 'matched' ) ),
					'source'   => 'manual',
					'wp_title' => sanitize_text_field( (string) ( $_POST['wp_title'] ?? '' ) ),
				)
			);
			$q    = $id ? 'updated=1' : 'error=1';
			$mode = View::is_advanced() ? '&mode=advanced' : '';
			// Prefer posted mode so save keeps advanced fields visible.
			if ( isset( $_POST['wpdocs_mode'] ) && 'advanced' === sanitize_key( (string) wp_unslash( $_POST['wpdocs_mode'] ) ) ) {
				$mode = '&mode=advanced';
			}
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-redirects&' . $q . $mode ) );
			exit;
		}
		if ( isset( $_POST['wpdocs_settings_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdocs_settings_nonce'] ) ), 'wpdocs_settings' ) ) {
			$base = esc_url_raw( (string) ( $_POST['base_url'] ?? '' ) );
			$tree = isset( $_POST['tree_url'] ) ? esc_url_raw( (string) wp_unslash( $_POST['tree_url'] ) ) : '';
			$opt  = get_option( 'wpdocs_settings', array() );
			if ( ! is_array( $opt ) ) {
				$opt = array();
			}
			$opt['base_url'] = $base;
			$opt['tree_url'] = $tree;
			update_option( 'wpdocs_settings', $opt, false );
			HttpSource::bust_status_cache();
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs&updated=1&refresh=1' ) );
			exit;
		}
	}

	public static function render_status(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only refresh flag.
		$fresh = isset( $_GET['refresh'] ) && '1' === (string) wp_unslash( $_GET['refresh'] );
		$st    = HttpSource::status( $fresh );
		self::open_shell( 'status' );
		include WPDOCS_DIR . 'src/Admin/views/status.php';
		self::close_shell( 'status' );
	}

	public static function render_bindings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$items = BindRepo::list( 100 );
		self::open_shell( 'bindings' );
		include WPDOCS_DIR . 'src/Admin/views/bindings.php';
		self::close_shell( 'bindings' );
	}

	public static function render_redirects(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$filter = isset( $_GET['status'] ) ? sanitize_key( (string) wp_unslash( $_GET['status'] ) ) : '';
		if ( $filter && ! in_array( $filter, RedirectRepo::statuses(), true ) ) {
			$filter = '';
		}
		$items = RedirectRepo::list( $filter, 100 );
		self::open_shell( 'redirects' );
		include WPDOCS_DIR . 'src/Admin/views/redirects.php';
		self::close_shell( 'redirects' );
	}

	public static function render_help(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		self::open_shell( 'help' );
		include WPDOCS_DIR . 'src/Admin/views/help.php';
		self::close_shell( 'help' );
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
			echo '—';
			return;
		}
		echo esc_html( $row->docs_book_slug . '/' . $row->docs_page_slug );
	}
}
