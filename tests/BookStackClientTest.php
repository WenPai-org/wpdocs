<?php
/**
 * BookStackClient classification (no network).
 *
 *   php tests/BookStackClientTest.php
 *
 * @package Wpdocs
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	/**
	 * @param string $v Value.
	 */
	function untrailingslashit( $v ) {
		return rtrim( (string) $v, '/\\' );
	}
}

if ( ! function_exists( 'is_wp_error' ) ) {
	/**
	 * @param mixed $v Value.
	 */
	function is_wp_error( $v ) {
		return $v instanceof WP_Error;
	}
}

if ( ! class_exists( 'WP_Error', false ) ) {
	/**
	 * Minimal stub.
	 */
	class WP_Error {
		/** @var string */
		private $message;
		public function __construct( $code = '', $message = '' ) {
			$this->message = (string) $message;
		}
		public function get_error_message() {
			return $this->message;
		}
	}
}

if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * Unused when transport injected.
	 *
	 * @return WP_Error
	 */
	function wp_remote_get( $url, $args = array() ) {
		return new WP_Error( 'no_http', 'transport required in unit test' );
	}
}

if ( ! function_exists( 'wp_remote_retrieve_response_code' ) ) {
	function wp_remote_retrieve_response_code( $r ) {
		return 0;
	}
}

if ( ! function_exists( 'wp_remote_retrieve_body' ) ) {
	function wp_remote_retrieve_body( $r ) {
		return '';
	}
}

require_once __DIR__ . '/../src/Http/BookStackClient.php';

use Wpdocs\Http\BookStackClient;

$fails = 0;

/**
 * @param mixed $got
 * @param mixed $want
 */
function wpdocs_bs_expect( string $name, $got, $want ): void {
	global $fails;
	if ( $got !== $want ) {
		fwrite( STDERR, "FAIL {$name}: got " . var_export( $got, true ) . ' want ' . var_export( $want, true ) . PHP_EOL );
		++$fails;
		return;
	}
	echo "ok {$name}" . PHP_EOL;
}

$transport_ok = static function () {
	return array(
		'code' => 200,
		'body' => wp_json_encode( array( 'version' => '24.12.1' ) ),
	);
};
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}

$client = new BookStackClient(
	'https://docs.example.test',
	'id',
	'secret',
	5,
	static function () {
		return array(
			'code' => 200,
			'body' => '{"version":"24.12.1"}',
		);
	}
);
$sys = $client->system();
wpdocs_bs_expect( 'system ok', ! empty( $sys['ok'] ), true );
wpdocs_bs_expect( 'system version', (string) ( $sys['data']['version'] ?? '' ), '24.12.1' );

$auth = new BookStackClient(
	'https://docs.example.test',
	'id',
	'secret',
	5,
	static function () {
		return array(
			'code' => 401,
			'body' => '{"error":"Unauthorized"}',
		);
	}
);
$bad = $auth->page( 1 );
wpdocs_bs_expect( 'auth error', (string) ( $bad['error'] ?? '' ), BookStackClient::ERR_AUTH );

$missing = new BookStackClient(
	'https://docs.example.test',
	'id',
	'secret',
	5,
	static function () {
		return array(
			'code' => 404,
			'body' => '{}',
		);
	}
);
$nf = $missing->page( 999 );
wpdocs_bs_expect( 'not found', (string) ( $nf['error'] ?? '' ), BookStackClient::ERR_NOT_FOUND );

$none = BookStackClient::from_config( array( 'base_url' => 'https://docs.example.test' ) );
wpdocs_bs_expect( 'from_config needs token', $none, null );

$row = BookStackClient::health_row( null );
wpdocs_bs_expect( 'health skipped', ! empty( $row['skipped'] ), true );

$row_ok = BookStackClient::health_row( $client );
wpdocs_bs_expect( 'health ok', ! empty( $row_ok['ok'] ), true );
wpdocs_bs_expect( 'health ver', (string) $row_ok['version'], '24.12.1' );

if ( $fails > 0 ) {
	fwrite( STDERR, "FAILED {$fails}" . PHP_EOL );
	exit( 1 );
}
echo "ALL PASS" . PHP_EOL;
exit( 0 );
