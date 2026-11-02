<?php
/**
 * Redirects page (档 A).
 *
 * @package Wpdocs
 * @var array<int,object> $items
 */

use Wpdocs\Admin\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

View::page_head(
	__( '跳转', 'wpdocs' ),
	__( '旧路径 301 到文档页，或返回 410。目标只存 page_id，不写死绝对 URL。', 'wpdocs' )
);
View::saved_notice();
?>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '新增 / 更新', 'wpdocs' ); ?></h2>
	<form method="post">
		<?php wp_nonce_field( 'wpdocs_redirect', 'wpdocs_redirect_nonce' ); ?>
		<?php
		View::field(
			array(
				'for'     => 'wp_path',
				'label'   => __( '路径', 'wpdocs' ),
				'help'    => __( '以 / 开头；不会劫持 wp-admin、wp-json 等保留路径。', 'wpdocs' ),
				'control' => '<input id="wp_path" type="text" name="wp_path" placeholder="/document/old" required />',
			)
		);
		View::field(
			array(
				'for'     => 'page_id',
				'label'   => __( '文档页 ID', 'wpdocs' ),
				'control' => '<input id="page_id" type="number" name="page_id" min="0" />',
			)
		);
		View::field(
			array(
				'for'     => 'status',
				'label'   => __( '状态', 'wpdocs' ),
				'control' => '<select id="status" name="status">'
					. '<option value="matched">matched（301）</option>'
					. '<option value="gone">gone（410）</option>'
					. '<option value="pending">pending</option>'
					. '<option value="unmatched">unmatched</option>'
					. '</select>',
			)
		);
		View::field(
			array(
				'for'     => 'wp_title',
				'label'   => __( '原标题（可选）', 'wpdocs' ),
				'control' => '<input id="wp_title" type="text" name="wp_title" />',
			)
		);
		?>
		<p class="wenpai-actions"><button type="submit" class="btn btn-primary"><?php esc_html_e( '保存跳转', 'wpdocs' ); ?></button></p>
	</form>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '现有跳转', 'wpdocs' ); ?></h2>
	<?php if ( array() === $items ) : ?>
		<p class="empty"><?php esc_html_e( '还没有跳转。', 'wpdocs' ); ?></p>
	<?php else : ?>
		<table class="tbl">
			<thead>
				<tr>
					<th><?php esc_html_e( 'ID', 'wpdocs' ); ?></th>
					<th><?php esc_html_e( '路径', 'wpdocs' ); ?></th>
					<th><?php esc_html_e( '文档页', 'wpdocs' ); ?></th>
					<th><?php esc_html_e( '状态', 'wpdocs' ); ?></th>
					<th><?php esc_html_e( '命中', 'wpdocs' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $items as $row ) : ?>
				<tr>
					<td class="num"><?php echo (int) $row->id; ?></td>
					<td><code><?php echo esc_html( $row->wp_path ); ?></code></td>
					<td class="num"><?php echo (int) $row->page_id; ?></td>
					<td><?php echo esc_html( $row->status ); ?></td>
					<td class="num"><?php echo (int) $row->hits; ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
