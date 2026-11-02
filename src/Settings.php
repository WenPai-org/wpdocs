<?php
/**
 * Settings storage with wp-config.php constant overrides.
 *
 * @package Wpdocs
 */

namespace Wpdocs;

use Wpdocs\Compat\Legacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Read/write access to the plugin option `wpdocs_settings`.
 *
 * Content modes (上线方式):
 *  - keep      保留现有文章正文
 *  - cocobook  从 CocoBook / BookStack 读取（运行时换正文；可试点/全部）
 *  - git       从 Git（Forgejo/本地）导入 Markdown 写入文章（整合 Git it Write）
 *
 * Legacy `mode` off|pilot|all is kept as CocoBook rollout scope.
 *
 * @since 0.1.0
 */
final class Settings {

	public const OPTION = 'wpdocs_settings';

	/** Content source modes. */
	public const CONTENT_KEEP     = 'keep';
	public const CONTENT_COCOBOOK = 'cocobook';
	public const CONTENT_GIT      = 'git';

	/** CocoBook rollout (when content_mode = cocobook). */
	public const MODE_OFF   = 'off';
	public const MODE_PILOT = 'pilot';
	public const MODE_ALL   = 'all';

	/**
	 * Defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults(): array {
		return array(
			'base_url'       => 'https://docs.cocobook.com',
			'token_id'       => '',
			'token_secret'   => '',
			'site_tag'       => '',
			'books'          => array(),
			'bff_url'        => 'https://comments.cyberforums.com',
			'content_mode'   => self::CONTENT_COCOBOOK,
			'mode'           => self::MODE_PILOT,
			'pilot_post_ids' => array(),
			'post_types'     => array( 'post' ),
			'new_page_prefix'=> '/document/',
			'page_ttl'       => 0, // 0 = never expire; refreshed only when CocoBook changes.
			'tree_url'       => '',
			'comments'       => true,
			'refresh_secret' => '',
			'redirects'      => false, // 301 legacy WP paths to CocoBook (see Redirects).
			'comment_min_chars' => 30, // Legacy comments shorter than this are not migrated.
			// Git import (ex-Git it Write).
			'git_forgejo_url'          => '',
			'git_forgejo_token'        => '',
			'git_webhook_secret'       => '',
			'git_base_directory'       => '',
			'git_default_post_type'    => 'post',
			'git_default_post_author'  => 1,
			'git_sync_interval'        => 'hourly',
			'git_repositories'         => array(),
		);
	}

	/**
	 * All settings merged with defaults (constants not applied).
	 *
	 * @return array<string,mixed>
	 */
	public static function raw(): array {
		$saved = Legacy::get_option( self::OPTION, array() );
		$all   = array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
		return self::migrate_content_mode( $all );
	}

	/**
	 * Derive content_mode from legacy mode=off when unset.
	 *
	 * @param array<string,mixed> $all Settings.
	 * @return array<string,mixed>
	 */
	private static function migrate_content_mode( array $all ): array {
		if ( empty( $all['content_mode'] ) || ! is_string( $all['content_mode'] ) ) {
			$m = (string) ( $all['mode'] ?? self::MODE_PILOT );
			$all['content_mode'] = self::MODE_OFF === $m ? self::CONTENT_KEEP : self::CONTENT_COCOBOOK;
		}
		if ( ! in_array( $all['content_mode'], array( self::CONTENT_KEEP, self::CONTENT_COCOBOOK, self::CONTENT_GIT ), true ) ) {
			$all['content_mode'] = self::CONTENT_COCOBOOK;
		}
		return $all;
	}

	/**
	 * Get one setting; wp-config constants win for connection fields.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( string $key ) {
		$const = self::constant_for( $key );
		if ( null !== $const && defined( $const ) && '' !== (string) constant( $const ) ) {
			return (string) constant( $const );
		}
		$all = self::raw();
		return $all[ $key ] ?? null;
	}

	/**
	 * Whether a field is locked by a wp-config constant.
	 *
	 * @param string $key Setting key.
	 */
	public static function is_locked( string $key ): bool {
		$const = self::constant_for( $key );
		return null !== $const && defined( $const ) && '' !== (string) constant( $const );
	}

