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
		$simple  = add_query_arg(
			array(
				'page' => $page,
				'mode' => 'simple',
			),
			admin_url( 'admin.php' )
		);
		$advanced = add_query_arg(
			array(
				'page' => $page,
				'mode' => 'advanced',
			),
			admin_url( 'admin.php' )
		);
		echo '<div class="mode"><nav class="seg" aria-label="' . esc_attr__( '显示深度', 'wpdocs' ) . '">';
		echo '<a class="' . ( $adv ? '' : 'on' ) . '" href="' . esc_url( $simple ) . '">' . esc_html__( '简单', 'wpdocs' ) . '</a>';
		echo '<a class="' . ( $adv ? 'on' : '' ) . '" href="' . esc_url( $advanced ) . '">' . esc_html__( '高级', 'wpdocs' ) . '</a>';
		echo '</nav></div>';
	}

	public static function is_advanced(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['mode'] ) && 'advanced' === sanitize_key( (string) wp_unslash( $_GET['mode'] ) );
	}

	/**
	 * Stacked field (APPROVAL prototypes: label → control → hint).
	 * Product CSS forces `.field` to one column; kit default is 280px|1fr settings grid.
	 *
	 * @param array{label:string,help?:string,for?:string,control:string,advanced?:bool} $args
	 */
	public static function field( array $args ): void {
		$for   = isset( $args['for'] ) ? (string) $args['for'] : '';
		$label = (string) ( $args['label'] ?? '' );
		$help  = (string) ( $args['help'] ?? '' );
		$ctl   = (string) ( $args['control'] ?? '' );
		$extra = '';
		if ( ! empty( $args['advanced'] ) ) {
			$extra = ' data-for-mode="advanced"';
			if ( ! self::is_advanced() ) {
				$extra .= ' hidden';
			}
		}
		echo '<div class="field"' . $extra . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		if ( '' !== $for ) {
			echo '<label for="' . esc_attr( $for ) . '">' . esc_html( $label ) . '</label>';
		} else {
			echo '<span class="field-label">' . esc_html( $label ) . '</span>';
		}
		echo $ctl; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- caller HTML.
		if ( '' !== $help ) {
			echo '<p class="hint d">' . esc_html( $help ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * Probe / kv status row (APPROVAL: .k | .v | .pill — no tile).
	 *
	 * @param array{title:string,detail?:string,pill?:string,pill_class?:string,url?:bool} $args
	 */
	public static function status_row( array $args ): void {
		$title  = (string) ( $args['title'] ?? '' );
		$detail = (string) ( $args['detail'] ?? '' );
		$pill   = (string) ( $args['pill'] ?? '' );
		$pcls   = (string) ( $args['pill_class'] ?? '' );
		$is_url = ! empty( $args['url'] );
		echo '<div class="simple-row is-static wpdocs-kv">';
		echo '<div class="t">' . esc_html( $title ) . '</div>';
		if ( '' !== $detail ) {
			echo '<div class="d' . ( $is_url ? ' is-url' : '' ) . '">' . esc_html( $detail ) . '</div>';
		} else {
			echo '<div class="d"></div>';
		}
		echo '<div class="r">';
		if ( '' !== $pill ) {
			echo '<span class="pill' . ( '' !== $pcls ? ' ' . esc_attr( $pcls ) : '' ) . '">' . esc_html( $pill ) . '</span>';
		}
		echo '</div></div>';
	}

	public static function icon( string $name ): string {
		return class_exists( 'Wenpai_Admin_Icons', false ) ? \Wenpai_Admin_Icons::svg( $name ) : '';
	}

	public static function saved_notice(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( empty( $_GET['updated'] ) ) {
			return;
		}
		echo '<div class="notice ok"><span>' . esc_html__( '已保存。', 'wpdocs' ) . '</span></div>';
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
				'label' => __( '已失效 · 410', 'wpdocs' ),
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
