<?php
/**
 * The main template file
 *
 * @package lubov-vera
 */

get_header();

$hero_data    = function_exists( 'lv_get_hero_data' ) ? lv_get_hero_data() : array();
$rutube_video = lv_get_latest_rutube_video();

if ( is_front_page() ) :
	?>

<main class="main">
	<!-- Основной контейнер hero -->
	<div class="container hero-container">
		<div class="content">
			<?php if ( ! empty( $hero_data['badge'] ) ) : ?>
			<div class="single-church-badge">
				<i class="fa-regular fa-heart"></i>
				<span><?php echo esc_html( $hero_data['badge'] ); ?></span>
			</div>
			<?php endif; ?>

			<?php if ( ! empty( $hero_data['title'] ) ) : ?>
			<h1><?php echo wp_kses_post( $hero_data['title'] ); ?></h1>
			<?php endif; ?>

			<?php if ( ! empty( $hero_data['desc'] ) ) : ?>
			<p><?php echo wp_kses_post( nl2br( $hero_data['desc'] ) ); ?></p>
			<?php endif; ?>

			<?php
			if ( $rutube_video ) :
				$thumb = $rutube_video['thumbnail'] ? $rutube_video['thumbnail'] : get_theme_file_uri( 'assets/img/devo.jpg' );
				?>
			<a href="#" data-video="<?php echo esc_url( $rutube_video['embed_url'] ); ?>" data-video-url="<?php echo esc_url( $rutube_video['video_url'] ); ?>" class="video-card js-video-popup">
				<div class="video-card-thumb">
					<img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $rutube_video['title'] ); ?>" class="video-card-img" loading="lazy" decoding="async" />
					<div class="video-card-play">
						<svg width="20" height="20" viewBox="0 0 24 24" fill="none"><path d="M8 5v14l11-7z" fill="#fff"/></svg>
					</div>
					<span class="video-card-duration"><?php echo esc_html( $rutube_video['duration'] ); ?></span>
				</div>
				<div class="video-card-body">
					<span class="video-card-title"><?php echo esc_html( $rutube_video['title'] ); ?></span>
					<?php if ( $rutube_video['views'] > 0 ) : ?>
					<span class="video-card-meta"><?php echo esc_html( $rutube_video['views'] ); ?> просмотров</span>
					<?php endif; ?>
				</div>
			</a>
			<?php endif; ?>
		</div>

		<div class="image-container">
			<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/devo.jpg' ) ); ?>" alt="<?php esc_attr_e( 'Hero Image', 'lubov-vera' ); ?>" loading="eager" decoding="async" />
			

		</div>
	</div>



	<!-- Карточки (Динамические) -->
	<?php get_template_part( 'template-parts/cards' ); ?>


</main>

<?php else : ?>
	<main id="primary" class="site-main">
		<div class="container">
			<?php
			if ( have_posts() ) :
				while ( have_posts() ) :
					the_post();
					get_template_part( 'template-parts/content', get_post_type() );
				endwhile;
				the_posts_navigation();
			else :
				get_template_part( 'template-parts/content', 'none' );
			endif;
			?>
		</div>
	</main>
<?php endif; ?>

<?php
get_footer();
