<?php
/**
 * BookStackClient against recorded fixtures (VCR-style, no network).
 *
 *   php tests/BookStackFixturesTest.php
 *
 * Fixtures live in tests/fixtures/bookstack/*.json — refresh by saving
 * live API responses (strip secrets); never commit tokens.
 *
 * @package Wpdocs
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $s ) {
		return rtrim( (string) $s, '/\\' );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data ) {
		return json_encode( $data );
	}
}
if ( ! class_exists( 'WP_Error', false ) ) {
	class WP_Error {
		/** @var string */
		private $msg;
		public function __construct( $code = '', $msg = '' ) {
			$this->msg = (string) $msg;
		}
		public function get_error_message() {
			return $this->msg;
		}
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
if ( ! function_exists( 'wp_remote_get' ) ) {
	/**
	 * @return WP_Error
	 */
	function wp_remote_get( $url, $args = array() ) {
		return new WP_Error( 'no_http', 'transport required in fixture test' );
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
$fix_dir = __DIR__ . '/fixtures/bookstack';

/**
 * @param mixed $got
 * @param mixed $want
 */
function wpdocs_fx_expect( string $name, $got, $want ): void {
	global $fails;
	if ( $got !== $want ) {
		fwrite( STDERR, "FAIL {$name}: got " . var_export( $got, true ) . ' want ' . var_export( $want, true ) . PHP_EOL );
		++$fails;
		return;
	}
	echo "ok {$name}" . PHP_EOL;
}

/**
 * Map request URL → fixture file + HTTP code.
 *
 * @return callable
 */
function wpdocs_fx_transport( string $fix_dir ): callable {
	return static function ( string $url, array $args ) use ( $fix_dir ) {
		$path = (string) ( parse_url( $url, PHP_URL_PATH ) ?? '' );
		$query = (string) ( parse_url( $url, PHP_URL_QUERY ) ?? '' );
		$file  = '';
		$code  = 200;
		if ( '/api/system' === $path ) {
			$file = $fix_dir . '/system.json';
		} elseif ( preg_match( '#^/api/pages/(\d+)$#', $path, $m ) ) {
			$id = (int) $m[1];
			$candidate = $fix_dir . '/page-' . $id . '.json';
			if ( is_readable( $candidate ) ) {
				$file = $candidate;
			} else {
				return array(
					'code' => 404,
					'body' => '{}',
				);
			}
		} elseif ( '/api/search' === $path ) {
			parse_str( $query, $q );
			$qstr = strtolower( (string) ( $q['query'] ?? '' ) );
			if ( false !== strpos( $qstr, 'hello' ) ) {
				$file = $fix_dir . '/search-hello.json';
			} else {
				return array(
					'code' => 200,
					'body' => '{"total":0,"data":[]}',
				);
			}
		} else {
			return array(
				'code' => 404,
				'body' => '{}',
			);
		}
		$body = file_get_contents( $file );
		if ( false === $body ) {
			return new WP_Error( 'fixture', 'unreadable ' . $file );
		}
		return array(
			'code' => $code,
			'body' => $body,
		);
	};
}

$client = new BookStackClient(
	'https://docs.example.test',
	'fixture-id',
	'fixture-secret',
	5,
	wpdocs_fx_transport( $fix_dir )
);

$sys = $client->system();
wpdocs_fx_expect( 'fixture system ok', ! empty( $sys['ok'] ), true );
wpdocs_fx_expect( 'fixture system version', (string) ( $sys['data']['version'] ?? '' ), '24.12.1' );

$page = $client->page( 12 );
wpdocs_fx_expect( 'fixture page ok', ! empty( $page['ok'] ), true );
wpdocs_fx_expect( 'fixture page name', (string) ( $page['data']['name'] ?? '' ), 'Getting Started' );
wpdocs_fx_expect( 'fixture page slug', (string) ( $page['data']['slug'] ?? '' ), 'getting-started' );

$missing = $client->page( 999 );
wpdocs_fx_expect( 'fixture page 404', (string) ( $missing['error'] ?? '' ), BookStackClient::ERR_NOT_FOUND );

$search = $client->search( 'hello docs' );
wpdocs_fx_expect( 'fixture search ok', ! empty( $search['ok'] ), true );
wpdocs_fx_expect( 'fixture search total', (int) ( $search['data']['total'] ?? 0 ), 2 );
wpdocs_fx_expect( 'fixture search first id', (int) ( $search['data']['data'][0]['id'] ?? 0 ), 12 );

$empty = $client->search( 'zzz-no-hit' );
wpdocs_fx_expect( 'fixture search empty', (int) ( $empty['data']['total'] ?? -1 ), 0 );

$row = BookStackClient::health_row( $client );
wpdocs_fx_expect( 'fixture health ok', ! empty( $row['ok'] ), true );
wpdocs_fx_expect( 'fixture health ver', (string) $row['version'], '24.12.1' );

if ( $fails > 0 ) {
	fwrite( STDERR, "FAILED {$fails}" . PHP_EOL );
	exit( 1 );
}
echo "ALL PASS" . PHP_EOL;
exit( 0 );
