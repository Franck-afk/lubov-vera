<?php
/**
 * Template part: Hero секция
 *
 * @package lubov-vera
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$hero_data = function_exists( 'lv_get_hero_data' ) ? lv_get_hero_data() : array();
$socials   = function_exists( 'lv_get_social_links' ) ? lv_get_social_links() : array();

/*
 * Если в полях hero ничего не заполнено, блок не выводится вовсе.
 * Раньше здесь всегда печаталась картинка-заглушка devo.jpg и пустая
 * обёртка — на сайте без настроек это выглядело как сломанный первый экран.
 *
 * Rutube сюда не входит: это не поле настройки, а внешняя выдача, и тянуть
 * её ради пустого блока незачем. Запрос к API тоже не делаем, если hero
 * всё равно не показываем.
 */
$lv_has_content = '' !== trim( (string) ( $hero_data['badge'] ?? '' ) )
	|| '' !== trim( (string) ( $hero_data['title'] ?? '' ) )
	|| '' !== trim( (string) ( $hero_data['desc'] ?? '' ) )
	|| '' !== trim( (string) ( $hero_data['image'] ?? '' ) )
	|| ! empty( $socials );

if ( ! $lv_has_content ) {
	return;
}

$rutube_video = lv_get_latest_rutube_video();

/*
 * Заголовок обязан существовать, иначе у страницы нет <h1> — это бьют и по
 * семантике, и по SEO. Если заголовок hero не заполнен, берём заголовок
 * самой страницы. На главной он совпадает с логотипом и крошками, поэтому
 * такой заголовок прячем визуально, но оставляем в разметке.
 */
$lv_hero_title = '' !== trim( (string) ( $hero_data['title'] ?? '' ) )
	? (string) $hero_data['title']
	: get_the_title();

// Прячем только подменённый заголовок на главной: он дублирует логотип.
$lv_title_hidden = is_front_page()
	&& '' === trim( (string) ( $hero_data['title'] ?? '' ) )
	&& '' !== trim( (string) $lv_hero_title );
?>

<div class="container hero-container">
	<div class="content">
		<?php if ( ! empty( $hero_data['badge'] ) ) : ?>
		<div class="single-church-badge">
			<i class="fa-regular fa-heart"></i>
			<span><?php echo esc_html( $hero_data['badge'] ); ?></span>
		</div>
		<?php endif; ?>

		<h1<?php echo $lv_title_hidden ? ' class="lv-visually-hidden"' : ''; ?>><?php echo wp_kses_post( $lv_hero_title ); ?></h1>

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

	<?php
	/*
	 * Правая колонка (image-container): картинка hero (если задана)
	 * и соцсети. Картинка может быть пустой — тогда показываем только
	 * соцсети в правой колонке, сохраняя двухколоночную сетку.
	 * Раньше при пустом hero_image подставлялась заглушка devo.jpg —
	 * убрано: пустое место лучше, чем чужое фото.
	 */
	if ( ! empty( $hero_data['image'] ) || ! empty( $socials ) ) :
		?>
	<div class="image-container">
		<?php if ( ! empty( $hero_data['image'] ) ) : ?>
			<?php
			echo lv_media_image( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted <img> markup from lv_media_image().
				$hero_data['image'],
				'large',
				array(
					'alt'     => __( 'Hero изображение', 'lubov-vera' ),
					'loading' => 'eager',
				)
			);
			?>
		<?php endif; ?>

		<?php if ( ! empty( $socials ) && is_array( $socials ) ) : ?>
		<div class="social-icons">
			<?php
			foreach ( $socials as $social ) :
				if ( empty( $social['url'] ) || empty( $social['type'] ) ) {
					continue;
				}
				?>
			<a href="<?php echo esc_url( $social['url'] ); ?>" target="_blank" rel="noopener noreferrer" class="social-icon">
				<img src="<?php echo esc_url( get_theme_file_uri( 'assets/icons/' . esc_attr( $social['type'] ) . '.svg' ) ); ?>" alt="<?php echo esc_attr( $social['type'] ); ?>" loading="lazy" decoding="async" />
			</a>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
	<?php endif; ?>
</div>
