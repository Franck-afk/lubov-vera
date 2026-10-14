# AGENTS.md

## Проект

WordPress-сайт церкви «Любовь и Вера» (`lubov-vera`), локально на Laragon: `http://lubov-vera.test`.
Классическая тема + один небольшой плагин. **Сборки нет**: никакого React/SCSS/бандлера — только PHP, jQuery и обычный CSS. Часть фронтовых библиотек подключается с CDN (Font Awesome, Swiper, GSAP).

Этот файл описывает **то, что реально лежит на диске**. Если меняете архитектуру — обновите и его.

## Структура

- `themes/lubov-vera/` — основная тема: шаблоны, `assets/`, `js/`, `inc/` (walker меню `class-church-walker-nav-menu.php`, поле иконки меню `menu-icons.php`) и **экран настроек** в `inc/options/`.
- `themes/lubov-vera-child/` — дочерняя тема: только `style.css` и `functions.php` (мост Font Library).
- `plugins/lubov-vera-core/` — кастомный плагин, **только слой данных**: опция, API-хелперы, CPT. UI в нём отсутствует.
- Легаси-плагина `lv-site-settings`, файла `lv-site-settings.zip` и опции **`lv_core_settings` не существует** — эти имена из старых заметок ошибочны.

## Модель данных

Одна сериализованная опция **`lv_site_settings`** (константа `LV_Core\Options::OPTION_KEY`).

- **Схема — единственный источник правды**: приватный `LV_Core\Options::schema()` отображает `key => [ 'type' => …, 'default' => … ]`. Из неё выводятся `get_defaults()` и `types()`, а `sanitize()` работает как белый список по типу. **Добавить ключ = одна строка в `schema()`.**
- Типы: `text, textarea, email, url, absint, array` (`array` — повторяющиеся блоки).
- Сейчас 45 ключей.
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

`lv_get_setting`, `lv_option`, `lv_get_hero_data`, `lv_get_cards_data`, `lv_get_timer_data`, `lv_get_social_links`, `lv_get_documents_list`, `lv_get_phone`, `lv_get_email`, `lv_get_address`, `lv_get_donation_widget_id`, `lv_get_legal_policy_url`, `lv_get_legal_policy_text`, `lv_sanitize_svg_content`.

- `lv_get_social_links()` проверяет наличие `assets/icons/{type}.svg` и пускает только `[a-z0-9_-]+` (защита от обхода пути).
- `lv_sanitize_svg_content()` вырезает `<script>/foreignobject/iframe/embed/object/animate/set`, инлайновые `on*=`, `javascript:`; если тега `<svg>` нет — возвращает `''`.

## CPT

- `testimony` — rewrite slug `/testimony/` (`with_front=false`), `rest_base` `testimonys`, `has_archive`, supports `title, editor, thumbnail, excerpt`.
- `ministry` — rewrite slug `/ministry/`, `rest_base` `ministrys`, те же supports.
- **Таксономий нет** (осознанно — проверено по базе).
- Одиночный вид — `single.php` (общий), архив — `archive.php` (общий, с определением типа).

Post meta в работе (ставятся через редактор/произвольные поля — **кастомных мета-боксов нет**): `_lv_single_image`, `_lv_post_gallery`, `_lv_author_role`, плюс вычисляемые темой `lv_views_count` и `lv_reading_time_val`.

## Экран настроек (в ТЕМЕ)

Файлы: `inc/options/class-lv-theme-settings.php`, `inc/options/schema.php`, `inc/options/helpers.php`, `inc/options/views/`; ассеты `assets/css/settings.css`, `assets/js/settings.js` (версия `2.2.0`).

