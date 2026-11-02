<?php
/**
 * Redirect helpers that run without a WordPress bootstrap.
 *
 *   php tests/RedirectPathTest.php
 *
 * Covers path normalization, reserved paths, unmatched-log cap, and dry-run
 * Decision::preview (hits_written always false; matched uses page_id).
 *
 * @package Wpdocs
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

require_once __DIR__ . '/../src/Redirect/Path.php';
require_once __DIR__ . '/../src/Redirect/Repository.php';
require_once __DIR__ . '/../src/Redirect/Decision.php';

use Wpdocs\Redirect\Decision;
use Wpdocs\Redirect\Path;
use Wpdocs\Redirect\Repository;

$fails = 0;

/**
 * @param mixed $got
 * @param mixed $want
 */
function wpdocs_expect( string $name, $got, $want ): void {
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

function wpdocs_expect_true( string $name, bool $got ): void {
	wpdocs_expect( $name, $got, true );
}

function wpdocs_expect_false( string $name, bool $got ): void {
	wpdocs_expect( $name, $got, false );
}

// --- Path::norm_path ---
wpdocs_expect( 'norm trailing slash', Path::norm_path( '/foo/bar/' ), '/foo/bar' );
wpdocs_expect( 'norm query', Path::norm_path( '/foo/bar?x=1' ), '/foo/bar' );
wpdocs_expect( 'norm hash', Path::norm_path( '/foo/bar#h' ), '/foo/bar' );
wpdocs_expect( 'norm absolute url', Path::norm_path( 'https://example.com/foo/bar/?q=1' ), '/foo/bar' );
wpdocs_expect( 'norm host with port', Path::norm_path( 'http://example.com:8080/docs/old' ), '/docs/old' );
wpdocs_expect( 'norm root', Path::norm_path( '/' ), '/' );
wpdocs_expect( 'norm empty', Path::norm_path( '' ), '/' );
wpdocs_expect( 'norm relative', Path::norm_path( 'foo' ), '/foo' );
wpdocs_expect( 'norm double slash', Path::norm_path( '/foo//bar/' ), '/foo/bar' );
wpdocs_expect( 'norm root url', Path::norm_path( 'https://example.com' ), '/' );

// --- Path::strip_home_prefix ---
wpdocs_expect( 'strip subdir admin', Path::strip_home_prefix( '/blog/wp-admin/plugins.php', '/blog' ), '/wp-admin/plugins.php' );
wpdocs_expect( 'strip subdir exact', Path::strip_home_prefix( '/blog', '/blog' ), '/' );
wpdocs_expect( 'strip no match', Path::strip_home_prefix( '/wp-admin', '/blog' ), '/wp-admin' );
wpdocs_expect( 'strip root home', Path::strip_home_prefix( '/wp-admin', '/' ), '/wp-admin' );

// --- reserved (must not hijack) ---
wpdocs_expect_true( 'reserved wp-admin', Path::is_reserved( '/wp-admin' ) );
wpdocs_expect_true( 'reserved wp-admin nested', Path::is_reserved( '/wp-admin/plugins.php' ) );
wpdocs_expect_true( 'reserved wp-login', Path::is_reserved( '/wp-login.php' ) );
wpdocs_expect_true( 'reserved wp-json', Path::is_reserved( '/wp-json' ) );
wpdocs_expect_true( 'reserved wp-json rest', Path::is_reserved( '/wp-json/wp/v2/posts' ) );
wpdocs_expect_true( 'reserved xmlrpc', Path::is_reserved( '/xmlrpc.php' ) );
wpdocs_expect_true( 'reserved wp-cron', Path::is_reserved( '/wp-cron.php' ) );
wpdocs_expect_true( 'reserved wp-content', Path::is_reserved( '/wp-content/uploads/x.jpg' ) );
wpdocs_expect_false( 'not reserved doc', Path::is_reserved( '/document/hello' ) );
wpdocs_expect_false( 'not reserved wp-admin-guide', Path::is_reserved( '/wp-admin-guide' ) );
wpdocs_expect_false( 'not reserved xmlrpc-docs', Path::is_reserved( '/xmlrpc-docs' ) );

$admin_via_home = Path::norm_path( Path::strip_home_prefix( '/blog/wp-admin', '/blog' ) );
wpdocs_expect_true( 'reserved after home strip', Path::is_reserved( $admin_via_home ) );

// --- 404 skip ---
wpdocs_expect_true( 'skip root 404', Path::should_skip_404_log( '/' ) );
wpdocs_expect_true( 'skip reserved 404', Path::should_skip_404_log( '/wp-login.php' ) );
wpdocs_expect_true( 'skip asset 404', Path::should_skip_404_log( '/logo.png' ) );
wpdocs_expect_false( 'log html-less 404', Path::should_skip_404_log( '/old-document' ) );

// --- unmatched cap (no DB) ---
wpdocs_expect( 'default cap', Repository::unmatched_log_cap(), Repository::UNMATCHED_LOG_CAP );
wpdocs_expect_true( 'cap allows 0', Repository::can_insert_unmatched( 0 ) );
wpdocs_expect_true( 'cap allows n-1', Repository::can_insert_unmatched( Repository::UNMATCHED_LOG_CAP - 1 ) );
wpdocs_expect_false( 'cap blocks n', Repository::can_insert_unmatched( Repository::UNMATCHED_LOG_CAP ) );
wpdocs_expect_false( 'cap blocks n+1', Repository::can_insert_unmatched( Repository::UNMATCHED_LOG_CAP + 1 ) );

// --- Decision::preview: dry-run never writes hits; matched uses page_id ---
$none = Decision::preview( '/missing', null );
wpdocs_expect( 'none action', $none['action'], 'none' );
wpdocs_expect_false( 'none no hits', $none['hits_written'] );

$reserved = Decision::preview(
	'/wp-admin',
	(object) array(
		'id'      => 1,
		'page_id' => 9,
		'status'  => Repository::MATCHED,
	)
);
wpdocs_expect( 'reserved wins over row', $reserved['action'], 'reserved' );
wpdocs_expect_false( 'reserved no hits', $reserved['hits_written'] );

$matched = Decision::preview(
	'/old-doc',
	(object) array(
		'id'      => 3,
		'page_id' => 42,
		'status'  => Repository::MATCHED,
	)
);
wpdocs_expect( 'matched action', $matched['action'], '301' );
wpdocs_expect( 'matched page_id', $matched['page_id'], 42 );
wpdocs_expect_false( 'matched no hits', $matched['hits_written'] );
wpdocs_expect_false( 'matched has no target url', array_key_exists( 'target', $matched ) );
wpdocs_expect_true( 'page_id is int', is_int( $matched['page_id'] ) );

$gone = Decision::preview(
	'/removed',
	(object) array(
		'id'      => 4,
		'page_id' => 0,
		'status'  => Repository::GONE,
	)
);
wpdocs_expect( 'gone action', $gone['action'], '410' );
wpdocs_expect_false( 'gone no hits', $gone['hits_written'] );

$pending = Decision::preview(
	'/later',
	(object) array(
		'id'      => 5,
		'page_id' => 0,
		'status'  => Repository::PENDING,
	)
);
wpdocs_expect( 'pending skip', $pending['action'], 'skip' );
wpdocs_expect_false( 'pending no hits', $pending['hits_written'] );

if ( $fails > 0 ) {
	fwrite( STDERR, "FAILED {$fails}" . PHP_EOL );
	exit( 1 );
}
echo 'PASS' . PHP_EOL;
exit( 0 );
