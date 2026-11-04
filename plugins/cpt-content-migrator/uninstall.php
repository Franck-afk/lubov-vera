<?php
/**
 * Удаление данных плагина CPT Content Migrator.
 *
 * Файл выполняется WordPress при удалении плагина (не при деактивации).
 * Здесь намеренно нет зависимости от класса плагина: к моменту удаления
 * файл основного класса может быть уже удалён.
 *
 * @package CPT_Content_Migrator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

if ( ! current_user_can( 'activate_plugins' ) ) {
	exit;
}

/**
 * Удаляет папку с её содержимым.
 *
 * @param string $dir Путь к папке.
 *
 * @return void
 */
function cpt_migrator_remove_directory( $dir ) {
	if ( ! is_string( $dir ) || '' === $dir ) {
		return;
	}

	// Защита от удаления произвольных каталогов.
	$real = realpath( $dir );

	if ( false === $real ) {
		return;
	}

	$allowed = array(
		realpath( WP_CONTENT_DIR ),
		realpath( ABSPATH ),
	);

	$is_allowed = false;

	foreach ( $allowed as $base ) {
		if ( false === $base ) {
			continue;
		}

		if ( 0 === strpos( $real, $base . DIRECTORY_SEPARATOR ) ) {
			$is_allowed = true;
			break;
		}
	}

	if ( ! $is_allowed ) {
		return;
	}

	if ( is_link( $real ) ) {
		@unlink( $real );
		return;
	}

	$items = @scandir( $real );

	if ( is_array( $items ) ) {
		foreach ( $items as $item ) {
			if ( '.' === $item || '..' === $item ) {
				continue;
			}

			$path = $real . DIRECTORY_SEPARATOR . $item;

			if ( is_dir( $path ) && ! is_link( $path ) ) {
				cpt_migrator_remove_directory( $path );
			} else {
				@unlink( $path );
			}
		}
	}

	@rmdir( $real );
}

/**
 * Удаляет опции плагина.
 *
 * @return void
 */
function cpt_migrator_delete_options() {
	delete_option( 'cpt_migrator_settings' );
	delete_option( 'cpt_migrator_log' );
	delete_option( 'cpt_migrator_detected_post_types' );

	// Транзиенты сессий импорта и результатов.
	global $wpdb;

	$like = $wpdb->esc_like( '_transient_cpt_migrator_' ) . '%';
	$keys = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );

	foreach ( (array) $keys as $key ) {
		delete_option( $key );
	}

	$like = $wpdb->esc_like( '_transient_timeout_cpt_migrator_' ) . '%';
	$keys = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $like ) );

	foreach ( (array) $keys as $key ) {
		delete_option( $key );
	}
}

/**
 * Снимает расписание wp-cron.
 *
 * @return void
 */
function cpt_migrator_clear_cron() {
	$hooks = array( 'cpt_migrator_auto_export' );

	foreach ( $hooks as $hook ) {
		$timestamp = wp_next_scheduled( $hook );

		while ( $timestamp ) {
			wp_unschedule_event( $timestamp, $hook );
			$timestamp = wp_next_scheduled( $hook );
		}
	}

	if ( function_exists( 'wp_clear_scheduled_hook' ) ) {
		wp_clear_scheduled_hook( 'cpt_migrator_auto_export' );
	}
}

// Удаляем расписание, опции, папку автоэкспорта и временные файлы.
cpt_migrator_clear_cron();
cpt_migrator_delete_options();

$uploads = wp_upload_dir();

if ( ! empty( $uploads['basedir'] ) ) {
	cpt_migrator_remove_directory( trailingslashit( $uploads['basedir'] ) . 'cpt-migrator' );
	cpt_migrator_remove_directory( trailingslashit( $uploads['basedir'] ) . 'cpt-migrator-tmp' );
}

if ( function_exists( 'get_temp_dir' ) ) {
	cpt_migrator_remove_directory( trailingslashit( get_temp_dir() ) . 'cpt-migrator-import' );
}
