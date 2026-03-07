/**
 * seznam-kalendar.js
 * Výběr termínu v kalendáři (zobrazení, navigace, renderování)
 * Závisí na: seznam.js (CURRENT_RECORD, SELECTED_DATE, SELECTED_TIME, CAL_MONTH, CAL_YEAR, showDayBookingsWithDistances, t())
 */

// === KALENDÁŘ ===
function showCalendar(id) {
  const z = WGS_DATA_CACHE.find(x => x.id == id);
  if (!z) return;
  CURRENT_RECORD = z;
  SELECTED_DATE = null;
  SELECTED_TIME = null;

  const content = `
    ${createCustomerHeader('showDetail')}

    <!-- Vybraný termín - fixní nad kalendářem -->
    <div style="display: flex; align-items: center; gap: 0.5rem; margin: 0 1rem;">
      <div id="selectedDateDisplay" style="display: none; flex: 1; background: #f5f5f5; border: 2px solid #666; color: #333; font-size: 0.75rem; padding: 0.4rem 0.8rem; border-radius: 4px; font-weight: 600; text-align: center; font-family: inherit; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;"></div>
      <button id="btnUlozitTerminHore" data-action="saveSelectedDate" style="display: none; background: #39ff14; color: #000; border: none; padding: 0.4rem 1rem; font-size: 0.75rem; font-weight: 700; border-radius: 4px; cursor: pointer; white-space: nowrap; font-family: inherit; letter-spacing: 0.03em; flex-shrink: 0;">Uložit</button>
    </div>

    <!-- Varování o kolizi - skryté, zobrazí se při výběru obsazeného času -->
    <div id="collisionWarning" style="display: none; background: #fee; border: 2px solid #c00; color: #900; font-size: 0.85rem; padding: 0.5rem 1rem; margin: 0.5rem 1rem 0; border-radius: 4px; font-weight: 600; text-align: center; font-family: inherit;"></div>

    <div class="modal-body" style="max-height: 60vh; overflow-y: auto; padding: 1rem;">
      <div class="calendar-container">
        <div id="calGrid"></div>
        <div id="distanceInfo"></div>
        <div id="dayBookings"></div>
        <div id="timeGrid"></div>
      </div>
    </div>
  `;

  ModalManager.show(content);
  const today = new Date();
  CAL_MONTH = today.getMonth();
  CAL_YEAR = today.getFullYear();
  renderCalendar(CAL_MONTH, CAL_YEAR);
}

function previousMonth() {
  const today = new Date();
  const currentMonth = today.getMonth();
  const currentYear = today.getFullYear();

  // Calculate previous month
  let prevMonth = CAL_MONTH - 1;
  let prevYear = CAL_YEAR;
  if (prevMonth < 0) {
    prevMonth = 11;
    prevYear--;
  }

  // Don't allow going to past months
  if (prevYear < currentYear || (prevYear === currentYear && prevMonth < currentMonth)) {
    wgsToast.warning(t('cannot_show_past_months'));
    return;
  }

  CAL_MONTH = prevMonth;
  CAL_YEAR = prevYear;
  renderCalendar(CAL_MONTH, CAL_YEAR);
}

function nextMonth(event) {
  if (event) event.stopPropagation();
  CAL_MONTH++;
  if (CAL_MONTH > 11) {
    CAL_MONTH = 0;
    CAL_YEAR++;
  }
  renderCalendar(CAL_MONTH, CAL_YEAR);
}

