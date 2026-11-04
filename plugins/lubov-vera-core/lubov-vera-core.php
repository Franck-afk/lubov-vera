<?php
/**
 * Plugin Name: Любовь и Вера — ядро
 * Plugin URI:  https://lubov-vera.test
 * Description: Данные сайта (опция lv_site_settings), хелперы для темы и CPT «Свидетельства»/«Служения». Без экрана настроек — он добавляется отдельно.
 * Version:     1.0.0
 * Requires PHP: 7.4
 * Author:      Любовь и Вера
 * Text Domain: lubov-vera-core
 *
 * @package LV_Core
 */

defined( 'ABSPATH' ) || exit;

define( 'LV_CORE_VERSION', '1.0.0' );
define( 'LV_CORE_FILE', __FILE__ );
define( 'LV_CORE_PATH', plugin_dir_path( __FILE__ ) );
define( 'LV_CORE_URL', plugin_dir_url( __FILE__ ) );

// Слой данных подключаем раньше хелперов: helpers.php зовёт Options в рантайме,
// но класс должен быть объявлен к моменту первого вызова.
require_once LV_CORE_PATH . 'includes/class-options.php';
require_once LV_CORE_PATH . 'includes/helpers.php';
require_once LV_CORE_PATH . 'includes/class-post-types.php';

// Обязательный бутстрап: конструктор класса вешает хук на init, и он должен
// попасть в очередь ДО того, как init отработает. Плагины грузятся раньше
// срабатывания init, поэтому вызываем здесь.
\LV_Core\Post_Types::get_instance();

// CPT сами вешают свой хук на init (приоритет 10) в конструкторе класса.

// Активация/деактивация: только пересборка правил перезаписи.
// Опции НЕ трогаются — данные лежат в lv_site_settings и переживают отключение.
register_activation_hook( __FILE__, 'lv_core_on_activate' );
register_deactivation_hook( __FILE__, 'lv_core_on_deactivate' );

/**
 * При активации: сразу регистрируем CPT, чтобы правила записались вместе с ними.
 */
function lv_core_on_activate() {
	if ( class_exists( '\LV_Core\Post_Types' ) ) {
		\LV_Core\Post_Types::get_instance()->register();
	}
	flush_rewrite_rules();
}

/**
 * При деактивации: убираем правила CPT. Данные остаются в базе.
 */
function lv_core_on_deactivate() {
	flush_rewrite_rules();
}
