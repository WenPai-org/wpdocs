<?php
/**
 * Settings — 三卡：模式 → 连接 → 其它。
 *
 * @package Wpdocs
 *
 * @var array<string,mixed> $s
 * @var array<string,mixed>|\WP_Error|null $books
 */

use Wpdocs\Admin\View;
use Wpdocs\Git\Bootstrap as GitBoot;
use Wpdocs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$adv = View::is_advanced();
ob_start();
View::mode_toggle( 'wpdocs-settings' );
$mode_html = ob_get_clean();

View::page_head(
	__( '设置', 'wpdocs' ),
	__( '先选内容从哪来，再填对应连接。', 'wpdocs' ),
	$mode_html
);
View::saved_notice();
View::error_notice();

$content_mode = Settings::content_mode();
$rollout_raw  = (string) ( $s['mode'] ?? Settings::MODE_PILOT );
$rollout      = in_array( $rollout_raw, array( Settings::MODE_PILOT, Settings::MODE_ALL ), true )
	? $rollout_raw
	: Settings::MODE_PILOT;

$content_ctl = View::choice_cards(
	'wpdocs[content_mode]',
	$content_mode,
	array(
		Settings::CONTENT_KEEP => array(
			'label' => __( '保留', 'wpdocs' ),
			'desc'  => __( '品牌站正文不动。', 'wpdocs' ),
			'icon'  => 'info',
		),
		Settings::CONTENT_COCOBOOK => array(
			'label' => __( '文档站', 'wpdocs' ),
			'desc'  => __( '打开文章时读 CocoBook。', 'wpdocs' ),
			'icon'  => 'link',
		),
		Settings::CONTENT_GIT => array(
			'label' => __( 'Git', 'wpdocs' ),
			'desc'  => __( '把仓库 Markdown 写入文章。', 'wpdocs' ),
			'icon'  => 'flash',
		),
	)
);

$rollout_ctl = View::choice_cards(
	'wpdocs[mode]',
	$rollout,
	array(
		Settings::MODE_PILOT => array(
			'label' => __( '试点', 'wpdocs' ),
			'desc'  => __( '只换指定文章。', 'wpdocs' ),
			'icon'  => 'flash',
		),
		Settings::MODE_ALL   => array(
			'label' => __( '全部', 'wpdocs' ),
			'desc'  => __( '凡能对应到文档页的都换。', 'wpdocs' ),
			'icon'  => 'link',
		),
	)
);

$token_locked = Settings::is_locked( 'token_secret' );
$token_saved  = '' !== (string) ( $s['token_secret'] ?? '' );
if ( $token_locked ) {
	$secret_ctl = '<code>' . esc_html__( '已由 wp-config.php 常量设置', 'wpdocs' ) . '</code>';
} else {
	$ph         = $token_saved ? esc_attr__( '已保存（留空不改）', 'wpdocs' ) : '';
	$secret_ctl = '<input id="wpdocs-token-secret" type="password" name="wpdocs[token_secret]" value="" autocomplete="new-password" placeholder="' . $ph . '" />';
	if ( $token_saved ) {
		$secret_ctl .= '<label class="chk" style="display:block;margin-top:8px"><input type="checkbox" name="wpdocs[token_secret_clear]" value="1" /> ' . esc_html__( '清除 Secret', 'wpdocs' ) . '</label>';
	}
}

$tid_ctl = Settings::is_locked( 'token_id' )
	? '<code>' . esc_html( (string) Settings::get( 'token_id' ) ) . '</code>'
	: '<input id="wpdocs-token-id" type="text" name="wpdocs[token_id]" value="' . esc_attr( (string) ( $s['token_id'] ?? '' ) ) . '" autocomplete="off" />';

$base_ctl = Settings::is_locked( 'base_url' )
	? '<code>' . esc_html( Settings::base_url() ) . '</code>'
	: '<input id="wpdocs-base-url" type="url" name="wpdocs[base_url]" value="' . esc_attr( (string) ( $s['base_url'] ?? '' ) ) . '" placeholder="https://docs.example.test" />';

