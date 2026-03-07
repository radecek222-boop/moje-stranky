/**
 * PSA Kalkulátor - JavaScript
 * WGS Payroll Calculator
 */
// === GLOBAL VARIABLES ===
let employees = [];
let allEmployeesDatabase = [];  // Kompletní seznam zaměstnanců z databáze
let salaryRate = 150;
let invoiceRate = 250;
let currentPeriod = { month: 11, year: 2025 };
const API_URL = '/app/psa_data.php';
const CSRF_TOKEN = window.PSA_CSRF_TOKEN || '';

// Speciální zaměstnanci - vždy zobrazeni, nelze smazat
const PERMANENT_EMPLOYEE_IDS = [19, 20, 21, 22];  // Marek, Lenka, Radek, Prémie

// === INITIALIZATION ===
window.addEventListener('DOMContentLoaded', () => {
  try {
    logger.log('PSA Kalkulátor initialized');
    initializePeriod();
    loadData();
  } catch (e) {
    console.error('Init error:', e);
  }
});

// === PERIOD MANAGEMENT ===
const MONTHS_CZ = ['', 'Leden', 'Únor', 'Březen', 'Duben', 'Květen', 'Červen',
                   'Červenec', 'Srpen', 'Září', 'Říjen', 'Listopad', 'Prosinec'];
const MONTHS_CZ_UPPER = ['', 'LEDEN', 'ÚNOR', 'BŘEZEN', 'DUBEN', 'KVĚTEN', 'ČERVEN',
                         'ČERVENEC', 'SRPEN', 'ZÁŘÍ', 'ŘÍJEN', 'LISTOPAD', 'PROSINEC'];

function initializePeriod() {
  // Vždy pracujeme s MINULÝM měsícem (výplaty za uplynulý měsíc)
  const now = new Date();
  // Posunout o 1 měsíc zpět
  now.setMonth(now.getMonth() - 1);
  currentPeriod.month = now.getMonth() + 1;  // getMonth() vrací 0-11
  currentPeriod.year = now.getFullYear();
  updatePeriodDisplay();
  updateNewAttendanceMonth();
}

function updatePeriodDisplay() {
  const periodText = `${MONTHS_CZ[currentPeriod.month]} ${currentPeriod.year}`;
  const displayEl = document.getElementById('periodDisplayText');
  if (displayEl) {
    displayEl.textContent = periodText;
  }
  // Aktualizovat i patičku tabulky
  updateFooterMonth();
}

// Aktualizovat měsíc v tlačítku "Nová docházka"
function updateNewAttendanceMonth() {
  const now = new Date();
  // Aktuální měsíc (pro novou docházku) = skutečný aktuální měsíc
  const nextMonth = now.getMonth() + 1;  // getMonth() vrací 0-11
  const monthEl = document.getElementById('newAttendanceMonth');
  if (monthEl) {
    monthEl.textContent = MONTHS_CZ_UPPER[nextMonth] || 'NOVÝ';
  }
}

// Aktualizovat měsíc v patičce tabulky
function updateFooterMonth() {
  const footerLabel = document.getElementById('footerMonthLabel');
  if (footerLabel) {
    footerLabel.textContent = `CELKEM za ${MONTHS_CZ[currentPeriod.month]}`;
  }
}

// === NOVÁ DOCHÁZKA (aktuální měsíc) ===
async function newAttendance() {
  const now = new Date();
  const newMonth = now.getMonth() + 1;  // Aktuální měsíc
  const newYear = now.getFullYear();

  // Kontrola jestli aktuální měsíc není stejný jako zobrazený
  if (currentPeriod.month === newMonth && currentPeriod.year === newYear) {
    wgsToast.info(`Už jste v období ${MONTHS_CZ[newMonth]} ${newYear}`);
    return;
  }

  // Přepnout na nový měsíc
  currentPeriod.month = newMonth;
  currentPeriod.year = newYear;

  updatePeriodDisplay();

  // Načíst data pro nové období (pokud existuje) nebo zobrazit prázdné
  await loadData();

  showSuccess(`Nová docházka za ${MONTHS_CZ[newMonth]} ${newYear}`);
  logger.log(`Switched to new attendance period: ${newMonth}/${newYear}`);
}

// === PERIOD OVERLAY ===
function togglePeriodOverlay() {
  const overlay = document.getElementById('periodOverlay');
  const periodBtn = document.getElementById('periodDisplay');

  if (overlay.classList.contains('active')) {
    closePeriodOverlay();
  } else {
    overlay.classList.add('active');
    periodBtn.classList.add('active');
    naplnitPeriodOverlay();
  }
}

function closePeriodOverlay() {
  const overlay = document.getElementById('periodOverlay');
  const periodBtn = document.getElementById('periodDisplay');
  overlay.classList.remove('active');
  periodBtn.classList.remove('active');
}

// === NEW PERIOD SELECTOR ===
function showNewPeriodSelector() {
  // Zavřít period overlay
  closePeriodOverlay();

  // Aktuální datum pro výchozí hodnoty
  const now = new Date();
  const currentMonth = now.getMonth() + 1;
  const currentYear = now.getFullYear();

  // Vytvořit modal
  const modal = document.createElement('div');
  modal.className = 'new-period-modal active';
  modal.id = 'newPeriodModal';

  // Generovat options pro měsíce
  const monthOptions = MONTHS_CZ.map((name, idx) => {
    if (idx === 0) return '';  // Přeskočit prázdný první prvek
    const selected = idx === currentMonth ? 'selected' : '';
    return `<option value="${idx}" ${selected}>${name}</option>`;
  }).join('');

  // Generovat options pro roky (aktuální rok + 2 roky dopředu a 3 roky zpět)
  const yearOptions = [];
  for (let y = currentYear - 3; y <= currentYear + 2; y++) {
    const selected = y === currentYear ? 'selected' : '';
    yearOptions.push(`<option value="${y}" ${selected}>${y}</option>`);
  }

  modal.innerHTML = `
    <div class="new-period-dialog">
      <div class="new-period-header">
        <span>Přidat nové období</span>
        <button class="new-period-close" data-action="closeNewPeriodSelector" title="Zavřít">&times;</button>
      </div>
      <div class="new-period-body">
        <div class="new-period-row">
          <div class="new-period-field">
            <label for="newPeriodMonth">Měsíc</label>
            <select id="newPeriodMonth">
              ${monthOptions}
            </select>
          </div>
          <div class="new-period-field">
            <label for="newPeriodYear">Rok</label>
            <select id="newPeriodYear">
              ${yearOptions.join('')}
            </select>
          </div>
        </div>
        <div class="new-period-info">
          Nové období bude obsahovat pouze permanentní zaměstnance (Marek, Lenka, Radek, Prémie). Další zaměstnance můžete přidat ručně.
        </div>
      </div>
      <div class="new-period-footer">
        <button class="btn btn-secondary" data-action="closeNewPeriodSelector">Zrušit</button>
        <button class="btn" data-action="confirmNewPeriod">Vytvořit období</button>
      </div>
    </div>
  `;

  document.body.appendChild(modal);

  // Zavřít při kliknutí mimo dialog
  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      closeNewPeriodSelector();
    }
  });

  // Zavřít při Escape
  const handleEscape = (e) => {
    if (e.key === 'Escape') {
      closeNewPeriodSelector();
      document.removeEventListener('keydown', handleEscape);
    }
  };
  document.addEventListener('keydown', handleEscape);
}

