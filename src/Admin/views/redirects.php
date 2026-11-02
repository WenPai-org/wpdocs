<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap wpdocs-admin" data-wpdocs-page="redirects">
	<h1><?php esc_html_e( '文派文档 · 跳转', 'wpdocs' ); ?></h1>
	<?php if ( ! empty( $_GET['updated'] ) ) : ?>
		<div class="notice notice-success is-dismissible"><p><?php esc_html_e( '已保存。', 'wpdocs' ); ?></p></div>
	<?php endif; ?>
	<form method="post" class="wpdocs-card">
		<?php wp_nonce_field( 'wpdocs_redirect', 'wpdocs_redirect_nonce' ); ?>
		<p><label>路径 <input type="text" name="wp_path" placeholder="/document/old" required></label>
		<label>page_id <input type="number" name="page_id" min="0"></label>
		<label>状态
			<select name="status">
				<option value="matched">matched (301)</option>
				<option value="gone">gone (410)</option>
				<option value="pending">pending</option>
				<option value="unmatched">unmatched</option>
			</select>
		</label>
		<label>标题 <input type="text" name="wp_title"></label>
		<button class="button button-primary"><?php esc_html_e( '保存跳转', 'wpdocs' ); ?></button></p>
	</form>
	<table class="widefat striped">
		<thead><tr><th>ID</th><th>path</th><th>page</th><th>status</th><th>hits</th></tr></thead>
		<tbody>
		<?php foreach ( $items as $row ) : ?>
			<tr>
				<td><?php echo (int) $row->id; ?></td>
				<td><code><?php echo esc_html( $row->wp_path ); ?></code></td>
				<td><?php echo (int) $row->page_id; ?></td>
				<td><?php echo esc_html( $row->status ); ?></td>
				<td><?php echo (int) $row->hits; ?></td>
			</tr>
		<?php endforeach; ?>
		</tbody>
	</table>
</div>
