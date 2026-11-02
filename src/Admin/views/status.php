<?php
/**
 * Status / overview (档 A).
 *
 * @package Wpdocs
 * @var array<string,mixed> $st
 */

use Wpdocs\Admin\View;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$home_ok   = ! empty( $st['home']['ok'] );
$site_ok   = ! empty( $st['sitemap']['ok'] );
$tree_skip = ! empty( $st['tree']['skipped'] );
$tree_ok   = ! empty( $st['tree']['ok'] );
$bind_n    = (int) \Wpdocs\Bind\Repository::count();
$redir_n   = (int) \Wpdocs\Redirect\Repository::count();
$opt       = get_option( 'wpdocs_settings', array() );
$base_in   = is_array( $opt ) ? (string) ( $opt['base_url'] ?? ( $st['base_url'] ?? '' ) ) : '';
$tree_in   = is_array( $opt ) && array_key_exists( 'tree_url', $opt ) ? (string) $opt['tree_url'] : '';
$base_show = (string) ( $st['base_url'] ?? '' );

$fmt_probe = static function ( array $p ): string {
	$code = (int) ( $p['code'] ?? 0 );
	$ms   = (int) ( $p['ms'] ?? 0 );
	$err  = (string) ( $p['error'] ?? '' );
	if ( $err ) {
		return sprintf( '%s · %dms', $err, $ms );
	}
	if ( 0 === $code && 0 === $ms && '' === $base_show ) {
		return __( '尚未探测', 'wpdocs' );
	}
	return sprintf( '%d · %dms', $code, $ms );
};

$refresh = '<a class="btn btn-ghost" href="' . esc_url( admin_url( 'admin.php?page=wpdocs&refresh=1' ) ) . '">' . esc_html__( '重新探测', 'wpdocs' ) . '</a>';

View::page_head(
	__( '状态', 'wpdocs' ),
	__( '文档站与发布树只读探测。正文不会写入 option。', 'wpdocs' ),
	$refresh
);
View::saved_notice();
View::error_notice();

$partial_ok = $site_ok || ( ! $tree_skip && $tree_ok );

if ( '' === $base_show ) :
	?>
	<div class="notice warn"><span><?php esc_html_e( '尚未填写文档站地址。探测会在保存地址后出现结果。', 'wpdocs' ); ?></span></div>
	<?php
elseif ( ! $home_ok && ! $partial_ok ) :
	?>
	<div class="notice bad"><span><?php esc_html_e( '暂时无法探测文档站，请检查文档站地址后重试。', 'wpdocs' ); ?></span></div>
	<?php
elseif ( ! $home_ok && $partial_ok ) :
	?>
	<div class="notice warn"><span><?php esc_html_e( '首页探测超时或失败，但站点地图或发布树仍可达。可点「重新探测」再试。', 'wpdocs' ); ?></span></div>
	<?php
else :
	?>
	<div class="notice info"><span><?php esc_html_e( '若站点曾使用旧文档桥，启用本插件时会把旧跳转一次性导入。', 'wpdocs' ); ?></span></div>
	<?php
endif;