function closeNewPeriodSelector() {
  const modal = document.getElementById('newPeriodModal');
  if (modal) {
    modal.remove();
  }
}

async function confirmNewPeriod() {
  const monthSelect = document.getElementById('newPeriodMonth');
  const yearSelect = document.getElementById('newPeriodYear');

  if (!monthSelect || !yearSelect) {
    wgsToast.error('Chyba při načítání hodnot');
    return;
  }

  const newMonth = parseInt(monthSelect.value);
  const newYear = parseInt(yearSelect.value);

  if (!newMonth || !newYear) {
    wgsToast.error('Vyberte měsíc a rok');
    return;
  }

  // Zkontrolovat jestli období už existuje
  const periodKey = `${newYear}-${String(newMonth).padStart(2, '0')}`;

  try {
    const response = await fetch(API_URL, { credentials: 'same-origin' });
    const payload = await response.json();

    if (payload.status === 'success' && payload.data && payload.data.periods && payload.data.periods[periodKey]) {
      // Období už existuje - přepnout na něj
      if (await wgsConfirm(`Období ${MONTHS_CZ[newMonth]} ${newYear} již existuje. Chcete se na něj přepnout?`, { titulek: 'Období existuje', btnPotvrdit: 'Přepnout' })) {
        closeNewPeriodSelector();
        currentPeriod.month = newMonth;
        currentPeriod.year = newYear;
        updatePeriodDisplay();
        await loadPeriod();
        showSuccess(`Přepnuto na ${MONTHS_CZ[newMonth]} ${newYear}`);
      }
      return;
    }
  } catch (error) {
    logger.error('Chyba při kontrole období:', error);
  }

  // Vytvořit nové období pouze s permanentními zaměstnanci
  closeNewPeriodSelector();

  // Přepnout na nové období
  currentPeriod.month = newMonth;
  currentPeriod.year = newYear;
  updatePeriodDisplay();

  // Nastavit pouze permanentní zaměstnance s nulovými hodinami
  employees = allEmployeesDatabase
    .filter(emp => PERMANENT_EMPLOYEE_IDS.includes(emp.id))
    .map(emp => ({
      ...emp,
      bank: formatBankCode(emp.bank),
      hours: 0,
      bonusAmount: 0,
      premieCastka: 0
    }));

  renderTable();
  updateStats();

  // Uložit nové období na server
  try {
    saveToLocalStorage();
    await saveToServer();
    showSuccess(`Vytvořeno nové období: ${MONTHS_CZ[newMonth]} ${newYear}`);
    logger.log(`Created new period ${periodKey} with permanent employees only`);
  } catch (error) {
    logger.error('Chyba při ukládání nového období:', error);
    wgsToast.error('Chyba při ukládání období');
  }
}

// Zavřít overlay při kliknutí mimo
document.addEventListener('click', (e) => {
  const overlay = document.getElementById('periodOverlay');
  const periodBtn = document.getElementById('periodDisplay');

  if (overlay && overlay.classList.contains('active')) {
    if (!overlay.contains(e.target) && !periodBtn.contains(e.target)) {
      closePeriodOverlay();
    }
  }
});

// Naplnit overlay uloženými obdobími
async function naplnitPeriodOverlay() {
  const container = document.getElementById('periodOverlayContent');
  if (!container) return;

  container.innerHTML = '<div class="period-loading">Načítám období...</div>';

  try {
    const response = await fetch(API_URL, { credentials: 'same-origin' });
    if (!response.ok) throw new Error('Nepodařilo se načíst data');

    const payload = await response.json();
    if (payload.status !== 'success' || !payload.data) {
      throw new Error('Neplatná odpověď serveru');
    }

    const periods = payload.data.periods || {};

    // Seřadit období sestupně
    const sortedPeriods = Object.keys(periods).sort().reverse();

    if (sortedPeriods.length === 0) {
      container.innerHTML = '<div class="period-no-data">Žádná uložená období</div>';
      return;
    }

    // Aktuální klíč období
    const currentKey = `${currentPeriod.year}-${String(currentPeriod.month).padStart(2, '0')}`;

    // Generovat položky
    const html = sortedPeriods.map(key => {
      const [year, month] = key.split('-');
      const monthNum = parseInt(month);
      const label = `${MONTHS_CZ[monthNum]} ${year}`;
      const data = periods[key];
      const hours = data.totalHours || 0;
      const salary = data.totalSalary ? Math.round(data.totalSalary).toLocaleString('cs-CZ') : '0';
      const isCurrent = key === currentKey;

      return `
        <div class="period-item${isCurrent ? ' current' : ''}" data-action="selectPeriod" data-period="${key}">
          <div class="period-item-checkbox"></div>
          <div class="period-item-info">
            <div class="period-item-name">${label}</div>
            <div class="period-item-stats">${hours} hodin / ${salary} Kč</div>
          </div>
        </div>
      `;
    }).join('');

    container.innerHTML = html;

  } catch (error) {
    logger.error('Chyba při načítání období pro overlay:', error);
    container.innerHTML = '<div class="period-no-data">Chyba při načítání období</div>';
  }
}

// Vybrat období z overlay
async function selectPeriod(periodKey) {
  // Parsovat období
  const [year, month] = periodKey.split('-');
  currentPeriod.year = parseInt(year);
  currentPeriod.month = parseInt(month);

  updatePeriodDisplay();
  closePeriodOverlay();

  await loadPeriod();
}

