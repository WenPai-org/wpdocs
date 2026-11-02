<?php
/**
 * Content mode migration smoke (no WP bootstrap — pure Settings helpers via stubs skipped).
 *
 * Run: php tests/ContentModeTest.php
 *
 * @package Wpdocs
 */

declare(strict_types=1);

$fail = 0;
$ok   = static function ( string $msg ): void {
	echo "OK  $msg\n";
};
$bad  = static function ( string $msg ) use ( &$fail ): void {
	echo "FAIL $msg\n";
	++$fail;
};

// Mirror migrate rules from Settings without loading WordPress.
$migrate = static function ( array $all ): array {
	if ( empty( $all['content_mode'] ) || ! is_string( $all['content_mode'] ) ) {
		$m = (string) ( $all['mode'] ?? 'pilot' );
		$all['content_mode'] = ( 'off' === $m ) ? 'keep' : 'cocobook';
	}
	if ( ! in_array( $all['content_mode'], array( 'keep', 'cocobook', 'git' ), true ) ) {
		$all['content_mode'] = 'cocobook';
	}
	return $all;
};

$a = $migrate( array( 'mode' => 'off' ) );
if ( 'keep' === $a['content_mode'] ) {
	$ok( 'mode=off → content_mode=keep' );
} else {
	$bad( 'mode=off migration' );
}

$b = $migrate( array( 'mode' => 'pilot' ) );
if ( 'cocobook' === $b['content_mode'] ) {
	$ok( 'mode=pilot → content_mode=cocobook' );
} else {
	$bad( 'mode=pilot migration' );
}

$c = $migrate( array( 'content_mode' => 'git', 'mode' => 'all' ) );
if ( 'git' === $c['content_mode'] ) {
	$ok( 'explicit git preserved' );
} else {
	$bad( 'explicit git' );
}

$d = $migrate( array( 'content_mode' => 'nope' ) );
if ( 'cocobook' === $d['content_mode'] ) {
	$ok( 'invalid content_mode → cocobook' );
} else {
	$bad( 'invalid content_mode' );
}

exit( $fail > 0 ? 1 : 0 );