if ( 0 === $bind_n && 0 === $redir_n ) :
	?>
	<div class="next">
		<div class="tile accent"><?php echo View::icon( 'map' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<strong><?php esc_html_e( '还没有绑定或跳转', 'wpdocs' ); ?></strong>
			<p class="d"><?php esc_html_e( '保存绑定后这里会出现计数；若站点曾用旧文档桥，启用本插件时会一次性导入跳转。', 'wpdocs' ); ?></p>
		</div>
		<a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-bindings' ) ); ?>"><?php esc_html_e( '去绑定', 'wpdocs' ); ?></a>
	</div>
	<?php
elseif ( ! $home_ok && ! $partial_ok && '' !== $base_show ) :
	?>
	<div class="next">
		<div class="tile bad"><?php echo View::icon( 'external' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		<div>
			<strong><?php esc_html_e( '暂时无法探测文档站', 'wpdocs' ); ?></strong>
			<p class="d"><?php esc_html_e( '请检查文档站地址后重试。', 'wpdocs' ); ?></p>
		</div>
		<a class="btn btn-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs&refresh=1' ) ); ?>"><?php esc_html_e( '重新探测', 'wpdocs' ); ?></a>
	</div>
	<?php
endif;
?>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '探测', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php echo esc_html( sprintf( /* translators: %s: plugin version */ __( '版本 %s。发布树只读，不写文档站。', 'wpdocs' ), WPDOCS_VERSION ) ); ?></p>
	<?php
	View::status_row(
		array(
			'title'      => __( '文档站', 'wpdocs' ),
			'detail'     => '' !== $base_show ? $base_show : __( '未填写', 'wpdocs' ),
			'url'        => '' !== $base_show,
			'icon'       => 'external',
			'pill'       => $home_ok ? __( '已连接', 'wpdocs' ) : ( '' !== $base_show ? __( '探测失败', 'wpdocs' ) : __( '未连接', 'wpdocs' ) ),
			'pill_class' => $home_ok ? 'ok' : ( '' !== $base_show ? 'bad' : 'warn' ),
		)
	);
	View::status_row(
		array(
			'title'      => __( '首页', 'wpdocs' ),
			'detail'     => $fmt_probe( (array) ( $st['home'] ?? array() ) ),
			'icon'       => 'home',
			'pill'       => $home_ok ? __( '正常', 'wpdocs' ) : ( '' !== $base_show ? __( '失败', 'wpdocs' ) : __( '等待', 'wpdocs' ) ),
			'pill_class' => $home_ok ? 'ok' : ( '' !== $base_show ? 'bad' : 'warn' ),
		)
	);
	View::status_row(
		array(
			'title'      => __( '站点地图', 'wpdocs' ),
			'detail'     => $fmt_probe( (array) ( $st['sitemap'] ?? array() ) ),
			'icon'       => 'map',
			'pill'       => $site_ok ? __( '正常', 'wpdocs' ) : ( '' !== $base_show ? __( '异常', 'wpdocs' ) : __( '等待', 'wpdocs' ) ),
			'pill_class' => $site_ok ? 'ok' : 'warn',
		)
	);
	if ( $tree_skip ) {
		View::status_row(
			array(
				'title'      => __( '发布树', 'wpdocs' ),
				'detail'     => __( '已跳过（未配置或显式留空）', 'wpdocs' ),
				'icon'       => 'server',
				'pill'       => __( '已跳过', 'wpdocs' ),
				'pill_class' => 'warn',
			)
		);
	} else {
		View::status_row(
			array(
				'title'      => __( '发布树', 'wpdocs' ),
				'detail'     => $fmt_probe( (array) ( $st['tree'] ?? array() ) ),
				'icon'       => 'server',
				'pill'       => $tree_ok ? __( '正常', 'wpdocs' ) : __( '失败', 'wpdocs' ),
				'pill_class' => $tree_ok ? 'ok' : 'bad',
			)
		);
	}
	$api = isset( $st['api'] ) && is_array( $st['api'] ) ? $st['api'] : array( 'skipped' => true );
	if ( empty( $api ) || ! empty( $api['skipped'] ) || empty( $api['configured'] ) ) {
		View::status_row(
			array(
				'title'      => __( 'CocoBook API', 'wpdocs' ),
				'detail'     => __( '未配置 Token（WPDOCS_TOKEN_ID / SECRET）', 'wpdocs' ),
				'icon'       => 'plug',
				'pill'       => __( '已跳过', 'wpdocs' ),
				'pill_class' => 'warn',
			)
		);
	} else {
		$api_ok  = ! empty( $api['ok'] );
		$api_ver = (string) ( $api['version'] ?? '' );
		$api_ms  = (int) ( $api['ms'] ?? 0 );
		$api_err = (string) ( $api['error'] ?? '' );
		$detail  = $api_ok
			? ( '' !== $api_ver ? sprintf( '%s · %dms', $api_ver, $api_ms ) : sprintf( '%dms', $api_ms ) )
			: ( '' !== $api_err ? sprintf( '%s · %dms', $api_err, $api_ms ) : sprintf( '%dms', $api_ms ) );
		View::status_row(
			array(
				'title'      => __( 'CocoBook API', 'wpdocs' ),
				'detail'     => $detail,
				'icon'       => 'plug',
				'pill'       => $api_ok ? __( '已连接', 'wpdocs' ) : __( '探测失败', 'wpdocs' ),
				'pill_class' => $api_ok ? 'ok' : 'bad',
			)
		);
	}
	View::status_row(
		array(
			'title'      => __( '绑定', 'wpdocs' ),
			'detail'     => (string) $bind_n,
			'icon'       => 'link',
			'pill'       => $bind_n > 0 ? __( '已有', 'wpdocs' ) : __( '空', 'wpdocs' ),
			'pill_class' => $bind_n > 0 ? 'ok' : 'warn',
		)
	);
	View::status_row(
		array(
			'title'      => __( '跳转', 'wpdocs' ),
			'detail'     => (string) $redir_n,
			'icon'       => 'share',
			'pill'       => $redir_n > 0 ? __( '已有', 'wpdocs' ) : __( '空', 'wpdocs' ),
			'pill_class' => $redir_n > 0 ? 'ok' : 'warn',
		)
	);
	?>
</section>

<section class="card">
	<h2 class="section-title"><?php esc_html_e( '探测地址', 'wpdocs' ); ?></h2>
	<p class="section-desc"><?php esc_html_e( '也可用 wp-config 常量 WPDOCS_BASE_URL / WPDOCS_TREE_URL 覆盖。', 'wpdocs' ); ?></p>
	<form method="post">
		<?php wp_nonce_field( 'wpdocs_settings', 'wpdocs_settings_nonce' ); ?>
		<?php
		View::field(
			array(
				'for'     => 'wpdocs-base-url',
				'label'   => __( '文档站地址', 'wpdocs' ),
				'help'    => __( '用于首页与 /cocobook/sitemap.xml 的 HEAD 探测。', 'wpdocs' ),
				'control' => '<input id="wpdocs-base-url" type="url" name="base_url" value="' . esc_attr( $base_in ) . '" placeholder="https://docs.example.test" />',
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-tree-url',
				'label'   => __( '发布树地址（可选）', 'wpdocs' ),
				'help'    => __( '留空则用「文档站/books」；保存为空字符串则跳过发布树探测。', 'wpdocs' ),
				'control' => '<input id="wpdocs-tree-url" type="url" name="tree_url" value="' . esc_attr( $tree_in ) . '" />',
			)
		);
		?>
		<div class="wenpai-actions"><button type="submit" class="btn btn-primary"><?php esc_html_e( '保存并刷新探测', 'wpdocs' ); ?></button></div>
	</form>
</section>
