<?php
/**
 * LV_Core\Options — тонкий слой данных.
 *
 * Хранилище — одна опция lv_site_settings. Никаких миграций и второй опции:
 * lv_core_settings плагин не читает и не создаёт.
 *
 * КАК ДОБАВИТЬ НОВЫЙ КЛЮЧ
 * ----------------------
 * Всё в одном месте — схема self::schema(): одна строка вида
 *
 *     'phone' => array( 'type' => 'text', 'default' => '' ),
 *
 * Из неё автоматически собираются get_defaults() (значения по умолчанию)
 * и types() (белый список + тип для sanitize). Новый тип санитизации
 * добавляется одним case в sanitize_value().
 *
 * Плагин ничего не пишет: sanitize() только чистит массив. Запись опции
 * появится вместе с экраном настроек (пункт 2), там же вызывается
 * flush_cache() после update_option().
 *
 * @package LV_Core
 */

namespace LV_Core;

defined( 'ABSPATH' ) || exit;

/**
 * Тонкий слой данных сайта поверх одной опции.
 */
class Options {

	const OPTION_KEY = 'lv_site_settings';

	/**
	 * Единственный экземпляр класса.
	 *
	 * @var Options|null
	 */
	private static $instance = null;

	/**
	 * Кэш объединённых данных (дефолты ← база).
	 *
	 * @var array|null
	 */
	private $cache = null;

	/**
	 * Схема полей: key => [ type, default ].
	 *
	 * @var array
	 */
	private $schema = null;

	/**
	 * Возвращает единственный экземпляр класса.
	 *
	 * @return Options
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Закрытый конструктор синглтона.
	 *
	 * @return void
	 */
	private function __construct() {}

	/* ------------------------------------------------------------- Схема */

	/**
	 * Единственный источник правды: какие ключи вообще существуют и какого они типа.
	 *
	 * @return array
	 */
	private function schema() {
		if ( null !== $this->schema ) {
			return $this->schema;
		}

		// Типы: text, textarea, color, url, email, absint, toggle, array.
		$this->schema = array(
			// Контакты.
			'phone'                => array(
				'type'    => 'text',
				'default' => '',
			),
			'email'                => array(
				'type'    => 'email',
				'default' => '',
			),
			'address'              => array(
				'type'    => 'textarea',
				'default' => '',
			),

			// Hero.
			'hero_badge'           => array(
				'type'    => 'text',
				'default' => '',
			),
			'hero_title'           => array(
				'type'    => 'text',
				'default' => '',
			),
			'hero_desc'            => array(
				'type'    => 'textarea',
				'default' => '',
			),
			'hero_video'           => array(
				'type'    => 'url',
				'default' => '',
			),
			'hero_image'           => array(
				'type'    => 'url',
				'default' => '',
			),

			// Таймер эфира. Дата — строка дд.мм.гггг, время — чч:мм.
			'timer_title'          => array(
				'type'    => 'text',
				'default' => '',
			),
			'timer_date'           => array(
				'type'    => 'text',
				'default' => '',
			),
			'timer_time'           => array(
				'type'    => 'text',
				'default' => '',
			),

			// Карточки под hero — три фиксированных слота.
			'card_1_title'         => array(
				'type'    => 'text',
				'default' => '',
			),
			'card_1_desc'          => array(
				'type'    => 'textarea',
				'default' => '',
			),
			'card_1_icon'          => array(
				'type'    => 'text',
				'default' => '',
			),
			'card_1_link'          => array(
				'type'    => 'url',
				'default' => '',
			),
			'card_2_title'         => array(
				'type'    => 'text',
				'default' => '',
			),
			'card_2_desc'          => array(
				'type'    => 'textarea',
				'default' => '',
			),
			'card_2_icon'          => array(
				'type'    => 'text',
				'default' => '',
			),
			'card_2_link'          => array(
				'type'    => 'url',
				'default' => '',
			),
			'card_3_title'         => array(
				'type'    => 'text',
				'default' => '',
			),
			'card_3_desc'          => array(
				'type'    => 'textarea',
				'default' => '',
			),
			'card_3_icon'          => array(
				'type'    => 'text',
				'default' => '',
			),
			'card_3_link'          => array(
				'type'    => 'url',
				'default' => '',
			),

			// Соцсети и прочие повторяющиеся блоки.
			'socials'              => array(
				'type'    => 'array',
				'default' => array(),
			),
			'schedule'             => array(
				'type'    => 'array',
				'default' => array(),
			),
			'documents'            => array(
				'type'    => 'array',
				'default' => array(),
			),
			'posts_cards'          => array(
				'type'    => 'array',
				'default' => array(),
			),

			// Карточки на архивах CPT: archive.php читает их напрямую.
			'testimonies_cards'    => array(
				'type'    => 'array',
				'default' => array(),
			),
			'ministries_cards'     => array(
				'type'    => 'array',
				'default' => array(),
			),
			'broadcasts_cards'     => array(
				'type'    => 'array',
				'default' => array(),
			),

			// Заголовки архивов и секций.
			'testimonies_title'    => array(
				'type'    => 'text',
				'default' => '',
			),
			'testimonies_desc'     => array(
				'type'    => 'textarea',
				'default' => '',
			),
			'ministries_title'     => array(
				'type'    => 'text',
				'default' => '',
			),
			'ministries_desc'      => array(
				'type'    => 'textarea',
				'default' => '',
			),
			'posts_title'          => array(
				'type'    => 'text',
				'default' => '',
			),
			'schedule_title'       => array(
				'type'    => 'text',
				'default' => '',
			),
			'posts_subtitle'       => array(
				'type'    => 'text',
				'default' => '',
			),

			// Юридические страницы — ID.
			'legal_policy'         => array(
				'type'    => 'absint',
				'default' => 0,
			),
			'legal_agreement'      => array(
				'type'    => 'absint',
				'default' => 0,
			),

			// Донаты и cookie-баннер.
			'donation_mixplat'     => array(
				'type'    => 'text',
				'default' => '',
			),
			'cookie_consent'       => array(
				'type'    => 'array',
				'default' => array(),
			),

			// Шрифты (Font Library).
			'font_body'            => array(
				'type'    => 'text',
				'default' => '',
			),
			'font_body_variant'    => array(
				'type'    => 'text',
				'default' => '',
			),
			'font_heading'         => array(
				'type'    => 'text',
				'default' => '',
			),
			'font_heading_variant' => array(
				'type'    => 'text',
				'default' => '',
			),
			'custom_fonts'         => array(
				'type'    => 'array',
				'default' => array(),
			),
		);

		return $this->schema;
	}

