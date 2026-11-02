<?php
/**
 * Load vendored Git it Write core when content mode is git.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Git;

use Wpdocs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Boots the Forgejo/local markdown → posts pipeline (ex-Git it Write).
 *
 * If the standalone `git-it-write` plugin is already active, we do not load our
 * copy; settings UI still points admins to that plugin or asks them to deactivate it.
 */
final class Bootstrap {

	/** @var bool */
	private static bool $loaded = false;

	/** Whether our stub (or the standalone plugin) is available. */
	public static function available(): bool {
		return class_exists( 'Git_It_Write', false ) || self::can_load();
	}

	/** Whether the standalone plugin owns the classes. */
	public static function standalone_active(): bool {
		return class_exists( 'Git_It_Write', false ) && ! defined( 'WPDOCS_GIW_EMBEDDED' );
	}

	/** Load embedded GIW when mode is git and standalone is off. */
	public static function maybe_boot(): void {
		if ( Settings::CONTENT_GIT !== Settings::content_mode() ) {
			return;
		}
		self::load();
		if ( ! self::standalone_active() && class_exists( 'GIW_Webhook', false ) ) {
			\GIW_Webhook::init();
		}
		if ( ! self::standalone_active() && class_exists( 'GIW_Sync', false ) && \Git_It_Write::is_local_mode() ) {
			\GIW_Sync::init();
		}
	}

	/** Ensure classes are loaded (idempotent). */
	public static function load(): bool {
		if ( self::$loaded ) {
			return class_exists( 'Git_It_Write', false );
		}
		self::$loaded = true;

		if ( class_exists( 'Git_It_Write', false ) ) {
			return true;
		}

		if ( ! self::can_load() ) {
			return false;
		}

		$autoload = WPDOCS_DIR . 'vendor/autoload.php';
		if ( is_readable( $autoload ) ) {
			require_once $autoload;
		}

		if ( ! defined( 'GIW_VERSION' ) ) {
			define( 'GIW_VERSION', WPDOCS_VERSION );
		}
		if ( ! defined( 'GIW_PATH' ) ) {
			define( 'GIW_PATH', WPDOCS_DIR . 'lib/git-it-write/' );
		}
		if ( ! defined( 'GIW_ADMIN_URL' ) ) {
			define( 'GIW_ADMIN_URL', WPDOCS_URL . 'lib/git-it-write/' );
		}
		define( 'WPDOCS_GIW_EMBEDDED', true );

		require_once WPDOCS_DIR . 'src/Git/Host.php';

		$inc = WPDOCS_DIR . 'lib/git-it-write/includes/';
		require_once $inc . 'utilities.php';
		require_once $inc . 'publisher.php';
		require_once $inc . 'publish-handler.php';
		require_once $inc . 'parsedown.php';
		require_once $inc . 'webhook.php';
		require_once $inc . 'shortcodes.php';

		if ( \Git_It_Write::is_local_mode() ) {
			require_once $inc . 'local-repository.php';
			require_once $inc . 'repo-config.php';
			require_once $inc . 'discovery.php';
			require_once $inc . 'sync.php';
		} else {
			require_once $inc . 'repository.php';
		}

		return class_exists( 'Git_It_Write', false );
	}

	private static function can_load(): bool {
		return is_readable( WPDOCS_DIR . 'lib/git-it-write/includes/publisher.php' );
	}
}
