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
	 * Kit field: label column + control column.
	 *
	 * @param array{label:string,help?:string,for?:string,control:string} $args
	 */
	public static function field( array $args ): void {
		$for   = isset( $args['for'] ) ? (string) $args['for'] : '';
		$label = (string) ( $args['label'] ?? '' );
		$help  = (string) ( $args['help'] ?? '' );
		$ctl   = (string) ( $args['control'] ?? '' );
		echo '<div class="field"><div>';
		echo '<div class="field-label">';
		if ( '' !== $for ) {
			echo '<label for="' . esc_attr( $for ) . '">' . esc_html( $label ) . '</label>';
		} else {
			echo esc_html( $label );
		}
		echo '</div>';
		if ( '' !== $help ) {
			echo '<div class="field-help">' . esc_html( $help ) . '</div>';
		}
		echo '</div><div class="field-ctl">' . $ctl . '</div></div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	}

	/**
	 * Overview status row.
	 *
	 * @param array{title:string,detail?:string,pill?:string,pill_class?:string,tile_class?:string,icon_html?:string} $args
	 */
	public static function status_row( array $args ): void {
		$title = (string) ( $args['title'] ?? '' );
		$detail = (string) ( $args['detail'] ?? '' );
		$pill   = (string) ( $args['pill'] ?? '' );
		$pcls   = (string) ( $args['pill_class'] ?? '' );
		$tcls   = (string) ( $args['tile_class'] ?? '' );
		$icon   = (string) ( $args['icon_html'] ?? '' );
		echo '<div class="simple-row is-static">';
		echo '<div class="tile' . ( '' !== $tcls ? ' ' . esc_attr( $tcls ) : '' ) . '">' . $icon . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		echo '<div><div class="t">' . esc_html( $title ) . '</div>';
		if ( '' !== $detail ) {
			echo '<div class="d">' . esc_html( $detail ) . '</div>';
		}
		echo '</div>';
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
		if ( empty( $_GET['updated'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}
		echo '<div class="notice ok"><span>' . esc_html__( '已保存。', 'wpdocs' ) . '</span></div>';
	}
}
