# AGENTS.md

## Проект

WordPress-сайт церкви «Любовь и Вера» (`lubov-vera`), локально на Laragon: `http://lubov-vera.test`.
Классическая тема + один небольшой плагин. **Сборки нет**: никакого React/SCSS/бандлера — только PHP, jQuery и обычный CSS. Часть фронтовых библиотек подключается с CDN (Font Awesome, Swiper, GSAP).

Этот файл описывает **то, что реально лежит на диске**. Если меняете архитектуру — обновите и его.

## Структура

- `themes/lubov-vera/` — основная тема: шаблоны, `assets/`, `js/`, `inc/` (walker меню `class-church-walker-nav-menu.php`, поле иконки меню `menu-icons.php`, виджет `class-lv-church-info-widget.php`, `schedule.php` — расписание служений для календаря/подвала/виджета) и **экран настроек** в `inc/options/`.
- `themes/lubov-vera-child/` — дочерняя тема: только `style.css` и `functions.php` (мост Font Library).
- `plugins/lubov-vera-core/` — кастомный плагин, **только слой данных**: опция, API-хелперы, CPT. UI в нём отсутствует.
- `plugins/leyka/` — сторонний плагин приёма пожертвований (3.32.3, ~1700 файлов, не правится). Данные (кампания, донаты, опции `leyka*`) живут в БД; тема только выводит форму и её оформляет.
- `plugins/quote-block/` — набор кастомных Gutenberg-блоков (см. ниже).
- Легаси-плагина `lv-site-settings`, файла `lv-site-settings.zip` и опции **`lv_core_settings` не существует** — эти имена из старых заметок ошибочны.

## Модель данных

Одна сериализованная опция **`lv_site_settings`** (константа `LV_Core\Options::OPTION_KEY`).

- **Схема — единственный источник правды**: приватный `LV_Core\Options::schema()` отображает `key => [ 'type' => …, 'default' => … ]`. Из неё выводятся `get_defaults()` и `types()`, а `sanitize()` работает как белый список по типу. **Добавить ключ = одна строка в `schema()`.**
- Типы: `text, textarea, email, url, absint, toggle, array` (`toggle` — `'1'`/`'0'`, `array` — повторяющиеся блоки).
- Сейчас 54 ключа.
- **Плагин опцию не пишет.** Пишет только экран настроек темы (сохранение/сброс). Фронт читает её через хелперы.

## Плагин: lubov-vera-core

Только данные; **автозагрузчика и классов Config/Admin/Frontend/Meta_Boxes/Install нет**.

| Файл | Назначение |
|------|---------|
| `lubov-vera-core.php` | Бутстрап: константы `LV_CORE_VERSION/PATH/URL`, подключение трёх includes, создание `Post_Types`, активация/деактивация → `flush_rewrite_rules()` (опции не трогает) |
| `includes/class-options.php` | Синглтон `LV_Core\Options`: `schema()`, `get_defaults()`, `types()`, `get()`, `get_all()`, `merge()`, `sanitize()`, `flush_cache()` |
| `includes/helpers.php` | Публичный API для темы (каждая функция в своём `function_exists`-guard — **на функцию**, не на файл) |
| `includes/class-post-types.php` | Регистрирует CPT `testimony` и `ministry` |
| `uninstall.php` | Удаляет `lv_site_settings` **только** при флаге `lv_core_remove_data_on_uninstall`; защищён `WP_UNINSTALL_PLUGIN`; записи и термины не удаляет |
| `tools/lv-core-smoke-test.php` | CLI-смоук: окружение, Options, хелперы, CPT, опция, статический скан кода |

Синглтон: `get_instance()`. **Методы-экземпляра вызывать только на экземпляре** — `Options::get()` не статический (фатал на PHP 8).

## Публичный API (хелперы) — `includes/helpers.php`

`lv_get_setting`, `lv_option`, `lv_get_hero_data`, `lv_get_cards_data`, `lv_get_timer_data`, `lv_get_social_links`, `lv_get_documents_list`, `lv_get_events_data`, `lv_get_phone`, `lv_get_email`, `lv_get_address`, `lv_get_donation_widget_id`, `lv_get_legal_policy_url`, `lv_get_legal_policy_text`, `lv_sanitize_svg_content`.

- `lv_get_social_links()` проверяет наличие `assets/icons/{type}.svg` и пускает только `[a-z0-9_-]+` (защита от обхода пути).
- `lv_get_events_data()` — события для карусели на главной: `{title, date, desc, image, link}`; строки без названия отбрасываются.
- `lv_sanitize_svg_content()` вырезает `<script>/foreignobject/iframe/embed/object/animate/set`, инлайновые `on*=`, `javascript:`; если тега `<svg>` нет — возвращает `''`.

