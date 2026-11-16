<?php
/**
 * Hook registration center.
 *
 * @package Wpdocs
 */

namespace Wpdocs;

use Wpdocs\Compat\Legacy;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Bootstraps the plugin.
 *
 * Freshness model:
 *  - Page **bodies** honour page_ttl (0 = never auto-expire); stored as files, not options.
 *  - Catalog **index** (books/pages metadata, no HTML) uses Catalog::index_ttl (≤300s).
 *  - CocoBook pushes changes through the BookStack webhook: a page event marks
 *    that page stale, anything else marks the catalog stale. Stale entries are
 *    refetched on next use; the old copy is only replaced by a successful fetch.
 *  - Safety net for missed webhooks: one cheap check per day. It asks BookStack
 *    for the total and newest updated_at of books, chapters and pages
 *    (3 tiny requests). Unchanged => nothing happens. Changed => only pages
 *    updated since the last check are cleared, then the catalog is rebuilt.
	 *  - Same daily pass samples bindings for remote title/slug drift (Bind\DriftCheck)
	 *    and records Metrics (coverage / unmatched / probe ok) for GET /status.
 *
 * @since 0.1.0
 */
final class Core {

	public const CHECK_HOOK   = 'wpdocs_check';
	public const LEGACY_HOOK  = 'wpdocs_sync_legacy';
	public const STATE_OPTION = 'wpdocs_state';

	/** @var bool */
	private static bool $booted = false;

	/** Init once. */
	public static function init(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		Frontend::register();
		Embed::register();
		add_action( 'rest_api_init', array( self::class, 'routes' ) );
		add_action( self::CHECK_HOOK, array( self::class, 'daily_check' ) );
		add_action( Catalog::REBUILD_HOOK, array( Catalog::class, 'rebuild' ) );
		add_action( 'init', array( self::class, 'ensure_schedule' ) );
		if ( is_admin() ) {
			add_action( 'admin_notices', array( self::class, 'health_notice' ) );
		}
	}

	/** Admin notice when docs API is down or token near expiry. */
	public static function health_notice(): void {
		if ( ! current_user_can( 'manage_options' ) || ! Settings::is_configured() ) {
			return;
		}
		if ( ! Client::failing() ) {
			return;
		}
		$h = Client::health();
		$msg = __( '文派文档：暂时无法从文档站读取内容。前台继续显示上次成功同步的文档，请检查地址和 API Token。', 'wpdocs' );
		echo '<div class="notice notice-error"><p>' . esc_html( $msg ) . ' <a href="' . esc_url( \Wpdocs\Admin\Menu::url( 'settings' ) ) . '">' . esc_html__( '打开设置', 'wpdocs' ) . '</a></p></div>';
	}

	/** Make sure only the daily check is scheduled (drops the old hourly job). */
	public static function ensure_schedule(): void {
		if ( wp_next_scheduled( self::LEGACY_HOOK ) ) {
			wp_clear_scheduled_hook( self::LEGACY_HOOK );
		}
		if ( ! wp_next_scheduled( self::CHECK_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CHECK_HOOK );
		}
	}

	/** Remove all scheduled jobs (deactivation). */
	public static function unschedule(): void {
		wp_clear_scheduled_hook( self::CHECK_HOOK );
		wp_clear_scheduled_hook( self::LEGACY_HOOK );
		wp_clear_scheduled_hook( Catalog::REBUILD_HOOK );
	}

