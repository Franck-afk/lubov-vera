<?php
/**
 * Экран настроек темы: отдельный пункт меню «Настройки сайта».
 *
 * Экран полностью рабочий: разделы и вкладки, сохранение (классический POST
 * на admin-post.php + AJAX-обработчик), сброс к дефолтам полей экрана и
 * модалка подтверждения. Пишет опцию lv_site_settings — ту же, что читает фронт;
 * других писателей у неё нет.
 *
 * Что где лежит:
 *   inc/options/class-lv-theme-settings.php — этот класс: меню, enqueue, сборка данных.
 *   inc/options/schema.php                   — разделы и поля.
 *   inc/options/views/                   — шаблоны экрана.
 *   assets/css/settings.css               — стили экрана.
 *   assets/js/settings.js                — переключение панелей и hash.
 *
 * Опция: lv_site_settings (та же, что читает фронт). Плагин не участвует.
 *
 * @package LV_Theme
 */

defined( 'ABSPATH' ) || exit;

/**
 * Класс экрана настроек темы.
 *
 * @package LV_Theme
 */
final class LV_Theme_Settings {

	const PAGE_SLUG    = 'lv-theme-options';
	const ACTION_SAVE  = 'lv_options_save';
	const ACTION_RESET = 'lv_options_reset';
	const NONCE        = 'lv_options';
	const NONCE_FIELD  = '_lv_nonce';
	const OPTION_KEY   = 'lv_site_settings';
	const CAP          = 'edit_theme_options';

	/**
	 * Экземпляр класса (синглтон).
	 *
	 * @var LV_Theme_Settings|null
	 */
	private static $instance = null;

	/**
	 * Возвращает единственный экземпляр класса.
	 *
	 * @return LV_Theme_Settings
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Вешает хуки администратора.
	 */
	private function __construct() {
		// Схема нужна всегда, а не только при отрисовке страницы.
		require_once get_template_directory() . '/inc/options/schema.php';
		require_once get_template_directory() . '/inc/options/helpers.php';

		add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_enqueue_scripts' ) );
		add_action( 'admin_post_' . self::ACTION_SAVE, array( $this, 'handle_save' ) );
		add_action( 'admin_post_' . self::ACTION_RESET, array( $this, 'handle_reset' ) );
		add_action( 'wp_ajax_' . self::ACTION_SAVE, array( $this, 'handle_save_ajax' ) );
	}

	/* ----------------------------------------------------------------- Меню */

	/**
	 * Регистрирует отдельный пункт меню «Настройки сайта».
	 */
	public function add_settings_page() {
		add_menu_page(
			__( 'Настройки сайта', 'lubov-vera' ),
			__( 'Настройки сайта', 'lubov-vera' ),
			self::CAP,
			self::PAGE_SLUG,
			array( $this, 'render_page' ),
			$this->menu_icon_url(),
			61
		);
	}

	/**
	 * URL кастомной иконки пункта меню.
	 *
	 * Иконка — векторный (SVG) белый логотип, лежит в ассетах темы, поэтому
	 * не зависит от медиатеки боевого сайта.
	 *
	 * @return string
	 */
	private function menu_icon_url() {
		return get_template_directory_uri() . '/assets/img/lv-menu-icon.svg';
	}

	/**
	 * Приводит картинку-иконку меню к размеру штатных иконок (20×20).
	 *
	 * @return string
	 */
	private function menu_icon_css() {
		return '#toplevel_page_' . self::PAGE_SLUG . ' .wp-menu-image img{width:20px;height:20px;padding:7px 0 0;object-fit:contain;opacity:1;}';
	}

	/* ------------------------------------------------------------- Ресурсы */

