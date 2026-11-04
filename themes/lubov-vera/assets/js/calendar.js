// Календарь служений церкви
document.addEventListener('DOMContentLoaded', function() {
  const calendarGrid = document.getElementById('calendarGrid');
  const eventsGrid = document.getElementById('eventsGrid');
  const dateRange = document.getElementById('dateRange');

  if (!calendarGrid || !eventsGrid || !dateRange) {
    return;
  }

  const config = window.lvCalendarData || {};

  const monthNames = ['ЯНВАРЬ', 'ФЕВРАЛЬ', 'МАРТ', 'АПРЕЛЬ', 'МАЙ', 'ИЮНЬ',
                     'ИЮЛЬ', 'АВГУСТ', 'СЕНТЯБРЬ', 'ОКТЯБРЬ', 'НОЯБРЬ', 'ДЕКАБРЬ'];
  const monthNamesGenitive = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня',
                     'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
  const dayNames = ['ВС', 'ПН', 'ВТ', 'СР', 'ЧТ', 'ПТ', 'СБ'];

  const defaultEvents = [
    { weekday: 5, time: '19:00', title: 'Молодёжное служение' },
    { weekday: 0, time: '10:00', title: 'Общецерковное служение' },
    { weekday: 0, time: '12:00', title: 'Общецерковное служение' },
    { weekday: 0, time: '17:00', title: 'Общецерковное служение' }
  ];

  const scheduleEvents = Array.isArray(config.events) ? config.events : defaultEvents;
  const eventLocation = config.location || 'ул. Ворошилова, 82, г. Ижевск';
  const siteOffset = typeof config.offset === 'number' ? config.offset : null;

  function pad(value) {
    return String(value).padStart(2, '0');
  }

  // Текущие дата и время по часовому поясу сайта, а не браузера.
  function getSiteNow() {
    const now = new Date();

    if (siteOffset === null) {
      return {
        date: `${now.getFullYear()}-${pad(now.getMonth() + 1)}-${pad(now.getDate())}`,
        time: `${pad(now.getHours())}:${pad(now.getMinutes())}`
      };
    }

    const site = new Date(now.getTime() + siteOffset * 60000);

    return {
      date: `${site.getUTCFullYear()}-${pad(site.getUTCMonth() + 1)}-${pad(site.getUTCDate())}`,
      time: `${pad(site.getUTCHours())}:${pad(site.getUTCMinutes())}`
    };
  }

  function toDateKey(year, month, day) {
    return `${year}-${pad(month + 1)}-${pad(day)}`;
  }

  function normalizeTime(value) {
    const match = /^(\d{1,2})[:.](\d{2})$/.exec(String(value).trim());

    if (!match) {
      return '';
    }

    const hours = parseInt(match[1], 10);
    const minutes = parseInt(match[2], 10);

    if (hours < 0 || hours > 23 || minutes < 0 || minutes > 59) {
      return '';
    }

    return `${pad(hours)}:${pad(minutes)}`;
  }

  const initialSiteDate = getSiteNow().date.split('-');
  const currentDate = new Date(parseInt(initialSiteDate[0], 10), parseInt(initialSiteDate[1], 10) - 1, 1);

  function renderCalendar(year, month) {
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const firstDay = new Date(year, month, 1).getDay();
    const siteNow = getSiteNow();

    calendarGrid.innerHTML = `
      <div class="day-header-cell">ВС</div>
      <div class="day-header-cell">ПН</div>
      <div class="day-header-cell">ВТ</div>
      <div class="day-header-cell">СР</div>
      <div class="day-header-cell">ЧТ</div>
      <div class="day-header-cell">ПТ</div>
      <div class="day-header-cell">СБ</div>
    `;

    for (let i = 0; i < firstDay; i++) {
      calendarGrid.innerHTML += '<div class="day-card empty"></div>';
    }

    const allEvents = [];

    for (let day = 1; day <= daysInMonth; day++) {
      const date = new Date(year, month, day);
      const dayOfWeek = date.getDay();
      const isFriday = dayOfWeek === 5;
      const isSunday = dayOfWeek === 0;
      const dateKey = toDateKey(year, month, day);

      const dayCard = document.createElement('div');
      dayCard.className = `day-card ${dateKey === siteNow.date ? 'today' : ''}`;

      const dayHeader = document.createElement('div');
      dayHeader.className = 'day-header';

      const dayName = document.createElement('span');
      dayName.className = 'day-name';
      dayName.textContent = dayNames[dayOfWeek];

      if (isFriday || isSunday) {
        dayName.style.color = '#e71d36';
      }

      const dayNumber = document.createElement('span');
      dayNumber.className = 'day-number';
      dayNumber.textContent = day;

      dayHeader.appendChild(dayName);
      dayHeader.appendChild(dayNumber);
      dayCard.appendChild(dayHeader);

      calendarGrid.appendChild(dayCard);

      // Добавляем события дня из настроек «Расписание».
      scheduleEvents.forEach(function (event) {
        if (parseInt(event.weekday, 10) !== dayOfWeek) {
          return;
        }

        const time = normalizeTime(event.time);

        if (!time) {
          return;
        }

        const isPast = dateKey < siteNow.date || (dateKey === siteNow.date && time < siteNow.time);

        allEvents.push(createEvent(day, month, year, time, event.title || 'Служение', dayNames[dayOfWeek], isPast));
      });
    }

    // Сортируем события по дате и времени
    allEvents.sort((a, b) => {
      const dateA = parseEventDate(a);
      const dateB = parseEventDate(b);
      if (dateA.getTime() !== dateB.getTime()) {
        return dateA - dateB;
      }
      // Если даты одинаковые, сортируем по времени
      return a.time.localeCompare(b.time);
    });

    // Фильтруем: показываем только события с сегодняшнего дня и будущие
    const upcomingEvents = allEvents.filter(e => !e.isPast);

    updateEventList(upcomingEvents);
    updateDateRange(year, month);
  }

  /**
   * Парсит дату события в объект Date
   */
  function parseEventDate(event) {
    const parts = event.date.split(' ');
    const day = parseInt(parts[0]);
    const monthName = parts[1];
    const year = parseInt(parts[2]);

    const monthIndex = monthNamesGenitive.indexOf(monthName);
    return new Date(year, monthIndex, day);
  }

  function createEvent(day, month, year, time, title, dayName, isPast) {
    return {
      time,
      title,
      location: eventLocation,
      date: `${day} ${monthNamesGenitive[month]} ${year}`,
      dayName,
      isPast
    };
  }

  function updateEventList(events) {
    if (events.length === 0) {
      eventsGrid.innerHTML = '<div class="no-events">Событий не запланировано</div>';
      return;
    }

    eventsGrid.innerHTML = events.map(function (event) {
      var card = document.createElement('div');
      card.className = 'event-card';

      var dateDiv = document.createElement('div');
      dateDiv.className = 'event-card-date';
      dateDiv.textContent = event.date;
      var daySpan = document.createElement('span');
      daySpan.className = 'event-card-day';
      daySpan.textContent = ' (' + event.dayName + ')';
      dateDiv.appendChild(daySpan);

      var timeDiv = document.createElement('div');
      timeDiv.className = 'event-card-time';
      timeDiv.textContent = event.time;

      var titleDiv = document.createElement('div');
      titleDiv.className = 'event-card-title';
      titleDiv.textContent = event.title;

      var locDiv = document.createElement('div');
      locDiv.className = 'event-card-location';
      locDiv.textContent = event.location;

      card.appendChild(dateDiv);
      card.appendChild(timeDiv);
      card.appendChild(titleDiv);
      card.appendChild(locDiv);
      return card.outerHTML;
    }).join('');
  }

  function updateDateRange(year, month) {
    dateRange.textContent = `${monthNames[month]} ${year}`;
  }

  // Инициализация календаря
  renderCalendar(currentDate.getFullYear(), currentDate.getMonth());

  // Навигация по месяцам
  document.getElementById('prevMonth').addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() - 1);
    renderCalendar(currentDate.getFullYear(), currentDate.getMonth());
  });

  document.getElementById('nextMonth').addEventListener('click', () => {
    currentDate.setMonth(currentDate.getMonth() + 1);
    renderCalendar(currentDate.getFullYear(), currentDate.getMonth());
  });
});
