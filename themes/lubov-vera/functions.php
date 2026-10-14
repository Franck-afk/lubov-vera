<?php
/**
 * Lubov-vera functions and definitions.
 *
 * @link https://developer.wordpress.org/themes/basics/theme-functions/
 *
 * @package lubov-vera
 */

if ( ! defined( '_S_VERSION' ) ) {
	define( '_S_VERSION', '1.0.5' );
}

if ( ! function_exists( 'lv_get_setting' ) ) {
	/**
	 * Фолбэк чтения настроек, если плагин lubov-vera-core не активен.
	 * Активный плагин определяет свою версию lv_get_setting.
	 *
	 * @param string $key      Ключ настройки.
	 * @param mixed  $fallback Значение по умолчанию.
	 * @return mixed Значение настройки или запасное значение.
	 */
	function lv_get_setting( $key, $fallback = '' ) {
		$settings = get_option( 'lv_site_settings', array() );
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		return array_key_exists( $key, $settings ) ? $settings[ $key ] : $fallback;
	}
}

add_action(
	'after_switch_theme',
	function () {
		flush_rewrite_rules();
	}
);

/**
 * Остальные настройки темы
 */
function lubov_vera_setup() {
	load_theme_textdomain( 'lubov-vera', get_template_directory() . '/languages' );
	add_theme_support( 'automatic-feed-links' );
	add_theme_support( 'title-tag' );
	add_theme_support( 'post-thumbnails' );
	add_theme_support( 'align-wide' );

	add_theme_support( 'editor-styles' );
	add_editor_style( 'assets/css/editor-style.css' );

	if ( ! isset( $content_width ) ) {
		$content_width = 1032;
	}

	register_nav_menus(
		array(
			'menu-1'            => esc_html__( 'Primary', 'lubov-vera' ),
			'footer-documents'  => esc_html__( 'Footer Documents', 'lubov-vera' ),
			'footer-navigation' => esc_html__( 'Footer Navigation', 'lubov-vera' ),
		)
	);

	add_theme_support( 'html5', array( 'search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script' ) );
	add_theme_support( 'customize-selective-refresh-widgets' );
	add_theme_support(
		'custom-logo',
		array(
			'height'      => 250,
			'width'       => 250,
			'flex-width'  => true,
			'flex-height' => true,
		)
	);
}
add_action( 'after_setup_theme', 'lubov_vera_setup' );

add_action(
	'enqueue_block_editor_assets',
	function () {
		wp_enqueue_script(
			'lubov-vera-editor',
			get_template_directory_uri() . '/assets/js/editor.js',
			array( 'wp-blocks', 'wp-dom-ready' ),
			_S_VERSION,
			true
		);
	}
);

add_action(
	'admin_head',
	function () {
		?>
	<style>
		.editor-block-list__block[data-type="core/quote"],
		.block-editor-block-list__block[data-type="core/quote"],
		.block-editor-block-types-list [data-type="core/quote"],
		.wp-block[data-type="core/quote"] {
			display: none !important;
		}
	</style>
		<?php
	}
);

$lv_disabled_blocks = array( 'core/quote' );

add_action(
	'init',
	function () use ( $lv_disabled_blocks ) {
		foreach ( $lv_disabled_blocks as $block ) {
			if ( WP_Block_Type_Registry::get_instance()->is_registered( $block ) ) {
				unregister_block_type( $block );
			}
		}
	},
	20
);

add_filter(
	'allowed_block_types_all',
	function ( $allowed_blocks ) use ( $lv_disabled_blocks ) {
		if ( is_array( $allowed_blocks ) ) {
			return array_values(
				array_filter(
					$allowed_blocks,
					function ( $block ) use ( $lv_disabled_blocks ) {
						return ! in_array( $block, $lv_disabled_blocks, true );
					}
				)
			);
		}
		return $allowed_blocks;
	},
	100
);

/**
 * Стили и скрипты с условной загрузкой
 */
