<?php
/**
 * Контрол-переключатель (чекбокс в виде свитча).
 *
 * Скрытый input со значением 0 идёт перед чекбоксом: без него снятый чекбокс
 * не попадёт в POST, sanitize() отбросит ключ, а merge() оставит прежнее
 * значение — выключить настройку было бы невозможно. При включённом чекбоксе
 * PHP берёт последнее значение с тем же именем, то есть «1».
 *
 * Ожидает: $field (key, label, desc, default), $saved.
 *
 * @package LV_Theme
 */

defined( 'ABSPATH' ) || exit;

$lv_key     = isset( $field['key'] ) ? (string) $field['key'] : '';
$lv_name    = lv_options_name( $lv_key );
$lv_id      = lv_options_field_id( $lv_key );
$lv_label   = isset( $field['label'] ) ? (string) $field['label'] : $lv_key;
$lv_desc    = isset( $field['desc'] ) ? (string) $field['desc'] : '';
$lv_default = isset( $field['default'] ) ? (string) $field['default'] : '0';
$lv_value   = (string) lv_options_value( $saved, $lv_key, $lv_default );
$lv_is_on   = ( '1' === $lv_value );
?>
<div class="lv-field">
	<label class="lv-field-label" for="<?php echo esc_attr( $lv_id ); ?>"><?php echo esc_html( $lv_label ); ?></label>

	<div class="lv-field-body">
		<label class="lv-toggle" for="<?php echo esc_attr( $lv_id ); ?>">
			<input type="hidden" name="<?php echo esc_attr( $lv_name ); ?>" value="0" />
			<input
				type="checkbox"
				id="<?php echo esc_attr( $lv_id ); ?>"
				name="<?php echo esc_attr( $lv_name ); ?>"
				value="1"
				class="lv-toggle__input"
				<?php checked( $lv_is_on ); ?>
			/>
			<span class="lv-toggle__track" aria-hidden="true">
				<span class="lv-toggle__thumb"></span>
			</span>
			<span class="lv-toggle__state" aria-hidden="true">
				<span class="lv-toggle__on"><?php esc_html_e( 'Включено', 'lubov-vera' ); ?></span>
				<span class="lv-toggle__off"><?php esc_html_e( 'Выключено', 'lubov-vera' ); ?></span>
			</span>
		</label>

		<?php if ( '' !== $lv_desc ) : ?>
			<p class="description"><?php echo esc_html( $lv_desc ); ?></p>
		<?php endif; ?>
	</div>
</div>
