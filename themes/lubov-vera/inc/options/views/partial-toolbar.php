<?php
/**
 * Панель сохранения (.lv-options__toolbar).
 *
 * Сидит внизу формы, внутри левой колонки: белая подложка, верхняя граница,
 * скругление снизу и тень вверх. Показывает кнопки и строку статуса AJAX.
 *
 * Ожидает: $tabs, $active_tab.
 *
 * @package LV_Theme
 */

defined( 'ABSPATH' ) || exit;

$lv_label = '';
if ( isset( $tabs[ $active_tab ]['label'] ) ) {
	$lv_label = (string) $tabs[ $active_tab ]['label'];
}
?>
<div class="lv-save-row">
	<span class="lv-save-row__section"><?php echo esc_html( $lv_label ); ?></span>

	<?php // Строка статуса: текст результата AJAX-сохранения. ?>
	<p class="lv-save-row__status" data-lv-status role="status" aria-live="polite"></p>

	<div class="lv-save-row__actions">
		<?php // Сброс идёт через подтверждение, а не обычным submit. ?>
		<button type="button" class="button lv-options__reset" data-lv-reset>
			<?php esc_html_e( 'Сбросить всё', 'lubov-vera' ); ?>
		</button>

		<button type="submit" class="button button-primary button-large lv-save" data-lv-save>
			<span class="lv-spinner" data-lv-spinner hidden></span>
			<span class="lv-save__label" data-lv-save-label><?php esc_html_e( 'Сохранить', 'lubov-vera' ); ?></span>
		</button>
	</div>
</div>