// === NAČÍST OBDOBÍ ===
async function loadPeriod() {
  const periodKey = `${currentPeriod.year}-${String(currentPeriod.month).padStart(2, '0')}`;

  try {
    const response = await fetch(API_URL, { credentials: 'same-origin' });
    if (!response.ok) throw new Error('Nepodařilo se načíst data');

    const payload = await response.json();
    if (payload.status !== 'success' || !payload.data) {
      throw new Error('Neplatná odpověď serveru');
    }

    const data = payload.data;

    // Zkontrolovat jestli období existuje
    if (!data.periods || !data.periods[periodKey]) {
      showError(`Období ${MONTHS_CZ[currentPeriod.month]} ${currentPeriod.year} nebylo nalezeno. Nejprve uložte data pro toto období.`);
      // Vynulovat hodiny
      employees.forEach(emp => emp.hours = 0);
      renderTable();
      updateStats();
      return;
    }

    // Načíst data období
    const periodData = data.periods[periodKey];

    // Aktualizovat databázi zaměstnanců
    allEmployeesDatabase = data.employees.map(emp => ({
      ...emp,
      bank: formatBankCode(emp.bank)
    }));

    // Filtrovat zaměstnance pro období:
    // 1. Permanentní zaměstnanci (Marek, Lenka, Radek, Prémie) - vždy
    // 2. Zaměstnanci kteří mají v období hours > 0
    employees = data.employees
      .filter(emp => {
        const periodEmp = periodData.employees.find(pe => pe.id === emp.id);
        // Permanentní zaměstnanci vždy
        if (PERMANENT_EMPLOYEE_IDS.includes(emp.id)) return true;
        // Ostatní jen pokud mají hodiny v období
        return periodEmp && periodEmp.hours > 0;
      })
      .map(emp => {
        const periodEmp = periodData.employees.find(pe => pe.id === emp.id);
        return {
          ...emp,
          // Účet a banka - preferovat hodnotu z období, jinak hlavní
          account: periodEmp && periodEmp.account ? periodEmp.account : (emp.account || ''),
          bank: formatBankCode(periodEmp && periodEmp.bank ? periodEmp.bank : (emp.bank || '')),
          // Hodiny a bonusy z období
          hours: periodEmp ? (periodEmp.hours || 0) : 0,
          bonusAmount: periodEmp ? (periodEmp.bonusAmount || 0) : (emp.bonusAmount || 0),
          premieCastka: periodEmp ? (periodEmp.premieCastka || 0) : 0
        };
      });

    renderTable();
    updateStats();
    showSuccess(`Načteno období: ${MONTHS_CZ[currentPeriod.month]} ${currentPeriod.year} (${periodData.totalHours || 0} hodin)`);
    logger.log(`Loaded period ${periodKey}:`, periodData);

  } catch (error) {
    logger.error('Chyba při načítání období:', error);
    showError('Chyba při načítání období: ' + error.message);
  }
}

// === VYČISTIT HODINY (NOVÉ OBDOBÍ) ===
function clearHours() {
  employees.forEach(emp => {
    emp.hours = 0;
    if (emp.type === 'bonus_girls') emp.bonusAmount = 0;
  });
  renderTable();
  updateStats();
  showSuccess('Hodiny vynulovány - připraveno pro nové období');
}

// === DATA LOADING ===
async function loadData(period = null) {
  try {
    logger.log('Loading data from JSON...', period ? `for period ${period}` : '');

    const response = await fetch(API_URL, { credentials: 'same-origin' });

    if (!response.ok) {
      throw new Error(`Server responded with ${response.status}`);
    }

    const payload = await response.json();

    if (payload.status !== 'success' || !payload.data) {
      throw new Error(payload.message || 'Neplatná odpověď serveru');
    }

    const data = payload.data;

    // Uložit kompletní databázi zaměstnanců pro výběr
    if (data.employees && data.employees.length > 0) {
      allEmployeesDatabase = data.employees.map(emp => ({
        ...emp,
        bank: formatBankCode(emp.bank)
      }));
      logger.log('loadData: allEmployeesDatabase loaded with', allEmployeesDatabase.length, 'employees');
    } else {
      // FALLBACK: Pokud employees je prázdné, zkusit načíst z posledního období
      logger.warn('loadData: data.employees is empty, trying fallback from periods...');
      if (data.periods) {
        const sortedPeriods = Object.keys(data.periods).sort().reverse();
        if (sortedPeriods.length > 0) {
          const lastPeriod = data.periods[sortedPeriods[0]];
          if (lastPeriod && lastPeriod.employees && lastPeriod.employees.length > 0) {
            allEmployeesDatabase = lastPeriod.employees.map(emp => ({
              ...emp,
              bank: formatBankCode(emp.bank || '')
            }));
            logger.log('loadData: FALLBACK - loaded', allEmployeesDatabase.length, 'employees from period', sortedPeriods[0]);
          }
        }
      }
    }

    // Load configuration
    if (data.config) {
      salaryRate = data.config.salaryRate || 150;
      invoiceRate = data.config.invoiceRate || 250;
      document.getElementById('salaryRate').value = salaryRate;
      document.getElementById('invoiceRate').value = invoiceRate;
    }

    // Určit klíč období - buď předaný nebo aktuální
    const periodKey = period || `${currentPeriod.year}-${String(currentPeriod.month).padStart(2, '0')}`;

    // Načíst zaměstnance podle období
    if (data.periods && data.periods[periodKey]) {
      // Období existuje - filtrovat zaměstnance
      const periodData = data.periods[periodKey];

      employees = data.employees
        .filter(emp => {
          const periodEmp = periodData.employees.find(pe => pe.id === emp.id);
          // Permanentní zaměstnanci vždy
          if (PERMANENT_EMPLOYEE_IDS.includes(emp.id)) return true;
          // Ostatní jen pokud mají hodiny v období
          return periodEmp && periodEmp.hours > 0;
        })
        .map(emp => {
          const periodEmp = periodData.employees.find(pe => pe.id === emp.id);
          return {
            ...emp,
            account: periodEmp && periodEmp.account ? periodEmp.account : (emp.account || ''),
            bank: formatBankCode(periodEmp && periodEmp.bank ? periodEmp.bank : (emp.bank || '')),
            hours: periodEmp ? (periodEmp.hours || 0) : 0,
            bonusAmount: periodEmp ? (periodEmp.bonusAmount || 0) : (emp.bonusAmount || 0),
            premieCastka: periodEmp ? (periodEmp.premieCastka || 0) : 0
          };
        });
      logger.log(`Loaded period ${periodKey} with ${employees.length} employees`);
    } else {
      // Období neexistuje - zobrazit pouze permanentní zaměstnance
      employees = data.employees
        .filter(emp => PERMANENT_EMPLOYEE_IDS.includes(emp.id))
        .map(emp => ({
          ...emp,
          bank: formatBankCode(emp.bank),
          hours: 0,
          bonusAmount: 0,
          premieCastka: 0
        }));
      logger.log(`New period ${periodKey} - showing only permanent employees`);
    }

    renderTable();
    updateStats();
  } catch (error) {
    logger.error('Error loading data:', error);
    // Try to load from localStorage as fallback
    loadFromLocalStorage();
  }
}

