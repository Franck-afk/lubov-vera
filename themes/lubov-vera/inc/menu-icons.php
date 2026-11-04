<?php
/**
 * Иконки для пунктов меню: поле в редакторе меню и сохранение значения.
 *
 * @package LV_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

add_action( 'wp_nav_menu_item_custom_fields', 'add_menu_icon_field', 10, 4 );

/**
 * Добавляет поле для иконки в редакторе меню.
 *
 * @param int $item_id ID пункта меню.
 * @return void
 */
function add_menu_icon_field( $item_id ) {
	$icon = get_post_meta( $item_id, '_menu_item_icon', true );
	// Добавляем nonce для безопасности.
	wp_nonce_field( 'menu_item_icon_nonce', 'menu_item_icon_nonce_field' );
	?>
	<p class="field-icon description description-wide">
		<label for="edit-menu-item-icon-<?php echo esc_attr( $item_id ); ?>">
			<?php esc_html_e( 'Иконка (класс Font Awesome)', 'lubov-vera' ); ?><br />
			<input type="text" id="edit-menu-item-icon-<?php echo esc_attr( $item_id ); ?>"
					class="widefat code edit-menu-item-icon"
					name="menu-item-icon[<?php echo esc_attr( $item_id ); ?>]"
					value="<?php echo esc_attr( $icon ); ?>" />
			<span class="description"><?php esc_html_e( 'Например: fas fa-home, far fa-user', 'lubov-vera' ); ?></span>
		</label>
	</p>
	<?php
}

add_action( 'wp_update_nav_menu_item', 'save_menu_icon_field', 10, 3 );

/**
 * Сохраняет иконку пункта меню с проверкой безопасности.
 *
 * @param int $menu_id         ID меню (не используется, нужен для позиции аргумента).
 * @param int $menu_item_db_id ID пункта меню.
 * @return void
 */
function save_menu_icon_field( $menu_id, $menu_item_db_id ) {
	// Проверяем nonce для защиты от CSRF.
	if ( ! isset( $_POST['menu_item_icon_nonce_field'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['menu_item_icon_nonce_field'] ) ), 'menu_item_icon_nonce' ) ) {
		return;
	}

	// Проверяем права пользователя на редактирование меню.
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	// Проверяем, что это не автосохранение.
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}

	if ( isset( $_POST['menu-item-icon'][ $menu_item_db_id ] ) ) {
		$icon_value = sanitize_text_field( wp_unslash( $_POST['menu-item-icon'][ $menu_item_db_id ] ) );
		// Дополнительная валидация: разрешаем только классы Font Awesome.
		if ( ! empty( $icon_value ) && ! preg_match( '/^fa[bsrl]?\sfa-[a-z0-9-]+(\sfa-[a-z0-9-]+)*$/', $icon_value ) ) {
			// Если не соответствует паттерну, очищаем.
			delete_post_meta( $menu_item_db_id, '_menu_item_icon' );
		} else {
			update_post_meta( $menu_item_db_id, '_menu_item_icon', $icon_value );
		}
	} else {
		delete_post_meta( $menu_item_db_id, '_menu_item_icon' );
	}
}
