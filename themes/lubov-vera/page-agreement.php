<?php
/**
 * Template Name: Соглашение ОПД
 *
 * @package lubov-vera
 */

get_header();

$page_title   = get_the_title();
$page_content = get_the_content();
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

		<div class="page-content">
			<?php echo apply_filters( 'the_content', $page_content ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Post content is already sanitized by WordPress. ?>
		</div>
	</div>
</main>

<?php
get_footer();
