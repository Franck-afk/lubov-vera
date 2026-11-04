<?php
/**
 * Повторяющийся блок: список строк + ручка перетаскивания (порядок задаёт
 * jQuery UI sortable в settings.js) + кнопка добавления + шаблон для клонирования.
 *
 * Имена полей строятся как lv_options[key][<index>][subkey]. Пока строк нет,
 * отправляется маркер _repeating_{key}=1 — по нему sanitize() очищает значение.
 *
 * Ожидает: $field (key, label, desc, add_label, subfields[]), $saved.
 *
 * @package LV_Theme
 */

defined( 'ABSPATH' ) || exit;

$lv_key      = isset( $field['key'] ) ? (string) $field['key'] : '';
$lv_name     = lv_options_name( $lv_key );
$lv_id       = lv_options_field_id( $lv_key );
$lv_label    = isset( $field['label'] ) ? (string) $field['label'] : $lv_key;
$lv_desc     = isset( $field['desc'] ) ? (string) $field['desc'] : '';
$lv_add      = isset( $field['add_label'] ) ? (string) $field['add_label'] : __( '+ Добавить', 'lubov-vera' );
$lv_subs     = isset( $field['subfields'] ) && is_array( $field['subfields'] ) ? $field['subfields'] : array();
$lv_rows     = lv_options_value( $saved, $lv_key );
$lv_rows     = is_array( $lv_rows ) ? $lv_rows : array();
$lv_has_rows = ( count( $lv_rows ) > 0 );

/**
 * Печатает один набор контролов строки.
 *
 * @param array  $subs    Описания подполей.
 * @param string $base    Имя контейнера, например lv_options[socials][0].
 * @param array  $row     Значения строки.
 * @param string $idx     Индекс строки в имени; '__index__' для шаблона.
 * @param bool   $is_temp Шаблон для клонирования (disabled, без значения).
 *
 * @return void
 */
