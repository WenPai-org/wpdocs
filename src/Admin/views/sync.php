<?php
/**
 * Tools · sync — 按当前内容模式只露相关动作。
 *
 * @package Wpdocs
 *
 * @var array<string,mixed>|\WP_Error|null $cat
 * @var array<int,array<string,mixed>> $log
 */

use Wpdocs\Admin\View;
use Wpdocs\Core;
use Wpdocs\Git\Bootstrap;
use Wpdocs\Git\Engine;
use Wpdocs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$content = Settings::content_mode();
$refresh = Core::refresh_url();
$state   = Core::state();

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$msg     = isset( $_GET['msg'] ) ? sanitize_key( (string) wp_unslash( $_GET['msg'] ) ) : '';
$notices = array(
	'test_ok'          => array( 'ok', __( '连接正常。', 'wpdocs' ) ),
	'test_error'       => array( 'bad', __( '暂时无法测试连接，请检查 CocoBook 地址与 Token。', 'wpdocs' ) ),
	'synced'           => array( 'ok', __( '索引已更新。', 'wpdocs' ) ),
	'sync_error'       => array( 'bad', __( '同步失败。', 'wpdocs' ) ),
	'imported'         => array( 'ok', __( '已写入绑定文章。', 'wpdocs' ) ),
	'imported_partial' => array( 'ok', __( '部分写入成功，见日志。', 'wpdocs' ) ),
	'import_error'     => array( 'bad', __( '导入失败。先绑定并检查 Token。', 'wpdocs' ) ),
	'git_ok'           => array( 'ok', __( 'Git 拉取完成。', 'wpdocs' ) ),
	'git_error'        => array( 'bad', __( 'Git 拉取失败。', 'wpdocs' ) ),
	'git_local'        => array( 'ok', __( '本地同步完成。', 'wpdocs' ) ),
);
if ( isset( $notices[ $msg ] ) ) {
	echo '<div class="notice ' . esc_attr( $notices[ $msg ][0] ) . '"><span>' . esc_html( $notices[ $msg ][1] ) . '</span></div>';
}

