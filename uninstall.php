<?php
/**
 * Uninstall: remove wpdocs-owned options, tables, cron and file cache.
 *
 * Intentionally keeps `cocobook_docs_*` option aliases (rollback path for the
 * legacy plugin) and `giw_*` options (may belong to standalone Git it Write).
 *
 * @package Wpdocs
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Options written by Wpdocs\Settings / state / logs / metrics / db versions.
$options = array(
	'wpdocs_settings',
	'wpdocs_state',
	'wpdocs_log',
	'wpdocs_health',
	'wpdocs_metrics_v1',
	'wpdocs_404',
	'wpdocs_bindings_db',
	'wpdocs_redirects_db',
);
foreach ( $options as $option ) {
	delete_option( $option );
}

// wpdocs_* transients (RemoteMeta page meta, drift report, publish status…).
$wpdb->query(
	$wpdb->prepare(
		"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
		$wpdb->esc_like( '_transient_wpdocs_' ) . '%',
		$wpdb->esc_like( '_transient_timeout_wpdocs_' ) . '%'
	)
);

// Custom tables.
foreach ( array( 'wpdocs_bindings', 'wpdocs_redirects' ) as $table ) {
	// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is a literal suffix on $wpdb->prefix.
	$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
}

// Cron.
wp_clear_scheduled_hook( 'wpdocs_check' );
wp_clear_scheduled_hook( 'wpdocs_sync_legacy' );
wp_clear_scheduled_hook( 'wpdocs_catalog_rebuild' );
delete_transient( 'wpdocs_cat_rebuild_lock' );

// File cache: wp-content/cache/wpdocs/.
$dir = trailingslashit( WP_CONTENT_DIR ) . 'cache/wpdocs/';
if ( is_dir( $dir ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
	WP_Filesystem();
	global $wp_filesystem;
	if ( $wp_filesystem ) {
		$wp_filesystem->delete( $dir, true );
	} else {
		// Fallback: plain recursive delete.
		$it = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $dir, FilesystemIterator::SKIP_DOTS ),
			RecursiveIteratorIterator::CHILD_FIRST
		);
		foreach ( $it as $f ) {
			$f->isDir() ? rmdir( $f->getPathname() ) : unlink( $f->getPathname() );
		}
		rmdir( $dir );
	}
}
