<?php
/**
 * Публичный API для темы.
 *
 * Единственный источник данных — LV_Core\Options (опция lv_site_settings).
 * Хелперы ничего не пишут и не ходят в базу напрямую.
 *
 * КАЖДЫЙ хелпер объявлен в своём if ( ! function_exists( ... ) ) — не одним
 * общим блоком на весь файл. Тема (functions.php) объявляет lv_get_setting
 * раньше плагина в некоторых запросах; общий guard подавил бы остальные
 * функции, и они остались бы неопределёнными.
 *
 * Хелперы, которые зовёт тема: lv_get_setting, lv_get_phone, lv_get_email,
 * lv_get_address, lv_get_hero_data, lv_get_timer_data, lv_get_cards_data,
 * lv_get_social_links. Остальные — публичный API на будущее (пункт 2).
 *
 * @package LV_Core
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'lv_get_setting' ) ) {
	/**
	 * Чтение любой настройки. Второй аргумент — произвольный дефолт
	 * (тема передаёт array() для повторяющихся блоков).
	 *
	 * @param string $key      Ключ.
	 * @param mixed  $fallback Значение по умолчанию.
	 * @return mixed
	 */
	function lv_get_setting( $key, $fallback = '' ) {
		if ( ! class_exists( '\LV_Core\Options' ) ) {
			return $fallback;
		}

		return \LV_Core\Options::get_instance()->get( $key, $fallback );
	}
}

if ( ! function_exists( 'lv_option' ) ) {
	/**
	 * Синоним lv_get_setting(). Зарезервирован под посекционные переопределения
	 * из мета _lv_opt_{key} — слой появится вместе с мета-боксами (пункт 2).
	 *
	 * @param string $key      Ключ.
	 * @param mixed  $fallback Значение по умолчанию.
	 * @return mixed
	 */
	function lv_option( $key, $fallback = '' ) {
		return lv_get_setting( $key, $fallback );
	}
}

/* ------------------------------------------------------ Hero */

if ( ! function_exists( 'lv_get_hero_data' ) ) {
	/**
	 * Данные hero-секции.
	 *
	 * @return array{badge:string,title:string,desc:string,video:string,image:string}
	 */
	function lv_get_hero_data() {
		return array(
			'badge' => (string) lv_get_setting( 'hero_badge', '' ),
			'title' => (string) lv_get_setting( 'hero_title', '' ),
			'desc'  => (string) lv_get_setting( 'hero_desc', '' ),
			'video' => (string) lv_get_setting( 'hero_video', '' ),
			'image' => (string) lv_get_setting( 'hero_image', '' ),
		);
	}
}

if ( ! function_exists( 'lv_get_cards_data' ) ) {
	/**
	 * Три карточки под hero. Совсем пустые (без заголовка и описания) отбрасываются.
	 *
	 * @return array<int,array{title:string,desc:string,icon:string,link:string}>
	 */
	function lv_get_cards_data() {
		$cards = array();

		for ( $i = 1; $i <= 3; $i++ ) {
			$card = array(
				'title' => (string) lv_get_setting( "card_{$i}_title", '' ),
				'desc'  => (string) lv_get_setting( "card_{$i}_desc", '' ),
				'icon'  => (string) lv_get_setting( "card_{$i}_icon", '' ),
				'link'  => (string) lv_get_setting( "card_{$i}_link", '' ),
			);

			if ( '' === trim( $card['title'] ) && '' === trim( $card['desc'] ) ) {
				continue;
			}

			$cards[] = $card;
		}

		return $cards;
	}
}

if ( ! function_exists( 'lv_get_timer_data' ) ) {
	/**
	 * Таймер эфира. Дата — строка дд.мм.гггг, время — чч:мм.
	 *
	 * @return array{title:string,date:string,time:string}
	 */
	function lv_get_timer_data() {
		return array(
			'title' => (string) lv_get_setting( 'timer_title', '' ),
			'date'  => (string) lv_get_setting( 'timer_date', '' ),
			'time'  => (string) lv_get_setting( 'timer_time', '' ),
		);
	}
}

/* ---------------------------------------------------- Повторяющиеся блоки */

if ( ! function_exists( 'lv_get_social_links' ) ) {
	/**
	 * Соцсети: список {url, type}. Строки без url или type отбрасываются.
	 * Тема строит иконку как assets/icons/{type}.svg.
	 *
	 * @return array<int,array{url:string,type:string}>
	 */
	function lv_get_social_links() {
		$rows = lv_get_setting( 'socials', array() );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$socials = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$url  = isset( $row['url'] ) ? (string) $row['url'] : '';
			$type = isset( $row['type'] ) ? (string) $row['type'] : '';

			if ( '' === trim( $url ) || '' === trim( $type ) ) {
				continue;
			}

			// Тип подставляется в путь assets/icons/{type}.svg, поэтому
			// пропускаем всё, что не является простым именем файла из
			// этой папки: иначе в URL попадёт ../../ и обход каталога.
			if ( ! preg_match( '/^[a-z0-9_-]+$/i', $type ) ) {
				continue;
			}

			$icon = get_theme_file_path( 'assets/icons/' . $type . '.svg' );

			if ( ! file_exists( $icon ) ) {
				continue;
			}

			$socials[] = array(
				'url'  => $url,
				'type' => $type,
			);
		}

		return $socials;
	}
}

