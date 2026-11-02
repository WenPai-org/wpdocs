<?php
/**
 * Git import actions (pull / sync) for wpdocs tools.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Git;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Engine {

	/**
	 * Pull one remote repo config by id.
	 *
	 * @param int  $repo_id Repo index.
	 * @param bool $force   Ignore SHA.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function pull_repo( int $repo_id, bool $force = false ) {
		if ( ! Bootstrap::load() ) {
			return new \WP_Error( 'wpdocs_git_missing', __( 'Git 导入核心未加载。', 'wpdocs' ) );
		}
		if ( ! function_exists( 'media_handle_sideload' ) ) {
			require_once ABSPATH . 'wp-admin/includes/media.php';
			require_once ABSPATH . 'wp-admin/includes/file.php';
			require_once ABSPATH . 'wp-admin/includes/image.php';
		}
		if ( $force && ! defined( 'GIW_PUBLISH_FORCE' ) ) {
			define( 'GIW_PUBLISH_FORCE', true );
		}
		$result = \GIW_Publish_Handler::publish_by_id( $repo_id );
		if ( false === $result ) {
			return new \WP_Error( 'wpdocs_git_pull_failed', __( '仓库拉取失败，请检查 Forgejo 地址、Token 与仓库配置。', 'wpdocs' ) );
		}
		return is_array( $result ) ? $result : array( 'ok' => true );
	}

	/**
	 * Sync all local-mode repos (base_directory).
	 *
	 * @param bool $force Ignore SHA.
	 * @return array<string,mixed>|\WP_Error
	 */
	public static function sync_local( bool $force = false ) {
		if ( ! Bootstrap::load() ) {
			return new \WP_Error( 'wpdocs_git_missing', __( 'Git 导入核心未加载。', 'wpdocs' ) );
		}
		if ( ! \Git_It_Write::is_local_mode() ) {
			return new \WP_Error( 'wpdocs_git_not_local', __( '未配置本地目录，无法本地同步。', 'wpdocs' ) );
		}
		if ( ! class_exists( 'GIW_Sync', false ) ) {
			return new \WP_Error( 'wpdocs_git_no_sync', __( '本地同步模块未加载。', 'wpdocs' ) );
		}
		return \GIW_Sync::sync_all( $force );
	}

	/**
	 * Pull every configured remote repository.
	 *
	 * @param bool $force Ignore SHA.
	 * @return array<int,array<string,mixed>|\WP_Error>
	 */
	public static function pull_all( bool $force = false ): array {
		if ( ! Bootstrap::load() ) {
			return array( new \WP_Error( 'wpdocs_git_missing', __( 'Git 导入核心未加载。', 'wpdocs' ) ) );
		}
		$repos   = \Git_It_Write::all_repositories();
		$results = array();
		foreach ( $repos as $id => $cfg ) {
			if ( (int) $id < 1 && empty( $cfg['username'] ) && empty( $cfg['repository'] ) ) {
				continue;
			}
			if ( empty( $cfg['username'] ) || empty( $cfg['repository'] ) ) {
				continue;
			}
			$results[ (int) $id ] = self::pull_repo( (int) $id, $force );
		}
		return $results;
	}

	/** REST webhook URL for Forgejo. */
	public static function webhook_url(): string {
		return rest_url( 'giw/v1/publish' );
	}
}
