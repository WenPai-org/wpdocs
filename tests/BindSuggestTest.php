<?php
/**
 * Suggest normalizer (no network).
 *
 *   php tests/BindSuggestTest.php
 *
 * @package Wpdocs
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

require_once __DIR__ . '/../src/Bind/Suggest.php';

use Wpdocs\Bind\Suggest;

$fail = 0;
$ok   = static function ( string $label, bool $cond ) use ( &$fail ): void {
	if ( $cond ) {
		echo "ok {$label}\n";
		return;
	}
	echo "FAIL {$label}\n";
	$fail++;
};

$row = Suggest::normalize_search_row(
	array(
		'type'  => 'page',
		'id'    => 42,
		'name'  => 'Hello',
		'slug'  => 'hello',
		'url'   => 'https://docs.example/books/demo/page/hello',
		'book'  => array(
			'id'   => 7,
			'slug' => 'demo',
			'name' => 'Demo',
		),
	)
);
$ok( 'normalize type', is_array( $row ) && 'page' === $row['type'] );
$ok( 'normalize id', is_array( $row ) && 42 === $row['id'] );
$ok( 'normalize book slug', is_array( $row ) && is_array( $row['book'] ) && 'demo' === $row['book']['slug'] );

$cands = Suggest::bind_candidates(
	array(
		array(
			'type' => 'page',
			'id'   => 1,
			'name' => 'A',
			'slug' => 'a',
			'book' => array( 'slug' => 'b1' ),
		),
		array(
			'type' => 'book',
			'id'   => 2,
			'name' => 'Book',
			'slug' => 'bk',
		),
		array(
			'type' => 'page',
			'id'   => 3,
			'name' => 'C',
			'slug' => 'c',
			'book' => array( 'slug' => 'b2' ),
		),
	),
	'page'
);
$ok( 'filter pages count', 2 === count( $cands ) );
$ok( 'cand0 page id', 1 === $cands[0]['docs_page_id'] );
$ok( 'cand0 book', 'b1' === $cands[0]['docs_book_slug'] );
$ok( 'cand0 slug', 'a' === $cands[0]['docs_page_slug'] );
$ok( 'cand1 page id', 3 === $cands[1]['docs_page_id'] );

$all = Suggest::bind_candidates(
	array(
		array(
			'type' => 'chapter',
			'id'   => 9,
			'name' => 'Ch',
			'slug' => 'ch',
		),
	),
	''
);
$ok( 'empty filter keeps chapter', 1 === count( $all ) && 'chapter' === $all[0]['type'] );

if ( $fail > 0 ) {
	fwrite( STDERR, "FAILED {$fail}\n" );
	exit( 1 );
}
echo "ALL PASS\n";
exit( 0 );
