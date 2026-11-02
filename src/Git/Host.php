<?php
/**
 * Embedded Git_It_Write host — settings backed by wpdocs options.
 *
 * @package Wpdocs
 */

use Wpdocs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Drop-in API expected by vendored GIW_* classes.
 */
final class Git_It_Write {

	public static function is_local_mode(): bool {
		$settings = self::general_settings();
		return ! empty( $settings['base_directory'] );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function default_config(): array {
		return array(
			'username'         => '',
			'repository'       => '',
			'folder'           => '',
			'branch'           => 'main',
			'post_type'        => 'post',
			'post_author'      => 1,
			'content_template' => '%%content%%',
			'last_publish'     => 0,
		);
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function default_general_settings(): array {
		return array(
			'webhook_secret'       => '',
			'forgejo_url'          => '',
			'forgejo_access_token' => '',
			'base_directory'       => '',
			'default_post_type'    => 'post',
			'default_post_author'  => 1,
			'sync_interval'        => 'hourly',
			'target_blog_id'       => 0,
		);
	}

	/**
	 * @return string[]
	 */
	public static function allowed_file_types(): array {
		return array( 'md' );
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public static function all_repositories(): array {
		$raw  = Settings::git_repositories();
		$out  = array();
		$def  = self::default_config();
		foreach ( $raw as $id => $config ) {
			$out[ (int) $id ] = wp_parse_args( is_array( $config ) ? $config : array(), $def );
		}
		if ( empty( $out ) ) {
			$out[0] = $def;
		}
		return $out;
	}

	/**
	 * Persist repository list (used by publish-handler after a pull).
	 *
	 * @param array<int,array<string,mixed>> $repos Repos keyed by id.
	 */
	public static function save_repositories( array $repos ): void {
		Settings::save_git_repositories( $repos );
	}

	/**
	 * @return array<string,mixed>
	 */
	public static function general_settings(): array {
		return wp_parse_args( Settings::git_general(), self::default_general_settings() );
	}
}
