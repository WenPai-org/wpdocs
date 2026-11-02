<?php
/**
 * In-plugin help (档 A：帮助不占第四 Tab).
 *
 * Structure matches settings: `.card` + `.field` (label | field-ctl).
 *
 * @package Wpdocs
 */

use Wpdocs\Admin\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

View::page_head(
	__( '帮助', 'wpdocs' ),
	__( '文派文档把品牌站文章绑到文档站，并处理旧路径跳转。', 'wpdocs' )
);
?>
<div class="notice info"><span><?php esc_html_e( '连接、书目、试点模式、评论与缓存请到「设置」页配置；Token 也可写在 wp-config.php。', 'wpdocs' ); ?></span></div>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '怎么开', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '按顺序做完这三步即可日常使用。', 'wpdocs' ); ?></p>
	<?php
	View::field(
		array(
			'label'   => __( '填文档站', 'wpdocs' ),
			'control' => '<p class="hint d">' . esc_html__( '在「设置」填写文档站地址与 Token，再到「状态」确认探测为正常。', 'wpdocs' ) . '</p>'
				. '<div class="wenpai-actions"><a class="btn btn-ghost" href="' . esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ) . '">' . esc_html__( '打开设置', 'wpdocs' ) . '</a></div>',
		)
	);
	View::field(
		array(
			'label'   => __( '建绑定', 'wpdocs' ),
			'control' => '<p class="hint d">' . esc_html__( '在「绑定」填写文章 ID 与文档书册/页面别名。简单模式够用；高级可补文档页 ID、外部 ID 与来源。', 'wpdocs' ) . '</p>'
				. '<div class="wenpai-actions"><a class="btn btn-ghost" href="' . esc_url( admin_url( 'admin.php?page=wpdocs-bindings' ) ) . '">' . esc_html__( '打开绑定', 'wpdocs' ) . '</a></div>',
		)
	);
	View::field(
		array(
			'label'   => __( '配跳转', 'wpdocs' ),
			'control' => '<p class="hint d">' . esc_html__( '在「跳转」登记旧路径；已匹配为 301，已下线为 410。启用时若存在旧跳转表会一次性导入。', 'wpdocs' ) . '</p>'
				. '<div class="wenpai-actions"><a class="btn btn-ghost" href="' . esc_url( admin_url( 'admin.php?page=wpdocs-redirects' ) ) . '">' . esc_html__( '打开跳转', 'wpdocs' ) . '</a></div>',
		)
	);
	?>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '简单与高级', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '页头「简单 | 高级」只控制显示深度，不改能力开关。', 'wpdocs' ); ?></p>
	<?php
	View::field(
		array(
			'label'   => __( '简单', 'wpdocs' ),
			'control' => '<p class="hint d">' . esc_html__( '绑定：文章 ID、类型、书册/页面别名。跳转：旧路径、文档页 ID、状态。', 'wpdocs' ) . '</p>',
		)
	);
	View::field(
		array(
			'label'   => __( '高级', 'wpdocs' ),
			'control' => '<p class="hint d">' . esc_html__( '绑定：文档页 ID、外部 ID、来源、状态、按 ID 反查、搜索文档页。跳转：原标题、试解析。未显示的高级字段保存时仍按当前值提交。', 'wpdocs' ) . '</p>',
		)
	);
	?>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '删除会清什么', 'wpdocs' ); ?></h2>
	<?php
	View::field(
		array(
			'label'   => __( '绑定行', 'wpdocs' ),
			'control' => '<p class="hint d">' . esc_html__( '勾选确认门后删除，只去掉该文章与文档页的对应关系，不改文章正文，也不改文档站。', 'wpdocs' ) . '</p>',
		)
	);
	View::field(
		array(
			'label'   => __( '跳转行', 'wpdocs' ),
			'control' => '<p class="hint d">' . esc_html__( '勾选确认门后删除，只去掉该旧路径映射。已匹配的访问会回到未拦截。', 'wpdocs' ) . '</p>',
		)
	);
	?>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '边界', 'wpdocs' ); ?></h2>
	<div class="simple-row is-static">
		<div class="tile accent"><?php echo View::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<div class="t"><?php esc_html_e( '本插件做什么', 'wpdocs' ); ?></div>
			<div class="d"><?php esc_html_e( '品牌站侧绑定与跳转，以及文档站只读探测。', 'wpdocs' ); ?></div>
		</div>
	</div>
	<div class="simple-row is-static">
		<div class="tile"><?php echo View::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<div class="t"><?php esc_html_e( '本插件不做什么', 'wpdocs' ); ?></div>
			<div class="d"><?php esc_html_e( '不改文档站主题壳、不自建评论、不把正文写入站点选项。', 'wpdocs' ); ?></div>
		</div>
	</div>
</section>
