<?php
/**
 * Settings — 档 A：主选择 choice 卡；该源连接；旁路。读取范围用 kit choice-cards cols-2，不是第二条主轴。
 * 书目：kit .wenpai-checkgroup 勾选清单（计数 + 全选/清空）。
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
	__( '先选内容从哪来，再填这一条路径需要的连接。', 'wpdocs' ),
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
			'label' => __( '保留正文', 'wpdocs' ),
			'desc'  => __( '品牌站自己管正文；可到工具一次性导入。', 'wpdocs' ),
			'icon_html'  => View::icon( 'info' ),
		),
		Settings::CONTENT_COCOBOOK => array(
			'label' => __( '文档站读取', 'wpdocs' ),
			'desc'  => __( '打开文章时读 CocoBook，不改正文库。', 'wpdocs' ),
			'icon_html'  => View::icon( 'link' ),
		),
		Settings::CONTENT_GIT => array(
			'label' => __( 'Git 写入', 'wpdocs' ),
			'desc'  => __( '把仓库 Markdown 写入文章。', 'wpdocs' ),
			'icon_html'  => View::icon( 'flash' ),
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
			'icon_html'  => View::icon( 'flash' ),
		),
		Settings::MODE_ALL   => array(
			'label' => __( '全部', 'wpdocs' ),
			'desc'  => __( '凡能对应到文档页的都换。', 'wpdocs' ),
			'icon_html'  => View::icon( 'link' ),
		),
	)
);

$token_locked = Settings::is_locked( 'token_secret' );
$token_saved  = '' !== (string) ( $s['token_secret'] ?? '' );
if ( $token_locked ) {
	$secret_ctl = '<code>' . esc_html__( '已由 wp-config.php 常量设置', 'wpdocs' ) . '</code>';
} else {
	$ph         = $token_saved ? esc_attr__( '留空表示不修改已保存的值', 'wpdocs' ) : '';
	$secret_ctl = '<input id="wpdocs-token-secret" type="password" name="wpdocs[token_secret]" value="" autocomplete="new-password" placeholder="' . $ph . '" />';
	if ( $token_saved ) {
		$secret_ctl .= '<label class="chk" style="display:block;margin-top:8px"><input type="checkbox" name="wpdocs[token_secret_clear]" value="1" /> ' . esc_html__( '清除密钥', 'wpdocs' ) . '</label>';
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
	$picked     = Settings::books();
	$books_list = array();
	foreach ( $books['data'] as $b ) {
		$bid = (int) ( $b['id'] ?? 0 );
		if ( $bid < 1 ) {
			continue;
		}
		$books_list[] = $b;
	}
	$books_total = count( $books_list );
	$books_sel   = count( array_intersect( $picked, wp_list_pluck( $books_list, 'id' ) ) );
	$books_ctl  .= '<div class="wenpai-checkgroup" data-wenpai-checkgroup data-wenpai-count-total="' . esc_attr( (string) $books_total ) . '">'
		. '<div class="wenpai-checkgroup__head">'
		. '<span class="wenpai-checkgroup__count" data-wenpai-count-template="' . esc_attr( __( '已选 %1$d / %2$d', 'wpdocs' ) ) . '">'
		. esc_html( sprintf( __( '已选 %1$d / %2$d', 'wpdocs' ), $books_sel, $books_total ) )
		. '</span>'
		. '<span class="wenpai-checkgroup__acts">'
		. '<button type="button" data-wenpai-cg-all>' . esc_html__( '全选', 'wpdocs' ) . '</button>'
		. '<button type="button" data-wenpai-cg-none>' . esc_html__( '清空', 'wpdocs' ) . '</button>'
		. '</span></div>';
	$books_ctl  .= '<input type="hidden" name="wpdocs[books][]" value="" />';
	$books_ctl  .= '<ul class="wenpai-checkgroup__list">';
	foreach ( $books_list as $b ) {
		$bid = (int) $b['id'];
		$books_ctl .= '<li><label class="chk"><input type="checkbox" name="wpdocs[books][]" value="' . esc_attr( (string) $bid ) . '" ' . checked( in_array( $bid, $picked, true ), true, false ) . ' /> '
			. '<span class="wenpai-checkgroup__text">' . esc_html( (string) ( $b['name'] ?? '' ) ) . '</span>'
			. '<span class="wenpai-checkgroup__meta">#' . esc_html( (string) $bid ) . '</span></label></li>';
	}
	$books_ctl .= '</ul></div>';
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
	. ( $git_token_saved ? esc_attr__( '留空表示不修改已保存的值', 'wpdocs' ) : '' ) . '" />';
if ( $git_token_saved ) {
	$git_token_ctl .= '<label class="chk" style="display:block;margin-top:8px"><input type="checkbox" name="wpdocs[git_forgejo_token_clear]" value="1" /> '
		. esc_html__( '清除 Token', 'wpdocs' ) . '</label>';
}

$comments_on    = ! empty( $s['comments'] );
$redirects_on   = ! empty( $s['redirects'] );
$giw_standalone = GitBoot::standalone_active();
$form_cls       = 'wpdocs-settings-form' . ( $adv ? '' : ' wpdocs-simple' );

if ( Settings::CONTENT_COCOBOOK === $content_mode ) {
	$adv_groups = array(
		'connect'  => __( '连接', 'wpdocs' ),
		'scope'    => __( '书目与范围', 'wpdocs' ),
		'comments' => __( '评论', 'wpdocs' ),
		'misc'     => __( '同步与缓存', 'wpdocs' ),
	);
} elseif ( Settings::CONTENT_GIT === $content_mode ) {
	$adv_groups = array(
		'connect' => __( '仓库', 'wpdocs' ),
		'misc'    => __( '同步与缓存', 'wpdocs' ),
	);
} else {
	$adv_groups = array(
		'connect' => __( '连接', 'wpdocs' ),
	);
}
$section = Wenpai_Admin_UI::section( $adv_groups );
$group_div = static function ( string $gid, bool $close = false ) use ( $adv, $section ): string {
	if ( $close ) {
		return '</div>';
	}
	// 简单模式不输出分组钩子：组内 advanced 字段各自隐藏，组保持展开。
	return '<div class="wpdocs-adv-group"' . ( $adv ? Wenpai_Admin_UI::group_attrs( $gid, $section ) : '' ) . '>';
};
?>
<form method="post" action="<?php echo esc_url( \Wpdocs\Admin\Menu::url( 'settings' ) ); ?>" class="<?php echo esc_attr( $form_cls ); ?>" id="wpdocs-settings-form">
	<?php wp_nonce_field( 'wpdocs_settings_full', 'wpdocs_settings_full_nonce' ); ?>
	<?php if ( $adv ) : ?>
		<input type="hidden" name="wpdocs_mode" value="advanced" />
	<?php endif; ?>

	<section class="card">
		<h2 class="section-title"><?php esc_html_e( '内容从哪来', 'wpdocs' ); ?></h2>
		<p class="section-desc"><?php esc_html_e( '三选一。只走一条路径；统一评论只在「文档站读取」。', 'wpdocs' ); ?></p>

		<?php
		View::field(
			array(
				'label'   => __( '主选择', 'wpdocs' ),
				'class'   => 'is-hero',
				'control' => $content_ctl,
			)
		);
		?>

		<?php if ( $adv && count( $adv_groups ) > 1 ) : ?>
			<?php
			$sub_items = array();
			foreach ( $adv_groups as $gid => $glabel ) {
				$sub_items[] = array(
					'id'    => $gid,
					'label' => $glabel,
					'url'   => \Wpdocs\Admin\Menu::url( 'settings', $gid, 'mode=advanced' ),
				);
			}
			echo Wenpai_Admin_UI::subnav_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped.
				array(
					'current' => $section,
					'label'   => __( '高级分组', 'wpdocs' ),
					'items'   => $sub_items,
				)
			);
			?>
		<?php endif; ?>

		<div data-wenpai-groups data-wenpai-groups-default="connect">
		<div class="wpdocs-when-content" data-wenpai-depends-on="wpdocs[content_mode]" data-wenpai-depends-value="keep" data-wenpai-depends-mode="disable"<?php echo Settings::CONTENT_KEEP === $content_mode ? '' : ' hidden'; ?>>
			<?php
ob_start();
?><a class="btn btn-ghost" href="<?php echo esc_url( \Wpdocs\Admin\Menu::url( 'tools', 'sync' ) ); ?>"><?php esc_html_e( '打开同步', 'wpdocs' ); ?></a><?php
echo Wenpai_Admin_UI::row_html( array( 'icon_html' => View::icon( 'info' ), 'title' => __( '前台不换正文', 'wpdocs' ), 'desc' => __( '需要把已绑定的 CocoBook 页写入文章时，到工具 → 同步。', 'wpdocs' ), 'right_html' => (string) ob_get_clean() ) );
?>
			<?php if ( $adv ) : ?>
				<?php echo $group_div( 'connect' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
				<?php
				$base_ctl_keep = Settings::is_locked( 'base_url' )
					? '<code>' . esc_html( Settings::base_url() ) . '</code>'
					: '<input id="wpdocs-base-url-keep" type="url" name="wpdocs[base_url]" value="' . esc_attr( (string) ( $s['base_url'] ?? '' ) ) . '" />';
				$tid_ctl_keep  = Settings::is_locked( 'token_id' )
					? '<code>' . esc_html( (string) Settings::get( 'token_id' ) ) . '</code>'
					: '<input id="wpdocs-token-id-keep" type="text" name="wpdocs[token_id]" value="' . esc_attr( (string) ( $s['token_id'] ?? '' ) ) . '" autocomplete="off" />';
				if ( $token_locked ) {
					$secret_ctl_keep = '<code>' . esc_html__( '已由 wp-config.php 常量设置', 'wpdocs' ) . '</code>';
				} else {
					$ph_keep         = $token_saved ? esc_attr__( '留空表示不修改已保存的值', 'wpdocs' ) : '';
					$secret_ctl_keep = '<input id="wpdocs-token-secret-keep" type="password" name="wpdocs[token_secret]" value="" autocomplete="new-password" placeholder="' . $ph_keep . '" />';
					if ( $token_saved ) {
						$secret_ctl_keep .= '<label class="chk" style="display:block;margin-top:8px"><input type="checkbox" name="wpdocs[token_secret_clear]" value="1" /> ' . esc_html__( '清除密钥', 'wpdocs' ) . '</label>';
					}
				}
				View::field(
					array(
						'for'     => 'wpdocs-base-url-keep',
						'label'   => __( 'CocoBook 地址', 'wpdocs' ),
						'help'    => __( '仅「从 CocoBook 导入」时需要。', 'wpdocs' ),
						'control' => $base_ctl_keep,
					)
				);
				View::field(
					array(
						'for'     => 'wpdocs-token-id-keep',
						'label'   => __( 'Token ID', 'wpdocs' ),
						'control' => $tid_ctl_keep,
					)
				);
				View::field(
					array(
						'for'     => 'wpdocs-token-secret-keep',
						'label'   => __( 'Token Secret', 'wpdocs' ),
						'help'    => __( '留空表示不修改已保存的值。', 'wpdocs' ),
						'control' => $secret_ctl_keep,
					)
				);
				?>
				<?php echo $group_div( 'connect', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php endif; ?>
		</div>

		<div class="wpdocs-when-content" data-wenpai-depends-on="wpdocs[content_mode]" data-wenpai-depends-value="cocobook" data-wenpai-depends-mode="disable"<?php echo Settings::CONTENT_COCOBOOK === $content_mode ? '' : ' hidden'; ?>>
			<?php if ( ! $adv ) : ?>
				<?php
				echo Wenpai_Admin_UI::row_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped.
					array(
						'icon_html' => View::icon( 'external' ),
						'state'     => Settings::is_configured() ? 'ok' : 'warn',
						'title'     => __( 'CocoBook', 'wpdocs' ),
						'desc'      => '' !== Settings::base_url() ? Settings::base_url() : __( '未填写', 'wpdocs' ),
						'pill'      => array(
							'text'  => Settings::is_configured() ? __( '已配置', 'wpdocs' ) : __( '未配置', 'wpdocs' ),
							'state' => Settings::is_configured() ? 'ok' : 'warn',
						),
						'href'      => \Wpdocs\Admin\Menu::url( 'settings', '', 'mode=advanced' ),
					)
				);
				?>
			<?php endif; ?>
			<?php echo $group_div( 'connect' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			View::field(
				array(
					'for'       => 'wpdocs-base-url',
					'label'     => __( 'CocoBook 地址', 'wpdocs' ),
					'help'      => __( 'CocoBook / BookStack 根地址。', 'wpdocs' ),
					'advanced'  => true,
					'control'   => $base_ctl,
				)
			);
			View::field(
				array(
					'for'       => 'wpdocs-token-id',
					'label'     => __( 'Token ID', 'wpdocs' ),
					'advanced'  => true,
					'control'   => $tid_ctl,
				)
			);
			View::field(
				array(
					'for'       => 'wpdocs-token-secret',
					'label'     => __( 'Token Secret', 'wpdocs' ),
					'help'      => __( '留空表示不修改已保存的值。', 'wpdocs' ),
					'advanced'  => true,
					'control'   => $secret_ctl,
				)
			);
			?>
			<?php echo $group_div( 'connect', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $group_div( 'scope' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			View::field(
				array(
					'for'       => 'wpdocs-site-tag',
					'label'     => __( '站点标签', 'wpdocs' ),
					'help'      => __( '未勾选书目时，自动用带此标签的书。', 'wpdocs' ),
					'advanced'  => true,
					'control'   => '<input id="wpdocs-site-tag" type="text" name="wpdocs[site_tag]" value="' . esc_attr( (string) ( $s['site_tag'] ?? '' ) ) . '" />',
				)
			);
			View::field(
				array(
					'label'     => __( '书目', 'wpdocs' ),
					'help'      => __( '可多选。框内上下滑动查看全部书。', 'wpdocs' ),
					'advanced'  => true,
					'control'   => $books_ctl,
				)
			);
			View::field(
				array(
					'label'     => __( '读取范围', 'wpdocs' ),
					'help'      => __( '只影响「文档站读取」路径下哪些文章换正文，不是第二条主选择。', 'wpdocs' ),
					'advanced'  => true,
					'control'   => $rollout_ctl,
				)
			);
			?>
			<div<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="field" data-wenpai-depends-on="wpdocs[mode]" data-wenpai-depends-value="pilot" data-wenpai-depends-mode="disable"<?php echo Settings::MODE_PILOT === $rollout ? '' : ' hidden'; ?>>
				<label class="field-label" for="wpdocs-pilot"><?php esc_html_e( '试点文章 ID', 'wpdocs' ); ?></label>
				<div class="field-ctl">
					<input id="wpdocs-pilot" type="text" name="wpdocs[pilot_post_ids]" value="<?php echo esc_attr( implode( ', ', Settings::pilot_post_ids() ) ); ?>" />
					<p class="hint"><?php esc_html_e( '逗号分隔。', 'wpdocs' ); ?></p>
				</div>
			</div>
			</div>
			<?php echo $group_div( 'scope', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $group_div( 'comments' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			ob_start();
			echo View::toggle_input( 'wpdocs[comments]', $comments_on ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			$wpdocs_comments_ctl = (string) ob_get_clean();
			echo Wenpai_Admin_UI::row_html(
				array(
					'icon_html'         => View::icon( 'feedback' ),
					'title'        => __( '统一评论', 'wpdocs' ),
					'desc'         => __( '与 CocoBook 同一帖。仅此路径提供。', 'wpdocs' ),
					'control_html' => $wpdocs_comments_ctl,
				)
			);
			?>
			<div<?php echo Wenpai_Admin_UI::mode_attrs( 'advanced' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>>
			<div class="field" data-wenpai-depends-on="wpdocs[comments]" data-wenpai-depends-value="1" data-wenpai-depends-mode="disable"<?php echo $comments_on ? '' : ' hidden'; ?>>
				<label class="field-label" for="wpdocs-bff"><?php esc_html_e( '评论服务', 'wpdocs' ); ?></label>
				<div class="field-ctl">
					<input id="wpdocs-bff" type="url" name="wpdocs[bff_url]" value="<?php echo esc_attr( (string) ( $s['bff_url'] ?? '' ) ); ?>" />
				</div>
			</div>
			</div>
			<?php echo $group_div( 'comments', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $group_div( 'misc' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			View::field(
				array(
					'for'      => 'wpdocs-tree-url',
					'label'    => __( '发布树地址', 'wpdocs' ),
					'help'     => __( '留空用「CocoBook 根地址/books」。', 'wpdocs' ),
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
					'help'     => __( '页正文缓存；0 = 正文不过期。发布树索引仍最多约 5 分钟刷新。', 'wpdocs' ),
					'advanced' => true,
					'control'  => '<input id="wpdocs-ttl" type="number" min="0" name="wpdocs[page_ttl]" value="' . esc_attr( (string) ( $s['page_ttl'] ?? 0 ) ) . '" />',
				)
			);
			View::field(
				array(
					'for'      => 'wpdocs-refresh-secret',
					'label'    => __( 'Webhook 密钥', 'wpdocs' ),
					'help'     => __( '拼进工具 → 同步里的 Webhook URL。留空保存时自动生成；也可在 wp-config 用 WPDOCS_REFRESH_SECRET。', 'wpdocs' ),
					'advanced' => true,
					'control'  => Settings::is_locked( 'refresh_secret' )
						? '<code>' . esc_html__( '已由 wp-config.php 常量设置', 'wpdocs' ) . '</code>'
						: '<input id="wpdocs-refresh-secret" type="text" name="wpdocs[refresh_secret]" value="' . esc_attr( (string) ( $s['refresh_secret'] ?? '' ) ) . '" autocomplete="off" />',
				)
			);
			View::field(
				array(
					'for'      => 'wpdocs-minc',
					'label'    => __( '旧评论迁移阈值', 'wpdocs' ),
					'advanced' => true,
					'control'  => '<input id="wpdocs-minc" type="number" min="1" max="1000" name="wpdocs[comment_min_chars]" value="' . esc_attr( (string) ( $s['comment_min_chars'] ?? 30 ) ) . '" />',
				)
			);
			?>
			<?php echo $group_div( 'misc', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
		</div>

		<div class="wpdocs-when-content" data-wenpai-depends-on="wpdocs[content_mode]" data-wenpai-depends-value="git" data-wenpai-depends-mode="disable"<?php echo Settings::CONTENT_GIT === $content_mode ? '' : ' hidden'; ?>>
			<?php if ( $giw_standalone ) : ?>
				<div class="notice warn"><span><?php esc_html_e( '已装独立 Git it Write。请停用后再用本页，或继续用那个插件。', 'wpdocs' ); ?></span></div>
			<?php endif; ?>
			<?php if ( ! $adv ) : ?>
				<?php
				$git_row_desc = '' !== $git_slug ? $git_slug : ( '' !== (string) ( $s['git_forgejo_url'] ?? '' ) ? (string) $s['git_forgejo_url'] : __( '未填写', 'wpdocs' ) );
				$git_row_ok   = '' !== $git_slug || '' !== (string) ( $s['git_base_directory'] ?? '' );
				echo Wenpai_Admin_UI::row_html( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- kit-escaped.
					array(
						'icon_html' => View::icon( 'server' ),
						'state'     => $git_row_ok ? 'ok' : 'warn',
						'title'     => __( '仓库', 'wpdocs' ),
						'desc'      => $git_row_desc,
						'pill'      => array(
							'text'  => $git_row_ok ? __( '已配', 'wpdocs' ) : __( '待填', 'wpdocs' ),
							'state' => $git_row_ok ? 'ok' : 'warn',
						),
						'href'      => \Wpdocs\Admin\Menu::url( 'settings', '', 'mode=advanced' ),
					)
				);
				?>
			<?php endif; ?>
			<?php echo $group_div( 'connect' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
			View::field(
				array(
					'for'       => 'wpdocs-git-url',
					'label'     => __( 'Forgejo 地址', 'wpdocs' ),
					'advanced'  => true,
					'control'   => '<input id="wpdocs-git-url" type="url" name="wpdocs[git_forgejo_url]" value="' . esc_attr( (string) ( $s['git_forgejo_url'] ?? '' ) ) . '" placeholder="https://feicode.com" />',
				)
			);
			View::field(
				array(
					'for'       => 'wpdocs-git-token',
					'label'     => __( 'Token', 'wpdocs' ),
					'help'      => __( '留空表示不修改已保存的值。', 'wpdocs' ),
					'advanced'  => true,
					'control'   => $git_token_ctl,
				)
			);
			View::field(
				array(
					'for'       => 'wpdocs-git-slug',
					'label'     => __( '仓库', 'wpdocs' ),
					'help'      => __( '格式 owner/name。拉取在工具 → 同步。', 'wpdocs' ),
					'advanced'  => true,
					'control'   => '<input id="wpdocs-git-slug" type="text" name="wpdocs[git_repo_slug]" value="' . esc_attr( $git_slug ) . '" placeholder="WenPai-org/docs" />',
				)
			);
			View::field(
				array(
					'for'       => 'wpdocs-git-folder',
					'label'     => __( '子目录', 'wpdocs' ),
					'advanced'  => true,
					'control'   => '<input id="wpdocs-git-folder" type="text" name="wpdocs[git_repo_folder]" value="' . esc_attr( (string) ( $primary['folder'] ?? '' ) ) . '" placeholder="docs" />',
				)
			);
			View::field(
				array(
					'for'       => 'wpdocs-git-branch',
					'label'     => __( '分支', 'wpdocs' ),
					'advanced'  => true,
					'control'   => '<input id="wpdocs-git-branch" type="text" name="wpdocs[git_repo_branch]" value="' . esc_attr( (string) ( $primary['branch'] ?? 'main' ) ) . '" />',
				)
			);
			?>
			<?php echo $group_div( 'connect', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php echo $group_div( 'misc' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<?php
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
			<?php echo $group_div( 'misc', true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
			<input type="hidden" name="wpdocs[git_repo_post_type]" value="<?php echo esc_attr( (string) ( $primary['post_type'] ?? 'post' ) ); ?>" />
			<input type="hidden" name="wpdocs[git_repo_post_author]" value="<?php echo esc_attr( (string) (int) ( $primary['post_author'] ?? 1 ) ); ?>" />
			<input type="hidden" name="wpdocs[git_repo_last_publish]" value="<?php echo esc_attr( (string) (int) ( $primary['last_publish'] ?? 0 ) ); ?>" />
		</div>
		</div>

		<?php
		ob_start();
		echo View::toggle_input( 'wpdocs[redirects]', $redirects_on ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		$wpdocs_redirects_ctl = (string) ob_get_clean();
		echo Wenpai_Admin_UI::row_html(
			array(
				'icon_html'         => View::icon( 'share' ),
				'title'        => __( '旧链接跳转', 'wpdocs' ),
				'desc_html'    => __( '旁路能力，与内容源无关。', 'wpdocs' ) . ' <a href="' . esc_url( \Wpdocs\Admin\Menu::url( 'tools', 'redirects' ) ) . '">' . esc_html__( '管理映射', 'wpdocs' ) . '</a>',
				'control_html' => $wpdocs_redirects_ctl,
			)
		);
		?>

		<div class="wenpai-actions">
			<button type="submit" class="btn btn-primary"><?php esc_html_e( '保存设置', 'wpdocs' ); ?></button>
		</div>
	</section>
</form>
