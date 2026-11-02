<?php
/**
 * Bind slug matching and resolve-batch plan (no WordPress bootstrap).
 *
 *   php tests/BindResolveDraftTest.php
 *
 * Covers post_name === docs_page_slug, book+page, skip_existing, and
 * ResolveDraft::plan always leaving applied=false (dry-run).
 *
 * @package Wpdocs
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

require_once __DIR__ . '/../src/Bind/Slug.php';
require_once __DIR__ . '/../src/Bind/ResolveDraft.php';

use Wpdocs\Bind\ResolveDraft;
use Wpdocs\Bind\Slug;

$fails = 0;

/**
 * @param mixed $got
 * @param mixed $want
 */
function wpdocs_bind_expect( string $name, $got, $want ): void {
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

function wpdocs_bind_true( string $name, bool $got ): void {
	wpdocs_bind_expect( $name, $got, true );
}

function wpdocs_bind_false( string $name, bool $got ): void {
	wpdocs_bind_expect( $name, $got, false );
}

$catalog = array(
	array(
		'docs_page_id'   => 42,
		'docs_book_slug' => 'guide',
		'docs_page_slug' => 'getting-started',
	),
	array(
		'docs_page_id'   => 43,
		'docs_book_slug' => 'guide',
		'docs_page_slug' => 'install',
	),
	array(
		'docs_page_id'   => 99,
		'docs_book_slug' => 'faq',
		'docs_page_slug' => 'install',
	),
);

// --- Slug::norm ---
wpdocs_bind_expect( 'norm empty', Slug::norm( '' ), '' );
wpdocs_bind_expect( 'norm case', Slug::norm( 'Getting Started' ), 'getting-started' );
wpdocs_bind_expect( 'norm slash', Slug::norm( '/guide/getting-started/' ), 'guide/getting-started' );
wpdocs_bind_expect( 'norm punct', Slug::norm( 'Hello, World!' ), 'hello-world' );

// --- exact vs book+page ranking ---
$exact = Slug::candidates( 'getting-started', $catalog );
wpdocs_bind_expect( 'exact kind', $exact['match'], Slug::MATCH_EXACT );
wpdocs_bind_expect( 'exact page id', $exact['candidates'][0]['docs_page_id'], 42 );

$bp_slash = Slug::candidates( 'guide/install', $catalog );
wpdocs_bind_expect( 'book_page slash', $bp_slash['match'], Slug::MATCH_BOOK_PAGE );
wpdocs_bind_expect( 'book_page slash id', $bp_slash['candidates'][0]['docs_page_id'], 43 );

$bp_hyphen = Slug::candidates( 'guide-install', $catalog );
wpdocs_bind_expect( 'book_page hyphen', $bp_hyphen['match'], Slug::MATCH_BOOK_PAGE );
wpdocs_bind_expect( 'book_page hyphen id', $bp_hyphen['candidates'][0]['docs_page_id'], 43 );

$amb = Slug::candidates( 'install', $catalog );
wpdocs_bind_expect( 'ambiguous two books', $amb['match'], Slug::MATCH_AMBIGUOUS );
wpdocs_bind_expect( 'ambiguous count', count( $amb['candidates'] ), 2 );

$filtered = Slug::candidates( 'install', $catalog, 'guide' );
wpdocs_bind_expect( 'book filter exact', $filtered['match'], Slug::MATCH_EXACT );
wpdocs_bind_expect( 'book filter id', $filtered['candidates'][0]['docs_page_id'], 43 );

$none = Slug::candidates( 'missing-page', $catalog );
wpdocs_bind_expect( 'none kind', $none['match'], Slug::MATCH_NONE );

$fuzzy = Slug::fuzzy_candidates( 'instal', $catalog );
wpdocs_bind_expect( 'fuzzy stub empty', $fuzzy, array() );
$near = Slug::candidates( 'instal', $catalog );
wpdocs_bind_expect( 'near slug is none not fuzzy', $near['match'], Slug::MATCH_NONE );

$exact_over_bp = Slug::candidates(
	'getting-started',
	array(
		array(
			'docs_page_id'   => 1,
			'docs_book_slug' => 'guide',
			'docs_page_slug' => 'getting-started',
		),
		array(
			'docs_page_id'   => 2,
			'docs_book_slug' => 'getting',
			'docs_page_slug' => 'started',
		),
	)
);
wpdocs_bind_expect( 'exact wins over book_page', $exact_over_bp['match'], Slug::MATCH_EXACT );
wpdocs_bind_expect( 'exact wins id', $exact_over_bp['candidates'][0]['docs_page_id'], 1 );

// --- ResolveDraft::plan shape ---
$plan = ResolveDraft::plan(
	array(
		'posts' => array(
			array(
				'wp_post_id' => 11144,
				'post_name'  => 'Getting Started',
				'post_type'  => 'post',
			),
			array(
				'wp_post_id' => 11145,
				'post_name'  => 'guide/install',
			),
			array(
				'wp_post_id' => 11146,
				'post_name'  => 'install',
			),
			array(
				'wp_post_id' => 11147,
				'post_name'  => 'no-such-page',
			),
			array(
				'wp_post_id' => 11148,
				'post_name'  => 'getting-started',
			),
			array(
				'post_name' => 'orphan',
			),
			array(
				'wp_post_id' => 11149,
			),
		),
		'pages'    => $catalog,
		'existing' => array( 11148 ),
		'apply'    => true,
	)
);

wpdocs_bind_expect( 'plan source', $plan['source'], 'slug' );
wpdocs_bind_true( 'plan echoes apply', $plan['apply'] );
wpdocs_bind_false( 'plan never applied', $plan['applied'] );
wpdocs_bind_expect( 'plan applied_ids empty', $plan['applied_ids'], array() );
wpdocs_bind_expect( 'summary bind', $plan['summary']['bind'], 2 );
wpdocs_bind_expect( 'summary ambiguous', $plan['summary']['ambiguous'], 1 );
wpdocs_bind_expect( 'summary unmatched', $plan['summary']['unmatched'], 1 );
wpdocs_bind_expect( 'summary skip', $plan['summary']['skip_existing'], 1 );
wpdocs_bind_expect( 'summary invalid', $plan['summary']['invalid'], 2 );
wpdocs_bind_false( 'summary not capped', $plan['summary']['capped'] );

wpdocs_bind_expect( 'item0 action', $plan['items'][0]['action'], ResolveDraft::ACTION_BIND );
wpdocs_bind_expect( 'item0 match', $plan['items'][0]['match'], Slug::MATCH_EXACT );
wpdocs_bind_expect( 'item0 draft source', $plan['items'][0]['draft']['source'], 'slug' );
wpdocs_bind_expect( 'item0 draft page', $plan['items'][0]['draft']['docs_page_id'], 42 );
wpdocs_bind_expect( 'item0 draft status', $plan['items'][0]['draft']['status'], 'active' );

wpdocs_bind_expect( 'item1 match', $plan['items'][1]['match'], Slug::MATCH_BOOK_PAGE );
wpdocs_bind_expect( 'item1 action', $plan['items'][1]['action'], ResolveDraft::ACTION_BIND );
wpdocs_bind_expect( 'item1 draft page', $plan['items'][1]['draft']['docs_page_id'], 43 );

wpdocs_bind_expect( 'item2 action', $plan['items'][2]['action'], ResolveDraft::ACTION_AMBIGUOUS );
wpdocs_bind_expect( 'item2 draft null', $plan['items'][2]['draft'], null );

wpdocs_bind_expect( 'item3 action', $plan['items'][3]['action'], ResolveDraft::ACTION_UNMATCHED );

wpdocs_bind_expect( 'item4 skip', $plan['items'][4]['action'], ResolveDraft::ACTION_SKIP_EXISTING );
wpdocs_bind_expect( 'item4 still has draft', $plan['items'][4]['draft']['docs_page_id'], 42 );

wpdocs_bind_expect( 'item5 invalid id', $plan['items'][5]['reason'], 'missing_wp_post_id' );
wpdocs_bind_expect( 'item6 invalid name', $plan['items'][6]['reason'], 'missing_post_name' );

$drafts = ResolveDraft::bind_drafts( $plan );
wpdocs_bind_expect( 'bind_drafts count', count( $drafts ), 2 );
wpdocs_bind_expect( 'bind_drafts first post', $drafts[0]['wp_post_id'], 11144 );
wpdocs_bind_expect( 'bind_drafts second post', $drafts[1]['wp_post_id'], 11145 );

$existing_rows = ResolveDraft::plan(
	array(
		'posts'    => array(
			array(
				'wp_post_id' => 7,
				'post_name'  => 'getting-started',
			),
		),
		'pages'    => $catalog,
		'existing' => array(
			array(
				'wp_post_id'   => 7,
				'docs_page_id' => 1,
			),
		),
	)
);
wpdocs_bind_expect( 'existing as rows', $existing_rows['items'][0]['action'], ResolveDraft::ACTION_SKIP_EXISTING );

$by_book = ResolveDraft::plan(
	array(
		'posts'     => array(
			array(
				'wp_post_id' => 8,
				'post_name'  => 'install',
			),
		),
		'pages'     => $catalog,
		'book_slug' => 'faq',
	)
);
wpdocs_bind_expect( 'request book filter', $by_book['items'][0]['action'], ResolveDraft::ACTION_BIND );
wpdocs_bind_expect( 'request book filter id', $by_book['items'][0]['draft']['docs_page_id'], 99 );

$per_post_book = ResolveDraft::plan(
	array(
		'posts' => array(
			array(
				'wp_post_id' => 9,
				'post_name'  => 'install',
				'book_slug'  => 'guide',
			),
		),
		'pages' => $catalog,
	)
);
wpdocs_bind_expect( 'per-post book_slug', $per_post_book['items'][0]['draft']['docs_page_id'], 43 );

$alias = ResolveDraft::plan(
	array(
		'posts' => array(
			array(
				'wp_post_id'  => 10,
				'slug'        => 'getting-started',
				'external_id' => 'wpcy:post:10',
			),
		),
		'pages' => array(
			array(
				'page_id'   => 42,
				'book_slug' => 'guide',
				'slug'      => 'getting-started',
			),
		),
	)
);
wpdocs_bind_expect( 'alias fields bind', $alias['items'][0]['action'], ResolveDraft::ACTION_BIND );
wpdocs_bind_expect( 'external_id passthrough', $alias['items'][0]['draft']['external_id'], 'wpcy:post:10' );

$empty = ResolveDraft::plan( array() );
wpdocs_bind_expect( 'empty items', $empty['items'], array() );
wpdocs_bind_expect( 'empty posts', $empty['summary']['posts'], 0 );
wpdocs_bind_false( 'empty not applied', $empty['applied'] );

wpdocs_bind_true( 'apply true', ResolveDraft::wants_apply( true ) );
wpdocs_bind_true( 'apply 1', ResolveDraft::wants_apply( 1 ) );
wpdocs_bind_true( 'apply "1"', ResolveDraft::wants_apply( '1' ) );
wpdocs_bind_true( 'apply "true"', ResolveDraft::wants_apply( 'true' ) );
wpdocs_bind_false( 'apply false', ResolveDraft::wants_apply( false ) );
wpdocs_bind_false( 'apply 0', ResolveDraft::wants_apply( 0 ) );
wpdocs_bind_false( 'apply missing', ResolveDraft::wants_apply( null ) );
wpdocs_bind_false( 'apply yes not enough', ResolveDraft::wants_apply( 'yes' ) );

$over = array();
for ( $i = 1; $i <= ResolveDraft::MAX_ITEMS + 3; $i++ ) {
	$over[] = array(
		'wp_post_id' => $i,
		'post_name'  => 'p' . $i,
	);
}
$capped = ResolveDraft::plan( array( 'posts' => $over, 'pages' => array() ) );
wpdocs_bind_true( 'capped flag', $capped['summary']['capped'] );
wpdocs_bind_expect( 'capped posts', $capped['summary']['posts'], ResolveDraft::MAX_ITEMS );

$keys = array( 'source', 'apply', 'applied', 'applied_ids', 'book_slug', 'summary', 'items' );
foreach ( $keys as $key ) {
	wpdocs_bind_true( "plan has {$key}", array_key_exists( $key, $plan ) );
}

if ( $fails > 0 ) {
	fwrite( STDERR, "FAILED {$fails}" . PHP_EOL );
	exit( 1 );
}
echo 'PASS' . PHP_EOL;
exit( 0 );