// === DATA SAVING ===
async function saveData() {
  try {
    logger.log('Saving data to server...');

    // Uložit do localStorage jako záloha
    saveToLocalStorage();

    // Uložit na server
    await saveToServer();

    showSuccess('Data byla úspěšně uložena');
  } catch (error) {
    logger.error('Error saving data:', error);
    showError('Chyba při ukládání dat: ' + error.message);
  }
}

// === SERVER SAVE ===
async function saveToServer() {
  // Spočítat statistiky pro období
  const stats = calculateStats();

  // Připravit data období - hodiny, prémie a základní info
  // Zahrnout VŠECHNY aktivní zaměstnance (ne jen ty s hodinami > 0)
  const periodEmployees = employees.filter(e => {
    return e.active !== false;  // Všichni aktivní zaměstnanci
  }).map(emp => ({
    id: emp.id,
    name: emp.name,
    hours: emp.hours || 0,
    type: emp.type || 'standard',
    bonusAmount: emp.bonusAmount || 0,
    premieCastka: emp.premieCastka || 0,
    account: emp.account || '',
    bank: emp.bank || ''
  }));

  // Kompletní databáze zaměstnanců k uložení
  // DŮLEŽITÉ: employees musí být celá databáze (allEmployeesDatabase),
  // NE filtrovaný seznam pro aktuální období (employees)!
  const employeesToSave = allEmployeesDatabase.length > 0
    ? allEmployeesDatabase
    : employees;

  // Data ve formátu pro SQL API
  const data = {
    config: {
      salaryRate: salaryRate,
      invoiceRate: invoiceRate
    },
    employees: employeesToSave,
    periodData: {
      year: currentPeriod.year,
      month: currentPeriod.month,
      employees: periodEmployees,
      totalHours: stats.totalHours,
      totalSalary: stats.totalSalary,
      totalInvoice: stats.totalInvoice,
      profit: stats.profit,
      marekBonus: stats.marekBonus || 0,
      radekBonus: stats.radekBonus || 0,
      girlsBonus: stats.girlsBonus || 0,
      radekTotal: stats.radekTotal || 0,
      premieCelkem: stats.premieCelkem || 0
    }
  };

  try {
    const response = await fetch(API_URL, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json; charset=utf-8',
        'X-CSRF-Token': CSRF_TOKEN
      },
      credentials: 'same-origin',
      body: JSON.stringify(data)
    });

    const result = await response.json();

    if (!response.ok || result.status !== 'success') {
      throw new Error(result.message || 'Nepodařilo se uložit data na server');
    }

    const periodKey = `${currentPeriod.year}-${String(currentPeriod.month).padStart(2, '0')}`;
    logger.log(`Data pro období ${periodKey} úspěšně uložena do SQL`, result);
    return result;
  } catch (error) {
    logger.error('Server save failed:', error);
    throw error;
  }
}

// === VÝPOČET STATISTIK ===
// Používá STEJNOU logiku jako updateStats() - počítá přesně jako v tabulce
function calculateStats() {
  let totalHours = 0;
  let totalSalary = 0;
  let totalInvoice = 0;

  // Nejprve spočítat celkové hodiny pro bonus special zaměstnanců
  let totalOtherHours = 0;
  employees.forEach(emp => {
    if (emp.active === false) return;
    if (emp.type !== 'special' && emp.type !== 'special2' && emp.type !== 'bonus_girls' && emp.type !== 'premie_polozka') {
      const isLenka = emp.name === 'Lenka' || emp.name.includes('Lenka');
      if (!isLenka) {
        totalOtherHours += parseFloat(emp.hours) || 0;
      }
    }
  });

  // Projít všechny zaměstnance a sečíst PŘESNĚ jako v tabulce
  employees.forEach(emp => {
    if (emp.active === false) return;

    const isLenka = emp.name === 'Lenka' || emp.name.includes('Lenka');
    let salary = 0;
    let invoice = 0;

    if (isLenka) {
      // Lenka má editovatelnou paušální odměnu (výchozí 8716 Kč)
      salary = emp.bonusAmount !== undefined ? (parseFloat(emp.bonusAmount) || 0) : 8716;
      invoice = 0;
    } else if (emp.type === 'bonus_girls') {
      // Bonus pro holky - editovatelná částka
      salary = parseFloat(emp.bonusAmount) || 0;
      invoice = 0;
    } else if (emp.type === 'special' || emp.type === 'special2') {
      // Special zaměstnanci (Marek, Radek) - bonus z hodin ostatních
      salary = totalOtherHours * 20;
      invoice = 0;
    } else if (emp.type === 'premie_polozka') {
      // Prémie položka - editovatelná částka
      salary = parseFloat(emp.premieCastka) || 0;
      invoice = 0;
    } else if (emp.type === 'pausalni' && emp.pausalni) {
      // Paušální zaměstnanci
      const hours = parseFloat(emp.hours) || 0;
      const monthlyRate = emp.pausalni.rate / 12;
      const monthlyTax = emp.pausalni.tax;
      salary = hours * salaryRate;
      invoice = Math.min(hours * invoiceRate, monthlyRate - monthlyTax);
    } else {
      // Standardní zaměstnanci
      const hours = parseFloat(emp.hours) || 0;
      salary = hours * salaryRate;
      invoice = hours * invoiceRate;
    }

    totalSalary += salary;
    totalInvoice += invoice;

    // Hodiny jen pro běžné zaměstnance
    if (emp.type !== 'special' && emp.type !== 'special2' && emp.type !== 'bonus_girls' && emp.type !== 'premie_polozka' && !isLenka) {
      totalHours += parseFloat(emp.hours) || 0;
    }
  });

  // Bonusy pro zpětnou kompatibilitu (používá se při ukládání do DB)
  const marekBonus = totalOtherHours * 20;
  const radekBonus = totalOtherHours * 20;
  const premiePolozky = employees.filter(e => e.type === 'premie_polozka');
  const premieCelkem = premiePolozky.reduce((sum, e) => sum + (parseFloat(e.premieCastka) || 0), 0);

  return {
    totalHours,
    totalSalary,
    totalInvoice,
    profit: totalInvoice - totalSalary,
    marekBonus,
    radekBonus,
    girlsBonus: 0,  // Již se nepočítá zvlášť
    radekTotal: radekBonus,
    premieCelkem
  };
}

