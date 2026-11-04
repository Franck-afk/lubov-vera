// js/main.js
document.addEventListener("DOMContentLoaded", function () {
  // ============================================
  // 1. ТАЙМЕР ОБРАТНОГО ОТСЧЁТА
  // ============================================
  const images = document.querySelectorAll(".post-image, .author-avatar");
  images.forEach((img) => {
    if (img.src) {
      const image = new Image();
      image.src = img.src;
    }
  });

  // ============================================
  // 2. ТАЙМЕР ОБРАТНОГО ОТСЧЁТА ДО ЭФИРА
  // ============================================
  const countdownTimer = document.getElementById("countdownTimer");
  if (countdownTimer) {
    // Точные метки начала и конца эфира задаёт PHP (ISO 8601 с зоной сайта).
    const startMs = Date.parse(countdownTimer.dataset.start || "");
    const endMs = Date.parse(countdownTimer.dataset.end || "");

    if (!isNaN(startMs) && !isNaN(endMs)) {
      const section = countdownTimer.closest(".countdown-section");
      const badge = countdownTimer.closest(".countdown-badge");
      let timerId = null;

      const pad = (value) => String(value).padStart(2, "0");

      const formatDiff = (ms) => {
        const total = Math.max(0, Math.floor(ms / 1000));
        const days = Math.floor(total / 86400);
        const hours = Math.floor((total % 86400) / 3600);
        const minutes = Math.floor((total % 3600) / 60);
        const seconds = total % 60;

        if (days > 0) {
          return `${days}д ${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
        }
        if (hours > 0) {
          return `${pad(hours)}:${pad(minutes)}:${pad(seconds)}`;
        }
        return `${pad(minutes)}:${pad(seconds)}`;
      };

      const tick = () => {
        const now = Date.now();

        if (now >= endMs) {
          if (section) {
            section.hidden = true;
          }
          if (timerId) {
            clearInterval(timerId);
          }
          return;
        }

        if (now >= startMs) {
          countdownTimer.textContent = "В ЭФИРЕ";
          if (badge) {
            badge.classList.add("is-live");
          }
          return;
        }

        countdownTimer.textContent = formatDiff(startMs - now);
      };

      tick();
      timerId = setInterval(tick, 1000);
    }
  }

  // ============================================
  // 3. КНОПКИ СУММЫ ПОЖЕРТВОВАНИЯ
  // ============================================
  const amountBtns = document.querySelectorAll(".amount-btn");
  const donationAmountInput = document.getElementById("donationAmount");

  if (amountBtns.length > 0 && donationAmountInput) {
    amountBtns.forEach((btn) => {
      btn.addEventListener("click", function () {
        // Удаляем активный класс со всех кнопок
        amountBtns.forEach((b) => b.classList.remove("active"));
        // Добавляем активный класс нажатой кнопке
        this.classList.add("active");
        // Устанавливаем значение в input
        donationAmountInput.value = this.dataset.amount;
      });
    });

    // Удаляем активный класс при ручном вводе суммы
    donationAmountInput.addEventListener("input", function () {
      amountBtns.forEach((b) => b.classList.remove("active"));
    });
  }

  // ============================================
  // 5. МОБИЛЬНОЕ МЕНЮ
  // ============================================
  const mobileMenuBtn = document.getElementById("mobileMenuBtn");
  const mobileMenu = document.getElementById("mobileMenu");
  const closeMobileMenu = document.getElementById("closeMobileMenu");

  // Открытие мобильного меню
  function openMobileMenu() {
    mobileMenu.classList.add("open");
    mobileMenuBtn.classList.add("active");
    document.body.style.overflow = "hidden";
  }

  // Закрытие мобильного меню
  function closeMobileMenuFunc() {
    mobileMenu.classList.remove("open");
    mobileMenuBtn.classList.remove("active");
    document.body.style.overflow = "";

    // Закрыть все подменю в мобильном меню
    document.querySelectorAll(".mobile-submenu").forEach((submenu) => {
      submenu.classList.remove("open");
    });
    // Удаляем класс open с кнопок
    document
      .querySelectorAll(".nav--mobile .nav__link--button")
      .forEach((button) => {
        button.classList.remove("open");
      });
  }

  if (mobileMenuBtn) {
    mobileMenuBtn.addEventListener("click", openMobileMenu);
  }

  if (closeMobileMenu) {
    closeMobileMenu.addEventListener("click", closeMobileMenuFunc);
  }

  // Обработчики для подменю в мобильной версии
  document
    .querySelectorAll(".nav--mobile .nav__link--button")
    .forEach((button) => {
      button.addEventListener("click", function (e) {
        e.preventDefault();
        const submenu = this.nextElementSibling;
        const icon = this.querySelector(".nav__arrow");
        if (submenu && submenu.classList.contains("mobile-submenu")) {
          submenu.classList.toggle("open");
          this.classList.toggle("open");
          if (icon) {
            icon.style.transform = submenu.classList.contains("open")
              ? "rotate(180deg)"
              : "rotate(0deg)";
          }
        }
      });
    });

  // Закрытие мобильного меню при изменении размера окна
  window.addEventListener("resize", () => {
    if (window.innerWidth >= 1024) {
      closeMobileMenuFunc();
    }
  });

  // ============================================
  // SWIPER СЛАЙДЕР
  // ============================================
  // Карусель событий (.events-swiper) инициализируется отдельно ниже —
  // у неё свои точки пагинации и кнопки навигации, привязанные к своей секции.
  if (document.querySelector(".swiper:not(.events-swiper)")) {
    const swiper = new Swiper(".swiper:not(.events-swiper)", {
      direction: "horizontal",
      loop: true,
      speed: 600,
      grabCursor: true,

      // Адаптивное количество слайдов
      slidesPerView: 3,
      spaceBetween: 10,
      breakpoints: {
        320: {
          slidesPerView: 1,
          spaceBetween: 8,
        },
        768: {
          slidesPerView: 2,
          spaceBetween: 10,
        },
        1200: {
          slidesPerView: 3,
          spaceBetween: 10,
        },
      },

      // Автопрокрутка
      autoplay: {
        delay: 5000,
        disableOnInteraction: false,
        pauseOnMouseEnter: true,
      },

      // Пагинация
      pagination: {
        el: ".swiper-pagination",
        clickable: true,
        dynamicBullets: true,
        dynamicMainBullets: 3,
      },

      // Навигация
      navigation: {
        nextEl: ".swiper-button-next",
        prevEl: ".swiper-button-prev",
      },

      // Эффекты
      effect: "slide",

      // Доступность
      a11y: {
        prevSlideMessage: "Предыдущий слайд",
        nextSlideMessage: "Следующий слайд",
        firstSlideMessage: "Это первый слайд",
        lastSlideMessage: "Это последний слайд",
        paginationBulletMessage: "Перейти к слайду {{index}}",
      },
    });
  }

  // ============================================
  // КАРУСЕЛЬ СОБЫТИЙ НА ГЛАВНОЙ (Swiper)
  // ============================================
  (function initEventsSwiper() {
    var el = document.querySelector(".events-swiper");
    if (!el) return;

    var section = el.closest(".events-swiper-section");
    var slides = el.querySelectorAll(".swiper-slide");
    if (!slides.length) return;

    // Три карточки в ряду на планшете и десктопе, одна — на телефоне.
    var perView = window.innerWidth >= 768 ? 3 : 1;

    // Крутить есть смысл только когда событий больше, чем видно на экране:
    // иначе Swiper дублирует слайды, а автопрокрутка бесконечно листает пустоту.
    var looping = slides.length > perView;

    new Swiper(el, {
      slidesPerView: perView,
      spaceBetween: 24,
      grabCursor: true,
      loop: looping,
      speed: 600,
      keyboard: { enabled: true },

      // При ресайзе состав ряда пересчитывается брейкпоинтами.
      breakpoints: {
        640: {
          slidesPerView: 2,
          spaceBetween: 16,
        },
        768: {
          slidesPerView: 3,
          spaceBetween: 24,
        },
      },

      autoplay: looping
        ? {
            delay: 5000,
            disableOnInteraction: false,
            pauseOnMouseEnter: true,
          }
        : false,

      // Пагинация и навигация ищутся внутри своей секции,
      // чтобы не зацепить элементы других слайдеров на странице.
      pagination: {
        el: el.querySelector(".swiper-pagination"),
        clickable: true,
        dynamicBullets: true,
        dynamicMainBullets: 3,
      },
      navigation: {
        nextEl: section ? section.querySelector(".swiper-button-next") : null,
        prevEl: section ? section.querySelector(".swiper-button-prev") : null,
      },

      // Доступность
      a11y: {
        prevSlideMessage: "Предыдущее событие",
        nextSlideMessage: "Следующее событие",
        firstSlideMessage: "Это первое событие",
        lastSlideMessage: "Это последнее событие",
        paginationBulletMessage: "Перейти к событию {{index}}",
      },
    });
  })();

  // ============================================
  // 7. ПРОСМОТРЩИК PDF ДОКУМЕНТОВ (PDF.js)
  // ============================================
  var pdfDoc = null;
  var pageNum = 1;
  var pageRendering = false;
  var pageNumPending = null;
  var scale = 1.0;
  var currentPdfUrl = null;

  var documentItems = document.querySelectorAll(".document-item");
  var viewerContainer = document.getElementById("pdf-render-container");
  var canvas = document.getElementById("pdf-render");
  var ctx = canvas ? canvas.getContext("2d") : null;
  var viewerTitle = document.getElementById("viewer-title");
  var viewerDate = document.getElementById("viewer-date");
  var viewerBtn = document.querySelector(".documents-viewer__btn--open");
  var pdfLoading = document.querySelector(".pdf-loading");
  var pdfError = document.querySelector(".pdf-error");
  var pdfErrorRetry = document.querySelector(".pdf-error__retry");
  var pdfPrev = document.getElementById("pdf-prev");
  var pdfNext = document.getElementById("pdf-next");
  var pdfZoomIn = document.getElementById("pdf-zoom-in");
  var pdfZoomOut = document.getElementById("pdf-zoom-out");
  var pdfPageCurrent = document.getElementById("pdf-page-current");
  var pdfPageTotal = document.getElementById("pdf-page-total");
  var pdfZoomLevel = document.getElementById("pdf-zoom-level");

  // Подключаем PDF.js
  if (documentItems.length && viewerContainer) {
    // Загружаем PDF.js
    var pdfjsScript = document.createElement("script");
    pdfjsScript.src =
      "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js";
    document.head.appendChild(pdfjsScript);

    pdfjsScript.onload = function () {
      pdfjsLib.GlobalWorkerOptions.workerSrc =
        "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";

      // Инициализация первого документа
      var firstItem = documentItems[0];
      if (firstItem) {
        var firstUrl = firstItem.getAttribute("data-url");
        var firstTitle = firstItem.getAttribute("data-title");
        var firstDate = firstItem.getAttribute("data-date");
        loadPdf(firstUrl, firstTitle, firstDate);
      }

      // Обработчик клика по элементу списка
      documentItems.forEach(function (item) {
        item.addEventListener("click", function (e) {
          e.preventDefault();
          e.stopPropagation();

          // Удаляем активный класс у всех элементов
          documentItems.forEach(function (i) {
            i.classList.remove("active");
          });

          // Добавляем активный класс текущему элементу
          item.classList.add("active");

          // Получаем данные из атрибута элемента
          var url = item.getAttribute("data-url");
          var title = item.getAttribute("data-title");
          var date = item.getAttribute("data-date");

          if (url) {
            loadPdf(url, title, date);
          }
        });
      });

      // Навигация по страницам
      if (pdfPrev) {
        pdfPrev.addEventListener("click", function () {
          if (pageNum > 1) {
            pageNum--;
            queueRenderPage(pageNum);
          }
        });
      }

      if (pdfNext) {
        pdfNext.addEventListener("click", function () {
          if (pdfDoc && pageNum < pdfDoc.numPages) {
            pageNum++;
            queueRenderPage(pageNum);
          }
        });
      }

      // Зум
      if (pdfZoomIn) {
        pdfZoomIn.addEventListener("click", function () {
          scale += 0.25;
          updateZoom();
        });
      }

      if (pdfZoomOut) {
        pdfZoomOut.addEventListener("click", function () {
          if (scale > 0.5) {
            scale -= 0.25;
            updateZoom();
          }
        });
      }

      // Повторная загрузка при ошибке
      if (pdfErrorRetry) {
        pdfErrorRetry.addEventListener("click", function (e) {
          e.preventDefault();
          if (currentPdfUrl) {
            loadPdf(currentPdfUrl, viewerTitle ? viewerTitle.textContent : "");
          }
        });
      }
    };
  }

  /**
   * Загрузка PDF документа
   */
  function loadPdf(url, title, date) {
    if (!url) return;

    currentPdfUrl = url;

    // Показываем индикатор загрузки
    if (pdfLoading) pdfLoading.classList.add("active");
    if (pdfError) pdfError.classList.remove("active");
    if (canvas) canvas.style.display = "none";

    // Обновляем заголовок и дату
    if (viewerTitle) {
      viewerTitle.textContent = title;
    }
    if (viewerDate) {
      if (date) {
        viewerDate.innerHTML = '<i class="far fa-calendar"></i> ' + date;
        viewerDate.style.display = "inline-flex";
      } else {
        viewerDate.style.display = "none";
      }
    }

    // Обновляем кнопку
    if (viewerBtn) {
      viewerBtn.href = url;
    }

    // Загружаем PDF
    pdfjsLib
      .getDocument(url)
      .promise.then(function (pdfDoc_) {
        pdfDoc = pdfDoc_;
        pageNum = 1;

        if (pdfPageTotal) {
          pdfPageTotal.textContent = pdfDoc.numPages;
        }

        if (pdfLoading) pdfLoading.classList.remove("active");
        if (canvas) canvas.style.display = "block";

        renderPage(pageNum);
      })
      .catch(function (err) {
        console.error("Ошибка загрузки PDF:", err);
        if (pdfLoading) pdfLoading.classList.remove("active");
        if (pdfError) pdfError.classList.add("active");
      });
  }

  /**
   * Очередь на рендеринг страницы
   */
  function queueRenderPage(num) {
    if (pageRendering) {
      pageNumPending = num;
    } else {
      renderPage(num);
    }
  }

  /**
   * Рендеринг страницы
   */
  function renderPage(num) {
    if (!canvas) return;

    pageRendering = true;

    pdfDoc.getPage(num).then(function (page) {
      // Вычисляем масштаб для полной ширины контейнера
      var containerWidth = viewerContainer.clientWidth - 40; // Учитываем padding
      var unscaledViewport = page.getViewport({ scale: 1 });

      // Масштабируем по ширине контейнера
      var calculatedScale = containerWidth / unscaledViewport.width;
      var finalScale = Math.min(calculatedScale, scale); // Не больше текущего зума

      var viewport = page.getViewport({ scale: finalScale });
      canvas.height = viewport.height;
      canvas.width = viewport.width;

      var renderContext = {
        canvasContext: ctx,
        viewport: viewport,
      };

      var renderTask = page.render(renderContext);

      renderTask.promise.then(function () {
        pageRendering = false;

        if (pdfPageCurrent) {
          pdfPageCurrent.textContent = num;
        }

        // Обновляем состояние кнопок
        if (pdfPrev) {
          pdfPrev.disabled = num <= 1;
        }
        if (pdfNext) {
          pdfNext.disabled = num >= pdfDoc.numPages;
        }

        if (pageNumPending !== null) {
          renderPage(pageNumPending);
          pageNumPending = null;
        }
      });
    });
  }

  /**
   * Обновление зума
   */
  function updateZoom() {
    if (pdfZoomLevel) {
      pdfZoomLevel.textContent = Math.round(scale * 100) + "%";
    }
    queueRenderPage(pageNum);
  }

  // ============================================
  // STACKING CARDS — fullscreen наложение при скролле
  // ============================================
  (function initStackingCards() {
    const section = document.querySelector('.stacking-section');
    if (!section) return;

    const cards = section.querySelectorAll('.stacking-card');
    if (!cards.length) return;

    const track = section.querySelector('.stacking-cards');
    const count = cards.length;
    const gap = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--stack-gap')) || 60;

    // Высота трека: все карточки скроллятся, последняя остаётся видимой
    var cardHeight = window.innerHeight * 0.6 + 540;
    var totalCardsHeight = count * cardHeight;
    var totalGaps = (count - 1) * gap;
    track.style.height = (totalCardsHeight + totalGaps) + 'px';

    // Каждой карточке задаём z-index и отступ (72px — высота шапки)
    cards.forEach(function (card, i) {
      card.style.zIndex = 10 + i;
      card.style.top = (72 + i * gap) + 'px';
    });

    // Intersection Observer — появление
    var observer = new IntersectionObserver(function (entries) {
      entries.forEach(function (entry) {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
        }
      });
    }, {
      root: null,
      rootMargin: '0px',
      threshold: 0.1,
    });

    cards.forEach(function (card) {
      observer.observe(card);
    });

    // Scroll-анимация: scale + opacity по позиции
    var ticking = false;

    function onScroll() {
      if (!ticking) {
        requestAnimationFrame(function () {
          var vh = window.innerHeight * 0.6 + 540;

          cards.forEach(function (card, i) {
            if (!card.classList.contains('is-visible')) return;

            var rect = card.getBoundingClientRect();
            var progress = -rect.top / vh; // 0 = карточка вверху, 1 = ушла вверх

            // Scale: 1 → 0.92 по мере ухода
            var scale = 1 - Math.max(0, Math.min(progress, 1)) * 0.08;
            // Opacity: 1 → 0.6 по мере ухода
            var opacity = 1 - Math.max(0, Math.min(progress, 1)) * 0.4;

            card.style.transform = 'scale(' + Math.max(scale, 0.92) + ')';
            card.style.opacity = Math.max(opacity, 0.6);
          });

          ticking = false;
        });
        ticking = true;
      }
    }

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  })();
});

