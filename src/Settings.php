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
 * @since 0.1.0
 */
final class Settings {

	public const OPTION = 'wpdocs_settings';

	/** Rollout modes. */
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
			'mode'           => self::MODE_PILOT,
			'pilot_post_ids' => array(),
			'post_types'     => array( 'post' ),
			'new_page_prefix'=> '/document/',
			'page_ttl'       => 0, // 0 = never expire; refreshed only when CocoBook changes.
			'comments'       => true,
			'refresh_secret' => '',
			'redirects'      => false, // 301 legacy WP paths to CocoBook (see Redirects).
			'comment_min_chars' => 30, // Legacy comments shorter than this are not migrated.
		);
	}

	/**
	 * All settings merged with defaults (constants not applied).
	 *
	 * @return array<string,mixed>
	 */
	public static function raw(): array {
		$saved = Legacy::get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $saved ) ? $saved : array() );
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

	/** Whether the connection is configured. */
	public static function is_configured(): bool {
		return '' !== self::base_url() && '' !== self::token_pair();
	}

	/** Current rollout mode. */
	public static function mode(): string {
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
	 * Sanitize and save from the settings form.
	 *
	 * @param array<string,mixed> $input Raw POST data.
	 * @return array<string,mixed> Saved settings.
	 */
	public static function save( array $input ): array {
		$cur = self::raw();
		$out = $cur;

		if ( isset( $input['base_url'] ) ) {
			$out['base_url'] = untrailingslashit( esc_url_raw( trim( (string) $input['base_url'] ) ) );
		}
		if ( isset( $input['token_id'] ) ) {
			$out['token_id'] = sanitize_text_field( (string) $input['token_id'] );
		}
		// Secret is write-only: empty input keeps the stored value.
		if ( isset( $input['token_secret'] ) && '' !== trim( (string) $input['token_secret'] ) ) {
			$out['token_secret'] = sanitize_text_field( (string) $input['token_secret'] );
		}
		if ( ! empty( $input['token_secret_clear'] ) ) {
			$out['token_secret'] = '';
		}
		if ( isset( $input['site_tag'] ) ) {
			$out['site_tag'] = sanitize_key( (string) $input['site_tag'] );
		}
		$out['books'] = self::int_list( $input['books'] ?? array() );
		if ( isset( $input['bff_url'] ) ) {
			$out['bff_url'] = untrailingslashit( esc_url_raw( trim( (string) $input['bff_url'] ) ) );
		}
		if ( isset( $input['mode'] ) ) {
			$m           = sanitize_key( (string) $input['mode'] );
			$out['mode'] = in_array( $m, array( self::MODE_OFF, self::MODE_PILOT, self::MODE_ALL ), true ) ? $m : self::MODE_PILOT;
		}
		if ( isset( $input['pilot_post_ids'] ) ) {
			$out['pilot_post_ids'] = self::int_list( $input['pilot_post_ids'] ); // accepts "1, 2" or an array
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
		$out['comments'] = ! empty( $input['comments'] );
		$out['redirects'] = ! empty( $input['redirects'] );
		if ( isset( $input['comment_min_chars'] ) ) {
			$out['comment_min_chars'] = max( 1, min( 1000, (int) $input['comment_min_chars'] ) );
		}

		if ( '' === (string) $out['refresh_secret'] ) {
			$out['refresh_secret'] = wp_generate_password( 40, false, false );
		}

		Legacy::update_option( self::OPTION, $out );
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
