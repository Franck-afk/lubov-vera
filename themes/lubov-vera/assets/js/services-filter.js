/**
 * Фильтр служений
 * Плавная анимация переключения карточек
 */

(function () {
  "use strict";

  // Ждём загрузки DOM
  document.addEventListener("DOMContentLoaded", function () {
    var filterBtns = document.querySelectorAll(".cards-filter .filter-btn");
    var cards = document.querySelectorAll(
      ".cards-grid .ministry-card, .cards-grid .post-card"
    );

    // Если нет кнопок или карточек, выходим
    if (!filterBtns.length || !cards.length) {
      return;
    }

    // Добавляем обработчики на кнопки фильтра
    filterBtns.forEach(function (btn) {
      btn.addEventListener("click", function () {
        // Удаляем активный класс у всех кнопок
        filterBtns.forEach(function (b) {
          b.classList.remove("active");
        });

        // Добавляем активный класс нажатой кнопке
        btn.classList.add("active");

        var filterValue = btn.getAttribute("data-filter");

        // Фильтруем карточки с анимацией
        filterCards(cards, filterValue);
      });
    });

    /**
     * Фильтрация карточек с плавной анимацией
     * @param {NodeList} cards - коллекция карточек
     * @param {string} filterValue - значение фильтра
     */
    function filterCards(cards, filterValue) {
      cards.forEach(function (card) {
        var category = card.getAttribute("data-category");

        if (filterValue === "all" || category === filterValue) {
          // Показываем карточку с анимацией
          card.classList.remove("hidden");
          card.classList.add("visible");
        } else {
          // Скрываем карточку
          card.classList.add("hidden");
          card.classList.remove("visible");
        }
      });
    }
  });
})();