	/**
	 * Constant name that overrides a key.
	 *
	 * @param string $key Setting key.
	 */
	private static function constant_for( string $key ): ?string {
		$map = array(
			'base_url'       => 'WPDOCS_BASE_URL',
			'token_id'       => 'WPDOCS_TOKEN_ID',
			'token_secret'   => 'WPDOCS_TOKEN_SECRET',
			'refresh_secret' => 'WPDOCS_REFRESH_SECRET',
			'git_forgejo_url'   => 'WPDOCS_GIT_FORGEJO_URL',
			'git_forgejo_token'=> 'WPDOCS_GIT_FORGEJO_TOKEN',
			'git_webhook_secret' => 'WPDOCS_GIT_WEBHOOK_SECRET',
		);
		return $map[ $key ] ?? null;
	}

	/** BookStack base URL without trailing slash. */
	public static function base_url(): string {
		return untrailingslashit( esc_url_raw( (string) self::get( 'base_url' ) ) );
	}

	/** Comments BFF URL without trailing slash. */
	public static function bff_url(): string {
		return untrailingslashit( esc_url_raw( (string) self::get( 'bff_url' ) ) );
	}

	/** `id:secret` or empty string. */
	public static function token_pair(): string {
		$id     = trim( (string) self::get( 'token_id' ) );
		$secret = trim( (string) self::get( 'token_secret' ) );
		return ( '' === $id || '' === $secret ) ? '' : $id . ':' . $secret;
	}

	/** Whether the CocoBook connection is configured. */
	public static function is_configured(): bool {
		return '' !== self::base_url() && '' !== self::token_pair();
	}

	/** keep | cocobook | git */
	public static function content_mode(): string {
		$m = (string) self::get( 'content_mode' );
		return in_array( $m, array( self::CONTENT_KEEP, self::CONTENT_COCOBOOK, self::CONTENT_GIT ), true )
			? $m
			: self::CONTENT_COCOBOOK;
	}

	/**
	 * CocoBook rollout. Forced off when content_mode is not cocobook.
	 */
	public static function mode(): string {
		if ( self::CONTENT_COCOBOOK !== self::content_mode() ) {
			return self::MODE_OFF;
		}
		$m = (string) self::get( 'mode' );
		return in_array( $m, array( self::MODE_OFF, self::MODE_PILOT, self::MODE_ALL ), true ) ? $m : self::MODE_PILOT;
	}

	/**
	 * Selected book IDs.
	 *
	 * @return int[]
	 */
	public static function books(): array {
		return self::int_list( self::get( 'books' ) );
	}

	/**
	 * Pilot WP post IDs.
	 *
	 * @return int[]
	 */
	public static function pilot_post_ids(): array {
		return self::int_list( self::get( 'pilot_post_ids' ) );
	}

	/**
	 * Post types the plugin may take over.
	 *
	 * @return string[]
	 */
	public static function post_types(): array {
		$v = self::get( 'post_types' );
		$v = is_array( $v ) ? $v : array( 'post' );
		return array_values( array_filter( array_map( 'sanitize_key', $v ) ) );
	}

