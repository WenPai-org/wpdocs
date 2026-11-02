<?php
/**
 * Publish probe URL builders and status JSON shape (no WordPress bootstrap).
 *
 *   php tests/PublishProbeTest.php
 *
 * Covers sitemap/tree URL join, HEAD result keys, skipped probes, and the
 * GET /status publish envelope. Does not issue HTTP.
 *
 * @package Wpdocs
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

require_once __DIR__ . '/../src/Publish/Probe.php';

use Wpdocs\Publish\Probe;

$fails = 0;

/**
 * @param mixed $got
 * @param mixed $want
 */
function wpdocs_pub_expect( string $name, $got, $want ): void {
	global $fails;
	if ( $got !== $want ) {
		fwrite(
			STDERR,
			"FAIL {$name}: got " . var_export( $got, true ) . ' want ' . var_export( $want, true ) . PHP_EOL
		);
		++$fails;
		return;
	}
	echo "ok {$name}" . PHP_EOL;
}

function wpdocs_pub_true( string $name, bool $got ): void {
	wpdocs_pub_expect( $name, $got, true );
}

function wpdocs_pub_false( string $name, bool $got ): void {
	wpdocs_pub_expect( $name, $got, false );
}

$base = 'https://docs.cocobook.com';

// --- join / sitemap / default tree ---
wpdocs_pub_expect( 'join home', Probe::join_url( $base, '/' ), $base . '/' );
wpdocs_pub_expect( 'join strip slash', Probe::join_url( $base . '/', '/cocobook/sitemap.xml' ), $base . '/cocobook/sitemap.xml' );
wpdocs_pub_expect( 'join empty base', Probe::join_url( '', '/books' ), '' );
wpdocs_pub_expect( 'sitemap path const', Probe::SITEMAP_PATH, '/cocobook/sitemap.xml' );
wpdocs_pub_expect( 'sitemap url', Probe::sitemap_url( $base ), $base . '/cocobook/sitemap.xml' );
wpdocs_pub_expect( 'sitemap empty', Probe::sitemap_url( '' ), '' );
wpdocs_pub_expect( 'tree path const', Probe::TREE_PATH, '/books' );
wpdocs_pub_expect( 'default tree', Probe::default_tree_url( $base ), $base . '/books' );

// --- tree URL override (constant > setting > default; explicit empty skips) ---
wpdocs_pub_expect(
	'tree constant wins',
	Probe::resolve_tree_url( $base, 'https://tree.example/raw', 'https://ignored.example' ),
	'https://tree.example/raw'
);
wpdocs_pub_expect(
	'tree setting',
	Probe::resolve_tree_url( $base, '', 'https://cdn.example/tree/' ),
	'https://cdn.example/tree'
);
wpdocs_pub_expect( 'tree setting skip', Probe::resolve_tree_url( $base, '', '' ), '' );
wpdocs_pub_expect( 'tree default when unset', Probe::resolve_tree_url( $base, '', null ), $base . '/books' );

// --- short timeout ---
wpdocs_pub_true( 'timeout short', Probe::TIMEOUT_SEC > 0 && Probe::TIMEOUT_SEC <= 8 );
wpdocs_pub_expect( 'timeout sec', Probe::TIMEOUT_SEC, 5 );
wpdocs_pub_expect( 'method HEAD', Probe::METHOD, 'HEAD' );

// --- result shape: HEAD metadata only, never a body ---
$ok = Probe::result( $base . '/', Probe::METHOD, 200, 42, '' );
wpdocs_pub_expect(
	'result keys',
	array_keys( $ok ),
	array( 'url', 'method', 'code', 'ms', 'error', 'ok', 'skipped' )
);
wpdocs_pub_expect( 'result url', $ok['url'], $base . '/' );
wpdocs_pub_expect( 'result method', $ok['method'], 'HEAD' );
wpdocs_pub_expect( 'result code', $ok['code'], 200 );
wpdocs_pub_expect( 'result ms', $ok['ms'], 42 );
wpdocs_pub_expect( 'result error', $ok['error'], '' );
wpdocs_pub_true( 'result ok 200', $ok['ok'] );
wpdocs_pub_false( 'result not skipped', $ok['skipped'] );
wpdocs_pub_false( 'result no body', array_key_exists( 'body', $ok ) );
wpdocs_pub_false( 'result no markdown', array_key_exists( 'markdown', $ok ) );
wpdocs_pub_false( 'result no content', array_key_exists( 'content', $ok ) );

