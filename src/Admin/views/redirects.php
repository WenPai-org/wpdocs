<?php
/**
 * Redirects page (档 A).
 *
 * Simple: path + target page id + status.
 * Advanced: title + dry-run.
 *
 * @package Wpdocs
 * @var array<int,object> $items
 * @var string            $filter
 */

use Wpdocs\Admin\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$embed  = ! empty( $wpdocs_embed );
$adv    = View::is_advanced();
$filter = isset( $filter ) ? (string) $filter : '';
ob_start();
View::mode_toggle( 'wpdocs-tools' );
$mode_html = ob_get_clean();

if ( ! $embed ) {
	View::page_head( __( '跳转', 'wpdocs' ), __( '旧文章地址转到文档站。没对上就回原文章，不拦截。', 'wpdocs' ), $mode_html );
	View::saved_notice();
	View::error_notice();
} else {
	echo $mode_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
if ( $adv ) :
	?>
	<div class="notice info" data-for-mode="advanced"><span><?php esc_html_e( '启用时若跳转表为空且存在旧跳转表，会一次性导入。目标用文档页 ID，不写死网址。', 'wpdocs' ); ?></span></div>
	<?php
endif;
?>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '手工映射', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '填旧路径和文档页 ID。简单模式只留这三项；试解析放在高级。', 'wpdocs' ); ?></p>
	<form method="post" id="wpdocs-redir-form">
		<?php wp_nonce_field( 'wpdocs_redir', 'wpdocs_redir_nonce' ); ?>
		<?php if ( $adv ) : ?>
			<input type="hidden" name="wpdocs_mode" value="advanced" />
		<?php endif; ?>
		<?php
		View::field(
			array(
				'for'     => 'wp_path',
				'label'   => __( '旧路径', 'wpdocs' ),
				'bad'     => __( '请填写以 / 开头的旧路径。', 'wpdocs' ),
				'help'    => __( '站内路径，例如 /2020/01/hello/。不要带域名。', 'wpdocs' ),
				'control' => '<input id="wp_path" type="text" name="wp_path" required placeholder="/old-post/" autocomplete="off" />',
			)
		);
		View::field(
			array(
				'for'     => 'docs_page_id',
				'label'   => __( '文档页 ID', 'wpdocs' ),
				'bad'     => __( '已匹配时请填写文档页 ID。', 'wpdocs' ),
				'control' => '<input id="docs_page_id" type="number" name="docs_page_id" min="0" value="0" />',
			)
		);
		View::field(
			array(
				'for'     => 'redir_status',
				'label'   => __( '状态', 'wpdocs' ),
				'help'    => __( '已匹配会 301；已下线回 410。未匹配与待处理不跳转。', 'wpdocs' ),
				'control' => '<select id="redir_status" name="status">'
					. '<option value="matched" selected>' . esc_html__( '已匹配', 'wpdocs' ) . '</option>'
					. '<option value="gone">' . esc_html__( '已下线', 'wpdocs' ) . '</option>'
					. '<option value="unmatched">' . esc_html__( '未匹配', 'wpdocs' ) . '</option>'
					. '<option value="pending">' . esc_html__( '待处理', 'wpdocs' ) . '</option>'
					. '</select>',
			)
		);
		View::field(
			array(
				'for'      => 'wp_title',
				'label'    => __( '原标题', 'wpdocs' ),
				'help'     => __( '可选，方便对照。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wp_title" type="text" name="wp_title" autocomplete="off" />',
			)
		);
		$dry_ctl  = '<div class="field-ctl-row">';
		$dry_ctl .= '<input id="dry-run-path" type="text" placeholder="/old-post/" autocomplete="off" />';
		$dry_ctl .= '<button type="button" class="btn btn-ghost" data-wpdocs-dry-run>' . View::icon( 'eye' ) . esc_html__( '试一下', 'wpdocs' ) . '</button>';
		$dry_ctl .= '</div><p class="hint d">' . esc_html__( '只告诉你会不会跳、跳到哪。不真跳、不改表。', 'wpdocs' ) . '</p>';
		$dry_ctl .= '<div class="preview-out" data-dry-run-out hidden></div>';
		View::field(
			array(
				'for'      => 'dry-run-path',
				'label'    => __( '试解析', 'wpdocs' ),
				'advanced' => true,
				'control'  => $dry_ctl,
			)
		);
		?>
		<div class="wenpai-actions"><button type="submit" class="btn btn-primary"><?php esc_html_e( '保存映射', 'wpdocs' ); ?></button></div>
	</form>
</section>

<section class="card">
	<div class="wpdocs-list-head">
		<div>
			<h2 class="section-title"><?php esc_html_e( '已有映射', 'wpdocs' ); ?></h2>
			<p class="section-desc"><?php esc_html_e( '点「编辑」回填上方表单。删除前先勾确认门。', 'wpdocs' ); ?></p>
		</div>
		<form method="get" class="wpdocs-filter">
			<input type="hidden" name="page" value="wpdocs-redirects" />
			<?php if ( $adv ) : ?>
				<input type="hidden" name="mode" value="advanced" />
			<?php endif; ?>
			<label class="sr-only" for="redir-filter"><?php esc_html_e( '按状态筛选', 'wpdocs' ); ?></label>
			<select id="redir-filter" name="status" onchange="this.form.submit()">
				<option value="" <?php selected( $filter, '' ); ?>><?php esc_html_e( '全部', 'wpdocs' ); ?></option>
				<option value="matched" <?php selected( $filter, 'matched' ); ?>><?php esc_html_e( '已匹配', 'wpdocs' ); ?></option>
				<option value="gone" <?php selected( $filter, 'gone' ); ?>><?php esc_html_e( '已下线', 'wpdocs' ); ?></option>
				<option value="unmatched" <?php selected( $filter, 'unmatched' ); ?>><?php esc_html_e( '未匹配', 'wpdocs' ); ?></option>
				<option value="pending" <?php selected( $filter, 'pending' ); ?>><?php esc_html_e( '待处理', 'wpdocs' ); ?></option>
			</select>
		</form>
	</div>
	<?php if ( array() === $items ) : ?>
		<p class="empty"><?php esc_html_e( '还没有路径映射。保存上方表单后会出现在这里。', 'wpdocs' ); ?></p>
	<?php else : ?>
		<div class="wenpai-gate">
			<label>
				<input type="checkbox" />
				<span><?php esc_html_e( '我确认要删除下方映射行。', 'wpdocs' ); ?></span>
			</label>
		</div>
		<table class="tbl">
			<thead>
				<tr>
					<th><?php esc_html_e( '旧路径', 'wpdocs' ); ?></th>
					<?php if ( $adv ) : ?>
						<th><?php esc_html_e( '原标题', 'wpdocs' ); ?></th>
					<?php endif; ?>
					<th><?php esc_html_e( '文档页', 'wpdocs' ); ?></th>
					<th><?php esc_html_e( '状态', 'wpdocs' ); ?></th>
					<th><?php esc_html_e( '操作', 'wpdocs' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $items as $row ) : ?>
				<?php
				$pill  = View::redir_status_pill( (string) ( $row->status ?? 'pending' ) );
				$title = (string) ( $row->wp_title ?? '' );
				$page  = (int) ( $row->page_id ?? 0 );
				$st    = (string) ( $row->status ?? 'matched' );
				$fill  = wp_json_encode(
					array(
						'wp_path'      => (string) $row->wp_path,
						'wp_title'     => $title,
						'docs_page_id' => $page,
						'status'       => $st,
					)
				);
				$row_id = (int) $row->id;
				?>
				<tr>
					<td><code><?php echo esc_html( (string) $row->wp_path ); ?></code></td>
					<?php if ( $adv ) : ?>
						<td><?php echo '' !== $title ? esc_html( $title ) : '—'; ?></td>
					<?php endif; ?>
					<td><?php echo $page > 0 ? '#' . $page : '—'; ?></td>
					<td><span class="pill <?php echo esc_attr( $pill['class'] ); ?>"><?php echo esc_html( $pill['label'] ); ?></span></td>
					<td class="wpdocs-row-actions">
						<button type="button" class="btn btn-ghost" data-wpdocs-fill="<?php echo esc_attr( (string) $fill ); ?>"><?php esc_html_e( '编辑', 'wpdocs' ); ?></button>
						<?php if ( 'matched' !== $st ) : ?>
							<form method="post" class="wpdocs-inline-form">
								<?php wp_nonce_field( 'wpdocs_redir_row', 'wpdocs_redir_row_nonce' ); ?>
								<input type="hidden" name="wpdocs_redir_row_id" value="<?php echo $row_id; ?>" />
								<input type="hidden" name="wpdocs_redir_row_action" value="matched" />
								<?php if ( $adv ) : ?><input type="hidden" name="wpdocs_mode" value="advanced" /><?php endif; ?>
								<?php if ( '' !== $filter ) : ?><input type="hidden" name="wpdocs_redir_filter" value="<?php echo esc_attr( $filter ); ?>" /><?php endif; ?>
								<button type="submit" class="btn btn-ghost"><?php esc_html_e( '启用', 'wpdocs' ); ?></button>
							</form>
						<?php else : ?>
							<form method="post" class="wpdocs-inline-form">
								<?php wp_nonce_field( 'wpdocs_redir_row', 'wpdocs_redir_row_nonce' ); ?>
								<input type="hidden" name="wpdocs_redir_row_id" value="<?php echo $row_id; ?>" />
								<input type="hidden" name="wpdocs_redir_row_action" value="gone" />
								<?php if ( $adv ) : ?><input type="hidden" name="wpdocs_mode" value="advanced" /><?php endif; ?>
								<?php if ( '' !== $filter ) : ?><input type="hidden" name="wpdocs_redir_filter" value="<?php echo esc_attr( $filter ); ?>" /><?php endif; ?>
								<button type="submit" class="btn btn-ghost"><?php esc_html_e( '下线', 'wpdocs' ); ?></button>
							</form>
						<?php endif; ?>
						<form method="post" class="wpdocs-inline-form">
							<?php wp_nonce_field( 'wpdocs_redir_row', 'wpdocs_redir_row_nonce' ); ?>
							<input type="hidden" name="wpdocs_redir_row_id" value="<?php echo $row_id; ?>" />
							<input type="hidden" name="wpdocs_redir_row_action" value="delete" />
							<?php if ( $adv ) : ?><input type="hidden" name="wpdocs_mode" value="advanced" /><?php endif; ?>
							<?php if ( '' !== $filter ) : ?><input type="hidden" name="wpdocs_redir_filter" value="<?php echo esc_attr( $filter ); ?>" /><?php endif; ?>
							<button type="submit" class="btn btn-ghost" data-wenpai-need-apply disabled><?php esc_html_e( '删除', 'wpdocs' ); ?></button>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
