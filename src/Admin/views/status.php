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
$content   = Settings::content_mode();
$content_lab = array(
	Settings::CONTENT_KEEP     => __( '保留正文', 'wpdocs' ),
	Settings::CONTENT_COCOBOOK => __( '文档站读取', 'wpdocs' ),
	Settings::CONTENT_GIT      => __( 'Git 写入', 'wpdocs' ),
);
$mode      = Settings::mode();
$mode_lab  = array(
	Settings::MODE_OFF   => __( '关闭', 'wpdocs' ),
	Settings::MODE_PILOT => __( '试点', 'wpdocs' ),
	Settings::MODE_ALL   => __( '全部', 'wpdocs' ),
);
$mode_txt = ( $content_lab[ $content ] ?? $content );
if ( Settings::CONTENT_COCOBOOK === $content ) {
	$mode_txt .= ' · ' . ( $mode_lab[ $mode ] ?? $mode );
}

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
	__( '看现在怎样：内容从哪来、CocoBook 探测、绑定与跳转。配置去设置；动作去工具。', 'wpdocs' ),
	$refresh
);
View::saved_notice();
View::error_notice();

$configured   = Settings::is_configured() || ( '' !== $base_show );
$empty_maps   = ( 0 === $bind_n && 0 === $redir_n );
$probe_failed = ( '' !== $base_show && ! $home_ok && ! ( $site_ok || ( ! $tree_skip && $tree_ok ) ) );
$show_next    = false;
$git_url      = (string) Settings::get( 'git_forgejo_url' );
$git_repos    = Settings::git_repositories();
$git_has_repo = false;
foreach ( $git_repos as $gr ) {
	if ( is_array( $gr ) && '' !== (string) ( $gr['username'] ?? '' ) && '' !== (string) ( $gr['repository'] ?? '' ) ) {
		$git_has_repo = true;
		break;
	}
}

if ( Settings::CONTENT_GIT === $content && '' === $git_url ) :
	$show_next = true;
	?>
	<div class="next">
		<div class="tile accent"><?php echo View::icon( 'settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<strong><?php esc_html_e( '还没配 Forgejo', 'wpdocs' ); ?></strong>
			<p class="d"><?php esc_html_e( '当前是 Git 写入。先在设置填写 Forgejo 地址、Token 与仓库。', 'wpdocs' ); ?></p>
		</div>
		<a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ); ?>"><?php esc_html_e( '去设置', 'wpdocs' ); ?></a>
	</div>
	<?php
elseif ( Settings::CONTENT_GIT === $content && ! $git_has_repo && '' === (string) Settings::get( 'git_base_directory' ) ) :
	$show_next = true;
	?>
	<div class="next">
		<div class="tile accent"><?php echo View::icon( 'settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<strong><?php esc_html_e( '还没填仓库', 'wpdocs' ); ?></strong>
			<p class="d"><?php esc_html_e( '填写 owner/name，再到工具 → 同步拉取。', 'wpdocs' ); ?></p>
		</div>
		<a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ); ?>"><?php esc_html_e( '去设置', 'wpdocs' ); ?></a>
	</div>
	<?php
