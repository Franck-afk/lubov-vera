<?php
/**
 * Контрол загрузки картинки: поле URL + кнопка «Медиатека» + превью.
 *
 * Обёртка .lv-field/.lv-field-body — как у остальных полей экрана;
 * внутренняя сетка превью своя.
 *
 * Ожидает: $field (key, label, desc, placeholder), $saved.
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
$lv_value = (string) lv_options_value( $saved, $lv_key );
?>
<div class="lv-field" data-lv-media-field>
	<label class="lv-field-label" for="<?php echo esc_attr( $lv_id ); ?>"><?php echo esc_html( $lv_label ); ?></label>

	<div class="lv-field-body">
		<div class="lv-media" data-lv-media>
			<div class="lv-media__preview<?php echo ( '' === $lv_value ) ? ' is-empty' : ''; ?>">
				<?php if ( '' !== $lv_value ) : ?>
					<img src="<?php echo esc_url( $lv_value ); ?>" alt="" />
				<?php else : ?>
					<span class="lv-media__placeholder">Нет картинки</span>
				<?php endif; ?>
			</div>

			<div class="lv-media__controls">
				<input
					type="text"
					id="<?php echo esc_attr( $lv_id ); ?>"
					name="<?php echo esc_attr( $lv_name ); ?>"
					value="<?php echo esc_attr( $lv_value ); ?>"
					placeholder="<?php echo esc_attr( $lv_ph ); ?>"
					class="lv-media__url"
					data-lv-media-url
				/>
				<div class="lv-media__buttons">
					<button type="button" class="button" data-lv-media-select><?php esc_html_e( 'Выбрать', 'lubov-vera' ); ?></button>
					<button type="button" class="button lv-media__remove" data-lv-media-remove<?php echo ( '' === $lv_value ) ? ' hidden' : ''; ?>><?php esc_html_e( 'Убрать', 'lubov-vera' ); ?></button>
				</div>
			</div>
		</div>

		<?php if ( '' !== $lv_desc ) : ?>
			<p class="description"><?php echo esc_html( $lv_desc ); ?></p>
		<?php endif; ?>
	</div>
</div>