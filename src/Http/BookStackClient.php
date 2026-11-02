<?php
/**
 * Read-only BookStack REST client (Token auth).
 *
 * @package Wpdocs
 */

namespace Wpdocs\Http;

use Wpdocs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * P0 surface: system ping, page read, search.
 * Never writes BookStack; never stores response bodies in options.
 */
final class BookStackClient {

	public const ERR_CONFIG    = 'config';
	public const ERR_HTTP      = 'http';
	public const ERR_AUTH      = 'auth';
	public const ERR_RATE      = 'rate';
	public const ERR_JSON      = 'json';
	public const ERR_NOT_FOUND = 'not_found';

	/** @var string */
	private $base_url;

	/** @var string */
	private $token_id;

	/** @var string */
	private $token_secret;

	/** @var int */
	private $timeout;

	/**
	 * Optional transport for tests: function( string $url, array $args ): array|\WP_Error
	 * Returning array shape: { code:int, body:string }.
	 *
	 * @var callable|null
	 */
	private $transport;

	/**
	 * @param string        $base_url     Docs root without trailing slash.
	 * @param string        $token_id     API token id.
	 * @param string        $token_secret API token secret.
	 * @param int           $timeout      Seconds.
	 * @param callable|null $transport    Optional override of wp_remote_get.
	 */
	public function __construct( string $base_url, string $token_id, string $token_secret, int $timeout = 8, $transport = null ) {
		$this->base_url     = untrailingslashit( $base_url );
		$this->token_id     = $token_id;
		$this->token_secret = $token_secret;
		$this->timeout      = max( 1, $timeout );
		$this->transport    = is_callable( $transport ) ? $transport : null;
	}

	/**
	 * Build from constants (+ optional overrides). Tokens never read from options.
	 *
	 * @param array{base_url?:string,token_id?:string,token_secret?:string,timeout?:int} $override Overrides.
	 */
	public static function from_config( array $override = array() ): ?self {
		$base = isset( $override['base_url'] ) ? (string) $override['base_url'] : '';
		if ( '' === $base && class_exists( '\Wpdocs\Settings', false ) ) {
			$base = Settings::base_url();
		} elseif ( '' === $base && defined( 'WPDOCS_BASE_URL' ) && WPDOCS_BASE_URL ) {
			$base = (string) WPDOCS_BASE_URL;
		}

		$id = isset( $override['token_id'] ) ? (string) $override['token_id'] : '';
		if ( '' === $id && class_exists( '\Wpdocs\Settings', false ) ) {
			$id = trim( (string) Settings::get( 'token_id' ) );
		} elseif ( '' === $id && defined( 'WPDOCS_TOKEN_ID' ) ) {
			$id = (string) WPDOCS_TOKEN_ID;
		}

		$secret = isset( $override['token_secret'] ) ? (string) $override['token_secret'] : '';
		if ( '' === $secret && class_exists( '\Wpdocs\Settings', false ) ) {
			$secret = trim( (string) Settings::get( 'token_secret' ) );
		} elseif ( '' === $secret && defined( 'WPDOCS_TOKEN_SECRET' ) ) {
			$secret = (string) WPDOCS_TOKEN_SECRET;
		}

		$timeout = isset( $override['timeout'] ) ? (int) $override['timeout'] : 8;

		if ( '' === $base || '' === $id || '' === $secret ) {
			return null;
		}

		return new self( $base, $id, $secret, $timeout );
	}

	/**
	 * GET /api/system.
	 *
	 * @return array{ok:bool,error?:string,code?:int,data?:array<string,mixed>,message?:string}
	 */
	public function system(): array {
		return $this->get_json( '/api/system' );
	}

	/**
	 * GET /api/pages/{id}.
	 *
	 * @return array{ok:bool,error?:string,code?:int,data?:array<string,mixed>,message?:string}
	 */
	public function page( int $id ): array {
		if ( $id < 1 ) {
			return array(
				'ok'    => false,
				'error' => self::ERR_CONFIG,
				'code'  => 0,
			);
		}
		return $this->get_json( '/api/pages/' . $id );
	}