	/* ------------------------------------------------------------ Чтение */

	/**
	 * Значения по умолчанию для всех известных ключей.
	 *
	 * @return array
	 */
	public function get_defaults(): array {
		$out = array();

		foreach ( $this->schema() as $key => $def ) {
			$out[ $key ] = $def['default'];
		}

		return $out;
	}

	/**
	 * Тип каждого известного ключа. Служит белым списком для sanitize().
	 *
	 * @return array
	 */
	public function types(): array {
		$out = array();

		foreach ( $this->schema() as $key => $def ) {
			$out[ $key ] = $def['type'];
		}

		return $out;
	}

	/**
	 * Значение одного ключа (дефолт ← база).
	 *
	 * @param string $key      Ключ.
	 * @param mixed  $fallback Значение, если ключа нет.
	 * @return mixed
	 */
	public function get( string $key, $fallback = '' ) {
		$all = $this->get_all();

		return array_key_exists( $key, $all ) ? $all[ $key ] : $fallback;
	}

	/**
	 * Весь набор: дефолты, поверх них — значения из базы.
	 *
	 * @return array
	 */
	public function get_all(): array {
		if ( null !== $this->cache ) {
			return $this->cache;
		}

		$stored = get_option( self::OPTION_KEY, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$this->cache = $this->merge( $this->get_defaults(), $stored );

		return $this->cache;
	}

	/* ----------------------------------------------------------- Запись */

	/**
	 * Наложить очищенные значения поверх текущих, не теряя остальные ключи.
	 *
	 * @param array $current Текущий набор.
	 * @param array $clean   Очищенные значения из формы.
	 * @return array
	 */
	public function merge( array $current, array $clean ): array {
		return array_merge( $current, $clean );
	}

	/**
	 * Очистить сырой ввод: отбросить неизвестные ключи, привести значения к типам.
	 *
	 * Обработчик POST сам вызывает wp_unslash() — здесь этого нет (пункт 2).
	 *
	 * @param array $raw Сырые данные.
	 * @return array
	 */
	public function sanitize( array $raw ): array {
		$types = $this->types();
		$clean = array();

		foreach ( $raw as $key => $value ) {
			// Белый список: неизвестные ключи молча выкидываем.
			if ( ! isset( $types[ $key ] ) ) {
				continue;
			}

			$clean[ $key ] = $this->sanitize_value( $value, $types[ $key ] );
		}

		return $clean;
	}

	/**
	 * Привести одно значение к его типу.
	 *
	 * @param mixed  $value Сырое значение.
	 * @param string $type  Тип из types().
	 * @return mixed
	 */
	private function sanitize_value( $value, string $type ) {
		switch ( $type ) {
			case 'textarea':
				return sanitize_textarea_field( (string) $value );

			case 'color':
				$color = sanitize_hex_color( (string) $value );
				return $color ? $color : '';

			case 'url':
				return esc_url_raw( (string) $value );

			case 'email':
				return sanitize_email( (string) $value );

			case 'absint':
				return absint( $value );

			case 'toggle':
				return ( '1' === (string) $value || 1 === $value || true === $value ) ? '1' : '0';

			case 'array':
				return $this->sanitize_deep( $value );

			case 'text':
			default:
				return sanitize_text_field( (string) $value );
		}
	}

	/**
	 * Рекурсивная очистка массива (повторяющиеся блоки).
	 *
	 * @param mixed $value Значение.
	 * @return array
	 */
	private function sanitize_deep( $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();

		foreach ( $value as $key => $item ) {
			$out[ $key ] = is_array( $item )
				? $this->sanitize_deep( $item )
				: sanitize_text_field( (string) $item );
		}

		return $out;
	}

	/* ------------------------------------------------------------- Прочее */

	/**
	 * Сбросить кэш. Вызывать после update_option(), иначе правки не будут видны
	 * до конца запроса.
	 */
	public function flush_cache() {
		$this->cache = null;
	}
}
