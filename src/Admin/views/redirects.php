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

$adv = View::is_advanced();
ob_start();
View::mode_toggle( 'wpdocs-redirects' );
$mode_html = ob_get_clean();

View::page_head(
	__( '跳转', 'wpdocs' ),
	__( '旧 WordPress 路径转到当前文档页，或返回 410。试跳转不写命中次数。', 'wpdocs' ),
	$mode_html
);
View::saved_notice();
View::error_notice();

if ( $adv ) :
	?>
	<div class="notice info"><span><?php esc_html_e( '激活时若跳转表为空且存在旧跳转表，会一次性导入。目标用文档页 ID，不写死 URL。', 'wpdocs' ); ?></span></div>
	<?php
endif;
?>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '手工跳转', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '路径写成 /path。已匹配走 301，已失效走 410。', 'wpdocs' ); ?></p>
	<form method="post">
		<?php wp_nonce_field( 'wpdocs_redirect', 'wpdocs_redirect_nonce' ); ?>
		<?php if ( $adv ) : ?>
			<input type="hidden" name="wpdocs_mode" value="advanced" />
		<?php endif; ?>
		<?php
		View::field(
			array(
				'for'     => 'wp_path',
				'label'   => __( '旧路径', 'wpdocs' ),
				'bad'     => __( '请填写以 / 开头的路径。', 'wpdocs' ),
				'help'    => __( '保存前会规范成以 / 开头的路径，同一路径只有一条。不会劫持 wp-admin、wp-json 等保留路径。', 'wpdocs' ),
				'control' => '<input id="wp_path" type="text" name="wp_path" placeholder="/document/old" required autocomplete="off" />',
			)
		);
		View::field(
			array(
				'for'     => 'page_id',
				'label'   => __( '文档页 ID', 'wpdocs' ),
				'help'    => __( '已匹配时填写目标文档页。已失效可填 0。', 'wpdocs' ),
				'control' => '<input id="page_id" type="number" name="page_id" min="0" />',
			)
		);
		View::field(
			array(
				'for'     => 'status',
				'label'   => __( '状态', 'wpdocs' ),
				'control' => '<select id="status" name="status">'
					. '<option value="matched">' . esc_html__( '已匹配（301）', 'wpdocs' ) . '</option>'
					. '<option value="gone">' . esc_html__( '已失效（410）', 'wpdocs' ) . '</option>'
					. '<option value="pending">' . esc_html__( '待处理', 'wpdocs' ) . '</option>'
					. '<option value="unmatched">' . esc_html__( '未匹配', 'wpdocs' ) . '</option>'
					. '</select>',
			)
		);
		View::field(
			array(
				'for'      => 'wp_title',
				'label'    => __( '原标题', 'wpdocs' ),
				'help'     => __( '便于辨认旧文，不参与跳转计算。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wp_title" type="text" name="wp_title" autocomplete="off" />',
			)
		);
		$dry_ctl  = '<div class="field-ctl-row">';
		$dry_ctl .= '<input id="dry-path" type="text" placeholder="/document/old" autocomplete="off" />';
		$dry_ctl .= '<button type="button" class="btn btn-ghost" data-wpdocs-dry-run>' . esc_html__( '试跳转', 'wpdocs' ) . '</button>';
		$dry_ctl .= '</div><p class="hint d">' . esc_html__( '试一条旧路径会怎样跳，不增加命中次数。', 'wpdocs' ) . '</p>';
		$dry_ctl .= '<div class="preview-out" data-dry-out hidden></div>';
		View::field(
			array(
				'for'      => 'dry-path',
				'label'    => __( '试跳转', 'wpdocs' ),
				'advanced' => true,
				'control'  => $dry_ctl,
			)
		);
		?>
		<div class="wenpai-actions"><button type="submit" class="btn btn-primary"><?php esc_html_e( '保存跳转', 'wpdocs' ); ?></button></div>
	</form>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '已有跳转', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '已匹配走 301，已失效走 410。长列表截断显示，不另做分页。', 'wpdocs' ); ?></p>
	<?php if ( array() === $items ) : ?>
		<p class="empty"><?php esc_html_e( '还没有旧路径跳转。启用本插件时若存在旧跳转表，会一次性导入；也可以在上方手工添加。', 'wpdocs' ); ?></p>
	<?php else : ?>
		<table class="tbl">
			<thead>
				<tr>
					<th><?php esc_html_e( '旧路径', 'wpdocs' ); ?></th>
					<th><?php esc_html_e( '文档页', 'wpdocs' ); ?></th>
					<th><?php esc_html_e( '状态', 'wpdocs' ); ?></th>
					<th><?php esc_html_e( '命中', 'wpdocs' ); ?></th>
					<th><?php esc_html_e( '最近命中', 'wpdocs' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $items as $row ) : ?>
				<?php
				$pill = View::redirect_status_pill( (string) $row->status );
				$hit  = ! empty( $row->last_hit ) ? (string) $row->last_hit : '—';
				?>
				<tr>
					<td><code><?php echo esc_html( $row->wp_path ); ?></code></td>
					<td class="num"><?php echo (int) $row->page_id; ?></td>
					<td><span class="pill <?php echo esc_attr( $pill['class'] ); ?>"><?php echo esc_html( $pill['label'] ); ?></span></td>
					<td class="num"><?php echo (int) $row->hits; ?></td>
					<td><?php echo esc_html( $hit ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</section>
