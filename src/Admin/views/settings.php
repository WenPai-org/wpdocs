<?php
/**
 * Settings — connection, books, rollout, comments, cache/sync.
 *
 * @package Wpdocs
 *
 * @var array<string,mixed> $s
 * @var array<string,mixed>|\WP_Error|null $books
 * @var array<string,mixed>|\WP_Error|null $cat
 * @var array<int,array<string,mixed>> $log
 */

use Wpdocs\Admin\View;
use Wpdocs\Client;
use Wpdocs\Core;
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

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$msg = isset( $_GET['msg'] ) ? sanitize_key( (string) wp_unslash( $_GET['msg'] ) ) : '';
$notices = array(
	'test_ok'    => array( 'ok', __( '连接正常。', 'wpdocs' ) ),
	'test_error' => array( 'bad', __( '连接失败，请查看下方日志。', 'wpdocs' ) ),
	'synced'     => array( 'ok', __( '同步完成。', 'wpdocs' ) ),
	'sync_error' => array( 'bad', __( '同步失败，请查看下方日志。', 'wpdocs' ) ),
);
if ( isset( $notices[ $msg ] ) ) {
	echo '<div class="notice ' . esc_attr( $notices[ $msg ][0] ) . '"><span>' . esc_html( $notices[ $msg ][1] ) . '</span></div>';
}

$token_locked = Settings::is_locked( 'token_secret' );
$token_saved  = '' !== (string) ( $s['token_secret'] ?? '' );
$secret_ctl   = '';
if ( $token_locked ) {
	$secret_ctl = '<code>' . esc_html__( '已由 wp-config.php 常量设置', 'wpdocs' ) . '</code>';
} else {
	$ph = $token_saved ? esc_attr__( '已保存（留空则不修改）', 'wpdocs' ) : '';
	$secret_ctl  = '<input id="wpdocs-token-secret" type="password" name="wpdocs[token_secret]" value="" autocomplete="new-password" placeholder="' . $ph . '" />';
	if ( $token_saved ) {
		$secret_ctl .= '<label class="hint" style="display:block;margin-top:8px"><input type="checkbox" name="wpdocs[token_secret_clear]" value="1" /> ' . esc_html__( '清除已保存的 Secret', 'wpdocs' ) . '</label>';
	}
}

$tid_ctl = Settings::is_locked( 'token_id' )
	? '<code>' . esc_html( (string) Settings::get( 'token_id' ) ) . '</code>'
	: '<input id="wpdocs-token-id" type="text" name="wpdocs[token_id]" value="' . esc_attr( (string) ( $s['token_id'] ?? '' ) ) . '" autocomplete="off" />';

$base_ctl = Settings::is_locked( 'base_url' )
	? '<code>' . esc_html( Settings::base_url() ) . '</code>'
	: '<input id="wpdocs-base-url" type="url" name="wpdocs[base_url]" value="' . esc_attr( (string) ( $s['base_url'] ?? '' ) ) . '" placeholder="https://docs.example.test" />';

$books_ctl = '';
if ( is_array( $books ) && ! is_wp_error( $books ) && ! empty( $books['data'] ) ) {
	$books_ctl .= '<fieldset class="wpdocs-books" style="max-height:16em;overflow:auto;border:1px solid var(--wenpai-border, #dcdcde);padding:.5em 1em;max-width:36em">';
	$picked = Settings::books();
	foreach ( $books['data'] as $b ) {
		$bid = (int) ( $b['id'] ?? 0 );
		if ( $bid < 1 ) {
			continue;
		}
		$books_ctl .= '<label style="display:block"><input type="checkbox" name="wpdocs[books][]" value="' . esc_attr( (string) $bid ) . '" ' . checked( in_array( $bid, $picked, true ), true, false ) . ' /> '
			. esc_html( (string) ( $b['name'] ?? '' ) . ' (#' . $bid . ')' ) . '</label>';
	}
	$books_ctl .= '</fieldset>';
} else {
	$books_ctl = '<p class="hint">' . esc_html__( '保存连接并测试通过后，这里会列出可选的书。', 'wpdocs' ) . '</p>';
	foreach ( Settings::books() as $bid ) {
		$books_ctl .= '<input type="hidden" name="wpdocs[books][]" value="' . esc_attr( (string) $bid ) . '" />';
	}
}

$mode = Settings::mode();
$mode_ctl  = '';
$modes = array(
	Settings::MODE_OFF   => __( '关闭（不接管任何文章）', 'wpdocs' ),
	Settings::MODE_PILOT => __( '试点（只接管下方填写的文章 ID）', 'wpdocs' ),
	Settings::MODE_ALL   => __( '全部（接管所有能对应到文档页的文章）', 'wpdocs' ),
);
foreach ( $modes as $k => $label ) {
	$mode_ctl .= '<label style="display:block"><input type="radio" name="wpdocs[mode]" value="' . esc_attr( $k ) . '" ' . checked( $mode, $k, false ) . ' /> ' . esc_html( $label ) . '</label>';
}

