/**
 * Прелоадер с анимацией GSAP
 */

(function() {
  'use strict';

  document.addEventListener('DOMContentLoaded', function() {
    // Проверяем, есть ли прелоадер на странице
    var preloader = document.querySelector('.preloader');
    if (!preloader) return;

    var content = document.querySelector('.main');
    var line1 = document.querySelector('.line1');
    var line2 = document.querySelector('.line2');

    if (!line1 || !line2) return;

    // Разбиваем текст на символы для анимации
    var chars1 = line1.innerText.split('');
    var chars2 = line2.innerText.split('');
    
    line1.innerHTML = chars1.map(function(char) {
      return char === ' ' ? '&nbsp;' : '<span>' + char + '</span>';
    }).join('');
    
    line2.innerHTML = chars2.map(function(char) {
      return char === ' ' ? '&nbsp;' : '<span>' + char + '</span>';
    }).join('');

    // Блокируем прокрутку во время прелоадера
    document.body.style.overflow = 'hidden';

    // Создаём timeline анимации
    var timeline = gsap.timeline({
      onComplete: function() {
        preloader.style.display = 'none';
        document.body.style.overflow = '';
      }
    });

    timeline
      .from('.line1 span', {
        opacity: 0,
        filter: 'blur(5px)',
        stagger: 0.05,
        ease: 'power2.out'
      })
      .from('.line2 span', {
        opacity: 0,
        filter: 'blur(5px)',
        stagger: 0.05,
        ease: 'power2.out'
      }, '-=0.5')
      .to('.preloader-text', {
        opacity: 0,
        duration: 0.5,
        ease: 'power1.in'
      }, '+=1')
      .to(preloader, {
        duration: 1.5,
        y: '-100%',
        ease: 'power2.inOut'
      });
  });
})();
