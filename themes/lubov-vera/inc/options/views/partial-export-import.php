<?php
/**
 * Панель «Экспорт / Импорт» — custom-секция схемы.
 *
 * Живёт вне главной формы настроек: здесь собственный <form> (импорт),
 * а вложенные формы в HTML запрещены. Таблица табов переключает эту панель
 * так же, как обычные — по data-panel.
 *
 * Ожидает: $tabs, $lv_key, $active_tab, $export_json.
 *
 * @package LV_Theme
 */

defined( 'ABSPATH' ) || exit;

$lv_section    = isset( $tabs[ $lv_key ] ) ? (array) $tabs[ $lv_key ] : array();
$lv_is_active  = ( (string) $lv_key === (string) $active_tab );
$lv_export_url = wp_nonce_url(
	admin_url( 'admin-post.php?action=lv_options_export' ),
	'lv_options',
	'_lv_nonce'
);
?>
<div
	class="lv-options__tab-panel<?php echo $lv_is_active ? ' active' : ''; ?>"
	id="lv-tab-in-<?php echo esc_attr( (string) $lv_key ); ?>"
	data-panel="<?php echo esc_attr( (string) $lv_key ); ?>"
	role="tabpanel"
	aria-labelledby="lv-tab-<?php echo esc_attr( (string) $lv_key ); ?>"
	<?php echo $lv_is_active ? '' : 'hidden'; ?>
>
	<div class="lv-field-header"><?php echo esc_html( isset( $lv_section['label'] ) ? $lv_section['label'] : '' ); ?></div>

	<?php if ( ! empty( $lv_section['desc'] ) ) : ?>
		<p><?php echo esc_html( $lv_section['desc'] ); ?></p>
	<?php endif; ?>

	<div class="lv-fields">

		<div class="lv-field">
			<label class="lv-field-label"><?php esc_html_e( 'Экспорт настроек', 'lubov-vera' ); ?></label>

			<div class="lv-field-body">
				<textarea
					class="large-text lv-code"
					rows="12"
					readonly
					data-lv-export-text
				><?php echo esc_textarea( $export_json ); ?></textarea>
				<p class="description">
					<?php esc_html_e( 'Копия текущих значений опции lv_site_settings. После сохранения настроек текст ниже не обновится — скачайте файл, он всегда свежий.', 'lubov-vera' ); ?>
				</p>

				<p class="lv-save-row">
					<a class="button button-secondary" href="<?php echo esc_url( $lv_export_url ); ?>"><?php esc_html_e( 'Скачать файл', 'lubov-vera' ); ?></a>
					<button type="button" class="button button-secondary" data-lv-export-copy><?php esc_html_e( 'Копировать', 'lubov-vera' ); ?></button>
					<span class="lv-save-row__status" data-lv-export-status></span>
				</p>
			</div>
		</div>

		<form
			id="lv-import-form"
			method="post"
			action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>"
		>
			<input type="hidden" name="action" value="lv_options_import" />
			<?php wp_nonce_field( 'lv_options', '_lv_nonce' ); ?>
			<input type="hidden" name="lv_active_tab" value="export_import" />

			<div class="lv-field">
				<label class="lv-field-label" for="lv-import-json"><?php esc_html_e( 'Импорт настроек', 'lubov-vera' ); ?></label>

				<div class="lv-field-body">
					<p class="description">
						<?php esc_html_e( 'Вставьте JSON из выгрузки или загрузите файл, затем нажмите «Импортировать». Ключи, которых нет в схеме, будут отброшены; остальные перезапишут текущие значения.', 'lubov-vera' ); ?>
					</p>

					<textarea
						id="lv-import-json"
						class="large-text lv-code"
						rows="12"
						name="lv_import_json"
						data-lv-import-text
					></textarea>

					<p>
						<input
							type="file"
							accept=".json,application/json,text/plain"
							data-lv-import-file
						/>
					</p>

					<p class="lv-save-row">
						<button type="submit" class="button button-primary" data-lv-import-submit>
							<span class="lv-spinner" hidden></span>
							<span data-lv-import-label><?php esc_html_e( 'Импортировать', 'lubov-vera' ); ?></span>
						</button>
						<span class="lv-save-row__status" data-lv-import-status></span>
					</p>
				</div>
			</div>
		</form>

	</div>
</div>
