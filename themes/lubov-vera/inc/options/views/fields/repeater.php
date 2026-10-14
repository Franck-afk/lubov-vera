<?php
/**
 * Повторяющийся блок: список строк + кнопка добавления + шаблон для клонирования.
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
				<?php else : ?>
					<input
						type="<?php echo esc_attr( in_array( $lv_stype, array( 'email', 'url' ), true ) ? $lv_stype : 'text' ); ?>"
						id="<?php echo esc_attr( $lv_sid ); ?>"
						name="<?php echo esc_attr( $base . '[' . $lv_subkey . ']' ); ?>"
						value="<?php echo esc_attr( $lv_sval ); ?>"
						placeholder="<?php echo esc_attr( $lv_sph ); ?>"
						<?php echo esc_attr( $lv_disabled ); ?>
					/>
				<?php endif; ?>
			</div>
		<?php endforeach; ?>

			<button type="button" class="lv-rep__remove" data-lv-rep-remove<?php echo esc_attr( $lv_disabled ); ?>>
			<span class="dashicons dashicons-no-alt"></span>
			<span class="screen-reader-text"><?php esc_html_e( 'Удалить строку', 'lubov-vera' ); ?></span>
		</button>
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