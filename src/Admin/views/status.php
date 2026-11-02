<?php
/**
 * Overview (档 A：概览 = 现在怎样).
 *
 * @package Wpdocs
 * @var array<string,mixed> $st
 */

use Wpdocs\Admin\View;
use Wpdocs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$home_ok   = ! empty( $st['home']['ok'] );
$site_ok   = ! empty( $st['sitemap']['ok'] );
$tree_skip = ! empty( $st['tree']['skipped'] );
$tree_ok   = ! empty( $st['tree']['ok'] );
$bind_n    = (int) \Wpdocs\Bind\Repository::count();
$redir_n   = (int) \Wpdocs\Redirect\Repository::count();
$base_show = Settings::base_url();
$mode      = Settings::mode();
$mode_lab  = array(
	Settings::MODE_OFF   => __( '关闭', 'wpdocs' ),
	Settings::MODE_PILOT => __( '试点', 'wpdocs' ),
	Settings::MODE_ALL   => __( '全部', 'wpdocs' ),
);
$mode_txt = $mode_lab[ $mode ] ?? $mode;

$fmt_probe = static function ( array $p ) use ( $base_show ): string {
	$code = (int) ( $p['code'] ?? 0 );
	$ms   = (int) ( $p['ms'] ?? 0 );
	$err  = (string) ( $p['error'] ?? '' );
	if ( $err ) {
		return sprintf( '%s · %dms', $err, $ms );
	}
	if ( 0 === $code && 0 === $ms && '' === $base_show ) {
		return __( '尚未探测', 'wpdocs' );
	}
	return sprintf( '%d · %dms', $code, $ms );
};

$refresh = '<a class="btn btn-ghost" href="' . esc_url( admin_url( 'admin.php?page=wpdocs&refresh=1' ) ) . '">' . esc_html__( '重新探测', 'wpdocs' ) . '</a>';

View::page_head(
	__( '概览', 'wpdocs' ),
	__( '文档站探测与接管状态。配置请到设置；绑定与同步请到工具。', 'wpdocs' ),
	$refresh
);
View::saved_notice();
View::error_notice();

$configured   = Settings::is_configured() || ( '' !== $base_show );
$empty_maps   = ( 0 === $bind_n && 0 === $redir_n );
$probe_failed = ( '' !== $base_show && ! $home_ok && ! ( $site_ok || ( ! $tree_skip && $tree_ok ) ) );
$show_next    = false;

if ( ! $configured ) :
	$show_next = true;
	?>
	<div class="next">
		<div class="tile accent"><?php echo View::icon( 'settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<strong><?php esc_html_e( '还没连上文档站', 'wpdocs' ); ?></strong>
			<p class="d"><?php esc_html_e( '先填写文档站地址与 Token，再回来看探测结果。', 'wpdocs' ); ?></p>
		</div>
		<a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ); ?>"><?php esc_html_e( '去设置', 'wpdocs' ); ?></a>
	</div>
	<?php
elseif ( $probe_failed ) :
	$show_next = true;
	?>
	<div class="next">
		<div class="tile bad"><?php echo View::icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<strong><?php esc_html_e( '暂时无法探测文档站', 'wpdocs' ); ?></strong>
			<p class="d"><?php esc_html_e( '请检查文档站地址后重试。', 'wpdocs' ); ?></p>
		</div>
		<a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs&refresh=1' ) ); ?>"><?php esc_html_e( '重新探测', 'wpdocs' ); ?></a>
	</div>
	<?php
elseif ( $empty_maps && Settings::MODE_OFF !== $mode ) :
	$show_next = true;
	?>
	<div class="next">
		<div class="tile accent"><?php echo View::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<strong><?php esc_html_e( '还没有绑定或跳转', 'wpdocs' ); ?></strong>
			<p class="d"><?php esc_html_e( '在工具里添加绑定，或同步书目后按标签对应文章。', 'wpdocs' ); ?></p>
		</div>
		<a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=bindings' ) ); ?>"><?php esc_html_e( '去工具', 'wpdocs' ); ?></a>
	</div>
	<?php
elseif ( ! Settings::is_configured() && '' !== $base_show && ! $show_next ) :
	$show_next = true;
	?>
	<div class="next">
		<div class="tile warn"><?php echo View::icon( 'plug' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<strong><?php esc_html_e( '还没填 API Token', 'wpdocs' ); ?></strong>
			<p class="d"><?php esc_html_e( '搜索、同步书目和接口探测需要 Token ID 与 Secret。', 'wpdocs' ); ?></p>
		</div>
		<a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ); ?>"><?php esc_html_e( '去设置', 'wpdocs' ); ?></a>
	</div>
	<?php
endif;

if ( '' !== $base_show && ! $home_ok && ( $site_ok || ( ! $tree_skip && $tree_ok ) ) && ! $show_next ) :
	?>
	<div class="notice warn"><span><?php esc_html_e( '首页探测超时或失败，但站点地图或发布树仍可达。可点「重新探测」再试。', 'wpdocs' ); ?></span></div>
	<?php