$lv_render_row = function ( array $subs, $base, array $row, $idx, $is_temp = false ) use ( $lv_key ) {
	$lv_disabled = $is_temp ? ' disabled' : '';
	?>
	<div class="lv-rep__row" data-lv-rep-row>
		<?php
		foreach ( $subs as $lv_sub ) :
			$lv_subkey = isset( $lv_sub['key'] ) ? (string) $lv_sub['key'] : '';
			if ( '' === $lv_subkey ) {
				continue;
			}
			// У строки на сервере id с её настоящим индексом, у шаблона — с плейсхолдером,
			// который подставит JS. Иначе у сохранённых строк были бы одинаковые id.
			$lv_sid    = ( '__index__' === $idx )
				? $lv_key . '_' . $lv_subkey . '__index__'
				: $lv_key . '_' . $lv_subkey . '_' . $idx;
			$lv_slabel = isset( $lv_sub['label'] ) ? (string) $lv_sub['label'] : $lv_subkey;
			$lv_stype  = isset( $lv_sub['type'] ) ? (string) $lv_sub['type'] : 'text';
			$lv_sval   = isset( $row[ $lv_subkey ] ) ? (string) $row[ $lv_subkey ] : '';
			$lv_sph    = isset( $lv_sub['placeholder'] ) ? (string) $lv_sub['placeholder'] : '';
			$lv_swide  = ! empty( $lv_sub['wide'] );
			$lv_sopts  = ( 'select' === $lv_stype && isset( $lv_sub['options'] ) && is_array( $lv_sub['options'] ) ) ? $lv_sub['options'] : array();
			?>
			<div class="lv-rep__col<?php echo $lv_swide ? ' lv-rep__col--wide' : ''; ?>">
				<label class="lv-rep__label" for="<?php echo esc_attr( $lv_sid ); ?>"><?php echo esc_html( $lv_slabel ); ?></label>

			<?php if ( $lv_sopts ) : ?>
				<select id="<?php echo esc_attr( $lv_sid ); ?>" name="<?php echo esc_attr( $base . '[' . $lv_subkey . ']' ); ?>"<?php echo esc_attr( $lv_disabled ); ?>>
					<?php foreach ( $lv_sopts as $lv_optval => $lv_optlbl ) : ?>
						<option value="<?php echo esc_attr( $lv_optval ); ?>"<?php selected( $lv_sval, $lv_optval ); ?>><?php echo esc_html( $lv_optlbl ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'media' === $lv_stype || 'file' === $lv_stype ) : ?>
				<?php
				// Компактный медиа-контрол строки. Дата-атрибуты data-lv-media-*
				// — тот же контракт, что у поля media.php, поэтому общий JS
				// медиатеки работает и здесь, и в клонированных строках.
				// Тип file — то же поле, но для документов: медиатека без
				// ограничения картинками, в превью показывается имя файла.
				$lv_is_file = ( 'file' === $lv_stype );
				$lv_has_img = ( '' !== $lv_sval );
				$lv_fname   = (string) wp_parse_url( $lv_sval, PHP_URL_PATH );
				$lv_fname   = '' !== $lv_fname ? wp_basename( $lv_fname ) : $lv_sval;
				?>
				<div class="lv-rep__media" data-lv-media<?php echo $lv_is_file ? ' data-lv-file' : ''; ?>>
					<div class="lv-rep__media-preview lv-media__preview<?php echo $lv_has_img ? '' : ' is-empty'; ?>">
						<?php if ( $lv_has_img ) : ?>
							<?php if ( $lv_is_file ) : ?>
							<span class="lv-media__file" title="<?php echo esc_attr( $lv_fname ); ?>"><?php echo esc_html( $lv_fname ); ?></span>
							<?php else : ?>
							<img src="<?php echo esc_url( $lv_sval ); ?>" alt="" />
							<?php endif; ?>
						<?php else : ?>
							<span class="lv-media__placeholder"><?php echo esc_html( $lv_is_file ? __( 'Нет файла', 'lubov-vera' ) : __( 'Нет', 'lubov-vera' ) ); ?></span>
						<?php endif; ?>
					</div>

					<div class="lv-rep__media-controls">
						<input
							type="text"
							id="<?php echo esc_attr( $lv_sid ); ?>"
							name="<?php echo esc_attr( $base . '[' . $lv_subkey . ']' ); ?>"
							value="<?php echo esc_attr( $lv_sval ); ?>"
							placeholder="<?php echo esc_attr( '' !== $lv_sph ? $lv_sph : ( $lv_is_file ? 'URL файла' : 'URL картинки' ) ); ?>"
							class="lv-rep__media-url"
							data-lv-media-url
							<?php echo esc_attr( $lv_disabled ); ?>
						/>
						<div class="lv-rep__media-buttons">
							<button type="button" class="button button-small" data-lv-media-select<?php echo esc_attr( $lv_disabled ); ?>><?php esc_html_e( 'Выбрать', 'lubov-vera' ); ?></button>
							<button type="button" class="button button-small" data-lv-media-remove<?php echo ( $lv_has_img ? '' : ' hidden' ) . esc_attr( $lv_disabled ); ?>><?php esc_html_e( 'Убрать', 'lubov-vera' ); ?></button>
						</div>
					</div>
				</div>
			<?php else : ?>
				<input
					type="<?php echo esc_attr( in_array( $lv_stype, array( 'email', 'url' ), true ) ? $lv_stype : 'text' ); ?>"
					id="<?php echo esc_attr( $lv_sid ); ?>"
					name="<?php echo esc_attr( $base . '[' . $lv_subkey . ']' ); ?>"
					value="<?php echo esc_attr( $lv_sval ); ?>"
					placeholder="<?php echo esc_attr( $lv_sph ); ?>"
					class="<?php echo esc_attr( 'datepicker' === $lv_stype ? 'lv-rep__date lv-datepicker' : '' ); ?>"
					data-lv-datepicker="<?php echo esc_attr( 'datepicker' === $lv_stype ? '1' : '' ); ?>"
					<?php echo esc_attr( $lv_disabled ); ?>
				/>
			<?php endif; ?>
			</div>
		<?php endforeach; ?>

			<div class="lv-rep__actions">
				<span class="lv-rep__drag" data-lv-rep-drag title="<?php esc_attr_e( 'Перетащите, чтобы изменить порядок', 'lubov-vera' ); ?>">
					<span class="dashicons dashicons-menu"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Изменить порядок строк', 'lubov-vera' ); ?></span>
				</span>
				<button type="button" class="lv-rep__copy" data-lv-rep-copy title="<?php esc_attr_e( 'Копировать строку', 'lubov-vera' ); ?>"<?php echo esc_attr( $lv_disabled ); ?>>
					<span class="dashicons dashicons-admin-page"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Копировать строку', 'lubov-vera' ); ?></span>
				</button>
				<button type="button" class="lv-rep__remove" data-lv-rep-remove title="<?php esc_attr_e( 'Удалить строку', 'lubov-vera' ); ?>"<?php echo esc_attr( $lv_disabled ); ?>>
					<span class="dashicons dashicons-no-alt"></span>
					<span class="screen-reader-text"><?php esc_html_e( 'Удалить строку', 'lubov-vera' ); ?></span>
				</button>
			</div>
	</div>
	<?php
};
?>
<div class="lv-field" data-lv-repeater="<?php echo esc_attr( $lv_key ); ?>">
	<label class="lv-field-label"><?php echo esc_html( $lv_label ); ?></label>

	<div class="lv-field-body">
		<?php if ( '' !== $lv_desc ) : ?>
			<p class="description"><?php echo esc_html( $lv_desc ); ?></p>
		<?php endif; ?>

		<div class="lv-rep" data-lv-rep>
			<div class="lv-rep__rows" data-lv-rep-rows>
				<?php
				foreach ( $lv_rows as $lv_i => $lv_row ) :
					$lv_row = is_array( $lv_row ) ? $lv_row : array();
					$lv_render_row( $lv_subs, $lv_name . '[' . (int) $lv_i . ']', $lv_row, (string) (int) $lv_i, false );
				endforeach;
				?>
			</div>

			<p class="lv-rep__empty" data-lv-rep-empty<?php echo $lv_has_rows ? ' hidden' : ''; ?>>
				<?php esc_html_e( 'Строк пока нет.', 'lubov-vera' ); ?>
			</p>

			<button type="button" class="button lv-rep__add" data-lv-rep-add><?php echo esc_html( $lv_add ); ?></button>
		</div>

		<?php /* Маркер: при пустом списке он очищает значение при сохранении. */ ?>
		<input type="hidden" name="<?php echo esc_attr( $lv_name ); ?>[__lv_empty]" value="1" data-lv-rep-marker />

		<template data-lv-rep-template>
			<?php $lv_render_row( $lv_subs, $lv_name . '[__index__]', array(), '__index__', true ); ?>
		</template>
	</div>
</div>