function lubov_vera_scripts() {
	// Стили загружаем везде (критичны для отображения).
	wp_enqueue_style( 'swiper-css', 'https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.css', array(), '12' );
	wp_enqueue_style( 'lubov-vera-reset', get_template_directory_uri() . '/assets/css/reset.css', array(), _S_VERSION );
	wp_enqueue_style( 'lubov-vera-main-style', get_template_directory_uri() . '/assets/css/style.css', array( 'lubov-vera-reset' ), _S_VERSION );
	wp_enqueue_style( 'lubov-vera-adaptive', get_template_directory_uri() . '/assets/css/adaptive.css', array( 'lubov-vera-main-style' ), _S_VERSION );
	wp_enqueue_style( 'lubov-vera-buttons', get_template_directory_uri() . '/assets/css/buttons.css', array( 'lubov-vera-main-style' ), _S_VERSION );
	wp_enqueue_style( 'lubov-vera-preloader', get_template_directory_uri() . '/assets/css/preloader.css', array( 'lubov-vera-main-style' ), _S_VERSION );
	wp_enqueue_style( 'lubov-vera-theme-style', get_stylesheet_uri(), array( 'lubov-vera-main-style' ), _S_VERSION );
	wp_enqueue_style( 'lubov-vera-dark-theme', get_template_directory_uri() . '/assets/css/dark-theme.css', array( 'lubov-vera-main-style', 'lubov-vera-adaptive', 'lubov-vera-theme-style' ), _S_VERSION );
	wp_enqueue_style( 'font-awesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css', array(), '6.0.0' );

	// Скрипты загружаем только там, где они нужны.
	global $template;
	$template_name = basename( $template );

	// Swiper нужен на главной, в шаблонах публикаций и архивах.
	$load_swiper = is_front_page() || is_singular() || is_archive() || in_array( $template_name, array( 'front-page.php', 'page.php', 'single.php' ), true );

	// GSAP нужен для прелоадера и анимаций на главной.
	$load_gsap = is_front_page() || in_array( $template_name, array( 'front-page.php' ), true );

	// Базовые скрипты (загружаются всегда).
	wp_enqueue_script( 'lubov-vera-navigation', get_template_directory_uri() . '/js/navigation.js', array(), _S_VERSION, true );

	// Preloader загружаем на всех страницах.
	wp_enqueue_script( 'lubov-vera-preloader', get_template_directory_uri() . '/assets/js/preloader.js', array(), _S_VERSION, true );

	// Условно загружаем Swiper.
	if ( $load_swiper ) {
		wp_enqueue_script( 'swiper-js', 'https://cdn.jsdelivr.net/npm/swiper@12/swiper-bundle.min.js', array(), '12', true );
		wp_enqueue_script( 'lubov-vera-script', get_template_directory_uri() . '/assets/js/script.js', array( 'swiper-js' ), _S_VERSION, true );
	}

	// Swiper для страницы строительства.
	if ( is_page_template( 'page-construction.php' ) ) {
		wp_enqueue_script( 'lubov-vera-swiper', get_template_directory_uri() . '/assets/js/swiper.js', array( 'swiper-js' ), _S_VERSION, true );
	}

	// Скрипт для страницы документов — загружается через $load_swiper (is_singular).

	// Условно загружаем GSAP.
	if ( $load_gsap ) {
		wp_enqueue_script( 'gsap', 'https://cdnjs.cloudflare.com/ajax/libs/gsap/3.11.4/gsap.min.js', array(), '3.11.4', true );
	}

	// Календарь и фильтр служений - на главной и страницах служений.
	if ( is_front_page() || is_page_template( 'page-ministries.php' ) || is_singular( 'ministry' ) ) {
		wp_enqueue_script( 'lubov-vera-calendar', get_template_directory_uri() . '/assets/js/calendar.js', array(), _S_VERSION, true );
		wp_enqueue_script( 'lubov-vera-services-filter', get_template_directory_uri() . '/assets/js/services-filter.js', array(), _S_VERSION, true );
	}
}
add_action( 'wp_enqueue_scripts', 'lubov_vera_scripts' );

require get_template_directory() . '/inc/class-church-walker-nav-menu.php';
require get_template_directory() . '/inc/menu-icons.php';

