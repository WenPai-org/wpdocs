<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpdocs-admin" data-wpdocs-page="status">
	<h1><?php esc_html_e( '文派文档 · 状态', 'wpdocs' ); ?></h1>
	<p class="description"><?php esc_html_e( '发布树 / 文档站只读诊断。不写正文进 option。', 'wpdocs' ); ?></p>
	<div class="wpdocs-card">
		<p><strong><?php esc_html_e( '版本', 'wpdocs' ); ?>:</strong> <?php echo esc_html( WPDOCS_VERSION ); ?></p>
		<p><strong><?php esc_html_e( '文档站', 'wpdocs' ); ?>:</strong> <?php echo esc_html( $st['base_url'] ); ?></p>
		<p><strong>home:</strong> <?php echo esc_html( (string) ( $st['home']['code'] ?? 0 ) ); ?> (<?php echo esc_html( (string) ( $st['home']['ms'] ?? 0 ) ); ?>ms)</p>
		<p><strong>sitemap:</strong> <?php echo esc_html( (string) ( $st['sitemap']['code'] ?? 0 ) ); ?> (<?php echo esc_html( (string) ( $st['sitemap']['ms'] ?? 0 ) ); ?>ms)</p>
		<p><strong><?php esc_html_e( '绑定数', 'wpdocs' ); ?>:</strong> <?php echo esc_html( (string) \Wpdocs\Bind\Repository::count() ); ?></p>
		<p><strong><?php esc_html_e( '跳转数', 'wpdocs' ); ?>:</strong> <?php echo esc_html( (string) \Wpdocs\Redirect\Repository::count() ); ?></p>
	</div>
</div>
