<?php
/**
 * Template Name: Мы строим храм
 *
 * @package lubov-vera
 */

get_header();

$page_title         = get_the_title();
$page_heading       = '';
$page_content       = get_the_content();
$gallery            = array();
$navigation         = array();
$donation_show      = '0' !== (string) lv_get_setting( 'donation_show', '1' );
$donation_title     = lv_get_setting( 'donation_title', '' );
$donation_desc      = lv_get_setting( 'donation_desc', '' );
$donation_campaign  = absint( lv_get_setting( 'donation_campaign_id', 0 ) );
$donation_shortcode = ( $donation_show && $donation_campaign ) ? '[leyka_campaign_form id="' . $donation_campaign . '"]' : '';
?>

<main class="main">
	<div class="container">
		<!-- Хлебные крошки -->
		<nav class="breadcrumbs" aria-label="Хлебные крошки">
			<ul class="breadcrumbs__list">
				<li class="breadcrumbs__item">
					<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="breadcrumbs__link">Главная</a>
				</li>
				<li class="breadcrumbs__item">
					<span class="breadcrumbs__current"><?php echo esc_html( $page_title ); ?></span>
				</li>
			</ul>
		</nav>

		<h1 class="page-title"><?php echo esc_html( $page_title ); ?></h1>

		<!-- Контент с формой -->
		<div class="construction-content">
			<!-- Левая колонка: Слайдер + Текст -->
			<div class="construction-left">
				<!-- Слайдер для фото -->
				<?php if ( ! empty( $gallery ) ) : ?>
				<div class="construction-slider swiper donate-page-slider">
					<div class="swiper-wrapper">
						<?php foreach ( $gallery as $image_id ) : ?>
						<div class="swiper-slide">
							<img
								src="<?php echo esc_url( wp_get_attachment_image_url( $image_id, 'large' ) ); ?>"
								alt="<?php echo esc_attr( get_the_title( $page_title ) ); ?>"
								class="construction-slider__image"
								loading="eager"
							/>
						</div>
						<?php endforeach; ?>
					</div>

					<!-- Навигация слайдера -->
					<div class="swiper-button-prev" aria-label="Предыдущее фото"></div>
					<div class="swiper-button-next" aria-label="Следующее фото"></div>
					<div class="swiper-pagination"></div>
				</div>
				<?php endif; ?>

				<!-- Текстовое описание -->
				<?php if ( $page_heading || $page_content ) : ?>
				<div class="construction-description">
					<?php if ( $page_heading ) : ?>
					<h2><?php echo wp_kses_post( $page_heading ); ?></h2>
					<?php endif; ?>

					<?php if ( $page_content ) : ?>
						<?php echo apply_filters( 'the_content', $page_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Post content is already sanitized by WordPress. ?>
					<?php endif; ?>
				</div>
				<?php endif; ?>

				<!-- Навигация по постам -->
				<?php if ( ! empty( $navigation ) ) : ?>
				<div class="post-navigation">
					<?php
					$prev = $navigation[0];
					$next = isset( $navigation[1] ) ? $navigation[1] : null;
					?>
					<?php if ( ! empty( $prev['prev_url'] ) || ! empty( $prev['prev_title'] ) ) : ?>
					<a href="<?php echo esc_url( $prev['prev_url'] ); ?>" class="post-nav-link post-nav-link--prev">
						<div class="post-nav-arrow">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<path d="M19 12H5M12 19l-7-7 7-7" />
							</svg>
						</div>
						<div class="post-nav-content">
							<span class="post-nav-label"><?php echo esc_html( $prev['prev_label'] ); ?></span>
							<span class="post-nav-title"><?php echo esc_html( $prev['prev_title'] ); ?></span>
						</div>
					</a>
					<?php endif; ?>

					<?php if ( $next && ( ! empty( $next['next_url'] ) || ! empty( $next['next_title'] ) ) ) : ?>
					<a href="<?php echo esc_url( $next['next_url'] ); ?>" class="post-nav-link post-nav-link--next">
						<div class="post-nav-content">
							<span class="post-nav-label"><?php echo esc_html( $next['next_label'] ); ?></span>
							<span class="post-nav-title"><?php echo esc_html( $next['next_title'] ); ?></span>
						</div>
						<div class="post-nav-arrow">
							<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
								<path d="M5 12h14M12 5l7 7-7 7" />
							</svg>
						</div>
					</a>
					<?php endif; ?>
				</div>
				<?php endif; ?>
			</div>

			<!-- Правая колонка: форма пожертвования -->
			<?php if ( $donation_show ) : ?>
			<div class="construction-right">
				<div class="donate-card donate-card--leyka leyka-alsfund-form">
					<?php if ( $donation_title || $donation_desc ) : ?>
					<div class="donate-form__header">
						<?php if ( $donation_title ) : ?>
							<h3><?php echo esc_html( $donation_title ); ?></h3>
						<?php endif; ?>
						<?php if ( $donation_desc ) : ?>
							<p><?php echo esc_html( $donation_desc ); ?></p>
						<?php endif; ?>
					</div>
					<?php endif; ?>

					<div class="donate-form__body">
						<?php if ( $donation_shortcode ) : ?>
						<!-- Форма пожертвования (плагин Лейка) -->
							<?php echo do_shortcode( $donation_shortcode ); ?>
						<?php else : ?>
						<p class="donate-form__placeholder">
							<?php esc_html_e( 'Форма пожертвования будет доступна после настройки', 'lubov-vera' ); ?>
						</p>
						<?php endif; ?>
					</div>
				</div>
			</div>
			<?php endif; ?>
		</div>
	</div>
</main>

<?php
get_footer();
