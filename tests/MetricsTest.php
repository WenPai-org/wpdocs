<?php
/**
 * Metrics pure helpers (no WordPress bootstrap).
 *
 *   php tests/MetricsTest.php
 *
 * @package Wpdocs
 */

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/../' );
}

require_once __DIR__ . '/../src/Metrics.php';

use Wpdocs\Metrics;

$fails = 0;

/**
 * @param mixed $got
 * @param mixed $want
 */
function wpdocs_mx_expect( string $name, $got, $want ): void {
	global $fails;
	if ( $got !== $want ) {
		fwrite( STDERR, "FAIL {$name}: got " . var_export( $got, true ) . ' want ' . var_export( $want, true ) . PHP_EOL );
		++$fails;
		return;
	}
	echo "ok {$name}" . PHP_EOL;
}

$c = Metrics::coverage( 3, 10 );
wpdocs_mx_expect( 'coverage active', $c['active'], 3 );
wpdocs_mx_expect( 'coverage eligible', $c['eligible'], 10 );
wpdocs_mx_expect( 'coverage ratio', $c['ratio'], 0.3 );

$empty = Metrics::coverage( 0, 0 );
wpdocs_mx_expect( 'coverage empty ratio', $empty['ratio'], null );

wpdocs_mx_expect( 'fail rate half', Metrics::probe_fail_rate( array( true, false, true, false ) ), 0.5 );
wpdocs_mx_expect( 'fail rate none', Metrics::probe_fail_rate( array() ), null );
wpdocs_mx_expect( 'fail rate all ok', Metrics::probe_fail_rate( array( true, true ) ), 0.0 );

$hist = array(
	array( 'day' => '2026-11-01', 'unmatched' => 5, 'home_ok' => true ),
);
$hist = Metrics::merge_day( $hist, '2026-11-02', array( 'unmatched' => 7, 'home_ok' => false ) );
wpdocs_mx_expect( 'merge days', count( $hist ), 2 );
$hist = Metrics::merge_day( $hist, '2026-11-02', array( 'unmatched' => 8, 'home_ok' => false ) );
wpdocs_mx_expect( 'merge same day replace', count( $hist ), 2 );
wpdocs_mx_expect( 'merge same day value', (int) $hist[1]['unmatched'], 8 );

$long = array();
for ( $i = 1; $i <= 16; $i++ ) {
	$long = Metrics::merge_day( $long, sprintf( '2026-10-%02d', $i ), array( 'unmatched' => $i, 'home_ok' => true ), 14 );
}
wpdocs_mx_expect( 'merge cap 14', count( $long ), 14 );
wpdocs_mx_expect( 'merge cap drops oldest', (int) $long[0]['unmatched'], 3 );

$sum = Metrics::summarize(
	array(
		'active'    => 4,
		'disabled'  => 1,
		'total'     => 5,
		'eligible'  => 8,
		'redirects' => array(
			'unmatched' => 12,
			'matched'   => 3,
			'gone'      => 1,
			'total'     => 16,
		),
		'home_ok'   => true,
		'history'   => array(
			array( 'day' => '2026-11-01', 'unmatched' => 10, 'home_ok' => true ),
			array( 'day' => '2026-11-02', 'unmatched' => 12, 'home_ok' => false ),
		),
	)
);
wpdocs_mx_expect( 'summarize ratio', $sum['bindings']['ratio'], 0.5 );
wpdocs_mx_expect( 'summarize unmatched', $sum['redirects']['unmatched'], 12 );
wpdocs_mx_expect( 'summarize fail rate', $sum['probe']['fail_rate_14d'], 0.5 );
wpdocs_mx_expect( 'summarize history days', $sum['history']['days'], 2 );
wpdocs_mx_expect( 'summarize series len', count( $sum['history']['unmatched'] ), 2 );

if ( $fails > 0 ) {
	fwrite( STDERR, "FAILED {$fails}" . PHP_EOL );
	exit( 1 );
}
echo "ALL PASS" . PHP_EOL;
exit( 0 );
