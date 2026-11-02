<?php
/**
 * Bindings page (档 A).
 *
 * Simple: post id + type + book/page slug.
 * Advanced: page id, external id, source, status, resolve.
 *
 * @package Wpdocs
 * @var array<int,object> $items
 */

use Wpdocs\Admin\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$adv = View::is_advanced();
ob_start();
View::mode_toggle( 'wpdocs-bindings' );
$mode_html = ob_get_clean();

View::page_head(
	__( '绑定', 'wpdocs' ),
	__( '把 WordPress 文章对到文档站的一页。不改文档站内容。', 'wpdocs' ),
	$mode_html
);
View::saved_notice();
View::error_notice();
?>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '手工绑定', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '一篇文章对应一页文档。简单模式用书册/页面别名；高级可填文档页 ID 与外部 ID。', 'wpdocs' ); ?></p>
	<form method="post" id="wpdocs-bind-form">
		<?php wp_nonce_field( 'wpdocs_bind', 'wpdocs_bind_nonce' ); ?>
		<?php if ( $adv ) : ?>
			<input type="hidden" name="wpdocs_mode" value="advanced" />
		<?php endif; ?>
		<?php
		View::field(
			array(
				'for'     => 'wp_post_id',
				'label'   => __( '文章 ID', 'wpdocs' ),
				'bad'     => __( '请填写有效的文章 ID。', 'wpdocs' ),
				'help'    => __( 'WordPress 文章或页面的数字 ID。', 'wpdocs' ),
				'control' => '<input id="wp_post_id" type="number" name="wp_post_id" required min="1" />',
			)
		);
		View::field(
			array(
				'for'     => 'wp_post_type',
				'label'   => __( '文章类型', 'wpdocs' ),
				'control' => '<select id="wp_post_type" name="wp_post_type">'
					. '<option value="post" selected>' . esc_html__( '文章', 'wpdocs' ) . '</option>'
					. '<option value="page">' . esc_html__( '页面', 'wpdocs' ) . '</option>'
					. '</select>',
			)
		);
		View::field(
			array(
				'for'     => 'docs_book_slug',
				'label'   => __( '书册别名', 'wpdocs' ),
				'help'    => __( '文档站书册 slug，与下方页面别名一起定位一页。', 'wpdocs' ),
				'control' => '<input id="docs_book_slug" type="text" name="docs_book_slug" autocomplete="off" />',
			)
		);
		View::field(
			array(
				'for'     => 'docs_page_slug',
				'label'   => __( '页面别名', 'wpdocs' ),
				'control' => '<input id="docs_page_slug" type="text" name="docs_page_slug" autocomplete="off" />',
			)
		);
		View::field(
			array(
				'for'      => 'docs_page_id',
				'label'    => __( '文档页 ID', 'wpdocs' ),
				'help'     => __( '有数字 ID 时可填；没有可留 0，靠别名定位。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="docs_page_id" type="number" name="docs_page_id" min="0" value="0" />',
			)
		);
		View::field(
			array(
				'for'      => 'external_id',
				'label'    => __( '外部 ID', 'wpdocs' ),
				'help'     => __( '可选。评论挂件用来对文章，例如 wpcy:post:42。不要填密钥或邮箱。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="external_id" type="text" name="external_id" placeholder="wpcy:post:42" autocomplete="off" />',
			)
		);
		View::field(
			array(
				'for'      => 'bind_source',
				'label'    => __( '来源', 'wpdocs' ),
				'help'     => __( '别名匹配可按文章别名对照文档页别名。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<select id="bind_source" name="source">'
					. '<option value="manual" selected>' . esc_html__( '手工', 'wpdocs' ) . '</option>'
					. '<option value="slug">' . esc_html__( '别名匹配', 'wpdocs' ) . '</option>'
					. '<option value="import">' . esc_html__( '导入', 'wpdocs' ) . '</option>'
					. '</select>',
			)
		);
		View::field(
			array(
				'for'      => 'bind_status',
				'label'    => __( '状态', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<select id="bind_status" name="status">'
					. '<option value="active" selected>' . esc_html__( '已启用', 'wpdocs' ) . '</option>'
					. '<option value="disabled">' . esc_html__( '已停用', 'wpdocs' ) . '</option>'
					. '</select>',
			)
		);
		$resolve_ctl  = '<div class="field-ctl-row">';
		$resolve_ctl .= '<input id="resolve-post" type="number" min="1" placeholder="' . esc_attr__( '文章 ID 或文档页 ID', 'wpdocs' ) . '" autocomplete="off" />';
		$resolve_ctl .= '<button type="button" class="btn btn-ghost" data-wpdocs-resolve>' . esc_html__( '反查', 'wpdocs' ) . '</button>';
		$resolve_ctl .= '</div><p class="hint d">' . esc_html__( '按文章 ID 或文档页 ID 查已有绑定。不写命中、不改表。', 'wpdocs' ) . '</p>';
		$resolve_ctl .= '<div class="preview-out" data-resolve-out hidden></div>';
		View::field(
			array(
				'for'      => 'resolve-post',
				'label'    => __( '按 ID 反查', 'wpdocs' ),
				'advanced' => true,
				'control'  => $resolve_ctl,
			)
		);
		?>
		<?php if ( $adv ) : ?>
			<div class="field is-note" data-for-mode="advanced">
				<div class="field-label"><?php esc_html_e( '文章列表', 'wpdocs' ); ?></div>
				<div class="field-ctl"><p class="hint"><?php esc_html_e( '文章列表会多一列「文档绑定」，显示书册/页面别名。该列不套本页顶栏。', 'wpdocs' ); ?></p></div>
			</div>
		<?php endif; ?>
		<div class="wenpai-actions"><button type="submit" class="btn btn-primary"><?php esc_html_e( '保存绑定', 'wpdocs' ); ?></button></div>
	</form>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '已有绑定', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '点「编辑」回填上方表单。删除前先勾确认门。', 'wpdocs' ); ?></p>
	<?php if ( array() === $items ) : ?>
		<p class="empty"><?php esc_html_e( '还没有文章绑定到文档页。保存上方表单后会出现在这里。', 'wpdocs' ); ?></p>
	<?php else : ?>
		<div class="wenpai-gate">
			<label>
				<input type="checkbox" />
				<span><?php esc_html_e( '我确认要删除下方绑定行。', 'wpdocs' ); ?></span>
			</label>
		</div>
		<table class="tbl">
			<thead>
				<tr>
					<th><?php esc_html_e( '文章', 'wpdocs' ); ?></th>
					<th><?php esc_html_e( '文档页', 'wpdocs' ); ?></th>
					<?php if ( $adv ) : ?>
						<th><?php esc_html_e( '外部 ID', 'wpdocs' ); ?></th>
						<th><?php esc_html_e( '来源', 'wpdocs' ); ?></th>
					<?php endif; ?>
					<th><?php esc_html_e( '状态', 'wpdocs' ); ?></th>
					<th><?php esc_html_e( '操作', 'wpdocs' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $items as $row ) : ?>
				<?php
				$pill = View::bind_status_pill( (string) ( $row->status ?? 'active' ) );
				$ext  = (string) ( $row->external_id ?? '' );
				$type = (string) ( $row->wp_post_type ?? 'post' );
				$type_label = 'page' === $type ? __( '页面', 'wpdocs' ) : __( '文章', 'wpdocs' );
				$fill = wp_json_encode(
					array(
						'wp_post_id'     => (int) $row->wp_post_id,
						'wp_post_type'   => $type,
						'docs_page_id'   => (int) $row->docs_page_id,
						'docs_book_slug' => (string) $row->docs_book_slug,
						'docs_page_slug' => (string) $row->docs_page_slug,
						'external_id'    => $ext,
						'source'         => (string) ( $row->source ?? 'manual' ),
						'status'         => (string) ( $row->status ?? 'active' ),
					)
				);
				$row_id = (int) $row->id;
				?>
				<tr>
					<td><?php echo (int) $row->wp_post_id; ?> <code><?php echo esc_html( $type_label ); ?></code></td>
					<td>
						<?php echo esc_html( $row->docs_book_slug . '/' . $row->docs_page_slug ); ?>
						<?php if ( $adv ) : ?>
							<code>#<?php echo (int) $row->docs_page_id; ?></code>
						<?php endif; ?>
					</td>
					<?php if ( $adv ) : ?>
						<td><?php echo '' !== $ext ? '<code>' . esc_html( $ext ) . '</code>' : '—'; ?></td>
						<td><?php echo esc_html( View::bind_source_label( (string) $row->source ) ); ?></td>
					<?php endif; ?>
					<td><span class="pill <?php echo esc_attr( $pill['class'] ); ?>"><?php echo esc_html( $pill['label'] ); ?></span></td>
					<td class="wpdocs-row-actions">
						<button type="button" class="btn btn-ghost" data-wpdocs-fill="<?php echo esc_attr( (string) $fill ); ?>"><?php esc_html_e( '编辑', 'wpdocs' ); ?></button>
						<?php if ( 'disabled' === (string) ( $row->status ?? '' ) ) : ?>
							<form method="post" class="wpdocs-inline-form">
								<?php wp_nonce_field( 'wpdocs_bind_row', 'wpdocs_bind_row_nonce' ); ?>
								<input type="hidden" name="wpdocs_bind_row_id" value="<?php echo $row_id; ?>" />
								<input type="hidden" name="wpdocs_bind_row_action" value="enable" />
								<?php if ( $adv ) : ?><input type="hidden" name="wpdocs_mode" value="advanced" /><?php endif; ?>
								<button type="submit" class="btn btn-ghost"><?php esc_html_e( '启用', 'wpdocs' ); ?></button>
							</form>
						<?php else : ?>
							<form method="post" class="wpdocs-inline-form">
								<?php wp_nonce_field( 'wpdocs_bind_row', 'wpdocs_bind_row_nonce' ); ?>
								<input type="hidden" name="wpdocs_bind_row_id" value="<?php echo $row_id; ?>" />
								<input type="hidden" name="wpdocs_bind_row_action" value="disable" />
								<?php if ( $adv ) : ?><input type="hidden" name="wpdocs_mode" value="advanced" /><?php endif; ?>
								<button type="submit" class="btn btn-ghost"><?php esc_html_e( '停用', 'wpdocs' ); ?></button>
							</form>
						<?php endif; ?>
						<form method="post" class="wpdocs-inline-form">
							<?php wp_nonce_field( 'wpdocs_bind_row', 'wpdocs_bind_row_nonce' ); ?>
							<input type="hidden" name="wpdocs_bind_row_id" value="<?php echo $row_id; ?>" />
							<input type="hidden" name="wpdocs_bind_row_action" value="delete" />
							<?php if ( $adv ) : ?><input type="hidden" name="wpdocs_mode" value="advanced" /><?php endif; ?>
							<button type="submit" class="btn btn-ghost" data-wenpai-need-apply disabled><?php esc_html_e( '删除', 'wpdocs' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