	/**
	 * Подключает ассеты экрана и иконку пункта меню.
	 *
	 * @param string $hook_suffix Текущий экран админки.
	 */
	public function admin_enqueue_scripts( $hook_suffix ) {
		// Иконка пункта меню нужна на всех экранах админки, а не только на
		// экране настроек, поэтому стиль печатается здесь.
		wp_register_style( 'lv-theme-admin-menu', false, array(), '2.1.0' );
		wp_enqueue_style( 'lv-theme-admin-menu' );
		wp_add_inline_style( 'lv-theme-admin-menu', $this->menu_icon_css() );

		if ( 'toplevel_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		// Кнопка «Выбрать» в поле картинки открывает штатную медиатеку.
		wp_enqueue_media();

		wp_enqueue_style(
			'lv-theme-settings',
			get_template_directory_uri() . '/assets/css/settings.css',
			array(),
			'2.2.0'
		);

		wp_enqueue_script(
			'lv-theme-settings',
			get_template_directory_uri() . '/assets/js/settings.js',
			array( 'jquery', 'media-views' ),
			'2.2.0',
			true
		);

		// Без этих данных скрипт не перехватывает форму и она уходит
		// обычным POST — то есть всё работает и без JS.
		wp_localize_script(
			'lv-theme-settings',
			'LVSettings',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'action'  => self::ACTION_SAVE,
				'nonce'   => wp_create_nonce( self::NONCE ),
				'strings' => array(
					'saving'  => __( 'Сохраняем…', 'lubov-vera' ),
					'saved'   => __( 'Настройки сохранены.', 'lubov-vera' ),
					'save'    => __( 'Сохранить', 'lubov-vera' ),
					'failed'  => __( 'Не удалось сохранить. Проверьте соединение и попробуйте ещё раз.', 'lubov-vera' ),
					'expired' => __( 'Сессия истекла. Обновите страницу.', 'lubov-vera' ),
				),
			)
		);
	}

	/* --------------------------------------------------------------- Экран */