endif;
?>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '现在怎样', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php echo esc_html( sprintf( __( '版本 %s。发布树只读，不写文档站。', 'wpdocs' ), WPDOCS_VERSION ) ); ?></p>
	<?php
	View::status_row(
		array(
			'title'      => __( '文档站', 'wpdocs' ),
			'detail'     => '' !== $base_show ? $base_show : __( '未填写', 'wpdocs' ),
			'url'        => '' !== $base_show,
			'icon'       => 'external',
			'pill'       => $home_ok ? __( '已连接', 'wpdocs' ) : ( '' !== $base_show ? __( '探测失败', 'wpdocs' ) : __( '未连接', 'wpdocs' ) ),
			'pill_class' => $home_ok ? 'ok' : ( '' !== $base_show ? 'bad' : 'warn' ),
			'tile_class' => $home_ok ? 'ok' : ( '' !== $base_show ? 'bad' : 'warn' ),
		)
	);
	View::status_row(
		array(
			'title'      => __( '首页', 'wpdocs' ),
			'detail'     => $fmt_probe( (array) ( $st['home'] ?? array() ) ),
			'icon'       => 'home',
			'pill'       => $home_ok ? __( '正常', 'wpdocs' ) : ( '' !== $base_show ? __( '失败', 'wpdocs' ) : __( '等待', 'wpdocs' ) ),
			'pill_class' => $home_ok ? 'ok' : ( '' !== $base_show ? 'bad' : 'warn' ),
			'tile_class' => $home_ok ? 'ok' : ( '' !== $base_show ? 'bad' : 'warn' ),
		)
	);
	View::status_row(
		array(
			'title'      => __( '站点地图', 'wpdocs' ),
			'detail'     => $fmt_probe( (array) ( $st['sitemap'] ?? array() ) ),
			'icon'       => 'map',
			'pill'       => $site_ok ? __( '正常', 'wpdocs' ) : ( '' !== $base_show ? __( '异常', 'wpdocs' ) : __( '等待', 'wpdocs' ) ),
			'pill_class' => $site_ok ? 'ok' : 'warn',
			'tile_class' => $site_ok ? 'ok' : 'warn',
		)
	);
	if ( $tree_skip ) {
		View::status_row(
			array(
				'title'      => __( '发布树', 'wpdocs' ),
				'detail'     => __( '已跳过', 'wpdocs' ),
				'icon'       => 'server',
				'pill'       => __( '已跳过', 'wpdocs' ),
				'pill_class' => 'warn',
				'tile_class' => 'warn',
			)
		);
	} else {
		View::status_row(
			array(
				'title'      => __( '发布树', 'wpdocs' ),
				'detail'     => $fmt_probe( (array) ( $st['tree'] ?? array() ) ),
				'icon'       => 'server',
				'pill'       => $tree_ok ? __( '正常', 'wpdocs' ) : __( '失败', 'wpdocs' ),
				'pill_class' => $tree_ok ? 'ok' : 'bad',
				'tile_class' => $tree_ok ? 'ok' : 'bad',
			)
		);
	}
	$api = isset( $st['api'] ) && is_array( $st['api'] ) ? $st['api'] : array( 'skipped' => true );
	if ( empty( $api ) || ! empty( $api['skipped'] ) || empty( $api['configured'] ) ) {
		View::status_row(
			array(
				'title'      => __( '文档站接口', 'wpdocs' ),
				'detail'     => __( '未配置 Token，搜索与同步不可用', 'wpdocs' ),
				'icon'       => 'plug',
				'pill'       => __( '已跳过', 'wpdocs' ),
				'pill_class' => 'warn',
				'tile_class' => 'warn',
			)
		);
	} else {
		$api_ok  = ! empty( $api['ok'] );
		$api_ver = (string) ( $api['version'] ?? '' );
		$api_ms  = (int) ( $api['ms'] ?? 0 );
		$api_err = (string) ( $api['error'] ?? '' );
		$detail  = $api_ok
			? ( '' !== $api_ver ? sprintf( '%s · %dms', $api_ver, $api_ms ) : sprintf( '%dms', $api_ms ) )
			: ( '' !== $api_err ? sprintf( '%s · %dms', $api_err, $api_ms ) : sprintf( '%dms', $api_ms ) );
		View::status_row(
			array(
				'title'      => __( '文档站接口', 'wpdocs' ),
				'detail'     => $detail,
				'icon'       => 'plug',
				'pill'       => $api_ok ? __( '已连接', 'wpdocs' ) : __( '探测失败', 'wpdocs' ),
				'pill_class' => $api_ok ? 'ok' : 'bad',
				'tile_class' => $api_ok ? 'ok' : 'bad',
			)
		);
	}
	View::status_row(
		array(
			'title'      => __( '接管模式', 'wpdocs' ),
			'detail'     => $mode_txt,
			'icon'       => 'flash',
			'pill'       => Settings::MODE_OFF === $mode ? __( '未开启', 'wpdocs' ) : __( '已开启', 'wpdocs' ),
			'pill_class' => Settings::MODE_OFF === $mode ? 'warn' : 'ok',
			'tile_class' => Settings::MODE_OFF === $mode ? 'warn' : 'ok',
		)
	);
	View::status_row(
		array(
			'title'      => __( '绑定', 'wpdocs' ),
			'detail'     => (string) $bind_n,
			'icon'       => 'link',
			'pill'       => $bind_n > 0 ? __( '已有', 'wpdocs' ) : __( '空', 'wpdocs' ),
			'pill_class' => $bind_n > 0 ? 'ok' : 'warn',
			'tile_class' => $bind_n > 0 ? 'ok' : 'warn',
		)
	);
	View::status_row(
		array(
			'title'      => __( '跳转', 'wpdocs' ),
			'detail'     => (string) $redir_n,
			'icon'       => 'share',
			'pill'       => $redir_n > 0 ? __( '已有', 'wpdocs' ) : __( '空', 'wpdocs' ),
			'pill_class' => $redir_n > 0 ? 'ok' : 'warn',
			'tile_class' => $redir_n > 0 ? 'ok' : 'warn',
		)
	);
	?>
</section>