## CPT

- `testimony` — rewrite slug `/testimony/` (`with_front=false`), `rest_base` `testimonys`, `has_archive`, supports `title, editor, thumbnail, excerpt`.
- `ministry` — rewrite slug `/ministry/`, `rest_base` `ministrys`, те же supports.
- **Таксономий нет** (осознанно — проверено по базе).
- Одиночный вид — `single.php` (общий), архив — `archive.php` (общий, с определением типа).

Post meta в работе (ставятся через редактор/произвольные поля — **кастомных мета-боксов нет**): `_lv_single_image`, `_lv_post_gallery`, `_lv_author_role`, плюс вычисляемые темой `lv_views_count` и `lv_reading_time_val`.

## Экран настроек (в ТЕМЕ)

Файлы: `inc/options/class-lv-theme-settings.php`, `inc/options/schema.php`, `inc/options/helpers.php`, `inc/options/views/`; ассеты `assets/css/settings.css`, `assets/js/settings.js` (версия `2.9.0`).

- Пункт меню: **отдельный верхнеуровневый «Настройки сайта»** (`add_menu_page`, позиция `61` → `/wp-admin/admin.php?page=lv-theme-options`), право `edit_theme_options`. Хук/id пункта — `toplevel_page_lv-theme-options` (важно для enqueue и CSS). Метод регистрации — `add_settings_page()`.
- **Иконка пункта меню — кастомный логотип, вектор (SVG), белый** `assets/img/lv-menu-icon.svg` (без плашки, прозрачный фон; исходник — `uploads/2026/03/cropped-cropped-bez-imeni-1-3.jpg`, обведён по контуру в SVG через GD — марширующие квадраты + упрощение Дугласа-Пекера): `menu_icon_url()` берёт её из ассетов темы (`get_template_directory_uri()`), WP рендерит как `<img>` (не dashicon). Размер задаёт `menu_icon_css()` (20×20, `object-fit:contain` — логотип не квадратный), печатается инлайном через `wp_register_style( 'lv-theme-admin-menu', false, ... )` + `wp_add_inline_style` в `admin_enqueue_scripts` **на всех** экранах админки (проверка `$hook_suffix` идёт после этого).
- Разметка — своя реализация на классах с префиксом `lv-` (`page.php`: `.wrap.lv-options`, `.lv-options__cols` + pseudo-кнопки, `.lv-options__body` (flex) > `.lv-options__main` > `.lv-options__layout` (flex) > `.lv-options__tablist` (вкладки вертикальные, слева) + `.lv-options__content` (форма + кастомные панели), и `.lv-options__sidebar` (виджеты `.lv-options__widget`, справа 300px); `.lv-options__tabs`/`.lv-options__tab`/`.lv-options__tab-panel`, `.lv-field`/`.lv-field-label`/`.lv-field-body`). **Панель сохранения `.lv-options__toolbar` (`.lv-save-row`) — в самом низу страницы, ПОСЛЕ всех панелей, и лежит вне главной формы** (у «Экспорт/Импорт» свой `<form>`, вложенные формы запрещены): кнопка «Сохранить» привязана к главной форме атрибутом `form="lv-options-form"`, «Сбросить» — `type="button"` с JS-обработчиком. Sticky `bottom: 0` (не `fixed`), диапазон прилипания ограничен `.lv-options`. Из ассетов — только ядро (`wp_enqueue_media()`; сторонних admin-библиотек нет). Классы `.pseudo-button*` — собственные (без префикса), CSS в `assets/css/settings.css`.
- Схема: **12 разделов / 45 полей** — `main, cards, contacts, socials, schedule, pages, broadcasts, events, documents, donation, legal, export_import`. Раздел `documents` — repeater `documents` (подполя `title` text, `file` file) для страницы «Документы» (`page-documents.php` читает через `lv_get_documents_list()`, строки без файла отбрасываются). Раздел `donation` — 4 поля для формы пожертвования на странице «Мы строим храм»: `donation_show` (toggle, по умолчанию `'1'`), `donation_title` (text), `donation_desc` (textarea), `donation_campaign_id` (text → absint). Шорткод Лейки собирается в шаблоне, а не хранится в опции. Раздел `export_import` — синтетическая секция (`fields` пустой, `custom` = `export-import`) — панель рендерится отдельным циклом после формы (вложенные `<form>` в HTML запрещены), партиал `views/partial-export-import.php`.
- Типы полей и партиалы (`inc/options/views/fields/`): `text` (обслуживает также `email`/`url`), `textarea`, `media`, `page_select`, `repeater`, `toggle` (свитч; скрытый input `value="0"` перед чекбоксом, чтобы снятый переключатель тоже попадал в POST). В строках повторителя доступны ещё `file` (медиатека без ограничения картинками, mime PDF+изображения, превью — имя файла; контейнер `data-lv-file` рядом с `data-lv-media`) и `datepicker`. Хелперы имени/id: `lv_options_name()`, `lv_options_value()`, `lv_options_field_id()`.
- Форма рисует **все** объявленные поля при каждом сохранении (иначе `sanitize()` + `merge()` не смогли бы обновить ключ, которого нет в POST).
- **Сохранение** (два обработчика, один конвейер): `handle_save()` (классический POST → `admin-post.php`, action `lv_options_save`, nonce `lv_options` / поле `_lv_nonce`) и `handle_save_ajax()` (`wp_ajax_lv_options_save`). Конвейер: права → nonce → `wp_unslash` → `Options::sanitize()` → убрать маркер повтора → `array_merge(current, clean)` → `update_option` → `Options::flush_cache()`. Так как форма шлёт всё, **пустая строка ПЕРЕЗАПИСЫВАЕТ присутствующий ключ** (задумано). Неизвестные ключи отбрасываются белым списком.
- **Экспорт** (`handle_export()`, action `lv_options_export`): `nocache_headers()` + `Content-Disposition: attachment; filename=lv-site-settings-<Ymd-Hi>.json`, тело — `wp_json_encode` обёртки `{plugin, exported, settings}` (`JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES`).
- **Импорт** (`handle_import()` + `handle_import_ajax()`, action `lv_options_import`): общий конвейер `do_import()` — `json_decode` → взять `settings` (или плоский массив) → `sanitize_values()` → `array_merge(current, clean)` → `update_option` → `flush_cache()`. Классический вариант редиректит с `updated=imported`/`import_failed` + `lv_imported=N`; AJAX — `wp_send_json_success/error`.
- **Аудит**: `lv_audit_settings_change()` в `functions.php` вызывается с хуков `lv_options_save_before`, `lv_options_reset_before`, `lv_options_import_before`.
- **Сброс** (`handle_reset()`, action `lv_options_reset`): пишет дефолты **только для 45 полей экрана**, поверх текущей опции, поэтому неэкранные ключи (шрифты, cookie, `donation_mixplat`, `hero_video`, `*_cards`, …) уцелевают.
- `render_page()` считает `$filled_count` для диалога сброса.
- Если плагин выключен, работает `sanitize_values_fallback()` в том же классе — санитайз по схеме темы.