// Экран настроек темы: пункт меню «Настройки сайта». Класс сам вешает
// хуки admin_menu и admin_enqueue_scripts при подключении.
require_once get_template_directory() . '/inc/options/class-lv-theme-settings.php';

if ( ! function_exists( 'lv_track_post_views' ) ) {
	/**
	 * Считает просмотры записи и хранит их в мета-поле.
	 *
	 * @param int $post_id ID записи.
	 * @return void
	 */
	function lv_track_post_views( $post_id ) {
		if ( empty( $post_id ) || ! is_singular() || is_preview() ) {
			return;
		}

		$cookie_name = 'lv_viewed_posts';
		$viewed      = isset( $_COOKIE[ $cookie_name ] ) ? explode( ',', sanitize_text_field( wp_unslash( $_COOKIE[ $cookie_name ] ) ) ) : array();

		if ( in_array( (string) $post_id, $viewed, true ) ) {
			return;
		}

		$viewed[] = (string) $post_id;
		setcookie( $cookie_name, implode( ',', $viewed ), time() + YEAR_IN_SECONDS, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true );

		$count = get_post_meta( $post_id, 'lv_views_count', true );
		$count = ( '' === $count || false === $count ) ? 0 : (int) $count;
		++$count;
		update_post_meta( $post_id, 'lv_views_count', $count );
	}
	add_action(
		'wp',
		function () {
			if ( is_singular() ) {
				lv_track_post_views( get_queried_object_id() );
			}
		}
	);
}

if ( ! function_exists( 'lv_update_reading_time' ) ) {
	/**
	 * Считает и сохраняет время чтения записи в мета-поле.
	 *
	 * @param int $post_id ID записи.
	 * @return void
	 */
	function lv_update_reading_time( $post_id ) {
		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		$content      = get_post_field( 'post_content', $post_id );
		$word_count   = count( preg_split( '/\s+/u', wp_strip_all_tags( $content ), -1, PREG_SPLIT_NO_EMPTY ) );
		$reading_time = ceil( $word_count / 180 );

		if ( $reading_time < 1 ) {
			$reading_time = 1;
		}

		update_post_meta( $post_id, 'lv_reading_time_val', $reading_time );
	}
	add_action( 'save_post', 'lv_update_reading_time' );
}

if ( ! function_exists( 'lv_get_reading_time' ) ) {
	/**
	 * Возвращает строку времени чтения записи.
	 *
	 * @param int $post_id ID записи.
	 * @return string Время чтения с подписью.
	 */
	function lv_get_reading_time( $post_id ) {
		$time = get_post_meta( $post_id, 'lv_reading_time_val', true );
		if ( empty( $time ) ) {
			// Если мета-поле пустое (старый пост), рассчитываем на лету.
			$content    = get_post_field( 'post_content', $post_id );
			$word_count = count( preg_split( '/\s+/u', wp_strip_all_tags( $content ), -1, PREG_SPLIT_NO_EMPTY ) );
			$time       = ceil( $word_count / 180 ) ? ceil( $word_count / 180 ) : 1;
		}
		return lv_plural( (int) $time, array( 'минута', 'минуты', 'минут' ) ) . ' чтения';
	}
}

if ( ! function_exists( 'lv_get_post_views_display' ) ) {
	/**
	 * Возвращает отформатированное число просмотров записи.
	 *
	 * @param int $post_id ID записи.
	 * @return string Число просмотров с подписью.
	 */
	function lv_get_post_views_display( $post_id ) {
		$count = get_post_meta( $post_id, 'lv_views_count', true );
		$num   = ( '' === $count || false === $count ) ? 0 : (int) $count;

		if ( $num >= 1000 ) {
			return round( $num / 1000, 1 ) . 'K просмотров';
		}

		return lv_plural( $num, array( 'просмотр', 'просмотра', 'просмотров' ) );
	}
}