// === LOCAL STORAGE (BACKUP) ===
function saveToLocalStorage() {
  const data = {
    employees: employees,
    salaryRate: salaryRate,
    invoiceRate: invoiceRate,
    period: currentPeriod,
    lastModified: new Date().toISOString()
  };
  localStorage.setItem('psaData', JSON.stringify(data));
  logger.log('Data saved to localStorage (backup)');
}

function loadFromLocalStorage() {
  const saved = localStorage.getItem('psaData');
  if (saved) {
    try {
      const data = JSON.parse(saved);
      employees = data.employees || [];
      salaryRate = data.salaryRate || 150;
      invoiceRate = data.invoiceRate || 250;

      if (data.period) {
        currentPeriod = data.period;
        updatePeriodDisplay();
      }

      document.getElementById('salaryRate').value = salaryRate;
      document.getElementById('invoiceRate').value = invoiceRate;

      renderTable();
      updateStats();
      logger.log('Data loaded from localStorage');
    } catch (error) {
      logger.error('Error loading from localStorage:', error);
    }
  }
}

// === EMPLOYEE MANAGEMENT ===
// Správa zaměstnanců přesunuta do psa-zamestnanci.js
// === TABLE RENDERING ===
function renderTable() {
  const tbody = document.getElementById('employeeTableBody');

  if (employees.length === 0) {
    tbody.innerHTML = `
      <tr>
        <td colspan="7" class="text-center" style="padding: 3rem;">
          <div data-action="showEmployeeSelector"
               style="cursor: pointer; display: inline-block; padding: 1.5rem 2.5rem; border: 2px dashed var(--c-grey); border-radius: 8px; transition: all 0.2s ease;"
               onmouseover="this.style.borderColor='var(--c-black)'; this.style.background='rgba(0,0,0,0.02)'"
               onmouseout="this.style.borderColor='var(--c-grey)'; this.style.background='transparent'">
            <div style="font-size: 2.5rem; font-weight: 300; color: var(--c-grey); line-height: 1;">+</div>
            <div style="margin-top: 0.5rem; font-size: 0.85rem; color: var(--c-grey);">Přidat zaměstnance</div>
          </div>
        </td>
      </tr>
    `;
    return;
  }

  // Calculate total hours for bonus calculation
  let totalOtherHours = 0;
  employees.forEach(emp => {
    if (emp.type !== 'special' && emp.type !== 'special2') {
      totalOtherHours += emp.hours || 0;
    }
  });

  tbody.innerHTML = employees.map((emp, index) => {
    let salary, invoice;
    let displayInfo = '';
    let isLenka = emp.name === 'Lenka' || emp.name.includes('Lenka');

    // Calculate based on employee type
    if (isLenka) {
      // Lenka má editovatelnou paušální odměnu (výchozí 8716 Kč)
      salary = emp.bonusAmount !== undefined ? (emp.bonusAmount || 0) : 8716;
      invoice = 0;
      displayInfo = '<span class="employee-type-badge" style="background: var(--c-info);">Paušální odměna</span>';
    } else if (emp.type === 'bonus_girls') {
      // Bonus pro holky - editovatelná částka
      salary = emp.bonusAmount || 0;
      invoice = 0;
      displayInfo = '<span class="employee-type-badge" style="background: var(--c-warning);">Manuální bonus</span>';
    } else if (emp.type === 'pausalni' && emp.pausalni) {
      const monthlyRate = emp.pausalni.rate / 12;
      const monthlyTax = emp.pausalni.tax;
      salary = emp.hours * salaryRate;
      invoice = Math.min(emp.hours * invoiceRate, monthlyRate - monthlyTax);
      displayInfo = '<span class="employee-type-badge">Paušál</span>';
    } else if (emp.type === 'special' || emp.type === 'special2') {
      // Bonus z hodin ostatních
      salary = totalOtherHours * 20;
      invoice = 0;
      displayInfo = '<span class="employee-type-badge">Pouze bonus</span>';
    } else if (emp.type === 'premie_polozka') {
      // Samostatná položka pro prémie - editovatelná částka
      salary = emp.premieCastka || 0;
      invoice = 0;
      displayInfo = '<span class="employee-type-badge" style="background: var(--c-black); color: var(--c-white);">Prémie</span>';
    } else {
      salary = emp.hours * salaryRate;
      invoice = emp.hours * invoiceRate;
      if (emp.type === 'swift') {
        displayInfo = '<span class="employee-type-badge">SWIFT</span>';
      }
    }

    const accountDisplay = emp.type === 'swift' && emp.swiftData ?
      emp.swiftData.iban.substr(0, 10) + '...' :
      emp.account || '';

    const bankDisplay = emp.type === 'swift' && emp.swiftData ?
      emp.swiftData.swift :
      formatBankCode(emp.bank) || '';

    return `
      <tr>
        <td>
          <input type="text"
                 value="${emp.name}"
                 class="table-input"
                 style="font-weight: 600; min-width: 100px;"
                 data-action="updateEmployeeField"
                 data-index="${index}"
                 data-field="name"
                 data-recalculate="true">
          ${displayInfo}
        </td>
        <td class="text-center">
          ${(emp.type === 'special' || emp.type === 'special2') ?
            '<span style="color: var(--c-grey);">–</span>' :
            isLenka ?
              `<input type="number"
                     value="${emp.bonusAmount !== undefined ? emp.bonusAmount : 8716}"
                     min="0"
                     step="100"
                     class="table-input"
                     style="width: 100px; text-align: center; font-weight: 600; background: #d1ecf1;"
                     placeholder="Částka (Kč)"
                     data-action="updateEmployeeField"
                     data-index="${index}"
                     data-field="bonusAmount">` :
            emp.type === 'premie_polozka' ?
              `<input type="number"
                     value="${emp.premieCastka || 0}"
                     min="0"
                     step="100"
                     class="table-input"
                     style="width: 100px; text-align: center; font-weight: 600;"
                     placeholder="Částka (Kč)"
                     data-action="updateEmployeeField"
                     data-index="${index}"
                     data-field="premieCastka">` :
            emp.type === 'bonus_girls' ?
              `<input type="number"
                     value="${emp.bonusAmount || 0}"
                     min="0"
                     step="100"
                     class="table-input"
                     style="width: 100px; text-align: center; font-weight: 600; background: #fff3cd;"
                     placeholder="Částka (Kč)"
                     data-action="updateEmployeeField"
                     data-index="${index}"
                     data-field="bonusAmount">` :
              `<input type="number"
                     value="${emp.hours}"
                     min="0"
                     class="table-input"
                     style="width: 80px; text-align: center; font-weight: 600;"
                     data-action="updateEmployeeField"
                     data-index="${index}"
                     data-field="hours">`
          }
        </td>
        <td class="text-right" style="font-weight: 600; color: var(--c-success);">
          ${formatCurrency(salary)}
          ${(emp.type === 'special' || emp.type === 'special2') ?
            '<br><span style="font-size: 0.75rem; color: var(--c-grey);">' + totalOtherHours + 'h × 20</span>' : ''}
        </td>
        <td class="text-right" style="font-weight: 600; color: var(--c-info);">
          ${formatCurrency(invoice)}
        </td>
        <td>
          <input type="text"
                 value="${accountDisplay}"
                 placeholder="Číslo účtu"
                 class="table-input"
                 ${emp.type === 'swift' ? 'readonly' : ''}
                 data-action="updateEmployeeField"
                 data-index="${index}"
                 data-field="account">
        </td>
        <td>
          <input type="text"
                 value="${bankDisplay}"
                 placeholder="Kód"
                 class="table-input"
                 style="width: 100px; text-align: center;"
                 ${emp.type === 'swift' ? 'readonly' : ''}
                 data-action="updateEmployeeField"
                 data-index="${index}"
                 data-field="bank">
        </td>
        <td class="text-center" style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 0.25rem; min-width: 120px; align-items: center; vertical-align: middle;">
          <button class="btn btn-sm" onclick="saveEmployeeChanges(${index})" title="Uložit změny">Uložit</button>
          ${emp.isNew ?
            `<button class="btn btn-sm" style="background: var(--c-success); color: white;" onclick="saveEmployeeToDatabase(${index})" title="Uložit do databáze">DB</button>` :
            `<button class="btn btn-sm qr-btn" style="background: var(--c-info); color: white;" onclick="generateSingleEmployeeQR(${index})" title="Generovat QR platbu">QR</button>`
          }
          ${PERMANENT_EMPLOYEE_IDS.includes(emp.id) ?
            `<span></span>` :
            `<button class="btn btn-danger btn-sm" onclick="removeEmployee(${index})" title="Odebrat z období">×</button>`
          }
        </td>
      </tr>
    `;
  }).join('');
}

