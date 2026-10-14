<?php
/**
 * Контрол однострочного текста. Обслуживает типы text, email и url —
 * различаются они только атрибутом type.
 *
 * Разметка общая для полей экрана: .lv-field > .lv-field-label +
 * .lv-field-body (контрол, затем p.description).
 *
 * Ожидает: $field (key, label, desc, placeholder, type), $saved.
 *
 * @package LV_Theme
 */

defined( 'ABSPATH' ) || exit;

$lv_key   = isset( $field['key'] ) ? (string) $field['key'] : '';
$lv_name  = lv_options_name( $lv_key );
$lv_id    = lv_options_field_id( $lv_key );
$lv_label = isset( $field['label'] ) ? (string) $field['label'] : $lv_key;
$lv_desc  = isset( $field['desc'] ) ? (string) $field['desc'] : '';
$lv_ph    = isset( $field['placeholder'] ) ? (string) $field['placeholder'] : '';
$lv_type  = ( isset( $field['type'] ) && in_array( $field['type'], array( 'email', 'url' ), true ) ) ? $field['type'] : 'text';
$lv_value = (string) lv_options_value( $saved, $lv_key );
?>
<div class="lv-field">
	<label class="lv-field-label" for="<?php echo esc_attr( $lv_id ); ?>"><?php echo esc_html( $lv_label ); ?></label>

	<div class="lv-field-body">
		<input
			type="<?php echo esc_attr( $lv_type ); ?>"
			id="<?php echo esc_attr( $lv_id ); ?>"
			name="<?php echo esc_attr( $lv_name ); ?>"
			value="<?php echo esc_attr( $lv_value ); ?>"
			placeholder="<?php echo esc_attr( $lv_ph ); ?>"
			class="regular-text"
		/>

		<?php if ( '' !== $lv_desc ) : ?>
			<p class="description"><?php echo esc_html( $lv_desc ); ?></p>
		<?php endif; ?>
	</div>
</div>