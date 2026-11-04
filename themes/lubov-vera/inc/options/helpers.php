<?php
/**
 * Вспомогательные функции экрана настроек.
 *
 * Каждая объявлена под своим function_exists: файл подключается один раз,
 * но страховка от «уже объявлено» стоит копейки и снимает класс ошибок
 * при смене родительской темы или повторном подключении.
 *
 * @package LV_Theme
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'lv_options_name' ) ) {
	/**
	 * Имя поля формы из ключа настройки.
	 * Плоский ключ: hero_title → lv_options[hero_title].
	 *
	 * @param string $key Ключ.
	 * @return string Имя поля формы.
	 */
	function lv_options_name( $key ) {
		return 'lv_options[' . (string) $key . ']';
	}
}

if ( ! function_exists( 'lv_options_value' ) ) {
	/**
	 * Текущее значение настройки.
	 *
	 * @param array  $saved    Все значения из lv_site_settings.
	 * @param string $key      Ключ.
	 * @param mixed  $fallback Значение по умолчанию.
	 * @return mixed Значение настройки или запасное значение.
	 */
	function lv_options_value( array $saved, $key, $fallback = '' ) {
		return array_key_exists( $key, $saved ) ? $saved[ $key ] : $fallback;
	}
}

if ( ! function_exists( 'lv_options_field_id' ) ) {
	/**
	 * ID для связи label[for] и контрола.
	 *
	 * @param string $key Ключ.
	 * @return string Идентификатор поля.
	 */
	function lv_options_field_id( $key ) {
		return 'lv-opt-' . preg_replace( '/[^a-z0-9_-]/i', '-', (string) $key );
	}
}