- Пункт меню: **отдельный верхнеуровневый «Настройки сайта»** (`add_menu_page`, позиция `61` → `/wp-admin/admin.php?page=lv-theme-options`), право `edit_theme_options`. Хук/id пункта — `toplevel_page_lv-theme-options` (важно для enqueue и CSS). Метод регистрации — `add_settings_page()`.
- **Иконка пункта меню — кастомный логотип, вектор (SVG), белый** `assets/img/lv-menu-icon.svg` (без плашки, прозрачный фон; исходник — `uploads/2026/03/cropped-cropped-bez-imeni-1-3.jpg`, обведён по контуру в SVG через GD — марширующие квадраты + упрощение Дугласа-Пекера): `menu_icon_url()` берёт её из ассетов темы (`get_template_directory_uri()`), WP рендерит как `<img>` (не dashicon). Размер задаёт `menu_icon_css()` (20×20, `object-fit:contain` — логотип не квадратный), печатается инлайном через `wp_register_style( 'lv-theme-admin-menu', false, ... )` + `wp_add_inline_style` в `admin_enqueue_scripts` **на всех** экранах админки (проверка `$hook_suffix` идёт после этого).
- Разметка — своя реализация на классах с префиксом `lv-` (`page.php`: `.wrap.lv-options`, `.lv-options__cols` + pseudo-кнопки, `.lv-options__tabs`/`.lv-options__tab`/`.lv-options__tab-panel`, `.lv-field`/`.lv-field-label`/`.lv-field-body`, панель сохранения `.lv-options__toolbar`, сайдбар `.lv-options__widget`; колонки `.lv-options__main`/`.lv-options__sidebar`). Из ассетов — только ядро (`wp_enqueue_media()`; сторонних admin-библиотек нет). Классы `.pseudo-button*` — собственные (без префикса), CSS в `assets/css/settings.css`.
- Схема: **7 разделов / 33 поля** — `main, cards, contacts, socials, schedule, pages, legal`.
- Типы полей и партиалы (`inc/options/views/fields/`): `text` (обслуживает также `email`/`url`), `textarea`, `media`, `page_select`, `repeater`. Хелперы имени/id: `lv_options_name()`, `lv_options_value()`, `lv_options_field_id()`.
- Форма рисует **все** объявленные поля при каждом сохранении (иначе `sanitize()` + `merge()` не смогли бы обновить ключ, которого нет в POST).
- **Сохранение** (два обработчика, один конвейер): `handle_save()` (классический POST → `admin-post.php`, action `lv_options_save`, nonce `lv_options` / поле `_lv_nonce`) и `handle_save_ajax()` (`wp_ajax_lv_options_save`). Конвейер: права → nonce → `wp_unslash` → `Options::sanitize()` → убрать маркер повтора → `array_merge(current, clean)` → `update_option` → `Options::flush_cache()`. Так как форма шлёт всё, **пустая строка ПЕРЕЗАПИСЫВАЕТ присутствующий ключ** (задумано). Неизвестные ключи отбрасываются белым списком.
- **Сброс** (`handle_reset()`, action `lv_options_reset`): пишет дефолты **только для 33 полей экрана**, поверх текущей опции, поэтому неэкранные ключи (шрифты, cookie, документы, донаты, `hero_video`, `*_cards`, …) уцелевают.
- `render_page()` считает `$filled_count` для диалога сброса.
- Если плагин выключен, работает `sanitize_values_fallback()` в том же классе — санитайз по схеме темы.

### settings.js

Табы (`.lv-options__tab` ↔ `.lv-options__tab-panel`, классы `is-active`/`active` + `hidden`, hash `#lv-<key>`, дублирует подпись в `[data-lv-active-section]`); свернуть/развернуть описания (`.js-lv-expand`/`.js-lv-collapse` → `lv-descs-off` на `.lv-options`); повторы (клон `<template>`, снять `disabled`, перенумеровать в сплошные `[i]`, замена `__index__`, переключение пустого состояния, Enter добавляет строку); один фрейм медиатеки на поля-картинки; AJAX-сохранение со строкой статуса (падает обратно на обычный POST, если нет `LVSettings`); Ctrl/Cmd+S; модалка подтверждения сброса (ставит форме `action=lv_options_reset` и отправляет нативно). Без JS всё деградирует корректно.

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

- `front-page.php` — главная: `template-parts/hero`, `countdown`, `schedule`, `cards`, `posts-swiper` (+ части календаря/обратного отсчёта внутри секции расписания).
- `single.php` — все типы (читает `_lv_single_image`, `_lv_post_gallery`, `_lv_author_role`).
- `archive.php` — все типы (карточки с учётом типа).
- Отдельные страницы: `page-testimonies.php`, `page-ministries.php`, `page-documents.php`, `page-policy.php`, `page-agreement.php`, `page-construction.php`; также `search.php`, `404.php`, `comments.php`.

### functions.php

Guarded-фолбэк `lv_get_setting()` (единственное место, где тема читает `lv_site_settings` напрямую — на случай выключенного плагина); настройка в `after_setup_theme` (меню `menu-1`, `footer-documents`, `footer-navigation`; кастомный логотип; `content_width` 1032); фронтовые enqueue; отключение блока `core/quote` + фильтр `allowed_block_types_all`; просмотры и время чтения; загрузка последнего видео Rutube (`lv_get_latest_rutube_video()`, транзиент `lv_latest_rutube_video`, превью-заглушка `assets/img/devo.jpg`); `lv_media_image()`; `lv_cards_settings()` / `lv_cards_per_page()`.