function renderCalendar(m, y) {
  const grid = document.getElementById('calGrid');
  grid.innerHTML = '';
  
  const monthNames = ['Leden', 'Únor', 'Březen', 'Duben', 'Květen', 'Červen',
                      'Červenec', 'Srpen', 'Září', 'Říjen', 'Listopad', 'Prosinec'];
  const navHeader = document.createElement('div');
  navHeader.className = 'calendar-controls';
  navHeader.innerHTML = `
    <button class="calendar-nav-btn" data-action="previousMonth">◀</button>
    <span class="calendar-month-title">${monthNames[m]} ${y}</span>
    <button class="calendar-nav-btn" data-action="nextMonth">▶</button>
  `;
  grid.appendChild(navHeader);
  
  const weekdays = document.createElement('div');
  weekdays.className = 'calendar-weekdays';
  weekdays.innerHTML = `<div>${t('monday')}</div><div>${t('tuesday')}</div><div>${t('wednesday')}</div><div>${t('thursday')}</div><div>${t('friday')}</div><div>${t('saturday')}</div><div>${t('sunday')}</div>`;
  grid.appendChild(weekdays);
  
  const daysGrid = document.createElement('div');
  daysGrid.className = 'calendar-days';
  
  const firstDay = new Date(y, m, 1);
  const lastDay = new Date(y, m + 1, 0);
  const daysInMonth = lastDay.getDate();
  const startDayOfWeek = (firstDay.getDay() + 6) % 7;
  
  const occupiedDays = new Set();
  WGS_DATA_CACHE.forEach(rec => {
    if (rec.termin && rec.id !== CURRENT_RECORD?.id) {
      const parts = rec.termin.split('.');
      if (parts.length === 3) {
        const day = parseInt(parts[0]);
        const month = parseInt(parts[1]);
        const year = parseInt(parts[2]);
        if (month === m + 1 && year === y) {
          occupiedDays.add(day);
        }
      }
    }
  });
  
  for (let i = 0; i < startDayOfWeek; i++) {
    const empty = document.createElement('div');
    empty.className = 'cal-day empty';
    daysGrid.appendChild(empty);
  }
  
  // Get today's date for comparison (at midnight for accurate comparison)
  const today = new Date();
  today.setHours(0, 0, 0, 0);

  for (let d = 1; d <= daysInMonth; d++) {
    const el = document.createElement('div');
    el.className = 'cal-day';

    // Create date for this calendar day
    const dayDate = new Date(y, m, d);
    dayDate.setHours(0, 0, 0, 0);

    // Disable past dates
    const isPast = dayDate < today;
    const isToday = dayDate.getTime() === today.getTime();
    if (isPast) {
      el.classList.add('disabled');
      el.title = 'Nelze vybrat minulé datum';
      el.style.opacity = '0.3';
      el.style.cursor = 'not-allowed';
      el.style.backgroundColor = 'var(--wgs-gray-f0)';
    } else {
      if (isToday) {
        el.classList.add('dnes');
        el.title = 'Dnes';
      }
      if (occupiedDays.has(d)) {
        el.classList.add('occupied');
        if (!isToday) el.title = 'Tento den má již nějaké termíny';
      }
    }

    if (isToday) {
      el.innerHTML = `<span class="cal-day-num">${d}</span>`;
    } else {
      el.textContent = d;
    }
    el.onclick = () => {
      // Prevent selection of past dates
      if (isPast) {
        wgsToast.warning(t('cannot_select_past_date'));
        return;
      }

      SELECTED_DATE = `${d}.${m + 1}.${y}`;
      document.querySelectorAll('.cal-day').forEach(x => x.classList.remove('selected'));
      el.classList.add('selected');

      const displayEl2 = document.getElementById('selectedDateDisplay');
      if (displayEl2) {
        displayEl2.textContent = `Den: ${SELECTED_DATE}`;
        displayEl2.style.display = 'flex';
      }
      const btnUlozit2 = document.getElementById('btnUlozitTerminHore');
      if (btnUlozit2) btnUlozit2.style.display = 'none';

      // Zobrazit časy okamžitě
      renderTimeGrid();

      // Zobrazit vzdálenost a další termíny na tento den
      showDayBookingsWithDistances(SELECTED_DATE);
    };
    daysGrid.appendChild(el);
  }
  
  grid.appendChild(daysGrid);
}

