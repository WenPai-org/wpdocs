<?php
/**
 * Read-only diagnostics against docs host / publish tree.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Publish;

use Wpdocs\Compat\Legacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class HttpSource {

	public static function base_url(): string {
		if ( defined( 'WPDOCS_BASE_URL' ) && WPDOCS_BASE_URL ) {
			return untrailingslashit( (string) WPDOCS_BASE_URL );
		}
		$s = Legacy::get_option( 'wpdocs_settings', array() );
		if ( is_array( $s ) && ! empty( $s['base_url'] ) ) {
			return untrailingslashit( (string) $s['base_url'] );
		}
		return 'https://docs.cocobook.com';
	}

	/** @return array<string,mixed> */
	public static function status(): array {
		$base = self::base_url();
		$out  = array(
			'base_url' => $base,
			'home'     => self::probe( $base . '/' ),
			'sitemap'  => self::probe( $base . '/sitemap.xml' ),
			'ok'       => false,
		);
		$out['ok'] = ( ( $out['home']['code'] ?? 0 ) === 200 );
		return $out;
	}

	/** @return array{code:int,ms:int,error:string} */
	private static function probe( string $url ): array {
		$t0 = microtime( true );
		$r  = wp_remote_get(
			$url,
			array(
				'timeout'     => 8,
				'redirection' => 3,
				'user-agent'  => 'Wpdocs/' . WPDOCS_VERSION,
			)
		);
		$ms = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		if ( is_wp_error( $r ) ) {
			return array( 'code' => 0, 'ms' => $ms, 'error' => $r->get_error_message() );
		}
		return array(
			'code'  => (int) wp_remote_retrieve_response_code( $r ),
			'ms'    => $ms,
			'error' => '',
		);
	}
}
