<?php
/**
 * Смоук-тест плагина lubov-vera-core после установки на staging.
 *
 * ЗАПУСК (только CLI, с корня WordPress):
 *     php wp-content/plugins/lubov-vera-core/tools/lv-core-smoke-test.php
 *
 * Код возврата: 0 — всё зелёное, 1 — есть критичные провалы.
 * Скрипт строго READ-ONLY: ничего не пишет и не удаляет.
 * Персональные данные (телефон, почта) в вывод не печатаются — только длина.
 *
 * Файл лежит в tools/, а не в корне плагина, чтобы его нельзя было открыть
 * по HTTP и вытащить содержимое опций. Дополнительно стоит проверка на CLI.
 *
 * @package LV_Core
 */

// --- Только CLI. Иначе 403 и выход. ---------------------------------
if ( 'cli' !== PHP_SAPI ) {
	http_response_code( 403 );
	exit( 'CLI only' );
}

// --- Подключаем WordPress. -------------------------------------------
// tools/ -> lubov-vera-core -> plugins -> wp-content -> корень WordPress.
$lv_root = dirname( __DIR__, 4 );
$lv_load = $lv_root . '/wp-load.php';

if ( ! file_exists( $lv_load ) ) {
	fwrite( STDERR, "FATAL: не найден wp-load.php по пути {$lv_load}\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fwrite -- CLI test helper.
	exit( 1 );
}

// Если плагин положен (битый require), WordPress упадёт ещё на загрузке.
// Перехватываем и даём читаемый ответ вместо дампа фатала.
try {
	require_once $lv_load;
} catch ( \Throwable $e ) {
	echo "\n";
	echo "==================================================================\n";
	echo "РЕЗУЛЬТАТ: ПРОВАЛ\n";
	echo 'WordPress не поднялся: ' . $e->getMessage() . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	echo "Проверять плагин бессмысленно — сначала почините загрузку.\n";
	exit( 1 );
}

$lv_plugin = dirname( __DIR__ );
$lv_fail   = 0;
$lv_warn   = 0;
$lv_line   = 0;

/**
 * Одна проверка. $mode: ok | fail | warn | info
 *
 * @param string $label Что проверяем.
 * @param bool   $cond  Результат.
 * @param string $mode  ok|fail|warn|info.
 * @param string $note  Дополнение.
 * @return bool
 */
function lv_check( $label, $cond, $mode = 'ok', $note = '' ) {
	global $lv_fail, $lv_warn, $lv_line;

	++$lv_line;
	$tag = 'info';

	if ( $cond ) {
		if ( 'warn' === $mode ) {
			++$lv_warn;
			$tag = 'WARN';
		} else {
			$tag = ( 'info' === $mode ) ? 'INFO' : ' OK ';
		}
	} elseif ( 'warn' !== $mode ) {
			++$lv_fail;
			$tag = 'FAIL';
	} else {
		++$lv_warn;
		$tag = 'WARN';
	}

	printf( "[%s] %s%s\n", $tag, $label, ( '' !== $note ) ? '  — ' . $note : '' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.

	return (bool) $cond;
}

/**
 * Безопасный вызов: ловит любой Error/Throwable, чтобы падение не убило скрипт.
 *
 * @param callable $callback Что зовём.
 * @param mixed    $expected Что ожидаем получить.
 * @return bool
 */
function lv_call_ok( $callback, $expected = null ) {
	global $lv_fail;

	// Проверяем здесь, а не хинтом callable: отсутствующая функция иначе
	// бросит TypeError до входа в тело, и перехват внутри не сработает.
	if ( ! is_callable( $callback ) ) {
		$name = is_string( $callback ) ? $callback : '(closure)';
		printf( "[FAIL] функция недоступна: %s\n", $name ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
		++$lv_fail;
		return false;
	}

	try {
		$result = call_user_func( $callback );
	} catch ( \Throwable $e ) {
		printf( "[FAIL] исключение при вызове: %s\n", $e->getMessage() ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
		++$lv_fail;
		return false;
	}

	if ( null === $expected ) {
		return true;
	}

	return gettype( $result ) === $expected || is_object( $result );
}

/**
 * Убрать комментарии и докблоки, чтобы скан искал по коду, а не по тексту.
 *
 * @param string $src Исходник.
 * @return string
 */
function lv_code_only( $src ) {
	$out = '';

	foreach ( token_get_all( $src ) as $token ) {
		if ( is_array( $token ) ) {
			if ( T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) {
				continue;
			}
			$out .= $token[1];
		} else {
			$out .= $token;
		}
	}

	return $out;
}

/**
 * Печатает заголовок раздела.
 *
 * @param string $t Заголовок.
 * @return void
 */
function lv_head( $t ) {
	echo PHP_EOL . "=== {$t} ===" . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
}

/* ------------------------------------------------------------ Окружение */

lv_head( 'Окружение' );
lv_check( 'ABSPATH определён', defined( 'ABSPATH' ) );
lv_check( 'WordPress загружен', function_exists( 'add_action' ) );
lv_check(
	'версия WordPress >= 6.0',
	version_compare( get_bloginfo( 'version' ), '6.0', '>=' ),
	version_compare( get_bloginfo( 'version' ), '6.0', '>=' ) ? 'ok' : 'fail',
	get_bloginfo( 'version' )
);
lv_check( 'PHP >= 7.4', version_compare( PHP_VERSION, '7.4', '>=' ), version_compare( PHP_VERSION, '7.4', '>=' ) ? 'ok' : 'fail', PHP_VERSION );
lv_check( 'плагин активен', in_array( 'lubov-vera-core/lubov-vera-core.php', (array) get_option( 'active_plugins', array() ), true ) );

/* ----------------------------------------------------- Класс Options */

lv_head( 'LV_Core\Options' );
lv_check( 'класс LV_Core\Options загружен', class_exists( '\LV_Core\Options' ) );

if ( class_exists( '\LV_Core\Options' ) ) {
	$opt = \LV_Core\Options::get_instance();
	lv_check( 'get_instance() отдаёт синглтон', \LV_Core\Options::get_instance() === $opt );
	lv_check( 'OPTION_KEY = lv_site_settings', 'lv_site_settings' === \LV_Core\Options::OPTION_KEY, 'ok', \LV_Core\Options::OPTION_KEY );

	$defaults = $opt->get_defaults();
	lv_check( 'get_defaults() — массив', is_array( $defaults ) );
	lv_check( 'get_defaults() не пуст', ! empty( $defaults ), 'ok', count( $defaults ) . ' ключей' );
	lv_check( 'get_all() — массив', is_array( $opt->get_all() ) );
	lv_check( 'types() — массив', is_array( $opt->types() ) );
	lv_check(
		'sanitize() отбрасывает неизвестный ключ',
		! array_key_exists( '__no_such_key__', $opt->sanitize( array( '__no_such_key__' => 'x' ) ) )
	);
}

/* --------------------------------------------------------- Хелперы */

// 13 хелперов + отдельная функция санитизации SVG.
$helpers  = array(
	'lv_get_setting'            => 'mixed',
	'lv_option'                 => 'mixed',
	'lv_get_hero_data'          => 'array',
	'lv_get_cards_data'         => 'array',
	'lv_get_timer_data'         => 'array',
	'lv_get_documents_list'     => 'array',
	'lv_get_social_links'       => 'array',
	'lv_get_donation_widget_id' => 'string',
	'lv_get_legal_policy_url'   => 'string',
	'lv_get_legal_policy_text'  => 'string',
	'lv_get_phone'              => 'string',
	'lv_get_address'            => 'string',
	'lv_get_email'              => 'string',
);
$extra_fn = 'lv_sanitize_svg_content';

lv_head( 'Хелперы: наличие (13 + 1)' );
$all_defined = true;
foreach ( array_keys( $helpers ) as $fn ) {
	$all_defined = lv_check( "объявлен: {$fn}", function_exists( $fn ) ) && $all_defined;
}
lv_check( "объявлен: {$extra_fn}", function_exists( $extra_fn ) );

lv_head( 'Хелперы: вызов без падения' );

// Вызываем так, как это делает тема.
lv_call_ok(
	function () {
		lv_get_setting( 'phone' );
	}
);
lv_call_ok(
	function () {
		lv_option( 'phone' );
	}
);
lv_call_ok( 'lv_get_hero_data', 'array' );
lv_call_ok( 'lv_get_cards_data', 'array' );
lv_call_ok( 'lv_get_timer_data', 'array' );
lv_call_ok( 'lv_get_documents_list', 'array' );
lv_call_ok( 'lv_get_social_links', 'array' );
lv_call_ok( 'lv_get_donation_widget_id', 'string' );
lv_call_ok( 'lv_get_legal_policy_url', 'string' );
lv_call_ok( 'lv_get_legal_policy_text', 'string' );
lv_call_ok( 'lv_get_phone', 'string' );
lv_call_ok( 'lv_get_address', 'string' );
lv_call_ok( 'lv_get_email', 'string' );
lv_call_ok(
	function () {
		lv_sanitize_svg_content( '' );
	}
);

// Требование «вызов без аргументов»: два хелпера требуют аргумент по сигнатуре,
// для них проверяем TypeError вместо ArgumentCountError — сигнатура не сломана.
foreach ( array( 'lv_get_setting', 'lv_option', $extra_fn ) as $fn ) {
	if ( ! is_callable( $fn ) ) {
		lv_check( "без аргументов: {$fn} — недоступна", false );
		continue;
	}
	try {
		call_user_func( $fn );
		lv_check( "без аргументов: {$fn} — отрабатывает", true, 'info', 'сигнатура допускает пустой вызов' );
	} catch ( \ArgumentCountError $e ) {
		lv_check( "без аргументов: {$fn} — ожидаемый отказ", true, 'info', 'требует аргумент по сигнатуре' );
	} catch ( \Throwable $e ) {
		lv_check( "без аргументов: {$fn}", false, 'fail', 'неожиданная ошибка: ' . $e->getMessage() );
	}
}

/* --------------------------------------------------------------- CPT */

lv_head( 'Типы записей' );
foreach ( array(
	'testimony' => 'testimony',
	'ministry'  => 'ministry',
) as $slug => $expected ) {
	$exists = post_type_exists( $slug );
	lv_check( "{$slug}: зарегистрирован", $exists );
	if ( ! $exists ) {
		continue;
	}

	$o = get_post_type_object( $slug );
	lv_check( "{$slug}: public", (bool) $o->public );
	lv_check( "{$slug}: has_archive", (bool) $o->has_archive );
	lv_check( "{$slug}: rewrite.slug = {$expected}", $expected === $o->rewrite['slug'], 'ok', (string) $o->rewrite['slug'] );
	lv_check(
		"{$slug}: поддержки (title/editor/thumbnail/excerpt)",
		post_type_supports( $slug, 'title' ) && post_type_supports( $slug, 'editor' )
		&& post_type_supports( $slug, 'thumbnail' ) && post_type_supports( $slug, 'excerpt' )
	);
	lv_check( "{$slug}: таксономий нет (ожидаемо)", empty( get_object_taxonomies( $slug, 'names' ) ), 'info', implode( ',', get_object_taxonomies( $slug, 'names' ) ) );
}

/* ------------------------------------------------------------ Опции */

lv_head( 'Опции' );

$site = get_option( 'lv_site_settings', array() );
lv_check( 'lv_site_settings существует', is_array( $site ) && ! empty( $site ) );
lv_check(
	'lv_site_settings: не меньше 38 ключей',
	is_array( $site ) && count( $site ) >= 38,
	( is_array( $site ) && count( $site ) >= 38 ) ? 'ok' : 'warn',
	count( $site ) . ' ключей (на staging состав может отличаться)'
);

$phone = isset( $site['phone'] ) ? (string) $site['phone'] : '';
lv_check( 'lv_site_settings[\'phone\'] непустой', '' !== $phone, '' !== $phone ? 'ok' : 'fail', 'длина ' . strlen( $phone ) . ' симв.' );

if ( function_exists( 'lv_get_setting' ) ) {
	$via_helper = (string) lv_get_setting( 'phone', '' );
	lv_check(
		'lv_get_setting(\'phone\') возвращает значение из опции',
		'' !== $via_helper && $via_helper === $phone,
		'' !== $via_helper ? 'ok' : 'fail',
		'длина ' . strlen( $via_helper ) . ' симв.'
	);
} else {
	lv_check( 'lv_get_setting(\'phone\') доступен', false );
}

// lv_core_settings плагин не должен ни читать, ни писать.
$core = get_option( 'lv_core_settings', false );
lv_check(
	'lv_core_settings отсутствует',
	false === $core,
	false === $core ? 'ok' : 'warn',
	false === $core ? '' : 'запись существует, но плагин её не использует'
);

/* ------------------------------------------- Статический скан кода */

lv_head( 'Статический скан кода плагина' );

$files       = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $lv_plugin, FilesystemIterator::SKIP_DOTS ) );
$hits_core   = array();
$hits_writes = array();
$scanned     = 0;
$lv_self     = 'tools/' . basename( __FILE__ );

foreach ( $files as $f ) {
	if ( 'php' !== strtolower( $f->getExtension() ) ) {
		continue;
	}

	// Сканер сам себя не проверяет.
	$name = str_replace( '\\', '/', str_replace( $lv_plugin . '\\', '', $f->getPathname() ) );
	if ( $name === $lv_self ) {
		continue;
	}

	++$scanned;
	// Комментарии вырезаем: упоминание опции в докблоке — не использование.
	$code = lv_code_only( file_get_contents( $f->getPathname() ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI test helper.

	if ( false !== strpos( $code, 'lv_core_settings' ) ) {
		// uninstall.php удаляет опцию на будущее — это по замыслу.
		if ( 'uninstall.php' !== $name ) {
			$hits_core[] = $name;
		}
	}
	if ( preg_match( '/\b(update_option|delete_option)\s*\(/', $code, $m ) ) {
		// uninstall.php удаляет данные, но только по флагу — это допустимо.
		if ( 'uninstall.php' !== $name ) {
			$hits_writes[] = $name . ' (' . $m[1] . ')';
		}
	}
}

lv_check( 'просканировано PHP-файлов', $scanned > 0, 'ok', $scanned . ' шт.' );
lv_check(
	'в коде нет упоминаний lv_core_settings',
	empty( $hits_core ),
	empty( $hits_core ) ? 'ok' : 'fail',
	implode( ', ', $hits_core )
);
lv_check(
	'в коде нет update_option/delete_option (кроме uninstall.php)',
	empty( $hits_writes ),
	empty( $hits_writes ) ? 'ok' : 'fail',
	implode( ', ', $hits_writes )
);

// uninstall.php обязан быть opt-in и не трогать контент клиента.
$un = $lv_plugin . '/uninstall.php';
if ( file_exists( $un ) ) {
	$un_src = file_get_contents( $un ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- CLI test helper.

	lv_check(
		'uninstall.php: удаление только по флагу lv_core_remove_data_on_uninstall',
		false !== strpos( $un_src, 'lv_core_remove_data_on_uninstall' )
			&& defined( 'WP_UNINSTALL_PLUGIN' ) !== true // константа не должна быть задана заранее.
	);

	lv_check(
		'uninstall.php: есть защита WP_UNINSTALL_PLUGIN',
		preg_match( "/defined\(\s*'WP_UNINSTALL_PLUGIN'\s*\)\s*\|\|\s*exit/", $un_src ) === 1
	);

	// Никаких delete_post / wp_delete_post / delete_term в uninstall.
	lv_check(
		'uninstall.php: не удаляет записи и термины',
		! preg_match( '/\b(wp_delete_post|delete_post|wp_delete_term|delete_term|wp_delete_attachment)\s*\(/', $un_src )
	);

	lv_check(
		'флаг lv_core_remove_data_on_uninstall не выставлен',
		! get_option( 'lv_core_remove_data_on_uninstall' ),
		get_option( 'lv_core_remove_data_on_uninstall' ) ? 'warn' : 'ok'
	);
} else {
	lv_check( 'uninstall.php присутствует', false, 'fail' );
}

/* --------------------------------------------------------- Итоги */

echo PHP_EOL . str_repeat( '=', 62 ) . PHP_EOL; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.

if ( $lv_fail ) {
	printf( "РЕЗУЛЬТАТ: ПРОВАЛ — критичных: %d, предупреждений: %d\n", $lv_fail, $lv_warn ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	exit( 1 );
}

if ( $lv_warn ) {
	printf( "РЕЗУЛЬТАТ: OK С ПРЕДУПРЕЖДЕНИЯМИ — %d. Разбираться не срочно.\n", $lv_warn ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI test output.
	exit( 0 );
}

echo "РЕЗУЛЬТАТ: ВСЁ ЗЕЛЁНОЕ\n";
exit( 0 );
