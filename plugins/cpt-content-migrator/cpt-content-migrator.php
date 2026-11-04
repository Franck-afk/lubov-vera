<?php
/**
 * Plugin Name:       CPT Content Migrator
 * Description:       Экспорт и импорт контента произвольных типов записей с автоэкспортом и журналом операций.
 * Version:           0.4.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       cpt-migrator
 * Domain Path:       /languages
 *
 * @package CPT_Content_Migrator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Основной класс плагина.
 *
 * Регистрирует страницу в меню «Инструменты», реализует экспорт записей
 * в JSON и пакетный импорт с проверкой данных, переносом изображений,
 * пробным запуском и отчётом. Дополнительно предоставляет автоэкспорт
 * по расписанию (wp-cron), журнал операций и страницу настроек.
 */
final class CPT_Content_Migrator {

	/**
	 * Слаг страницы в меню «Инструменты».
	 */
	private const PAGE_SLUG = 'cpt-migrator';

	/**
	 * Действие экспорта. Используется и как action формы, и как действие nonce.
	 */
	private const EXPORT_ACTION = 'cpt_migrator_export';

	/**
	 * Имя поля nonce для формы экспорта.
	 */
	private const EXPORT_NONCE_NAME = 'cpt_migrator_export_nonce';

	/**
	 * Действие импорта. Используется и как action формы, и как действие nonce.
	 */
	private const IMPORT_ACTION = 'cpt_migrator_import';

	/**
	 * Имя поля nonce для формы импорта.
	 */
	private const IMPORT_NONCE_NAME = 'cpt_migrator_import_nonce';

	/**
	 * Белый список вкладок. Любое другое значение в $_GET['tab'] игнорируется.
	 */
	private const ALLOWED_TABS = array( 'export', 'import', 'settings', 'log' );

	/**
	 * Группа и имя опции настроек (Settings API).
	 */
	private const SETTINGS_GROUP = 'cpt_migrator_settings_group';

	/**
	 * Имя опции с настройками.
	 */
	private const SETTINGS_OPTION = 'cpt_migrator_settings';

	/**
	 * Имя опции с журналом операций.
	 */
	private const LOG_OPTION = 'cpt_migrator_log';

	/**
	 * Хук wp-cron для автоэкспорта.
	 */
	private const CRON_HOOK = 'cpt_migrator_auto_export';

	/**
	 * Имя папки автоэкспорта внутри wp-content/uploads.
	 */
	private const EXPORT_DIR_NAME = 'cpt-migrator';

	/**
	 * Максимальное количество записей в журнале операций.
	 */
	private const LOG_MAX_ENTRIES = 200;

	/**
	 * Количество строк журнала на одной странице.
	 */
	private const LOG_PER_PAGE = 50;

	/**
	 * Действие очистки журнала.
	 */
	private const CLEAR_LOG_ACTION = 'cpt_migrator_clear_log';

	/**
	 * Действие ручного запуска автоэкспорта.
	 */
	private const RUN_EXPORT_ACTION = 'cpt_migrator_run_export';

	/**
	 * Допустимые периоды автоэкспорта.
	 */
	private const EXPORT_SCHEDULES = array( 'daily', 'weekly' );

	/**
	 * Версия формата экспорта. Увеличивайте при изменении структуры JSON.
	 */
	private const EXPORT_FORMAT_VERSION = '1.1';

	/**
	 * Типы записей по умолчанию для экспорта и импорта.
	 *
	 * Пустой список означает «все пользовательские типы записей сайта»:
	 * список определяется автоматически, чтобы плагин не зависел от
	 * конкретных слагов и работал на любом сайте.
	 */
	private const DEFAULT_POST_TYPES = array();

	/**
	 * Служебные типы записей WordPress, которые нельзя переносить.
	 */
	private const CORE_POST_TYPES = array(
		'post',
		'page',
		'attachment',
		'revision',
		'nav_menu_item',
		'custom_css',
		'customize_changeset',
		'oembed_cache',
		'user_request',
		'wp_block',
		'wp_template',
		'wp_template_part',
		'wp_global_styles',
		'wp_navigation',
		'wp_font_family',
		'wp_font_face',
		'wp_font_collection',
	);

	/**
	 * Имя опции со списком обнаруженных в файле типов записей.
	 */
	private const DETECTED_POST_TYPES_OPTION = 'cpt_migrator_detected_post_types';

	/**
	 * Максимальное количество строк в таблице соответствий типов записей.
	 */
	private const MAX_POST_TYPE_MAP_ROWS = 20;

	/**
	 * Системные мета-ключи WordPress, которые не переносятся при импорте.
	 *
	 * Остальные ключи, в том числе начинающиеся с подчёркивания
	 * (настройки Carbon Fields, SEO-плагинов, темы), переносятся —
	 * иначе запись теряет большую часть своего содержимого.
	 */
	private const BLOCKED_IMPORT_META_KEYS = array(
		'_edit_lock',
		'_edit_last',
		'_wp_old_slug',
		'_wp_old_date',
		'_wp_old_status',
		'_wp_old_parent',
		'_wp_desired_post_slug',
		'_encloseme',
		'_pingme',
		'_wp_trash_meta_status',
		'_wp_trash_meta_time',
		'_wp_attachment_metadata',
		'_wp_attached_file',
		'_thumbnail_id',
	);

	/**
	 * Шаблоны мета-ключей, которые никогда не переносятся (секреты, токены).
	 */
	private const SENSITIVE_META_PATTERN = '/(pass(word)?|secret|token|api_?key|licen[cs]e|nonce|salt|auth_?key|client_?secret)/i';

	/**
	 * Elementor-ключи, содержимое которых нельзя разворачивать.
	 */
	private const ELEMENTOR_META_KEYS = array( '_elementor_data', '_elementor_edit_mode', '_elementor_template_type' );

	/**
	 * Версии формата JSON, которые умеет импортировать плагин.
	 */
	private const SUPPORTED_FORMAT_VERSIONS = array( '1.0', '1.1' );

	/**
	 * Допустимые стратегии обработки дубликатов.
	 */
	private const DUPLICATE_ACTIONS = array( 'skip', 'update', 'suffix' );

	/**
	 * Статусы, учитываемые при поиске существующей записи.
	 */
	private const IMPORT_POST_STATUSES = array( 'publish', 'future', 'draft', 'pending', 'private', 'trash' );

	/**
	 * Максимальный размер загружаемого JSON-файла (5 МБ).
	 */
	private const MAX_IMPORT_FILE_SIZE = 5 * 1024 * 1024;

	/**
	 * Количество записей, обрабатываемых за один AJAX-запрос.
	 */
	private const IMPORT_BATCH_SIZE = 5;

	/**
	 * Время жизни сессии импорта (6 часов).
	 */
	private const IMPORT_SESSION_TTL = 6 * 60 * 60;

	/**
	 * AJAX-действие обработки пакета импорта.
	 */
	private const IMPORT_BATCH_ACTION = 'cpt_migrator_import_batch';

	/**
	 * Мета-ключ вложения с исходным URL.
	 */
	private const SOURCE_URL_META_KEY = '_cpt_migrator_source_url';

	/**
	 * Таймаут загрузки одного изображения (секунды).
	 */
	private const IMAGE_DOWNLOAD_TIMEOUT = 15;

	/**
	 * Количество попыток загрузки одного изображения.
	 */
	private const IMAGE_DOWNLOAD_ATTEMPTS = 2;

	/**
	 * Максимальное количество строк отчёта, которое хранится в сессии.
	 *
	 * Счётчики итогов при этом считаются отдельно и остаются точными.
	 */
	private const MAX_IMPORT_REPORT_ROWS = 500;

	/**
	 * Экземпляр класса.
	 *
	 * @var CPT_Content_Migrator|null
	 */
	private static $instance = null;

