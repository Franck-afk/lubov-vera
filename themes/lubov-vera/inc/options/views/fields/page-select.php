<?php
/**
 * Контрол выбора страницы (для юридических документов).
 *
 * Разметка общая для полей экрана: .lv-field > .lv-field-label +
 * .lv-field-body.
 *
 * Ожидает: $field (key, label, desc), $saved.
 *
 * @package LV_Theme
 */

defined( 'ABSPATH' ) || exit;

$lv_key   = isset( $field['key'] ) ? (string) $field['key'] : '';
$lv_name  = lv_options_name( $lv_key );
$lv_id    = lv_options_field_id( $lv_key );
$lv_label = isset( $field['label'] ) ? (string) $field['label'] : $lv_key;
$lv_desc  = isset( $field['desc'] ) ? (string) $field['desc'] : '';
$lv_value = absint( lv_options_value( $saved, $lv_key, 0 ) );
?>
<div class="lv-field">
	<label class="lv-field-label" for="<?php echo esc_attr( $lv_id ); ?>"><?php echo esc_html( $lv_label ); ?></label>

	<div class="lv-field-body">
		<?php
		wp_dropdown_pages(
			array(
				'name'              => esc_attr( $lv_name ),
				'id'                => esc_attr( $lv_id ),
				'selected'          => absint( $lv_value ),
				'show_option_none'  => '— не выбрана —',
				'option_none_value' => '0',
			)
		);
		?>

		<?php if ( '' !== $lv_desc ) : ?>
			<p class="description"><?php echo esc_html( $lv_desc ); ?></p>
		<?php endif; ?>
	</div>
</div>