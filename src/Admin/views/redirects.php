<?php
/**
 * Redirects page (档 A).
 *
 * @package Wpdocs
 * @var array<int,object> $items
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="page-head">
	<div>
		<h1><?php esc_html_e( '跳转', 'wpdocs' ); ?></h1>
		<p class="lede"><?php esc_html_e( '旧 WP 路径 → 301（matched）或 410（gone）。目标用 page_id，不写死绝对 URL。', 'wpdocs' ); ?></p>
	</div>
</div>

<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="notice ok"><span><?php esc_html_e( '跳转已保存。', 'wpdocs' ); ?></span></div>
<?php endif; ?>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '新增 / 更新', 'wpdocs' ); ?></h2>
	<form method="post">
		<?php wp_nonce_field( 'wpdocs_redirect', 'wpdocs_redirect_nonce' ); ?>
		<div class="field">
			<label for="wp_path"><?php esc_html_e( '路径', 'wpdocs' ); ?></label>
			<input id="wp_path" type="text" name="wp_path" placeholder="/document/old" required />
			<p class="d"><?php esc_html_e( '规范化为前导 /；不劫持 wp-admin / wp-json 等。', 'wpdocs' ); ?></p>
		</div>
		<div class="field">
			<label for="page_id"><?php esc_html_e( 'page_id', 'wpdocs' ); ?></label>
			<input id="page_id" type="number" name="page_id" min="0" />
		</div>
		<div class="field">
			<label for="status"><?php esc_html_e( '状态', 'wpdocs' ); ?></label>
			<select id="status" name="status">
				<option value="matched">matched (301)</option>
				<option value="gone">gone (410)</option>
				<option value="pending">pending</option>
				<option value="unmatched">unmatched</option>
			</select>
		</div>
		<div class="field">
			<label for="wp_title"><?php esc_html_e( '原标题（可选）', 'wpdocs' ); ?></label>
			<input id="wp_title" type="text" name="wp_title" />
		</div>
		<p><button type="submit" class="btn btn-primary"><?php esc_html_e( '保存跳转', 'wpdocs' ); ?></button></p>
	</form>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '现有跳转', 'wpdocs' ); ?></h2>
	<?php if ( array() === $items ) : ?>
		<p class="empty"><?php esc_html_e( '尚无跳转。', 'wpdocs' ); ?></p>
	<?php else : ?>
		<table class="tbl">
			<thead>
				<tr>
					<th>ID</th>
					<th>path</th>
					<th>page</th>
					<th>status</th>
					<th>hits</th>
				</tr>
			</thead>
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
	<?php endif; ?>
</section>
