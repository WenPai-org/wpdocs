<?php
/**
 * Settings — 档 A：简单默认；高级折叠细节。
 *
 * @package Wpdocs
 *
 * @var array<string,mixed> $s
 * @var array<string,mixed>|\WP_Error|null $books
 */

use Wpdocs\Admin\View;
use Wpdocs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$adv = View::is_advanced();
ob_start();
View::mode_toggle( 'wpdocs-settings' );
$mode_html = ob_get_clean();

View::page_head(
	__( '设置', 'wpdocs' ),
	__( '连接文档站、选择同步书目，并控制文章接管与评论。', 'wpdocs' ),
	$mode_html
);
View::saved_notice();
View::error_notice();

$token_locked = Settings::is_locked( 'token_secret' );
$token_saved  = '' !== (string) ( $s['token_secret'] ?? '' );
$secret_ctl   = '';
if ( $token_locked ) {
	$secret_ctl = '<code>' . esc_html__( '已由 wp-config.php 常量设置', 'wpdocs' ) . '</code>';
} else {
	$ph = $token_saved ? esc_attr__( '已保存（留空则不修改）', 'wpdocs' ) : '';
	$secret_ctl = '<input id="wpdocs-token-secret" type="password" name="wpdocs[token_secret]" value="" autocomplete="new-password" placeholder="' . $ph . '" />';
	if ( $token_saved ) {
		$secret_ctl .= '<label class="chk" style="display:block;margin-top:8px"><input type="checkbox" name="wpdocs[token_secret_clear]" value="1" /> ' . esc_html__( '清除已保存的 Secret', 'wpdocs' ) . '</label>';
	}
}

$tid_ctl = Settings::is_locked( 'token_id' )
	? '<code>' . esc_html( (string) Settings::get( 'token_id' ) ) . '</code>'
	: '<input id="wpdocs-token-id" type="text" name="wpdocs[token_id]" value="' . esc_attr( (string) ( $s['token_id'] ?? '' ) ) . '" autocomplete="off" />';

$base_ctl = Settings::is_locked( 'base_url' )
	? '<code>' . esc_html( Settings::base_url() ) . '</code>'
	: '<input id="wpdocs-base-url" type="url" name="wpdocs[base_url]" value="' . esc_attr( (string) ( $s['base_url'] ?? '' ) ) . '" placeholder="https://docs.example.test" />';

$tree_in  = (string) ( $s['tree_url'] ?? '' );
$books_ctl = '';
if ( is_array( $books ) && ! is_wp_error( $books ) && ! empty( $books['data'] ) ) {
	$books_ctl .= '<div class="chk-grid wpdocs-books">';
	$books_ctl .= '<input type="hidden" name="wpdocs[books][]" value="" />';
	$picked     = Settings::books();
	foreach ( $books['data'] as $b ) {
		$bid = (int) ( $b['id'] ?? 0 );
		if ( $bid < 1 ) {
			continue;
		}
		$books_ctl .= '<label><input type="checkbox" name="wpdocs[books][]" value="' . esc_attr( (string) $bid ) . '" ' . checked( in_array( $bid, $picked, true ), true, false ) . ' /> '
			. esc_html( (string) ( $b['name'] ?? '' ) . ' (#' . $bid . ')' ) . '</label>';
	}
	$books_ctl .= '</div>';
} else {
	$books_ctl = '<p class="hint">' . esc_html__( '保存连接后，这里会列出可选的书。也可只填站点标签自动选书。', 'wpdocs' ) . '</p>';
	foreach ( Settings::books() as $bid ) {
		$books_ctl .= '<input type="hidden" name="wpdocs[books][]" value="' . esc_attr( (string) $bid ) . '" />';
	}
}

$mode     = Settings::mode();
$mode_ctl = View::choice_cards(
	'wpdocs[mode]',
	$mode,
	array(
		Settings::MODE_OFF   => array(
			'label' => __( '关闭', 'wpdocs' ),
			'desc'  => __( '不接管任何文章正文。', 'wpdocs' ),
			'icon'  => 'info',
		),
		Settings::MODE_PILOT => array(
			'label' => __( '试点', 'wpdocs' ),
			'desc'  => __( '只接管下方填写的文章 ID。', 'wpdocs' ),
			'icon'  => 'flash',
		),
		Settings::MODE_ALL   => array(
			'label' => __( '全部', 'wpdocs' ),
			'desc'  => __( '接管所有能对应到文档页的文章。', 'wpdocs' ),
			'icon'  => 'link',
		),
	)
);

