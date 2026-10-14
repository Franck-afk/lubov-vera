/**
 * Переключение темы (светлая/тёмная)
 */
(function() {
    'use strict';

    function initTheme() {
        const themeToggle = document.getElementById('themeToggle');
        const mobileThemeToggle = document.getElementById('mobileThemeToggle');
        const mobileThemeToggleFloating = document.getElementById('mobileThemeToggleFloating');
        const html = document.documentElement;

        if (!themeToggle && !mobileThemeToggle && !mobileThemeToggleFloating) {
            console.warn('Theme toggle buttons not found');
            return;
        }

        // Функция применения темы
        function applyTheme(isDark) {
            if (isDark) {
                html.classList.add('dark');
            } else {
                html.classList.remove('dark');
            }
        }

        // Функция переключения темы
        function toggleTheme() {
            const isDark = !html.classList.contains('dark');
            applyTheme(isDark);
            localStorage.setItem('theme', isDark ? 'dark' : 'light');

            // Переприменяем стили для сторонних форм, если они есть
            if (typeof styleMixplatForm === 'function') {
                setTimeout(styleMixplatForm, 50);
            }
        }

        // Применяем сохранённую тему при загрузке
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme) {
            applyTheme(savedTheme === 'dark');
        }

        // Добавляем обработчики на все найденные кнопки
        [themeToggle, mobileThemeToggle, mobileThemeToggleFloating].forEach(btn => {
            if (btn) {
                btn.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleTheme();
                });
            }
        });
    }

    // Инициализируем сразу, если DOM готов, или ждем загрузки
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
})();
