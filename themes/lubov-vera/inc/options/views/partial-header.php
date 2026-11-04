<?php
/**
 * Notice экрана: результат классического сохранения/сброса/импорта.
 *
 * Своего бара с кнопками нет — действия живут в .pseudo-button над формой
 * и в .lv-options__toolbar под ней. Поэтому здесь остаётся только
 * уведомление WP после редиректа.
 *
 * Ожидает: $updated ('saved' | 'reset' | 'imported' | 'import_failed' | ''), $imported.
 *
 * @package LV_Theme
 */

defined( 'ABSPATH' ) || exit;

if ( 'saved' === $updated ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Настройки сохранены.', 'lubov-vera' ); ?></p>
	</div>
<?php elseif ( 'reset' === $updated ) : ?>
	<div class="notice notice-warning is-dismissible">
		<p><?php esc_html_e( 'Настройки сброшены к значениям по умолчанию.', 'lubov-vera' ); ?></p>
	</div>
<?php elseif ( 'imported' === $updated ) : ?>
	<div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Настройки импортированы.', 'lubov-vera' ); ?></p>
	</div>
<?php elseif ( 'import_failed' === $updated ) : ?>
	<div class="notice notice-error is-dismissible">
		<p><?php esc_html_e( 'Не удалось импортировать: JSON повреждён или не разобран.', 'lubov-vera' ); ?></p>
	</div>
<?php endif; ?>