### settings.js

Табы (`.lv-options__tab` ↔ `.lv-options__tab-panel`, классы `is-active`/`active` + `hidden`, hash `#lv-<key>`, дублирует подпись в `[data-lv-active-section]`); свернуть/развернуть описания (`.js-lv-expand`/`.js-lv-collapse` → `lv-descs-off` на `.lv-options`); повторы (клон `<template>`, снять `disabled`, перенумеровать в сплошные `[i]`, замена `__index__`, переключение пустого состояния, Enter добавляет строку, кнопка-копия `[data-lv-rep-copy]` дублирует строку со значениями и вставляет её сразу за источником, порядок строк меняется ручкой `[data-lv-rep-drag]` через jQuery UI sortable (`axis: y`, после перестановки `reindex`); после добавления строки летит событие `lv:rep:added` — по нему модуль датапикера инициализирует поля новой строки, а в клоне-копии предварительно снимаются `hasDatepicker` и `.ui-datepicker-trigger`); один фрейм медиатеки на поля-картинки (контракт `data-lv-media-*`; целевое поле запоминается в `$current` на момент открытия фрейма — иначе при нескольких медиа-полях вставка шла бы в первое); датапикер строк повторителя — jQuery UI, русские названия месяцев, формат `dd.mm.yy`; AJAX-сохранение со строкой статуса (падает обратно на обычный POST, если нет `LVSettings`); Ctrl/Cmd+S; модалка подтверждения сброса (ставит форме `action=lv_options_reset` и отправляет нативно); экспорт настроек (копирование JSON-выгрузки в буфер через `navigator.clipboard` с `execCommand`-фолбэком, `[data-lv-export-copy]`/`[data-lv-export-text]`/`[data-lv-export-status]`); импорт настроек (файл → textarea через `FileReader`, `[data-lv-import-file]`/`[data-lv-import-text]`, AJAX-отправка формы `#lv-import-form` со статусом, без `LVSettings` — нативный POST). Без JS всё деградирует корректно.

