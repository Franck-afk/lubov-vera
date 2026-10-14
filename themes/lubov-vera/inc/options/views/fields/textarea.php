<?php
/**
 * Контрол многострочного текста.
 *
 * Разметка общая для полей экрана: .lv-field > .lv-field-label +
 * .lv-field-body.
 *
 * Ожидает: $field (key, label, desc, rows, placeholder), $saved.
 *
 * @package LV_Theme
 */

defined( 'ABSPATH' ) || exit;

$lv_key   = isset( $field['key'] ) ? (string) $field['key'] : '';
$lv_name  = lv_options_name( $lv_key );
$lv_id    = lv_options_field_id( $lv_key );
$lv_label = isset( $field['label'] ) ? (string) $field['label'] : $lv_key;
$lv_desc  = isset( $field['desc'] ) ? (string) $field['desc'] : '';
$lv_rows  = ! empty( $field['rows'] ) ? absint( $field['rows'] ) : 3;
$lv_ph    = isset( $field['placeholder'] ) ? (string) $field['placeholder'] : '';
$lv_value = (string) lv_options_value( $saved, $lv_key );
?>
<div class="lv-field">
	<label class="lv-field-label" for="<?php echo esc_attr( $lv_id ); ?>"><?php echo esc_html( $lv_label ); ?></label>

	<div class="lv-field-body">
		<textarea
			id="<?php echo esc_attr( $lv_id ); ?>"
			name="<?php echo esc_attr( $lv_name ); ?>"
			rows="<?php echo esc_attr( (string) $lv_rows ); ?>"
			placeholder="<?php echo esc_attr( $lv_ph ); ?>"
			class="large-text"
		><?php echo esc_textarea( $lv_value ); ?></textarea>

		<?php if ( '' !== $lv_desc ) : ?>
			<p class="description"><?php echo esc_html( $lv_desc ); ?></p>
		<?php endif; ?>
	</div>
</div>