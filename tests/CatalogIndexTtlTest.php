<?php
/**
 * Catalog::index_ttl pure cases (no WP / no network).
 *
 *   php tests/CatalogIndexTtlTest.php
 *
 * @package Wpdocs
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

require_once __DIR__ . '/../src/Catalog.php';

use Wpdocs\Catalog;

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

assert_eq( Catalog::INDEX_TTL, 300, 'default cap constant' );
assert_eq( Catalog::index_ttl( 0 ), 300, 'page_ttl=0 → short index TTL' );
assert_eq( Catalog::index_ttl( -1 ), 300, 'negative → short index TTL' );
assert_eq( Catalog::index_ttl( 60 ), 60, 'page_ttl under cap kept' );
assert_eq( Catalog::index_ttl( 3600 ), 300, 'page_ttl over cap clamped' );
assert_eq( Catalog::index_ttl( 0, 120 ), 120, 'custom cap' );
assert_eq( Catalog::index_ttl( 0, 10 ), 30, 'cap floor 30' );

if ( $fails > 0 ) {
	fwrite( STDERR, "{$fails} failed\n" );
	exit( 1 );
}
echo "ALL PASS\n";
