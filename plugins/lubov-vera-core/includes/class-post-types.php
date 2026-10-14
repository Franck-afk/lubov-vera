<?php
/**
 * LV_Core\Post_Types — типы записей «Свидетельства» и «Служения».
 *
 * Слаги зашиты под тему: single.php строит ссылки как home_url( '/testimony/' )
 * и home_url( '/ministry/' ), archive.php определяет тип через get_post_type().
 * Менять их без правки темы нельзя.
 *
 * Таксономии намеренно НЕ подключены. Ни одна существующая запись
 * не использует рубрики и метки — проверено по базе.
 *
 * Правила перезаписи здесь НЕ сбрасываются: сброс делает только точка входа
 * (активация/деактивация).
 *
 * @package LV_Core
 */

namespace LV_Core;

defined( 'ABSPATH' ) || exit;

/**
 * Регистрация типов записей «Свидетельства» и «Служения».
 */
class Post_Types {

	/**
	 * Единственный экземпляр класса.
	 *
	 * @var Post_Types|null
	 */
	private static $instance = null;

	/**
	 * Защита от повторной регистрации в одном запросе.
	 *
	 * @var bool
	 */
	private static $registered = false;

	/**
	 * Возвращает единственный экземпляр класса.
	 *
	 * @return Post_Types
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Вешает регистрацию типов записей на init.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register' ), 10 );
	}

	/**
	 * Регистрация типов записей.
	 *
	 * Публичный метод: точке входа он нужен на активации, когда init
	 * этого запроса уже прошёл.
	 */
	public function register() {
		if ( self::$registered ) {
			return;
		}
		self::$registered = true;

		register_post_type( 'testimony', $this->args( 'testimony', 'dashicons-format-quote', 21 ) );
		register_post_type( 'ministry', $this->args( 'ministry', 'dashicons-groups', 22 ) );
		register_post_type( 'broadcast', $this->args( 'broadcast', 'dashicons-video-alt3', 23 ) );
	}

	/**
	 * Общие аргументы для обоих типов.
	 *
	 * @param string $slug     Слаг типа записи и его архива.
	 * @param string $icon     Иконка в меню админки.
	 * @param int    $position Позиция в меню админки.
	 * @return array
	 */
	private function args( $slug, $icon, $position ) {
		if ( 'broadcast' === $slug ) {
			$plural   = 'Трансляции';
			$singular = 'Трансляция';
		} else {
			$plural   = ( 'testimony' === $slug ) ? 'Свидетельства' : 'Служения';
			$singular = ( 'testimony' === $slug ) ? 'Свидетельство' : 'Служение';
		}

		return array(
			'labels'        => array(
				'name'               => $plural,
				'singular_name'      => $singular,
				'add_new_item'       => 'Добавить: ' . mb_strtolower( $singular ),
				'edit_item'          => 'Редактировать: ' . mb_strtolower( $singular ),
				'new_item'           => 'Новая запись: ' . mb_strtolower( $singular ),
				'view_item'          => 'Открыть: ' . mb_strtolower( $singular ),
				'search_items'       => 'Поиск: ' . mb_strtolower( $plural ),
				'not_found'          => $plural . ' не найдены',
				'not_found_in_trash' => $plural . ' в корзине нет',
				'all_items'          => 'Все: ' . mb_strtolower( $plural ),
				'menu_name'          => $plural,
			),
			'public'        => true,
			'has_archive'   => true,
			'show_in_rest'  => true,
			'rest_base'     => $slug . 's',
			'rewrite'       => array(
				'slug'       => $slug,
				'with_front' => false,
			),
			'menu_icon'     => $icon,
			'menu_position' => $position,
			'supports'      => array( 'title', 'editor', 'thumbnail', 'excerpt' ),
			// Таксономии здесь не подключаем — см. докблок класса.
		);
	}
}
