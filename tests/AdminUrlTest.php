<?php
/**
 * Canonical admin URL tests without WordPress.
 *
 * @package Wpdocs
 */

define( 'ABSPATH', __DIR__ . '/../' );

function __( $text, $domain = 'wpdocs' ) {
	return $text;
}

function sanitize_key( $key ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $key ) );
}

function wp_unslash( $value ) {
	return $value;
}

function add_query_arg( $args, $base ) {
	return $base . '?' . http_build_query( $args );
}

function admin_url( $path ) {
	return 'https://example.test/wp-admin/' . $path;
}

final class Wenpai_Admin_Menu {
	public static function url( $slug, $args = array() ) {
		$args = array_merge( array( 'page' => sanitize_key( $slug ) ), $args );
		return add_query_arg( $args, admin_url( 'options-general.php' ) );
	}
}

require __DIR__ . '/../src/Admin/Menu.php';

use Wpdocs\Admin\Menu;

$cases = array(
	array(
		'actual'   => Menu::url(),
		'expected' => 'https://example.test/wp-admin/options-general.php?page=wpdocs',
		'label'    => 'overview',
	),
	array(
		'actual'   => Menu::url( 'settings' ),
		'expected' => 'https://example.test/wp-admin/options-general.php?page=wpdocs&tab=settings',
		'label'    => 'settings',
	),
	array(
		'actual'   => Menu::url( 'tools', 'redirects' ),
		'expected' => 'https://example.test/wp-admin/options-general.php?page=wpdocs&tab=tools&section=redirects',
		'label'    => 'tool section',
	),
	array(
		'actual'   => Menu::url( 'settings', '', 'updated=1&mode=advanced' ),
		'expected' => 'https://example.test/wp-admin/options-general.php?page=wpdocs&tab=settings&updated=1&mode=advanced',
		'label'    => 'save receipt',
	),
	array(
		'actual'   => Menu::url( 'tools', 'bindings', 'updated=1&mode=advanced&status=matched' ),
		'expected' => 'https://example.test/wp-admin/options-general.php?page=wpdocs&tab=tools&section=bindings&updated=1&mode=advanced&status=matched',
		'label'    => 'row action state',
	),
);

foreach ( $cases as $case ) {
	if ( $case['actual'] !== $case['expected'] ) {
		fwrite( STDERR, "FAIL {$case['label']}\nexpected {$case['expected']}\nactual   {$case['actual']}\n" );
		exit( 1 );
	}
	echo "ok {$case['label']}\n";
}

echo "ALL PASS\n";
