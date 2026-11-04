<?php
/**
 * Custom Walker_Nav_Menu для церковного сайта
 * Поддерживает:
 * - Десктопное меню с выпадающими списками
 * - Мобильное меню (Volcker/гамбургер)
 * - Иконки для пунктов меню
 * - Правильные CSS классы
 *
 * @package LV_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Кастомный walker для вывода меню навигации.
 */
class Church_Walker_Nav_Menu extends Walker_Nav_Menu {

	/**
	 * Текущий элемент.
	 *
	 * @var string
	 */
	public $current_item = '';

	/**
	 * Классы элемента.
	 *
	 * @var array
	 */
	public $item_classes = array();

	/**
	 * Флаг мобильного меню.
	 *
	 * @var bool
	 */
	public $is_mobile = false;

	/**
	 * Конструктор для определения типа меню.
	 *
	 * @param bool $is_mobile Является ли мобильным меню.
	 */
	public function __construct( $is_mobile = false ) {
		$this->is_mobile = $is_mobile;
	}

	/**
	 * Starts the list before the elements are added.
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param int      $depth  Depth of menu item. Used for padding.
	 * @param stdClass $args   An object of wp_nav_menu() arguments.
	 */
	public function start_lvl( &$output, $depth = 0, $args = null ) {
		if ( $this->is_mobile ) {
			// Мобильная версия сабменю.
			$indent  = str_repeat( "\t", $depth );
			$output .= "\n$indent<ul class=\"mobile-submenu\">\n";
		} else {
			// Десктопная версия сабменю.
			$indent  = str_repeat( "\t", $depth );
			$output .= "\n$indent<ul class=\"submenu\">\n";
		}
	}

	/**
	 * Ends the list of after the elements are added.
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param int      $depth  Depth of menu item. Used for padding.
	 * @param stdClass $args   An object of wp_nav_menu() arguments.
	 */
	public function end_lvl( &$output, $depth = 0, $args = null ) {
		$indent  = str_repeat( "\t", $depth );
		$output .= "$indent</ul>\n";
	}

	/**
	 * Start the element output.
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param WP_Post  $item   Menu item data object.
	 * @param int      $depth  Depth of menu item. Used for padding.
	 * @param stdClass $args   An object of wp_nav_menu() arguments.
	 * @param int      $id     Current item ID.
	 */
	public function start_el( &$output, $item, $depth = 0, $args = null, $id = 0 ) {
		$indent = ( $depth ) ? str_repeat( "\t", $depth ) : '';

		// Получаем классы для текущего элемента.
		$classes   = empty( $item->classes ) ? array() : (array) $item->classes;
		$classes[] = 'nav__item';

		// Добавляем класс для элементов с дочерними пунктами.
		if ( in_array( 'menu-item-has-children', $classes, true ) ) {
			$classes[] = 'nav__item--has-submenu';
		}

		// Добавляем класс для текущей страницы.
		if ( in_array( 'current-menu-item', $classes, true ) ) {
			$classes[] = 'active';
		}

		// Фильтруем классы.
		$class_names = join( ' ', apply_filters( 'nav_menu_css_class', array_filter( $classes ), $item, $args, $depth ) );
		$class_names = $class_names ? ' class="' . esc_attr( $class_names ) . '"' : '';

		// Собираем ID элемента.
		$item_id = apply_filters( 'nav_menu_item_id', 'menu-item-' . $item->ID, $item, $args, $depth );
		$item_id = $item_id ? ' id="' . esc_attr( $item_id ) . '"' : '';

		// Начинаем формировать элемент.
		$output .= $indent . '<li' . $item_id . $class_names . '>';

		// Получаем атрибуты ссылки.
		$atts           = array();
		$atts['title']  = ! empty( $item->attr_title ) ? $item->attr_title : '';
		$atts['target'] = ! empty( $item->target ) ? $item->target : '';
		$atts['rel']    = ! empty( $item->xfn ) ? $item->xfn : '';
		$atts['href']   = ! empty( $item->url ) ? $item->url : '';

		// Получаем иконку из пользовательского поля.
		$icon = get_post_meta( $item->ID, '_menu_item_icon', true );

		// Проверяем, есть ли дочерние элементы.
		$has_children = in_array( 'menu-item-has-children', $classes, true );

		if ( $has_children ) {
			// Для элементов с подменю делаем кнопку.
			$output .= $this->render_dropdown_toggle( $item, $atts, $args, $depth, $icon );
		} else {
			// Обычная ссылка.
			$output .= $this->render_link( $item, $atts, $args, $depth, $icon );
		}
	}

