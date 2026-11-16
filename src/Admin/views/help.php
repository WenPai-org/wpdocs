<?php
/**
 * In-plugin help (档 A：帮助不占第四 Tab).
 *
 * Kit 0.7 help_page skeleton: .wenpai-help groups of numbered/dot steps.
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
	<?php
	echo Wenpai_Admin_UI::help_html(
		array(
			'groups' => array(
				array(
					'title'    => __( '主选择（内容从哪来）', 'wpdocs' ),
					'numbered' => false,
					'steps'    => array(
						array(
							'title' => __( '保留正文', 'wpdocs' ),
							'body'  => __( '品牌站自己管正文。可选到「工具 → 同步」把已绑定的 CocoBook 页一次性写入文章。', 'wpdocs' ),
						),
						array(
							'title' => __( '文档站读取', 'wpdocs' ),
							'body'  => __( '打开文章时从 CocoBook 读正文（不改正文库）。统一评论只在此路径提供；读取范围用试点/全部。', 'wpdocs' ),
						),
						array(
							'title' => __( 'Git 写入', 'wpdocs' ),
							'body'  => __( '从 Forgejo（或本地目录）把 Markdown 导入并写入文章。拉取在「工具 → 同步」。', 'wpdocs' ),
						),
					),
				),
				array(
					'title'    => __( '怎么开', 'wpdocs' ),
					'numbered' => true,
					'steps'    => array(
						array(
							'title'     => __( '选路径并连接', 'wpdocs' ),
							'body_html' => '<p>' . esc_html__( '在「设置」先选内容从哪来，再填对应连接（CocoBook 或 Forgejo）。选「文档站读取」时再到「概览」确认探测正常。', 'wpdocs' ) . '</p>'
								. '<p><a href="' . esc_url( \Wpdocs\Admin\Menu::url( 'settings' ) ) . '">' . esc_html__( '打开设置', 'wpdocs' ) . '</a></p>',
						),
						array(
							'title'     => __( '建绑定', 'wpdocs' ),
							'body_html' => '<p>' . esc_html__( '在「工具 → 绑定」填写文章 ID 与文档书册/页面别名。简单模式够用；高级可补文档页 ID、外部 ID 与来源。', 'wpdocs' ) . '</p>'
								. '<p><a href="' . esc_url( \Wpdocs\Admin\Menu::url( 'tools', 'bindings' ) ) . '">' . esc_html__( '打开绑定', 'wpdocs' ) . '</a></p>',
						),
						array(
							'title'     => __( '配跳转', 'wpdocs' ),
							'body_html' => '<p>' . esc_html__( '在「工具 → 跳转」登记旧路径；已匹配为 301，已下线为 410。启用时若存在旧跳转表会一次性导入。', 'wpdocs' ) . '</p>'
								. '<p><a href="' . esc_url( \Wpdocs\Admin\Menu::url( 'tools', 'redirects' ) ) . '">' . esc_html__( '打开跳转', 'wpdocs' ) . '</a></p>',
						),
					),
				),
				array(
					'title'    => __( '简单与高级', 'wpdocs' ),
					'numbered' => false,
					'steps'    => array(
						array(
							'title' => __( '简单', 'wpdocs' ),
							'body'  => __( '绑定：文章 ID、类型、书册/页面别名。跳转：旧路径、文档页 ID、状态。', 'wpdocs' ),
						),
						array(
							'title' => __( '高级', 'wpdocs' ),
							'body'  => __( '绑定：文档页 ID、外部 ID、来源、状态、按 ID 反查、搜索文档页。跳转：原标题、试解析。未显示的高级字段保存时仍按当前值提交。', 'wpdocs' ),
						),
					),
				),
				array(
					'title'    => __( '删除会清什么', 'wpdocs' ),
					'numbered' => false,
					'steps'    => array(
						array(
							'title' => __( '绑定行', 'wpdocs' ),
							'body'  => __( '勾选确认门后删除，只去掉该文章与文档页的对应关系，不改文章正文，也不改文档站。', 'wpdocs' ),
						),
						array(
							'title' => __( '跳转行', 'wpdocs' ),
							'body'  => __( '勾选确认门后删除，只去掉该旧路径映射。已匹配的访问会回到未拦截。', 'wpdocs' ),
						),
					),
				),
				array(
					'title'    => __( '边界', 'wpdocs' ),
					'numbered' => false,
					'steps'    => array(
						array(
							'title' => __( '本插件做什么', 'wpdocs' ),
							'body'  => __( '内容源切换、绑定、旧链跳转、CocoBook 只读探测，以及按源导入/拉取。', 'wpdocs' ),
						),
						array(
							'title' => __( '分工边界', 'wpdocs' ),
							'body'  => __( '主题壳、正文存储和导出由 CocoBook 负责；正文保留在文档库，不写入站点选项；评论沿用各写入方式原有的评论区。', 'wpdocs' ),
						),
					),
				),
				array(
					'title'    => __( '运维与 REST', 'wpdocs' ),
					'numbered' => false,
					'steps'    => array(
						array(
							'title' => __( '导出', 'wpdocs' ),
							'body'  => __( '整书/单页 ZIP、Markdown、PDF 在 CocoBook 后台导出。', 'wpdocs' ),
						),
						array(
							'title' => __( 'Webhook', 'wpdocs' ),
							'body'  => __( '工具 → 同步里复制 CocoBook Webhook URL，在文档站挂 page 事件。会刷新索引与绑定「远端」探测。', 'wpdocs' ),
						),
						array(
							'title'     => __( 'REST', 'wpdocs' ),
							'body_html' => '<p>' . esc_html__( '命名空间 /wpdocs/v1，需 manage_options（Application Password）。status / bindings / redirects / search / suggest；refresh 用路径密钥不用 App Password。', 'wpdocs' ) . '</p><p><code>docs/ops/wpdocs-ops.md</code></p>',
						),
					),
				),
			),
		)
	); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped.
	?>
</section>
