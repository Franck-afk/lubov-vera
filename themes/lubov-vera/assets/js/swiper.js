// Инициализация Swiper для слайдера строительства
const constructionSwiper = document.querySelector(".construction-slider");
if (constructionSwiper) {
  new Swiper(constructionSwiper, {
    // Основные настройки
    slidesPerView: 1,
    spaceBetween: 30,
    speed: 800,

    // Навигация стрелками
    navigation: {
      nextEl: constructionSwiper.querySelector(".swiper-button-next"),
      prevEl: constructionSwiper.querySelector(".swiper-button-prev"),
    },

    // Пагинация точками
    pagination: {
      el: constructionSwiper.querySelector(".swiper-pagination"),
      clickable: true,
      dynamicBullets: true,
    },

    // Дополнительные настройки
    loop: true,
    autoplay: {
      delay: 3000,
      disableOnInteraction: false,
      pauseOnMouseEnter: true,
    },

    effect: "slide",
    grabCursor: true,

    // Адаптивность
    breakpoints: {
      640: {
        slidesPerView: 1,
        spaceBetween: 20,
      },
      768: {
        slidesPerView: 1,
        spaceBetween: 30,
      },
      1024: {
        slidesPerView: 1,
        spaceBetween: 40,
      },
    },

    // Управление
    keyboard: {
      enabled: true,
      onlyInViewport: true,
    },

    mousewheel: {
      invert: false,
    },
  });
}

// Инициализация Swiper для других слайдеров (класс .mySwiper)
const swiper = document.querySelector(".mySwiper");
if (swiper) {
  new Swiper(swiper, {
    // Основные настройки
    slidesPerView: 1,
    spaceBetween: 30,
    speed: 800,

    // Навигация стрелками
    navigation: {
      nextEl: ".swiper-button-next",
      prevEl: ".swiper-button-prev",
    },

    // Пагинация точками
    pagination: {
      el: ".swiper-pagination",
      clickable: true,
      dynamicBullets: true,
    },

    // Дополнительные настройки
    loop: true,
    autoplay: {
      delay: 3000,
      disableOnInteraction: false,
      pauseOnMouseEnter: true,
    },

    effect: "slide",
    grabCursor: true,

    // Адаптивность
    breakpoints: {
      640: {
        slidesPerView: 1,
        spaceBetween: 20,
      },
      768: {
        slidesPerView: 1,
        spaceBetween: 30,
      },
      1024: {
        slidesPerView: 1,
        spaceBetween: 40,
      },
    },

    // Управление
    keyboard: {
      enabled: true,
      onlyInViewport: true,
    },

    mousewheel: {
      invert: false,
    },
  });
}
