<?php
/**
 * Bind\DriftCheck::summarize (no WP / no network).
 *
 *   php tests/BindDriftCheckTest.php
 *
 * @package Wpdocs
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}

if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $k ) {
		return false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $k, $v, $t ) {
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $k ) {
		return true;
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $v ) {
		return strtolower( (string) $v );
	}
}
if ( ! function_exists( 'untrailingslashit' ) ) {
	function untrailingslashit( $v ) {
		return rtrim( (string) $v, '/\\' );
	}
}

require_once __DIR__ . '/../src/Http/BookStackClient.php';
require_once __DIR__ . '/../src/Bind/RemoteMeta.php';
require_once __DIR__ . '/../src/Bind/DriftCheck.php';

use Wpdocs\Bind\DriftCheck;
use Wpdocs\Bind\RemoteMeta;

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

$rows = array(
	(object) array( 'wp_post_id' => 10, 'docs_page_id' => 1 ),
	(object) array( 'wp_post_id' => 11, 'docs_page_id' => 2 ),
	(object) array( 'wp_post_id' => 12, 'docs_page_id' => 3 ),
);
$meta = array(
	1 => array( 'state' => RemoteMeta::STATE_OK, 'title' => 'A' ),
	2 => array( 'state' => RemoteMeta::STATE_BROKEN, 'title' => '' ),
	3 => array( 'state' => RemoteMeta::STATE_STALE, 'title' => 'C' ),
);
$r = DriftCheck::summarize( $meta, $rows );
assert_eq( $r['checked'], 3, 'checked' );
assert_eq( $r['ok'], 1, 'ok' );
assert_eq( $r['broken'], 1, 'broken' );
assert_eq( $r['stale'], 1, 'stale' );
assert_eq( count( $r['samples'] ), 2, 'samples for bad rows' );
assert_eq( $r['samples'][0]['wp_post_id'], 11, 'broken post id' );
assert_eq( $r['samples'][1]['docs_page_id'], 3, 'stale page id' );

if ( $fails > 0 ) {
	fwrite( STDERR, "{$fails} failed\n" );
	exit( 1 );
}
echo "ALL PASS\n";
