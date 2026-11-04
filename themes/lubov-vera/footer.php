<?php
/**
 * The template for displaying the footer
 *
 * Contains the closing of the #content div and all content after.
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package lubov-vera
 */

$footer_addr     = function_exists( 'lv_get_address' ) ? lv_get_address() : '';
$footer_phone    = function_exists( 'lv_get_phone' ) ? lv_get_phone() : '';
$footer_email    = function_exists( 'lv_get_email' ) ? lv_get_email() : '';
$footer_schedule = function_exists( 'lv_get_schedule_groups' ) ? lv_get_schedule_groups() : array();
?>

	<!-- Подвал сайта -->
	<footer class="footer" style="margin-top: 50px;">
		<div class="container">
		<div class="footer__grid">
			<div class="footer__col">
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="footer__logo-link">
				<?php
				if ( has_custom_logo() ) {
					the_custom_logo();
				} else {
					?>
				<img
					src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logotip.webp' ) ); ?>"
					alt="<?php bloginfo( 'name' ); ?>"
					class="footer__logo"
					loading="lazy"
					decoding="async"
				/>
					<?php
				}
				?>
			</a>
			</div>

			<div class="footer__col">
			<h4 class="footer__title"><?php esc_html_e( 'Документы', 'lubov-vera' ); ?></h4>
			<?php
			if ( has_nav_menu( 'footer-documents' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'footer-documents',
						'menu_class'     => 'footer__list',
						'container'      => false,
						'fallback_cb'    => false,
					)
				);
			} else {
				?>
				<ul class="footer__list">
				<li><a href="#" class="footer__link"><?php esc_html_e( 'Документы', 'lubov-vera' ); ?></a></li>
				<li><a href="#" class="footer__link"><?php echo wp_kses_post( __( 'Политика обработки<br>персональных&nbsp;данных', 'lubov-vera' ) ); ?></a></li>
				<li><a href="#" class="footer__link"><?php echo wp_kses_post( __( 'Соглашение на&nbsp;обработку<br>персональных&nbsp;данных', 'lubov-vera' ) ); ?></a></li>
				</ul>
				<?php
			}
			?>
			</div>

			<div class="footer__col">
			<h4 class="footer__title"><?php esc_html_e( 'Навигация', 'lubov-vera' ); ?></h4>
			<?php
			if ( has_nav_menu( 'footer-navigation' ) ) {
				wp_nav_menu(
					array(
						'theme_location' => 'footer-navigation',
						'menu_class'     => 'footer__list',
						'container'      => false,
						'fallback_cb'    => false,
					)
				);
			} else {
				?>
				<ul class="footer__list">
				<li><a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="footer__link"><?php esc_html_e( 'Главная', 'lubov-vera' ); ?></a></li>
				<li><a href="#" class="footer__link"><?php esc_html_e( 'О церкви', 'lubov-vera' ); ?></a></li>
				<li><a href="#" class="footer__link"><?php esc_html_e( 'Служения', 'lubov-vera' ); ?></a></li>
				<li><a href="#" class="footer__link"><?php esc_html_e( 'Свидетельства', 'lubov-vera' ); ?></a></li>
				<li><a href="#" class="footer__link"><?php esc_html_e( 'Мы строим храм', 'lubov-vera' ); ?></a></li>
				</ul>
				<?php
			}
			?>
			</div>

			<div class="footer__col">
			<h4 class="footer__title"><?php esc_html_e( 'Адрес церкви', 'lubov-vera' ); ?></h4>
			<?php if ( $footer_addr ) : ?>
			<p class="footer__text"><?php echo esc_html( $footer_addr ); ?></p>
			<?php endif; ?>

			<?php if ( $footer_phone ) : ?>
			<p class="footer__text"><?php /* translators: %s: church phone number. */ printf( esc_html__( 'Тел. %s', 'lubov-vera' ), esc_html( $footer_phone ) ); ?></p>
			<?php endif; ?>

			<?php if ( $footer_email ) : ?>
			<p class="footer__text"><?php /* translators: %s: church email address. */ printf( esc_html__( 'E-mail: %s', 'lubov-vera' ), esc_html( $footer_email ) ); ?></p>
			<?php endif; ?>
			
			<?php if ( ! empty( $footer_schedule ) ) : ?>
			<p class="footer__text">
				<?php esc_html_e( 'Расписание служений:', 'lubov-vera' ); ?><br />
				<?php
				$footer_schedule_lines = array();

				foreach ( $footer_schedule as $footer_group ) {
					if ( empty( $footer_group['times'] ) ) {
						continue;
					}

					$footer_schedule_lines[] = $footer_group['short'] . ' ' . implode( ', ', $footer_group['times'] );
				}

				echo esc_html( implode( ', ', $footer_schedule_lines ) );
				?>
			</p>
			<?php endif; ?>

			</div>
		</div>

		<div class="footer__bottom">
			<p class="footer__copyright">
			<?php echo esc_html( date_i18n( 'Y' ) ); ?> &copy; <?php esc_html_e( 'Местная религиозная организация Церковь христиан веры евангельской "Вера, действующая любовью" в г. Ижевске. Все права защищены.', 'lubov-vera' ); ?>
			</p>
		</div>
		</div>
	</footer>

</div><!-- #page -->

<?php wp_footer(); ?>

<div class="video-modal" id="videoModal">
	<div class="video-modal__overlay" id="videoModalOverlay"></div>
	<div class="video-modal__content">
		<button class="video-modal__close" id="videoModalClose" aria-label="Закрыть">
			<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
		</button>
		<div class="video-modal__iframe" id="videoModalIframe"></div>
		<a href="#" class="video-modal__rutube" id="videoModalRutube" target="_blank" rel="noopener noreferrer" aria-label="Посмотреть на Rutube">
			<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M22 5.5V19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5.5A2 2 0 0 1 4 3.5h16a2 2 0 0 1 2 2z"/><path d="M10 9l6 3-6 3V9z"/></svg>
			<span>Посмотреть на Rutube</span>
		</a>
	</div>
</div>

<script nonce="<?php echo esc_attr( $GLOBALS['lv_csp_nonce'] ); ?>">
document.addEventListener("DOMContentLoaded", function() {
	var modal = document.getElementById("videoModal");
	var closeBtn = document.getElementById("videoModalClose");
	var frame = document.getElementById("videoModalIframe");
	var rutubeLink = document.getElementById("videoModalRutube");
	var triggers = document.querySelectorAll(".js-video-popup");
	if (!modal || !frame) return;
	function open(e) {
		var url = e.currentTarget.getAttribute("data-video");
		var rutubeUrl = e.currentTarget.getAttribute("data-video-url");
		if (!url) return;
		var iframe = document.createElement("iframe");
		iframe.src = url + "?autoplay=1";
		iframe.allow = "autoplay; encrypted-media";
		iframe.allowFullscreen = true;
		frame.innerHTML = "";
		frame.appendChild(iframe);
		if (rutubeLink && rutubeUrl) {
			rutubeLink.href = rutubeUrl;
			rutubeLink.style.display = "";
		} else if (rutubeLink) {
			rutubeLink.style.display = "none";
		}
		modal.classList.add("active");
		document.body.style.overflow = "hidden";
	}
	function close() {
		frame.innerHTML = "";
		modal.classList.remove("active");
		document.body.style.overflow = "";
	}
	triggers.forEach(function(el) { el.addEventListener("click", open); });
	if (closeBtn) closeBtn.addEventListener("click", close);
});
</script>

</body>
</html>
