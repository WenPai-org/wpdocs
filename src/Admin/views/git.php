<?php
/**
 * Tools · Git import — pull / sync / webhook.
 *
 * @package Wpdocs
 */

use Wpdocs\Admin\View;
use Wpdocs\Git\Bootstrap;
use Wpdocs\Git\Engine;
use Wpdocs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// phpcs:ignore WordPress.Security.NonceVerification.Recommended
$msg = isset( $_GET['msg'] ) ? sanitize_key( (string) wp_unslash( $_GET['msg'] ) ) : '';
$notices = array(
	'git_ok'    => array( 'ok', __( 'Git 拉取完成。', 'wpdocs' ) ),
	'git_error' => array( 'bad', __( 'Git 拉取失败，请检查地址、Token 与仓库。', 'wpdocs' ) ),
	'git_local' => array( 'ok', __( '本地目录同步完成。', 'wpdocs' ) ),
);
if ( isset( $notices[ $msg ] ) ) {
	echo '<div class="notice ' . esc_attr( $notices[ $msg ][0] ) . '"><span>' . esc_html( $notices[ $msg ][1] ) . '</span></div>';
}

$is_git = Settings::CONTENT_GIT === Settings::content_mode();
$loaded = Bootstrap::load();
$general = Settings::git_general();
$repos   = Settings::git_repositories();
$hook    = Engine::webhook_url();
?>
<section class="card">
	<h2 class="section-title"><?php esc_html_e( 'Git 导入', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '把仓库里的 Markdown 写入 WordPress（原 Git it Write）。请先在设置里选「Git 导入」并保存连接。', 'wpdocs' ); ?></p>
	<?php if ( ! $is_git ) : ?>
		<div class="empty">
			<p><?php esc_html_e( '当前不是 Git 导入模式。', 'wpdocs' ); ?></p>
			<p><a class="btn btn-secondary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ); ?>"><?php esc_html_e( '去设置', 'wpdocs' ); ?></a></p>
		</div>
	<?php elseif ( ! $loaded ) : ?>
		<div class="empty">
			<p><?php esc_html_e( 'Git 核心未加载。请确认 lib/git-it-write 与 vendor（composer install）齐全。', 'wpdocs' ); ?></p>
		</div>
	<?php else : ?>
		<div class="wenpai-actions">
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=git' ) ); ?>" style="display:inline">
				<?php wp_nonce_field( 'wpdocs_git_pull', 'wpdocs_git_pull_nonce' ); ?>
				<button type="submit" class="btn btn-primary"><?php esc_html_e( '拉取全部远程仓', 'wpdocs' ); ?></button>
			</form>
			<?php if ( ! empty( $general['base_directory'] ) ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=git' ) ); ?>" style="display:inline">
					<?php wp_nonce_field( 'wpdocs_git_sync_local', 'wpdocs_git_sync_local_nonce' ); ?>
					<button type="submit" class="btn btn-ghost"><?php esc_html_e( '同步本地目录', 'wpdocs' ); ?></button>
				</form>
			<?php endif; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=git' ) ); ?>" style="display:inline">
				<?php wp_nonce_field( 'wpdocs_git_pull', 'wpdocs_git_pull_nonce' ); ?>
				<input type="hidden" name="wpdocs_git_force" value="1" />
				<button type="submit" class="btn btn-ghost"><?php esc_html_e( '强制全量拉取', 'wpdocs' ); ?></button>
			</form>
		</div>
		<?php
		View::field(
			array(
				'label'   => __( 'Webhook', 'wpdocs' ),
				'help'    => __( '在 Forgejo 仓库 Webhooks 填此 URL，Secret 与设置页一致；事件选 push。', 'wpdocs' ),
				'control' => '<code class="wenpai-code">' . esc_html( $hook ) . '</code>',
			)
		);
		?>
		<?php if ( ! empty( $general['base_directory'] ) ) : ?>
			<p class="hint"><?php echo esc_html( sprintf( __( '本地模式：%s', 'wpdocs' ), (string) $general['base_directory'] ) ); ?></p>
		<?php elseif ( empty( $repos ) ) : ?>
			<p class="hint"><?php esc_html_e( '还没有远程仓库配置。', 'wpdocs' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ); ?>"><?php esc_html_e( '去设置', 'wpdocs' ); ?></a>
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
					· <?php echo esc_html( sprintf( __( '上次 %s', 'wpdocs' ), $last ) ); ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=git' ) ); ?>" style="display:inline">
						<?php wp_nonce_field( 'wpdocs_git_pull', 'wpdocs_git_pull_nonce' ); ?>
						<input type="hidden" name="wpdocs_git_repo_id" value="<?php echo esc_attr( (string) (int) $id ); ?>" />
						<button type="submit" class="btn btn-ghost"><?php esc_html_e( '拉取', 'wpdocs' ); ?></button>
					</form>
				</li>
			<?php endforeach; ?>
			</ul>
		<?php endif; ?>
	<?php endif; ?>
</section>
