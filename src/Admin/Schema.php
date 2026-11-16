<?php
/**
 * Settings form schema — Wenpai_Admin_Settings contract (kit 0.10).
 *
 * Fields are declared once here; the template renders leaf fields via
 * field_html() and keeps product widgets (content-mode cards, books
 * checkgroup, git repo composite, wp-config locks) custom. Custom widgets
 * that act as depends_on triggers are still registered so server-side
 * initial state and sanitize delegation see them.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Admin;

use Wpdocs\Settings;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Schema {

	/** Option name; field inputs post as wpdocs_settings[field]. */
	public const OPTION = Settings::OPTION;

	/**
	 * Advanced group map for the saved content mode (same ids the template
	 * uses for group_attrs/subnav). Order matters: connect is always first.
	 *
	 * @return array<string,string> Group id => label.
	 */
	public static function groups(): array {
		if ( Settings::CONTENT_GIT === Settings::content_mode() ) {
			return array(
				'connect' => __( '仓库', 'wpdocs' ),
				'misc'    => __( '同步与缓存', 'wpdocs' ),
			);
		}
		if ( Settings::CONTENT_KEEP === Settings::content_mode() ) {
			return array( 'connect' => __( '连接', 'wpdocs' ) );
		}
		return array(
			'connect'  => __( '连接', 'wpdocs' ),
			'scope'    => __( '书目与范围', 'wpdocs' ),
			'comments' => __( '评论', 'wpdocs' ),
			'misc'     => __( '同步与缓存', 'wpdocs' ),
		);
	}

	/**
	 * Field schema. 'mode' => 'advanced' hides the field on the simple page
	 * (server-side); 'group' joins the ?section= view contract. depends_on
	 * values use the wpdocs_settings[...] input names via the option prefix.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function fields(): array {
		$cc    = array( Settings::CONTENT_KEEP, Settings::CONTENT_COCOBOOK );
		$cocok = Settings::CONTENT_COCOBOOK;
		$git   = Settings::CONTENT_GIT;
		return array(
			// --- depends_on triggers (custom widgets, still registered) ---
			'content_mode' => array(
				'type'    => 'choice',
				'default' => Settings::CONTENT_COCOBOOK,
				'label'   => __( '内容从哪来', 'wpdocs' ),
				'choices' => array(
					Settings::CONTENT_KEEP     => __( '保留正文', 'wpdocs' ),
					Settings::CONTENT_COCOBOOK => __( '文档站读取', 'wpdocs' ),
					Settings::CONTENT_GIT      => __( 'Git 写入', 'wpdocs' ),
				),
			),
			'mode' => array(
				'type'    => 'choice',
				'default' => Settings::MODE_PILOT,
				'label'   => __( '读取范围', 'wpdocs' ),
				'choices' => array(
					Settings::MODE_PILOT => __( '试点', 'wpdocs' ),
					Settings::MODE_ALL   => __( '全部', 'wpdocs' ),
				),
			),
			'comments' => array(
				'type'    => 'toggle',
				'default' => true,
				'label'   => __( '统一评论', 'wpdocs' ),
			),
			// --- CocoBook / keep connection (one shared set) ---
			'base_url' => array(
				'type'      => 'url',
				'default'   => 'https://docs.cocobook.com',
				'label'     => __( 'CocoBook 地址', 'wpdocs' ),
				'desc'      => __( 'CocoBook / BookStack 根地址。', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'connect',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $cc ),
			),
			'token_id' => array(
				'type'      => 'text',
				'label'     => __( 'Token ID', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'connect',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $cc ),
			),
			'token_secret' => array(
				'type'      => 'secret',
				'label'     => __( 'Token Secret', 'wpdocs' ),
				'desc'      => __( '留空表示不修改已保存的值。', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'connect',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $cc ),
			),
			// --- CocoBook scope / comments / misc ---
			'site_tag' => array(
				'type'      => 'text',
				'label'     => __( '站点标签', 'wpdocs' ),
				'desc'      => __( '未勾选书目时，自动用带此标签的书。', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'scope',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $cocok ),
			),
			'bff_url' => array(
				'type'      => 'url',
				'label'     => __( '评论服务', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'comments',
				'depends_on' => array(
					'all' => array(
						array( 'field' => 'content_mode', 'value' => $cocok ),
						array( 'field' => 'comments', 'value' => '1' ),
					),
				),
			),
			'tree_url' => array(
				'type'      => 'url',
				'label'     => __( '发布树地址', 'wpdocs' ),
				'desc'      => __( '留空用「CocoBook 根地址/books」。', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'misc',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $cocok ),
			),
			'page_ttl' => array(
				'type'      => 'number',
				'default'   => 0,
				'label'     => __( '缓存秒数', 'wpdocs' ),
				'desc'      => __( '页正文缓存；0 = 正文不过期。发布树索引仍最多约 5 分钟刷新。', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'misc',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $cocok ),
			),
			'refresh_secret' => array(
				'type'      => 'text',
				'label'     => __( 'Webhook 密钥', 'wpdocs' ),
				'desc'      => __( '拼进工具 → 同步里的 Webhook URL。留空保存时自动生成；也可在 wp-config 用 WPDOCS_REFRESH_SECRET。', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'misc',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $cocok ),
			),
			'comment_min_chars' => array(
				'type'      => 'number',
				'default'   => 30,
				'label'     => __( '旧评论迁移阈值', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'misc',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $cocok ),
			),
			// --- Git connection / misc ---
			'git_forgejo_url' => array(
				'type'      => 'url',
				'label'     => __( 'Forgejo 地址', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'connect',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $git ),
			),
			'git_forgejo_token' => array(
				'type'      => 'secret',
				'label'     => __( 'Token', 'wpdocs' ),
				'desc'      => __( '留空表示不修改已保存的值。', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'connect',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $git ),
			),
			'git_webhook_secret' => array(
				'type'      => 'text',
				'label'     => __( 'Webhook Secret', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'misc',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $git ),
			),
			'git_base_directory' => array(
				'type'      => 'text',
				'label'     => __( '本地目录', 'wpdocs' ),
				'desc'      => __( '填了则走本地扫描，忽略上方远程仓库。', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'misc',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $git ),
			),
			'git_default_post_type' => array(
				'type'      => 'text',
				'label'     => __( '文章类型', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'misc',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $git ),
			),
			// --- Custom widgets kept out of field_html() (still registered) ---
			'books' => array(
				'type'        => 'multicheck',
				'default'     => array(),
				'label'       => __( '书目', 'wpdocs' ),
				'choices'     => array(),
				'presentation' => 'checkgroup',
				'mode'        => 'advanced',
				'group'       => 'scope',
				'depends_on'  => array( 'field' => 'content_mode', 'value' => $cocok ),
			),
			'pilot_post_ids' => array(
				'type'      => 'text',
				'default'   => '',
				'label'     => __( '试点文章 ID', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'scope',
				'depends_on' => array(
					'all' => array(
						array( 'field' => 'content_mode', 'value' => $cocok ),
						array( 'field' => 'mode', 'value' => Settings::MODE_PILOT ),
					),
				),
			),
			'post_types' => array(
				'type'      => 'text',
				'default'   => array( 'post' ),
				'label'     => __( '文章类型', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'misc',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $cocok ),
			),
			'git_repo_slug' => array(
				'type'      => 'text',
				'default'   => '',
				'label'     => __( '仓库', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'connect',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $git ),
			),
			'git_repo_folder' => array(
				'type'      => 'text',
				'default'   => '',
				'label'     => __( '子目录', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'connect',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $git ),
			),
			'git_repo_branch' => array(
				'type'      => 'text',
				'default'   => 'main',
				'label'     => __( '分支', 'wpdocs' ),
				'mode'      => 'advanced',
				'group'     => 'connect',
				'depends_on' => array( 'field' => 'content_mode', 'value' => $git ),
			),
			'redirects' => array(
				'type'    => 'toggle',
				'default' => false,
				'label'   => __( '旧链接跳转', 'wpdocs' ),
			),
		);
	}

	/**
	 * Kit UI strings in the wpdocs text domain.
	 *
	 * @return array<string,string>
	 */
	public static function strings(): array {
		return array(
			'on'                 => __( '已开启', 'wpdocs' ),
			'off'                => __( '未开启', 'wpdocs' ),
			'secret_saved'       => __( '已保存一个值，出于安全不会显示。留空表示不修改。', 'wpdocs' ),
			'secret_empty'       => __( '尚未填写。', 'wpdocs' ),
			'secret_clear'       => __( '清除已保存的值', 'wpdocs' ),
			'saved'              => __( '设置已保存。', 'wpdocs' ),
			/* translators: %d: number of settings that were not saved. */
			'invalid'            => __( '%d 项设置未能保存，请按字段下方的提示修改。', 'wpdocs' ),
			'partial_saved'      => __( '其余设置已保存。', 'wpdocs' ),
			'checkgroup_count'   => __( '已选 %1$d / %2$d', 'wpdocs' ),
			'checkgroup_all'     => __( '全选', 'wpdocs' ),
			'checkgroup_none'    => __( '清空', 'wpdocs' ),
		);
	}

	/**
	 * Shared framework instance. wpdocs posts the form to its own admin page
	 * and sanitizes through Settings::save(), so the kit's register_setting
	 * (options.php path) is removed like wpslug does.
	 *
	 * @return \Wenpai_Admin_Settings|null
	 */
	public static function framework() {
		if ( ! class_exists( 'Wenpai_Admin_Settings' ) ) {
			return null;
		}
		$fw = \Wenpai_Admin_Settings::get_instance( self::OPTION );
		if ( null === $fw ) {
			$fw = \Wenpai_Admin_Settings::register(
				self::OPTION,
				self::fields(),
				array(
					'group'    => 'wpdocs_settings',
					'strings'  => self::strings(),
					'sections' => self::groups(),
				)
			);
			remove_action( 'admin_init', array( $fw, 'register_setting' ) );
		}
		return $fw;
	}

}
