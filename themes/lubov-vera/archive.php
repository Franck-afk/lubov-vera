<?php
/**
 * The template for displaying archive pages
 *
 * @package lubov-vera
 */

get_header();

$lv_post_type = get_post_type();
$cpt_keys     = array(
	'testimony' => array(
		'cards'    => 'testimonies_cards',
		'title'    => 'testimonies_title',
		'desc'     => 'testimonies_desc',
		'fallback' => 'Свидетельства',
		'empty'    => 'Свидетельств пока нет.',
	),
	'ministry'  => array(
		'cards'    => 'ministries_cards',
		'title'    => 'ministries_title',
		'desc'     => 'ministries_desc',
		'fallback' => 'Служения',
		'empty'    => 'Служений пока нет.',
	),
);

if ( isset( $cpt_keys[ $lv_post_type ] ) ) :
	$key        = $cpt_keys[ $lv_post_type ];
	$c          = lv_cards_settings( $key['cards'] );
	$page_title = lv_get_setting( $key['title'], '' );
	if ( '' === $page_title ) {
		$page_title = $key['fallback'];
	}
	$page_desc = lv_get_setting( $key['desc'], '' );
	$lv_paged  = max( 1, get_query_var( 'paged' ) ? get_query_var( 'paged' ) : get_query_var( 'page' ) );
	$query     = new WP_Query(
		array(
			'post_type'      => $lv_post_type,
			'posts_per_page' => lv_cards_per_page( $key['cards'] ),
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
			<?php
			if ( $page_desc ) :
				?>
				<p class="page-description mb-8"><?php echo esc_html( $page_desc ); ?></p><?php endif; ?>

			<?php if ( $query->have_posts() ) : ?>
			<div class="cards-grid" style="grid-template-columns: repeat(<?php echo absint( $c['columns'] ? $c['columns'] : 3 ); ?>, 1fr);">
				<?php
				while ( $query->have_posts() ) :
					$query->the_post();
					?>
					<?php
					$lv_post_id    = get_the_ID();
					$lv_card_title = get_the_title();
					$permalink     = get_permalink();
					$excerpt       = get_the_excerpt();
					$author_id     = get_post_field( 'post_author', $lv_post_id );
					$author_name   = get_the_author_meta( 'display_name', $author_id );
					$avatar_url    = ! empty( $c['avatar_image'] ) ? $c['avatar_image'] : get_avatar_url( $author_id, array( 'size' => 48 ) );
					$image_size    = in_array( $c['image_size'], array( 'thumbnail', 'medium', 'large', 'full' ), true ) ? $c['image_size'] : 'medium';
					$thumb_url     = get_the_post_thumbnail_url( $lv_post_id, $image_size );
					$date          = get_the_date();
					$reading_time  = function_exists( 'lv_get_reading_time' ) ? lv_get_reading_time( $lv_post_id ) : '';
					$views         = function_exists( 'lv_get_post_views_display' ) ? lv_get_post_views_display( $lv_post_id ) : '';
					$desc_trim     = absint( $c['desc_trim'] ? $c['desc_trim'] : 200 );

					if ( $desc_trim > 0 && mb_strlen( $excerpt ) > $desc_trim ) {
						$excerpt = mb_substr( $excerpt, 0, $desc_trim ) . '…';
					}
					?>
					<div class="post-card">
						<?php if ( '1' === $c['show_photo'] && $thumb_url ) : ?>
						<a href="<?php echo esc_url( $permalink ); ?>" class="card-image-link">
							<?php
							echo get_the_post_thumbnail(
								$lv_post_id,
								$image_size,
								array(
									'class' => 'card-image',
									'alt'   => $lv_card_title,
								)
							);
							?>
						</a>
						<?php endif; ?>

						<div class="card-content">
							<?php if ( '1' === $c['show_avatar'] || '1' === $c['show_author'] || '1' === $c['show_date'] ) : ?>
							<div class="author-block">
								<?php if ( '1' === $c['show_avatar'] ) : ?>
									<?php echo lv_media_image( $avatar_url, 'thumbnail', array( 'class' => 'author-avatar' ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted markup from lv_media_image(). ?>
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

							<?php if ( '1' === $c['show_title'] && $lv_card_title ) : ?>
							<h3 class="card-title"><?php echo esc_html( $lv_card_title ); ?></h3>
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
			<nav class="pagination" style="margin-bottom: 50px;">
					<?php
					$pagination = paginate_links(
						array(
							'total'     => $query->max_num_pages,
							'current'   => $paged,
							'prev_text' => '←',
							'next_text' => '→',
						)
					);
					if ( $pagination ) {
						echo wp_kses_post( $pagination );
					}
					?>
			</nav>
			<?php endif; ?>

				<?php wp_reset_postdata(); ?>
			<?php else : ?>
				<p><?php echo esc_html( $key['empty'] ); ?></p>
			<?php endif; ?>
		</div>
	</main>
<?php else : ?>
	<?php global $wp_query; ?>
	<main id="primary" class="main" style="padding-bottom:4rem;">
		<div class="container">
			<nav class="breadcrumbs" aria-label="Хлебные крошки">
				<ul class="breadcrumbs__list">
					<li class="breadcrumbs__item">
						<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="breadcrumbs__link">Главная</a>
					</li>
					<li class="breadcrumbs__item">
						<span class="breadcrumbs__current"><?php the_archive_title(); ?></span>
					</li>
				</ul>
			</nav>
			<?php if ( have_posts() ) : ?>
				<h1 class="page-title"><?php the_archive_title(); ?></h1>
				<div class="cards-grid" style="grid-template-columns: repeat(3, 1fr);">
					<?php
					while ( have_posts() ) :
						the_post();
						$lv_post_id    = get_the_ID();
						$lv_card_title = get_the_title();
						$permalink     = get_permalink();
						$excerpt       = get_the_excerpt();
						$author_id     = get_post_field( 'post_author', $lv_post_id );
						$author_name   = get_the_author_meta( 'display_name', $author_id );
						$avatar_url    = get_avatar_url( $author_id, array( 'size' => 48 ) );
						$thumb_url     = get_the_post_thumbnail_url( $lv_post_id, 'medium' );
						$date          = get_the_date();
						$reading_time  = function_exists( 'lv_get_reading_time' ) ? lv_get_reading_time( $lv_post_id ) : '';
						$views         = function_exists( 'lv_get_post_views_display' ) ? lv_get_post_views_display( $lv_post_id ) : '';
						$desc_trim     = 200;
						if ( $desc_trim > 0 && mb_strlen( $excerpt ) > $desc_trim ) {
							$excerpt = mb_substr( $excerpt, 0, $desc_trim ) . '…';
						}
						?>
						<div class="post-card">
							<?php if ( $thumb_url ) : ?>
							<a href="<?php echo esc_url( $permalink ); ?>" class="card-image-link">
								<?php
								echo get_the_post_thumbnail(
									$lv_post_id,
									'medium',
									array(
										'class' => 'card-image',
										'alt'   => $lv_card_title,
									)
								);
								?>
							</a>
							<?php endif; ?>

							<div class="card-content">
								<div class="author-block">
									<img class="author-avatar" src="<?php echo esc_url( $avatar_url ); ?>" alt="" />
									<div class="author-info">
										<span class="author-name"><?php echo esc_html( $author_name ); ?></span>
										<span class="post-date"><?php echo esc_html( $date ); ?></span>
									</div>
								</div>

								<?php if ( $lv_card_title ) : ?>
								<h3 class="card-title"><?php echo esc_html( $lv_card_title ); ?></h3>
								<?php endif; ?>

								<?php if ( $excerpt ) : ?>
								<p class="card-description"><?php echo esc_html( $excerpt ); ?></p>
								<?php endif; ?>

								<div class="meta-info">
									<?php if ( $reading_time ) : ?>
									<span class="meta-item"><i class="fa-regular fa-clock"></i> <?php echo esc_html( $reading_time ); ?></span>
									<?php endif; ?>
									<?php if ( $views ) : ?>
									<span class="meta-item"><i class="fa-regular fa-eye"></i> <?php echo esc_html( $views ); ?></span>
									<?php endif; ?>
								</div>

								<a href="<?php echo esc_url( $permalink ); ?>" class="card-button">Подробнее</a>
							</div>
						</div>
					<?php endwhile; ?>
				</div>

				<?php if ( $wp_query->max_num_pages > 1 ) : ?>
<nav class="pagination" style="margin-bottom: 50px;">
					<?php
					echo wp_kses_post(
						paginate_links(
							array(
								'total'     => $wp_query->max_num_pages,
								'current'   => max( 1, get_query_var( 'paged' ) ),
								'prev_text' => '←',
								'next_text' => '→',
							)
						)
					);
					?>
				</nav>
				<?php endif; ?>
			<?php else : ?>
				<p>Публикаций пока нет.</p>
			<?php endif; ?>
		</div>
	</main>
<?php endif; ?>

<?php
get_footer();