$comments_on  = ! empty( $s['comments'] );
$redirects_on = ! empty( $s['redirects'] );
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ); ?>" class="wpdocs-settings-form" id="wpdocs-settings-form">
	<?php wp_nonce_field( 'wpdocs_settings_full', 'wpdocs_settings_full_nonce' ); ?>
	<?php if ( $adv ) : ?>
		<input type="hidden" name="wpdocs_mode" value="advanced" />
	<?php endif; ?>

	<section class="card">
		<h2 class="section-title"><?php esc_html_e( '连接与书目', 'wpdocs' ); ?></h2>
		<p class="section-desc"><?php esc_html_e( '先填地址与 Token，再选书目。缓存与发布树在高级。', 'wpdocs' ); ?></p>
		<?php
		View::field(
			array(
				'for'     => 'wpdocs-base-url',
				'label'   => __( '文档站地址', 'wpdocs' ),
				'help'    => __( 'BookStack / CocoBook 根地址，不要尾斜杠。', 'wpdocs' ),
				'control' => $base_ctl,
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-tree-url',
				'label'    => __( '发布树地址', 'wpdocs' ),
				'help'     => __( '留空则用「文档站/books」；保存为空字符串则跳过发布树探测。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-tree-url" type="url" name="wpdocs[tree_url]" value="' . esc_attr( $tree_in ) . '" />',
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-token-id',
				'label'   => __( 'Token ID', 'wpdocs' ),
				'help'    => __( '文档站 API 只读 Token。也可在 wp-config.php 定义常量。', 'wpdocs' ),
				'control' => $tid_ctl,
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-token-secret',
				'label'   => __( 'Token Secret', 'wpdocs' ),
				'help'    => __( '密钥不回显；留空表示不修改。', 'wpdocs' ),
				'control' => $secret_ctl,
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-site-tag',
				'label'   => __( '站点标签', 'wpdocs' ),
				'help'    => __( '未勾选具体书目时，自动使用带此 site 标签的书。', 'wpdocs' ),
				'control' => '<input id="wpdocs-site-tag" type="text" name="wpdocs[site_tag]" value="' . esc_attr( (string) ( $s['site_tag'] ?? '' ) ) . '" />',
			)
		);
		View::field(
			array(
				'label'   => __( '同步的书', 'wpdocs' ),
				'control' => $books_ctl,
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-post-types',
				'label'    => __( '文章类型', 'wpdocs' ),
				'help'     => __( '逗号分隔，默认 post。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-post-types" type="text" name="wpdocs[post_types]" value="' . esc_attr( implode( ',', (array) ( $s['post_types'] ?? array( 'post' ) ) ) ) . '" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-ttl',
				'label'    => __( '缓存过期秒数', 'wpdocs' ),
				'help'     => __( '填 0 表示不过期（推荐）。正文缓存在文件，不进选项表。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-ttl" type="number" min="0" name="wpdocs[page_ttl]" value="' . esc_attr( (string) ( $s['page_ttl'] ?? 0 ) ) . '" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-minc',
				'label'    => __( '旧评论迁移阈值', 'wpdocs' ),
				'help'     => __( '迁移旧评论时，只迁移不少于这么多字的评论。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-minc" type="number" min="1" max="1000" name="wpdocs[comment_min_chars]" value="' . esc_attr( (string) ( $s['comment_min_chars'] ?? 30 ) ) . '" />',
			)
		);
		?>
		<div class="wenpai-actions">
			<button type="submit" class="btn btn-primary"><?php esc_html_e( '保存设置', 'wpdocs' ); ?></button>
			<a class="btn btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=sync' ) ); ?>"><?php esc_html_e( '去同步工具', 'wpdocs' ); ?></a>
		</div>
	</section>

	<section class="card">
		<h2 class="section-title"><?php esc_html_e( '上线方式', 'wpdocs' ); ?></h2>
		<p class="section-desc"><?php esc_html_e( '选择文档正文如何出现在品牌站。', 'wpdocs' ); ?></p>
		<?php
		View::field(
			array(
				'label'   => __( '模式', 'wpdocs' ),
				'help'    => __( '试点适合先验证几篇；确认无误再开全部。', 'wpdocs' ),
				'control' => $mode_ctl,
			)
		);
		?>
		<div class="field" data-wpdocs-when-mode="pilot"<?php echo Settings::MODE_PILOT === $mode ? '' : ' hidden'; ?>>
			<label class="field-label" for="wpdocs-pilot"><?php esc_html_e( '试点文章 ID', 'wpdocs' ); ?></label>
			<div class="field-ctl">
				<input id="wpdocs-pilot" type="text" name="wpdocs[pilot_post_ids]" value="<?php echo esc_attr( implode( ', ', Settings::pilot_post_ids() ) ); ?>" />
				<p class="hint"><?php esc_html_e( '逗号分隔的 WordPress 文章 ID。', 'wpdocs' ); ?></p>
			</div>
		</div>
		<div class="simple-row is-action">
			<div class="tile"><?php echo View::icon( 'feedback' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div>
				<div class="t"><?php esc_html_e( '统一评论', 'wpdocs' ); ?></div>
				<div class="d"><?php esc_html_e( '评论挂到 cocobook:page:{id}，与文档站、论坛同一帖。', 'wpdocs' ); ?></div>
			</div>
			<div class="r"><?php echo View::toggle_input( 'wpdocs[comments]', $comments_on ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<div class="field" data-wpdocs-when-on="comments"<?php echo $comments_on ? '' : ' hidden'; ?>>
			<label class="field-label" for="wpdocs-bff"><?php esc_html_e( '评论服务地址', 'wpdocs' ); ?></label>
			<div class="field-ctl">
				<input id="wpdocs-bff" type="url" name="wpdocs[bff_url]" value="<?php echo esc_attr( (string) ( $s['bff_url'] ?? '' ) ); ?>" />
				<p class="hint"><?php esc_html_e( '评论 BFF 根地址。关掉「统一评论」时仍会保存。', 'wpdocs' ); ?></p>
			</div>
		</div>
		<div class="simple-row is-action">
			<div class="tile"><?php echo View::icon( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div>
				<div class="t"><?php esc_html_e( '旧链接跳转', 'wpdocs' ); ?></div>
				<div class="d"><?php esc_html_e( '映射表已匹配则 301；已下线则 410。', 'wpdocs' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=redirects' ) ); ?>"><?php esc_html_e( '管理映射', 'wpdocs' ); ?></a>
				</div>
			</div>
			<div class="r"><?php echo View::toggle_input( 'wpdocs[redirects]', $redirects_on ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
	</section>
</form>