if ( ! function_exists( 'lv_get_documents_list' ) ) {
	/**
	 * Документы: список {file, title}. Строки без файла отбрасываются —
	 * шаблон всё равно их пропускает, но фильтруем сразу, чтобы не отдавать мусор.
	 *
	 * @return array<int,array{file:string,title:string}>
	 */
	function lv_get_documents_list() {
		$rows = lv_get_setting( 'documents', array() );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$docs = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$file = isset( $row['file'] ) ? (string) $row['file'] : '';

			if ( '' === trim( $file ) ) {
				continue;
			}

			$docs[] = array(
				'file'  => $file,
				'title' => isset( $row['title'] ) ? (string) $row['title'] : '',
			);
		}

		return $docs;
	}
}

if ( ! function_exists( 'lv_get_events_data' ) ) {
	/**
	 * События для карусели на главной: список {title, author_name, author_avatar,
	 * date, desc, image, link}. Строки без названия отбрасываются — событие
	 * без названия показывать нечем.
	 *
	 * @return array<int,array{title:string,author_name:string,author_avatar:string,date:string,desc:string,image:string,link:string}>
	 */
	function lv_get_events_data() {
		$rows = lv_get_setting( 'events', array() );

		if ( ! is_array( $rows ) ) {
			return array();
		}

		$events = array();

		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$title = isset( $row['title'] ) ? (string) $row['title'] : '';

			if ( '' === trim( $title ) ) {
				continue;
			}

			$events[] = array(
				'title'         => $title,
				'author_name'   => isset( $row['author_name'] ) ? (string) $row['author_name'] : '',
				'author_avatar' => isset( $row['author_avatar'] ) ? (string) $row['author_avatar'] : '',
				'date'          => isset( $row['date'] ) ? (string) $row['date'] : '',
				'desc'          => isset( $row['desc'] ) ? (string) $row['desc'] : '',
				'image'         => isset( $row['image'] ) ? (string) $row['image'] : '',
				'link'          => isset( $row['link'] ) ? (string) $row['link'] : '',
			);
		}

		return $events;
	}
}

/* -------------------------------------------------------- Контакты */

if ( ! function_exists( 'lv_get_phone' ) ) {
	/**
	 * Телефон в исходном виде; шаблон сам чистит его для tel:.
	 *
	 * @return string
	 */
	function lv_get_phone() {
		return (string) lv_get_setting( 'phone', '' );
	}
}

if ( ! function_exists( 'lv_get_email' ) ) {
	/**
	 * Электронная почта сайта.
	 *
	 * @return string
	 */
	function lv_get_email() {
		return (string) lv_get_setting( 'email', '' );
	}
}

if ( ! function_exists( 'lv_get_address' ) ) {
	/**
	 * Почтовый адрес сайта.
	 *
	 * @return string
	 */
	function lv_get_address() {
		return (string) lv_get_setting( 'address', '' );
	}
}

/* --------------------------------------------------------- Прочее */

if ( ! function_exists( 'lv_get_donation_widget_id' ) ) {
	/**
	 * ID виджета доната. Пустая строка, если не задан.
	 *
	 * @return string
	 */
	function lv_get_donation_widget_id() {
		return (string) lv_get_setting( 'donation_mixplat', '' );
	}
}

if ( ! function_exists( 'lv_get_legal_policy_url' ) ) {
	/**
	 * Ссылка на страницу политики. Пустая строка, если страница не выбрана.
	 *
	 * @return string
	 */
	function lv_get_legal_policy_url() {
		$id = absint( lv_get_setting( 'legal_policy', 0 ) );

		if ( ! $id ) {
			return '';
		}

		$url = get_permalink( $id );

		return $url ? $url : '';
	}
}

if ( ! function_exists( 'lv_get_legal_policy_text' ) ) {
	/**
	 * Текст политики (содержимое выбранной страницы). Пустая строка, если страницы нет.
	 * Возвращается «как есть» — экранирует шаблон.
	 *
	 * @return string
	 */
	function lv_get_legal_policy_text() {
		$id = absint( lv_get_setting( 'legal_policy', 0 ) );

		if ( ! $id ) {
			return '';
		}

		return (string) get_post_field( 'post_content', $id );
	}
}

if ( ! function_exists( 'lv_sanitize_svg_content' ) ) {
	/**
	 * Очистка SVG перед выводом: вырезаем скрипты, обработчики событий и javascript:-ссылки.
	 * Если в строке нет тега <svg> — возвращаем пустую строку.
	 *
	 * @param string $svg Исходная разметка.
	 * @return string
	 */
	function lv_sanitize_svg_content( $svg ) {
		$svg = wp_check_invalid_utf8( (string) $svg );

		if ( '' === trim( $svg ) ) {
			return '';
		}

		// Опасные элементы вместе с содержимым.
		$svg = preg_replace(
			'#<\s*(script|foreignobject|iframe|embed|object|animate|set)\b[^>]*>.*?<\s*/\s*\1\s*>#is',
			'',
			$svg
		);

		// Незакрытые / одиночные опасные элементы.
		$svg = preg_replace( '#<\s*/?\s*(script|foreignobject|iframe|embed|object|animate|set)\b[^>]*>#is', '', $svg );

		// Убираем инлайновые обработчики событий — атрибуты, начинающиеся с on.
		$svg = preg_replace( '/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $svg );

		// javascript: в ссылках и src.
		$svg = preg_replace(
			'/(href|xlink:href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i',
			'',
			$svg
		);

		// Если это вообще не SVG — не отдаём.
		if ( false === stripos( $svg, '<svg' ) ) {
			return '';
		}

		return $svg;
	}
}