$books_ctl = '';
if ( is_array( $books ) && ! is_wp_error( $books ) && ! empty( $books['data'] ) ) {
	$books_ctl .= '<div class="chk-grid wpdocs-books">';
	$books_ctl .= '<input type="hidden" name="wpdocs[books][]" value="" />';
	$picked     = Settings::books();
	foreach ( $books['data'] as $b ) {
		$bid = (int) ( $b['id'] ?? 0 );
		if ( $bid < 1 ) {
			continue;
		}
		$books_ctl .= '<label><input type="checkbox" name="wpdocs[books][]" value="' . esc_attr( (string) $bid ) . '" ' . checked( in_array( $bid, $picked, true ), true, false ) . ' /> '
			. esc_html( (string) ( $b['name'] ?? '' ) . ' (#' . $bid . ')' ) . '</label>';
	}
	$books_ctl .= '</div>';
} else {
	$books_ctl = '<p class="hint">' . esc_html__( '保存连接后列出可选书；也可只填站点标签。', 'wpdocs' ) . '</p>';
	foreach ( Settings::books() as $bid ) {
		$books_ctl .= '<input type="hidden" name="wpdocs[books][]" value="' . esc_attr( (string) $bid ) . '" />';
	}
}

$repos   = Settings::git_repositories();
$primary = array();
foreach ( $repos as $row ) {
	if ( is_array( $row ) && ( '' !== (string) ( $row['username'] ?? '' ) || '' !== (string) ( $row['repository'] ?? '' ) ) ) {
		$primary = $row;
		break;
	}
}
if ( empty( $primary ) ) {
	$primary = array(
		'username'    => '',
		'repository'  => '',
		'folder'      => '',
		'branch'      => 'main',
		'post_type'   => 'post',
		'post_author' => get_current_user_id() ?: 1,
	);
}
$git_slug = trim( (string) ( $primary['username'] ?? '' ) . '/' . (string) ( $primary['repository'] ?? '' ), '/' );

$git_token_saved = '' !== (string) ( $s['git_forgejo_token'] ?? '' );
$git_token_ctl   = '<input id="wpdocs-git-token" type="password" name="wpdocs[git_forgejo_token]" value="" autocomplete="new-password" placeholder="'
	. ( $git_token_saved ? esc_attr__( '已保存（留空不改）', 'wpdocs' ) : '' ) . '" />';
if ( $git_token_saved ) {
	$git_token_ctl .= '<label class="chk" style="display:block;margin-top:8px"><input type="checkbox" name="wpdocs[git_forgejo_token_clear]" value="1" /> '
		. esc_html__( '清除 Token', 'wpdocs' ) . '</label>';
}