	/**
	 * Выводит экран настроек.
	 */
	public function render_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Недостаточно прав.' );
		}

		// Данные для шаблонов.
		$tabs       = lv_theme_options_schema();
		$saved      = get_option( 'lv_site_settings', array() );
		$saved      = is_array( $saved ) ? $saved : array();
		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only tab state.
		$updated    = isset( $_GET['updated'] ) ? sanitize_key( wp_unslash( $_GET['updated'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only notice flag.

		if ( '' === $active_tab || ! isset( $tabs[ $active_tab ] ) ) {
			$active_tab = (string) array_key_first( $tabs );
		}

		// Сколько полей этого экрана заполнено — диалог сброса обязан
		// предупредить, сколько данных пропадёт.
		$filled_count = 0;

		foreach ( $tabs as $lv_section ) {
			foreach ( array_keys( (array) $lv_section['fields'] ) as $lv_key ) {
				$lv_val = isset( $saved[ $lv_key ] ) ? $saved[ $lv_key ] : '';

				if ( is_array( $lv_val ) ) {
					if ( count( $lv_val ) > 0 ) {
						++$filled_count;
					}
				} elseif ( '' !== trim( (string) $lv_val ) ) {
					++$filled_count;
				}
			}
		}

		require get_template_directory() . '/inc/options/views/page.php';
	}

	/* ------------------------------------------------------- Сохранение */

	/**
	 * Обработчик классического POST. Без AJAX.
	 *
	 * Порядок: права → nonce → wp_unslash → sanitize → merge → update_option.
	 * Порядок важен: права проверяем до nonce, чтобы пользователю без прав
	 * не показывалась форма и не тратилась проверка токена.
	 */
	public function handle_save() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Недостаточно прав.' );
		}

		check_admin_referer( self::NONCE, self::NONCE_FIELD );

		$this->save_options( $this->posted_options() );

		// add_menu_page живёт под admin.php.
		$args = array(
			'page'    => self::PAGE_SLUG,
			'updated' => 'saved',
		);

		$tab = isset( $_POST['lv_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['lv_active_tab'] ) ) : '';
		if ( '' !== $tab ) {
			$args['tab'] = $tab;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Тот же сценарий сохранения, но без перезагрузки страницы.
	 *
	 * Отдельный обработчик нужен из-за другого контракта: здесь нельзя
	 * делать редирект и exit — клиент ждёт JSON, на котором рисует
	 * текст оповещения. Проверки прав и токена те же, что и у POST,
	 * только возвращаем ошибку, а не wp_die.
	 */
	public function handle_save_ajax() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Недостаточно прав.', 'lubov-vera' ) ),
				403
			);
		}

		if ( ! wp_verify_nonce( isset( $_POST[ self::NONCE_FIELD ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_FIELD ] ) ) : '', self::NONCE ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Сессия истекла. Обновите страницу.', 'lubov-vera' ) ),
				403
			);
		}

		$tab = isset( $_POST['lv_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['lv_active_tab'] ) ) : '';

		$saved = $this->save_options( $this->posted_options() );

		wp_send_json_success(
			array(
				'message' => __( 'Настройки сохранены.', 'lubov-vera' ),
				'tab'     => $tab,
				'keys'    => array_keys( $saved ),
				'time'    => current_time( 'H:i' ),
			)
		);
	}

	/**
	 * Сброс полей этого экрана к значениям по умолчанию.
	 *
	 * Намеренно НЕ восстанавливает всю опцию: кроме полей экрана в ней лежат
	 * шрифты и настройки cookie-баннера, которых на форме нет. Их сброс был бы
	 * тихой потерей того, чего пользователь не видел.
	 *
	 * Возвращаем редиректом, а не AJAX: после сброса форма должна показать
	 * новые значения, а обновлять их на лету значит дублировать всю логику
	 * перерисовки полей.
	 */
	public function handle_reset() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( 'Недостаточно прав.' );
		}

		check_admin_referer( self::NONCE, self::NONCE_FIELD );

		$defaults = $this->defaults_for_screen_fields();

		$current = get_option( self::OPTION_KEY, array() );
		$current = is_array( $current ) ? $current : array();

		// Аудит сброса.
		do_action( 'lv_options_reset_before', $current );

		update_option( self::OPTION_KEY, array_merge( $current, $defaults ) );

		if ( class_exists( '\LV_Core\Options' ) ) {
			\LV_Core\Options::get_instance()->flush_cache();
		}

		$args = array(
			'page'    => self::PAGE_SLUG,
			'updated' => 'reset',
		);

		$tab = isset( $_POST['lv_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['lv_active_tab'] ) ) : '';
		if ( '' !== $tab ) {
			$args['tab'] = $tab;
		}

		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Дефолты только для ключей, которые есть на этом экране.
	 *
	 * @return array
	 */
	private function defaults_for_screen_fields() {
		$keys = array();
		foreach ( lv_theme_options_schema() as $section ) {
			foreach ( array_keys( (array) $section['fields'] ) as $key ) {
				$keys[] = $key;
			}
		}

		if ( ! class_exists( '\LV_Core\Options' ) ) {
			// Без плагина дефолтов нет — оставляем значения как есть.
			return array();
		}

		$defaults = \LV_Core\Options::get_instance()->get_defaults();
		$out      = array();

		foreach ( $keys as $key ) {
			if ( array_key_exists( $key, $defaults ) ) {
				$out[ $key ] = $defaults[ $key ];
			}
		}

		return $out;
	}

	/**
	 * Значения полей из POST, без unslаш и санации.
	 *
	 * @return array
	 */
	private function posted_options() {
		if ( isset( $_POST['lv_options'] ) && is_array( $_POST['lv_options'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Missing
			return wp_unslash( $_POST['lv_options'] ); // phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput
		}

		return array();
	}

	/**
	 * Единственное место, где пишется опция.
	 *
	 * Порядок: sanitize -> чтение текущего -> merge -> update_option ->
	 * сброс кэша плагина. Вызывается и классическим POST, и AJAX,
	 * чтобы обе ветки не разошлись.
	 *
	 * @param array $raw Сырые значения из формы.
	 * @return array Записанные значения.
	 */
	private function save_options( array $raw ) {
		$clean = $this->sanitize_values( $raw );

		$current = get_option( self::OPTION_KEY, array() );
		$current = is_array( $current ) ? $current : array();

		// merge, а не перезапись: ключи, которых нет в форме, должны уцелеть.
		$merged = array_merge( $current, $clean );

		// Аудит: старые → новые значения.
		do_action( 'lv_options_save_before', $current, $merged );

		update_option( self::OPTION_KEY, $merged );

		// У плагина есть кэш чтения — сбрасываем, иначе в этом же запросе
		// он вернёт старые значения.
		if ( class_exists( '\LV_Core\Options' ) ) {
			\LV_Core\Options::get_instance()->flush_cache();
		}

		return $merged;
	}

	/**
	 * Очистить значения. Основной путь — санация плагина по общей схеме.
	 *
	 * @param array $raw Сырые данные из формы.
	 * @return array
	 */
	private function sanitize_values( $raw ) {
		// Единая схема санации живёт в плагине — не дублируем её.
		if ( class_exists( '\LV_Core\Options' ) ) {
			$clean = \LV_Core\Options::get_instance()->sanitize( $raw );
		} else {
			$clean = $this->sanitize_values_fallback( $raw );
		}

		return $this->strip_repeater_markers( $clean );
	}

	/**
	 * Убрать служебный маркер пустого повторяющегося блока.
	 *
	 * Маркер нужен только чтобы ключ массива всегда попал в POST — иначе при
	 * пустом списке PHP не передал бы его вовсе и array_merge сохранил бы
	 * прежнее значение. В базе он не нужен.
	 *
	 * @param array $clean Очищенные значения.
	 * @return array
	 */
	private function strip_repeater_markers( array $clean ) {
		foreach ( lv_theme_options_schema() as $section ) {
			foreach ( (array) $section['fields'] as $key => $field ) {
				if ( 'repeater' !== ( isset( $field['type'] ) ? $field['type'] : '' ) ) {
					continue;
				}

				if ( isset( $clean[ $key ]['__lv_empty'] ) ) {
					unset( $clean[ $key ]['__lv_empty'] );
				}
			}
		}

		return $clean;
	}

	/**
	 * Фолбэк на случай, если плагин выключен.
	 *
	 * @param array $raw Сырые данные из формы.
	 * @return array
	 */
	private function sanitize_values_fallback( $raw ) {
		// Тот же список ключей, но из схемы темы.
		$types = array();

		foreach ( lv_theme_options_schema() as $section ) {
			foreach ( (array) $section['fields'] as $key => $field ) {
				$types[ $key ] = isset( $field['type'] ) ? $field['type'] : 'text';
			}
		}

		$clean = array();

		foreach ( (array) $raw as $key => $value ) {
			if ( ! isset( $types[ $key ] ) ) {
				continue;
			}

			switch ( $types[ $key ] ) {
				case 'textarea':
					$clean[ $key ] = sanitize_textarea_field( (string) $value );
					break;
				case 'email':
					$clean[ $key ] = sanitize_email( (string) $value );
					break;
				case 'url':
				case 'media':
					$clean[ $key ] = esc_url_raw( (string) $value );
					break;
				case 'repeater':
				case 'array':
					$clean[ $key ] = $this->sanitize_array_deep( $value );
					break;
				case 'page_select':
					$clean[ $key ] = absint( $value );
					break;
				case 'text':
				default:
					$clean[ $key ] = sanitize_text_field( (string) $value );
					break;
			}
		}

		return $clean;
	}

	/**
	 * Рекурсивная очистка повторяющегося блока.
	 *
	 * @param mixed $value Значение.
	 * @return array
	 */
	private function sanitize_array_deep( $value ) {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$out = array();

		foreach ( $value as $key => $item ) {
			$out[ $key ] = is_array( $item )
				? $this->sanitize_array_deep( $item )
				: sanitize_text_field( (string) $item );
		}

		return $out;
	}
}

// Единственная точка инициализации: хуки должны попасть в очередь до admin_menu.
LV_Theme_Settings::get_instance();