if ( ! function_exists( 'lv_plural' ) ) {
	/**
	 * Выбирает форму существительного по числу (русская плюрализация).
	 *
	 * @param int   $number Число.
	 * @param array $titles Формы слова: одна, две, много.
	 * @return string Число вместе с выбранной формой слова.
	 */
	function lv_plural( $number, $titles ) {
		$cases = array( 2, 0, 1, 1, 1, 2 );
		return $number . ' ' . $titles[ ( $number % 100 > 4 && $number % 100 < 20 ) ? 2 : $cases[ min( $number % 10, 5 ) ] ];
	}
}

if ( ! function_exists( 'lv_extract_rutube_id' ) ) {
	/**
	 * Извлекает ID видео из ссылки Rutube.
	 *
	 * @param string $url Ссылка на видео Rutube.
	 * @return string|null ID видео или null, если ссылка не распознана.
	 */
	function lv_extract_rutube_id( $url ) {
		if ( preg_match( '~rutube\.ru/video/([a-f0-9]+)/?~i', $url, $m ) ) {
			return $m[1];
		}
		if ( preg_match( '~rutube\.ru/play/embed/([a-f0-9]+)~i', $url, $m ) ) {
			return $m[1];
		}
		return null;
	}
}

if ( ! function_exists( 'lv_fetch_url' ) ) {
	/**
	 * Загружает тело ответа по URL с фолбэком на file_get_contents.
	 *
	 * @param string $url URL запроса.
	 * @return string Тело ответа или пустая строка.
	 */
	function lv_fetch_url( $url ) {
		$body     = '';
		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 10,
				'sslverify' => false,
				'headers'   => array( 'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36' ),
			)
		);
		if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
			$body = wp_remote_retrieve_body( $response );
		}
		if ( empty( $body ) ) {
			$ctx = stream_context_create(
				array(
					'http' => array(
						'timeout'    => 10,
						'user_agent' => 'Mozilla/5.0',
						'header'     => "Accept: application/json\r\n",
					),
					'ssl'  => array(
						'verify_peer'      => false,
						'verify_peer_name' => false,
					),
				)
			);
			// Fallback for environments where wp_remote_get() is blocked; behavior preserved.
			$body = @file_get_contents( $url, false, $ctx ); // phpcs:ignore WordPress.PHP.NoSilencedErrors.Discouraged, WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		}
		return $body;
	}
}

if ( ! function_exists( 'lv_get_latest_rutube_video' ) ) {
	/**
	 * Возвращает данные последнего видео Rutube (с транзиентным кэшем).
	 *
	 * @return array|null Данные видео или null, если видео нет.
	 */
	function lv_get_latest_rutube_video() {
		$cache_key = 'lv_latest_rutube_video';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$body = lv_fetch_url( 'https://rutube.ru/api/video/person/24631576/?page=1&per_page=1' );

		if ( ! empty( $body ) ) {
			$data = json_decode( $body, true );
			if ( ! empty( $data['results'][0] ) ) {
				$v      = $data['results'][0];
				$result = array(
					'id'           => $v['id'],
					'title'        => $v['title'] ?? 'Видео',
					'thumbnail'    => $v['thumbnail_url'] ?? '',
					'preview'      => $v['preview_url'] ?? '',
					'video_url'    => $v['video_url'],
					'embed_url'    => $v['embed_url'],
					'duration'     => lv_format_duration( $v['duration'] ?? 0 ),
					'duration_raw' => $v['duration'] ?? 0,
					'views'        => $v['hits'] ?? 0,
				);
				set_transient( $cache_key, $result, HOUR_IN_SECONDS );
				return $result;
			}
		}

		set_transient( $cache_key, null, 5 * MINUTE_IN_SECONDS );
		return null;
	}
}

if ( ! function_exists( 'lv_format_duration' ) ) {
	/**
	 * Форматирует длительность в секундах как время.
	 *
	 * @param int $seconds Длительность в секундах.
	 * @return string Отформатированная длительность.
	 */
	function lv_format_duration( $seconds ) {
		if ( $seconds >= 3600 ) {
			return gmdate( 'H:i:s', $seconds );
		}
		return gmdate( 'i:s', $seconds );
	}
}