// === STATISTICS ===
function updateStats() {
  let totalHours = 0;
  let totalSalary = 0;
  let totalInvoice = 0;

  // Nejprve spočítat celkové hodiny pro bonus special zaměstnanců
  let totalOtherHours = 0;
  employees.forEach(emp => {
    if (emp.type !== 'special' && emp.type !== 'special2' && emp.type !== 'bonus_girls' && emp.type !== 'premie_polozka') {
      const isLenka = emp.name === 'Lenka' || emp.name.includes('Lenka');
      if (!isLenka) {
        totalOtherHours += emp.hours || 0;
      }
    }
  });

  // Počet aktivních zaměstnanců (jen běžní s hodinami)
  let activeEmployeesCount = 0;

  // Projít všechny zaměstnance a sečíst PŘESNĚ jako v tabulce
  employees.forEach(emp => {
    const isLenka = emp.name === 'Lenka' || emp.name.includes('Lenka');
    let salary = 0;
    let invoice = 0;

    if (isLenka) {
      // Lenka má editovatelnou paušální odměnu (výchozí 8716 Kč)
      salary = emp.bonusAmount !== undefined ? (emp.bonusAmount || 0) : 8716;
      invoice = 0;
    } else if (emp.type === 'bonus_girls') {
      // Bonus pro holky - editovatelná částka
      salary = emp.bonusAmount || 0;
      invoice = 0;
    } else if (emp.type === 'special' || emp.type === 'special2') {
      // Special zaměstnanci (Marek, Radek) - bonus z hodin ostatních
      salary = totalOtherHours * 20;
      invoice = 0;
    } else if (emp.type === 'premie_polozka') {
      // Prémie položka - editovatelná částka
      salary = emp.premieCastka || 0;
      invoice = 0;
    } else if (emp.type === 'pausalni' && emp.pausalni) {
      // Paušální zaměstnanci
      const monthlyRate = emp.pausalni.rate / 12;
      const monthlyTax = emp.pausalni.tax;
      salary = emp.hours * salaryRate;
      invoice = Math.min(emp.hours * invoiceRate, monthlyRate - monthlyTax);
      if (emp.hours > 0) activeEmployeesCount++;
    } else {
      // Standardní zaměstnanci
      salary = (emp.hours || 0) * salaryRate;
      invoice = (emp.hours || 0) * invoiceRate;
      if (emp.hours > 0) activeEmployeesCount++;
    }

    totalSalary += salary;
    totalInvoice += invoice;

    // Hodiny jen pro běžné zaměstnance
    if (emp.type !== 'special' && emp.type !== 'special2' && emp.type !== 'bonus_girls' && emp.type !== 'premie_polozka' && !isLenka) {
      totalHours += emp.hours || 0;
    }
  });

  const totalProfit = totalInvoice - totalSalary;
  const profitMargin = totalInvoice > 0 ? (totalProfit / totalInvoice * 100) : 0;

  // Update UI
  document.getElementById('totalHours').textContent = formatNumber(totalHours);
  document.getElementById('totalSalary').textContent = formatCurrency(totalSalary);
  document.getElementById('totalInvoice').textContent = formatCurrency(totalInvoice);
  document.getElementById('employeeCount').textContent = activeEmployeesCount; // ← POČÍTÁ JEN S HODINAMI
  document.getElementById('totalProfit').textContent = formatCurrency(totalProfit);
  document.getElementById('profitMargin').textContent = `Marže: ${profitMargin.toFixed(1)}%`;

  // Averages - ONLY from active employees with hours (excluding Lenka, special, bonus_girls, premie)
  const avgHours = activeEmployeesCount > 0
    ? totalHours / activeEmployeesCount
    : 0;
  const avgSalary = activeEmployeesCount > 0 ? totalSalary / activeEmployeesCount : 0;

  document.getElementById('avgHoursPerEmployee').textContent = avgHours.toFixed(1) + 'h';
  document.getElementById('avgSalaryPerEmployee').textContent = formatCurrency(avgSalary);

  // Info
  document.getElementById('salaryRateInfo').textContent = `${salaryRate} Kč/hodina`;
  document.getElementById('invoiceRateInfo').textContent = `${invoiceRate} Kč/hodina`;

  // Footer
  document.getElementById('footerTotalHours').textContent = formatNumber(totalHours);
  document.getElementById('footerTotalSalary').textContent = formatCurrency(totalSalary);
  document.getElementById('footerTotalInvoice').textContent = formatCurrency(totalInvoice);
}