### Hero (`template-parts/hero.php`)

Выводится, только если непусты badge/title/desc/**image**/соцсети. `<h1>` присутствует всегда (визуально скрыт, если дублирует логотип). Правая колонка `image-container` есть, если задан `hero_image` **или** соцсети — **заглушки `devo.jpg` для картинки hero нет** (соцсети лежат внутри колонки). `devo.jpg` остаётся только превью-заглушкой для видео Rutube.

### Таймер эфира (`template-parts/countdown.php`)

Выводится (секция `.countdown-section`), только если задан `timer_title` и корректные `timer_date` + `timer_time`; иначе `return`. Время — новое поле `timer_time` (чч:мм, пусто → `19:00`). Дата/время парсятся серверно через `DateTimeImmutable::createFromFormat( 'd.m.Y H:i', …, wp_timezone() )` с проверкой `getLastErrors()`; начало/конец эфира печатаются ISO-8601 в `data-start`/`data-end`. Пока `now < start` — обратный отсчёт; в окне `[start, start + lv_broadcast_air_minutes (120)]` — «В ЭФИРЕ» + `.countdown-badge.is-live`; после конца секция не рендерится. JS (`script.js`) досчитывает раз в секунду, повторяет ту же логику и скрывает секцию (`hidden`) по `data-end`.

### CSS / JS

- Порядок подключения: `assets/css/reset.css` → `assets/css/style.css` → `adaptive.css` → `buttons.css` → `preloader.css` → `get_stylesheet_uri()` → `dark-theme.css`.
- Брейкпоинты: `1199, 991, 767, 575, 375`. Тёмная тема: `html.dark`.
- JS: `js/navigation.js`; `assets/js/script.js` (таймер/sticky-склейка), `calendar.js` (календарь служений), `services-filter.js`, `preloader.js`, `swiper.js`, `editor.js`.
- Иконки соцсетей: `assets/icons/*.svg` (есть `vk.svg`, `youtube.svg`, `rutube.svg` и др.).

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
- **Статус (`phpcs.xml`)**: **0 ошибок и 0 предупреждений** на всех 46 PHP-файлах
  темы, дочерней темы и плагинов (`lubov-vera-core`, `quote-block`). Оставшиеся `phpcs:ignore` — только обоснованные,
  с указанием причины: синхронные `<script>` до `wp_head()` (`theme.js`, PDF.js в
  `page-documents.php`), trusted-HTML из `lv_media_image()`, fallback
  `@file_get_contents` в `lv_fetch_url()`, инлайн SVG через `readfile()` в
  `template-parts/cards.php`, прямые запросы в `uninstall.php` и консольный вывод
  `tools/lv-core-smoke-test.php`. Имена файлов приведены к WPCS: `class-lv-theme-settings.php`,
  `class-church-walker-nav-menu.php`, `views/fields/page-select.php`.

## Соглашения и подводные камни

1. **Методы-экземпляра не статические.** Вызывать `LV_Core\Options::get_instance()->get(...)`; `OPTION_KEY` — константа класса.
2. **`function_exists` — на функцию, не на файл** (и в `includes/helpers.php`, и в `functions.php`). Один guard на файл подавит соседние хелперы → «undefined function».
3. **Тема читает настройки через хелперы**, а не через `get_option('lv_site_settings')` напрямую (единственное исключение — guarded-фолбэк в `functions.php`).
4. **Санитайз перезаписывает присутствующие ключи** — пустая строка сохраняется как пустая. Это и есть контракт полного сохранения; «чинить» не нужно.
- Повторы именуются `lv_options[key][i][sub]`; тема сама убирает маркер `__lv_empty`. (В докблоке партиала повтора упомянут устаревший маркер `_repeating_{key}`, которого на деле нет — это дрейф комментария, а не поведение.)
- **Новую настройку добавляйте в одном месте**: строка в `Options::schema()` (значение/тип) и, если поле должно быть редактируемым, запись в `inc/options/schema.php`. Вывод на фронт подключается в шаблоне явно.
- **Не добавляйте `overflow: visible` admin-контейнерам WP**; **`position: sticky` требует предков без overflow**.
- **Тесты не должны портить живую БД.** CLI-скрипты ходят в реальную базу `.test`. Снимайте снапшот `lv_site_settings` (например, в `baseline.json`) и восстанавливайте: сброс/сохранение, вызывающие `exit`, гоняйте **в подпроцессе**, а восстановление делайте в `finally` родителя. Проверяйте глубоким сравнением значений (не только набора ключей) и не оставляйте после себя опций/страниц.