if ( ! function_exists( 'lv_media_image' ) ) {
	/**
	 * Вывод картинки из медиатеки WordPress.
	 *
	 * Если URL принадлежит библиотеке файлов, отдаём через wp_get_attachment_image() —
	 * браузер получает адаптивные srcset и sizes, а не один фиксированный файл.
	 * Для внешних картинок (граватары, сторонние ссылки) возвращаем обычный <img>.
	 *
	 * @param string $url   URL картинки.
	 * @param string $size  Размер из медиатеки.
	 * @param array  $attr  Дополнительные атрибуты (class, alt, loading…).
	 * @return string
	 */
	function lv_media_image( $url, $size = 'large', $attr = array() ) {
		if ( '' === trim( (string) $url ) ) {
			return '';
		}

		$id = attachment_url_to_postid( $url );

		if ( $id ) {
			return wp_get_attachment_image( $id, $size, false, $attr );
		}

		$html = '<img src="' . esc_url( $url ) . '"';

		foreach ( $attr as $k => $v ) {
			if ( 'src' === $k ) {
				continue;
			}
			$html .= ' ' . esc_attr( $k ) . '="' . esc_attr( $v ) . '"';
		}

		$html .= ' decoding="async"';

		// Не дублируем loading, если его уже задали в $attr (например, для hero).
		if ( ! isset( $attr['loading'] ) ) {
			$html .= ' loading="lazy"';
		}

		$html .= ' />';

		return $html;
	}
}

if ( ! function_exists( 'lv_cards_settings' ) ) {
	/**
	 * Настройки карточек для раздела, всегда с полным набором ключей.
	 *
	 * В опции эти группы (ministries_cards, testimonies_cards) заведены как
	 * обычный массив с пустым значением по умолчанию, поэтому на сайте их может
	 * не быть вовсе. Шаблоны же читают из них около пятнадцати ключей напрямую —
	 * без этого помощника каждая карточка сыпала бы в HTML десяток
	 * "Undefined array key" и теряла фото, заголовок, автора и дату.
	 *
	 * Отсутствие сохранённых настроек трактуем как «показывать всё».
	 *
	 * @param string $key Ключ группы, например ministries_cards.
	 * @return array
	 */
	function lv_cards_settings( $key ) {
		$defaults = array(
			'posts_per_page'     => 12,
			'columns'            => 3,
			'desc_trim'          => 200,
			'image_size'         => 'medium',
			'readmore_text'      => '',
			'avatar_image'       => '',
			'show_avatar_single' => '1',
			'show_photo'         => '1',
			'show_title'         => '1',
			'show_desc'          => '1',
			'show_author'        => '1',
			'show_date'          => '1',
			'show_avatar'        => '1',
			'show_reading_time'  => '1',
			'show_views'         => '1',
			'show_readmore'      => '1',
		);

		$group = lv_get_setting( $key, array() );

		if ( ! is_array( $group ) ) {
			$group = array();
		}

		return array_merge( $defaults, $group );
	}
}

if ( ! function_exists( 'lv_cards_per_page' ) ) {
	/**
	 * Сколько записей показывать на странице раздела.
	 *
	 * @param string $key      Ключ группы, например ministries_cards.
	 * @param int    $fallback Сколько показывать, если значения нет или оно пустое.
	 * @return int Количество записей на странице.
	 */
	function lv_cards_per_page( $key, $fallback = 12 ) {
		$fallback = absint( $fallback );

		if ( $fallback < 1 ) {
			$fallback = 12;
		}

		$per_page = absint( lv_cards_settings( $key )['posts_per_page'] );

		return $per_page > 0 ? $per_page : $fallback;
	}
}

/**
 * =============================================================================
 * SECURITY: Заголовки, rate-limiting, аудит, защита cookies
 * =============================================================================
 */

/**
 * Security Headers (CSP, HSTS, X-Frame-Options, etc.)
 * Настраивается через фильтр, чтобы можно было переопределить в дочерней теме.
 */