## Плагин: quote-block (набор блоков)

Отдельный плагин `plugins/quote-block/` — набор кастомных Gutenberg-блоков. Сборка — `@wordpress/scripts` (webpack). Архитектура **мультиблочная**: один блок = один каталог `src/blocks/<slug>/` (`block.json`, `index.js`, `edit.js`, `save.js`, `editor.scss`, `style.scss`, при необходимости `view.js`). `webpack.config.js` — просто реэкспорт конфига `@wordpress/scripts`: точки входа определяются автоматически по всем `src/**/block.json` (поля `file:` в `editorScript`/`style`/`viewScript`), поэтому сборка раскладывается по `build/blocks/<slug>/` (`index.js`, `index.css` — только редактор, `style-index.css` — фронт+редактор, `view.js`, `index.asset.php`, `block.json`). Сборка: `npm run build`; lint JS сейчас не гоняется — dev-зависимости в `node_modules` сломаны (eslint падает на `@typescript-eslint`/`ts-api-utils`).

- `quote-block.php`: переводы + на `init` перебирает `build/blocks/*/block.json` и вызывает `register_block_type()` для каждого каталога. Скрипты/стили WordPress подключает сам по ссылкам `file:` в метаданных (поэтому отдельные `wp_register_*` не нужны).
- **11 блоков**: `quote` (Цитата), `callout` (Уведомление), `cta` (Призыв к действию), `verse` (Библейский стих), `button` (Кнопка), `section-heading` (Заголовок секции), `cards` (Карточки/сетка), `tabs`+`tab` (Вкладки), `accordion`+`accordion-item` (Аккордеон). У `tab`/`accordion-item` задан `parent` (только внутри контейнера). Префикс CSS-классов блоков — `lv-<slug>`.
- `cards` — контейнер `InnerBlocks` (CSS grid, `--lv-cards-columns`), дети-`core/group` оформляются как карточки.
- `accordion`/`accordion-item` — на нативных `<details>/<summary>` (работают без JS); `view.js` закрывает прочие `<details>` в режиме «один открытый» (`allowMultiple=false`).
- `tabs`/`tab` — навигация строится скриптом: `tabNav.js` (`initTabs`) собирает кнопки из `data-title` дочерних `.lv-tabs__item`, вызывается и из `view.js` (фронт), и из `edit.js` (редактор). Без JS все вкладки видны подряд (`.lv-tabs__inner.is-ready` включает переключение).
- Атрибуты `quote`: `quote`, `author` (оба `source:html`), `textAlign`, `preset` (классы `quote-block--preset-*`), `quoteColor`, `borderColor`, `borderWidth`, `showBorder`, `quoteSize`, `authorPosition`. UI пресетов — `src/blocks/quote/components/Presets.js`; нативные `styles` из `block.json` убраны (CSS стилизует только `--preset-*`).

## Тема

### Шаблоны

- `front-page.php` — главная: `template-parts/hero`, `countdown`, `schedule`, `cards`, `posts-swiper`, `events-swiper`, `calendar` (календарь вынесен в отдельный партиал; раньше разметка дублировалась прямо в шаблоне).
- `single.php` — все типы (читает `_lv_single_image`, `_lv_post_gallery`, `_lv_author_role`).
- `archive.php` — все типы (карточки с учётом типа).
- Отдельные страницы: `page-testimonies.php`, `page-ministries.php`, `page-documents.php`, `page-policy.php`, `page-agreement.php`, `page-construction.php`; также `search.php`, `404.php`, `comments.php`.
- `page-construction.php` («Мы строим храм») — две колонки `.construction-content`: слева слайдер/текст, справа форма пожертвования. Настройки читаются через `lv_get_setting()`: `donation_show` (выключено — правая колонка не рендерится), `donation_title`/`donation_desc` (пустые — заголовок/описание не печатаются), `donation_campaign_id` (ID кампании Лейки). Шорткод собирается в шаблоне: `[leyka_campaign_form id="<ID>"]` и выводится через `do_shortcode()`; при пустом ID — заглушка «Форма пожертвования будет доступна после настройки». Контент страницы — `get_the_content()` + `apply_filters( 'the_content', … )` (как в `page-policy.php`).

### functions.php

