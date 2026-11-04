<?php
/**
 * Дочерняя тема «Любовь и Вера»: мост Font Library.
 *
 * Подключает семейство шрифта из Font Library WordPress к body и заголовкам.
 *
 * @package Lubov_Vera_Child
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// BEGIN FONT LIBRARY BRIDGE.
/**
 * Apply a font uploaded via the WordPress Font Library (Site Editor -> Fonts)
 * on the front end.
 *
 * WHY: `lubov-vera` is a classic theme and hardcodes `font-family: "Inter"` in
 * style.css. Classic themes do not apply Font Library fonts automatically, so a
 * font uploaded in WP would never render without this bridge.
 *
 * SCOPE (decided): SITE-WIDE — body text + headings. If the agreed scope ever
 * changes (headings-only, or a single accent element), edit the SELECTORS
 * constant below; nothing else needs to change.
 *
 * The applied family is EXPLICIT and order-independent: it matches by name
 * (case-insensitive) and can be re-pointed with the `lv_site_font_family`
 * filter. If the named family is not in the Font Library, NOTHING is printed and
 * the theme keeps its own font — we deliberately never fall back to "the first
 * uploaded font" (that would silently depend on array order).
 */
if ( ! function_exists( 'lv_site_font_family_name' ) ) {
	/**
	 * Имя семейства шрифта из Font Library, применяемого ко всему сайту.
	 *
	 * @return string
	 */
	function lv_site_font_family_name() {
		/**
		 * Filter the Font Library family applied site-wide.
		 *
		 * @param string $name Family name (must exist in the Font Library).
		 */
		return (string) apply_filters( 'lv_site_font_family', 'Gilroy' );
	}
}

if ( ! function_exists( 'lv_font_library_stack' ) ) {
	/**
	 * CSS-стек шрифтов для выбранного семейства из Font Library.
	 *
	 * @return string
	 */
	function lv_font_library_stack() {
		$target = lv_site_font_family_name();
		if ( '' === $target || ! function_exists( 'wp_get_global_settings' ) ) {
			return '';
		}

		$families = wp_get_global_settings( array( 'typography', 'fontFamilies' ) );
		$custom   = isset( $families['custom'] ) && is_array( $families['custom'] ) ? $families['custom'] : array();

		$family = '';
		foreach ( $custom as $item ) {
			if ( ! empty( $item['fontFamily'] )
				&& 0 === strcasecmp( trim( (string) $item['fontFamily'] ), $target )
			) {
				$family = trim( preg_replace( '/[^A-Za-z0-9 \-]/', '', (string) $item['fontFamily'] ) );
				break;
			}
		}
		if ( '' === $family ) {
			return '';
		}

		// System stack only — "Inter" is intentionally omitted because it is not
		// loaded anywhere on this site (see theme style.css).
		return "'" . $family . "', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif";
	}
}

if ( ! function_exists( 'lv_enqueue_font_library_style' ) ) {
	/**
	 * Подключает инлайновый стиль со шрифтом Font Library.
	 *
	 * @return void
	 */
	function lv_enqueue_font_library_style() {
		$stack = lv_font_library_stack();
		if ( '' === $stack ) {
			return;
		}

		$selectors = 'body,.page-title,h1,h2,h3,h4,h5,h6';

		// Enqueue (not a raw wp_head echo) and depend on the last theme
		// stylesheet so the rule is printed AFTER the theme's own font-family
		// declarations and wins on order. Note: wp_enqueue_style() with a falsy
		// $src does NOT register the handle, so register it explicitly first —
		// otherwise wp_add_inline_style() silently has nothing to attach to.
		$deps = wp_style_is( 'lubov-vera-dark-theme', 'enqueued' ) ? array( 'lubov-vera-dark-theme' ) : array();

		if ( ! wp_style_is( 'lv-site-font', 'registered' ) ) {
			wp_register_style( 'lv-site-font', false, $deps, '1.0.0' );
		}
		wp_enqueue_style( 'lv-site-font' );
		wp_add_inline_style( 'lv-site-font', $selectors . '{font-family:' . $stack . ';}' );
	}
}
add_action( 'wp_enqueue_scripts', 'lv_enqueue_font_library_style', 20 );
// END FONT LIBRARY BRIDGE.