$comments_on    = ! empty( $s['comments'] );
$redirects_on   = ! empty( $s['redirects'] );
$giw_standalone = GitBoot::standalone_active();
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ); ?>" class="wpdocs-settings-form" id="wpdocs-settings-form">
	<?php wp_nonce_field( 'wpdocs_settings_full', 'wpdocs_settings_full_nonce' ); ?>
	<?php if ( $adv ) : ?>
		<input type="hidden" name="wpdocs_mode" value="advanced" />
	<?php endif; ?>

	<section class="card">
		<h2 class="section-title"><?php esc_html_e( '内容从哪来', 'wpdocs' ); ?></h2>
		<?php
		View::field(
			array(
				'label'   => __( '模式', 'wpdocs' ),
				'control' => $content_ctl,
			)
		);
		?>
	</section>

	<section class="card" data-wpdocs-when-content="keep"<?php echo Settings::CONTENT_KEEP === $content_mode ? '' : ' hidden'; ?>>
		<h2 class="section-title"><?php esc_html_e( '保留正文', 'wpdocs' ); ?></h2>
		<p class="section-desc"><?php esc_html_e( '前台不换正文。导入请到工具 → 同步。', 'wpdocs' ); ?></p>
		<p><a class="btn btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=sync' ) ); ?>"><?php esc_html_e( '打开同步', 'wpdocs' ); ?></a></p>
		<?php
		// Import still needs Token; keep fields in advanced only.
		View::field(
			array(
				'for'      => 'wpdocs-base-url-keep',
				'label'    => __( '文档站地址', 'wpdocs' ),
				'help'     => __( '仅导入时需要。', 'wpdocs' ),
				'advanced' => true,
				'control'  => Settings::is_locked( 'base_url' )
					? '<code>' . esc_html( Settings::base_url() ) . '</code>'
					: '<input id="wpdocs-base-url-keep" type="url" name="wpdocs[base_url]" value="' . esc_attr( (string) ( $s['base_url'] ?? '' ) ) . '" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-token-id-keep',
				'label'    => __( 'Token ID', 'wpdocs' ),
				'advanced' => true,
				'control'  => Settings::is_locked( 'token_id' )
					? '<code>' . esc_html( (string) Settings::get( 'token_id' ) ) . '</code>'
					: '<input id="wpdocs-token-id-keep" type="text" name="wpdocs[token_id]" value="' . esc_attr( (string) ( $s['token_id'] ?? '' ) ) . '" autocomplete="off" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-token-secret-keep',
				'label'    => __( 'Token Secret', 'wpdocs' ),
				'advanced' => true,
				'control'  => $secret_ctl,
			)
		);
		?>
	</section>

	<section class="card" data-wpdocs-when-content="cocobook"<?php echo Settings::CONTENT_COCOBOOK === $content_mode ? '' : ' hidden'; ?>>
		<h2 class="section-title"><?php esc_html_e( '文档站连接', 'wpdocs' ); ?></h2>
		<p class="section-desc"><?php esc_html_e( '填地址与 Token，再选读哪些文章。', 'wpdocs' ); ?></p>
		<?php
		View::field(
			array(
				'for'     => 'wpdocs-base-url',
				'label'   => __( '地址', 'wpdocs' ),
				'help'    => __( 'CocoBook / BookStack 根地址。', 'wpdocs' ),
				'control' => $base_ctl,
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-token-id',
				'label'   => __( 'Token ID', 'wpdocs' ),
				'control' => $tid_ctl,
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-token-secret',
				'label'   => __( 'Token Secret', 'wpdocs' ),
				'help'    => __( '不回显；留空表示不修改。', 'wpdocs' ),
				'control' => $secret_ctl,
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-site-tag',
				'label'   => __( '站点标签', 'wpdocs' ),
				'help'    => __( '未勾选书目时，自动用带此标签的书。', 'wpdocs' ),
				'control' => '<input id="wpdocs-site-tag" type="text" name="wpdocs[site_tag]" value="' . esc_attr( (string) ( $s['site_tag'] ?? '' ) ) . '" />',
			)
		);
		View::field(
			array(
				'label'   => __( '书目', 'wpdocs' ),
				'control' => $books_ctl,
			)
		);
		View::field(
			array(
				'label'   => __( '读取范围', 'wpdocs' ),
				'control' => $rollout_ctl,
			)
		);
		?>
		<div class="field" data-wpdocs-when-mode="pilot"<?php echo Settings::MODE_PILOT === $rollout ? '' : ' hidden'; ?>>
			<label class="field-label" for="wpdocs-pilot"><?php esc_html_e( '试点文章 ID', 'wpdocs' ); ?></label>
			<div class="field-ctl">
				<input id="wpdocs-pilot" type="text" name="wpdocs[pilot_post_ids]" value="<?php echo esc_attr( implode( ', ', Settings::pilot_post_ids() ) ); ?>" />
				<p class="hint"><?php esc_html_e( '逗号分隔。', 'wpdocs' ); ?></p>
			</div>
		</div>
		<?php
		View::field(
			array(
				'for'      => 'wpdocs-tree-url',
				'label'    => __( '发布树地址', 'wpdocs' ),
				'help'     => __( '留空用「文档站/books」。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-tree-url" type="url" name="wpdocs[tree_url]" value="' . esc_attr( (string) ( $s['tree_url'] ?? '' ) ) . '" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-post-types',
				'label'    => __( '文章类型', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-post-types" type="text" name="wpdocs[post_types]" value="' . esc_attr( implode( ',', (array) ( $s['post_types'] ?? array( 'post' ) ) ) ) . '" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-ttl',
				'label'    => __( '缓存秒数', 'wpdocs' ),
				'help'     => __( '0 = 不过期。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-ttl" type="number" min="0" name="wpdocs[page_ttl]" value="' . esc_attr( (string) ( $s['page_ttl'] ?? 0 ) ) . '" />',
			)
		);
		?>
	</section>

	<section class="card" data-wpdocs-when-content="git"<?php echo Settings::CONTENT_GIT === $content_mode ? '' : ' hidden'; ?>>
		<h2 class="section-title"><?php esc_html_e( 'Git 连接', 'wpdocs' ); ?></h2>
		<p class="section-desc"><?php esc_html_e( '填 Forgejo 与一个仓库。拉取在工具 → 同步。', 'wpdocs' ); ?></p>
		<?php if ( $giw_standalone ) : ?>
			<div class="notice warn"><span><?php esc_html_e( '已装独立 Git it Write。请停用后再用本页，或继续用那个插件。', 'wpdocs' ); ?></span></div>
		<?php endif; ?>
		<?php
		View::field(
			array(
				'for'     => 'wpdocs-git-url',
				'label'   => __( 'Forgejo 地址', 'wpdocs' ),
				'control' => '<input id="wpdocs-git-url" type="url" name="wpdocs[git_forgejo_url]" value="' . esc_attr( (string) ( $s['git_forgejo_url'] ?? '' ) ) . '" placeholder="https://feicode.com" />',
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-git-token',
				'label'   => __( 'Token', 'wpdocs' ),
				'control' => $git_token_ctl,
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-git-slug',
				'label'   => __( '仓库', 'wpdocs' ),
				'help'    => __( '格式 owner/name。', 'wpdocs' ),
				'control' => '<input id="wpdocs-git-slug" type="text" name="wpdocs[git_repo_slug]" value="' . esc_attr( $git_slug ) . '" placeholder="WenPai-org/docs" />',
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-git-folder',
				'label'   => __( '子目录', 'wpdocs' ),
				'control' => '<input id="wpdocs-git-folder" type="text" name="wpdocs[git_repo_folder]" value="' . esc_attr( (string) ( $primary['folder'] ?? '' ) ) . '" placeholder="docs" />',
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-git-branch',
				'label'   => __( '分支', 'wpdocs' ),
				'control' => '<input id="wpdocs-git-branch" type="text" name="wpdocs[git_repo_branch]" value="' . esc_attr( (string) ( $primary['branch'] ?? 'main' ) ) . '" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-git-webhook',
				'label'    => __( 'Webhook Secret', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-git-webhook" type="text" name="wpdocs[git_webhook_secret]" value="' . esc_attr( (string) ( $s['git_webhook_secret'] ?? '' ) ) . '" autocomplete="off" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-git-base',
				'label'    => __( '本地目录', 'wpdocs' ),
				'help'     => __( '填了则走本地扫描，忽略上方远程仓库。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-git-base" type="text" name="wpdocs[git_base_directory]" value="' . esc_attr( (string) ( $s['git_base_directory'] ?? '' ) ) . '" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-git-ptype',
				'label'    => __( '文章类型', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-git-ptype" type="text" name="wpdocs[git_default_post_type]" value="' . esc_attr( (string) ( $s['git_default_post_type'] ?? 'post' ) ) . '" />',
			)
		);
		?>
		<input type="hidden" name="wpdocs[git_repo_post_type]" value="<?php echo esc_attr( (string) ( $primary['post_type'] ?? 'post' ) ); ?>" />
		<input type="hidden" name="wpdocs[git_repo_post_author]" value="<?php echo esc_attr( (string) (int) ( $primary['post_author'] ?? 1 ) ); ?>" />
		<input type="hidden" name="wpdocs[git_repo_last_publish]" value="<?php echo esc_attr( (string) (int) ( $primary['last_publish'] ?? 0 ) ); ?>" />
	</section>

	<section class="card">
		<h2 class="section-title"><?php esc_html_e( '其它', 'wpdocs' ); ?></h2>
		<div class="simple-row is-action">
			<div class="tile"><?php echo View::icon( 'feedback' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div>
				<div class="t"><?php esc_html_e( '统一评论', 'wpdocs' ); ?></div>
				<div class="d"><?php esc_html_e( '与文档站同一帖。', 'wpdocs' ); ?></div>
			</div>
			<div class="r"><?php echo View::toggle_input( 'wpdocs[comments]', $comments_on ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<div class="field" data-wpdocs-when-on="comments"<?php echo $comments_on ? '' : ' hidden'; ?>>
			<label class="field-label" for="wpdocs-bff"><?php esc_html_e( '评论服务', 'wpdocs' ); ?></label>
			<div class="field-ctl">
				<input id="wpdocs-bff" type="url" name="wpdocs[bff_url]" value="<?php echo esc_attr( (string) ( $s['bff_url'] ?? '' ) ); ?>" />
			</div>
		</div>
		<div class="simple-row is-action">
			<div class="tile"><?php echo View::icon( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div>
				<div class="t"><?php esc_html_e( '旧链接跳转', 'wpdocs' ); ?></div>
				<div class="d"><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=redirects' ) ); ?>"><?php esc_html_e( '管理映射', 'wpdocs' ); ?></a></div>
			</div>
			<div class="r"><?php echo View::toggle_input( 'wpdocs[redirects]', $redirects_on ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<?php
		View::field(
			array(
				'for'      => 'wpdocs-minc',
				'label'    => __( '旧评论迁移阈值', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-minc" type="number" min="1" max="1000" name="wpdocs[comment_min_chars]" value="' . esc_attr( (string) ( $s['comment_min_chars'] ?? 30 ) ) . '" />',
			)
		);
		?>
		<div class="wenpai-actions">
			<button type="submit" class="btn btn-primary"><?php esc_html_e( '保存', 'wpdocs' ); ?></button>
		</div>
	</section>
</form>