$bad = Probe::result( $base . '/cocobook/sitemap.xml', Probe::METHOD, 0, 9, 'timed out' );
wpdocs_pub_false( 'transport not ok', $bad['ok'] );
wpdocs_pub_expect( 'transport error kept', $bad['error'], 'timed out' );
wpdocs_pub_expect( 'transport code 0', $bad['code'], 0 );

$not_found = Probe::result( $base . '/cocobook/sitemap.xml', Probe::METHOD, 404, 5, '' );
wpdocs_pub_false( '404 not ok', $not_found['ok'] );

wpdocs_pub_true( '204 ok', Probe::is_ok_code( 204 ) );
wpdocs_pub_false( '199 not ok', Probe::is_ok_code( 199 ) );
wpdocs_pub_false( '301 not ok', Probe::is_ok_code( 301 ) );
wpdocs_pub_false( '0 not ok', Probe::is_ok_code( 0 ) );

$skip = Probe::skipped( '' );
wpdocs_pub_true( 'skipped flag', $skip['skipped'] );
wpdocs_pub_false( 'skipped not ok', $skip['ok'] );
wpdocs_pub_expect( 'skipped code', $skip['code'], 0 );
wpdocs_pub_expect( 'skipped ms', $skip['ms'], 0 );
wpdocs_pub_expect( 'skipped method', $skip['method'], 'HEAD' );
wpdocs_pub_expect( 'skipped keys', array_keys( $skip ), array( 'url', 'method', 'code', 'ms', 'error', 'ok', 'skipped' ) );

// --- GET /status publish envelope ---
$home    = Probe::result( $base . '/', Probe::METHOD, 200, 10, '' );
$sitemap = Probe::result( Probe::sitemap_url( $base ), Probe::METHOD, 404, 8, '' );
$tree    = Probe::result( Probe::default_tree_url( $base ), Probe::METHOD, 200, 11, '' );
$payload = Probe::status_payload( $base, Probe::default_tree_url( $base ), $home, $sitemap, $tree );

wpdocs_pub_expect(
	'status keys',
	array_keys( $payload ),
	array( 'base_url', 'tree_url', 'timeout', 'home', 'sitemap', 'tree', 'ok', 'error' )
);
wpdocs_pub_expect( 'status base', $payload['base_url'], $base );
wpdocs_pub_expect( 'status tree_url', $payload['tree_url'], $base . '/books' );
wpdocs_pub_expect( 'status timeout', $payload['timeout'], Probe::TIMEOUT_SEC );
wpdocs_pub_true( 'status ok follows home', $payload['ok'] );
wpdocs_pub_expect( 'status error empty', $payload['error'], '' );
wpdocs_pub_false( 'sitemap 404 does not flip ok', $payload['sitemap']['ok'] );
wpdocs_pub_true( 'tree 200 ok', $payload['tree']['ok'] );
wpdocs_pub_false( 'envelope no body', array_key_exists( 'body', $payload ) );

$home_down = Probe::result( $base . '/', Probe::METHOD, 0, 3, 'connection refused' );
$down      = Probe::status_payload( $base, '', $home_down, Probe::skipped( Probe::sitemap_url( $base ) ), Probe::skipped( '' ) );
wpdocs_pub_false( 'home fail => not ok', $down['ok'] );
wpdocs_pub_true( 'empty tree skipped', $down['tree']['skipped'] );

$fatal = Probe::failed_status( 'boom' );
wpdocs_pub_false( 'fatal not ok', $fatal['ok'] );
wpdocs_pub_expect( 'fatal error', $fatal['error'], 'boom' );
wpdocs_pub_true( 'fatal home skipped', $fatal['home']['skipped'] );
wpdocs_pub_true( 'fatal sitemap skipped', $fatal['sitemap']['skipped'] );
wpdocs_pub_true( 'fatal tree skipped', $fatal['tree']['skipped'] );
wpdocs_pub_expect( 'fatal keys', array_keys( $fatal ), array( 'base_url', 'tree_url', 'timeout', 'home', 'sitemap', 'tree', 'ok', 'error' ) );

if ( $fails > 0 ) {
	fwrite( STDERR, "FAILED {$fails}" . PHP_EOL );
	exit( 1 );
}
echo 'PASS' . PHP_EOL;
exit( 0 );
