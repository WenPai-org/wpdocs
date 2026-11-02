<?php
/**
 * Bootstrap.
 *
 * @package Wpdocs
 */

namespace Wpdocs;

use Wpdocs\Admin\Menu;
use Wpdocs\Bind\Repository as BindRepo;
use Wpdocs\Compat\Legacy;
use Wpdocs\Redirect\Listener;
use Wpdocs\Redirect\Repository as RedirectRepo;
use Wpdocs\Rest\Controller;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Plugin {

	/** @var bool */
	private static bool $booted = false;

	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		Legacy::register_aliases();
		Listener::register();
		add_action( 'admin_init', array( RedirectRepo::class, 'install' ) );
		add_action( 'admin_init', array( BindRepo::class, 'install' ) );
		add_action( 'rest_api_init', array( Controller::class, 'register' ) );
		if ( is_admin() ) {
			Menu::register();
		}
	}

	public static function activate(): void {
		RedirectRepo::install( true );
		BindRepo::install( true );
		flush_rewrite_rules( false );
	}

	public static function deactivate(): void {
		flush_rewrite_rules( false );
	}
}
