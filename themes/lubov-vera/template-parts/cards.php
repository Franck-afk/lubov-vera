<?php
/**
 * Template part: вывод сетки карточек.
 *
 * @package LV_Theme
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cards = function_exists( 'lv_get_cards_data' ) ? lv_get_cards_data() : array();
if ( empty( $cards ) ) {
	return;
}
?>

<section class="cards-section">
	<div class="container">
		<div class="cards-grid">
			<?php foreach ( $cards as $card ) : ?>
			<div class="card">
				<?php
				$icon_url = $card['icon'] ?? '';
				if ( $icon_url ) :
					$icons_dir = get_theme_file_uri( 'assets/icons/' );
					$is_inline = strpos( $icon_url, $icons_dir ) === 0 && substr( $icon_url, -4 ) === '.svg';
					?>
				<div class="card-icon">
					<?php
					if ( $is_inline ) {
						$icon_path = get_theme_file_path( 'assets/icons/' . basename( $icon_url ) );
						if ( file_exists( $icon_path ) ) {
							readfile( $icon_path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_readfile -- Local theme SVG icon inlined for direct styling.
						}
					} else {
						echo '<img src="' . esc_url( $icon_url ) . '" alt="" loading="lazy" decoding="async" />';
					}
					?>
				</div>
				<?php endif; ?>
				<?php
				if ( ! empty( $card['link'] ) ) :
					?>
					<a href="<?php echo esc_url( $card['link'] ); ?>" class="card__link"><?php endif; ?>
				<h3><?php echo esc_html( $card['title'] ); ?></h3>
				<?php if ( ! empty( $card['desc'] ) ) : ?>
				<p><?php echo wp_kses_post( nl2br( $card['desc'] ) ); ?></p>
				<?php endif; ?>
				<?php
				if ( ! empty( $card['link'] ) ) :
					?>
					</a><?php endif; ?>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
</section>