	/**
	 * Git general settings shape for GIW host.
	 *
	 * @return array<string,mixed>
	 */
	public static function git_general(): array {
		$token = (string) self::get( 'git_forgejo_token' );
		return array(
			'webhook_secret'       => (string) self::get( 'git_webhook_secret' ),
			'forgejo_url'          => untrailingslashit( (string) self::get( 'git_forgejo_url' ) ),
			'forgejo_access_token' => $token,
			'base_directory'       => (string) self::get( 'git_base_directory' ),
			'default_post_type'    => sanitize_key( (string) self::get( 'git_default_post_type' ) ) ?: 'post',
			'default_post_author'  => max( 1, (int) self::get( 'git_default_post_author' ) ),
			'sync_interval'        => sanitize_key( (string) self::get( 'git_sync_interval' ) ) ?: 'hourly',
			'target_blog_id'       => 0,
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function git_repositories(): array {
		$repos = self::get( 'git_repositories' );
		if ( ! is_array( $repos ) || empty( $repos ) ) {
			// One-shot adopt from standalone GIW if present.
			$legacy = get_option( 'giw_repositories', null );
			if ( is_array( $legacy ) && ! empty( $legacy ) ) {
				return $legacy;
			}
			return array();
		}
		return $repos;
	}

	/**
	 * @param array<int,array<string,mixed>> $repos Repos.
	 */
	public static function save_git_repositories( array $repos ): void {
		$cur                   = self::raw();
		$cur['git_repositories'] = $repos;
		Legacy::update_option( self::OPTION, $cur );
	}

	/**
	 * Sanitize and save from the settings form.
	 *
	 * @param array<string,mixed> $input Raw POST data.
	 * @return array<string,mixed> Saved settings.
	 */
	public static function save( array $input ): array {
		$cur = self::raw();
		$out = $cur;

		if ( isset( $input['base_url'] ) && ! self::is_locked( 'base_url' ) ) {
			$out['base_url'] = untrailingslashit( esc_url_raw( trim( (string) $input['base_url'] ) ) );
		}
		if ( array_key_exists( 'tree_url', $input ) ) {
			$out['tree_url'] = esc_url_raw( trim( (string) $input['tree_url'] ) );
		}
		if ( isset( $input['token_id'] ) && ! self::is_locked( 'token_id' ) ) {
			$out['token_id'] = sanitize_text_field( (string) $input['token_id'] );
		}
		// Secret is write-only: empty input keeps the stored value.
		if ( ! self::is_locked( 'token_secret' ) ) {
			if ( isset( $input['token_secret'] ) && '' !== trim( (string) $input['token_secret'] ) ) {
				$out['token_secret'] = sanitize_text_field( (string) $input['token_secret'] );
			}
			if ( ! empty( $input['token_secret_clear'] ) ) {
				$out['token_secret'] = '';
			}
		}
		if ( isset( $input['site_tag'] ) ) {
			$out['site_tag'] = sanitize_key( (string) $input['site_tag'] );
		}
		$out['books'] = self::int_list( $input['books'] ?? array() );
		if ( isset( $input['bff_url'] ) ) {
			$out['bff_url'] = untrailingslashit( esc_url_raw( trim( (string) $input['bff_url'] ) ) );
		}

		if ( isset( $input['content_mode'] ) ) {
			$cm = sanitize_key( (string) $input['content_mode'] );
			$out['content_mode'] = in_array( $cm, array( self::CONTENT_KEEP, self::CONTENT_COCOBOOK, self::CONTENT_GIT ), true )
				? $cm
				: self::CONTENT_COCOBOOK;
		}

		if ( isset( $input['mode'] ) ) {
			$m           = sanitize_key( (string) $input['mode'] );
			$out['mode'] = in_array( $m, array( self::MODE_OFF, self::MODE_PILOT, self::MODE_ALL ), true ) ? $m : self::MODE_PILOT;
		}
		// Keep + content_mode=keep ⇒ force rollout off for Frontend.
		if ( self::CONTENT_KEEP === ( $out['content_mode'] ?? '' ) ) {
			$out['mode'] = self::MODE_OFF;
		}

		if ( isset( $input['pilot_post_ids'] ) ) {
			$out['pilot_post_ids'] = self::int_list( $input['pilot_post_ids'] );
		}
		if ( isset( $input['post_types'] ) ) {
			$types             = is_array( $input['post_types'] ) ? $input['post_types'] : explode( ',', (string) $input['post_types'] );
			$out['post_types'] = array_values( array_filter( array_map( 'sanitize_key', array_map( 'trim', $types ) ) ) );
		}
		if ( isset( $input['new_page_prefix'] ) ) {
			$p                      = '/' . trim( sanitize_text_field( (string) $input['new_page_prefix'] ), '/' ) . '/';
			$out['new_page_prefix'] = '//' === $p ? '/' : $p;
		}
		if ( isset( $input['page_ttl'] ) ) {
			$ttl             = (int) $input['page_ttl'];
			$out['page_ttl'] = $ttl <= 0 ? 0 : max( 60, min( DAY_IN_SECONDS, $ttl ) );
		}
		$out['comments']  = isset( $input['comments'] ) && '1' === (string) $input['comments'];
		$out['redirects'] = isset( $input['redirects'] ) && '1' === (string) $input['redirects'];
		if ( isset( $input['comment_min_chars'] ) ) {
			$out['comment_min_chars'] = max( 1, min( 1000, (int) $input['comment_min_chars'] ) );
		}

		// Git fields.
		if ( isset( $input['git_forgejo_url'] ) && ! self::is_locked( 'git_forgejo_url' ) ) {
			$out['git_forgejo_url'] = untrailingslashit( esc_url_raw( trim( (string) $input['git_forgejo_url'] ) ) );
		}
		if ( ! self::is_locked( 'git_forgejo_token' ) ) {
			if ( isset( $input['git_forgejo_token'] ) && '' !== trim( (string) $input['git_forgejo_token'] ) ) {
				$out['git_forgejo_token'] = sanitize_text_field( (string) $input['git_forgejo_token'] );
			}
			if ( ! empty( $input['git_forgejo_token_clear'] ) ) {
				$out['git_forgejo_token'] = '';
			}
		}
		if ( isset( $input['git_webhook_secret'] ) && ! self::is_locked( 'git_webhook_secret' ) ) {
			$secret = trim( (string) $input['git_webhook_secret'] );
			if ( '' !== $secret ) {
				$out['git_webhook_secret'] = sanitize_text_field( $secret );
			}
		}
		if ( isset( $input['git_base_directory'] ) ) {
			$out['git_base_directory'] = sanitize_text_field( (string) $input['git_base_directory'] );
		}
		if ( isset( $input['git_default_post_type'] ) ) {
			$out['git_default_post_type'] = sanitize_key( (string) $input['git_default_post_type'] ) ?: 'post';
		}
		if ( isset( $input['git_default_post_author'] ) ) {
			$out['git_default_post_author'] = max( 1, (int) $input['git_default_post_author'] );
		}
		if ( isset( $input['git_sync_interval'] ) ) {
			$iv = sanitize_key( (string) $input['git_sync_interval'] );
			$out['git_sync_interval'] = in_array( $iv, array( 'every_30_minutes', 'hourly', 'every_6_hours', 'every_12_hours', 'daily' ), true )
				? $iv
				: 'hourly';
		}
		if ( isset( $input['git_repositories'] ) && is_array( $input['git_repositories'] ) ) {
			$out['git_repositories'] = self::sanitize_git_repos( $input['git_repositories'] );
		} elseif ( array_key_exists( 'git_repo_slug', $input ) ) {
			// Simple settings form: one primary repo as owner/name.
			$slug   = trim( (string) $input['git_repo_slug'] );
			$folder = sanitize_text_field( (string) ( $input['git_repo_folder'] ?? '' ) );
			$branch = sanitize_text_field( (string) ( $input['git_repo_branch'] ?? 'main' ) ) ?: 'main';
			$user   = '';
			$repo   = '';
			if ( '' !== $slug && false !== strpos( $slug, '/' ) ) {
				$parts = explode( '/', $slug, 2 );
				$user  = sanitize_text_field( $parts[0] );
				$repo  = sanitize_text_field( $parts[1] );
			}
			if ( '' === $user && '' === $repo ) {
				$out['git_repositories'] = array();
			} else {
				$out['git_repositories'] = array(
					1 => array(
						'username'         => $user,
						'repository'       => $repo,
						'folder'           => $folder,
						'branch'           => $branch,
						'post_type'        => sanitize_key( (string) ( $input['git_repo_post_type'] ?? $out['git_default_post_type'] ?? 'post' ) ) ?: 'post',
						'post_author'      => max( 1, (int) ( $input['git_repo_post_author'] ?? $out['git_default_post_author'] ?? 1 ) ),
						'content_template' => '%%content%%',
						'last_publish'     => (int) ( $input['git_repo_last_publish'] ?? 0 ),
					),
				);
			}
		}
		// CocoBook rollout: UI only offers pilot|all; coerce off → pilot when in cocobook.
		if ( self::CONTENT_COCOBOOK === ( $out['content_mode'] ?? '' ) && self::MODE_OFF === ( $out['mode'] ?? '' ) ) {
			$out['mode'] = self::MODE_PILOT;
		}

		if ( '' === (string) $out['refresh_secret'] ) {
			$out['refresh_secret'] = wp_generate_password( 40, false, false );
		}
		if ( self::CONTENT_GIT === ( $out['content_mode'] ?? '' ) && '' === (string) ( $out['git_webhook_secret'] ?? '' ) ) {
			$out['git_webhook_secret'] = wp_generate_password( 32, false, false );
		}

		// Adopt standalone GIW general settings once when switching to git with empty forgejo url.
		if ( self::CONTENT_GIT === ( $out['content_mode'] ?? '' ) && '' === (string) ( $out['git_forgejo_url'] ?? '' ) ) {
			$giw = get_option( 'giw_general_settings', array() );
			if ( is_array( $giw ) && ! empty( $giw['forgejo_url'] ) ) {
				$out['git_forgejo_url']    = untrailingslashit( (string) $giw['forgejo_url'] );
				$out['git_forgejo_token']  = (string) ( $giw['forgejo_access_token'] ?? $out['git_forgejo_token'] );
				$out['git_webhook_secret'] = (string) ( $giw['webhook_secret'] ?? $out['git_webhook_secret'] );
				$out['git_base_directory'] = (string) ( $giw['base_directory'] ?? $out['git_base_directory'] );
			}
		}

		Legacy::update_option( self::OPTION, $out );
		return $out;
	}

	/**
	 * @param array<int|string,mixed> $raw Raw repo rows from form.
	 * @return array<int,array<string,mixed>>
	 */
	private static function sanitize_git_repos( array $raw ): array {
		$out = array();
		$i   = 0;
		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$user = sanitize_text_field( (string) ( $row['username'] ?? '' ) );
			$repo = sanitize_text_field( (string) ( $row['repository'] ?? '' ) );
			if ( '' === $user && '' === $repo ) {
				continue;
			}
			++$i;
			$out[ $i ] = array(
				'username'         => $user,
				'repository'       => $repo,
				'folder'           => sanitize_text_field( (string) ( $row['folder'] ?? '' ) ),
				'branch'           => sanitize_text_field( (string) ( $row['branch'] ?? 'main' ) ) ?: 'main',
				'post_type'        => sanitize_key( (string) ( $row['post_type'] ?? 'post' ) ) ?: 'post',
				'post_author'      => max( 1, (int) ( $row['post_author'] ?? 1 ) ),
				'content_template' => (string) ( $row['content_template'] ?? '%%content%%' ),
				'last_publish'     => (int) ( $row['last_publish'] ?? 0 ),
			);
		}
		return $out;
	}

	/**
	 * Normalize a list of positive ints from array or comma/space string.
	 *
	 * @param mixed $v Input.
	 * @return int[]
	 */
	public static function int_list( $v ): array {
		if ( is_string( $v ) ) {
			$v = preg_split( '/[\s,，]+/u', $v );
		}
		if ( ! is_array( $v ) ) {
			return array();
		}
		$ids = array_filter( array_map( 'absint', $v ) );
		return array_values( array_unique( $ids ) );
	}

	/** Whether legacy-path redirects are on and connection is configured. */
	public static function redirects_enabled(): bool {
		return (bool) self::get( 'redirects' ) && self::is_configured();
	}

}