Guarded-фолбэк `lv_get_setting()` (единственное место, где тема читает `lv_site_settings` напрямую — на случай выключенного плагина); настройка в `after_setup_theme` (меню `menu-1`, `footer-documents`, `footer-navigation`; кастомный логотип; `content_width` 1032); фронтовые enqueue (для `calendar.js` локализуется `lvCalendarData`: события расписания, адрес и смещение часового пояса сайта); отключение блока `core/quote` + фильтр `allowed_block_types_all`; просмотры и время чтения; загрузка последнего видео Rutube (`lv_get_latest_rutube_video()`, транзиент `lv_latest_rutube_video`, превью-заглушка `assets/img/devo.jpg`); `lv_media_image()`; `lv_cards_settings()` / `lv_cards_per_page()`. Подключает `inc/schedule.php`.

**Сайдбар**: `register_sidebar()` с `id=sidebar-1` в `lubov_vera_setup()` (`functions.php:67-77`) + фильтры `gutenberg_use_widgets_block_editor`/`use_widgets_block_editor` → `__return_false` (приоритет 99) — отключают блочный редактор виджетов, которые не снимает Clearfy. В начале `functions.php` — разовый сид: если `widget_lv_church_info` ещё нет, виджет `lv_church_info` создаётся из `lv_get_setting('address'|'phone'|'email')` и ставится в `sidebar-1` (повторно не срабатывает). Виджет `LV_Church_Info_Widget` (`inc/class-lv-church-info-widget.php`, файл именован по классу для WPCS) — адрес/телефон/email через `lv_get_*()` + расписание служений из настроек (`lv_get_schedule_groups()`); подключается через `require` + `add_action('widgets_init')` (`functions.php:229-235`). `sidebar.php` вызывает `dynamic_sidebar('sidebar-1')`; `single.php:250-254` — свой `<aside class="single-sidebar">` с `dynamic_sidebar` вместо прежней захардкоженной разметки.

### Расписание служений (`inc/schedule.php`)

Единый источник расписания для календаря на главной, подвала и виджета. Данные — repeater `schedule` (подполя `day`, `time`, `text`) из раздела «Расписание»; `day` понимает полные и короткие русские названия (`Воскресенье`/`Вс`, `Пятница`/`Пт`), `time` — `чч:мм` (можно несколько через запятую/точку с запятой/слэш). Пока список пуст, `lv_get_schedule_items()` возвращает запасной набор: Пт 19:00 «Молодёжное служение», Вс 10:00/12:00/17:00 «Общецерковное служение».

- `lv_get_schedule_items()` — нормализованные строки; `lv_get_schedule_calendar_events()` — `{weekday, time, title}` для JS (день в формате `getDay()`, 0 — воскресенье); `lv_get_schedule_groups()` — группировка по дню для подвала/виджета (дедупликация времён).
- `functions.php` при enqueue `calendar.js` передаёт `lvCalendarData` (`events`, `location`, `offset`). Адрес — `lv_get_address()` с запасным `ул. Ворошилова, 82, г. Ижевск`; `offset` — смещение часового пояса сайта в минутах, поэтому «сегодня» и прошедшие события считаются по времени сайта, а не браузера.
- `calendar.js` считает событие прошедшим по дате и времени (в текущем дне — до начала служения) и выходит без ошибок, если блока календаря на странице нет.
- `footer.php` и `LV_Church_Info_Widget` больше не хардкодят воскресные службы; `front-page.php` подключает `template-parts/calendar.php` вместо дублирующей разметки.

### Hero (`template-parts/hero.php`)

Выводится, только если непусты badge/title/desc/**image**/соцсети. `<h1>` присутствует всегда (визуально скрыт, если дублирует логотип). Правая колонка `image-container` есть, если задан `hero_image` **или** соцсети — **заглушки `devo.jpg` для картинки hero нет** (соцсети лежат внутри колонки). `devo.jpg` остаётся только превью-заглушкой для видео Rutube.

### Таймер эфира (`template-parts/countdown.php`)

Выводится (секция `.countdown-section`), только если задан `timer_title` и корректные `timer_date` + `timer_time`; иначе `return`. Время — новое поле `timer_time` (чч:мм, пусто → `19:00`). Дата/время парсятся серверно через `DateTimeImmutable::createFromFormat( 'd.m.Y H:i', …, wp_timezone() )` с проверкой `getLastErrors()`; начало/конец эфира печатаются ISO-8601 в `data-start`/`data-end`. Пока `now < start` — обратный отсчёт; в окне `[start, start + lv_broadcast_air_minutes (120)]` — «В ЭФИРЕ» + `.countdown-badge.is-live`; после конца секция не рендерится. JS (`script.js`) досчитывает раз в секунду, повторяет ту же логику и скрывает секцию (`hidden`) по `data-end`.