// === RATES ===
async function updateRates() {
  salaryRate = parseInt(document.getElementById('salaryRate').value) || 150;
  invoiceRate = parseInt(document.getElementById('invoiceRate').value) || 250;

  renderTable();
  updateStats();

  // Automaticky uložit na server
  try {
    saveToLocalStorage();
    await saveToServer();
    logger.log('Rates updated and saved');
  } catch (error) {
    logger.error('Failed to save after updating rates:', error);
  }
}

// === UTILITIES ===
function formatCurrency(amount) {
  return new Intl.NumberFormat('cs-CZ', {
    style: 'currency',
    currency: 'CZK',
    minimumFractionDigits: 0,
    maximumFractionDigits: 0
  }).format(amount);
}

// Step 134: Use centralized formatNumber from utils.js if available
function formatNumber(num) {
  if (window.Utils && window.Utils.formatNumber) {
    return window.Utils.formatNumber(num);
  }
  return new Intl.NumberFormat('cs-CZ').format(num);
}

function formatBankCode(code) {
  if (!code) return '';
  const digits = code.toString().replace(/\D/g, '');
  if (!digits) return '';
  return digits.padStart(4, '0').slice(-4);
}

function normalizeAccount(account) {
  if (!account) return '';
  const digits = account.toString().replace(/\D/g, '');
  if (!digits) return '';
  const accountPart = digits.slice(-10);
  const prefixPart = digits.length > 10 ? digits.slice(0, -10) : '';
  return prefixPart ? `${parseInt(prefixPart, 10)}-${accountPart}` : accountPart;
}

/**
 * Konverze ceskeho cisla uctu na IBAN
 * Format: CZ + 2 kontrolni cislice + 4 kod banky + 6 predcisli + 10 cislo uctu
 */
function convertToIBAN(account, bankCode) {
  // Nejprve odstranit jen mezery (pomlcku zatim nechat pro split)
  let rawAccount = (account || '').toString().replace(/\s/g, '');
  bankCode = (bankCode || '').toString().replace(/\D/g, '').padStart(4, '0');

  // Rozdelit predcisli a cislo uctu
  let predcisli = '';
  let cisloUctu = '';

  if (rawAccount.includes('-')) {
    // Format: predcisli-cislo (napr. 19-123456789)
    const parts = rawAccount.split('-');
    predcisli = (parts[0] || '').replace(/\D/g, '');
    cisloUctu = (parts[1] || '').replace(/\D/g, '');
  } else {
    // Bez pomlcky - jen cisla
    const digits = rawAccount.replace(/\D/g, '');
    if (digits.length > 10) {
      predcisli = digits.slice(0, -10);
      cisloUctu = digits.slice(-10);
    } else {
      predcisli = '';
      cisloUctu = digits;
    }
  }

  // Doplnit na spravnou delku
  predcisli = predcisli.padStart(6, '0');
  cisloUctu = cisloUctu.padStart(10, '0');

  // BBAN = kod banky + predcisli + cislo uctu
  const bban = bankCode + predcisli + cisloUctu;

  // Kontrola BBAN delky (4 + 6 + 10 = 20 znaku)
  if (bban.length !== 20) {
    throw new Error(`Neplatná délka BBAN: ${bban.length} (očekáváno 20)`);
  }

  // Vypocet kontrolnich cislic (ISO 7064 Mod 97-10)
  // Presunout CZ00 na konec a nahradit pismena cisly (C=12, Z=35)
  const checkString = bban + '123500'; // CZ = 12 35, 00 = placeholder
  let remainder = BigInt(checkString) % 97n;
  const checkDigits = String(98n - remainder).padStart(2, '0');

  const iban = 'CZ' + checkDigits + bban;

  // Kontrola IBAN delky (CZ ma vzdy 24 znaku)
  if (iban.length !== 24) {
    throw new Error(`Neplatná délka IBAN: ${iban.length} (očekáváno 24)`);
  }

  return iban;
}

function sanitizeMessage(message) {
  if (!message) return '';
  return message
    .toString()
    .replace(/[\r\n]+/g, ' ')
    .replace(/\*/g, ' ')
    .trim()
    .slice(0, 60);
}

let qrLibraryPromise = null;

/**
 * Robustni loader pro QR knihovnu
 * - resi "load uz probehl"
 * - resi "script je v DOM, ale byl pridan mimo vas loader"
 * - resi "neco se pokazilo a uz se nic nestane" (timeout)
 * - po failu resetuje qrLibraryPromise
 */
