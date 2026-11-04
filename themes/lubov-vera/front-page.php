<?php
/**
 * The front page template file
 *
 * @package lubov-vera
 */

get_header();

?>

<main class="main">
	<!-- Hero -->
	<?php get_template_part( 'template-parts/hero' ); ?>

	<!-- Таймер до эфира -->
	<?php get_template_part( 'template-parts/countdown' ); ?>

	<!-- Расписание -->
	<?php get_template_part( 'template-parts/schedule' ); ?>

	<!-- Карточки -->
	<?php get_template_part( 'template-parts/cards' ); ?>

	<!-- Публикации -->
	<?php get_template_part( 'template-parts/posts-swiper' ); ?>

	<!-- События (карусель Swiper) -->
	<?php get_template_part( 'template-parts/events-swiper' ); ?>

	<!-- Календарь служений (отрисовывается через JS) -->
	<?php get_template_part( 'template-parts/calendar' ); ?>
</main>

<?php get_footer(); ?>
