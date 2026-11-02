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
	__( '先选「内容从哪来」，再绑文章、处理旧链。', 'wpdocs' )
);
?>
<div class="notice info"><span><?php esc_html_e( '主选择、连接与旁路能力在「设置」；绑定 / 跳转 / 同步在「工具」。Token 也可写在 wp-config.php。', 'wpdocs' ); ?></span></div>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '主选择（内容从哪来）', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '三选一；只走一条路径。', 'wpdocs' ); ?></p>
	<?php
	View::field(
		array(
			'label'   => __( '保留正文', 'wpdocs' ),
			'control' => '<p class="hint d">' . esc_html__( '品牌站自己管正文。可选到「工具 → 同步」把已绑定的 CocoBook 页一次性写入文章。', 'wpdocs' ) . '</p>',
		)
	);
	View::field(
		array(
			'label'   => __( '文档站读取', 'wpdocs' ),
			'control' => '<p class="hint d">' . esc_html__( '打开文章时从 CocoBook 读正文（不改正文库）。统一评论只在此路径提供；读取范围用试点/全部。', 'wpdocs' ) . '</p>',
		)
	);
	View::field(
		array(
			'label'   => __( 'Git 写入', 'wpdocs' ),
			'control' => '<p class="hint d">' . esc_html__( '从 Forgejo（或本地目录）把 Markdown 导入并写入文章。拉取在「工具 → 同步」。', 'wpdocs' ) . '</p>',
		)
	);
	?>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '怎么开', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '按顺序做完即可日常使用。', 'wpdocs' ); ?></p>
	<?php
	View::field(
		array(
			'label'   => __( '选路径并连接', 'wpdocs' ),
			'control' => '<p class="hint d">' . esc_html__( '在「设置」先选内容从哪来，再填对应连接（CocoBook 或 Forgejo）。选「文档站读取」时再到「概览」确认探测正常。', 'wpdocs' ) . '</p>'
				. '<div class="wenpai-actions"><a class="btn btn-ghost" href="' . esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ) . '">' . esc_html__( '打开设置', 'wpdocs' ) . '</a></div>',
		)
	);
	View::field(
		array(
			'label'   => __( '建绑定', 'wpdocs' ),
			'control' => '<p class="hint d">' . esc_html__( '在「工具 → 绑定」填写文章 ID 与文档书册/页面别名。简单模式够用；高级可补文档页 ID、外部 ID 与来源。', 'wpdocs' ) . '</p>'
				. '<div class="wenpai-actions"><a class="btn btn-ghost" href="' . esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=bindings' ) ) . '">' . esc_html__( '打开绑定', 'wpdocs' ) . '</a></div>',
		)
	);
	View::field(
		array(
			'label'   => __( '配跳转', 'wpdocs' ),
			'control' => '<p class="hint d">' . esc_html__( '在「工具 → 跳转」登记旧路径；已匹配为 301，已下线为 410。启用时若存在旧跳转表会一次性导入。', 'wpdocs' ) . '</p>'
				. '<div class="wenpai-actions"><a class="btn btn-ghost" href="' . esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=redirects' ) ) . '">' . esc_html__( '打开跳转', 'wpdocs' ) . '</a></div>',
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
			<div class="d"><?php esc_html_e( '内容源切换、绑定、旧链跳转、CocoBook 只读探测，以及按源导入/拉取。', 'wpdocs' ); ?></div>
		</div>
	</div>
	<div class="simple-row is-static">
		<div class="tile"><?php echo View::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<div class="t"><?php esc_html_e( '本插件不做什么', 'wpdocs' ); ?></div>
			<div class="d"><?php esc_html_e( '不改 CocoBook 主题壳；不在「保留正文 / Git 写入」提供统一评论；不把正文写入站点选项。', 'wpdocs' ); ?></div>
		</div>
	</div>
</section>