	/**
	 * Stored check state.
	 *
	 * @return array{fingerprint:array<string,mixed>,checked:int,changed:int,result:string}
	 */
	public static function state(): array {
		$saved = Legacy::get_option( self::STATE_OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();
		return array(
			'fingerprint' => isset( $saved['fingerprint'] ) && is_array( $saved['fingerprint'] ) ? $saved['fingerprint'] : array(),
			'checked'     => (int) ( $saved['checked'] ?? 0 ),
			'changed'     => (int) ( $saved['changed'] ?? 0 ),
			'result'      => (string) ( $saved['result'] ?? '' ),
		);
	}

	/**
	 * Save check state.
	 *
	 * @param array<string,mixed> $state State.
	 */
	private static function save_state( array $state ): void {
		Legacy::update_option( self::STATE_OPTION, $state );
	}

	/**
	 * Cheap change fingerprint: total + newest updated_at per content type.
	 *
	 * @return array<string,array{total:int,updated:string}>|\WP_Error
	 */
	public static function fingerprint() {
		$parts = array();
		foreach ( array( 'books', 'chapters', 'pages' ) as $type ) {
			$res = Client::get(
				$type,
				array(
					'count' => 1,
					'sort'  => '-updated_at',
				)
			);
			if ( is_wp_error( $res ) ) {
				return $res;
			}
			$row            = isset( $res['data'][0] ) && is_array( $res['data'][0] ) ? $res['data'][0] : array();
			$parts[ $type ] = array(
				'total'   => (int) ( $res['total'] ?? 0 ),
				'updated' => (string) ( $row['updated_at'] ?? '' ),
			);
		}
		return $parts;
	}

	/**
	 * Delete every cached page body (catalog is handled separately).
	 *
	 * @return int Page entries marked stale (old copies stay until refetched).
	 */
	public static function flush_pages(): int {
		return Client::mark_stale_prefix( 'page_' );
	}

	/**
	 * Manual "sync now": drop all cached copies, rebuild the catalog, remember the fingerprint.
	 *
	 * @return array<string,mixed>|\WP_Error Catalog.
	 */
	public static function sync_now() {
		Client::$force = true;
		$n = self::flush_pages();
		Catalog::invalidate( 0 );
		$cat = Catalog::rebuild();
		$now = time();
		$fp  = self::fingerprint();
		$st  = self::state();
		if ( ! is_wp_error( $fp ) ) {
			$st['fingerprint'] = $fp;
		}
		$st['checked'] = $now;
		$st['changed'] = $now;
		$st['result']  = is_wp_error( $cat ) ? 'error' : 'manual';
		self::save_state( $st );
		Client::log( is_wp_error( $cat ) ? 'error' : 'info', sprintf( 'Manual sync: %d cached pages marked stale, catalog %s.', $n, is_wp_error( $cat ) ? 'failed: ' . $cat->get_error_message() : 'rebuilt' ) );
		return $cat;
	}

	/** Daily safety-net check (only acts when CocoBook actually changed). */
	public static function daily_check(): void {
		Client::$force = true;
		if ( ! Settings::is_configured() || ( Settings::MODE_OFF === Settings::mode() && ! Settings::redirects_enabled() ) ) {
			return;
		}
		$now = time();
		$st  = self::state();
		$fp  = self::fingerprint();

		if ( is_wp_error( $fp ) ) {
			$st['result'] = 'error';
			self::save_state( $st );
			Client::log( 'error', 'Daily check failed: ' . $fp->get_error_message() );
			return;
		}

		if ( $st['fingerprint'] && $fp === $st['fingerprint'] ) {
			$st['checked'] = $now;
			$st['result']  = 'unchanged';
			self::save_state( $st );
			self::maybe_drift_check();
			return;
		}

		$cleared = 0;
		$how     = 'all';
		if ( $st['fingerprint'] && $st['checked'] > 0 ) {
			// Incremental: pages edited since the last check (1h margin for clock skew).
			$since   = gmdate( 'Y-m-d H:i:s', $st['checked'] - HOUR_IN_SECONDS );
			$changed = Client::list_all( 'pages', array( 'filter[updated_at:gt]' => $since ) );
			if ( ! is_wp_error( $changed ) ) {
				foreach ( $changed as $row ) {
					$id = absint( $row['id'] ?? 0 );
					if ( $id > 0 ) {
						Client::mark_stale( 'page_' . $id );
						++$cleared;
					}
				}
				$how = 'incremental';
			}
		}
		if ( 'all' === $how ) {
			$cleared = self::flush_pages();
		}

		Catalog::invalidate( 0 );
		$cat = Catalog::rebuild();

		$st['fingerprint'] = $fp;
		$st['checked']     = $now;
		$st['changed']     = $now;
		$st['result']      = is_wp_error( $cat ) ? 'error' : 'changed';
		self::save_state( $st );
		Client::log(
			is_wp_error( $cat ) ? 'error' : 'info',
			sprintf( 'Daily check: 文档站有更新, %d page caches marked stale (%s), catalog %s.', $cleared, $how, is_wp_error( $cat ) ? 'failed: ' . $cat->get_error_message() : 'rebuilt' )
		);
		self::maybe_drift_check();
	}

	/** Sample bindings for remote broken / slug drift; record daily metrics. */
	private static function maybe_drift_check(): void {
		if ( Settings::is_configured() ) {
			try {
				\Wpdocs\Bind\DriftCheck::run();
			} catch ( \Throwable $e ) {
				Client::log( 'error', 'Drift check failed: ' . $e->getMessage() );
			}
		}
		try {
			Metrics::record_daily();
		} catch ( \Throwable $e ) {
			Client::log( 'error', 'Metrics record failed: ' . $e->getMessage() );
		}
	}

	/** REST routes. */
	public static function routes(): void {
		register_rest_route(
			'wpdocs/v1',
			'/refresh/(?P<secret>[A-Za-z0-9_\-]{16,128})',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'refresh' ),
				'permission_callback' => '__return_true', // Secret checked in callback (BookStack webhooks are unsigned).
			)
		);
		register_rest_route(
			'cocobook-docs/v1',
			'/refresh/(?P<secret>[A-Za-z0-9_\-]{16,128})',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'refresh' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * BookStack webhook → cache refresh.
	 *
	 * @param \WP_REST_Request $req Request.
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function refresh( \WP_REST_Request $req ) {
		$expected = (string) Settings::get( 'refresh_secret' );
		$given    = (string) $req->get_param( 'secret' );
		if ( '' === $expected || ! hash_equals( $expected, $given ) ) {
			return new \WP_Error( 'rest_no_route', 'No route was found matching the URL and request method.', array( 'status' => 404 ) );
		}
		$body  = $req->get_json_params();
		$body  = is_array( $body ) ? $body : array();
		$event = sanitize_key( (string) ( $body['event'] ?? '' ) );
		$id    = absint( $body['related_item']['id'] ?? $body['id'] ?? 0 );

		if ( 0 === strpos( $event, 'page_' ) && $id > 0 ) {
			Catalog::invalidate( $id );
			\Wpdocs\Bind\RemoteMeta::bust( $id );
		} else {
			Catalog::invalidate( 0 );
		}
		Client::log( 'info', sprintf( 'Webhook %s #%d: cache marked stale.', '' !== $event ? $event : 'refresh', $id ) );

		return new \WP_REST_Response(
			array(
				'ok'      => true,
				'event'   => $event,
				'page_id' => $id,
				'bind'    => $id > 0 ? 'remote_meta_busted' : 'catalog_only',
			),
			200
		);
	}

	/** Full CocoBook → wpdocs refresh URL (admin copy-paste). */
	public static function refresh_url(): string {
		$secret = (string) Settings::get( 'refresh_secret' );
		if ( '' === $secret ) {
			return rest_url( 'wpdocs/v1/refresh/{secret}' );
		}
		return rest_url( 'wpdocs/v1/refresh/' . rawurlencode( $secret ) );
	}
}
