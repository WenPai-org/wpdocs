<?php
/**
 * Bindings page (档 A).
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
		<h1><?php esc_html_e( '绑定', 'wpdocs' ); ?></h1>
		<p class="lede"><?php esc_html_e( 'WP 文章与 BookStack 页的映射；可选 external_id 供评论挂件。', 'wpdocs' ); ?></p>
	</div>
</div>

<?php if ( ! empty( $_GET['updated'] ) ) : // phpcs:ignore WordPress.Security.NonceVerification.Recommended ?>
	<div class="notice ok"><span><?php esc_html_e( '绑定已保存。', 'wpdocs' ); ?></span></div>
<?php endif; ?>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '新增 / 更新', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '同一 wp_post_id 再次保存会覆盖。', 'wpdocs' ); ?></p>
	<form method="post">
		<?php wp_nonce_field( 'wpdocs_bind', 'wpdocs_bind_nonce' ); ?>
		<div class="field">
			<label for="wp_post_id"><?php esc_html_e( 'WP Post ID', 'wpdocs' ); ?></label>
			<input id="wp_post_id" type="number" name="wp_post_id" required min="1" />
		</div>
		<div class="field">
			<label for="wp_post_type"><?php esc_html_e( '文章类型', 'wpdocs' ); ?></label>
			<input id="wp_post_type" type="text" name="wp_post_type" value="post" />
		</div>
		<div class="field">
			<label for="docs_page_id"><?php esc_html_e( 'Docs page id', 'wpdocs' ); ?></label>
			<input id="docs_page_id" type="number" name="docs_page_id" min="0" />
		</div>
		<div class="field">
			<label for="docs_book_slug"><?php esc_html_e( 'Book slug', 'wpdocs' ); ?></label>
			<input id="docs_book_slug" type="text" name="docs_book_slug" />
		</div>
		<div class="field">
			<label for="docs_page_slug"><?php esc_html_e( 'Page slug', 'wpdocs' ); ?></label>
			<input id="docs_page_slug" type="text" name="docs_page_slug" />
		</div>
		<div class="field">
			<label for="external_id"><?php esc_html_e( 'external_id（可选）', 'wpdocs' ); ?></label>
			<input id="external_id" type="text" name="external_id" placeholder="wpcy:post:11144" autocomplete="off" />
			<p class="d"><?php esc_html_e( '不要填密钥或真实邮箱。', 'wpdocs' ); ?></p>
		</div>
		<p><button type="submit" class="btn btn-primary"><?php esc_html_e( '保存绑定', 'wpdocs' ); ?></button></p>
	</form>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '现有绑定', 'wpdocs' ); ?></h2>
	<?php if ( array() === $items ) : ?>
		<p class="empty"><?php esc_html_e( '尚无绑定。', 'wpdocs' ); ?></p>
	<?php else : ?>
		<table class="tbl">
			<thead>
				<tr>
					<th>ID</th>
					<th>WP</th>
					<th>Docs</th>
					<th>external_id</th>
					<th>source</th>
				</tr>
			</thead>
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
	<?php endif; ?>
</section>
