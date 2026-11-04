<?php
/**
 * Схема экрана настроек: разделы → поля.
 *
 * Источник правды для каркаса. Поля читаются из той же опции lv_site_settings,
 * которую уже читает фронт, — значение приходит через $saved, оно НЕ пишется
 * самим экраном (запись появится на этапе 2).
 *
 * Структура раздела:
 *   key     — идентификатор раздела (совпадает с data-panel и с hash)
 *   label   — подпись в левом меню и в крошках
 *   fields  — key => [ type, label, desc, ... ]
 *
 * Типы: text, textarea, email, url, page_select.
 *
 * @package LV_Theme
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'lv_theme_options_schema' ) ) {
	/**
	 * Возвращает схему разделов и полей экрана настроек.
	 *
	 * @return array Схема разделов.
	 */
	function lv_theme_options_schema() {
		return array(

			/* ------------------------------------------------- Главная */
			'main'          => array(
				'label'  => 'Главная',
				'fields' => array(
					'hero_badge'     => array(
						'type'  => 'text',
						'label' => 'Бейдж над заголовком',
						'desc'  => 'Короткая строка перед заголовком hero, например «Вера, действующая любовью»',
					),
					'hero_title'     => array(
						'type'  => 'text',
						'label' => 'Заголовок hero',
						'desc'  => 'Главный заголовок на первом экране',
					),
					'hero_desc'      => array(
						'type'  => 'textarea',
						'label' => 'Описание hero',
						'desc'  => 'Текст под заголовком',
						'rows'  => 3,
					),
					'hero_image'     => array(
						'type'        => 'media',
						'label'       => 'Картинка hero',
						'desc'        => 'Показывается справа. Если не выбрана — берётся картинка темы по умолчанию.',
						'placeholder' => 'https://example.com/hero.jpg',
					),
					'timer_title'    => array(
						'type'  => 'text',
						'label' => 'Заголовок таймера',
						'desc'  => 'Если пусто — блок таймера не выводится',
					),
					'timer_date'     => array(
						'type'        => 'text',
						'label'       => 'Дата эфира',
						'desc'        => 'Формат строго дд.мм.гггг, например 09.07.2026',
						'placeholder' => 'дд.мм.гггг',
					),
					'timer_time'     => array(
						'type'        => 'text',
						'label'       => 'Время эфира',
						'desc'        => 'Формат чч:мм, например 19:00. Если пусто — 19:00.',
						'placeholder' => 'чч:мм',
					),
					'posts_title'    => array(
						'type'  => 'text',
						'label' => 'Заголовок блока публикаций',
						'desc'  => 'Над каруселью анонсов на главной',
					),
					'posts_subtitle' => array(
						'type'  => 'text',
						'label' => 'Подзаголовок блока публикаций',
					),
				),
			),

			/* ------------------------------------------------ Карточки */
			'cards'         => array(
				'label'  => 'Карточки',
				'desc'   => 'Три карточки под hero. Блок исчезает, если у карточки нет заголовка и описания. Ссылка нужна обеим.',
				'fields' => array(
					'card_1_title' => array(
						'type'  => 'text',
						'label' => 'Карточка 1 — заголовок',
					),
					'card_1_desc'  => array(
						'type'  => 'textarea',
						'label' => 'Карточка 1 — описание',
						'rows'  => 3,
					),
					'card_1_icon'  => array(
						'type'  => 'text',
						'label' => 'Карточка 1 — иконка',
						'desc'  => 'URL картинки. Нужен файл в теме, если он лежит в assets/icons/ — тогда иконка вставится прямо в разметку',
					),
					'card_1_link'  => array(
						'type'  => 'url',
						'label' => 'Карточка 1 — ссылка',
						'desc'  => 'Пусто — карточка останется без клика',
					),
					'card_2_title' => array(
						'type'  => 'text',
						'label' => 'Карточка 2 — заголовок',
					),
					'card_2_desc'  => array(
						'type'  => 'textarea',
						'label' => 'Карточка 2 — описание',
						'rows'  => 3,
					),
					'card_2_icon'  => array(
						'type'  => 'text',
						'label' => 'Карточка 2 — иконка',
					),
					'card_2_link'  => array(
						'type'  => 'url',
						'label' => 'Карточка 2 — ссылка',
					),
					'card_3_title' => array(
						'type'  => 'text',
						'label' => 'Карточка 3 — заголовок',
					),
					'card_3_desc'  => array(
						'type'  => 'textarea',
						'label' => 'Карточка 3 — описание',
						'rows'  => 3,
					),
					'card_3_icon'  => array(
						'type'  => 'text',
						'label' => 'Карточка 3 — иконка',
					),
					'card_3_link'  => array(
						'type'  => 'url',
						'label' => 'Карточка 3 — ссылка',
					),
				),
			),

			/* ----------------------------------------------- Контакты */
			'contacts'      => array(
				'label'  => 'Контакты',
				'desc'   => 'Выводятся в шапке и в подвале на всём сайте.',
				'fields' => array(
					'phone'   => array(
						'type'  => 'text',
						'label' => 'Телефон',
						'desc'  => 'В ссылку tel: попадут только цифры и плюс',
					),
					'email'   => array(
						'type'  => 'email',
						'label' => 'E-mail',
						'desc'  => 'Показывается в подвале',
					),
					'address' => array(
						'type'  => 'textarea',
						'label' => 'Адрес',
						'rows'  => 3,
					),
				),
			),

			/* ------------------------------------------------- Соцсети */
			'socials'       => array(
				'label'  => 'Соцсети',
				'desc'   => 'Иконки под hero. Тип ограничен теми соцсетям, для которых есть значок в теме.',
				'fields' => array(
					'socials' => array(
						'type'      => 'repeater',
						'label'     => 'Ссылки на соцсети',
						'desc'      => 'Без строк иконки не выводятся.',
						'add_label' => '+ Добавить соцсеть',
						'subfields' => array(
							array(
								'key'   => 'url',
								'type'  => 'url',
								'label' => 'Ссылка',
								'wide'  => true,
							),
							array(
								'key'     => 'type',
								'type'    => 'select',
								'label'   => 'Тип',
								'options' => array(
									'vk'      => 'ВКонтакте',
									'youtube' => 'YouTube',
									'rutube'  => 'Rutube',
								),
							),
						),
					),
				),
			),

			/* ---------------------------------------------- Расписание */
			'schedule'      => array(
				'label'  => 'Расписание',
				'desc'   => 'Блок «Расписание эфиров» на главной. Без строк не выводится.',
				'fields' => array(
					'schedule_title' => array(
						'type'  => 'text',
						'label' => 'Заголовок блока',
					),
					'schedule'       => array(
						'type'      => 'repeater',
						'label'     => 'Трансляции',
						'add_label' => '+ Добавить трансляцию',
						'subfields' => array(
							array(
								'key'         => 'day',
								'type'        => 'text',
								'label'       => 'День',
								'placeholder' => 'Воскресенье',
							),
							array(
								'key'         => 'time',
								'type'        => 'text',
								'label'       => 'Время',
								'placeholder' => '10:00',
							),
							array(
								'key'   => 'text',
								'type'  => 'text',
								'label' => 'Описание',
								'wide'  => true,
							),
						),
					),
				),
			),

			/* -------------------------------------------------- Разделы */
			'pages'         => array(
				'label'  => 'Разделы и архивы',
				'desc'   => 'Заголовки и описания над архивами «Свидетельства» и «Служения».',
				'fields' => array(
					'testimonies_title' => array(
						'type'  => 'text',
						'label' => 'Свидетельства — заголовок',
					),
					'testimonies_desc'  => array(
						'type'  => 'textarea',
						'label' => 'Свидетельства — описание',
						'rows'  => 3,
					),
					'ministries_title'  => array(
						'type'  => 'text',
						'label' => 'Служения — заголовок',
					),
					'ministries_desc'   => array(
						'type'  => 'textarea',
						'label' => 'Служения — описание',
						'rows'  => 3,
					),
					'broadcasts_title'  => array(
						'type'  => 'text',
						'label' => 'Трансляции — заголовок',
					),
					'broadcasts_desc'   => array(
						'type'  => 'textarea',
						'label' => 'Трансляции — описание',
						'rows'  => 3,
					),
				),
			),

			/* ---------------------------------------------- Трансляции */
			'broadcasts'    => array(
				'label'  => 'Настройки трансляций',
				'desc'   => 'Настройки карточек на странице Трансляции.',
				'fields' => array(
					'broadcasts_cards' => array(
						'type'      => 'repeater',
						'label'     => 'Настройки карточек трансляций',
						'add_label' => '+ Добавить настройку',
						'subfields' => array(
							array(
								'key'   => 'posts_per_page',
								'type'  => 'text',
								'label' => 'Постов на страницу',
							),
							array(
								'key'   => 'columns',
								'type'  => 'text',
								'label' => 'Колонок',
							),
							array(
								'key'   => 'desc_trim',
								'type'  => 'text',
								'label' => 'Обрезать описание (символов)',
							),
							array(
								'key'     => 'image_size',
								'type'    => 'select',
								'label'   => 'Размер изображения',
								'options' => array(
									'thumbnail' => 'Thumbnail',
									'medium'    => 'Medium',
									'large'     => 'Large',
									'full'      => 'Full',
								),
							),
							array(
								'key'   => 'readmore_text',
								'type'  => 'text',
								'label' => 'Текст кнопки «Подробнее»',
							),
							array(
								'key'   => 'show_avatar',
								'type'  => 'toggle',
								'label' => 'Показывать аватар',
							),
							array(
								'key'   => 'show_photo',
								'type'  => 'toggle',
								'label' => 'Показывать фото',
							),
							array(
								'key'   => 'show_title',
								'type'  => 'toggle',
								'label' => 'Показывать заголовок',
							),
							array(
								'key'   => 'show_desc',
								'type'  => 'toggle',
								'label' => 'Показывать описание',
							),
							array(
								'key'   => 'show_author',
								'type'  => 'toggle',
								'label' => 'Показывать автора',
							),
							array(
								'key'   => 'show_date',
								'type'  => 'toggle',
								'label' => 'Показывать дату',
							),
							array(
								'key'   => 'show_avatar_single',
								'type'  => 'toggle',
								'label' => 'Показывать аватар на одиночной',
							),
							array(
								'key'   => 'show_reading_time',
								'type'  => 'toggle',
								'label' => 'Показывать время чтения',
							),
							array(
								'key'   => 'show_views',
								'type'  => 'toggle',
								'label' => 'Показывать просмотры',
							),
							array(
								'key'   => 'show_readmore',
								'type'  => 'toggle',
								'label' => 'Показывать кнопку «Подробнее»',
							),
						),
					),
				),
			),

			/* ------------------------------------------------- События */
			'events'        => array(
				'label'  => 'События',
				'desc'   => 'Карусель карточек событий на главной — между блоком трансляций и календарём. Три карточки в ряду. Вверху слева на карточке — аватар, имя автора и дата публикации, внизу слева — заголовок и описание. Без строк карусель не выводится.',
				'fields' => array(
					'events_show'     => array(
						'type'    => 'toggle',
						'label'   => 'Показывать карусель событий',
						'desc'    => 'Выключите, чтобы скрыть блок на главной. Сами события при этом сохранятся.',
						'default' => '1',
					),
					'events_title'    => array(
						'type'  => 'text',
						'label' => 'Заголовок блока',
						'desc'  => 'Если пусто — «События церкви»',
					),
					'events_subtitle' => array(
						'type'  => 'text',
						'label' => 'Подзаголовок блока',
						'desc'  => 'Необязательная строка под заголовком',
					),
					'events'          => array(
						'type'      => 'repeater',
						'label'     => 'События',
						'desc'      => 'Название обязательно — строка без него не попадёт в карусель.',
						'add_label' => '+ Добавить событие',
						'subfields' => array(
							array(
								'key'         => 'title',
								'type'        => 'text',
								'label'       => 'Название события',
								'placeholder' => 'Молитвенное служение',
							),
							array(
								'key'         => 'author_name',
								'type'        => 'text',
								'label'       => 'Имя автора',
								'placeholder' => 'Пастор Иван',
							),
							array(
								'key'   => 'author_avatar',
								'type'  => 'media',
								'label' => 'Аватар автора',
								'desc'  => 'Выбирается из медиатеки WordPress. Пусто — будет только имя и дата.',
								'wide'  => true,
							),
							array(
								'key'         => 'date',
								'type'        => 'datepicker',
								'label'       => 'Дата публикации',
								'placeholder' => 'дд.мм.гггг',
							),
							array(
								'key'   => 'desc',
								'type'  => 'text',
								'label' => 'Краткое описание',
								'wide'  => true,
							),
							array(
								'key'   => 'image',
								'type'  => 'media',
								'label' => 'Картинка события',
								'desc'  => 'Выбирается из медиатеки WordPress — станет фоном карточки. Пусто — карточка останется тёмной.',
								'wide'  => true,
							),
							array(
								'key'   => 'link',
								'type'  => 'url',
								'label' => 'Ссылка',
								'desc'  => 'Заголовок станет ссылкой. Пусто — заголовок останется текстом.',
							),
						),
					),
				),
			),

			/* ------------------------------------------------ Документы */
			'documents'     => array(
				'label'  => 'Документы',
				'desc'   => 'Список документов на странице «Документы»: слева список, справа просмотрщик (PDF и картинки). Файл обязателен — строка без него не попадёт в список.',
				'fields' => array(
					'documents' => array(
						'type'      => 'repeater',
						'label'     => 'Документы',
						'desc'      => 'Файл обязательно — строка без него не попадёт на страницу.',
						'add_label' => '+ Добавить документ',
						'subfields' => array(
							array(
								'key'         => 'title',
								'type'        => 'text',
								'label'       => 'Название документа',
								'placeholder' => 'Устав церкви',
							),
							array(
								'key'   => 'file',
								'type'  => 'file',
								'label' => 'Файл',
								'desc'  => 'Выбирается из медиатеки WordPress — PDF или картинка, просмотрщик понимает оба формата.',
								'wide'  => true,
							),
						),
					),
				),
			),

			/* ------------------------------------------- Пожертвования */
			'donation'      => array(
				'label'  => 'Пожертвования',
				'desc'   => 'Форма пожертвования на странице «Мы строим храм». Работает на плагине Leyka: форма подтягивается шорткодом по ID кампании. Сборы, донаты и способы оплаты настраиваются в разделе «Лейка» админки.',
				'fields' => array(
					'donation_show'        => array(
						'type'    => 'toggle',
						'label'   => 'Показывать форму пожертвования',
						'desc'    => 'Выключите, чтобы убрать правую колонку с формой со страницы.',
						'default' => '1',
					),
					'donation_title'       => array(
						'type'  => 'text',
						'label' => 'Заголовок формы',
						'desc'  => 'Например «Поддержать строительство храма»',
					),
					'donation_desc'        => array(
						'type'  => 'textarea',
						'label' => 'Описание формы',
						'desc'  => 'Короткая строка под заголовком.',
						'rows'  => 3,
					),
					'donation_campaign_id' => array(
						'type'  => 'text',
						'label' => 'ID кампании Leyka',
						'desc'  => 'ID кампании из раздела «Лейка → Кампании». Если пусто — вместо формы выводится заглушка.',
					),
				),
			),

			/* ---------------------------------------------- Юридические */
				'legal'     => array(
					'label'  => 'Юридические страницы',
					'desc'   => 'Нужны для ссылки в подвале и в тексте согласия на обработку данных.',
					'fields' => array(
						'legal_policy'    => array(
							'type'  => 'page_select',
							'label' => 'Политика обработки персональных данных',
						),
						'legal_agreement' => array(
							'type'  => 'page_select',
							'label' => 'Согласие на обработку персональных данных',
						),
					),
				),

			/* ------------------------------------------- Экспорт / Импорт */
			'export_import' => array(
				'label'  => 'Экспорт / Импорт',
				'desc'   => 'Резервная копия настроек: выгрузка текущих значений в JSON-файл и загрузка обратно. Ключи, которых нет в схеме, при импорте отбрасываются.',
				'fields' => array(),
				'custom' => 'export-import',
			),
		);
	}
}