### Карусель событий (`template-parts/events-swiper.php`)

Выводится на главной между `posts-swiper` и календарём — «между трансляций и календарём». Рендерится, только если `lv_get_events_data()` вернул хотя бы одну строку с названием. Заголовок блока — `events_title` (пусто → «События церкви»), подзаголовок — `events_subtitle`. Настройки — раздел «События» экрана настроек: переключатель `events_show` (по умолчанию `'1'`; выключен — секция не рендерится), `events_title`, `events_subtitle` и repeater `events` с подполями `title, author_name, author_avatar, date, desc, image, link`; строка без названия в карусель не попадает.

Разметка — `.swiper.events-swiper` (Swiper 12 с CDN, общий для сайта); кнопки навигации лежат в шапке секции (`.swiper-navigation`), пагинация — внутри слайдера. Три карточки в ряду от 768px, две — от 640px, одна — на телефоне (breakpoints в `script.js`). Карточка — картинка на фоне + градиентный оверлей (`.event-card__bg` / `.event-card__overlay`), контент поверх (`.event-card__content`): вверху слева `.author-block` (аватар, имя автора, дата публикации), внизу слева заголовок `.card-title` и описание `.card-description` — переиспользуются общие классы карточек архива. Заголовок оборачивается в `.post-title-link`, если задан `link`.

