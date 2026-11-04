<?php
/**
 * Расписание служений: единый источник для календаря, подвала и виджета.
 *
 * Данные берутся из настройки schedule (раздел «Расписание» экрана настроек).
 * Пока список пуст, используется запасной набор, который исторически был
 * зашит в calendar.js, — чтобы главная не осталась без расписания.
 *
 * @package lubov-vera
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'lv_schedule_default_items' ) ) {
	/**
	 * Запасное расписание, если настройка schedule пуста.
	 *
	 * @return array
	 */
	function lv_schedule_default_items() {
		return array(
			array(
				'day'  => 'Пятница',
				'time' => '19:00',
				'text' => 'Молодёжное служение',
			),
			array(
				'day'  => 'Воскресенье',
				'time' => '10:00',
				'text' => 'Общецерковное служение',
			),
			array(
				'day'  => 'Воскресенье',
				'time' => '12:00',
				'text' => 'Общецерковное служение',
			),
			array(
				'day'  => 'Воскресенье',
				'time' => '17:00',
				'text' => 'Общецерковное служение',
			),
		);
	}
}

if ( ! function_exists( 'lv_schedule_normalize_day' ) ) {
	/**
	 * Приводит название дня к нижнему регистру без хвостовой пунктуации.
	 *
	 * @param string $day Название дня.
	 * @return string
	 */
	function lv_schedule_normalize_day( $day ) {
		$day = rtrim( trim( (string) $day ), ' .,' );

		if ( function_exists( 'mb_strtolower' ) ) {
			return mb_strtolower( $day, 'UTF-8' );
		}

		return strtolower( $day );
	}
}

if ( ! function_exists( 'lv_schedule_day_index' ) ) {
	/**
	 * Индекс дня недели в формате JS getDay() (0 — воскресенье).
	 *
	 * @param string $day Название дня из настроек.
	 * @return int|null
	 */
	function lv_schedule_day_index( $day ) {
		$map = array(
			'воскресенье' => 0,
			'воскресение' => 0,
			'вс'          => 0,
			'понедельник' => 1,
			'пн'          => 1,
			'вторник'     => 2,
			'вт'          => 2,
			'среда'       => 3,
			'среду'       => 3,
			'ср'          => 3,
			'четверг'     => 4,
			'чт'          => 4,
			'пятница'     => 5,
			'пятницу'     => 5,
			'пт'          => 5,
			'суббота'     => 6,
			'субботу'     => 6,
			'сб'          => 6,
		);

		$key = lv_schedule_normalize_day( $day );

		return array_key_exists( $key, $map ) ? $map[ $key ] : null;
	}
}

if ( ! function_exists( 'lv_schedule_day_short' ) ) {
	/**
	 * Короткая подпись дня для компактных мест вроде подвала.
	 *
	 * @param string $day Название дня.
	 * @return string
	 */
	function lv_schedule_day_short( $day ) {
		$short = array( 'Вс', 'Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб' );
		$index = lv_schedule_day_index( $day );

		return null !== $index ? $short[ $index ] : (string) $day;
	}
}

if ( ! function_exists( 'lv_schedule_split_times' ) ) {
	/**
	 * Разбивает строку времени на несколько значений (10:00, 12:00).
	 *
	 * @param string $time Строка времени.
	 * @return array
	 */
	function lv_schedule_split_times( $time ) {
		$parts = preg_split( '/\s*[,;\/]\s*/u', (string) $time );
		$out   = array();

		foreach ( (array) $parts as $part ) {
			$part = trim( $part );

			if ( '' !== $part ) {
				$out[] = $part;
			}
		}

		return $out;
	}
}

if ( ! function_exists( 'lv_schedule_normalize_time' ) ) {
	/**
	 * Приводит время к виду чч:мм или возвращает пустую строку.
	 *
	 * @param string $time Сырое время.
	 * @return string
	 */
	function lv_schedule_normalize_time( $time ) {
		if ( ! preg_match( '/^(\d{1,2})[:.](\d{2})$/', trim( (string) $time ), $matches ) ) {
			return '';
		}

		$hours   = (int) $matches[1];
		$minutes = (int) $matches[2];

		if ( $hours < 0 || $hours > 23 || $minutes < 0 || $minutes > 59 ) {
			return '';
		}

		return sprintf( '%02d:%02d', $hours, $minutes );
	}
}

if ( ! function_exists( 'lv_get_schedule_items' ) ) {
	/**
	 * Нормализованные строки расписания (с запасным набором).
	 *
	 * @return array
	 */
	function lv_get_schedule_items() {
		$raw   = function_exists( 'lv_get_setting' ) ? lv_get_setting( 'schedule', array() ) : array();
		$items = array();

		if ( is_array( $raw ) ) {
			foreach ( $raw as $row ) {
				if ( ! is_array( $row ) ) {
					continue;
				}

				$day  = isset( $row['day'] ) ? trim( (string) $row['day'] ) : '';
				$time = isset( $row['time'] ) ? trim( (string) $row['time'] ) : '';
				$text = isset( $row['text'] ) ? trim( (string) $row['text'] ) : '';

				if ( '' === $day || '' === $time ) {
					continue;
				}

				foreach ( lv_schedule_split_times( $time ) as $single_time ) {
					$items[] = array(
						'day'  => $day,
						'time' => $single_time,
						'text' => $text,
					);
				}
			}
		}

		return ! empty( $items ) ? $items : lv_schedule_default_items();
	}
}

if ( ! function_exists( 'lv_get_schedule_calendar_events' ) ) {
	/**
	 * События для клиентского календаря: день недели, время, название.
	 *
	 * @return array
	 */
	function lv_get_schedule_calendar_events() {
		$events = array();

		foreach ( lv_get_schedule_items() as $item ) {
			$weekday = lv_schedule_day_index( $item['day'] );
			$time    = lv_schedule_normalize_time( $item['time'] );

			if ( null === $weekday || '' === $time ) {
				continue;
			}

			$events[] = array(
				'weekday' => $weekday,
				'time'    => $time,
				'title'   => '' !== $item['text'] ? $item['text'] : 'Служение',
			);
		}

		return $events;
	}
}

if ( ! function_exists( 'lv_get_schedule_groups' ) ) {
	/**
	 * Расписание, сгруппированное по дням недели (для подвала и виджета).
	 *
	 * @return array
	 */
	function lv_get_schedule_groups() {
		$groups = array();

		foreach ( lv_get_schedule_items() as $item ) {
			$index = lv_schedule_day_index( $item['day'] );
			$key   = null !== $index ? 'day-' . $index : 'day-' . lv_schedule_normalize_day( $item['day'] );

			if ( ! isset( $groups[ $key ] ) ) {
				$groups[ $key ] = array(
					'day'   => $item['day'],
					'short' => lv_schedule_day_short( $item['day'] ),
					'times' => array(),
				);
			}

			if ( ! in_array( $item['time'], $groups[ $key ]['times'], true ) ) {
				$groups[ $key ]['times'][] = $item['time'];
			}
		}

		return array_values( $groups );
	}
}