$mode_lab = array(
	Settings::CONTENT_KEEP     => __( '保留正文', 'wpdocs' ),
	Settings::CONTENT_COCOBOOK => __( '文档站读取', 'wpdocs' ),
	Settings::CONTENT_GIT      => __( 'Git 写入', 'wpdocs' ),
);
?>
<section class="card">
	<h2 class="section-title"><?php esc_html_e( '同步', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php
		echo esc_html(
			sprintf(
				/* translators: %s: content mode label */
				__( '当前内容从哪来：%s。设置里可改。', 'wpdocs' ),
				$mode_lab[ $content ] ?? $content
			)
		);
	?></p>

	<?php if ( Settings::CONTENT_GIT === $content ) : ?>
		<?php
		$loaded  = Bootstrap::load();
		$general = Settings::git_general();
		$repos   = Settings::git_repositories();
		$hook    = Engine::webhook_url();
		?>
		<?php if ( ! $loaded ) : ?>
			<?php echo Wenpai_Admin_UI::empty_state_html( array( 'text' => __( 'Git 核心未加载。', 'wpdocs' ) ) ); ?>
		<?php else : ?>
			<div class="wenpai-actions">
				<form method="post" action="<?php echo esc_url( \Wpdocs\Admin\Menu::url( 'tools', 'sync' ) ); ?>" style="display:inline">
					<?php wp_nonce_field( 'wpdocs_git_pull', 'wpdocs_git_pull_nonce' ); ?>
					<button type="submit" class="btn btn-primary"><?php esc_html_e( '拉取仓库', 'wpdocs' ); ?></button>
				</form>
				<form method="post" action="<?php echo esc_url( \Wpdocs\Admin\Menu::url( 'tools', 'sync' ) ); ?>" style="display:inline">
					<?php wp_nonce_field( 'wpdocs_git_pull', 'wpdocs_git_pull_nonce' ); ?>
					<input type="hidden" name="wpdocs_git_force" value="1" />
					<button type="submit" class="btn btn-ghost"><?php esc_html_e( '强制全量', 'wpdocs' ); ?></button>
				</form>
				<?php if ( ! empty( $general['base_directory'] ) ) : ?>
					<form method="post" action="<?php echo esc_url( \Wpdocs\Admin\Menu::url( 'tools', 'sync' ) ); ?>" style="display:inline">
						<?php wp_nonce_field( 'wpdocs_git_sync_local', 'wpdocs_git_sync_local_nonce' ); ?>
						<button type="submit" class="btn btn-ghost"><?php esc_html_e( '同步本地目录', 'wpdocs' ); ?></button>
					</form>
				<?php endif; ?>
			</div>
			<?php
			View::field(
				array(
					'label'   => __( 'Webhook', 'wpdocs' ),
					'help'    => __( 'Forgejo push → 此地址；Secret 在设置高级里。', 'wpdocs' ),
					'control' => '<div class="wpdocs-code-row"><code class="wenpai-code">' . esc_html( $hook ) . '</code>'
						. Wenpai_Admin_UI::copy_button_html( array( 'text' => $hook, 'variant' => 'ghost' ) ) . '</div>',
				)
			);
			?>
			<?php if ( ! empty( $general['base_directory'] ) ) : ?>
				<p class="hint"><?php echo esc_html( (string) $general['base_directory'] ); ?></p>
			<?php elseif ( empty( $repos ) ) : ?>
				<p class="hint"><?php esc_html_e( '还没配仓库。', 'wpdocs' ); ?>
					<a href="<?php echo esc_url( \Wpdocs\Admin\Menu::url( 'settings' ) ); ?>"><?php esc_html_e( '去设置', 'wpdocs' ); ?></a>
				</p>
			<?php else : ?>
				<ul class="wpdocs-git-repo-list">
				<?php foreach ( $repos as $id => $repo ) : ?>
					<?php
					if ( ! is_array( $repo ) || empty( $repo['username'] ) || empty( $repo['repository'] ) ) {
						continue;
					}
					$last = ! empty( $repo['last_publish'] ) ? wp_date( 'Y-m-d H:i', (int) $repo['last_publish'] ) : '—';
					?>
					<li>
						<strong><?php echo esc_html( $repo['username'] . '/' . $repo['repository'] ); ?></strong>
						· <?php echo esc_html( (string) ( $repo['branch'] ?? 'main' ) ); ?>
						· <?php echo esc_html( (string) ( $repo['folder'] ?? '' ) ); ?>
						· <?php echo esc_html( $last ); ?>
					</li>
				<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		<?php endif; ?>

	<?php elseif ( Settings::CONTENT_COCOBOOK === $content ) : ?>
		<div class="wenpai-actions">
			<form method="post" action="<?php echo esc_url( \Wpdocs\Admin\Menu::url( 'tools', 'sync' ) ); ?>" style="display:inline">
				<?php wp_nonce_field( 'wpdocs_test', 'wpdocs_test_nonce' ); ?>
				<button type="submit" class="btn btn-ghost"><?php esc_html_e( '测试连接', 'wpdocs' ); ?></button>
			</form>
			<form method="post" action="<?php echo esc_url( \Wpdocs\Admin\Menu::url( 'tools', 'sync' ) ); ?>" style="display:inline">
				<?php wp_nonce_field( 'wpdocs_sync', 'wpdocs_sync_nonce' ); ?>
				<button type="submit" class="btn btn-primary"><?php esc_html_e( '更新索引', 'wpdocs' ); ?></button>
			</form>
		</div>
		<?php if ( is_array( $cat ) ) : ?>
			<p class="hint"><?php echo esc_html( sprintf(
				__( '上次 %1$s · %2$d 书 · %3$d 页', 'wpdocs' ),
				(string) ( $cat['built'] ?? '' ),
				count( $cat['books'] ?? array() ),
				count( $cat['pages'] ?? array() )
			) ); ?></p>
		<?php elseif ( is_wp_error( $cat ) ) : ?>
			<p class="hint is-bad"><?php echo esc_html( $cat->get_error_message() ); ?></p>
		<?php elseif ( ! Settings::is_configured() ) : ?>
			<p class="hint"><?php esc_html_e( '还没配 Token。', 'wpdocs' ); ?>
				<a href="<?php echo esc_url( \Wpdocs\Admin\Menu::url( 'settings' ) ); ?>"><?php esc_html_e( '去设置', 'wpdocs' ); ?></a>
			</p>
		<?php endif; ?>
		<?php
		View::field(
			array(
				'label'   => __( 'CocoBook Webhook', 'wpdocs' ),
				'help'    => sprintf(
					/* translators: 1: last check time, 2: last check result */
					__( '上次检查 %1$s（%2$s）。在 CocoBook 后台把 page 创建/更新/删除事件 POST 到此地址。密钥在路径里；保存设置时自动生成。', 'wpdocs' ),
					$state['checked'] ? wp_date( 'Y-m-d H:i', $state['checked'] ) : '—',
					'' !== $state['result'] ? $state['result'] : '—'
				),
				'control' => '<div class="wpdocs-code-row"><code class="wenpai-code">' . esc_html( $refresh ) . '</code>'
					. Wenpai_Admin_UI::copy_button_html( array( 'text' => $refresh, 'variant' => 'ghost' ) ) . '</div>',
			)
		);
		?>

	<?php else : /* keep */ ?>
		<p class="section-desc"><?php esc_html_e( '正文留在 WordPress。把已绑定的 CocoBook 页一次性写入文章会覆盖正文。', 'wpdocs' ); ?></p>
		<form method="post" action="<?php echo esc_url( \Wpdocs\Admin\Menu::url( 'tools', 'sync' ) ); ?>" id="wpdocs-import-cb-form">
			<?php wp_nonce_field( 'wpdocs_import_cb', 'wpdocs_import_cb_nonce' ); ?>
			<?php
			echo Wenpai_Admin_UI::gate_html(
				array(
					'name'  => 'wpdocs_import_confirm',
					'label' => __( '我已核对绑定列表，确认覆盖这些文章的正文。', 'wpdocs' ),
					'attrs' => Settings::is_configured() ? array( 'required' => true ) : array( 'required' => true, 'disabled' => true ),
				)
			);
			?>
			<div class="wenpai-actions">
				<button type="submit" class="btn btn-danger" <?php disabled( ! Settings::is_configured() ); ?>><?php esc_html_e( '从 CocoBook 导入', 'wpdocs' ); ?></button>
			</div>
		</form>
		<form method="post" action="<?php echo esc_url( \Wpdocs\Admin\Menu::url( 'tools', 'sync' ) ); ?>" style="margin-top:12px">
			<?php wp_nonce_field( 'wpdocs_test', 'wpdocs_test_nonce' ); ?>
			<button type="submit" class="btn btn-ghost" <?php disabled( ! Settings::is_configured() ); ?>><?php esc_html_e( '测试连接', 'wpdocs' ); ?></button>
		</form>
		<?php if ( ! Settings::is_configured() ) : ?>
			<p class="hint"><?php esc_html_e( '导入需要 Token：设置 → 保留正文 → 开「高级」填 CocoBook 地址。', 'wpdocs' ); ?>
				<a href="<?php echo esc_url( \Wpdocs\Admin\Menu::url( 'settings', '', 'mode=advanced' ) ); ?>"><?php esc_html_e( '去设置', 'wpdocs' ); ?></a>
			</p>
		<?php endif; ?>
	<?php endif; ?>
</section>

<?php if ( ! empty( $log ) ) : ?>
<section class="card">
	<h2 class="section-title"><?php esc_html_e( '最近日志', 'wpdocs' ); ?></h2>
	<?php foreach ( array_slice( $log, 0, 12 ) as $row ) : ?>
		<?php
echo Wenpai_Admin_UI::row_html( array( 'icon_html' => View::icon( 'info' ), 'title' => (string) ( $row['m'] ?? '' ), 'desc' => wp_date( 'Y-m-d H:i', (int) ( $row['t'] ?? 0 ) ) ) );
?>
	<?php endforeach; ?>
</section>
<?php endif; ?>
