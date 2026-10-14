<?php
/**
 * The template for displaying all pages
 *
 * @link https://developer.wordpress.org/themes/basics/template-hierarchy/
 *
 * @package lubov-vera
 */

get_header();
?>

	<?php if ( is_front_page() ) : ?>
		<!-- Основной контент (только для главной статической страницы) -->
		<main class="main">
			<!-- Hero секция -->
			<?php get_template_part( 'template-parts/hero' ); ?>



			<!-- Карточки -->
			<?php get_template_part( 'template-parts/cards' ); ?>

			<!-- Публикации -->
			<?php get_template_part( 'template-parts/posts-swiper' ); ?>
		</main>
	<?php else : ?>
		<main id="primary" class="site-main">
			<div class="container">
				<!-- Хлебные крошки -->
				<nav class="breadcrumbs" aria-label="Хлебные крошки">
					<ul class="breadcrumbs__list">
						<li class="breadcrumbs__item">
							<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="breadcrumbs__link">Главная</a>
						</li>
						<li class="breadcrumbs__item">
							<span class="breadcrumbs__current"><?php the_title(); ?></span>
						</li>
					</ul>
				</nav>
				<?php
				while ( have_posts() ) :
					the_post();
					?>
					<h1 class="page-title"><?php the_title(); ?></h1>
					<article class="single-body">
						<?php the_content(); ?>
					</article>
					<?php
					if ( comments_open() || get_comments_number() ) :
						comments_template();
					endif;
				endwhile;
				?>
			</div>
		</main>
	<?php endif; ?>

<?php
get_sidebar();
get_footer();
