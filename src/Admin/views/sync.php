<?php
/**
 * Tools · sync — 连接、CocoBook 导入、日志。
 *
 * @package Wpdocs
 *
 * @var array<string,mixed>|\WP_Error|null $cat
 * @var array<int,array<string,mixed>> $log
 */

use Wpdocs\Admin\View;
use Wpdocs\Core;
use Wpdocs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$refresh = rest_url( 'wpdocs/v1/refresh/' ) . ( '' !== (string) Settings::get( 'refresh_secret' ) ? '{secret}' : '' );
$state   = Core::state();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$msg = isset( $_GET['msg'] ) ? sanitize_key( (string) wp_unslash( $_GET['msg'] ) ) : '';
$notices = array(
	'test_ok'          => array( 'ok', __( '连接正常。', 'wpdocs' ) ),
	'test_error'       => array( 'bad', __( '暂时无法连接文档站，请检查 Token 后重试。', 'wpdocs' ) ),
	'synced'           => array( 'ok', __( '同步完成。', 'wpdocs' ) ),
	'sync_error'       => array( 'bad', __( '暂时无法同步，请查看下方日志。', 'wpdocs' ) ),
	'imported'         => array( 'ok', __( '已将 CocoBook 正文写入绑定文章。', 'wpdocs' ) ),
	'imported_partial' => array( 'ok', __( '部分文章已导入；失败项见日志。', 'wpdocs' ) ),
	'import_error'     => array( 'bad', __( '未能导入。请确认已绑定且 Token 可用。', 'wpdocs' ) ),
);
if ( isset( $notices[ $msg ] ) ) {
	echo '<div class="notice ' . esc_attr( $notices[ $msg ][0] ) . '"><span>' . esc_html( $notices[ $msg ][1] ) . '</span></div>';
}
?>
<section class="card">
	<h2 class="section-title"><?php esc_html_e( '连接与索引', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '测试只读探测；立即同步会标记缓存过期并重建书目索引。', 'wpdocs' ); ?></p>
	<div class="wenpai-actions">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=sync' ) ); ?>" style="display:inline">
			<?php wp_nonce_field( 'wpdocs_test', 'wpdocs_test_nonce' ); ?>
			<button type="submit" class="btn btn-ghost"><?php esc_html_e( '测试连接', 'wpdocs' ); ?></button>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=sync' ) ); ?>" style="display:inline">
			<?php wp_nonce_field( 'wpdocs_sync', 'wpdocs_sync_nonce' ); ?>
			<button type="submit" class="btn btn-primary"><?php esc_html_e( '立即同步', 'wpdocs' ); ?></button>
		</form>
	</div>
	<?php if ( is_array( $cat ) ) : ?>
		<p class="hint"><?php echo esc_html( sprintf(
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
	<?php elseif ( ! Settings::is_configured() ) : ?>
		<div class="empty">
			<p><?php esc_html_e( '还没配置文档站 Token。先到设置页填好连接，再回来同步。', 'wpdocs' ); ?></p>
			<p><a class="btn btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ); ?>"><?php esc_html_e( '去设置', 'wpdocs' ); ?></a></p>
		</div>
	<?php endif; ?>
	<?php
	View::field(
		array(
			'label'   => __( 'Webhook', 'wpdocs' ),
			'help'    => __( '文档站有改动时 POST 到此地址；另外每天检查一次。', 'wpdocs' ),
			'control' => '<code class="wenpai-code">' . esc_html( $refresh ) . '</code><p class="hint">' . esc_html( sprintf(
				__( '上次检查 %1$s（%2$s）。', 'wpdocs' ),
				$state['checked'] ? wp_date( 'Y-m-d H:i', $state['checked'] ) : '—',
				'' !== $state['result'] ? $state['result'] : '—'
			) ) . '</p>',
		)
	);
	?>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '导入到文章', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '把已绑定的 CocoBook 页正文写入 WordPress（覆盖 post_content）。适合「保留」模式：导入后由 WP 持有正文，不再运行时读取。', 'wpdocs' ); ?></p>
	<div class="wenpai-actions">
		<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=sync' ) ); ?>" style="display:inline" onsubmit="return confirm('<?php echo esc_js( __( '将覆盖已绑定文章的正文，确定？', 'wpdocs' ) ); ?>');">
			<?php wp_nonce_field( 'wpdocs_import_cb', 'wpdocs_import_cb_nonce' ); ?>
			<button type="submit" class="btn btn-ghost" <?php disabled( ! Settings::is_configured() ); ?>><?php esc_html_e( '从 CocoBook 导入', 'wpdocs' ); ?></button>
		</form>
		<a class="btn btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=git' ) ); ?>"><?php esc_html_e( '从 Git 导入…', 'wpdocs' ); ?></a>
	</div>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '日志', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '最近同步与连接记录。', 'wpdocs' ); ?></p>
	<?php if ( empty( $log ) ) : ?>
		<div class="empty"><p><?php esc_html_e( '还没有同步记录。测试连接或立即同步后会出现在这里。', 'wpdocs' ); ?></p></div>
	<?php else : ?>
		<?php foreach ( array_slice( $log, 0, 20 ) as $row ) : ?>
			<div class="simple-row is-static">
				<div class="tile"><?php echo View::icon( 'info' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
				<div>
					<div class="t"><?php echo esc_html( (string) ( $row['m'] ?? '' ) ); ?></div>
					<div class="d"><?php echo esc_html( wp_date( 'Y-m-d H:i:s', (int) ( $row['t'] ?? 0 ) ) . ' · ' . (string) ( $row['l'] ?? '' ) ); ?></div>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</section>
