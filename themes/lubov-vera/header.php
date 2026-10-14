<?php
/**
 * The header for our theme
 *
 * This is the template that displays all of the <head> section and everything up until <div id="content">
 *
 * @link https://developer.wordpress.org/themes/basics/template-files/#template-partials
 *
 * @package lubov-vera
 */

?>
<!doctype html>
<html <?php language_attributes(); ?> class="scroll-smooth">
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<link rel="profile" href="https://gmpg.org/xfn/11">

	<!-- Тема (синхронно, до рендера) -->
	<script src="<?php echo esc_url( get_theme_file_uri( 'assets/js/theme.js' ) ); ?>"></script><?php // phpcs:ignore WordPress.WP.EnqueuedResources.NonEnqueuedScript -- Synchronous pre-render script to avoid a flash of the wrong theme. ?>
	<script nonce="<?php echo esc_attr( $GLOBALS['lv_csp_nonce'] ); ?>">
		// Мгновенное применение темы до загрузки страницы
		(function () {
			const savedTheme = localStorage.getItem("theme");
			const html = document.documentElement;
			if (savedTheme === "dark") {
				html.classList.add("dark");
			} else if (savedTheme === "light") {
				html.classList.remove("dark");
			} else if (window.matchMedia("(prefers-color-scheme: dark)").matches) {
				html.classList.add("dark");
			}
		})();
	</script>

	<?php wp_head(); ?>
</head>

