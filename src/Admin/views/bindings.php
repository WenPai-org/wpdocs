<?php
/**
 * Bindings page (档 A).
 *
 * @package Wpdocs
 * @var array<int,object> $items
 */

use Wpdocs\Admin\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

View::page_head(
	__( '绑定', 'wpdocs' ),
	__( '把 WordPress 文章映射到 BookStack 页面；可选 external_id 供评论挂件使用。', 'wpdocs' )
);
View::saved_notice();
?>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '新增 / 更新', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '同一文章 ID 再次保存会覆盖。', 'wpdocs' ); ?></p>
	<form method="post">
		<?php wp_nonce_field( 'wpdocs_bind', 'wpdocs_bind_nonce' ); ?>
		<?php
		View::field(
			array(
				'for'     => 'wp_post_id',
				'label'   => __( '文章 ID', 'wpdocs' ),
				'help'    => __( 'WordPress 文章或页面的 ID。', 'wpdocs' ),
				'control' => '<input id="wp_post_id" type="number" name="wp_post_id" required min="1" />',
			)
		);
		View::field(
			array(
				'for'     => 'wp_post_type',
				'label'   => __( '文章类型', 'wpdocs' ),
				'control' => '<input id="wp_post_type" type="text" name="wp_post_type" value="post" />',
			)
		);
		View::field(
			array(
				'for'     => 'docs_page_id',
				'label'   => __( '文档页 ID', 'wpdocs' ),
				'help'    => __( 'BookStack page id。', 'wpdocs' ),
				'control' => '<input id="docs_page_id" type="number" name="docs_page_id" min="0" />',
			)
		);
		View::field(
			array(
				'for'     => 'docs_book_slug',
				'label'   => __( '书 slug', 'wpdocs' ),
				'control' => '<input id="docs_book_slug" type="text" name="docs_book_slug" />',
			)
		);
		View::field(
			array(
				'for'     => 'docs_page_slug',
				'label'   => __( '页 slug', 'wpdocs' ),
				'control' => '<input id="docs_page_slug" type="text" name="docs_page_slug" />',
			)
		);
		View::field(
			array(
				'for'     => 'external_id',
				'label'   => __( 'external_id（可选）', 'wpdocs' ),
				'help'    => __( '不要填写密钥或真实邮箱。', 'wpdocs' ),
				'control' => '<input id="external_id" type="text" name="external_id" placeholder="wpcy:post:11144" autocomplete="off" />',
			)
		);
		?>
		<p class="wenpai-actions"><button type="submit" class="btn btn-primary"><?php esc_html_e( '保存绑定', 'wpdocs' ); ?></button></p>
	</form>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '现有绑定', 'wpdocs' ); ?></h2>
	<?php if ( array() === $items ) : ?>
		<p class="empty"><?php esc_html_e( '还没有绑定。', 'wpdocs' ); ?></p>
	<?php else : ?>
		<table class="tbl">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'wpdocs' ); ?></th>
					<th><?php esc_html_e( '文章', 'wpdocs' ); ?></th>
					<th><?php esc_html_e( '文档', 'wpdocs' ); ?></th>
					<th>external_id</th>
					<th><?php esc_html_e( '来源', 'wpdocs' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $items as $row ) : ?>
				<tr>
					<td class="num"><?php echo (int) $row->id; ?></td>
					<td>
						<div class="t">#<?php echo (int) $row->wp_post_id; ?></div>
						<div class="d"><code><?php echo esc_html( $row->wp_post_type ); ?></code></div>
					</td>
					<td>
						<div class="t"><?php echo esc_html( $row->docs_book_slug . '/' . $row->docs_page_slug ); ?></div>
						<div class="d">#<?php echo (int) $row->docs_page_id; ?></div>
					</td>
					<td><code><?php echo esc_html( $row->external_id ); ?></code></td>
					<td><?php echo esc_html( $row->source ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