	/**
	 * Рендерит обычную ссылку.
	 *
	 * @param WP_Post  $item  Menu item data object.
	 * @param array    $atts  Attributes of the link.
	 * @param stdClass $args  An object of wp_nav_menu() arguments.
	 * @param int      $depth Depth of menu item. Used for padding.
	 * @param string   $icon  Icon class for the menu item.
	 * @return string
	 */
	private function render_link( $item, $atts, $args, $depth, $icon = '' ) {
		$link = '';

		// Фильтруем атрибуты.
		$atts = apply_filters( 'nav_menu_link_attributes', $atts, $item, $args, $depth );

		$attributes = '';
		foreach ( $atts as $attr => $value ) {
			if ( ! empty( $value ) ) {
				$value       = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
				$attributes .= ' ' . $attr . '="' . $value . '"';
			}
		}

		// Текст ссылки.
		$title = apply_filters( 'the_title', $item->title, $item->ID );

		// Формируем HTML ссылки.
		$link .= '<a' . $attributes . ' class="nav__link">';

		// Добавляем иконку если есть.
		if ( ! empty( $icon ) ) {
			$link .= '<i class="' . esc_attr( $icon ) . ' nav__icon"></i>';
		}

		$link .= $title;
		$link .= '</a>';

		return $link;
	}

	/**
	 * Рендерит кнопку для выпадающего меню.
	 *
	 * @param WP_Post  $item  Menu item data object.
	 * @param array    $atts  Attributes of the link.
	 * @param stdClass $args  An object of wp_nav_menu() arguments.
	 * @param int      $depth Depth of menu item. Used for padding.
	 * @param string   $icon  Icon class for the menu item.
	 * @return string
	 */
	private function render_dropdown_toggle( $item, $atts, $args, $depth, $icon = '' ) {
		$toggle = '';

		// Текст ссылки.
		$title = apply_filters( 'the_title', $item->title, $item->ID );

		if ( $this->is_mobile ) {
			// Мобильная версия - кнопка.
			$toggle .= '<button class="nav__link nav__link--button">';

			if ( ! empty( $icon ) ) {
				$toggle .= '<i class="' . esc_attr( $icon ) . ' nav__icon"></i>';
			}

			$toggle .= $title;
			$toggle .= $this->get_arrow_svg();
			$toggle .= '</button>';
		} else {
			// Десктопная версия - ссылка со стрелкой.
			$attributes = '';
			foreach ( $atts as $attr => $value ) {
				if ( ! empty( $value ) ) {
					$value       = ( 'href' === $attr ) ? esc_url( $value ) : esc_attr( $value );
					$attributes .= ' ' . $attr . '="' . $value . '"';
				}
			}

			$toggle .= '<a href="' . esc_url( $atts['href'] ) . '"' . $attributes . ' class="nav__link nav__link--has-submenu">';

			if ( ! empty( $icon ) ) {
				$toggle .= '<i class="' . esc_attr( $icon ) . ' nav__icon"></i>';
			}

			$toggle .= $title;
			$toggle .= $this->get_arrow_svg();
			$toggle .= '</a>';
		}

		return $toggle;
	}

	/**
	 * Возвращает SVG стрелки.
	 *
	 * @return string
	 */
	private function get_arrow_svg() {
		return '<svg class="nav__arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>';
	}

	/**
	 * Ends the element output, if needed.
	 *
	 * @param string   $output Used to append additional content (passed by reference).
	 * @param WP_Post  $item   Menu item data object.
	 * @param int      $depth  Depth of menu item. Used for padding.
	 * @param stdClass $args   An object of wp_nav_menu() arguments.
	 */
	public function end_el( &$output, $item, $depth = 0, $args = null ) {
		$output .= "</li>\n";
	}
}
