<?php
/**
 * Template part: Карточки с наложением (Fullscreen Stacking Cards)
 *
 * @package lubov-vera
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$posts_title = lv_get_setting( 'posts_title', '' );
if ( '' === $posts_title ) {
	$posts_title = 'Ближайшие события';
}
$posts_subtitle = lv_get_setting( 'posts_subtitle', 'Прокрутите вниз, чтобы увидеть расписание' );
$cards          = lv_get_setting( 'posts_cards', array() );

if ( empty( $cards ) ) {
	return;
}
?>

<section class="stacking-section">
	<div class="stacking-section__header">
		<div class="container">
			<h2 class="stacking-section__title"><?php echo esc_html( $posts_title ); ?></h2>
			<?php if ( $posts_subtitle ) : ?>
			<p class="stacking-section__subtitle"><?php echo esc_html( $posts_subtitle ); ?></p>
			<?php endif; ?>
		</div>
	</div>

	<div class="stacking-cards" data-count="<?php echo count( $cards ); ?>">
		<div class="container">
		<?php foreach ( $cards as $i => $card ) : ?>
		<div
			class="stacking-card"
			data-index="<?php echo esc_attr( $i ); ?>"
			style="--card-bg: <?php echo esc_attr( $card['bg'] ?? '#c4a747' ); ?>;"
		>
			<!-- Фон карточки -->
			<div class="stacking-card__bg">
				<?php if ( ! empty( $card['image'] ) ) : ?>
					<img src="<?php echo esc_url( $card['image'] ); ?>" alt="" class="stacking-card__bg-img" />
				<?php endif; ?>
				<div class="stacking-card__overlay"></div>
			</div>

			<!-- Контент поверх фона -->
			<div class="stacking-card__content">
				<div class="stacking-card__top">
					<span class="stacking-card__number"><?php echo esc_html( str_pad( $i + 1, 2, '0', STR_PAD_LEFT ) ); ?></span>
					<?php if ( ! empty( $card['location'] ) || ! empty( $card['icon'] ) ) : ?>
					<span class="stacking-card__badge">
						<?php if ( ! empty( $card['icon'] ) ) : ?>
							<i class="<?php echo esc_attr( $card['icon'] ); ?>"></i>
						<?php endif; ?>
						<?php echo esc_html( $card['location'] ); ?>
					</span>
					<?php endif; ?>
				</div>

				<div class="stacking-card__center">
					<h3 class="stacking-card__title"><?php echo esc_html( $card['title'] ?? '' ); ?></h3>
					<?php if ( ! empty( $card['desc'] ) ) : ?>
					<p class="stacking-card__desc"><?php echo esc_html( $card['desc'] ); ?></p>
					<?php endif; ?>
				</div>

				<div class="stacking-card__bottom">
					<div class="stacking-card__meta">
						<?php if ( ! empty( $card['date'] ) ) : ?>
						<span class="stacking-card__date">
							<i class="fa-regular fa-calendar"></i>
							<?php echo esc_html( $card['date'] ); ?>
						</span>
						<?php endif; ?>
						<?php if ( ! empty( $card['location'] ) ) : ?>
						<span class="stacking-card__location">
							<i class="fa-solid fa-location-dot"></i>
							<?php echo esc_html( $card['location'] ); ?>
						</span>
						<?php endif; ?>
					</div>
					<div class="stacking-card__progress">
						<span class="stacking-card__progress-bar"></span>
					</div>
				</div>
			</div>
		</div>
		<?php endforeach; ?>
		</div>
	</div>
</section>
