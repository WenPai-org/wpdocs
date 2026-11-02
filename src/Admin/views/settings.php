<?php
/**
 * Settings — 档 A：三种内容模式 + 条件连接。
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
	__( '三种模式：保留正文、CocoBook 读取、或从 Git 导入（整合 Git it Write）。', 'wpdocs' ),
	$mode_html
);
View::saved_notice();
View::error_notice();

$content_mode = Settings::content_mode();
// For CocoBook rollout UI, read stored mode even when content_mode temporarily not cocobook.
$rollout_raw  = (string) ( $s['mode'] ?? Settings::MODE_PILOT );
$rollout      = in_array( $rollout_raw, array( Settings::MODE_OFF, Settings::MODE_PILOT, Settings::MODE_ALL ), true )
	? $rollout_raw
	: Settings::MODE_PILOT;
if ( Settings::CONTENT_KEEP === $content_mode ) {
	$rollout = Settings::MODE_OFF;
}

$content_ctl = View::choice_cards(
	'wpdocs[content_mode]',
	$content_mode,
	array(
		Settings::CONTENT_KEEP => array(
			'label' => __( '保留', 'wpdocs' ),
			'desc'  => __( '不改正文。可在工具里一次性从 CocoBook 导入进文章。', 'wpdocs' ),
			'icon'  => 'info',
		),
		Settings::CONTENT_COCOBOOK => array(
			'label' => __( 'CocoBook 读取', 'wpdocs' ),
			'desc'  => __( '运行时用文档站正文替换品牌站文章（可试点）。', 'wpdocs' ),
			'icon'  => 'link',
		),
		Settings::CONTENT_GIT => array(
			'label' => __( 'Git 导入', 'wpdocs' ),
			'desc'  => __( '从 Forgejo / 本地仓把 Markdown 写入文章（Git it Write）。', 'wpdocs' ),
			'icon'  => 'flash',
		),
	)
);

$rollout_ctl = View::choice_cards(
	'wpdocs[mode]',
	$rollout,
	array(
		Settings::MODE_OFF   => array(
			'label' => __( '关闭读取', 'wpdocs' ),
			'desc'  => __( '已选 CocoBook，但暂不换正文。', 'wpdocs' ),
			'icon'  => 'info',
		),
		Settings::MODE_PILOT => array(
			'label' => __( '试点', 'wpdocs' ),
			'desc'  => __( '只接管下方填写的文章 ID。', 'wpdocs' ),
			'icon'  => 'flash',
		),
		Settings::MODE_ALL   => array(
			'label' => __( '全部', 'wpdocs' ),
			'desc'  => __( '接管所有能对应到文档页的文章。', 'wpdocs' ),
			'icon'  => 'link',
		),
	)
);

$token_locked = Settings::is_locked( 'token_secret' );
$token_saved  = '' !== (string) ( $s['token_secret'] ?? '' );
$secret_ctl   = '';
if ( $token_locked ) {
	$secret_ctl = '<code>' . esc_html__( '已由 wp-config.php 常量设置', 'wpdocs' ) . '</code>';
} else {
	$ph = $token_saved ? esc_attr__( '已保存（留空则不修改）', 'wpdocs' ) : '';
	$secret_ctl = '<input id="wpdocs-token-secret" type="password" name="wpdocs[token_secret]" value="" autocomplete="new-password" placeholder="' . $ph . '" />';
	if ( $token_saved ) {
		$secret_ctl .= '<label class="chk" style="display:block;margin-top:8px"><input type="checkbox" name="wpdocs[token_secret_clear]" value="1" /> ' . esc_html__( '清除已保存的 Secret', 'wpdocs' ) . '</label>';
	}
}

$tid_ctl = Settings::is_locked( 'token_id' )
	? '<code>' . esc_html( (string) Settings::get( 'token_id' ) ) . '</code>'
	: '<input id="wpdocs-token-id" type="text" name="wpdocs[token_id]" value="' . esc_attr( (string) ( $s['token_id'] ?? '' ) ) . '" autocomplete="off" />';

$base_ctl = Settings::is_locked( 'base_url' )
	? '<code>' . esc_html( Settings::base_url() ) . '</code>'
	: '<input id="wpdocs-base-url" type="url" name="wpdocs[base_url]" value="' . esc_attr( (string) ( $s['base_url'] ?? '' ) ) . '" placeholder="https://docs.example.test" />';

$tree_in   = (string) ( $s['tree_url'] ?? '' );
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
	$books_ctl = '<p class="hint">' . esc_html__( '保存连接后，这里会列出可选的书。也可只填站点标签自动选书。', 'wpdocs' ) . '</p>';
	foreach ( Settings::books() as $bid ) {
		$books_ctl .= '<input type="hidden" name="wpdocs[books][]" value="' . esc_attr( (string) $bid ) . '" />';
	}
}

$git_token_saved = '' !== (string) ( $s['git_forgejo_token'] ?? '' );
$git_token_ctl   = '<input id="wpdocs-git-token" type="password" name="wpdocs[git_forgejo_token]" value="" autocomplete="new-password" placeholder="'
	. ( $git_token_saved ? esc_attr__( '已保存（留空则不修改）', 'wpdocs' ) : '' ) . '" />';
if ( $git_token_saved ) {
	$git_token_ctl .= '<label class="chk" style="display:block;margin-top:8px"><input type="checkbox" name="wpdocs[git_forgejo_token_clear]" value="1" /> '
		. esc_html__( '清除已保存的 Token', 'wpdocs' ) . '</label>';
}

$repos     = Settings::git_repositories();
$repos_html = '<div class="wpdocs-git-repos">';
$repos_html .= '<input type="hidden" name="wpdocs[git_repositories][0][username]" value="" />';
$rows = $repos;
if ( empty( $rows ) ) {
	$rows = array(
		1 => array(
			'username'   => '',
			'repository' => '',
			'folder'     => '',
			'branch'     => 'main',
			'post_type'  => 'post',
			'post_author'=> get_current_user_id() ?: 1,
		),
	);
}
foreach ( $rows as $rid => $repo ) {
	if ( ! is_array( $repo ) ) {
		continue;
	}
	$rid = (int) $rid;
	if ( $rid < 1 ) {
		$rid = 1;
	}
	$repos_html .= '<div class="wpdocs-git-repo card" style="margin:12px 0;padding:12px">';
	$repos_html .= '<p class="hint">' . esc_html( sprintf( __( '仓库 #%d', 'wpdocs' ), $rid ) ) . '</p>';
	$repos_html .= '<div class="field"><label class="field-label">' . esc_html__( '用户 / 组织', 'wpdocs' ) . '</label><div class="field-ctl">'
		. '<input type="text" name="wpdocs[git_repositories][' . $rid . '][username]" value="' . esc_attr( (string) ( $repo['username'] ?? '' ) ) . '" /></div></div>';
	$repos_html .= '<div class="field"><label class="field-label">' . esc_html__( '仓库名', 'wpdocs' ) . '</label><div class="field-ctl">'
		. '<input type="text" name="wpdocs[git_repositories][' . $rid . '][repository]" value="' . esc_attr( (string) ( $repo['repository'] ?? '' ) ) . '" /></div></div>';
	$repos_html .= '<div class="field"><label class="field-label">' . esc_html__( '子目录', 'wpdocs' ) . '</label><div class="field-ctl">'
		. '<input type="text" name="wpdocs[git_repositories][' . $rid . '][folder]" value="' . esc_attr( (string) ( $repo['folder'] ?? '' ) ) . '" placeholder="docs" /></div></div>';
	$repos_html .= '<div class="field"><label class="field-label">' . esc_html__( '分支', 'wpdocs' ) . '</label><div class="field-ctl">'
		. '<input type="text" name="wpdocs[git_repositories][' . $rid . '][branch]" value="' . esc_attr( (string) ( $repo['branch'] ?? 'main' ) ) . '" /></div></div>';
	$repos_html .= '<div class="field"><label class="field-label">' . esc_html__( '文章类型', 'wpdocs' ) . '</label><div class="field-ctl">'
		. '<input type="text" name="wpdocs[git_repositories][' . $rid . '][post_type]" value="' . esc_attr( (string) ( $repo['post_type'] ?? 'post' ) ) . '" /></div></div>';
	$repos_html .= '<input type="hidden" name="wpdocs[git_repositories][' . $rid . '][post_author]" value="' . esc_attr( (string) (int) ( $repo['post_author'] ?? 1 ) ) . '" />';
	$repos_html .= '<input type="hidden" name="wpdocs[git_repositories][' . $rid . '][content_template]" value="' . esc_attr( (string) ( $repo['content_template'] ?? '%%content%%' ) ) . '" />';
	$repos_html .= '<input type="hidden" name="wpdocs[git_repositories][' . $rid . '][last_publish]" value="' . esc_attr( (string) (int) ( $repo['last_publish'] ?? 0 ) ) . '" />';
	$repos_html .= '</div>';
}
$repos_html .= '<p class="hint">' . esc_html__( '清空用户与仓库名可删除该行。需要多个仓库时，保存后再回来会保留；暂请在选项里追加序号键，或用工具页拉取。', 'wpdocs' ) . '</p>';
$repos_html .= '</div>';

$comments_on  = ! empty( $s['comments'] );
$redirects_on = ! empty( $s['redirects'] );
$giw_standalone = GitBoot::standalone_active();
?>
<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-settings' ) ); ?>" class="wpdocs-settings-form" id="wpdocs-settings-form">
	<?php wp_nonce_field( 'wpdocs_settings_full', 'wpdocs_settings_full_nonce' ); ?>
	<?php if ( $adv ) : ?>
		<input type="hidden" name="wpdocs_mode" value="advanced" />
	<?php endif; ?>

	<section class="card">
		<h2 class="section-title"><?php esc_html_e( '上线方式', 'wpdocs' ); ?></h2>
		<p class="section-desc"><?php esc_html_e( '保留 = 不动正文；CocoBook 读取 = 运行时换正文；Git 导入 = 把 Markdown 写入文章。', 'wpdocs' ); ?></p>
		<?php
		View::field(
			array(
				'label'   => __( '内容模式', 'wpdocs' ),
				'help'    => __( '三种互斥。切换后请保存，再配置对应连接。', 'wpdocs' ),
				'control' => $content_ctl,
			)
		);
		?>
	</section>

	<section class="card" data-wpdocs-when-content="cocobook,keep"<?php echo in_array( $content_mode, array( Settings::CONTENT_COCOBOOK, Settings::CONTENT_KEEP ), true ) ? '' : ' hidden'; ?>>
		<h2 class="section-title"><?php esc_html_e( 'CocoBook 连接与书目', 'wpdocs' ); ?></h2>
		<p class="section-desc"><?php esc_html_e( '读取模式与「导入到文章」工具都需要 Token。保留模式下可不填。', 'wpdocs' ); ?></p>
		<?php
		View::field(
			array(
				'for'     => 'wpdocs-base-url',
				'label'   => __( '文档站地址', 'wpdocs' ),
				'help'    => __( 'BookStack / CocoBook 根地址，不要尾斜杠。', 'wpdocs' ),
				'control' => $base_ctl,
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-tree-url',
				'label'    => __( '发布树地址', 'wpdocs' ),
				'help'     => __( '留空则用「文档站/books」；保存为空字符串则跳过发布树探测。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-tree-url" type="url" name="wpdocs[tree_url]" value="' . esc_attr( $tree_in ) . '" />',
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-token-id',
				'label'   => __( 'Token ID', 'wpdocs' ),
				'help'    => __( '文档站 API 只读 Token。也可在 wp-config.php 定义常量。', 'wpdocs' ),
				'control' => $tid_ctl,
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-token-secret',
				'label'   => __( 'Token Secret', 'wpdocs' ),
				'help'    => __( '密钥不回显；留空表示不修改。', 'wpdocs' ),
				'control' => $secret_ctl,
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-site-tag',
				'label'   => __( '站点标签', 'wpdocs' ),
				'help'    => __( '未勾选具体书目时，自动使用带此 site 标签的书。', 'wpdocs' ),
				'control' => '<input id="wpdocs-site-tag" type="text" name="wpdocs[site_tag]" value="' . esc_attr( (string) ( $s['site_tag'] ?? '' ) ) . '" />',
			)
		);
		View::field(
			array(
				'label'   => __( '同步的书', 'wpdocs' ),
				'control' => $books_ctl,
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-post-types',
				'label'    => __( '文章类型', 'wpdocs' ),
				'help'     => __( '逗号分隔，默认 post。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-post-types" type="text" name="wpdocs[post_types]" value="' . esc_attr( implode( ',', (array) ( $s['post_types'] ?? array( 'post' ) ) ) ) . '" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-ttl',
				'label'    => __( '缓存过期秒数', 'wpdocs' ),
				'help'     => __( '填 0 表示不过期（推荐）。正文缓存在文件，不进选项表。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-ttl" type="number" min="0" name="wpdocs[page_ttl]" value="' . esc_attr( (string) ( $s['page_ttl'] ?? 0 ) ) . '" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-minc',
				'label'    => __( '旧评论迁移阈值', 'wpdocs' ),
				'help'     => __( '迁移旧评论时，只迁移不少于这么多字的评论。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-minc" type="number" min="1" max="1000" name="wpdocs[comment_min_chars]" value="' . esc_attr( (string) ( $s['comment_min_chars'] ?? 30 ) ) . '" />',
			)
		);
		?>
	</section>

	<section class="card" data-wpdocs-when-content="cocobook"<?php echo Settings::CONTENT_COCOBOOK === $content_mode ? '' : ' hidden'; ?>>
		<h2 class="section-title"><?php esc_html_e( 'CocoBook 读取范围', 'wpdocs' ); ?></h2>
		<p class="section-desc"><?php esc_html_e( '试点适合先验证几篇；确认无误再开全部。', 'wpdocs' ); ?></p>
		<?php
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
				<p class="hint"><?php esc_html_e( '逗号分隔的 WordPress 文章 ID。', 'wpdocs' ); ?></p>
			</div>
		</div>
	</section>

	<section class="card" data-wpdocs-when-content="git"<?php echo Settings::CONTENT_GIT === $content_mode ? '' : ' hidden'; ?>>
		<h2 class="section-title"><?php esc_html_e( 'Git 导入（Git it Write）', 'wpdocs' ); ?></h2>
		<p class="section-desc"><?php esc_html_e( '从 Forgejo 远程仓或本机目录拉取 Markdown，写入 WordPress 文章。Webhook 与立即拉取在工具页。', 'wpdocs' ); ?></p>
		<?php if ( $giw_standalone ) : ?>
			<div class="notice warn"><span><?php esc_html_e( '检测到独立「Git it Write」插件。请停用它，或继续用该插件管理仓库；文派文档不会再加载内置副本。', 'wpdocs' ); ?></span></div>
		<?php endif; ?>
		<?php
		View::field(
			array(
				'for'     => 'wpdocs-git-url',
				'label'   => __( 'Forgejo 地址', 'wpdocs' ),
				'help'    => __( '例如 https://feicode.com。本地目录模式可留空。', 'wpdocs' ),
				'control' => '<input id="wpdocs-git-url" type="url" name="wpdocs[git_forgejo_url]" value="' . esc_attr( (string) ( $s['git_forgejo_url'] ?? '' ) ) . '" />',
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-git-token',
				'label'   => __( 'Forgejo Token', 'wpdocs' ),
				'help'    => __( '读仓库权限即可。留空不修改。', 'wpdocs' ),
				'control' => $git_token_ctl,
			)
		);
		View::field(
			array(
				'for'     => 'wpdocs-git-webhook',
				'label'   => __( 'Webhook Secret', 'wpdocs' ),
				'help'    => __( 'Forgejo Webhook 的 HMAC 密钥；首次保存可自动生成。', 'wpdocs' ),
				'control' => '<input id="wpdocs-git-webhook" type="text" name="wpdocs[git_webhook_secret]" value="' . esc_attr( (string) ( $s['git_webhook_secret'] ?? '' ) ) . '" autocomplete="off" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-git-base',
				'label'    => __( '本地目录', 'wpdocs' ),
				'help'     => __( '填绝对路径则走本地模式（扫描子目录 + .giw.yml），忽略远程仓列表。', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-git-base" type="text" name="wpdocs[git_base_directory]" value="' . esc_attr( (string) ( $s['git_base_directory'] ?? '' ) ) . '" placeholder="/var/www/docs-repos" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-git-ptype',
				'label'    => __( '默认文章类型', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-git-ptype" type="text" name="wpdocs[git_default_post_type]" value="' . esc_attr( (string) ( $s['git_default_post_type'] ?? 'post' ) ) . '" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-git-author',
				'label'    => __( '默认作者 ID', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<input id="wpdocs-git-author" type="number" min="1" name="wpdocs[git_default_post_author]" value="' . esc_attr( (string) (int) ( $s['git_default_post_author'] ?? 1 ) ) . '" />',
			)
		);
		View::field(
			array(
				'for'      => 'wpdocs-git-interval',
				'label'    => __( '本地同步间隔', 'wpdocs' ),
				'advanced' => true,
				'control'  => '<select id="wpdocs-git-interval" name="wpdocs[git_sync_interval]">'
					. '<option value="every_30_minutes"' . selected( (string) ( $s['git_sync_interval'] ?? '' ), 'every_30_minutes', false ) . '>' . esc_html__( '每 30 分钟', 'wpdocs' ) . '</option>'
					. '<option value="hourly"' . selected( (string) ( $s['git_sync_interval'] ?? 'hourly' ), 'hourly', false ) . '>' . esc_html__( '每小时', 'wpdocs' ) . '</option>'
					. '<option value="every_6_hours"' . selected( (string) ( $s['git_sync_interval'] ?? '' ), 'every_6_hours', false ) . '>' . esc_html__( '每 6 小时', 'wpdocs' ) . '</option>'
					. '<option value="every_12_hours"' . selected( (string) ( $s['git_sync_interval'] ?? '' ), 'every_12_hours', false ) . '>' . esc_html__( '每 12 小时', 'wpdocs' ) . '</option>'
					. '<option value="daily"' . selected( (string) ( $s['git_sync_interval'] ?? '' ), 'daily', false ) . '>' . esc_html__( '每天', 'wpdocs' ) . '</option>'
					. '</select>',
			)
		);
		View::field(
			array(
				'label'   => __( '远程仓库', 'wpdocs' ),
				'help'    => __( '本地目录为空时使用。', 'wpdocs' ),
				'control' => $repos_html,
			)
		);
		?>
		<p class="hint"><a href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=git' ) ); ?>"><?php esc_html_e( '去工具 · Git 拉取', 'wpdocs' ); ?></a></p>
	</section>

	<section class="card">
		<h2 class="section-title"><?php esc_html_e( '评论与跳转', 'wpdocs' ); ?></h2>
		<div class="simple-row is-action">
			<div class="tile"><?php echo View::icon( 'feedback' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div>
				<div class="t"><?php esc_html_e( '统一评论', 'wpdocs' ); ?></div>
				<div class="d"><?php esc_html_e( '评论挂到 cocobook:page:{id}，与文档站、论坛同一帖。', 'wpdocs' ); ?></div>
			</div>
			<div class="r"><?php echo View::toggle_input( 'wpdocs[comments]', $comments_on ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<div class="field" data-wpdocs-when-on="comments"<?php echo $comments_on ? '' : ' hidden'; ?>>
			<label class="field-label" for="wpdocs-bff"><?php esc_html_e( '评论服务地址', 'wpdocs' ); ?></label>
			<div class="field-ctl">
				<input id="wpdocs-bff" type="url" name="wpdocs[bff_url]" value="<?php echo esc_attr( (string) ( $s['bff_url'] ?? '' ) ); ?>" />
				<p class="hint"><?php esc_html_e( '评论 BFF 根地址。关掉「统一评论」时仍会保存。', 'wpdocs' ); ?></p>
			</div>
		</div>
		<div class="simple-row is-action">
			<div class="tile"><?php echo View::icon( 'share' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
			<div>
				<div class="t"><?php esc_html_e( '旧链接跳转', 'wpdocs' ); ?></div>
				<div class="d"><?php esc_html_e( '映射表已匹配则 301；已下线则 410。', 'wpdocs' ); ?>
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=redirects' ) ); ?>"><?php esc_html_e( '管理映射', 'wpdocs' ); ?></a>
				</div>
			</div>
			<div class="r"><?php echo View::toggle_input( 'wpdocs[redirects]', $redirects_on ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></div>
		</div>
		<div class="wenpai-actions">
			<button type="submit" class="btn btn-primary"><?php esc_html_e( '保存设置', 'wpdocs' ); ?></button>
			<a class="btn btn-ghost" href="<?php echo esc_url( admin_url( 'admin.php?page=wpdocs-tools&section=sync' ) ); ?>"><?php esc_html_e( '去同步工具', 'wpdocs' ); ?></a>
		</div>
	</section>
</form>