// QR platby přesunuty do psa-qr-platby.js
// === UNIVERSAL EVENT DELEGATION FOR REMOVED INLINE HANDLERS ===
document.addEventListener('DOMContentLoaded', () => {
  // Společná funkce pro zpracování data-action
  function zpracujDataAction(target) {
    const action = target.getAttribute('data-action');

    // Special cases
    if (action === 'reload') {
      location.reload();
      return;
    }

    // Step 115 - Podpora parametrů pro onclick migraci
    switch (action) {
      case 'generateSingleEmployeeQR': {
        const gIndexStr = target.getAttribute('data-index');
        const gIndex = Number.parseInt(gIndexStr, 10);

        if (!Number.isInteger(gIndex) || gIndex < 0) {
          console.warn('QR: invalid data-index', gIndexStr, target);
          wgsToast.error('Neplatný index zaměstnance');
          return;
        }

        if (typeof generateSingleEmployeeQR !== 'function') {
          console.error('generateSingleEmployeeQR is not a function');
          wgsToast.error('QR modul není načten');
          return;
        }

        generateSingleEmployeeQR(gIndex);
        return;
      }

      case 'saveEmployeeToDatabase': {
        const sIndex = target.getAttribute('data-index');
        if (sIndex !== null && typeof saveEmployeeToDatabase === 'function') {
          saveEmployeeToDatabase(parseInt(sIndex, 10));
        }
        return;
      }

      case 'saveEmployeeChanges': {
        const scIndex = parseInt(target.getAttribute('data-index'), 10);
        if (!isNaN(scIndex) && typeof saveEmployeeChanges === 'function') {
          saveEmployeeChanges(scIndex);
        }
        return;
      }

      case 'removeEmployee': {
        const rIndex = target.getAttribute('data-index');
        if (rIndex !== null && typeof removeEmployee === 'function') {
          removeEmployee(parseInt(rIndex, 10));
        }
        return;
      }

      case 'copySWIFTDetails': {
        const name = target.getAttribute('data-name');
        const iban = target.getAttribute('data-iban');
        const swift = target.getAttribute('data-swift');
        const amount = parseFloat(target.getAttribute('data-amount'));
        if (name && iban && swift && typeof copySWIFTDetails === 'function') {
          copySWIFTDetails(name, iban, swift, amount);
        }
        return;
      }

      case 'downloadQR': {
        const qrid = target.getAttribute('data-qrid');
        const dName = target.getAttribute('data-name');
        if (qrid && dName && typeof downloadQR === 'function') {
          downloadQR(qrid, dName);
        }
        return;
      }

      case 'shareQR': {
        const shareQrid = target.getAttribute('data-qrid');
        const shareName = target.getAttribute('data-name');
        const shareAmount = target.getAttribute('data-amount');
        if (shareQrid && shareName && typeof shareQRCode === 'function') {
          shareQRCode(shareQrid, shareName, shareAmount);
        }
        return;
      }

      case 'updateEmployeeField': {
        const empIndex = parseInt(target.getAttribute('data-index'), 10);
        const empField = target.getAttribute('data-field');
        const empRecalculate = target.getAttribute('data-recalculate') === 'true';
        if (!isNaN(empIndex) && empField && typeof updateEmployee === 'function') {
          updateEmployee(empIndex, empField, target.value, empRecalculate);
        }
        return;
      }

      // === PSA HLAVNÍ AKCE ===
      case 'saveData':
        if (typeof saveData === 'function') saveData();
        return;

      case 'addEmployee':
        if (typeof addEmployee === 'function') addEmployee();
        return;

      case 'exportToExcel':
        if (typeof exportToExcel === 'function') exportToExcel();
        return;

      case 'printReport':
        if (typeof printReport === 'function') printReport();
        return;

      case 'clearAll':
        if (typeof clearAll === 'function') clearAll();
        return;

      case 'generatePaymentQR':
        if (typeof generatePaymentQR === 'function') generatePaymentQR();
        return;

      case 'closeQRModal':
        if (typeof closeQRModal === 'function') closeQRModal();
        return;

      case 'updatePeriod':
        if (typeof updatePeriod === 'function') updatePeriod();
        return;

      case 'updateRates':
        if (typeof updateRates === 'function') updateRates();
        return;

      // === OBDOBÍ MANAGEMENT ===
      case 'togglePeriodOverlay':
        if (typeof togglePeriodOverlay === 'function') togglePeriodOverlay();
        return;

      case 'closePeriodOverlay':
        if (typeof closePeriodOverlay === 'function') closePeriodOverlay();
        return;

      case 'showNewPeriodSelector':
        if (typeof showNewPeriodSelector === 'function') showNewPeriodSelector();
        return;

      case 'closeNewPeriodSelector':
        if (typeof closeNewPeriodSelector === 'function') closeNewPeriodSelector();
        return;

      case 'confirmNewPeriod':
        if (typeof confirmNewPeriod === 'function') confirmNewPeriod();
        return;

      case 'selectPeriod': {
        const periodToSelect = target.getAttribute('data-period');
        if (periodToSelect && typeof selectPeriod === 'function') {
          selectPeriod(periodToSelect);
        }
        return;
      }

      case 'loadPeriod':
        if (typeof loadPeriod === 'function') loadPeriod();
        return;

      case 'clearHours':
        if (typeof clearHours === 'function') clearHours();
        return;

      case 'newAttendance':
        if (typeof newAttendance === 'function') newAttendance();
        return;

      // === EMPLOYEE SELECTOR ===
      case 'showEmployeeSelector':
        if (typeof showEmployeeSelector === 'function') showEmployeeSelector();
        return;

      case 'closeEmployeeSelector':
        if (typeof closeEmployeeSelector === 'function') closeEmployeeSelector();
        return;

      case 'selectAllEmployees':
        if (typeof selectAllEmployees === 'function') selectAllEmployees();
        return;

      case 'deselectAllEmployees':
        if (typeof deselectAllEmployees === 'function') deselectAllEmployees();
        return;

      case 'confirmEmployeeSelection':
        if (typeof confirmEmployeeSelection === 'function') confirmEmployeeSelection();
        return;
    }

    // Try to call function if it exists (fallback)
    if (typeof window[action] === 'function') {
      window[action]();
    }
  }

  // Handle data-action buttons - kliknutí
  // DŮLEŽITÉ: Vyloučit INPUT pole - ty se zpracují pouze při change eventu
  document.addEventListener('click', (e) => {
    const target = e.target.closest('[data-action]');
    if (!target) return;

    // Přeskočit INPUT a TEXTAREA - tyto elementy se zpracují pouze při change
    if (target.tagName === 'INPUT' || target.tagName === 'TEXTAREA') return;

    zpracujDataAction(target);
  });

  // Handle data-action inputs - změna hodnoty (pro onchange migraci)
  document.addEventListener('change', (e) => {
    const target = e.target.closest('[data-action]');
    if (!target) return;
    zpracujDataAction(target);
  });

  // Handle data-action buttons - klávesnice (Enter/Space) pro přístupnost
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Enter' && e.key !== ' ') return;
    const target = e.target.closest('[data-action]');
    if (!target) return;
    // Jen pro elementy s role="button" (ne skutečné buttony, ty to mají automaticky)
    if (target.tagName !== 'BUTTON' && target.getAttribute('role') === 'button') {
      e.preventDefault();
      zpracujDataAction(target);
    }
  });

  // Handle data-navigate buttons
  document.addEventListener('click', (e) => {
    const navigate = e.target.closest('[data-navigate]')?.getAttribute('data-navigate');
    if (navigate) {
      if (typeof navigateTo === 'function') {
        navigateTo(navigate);
      } else {
        location.href = navigate;
      }
    }
  });

  // Handle data-onchange inputs
  document.addEventListener('change', (e) => {
    const target = e.target.closest('[data-onchange]');
    if (!target) return;
    
    const action = target.getAttribute('data-onchange');
    const value = target.getAttribute('data-onchange-value') || target.value;
    
    if (typeof window[action] === 'function') {
      window[action](value);
    }
  });
});