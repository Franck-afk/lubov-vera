<?php
/**
 * The template for displaying all single posts
 *
 * @package lubov-vera
 */

get_header();

while ( have_posts() ) :
	the_post();

	$lv_post_id   = get_the_ID();
	$lv_post_type = get_post_type();

	// Получаем настройки оформления.
	$layout        = 'testimony' === $lv_post_type ? 'testimony' : ( 'ministry' === $lv_post_type ? 'ministry' : 'news' );
	$gallery       = get_post_meta( $lv_post_id, '_lv_post_gallery', true ) ? get_post_meta( $lv_post_id, '_lv_post_gallery', true ) : array();
	$single_img_id = get_post_meta( $lv_post_id, '_lv_single_image', true );

	$author_role = get_post_meta( $lv_post_id, '_lv_author_role', true ) ? get_post_meta( $lv_post_id, '_lv_author_role', true ) : 'Главный пастор';
	$author_id   = get_the_author_meta( 'ID' );

	$hero_data    = function_exists( 'lv_get_hero_data' ) ? lv_get_hero_data() : array();
	$church_addr  = function_exists( 'lv_get_address' ) ? lv_get_address() : '';
	$church_phone = function_exists( 'lv_get_phone' ) ? lv_get_phone() : '';
	$church_email = function_exists( 'lv_get_email' ) ? lv_get_email() : '';
	$hero_badge   = isset( $hero_data['badge'] ) && $hero_data['badge'] ? $hero_data['badge'] : 'Вера, действующая любовь';

	// Автоматические данные.
	$display_views        = lv_get_post_views_display( $lv_post_id );
	$display_reading_time = lv_get_reading_time( $lv_post_id );
	$display_date         = get_the_date( 'j F Y' );
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
					<span class="breadcrumbs__current"><?php echo esc_html( get_the_title() ); ?></span>
				</li>
			</ul>
		</nav>

		<!-- ИЗОБРАЖЕНИЕ: Слайдер (для Служений) или Одиночное фото (для Свидетельств) -->
		<?php if ( 'ministry' === $layout && ! empty( $gallery ) ) : ?>
			<!-- Слайдер для Служений -->
			<div class="single-content-carousel swiper">
				<div class="swiper-wrapper">
				<?php foreach ( $gallery as $img_id ) : ?>
					<div class="swiper-slide">
						<img
						src="<?php echo esc_url( wp_get_attachment_image_url( $img_id, 'full' ) ); ?>"
						alt="<?php the_title_attribute(); ?>"
						class="single-content-image"
						/>
					</div>
				<?php endforeach; ?>
				</div>
				<div class="swiper-pagination"></div>
			</div>
		<?php elseif ( 'testimony' === $layout ) : ?>
			<!-- Одиночное фото для Свидетельств -->
			<div class="single-main-image">
				<?php
				$img_url = $single_img_id ? wp_get_attachment_image_url( $single_img_id, 'full' ) : get_the_post_thumbnail_url( get_the_ID(), 'full' );
				if ( $img_url ) :
					?>
					<img src="<?php echo esc_url( $img_url ); ?>" alt="<?php the_title_attribute(); ?>" class="single-main-image__img" loading="eager" />
				<?php endif; ?>
			</div>
		<?php elseif ( has_post_thumbnail() ) : ?>
			<!-- Обычный пост (Миниатюра) -->
			<div class="single-hero">
				<?php the_post_thumbnail( 'full', array( 'class' => 'single-hero__image' ) ); ?>
			</div>
		<?php endif; ?>

		<!-- Заголовок и метаданные -->
		<div class="single-article">
			<div class="single-church-badge">
			<i class="fa-regular fa-heart"></i>
			<span><?php echo esc_html( $hero_badge ); ?></span>
			</div>

			<h1 class="single-title"><?php echo esc_html( get_the_title() ); ?></h1>

			<!-- Автор -->
			<div class="single-author">
			<?php
			$cards_key   = 'ministry' === $lv_post_type ? 'ministries_cards' : 'testimonies_cards';
			$lv_cf_cards = lv_get_setting( $cards_key, array() );
			$show_avatar = ( $lv_cf_cards['show_avatar_single'] ?? '1' ) === '1';
			if ( $show_avatar ) {
				$avatar_src = ! empty( $lv_cf_cards['avatar_image'] ) ? $lv_cf_cards['avatar_image'] : get_avatar_url( $author_id, array( 'size' => 100 ) );
				echo lv_media_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted markup from lv_media_image().
					$avatar_src,
					'thumbnail',
					array(
						'class' => 'single-author__avatar',
						'style' => 'width:100px;height:100px;border-radius:50%;object-fit:cover;',
					)
				);
			}
			?>
			<div class="single-author__info">
				<h3 class="single-author__name"><?php echo esc_html( get_the_author() ); ?></h3>
				<p class="single-author__role"><?php echo esc_html( $author_role ); ?></p>
				<div class="single-author__meta">
				<span class="single-author__meta-item">
					<i class="fa-regular fa-calendar-alt"></i>
					<span><?php echo esc_html( $display_date ); ?></span>
				</span>
				<span class="single-author__meta-divider">•</span>
				<span class="single-author__meta-item">
					<i class="fa-regular fa-eye"></i>
					<span><?php echo esc_html( $display_views ); ?></span>
				</span>
				<span class="single-author__meta-divider">•</span>
				<span class="single-author__meta-item">
					<i class="fa-regular fa-clock"></i>
					<span><?php echo esc_html( $display_reading_time ); ?></span>
				</span>
				</div>
			</div>
			</div>
		</div>

		<!-- Основная сетка -->
		<div class="single-grid">
			<div class="single-content">
			<article class="single-body">
				<?php the_content(); ?>
			</article>

			<!-- Навигация -->
			<?php
			$current_date = get_the_date( 'Y-m-d H:i:s' );
			$current_id   = get_the_ID();

			$prev_post = get_posts(
				array(
					'post_type'      => $lv_post_type,
					'posts_per_page' => 1,
					'post_status'    => 'publish',
					'date_query'     => array(
						array(
							'before'    => $current_date,
							'inclusive' => false,
						),
					),
					'orderby'        => 'date',
					'order'          => 'DESC',
					'post__not_in'   => array( $current_id ),
				)
			);
			$prev_post = ! empty( $prev_post ) ? $prev_post[0] : null;

			$next_post = get_posts(
				array(
					'post_type'      => $lv_post_type,
					'posts_per_page' => 1,
					'post_status'    => 'publish',
					'date_query'     => array(
						array(
							'after'     => $current_date,
							'inclusive' => false,
						),
					),
					'orderby'        => 'date',
					'order'          => 'ASC',
					'post__not_in'   => array( $current_id ),
				)
			);
			$next_post = ! empty( $next_post ) ? $next_post[0] : null;
			?>
			<div class="post-navigation">
				<?php if ( $prev_post ) : ?>
				<a href="<?php echo esc_url( get_permalink( $prev_post->ID ) ); ?>" class="post-nav-link post-nav-link--prev">
				<div class="post-nav-arrow">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M19 12H5M12 19l-7-7 7-7"/>
					</svg>
				</div>
				<div class="post-nav-content">
					<span class="post-nav-label">Предыдущая запись</span>
					<span class="post-nav-title"><?php echo esc_html( $prev_post->post_title ); ?></span>
				</div>
				</a>
				<?php endif; ?>

				<?php if ( $next_post ) : ?>
				<a href="<?php echo esc_url( get_permalink( $next_post->ID ) ); ?>" class="post-nav-link post-nav-link--next">
				<div class="post-nav-content">
					<span class="post-nav-label">Следующая запись</span>
					<span class="post-nav-title"><?php echo esc_html( $next_post->post_title ); ?></span>
				</div>
				<div class="post-nav-arrow">
					<svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M5 12h14M12 5l7 7-7 7"/>
					</svg>
				</div>
				</a>
				<?php endif; ?>
			</div>
			</div>

			<!-- Сайдбар с виджетом "Информация о церкви" -->
			<aside id="secondary" class="single-sidebar widget-area">
				<?php dynamic_sidebar( 'sidebar-1' ); ?>
			</aside>
		</div>
	</div>
</main>

	<?php
endwhile;

get_footer();