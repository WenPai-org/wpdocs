<?php
/**
 * Plugin Name:       文派文档
 * Plugin URI:        https://wpcy.com/docs
 * Description:       文派文档（wpdocs）：内容从哪来（保留正文 / 文档站读取 / Git 写入）、绑定、旧路径跳转与 CocoBook 只读探测。
 * Version:           0.3.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            WPCY.COM
 * Author URI:        https://wpcy.com/docs
 * License:           GPL-2.0-or-later
 * Text Domain:       wpdocs
 * Update URI:        https://updates.wenpai.net
 *
 * @package Wpdocs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPDOCS_VERSION', '0.3.0' );
define( 'WPDOCS_FILE', __FILE__ );
define( 'WPDOCS_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPDOCS_URL', plugin_dir_url( __FILE__ ) );

/*
 * Optional wp-config.php overrides:
 *   define( 'WPDOCS_BASE_URL', 'https://docs.cocobook.com' );
 *   define( 'WPDOCS_TOKEN_ID', '...' );
 *   define( 'WPDOCS_TOKEN_SECRET', '...' );
 * Legacy aliases COCOBOOK_DOCS_* still accepted via Compat\Legacy.
 */

$wpdocs_kit = WPDOCS_DIR . 'lib/wenpai-admin-ui/php/load.php';
if ( is_readable( $wpdocs_kit ) ) {
	require_once $wpdocs_kit;
}

spl_autoload_register(
	static function ( string $class ): void {
		$prefix = 'Wpdocs\\';
		if ( strncmp( $class, $prefix, strlen( $prefix ) ) !== 0 ) {
			return;
		}
		$rel  = str_replace( '\\', '/', substr( $class, strlen( $prefix ) ) );
		$file = WPDOCS_DIR . 'src/' . $rel . '.php';
		if ( is_readable( $file ) ) {
			require_once $file;
		}
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		\Wpdocs\Plugin::boot();
	},
	5
);

register_activation_hook(
	__FILE__,
	static function (): void {
		\Wpdocs\Plugin::activate();
	}
);

register_deactivation_hook(
	__FILE__,
	static function (): void {
		\Wpdocs\Plugin::deactivate();
	}
);
