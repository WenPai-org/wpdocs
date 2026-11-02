<?php
/**
 * Aliases for cocobook-docs option / constant / table names.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Compat;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Legacy {

	public const OPTION_MAP = array(
		'wpdocs_settings'         => 'cocobook_docs_settings',
		'wpdocs_state'            => 'cocobook_docs_state',
		'wpdocs_redirects_db'     => 'cocobook_docs_redirects_db',
		'wpdocs_bindings_db'      => 'cocobook_docs_bindings_db',
		'wpdocs_404'              => 'cocobook_docs_404',
		'wpdocs_health'            => 'cocobook_docs_health',
		'wpdocs_log'               => 'cocobook_docs_log',
	);

	public static function register_aliases(): void {
		if ( defined( 'COCOBOOK_DOCS_BASE_URL' ) && ! defined( 'WPDOCS_BASE_URL' ) ) {
			define( 'WPDOCS_BASE_URL', COCOBOOK_DOCS_BASE_URL );
		}
		if ( defined( 'COCOBOOK_DOCS_TOKEN_ID' ) && ! defined( 'WPDOCS_TOKEN_ID' ) ) {
			define( 'WPDOCS_TOKEN_ID', COCOBOOK_DOCS_TOKEN_ID );
		}
		if ( defined( 'COCOBOOK_DOCS_TOKEN_SECRET' ) && ! defined( 'WPDOCS_TOKEN_SECRET' ) ) {
			define( 'WPDOCS_TOKEN_SECRET', COCOBOOK_DOCS_TOKEN_SECRET );
		}
		if ( defined( 'COCOBOOK_DOCS_REFRESH_SECRET' ) && ! defined( 'WPDOCS_REFRESH_SECRET' ) ) {
			define( 'WPDOCS_REFRESH_SECRET', COCOBOOK_DOCS_REFRESH_SECRET );
		}
	}

	/** Read option with fallback to legacy key. */
	public static function get_option( string $new_key, $default = false ) {
		$v = get_option( $new_key, null );
		if ( null !== $v && false !== $v ) {
			return $v;
		}
		$old = self::OPTION_MAP[ $new_key ] ?? null;
		return $old ? get_option( $old, $default ) : $default;
	}

	public static function update_option( string $new_key, $value ): bool {
		$ok = update_option( $new_key, $value, false );
		$old = self::OPTION_MAP[ $new_key ] ?? null;
		if ( $old ) {
			update_option( $old, $value, false );
		}
		return $ok;
	}
}
