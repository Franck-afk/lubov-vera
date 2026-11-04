<?php
/**
 * Удаление данных плагина lubov-vera-core при делении плагина.
 *
 * OPT-IN: ничего не удаляется, пока пользователь сам не включит флаг
 * lv_core_remove_data_on_uninstall. Это сделано для того, чтобы не потерять
 * данные при СЛУЧАЙНОМ удалении плагина — настройки сайта (телефоны, соцсети,
 * тексты, выбор юридических страниц) набираются месяцами и не восстанавливаются
 * из бэкапа на конкретном staging.
 *
 * ЧТО УДАЛЯЕТСЯ (только при включённом флаге):
 *   - опцию lv_site_settings   — настройки сайта;
 *   - опцию lv_core_settings    — зарезервирована на будущее, сейчас не создаётся;
 *   - все transient'ы с префиксом lv_ (кэш; включает кэш темы lv_latest_rutube_video).
 *
 * ЧТО НЕ УДАЛЯЕТСЯ НИКОГДА:
 *   - записи, страницы, вложения;
 *   - post meta (_lv_single_image, _lv_post_gallery, _lv_author_role и прочие);
 *   - таксономические термины.
 *   Это контент клиента. Удаление — только отдельным явным решением.
 *
 * @package LV_Core
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Опция-флаг opt-in. Пока её нет или она не равна '1' — выходим молча.
 */
if ( '1' !== (string) get_option( 'lv_core_remove_data_on_uninstall', '' ) ) {
	return;
}

/* ------------------------------------------------------- Опции */

// Настройки сайта.
delete_option( 'lv_site_settings' );

// Зарезервировано под будущую архитектуру: на случай, если опция появится.
delete_option( 'lv_core_settings' );

/* ----------------------------------------------------- Transient'ы */

/*
 * Удалить все transient'ы с префиксом lv_.
 *
 * delete_transient() требует точного ключа, поэтому перебираем имена в options.
 * ВНИМАНИЕ: при внешнем объектном кэше (Redis/Memcached) transient'ы лежат
 * не в options, и такой перебор их не увидит. Это не ошибка — transient'ы
 * и так протухнут; данные сайта лежат только в опциях.
 */
global $wpdb;

/*
 * Префиксы transient'ов. % добавляется ПОСЛЕ esc_like(): сам esc_like()
 * экранирует и символ %, из-за чего шаблон ищет буквальный знак процента
 * и всегда возвращает пустую выборку.
 */
$prefixes = array(
	'_transient_lv_',
	'_transient_timeout_lv_',
	'_site_transient_lv_',
	'_site_transient_timeout_lv_',
);

foreach ( $prefixes as $prefix ) {
	$lv_names = $wpdb->get_col( // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- cleanup query.
		$wpdb->prepare(
			"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
			$wpdb->esc_like( $prefix ) . '%'
		)
	);

	if ( empty( $lv_names ) ) {
		continue;
	}

	foreach ( $lv_names as $name ) {
		delete_option( $name );
	}
}

// Флаг снимаем: очистка одноразовая. Если плагин поставят заново и снова
// удалят — данные новой установки останутся на месте, пока флаг не выставят
// вручную ещё раз.
delete_option( 'lv_core_remove_data_on_uninstall' );
