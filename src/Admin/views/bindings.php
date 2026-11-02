<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpdocs-admin" data-wpdocs-page="bindings">
	<h1><?php esc_html_e( '文派文档 · 绑定', 'wpdocs' ); ?></h1>
	<?php if ( ! empty( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '已保存。', 'wpdocs' ); ?></p></div>
	<?php endif; ?>
	<form method="post" class="wpdocs-card">
		<?php wp_nonce_field( 'wpdocs_bind', 'wpdocs_bind_nonce' ); ?>
		<p><label>WP Post ID <input type="number" name="wp_post_id" required min="1"></label>
		<label>docs page id <input type="number" name="docs_page_id" min="0"></label>
		<label>book slug <input type="text" name="docs_book_slug"></label>
		<label>page slug <input type="text" name="docs_page_slug"></label>
		<label>external_id <input type="text" name="external_id" placeholder="wpcy:post:11144"></label>
		<button class="button button-primary"><?php esc_html_e( '保存绑定', 'wpdocs' ); ?></button></p>
	</form>
	<table class="widefat striped">
		<thead><tr><th>ID</th><th>WP</th><th>Docs</th><th>external_id</th><th>source</th></tr></thead>
		<tbody>
		<?php foreach ( $items as $row ) : ?>
			<tr>
				<td><?php echo (int) $row->id; ?></td>
				<td><?php echo (int) $row->wp_post_id; ?> <code><?php echo esc_html( $row->wp_post_type ); ?></code></td>
				<td><?php echo esc_html( $row->docs_book_slug . '/' . $row->docs_page_slug ); ?> (#<?php echo (int) $row->docs_page_id; ?>)</td>
				<td><code><?php echo esc_html( $row->external_id ); ?></code></td>
				<td><?php echo esc_html( $row->source ); ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
