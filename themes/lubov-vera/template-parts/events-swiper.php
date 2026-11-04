<?php
/**
 * Template part: карусель событий (Swiper) на главной.
 *
 * Выводится между блоком трансляций и календарём. Три карточки в ряду
 * на широких экранах. Карточка — картинка на фоне + оверлей: вверху слева
 * аватар автора, имя автора и дата публикации, внизу слева заголовок
 * и описание. Блок исчезает, если нет ни одного события с названием или
 * переключатель events_show выключен на экране настроек.
 *
 * @package lubov-vera
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$events_show     = function_exists( 'lv_get_setting' ) ? (string) lv_get_setting( 'events_show', '1' ) : '1';
$events_title    = function_exists( 'lv_get_setting' ) ? (string) lv_get_setting( 'events_title', '' ) : '';
$events_subtitle = function_exists( 'lv_get_setting' ) ? (string) lv_get_setting( 'events_subtitle', '' ) : '';
$events          = function_exists( 'lv_get_events_data' ) ? lv_get_events_data() : array();

if ( '1' !== $events_show || empty( $events ) ) {
	return;
}

if ( '' === trim( $events_title ) ) {
	$events_title = 'События церкви';
}
?>

<section class="events-swiper-section" id="events">
	<div class="container">
		<div class="events-swiper-section__header">
			<div class="events-swiper-section__heading">
				<h2 class="section-title"><?php echo esc_html( $events_title ); ?></h2>
				<?php if ( '' !== trim( $events_subtitle ) ) : ?>
				<p class="events-swiper-section__subtitle"><?php echo esc_html( $events_subtitle ); ?></p>
				<?php endif; ?>
			</div>

			<div class="swiper-navigation">
				<div class="swiper-button-prev" aria-label="Предыдущее событие"></div>
				<div class="swiper-button-next" aria-label="Следующее событие"></div>
			</div>
		</div>

		<div class="swiper events-swiper">
			<div class="swiper-wrapper">
				<?php
				foreach ( $events as $event ) :
					$event_title  = (string) $event['title'];
					$event_author = (string) $event['author_name'];
					$event_avatar = (string) $event['author_avatar'];
					$event_date   = (string) $event['date'];
					$event_desc   = (string) $event['desc'];
					$event_image  = (string) $event['image'];
					$event_link   = (string) $event['link'];
					$has_author   = ( '' !== $event_author || '' !== $event_avatar || '' !== $event_date );
					?>
				<div class="swiper-slide">
					<article class="event-card">
						<div class="event-card__bg">
							<?php if ( '' !== $event_image ) : ?>
							<img
								src="<?php echo esc_url( $event_image ); ?>"
								alt="<?php echo esc_attr( $event_title ); ?>"
								loading="lazy"
								decoding="async"
							/>
							<?php endif; ?>
							<div class="event-card__overlay"></div>
						</div>

						<div class="event-card__content">
							<?php if ( $has_author ) : ?>
							<div class="author-block">
								<?php if ( '' !== $event_avatar ) : ?>
								<img class="author-avatar" src="<?php echo esc_url( $event_avatar ); ?>" alt="" />
								<?php endif; ?>
								<div class="author-info">
									<?php if ( '' !== $event_author ) : ?>
									<span class="author-name"><?php echo esc_html( $event_author ); ?></span>
									<?php endif; ?>
									<?php if ( '' !== $event_date ) : ?>
									<span class="post-date"><?php echo esc_html( $event_date ); ?></span>
									<?php endif; ?>
								</div>
							</div>
							<?php endif; ?>

							<div class="event-card__text">
								<h3 class="card-title">
									<?php if ( '' !== $event_link ) : ?>
									<a class="post-title-link" href="<?php echo esc_url( $event_link ); ?>"><?php echo esc_html( $event_title ); ?></a>
									<?php else : ?>
										<?php echo esc_html( $event_title ); ?>
									<?php endif; ?>
								</h3>

								<?php if ( '' !== $event_desc ) : ?>
								<p class="card-description"><?php echo esc_html( $event_desc ); ?></p>
								<?php endif; ?>
							</div>
						</div>
					</article>
				</div>
				<?php endforeach; ?>
			</div>

			<div class="swiper-pagination"></div>
		</div>
	</div>
</section>
