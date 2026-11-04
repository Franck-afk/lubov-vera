<?php
/**
 * Правая колонка (.lv-options__sidebar) — карточки .lv-options__widget.
 *
 * Карточки с тенью и радиусом .5rem, вертикальным отступом 20/35px,
 * версия темы и справочные ссылки.
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

$lv_version = function_exists( 'wp_get_theme' ) ? wp_get_theme()->get( 'Version' ) : '';
?>

<div class="lv-options__widget">
	<strong><?php esc_html_e( 'Версия темы', 'lubov-vera' ); ?></strong>: <?php echo esc_html( $lv_version ); ?>
</div>

<div class="lv-options__widget">
	<strong><?php esc_html_e( 'Открытый раздел', 'lubov-vera' ); ?></strong>: <span data-lv-active-section><?php echo esc_html( $lv_label ); ?></span>
</div>

<div class="lv-options__widget">
	<div class="lv-options__widget__header"><?php esc_html_e( 'Об этом разделе', 'lubov-vera' ); ?></div>
	<div class="lv-options__widget__description">
		<?php
		$lv_section = isset( $tabs[ $active_tab ] ) ? (array) $tabs[ $active_tab ] : array();

		if ( ! empty( $lv_section['desc'] ) ) {
			echo esc_html( $lv_section['desc'] );
		} else {
			esc_html_e( 'Выберите раздел во вкладках слева, чтобы увидеть его описание.', 'lubov-vera' );
		}
		?>
	</div>
</div>

<div class="lv-options__widget">
	<div class="lv-options__widget__header"><?php esc_html_e( 'Быстрые ссылки', 'lubov-vera' ); ?></div>
	<div class="lv-options__widget__description lv-widget-links">
		<a href="<?php echo esc_url( admin_url( 'customize.php' ) ); ?>" target="_blank"><?php esc_html_e( 'Кастомайзер', 'lubov-vera' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'widgets.php' ) ); ?>"><?php esc_html_e( 'Виджеты', 'lubov-vera' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'nav-menus.php' ) ); ?>"><?php esc_html_e( 'Меню', 'lubov-vera' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'edit.php' ) ); ?>"><?php esc_html_e( 'Записи', 'lubov-vera' ); ?></a>
	</div>
</div>

<div class="lv-options__widget">
	<div class="lv-options__widget__description">
		<?php esc_html_e( 'Остальные настройки сайта: шрифты — в Кастомайзере, cookie-баннер — в плагине lubov-vera-core.', 'lubov-vera' ); ?>
	</div>
</div>
