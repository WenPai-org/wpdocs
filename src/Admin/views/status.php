<?php
/**
 * Status page (档 A).
 *
 * @package Wpdocs
 * @var array<string,mixed> $st
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$home_ok    = ! empty( $st['home']['ok'] );
$site_ok    = ! empty( $st['sitemap']['ok'] );
$tree_skip  = ! empty( $st['tree']['skipped'] );
$tree_ok    = ! empty( $st['tree']['ok'] );
$bind_n     = (int) \Wpdocs\Bind\Repository::count();
$redir_n    = (int) \Wpdocs\Redirect\Repository::count();
$opt        = get_option( 'wpdocs_settings', array() );
$base_input = is_array( $opt ) ? (string) ( $opt['base_url'] ?? ( $st['base_url'] ?? '' ) ) : '';
$tree_input = is_array( $opt ) ? (string) ( $opt['tree_url'] ?? '' ) : '';
?>
<div class="page-head">
	<div>
		<h1><?php esc_html_e( '状态', 'wpdocs' ); ?></h1>
		<p class="lede"><?php esc_html_e( '文档站与发布树只读探测。正文不会写入 option。', 'wpdocs' ); ?></p>
	</div>
</div>

<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="notice ok"><span><?php esc_html_e( '设置已保存。', 'wpdocs' ); ?></span></div>
<?php endif; ?>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '概览', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '版本、计数与探测摘要。', 'wpdocs' ); ?></p>
	<div class="simple-row"><span class="k"><?php esc_html_e( '版本', 'wpdocs' ); ?></span><span class="v"><code><?php echo esc_html( WPDOCS_VERSION ); ?></code></span></div>
	<div class="simple-row"><span class="k"><?php esc_html_e( '文档站', 'wpdocs' ); ?></span><span class="v"><code><?php echo esc_html( (string) ( $st['base_url'] ?? '' ) ); ?></code></span></div>
	<div class="simple-row">
		<span class="k"><?php esc_html_e( '首页探测', 'wpdocs' ); ?></span>
		<span class="v"><span class="pill <?php echo $home_ok ? 'ok' : 'bad'; ?>"><?php echo esc_html( (string) ( $st['home']['code'] ?? 0 ) ); ?> · <?php echo esc_html( (string) ( $st['home']['ms'] ?? 0 ) ); ?>ms</span></span>
	</div>
	<div class="simple-row">
		<span class="k"><?php esc_html_e( '站点地图', 'wpdocs' ); ?></span>
		<span class="v"><span class="pill <?php echo $site_ok ? 'ok' : 'warn'; ?>"><?php echo esc_html( (string) ( $st['sitemap']['code'] ?? 0 ) ); ?> · <?php echo esc_html( (string) ( $st['sitemap']['ms'] ?? 0 ) ); ?>ms</span></span>
	</div>
	<div class="simple-row">
		<span class="k"><?php esc_html_e( '发布树', 'wpdocs' ); ?></span>
		<span class="v">
			<?php if ( $tree_skip ) : ?>
				<span class="pill warn"><?php esc_html_e( '已跳过', 'wpdocs' ); ?></span>
			<?php else : ?>
				<span class="pill <?php echo $tree_ok ? 'ok' : 'bad'; ?>"><?php echo esc_html( (string) ( $st['tree']['code'] ?? 0 ) ); ?> · <?php echo esc_html( (string) ( $st['tree']['ms'] ?? 0 ) ); ?>ms</span>
			<?php endif; ?>
		</span>
	</div>
	<div class="simple-row"><span class="k"><?php esc_html_e( '绑定', 'wpdocs' ); ?></span><span class="v"><?php echo esc_html( (string) $bind_n ); ?></span></div>
	<div class="simple-row"><span class="k"><?php esc_html_e( '跳转', 'wpdocs' ); ?></span><span class="v"><?php echo esc_html( (string) $redir_n ); ?></span></div>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '探测地址', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '可用 wp-config 常量 WPDOCS_BASE_URL / WPDOCS_TREE_URL 覆盖。', 'wpdocs' ); ?></p>
	<form method="post">
		<?php wp_nonce_field( 'wpdocs_settings', 'wpdocs_settings_nonce' ); ?>
		<div class="field">
			<label for="wpdocs-base-url"><?php esc_html_e( '文档站 base URL', 'wpdocs' ); ?></label>
			<input id="wpdocs-base-url" type="url" name="base_url" value="<?php echo esc_attr( $base_input ); ?>" placeholder="https://docs.example.test" />
			<p class="d"><?php esc_html_e( '用于 home 与 /cocobook/sitemap.xml HEAD。', 'wpdocs' ); ?></p>
		</div>
		<div class="field">
			<label for="wpdocs-tree-url"><?php esc_html_e( '发布树 URL（可选）', 'wpdocs' ); ?></label>
			<input id="wpdocs-tree-url" type="url" name="tree_url" value="<?php echo esc_attr( $tree_input ); ?>" placeholder="" />
			<p class="d"><?php esc_html_e( '留空则用 base/books；显式空串跳过 tree。', 'wpdocs' ); ?></p>
		</div>
		<p><button type="submit" class="btn btn-primary"><?php esc_html_e( '保存并刷新探测', 'wpdocs' ); ?></button></p>
	</form>
</section>

<?php if ( 0 === $bind_n && 0 === $redir_n ) : ?>
	<div class="next">
		<p><?php esc_html_e( '还没有绑定或跳转。可从绑定页手工录入，或激活时从旧 cocobook_redirects 表导入。', 'wpdocs' ); ?></p>
		<a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-bindings' ) ); ?>"><?php esc_html_e( '去绑定', 'wpdocs' ); ?></a>
	</div>
<?php endif; ?>