add_action(
	'init',
	function () {
		$GLOBALS['lv_csp_nonce'] = base64_encode( wp_create_nonce( 'lv_csp_nonce' ) ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}
);

add_filter(
	'wp_headers',
	function ( $headers ) {
		$is_admin = false;
		if ( function_exists( 'is_admin' ) ) {
			$is_admin = is_admin();
		}
		if ( ! $is_admin ) {
			$raw_request_uri   = filter_input( INPUT_SERVER, 'REQUEST_URI', FILTER_UNSAFE_RAW );
			$raw_request_uri   = '' === $raw_request_uri ? '' : $raw_request_uri;
			$server_request_uri = wp_unslash( $raw_request_uri );
			$request_uri        = $server_request_uri;
			$is_admin           = 0 === strpos( $request_uri, '/wp-admin/' );
		}

		if ( headers_sent() || $is_admin ) {
			return $headers;
		}

		$nonce = $GLOBALS['lv_csp_nonce'];

		$csp = implode(
			'; ',
			array(
				"default-src 'self'",
				"script-src 'self' 'nonce-{$nonce}' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com 'unsafe-inline'",
				"style-src 'self' 'unsafe-inline' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com",
				"font-src 'self' data: https://fonts.gstatic.com https://cdnjs.cloudflare.com",
				"img-src 'self' data: https:",
				"connect-src 'self' https://rutube.ru https://cdn.jsdelivr.net",
				"frame-src 'self' https://rutube.ru https://www.youtube.com https://www.youtube-nocookie.com",
				"object-src 'none'",
				"base-uri 'self'",
				"form-action 'self'",
				"frame-ancestors 'none'",
				'upgrade-insecure-requests',
			)
		);

		$headers['Content-Security-Policy']           = $csp;
		$headers['X-Content-Type-Options']            = 'nosniff';
		$headers['X-Frame-Options']                   = 'SAMEORIGIN';
		$headers['Referrer-Policy']                   = 'strict-origin-when-cross-origin';
		$headers['Permissions-Policy']                = 'camera=(), microphone=(), geolocation=()';
		$headers['X-Permitted-Cross-Domain-Policies'] = 'none';

		if ( is_ssl() ) {
			$headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains; preload';
		}

		return $headers;
	}
);

/**
 * Rate limiting для AJAX-экшенов настроек (save/reset)
 * Использует transient с ключом по IP + action.
 */
add_action(
	'check_admin_referer',
	function ( $action, $result ) {
		if ( ! $result ) {
			return;
		}

		// check_admin_referer вызывается с action = 'lv_options' (nonce action).
		$limited_actions = array( 'lv_options' );
		if ( ! in_array( $action, $limited_actions, true ) ) {
			return;
		}

		$ip       = lv_get_client_ip();
		$key      = 'lv_ratelimit_save_' . md5( $ip );
		$limit    = 30;
		$window   = MINUTE_IN_SECONDS;
		$current  = (int) get_transient( $key );

		if ( $current >= $limit ) {
			wp_die( esc_html__( 'Слишком много запросов. Попробуйте позже.', 'lubov-vera' ), 429 );
		}

		set_transient( $key, $current + 1, $window );
	},
	10,
	2
);

/**
 * Получение IP клиента с учётом прокси/Cloudflare
 */
function lv_get_client_ip() {
	$ip_headers = array(
		'HTTP_CF_CONNECTING_IP',
		'HTTP_X_FORWARDED_FOR',
		'HTTP_X_REAL_IP',
		'HTTP_CLIENT_IP',
		'REMOTE_ADDR',
	);

	foreach ( $ip_headers as $header ) {
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- $_SERVER access sanitized immediately via wp_unslash()
		$server_val = isset( $_SERVER[ $header ] ) ? wp_unslash( $_SERVER[ $header ] ) : '';
		if ( ! empty( $server_val ) ) {
			$ips = explode( ',', $server_val );
			$ip  = trim( $ips[0] );
			if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE ) ) {
				return $ip;
			}
		}
	}
	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- $_SERVER access sanitized immediately via wp_unslash()
	return wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1' );
}

/**
 * Аудит изменений настроек (lv_site_settings).
 * Пишет в опцию lv_settings_audit_log.
 *
 * @param array  $old_settings Значения до изменения.
 * @param array  $new_settings Значения после изменения.
 * @param string $action       Тип действия: 'save' | 'reset'.
 * @return void
 */
