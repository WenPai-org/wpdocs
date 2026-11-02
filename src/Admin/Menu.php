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
use Wpdocs\Settings;
use Wpdocs\Client;
use Wpdocs\Catalog;
use Wpdocs\Core;
use Wpdocs\Compat\Legacy;
use Wpdocs\Git\Engine as GitEngine;
use Wpdocs\Import\CocoBook as CocoBookImport;

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
		add_submenu_page( 'wpdocs', __( '概览', 'wpdocs' ), __( '概览', 'wpdocs' ), 'manage_options', 'wpdocs', array( self::class, 'render_status' ) );
		add_submenu_page( 'wpdocs', __( '设置', 'wpdocs' ), __( '设置', 'wpdocs' ), 'manage_options', 'wpdocs-settings', array( self::class, 'render_settings' ) );
		add_submenu_page( 'wpdocs', __( '工具', 'wpdocs' ), __( '工具', 'wpdocs' ), 'manage_options', 'wpdocs-tools', array( self::class, 'render_tools' ) );
		// Deep links keep working; not listed under parent (档 A：横 Tab 只有概览/设置/工具).
		add_submenu_page( '', __( '绑定', 'wpdocs' ), __( '绑定', 'wpdocs' ), 'manage_options', 'wpdocs-bindings', array( self::class, 'render_bindings' ) );
		add_submenu_page( '', __( '跳转', 'wpdocs' ), __( '跳转', 'wpdocs' ), 'manage_options', 'wpdocs-redirects', array( self::class, 'render_redirects' ) );
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
					'id'        => 'overview',
					'label'     => __( '概览', 'wpdocs' ),
					'url'       => admin_url( 'admin.php?page=wpdocs' ),
					'icon_html' => $icon( 'status' ),
				),
				array(
					'id'        => 'settings',
					'label'     => __( '设置', 'wpdocs' ),
					'url'       => admin_url( 'admin.php?page=wpdocs-settings' ),
					'icon_html' => $icon( 'settings' ),
				),
				array(
					'id'        => 'tools',
					'label'     => __( '工具', 'wpdocs' ),
					'url'       => admin_url( 'admin.php?page=wpdocs-tools' ),
					'icon_html' => $icon( 'tools' ),
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

		// Full settings (connection / books / rollout / comments).
		if ( isset( $_POST['wpdocs_settings_full_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdocs_settings_full_nonce'] ) ), 'wpdocs_settings_full' ) ) {
			$input = isset( $_POST['wpdocs'] ) && is_array( $_POST['wpdocs'] ) ? wp_unslash( $_POST['wpdocs'] ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			Settings::save( is_array( $input ) ? $input : array() );
			\Wpdocs\Publish\HttpSource::bust_status_cache();
			$mode = isset( $_POST['wpdocs_mode'] ) && 'advanced' === sanitize_key( (string) wp_unslash( $_POST['wpdocs_mode'] ) ) ? '&mode=advanced' : '';
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-settings&updated=1' . $mode ) );
			exit;
		}
		if ( isset( $_POST['wpdocs_test_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdocs_test_nonce'] ) ), 'wpdocs_test' ) ) {
			Client::$force = true;
			$res = Client::get( 'system' );
			$ok  = ! is_wp_error( $res );
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-tools&section=sync&msg=' . ( $ok ? 'test_ok' : 'test_error' ) ) );
			exit;
		}
		if ( isset( $_POST['wpdocs_sync_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdocs_sync_nonce'] ) ), 'wpdocs_sync' ) ) {
			$cat = Core::sync_now();
			$ok  = ! is_wp_error( $cat );
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-tools&section=sync&msg=' . ( $ok ? 'synced' : 'sync_error' ) ) );
			exit;
		}
		if ( isset( $_POST['wpdocs_import_cb_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdocs_import_cb_nonce'] ) ), 'wpdocs_import_cb' ) ) {
			$stats = CocoBookImport::run();
			$ok    = $stats['fail'] < 1 && $stats['ok'] > 0;
			Legacy::update_option(
				'wpdocs_log',
				array_merge(
					(array) Legacy::get_option( 'wpdocs_log', array() ),
					array(
						array(
							't' => time(),
							'l' => 'import',
							'm' => sprintf( 'CocoBook import ok=%d fail=%d skip=%d', $stats['ok'], $stats['fail'], $stats['skipped'] ),
						),
					)
				)
			);
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-tools&section=sync&msg=' . ( $ok ? 'imported' : ( $stats['ok'] > 0 ? 'imported_partial' : 'import_error' ) ) ) );
			exit;
		}
		if ( isset( $_POST['wpdocs_git_pull_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdocs_git_pull_nonce'] ) ), 'wpdocs_git_pull' ) ) {
			$force   = ! empty( $_POST['wpdocs_git_force'] );
			$repo_id = isset( $_POST['wpdocs_git_repo_id'] ) ? (int) $_POST['wpdocs_git_repo_id'] : 0;
			if ( $repo_id > 0 ) {
				$res = GitEngine::pull_repo( $repo_id, $force );
				$ok  = ! is_wp_error( $res );
			} else {
				$all = GitEngine::pull_all( $force );
				$ok  = true;
				foreach ( $all as $one ) {
					if ( is_wp_error( $one ) ) {
						$ok = false;
						break;
					}
				}
				if ( empty( $all ) ) {
					$ok = false;
				}
			}
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-tools&section=sync&msg=' . ( $ok ? 'git_ok' : 'git_error' ) ) );
			exit;
		}
		if ( isset( $_POST['wpdocs_git_sync_local_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wpdocs_git_sync_local_nonce'] ) ), 'wpdocs_git_sync_local' ) ) {
			$res = GitEngine::sync_local( ! empty( $_POST['wpdocs_git_force'] ) );
			$ok  = ! is_wp_error( $res );
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-tools&section=sync&msg=' . ( $ok ? 'git_local' : 'git_error' ) ) );
			exit;
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
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-tools&section=bindings&' . $q . $mode ) );
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
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-tools&section=redirects&' . $q . $mode ) );
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
						wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-tools&section=bindings&error=1' . $mode ) );
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
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-tools&section=bindings&' . $q . $mode ) );
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
			wp_safe_redirect( admin_url( 'admin.php?page=wpdocs-tools&section=redirects&' . $q . $mode ) );
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
		self::open_shell( 'overview' );
		include WPDOCS_DIR . 'src/Admin/views/status.php';
		self::close_shell( 'overview' );
	}





	/** @return string bindings|redirects|sync */
	private static function tools_section(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$s = isset( $_GET['section'] ) ? sanitize_key( (string) wp_unslash( $_GET['section'] ) ) : '';
		// Legacy ?section=git → sync (Git actions live on sync now).
		if ( 'git' === $s ) {
			return 'sync';
		}
		if ( in_array( $s, array( 'bindings', 'redirects', 'sync' ), true ) ) {
			return $s;
		}
		return 'bindings';
	}

	public static function render_tools(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		self::render_tools_section( self::tools_section() );
	}

	public static function render_bindings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		self::render_tools_section( 'bindings' );
	}

	public static function render_redirects(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		self::render_tools_section( 'redirects' );
	}

	private static function render_tools_section( string $section ): void {
		$items = null;
		$filter = '';
		if ( 'bindings' === $section ) {
			$items = BindRepo::list();
		} elseif ( 'redirects' === $section ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$filter = isset( $_GET['filter'] ) ? sanitize_key( (string) wp_unslash( $_GET['filter'] ) ) : '';
			$items  = RedirectRepo::list( $filter );
		} elseif ( 'sync' === $section ) {
			$s     = Settings::raw();
			$books = Settings::is_configured() ? Client::get( 'books', array( 'count' => 500, 'sort' => 'name' ) ) : null;
			$cat   = Settings::is_configured() ? Catalog::get() : null;
			$log   = Legacy::get_option( 'wpdocs_log', array() );
			$log   = is_array( $log ) ? array_reverse( $log ) : array();
		}

		ob_start();
		View::subnav(
			$section,
			array(
				array(
					'id'    => 'bindings',
					'label' => __( '绑定', 'wpdocs' ),
					'url'   => admin_url( 'admin.php?page=wpdocs-tools&section=bindings' ),
				),
				array(
					'id'    => 'redirects',
					'label' => __( '跳转', 'wpdocs' ),
					'url'   => admin_url( 'admin.php?page=wpdocs-tools&section=redirects' ),
				),
				array(
					'id'    => 'sync',
					'label' => __( '同步', 'wpdocs' ),
					'url'   => admin_url( 'admin.php?page=wpdocs-tools&section=sync' ),
				),
			)
		);
		$sub = ob_get_clean();

		$titles = array(
			'bindings'  => array( __( '绑定', 'wpdocs' ), __( '文章对到文档页。', 'wpdocs' ) ),
			'redirects' => array( __( '跳转', 'wpdocs' ), __( '旧路径 301 / 410。', 'wpdocs' ) ),
			'sync'      => array( __( '同步', 'wpdocs' ), __( '按当前模式更新索引、导入或拉取 Git。', 'wpdocs' ) ),
		);
		$title = $titles[ $section ][0] ?? __( '工具', 'wpdocs' );
		$lede  = $titles[ $section ][1] ?? '';

		self::open_shell( 'tools' );
		View::page_head( $title, $lede, $sub );
		View::saved_notice();
		View::error_notice();

		$wpdocs_embed = true; // views skip their own page_head.
		if ( 'bindings' === $section ) {
			include WPDOCS_DIR . 'src/Admin/views/bindings.php';
		} elseif ( 'redirects' === $section ) {
			include WPDOCS_DIR . 'src/Admin/views/redirects.php';
		} else {
			include WPDOCS_DIR . 'src/Admin/views/sync.php';
		}
		self::close_shell( 'tools' );
	}

	public static function render_settings(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		$s     = Settings::raw();
		$books = Settings::is_configured() ? Client::get( 'books', array( 'count' => 500, 'sort' => 'name' ) ) : null;
		self::open_shell( 'settings' );
		include WPDOCS_DIR . 'src/Admin/views/settings.php';
		self::close_shell( 'settings' );
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
