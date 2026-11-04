<?php
/**
 * Church info widget.
 *
 * @package LV_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Displays church address, phone, email and service times.
 */
class LV_Church_Info_Widget extends WP_Widget {

	/**
	 * Register widget.
	 */
	public function __construct() {
		parent::__construct(
			'lv_church_info',
			'Информация о церкви',
			array( 'description' => 'Адрес, телефон, email и расписание служений.' )
		);
	}

	/**
	 * Front-end display.
	 *
	 * @param array $args     Widget arguments.
	 * @param array $instance Saved values from database.
	 */
	public function widget( $args, $instance ) {
		$address  = function_exists( 'lv_get_address' ) ? lv_get_address() : '';
		$phone    = function_exists( 'lv_get_phone' ) ? lv_get_phone() : '';
		$email    = function_exists( 'lv_get_email' ) ? lv_get_email() : '';
		$schedule = function_exists( 'lv_get_schedule_groups' ) ? lv_get_schedule_groups() : array();

		echo wp_kses_post( $args['before_widget'] );

		// Адрес.
		echo '<div class="single-sidebar-title">' . esc_html__( 'Адрес церкви', 'lubov-vera' ) . '</div>';
		echo '<div class="single-church-address">';
		if ( $address ) {
			echo '<p><strong>' . esc_html( $address ) . '</strong></p>';
		}
		if ( $phone ) {
			echo '<p><a href="tel:' . esc_attr( preg_replace( '/[^\d+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a></p>';
		}
		if ( $email ) {
			echo '<p><a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a></p>';
		}
		echo '</div>';

		// Расписание.
		$schedule_title = lv_get_setting( 'schedule_title', 'Общецерковные служения' );
		echo '<div class="single-sidebar-title">' . esc_html( $schedule_title ) . '</div>';
		echo '<div class="single-service-times">';

		if ( ! empty( $schedule ) ) {
			foreach ( $schedule as $group ) {
				if ( empty( $group['times'] ) ) {
					continue;
				}

				echo '<p>' . esc_html( $group['short'] . '. ' . implode( ', ', $group['times'] ) ) . '</p>';
			}
		}

		echo '</div>';

		echo wp_kses_post( $args['after_widget'] );
	}

	/**
	 * Back-end form.
	 *
	 * @param array $instance Previously saved values from database.
	 */
	public function form( $instance ) {
		echo '<p>Виджет выводит данные из настроек сайта. Дополнительных полей нет.</p>';
	}

	/**
	 * Sanitize widget form values as they are saved.
	 *
	 * @param array $new_instance Values just sent to be saved.
	 * @param array $old_instance Previously saved values from database.
	 * @return array Updated safe values to be saved.
	 */
	public function update( $new_instance, $old_instance ) {
		return $old_instance;
	}
}