$refresh = rest_url( 'wpdocs/v1/refresh/' ) . ( '' !== (string) Settings::get( 'refresh_secret' ) ? '<secret>' : '' );
$state   = Core::state();
?>
<div class="card">
	<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ); ?>">
		<?php wp_nonce_field( 'wpdocs_settings_full', 'wpdocs_settings_full_nonce' ); ?>
		<?php if ( $adv ) : ?>
			<input type="hidden" name="wpdocs_mode" value="advanced" />
		<?php endif; ?>

		<div class="field-group">
			<div class="field-group-title"><?php esc_html_e( '连接', 'wpdocs' ); ?></div>
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
					'for'     => 'wpdocs-token-id',
					'label'   => __( 'Token ID', 'wpdocs' ),
					'control' => $tid_ctl,
				)
			);
			View::field(
				array(
					'for'     => 'wpdocs-token-secret',
					'label'   => __( 'Token Secret', 'wpdocs' ),
					'help'    => __( '使用只读角色「API 只读」下的专用账号生成的 Token。也可在 wp-config.php 定义 WPDOCS_TOKEN_ID / WPDOCS_TOKEN_SECRET。', 'wpdocs' ),
					'control' => $secret_ctl,
				)
			);
			?>
		</div>

		<div class="field-group">
			<div class="field-group-title"><?php esc_html_e( '书目', 'wpdocs' ); ?></div>
			<?php
			View::field(
				array(
					'for'     => 'wpdocs-site-tag',
					'label'   => __( '站点标签 site', 'wpdocs' ),
					'help'    => __( '未勾选具体书目时，自动使用带此 site 标签的书（例如 wpcy）。', 'wpdocs' ),
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
					'for'     => 'wpdocs-post-types',
					'label'   => __( '文章类型', 'wpdocs' ),
					'help'    => __( '逗号分隔，默认 post。', 'wpdocs' ),
					'control' => '<input id="wpdocs-post-types" type="text" name="wpdocs[post_types]" value="' . esc_attr( implode( ',', (array) ( $s['post_types'] ?? array( 'post' ) ) ) ) . '" />',
				)
			);
			?>
		</div>

		<div class="field-group">
			<div class="field-group-title"><?php esc_html_e( '上线与评论', 'wpdocs' ); ?></div>
			<?php
			View::field(
				array(
					'label'   => __( '模式', 'wpdocs' ),
					'control' => $mode_ctl,
				)
			);
			View::field(
				array(
					'for'     => 'wpdocs-pilot',
					'label'   => __( '试点文章 ID', 'wpdocs' ),
					'control' => '<input id="wpdocs-pilot" type="text" name="wpdocs[pilot_post_ids]" value="' . esc_attr( implode( ', ', Settings::pilot_post_ids() ) ) . '" />',
				)
			);
			View::field(
				array(
					'for'     => 'wpdocs-bff',
					'label'   => __( '评论服务地址', 'wpdocs' ),
					'control' => '<input id="wpdocs-bff" type="url" name="wpdocs[bff_url]" value="' . esc_attr( (string) ( $s['bff_url'] ?? '' ) ) . '" />'
						. '<label class="hint" style="display:block;margin-top:8px"><input type="checkbox" name="wpdocs[comments]" value="1" ' . checked( ! empty( $s['comments'] ), true, false ) . ' /> '
						. esc_html__( '评论统一使用 cocobook:page:{id}，与文档站、论坛同一帖子', 'wpdocs' ) . '</label>',
				)
			);
			View::field(
				array(
					'label'   => __( '旧链接跳转', 'wpdocs' ),
					'help'    => __( '启用后，映射表里已匹配的旧链接 301 到文档页，已下线返回 410。', 'wpdocs' ),
					'control' => '<label><input type="checkbox" name="wpdocs[redirects]" value="1" ' . checked( ! empty( $s['redirects'] ), true, false ) . ' /> '
						. esc_html__( '启用旧链接跳转', 'wpdocs' ) . '</label>'
						. ' <a class="hint" href="' . esc_url( admin_url( 'admin.php?page=wpdocs-redirects' ) ) . '">' . esc_html__( '管理链接映射', 'wpdocs' ) . '</a>',
				)
			);
			View::field(
				array(
					'for'       => 'wpdocs-minc',
					'label'     => __( '旧评论迁移阈值', 'wpdocs' ),
					'help'      => __( '迁移旧评论时，只迁移不少于这么多字的评论（默认 30）。', 'wpdocs' ),
					'advanced'  => true,
					'control'   => '<input id="wpdocs-minc" type="number" min="1" max="1000" name="wpdocs[comment_min_chars]" value="' . esc_attr( (string) ( $s['comment_min_chars'] ?? 30 ) ) . '" />',
				)
			);
			View::field(
				array(
					'for'      => 'wpdocs-ttl',
					'label'    => __( '缓存过期秒数', 'wpdocs' ),
					'help'     => __( '填 0 表示不过期（推荐）：只有文档站有更新时才刷新。正文缓存在文件，不进 option。', 'wpdocs' ),
					'control'  => '<input id="wpdocs-ttl" type="number" min="0" name="wpdocs[page_ttl]" value="' . esc_attr( (string) ( $s['page_ttl'] ?? 0 ) ) . '" />',
				)
			);
			?>
		</div>

		<div class="actions">
			<button type="submit" class="btn btn-primary"><?php esc_html_e( '保存设置', 'wpdocs' ); ?></button>
		</div>
	</form>
