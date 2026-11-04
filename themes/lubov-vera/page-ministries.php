<?php
/**
 * Template Name: Служения
 *
 * @package lubov-vera
 */

get_header();

$c = lv_cards_settings( 'ministries_cards' );

$page_title = lv_get_setting( 'ministries_title', '' );
if ( '' === $page_title ) {
	$page_title = get_the_title();
}
$page_desc = lv_get_setting( 'ministries_desc', '' );

$lv_paged = max( 1, get_query_var( 'paged' ) ? get_query_var( 'paged' ) : get_query_var( 'page' ) );

$query = new WP_Query(
	array(
		'post_type'      => 'ministry',
		'posts_per_page' => lv_cards_per_page( 'ministries_cards' ),
		'paged'          => $lv_paged,
	)
);
?>

<main id="primary" class="main" style="padding-bottom:4rem;">
	<div class="container">
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

		<?php if ( $page_desc ) : ?>
		<p class="page-description mb-8"><?php echo esc_html( $page_desc ); ?></p>
		<?php endif; ?>

		<?php if ( $query->have_posts() ) : ?>
		<div class="cards-grid" style="grid-template-columns: repeat(<?php echo absint( $c['columns'] ? $c['columns'] : 3 ); ?>, 1fr);">
			<?php
			while ( $query->have_posts() ) :
				$query->the_post();
				?>
				<?php
				$lv_post_id   = get_the_ID();
				$lv_title     = get_the_title();
				$permalink    = get_permalink();
				$excerpt      = get_the_excerpt();
				$author_id    = get_post_field( 'post_author', $lv_post_id );
				$author_name  = get_the_author_meta( 'display_name', $author_id );
				$avatar_url   = ! empty( $c['avatar_image'] ) ? $c['avatar_image'] : get_avatar_url( $author_id, array( 'size' => 48 ) );
				$image_size   = in_array( $c['image_size'], array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $c['image_size'] : 'medium';
				$thumb_url    = get_the_post_thumbnail_url( $lv_post_id, $image_size );
				$thumb_html   = get_the_post_thumbnail(
					$lv_post_id,
					$image_size,
					array(
						'class' => 'card-image',
						'alt'   => $lv_title,
					)
				);
				$date         = get_the_date();
				$reading_time = function_exists( 'lv_get_reading_time' ) ? lv_get_reading_time( $lv_post_id ) : '';
				$views        = function_exists( 'lv_get_post_views_display' ) ? lv_get_post_views_display( $lv_post_id ) : '';
				$desc_trim    = absint( $c['desc_trim'] ? $c['desc_trim'] : 200 );

				if ( $desc_trim > 0 && mb_strlen( $excerpt ) > $desc_trim ) {
					$excerpt = mb_substr( $excerpt, 0, $desc_trim ) . '…';
				}
				?>
				<div class="post-card">
					<?php if ( '1' === $c['show_photo'] && $thumb_url ) : ?>
					<a href="<?php echo esc_url( $permalink ); ?>" class="card-image-link">
						<?php echo wp_kses_post( $thumb_html ); ?>
					</a>
					<?php endif; ?>

					<div class="card-content">
						<?php if ( '1' === $c['show_avatar'] || '1' === $c['show_author'] || '1' === $c['show_date'] ) : ?>
						<div class="author-block">
							<?php if ( '1' === $c['show_avatar'] ) : ?>
								<img class="author-avatar" src="<?php echo esc_url( $avatar_url ); ?>" alt="" />
							<?php endif; ?>
							<div class="author-info">
								<?php if ( '1' === $c['show_author'] ) : ?>
									<span class="author-name"><?php echo esc_html( $author_name ); ?></span>
								<?php endif; ?>
								<?php if ( '1' === $c['show_date'] ) : ?>
								<span class="post-date"><?php echo esc_html( $date ); ?></span>
								<?php endif; ?>
							</div>
						</div>
						<?php endif; ?>

						<?php if ( '1' === $c['show_title'] && $lv_title ) : ?>
						<h3 class="card-title"><?php echo esc_html( $lv_title ); ?></h3>
						<?php endif; ?>

						<?php if ( '1' === $c['show_desc'] && $excerpt ) : ?>
						<p class="card-description"><?php echo esc_html( $excerpt ); ?></p>
						<?php endif; ?>

						<?php if ( '1' === $c['show_reading_time'] || '1' === $c['show_views'] ) : ?>
						<div class="meta-info">
							<?php if ( '1' === $c['show_reading_time'] && $reading_time ) : ?>
								<span class="meta-item"><i class="fa-regular fa-clock"></i> <?php echo esc_html( $reading_time ); ?></span>
							<?php endif; ?>
							<?php if ( '1' === $c['show_views'] && $views ) : ?>
								<span class="meta-item"><i class="fa-regular fa-eye"></i> <?php echo esc_html( $views ); ?></span>
							<?php endif; ?>
						</div>
						<?php endif; ?>

						<?php if ( '1' === $c['show_readmore'] ) : ?>
						<a href="<?php echo esc_url( $permalink ); ?>" class="card-button"><?php echo esc_html( $c['readmore_text'] ? $c['readmore_text'] : 'Подробнее' ); ?></a>
						<?php endif; ?>
					</div>
				</div>
			<?php endwhile; ?>
		</div>

			<?php if ( $query->max_num_pages > 1 ) : ?>
		<nav class="pagination">
				<?php
				echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Core pagination markup.
					array(
						'total'     => $query->max_num_pages,
						'current'   => $lv_paged,
						'prev_text' => '←',
						'next_text' => '→',
					)
				);
				?>
		</nav>
		<?php endif; ?>

		<?php else : ?>
			<p>Служений пока нет.</p>
		<?php endif; ?>
		<?php wp_reset_postdata(); ?>
	</div>
</main>

<?php
get_footer();