	/**
	 * GET /api/search.
	 *
	 * @return array{ok:bool,error?:string,code?:int,data?:array<string,mixed>,message?:string}
	 */
	public function search( string $query, int $count = 20, int $page = 1 ): array {
		$query = trim( $query );
		if ( '' === $query ) {
			return array(
				'ok'    => false,
				'error' => self::ERR_CONFIG,
				'code'  => 0,
			);
		}
		$count = max( 1, min( 100, $count ) );
		$page  = max( 1, $page );
		$path  = '/api/search?' . http_build_query(
			array(
				'query' => $query,
				'count' => $count,
				'page'  => $page,
			),
			'',
			'&',
			PHP_QUERY_RFC3986
		);
		return $this->get_json( $path );
	}

	/**
	 * Compact Studio/REST row for API health (no secrets, no body dump).
	 *
	 * @return array{configured:bool,ok:bool,skipped:bool,error:string,code:int,version:string,ms:int}
	 */
	public static function health_row( ?self $client ): array {
		if ( null === $client ) {
			return array(
				'configured' => false,
				'ok'         => false,
				'skipped'    => true,
				'error'      => self::ERR_CONFIG,
				'code'       => 0,
				'version'    => '',
				'ms'         => 0,
			);
		}
		$t0  = microtime( true );
		$res = $client->system();
		$ms  = (int) round( ( microtime( true ) - $t0 ) * 1000 );
		$data = isset( $res['data'] ) && is_array( $res['data'] ) ? $res['data'] : array();
		$ver  = '';
		if ( isset( $data['version'] ) && is_scalar( $data['version'] ) ) {
			$ver = (string) $data['version'];
		} elseif ( isset( $data['app_version'] ) && is_scalar( $data['app_version'] ) ) {
			$ver = (string) $data['app_version'];
		}
		return array(
			'configured' => true,
			'ok'         => ! empty( $res['ok'] ),
			'skipped'    => false,
			'error'      => (string) ( $res['error'] ?? '' ),
			'code'       => (int) ( $res['code'] ?? 0 ),
			'version'    => $ver,
			'ms'         => $ms,
		);
	}

	/**
	 * @param string $path Path beginning with /api/.
	 * @return array{ok:bool,error?:string,code?:int,data?:array<string,mixed>,message?:string}
	 */
	private function get_json( string $path ): array {
		if ( '' === $this->base_url || '' === $this->token_id || '' === $this->token_secret ) {
			return array(
				'ok'    => false,
				'error' => self::ERR_CONFIG,
				'code'  => 0,
			);
		}

		$url  = $this->base_url . $path;
		$args = array(
			'timeout' => $this->timeout,
			'headers' => array(
				'Authorization' => 'Token ' . $this->token_id . ':' . $this->token_secret,
				'Accept'        => 'application/json',
			),
		);

		if ( null !== $this->transport ) {
			$response = call_user_func( $this->transport, $url, $args );
		} else {
			$response = wp_remote_get( $url, $args );
		}

		if ( is_wp_error( $response ) ) {
			return array(
				'ok'      => false,
				'error'   => self::ERR_HTTP,
				'code'    => 0,
				'message' => $response->get_error_message(),
			);
		}

		if ( is_array( $response ) && isset( $response['code'], $response['body'] ) && ! isset( $response['response'] ) ) {
			$code = (int) $response['code'];
			$body = (string) $response['body'];
		} else {
			$code = (int) wp_remote_retrieve_response_code( $response );
			$body = (string) wp_remote_retrieve_body( $response );
		}

		if ( 401 === $code || 403 === $code ) {
			return array(
				'ok'    => false,
				'error' => self::ERR_AUTH,
				'code'  => $code,
			);
		}
		if ( 429 === $code ) {
			return array(
				'ok'    => false,
				'error' => self::ERR_RATE,
				'code'  => $code,
			);
		}
		if ( 404 === $code ) {
			return array(
				'ok'    => false,
				'error' => self::ERR_NOT_FOUND,
				'code'  => $code,
			);
		}
		if ( $code < 200 || $code >= 300 ) {
			return array(
				'ok'    => false,
				'error' => self::ERR_HTTP,
				'code'  => $code,
			);
		}

		$data = json_decode( $body, true );
		if ( ! is_array( $data ) ) {
			return array(
				'ok'    => false,
				'error' => self::ERR_JSON,
				'code'  => $code,
			);
		}

		return array(
			'ok'   => true,
			'code' => $code,
			'data' => $data,
		);
	}
}
