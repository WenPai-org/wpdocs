<?php
/**
 * Admin markup helpers aligned with wenpai-admin-ui kit classes.
 *
 * @package Wpdocs
 */

namespace Wpdocs\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class View {

	public static function page_head( string $title, string $lede, string $right_html = '' ): void {
		echo '<div class="wenpai-page-head"><div>';
		echo '<h1 class="wenpai-h1">' . esc_html( $title ) . '</h1>';
		if ( '' !== $lede ) {
			echo '<p class="wenpai-lede">' . esc_html( $lede ) . '</p>';
		}
		echo '</div>';
		if ( '' !== $right_html ) {
			echo $right_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller HTML.
		}
		echo '</div>';
	}

	/**
	 * Kit 简单/高级切换（URL ?mode=）。
	 */
	public static function mode_toggle( string $page ): void {
		$adv     = self::is_advanced();
		$tab     = 'wpdocs-tools' === $page ? 'tools' : ( 'wpdocs-settings' === $page ? 'settings' : 'overview' );
		$section = '';
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['section'] ) ) {
			$section = sanitize_key( (string) wp_unslash( $_GET['section'] ) );
		}
		$simple   = Menu::url( $tab, $section, 'mode=simple' );
		$advanced = Menu::url( $tab, $section, 'mode=advanced' );
		echo \Wenpai_Admin_UI::mode_switch_html(
			array(
				'label' => __( '显示深度', 'wpdocs' ),
				'items' => array(
					array(
						'label'   => __( '简单', 'wpdocs' ),
						'href'    => $simple,
						'current' => ! $adv,
					),
					array(
						'label'   => __( '高级', 'wpdocs' ),
						'href'    => $advanced,
						'current' => $adv,
					),
				),
			)
		);
	}

	public static function is_advanced(): bool {
		return 'advanced' === \Wenpai_Admin_UI::mode();
	}

	/**
	 * Kit settings field: 280px label | 1fr `.field-ctl` (hint under control).
	 *
	 * @param array{label:string,help?:string,bad?:string,for?:string,control:string,advanced?:bool} $args
	 */
	public static function field( array $args ): void {
		$for   = isset( $args['for'] ) ? (string) $args['for'] : '';
		$label = (string) ( $args['label'] ?? '' );
		$help  = (string) ( $args['help'] ?? '' );
		$bad   = (string) ( $args['bad'] ?? '' );
		$ctl   = (string) ( $args['control'] ?? '' );
		$extra = '';
		if ( ! empty( $args['advanced'] ) ) {
			$extra = \Wenpai_Admin_UI::mode_attrs( 'advanced' );
		}
		$cls = 'field' . ( isset( $args['class'] ) && '' !== (string) $args['class'] ? ' ' . (string) $args['class'] : '' );
		echo '<div class="' . esc_attr( $cls ) . '"' . $extra . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( '' !== $for ) {
			echo '<label class="field-label" for="' . esc_attr( $for ) . '">' . esc_html( $label ) . '</label>';
		} else {
			echo '<div class="field-label">' . esc_html( $label ) . '</div>';
		}
		echo '<div class="field-ctl">';
		echo $ctl; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller HTML.
		if ( '' !== $bad ) {
			echo '<p class="hint is-bad" data-wpdocs-bad hidden>' . esc_html( $bad ) . '</p>';
		}
		if ( '' !== $help ) {
			echo '<p class="hint">' . esc_html( $help ) . '</p>';
		}
		echo '</div></div>';
	}

	/**
	 * Status row — same shape as 智储 overview: tile icon | title+detail | pill.
	 *
	 * @param array{title:string,detail?:string,pill?:string,pill_class?:string,url?:bool,icon?:string,tile_class?:string} $args
	 */
	public static function status_row( array $args ): void {
		$title  = (string) ( $args['title'] ?? '' );
		$detail = (string) ( $args['detail'] ?? '' );
		$pill   = (string) ( $args['pill'] ?? '' );
		$pcls   = (string) ( $args['pill_class'] ?? '' );
		$icon   = (string) ( $args['icon'] ?? 'info' );
		$tile   = (string) ( $args['tile_class'] ?? $pcls );
		$row    = array(
			'icon_html' => self::icon( $icon ),
			'state'     => $tile,
			'title'     => $title,
		);
		if ( '' !== $detail ) {
			$row['desc'] = $detail;
		}
		if ( '' !== $pill ) {
			$row['pill'] = array(
				'text'  => $pill,
				'state' => $pcls,
			);
		}
		echo \Wenpai_Admin_UI::row_html( $row );
	}


	/**
	 * Kit choice cards (native radio).
	 *
	 * @param string               $name    Input name.
	 * @param string               $current Current value.
	 * @param array<string,array{label:string,desc?:string,icon?:string}> $choices Value => meta.
	 */
	public static function choice_cards( string $name, string $current, array $choices ): string {
		$cols = 2 === count( $choices ) ? ' cols-2' : '';
		$out  = '<div class="choice-cards' . $cols . '">';
		foreach ( $choices as $k => $c ) {
			$c    = is_array( $c ) ? $c : array( 'label' => (string) $c );
			$on   = (string) $k === (string) $current;
			$cls  = 'choice' . ( $on ? ' is-on' : '' );
			$icon = '';
			if ( ! empty( $c['icon'] ) ) {
				$icon = '<span class="choice-ico" aria-hidden="true">' . self::icon( (string) $c['icon'] ) . '</span>';
			}
			$out .= '<label class="' . esc_attr( $cls ) . '">';
			$out .= '<input type="radio" name="' . esc_attr( $name ) . '" value="' . esc_attr( (string) $k ) . '"' . checked( $on, true, false ) . ' />';
			$out .= $icon;
			$out .= '<span><span class="t">' . esc_html( (string) ( $c['label'] ?? '' ) ) . '</span>';
			if ( ! empty( $c['desc'] ) ) {
				$out .= '<span class="d">' . esc_html( (string) $c['desc'] ) . '</span>';
			}
			$out .= '</span></label>';
		}
		return $out . '</div>';
	}

	/**
	 * Form-compatible kit switch (hidden 0/1 + .wenpai-switch checkbox).
	 *
	 * @param string $name  Input name (writes 0 then 1 when on).
	 * @param bool   $on    Current.
	 * @param string $label_on  on-state text.
	 * @param string $label_off off-state text.
	 */
	public static function toggle_input( string $name, bool $on, string $label_on = '', string $label_off = '' ): string {
		$label_on  = '' !== $label_on ? $label_on : __( '已开启', 'wpdocs' );
		$label_off = '' !== $label_off ? $label_off : __( '未开启', 'wpdocs' );
		if ( class_exists( 'Wenpai_Admin_Settings', false ) ) {
			return \Wenpai_Admin_Settings::switch_html(
				array(
					'name'    => $name,
					'checked' => $on,
					'on'      => $label_on,
					'off'     => $label_off,
				)
			);
		}
		$html  = '<input type="hidden" name="' . esc_attr( $name ) . '" value="0" />';
		$html .= '<input type="checkbox" role="switch" name="' . esc_attr( $name ) . '" value="1" ' . checked( $on, true, false ) . ' />';
		return $html;
	}

	/**
	 * Tools / settings section subnav (.wenpai-subnav > .seg).
	 *
	 * @param string                         $current Current section id.
	 * @param array<int,array{id:string,label:string,url:string}> $items Items.
	 */
	public static function subnav( string $current, array $items ): void {
		echo \Wenpai_Admin_UI::subnav_html(
			array(
				'current' => $current,
				'label'   => __( '本页分区', 'wpdocs' ),
				'items'   => $items,
			)
		);
	}

	/**
	 * Prefer kit Remix paths; product tab aliases only when kit has no key.
	 */
	public static function icon( string $name ): string {
		// Product-specific keys first — kit svg() falls back to info for unknown names.
		$local = self::product_icon_path( $name );
		if ( '' !== $local ) {
			return '<svg class="wenpai-ico" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="' . esc_attr( $local ) . '"/></svg>';
		}
		$kit_name = self::kit_alias( $name );
		if ( class_exists( 'Wenpai_Admin_Icons', false ) ) {
			$html = \Wenpai_Admin_Icons::svg( $kit_name );
			if ( '' !== $html ) {
				return $html;
			}
		}
		return '';
	}

	/** Map product tab names onto kit semantic keys when possible. */
	private static function kit_alias( string $name ): string {
		$aliases = array(
			'tools' => 'M5.32943 3.27158C6.56252 2.8332 7.9923 3.2547 8.82843 4.09082C9.66455 4.92695 10.0861 6.35673 9.64767 7.58981L20.0019 17.944L17.9999 19.946L7.64575 9.59176C6.41267 10.0301 4.98289 9.60865 4.14676 8.77252C3.31064 7.9364 2.88914 6.50661 3.32751 5.27353L5.44363 7.38965C6.02942 7.97543 6.97916 7.97543 7.56495 7.38965C8.15074 6.80386 8.15074 5.85411 7.56495 5.26832L5.32943 3.27158ZM15.7073 13.2928L17.9999 15.5855L15.5855 17.9999L13.2928 15.7073L15.7073 13.2928ZM18.3641 8.22174L20.4855 6.10035C20.8759 5.70982 20.8759 5.07666 20.4855 4.68613L19.4141 3.61474C19.0235 3.22421 18.3904 3.22421 17.9999 3.61474L15.8784 5.73613L18.3641 8.22174ZM8.22174 18.3641L5.73613 15.8784L3.61474 17.9999C3.22421 18.3904 3.22421 19.0235 3.61474 19.4141L4.68613 20.4855C5.07666 20.8759 5.70982 20.8759 6.10035 20.4855L8.22174 18.3641Z',
			'settings' => 'settings',
		);
		return $aliases[ $name ] ?? $name;
	}

	/**
	 * Product-only Remix line paths (MIT RemixIcon v3.5.0) for keys absent from kit.
	 *
	 * @return string Path `d` or empty.
	 */
	private static function product_icon_path( string $name ): string {
		$map = array(
			// error-warning-line — status tab (kit maps status→info first).
			'status' => 'M13 9H21L11 24V15H4L13 0V9ZM11 11V7.22063L7.53238 13H13V17.3944L17.263 11H11Z',
			// Editor/link (chain).
			'link'   => 'M18.3643 15.5353L16.95 14.1211L18.3643 12.7069C20.3169 10.7543 20.3169 7.58847 18.3643 5.63585C16.4116 3.68323 13.2458 3.68323 11.2932 5.63585L9.87898 7.05007L8.46477 5.63585L9.87898 4.22164C12.6127 1.48797 17.0448 1.48797 19.7785 4.22164C22.5121 6.95531 22.5121 11.3875 19.7785 14.1211L18.3643 15.5353ZM15.5358 18.3638L14.1216 19.778C11.388 22.5117 6.9558 22.5117 4.22213 19.778C1.48846 17.0443 1.48846 12.6122 4.22213 9.87849L5.63634 8.46428L7.05055 9.87849L5.63634 11.2927C3.68372 13.2453 3.68372 16.4112 5.63634 18.3638C7.58896 20.3164 10.7548 20.3164 12.7074 18.3638L14.1216 16.9496L15.5358 18.3638ZM14.8287 7.75717L16.2429 9.17139L9.17187 16.2425L7.75766 14.8282L14.8287 7.75717Z',
			'tools' => 'M5.32943 3.27158C6.56252 2.8332 7.9923 3.2547 8.82843 4.09082C9.66455 4.92695 10.0861 6.35673 9.64767 7.58981L20.0019 17.944L17.9999 19.946L7.64575 9.59176C6.41267 10.0301 4.98289 9.60865 4.14676 8.77252C3.31064 7.9364 2.88914 6.50661 3.32751 5.27353L5.44363 7.38965C6.02942 7.97543 6.97916 7.97543 7.56495 7.38965C8.15074 6.80386 8.15074 5.85411 7.56495 5.26832L5.32943 3.27158ZM15.7073 13.2928L17.9999 15.5855L15.5855 17.9999L13.2928 15.7073L15.7073 13.2928ZM18.3641 8.22174L20.4855 6.10035C20.8759 5.70982 20.8759 5.07666 20.4855 4.68613L19.4141 3.61474C19.0235 3.22421 18.3904 3.22421 17.9999 3.61474L15.8784 5.73613L18.3641 8.22174ZM8.22174 18.3641L5.73613 15.8784L3.61474 17.9999C3.22421 18.3904 3.22421 19.0235 3.61474 19.4141L4.68613 20.4855C5.07666 20.8759 5.70982 20.8759 6.10035 20.4855L8.22174 18.3641Z',
			'settings' => 'M12 1L21.5 6.5V17.5L12 23L2.5 17.5V6.5L12 1ZM12 3.311L4.5 7.65311V16.3469L12 20.689L19.5 16.3469V7.65311L12 3.311ZM12 16C9.79086 16 8 14.2091 8 12C8 9.79086 9.79086 8 12 8C14.2091 8 16 9.79086 16 12C16 14.2091 14.2091 16 12 16ZM12 14C13.1046 14 14 13.1046 14 12C14 10.8954 13.1046 10 12 10C10.8954 10 10 10.8954 10 12C10 13.1046 10.8954 14 12 14Z',
			// share-forward-line.
			'share'  => 'M13 14H11C7.54202 14 4.53953 15.9502 3.03239 18.8107C3.01093 18.5433 3 18.2729 3 18C3 12.4772 7.47715 8 13 8V2.5L23.5 11L13 19.5V14ZM11 12H15V15.3078L20.3214 11L15 6.69224V10H13C10.5795 10 8.41011 11.0749 6.94312 12.7735C8.20873 12.2714 9.58041 12 11 12Z',
		);
		return $map[ $name ] ?? '';
	}

	public static function saved_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['updated'] ) ) {
			return;
		}
		echo '<div class="wenpai-notice-wrap" data-wenpai-fade><div class="notice ok"><span>' . esc_html__( '已保存。', 'wpdocs' ) . '</span></div></div>';
	}

	public static function error_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['error'] ) ) {
			return;
		}
		echo '<div class="notice bad"><span>' . esc_html__( '未能保存，请检查必填项后重试。', 'wpdocs' ) . '</span></div>';
	}

	/** @return array{label:string,class:string} */
	public static function redirect_status_pill( string $status ): array {
		$map = array(
			'matched'   => array(
				'label' => __( '已匹配 · 301', 'wpdocs' ),
				'class' => 'ok',
			),
			'gone'      => array(
				'label' => __( '已下线 · 410', 'wpdocs' ),
				'class' => 'bad',
			),
			'pending'   => array(
				'label' => __( '待处理', 'wpdocs' ),
				'class' => 'warn',
			),
			'unmatched' => array(
				'label' => __( '未匹配', 'wpdocs' ),
				'class' => 'warn',
			),
		);
		return $map[ $status ] ?? array(
			'label' => $status,
			'class' => 'warn',
		);
	}

	/** @return array{label:string,class:string} */
	public static function redir_status_pill( string $status ): array {
		return self::redirect_status_pill( $status );
	}

	/** @return array{label:string,class:string} */
	public static function bind_status_pill( string $status ): array {
		if ( 'disabled' === $status ) {
			return array(
				'label' => __( '已停用', 'wpdocs' ),
				'class' => 'warn',
			);
		}
		return array(
			'label' => __( '已启用', 'wpdocs' ),
			'class' => 'ok',
		);
	}

	/**
	 * Remote BookStack probe pill for a binding row.
	 *
	 * @param array{state?:string}|string $meta_or_state
	 * @return array{label:string,class:string}
	 */
	public static function bind_remote_pill( $meta_or_state ): array {
		$state = is_array( $meta_or_state )
			? (string) ( $meta_or_state['state'] ?? '' )
			: (string) $meta_or_state;
		$map = array(
			'ok'        => array(
				'label' => __( '远端正常', 'wpdocs' ),
				'class' => 'ok',
			),
			'broken'    => array(
				'label' => __( '远端失效', 'wpdocs' ),
				'class' => 'bad',
			),
			'stale'     => array(
				'label' => __( '别名已变', 'wpdocs' ),
				'class' => 'warn',
			),
			'forbidden' => array(
				'label' => __( '无权限', 'wpdocs' ),
				'class' => 'bad',
			),
			'skipped'   => array(
				'label' => __( '未探测', 'wpdocs' ),
				'class' => '',
			),
			'unknown'   => array(
				'label' => __( '探测失败', 'wpdocs' ),
				'class' => 'warn',
			),
		);
		return $map[ $state ] ?? $map['unknown'];
	}

	public static function bind_source_label( string $source ): string {
		$map = array(
			'manual' => __( '手工', 'wpdocs' ),
			'slug'   => __( '别名匹配', 'wpdocs' ),
			'import' => __( '导入', 'wpdocs' ),
			'auto'   => __( '自动', 'wpdocs' ),
		);
		return $map[ $source ] ?? $source;
	}
}