Подполя repeater `events`: `title` (text), `author_name` (text), `author_avatar` (**media**), `date` (**datepicker**, формат `дд.мм.гггг`), `desc` (text), `image` (**media**), `link` (url). Тип `media` и `datepicker` в строках повторителя рендерит `views/fields/repeater.php`; медиа-контрол переиспользует контракт `data-lv-media-*` (общий код медиатеки в `settings.js`), датапикер — jQuery UI (`jquery-ui-datepicker` + `wp_localize_jquery_ui_datepicker()` в enqueue экрана; CSS datepicker'а нет в ядре WP — стили `#ui-datepicker-div` в `settings.css`). Новые строки повторителя получают инициализацию через событие `lv:rep:added` (его триггерит обработчик `[data-lv-rep-add]`, а также обработчик копирования `[data-lv-rep-copy]`). Версии ассетов экрана настроек: CSS `2.9.1`, JS `2.9.0`.

**Осторожно с классом `.event-card`:** тот же класс используют карточки «Предстоящие события» в календаре (`calendar.js`, стили `.calendar-container .event-card`). Поэтому все стили карусели событий обязательно scope'ить под `.events-swiper-section` — иначе глобальные правила (например `min-height: 340px`) распухают карточки календаря. По той же причине `.event-card` в карусели требует `width: 100%` (глобальный `.swiper-slide` — `display: flex`, контент карточки абсолютный, флекс-basis 0).

`script.js` инициализирует карусель отдельной функцией `initEventsSwiper()` — общая инициализация `.swiper` её не трогает (селектор `.swiper:not(.events-swiper)`), а точки пагинации/навигации ищутся внутри своей секции. Loop и autoplay включаются, только когда слайдов больше, чем видно на текущем брейкпоинте. Стили: `.events-swiper-section` / `.event-card*` в `style.css`, тёмная тема — `dark-theme.css`, мобильные правила — `adaptive.css`.

### CSS / JS

- Порядок подключения: `assets/css/reset.css` → `assets/css/style.css` → `adaptive.css` → `buttons.css` → `preloader.css` → `get_stylesheet_uri()` → `dark-theme.css`. Отдельно `assets/css/leyka.css` — только на странице «Мы строим храм» (`lubov_vera_leyka_styles()` на `wp_enqueue_scripts` с приоритетом 99, чтобы печататься после стилей плагина).
- Брейкпоинты: `1199, 991, 767, 575, 375`. Тёмная тема: `html.dark`.
- JS: `js/navigation.js`; `assets/js/script.js` (таймер/sticky-склейка), `calendar.js` (календарь служений; данные из `lvCalendarData`), `services-filter.js`, `preloader.js`, `swiper.js`, `editor.js`.
- Иконки соцсетей: `assets/icons/*.svg` (есть `vk.svg`, `youtube.svg`, `rutube.svg` и др.).

## Плагин: leyka (пожертвования)

Сторонний плагин `plugins/leyka/` (3.32.3, ~1700 файлов, **не правится**). Перенесён с прежнего сайта вместе с данными. UI — в админке («Лейка»): кампании, донаты, шлюзы, юридические тексты. Тема его не настраивает, а только выводит форму и оформляет её.

Данные в БД (не в коде):

- Кампания `4267` — «Сбор на уставную деятельность» (`campaign_type=persistent`, `campaign_currency=rub`, `campaign_template=star`, `donations_type_default=recurring`). Мета `total_funded` = `74101`, `count_views` = `2096`, `count_submits` = `30`. `campaign_css` целиком закомментирован — оформление берётся только из `assets/css/leyka.css`.
- 34 доната (`leyka_donation`): 10 `funded` (сумма 73 801 ₽) и 24 `submitted`. Расхождение с `total_funded` (300 ₽) было и на источнике — это исторический счётчик, не пересчитывается.
- Шлюзы Mixplat: активные PM для RUB — `mixplat-bankcard, mixplat-sbp, mixplat-mirpay, mixplat-sberpay, mixplat-yandex, mixplat-mobile, mixplat-sms, mixplat-bnpl`; `leyka_mixplat_service_id=366120`, `leyka_mixplat_test_mode=0`, `pm_order` совпадает с источником. Ключи (`leyka_mixplat_secret_key`, `leyka_mixplat_widget_key`) — **боевые**, перенесены как есть.
- Служебные страницы: `149` «Спасибо!» (`leyka_success_page`, `leyka_quittance_redirect_page`), `150` «Ошибка платежа» (`leyka_failure_page`) — созданы активацией плагина; `858` «Условия сервиса сбора пожертвований» (`leyka_terms_of_service_page`), `859` «Условия использования персональных данных» (`leyka_pd_terms_page`) — импортированы с источника. Юридические тексты лежат в опциях `leyka_terms_of_service_text` / `leyka_pd_terms_text` с плейсхолдерами `#LEGAL_NAME#`, `#LEGAL_FACE_POSITION#` и т. п.

Фронт:

- Форма — шорткод `[leyka_campaign_form id="4267"]` из `page-construction.php` (ID берётся из настройки `donation_campaign_id`). Кампания также доступна по своему URL (`/leyka_campaign/…`), но меню на неё не ссылается.
- Так как шорткод стоит **в шаблоне**, а не в `post_content`, плагин сам не подключает свои ассеты (он ищет шорткод в контенте). Поэтому тема ставит фильтры `leyka_form_is_screening` и `leyka_modern_template_displayed` → `true` на `page-construction.php` (`lubov_vera_leyka_screening()`). Без них форма рендерится без `public.css`/`public.js` — «сырым» полотном на 7 тысяч пикселей.
- Плагин печатает `<script>document.documentElement.classList.add("leyka-js")</script>` без nonce, а CSP темы блокирует такие скрипты; тема дублирует его с nonce (`lubov_vera_leyka_js_class()` на `wp_head`, приоритет 11). От класса зависят CSS-состояния формы (оверлей, закрытие, финальный экран).
- CSP темы (`wp_headers` в `functions.php`) разрешает `https://*.mixplat.ru` в `script-src`/`connect-src`/`frame-src`, `https://*.donation.ru` в `connect-src` и `worker-src 'self' blob:` — иначе виджет оплаты Mixplat не загружается. Отдельный фильтр `wp_inline_script_attributes` вешает nonce темы на все inline-скрипты ядра (`wp_localize_script`, speculation rules) — иначе `'unsafe-inline'` в CSP игнорируется из-за наличия nonce.
- Оформление — `assets/css/leyka.css` (дизайн сайта БФ «Живи сейчас» — https://alsfund.ru/campaign/pomoch-fondu/: карточка формы с мягкой тенью, чипы сумм, коробочки оплат, поля донора — подчёркивание, кнопка — пилюля; акцент `--lv-leyka-accent: #f36421`, скругления «таблетки», подпись платформы скрыта правилом `.leyka-tpl-star-form .section.section--signature #leyka-platform-signature`). Файл подключается только на шаблоне `page-construction.php` после стилей плагина; переопределения плагина селекторами `div[id*="leyka-pf-"]` и префиксом `html.dark` задаются явно. Данные (кампания, тексты) — из настроек темы (`lv_get_setting`).
- `leyka_platform_signature_on_form_enabled=1`; скрывает подпись именно CSS темы, а не настройка плагина.
- Если плагин выключен, а ID кампании задан, шорткод выведется как обычный текст `[leyka_campaign_form id="…"]` (отдельной проверки на активность плагина в шаблоне нет; заглушка печатается только при пустом `donation_campaign_id`). При переносе сайта копировать **и** `plugins/leyka/`, **и** опции `leyka*` + CPT-записи из БД.

## Дочерняя тема — мост Font Library

`lubov-vera-child/functions.php` навешивает семейство из **Font Library** WordPress (по умолчанию `Gilroy`, фильтр `lv_site_font_family`) на весь сайт (body + заголовки) инлайновым стилем после последней таблицы темы. Родитель жёстко задаёт `font-family: "Inter"` и как классическая тема не применяет шрифты Font Library. Если названного семейства нет — ничего не печатается (тема оставляет свой шрифт), «первый загруженный» не подставляется. При переносе сайта копировать **и** записи БД (`wp_font_family`/`wp_font_face`), **и** `uploads/fonts/`, затем сбросить кэш.

## Линтер (WordPress Coding Standards)

Код проверяется PHPCS по стандарту `WordPress`. Конфиг — `phpcs.xml` в корне `wp-content`
(сканирует тему, дочернюю тему и оба плагина — `lubov-vera-core`, `quote-block`; исключает
`vendor`/`node_modules`/`assets/img`/`assets/fonts` и собранный `plugins/quote-block/build`).

- Инструменты объявлены как dev-зависимости Composer в `composer.json`
  (`squizlabs/php_codesniffer`, `wp-coding-standards/wpcs`,
  `dealerdirect/phpcodesniffer-composer-installer`). В веб-корне `vendor/` не держим
  (в `.gitignore`) — ставится `composer install`.
- Запуск: `composer lint` (phpcs), автофикс форматирования — `composer lint:fix` (phpcbf).
  Напрямую: `vendor\bin\phpcs` / `vendor\bin\phpcbf` с тем же `phpcs.xml`.
- **Статус (`phpcs.xml`)**: **0 ошибок и 3 предупреждения** на всех 52 PHP-файлах
  темы, дочерней темы и плагинов (`lubov-vera-core`, `quote-block`). Предупреждения —
  только `Generic.CodeAnalysis.UnusedFunctionParameter` для параметров хуков с
  underscore-префиксом в `functions.php` (строки 827, 842): коллбэкам фильтров
  параметры нужны по сигнатуре, хотя и не используются. Оставшиеся `phpcs:ignore` — только обоснованные,
  с указанием причины: синхронные `<script>` до `wp_head()` (`theme.js`, PDF.js в
  `page-documents.php`), trusted-HTML из `lv_media_image()`, fallback
  `@file_get_contents` в `lv_fetch_url()`, инлайн SVG через `readfile()` в
  `template-parts/cards.php`, прямые запросы в `uninstall.php` и консольный вывод
  `tools/lv-core-smoke-test.php`. Имена файлов приведены к WPCS: `class-lv-theme-settings.php`,
  `class-church-walker-nav-menu.php`, `class-lv-church-info-widget.php`, `views/fields/page-select.php`.

## Соглашения и подводные камни

1. **Методы-экземпляра не статические.** Вызывать `LV_Core\Options::get_instance()->get(...)`; `OPTION_KEY` — константа класса.
2. **`function_exists` — на функцию, не на файл** (и в `includes/helpers.php`, и в `functions.php`). Один guard на файл подавит соседние хелперы → «undefined function».
3. **Тема читает настройки через хелперы**, а не через `get_option('lv_site_settings')` напрямую (единственное исключение — guarded-фолбэк в `functions.php`).
4. **Санитайз перезаписывает присутствующие ключи** — пустая строка сохраняется как пустая. Это и есть контракт полного сохранения; «чинить» не нужно.
- Повторы именуются `lv_options[key][i][sub]`; тема сама убирает маркер `__lv_empty`. (В докблоке партиала повтора упомянут устаревший маркер `_repeating_{key}`, которого на деле нет — это дрейф комментария, а не поведение.)
- **Новую настройку добавляйте в одном месте**: строка в `Options::schema()` (значение/тип) и, если поле должно быть редактируемым, запись в `inc/options/schema.php`. Вывод на фронт подключается в шаблоне явно.
- **Не добавляйте `overflow: visible` admin-контейнерам WP**; **`position: sticky` требует предков без overflow**.
- **Тесты не должны портить живую БД.** CLI-скрипты ходят в реальную базу `.test`. Снимайте снапшот `lv_site_settings` (например, в `baseline.json`) и восстанавливайте: сброс/сохранение, вызывающие `exit`, гоняйте **в подпроцессе**, а восстановление делайте в `finally` родителя. Проверяйте глубоким сравнением значений (не только набора ключей) и не оставляйте после себя опций/страниц.
