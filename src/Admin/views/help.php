<?php
/**
 * In-plugin help (档 A：帮助不占第四 Tab).
 *
 * @package Wpdocs
 */

use Wpdocs\Admin\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

View::page_head(
	__( '帮助', 'wpdocs' ),
	__( '文派文档把品牌站文章绑到 BookStack，并处理旧路径跳转。', 'wpdocs' )
);
?>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '怎么开', 'wpdocs' ); ?></h2>
	<div class="field">
		<div>
			<div class="field-label"><?php esc_html_e( '1. 填文档站', 'wpdocs' ); ?></div>
			<div class="field-help"><?php esc_html_e( '在「状态」保存文档站地址，确认首页与站点地图探测为正常。', 'wpdocs' ); ?></div>
		</div>
		<div class="field-ctl"><a class="btn btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs' ) ); ?>"><?php esc_html_e( '打开状态', 'wpdocs' ); ?></a></div>
	</div>
	<div class="field">
		<div>
			<div class="field-label"><?php esc_html_e( '2. 建绑定', 'wpdocs' ); ?></div>
			<div class="field-help"><?php esc_html_e( '在「绑定」填写文章 ID 与文档书/页 slug 或 page id。批量可走 REST resolve-batch。', 'wpdocs' ); ?></div>
		</div>
		<div class="field-ctl"><a class="btn btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-bindings' ) ); ?>"><?php esc_html_e( '打开绑定', 'wpdocs' ); ?></a></div>
	</div>
	<div class="field">
		<div>
			<div class="field-label"><?php esc_html_e( '3. 配跳转', 'wpdocs' ); ?></div>
			<div class="field-help"><?php esc_html_e( '在「跳转」登记旧路径；matched 为 301，gone 为 410。启用时若存在旧 cocobook_redirects 表会一次性导入。', 'wpdocs' ); ?></div>
		</div>
		<div class="field-ctl"><a class="btn btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-redirects' ) ); ?>"><?php esc_html_e( '打开跳转', 'wpdocs' ); ?></a></div>
	</div>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '边界', 'wpdocs' ); ?></h2>
	<div class="simple-row is-static">
		<div class="tile"><?php echo View::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<div class="t"><?php esc_html_e( '本插件做什么', 'wpdocs' ); ?></div>
			<div class="d"><?php esc_html_e( '品牌站侧 Bind / Redirect，以及文档站只读探测。', 'wpdocs' ); ?></div>
		</div>
	</div>
	<div class="simple-row is-static">
		<div class="tile"><?php echo View::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<div class="t"><?php esc_html_e( '本插件不做什么', 'wpdocs' ); ?></div>
			<div class="d"><?php esc_html_e( '不改 BookStack 主题壳、不自建评论、不把正文写入 option。', 'wpdocs' ); ?></div>
		</div>
	</div>
</section>