function lv_audit_settings_change( $old_settings, $new_settings, $action = 'save' ) {
	if ( ! current_user_can( 'edit_theme_options' ) ) {
		return;
	}

	$diff     = array();
	$all_keys = array_unique( array_merge( array_keys( $old_settings ), array_keys( $new_settings ) ) );

	foreach ( $all_keys as $key ) {
		$old_v = isset( $old_settings[ $key ] ) ? $old_settings[ $key ] : null;
		$new_v = isset( $new_settings[ $key ] ) ? $new_settings[ $key ] : null;

		if ( ! is_scalar( $old_v ) ) {
			$old_v = wp_json_encode( $old_v );
		}
		if ( ! is_scalar( $new_v ) ) {
			$new_v = wp_json_encode( $new_v );
		}

		if ( $old_v !== $new_v ) {
			$diff[ $key ] = array(
				'old' => $old_v,
				'new' => $new_v,
			);
		}
	}

	if ( empty( $diff ) ) {
		return;
	}

	$log = get_option( 'lv_settings_audit_log', array() );
	if ( ! is_array( $log ) ) {
		$log = array();
	}

	// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- $_SERVER access sanitized immediately via wp_unslash()
	$user_agent = isset( $_SERVER['HTTP_USER_AGENT'] ) ? wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) : '';
	$log[]      = array(
		'timestamp' => current_time( 'mysql', true ),
		'user_id'   => get_current_user_id(),
		'action'    => $action,
		'ip'        => lv_get_client_ip(),
		'ua'        => $user_agent ? substr( $user_agent, 0, 255 ) : '',
		'diff'      => $diff,
	);

	if ( count( $log ) > 500 ) {
		$log = array_slice( $log, -500 );
	}

	update_option( 'lv_settings_audit_log', $log );
}

/**
 * Хук аудита при сохранении настроек
 */
add_action(
	'lv_options_save_before',
	function ( $old_settings, $new_settings ) {
		lv_audit_settings_change( $old_settings, $new_settings, 'save' );
	},
	10,
	2
);

add_action(
	'lv_options_reset_before',
	function ( $old_settings ) {
		lv_audit_settings_change( $old_settings, array(), 'reset' );
	},
	10,
	1
);

/**
 * Безопасные куки
 */
add_filter(
	'setcookie_params',
	function ( $params ) {
		if ( is_ssl() ) {
			$params['secure'] = true;
		}
		$params['httponly'] = true;
		$params['samesite'] = 'Lax';
		return $params;
	}
);

/**
 * Скрываем версию WP
 */
remove_action( 'wp_head', 'wp_generator' );
add_filter( 'the_generator', '__return_empty_string' );

/**
 * Удаляем emoji
 */
remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
remove_action( 'wp_print_styles', 'print_emoji_styles' );
remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
remove_action( 'admin_print_styles', 'print_emoji_styles' );

/**
 * Минимальная длина пароля
 */
add_filter(
	'wp_insert_user_data',
	function ( $data, $update, $_user_id ) {
		if ( ! $update && isset( $data['user_pass'] ) && strlen( $data['user_pass'] ) < 12 ) {
			return new WP_Error( 'weak_password', 'Пароль должен быть не короче 12 символов.' );
		}
		return $data;
	},
	10,
	3
);

/**
 * Принудительный logout после смены пароля
 */
add_action(
	'password_reset',
	function ( $_user, $_new_pass ) {
		wp_destroy_all_sessions();
	},
	10,
	2
);

/**
 * Логирование неудачных входов
 */
add_action(
	'wp_login_failed',
	function ( $username ) {
		$ip   = lv_get_client_ip();
		$key  = 'lv_login_failed_' . md5( $ip );
		$fail = (int) get_transient( $key ) + 1;
		set_transient( $key, $fail, 15 * MINUTE_IN_SECONDS );

		if ( $fail >= 5 ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( sprintf( '[LV Security] Brute-force detected from IP %s (user: %s)', $ip, $username ) );
		}
	}
);

/**
 * Очистка сессий при выходе
 */
add_action(
	'wp_logout',
	function () {
		wp_destroy_all_sessions();
	}
);