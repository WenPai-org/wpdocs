<?php
/**
 * Bind\RemoteMeta response mapping (no network / no WP bootstrap).
 *
 *   php tests/BindRemoteMetaTest.php
 *
 * @package Wpdocs
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

if ( ! function_exists( 'sanitize_title' ) ) {
	/**
	 * @param string $v Value.
	 */
	function sanitize_title( $v ) {
		$v = strtolower( (string) $v );
		$v = preg_replace( '/[^a-z0-9\-]+/', '-', $v );
		return trim( (string) $v, '-' );
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/** @return false */
	function get_transient( $key ) {
		return false;
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/** @return true */
	function set_transient( $key, $value, $ttl ) {
		return true;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/** @return true */
	function delete_transient( $key ) {
		return true;
	}
}

if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $v ) {
		return rtrim( (string) $v, '/\\' );
	}
}

require_once __DIR__ . '/../src/Http/BookStackClient.php';
require_once __DIR__ . '/../src/Bind/RemoteMeta.php';

use Wpdocs\Bind\RemoteMeta;
use Wpdocs\Http\BookStackClient;

$fails = 0;

/**
 * @param mixed $got
 * @param mixed $want
 */
function assert_eq( $got, $want, string $label ): void {
	global $fails;
	if ( $got !== $want ) {
		fwrite( STDERR, "FAIL {$label}: got " . var_export( $got, true ) . " want " . var_export( $want, true ) . "\n" );
		++$fails;
		return;
	}
	echo "ok {$label}\n";
}

$ok = RemoteMeta::from_response(
	array(
		'ok'   => true,
		'data' => array(
			'name'       => '开始使用',
			'slug'       => 'start-guide',
			'updated_at' => '2026-11-01T12:00:00.000000Z',
		),
	)
);
assert_eq( $ok['state'], RemoteMeta::STATE_OK, 'ok state' );
assert_eq( $ok['title'], '开始使用', 'ok title' );
assert_eq( $ok['slug'], 'start-guide', 'ok slug' );

$broken = RemoteMeta::from_response(
	array(
		'ok'    => false,
		'error' => BookStackClient::ERR_NOT_FOUND,
		'code'  => 404,
	)
);
assert_eq( $broken['state'], RemoteMeta::STATE_BROKEN, '404 broken' );

$auth = RemoteMeta::from_response(
	array(
		'ok'    => false,
		'error' => BookStackClient::ERR_AUTH,
		'code'  => 401,
	)
);
assert_eq( $auth['state'], RemoteMeta::STATE_UNKNOWN, 'auth unknown not broken' );

$stale = RemoteMeta::normalize(
	array(
		'state' => RemoteMeta::STATE_OK,
		'title' => '开始使用',
		'slug'  => 'start-guide-v2',
	),
	'start-guide'
);
assert_eq( $stale['state'], RemoteMeta::STATE_STALE, 'slug drift → stale' );
assert_eq( $stale['slug_match'], false, 'slug_match false' );

$match = RemoteMeta::normalize(
	array(
		'state' => RemoteMeta::STATE_OK,
		'title' => '开始使用',
		'slug'  => 'start-guide',
	),
	'start-guide'
);
assert_eq( $match['state'], RemoteMeta::STATE_OK, 'slug match stays ok' );
assert_eq( $match['slug_match'], true, 'slug_match true' );

assert_eq( RemoteMeta::cache_key( 42 ), 'wpdocs_page_meta_42', 'cache key' );

if ( $fails > 0 ) {
	fwrite( STDERR, "{$fails} failed\n" );
	exit( 1 );
}
echo "ALL PASS\n";