</div>

<div class="card">
	<div class="field-group-title"><?php esc_html_e( '同步', 'wpdocs' ); ?></div>
	<p class="hint"><?php esc_html_e( '测试连接只读探测；立即同步会标记缓存过期并重建书目索引。', 'wpdocs' ); ?></p>
	<div class="actions" style="display:flex;gap:8px;flex-wrap:wrap">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ); ?>">
			<?php wp_nonce_field( 'wpdocs_test', 'wpdocs_test_nonce' ); ?>
			<button type="submit" class="btn btn-ghost"><?php esc_html_e( '测试连接', 'wpdocs' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ); ?>">
			<?php wp_nonce_field( 'wpdocs_sync', 'wpdocs_sync_nonce' ); ?>
			<button type="submit" class="btn btn-primary"><?php esc_html_e( '立即同步', 'wpdocs' ); ?></button>
		</form>
	</div>
	<?php if ( is_array( $cat ) ) : ?>
		<p class="hint"><?php echo esc_html( sprintf(
			/* translators: 1: time 2: books 3: pages 4: wp-id 5: wp-path 6: wp-slug 7: conflicts */
			__( '上次同步 %1$s：%2$d 本书，%3$d 个页面；按 wp-id 对应 %4$d，按 wp-path 对应 %5$d，按 wp-slug 对应 %6$d；冲突 %7$d。', 'wpdocs' ),
			(string) ( $cat['built'] ?? '' ),
			count( $cat['books'] ?? array() ),
			count( $cat['pages'] ?? array() ),
			count( $cat['by_wp_id'] ?? array() ),
			count( $cat['by_wp_path'] ?? array() ),
			count( $cat['by_wp_slug'] ?? array() ),
			count( $cat['conflicts'] ?? array() )
		) ); ?></p>
		<?php if ( ! empty( $cat['conflicts'] ) ) : ?>
			<details><summary><?php esc_html_e( '冲突明细（保留先出现的页面）', 'wpdocs' ); ?></summary><ul>
			<?php foreach ( array_slice( (array) $cat['conflicts'], 0, 50 ) as $c ) : ?>
				<li><code><?php echo esc_html( $c[0] . ' = ' . $c[1] ); ?></code>：<?php echo esc_html( '#' . $c[2] . ' / #' . $c[3] ); ?></li>
			<?php endforeach; ?>
			</ul></details>
		<?php endif; ?>
	<?php elseif ( is_wp_error( $cat ) ) : ?>
		<p class="hint is-bad"><?php echo esc_html( $cat->get_error_message() ); ?></p>
	<?php endif; ?>
	<p class="hint" data-for-mode="advanced"><?php esc_html_e( 'BookStack Webhook 刷新地址（POST）：', 'wpdocs' ); ?> <code><?php echo esc_html( $refresh ); ?></code></p>
	<p class="hint"><?php echo esc_html( sprintf(
		__( '更新方式：文档站有改动时由 Webhook 实时刷新；另外每天检查一次。上次检查 %1$s（%2$s）。', 'wpdocs' ),
		$state['checked'] ? wp_date( 'Y-m-d H:i', $state['checked'] ) : '—',
		'' !== $state['result'] ? $state['result'] : '—'
	) ); ?></p>
</div>

<div class="card" data-for-mode="advanced">
	<div class="field-group-title"><?php esc_html_e( '日志', 'wpdocs' ); ?></div>
	<?php if ( ! $log ) : ?>
		<p class="hint"><?php esc_html_e( '暂无记录。', 'wpdocs' ); ?></p>
	<?php else : ?>
		<div class="simple-list">
		<?php foreach ( array_slice( $log, 0, 20 ) as $row ) : ?>
			<div class="simple-row is-static">
				<div class="tile"><?php echo View::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div>
					<div class="t"><?php echo esc_html( (string) ( $row['m'] ?? '' ) ); ?></div>
					<div class="d"><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) ( $row['t'] ?? 0 ) ) . ' · ' . (string) ( $row['l'] ?? '' ) ); ?></div>
				</div>
			</div>
		<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>