elseif ( Settings::CONTENT_KEEP === $content && $empty_maps ) :
	$show_next = true;
	?>
	<div class="next">
		<div class="tile accent"><?php echo View::icon( 'link' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<strong><?php esc_html_e( '还没有绑定或跳转', 'wpdocs' ); ?></strong>
			<p class="d"><?php esc_html_e( '保留正文时，绑定用于一次性导入；跳转处理旧路径。', 'wpdocs' ); ?></p>
		</div>
		<a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=bindings' ) ); ?>"><?php esc_html_e( '去工具', 'wpdocs' ); ?></a>
	</div>
	<?php
elseif ( Settings::CONTENT_COCOBOOK === $content && ! $configured ) :
	$show_next = true;
	?>
	<div class="next">
		<div class="tile accent"><?php echo View::icon( 'settings' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<strong><?php esc_html_e( '还没连上 CocoBook', 'wpdocs' ); ?></strong>
			<p class="d"><?php esc_html_e( '当前是文档站读取。先在设置填写地址与 Token。', 'wpdocs' ); ?></p>
		</div>
		<a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ); ?>"><?php esc_html_e( '去设置', 'wpdocs' ); ?></a>
	</div>
	<?php
elseif ( Settings::CONTENT_COCOBOOK === $content && $probe_failed ) :
	$show_next = true;
	?>
	<div class="next">
		<div class="tile bad"><?php echo View::icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<strong><?php esc_html_e( '暂时无法探测 CocoBook', 'wpdocs' ); ?></strong>
			<p class="d"><?php esc_html_e( '请检查地址后重试。', 'wpdocs' ); ?></p>
		</div>
		<a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs&refresh=1' ) ); ?>"><?php esc_html_e( '重新探测', 'wpdocs' ); ?></a>
	</div>
	<?php
elseif ( Settings::CONTENT_COCOBOOK === $content && $empty_maps ) :
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
elseif ( Settings::CONTENT_COCOBOOK === $content && ! Settings::is_configured() && '' !== $base_show ) :
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

$drift = \Wpdocs\Bind\DriftCheck::last();
$drift_bad = (int) ( $drift['broken'] ?? 0 ) + (int) ( $drift['stale'] ?? 0 ) + (int) ( $drift['forbidden'] ?? 0 );
if ( $drift_bad > 0 ) :
	?>
	<div class="notice warn"><span><?php
		echo esc_html(
			sprintf(
				/* translators: 1: broken, 2: stale, 3: forbidden */
				__( '绑定抽检：%1$d 失效、%2$d 别名已变、%3$d 无权限。到工具 → 绑定查看「远端」列。', 'wpdocs' ),
				(int) ( $drift['broken'] ?? 0 ),
				(int) ( $drift['stale'] ?? 0 ),
				(int) ( $drift['forbidden'] ?? 0 )
			)
		);
	?></span>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=bindings' ) ); ?>"><?php esc_html_e( '打开绑定', 'wpdocs' ); ?></a>
	</div>
	<?php
endif;
?>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '现在怎样', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php echo esc_html( sprintf( __( '版本 %s。发布树只读，不写 CocoBook。', 'wpdocs' ), WPDOCS_VERSION ) ); ?></p>
	<?php
	View::status_row(
		array(
			'title'      => __( 'CocoBook', 'wpdocs' ),
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
				'title'      => __( 'CocoBook 接口', 'wpdocs' ),
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
				'title'      => __( 'CocoBook 接口', 'wpdocs' ),
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
			'title'      => __( '内容从哪来', 'wpdocs' ),
			'detail'     => $mode_txt,
			'icon'       => 'flash',
			'pill'       => Settings::CONTENT_KEEP === $content ? __( '不换正文', 'wpdocs' ) : __( '已选用', 'wpdocs' ),
			'pill_class' => Settings::CONTENT_KEEP === $content ? 'warn' : 'ok',
			'tile_class' => Settings::CONTENT_KEEP === $content ? 'warn' : 'ok',
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
	$mx = \Wpdocs\Metrics::snapshot( $st );
	$cov = isset( $mx['bindings'] ) && is_array( $mx['bindings'] ) ? $mx['bindings'] : array();
	$ratio = $cov['ratio'] ?? null;
	$cov_detail = sprintf(
		'%d / %d',
		(int) ( $cov['active'] ?? 0 ),
		(int) ( $cov['eligible'] ?? 0 )
	);
	if ( null !== $ratio ) {
		$cov_detail .= ' · ' . (string) round( (float) $ratio * 100, 1 ) . '%';
	}
	View::status_row(
		array(
			'title'      => __( '绑定覆盖', 'wpdocs' ),
			'detail'     => $cov_detail,
			'icon'       => 'link',
			'pill'       => null !== $ratio && (float) $ratio >= 0.5 ? __( '过半', 'wpdocs' ) : __( '偏低', 'wpdocs' ),
			'pill_class' => null !== $ratio && (float) $ratio >= 0.5 ? 'ok' : 'warn',
			'tile_class' => null !== $ratio && (float) $ratio >= 0.5 ? 'ok' : 'warn',
		)
	);
	$unm = (int) ( $mx['redirects']['unmatched'] ?? 0 );
	$hist_n = (int) ( $mx['history']['days'] ?? 0 );
	View::status_row(
		array(
			'title'      => __( '未匹配 404', 'wpdocs' ),
			'detail'     => $hist_n > 0
				? sprintf(
					/* translators: 1: unmatched count, 2: history days */
					__( '%1$d 条 · 已记 %2$d 日', 'wpdocs' ),
					$unm,
					$hist_n
				)
				: (string) $unm,
			'icon'       => 'share',
			'pill'       => $unm > 0 ? __( '待处理', 'wpdocs' ) : __( '干净', 'wpdocs' ),
			'pill_class' => $unm > 0 ? 'warn' : 'ok',
			'tile_class' => $unm > 0 ? 'warn' : 'ok',
		)
	);
	$fail_r = $mx['probe']['fail_rate_14d'] ?? null;
	$samp   = (int) ( $mx['probe']['samples'] ?? 0 );
	$fail_detail = $samp > 0 && null !== $fail_r
		? sprintf(
			/* translators: 1: percent, 2: sample days */
			__( '%1$s%% · %2$d 日样本', 'wpdocs' ),
			(string) round( (float) $fail_r * 100, 1 ),
			$samp
		)
		: __( '尚无日检样本', 'wpdocs' );
	View::status_row(
		array(
			'title'      => __( '探测失败率', 'wpdocs' ),
			'detail'     => $fail_detail,
			'icon'       => 'server',
			'pill'       => null !== $fail_r && (float) $fail_r > 0.2 ? __( '偏高', 'wpdocs' ) : ( $samp > 0 ? __( '正常', 'wpdocs' ) : __( '等待', 'wpdocs' ) ),
			'pill_class' => null !== $fail_r && (float) $fail_r > 0.2 ? 'bad' : ( $samp > 0 ? 'ok' : 'warn' ),
			'tile_class' => null !== $fail_r && (float) $fail_r > 0.2 ? 'bad' : ( $samp > 0 ? 'ok' : 'warn' ),
		)
	);
	?>
</section>