	/**
	 * Возвращает единственный экземпляр класса.
	 *
	 * @return CPT_Content_Migrator
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Конструктор.
	 */
	private function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'update_option_' . self::SETTINGS_OPTION, array( $this, 'reschedule_cron' ) );
		add_filter( 'cron_schedules', array( $this, 'register_cron_schedules' ) );

		// Экспорт запускается только через admin-post.php после проверки nonce и прав.
		add_action( 'admin_post_' . self::EXPORT_ACTION, array( $this, 'handle_export' ) );

		// Импорт также запускается только через admin-post.php.
		add_action( 'admin_post_' . self::IMPORT_ACTION, array( $this, 'handle_import' ) );
		add_action( 'wp_ajax_' . self::IMPORT_BATCH_ACTION, array( $this, 'handle_import_batch' ) );

		// Автоэкспорт по расписанию.
		add_action( self::CRON_HOOK, array( $this, 'run_scheduled_export' ) );

		// Очистка журнала и ручной запуск автоэкспорта.
		add_action( 'admin_post_' . self::CLEAR_LOG_ACTION, array( $this, 'handle_clear_log' ) );
		add_action( 'admin_post_' . self::RUN_EXPORT_ACTION, array( $this, 'handle_run_export' ) );

		// Настройка и снятие расписания.
		register_activation_hook( __FILE__, array( $this, 'activate' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivate' ) );
	}

	/**
	 * Выполняется при активации плагина.
	 */
	public function activate() {
		$this->get_settings();
		$this->reschedule_cron();
	}

	/**
	 * Выполняется при деактивации плагина.
	 *
	 * Данные и файлы не удаляются: для этого предназначен uninstall.php.
	 */
	public function deactivate() {
		$this->unschedule_cron();
	}

	/**
	 * Добавляет собственное расписание «раз в неделю».
	 *
	 * @param array $schedules Расписания cron.
	 *
	 * @return array
	 */
	public function register_cron_schedules( $schedules ) {
		if ( ! is_array( $schedules ) ) {
			$schedules = array();
		}

		$schedules['cpt_migrator_weekly'] = array(
			'interval' => WEEK_IN_SECONDS,
			'display'  => __( 'Раз в неделю (CPT Migrator)', 'cpt-migrator' ),
		);

		return $schedules;
	}

	/**
	 * Добавляет страницу «Инструменты → CPT Migrator».
	 */
	public function register_admin_page() {
		add_management_page(
			__( 'CPT Content Migrator', 'cpt-migrator' ),
			__( 'CPT Migrator', 'cpt-migrator' ),
			'manage_options',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Подключает скрипт пакетного импорта на странице плагина.
	 *
	 * @param string $hook_suffix Суффикс текущей страницы админки.
	 */
	public function enqueue_admin_assets( $hook_suffix ) {
		if ( 'tools_page_' . self::PAGE_SLUG !== $hook_suffix ) {
			return;
		}

		wp_enqueue_script(
			'cpt-migrator-import',
			plugins_url( 'assets/import.js', __FILE__ ),
			array(),
			'0.4.0',
			true
		);

		$config = array(
			'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
			'action'   => self::IMPORT_BATCH_ACTION,
			'nonce'    => wp_create_nonce( self::IMPORT_BATCH_ACTION ),
			'importId' => $this->get_requested_import_id(),
			'strings'  => array(
				'progress'    => __( 'Обработано %1$d из %2$d…', 'cpt-migrator' ),
				'starting'    => __( 'Импорт запускается…', 'cpt-migrator' ),
				'error'       => __( 'Не удалось продолжить импорт. Проверьте соединение с сервером и нажмите «Повторить».', 'cpt-migrator' ),
				'retry'       => __( 'Повторить', 'cpt-migrator' ),
				'completing'  => __( 'Импорт завершён. Открываем отчёт…', 'cpt-migrator' ),
				'invalidJson' => __( 'Сервер вернул некорректный ответ.', 'cpt-migrator' ),
			),
		);

		wp_add_inline_script(
			'cpt-migrator-import',
			'var cptMigratorImport = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES ) . ';',
			'before'
		);
	}

	/**
	 * Регистрирует настройки плагина через Settings API.
	 */
	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			self::SETTINGS_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => $this->get_default_settings(),
			)
		);

		add_settings_section(
			'cpt_migrator_auto_export_section',
			__( 'Автоэкспорт', 'cpt-migrator' ),
			array( $this, 'render_auto_export_section' ),
			self::SETTINGS_OPTION
		);

		add_settings_field(
			'cpt_migrator_auto_export',
			__( 'Включить автоэкспорт', 'cpt-migrator' ),
			$this->get_safe_callback( 'render_auto_export_field' ),
			self::SETTINGS_OPTION,
			'cpt_migrator_auto_export_section'
		);

		add_settings_field(
			'cpt_migrator_export_frequency',
			__( 'Периодичность', 'cpt-migrator' ),
			$this->get_safe_callback( 'render_export_frequency_field' ),
			self::SETTINGS_OPTION,
			'cpt_migrator_auto_export_section'
		);

		add_settings_field(
			'cpt_migrator_export_retention',
			__( 'Хранить последние файлов', 'cpt-migrator' ),
			$this->get_safe_callback( 'render_export_retention_field' ),
			self::SETTINGS_OPTION,
			'cpt_migrator_auto_export_section'
		);

		add_settings_section(
			'cpt_migrator_content_section',
			__( 'Типы записей', 'cpt-migrator' ),
			$this->get_safe_callback( 'render_content_section' ),
			self::SETTINGS_OPTION
		);

		add_settings_field(
			'cpt_migrator_post_types',
			__( 'Типы записей для экспорта', 'cpt-migrator' ),
			$this->get_safe_callback( 'render_post_types_field' ),
			self::SETTINGS_OPTION,
			'cpt_migrator_content_section'
		);

		add_settings_field(
			'cpt_migrator_post_type_map',
			__( 'Соответствие типов записей', 'cpt-migrator' ),
			$this->get_safe_callback( 'render_post_type_map_field' ),
			self::SETTINGS_OPTION,
			'cpt_migrator_content_section'
		);

		add_settings_section(
			'cpt_migrator_storage_section',
			__( 'Хранилище файлов', 'cpt-migrator' ),
			$this->get_safe_callback( 'render_storage_section' ),
			self::SETTINGS_OPTION
		);

		add_settings_field(
			'cpt_migrator_storage_path',
			__( 'Папка автоэкспорта', 'cpt-migrator' ),
			$this->get_safe_callback( 'render_storage_path_field' ),
			self::SETTINGS_OPTION,
			'cpt_migrator_storage_section'
		);
	}

	/**
	 * Возвращает безопасный обработчик для Settings API.
	 *
	 * Если метод потерян или переименован, страница настроек продолжит
	 * работать: вместо фатальной ошибки будет выведено сообщение.
	 *
	 * @param string $method Имя метода.
	 *
	 * @return callable
	 */
	private function get_safe_callback( $method ) {
		return function () use ( $method ) {
			if ( ! method_exists( $this, $method ) ) {
				echo '<p class="notice notice-error">' . esc_html__( 'Обработчик поля настроек не найден.', 'cpt-migrator' ) . '</p>';
				return;
			}

			call_user_func_array( array( $this, $method ), func_get_args() );
		};
	}

	/**
	 * Значения настроек по умолчанию.
	 *
	 * @return array
	 */
	private function get_default_settings() {
		return array(
			'auto_export'      => 0,
			'export_frequency' => 'daily',
			'export_retention' => 5,
			'post_types'       => self::DEFAULT_POST_TYPES,
			'post_type_map'    => array(),
		);
	}

	/**
	 * Возвращает таблицу соответствий «тип в файле → тип на сайте».
	 *
	 * @return array
	 */
	private function get_post_type_map() {
		$settings = $this->get_settings();
		$stored   = isset( $settings['post_type_map'] ) && is_array( $settings['post_type_map'] )
			? $settings['post_type_map']
			: array();

		$available = $this->get_available_post_types();
		$map       = array();

		foreach ( $stored as $source => $target ) {
			if ( ! is_string( $source ) || ! is_string( $target ) ) {
				continue;
			}

			$source = sanitize_key( $source );
			$target = sanitize_key( $target );

			if ( '' === $source || '' === $target ) {
				continue;
			}

			if ( ! in_array( $target, $available, true ) ) {
				continue;
			}

			$map[ $source ] = $target;
		}

		return $map;
	}

	/**
	 * Определяет типы записей, присутствующие в загруженном файле.
	 *
	 * @param array $data Данные JSON.
	 *
	 * @return string[]
	 */
	private function detect_source_post_types( $data ) {
		$types = array();

		if ( empty( $data['posts'] ) || ! is_array( $data['posts'] ) ) {
			return $types;
		}

		foreach ( $data['posts'] as $post ) {
			if ( ! is_array( $post ) || empty( $post['post_type'] ) || ! is_scalar( $post['post_type'] ) ) {
				continue;
			}

			$type = sanitize_key( (string) $post['post_type'] );

			if ( '' !== $type && ! in_array( $type, $types, true ) ) {
				$types[] = $type;
			}
		}

		return $types;
	}

	/**
	 * Сохраняет обнаруженные типы записей, чтобы показать их в настройках.
	 *
	 * @param string[] $types Типы записей.
	 */
	private function remember_detected_post_types( $types ) {
		if ( empty( $types ) ) {
			return;
		}

		update_option( self::DETECTED_POST_TYPES_OPTION, array_values( $types ), false );
	}

	/**
	 * Возвращает типы из файла, для которых не настроено соответствие.
	 *
	 * @param string[] $types Типы из файла.
	 *
	 * @return string[]
	 */
	private function get_unmapped_source_post_types( $types ) {
		$map  = $this->get_post_type_map();
		$miss = array();

		foreach ( $types as $type ) {
			if ( isset( $map[ $type ] ) ) {
				continue;
			}

			if ( post_type_exists( $type ) ) {
				continue;
			}

			$miss[] = $type;
		}

		return $miss;
	}

	/**
	 * Преобразует тип записи из файла в тип записи на этом сайте.
	 *
	 * @param string $source_type Тип записи из файла.
	 *
	 * @return string
	 */
	private function map_source_post_type( $source_type ) {
		$map = $this->get_post_type_map();

		if ( isset( $map[ $source_type ] ) ) {
			return $map[ $source_type ];
		}

		return $source_type;
	}

	/**
	 * Возвращает список доступных для переноса типов записей.
	 *
	 * @return string[]
	 */
	private function get_available_post_types() {
		$types = get_post_types( array( 'public' => true ), 'names' );

		if ( ! is_array( $types ) ) {
			$types = array();
		}

		$available = array();

		foreach ( $types as $type ) {
			if ( in_array( $type, self::CORE_POST_TYPES, true ) ) {
				continue;
			}

			$available[] = $type;
		}

		return $available;
	}

	/**
	 * Возвращает типы записей, выбранные для экспорта и импорта.
	 *
	 * Несуществующие типы отбрасываются, чтобы после смены темы
	 * или деактивации плагина экспорт не становился пустым.
	 *
	 * @return string[]
	 */
	private function get_post_types() {
		$settings = $this->get_settings();
		$stored   = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
			? $settings['post_types']
			: self::DEFAULT_POST_TYPES;

		$available = $this->get_available_post_types();
		$types     = array();

		foreach ( $stored as $type ) {
			if ( ! is_string( $type ) ) {
				continue;
			}

			$type = sanitize_key( $type );

			if ( in_array( $type, $available, true ) && ! in_array( $type, $types, true ) ) {
				$types[] = $type;
			}
		}

		// Типы не заданы или все устарели — работаем со всеми
		// пользовательскими типами записей сайта.
		if ( empty( $types ) ) {
			$types = $available;
		}

		/**
		 * Позволяет изменить список переносимых типов записей.
		 *
		 * @param string[] $types Типы записей.
		 */
		$types = (array) apply_filters( 'cpt_migrator_post_types', $types );

		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $types ) ) ) );
	}

	/**
	 * Возвращает типы записей, в которые разрешено импортировать данные.
	 *
	 * Это все зарегистрированные на сайте пользовательские типы записей,
	 * кроме служебных типов WordPress. Типы для экспорта и для импорта
	 * не связаны: экспорт выбранной настройкой, импорт — допустимыми
	 * типами сайта.
	 *
	 * @return string[]
	 */
	private function get_importable_post_types() {
		$types = get_post_types( array(), 'names' );

		if ( ! is_array( $types ) ) {
			$types = array();
		}

		$result = array();

		foreach ( $types as $type ) {
			if ( in_array( $type, self::CORE_POST_TYPES, true ) ) {
				continue;
			}

			$result[] = $type;
		}

		/**
		 * Позволяет изменить список типов записей, разрешённых при импорте.
		 *
		 * @param string[] $types Типы записей.
		 */
		$result = (array) apply_filters( 'cpt_migrator_import_post_types', $result );

		return array_values( array_unique( array_filter( array_map( 'sanitize_key', $result ) ) ) );
	}

	/**
	 * Возвращает сохранённые настройки с применением значений по умолчанию.
	 *
	 * @return array
	 */
	private function get_settings() {
		$stored   = get_option( self::SETTINGS_OPTION, array() );
		$defaults = $this->get_default_settings();

		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$settings = wp_parse_args( $stored, $defaults );

		$settings['auto_export']      = ! empty( $settings['auto_export'] ) ? 1 : 0;
		$settings['export_frequency'] = in_array( $settings['export_frequency'], self::EXPORT_SCHEDULES, true )
			? $settings['export_frequency']
			: 'daily';
		$settings['export_retention'] = max( 1, min( 100, (int) $settings['export_retention'] ) );
		$settings['post_types']       = $this->sanitize_post_types(
			isset( $settings['post_types'] ) ? $settings['post_types'] : array()
		);
		$settings['post_type_map']    = $this->sanitize_post_type_map(
			isset( $settings['post_type_map'] ) ? $settings['post_type_map'] : array()
		);

		return $settings;
	}

	/**
	 * Проверяет и очищает значения настроек.
	 *
	 * @param mixed $input Значения из формы.
	 *
	 * @return array
	 */
	public function sanitize_settings( $input ) {
		$output = $this->get_default_settings();

		if ( ! is_array( $input ) ) {
			return $output;
		}

		$output['auto_export'] = ! empty( $input['auto_export'] ) ? 1 : 0;

		$frequency = isset( $input['export_frequency'] ) ? sanitize_key( $input['export_frequency'] ) : 'daily';

		$output['export_frequency'] = in_array( $frequency, self::EXPORT_SCHEDULES, true ) ? $frequency : 'daily';

		$retention = isset( $input['export_retention'] ) ? absint( $input['export_retention'] ) : 5;

		$output['export_retention'] = max( 1, min( 100, $retention ) );

		$output['post_types'] = $this->sanitize_post_types(
			isset( $input['post_types'] ) ? $input['post_types'] : array()
		);

		$output['post_type_map'] = $this->sanitize_post_type_map(
			isset( $input['post_type_map'] ) ? $input['post_type_map'] : array(),
			isset( $input['post_type_map_new'] ) ? $input['post_type_map_new'] : '',
			isset( $input['post_type_map_new_target'] ) ? $input['post_type_map_new_target'] : ''
		);

		return $output;
	}

	/**
	 * Проверяет таблицу соответствий типов записей.
	 *
	 * @param mixed  $map       Таблица «тип в файле => тип на сайте».
	 * @param string $new_source Новый тип в файле.
	 * @param string $new_target Тип на сайте для новой строки.
	 *
	 * @return array
	 */
	private function sanitize_post_type_map( $map, $new_source = '', $new_target = '' ) {
		$available = $this->get_available_post_types();
		$result    = array();

		if ( is_array( $map ) ) {
			foreach ( $map as $source => $target ) {
				if ( ! is_string( $source ) || ! is_string( $target ) ) {
					continue;
				}

				$source = sanitize_key( $source );
				$target = sanitize_key( $target );

				if ( '' === $source || '' === $target ) {
					continue;
				}

				if ( ! in_array( $target, $available, true ) ) {
					continue;
				}

				$result[ $source ] = $target;
			}
		}

		// Новая строка, добавленная пользователем вручную.
		if ( is_string( $new_source ) && is_string( $new_target ) ) {
			$new_source = sanitize_key( $new_source );
			$new_target = sanitize_key( $new_target );

			if ( '' !== $new_source && '' !== $new_target && in_array( $new_target, $available, true ) ) {
				$result[ $new_source ] = $new_target;
			}
		}

		return $result;
	}

	/**
	 * Проверяет список типов записей из настроек.
	 *
	 * @param mixed $types Список типов.
	 *
	 * @return string[]
	 */
	private function sanitize_post_types( $types ) {
		if ( ! is_array( $types ) ) {
			$types = array();
		}

		$available = $this->get_available_post_types();
		$result    = array();

		foreach ( $types as $type ) {
			if ( ! is_string( $type ) ) {
				continue;
			}

			$type = sanitize_key( $type );

			if ( in_array( $type, $available, true ) && ! in_array( $type, $result, true ) ) {
				$result[] = $type;
			}
		}

		// Ничего не выбрано или выбраны несуществующие типы —
		// переносим все пользовательские типы записей сайта.
		if ( empty( $result ) ) {
			$result = $available;
		}

		return $result;
	}

	/**
	 * Описание раздела автоэкспорта.
	 */
	public function render_auto_export_section() {
		?>
		<p>
			<?php
			printf(
				/* translators: %s: путь к папке uploads. */
				esc_html__( 'Резервные копии сохраняются в %s. Файлы защищены от прямого доступа из браузера.', 'cpt-migrator' ),
				'<code>' . esc_html( $this->get_export_dir() ) . '</code>'
			);
			?>
		</p>
		<?php
	}

	/**
	 * Поле «Включить автоэкспорт».
	 */
	public function render_auto_export_field() {
		$settings = $this->get_settings();
		?>
		<label for="cpt_migrator_auto_export">
			<input
				type="checkbox"
				id="cpt_migrator_auto_export"
				name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[auto_export]"
				value="1"
				<?php checked( 1, (int) $settings['auto_export'] ); ?>
			/>
			<?php esc_html_e( 'Выгружать записи по расписанию через wp-cron', 'cpt-migrator' ); ?>
		</label>
		<?php
	}

	/**
	 * Поле «Периодичность».
	 */
	public function render_export_frequency_field() {
		$settings = $this->get_settings();
		?>
		<select
			id="cpt_migrator_export_frequency"
			name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[export_frequency]"
		>
			<option value="daily" <?php selected( 'daily', $settings['export_frequency'] ); ?>>
				<?php esc_html_e( 'Ежедневно', 'cpt-migrator' ); ?>
			</option>
			<option value="weekly" <?php selected( 'weekly', $settings['export_frequency'] ); ?>>
				<?php esc_html_e( 'Еженедельно', 'cpt-migrator' ); ?>
			</option>
		</select>
		<p class="description">
			<?php esc_html_e( 'Расписание выполняется на фоне сайта при посещении страниц (wp-cron).', 'cpt-migrator' ); ?>
		</p>
		<?php
	}

	/**
	 * Поле «Хранить последние N файлов».
	 */
	public function render_export_retention_field() {
		$settings = $this->get_settings();
		?>
		<input
			type="number"
			id="cpt_migrator_export_retention"
			name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[export_retention]"
			value="<?php echo esc_attr( (string) (int) $settings['export_retention'] ); ?>"
			min="1"
			max="100"
			step="1"
			class="small-text"
		/>
		<p class="description">
			<?php esc_html_e( 'Более старые файлы будут удаляться автоматически.', 'cpt-migrator' ); ?>
		</p>
		<?php
	}

	/**
	 * Описание раздела типов записей.
	 */
	public function render_content_section() {
		?>
		<p>
			<?php esc_html_e( 'Выберите типы записей для выгрузки в JSON. При импорте данные попадают в одноимённые типы записей этого сайта; если в файле тип назван иначе, задайте соответствие в таблице ниже.', 'cpt-migrator' ); ?>
		</p>
		<?php
	}

	/**
	 * Поле выбора переносимых типов записей.
	 */
	public function render_post_types_field() {
		$settings  = $this->get_settings();
		$selected  = isset( $settings['post_types'] ) && is_array( $settings['post_types'] )
			? $settings['post_types']
			: $this->get_available_post_types();
		$available = $this->get_available_post_types();

		if ( empty( $available ) ) {
			?>
			<p class="notice notice-warning">
				<?php esc_html_e( 'На сайте не найдено ни одного пользовательского типа записи.', 'cpt-migrator' ); ?>
			</p>
			<?php
			return;
		}

		$objects = get_post_types( array(), 'objects' );
		?>
		<p class="description">
			<?php esc_html_e( 'Какие типы записей выгружать в JSON-файл. Если ничего не выбрано, выгружаются все пользовательские типы записей сайта. Типы для импорта определяются автоматически.', 'cpt-migrator' ); ?>
		</p>
		<fieldset>
			<legend class="screen-reader-text">
				<?php esc_html_e( 'Типы записей для экспорта', 'cpt-migrator' ); ?>
			</legend>
			<?php foreach ( $available as $type ) : ?>
				<label style="display:block;margin-bottom:4px;">
					<input
						type="checkbox"
						name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[post_types][]"
						value="<?php echo esc_attr( $type ); ?>"
						<?php checked( in_array( $type, $selected, true ) ); ?>
					/>
					<?php
					echo esc_html(
						isset( $objects[ $type ] ) && isset( $objects[ $type ]->labels->name )
							? $objects[ $type ]->labels->name
							: $type
					);
					echo ' <code>' . esc_html( $type ) . '</code>';
					?>
				</label>
			<?php endforeach; ?>
		</fieldset>
		<?php
	}

	/**
	 * Поле таблицы соответствий типов записей из файла типам на сайте.
	 */
	public function render_post_type_map_field() {
		$map       = $this->get_post_type_map();
		$available = $this->get_available_post_types();
		$detected  = get_option( self::DETECTED_POST_TYPES_OPTION, array() );
		$detected  = is_array( $detected ) ? $detected : array();

		$sources = array();

		foreach ( $detected as $type ) {
			if ( is_string( $type ) && '' !== $type && ! in_array( $type, $sources, true ) ) {
				$sources[] = $type;
			}
		}

		foreach ( array_keys( $map ) as $type ) {
			if ( ! in_array( $type, $sources, true ) ) {
				$sources[] = $type;
			}
		}

		$sources = array_slice( $sources, 0, self::MAX_POST_TYPE_MAP_ROWS );
		$objects = get_post_types( array(), 'objects' );
		?>
		<p class="description">
			<?php esc_html_e( 'Если на исходном сайте типы записей называются иначе, укажите соответствие. Например: svidetelstvo → Свидетельства.', 'cpt-migrator' ); ?>
		</p>

		<?php if ( ! empty( $detected ) ) : ?>
			<p>
				<?php
				printf(
					/* translators: %s: comma separated post type slugs. */
					esc_html__( 'В последнем загруженном файле найдены типы: %s.', 'cpt-migrator' ),
					esc_html( implode( ', ', $detected ) )
				);
				?>
			</p>
		<?php endif; ?>

		<?php if ( empty( $sources ) ) : ?>
			<p class="description"><?php esc_html_e( 'Соответствия пока не требуются: загрузите JSON-файл, чтобы увидеть его типы записей.', 'cpt-migrator' ); ?></p>
		<?php else : ?>
			<table class="widefat striped" style="max-width: 720px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Тип в файле', 'cpt-migrator' ); ?></th>
						<th><?php esc_html_e( 'Тип на этом сайте', 'cpt-migrator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $sources as $source ) : ?>
						<tr>
							<td>
								<code><?php echo esc_html( $source ); ?></code>
							</td>
							<td>
								<select name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[post_type_map][<?php echo esc_attr( $source ); ?>]">
									<option value=""><?php esc_html_e( '— не переносить —', 'cpt-migrator' ); ?></option>
									<?php foreach ( $available as $type ) : ?>
										<option value="<?php echo esc_attr( $type ); ?>" <?php selected( isset( $map[ $source ] ) ? $map[ $source ] : '', $type ); ?>>
											<?php
											echo esc_html(
												isset( $objects[ $type ] ) && isset( $objects[ $type ]->labels->name )
													? $objects[ $type ]->labels->name
													: $type
											);
											?>
										</option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<p style="margin-top: 12px;">
				<label style="display:block;">
					<span style="display:inline-block;width:220px;"><?php esc_html_e( 'Добавить тип из файла', 'cpt-migrator' ); ?></span>
					<input type="text" name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[post_type_map_new]" value="" placeholder="например, svidetelstvo" class="regular-text" />
				</label>
				<label style="display:block;margin-top:6px;">
					<span style="display:inline-block;width:220px;"><?php esc_html_e( 'Перенести в', 'cpt-migrator' ); ?></span>
					<select name="<?php echo esc_attr( self::SETTINGS_OPTION ); ?>[post_type_map_new_target]">
						<option value=""><?php esc_html_e( '— выберите тип —', 'cpt-migrator' ); ?></option>
						<?php foreach ( $available as $type ) : ?>
							<option value="<?php echo esc_attr( $type ); ?>">
								<?php
								echo esc_html(
									isset( $objects[ $type ] ) && isset( $objects[ $type ]->labels->name )
										? $objects[ $type ]->labels->name
										: $type
								);
								?>
							</option>
						<?php endforeach; ?>
					</select>
				</label>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Описание раздела хранилища.
	 */
	public function render_storage_section() {
		?>
		<p>
			<?php esc_html_e( 'Папка защищена файлами .htaccess и index.php, имена файлов непредсказуемы.', 'cpt-migrator' ); ?>
		</p>
		<?php
	}

	/**
	 * Поле с путём к папке автоэкспорта.
	 */
	public function render_storage_path_field() {
		?>
		<code><?php echo esc_html( $this->get_export_dir() ); ?></code>
		<?php
	}

	/**
	 * Записывает событие в журнал операций.
	 *
	 * @param string $action  Тип операции.
	 * @param array  $details Детали операции.
	 */
	private function log_event( $action, $details = array() ) {
		$user = wp_get_current_user();

		$entry = array(
			'time'    => current_time( 'mysql' ),
			'user_id' => get_current_user_id(),
			'user'    => ( $user && $user->ID ) ? $user->user_login : __( 'Система (cron)', 'cpt-migrator' ),
			'action'  => sanitize_key( $action ),
			'details' => $details,
		);

		/**
		 * Позволяет изменить или отключить запись в журнал.
		 *
		 * @param array $entry Запись журнала.
		 */
		$entry = apply_filters( 'cpt_migrator_log_entry', $entry );

		if ( ! is_array( $entry ) ) {
			return;
		}

		$log = get_option( self::LOG_OPTION, array() );

		if ( ! is_array( $log ) ) {
			$log = array();
		}

		array_unshift( $log, $entry );

		if ( count( $log ) > self::LOG_MAX_ENTRIES ) {
			$log = array_slice( $log, 0, self::LOG_MAX_ENTRIES );
		}

		update_option( self::LOG_OPTION, $log, false );
	}

	/**
	 * Возвращает записи журнала.
	 *
	 * @return array
	 */
	private function get_log_entries() {
		$log = get_option( self::LOG_OPTION, array() );

		return is_array( $log ) ? $log : array();
	}

	/**
	 * Обрабатывает очистку журнала (обработчик admin-post).
	 */
	public function handle_clear_log() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Недостаточно прав для очистки журнала.', 'cpt-migrator' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::CLEAR_LOG_ACTION );

		delete_option( self::LOG_OPTION );

		$this->log_event(
			'log_cleared',
			array(
				'source' => __( 'Вручную', 'cpt-migrator' ),
			)
		);

		$this->redirect_to_page(
			array(
				'tab'  => 'log',
				'note' => 'cleared',
			)
		);
	}

	/**
	 * Обрабатывает ручной запуск автоэкспорта (обработчик admin-post).
	 */
	public function handle_run_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Недостаточно прав для запуска экспорта.', 'cpt-migrator' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::RUN_EXPORT_ACTION );

		$result = $this->perform_export( 'manual' );

		$this->redirect_to_page(
			array(
				'tab'  => 'settings',
				'note' => ( false !== $result ) ? 'exported' : 'export_failed',
			)
		);
	}

	/**
	 * Возвращает путь к папке автоэкспорта внутри wp-content/uploads.
	 *
	 * @return string
	 */
	private function get_export_dir() {
		$uploads = wp_upload_dir();
		$basedir = isset( $uploads['basedir'] ) ? $uploads['basedir'] : WP_CONTENT_DIR;

		return trailingslashit( $basedir ) . self::EXPORT_DIR_NAME;
	}

	/**
	 * Создаёт папку автоэкспорта и файлы защиты.
	 *
	 * Файлы защиты создаются ДО записи данных, чтобы содержимое
	 * никогда не оказывалось в публичной папке без запрета доступа.
	 *
	 * @return string|false Путь к папке или false при ошибке.
	 */
	private function prepare_export_dir() {
		$dir = $this->get_export_dir();

		if ( is_link( $dir ) ) {
			return false;
		}

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			return false;
		}

		if ( ! is_writable( $dir ) ) {
			return false;
		}

		$htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';

		if ( ! file_exists( $htaccess ) ) {
			$rules = "# Запрет прямого доступа к файлам автоэкспорта.\n"
				. "<IfModule mod_authz_core.c>\n"
				. "\tRequire all denied\n"
				. "</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n"
				. "\tOrder allow,deny\n"
				. "\tDeny from all\n"
				. "</IfModule>\n";

			if ( false === file_put_contents( $htaccess, $rules, LOCK_EX ) ) {
				return false;
			}
		}

		$index = $dir . DIRECTORY_SEPARATOR . 'index.php';

		if ( ! file_exists( $index ) && false === file_put_contents( $index, '', LOCK_EX ) ) {
			return false;
		}

		return $dir;
	}

	/**
	 * Генерирует непредсказуемое имя файла автоэкспорта.
	 *
	 * @return string
	 */
	private function generate_export_filename() {
		return 'cpt-export-' . gmdate( 'Ymd-His' ) . '-' . wp_generate_password( 16, false, false ) . '.json';
	}

	/**
	 * Сохраняет JSON-файл автоэкспорта в защищённую папку.
	 *
	 * @param string $json Содержимое JSON.
	 *
	 * @return string|false Путь к файлу или false при ошибке.
	 */
	private function save_export_file( $json ) {
		$dir = $this->prepare_export_dir();

		if ( false === $dir ) {
			return false;
		}

		$filename = $this->generate_export_filename();
		$path     = $dir . DIRECTORY_SEPARATOR . $filename;

		if ( false === file_put_contents( $path, $json, LOCK_EX ) ) {
			return false;
		}

		@chmod( $path, 0640 );

		return $path;
	}

	/**
	 * Удаляет лишние файлы автоэкспорта, оставляя последние N.
	 *
	 * @param int $keep Сколько файлов оставить.
	 *
	 * @return int Сколько файлов удалено.
	 */
	private function prune_export_files( $keep ) {
		$keep = max( 1, (int) $keep );
		$dir  = $this->get_export_dir();

		if ( ! is_dir( $dir ) || is_link( $dir ) ) {
			return 0;
		}

		$files = glob( $dir . DIRECTORY_SEPARATOR . 'cpt-export-*.json' );

		if ( ! is_array( $files ) || count( $files ) <= $keep ) {
			return 0;
		}

		usort(
			$files,
			static function ( $first, $second ) {
				return filemtime( $second ) - filemtime( $first );
			}
		);

		$removed = 0;

		foreach ( array_slice( $files, $keep ) as $file ) {
			if ( @unlink( $file ) ) {
				++$removed;
			}
		}

		return $removed;
	}

	/**
	 * Планирует или перепланирует автоэкспорт.
	 */
	private function reschedule_cron() {
		$settings = $this->get_settings();

		$this->unschedule_cron();

		if ( empty( $settings['auto_export'] ) ) {
			return;
		}

		$recurrence = 'weekly' === $settings['export_frequency'] ? 'cpt_migrator_weekly' : 'daily';

		wp_schedule_event( time() + HOUR_IN_SECONDS, $recurrence, self::CRON_HOOK );
	}

	/**
	 * Снимает расписание автоэкспорта.
	 */
	private function unschedule_cron() {
		$timestamp = wp_next_scheduled( self::CRON_HOOK );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::CRON_HOOK );
			$timestamp = wp_next_scheduled( self::CRON_HOOK );
		}
	}

	/**
	 * Выполняет автоэкспорт по расписанию.
	 */
	public function run_scheduled_export() {
		$settings = $this->get_settings();

		if ( empty( $settings['auto_export'] ) ) {
			return;
		}

		$this->perform_export( 'cron' );
	}

	/**
	 * Формирует и сохраняет файл автоэкспорта.
	 *
	 * @param string $source Источник запуска: cron, manual.
	 *
	 * @return array|false
	 */
	private function perform_export( $source ) {
		$settings = $this->get_settings();

		$payload = $this->build_export_payload( false );

		if ( is_wp_error( $payload ) ) {
			$this->log_event(
				'export_failed',
				array(
					'source'  => $source,
					'message' => $payload->get_error_message(),
				)
			);

			return false;
		}

		$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

		if ( false === $json ) {
			$this->log_event(
				'export_failed',
				array(
					'source'  => $source,
					'message' => __( 'Не удалось сформировать JSON.', 'cpt-migrator' ),
				)
			);

			return false;
		}

		$path = $this->save_export_file( $json );

		if ( false === $path ) {
			$this->log_event(
				'export_failed',
				array(
					'source'  => $source,
					'message' => __( 'Не удалось записать файл автоэкспорта.', 'cpt-migrator' ),
				)
			);

			return false;
		}

		$removed = $this->prune_export_files( (int) $settings['export_retention'] );

		$this->log_event(
			'export',
			array(
				'source'    => $source,
				'posts'     => isset( $payload['posts_count'] ) ? (int) $payload['posts_count'] : 0,
				'file'      => wp_basename( $path ),
				'bytes'     => (int) filesize( $path ),
				'removed'   => $removed,
			)
		);

		return array(
			'path'  => $path,
			'count' => isset( $payload['posts_count'] ) ? (int) $payload['posts_count'] : 0,
		);
	}

	/**
	 * Возвращает активную вкладку.
	 *
	 * Значение из URL проверяется по белому списку; при отсутствии или
	 * недопустимом значении используется «export».
	 *
	 * @return string
	 */
	private function get_active_tab() {
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'export';

		return in_array( $tab, self::ALLOWED_TABS, true ) ? $tab : 'export';
	}

	/**
	 * Выводит страницу плагина.
	 */
	public function render_page() {
		// Дополнительная проверка прав: страница уже защищена capability в add_management_page().
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Недостаточно прав для доступа к этой странице.', 'cpt-migrator' ) );
		}

		$active_tab = $this->get_active_tab();
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<h2 class="nav-tab-wrapper">
				<?php
				$this->render_tab_link( 'export', __( 'Экспорт', 'cpt-migrator' ), $active_tab );
				$this->render_tab_link( 'import', __( 'Импорт', 'cpt-migrator' ), $active_tab );
				$this->render_tab_link( 'settings', __( 'Настройки', 'cpt-migrator' ), $active_tab );
				$this->render_tab_link( 'log', __( 'Журнал', 'cpt-migrator' ), $active_tab );
				?>
			</h2>

			<?php
			switch ( $active_tab ) {
				case 'import':
					$this->render_import_tab();
					break;

				case 'settings':
					$this->render_settings_tab();
					break;

				case 'log':
					$this->render_log_tab();
					break;

				default:
					$this->render_export_tab();
					break;
			}
			?>
		</div>
		<?php
	}

	/**
	 * Выводит ссылку вкладки.
	 *
	 * @param string $tab        Слаг вкладки.
	 * @param string $label      Подпись вкладки.
	 * @param string $active_tab Текущая активная вкладка.
	 */
	private function render_tab_link( $tab, $label, $active_tab ) {
		$url = add_query_arg(
			array(
				'page' => self::PAGE_SLUG,
				'tab'  => $tab,
			),
			admin_url( 'tools.php' )
		);

		$classes = 'nav-tab';
		if ( $active_tab === $tab ) {
			$classes .= ' nav-tab-active';
		}
		?>
		<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $classes ); ?>"<?php echo $active_tab === $tab ? ' aria-current="page"' : ''; ?>>
			<?php echo esc_html( $label ); ?>
		</a>
		<?php
	}

	/**
	 * Выводит вкладку «Экспорт».
	 */
	private function render_export_tab() {
		$post_types = $this->get_post_types();
		?>
		<div class="cpt-migrator-tab-panel">
			<p><?php esc_html_e( 'Будут выгружены записи выбранных типов. По умолчанию записи из корзины не включаются.', 'cpt-migrator' ); ?></p>

			<?php $this->render_export_summary( $post_types ); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php /* Значение action и действие nonce проверяются в handle_export(). */ ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::EXPORT_ACTION ); ?>" />
				<?php wp_nonce_field( self::EXPORT_ACTION, self::EXPORT_NONCE_NAME ); ?>

				<p>
					<label for="cpt_migrator_include_trash">
						<input type="checkbox" id="cpt_migrator_include_trash" name="cpt_migrator_include_trash" value="1" />
						<?php esc_html_e( 'Включить записи из корзины', 'cpt-migrator' ); ?>
					</label>
				</p>

				<?php submit_button( __( 'Скачать JSON', 'cpt-migrator' ), 'primary', 'cpt_migrator_export_submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Выводит сводку по количеству записей, попадающих в экспорт.
	 *
	 * @param string[] $post_types Типы записей.
	 */
	private function render_export_summary( $post_types ) {
		if ( empty( $post_types ) ) {
			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Не выбрано ни одного типа записей. Укажите их на вкладке «Настройки».', 'cpt-migrator' ); ?></p>
			</div>
			<?php
			return;
		}

		$objects   = get_post_types( array(), 'objects' );
		$statuses  = array( 'publish', 'future', 'draft', 'pending', 'private' );
		$total     = 0;
		$rows      = array();

		foreach ( $post_types as $type ) {
			$counts = wp_count_posts( $type );
			$count  = 0;

			if ( isset( $counts ) ) {
				foreach ( $statuses as $status ) {
					if ( isset( $counts->$status ) ) {
						$count += (int) $counts->$status;
					}
				}
			}

			$total += $count;
			$label  = isset( $objects[ $type ] ) && isset( $objects[ $type ]->labels->name )
				? $objects[ $type ]->labels->name
				: $type;

			$rows[] = array(
				'label' => $label,
				'type'  => $type,
				'count' => $count,
			);
		}
		?>
		<table class="widefat striped" style="max-width: 640px;margin-bottom: 16px;">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Тип записи', 'cpt-migrator' ); ?></th>
					<th><?php esc_html_e( 'Слаг', 'cpt-migrator' ); ?></th>
					<th><?php esc_html_e( 'Записей', 'cpt-migrator' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $row ) : ?>
					<tr>
						<td><?php echo esc_html( $row['label'] ); ?></td>
						<td><code><?php echo esc_html( $row['type'] ); ?></code></td>
						<td><?php echo esc_html( (string) $row['count'] ); ?></td>
					</tr>
				<?php endforeach; ?>
			</tbody>
			<tfoot>
				<tr>
					<th><?php esc_html_e( 'Всего', 'cpt-migrator' ); ?></th>
					<td></td>
					<td><strong><?php echo esc_html( (string) $total ); ?></strong></td>
				</tr>
			</tfoot>
		</table>

		<?php if ( 0 === $total ) : ?>
			<div class="notice notice-warning">
				<p>
					<?php esc_html_e( 'В выбранных типах записей нет данных. Проверьте типы на вкладке «Настройки» и статус публикации записей.', 'cpt-migrator' ); ?>
				</p>
			</div>
		<?php endif; ?>
		<?php
	}

	/**
	 * Выводит вкладку «Настройки».
	 */
	private function render_settings_tab() {
		$settings = $this->get_settings();
		$note     = isset( $_GET['note'] ) ? sanitize_key( wp_unslash( $_GET['note'] ) ) : '';
		$next     = wp_next_scheduled( self::CRON_HOOK );
		?>
		<div class="cpt-migrator-tab-panel">
			<?php if ( 'exported' === $note ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Файл автоэкспорта создан.', 'cpt-migrator' ); ?></p>
				</div>
			<?php elseif ( 'export_failed' === $note ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php esc_html_e( 'Не удалось создать файл автоэкспорта.', 'cpt-migrator' ); ?></p>
				</div>
			<?php elseif ( 'unmapped' === $note ) : ?>
				<div class="notice notice-warning">
					<p>
						<?php esc_html_e( 'В файле найдены типы записей, для которых не настроено соответствие. Заполните таблицу «Соответствие типов записей» ниже и загрузите файл заново.', 'cpt-migrator' ); ?>
					</p>
				</div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php
				settings_fields( self::SETTINGS_GROUP );
				do_settings_sections( self::SETTINGS_OPTION );
				submit_button( __( 'Сохранить настройки', 'cpt-migrator' ) );
				?>
			</form>

			<hr />

			<h2><?php esc_html_e( 'Состояние расписания', 'cpt-migrator' ); ?></h2>

			<table class="widefat striped" style="max-width: 640px;">
				<tbody>
					<tr>
						<th scope="row"><?php esc_html_e( 'Автоэкспорт', 'cpt-migrator' ); ?></th>
						<td>
							<?php
							echo empty( $settings['auto_export'] )
								? esc_html__( 'Выключен', 'cpt-migrator' )
								: esc_html__( 'Включён', 'cpt-migrator' );
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Периодичность', 'cpt-migrator' ); ?></th>
						<td>
							<?php
							echo 'weekly' === $settings['export_frequency']
								? esc_html__( 'Еженедельно', 'cpt-migrator' )
								: esc_html__( 'Ежедневно', 'cpt-migrator' );
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Ближайший запуск', 'cpt-migrator' ); ?></th>
						<td>
							<?php
							echo $next
								? esc_html( wp_date( 'd.m.Y H:i', (int) $next ) )
								: esc_html__( 'Не запланирован', 'cpt-migrator' );
							?>
						</td>
					</tr>
				</tbody>
			</table>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 12px;">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::RUN_EXPORT_ACTION ); ?>" />
				<?php wp_nonce_field( self::RUN_EXPORT_ACTION ); ?>
				<?php submit_button( __( 'Выполнить автоэкспорт сейчас', 'cpt-migrator' ), 'secondary', 'cpt_migrator_run_export_submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Выводит вкладку «Журнал».
	 */
	private function render_log_tab() {
		$entries = $this->get_log_entries();
		$total   = count( $entries );
		$pages   = max( 1, (int) ceil( $total / self::LOG_PER_PAGE ) );
		$page    = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$page    = min( $page, $pages );
		$slice   = array_slice( $entries, ( $page - 1 ) * self::LOG_PER_PAGE, self::LOG_PER_PAGE );
		$note    = isset( $_GET['note'] ) ? sanitize_key( wp_unslash( $_GET['note'] ) ) : '';
		?>
		<div class="cpt-migrator-tab-panel">
			<?php if ( 'cleared' === $note ) : ?>
				<div class="notice notice-success is-dismissible">
					<p><?php esc_html_e( 'Журнал очищен.', 'cpt-migrator' ); ?></p>
				</div>
			<?php endif; ?>

			<p>
				<?php
				printf(
					/* translators: %d: number of log entries. */
					esc_html__( 'Всего записей в журнале: %d.', 'cpt-migrator' ),
					(int) $total
				);
				?>
			</p>

			<?php if ( empty( $slice ) ) : ?>
				<p><?php esc_html_e( 'Журнал пока пуст.', 'cpt-migrator' ); ?></p>
			<?php else : ?>
				<table class="widefat striped cpt-migrator-log-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Дата и время', 'cpt-migrator' ); ?></th>
							<th><?php esc_html_e( 'Пользователь', 'cpt-migrator' ); ?></th>
							<th><?php esc_html_e( 'Операция', 'cpt-migrator' ); ?></th>
							<th><?php esc_html_e( 'Детали', 'cpt-migrator' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $slice as $entry ) : ?>
							<?php
							$entry  = is_array( $entry ) ? $entry : array();
							$time   = isset( $entry['time'] ) ? (string) $entry['time'] : '';
							$user   = isset( $entry['user'] ) ? (string) $entry['user'] : '';
							$action = isset( $entry['action'] ) ? (string) $entry['action'] : '';
							$detail = isset( $entry['details'] ) && is_array( $entry['details'] ) ? $entry['details'] : array();
							?>
							<tr>
								<td><?php echo esc_html( $time ); ?></td>
								<td><?php echo esc_html( $user ); ?></td>
								<td><?php echo esc_html( $this->get_log_action_label( $action ) ); ?></td>
								<td><?php echo esc_html( $this->format_log_details( $detail ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<?php if ( $pages > 1 ) : ?>
					<div class="tablenav bottom">
						<div class="tablenav-pages">
							<span class="displaying-num">
								<?php
								printf(
									/* translators: %s: page number. */
									esc_html__( 'Страница %s из %d', 'cpt-migrator' ),
									esc_html( (string) $page ),
									(int) $pages
								);
								?>
							</span>
							<?php
							echo wp_kses_post(
								paginate_links(
									array(
										'base'      => add_query_arg( 'paged', '%#%' ),
										'format'    => '',
										'current'   => $page,
										'total'     => $pages,
										'prev_text' => __( '&laquo;', 'cpt-migrator' ),
										'next_text' => __( '&raquo;', 'cpt-migrator' ),
									)
								)
							);
							?>
						</div>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin-top: 12px;">
				<input type="hidden" name="action" value="<?php echo esc_attr( self::CLEAR_LOG_ACTION ); ?>" />
				<?php wp_nonce_field( self::CLEAR_LOG_ACTION ); ?>
				<?php submit_button( __( 'Очистить журнал', 'cpt-migrator' ), 'delete', 'cpt_migrator_clear_log_submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Возвращает читаемую подпись операции журнала.
	 *
	 * @param string $action Тип операции.
	 *
	 * @return string
	 */
	private function get_log_action_label( $action ) {
		$labels = array(
			'export'        => __( 'Экспорт', 'cpt-migrator' ),
			'export_failed' => __( 'Ошибка экспорта', 'cpt-migrator' ),
			'import'        => __( 'Импорт', 'cpt-migrator' ),
			'import_failed' => __( 'Ошибка импорта', 'cpt-migrator' ),
			'log_cleared'   => __( 'Очистка журнала', 'cpt-migrator' ),
		);

		return isset( $labels[ $action ] ) ? $labels[ $action ] : $action;
	}

	/**
	 * Форматирует детали операции для вывода в журнале.
	 *
	 * @param array $details Детали.
	 *
	 * @return string
	 */
	private function format_log_details( $details ) {
		if ( empty( $details ) ) {
			return '—';
		}

		$labels = array(
			'source'  => __( 'Источник', 'cpt-migrator' ),
			'posts'   => __( 'Записей', 'cpt-migrator' ),
			'file'    => __( 'Файл', 'cpt-migrator' ),
			'bytes'   => __( 'Размер', 'cpt-migrator' ),
			'removed' => __( 'Удалено файлов', 'cpt-migrator' ),
			'created' => __( 'Создано', 'cpt-migrator' ),
			'updated' => __( 'Обновлено', 'cpt-migrator' ),
			'skipped' => __( 'Пропущено', 'cpt-migrator' ),
			'errors'  => __( 'Ошибок', 'cpt-migrator' ),
			'images'  => __( 'Ошибок изображений', 'cpt-migrator' ),
			'message' => __( 'Сообщение', 'cpt-migrator' ),
		);

		$parts = array();

		foreach ( $details as $key => $value ) {
			if ( ! isset( $labels[ $key ] ) ) {
				continue;
			}

			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'strval', $value ) );
			}

			$parts[] = $labels[ $key ] . ': ' . (string) $value;
		}

		return empty( $parts ) ? '—' : implode( '; ', $parts );
	}

	/**
	 * Формирует и отдаёт JSON-файл экспорта.
	 *
	 * Хук: admin_post_cpt_migrator_export.
	 */
	public function handle_export() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Недостаточно прав для экспорта.', 'cpt-migrator' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::EXPORT_ACTION, self::EXPORT_NONCE_NAME );

		$include_trash = isset( $_POST['cpt_migrator_include_trash'] )
			&& '1' === sanitize_text_field( wp_unslash( $_POST['cpt_migrator_include_trash'] ) );

		$payload = $this->build_export_payload( $include_trash );

		if ( is_wp_error( $payload ) ) {
			wp_die(
				esc_html( $payload->get_error_message() ),
				'',
				array( 'response' => 500 )
			);
		}

		$json = wp_json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT );

		if ( false === $json ) {
			wp_die( esc_html__( 'Не удалось сформировать JSON-файл.', 'cpt-migrator' ) );
		}

		$filename = sanitize_file_name( 'cpt-export-' . wp_date( 'Y-m-d' ) . '.json' );

		$this->log_event(
			'export',
			array(
				'source' => __( 'Вручную', 'cpt-migrator' ),
				'posts'  => isset( $payload['posts_count'] ) ? (int) $payload['posts_count'] : 0,
				'bytes'  => strlen( $json ),
			)
		);

		nocache_headers();
		header( 'Content-Type: application/json; charset=UTF-8' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );

		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- JSON отдаётся как файл.
		exit;
	}

	/**
	 * Собирает данные экспорта.
	 *
	 * @param bool $include_trash Включать ли записи из корзины.
	 *
	 * @return array|WP_Error
	 */
	private function build_export_payload( $include_trash ) {
		// Корзина добавляется только по явному согласию пользователя.
		$post_statuses = array( 'publish', 'future', 'draft', 'pending', 'private' );
		if ( $include_trash ) {
			$post_statuses[] = 'trash';
		}

		$post_types = $this->get_post_types();

		if ( empty( $post_types ) ) {
			return new WP_Error(
				'cpt_migrator_no_post_types',
				__( 'Не выбрано ни одного типа записей для переноса. Укажите типы в настройках плагина.', 'cpt-migrator' )
			);
		}

		$posts = get_posts(
			array(
				'post_type'      => $post_types,
				'post_status'    => $post_statuses,
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
			)
		);

		$items = array();

		foreach ( $posts as $post ) {
			$taxonomies = $this->get_taxonomies_for_post( $post );

			if ( is_wp_error( $taxonomies ) ) {
				return $taxonomies;
			}

			$items[] = array(
				'ID'               => (int) $post->ID,
				'post_type'        => $post->post_type,
				'post_title'       => $post->post_title,
				'post_name'        => $post->post_name,
				'post_content'     => $post->post_content,
				'post_excerpt'     => $post->post_excerpt,
				'post_status'      => $post->post_status,
				'post_date'        => $post->post_date,
				'post_date_gmt'    => $post->post_date_gmt,
				'menu_order'       => (int) $post->menu_order,
				'meta'             => $this->get_meta_for_post( $post ),
				'taxonomies'       => $taxonomies,
				'thumbnail'        => $this->get_thumbnail_for_post( $post ),
				'attachments_used' => $this->get_attachments_used( $post ),
			);
		}

		return array(
			'format_version' => self::EXPORT_FORMAT_VERSION,
			'exported_at'    => gmdate( 'c' ),
			'site_url'       => home_url( '/' ),
			'posts_count'    => count( $items ),
			'posts'          => $items,
		);
	}

	/**
	 * Возвращает метаполя записи для экспорта.
	 *
	 * @param WP_Post $post Запись.
	 *
	 * @return array
	 */
	private function get_meta_for_post( $post ) {
		$excluded_keys = $this->get_excluded_meta_keys( $post );
		$meta          = array();

		foreach ( get_post_meta( $post->ID ) as $key => $values ) {
			if ( in_array( $key, $excluded_keys, true ) ) {
				continue;
			}

			// Ключи, похожие на секреты, никогда не попадают в выгрузку.
			if ( preg_match( self::SENSITIVE_META_PATTERN, $key ) ) {
				continue;
			}

			$prepared = array();

			foreach ( $values as $value ) {
				if ( in_array( $key, self::ELEMENTOR_META_KEYS, true ) ) {
					// Elementor-ключи сохраняем ровно в том виде, в каком они лежат в БД.
					$prepared[] = $value;
					continue;
				}

				$prepared[] = $this->normalize_meta_value( $value );
			}

			$meta[ $key ] = 1 === count( $prepared ) ? $prepared[0] : $prepared;
		}

		return $meta;
	}

	/**
	 * Возвращает список системных мета-ключей, которые не попадают в экспорт.
	 *
	 * @param WP_Post $post Запись.
	 *
	 * @return string[]
	 */
	private function get_excluded_meta_keys( $post ) {
		$excluded = array(
			'_edit_lock',
			'_edit_last',
			'_wp_old_slug',
			'_wp_old_date',
			'_wp_old_status',
			'_wp_old_parent',
			'_wp_desired_post_slug',
			'_encloseme',
			'_pingme',
			'_wp_trash_meta_status',
			'_wp_trash_meta_time',
			'_wp_attachment_metadata',
			'_wp_attached_file',
			'_thumbnail_id', // Миниатюра выгружается отдельным полем.
		);

		/**
		 * Позволяет изменить список исключаемых мета-ключей.
		 *
		 * @param string[] $excluded Системные ключи.
		 * @param WP_Post  $post     Текущая запись.
		 */
		return (array) apply_filters( 'cpt_migrator_excluded_meta_keys', $excluded, $post );
	}

	/**
	 * Рекурсивно разворачивает сериализованные значения метаполей.
	 *
	 * Используется безопасная десериализация: объекты не создаются
	 * (allowed_classes => false), поэтому импорт не может привести к
	 * PHP Object Injection через подделанную сериализованную строку.
	 *
	 * @param mixed $value Значение метаполя.
	 *
	 * @return mixed
	 */
	private function normalize_meta_value( $value ) {
		if ( is_string( $value ) ) {
			$value = $this->safe_unserialize( $value );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $index => $item ) {
				$value[ $index ] = $this->normalize_meta_value( $item );
			}
		}

		return $value;
	}

	/**
	 * Безопасно разворачивает сериализованную строку.
	 *
	 * @param string $value Значение.
	 *
	 * @return mixed
	 */
	private function safe_unserialize( $value ) {
		if ( ! is_serialized( $value ) ) {
			return $value;
		}

		$trimmed  = trim( $value );
		$decoded  = @unserialize( $trimmed, array( 'allowed_classes' => false ) );

		if ( false === $decoded && 'b:0;' !== $trimmed ) {
			// Данные повреждены или содержат объект — оставляем строку как есть.
			return $value;
		}

		if ( $this->contains_incomplete_class( $decoded ) ) {
			// Объект восстановить не удалось — безопаснее не разворачивать вовсе.
			return $value;
		}

		return $decoded;
	}

	/**
	 * Возвращает термины всех таксономий, связанных с типом записи.
	 *
	 * @param WP_Post $post Запись.
	 *
	 * @return array|WP_Error
	 */
	private function get_taxonomies_for_post( $post ) {
		$result     = array();
		$taxonomies = get_object_taxonomies( $post->post_type, 'objects' );

		foreach ( $taxonomies as $taxonomy ) {
			$terms = wp_get_object_terms( $post->ID, $taxonomy->name );

			if ( is_wp_error( $terms ) ) {
				return new WP_Error(
					'cpt_migrator_taxonomy_terms',
					sprintf(
						/* translators: 1: taxonomy name, 2: post ID, 3: error message. */
						__( 'Не удалось получить термины таксономии «%1$s» для записи #%2$d: %3$s', 'cpt-migrator' ),
						$taxonomy->name,
						$post->ID,
						$terms->get_error_message()
					)
				);
			}

			if ( empty( $terms ) ) {
				continue;
			}

			$result[ $taxonomy->name ] = array();

			foreach ( $terms as $term ) {
				$parent_slug = '';

				if ( $term->parent ) {
					$parent = get_term( $term->parent, $taxonomy->name );

					if ( $parent && ! is_wp_error( $parent ) ) {
						$parent_slug = $parent->slug;
					}
				}

				$result[ $taxonomy->name ][] = array(
					'slug'        => $term->slug,
					'name'        => $term->name,
					'parent_slug' => $parent_slug,
				);
			}
		}

		return $result;
	}

	/**
	 * Возвращает данные миниатюры записи.
	 *
	 * @param WP_Post $post Запись.
	 *
	 * @return array|null
	 */
	private function get_thumbnail_for_post( $post ) {
		$thumbnail_id = (int) get_post_thumbnail_id( $post->ID );

		if ( $thumbnail_id <= 0 ) {
			return null;
		}

		$url  = wp_get_attachment_url( $thumbnail_id );
		$file = get_attached_file( $thumbnail_id );
		$alt  = get_post_meta( $thumbnail_id, '_wp_attachment_image_alt', true );

		return array(
			'id'       => $thumbnail_id,
			'url'      => $url ? $url : '',
			'filename' => $file ? wp_basename( $file ) : '',
			'alt'      => is_string( $alt ) ? $alt : '',
			'title'    => get_the_title( $thumbnail_id ),
		);
	}

	/**
	 * Находит в контенте и данных Elementor ссылки на файлы из wp-content/uploads.
	 *
	 * @param WP_Post $post Запись.
	 *
	 * @return string[]
	 */
	private function get_attachments_used( $post ) {
		$patterns = array(
			// Обычные URL в HTML-контенте.
			'~(?:(?:https?:)?//[^\s"\'<>]+)?/wp-content/uploads/[^\s"\'<>]+~i',
			// URL внутри JSON (Elementor): слеши экранированы как \/.
			'~(?:(?:https?:)?\\\\/\\\\/[^\s"\'<>]+)?\\\\/wp-content\\\\/uploads\\\\/[^\s"\'<>]+~i',
		);

		$found   = array();
		$sources = array( $post->post_content );

		foreach ( self::ELEMENTOR_META_KEYS as $meta_key ) {
			$meta_value = get_post_meta( $post->ID, $meta_key, true );

			if ( is_string( $meta_value ) && '' !== $meta_value ) {
				$sources[] = $meta_value;
			}
		}

		foreach ( $sources as $source ) {
			foreach ( $patterns as $pattern ) {
				if ( preg_match_all( $pattern, $source, $matches ) ) {
					foreach ( $matches[0] as $match ) {
						$found[] = $this->cleanup_attachment_url( $match );
					}
				}
			}
		}

		return array_values( array_unique( array_filter( $found ) ) );
	}

	/**
	 * Приводит найденный URL к рабочему виду: убирает экранирование слешей и хвостовые символы.
	 *
	 * @param string $url Найденный URL.
	 *
	 * @return string
	 */
	private function cleanup_attachment_url( $url ) {
		$url = str_replace( '\\/', '/', $url );

		return rtrim( $url, "\\\"'<>),;" );
	}

	/**
	 * Обрабатывает загрузку JSON-файла и создаёт сессию пакетного импорта.
	 *
	 * Хук: admin_post_cpt_migrator_import.
	 */
	public function handle_import() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die(
				esc_html__( 'Недостаточно прав для импорта.', 'cpt-migrator' ),
				'',
				array( 'response' => 403 )
			);
		}

		check_admin_referer( self::IMPORT_ACTION, self::IMPORT_NONCE_NAME );

		$dry_run = isset( $_POST['cpt_migrator_dry_run'] );

		$duplicate_action = isset( $_POST['cpt_migrator_duplicate_action'] )
			? sanitize_key( wp_unslash( $_POST['cpt_migrator_duplicate_action'] ) )
			: 'skip';

		if ( ! in_array( $duplicate_action, self::DUPLICATE_ACTIONS, true ) ) {
			$duplicate_action = 'skip';
		}

		$data = $this->read_import_file();

		if ( is_wp_error( $data ) ) {
			$this->log_event(
				'import_failed',
				array(
					'source'  => __( 'Вручную', 'cpt-migrator' ),
					'message' => $data->get_error_message(),
				)
			);

			$this->store_import_error( $data, $dry_run );
			$this->redirect_to_import_page();
		}

		$detected = $this->detect_source_post_types( $data );

		$this->remember_detected_post_types( $detected );

		$unmapped = $this->get_unmapped_source_post_types( $detected );

		if ( ! empty( $unmapped ) ) {
			$message = sprintf(
				/* translators: %s: comma separated post type slugs. */
				__( 'В файле найдены типы записей, для которых не настроено соответствие: %s. Укажите соответствие в настройках плагина и загрузите файл заново.', 'cpt-migrator' ),
				implode( ', ', $unmapped )
			);

			$this->log_event(
				'import_failed',
				array(
					'source'  => __( 'Вручную', 'cpt-migrator' ),
					'message' => $message,
				)
			);

			$this->store_import_error( new WP_Error( 'cpt_migrator_unmapped_post_types', $message ), $dry_run );
			$this->redirect_to_page(
				array(
					'tab'  => 'settings',
					'note' => 'unmapped',
				)
			);
		}

		$session_id = $this->create_import_session( $data, $dry_run, $duplicate_action );

		if ( is_wp_error( $session_id ) ) {
			$this->log_event(
				'import_failed',
				array(
					'source'  => __( 'Вручную', 'cpt-migrator' ),
					'message' => $session_id->get_error_message(),
				)
			);

			$this->store_import_error( $session_id, $dry_run );
			$this->redirect_to_import_page();
		}

		$this->log_event(
			'import',
			array(
				'source' => __( 'Вручную', 'cpt-migrator' ),
				'posts'  => isset( $data['posts_count'] ) ? (int) $data['posts_count'] : count( $data['posts'] ),
			)
		);

		wp_safe_redirect(
			$this->get_import_page_url(
				array(
					'import_id' => $session_id,
				)
			)
		);
		exit;
	}

	/**
	 * Обрабатывает один AJAX-пакет импорта.
	 *
	 * Хук: wp_ajax_cpt_migrator_import_batch.
	 */
	public function handle_import_batch() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Недостаточно прав для импорта.', 'cpt-migrator' ) ),
				403
			);
		}

		check_ajax_referer( self::IMPORT_BATCH_ACTION, 'nonce' );

		if ( function_exists( 'set_time_limit' ) ) {
			@set_time_limit( 180 );
		}

		$session_id = isset( $_POST['import_id'] ) && is_string( $_POST['import_id'] )
			? sanitize_key( wp_unslash( $_POST['import_id'] ) )
			: '';

		$state = $this->get_import_session( $session_id );

		if ( null === $state ) {
			wp_send_json_error(
				array( 'message' => __( 'Сессия импорта не найдена или истекла. Загрузите файл заново.', 'cpt-migrator' ) ),
				404
			);
		}

		$lock_key = $this->get_import_session_key( $session_id ) . '_lock';
		$lock_val = wp_generate_uuid4();

		if ( false !== get_transient( $lock_key ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Импорт уже выполняется в другой вкладке. Дождитесь завершения.', 'cpt-migrator' ) ),
				409
			);
		}

		// TTL заведомо больше максимального времени обработки пакета.
		if ( ! set_transient( $lock_key, $lock_val, 15 * MINUTE_IN_SECONDS ) && false === get_transient( $lock_key ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Не удалось заблокировать сессию импорта. Попробуйте ещё раз.', 'cpt-migrator' ) ),
				409
			);
		}

		register_shutdown_function(
			static function () use ( $lock_key ) {
				delete_transient( $lock_key );
			}
		);

		$state       = $this->process_import_batch( $session_id, $state, $this->get_import_batch_size() );
		$total       = (int) $state['total'];
		$processed   = (int) $state['offset'];
		$is_finished = $processed >= $total;

		if ( $is_finished ) {
			$result = $this->finalize_import_state( $state );

			$this->store_import_result( $result );
			$this->delete_import_session( $session_id );

			$summary = isset( $result['summary'] ) ? $result['summary'] : array();

			$this->log_event(
				'import',
				array(
					'source'  => __( 'Вручную', 'cpt-migrator' ),
					'created' => isset( $summary['created'] ) ? (int) $summary['created'] : 0,
					'updated' => isset( $summary['updated'] ) ? (int) $summary['updated'] : 0,
					'skipped' => isset( $summary['skipped'] ) ? (int) $summary['skipped'] : 0,
					'errors'  => isset( $summary['errors'] ) ? (int) $summary['errors'] : 0,
					'images'  => isset( $result['image_errors'] ) ? count( $result['image_errors'] ) : 0,
				)
			);

			wp_send_json_success(
				array(
					'done'      => true,
					'processed' => $processed,
					'total'     => $total,
					'percent'   => 100,
					'redirect'  => $this->get_import_page_url(),
				)
			);
		}

		if ( ! $this->save_import_session( $session_id, $state ) ) {
			wp_send_json_error(
				array( 'message' => __( 'Не удалось сохранить состояние импорта. Попробуйте продолжить.', 'cpt-migrator' ) ),
				500
			);
		}

		wp_send_json_success(
			array(
				'done'      => false,
				'processed' => $processed,
				'total'     => $total,
				'percent'   => $total > 0 ? (int) floor( ( $processed / $total ) * 100 ) : 100,
			)
		);
	}

	/**
	 * Сохраняет результат ошибки загрузки/создания сессии импорта.
	 *
	 * @param WP_Error $error   Ошибка.
	 * @param bool     $dry_run Пробный запуск.
	 */
	private function store_import_error( $error, $dry_run ) {
		$result = array(
			'dry_run'      => (bool) $dry_run,
			'rows'         => array(),
			'warnings'     => array(),
			'errors'       => array( $error->get_error_message() ),
			'image_errors' => array(),
			'summary'      => array(
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'errors'  => 1,
			),
		);

		$this->store_import_result( $result );
	}

	/**
	 * Создаёт сессию импорта с исходными записями и настройками.
	 *
	 * @param array  $data             Данные JSON.
	 * @param bool   $dry_run          Пробный запуск.
	 * @param string $duplicate_action Действие при дубликате.
	 *
	 * @return string|WP_Error
	 */
	private function create_import_session( $data, $dry_run, $duplicate_action ) {
		$items    = array_values( $data['posts'] );
		$warnings = array();

		if ( isset( $data['posts_count'] ) && (int) $data['posts_count'] !== count( $items ) ) {
			$warnings[] = sprintf(
				/* translators: 1: declared count, 2: actual count. */
				__( 'В файле указано %1$d записей, фактически найдено %2$d.', 'cpt-migrator' ),
				(int) $data['posts_count'],
				count( $items )
			);
		}

		$base_url = isset( $data['site_url'] ) && is_string( $data['site_url'] )
			? esc_url_raw( $data['site_url'] )
			: home_url( '/' );

		$session_id = wp_generate_uuid4();

		$items_payload = '';

		foreach ( $items as $item ) {
			$encoded = wp_json_encode( $item );

			if ( false === $encoded ) {
				return new WP_Error( 'cpt_migrator_session_failed', __( 'Не удалось создать сессию импорта.', 'cpt-migrator' ) );
			}

			$items_payload .= $encoded . "\n";
		}

		if ( ! $this->write_import_file( $this->get_import_items_path( $session_id ), $items_payload ) ) {
			return new WP_Error( 'cpt_migrator_session_failed', __( 'Не удалось создать сессию импорта.', 'cpt-migrator' ) );
		}

		$state = array(
			'total'            => count( $items ),
			'offset'           => 0,
			'items_offset'     => 0,
			'dry_run'          => (bool) $dry_run,
			'duplicate_action' => $duplicate_action,
			'base_url'         => $base_url,
			'rows'             => array(),
			'counts'           => array(
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'errors'  => 0,
			),
			'warnings'         => $warnings,
			'errors'           => array(),
			'image_errors'     => array(),
			'used_names'       => array(),
			'term_map'         => $this->collect_term_map( $items ),
		);

		$state_payload = wp_json_encode( $state );

		if ( false === $state_payload || ! $this->write_import_file( $this->get_import_state_path( $session_id ), $state_payload ) ) {
			return new WP_Error( 'cpt_migrator_session_failed', __( 'Не удалось создать сессию импорта.', 'cpt-migrator' ) );
		}

		$this->touch_import_session( $session_id, count( $items ) );

		// Проверяем фактическое наличие метки, а не результат set_transient():
		// update_option() возвращает false, если значение не изменилось.
		if ( false === get_transient( $this->get_import_session_key( $session_id ) ) ) {
			return new WP_Error( 'cpt_migrator_session_failed', __( 'Не удалось создать сессию импорта.', 'cpt-migrator' ) );
		}

		return $session_id;
	}
	/**
	 * Возвращает каталог для хранения данных сессии импорта.
	 *
	 * Каталог всегда находится вне публичной части сайта: сначала
	 * используется системная временная папка, и только если она недоступна
	 * для записи — защищённая папка внутри uploads с файлами запрета доступа.
	 *
	 * @return string
	 */
	private function get_import_storage_dir() {
		static $resolved = '';

		if ( '' !== $resolved ) {
			return $resolved;
		}

		$candidates = array();

		$temp = get_temp_dir();

		if ( is_dir( $temp ) ) {
			$candidates[] = trailingslashit( $temp ) . 'cpt-migrator-import';
		}

		$uploads = wp_upload_dir();

		if ( ! empty( $uploads['basedir'] ) ) {
			$candidates[] = trailingslashit( $uploads['basedir'] ) . 'cpt-migrator-tmp';
		}

		$candidates[] = trailingslashit( WP_CONTENT_DIR ) . 'cpt-migrator-tmp';

		foreach ( $candidates as $dir ) {
			if ( is_link( $dir ) ) {
				continue;
			}

			if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
				continue;
			}

			if ( ! is_writable( $dir ) ) {
				continue;
			}

			$resolved = $dir;
			break;
		}

		if ( '' === $resolved ) {
			$resolved = $candidates[0];
		}

		return $resolved;
	}

	/**
	 * Возвращает путь к файлу с исходными записями сессии.
	 *
	 * @param string $session_id ID сессии.
	 *
	 * @return string
	 */
	private function get_import_items_path( $session_id ) {
		return $this->get_import_storage_dir() . DIRECTORY_SEPARATOR . sanitize_file_name( $session_id ) . '.items.ndjson';
	}

	/**
	 * Возвращает путь к файлу с состоянием сессии.
	 *
	 * @param string $session_id ID сессии.
	 *
	 * @return string
	 */
	private function get_import_state_path( $session_id ) {
		return $this->get_import_storage_dir() . DIRECTORY_SEPARATOR . sanitize_file_name( $session_id ) . '.state.json';
	}

	/**
	 * Записывает файл сессии импорта.
	 *
	 * Файлы защиты (.htaccess, index.php) создаются ДО записи данных,
	 * чтобы содержимое никогда не оказывалось в публичной папке без
	 * запрета прямого доступа.
	 *
	 * @param string $path    Путь к файлу.
	 * @param string $content Содержимое.
	 *
	 * @return bool
	 */
	private function write_import_file( $path, $content ) {
		$dir = dirname( $path );

		// Отказ от работы через симлинк: иначе возможна запись в произвольный каталог.
		if ( is_link( $dir ) ) {
			$this->log_storage_error( 'Каталог хранения является символической ссылкой: ' . $dir );
			return false;
		}

		if ( ! is_dir( $dir ) && ! wp_mkdir_p( $dir ) ) {
			$this->log_storage_error( 'Не удалось создать каталог хранения: ' . $dir );
			return false;
		}

		if ( ! is_writable( $dir ) ) {
			$this->log_storage_error( 'Каталог хранения недоступен для записи: ' . $dir );
			return false;
		}

		@chmod( $dir, 0750 );

		if ( ! $this->protect_storage_dir( $dir ) ) {
			$this->log_storage_error( 'Не удалось создать файлы защиты каталога: ' . $dir );
			return false;
		}

		$written = file_put_contents( $path, $content, LOCK_EX );

		if ( false === $written ) {
			$this->log_storage_error( 'Не удалось записать файл сессии: ' . $path );
			return false;
		}

		@chmod( $path, 0640 );

		return true;
	}

	/**
	 * Пишет диагностическое сообщение в лог PHP.
	 *
	 * @param string $message Сообщение.
	 */
	private function log_storage_error( $message ) {
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( '[CPT Migrator] ' . $message );
		}
	}

	/**
	 * Создаёт файлы защиты каталога от прямого доступа из браузера.
	 *
	 * @param string $dir Путь к каталогу.
	 *
	 * @return bool
	 */
	private function protect_storage_dir( $dir ) {
		$htaccess = $dir . DIRECTORY_SEPARATOR . '.htaccess';

		if ( ! file_exists( $htaccess ) ) {
			$rules = "# Запрет прямого доступа к служебным файлам.\n"
				. "<IfModule mod_authz_core.c>\n"
				. "\tRequire all denied\n"
				. "</IfModule>\n"
				. "<IfModule !mod_authz_core.c>\n"
				. "\tOrder allow,deny\n"
				. "\tDeny from all\n"
				. "</IfModule>\n";

			if ( false === file_put_contents( $htaccess, $rules, LOCK_EX ) ) {
				$this->log_storage_error( 'Не удалось записать .htaccess: ' . $htaccess );
				return false;
			}
		}

		$index = $dir . DIRECTORY_SEPARATOR . 'index.php';

		if ( ! file_exists( $index ) && false === file_put_contents( $index, '', LOCK_EX ) ) {
			$this->log_storage_error( 'Не удалось записать index.php: ' . $index );
			return false;
		}

		return true;
	}

	/**
	 * Обрабатывает очередной пакет записей.
	 *
	 * @param string $session_id ID сессии.
	 * @param array  $state      Состояние сессии.
	 * @param int    $batch_size Размер пакета.
	 *
	 * @return array
	 */
	private function process_import_batch( $session_id, $state, $batch_size ) {
		$processed = 0;

		$warnings     = &$state['warnings'];
		$errors       = &$state['errors'];
		$image_errors = &$state['image_errors'];

		$items_path = $this->get_import_items_path( $session_id );
		$handle     = @fopen( $items_path, 'r' );

		if ( false === $handle ) {
			$message    = __( 'Не удалось прочитать данные сессии импорта.', 'cpt-migrator' );
			$state['rows'][] = $this->build_import_row( array(), 'error', 0, $message );
			$errors[]   = $message;

			return $state;
		}

		if ( (int) $state['items_offset'] > 0 ) {
			fseek( $handle, (int) $state['items_offset'] );
		}

		while ( $processed < $batch_size && (int) $state['offset'] < (int) $state['total'] ) {
			$line = fgets( $handle );

			if ( false === $line || '' === trim( $line ) ) {
				break;
			}

			$item = json_decode( $line, true );

			if ( ! is_array( $item ) ) {
				$item = array();
			}

			$state['items_offset'] = (int) ftell( $handle );

			$index    = (int) $state['offset'];
			$prepared = $this->prepare_import_item( $item, $index );

			if ( is_wp_error( $prepared ) ) {
				$message    = $prepared->get_error_message();
				$row_index  = $this->add_import_row( $state, $this->build_import_row( $item, 'error', 0, $message ) );
				$errors[]   = $message;
				$this->count_import_action( $state, 'error' );

				++$state['offset'];
				++$processed;
				continue;
			}

			$prepared['taxonomies'] = $this->validate_import_taxonomies( $prepared, $warnings );

			$existing    = $this->find_existing_post( $prepared['post_type'], $prepared['post_name'] );
			$action      = 'create';
			$target_id   = 0;
			$result_name = $prepared['post_name'];

			if ( $existing ) {
				switch ( $state['duplicate_action'] ) {
					case 'update':
						$action    = 'update';
						$target_id = (int) $existing->ID;
						break;

					case 'suffix':
						break;

					case 'skip':
					default:
						$action    = 'skip';
						$target_id = (int) $existing->ID;
						break;
				}
			}

			if ( 'create' === $action ) {
				$reserved_names = isset( $state['used_names'][ $prepared['post_type'] ] )
					? $state['used_names'][ $prepared['post_type'] ]
					: array();

				$result_name = $this->get_unique_post_name( $prepared['post_type'], $result_name, $reserved_names );

				if ( ! isset( $state['used_names'][ $prepared['post_type'] ] ) ) {
					$state['used_names'][ $prepared['post_type'] ] = array();
				}

				$state['used_names'][ $prepared['post_type'] ][ $result_name ] = true;
			}

			$prepared['post_name'] = $result_name;
			$row_index             = $this->add_import_row(
				$state,
				$this->build_import_row(
					$prepared,
					$action,
					$target_id,
					$this->get_import_row_message( $action, $target_id, $state['dry_run'] )
				)
			);

			if ( ! $state['dry_run'] && in_array( $action, array( 'create', 'update' ), true ) ) {
				$outcome = $this->execute_import_item(
					$prepared,
					$action,
					$target_id,
					$state['term_map'],
					$warnings,
					$image_errors,
					$state['base_url']
				);

				if ( is_wp_error( $outcome ) ) {
					$message = $outcome->get_error_message();
					$errors[] = $message;

					if ( $row_index >= 0 ) {
						$state['rows'][ $row_index ]['action']  = 'error';
						$state['rows'][ $row_index ]['message'] = $message;
					}

					$this->count_import_action( $state, 'error' );
				} else {
					if ( $row_index >= 0 ) {
						$state['rows'][ $row_index ]['target_id'] = (int) $outcome['post_id'];
						$state['rows'][ $row_index ]['post_name'] = $outcome['post_name'];
						$state['rows'][ $row_index ]['message']   = $outcome['message'];
					}

					$this->count_import_action( $state, $action );
				}
			} else {
				$this->count_import_action( $state, $action );
			}

			++$state['offset'];
			++$processed;
		}

		fclose( $handle );

		return $state;
	}

	/**
	 * Добавляет строку в отчёт, ограничивая их количество.
	 *
	 * @param array $state Состояние сессии.
	 * @param array $row   Строка отчёта.
	 *
	 * @return int Индекс строки или -1, если строка не была сохранена.
	 */
	private function add_import_row( &$state, $row ) {
		if ( count( $state['rows'] ) >= self::MAX_IMPORT_REPORT_ROWS ) {
			return -1;
		}

		$state['rows'][] = $row;

		return count( $state['rows'] ) - 1;
	}

	/**
	 * Увеличивает счётчик действия в итогах импорта.
	 *
	 * Счётчики ведутся отдельно от строк отчёта, поэтому ограничение
	 * количества строк не влияет на точность итогов.
	 *
	 * @param array  $state  Состояние сессии.
	 * @param string $action Действие.
	 */
	private function count_import_action( &$state, $action ) {
		if ( ! isset( $state['counts'] ) || ! is_array( $state['counts'] ) ) {
			$state['counts'] = array(
				'created' => 0,
				'updated' => 0,
				'skipped' => 0,
				'errors'  => 0,
			);
		}

		switch ( $action ) {
			case 'create':
				++$state['counts']['created'];
				break;

			case 'update':
				++$state['counts']['updated'];
				break;

			case 'skip':
				++$state['counts']['skipped'];
				break;

			default:
				++$state['counts']['errors'];
				break;
		}
	}

	/**
	 * Формирует итоговый отчёт из состояния сессии.
	 *
	 * @param array $state Состояние сессии.
	 *
	 * @return array
	 */
	private function finalize_import_state( $state ) {
		$summary = isset( $state['counts'] ) && is_array( $state['counts'] )
			? array_merge( $this->count_import_rows( $state['rows'] ), $state['counts'] )
			: $this->count_import_rows( $state['rows'] );

		return array(
			'dry_run'      => (bool) $state['dry_run'],
			'rows'         => $state['rows'],
			'rows_truncated' => count( $state['rows'] ) >= self::MAX_IMPORT_REPORT_ROWS,
			'warnings'     => array_values( array_unique( $state['warnings'] ) ),
			'errors'       => $state['errors'],
			'image_errors' => array_values( array_unique( $state['image_errors'] ) ),
			'summary'      => $summary,
		);
	}

	/**
	 * Возвращает размер пакета импорта.
	 *
	 * @return int
	 */
	private function get_import_batch_size() {
		$size = (int) apply_filters( 'cpt_migrator_import_batch_size', self::IMPORT_BATCH_SIZE );

		return max( 1, min( 50, $size ) );
	}

	/**
	 * Возвращает URL страницы импорта.
	 *
	 * @param array $args Дополнительные query-параметры.
	 *
	 * @return string
	 */
	private function get_import_page_url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::PAGE_SLUG,
					'tab'  => 'import',
				),
				$args
			),
			admin_url( 'tools.php' )
		);
	}

	/**
	 * Редиректит на страницу импорта и завершает запрос.
	 *
	 * @param array $args Дополнительные query-параметры.
	 */
	private function redirect_to_import_page( $args = array() ) {
		wp_safe_redirect( $this->get_import_page_url( $args ) );
		exit;
	}

	/**
	 * Возвращает URL страницы плагина с указанной вкладкой.
	 *
	 * @param array $args Дополнительные query-параметры.
	 *
	 * @return string
	 */
	private function get_plugin_page_url( $args = array() ) {
		return add_query_arg(
			array_merge(
				array(
					'page' => self::PAGE_SLUG,
					'tab'  => 'export',
				),
				$args
			),
			admin_url( 'tools.php' )
		);
	}

	/**
	 * Редиректит на страницу плагина и завершает запрос.
	 *
	 * @param array $args Дополнительные query-параметры.
	 */
	private function redirect_to_page( $args = array() ) {
		wp_safe_redirect( $this->get_plugin_page_url( $args ) );
		exit;
	}

	/**
	 * Возвращает import_id из запроса.
	 *
	 * @return string
	 */
	private function get_requested_import_id() {
		if ( ! isset( $_GET['import_id'] ) || ! is_string( $_GET['import_id'] ) ) {
			return '';
		}

		return sanitize_key( wp_unslash( $_GET['import_id'] ) );
	}

	/**
	 * Возвращает ключ transient сессии импорта.
	 *
	 * @param string $session_id ID сессии.
	 *
	 * @return string
	 */
	private function get_import_session_key( $session_id ) {
		return 'cpt_migrator_import_session_' . get_current_user_id() . '_' . $session_id;
	}

	/**
	 * Возвращает состояние сессии импорта.
	 *
	 * @param string $session_id ID сессии.
	 *
	 * @return array|null
	 */
	private function get_import_session( $session_id ) {
		if ( '' === $session_id ) {
			return null;
		}

		$meta = get_transient( $this->get_import_session_key( $session_id ) );

		if ( ! is_array( $meta ) ) {
			return null;
		}

		$path = $this->get_import_state_path( $session_id );

		if ( ! is_readable( $path ) ) {
			return null;
		}

		$raw = file_get_contents( $path );

		if ( false === $raw || '' === $raw ) {
			return null;
		}

		$state = json_decode( $raw, true );

		if ( ! is_array( $state ) ) {
			return null;
		}

		$state['total'] = isset( $meta['total'] )
			? (int) $meta['total']
			: ( isset( $state['total'] ) ? (int) $state['total'] : 0 );

		return $state;
	}

	/**
	 * Сохраняет состояние сессии импорта.
	 *
	 * Основное хранилище состояния — файл. Transient используется только как
	 * метка времени жизни сессии, поэтому его значение должно меняться при
	 * каждом сохранении: иначе update_option() вернёт false, и импорт был бы
	 * ошибочно прерван после первого же пакета.
	 *
	 * @param string $session_id ID сессии.
	 * @param array  $state      Состояние.
	 *
	 * @return bool
	 */
	private function save_import_session( $session_id, $state ) {
		$payload = wp_json_encode( $state );

		if ( false === $payload ) {
			return false;
		}

		if ( ! $this->write_import_file( $this->get_import_state_path( $session_id ), $payload ) ) {
			return false;
		}

		$this->touch_import_session( $session_id, (int) $state['total'] );

		return true;
	}

	/**
	 * Обновляет метку времени жизни сессии импорта.
	 *
	 * @param string $session_id ID сессии.
	 * @param int    $total      Всего записей.
	 */
	private function touch_import_session( $session_id, $total ) {
		set_transient(
			$this->get_import_session_key( $session_id ),
			array(
				'total'   => (int) $total,
				'updated' => time(),
			),
			self::IMPORT_SESSION_TTL
		);
	}

	/**
	 * Удаляет сессию импорта.
	 *
	 * @param string $session_id ID сессии.
	 */
	private function delete_import_session( $session_id ) {
		delete_transient( $this->get_import_session_key( $session_id ) );

		$items_path = $this->get_import_items_path( $session_id );
		$state_path = $this->get_import_state_path( $session_id );

		if ( file_exists( $items_path ) ) {
			@unlink( $items_path );
		}

		if ( file_exists( $state_path ) ) {
			@unlink( $state_path );
		}
	}

	/**
	 * Ключ transient с результатом импорта для текущего пользователя.
	 *
	 * @return string
	 */
	private function get_import_result_key() {
		return 'cpt_migrator_import_result_' . get_current_user_id();
	}

	/**
	 * Возвращает путь к файлу с результатом импорта.
	 *
	 * @return string
	 */
	private function get_import_result_path() {
		$meta = get_transient( $this->get_import_result_key() );
		$token = is_array( $meta ) && ! empty( $meta['token'] ) ? sanitize_file_name( (string) $meta['token'] ) : '';

		if ( '' === $token ) {
			return '';
		}

		return $this->get_import_storage_dir() . DIRECTORY_SEPARATOR . 'result-' . $token . '.json';
	}

	/**
	 * Сохраняет отчёт об импорте.
	 *
	 * @param array $result Отчёт.
	 */
	private function store_import_result( $result ) {
		$this->cleanup_stale_result_files();

		$payload = wp_json_encode( $result );
		$token   = wp_generate_password( 24, false, false );

		if ( false !== $payload && '' !== $token ) {
			$this->write_import_file( $this->get_import_result_path_for_token( $token ), $payload );
		}

		set_transient(
			$this->get_import_result_key(),
			array(
				'stored' => true,
				'token'  => $token,
			),
			HOUR_IN_SECONDS
		);
	}

	/**
	 * Удаляет устаревшие файлы отчётов, которые никто не открыл.
	 *
	 * @return void
	 */
	private function cleanup_stale_result_files() {
		$dir = $this->get_import_storage_dir();

		if ( ! is_dir( $dir ) || is_link( $dir ) ) {
			return;
		}

		$files = glob( $dir . DIRECTORY_SEPARATOR . 'result-*.json' );

		if ( ! is_array( $files ) ) {
			return;
		}

		$lifetime = 2 * DAY_IN_SECONDS;

		foreach ( $files as $file ) {
			$modified = @filemtime( $file );

			if ( false === $modified || ( time() - $modified ) < $lifetime ) {
				continue;
			}

			@unlink( $file );
		}
	}

	/**
	 * Возвращает путь к файлу результата по токену.
	 *
	 * @param string $token Токен.
	 *
	 * @return string
	 */
	private function get_import_result_path_for_token( $token ) {
		return $this->get_import_storage_dir() . DIRECTORY_SEPARATOR . 'result-' . sanitize_file_name( $token ) . '.json';
	}

	/**
	 * Читает отчёт об импорте.
	 *
	 * @return array|null
	 */
	private function get_import_result() {
		$meta = get_transient( $this->get_import_result_key() );

		if ( ! is_array( $meta ) ) {
			return null;
		}

		$path = $this->get_import_result_path();

		if ( '' === $path || ! is_readable( $path ) ) {
			return null;
		}

		$raw = file_get_contents( $path );

		if ( false === $raw || '' === $raw ) {
			return null;
		}

		$result = json_decode( $raw, true );

		return is_array( $result ) ? $result : null;
	}

	/**
	 * Удаляет сохранённый отчёт об импорте.
	 */
	private function delete_import_result() {
		$path = $this->get_import_result_path();

		delete_transient( $this->get_import_result_key() );

		if ( '' !== $path && file_exists( $path ) ) {
			@unlink( $path );
		}
	}

	/**
	 * Проверяет загруженный файл и возвращает декодированный JSON.
	 *
	 * @return array|WP_Error
	 */
	private function read_import_file() {
		if ( empty( $_FILES['cpt_migrator_import_file'] ) || ! is_array( $_FILES['cpt_migrator_import_file'] ) ) {
			return new WP_Error( 'cpt_migrator_missing_file', __( 'Файл не был загружен.', 'cpt-migrator' ) );
		}

		$file = $_FILES['cpt_migrator_import_file'];

		if ( ! isset( $file['error'], $file['tmp_name'], $file['name'] ) || ! is_string( $file['tmp_name'] ) || ! is_string( $file['name'] ) ) {
			return new WP_Error( 'cpt_migrator_invalid_upload', __( 'Некорректные данные загруженного файла.', 'cpt-migrator' ) );
		}

		$error = (int) $file['error'];

		if ( UPLOAD_ERR_OK !== $error ) {
			return new WP_Error( 'cpt_migrator_upload_error', $this->get_upload_error_message( $error ) );
		}

		if ( ! is_uploaded_file( $file['tmp_name'] ) ) {
			return new WP_Error( 'cpt_migrator_not_uploaded', __( 'Файл не является загруженным файлом.', 'cpt-migrator' ) );
		}

		$extension = strtolower( pathinfo( (string) $file['name'], PATHINFO_EXTENSION ) );

		if ( 'json' !== $extension ) {
			return new WP_Error( 'cpt_migrator_invalid_extension', __( 'Допускаются только файлы с расширением .json.', 'cpt-migrator' ) );
		}

		$size = filesize( $file['tmp_name'] );

		if ( false === $size ) {
			return new WP_Error( 'cpt_migrator_size_error', __( 'Не удалось определить размер файла.', 'cpt-migrator' ) );
		}

		if ( 0 === $size ) {
			return new WP_Error( 'cpt_migrator_empty_file', __( 'Файл пуст.', 'cpt-migrator' ) );
		}

		if ( $size > self::MAX_IMPORT_FILE_SIZE ) {
			return new WP_Error(
				'cpt_migrator_file_too_large',
				sprintf(
					/* translators: %s: maximum file size. */
					__( 'Файл слишком большой. Максимальный размер: %s.', 'cpt-migrator' ),
					size_format( self::MAX_IMPORT_FILE_SIZE )
				)
			);
		}

		$mime = $this->detect_upload_mime( $file );

		if ( '' === $mime ) {
			return new WP_Error( 'cpt_migrator_unknown_mime', __( 'Не удалось определить MIME-тип файла.', 'cpt-migrator' ) );
		}

		$allowed_mimes = array( 'application/json', 'text/json', 'text/plain', 'application/octet-stream' );

		if ( ! in_array( $mime, $allowed_mimes, true ) ) {
			return new WP_Error(
				'cpt_migrator_invalid_mime',
				sprintf(
					/* translators: %s: detected MIME type. */
					__( 'Недопустимый MIME-тип «%s». Загрузите JSON-файл.', 'cpt-migrator' ),
					$mime
				)
			);
		}

		$raw = file_get_contents( $file['tmp_name'] );

		if ( false === $raw ) {
			return new WP_Error( 'cpt_migrator_read_error', __( 'Не удалось прочитать загруженный файл.', 'cpt-migrator' ) );
		}

		if ( 0 === strpos( $raw, "\xEF\xBB\xBF" ) ) {
			$raw = substr( $raw, 3 );
		}

		$data = json_decode( $raw, true );

		if ( JSON_ERROR_NONE !== json_last_error() ) {
			return new WP_Error(
				'cpt_migrator_invalid_json',
				sprintf(
					/* translators: %s: JSON parser error. */
					__( 'Не удалось разобрать JSON: %s', 'cpt-migrator' ),
					json_last_error_msg()
				)
			);
		}

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'cpt_migrator_invalid_structure', __( 'Корневой элемент JSON должен быть объектом.', 'cpt-migrator' ) );
		}

		if ( ! isset( $data['format_version'] ) || ! is_scalar( $data['format_version'] ) ) {
			return new WP_Error( 'cpt_migrator_missing_version', __( 'В JSON отсутствует поле format_version.', 'cpt-migrator' ) );
		}

		$version = (string) $data['format_version'];

		if ( ! in_array( $version, self::SUPPORTED_FORMAT_VERSIONS, true ) ) {
			return new WP_Error(
				'cpt_migrator_unsupported_version',
				sprintf(
					/* translators: 1: file format version, 2: supported versions. */
					__( 'Неподдерживаемая версия формата «%1$s». Поддерживаются версии: %2$s.', 'cpt-migrator' ),
					$version,
					implode( ', ', self::SUPPORTED_FORMAT_VERSIONS )
				)
			);
		}

		if ( ! isset( $data['posts'] ) || ! is_array( $data['posts'] ) ) {
			return new WP_Error( 'cpt_migrator_missing_posts', __( 'В JSON отсутствует массив posts.', 'cpt-migrator' ) );
		}

		return $data;
	}

	/**
	 * Возвращает понятное сообщение для кода ошибки загрузки PHP.
	 *
	 * @param int $error Код ошибки.
	 *
	 * @return string
	 */
	private function get_upload_error_message( $error ) {
		switch ( $error ) {
			case UPLOAD_ERR_INI_SIZE:
			case UPLOAD_ERR_FORM_SIZE:
				return __( 'Файл превышает максимально допустимый размер.', 'cpt-migrator' );

			case UPLOAD_ERR_PARTIAL:
				return __( 'Файл загружен не полностью.', 'cpt-migrator' );

			case UPLOAD_ERR_NO_FILE:
				return __( 'Файл не выбран.', 'cpt-migrator' );

			case UPLOAD_ERR_NO_TMP_DIR:
				return __( 'На сервере нет временной папки для загрузки.', 'cpt-migrator' );

			case UPLOAD_ERR_CANT_WRITE:
				return __( 'Не удалось записать файл на диск.', 'cpt-migrator' );

			case UPLOAD_ERR_EXTENSION:
				return __( 'Загрузка остановлена расширением PHP.', 'cpt-migrator' );

			default:
				return __( 'Не удалось загрузить файл.', 'cpt-migrator' );
		}
	}

	/**
	 * Определяет MIME-тип загруженного файла.
	 *
	 * @param array $file Элемент массива $_FILES.
	 *
	 * @return string
	 */
	private function detect_upload_mime( $file ) {
		$mime = '';

		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );

			if ( false !== $finfo ) {
				$mime = finfo_file( $finfo, $file['tmp_name'] );
				finfo_close( $finfo );
			}
		}

		if ( ! is_string( $mime ) || '' === $mime ) {
			if ( function_exists( 'mime_content_type' ) ) {
				$mime = mime_content_type( $file['tmp_name'] );
			}
		}

		if ( ! is_string( $mime ) || '' === $mime ) {
			$mime = isset( $file['type'] ) ? (string) $file['type'] : '';
		}

		$mime      = strtolower( trim( $mime ) );
		$semicolon = strpos( $mime, ';' );

		if ( false !== $semicolon ) {
			$mime = trim( substr( $mime, 0, $semicolon ) );
		}

		return $mime;
	}

	/**
	 * Проверяет и нормализует одну запись из JSON.
	 *
	 * @param mixed $item  Элемент массива posts.
	 * @param int   $index Индекс элемента.
	 *
	 * @return array|WP_Error
	 */
	private function prepare_import_item( $item, $index ) {
		if ( ! is_array( $item ) ) {
			return new WP_Error(
				'cpt_migrator_invalid_item',
				sprintf(
					/* translators: %d: item number. */
					__( 'Запись #%d: элемент JSON не является объектом.', 'cpt-migrator' ),
					$index + 1
				)
			);
		}

		$post_type = isset( $item['post_type'] ) && is_scalar( $item['post_type'] )
			? sanitize_key( (string) $item['post_type'] )
			: '';

		if ( '' === $post_type ) {
			return new WP_Error(
				'cpt_migrator_missing_post_type',
				sprintf(
					/* translators: %d: item number. */
					__( 'Запись #%d: не указан post_type.', 'cpt-migrator' ),
					$index + 1
				)
			);
		}

		$source_post_type = $post_type;

		// Тип из файла может называться иначе, чем на этом сайте.
		$post_type = $this->map_source_post_type( $source_post_type );

		if ( ! post_type_exists( $post_type ) ) {
			return new WP_Error(
				'cpt_migrator_unknown_post_type',
				sprintf(
					/* translators: 1: post type, 2: item number. */
					__( 'Запись #%2$d: тип записи «%1$s» не зарегистрирован на этом сайте.', 'cpt-migrator' ),
					$source_post_type,
					$index + 1
				)
			);
		}

		/**
		 * Белый список типов записей, разрешённых при импорте.
		 *
		 * Без проверки импорт мог бы создать записи служебных типов
		 * (wp_template, wp_block, wp_global_styles и т. п.).
		 *
		 * @param string[] $allowed Разрешённые типы записей.
		 */
		$allowed_post_types = $this->get_importable_post_types();

		if ( ! in_array( $post_type, $allowed_post_types, true ) ) {
			return new WP_Error(
				'cpt_migrator_post_type_not_allowed',
				sprintf(
					/* translators: 1: source post type, 2: item number. */
					__( 'Запись #%2$d: тип записи «%1$s» не перенесён. Укажите его соответствие в настройках плагина.', 'cpt-migrator' ),
					$source_post_type,
					$index + 1
				)
			);
		}

		$post_title = isset( $item['post_title'] ) && is_scalar( $item['post_title'] )
			? (string) $item['post_title']
			: '';

		$post_name = isset( $item['post_name'] ) && is_scalar( $item['post_name'] )
			? trim( (string) $item['post_name'] )
			: '';

		if ( '' === $post_name ) {
			$post_name = sanitize_title( $post_title );
		}

		if ( '' === $post_name ) {
			$post_name = 'imported-' . ( $index + 1 );
		}

		$post_status = isset( $item['post_status'] ) && is_scalar( $item['post_status'] )
			? sanitize_key( (string) $item['post_status'] )
			: 'draft';

		if ( ! in_array( $post_status, self::IMPORT_POST_STATUSES, true ) ) {
			$post_status = 'draft';
		}

		return array(
			'ID'               => isset( $item['ID'] ) && is_numeric( $item['ID'] ) ? (int) $item['ID'] : 0,
			'post_type'        => $post_type,
			'post_title'       => $post_title,
			'post_name'        => $post_name,
			'post_content'     => $this->sanitize_imported_content( isset( $item['post_content'] ) && is_scalar( $item['post_content'] ) ? (string) $item['post_content'] : '' ),
			'post_excerpt'     => $this->sanitize_imported_content( isset( $item['post_excerpt'] ) && is_scalar( $item['post_excerpt'] ) ? (string) $item['post_excerpt'] : '' ),
			'post_status'      => $post_status,
			'post_date'        => isset( $item['post_date'] ) && is_scalar( $item['post_date'] ) ? (string) $item['post_date'] : '',
			'post_date_gmt'    => isset( $item['post_date_gmt'] ) && is_scalar( $item['post_date_gmt'] ) ? (string) $item['post_date_gmt'] : '',
			'menu_order'       => isset( $item['menu_order'] ) && is_numeric( $item['menu_order'] ) ? (int) $item['menu_order'] : 0,
			'meta'             => isset( $item['meta'] ) && is_array( $item['meta'] ) ? $item['meta'] : array(),
			'taxonomies'       => isset( $item['taxonomies'] ) && is_array( $item['taxonomies'] ) ? $item['taxonomies'] : array(),
			'thumbnail'        => isset( $item['thumbnail'] ) && is_array( $item['thumbnail'] ) ? $item['thumbnail'] : array(),
			'attachments_used' => isset( $item['attachments_used'] ) && is_array( $item['attachments_used'] ) ? $item['attachments_used'] : array(),
		);
	}

	/**
	 * Очищает импортируемый HTML-контент.
	 *
	 * Пользователь с правом unfiltered_html сознательно может сохранять
	 * произвольную разметку, но для остальных пользователей применяется
	 * стандартная фильтрация WordPress, чтобы импорт не превращался
	 * в способ внедрить скрипты на сайт.
	 *
	 * @param string $content Исходный контент.
	 *
	 * @return string
	 */
	private function sanitize_imported_content( $content ) {
		if ( ! is_string( $content ) || '' === $content ) {
			return '';
		}

		if ( current_user_can( 'unfiltered_html' ) ) {
			return $content;
		}

		$allowed = wp_kses_allowed_html( 'post' );

		/**
		 * Позволяет изменить список разрешённых HTML-тегов при импорте.
		 *
		 * @param array  $allowed Разрешённые теги и атрибуты.
		 * @param string $content Исходный контент.
		 */
		$allowed = (array) apply_filters( 'cpt_migrator_import_allowed_html', $allowed, $content );

		return wp_kses( $content, $allowed );
	}

	/**
	 * Проверяет таксономии и термины перед импортом.
	 *
	 * @param array $prepared Подготовленная запись.
	 * @param array $warnings Предупреждения (передаются по ссылке).
	 *
	 * @return array Допустимые таксономии.
	 */
	private function validate_import_taxonomies( $prepared, &$warnings ) {
		$valid = array();

		foreach ( $prepared['taxonomies'] as $taxonomy => $terms ) {
			if ( ! is_string( $taxonomy ) || '' === $taxonomy || ! is_array( $terms ) ) {
				continue;
			}

			if ( ! taxonomy_exists( $taxonomy ) ) {
				$warnings[] = sprintf(
					/* translators: 1: taxonomy, 2: post title or slug. */
					__( 'Таксономия «%1$s» не зарегистрирована — термины для записи «%2$s» пропущены.', 'cpt-migrator' ),
					$taxonomy,
					'' !== $prepared['post_title'] ? $prepared['post_title'] : $prepared['post_name']
				);
				continue;
			}

			if ( ! is_object_in_taxonomy( $prepared['post_type'], $taxonomy ) ) {
				$warnings[] = sprintf(
					/* translators: 1: taxonomy, 2: post type. */
					__( 'Таксономия «%1$s» не привязана к типу записи «%2$s» — термины пропущены.', 'cpt-migrator' ),
					$taxonomy,
					$prepared['post_type']
				);
				continue;
			}

			$clean_terms = array();

			foreach ( $terms as $term ) {
				if ( ! is_array( $term ) ) {
					continue;
				}

				$slug = isset( $term['slug'] ) && is_scalar( $term['slug'] ) ? trim( (string) $term['slug'] ) : '';

				if ( '' === $slug ) {
					continue;
				}

				$clean_terms[] = array(
					'slug'        => $slug,
					'name'        => isset( $term['name'] ) && is_scalar( $term['name'] ) ? (string) $term['name'] : $slug,
					'parent_slug' => isset( $term['parent_slug'] ) && is_scalar( $term['parent_slug'] ) ? trim( (string) $term['parent_slug'] ) : '',
				);
			}

			if ( ! empty( $clean_terms ) ) {
				$valid[ $taxonomy ] = $clean_terms;
			}
		}

		return $valid;
	}

	/**
	 * Собирает данные всех терминов из файла для восстановления иерархии.
	 *
	 * @param array $items Массив записей из JSON.
	 *
	 * @return array
	 */
	private function collect_term_map( $items ) {
		$map = array();

		foreach ( $items as $item ) {
			if ( ! is_array( $item ) || empty( $item['taxonomies'] ) || ! is_array( $item['taxonomies'] ) ) {
				continue;
			}

			foreach ( $item['taxonomies'] as $taxonomy => $terms ) {
				if ( ! is_string( $taxonomy ) || '' === $taxonomy || ! is_array( $terms ) ) {
					continue;
				}

				foreach ( $terms as $term ) {
					if ( ! is_array( $term ) || empty( $term['slug'] ) || ! is_scalar( $term['slug'] ) ) {
						continue;
					}

					$slug = trim( (string) $term['slug'] );

					if ( '' === $slug || isset( $map[ $taxonomy ][ $slug ] ) ) {
						continue;
					}

					$map[ $taxonomy ][ $slug ] = array(
						'name'        => isset( $term['name'] ) && is_scalar( $term['name'] ) ? (string) $term['name'] : $slug,
						'parent_slug' => isset( $term['parent_slug'] ) && is_scalar( $term['parent_slug'] ) ? trim( (string) $term['parent_slug'] ) : '',
					);
				}
			}
		}

		return $map;
	}

	/**
	 * Ищет существующую запись по типу записи и slug.
	 *
	 * @param string $post_type Тип записи.
	 * @param string $post_name Slug.
	 *
	 * @return WP_Post|null
	 */
	private function find_existing_post( $post_type, $post_name ) {
		if ( '' === $post_name ) {
			return null;
		}

		$posts = get_posts(
			array(
				'post_type'        => $post_type,
				'name'             => $post_name,
				'post_status'      => self::IMPORT_POST_STATUSES,
				'posts_per_page'   => 1,
				'orderby'          => 'ID',
				'order'            => 'ASC',
				'no_found_rows'    => true,
				'suppress_filters' => true,
			)
		);

		return ! empty( $posts ) ? $posts[0] : null;
	}

	/**
	 * Подбирает свободный slug с суффиксом.
	 *
	 * @param string $post_type Тип записи.
	 * @param string $post_name Исходный slug.
	 * @param array  $reserved  Slug, уже занятые записями из текущего файла.
	 *
	 * @return string
	 */
	private function get_unique_post_name( $post_type, $post_name, $reserved = array() ) {
		$candidate = $post_name;
		$suffix    = 1;

		while ( $this->find_existing_post( $post_type, $candidate ) || isset( $reserved[ $candidate ] ) ) {
			++$suffix;
			$candidate = $post_name . '-' . $suffix;
		}

		return $candidate;
	}

	/**
	 * Формирует строку таблицы результата.
	 *
	 * @param mixed  $item      Запись из JSON или подготовленный массив.
	 * @param string $action    Действие.
	 * @param int    $target_id ID существующей записи.
	 * @param string $message   Пояснение.
	 *
	 * @return array
	 */
	private function build_import_row( $item, $action, $target_id, $message ) {
		$item = is_array( $item ) ? $item : array();

		return array(
			'source_id' => isset( $item['ID'] ) && is_numeric( $item['ID'] ) ? (int) $item['ID'] : 0,
			'post_type' => isset( $item['post_type'] ) && is_scalar( $item['post_type'] ) ? (string) $item['post_type'] : '',
			'title'     => isset( $item['post_title'] ) && is_scalar( $item['post_title'] ) ? (string) $item['post_title'] : '',
			'post_name' => isset( $item['post_name'] ) && is_scalar( $item['post_name'] ) ? (string) $item['post_name'] : '',
			'action'    => $action,
			'target_id' => (int) $target_id,
			'message'   => $message,
		);
	}

	/**
	 * Текст для строки результата.
	 *
	 * @param string $action    Действие.
	 * @param int    $target_id ID существующей записи.
	 * @param bool   $dry_run   Пробный запуск.
	 *
	 * @return string
	 */
	private function get_import_row_message( $action, $target_id, $dry_run ) {
		if ( 'skip' === $action ) {
			return $dry_run
				? sprintf(
					/* translators: %d: post ID. */
					__( 'Будет пропущена: уже существует запись #%d.', 'cpt-migrator' ),
					$target_id
				)
				: sprintf(
					/* translators: %d: post ID. */
					__( 'Пропущена: уже существует запись #%d.', 'cpt-migrator' ),
					$target_id
				);
		}

		if ( 'update' === $action ) {
			return $dry_run
				? sprintf(
					/* translators: %d: post ID. */
					__( 'Будет обновлена запись #%d.', 'cpt-migrator' ),
					$target_id
				)
				: __( 'Ожидает обновления.', 'cpt-migrator' );
		}

		if ( 'create' === $action ) {
			return $dry_run
				? __( 'Будет создана новая запись.', 'cpt-migrator' )
				: __( 'Ожидает создания.', 'cpt-migrator' );
		}

		return '';
	}

	/**
	 * Считает итоги по строкам результата.
	 *
	 * @param array $rows Строки результата.
	 *
	 * @return array
	 */
	private function count_import_rows( $rows ) {
		$summary = array(
			'created' => 0,
			'updated' => 0,
			'skipped' => 0,
			'errors'  => 0,
		);

		foreach ( $rows as $row ) {
			switch ( $row['action'] ) {
				case 'create':
					++$summary['created'];
					break;

				case 'update':
					++$summary['updated'];
					break;

				case 'skip':
					++$summary['skipped'];
					break;

				default:
					++$summary['errors'];
					break;
			}
		}

		return $summary;
	}

	/**
	 * Создаёт или обновляет запись и синхронизирует метаполя и таксономии.
	 *
	 * @param array  $prepared     Подготовленная запись.
	 * @param string $action       create или update.
	 * @param int    $target_id    ID существующей записи.
	 * @param array  $term_map     Данные терминов из файла.
	 * @param array  $warnings     Предупреждения (передаются по ссылке).
	 * @param array  $image_errors Ошибки изображений (передаются по ссылке).
	 * @param string $base_url     URL исходного сайта.
	 *
	 * @return array|WP_Error
	 */
	private function execute_import_item( $prepared, $action, $target_id, $term_map, &$warnings, &$image_errors, $base_url ) {
		$postarr = array(
			'post_type'    => $prepared['post_type'],
			'post_title'   => $prepared['post_title'],
			'post_name'    => $prepared['post_name'],
			'post_content' => $prepared['post_content'],
			'post_excerpt' => $prepared['post_excerpt'],
			'post_status'  => $prepared['post_status'],
			'menu_order'   => $prepared['menu_order'],
		);

		if ( '' !== $prepared['post_date'] ) {
			$postarr['post_date'] = $prepared['post_date'];
		}

		if ( '' !== $prepared['post_date_gmt'] ) {
			$postarr['post_date_gmt'] = $prepared['post_date_gmt'];
		}

		if ( 'update' === $action ) {
			$postarr['ID'] = $target_id;
			$post_id       = wp_update_post( wp_slash( $postarr ), true );
		} else {
			$post_id = wp_insert_post( wp_slash( $postarr ), true );
		}

		if ( is_wp_error( $post_id ) ) {
			return new WP_Error(
				'cpt_migrator_save_failed',
				sprintf(
					/* translators: 1: post title, 2: error message. */
					__( 'Не удалось сохранить запись «%1$s»: %2$s', 'cpt-migrator' ),
					$prepared['post_title'],
					$post_id->get_error_message()
				)
			);
		}

		if ( ! $post_id ) {
			return new WP_Error(
				'cpt_migrator_save_failed',
				sprintf(
					/* translators: %s: post title. */
					__( 'Не удалось сохранить запись «%s».', 'cpt-migrator' ),
					$prepared['post_title']
				)
			);
		}

		$post_id = (int) $post_id;
		$url_map = $this->import_post_images( $post_id, $prepared, $warnings, $image_errors, $base_url );

		if ( ! empty( $url_map ) ) {
			$content = $this->replace_urls_in_content( $prepared['post_content'], $url_map );
			$excerpt = $this->replace_urls_in_content( $prepared['post_excerpt'], $url_map );

			if ( $content !== $prepared['post_content'] || $excerpt !== $prepared['post_excerpt'] ) {
				$updated = wp_update_post(
					wp_slash(
						array(
							'ID'           => $post_id,
							'post_content' => $content,
							'post_excerpt' => $excerpt,
						)
					),
					true
				);

				if ( is_wp_error( $updated ) ) {
					$warnings[] = sprintf(
						/* translators: 1: post ID, 2: error message. */
						__( 'Не удалось заменить URL изображений в записи #%1$d: %2$s', 'cpt-migrator' ),
						$post_id,
						$updated->get_error_message()
					);
				}
			}
		}

		$this->import_post_meta( $post_id, $prepared['meta'], $url_map );
		$this->import_post_taxonomies( $post_id, $prepared['taxonomies'], $term_map, $warnings );

		return array(
			'post_id'   => $post_id,
			'post_name' => (string) get_post_field( 'post_name', $post_id ),
			'message'   => 'update' === $action
				? sprintf(
					/* translators: %d: post ID. */
					__( 'Обновлена запись #%d.', 'cpt-migrator' ),
					$post_id
				)
				: sprintf(
					/* translators: %d: post ID. */
					__( 'Создана запись #%d.', 'cpt-migrator' ),
					$post_id
				),
		);
	}

	/**
	 * Записывает метаполя через update_post_meta.
	 *
	 * @param int   $post_id ID записи.
	 * @param array $meta    Метаполя.
	 * @param array $url_map Карта замены URL изображений.
	 */
	private function import_post_meta( $post_id, $meta, $url_map = array() ) {
		if ( empty( $meta ) || ! is_array( $meta ) ) {
			return;
		}

		$search = empty( $url_map ) ? array() : $this->get_url_search_map( $url_map );

		foreach ( $meta as $key => $value ) {
			if ( ! is_string( $key ) || '' === $key ) {
				continue;
			}

			if ( ! $this->is_importable_meta_key( $key ) ) {
				continue;
			}

			if ( ! in_array( $key, self::ELEMENTOR_META_KEYS, true ) ) {
				$value = $this->normalize_meta_value( $value );
			}

			if ( ! empty( $search ) ) {
				$value = $this->replace_urls_recursive( $value, $search );
			}

			if ( null === $value ) {
				$value = '';
			}

			// Объекты не записываем: при чтении они были бы десериализованы.
			if ( is_object( $value ) ) {
				continue;
			}

			update_post_meta( $post_id, $key, wp_slash( $value ) );
		}
	}

	/**
	 * Проверяет, разрешено ли импортировать мета-ключ.
	 *
	 * Запрещаются только системные ключи WordPress и ключи, похожие
	 * на секреты. Пользовательские ключи (в том числе с подчёркиванием)
	 * переносятся, иначе теряется содержимое записей.
	 *
	 * @param string $key Мета-ключ.
	 *
	 * @return bool
	 */
	private function is_importable_meta_key( $key ) {
		if ( in_array( $key, self::BLOCKED_IMPORT_META_KEYS, true ) ) {
			return false;
		}

		if ( preg_match( self::SENSITIVE_META_PATTERN, $key ) ) {
			return false;
		}

		/**
		 * Позволяет изменить решение о переносе мета-ключа.
		 *
		 * @param bool   $allowed Разрешён ли перенос.
		 * @param string $key     Мета-ключ.
		 */
		return (bool) apply_filters( 'cpt_migrator_import_meta_key', true, $key );
	}

	/**
	 * Импортирует миниатюру и файлы, использованные в контенте.
	 *
	 * @param int    $post_id      ID записи.
	 * @param array  $prepared     Подготовленная запись.
	 * @param array  $warnings     Предупреждения (передаются по ссылке).
	 * @param array  $image_errors Ошибки изображений (передаются по ссылке).
	 * @param string $base_url     URL исходного сайта.
	 *
	 * @return array Карта «старый URL => новый URL».
	 */
	private function import_post_images( $post_id, $prepared, &$warnings, &$image_errors, $base_url ) {
		$map              = array();
		$candidates       = array();
		$thumbnail        = isset( $prepared['thumbnail'] ) && is_array( $prepared['thumbnail'] ) ? $prepared['thumbnail'] : array();
		$attachment_urls  = isset( $prepared['attachments_used'] ) && is_array( $prepared['attachments_used'] ) ? $prepared['attachments_used'] : array();
		$thumb_url        = isset( $thumbnail['url'] ) && is_string( $thumbnail['url'] ) ? trim( $thumbnail['url'] ) : '';
		$thumb_key        = '' !== $thumb_url ? $this->normalize_source_url( $thumb_url, $base_url ) : '';
		$thumb_alt        = isset( $thumbnail['alt'] ) && is_string( $thumbnail['alt'] ) ? sanitize_text_field( $thumbnail['alt'] ) : '';
		$thumb_name       = isset( $thumbnail['title'] ) && is_string( $thumbnail['title'] ) ? sanitize_text_field( $thumbnail['title'] ) : '';

		if ( ! empty( $thumbnail ) && '' === $thumb_url ) {
			$this->add_image_error( $image_errors, $prepared, '', __( 'не указан URL миниатюры.', 'cpt-migrator' ) );
		} elseif ( '' !== $thumb_url && '' === $thumb_key ) {
			$this->add_image_error( $image_errors, $prepared, $thumb_url, __( 'некорректный URL миниатюры.', 'cpt-migrator' ) );
		} elseif ( '' !== $thumb_key ) {
			$candidates[ $thumb_key ] = array(
				'sources'      => array( $thumb_url ),
				'is_thumbnail' => true,
				'alt'          => $thumb_alt,
				'title'        => $thumb_name,
			);
		}

		foreach ( $attachment_urls as $attachment_url ) {
			if ( ! is_string( $attachment_url ) || '' === trim( $attachment_url ) ) {
				continue;
			}

			$attachment_url = trim( $attachment_url );
			$source_key     = $this->normalize_source_url( $attachment_url, $base_url );

			if ( '' === $source_key ) {
				$this->add_image_error( $image_errors, $prepared, $attachment_url, __( 'некорректный URL.', 'cpt-migrator' ) );
				continue;
			}

			if ( ! isset( $candidates[ $source_key ] ) ) {
				$candidates[ $source_key ] = array(
					'sources'      => array(),
					'is_thumbnail' => false,
					'alt'          => '',
					'title'        => '',
				);
			}

			if ( ! in_array( $attachment_url, $candidates[ $source_key ]['sources'], true ) ) {
				$candidates[ $source_key ]['sources'][] = $attachment_url;
			}
		}

		$thumbnail_id = 0;

		foreach ( $candidates as $source_url => $candidate ) {
			$result = $this->get_or_create_attachment( $source_url, $post_id, $candidate['title'] );

			if ( is_wp_error( $result ) ) {
				$this->add_image_error( $image_errors, $prepared, $source_url, $result->get_error_message() );
				continue;
			}

			foreach ( $candidate['sources'] as $original_source ) {
				$this->add_url_map( $map, $original_source, $result['url'] );
			}

			$this->add_url_map( $map, $source_url, $result['url'] );

			if ( $candidate['is_thumbnail'] ) {
				$thumbnail_id = (int) $result['id'];

				if ( '' !== $candidate['alt'] ) {
					update_post_meta( $thumbnail_id, '_wp_attachment_image_alt', wp_slash( $candidate['alt'] ) );
				}
			}
		}

		if ( $thumbnail_id > 0 && ! set_post_thumbnail( $post_id, $thumbnail_id ) ) {
			$thumbnail_url = wp_get_attachment_url( $thumbnail_id );

			$this->add_image_error(
				$image_errors,
				$prepared,
				is_string( $thumbnail_url ) ? $thumbnail_url : '',
				__( 'не удалось назначить миниатюру.', 'cpt-migrator' )
			);
		}

		return $map;
	}

	/**
	 * Возвращает существующее вложение или скачивает новое.
	 *
	 * @param string $source_url Исходный URL.
	 * @param int    $post_id    ID записи.
	 * @param string $title      Заголовок вложения.
	 *
	 * @return array|WP_Error
	 */
	private function get_or_create_attachment( $source_url, $post_id, $title ) {
		$existing_id = $this->find_existing_attachment( $source_url );

		if ( $existing_id > 0 ) {
			$url = wp_get_attachment_url( $existing_id );

			if ( ! is_string( $url ) || '' === $url ) {
				return new WP_Error(
					'cpt_migrator_attachment_url_missing',
					__( 'Не удалось получить URL существующего вложения.', 'cpt-migrator' )
				);
			}

			$this->attach_existing_attachment( $existing_id, $post_id );

			if ( '' === (string) get_post_meta( $existing_id, self::SOURCE_URL_META_KEY, true ) ) {
				update_post_meta( $existing_id, self::SOURCE_URL_META_KEY, $source_url );
			}

			return array(
				'id'  => $existing_id,
				'url' => $url,
			);
		}

		return $this->download_attachment( $source_url, $post_id, $title );
	}

	/**
	 * Ищет вложение по исходному URL или имени файла.
	 *
	 * @param string $source_url Исходный URL.
	 *
	 * @return int ID вложения или 0.
	 */
	private function find_existing_attachment( $source_url ) {
		$ids = get_posts(
			array(
				'post_type'        => 'attachment',
				'post_status'      => 'inherit',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'no_found_rows'    => true,
				'suppress_filters' => true,
				'meta_key'         => self::SOURCE_URL_META_KEY,
				'meta_value'       => $source_url,
			)
		);

		if ( ! empty( $ids ) ) {
			$attachment_id = (int) $ids[0];

			if ( 'attachment' === get_post_type( $attachment_id ) && 'trash' !== get_post_status( $attachment_id ) ) {
				return $attachment_id;
			}
		}

		$filename = $this->get_filename_from_url( $source_url );

		if ( '' === $filename ) {
			return 0;
		}

		global $wpdb;

		$like = '%/' . $wpdb->esc_like( $filename );

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = '_wp_attached_file' AND meta_value LIKE %s LIMIT 50",
				$like
			)
		);

		foreach ( $rows as $row ) {
			if ( wp_basename( (string) $row->meta_value ) !== $filename ) {
				continue;
			}

			$attachment_id = (int) $row->post_id;

			if ( 'attachment' !== get_post_type( $attachment_id ) || 'trash' === get_post_status( $attachment_id ) ) {
				continue;
			}

			return $attachment_id;
		}

		return 0;
	}

	/**
	 * Скачивает вложение с повторными попытками.
	 *
	 * @param string $source_url Исходный URL.
	 * @param int    $post_id    ID записи.
	 * @param string $title      Заголовок вложения.
	 *
	 * @return array|WP_Error
	 */
	private function download_attachment( $source_url, $post_id, $title ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/media.php';
		require_once ABSPATH . 'wp-admin/includes/image.php';

		$attempts = (int) apply_filters( 'cpt_migrator_image_download_attempts', self::IMAGE_DOWNLOAD_ATTEMPTS );
		$attempts = max( 1, min( 5, $attempts ) );
		$timeout  = (int) apply_filters( 'cpt_migrator_image_download_timeout', self::IMAGE_DOWNLOAD_TIMEOUT );
		$timeout  = max( 1, min( 120, $timeout ) );

		$filename = $this->get_filename_from_url( $source_url );

		if ( '' === $filename ) {
			$filename = 'imported-image';
		}

		$title      = '' !== $title ? sanitize_text_field( $title ) : $this->get_attachment_title( $filename );
		$last_error = null;

		for ( $attempt = 1; $attempt <= $attempts; $attempt++ ) {
			$tmp_file = $this->download_image_file( $source_url, $timeout );

			if ( is_wp_error( $tmp_file ) ) {
				$last_error = $tmp_file;
			} else {
				$prepared_filename = $this->prepare_attachment_filename( $tmp_file, $filename );
				$type_check       = $this->validate_attachment_type( $tmp_file, $prepared_filename );

				if ( is_wp_error( $type_check ) ) {
					if ( file_exists( $tmp_file ) ) {
						@unlink( $tmp_file );
					}

					$last_error = $type_check;
				} else {
					$file_array = array(
						'name'     => $prepared_filename,
						'type'     => $this->get_mime_type_for_file( $tmp_file, $prepared_filename ),
						'tmp_name' => $tmp_file,
						'error'    => 0,
						'size'     => (int) filesize( $tmp_file ),
					);

					$post_data = array(
						'post_title'  => $title,
						'post_parent' => $post_id,
					);

					$attachment_id = media_handle_sideload( $file_array, $post_id, $title, $post_data );

					if ( ! is_wp_error( $attachment_id ) ) {
						$attachment_id = (int) $attachment_id;

						update_post_meta( $attachment_id, self::SOURCE_URL_META_KEY, $source_url );

						$url = wp_get_attachment_url( $attachment_id );

						if ( is_string( $url ) && '' !== $url ) {
							return array(
								'id'  => $attachment_id,
								'url' => $url,
							);
						}

						$last_error = new WP_Error(
							'cpt_migrator_attachment_url_missing',
							__( 'Не удалось получить URL загруженного вложения.', 'cpt-migrator' )
						);
					} else {
						$last_error = $attachment_id;
					}

					if ( file_exists( $tmp_file ) ) {
						@unlink( $tmp_file );
					}
				}
			}

			if ( $attempt < $attempts ) {
				sleep( 1 );
			}
		}

		return new WP_Error(
			'cpt_migrator_image_download_failed',
			sprintf(
				/* translators: 1: attempts count, 2: last error message. */
				__( 'Не удалось скачать изображение после %1$d попыток: %2$s', 'cpt-migrator' ),
				$attempts,
				$last_error ? $last_error->get_error_message() : __( 'неизвестная ошибка', 'cpt-migrator' )
			)
		);
	}

	/**
	 * Приводит имя файла к расширению, соответствующему реальному типу данных.
	 *
	 * @param string $tmp_file Временный файл.
	 * @param string $filename Имя файла из URL.
	 *
	 * @return string
	 */
	private function prepare_attachment_filename( $tmp_file, $filename ) {
		$filetype = wp_check_filetype_and_ext( $tmp_file, $filename );

		if ( ! empty( $filetype['ext'] ) ) {
			return $filename;
		}

		$real_type = $this->get_mime_type_for_file( $tmp_file, $filename );
		$extension = $this->get_extension_for_mime_type( $real_type );

		if ( '' === $extension ) {
			return $filename;
		}

		$base = pathinfo( $filename, PATHINFO_FILENAME );

		if ( '' === $base ) {
			$base = 'imported-image';
		}

		return $base . '.' . $extension;
	}

	/**
	 * Проверяет, что тип файла допустим для импорта в медиатеку.
	 *
	 * Решение принимается по политике самого сайта (get_allowed_mime_types):
	 * если администратор разрешил, например, SVG — импорт их переносит.
	 * HTML и JS блокируются всегда: такие файлы исполняются в браузере
	 * с origin сайта и превращают импорт в способ положить вредоносный файл.
	 *
	 * @param string $tmp_file Временный файл.
	 * @param string $filename Имя файла.
	 *
	 * @return true|WP_Error
	 */
	private function validate_attachment_type( $tmp_file, $filename ) {
		$always_blocked = array(
			'text/html',
			'application/xhtml+xml',
			'text/javascript',
			'application/javascript',
			'application/x-javascript',
		);

		$mime = strtolower( $this->get_mime_type_for_file( $tmp_file, $filename ) );

		if ( in_array( $mime, $always_blocked, true ) ) {
			return new WP_Error(
				'cpt_migrator_blocked_file_type',
				sprintf(
					/* translators: %s: MIME type. */
					__( 'Тип файла «%s» запрещён при импорте.', 'cpt-migrator' ),
					$mime
				)
			);
		}

		$extension = strtolower( pathinfo( $filename, PATHINFO_EXTENSION ) );

		if ( in_array( $extension, array( 'html', 'htm', 'xhtml', 'js', 'mjs' ), true ) ) {
			return new WP_Error(
				'cpt_migrator_blocked_file_type',
				sprintf(
					/* translators: %s: file extension. */
					__( 'Расширение «%s» запрещено при импорте.', 'cpt-migrator' ),
					$extension
				)
			);
		}

		$allowed = $this->get_allowed_attachment_mime_types();

		if ( ! in_array( $mime, $allowed, true ) ) {
			return new WP_Error(
				'cpt_migrator_file_type_not_allowed',
				sprintf(
					/* translators: %s: MIME type. */
					__( 'Тип файла «%s» не разрешён на этом сайте.', 'cpt-migrator' ),
					$mime
				)
			);
		}

		return true;
	}

	/**
	 * Возвращает список MIME-типов, разрешённых для вложений.
	 *
	 * @return string[]
	 */
	private function get_allowed_attachment_mime_types() {
		$types = get_allowed_mime_types();
		$list  = array();

		foreach ( (array) $types as $mime ) {
			$list[] = strtolower( (string) $mime );
		}

		/**
		 * Позволяет изменить список MIME-типов для импортируемых вложений.
		 *
		 * @param string[] $list MIME-типы.
		 */
		$list = (array) apply_filters( 'cpt_migrator_allowed_attachment_mime_types', $list );

		return array_values( array_unique( array_map( 'strtolower', array_map( 'strval', $list ) ) ) );
	}

	/**
	 * Возвращает расширение для MIME-типа.
	 *
	 * @param string $mime_type MIME-тип.
	 *
	 * @return string
	 */
	private function get_extension_for_mime_type( $mime_type ) {
		if ( ! is_string( $mime_type ) || '' === $mime_type ) {
			return '';
		}

		$types = get_allowed_mime_types();

		foreach ( $types as $extensions => $allowed_mime ) {
			if ( $allowed_mime === $mime_type ) {
				$list = explode( '|', $extensions );

				return isset( $list[0] ) ? $list[0] : '';
			}
		}

		return '';
	}

	/**
	 * Определяет MIME-тип скачанного файла.
	 *
	 * @param string $tmp_file Временный файл.
	 * @param string $filename Имя файла.
	 *
	 * @return string
	 */
	private function get_mime_type_for_file( $tmp_file, $filename ) {
		if ( is_readable( $tmp_file ) ) {
			$detected = wp_check_filetype_and_ext( $tmp_file, $filename );

			if ( ! empty( $detected['type'] ) ) {
				return $detected['type'];
			}
		}

		$filetype = wp_check_filetype( $filename );

		if ( ! empty( $filetype['type'] ) ) {
			return $filetype['type'];
		}

		if ( function_exists( 'finfo_open' ) ) {
			$finfo = finfo_open( FILEINFO_MIME_TYPE );

			if ( false !== $finfo ) {
				$mime = finfo_file( $finfo, $tmp_file );
				finfo_close( $finfo );

				if ( is_string( $mime ) && '' !== $mime ) {
					return $mime;
				}
			}
		}

		return 'application/octet-stream';
	}

	/**
	 * Скачивает файл изображения с проверкой адреса на внутренние ресурсы.
	 *
	 * Значение $source_url берётся из загруженного JSON-файла, то есть
	 * фактически контролируется пользователем. Поэтому внутренние адреса
	 * (loopback, приватные диапазоны, link-local, metadata-сервисы)
	 * блокируются, а загрузка идёт только через download_url(), который
	 * дополнительно применяет защиту WordPress (wp_safe_remote_get).
	 *
	 * @param string $source_url Исходный URL.
	 * @param int    $timeout    Таймаут загрузки.
	 *
	 * @return string|WP_Error
	 */
	private function download_image_file( $source_url, $timeout ) {
		$parts = wp_parse_url( $source_url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			return new WP_Error(
				'cpt_migrator_invalid_image_url',
				__( 'Некорректный URL изображения.', 'cpt-migrator' )
			);
		}

		if ( $this->is_internal_host( $parts['host'] ) ) {
			return new WP_Error(
				'cpt_migrator_blocked_image_host',
				sprintf(
					/* translators: %s: host name. */
					__( 'Адрес «%s» указывает на внутренний ресурс и запрещён.', 'cpt-migrator' ),
					$parts['host']
				)
			);
		}

		/**
		 * Позволяет изменить проверку адреса перед загрузкой изображения.
		 *
		 * @param bool   $allowed    Разрешена ли загрузка с этого адреса.
		 * @param string $source_url Исходный URL.
		 * @param string $host       Хост.
		 */
		$allowed = apply_filters( 'cpt_migrator_allow_image_host', true, $source_url, $parts['host'] );

		if ( ! $allowed ) {
			return new WP_Error(
				'cpt_migrator_blocked_image_host',
				__( 'Загрузка изображения с этого адреса запрещена.', 'cpt-migrator' )
			);
		}

		// download_url() использует wp_safe_remote_get() и сам блокирует
		// небезопасные адреса. Запасного обхода через wp_remote_get() нет.
		return download_url( $source_url, $timeout );
	}

	/**
	 * Определяет, указывает ли хост на внутренний ресурс.
	 *
	 * @param string $host Хост.
	 *
	 * @return bool
	 */
	private function is_internal_host( $host ) {
		$host = strtolower( trim( (string) $host ) );

		if ( '' === $host ) {
			return true;
		}

		$blocked_hosts = array( 'localhost', 'localhost.localdomain', 'ip6-localhost', 'ip6-loopback' );

		if ( in_array( $host, $blocked_hosts, true ) ) {
			return true;
		}

		// IPv4 в прямой записи.
		if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			return $this->is_internal_ip( $host );
		}

		// IPv6 в прямой записи.
		if ( filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			return $this->is_internal_ip( $host );
		}

		// Случай с доменным именем: проверяем разрешённые IP-адреса.
		$ip = gethostbyname( $host );

		if ( $ip !== $host ) {
			return $this->is_internal_ip( $ip );
		}

		// IPv4 не разрешился — пробуем IPv6.
		if ( function_exists( 'dns_get_record' ) ) {
			$records = @dns_get_record( $host, DNS_AAAA );

			if ( is_array( $records ) && ! empty( $records ) ) {
				foreach ( $records as $record ) {
					if ( empty( $record['ipv6'] ) ) {
						continue;
					}

					if ( $this->is_internal_ip( $record['ipv6'] ) ) {
						return true;
					}
				}

				return false;
			}
		}

		// Имя не разрешилось — считаем хост небезопасным.
		return true;
	}

	/**
	 * Определяет, относится ли IP-адрес к внутреннему диапазону.
	 *
	 * @param string $ip IP-адрес.
	 *
	 * @return bool
	 */
	private function is_internal_ip( $ip ) {
		$ip = (string) $ip;

		// IPv4 в виде IPv6 (::ffff:127.0.0.1) приводим к обычному IPv4.
		if ( preg_match( '/^::ffff:(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})$/i', $ip, $matches ) ) {
			$ip = $matches[1];
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
			// Внутренние и служебные диапазоны IPv4.
			$blocked = array(
				'0.0.0.0/8',
				'10.0.0.0/8',
				'100.64.0.0/10',
				'127.0.0.0/8',
				'169.254.0.0/16',
				'172.16.0.0/12',
				'192.168.0.0/16',
				'224.0.0.0/4',
				'240.0.0.0/4',
			);

			foreach ( $blocked as $cidr ) {
				if ( $this->ip_in_cidr( $ip, $cidr ) ) {
					return true;
				}
			}

			return false;
		}

		if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
			$blocked = array(
				'::1/128',
				'::/128',
				'fc00::/7',
				'fe80::/10',
				'ff00::/8',
			);

			foreach ( $blocked as $cidr ) {
				if ( $this->ip_in_cidr( $ip, $cidr ) ) {
					return true;
				}
			}

			return false;
		}

		return true;
	}

	/**
	 * Проверяет, входит ли IP-адрес в подсеть CIDR.
	 *
	 * @param string $ip   IP-адрес.
	 * @param string $cidr Подсеть в формате CIDR.
	 *
	 * @return bool
	 */
	private function ip_in_cidr( $ip, $cidr ) {
		$parts = explode( '/', $cidr );

		if ( 2 !== count( $parts ) ) {
			return false;
		}

		$subnet = $parts[0];
		$bits   = (int) $parts[1];

		$ip_binary    = @inet_pton( $ip );
		$subnet_binary = @inet_pton( $subnet );

		if ( false === $ip_binary || false === $subnet_binary ) {
			return false;
		}

		if ( strlen( $ip_binary ) !== strlen( $subnet_binary ) ) {
			return false;
		}

		$max_bits = strlen( $ip_binary ) * 8;

		if ( $bits < 0 || $bits > $max_bits ) {
			return false;
		}

		$bytes = (int) floor( $bits / 8 );
		$rest  = $bits % 8;

		if ( $bytes > 0 && substr( $ip_binary, 0, $bytes ) !== substr( $subnet_binary, 0, $bytes ) ) {
			return false;
		}

		if ( 0 === $rest ) {
			return true;
		}

		$mask = ~( ( 1 << ( 8 - $rest ) ) - 1 ) & 0xFF;

		return ( ord( $ip_binary[ $bytes ] ) & $mask ) === ( ord( $subnet_binary[ $bytes ] ) & $mask );
	}

	/**
	 * Прикрепляет существующее вложение к записи, если оно ни к чему не прикреплено.
	 *
	 * @param int $attachment_id ID вложения.
	 * @param int $post_id       ID записи.
	 */
	private function attach_existing_attachment( $attachment_id, $post_id ) {
		if ( (int) get_post_field( 'post_parent', $attachment_id ) > 0 ) {
			return;
		}

		wp_update_post(
			array(
				'ID'          => $attachment_id,
				'post_parent' => $post_id,
			)
		);
	}

	/**
	 * Нормализует URL исходного изображения.
	 *
	 * @param string $url      URL из JSON.
	 * @param string $base_url URL исходного сайта.
	 *
	 * @return string
	 */
	private function normalize_source_url( $url, $base_url ) {
		if ( ! is_string( $url ) ) {
			return '';
		}

		$url = trim( html_entity_decode( $url, ENT_QUOTES, 'UTF-8' ) );

		if ( '' === $url ) {
			return '';
		}

		if ( 0 === strpos( $url, '//' ) ) {
			$scheme = wp_parse_url( $base_url, PHP_URL_SCHEME );
			$url    = ( is_string( $scheme ) && '' !== $scheme ? $scheme : 'https' ) . ':' . $url;
		} elseif ( 0 === strpos( $url, '/' ) ) {
			$origin = $this->get_url_origin( $base_url );

			if ( '' === $origin ) {
				return '';
			}

			$url = $origin . $url;
		} elseif ( ! preg_match( '~^https?://~i', $url ) ) {
			$origin = $this->get_url_origin( $base_url );

			if ( '' === $origin ) {
				return '';
			}

			$url = $origin . '/' . ltrim( $url, '/' );
		}

		$url   = esc_url_raw( $url );
		$parts = wp_parse_url( $url );

		if (
			'' === $url ||
			! is_array( $parts ) ||
			empty( $parts['scheme'] ) ||
			empty( $parts['host'] ) ||
			! in_array( strtolower( (string) $parts['scheme'] ), array( 'http', 'https' ), true )
		) {
			return '';
		}

		return $url;
	}

	/**
	 * Возвращает схему, хост и порт базового URL.
	 *
	 * @param string $base_url Базовый URL.
	 *
	 * @return string
	 */
	private function get_url_origin( $base_url ) {
		$parts = wp_parse_url( $base_url );

		if ( ! is_array( $parts ) || empty( $parts['scheme'] ) || empty( $parts['host'] ) ) {
			return '';
		}

		$origin = $parts['scheme'] . '://' . $parts['host'];

		if ( ! empty( $parts['port'] ) ) {
			$origin .= ':' . $parts['port'];
		}

		return $origin;
	}

	/**
	 * Возвращает безопасное имя файла из URL.
	 *
	 * @param string $url URL.
	 *
	 * @return string
	 */
	private function get_filename_from_url( $url ) {
		$path = wp_parse_url( $url, PHP_URL_PATH );

		if ( ! is_string( $path ) || '' === $path ) {
			return '';
		}

		return sanitize_file_name( rawurldecode( wp_basename( $path ) ) );
	}

	/**
	 * Возвращает заголовок вложения по имени файла.
	 *
	 * @param string $filename Имя файла.
	 *
	 * @return string
	 */
	private function get_attachment_title( $filename ) {
		$title = pathinfo( $filename, PATHINFO_FILENAME );
		$title = str_replace( array( '-', '_' ), ' ', $title );

		return '' !== $title ? sanitize_text_field( $title ) : sanitize_text_field( $filename );
	}

	/**
	 * Добавляет URL в карту замены, включая варианты схемы.
	 *
	 * @param array  $map        Карта замены (передаётся по ссылке).
	 * @param string $source_url Старый URL.
	 * @param string $target_url Новый URL.
	 */
	private function add_url_map( &$map, $source_url, $target_url ) {
		if ( ! is_string( $source_url ) || '' === $source_url || ! is_string( $target_url ) || '' === $target_url ) {
			return;
		}

		$parts = wp_parse_url( $source_url );

		if ( ! is_array( $parts ) || empty( $parts['host'] ) ) {
			// Относительный адрес: сохраняем относительный вид.
			$target_path = wp_parse_url( $target_url, PHP_URL_PATH );

			$map[ $source_url ] = is_string( $target_path ) && '' !== $target_path ? $target_path : $target_url;

			return;
		}

		$map[ $source_url ] = $target_url;

		$path  = isset( $parts['path'] ) ? $parts['path'] : '';
		$query = isset( $parts['query'] ) ? '?' . $parts['query'] : '';
		$host  = $parts['host'];

		if ( ! empty( $parts['port'] ) ) {
			$host .= ':' . $parts['port'];
		}

		$map[ '//' . $host . $path . $query ]        = $target_url;
		$map[ 'http://' . $host . $path . $query ]  = $target_url;
		$map[ 'https://' . $host . $path . $query ] = $target_url;
	}

	/**
	 * Заменяет URL изображений в контенте записи.
	 *
	 * @param string $content Контент.
	 * @param array  $url_map Карта замены.
	 *
	 * @return string
	 */
	private function replace_urls_in_content( $content, $url_map ) {
		if ( ! is_string( $content ) || '' === $content || empty( $url_map ) ) {
			return $content;
		}

		return strtr( $content, $this->get_url_search_map( $url_map ) );
	}

	/**
	 * Рекурсивно заменяет URL в массивах и сериализованных строках.
	 *
	 * Обычные строки (в том числе JSON) заменяются напрямую: карта поиска
	 * содержит варианты URL с экранированными слешами, поэтому структура
	 * JSON не нарушается.
	 *
	 * @param mixed $value  Значение.
	 * @param array $search Карта поиска.
	 *
	 * @return mixed
	 */
	private function replace_urls_recursive( $value, $search ) {
		if ( is_array( $value ) ) {
			foreach ( $value as $key => $item ) {
				$value[ $key ] = $this->replace_urls_recursive( $item, $search );
			}

			return $value;
		}

		if ( is_object( $value ) ) {
			foreach ( get_object_vars( $value ) as $key => $item ) {
				$value->$key = $this->replace_urls_recursive( $item, $search );
			}

			return $value;
		}

		if ( ! is_string( $value ) || '' === $value ) {
			return $value;
		}

		if ( is_serialized( $value ) ) {
			$unserialized = @unserialize( $value, array( 'allowed_classes' => false ) );

			if ( false === $unserialized && 'b:0;' !== trim( $value ) ) {
				return $value;
			}

			if ( $this->contains_incomplete_class( $unserialized ) ) {
				return $value;
			}

			return serialize( $this->replace_urls_recursive( $unserialized, $search ) );
		}

		return strtr( $value, $search );
	}

	/**
	 * Проверяет, содержит ли значение объекты, которые не удалось разобрать.
	 *
	 * @param mixed $value Значение.
	 *
	 * @return bool
	 */
	private function contains_incomplete_class( $value ) {
		if ( is_object( $value ) ) {
			return '__PHP_Incomplete_Class' === get_class( $value );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $item ) {
				if ( $this->contains_incomplete_class( $item ) ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Строит карту поиска с учётом всех вариантов записи URL.
	 *
	 * Учитываются: обычная запись, JSON со слешами «\/», HTML-сущности
	 * «&amp;» и «&#038;», а также их комбинации.
	 *
	 * @param array $url_map Карта «старый URL => новый URL».
	 *
	 * @return array
	 */
	private function get_url_search_map( $url_map ) {
		$search = array();

		foreach ( $url_map as $source_url => $target_url ) {
			if ( ! is_string( $source_url ) || '' === $source_url || ! is_string( $target_url ) || '' === $target_url ) {
				continue;
			}

			$source_variants = array(
				$source_url,
				str_replace( '/', '\\/', $source_url ),
				str_replace( '&', '&amp;', $source_url ),
				str_replace( '&', '&#038;', $source_url ),
				str_replace( array( '&', '/' ), array( '&amp;', '\\/' ), $source_url ),
				str_replace( array( '&', '/' ), array( '&#038;', '\\/' ), $source_url ),
			);

			$target_variants = array(
				$target_url,
				str_replace( '/', '\\/', $target_url ),
				str_replace( '&', '&amp;', $target_url ),
				str_replace( '&', '&#038;', $target_url ),
				str_replace( array( '&', '/' ), array( '&amp;', '\\/' ), $target_url ),
				str_replace( array( '&', '/' ), array( '&#038;', '\\/' ), $target_url ),
			);

			foreach ( $source_variants as $index => $variant ) {
				if ( '' === $variant ) {
					continue;
				}

				$search[ $variant ] = $target_variants[ $index ];
			}

			// В контенте встречаются и относительные пути к загрузкам.
			$source_path = wp_parse_url( $source_url, PHP_URL_PATH );

			if ( is_string( $source_path ) && '' !== $source_path ) {
				$target_path = wp_parse_url( $target_url, PHP_URL_PATH );

				if ( is_string( $target_path ) && '' !== $target_path ) {
					$search[ $source_path ] = $target_path;
					$search[ str_replace( '/', '\\/', $source_path ) ] = str_replace( '/', '\\/', $target_path );
				}
			}
		}

		// Более длинные варианты должны проверяться первыми, чтобы
		// «/img.png.webp» не заменялся на «/img.png».
		uksort(
			$search,
			static function ( $first, $second ) {
				return strlen( $second ) - strlen( $first );
			}
		);

		return $search;
	}

	/**
	 * Добавляет сообщение об ошибке изображения в отчёт.
	 *
	 * @param array  $image_errors Ошибки (передаются по ссылке).
	 * @param array  $prepared     Подготовленная запись.
	 * @param string $url          URL изображения.
	 * @param string $message      Сообщение.
	 */
	private function add_image_error( &$image_errors, $prepared, $url, $message ) {
		$label = '' !== $prepared['post_title'] ? $prepared['post_title'] : $prepared['post_name'];

		$image_errors[] = sprintf(
			/* translators: 1: post title or slug, 2: image URL, 3: error message. */
			__( 'Запись «%1$s»: изображение %2$s — %3$s', 'cpt-migrator' ),
			$label,
			'' !== $url ? $url : __( '(URL не указан)', 'cpt-migrator' ),
			$message
		);
	}

	/**
	 * Назначает таксономии, создавая недостающие термины.
	 *
	 * @param int   $post_id    ID записи.
	 * @param array $taxonomies Таксономии из файла.
	 * @param array $term_map   Данные терминов из файла.
	 * @param array $warnings   Предупреждения (передаются по ссылке).
	 */
	private function import_post_taxonomies( $post_id, $taxonomies, $term_map, &$warnings ) {
		if ( empty( $taxonomies ) || ! is_array( $taxonomies ) ) {
			return;
		}

		foreach ( $taxonomies as $taxonomy => $terms ) {
			if ( ! is_string( $taxonomy ) || '' === $taxonomy || ! is_array( $terms ) ) {
				continue;
			}

			$term_ids  = array();
			$resolving = array();

			foreach ( $terms as $term ) {
				if ( ! is_array( $term ) || empty( $term['slug'] ) || ! is_scalar( $term['slug'] ) ) {
					continue;
				}

				$slug = trim( (string) $term['slug'] );

				if ( '' === $slug ) {
					continue;
				}

				$name        = isset( $term['name'] ) && is_scalar( $term['name'] ) ? (string) $term['name'] : $slug;
				$parent_slug = isset( $term['parent_slug'] ) && is_scalar( $term['parent_slug'] ) ? trim( (string) $term['parent_slug'] ) : '';
				$term_id     = $this->ensure_term( $taxonomy, $slug, $name, $parent_slug, $term_map, $warnings, $resolving );

				if ( $term_id > 0 ) {
					$term_ids[] = $term_id;
				}
			}

			if ( empty( $term_ids ) ) {
				continue;
			}

			$result = wp_set_object_terms( $post_id, array_values( array_unique( $term_ids ) ), $taxonomy, false );

			if ( is_wp_error( $result ) ) {
				$warnings[] = sprintf(
					/* translators: 1: taxonomy, 2: post ID, 3: error message. */
					__( 'Не удалось назначить термины таксономии «%1$s» записи #%2$d: %3$s', 'cpt-migrator' ),
					$taxonomy,
					$post_id,
					$result->get_error_message()
				);
			}
		}
	}

	/**
	 * Возвращает ID термина, создавая его при необходимости.
	 *
	 * @param string $taxonomy    Таксономия.
	 * @param string $slug        Slug термина.
	 * @param string $name        Название термина.
	 * @param string $parent_slug Slug родительского термина.
	 * @param array  $term_map    Данные терминов из файла.
	 * @param array  $warnings    Предупреждения (передаются по ссылке).
	 * @param array  $resolving   Служебный стек для защиты от циклов.
	 *
	 * @return int
	 */
	private function ensure_term( $taxonomy, $slug, $name, $parent_slug, $term_map, &$warnings, &$resolving ) {
		$existing = get_term_by( 'slug', $slug, $taxonomy );

		if ( $existing instanceof WP_Term ) {
			return (int) $existing->term_id;
		}

		$key       = $taxonomy . '|' . $slug;
		$parent_id = 0;

		if ( isset( $resolving[ $key ] ) ) {
			$warnings[] = sprintf(
				/* translators: 1: term slug, 2: taxonomy. */
				__( 'Обнаружена циклическая иерархия терминов для «%1$s» в таксономии «%2$s». Родительский термин не назначен.', 'cpt-migrator' ),
				$slug,
				$taxonomy
			);
		} else {
			$resolving[ $key ] = true;

			if ( '' !== $parent_slug ) {
				$parent = isset( $term_map[ $taxonomy ][ $parent_slug ] )
					? $term_map[ $taxonomy ][ $parent_slug ]
					: array(
						'name'        => $parent_slug,
						'parent_slug' => '',
					);

				$parent_id = $this->ensure_term(
					$taxonomy,
					$parent_slug,
					$parent['name'],
					$parent['parent_slug'],
					$term_map,
					$warnings,
					$resolving
				);
			}

			unset( $resolving[ $key ] );
		}

		// Термин мог быть создан во время разрешения родителя.
		$existing = get_term_by( 'slug', $slug, $taxonomy );

		if ( $existing instanceof WP_Term ) {
			return (int) $existing->term_id;
		}

		$result = wp_insert_term(
			'' !== $name ? $name : $slug,
			$taxonomy,
			array(
				'slug'   => $slug,
				'parent' => $parent_id,
			)
		);

		if ( is_wp_error( $result ) ) {
			$existing = get_term_by( 'slug', $slug, $taxonomy );

			if ( $existing instanceof WP_Term ) {
				return (int) $existing->term_id;
			}

			$warnings[] = sprintf(
				/* translators: 1: term name, 2: taxonomy, 3: error message. */
				__( 'Не удалось создать термин «%1$s» в таксономии «%2$s»: %3$s', 'cpt-migrator' ),
				$name,
				$taxonomy,
				$result->get_error_message()
			);

			return 0;
		}

		return isset( $result['term_id'] ) ? (int) $result['term_id'] : 0;
	}

	/**
	 * Выводит вкладку «Импорт».
	 */
	private function render_import_tab() {
		$import_id = $this->get_requested_import_id();

		if ( '' !== $import_id ) {
			$state = $this->get_import_session( $import_id );

			if ( null !== $state ) {
				$this->render_import_progress( $import_id, $state );
				return;
			}

			?>
			<div class="notice notice-error">
				<p><?php esc_html_e( 'Сессия импорта не найдена или истекла. Загрузите файл заново.', 'cpt-migrator' ); ?></p>
			</div>
			<?php
		}

		$this->render_import_result();
		?>
		<div class="cpt-migrator-tab-panel">
			<p><?php esc_html_e( 'Загрузите JSON-файл, полученный на вкладке «Экспорт». Существующая запись ищется по post_type + post_name.', 'cpt-migrator' ); ?></p>
			<p><?php esc_html_e( 'Миниатюра и изображения из attachments_used будут скачаны, прикреплены к записи, а старые URL заменены в контенте и метаполях.', 'cpt-migrator' ); ?></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
				<?php /* Значение action и действие nonce проверяются в handle_import(). */ ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( self::IMPORT_ACTION ); ?>" />
				<?php wp_nonce_field( self::IMPORT_ACTION, self::IMPORT_NONCE_NAME ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="cpt_migrator_import_file"><?php esc_html_e( 'JSON-файл', 'cpt-migrator' ); ?></label>
						</th>
						<td>
							<input type="file" name="cpt_migrator_import_file" id="cpt_migrator_import_file" accept=".json,application/json" required />
							<p class="description">
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: maximum file size. */
										__( 'Максимальный размер файла: %s.', 'cpt-migrator' ),
										size_format( self::MAX_IMPORT_FILE_SIZE )
									)
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row">
							<label for="cpt_migrator_duplicate_action"><?php esc_html_e( 'Дубликаты', 'cpt-migrator' ); ?></label>
						</th>
						<td>
							<select name="cpt_migrator_duplicate_action" id="cpt_migrator_duplicate_action">
								<option value="skip"><?php esc_html_e( 'Пропускать существующие', 'cpt-migrator' ); ?></option>
								<option value="update"><?php esc_html_e( 'Обновлять существующие', 'cpt-migrator' ); ?></option>
								<option value="suffix"><?php esc_html_e( 'Создавать новую с суффиксом', 'cpt-migrator' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Поиск существующей записи выполняется по post_type + post_name.', 'cpt-migrator' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Режим', 'cpt-migrator' ); ?></th>
						<td>
							<label for="cpt_migrator_dry_run">
								<input type="checkbox" id="cpt_migrator_dry_run" name="cpt_migrator_dry_run" value="1" />
								<?php esc_html_e( 'Пробный запуск (ничего не записывать)', 'cpt-migrator' ); ?>
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( __( 'Импортировать', 'cpt-migrator' ), 'primary', 'cpt_migrator_import_submit', false ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Выводит индикатор пакетного импорта.
	 *
	 * @param string $import_id ID сессии.
	 * @param array  $state     Состояние сессии.
	 */
	private function render_import_progress( $import_id, $state ) {
		$total     = isset( $state['total'] ) ? (int) $state['total'] : 0;
		$processed = isset( $state['offset'] ) ? (int) $state['offset'] : 0;
		$dry_run   = ! empty( $state['dry_run'] );
		?>
		<div class="cpt-migrator-tab-panel">
			<h2><?php esc_html_e( 'Импорт выполняется', 'cpt-migrator' ); ?></h2>

			<p>
				<?php
				echo esc_html(
					$dry_run
						? __( 'Пробный запуск: данные не записываются, изображения не скачиваются.', 'cpt-migrator' )
						: __( 'Не закрывайте страницу до завершения импорта.', 'cpt-migrator' )
				);
				?>
			</p>

			<div id="cpt-migrator-import-progress" data-import-id="<?php echo esc_attr( $import_id ); ?>">
				<progress
					id="cpt-migrator-import-progress-bar"
					max="<?php echo esc_attr( (string) max( 1, $total ) ); ?>"
					value="<?php echo esc_attr( (string) $processed ); ?>"
					style="width: 100%; height: 20px;"
				></progress>

				<p id="cpt-migrator-import-status" aria-live="polite">
					<?php
					printf(
						/* translators: 1: processed count, 2: total count. */
						esc_html__( 'Обработано %1$d из %2$d…', 'cpt-migrator' ),
						$processed,
						$total
					);
					?>
				</p>

				<div id="cpt-migrator-import-error" class="notice notice-error" hidden>
					<p></p>
				</div>

				<p>
					<button type="button" class="button button-primary" id="cpt-migrator-import-retry" hidden>
						<?php esc_html_e( 'Повторить', 'cpt-migrator' ); ?>
					</button>
				</p>
			</div>

			<noscript>
				<div class="notice notice-error">
					<p><?php esc_html_e( 'Для пакетного импорта нужно включить JavaScript.', 'cpt-migrator' ); ?></p>
				</div>
			</noscript>
		</div>
		<?php
	}

	/**
	 * Выводит результат последнего импорта или пробного запуска.
	 */
	private function render_import_result() {
		$result = $this->get_import_result();

		if ( ! is_array( $result ) ) {
			return;
		}

		$this->delete_import_result();

		$dry_run      = ! empty( $result['dry_run'] );
		$summary      = isset( $result['summary'] ) && is_array( $result['summary'] ) ? $result['summary'] : array();
		$rows         = isset( $result['rows'] ) && is_array( $result['rows'] ) ? $result['rows'] : array();
		$errors       = isset( $result['errors'] ) && is_array( $result['errors'] ) ? $result['errors'] : array();
		$warnings     = isset( $result['warnings'] ) && is_array( $result['warnings'] ) ? $result['warnings'] : array();
		$image_errors = isset( $result['image_errors'] ) && is_array( $result['image_errors'] ) ? $result['image_errors'] : array();
		$created      = isset( $summary['created'] ) ? (int) $summary['created'] : 0;
		$updated      = isset( $summary['updated'] ) ? (int) $summary['updated'] : 0;
		$skipped      = isset( $summary['skipped'] ) ? (int) $summary['skipped'] : 0;
		$failed       = isset( $summary['errors'] ) ? (int) $summary['errors'] : 0;

		$notice_class = 'notice-info';

		if ( ! $dry_run ) {
			$notice_class = $failed > 0 ? 'notice-warning' : 'notice-success';
		}
		?>
		<div class="notice <?php echo esc_attr( $notice_class ); ?>">
			<p>
				<strong>
					<?php
					echo esc_html(
						$dry_run
							? __( 'Пробный запуск завершён: изменения не сохранены.', 'cpt-migrator' )
							: __( 'Импорт завершён.', 'cpt-migrator' )
					);
					?>
				</strong>
			</p>
			<p>
				<?php
				if ( $dry_run ) {
					printf(
						/* translators: 1: created, 2: updated, 3: skipped, 4: errors. */
						esc_html__( 'Будет создано: %1$d. Будет обновлено: %2$d. Будет пропущено: %3$d. Ошибок: %4$d.', 'cpt-migrator' ),
						$created,
						$updated,
						$skipped,
						$failed
					);
				} else {
					printf(
						/* translators: 1: created, 2: updated, 3: skipped, 4: errors. */
						esc_html__( 'Создано: %1$d. Обновлено: %2$d. Пропущено: %3$d. Ошибок: %4$d.', 'cpt-migrator' ),
						$created,
						$updated,
						$skipped,
						$failed
					);
				}
				?>
			</p>
			<?php if ( $dry_run ) : ?>
				<p><?php esc_html_e( 'Чтобы выполнить импорт, загрузите этот же файл с выключенным «Пробным запуском».', 'cpt-migrator' ); ?></p>
			<?php endif; ?>
		</div>

		<?php if ( ! empty( $errors ) ) : ?>
			<div class="notice notice-error">
				<p><strong><?php esc_html_e( 'Ошибки', 'cpt-migrator' ); ?></strong></p>
				<ul>
					<?php foreach ( $errors as $error ) : ?>
						<li><?php echo esc_html( $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $image_errors ) ) : ?>
			<div class="notice notice-warning">
				<p><strong><?php esc_html_e( 'Не удалось импортировать изображения', 'cpt-migrator' ); ?></strong></p>
				<ul>
					<?php foreach ( $image_errors as $image_error ) : ?>
						<li><?php echo esc_html( $image_error ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $warnings ) ) : ?>
			<div class="notice notice-warning">
				<p><strong><?php esc_html_e( 'Предупреждения', 'cpt-migrator' ); ?></strong></p>
				<ul>
					<?php foreach ( $warnings as $warning ) : ?>
						<li><?php echo esc_html( $warning ); ?></li>
					<?php endforeach; ?>
				</ul>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $result['rows_truncated'] ) ) : ?>
			<div class="notice notice-info">
				<p>
					<?php
					printf(
						/* translators: %d: number of rows. */
						esc_html__( 'В таблице показаны первые %d строк отчёта. Итоговые счётчики учитывают все записи.', 'cpt-migrator' ),
						(int) self::MAX_IMPORT_REPORT_ROWS
					);
					?>
				</p>
			</div>
		<?php endif; ?>

		<?php if ( ! empty( $rows ) ) : ?>
			<table class="widefat striped cpt-migrator-result-table">
				<thead>
					<tr>
						<th><?php esc_html_e( 'ID в файле', 'cpt-migrator' ); ?></th>
						<th><?php esc_html_e( 'Тип', 'cpt-migrator' ); ?></th>
						<th><?php esc_html_e( 'Заголовок', 'cpt-migrator' ); ?></th>
						<th><?php esc_html_e( 'Slug', 'cpt-migrator' ); ?></th>
						<th><?php esc_html_e( 'Действие', 'cpt-migrator' ); ?></th>
						<th><?php esc_html_e( 'Детали', 'cpt-migrator' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $row ) : ?>
						<?php
						$row_source_id = isset( $row['source_id'] ) ? (int) $row['source_id'] : 0;
						$row_post_type = isset( $row['post_type'] ) ? (string) $row['post_type'] : '';
						$row_title     = isset( $row['title'] ) ? (string) $row['title'] : '';
						$row_post_name = isset( $row['post_name'] ) ? (string) $row['post_name'] : '';
						$row_action    = isset( $row['action'] ) ? (string) $row['action'] : 'error';
						$row_message   = isset( $row['message'] ) ? (string) $row['message'] : '';
						?>
						<tr>
							<td><?php echo esc_html( $row_source_id > 0 ? (string) $row_source_id : '—' ); ?></td>
							<td><?php echo esc_html( $row_post_type ); ?></td>
							<td><?php echo esc_html( '' !== $row_title ? $row_title : '—' ); ?></td>
							<td><code><?php echo esc_html( $row_post_name ); ?></code></td>
							<td><strong><?php echo esc_html( $this->get_import_action_label( $row_action, $dry_run ) ); ?></strong></td>
							<td><?php echo esc_html( $row_message ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
		<?php
	}

	/**
	 * Подпись действия в таблице результата.
	 *
	 * @param string $action  Действие.
	 * @param bool   $dry_run Пробный запуск.
	 *
	 * @return string
	 */
	private function get_import_action_label( $action, $dry_run ) {
		if ( 'create' === $action ) {
			return $dry_run ? __( 'Будет создана', 'cpt-migrator' ) : __( 'Создана', 'cpt-migrator' );
		}

		if ( 'update' === $action ) {
			return $dry_run ? __( 'Будет обновлена', 'cpt-migrator' ) : __( 'Обновлена', 'cpt-migrator' );
		}

		if ( 'skip' === $action ) {
			return $dry_run ? __( 'Будет пропущена', 'cpt-migrator' ) : __( 'Пропущена', 'cpt-migrator' );
		}

		return __( 'Ошибка', 'cpt-migrator' );
	}
}

CPT_Content_Migrator::instance();