<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div id="page" class="site">
	<a class="skip-link screen-reader-text" href="#primary"><?php esc_html_e( 'Skip to content', 'lubov-vera' ); ?></a>

	<header class="header">
		<div class="container header__inner">
			<!-- Логотип -->
			<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo">
				<?php
				if ( has_custom_logo() ) {
					the_custom_logo();
				} else {
					?>
					<img
						src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logotip.webp' ) ); ?>"
						alt="<?php bloginfo( 'name' ); ?>"
						class="logo__img"
					/>
					<?php
				}
				?>
			</a>

			<!-- Десктопное меню -->
			<nav class="nav nav--desktop">
				<?php
				if ( has_nav_menu( 'menu-1' ) ) {
					wp_nav_menu(
						array(
							'theme_location' => 'menu-1',
							'menu_class'     => 'nav__list',
							'depth'          => 2,
							'walker'         => new Church_Walker_Nav_Menu( false ),
							'container'      => false,
						)
					);
				} else {
					// Fallback если меню не назначено.
					wp_nav_menu(
						array(
							'menu_class'  => 'nav__list',
							'container'   => false,
							'fallback_cb' => 'wp_page_menu',
						)
					);
				}
				?>
			</nav>

			<!-- Кнопки действий -->
			<div class="header__actions">
	
				<button type="button" class="btn btn--primary donate-btn" id="donateBtn"><?php esc_html_e( 'Пожертвовать', 'lubov-vera' ); ?></button>

				<!-- Переключатель темы -->
				<button
					id="themeToggle"
					class="theme-toggle"
					aria-label="<?php esc_attr_e( 'Переключить тему', 'lubov-vera' ); ?>"
				>
					<svg
						class="sun-icon"
						viewBox="0 0 24 24"
						fill="none"
						stroke="currentColor"
					>
						<path
							stroke-linecap="round"
							stroke-linejoin="round"
							stroke-width="2"
							d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
						></path>
					</svg>
					<svg
						class="moon-icon"
						viewBox="0 0 24 24"
						fill="none"
						stroke="currentColor"
					>
						<path
							stroke-linecap="round"
							stroke-linejoin="round"
							stroke-width="2"
							d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
						></path>
					</svg>
				</button>
			</div>

			<!-- Правая часть для мобильных (гамбургер) -->
			<button class="hamburger" id="mobileMenuBtn" aria-label="<?php esc_attr_e( 'Меню', 'lubov-vera' ); ?>">
				<span class="hamburger__line"></span>
				<span class="hamburger__line"></span>
				<span class="hamburger__line"></span>
			</button>
		</div>

		<!-- Мобильная кнопка регистрации (на всю ширину) -->
		<div class="header__mobile-reg-wrapper">
			<button type="button" class="header__mobile-reg btn btn--primary donate-btn" id="donateBtnMobile">
				<?php esc_html_e( 'Пожертвовать', 'lubov-vera' ); ?>
			</button>
		</div>
	</header>

	<!-- Попап пожертвования -->
	<div class="donate-popup-overlay" id="donatePopup">
		<div class="donate-popup">
			<button type="button" class="donate-popup__close" id="donatePopupClose" aria-label="Закрыть">
				<svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
					<path d="M6 18L18 6M6 6l12 12"></path>
				</svg>
			</button>
			<div class="donate-popup__body">
				<h3 class="donate-popup__title">Пожертвовать</h3>
				<p class="donate-popup__desc">Ваша поддержка помогает нам продолжать служение</p>
				<div class="donate-popup__content">
					<div class="donate-popup__qr">
						<img src="<?php echo esc_url( get_theme_file_uri( 'assets/img/QR-kod.png' ) ); ?>" alt="QR-код для пожертвования" class="donate-popup__qr-img" id="donateQrCode" onerror="this.style.display='none'">
						<p class="donate-popup__qr-label">Сканируйте QR-код для пожертвования</p>
					</div>
				</div>
			</div>
		</div>
	</div>
	<script nonce="<?php echo esc_attr( $GLOBALS['lv_csp_nonce'] ); ?>">
	(function () {
		var popup = document.getElementById('donatePopup');
		if (!popup) {
			return;
		}
		var closeBtn = document.getElementById('donatePopupClose');
		var backdrop = popup;

		function openPopup() {
			popup.classList.remove('closing');
			popup.classList.add('open');
			document.body.style.overflow = 'hidden';
		}

		function closePopup() {
			if (!popup.classList.contains('open')) {
				return;
			}
			popup.classList.add('closing');
			setTimeout(function () {
				popup.classList.remove('open', 'closing');
				document.body.style.overflow = '';
			}, 250);
		}

		// Делегирование: любая кнопка .donate-btn на странице (включая мобильную).
		document.addEventListener('click', function (e) {
			if (e.target.closest('.donate-btn')) {
				e.preventDefault();
				openPopup();
			}
		});

		if (closeBtn) {
			closeBtn.addEventListener('click', closePopup);
		}
		popup.addEventListener('click', function (e) {
			if (e.target === backdrop) {
				closePopup();
			}
		});

		document.addEventListener('keydown', function (e) {
			if ('Escape' === e.key && popup.classList.contains('open')) {
				closePopup();
			}
		});
	})();
	</script>

	<!-- Мобильное меню -->
	<div class="mobile-menu" id="mobileMenu">
		<div class="mobile-menu__inner">
			<div class="mobile-menu__header">
				<a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="logo">
					<?php
					if ( has_custom_logo() ) {
						the_custom_logo();
					} else {
						?>
						<img
							src="<?php echo esc_url( get_theme_file_uri( 'assets/img/logotip.webp' ) ); ?>"
							alt="<?php bloginfo( 'name' ); ?>"
							class="logo__img"
							loading="lazy"
							decoding="async"
						/>
						<?php
					}
					?>
				</a>
				<button class="mobile-menu__close" id="closeMobileMenu" aria-label="<?php esc_attr_e( 'Закрыть меню', 'lubov-vera' ); ?>">
					<svg
						width="24"
						height="24"
						viewBox="0 0 24 24"
						fill="none"
						stroke="currentColor"
					>
						<path
							stroke-linecap="round"
							stroke-linejoin="round"
							stroke-width="2"
							d="M6 18L18 6M6 6l12 12"
						></path>
					</svg>
				</button>
			</div>

			<nav class="nav nav--mobile">
				<?php
				if ( has_nav_menu( 'menu-1' ) ) {
					wp_nav_menu(
						array(
							'theme_location' => 'menu-1',
							'menu_class'     => 'nav__list',
							'depth'          => 2,
							'walker'         => new Church_Walker_Nav_Menu( true ),
							'container'      => false,
						)
					);
				} else {
					// Fallback если меню не назначено.
					wp_nav_menu(
						array(
							'menu_class'  => 'nav__list',
							'container'   => false,
							'fallback_cb' => 'wp_page_menu',
						)
					);
				}
				?>
			</nav>

			<?php $mobile_phone = function_exists( 'lv_get_phone' ) ? lv_get_phone() : ''; if ( $mobile_phone ) : ?>
			<div class="mobile-menu__actions">
				<a href="tel:<?php echo esc_attr( preg_replace( '/[^\d+]/', '', $mobile_phone ) ); ?>" class="mobile-menu__phone">
					<svg
						width="20"
						height="20"
						viewBox="0 0 24 24"
						fill="none"
						stroke="currentColor"
					>
						<path
							stroke-linecap="round"
							stroke-linejoin="round"
							stroke-width="2"
							d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"
						></path>
					</svg>
					<span><?php echo esc_html( $mobile_phone ); ?></span>
				</a>
			</div>
			<?php endif; ?>
		</div>
	</div>

	<!-- Плавающая кнопка переключения темы (мобильная версия) -->
	<button
		id="mobileThemeToggle"
		class="theme-toggle-floating"
		aria-label="<?php esc_attr_e( 'Переключить тему', 'lubov-vera' ); ?>"
	>
		<svg
			class="sun-icon"
			width="20"
			height="20"
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
		>
			<path
				stroke-linecap="round"
				stroke-linejoin="round"
				stroke-width="2"
				d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"
			></path>
		</svg>
		<svg
			class="moon-icon"
			width="20"
			height="20"
			viewBox="0 0 24 24"
			fill="none"
			stroke="currentColor"
		>
			<path
				stroke-linecap="round"
				stroke-linejoin="round"
				stroke-width="2"
				d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"
			></path>
		</svg>
		<span class="theme-toggle__text dark-hidden"><?php esc_html_e( 'Тёмная тема', 'lubov-vera' ); ?></span>
		<span class="theme-toggle__text dark-visible"><?php esc_html_e( 'Светлая тема', 'lubov-vera' ); ?></span>
	</button>