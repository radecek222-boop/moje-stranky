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
const API_URL = 'app/psa_data.php';
const CSRF_TOKEN = window.PSA_CSRF_TOKEN || '';

// Speciální zaměstnanci - vždy zobrazeni, nelze smazat
const PERMANENT_EMPLOYEE_IDS = [19, 20, 21, 22];  // Marek, Lenka, Radek, Prémie

// === INITIALIZATION ===
window.addEventListener('DOMContentLoaded', () => {
  logger.log('PSA Kalkulátor initialized');
  initializePeriod();
  loadData();
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
      if (await wgsConfirm(`Období ${MONTHS_CZ[newMonth]} ${newYear} již existuje. Chcete se na něj přepnout?`, 'Přepnout', 'Zrušit')) {
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
      // Lenka má paušální mzdu 8716 Kč
      salary = 8716;
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
function addEmployee() {
  // Použít nový employee selector s checkboxy
  showEmployeeSelector();
}

// === EMPLOYEE SELECTOR (checkbox overlay) ===
async function showEmployeeSelector() {
  logger.log('showEmployeeSelector started, allEmployeesDatabase.length =', allEmployeesDatabase.length);

  // Pokud je databáze prázdná, zkusit načíst data znovu
  if (allEmployeesDatabase.length === 0) {
    logger.log('allEmployeesDatabase is empty, trying to reload data...');
    try {
      await loadData();
      logger.log('After loadData, allEmployeesDatabase.length =', allEmployeesDatabase.length);
    } catch (err) {
      logger.error('Failed to reload data:', err);
    }
  }

  const currentIds = employees.map(e => e.id);
  const excludedTypes = ['special', 'special2', 'pausalni', 'premie_polozka'];

  // Všichni zaměstnanci z databáze (kromě speciálních a permanentních)
  const allAvailable = allEmployeesDatabase.filter(emp =>
    !PERMANENT_EMPLOYEE_IDS.includes(emp.id) &&
    !excludedTypes.includes(emp.type)
  );

  logger.log('Employee selector - allEmployeesDatabase:', allEmployeesDatabase.length, 'allAvailable:', allAvailable.length);

  if (allAvailable.length === 0) {
    wgsToast.info('Žádní zaměstnanci v databázi - zkontrolujte přihlášení');
    return;
  }

  const modal = document.createElement('div');
  modal.className = 'modal';
  modal.id = 'employeeSelectorModal';
  modal.innerHTML = `
    <div class="modal-content" style="max-width: 500px;">
      <div class="modal-header">
        <h2 class="modal-title">Vybrat zaměstnance</h2>
        <span class="close-modal" data-action="closeEmployeeSelector">&times;</span>
      </div>
      <div style="padding: 1rem 1.5rem; border-bottom: 1px solid var(--c-border); display: flex; gap: 1rem;">
        <button class="btn btn-sm" data-action="selectAllEmployees">Vybrat vše</button>
        <button class="btn btn-sm btn-secondary" data-action="deselectAllEmployees">Zrušit výběr</button>
      </div>
      <div style="padding: 1rem 1.5rem; max-height: 400px; overflow-y: auto;">
        ${allAvailable.map(emp => {
          const isAlreadyAdded = currentIds.includes(emp.id);
          return `
            <label style="display: flex; align-items: center; padding: 0.75rem; margin-bottom: 0.5rem; border: 1px solid var(--c-border); border-radius: 4px; cursor: pointer; ${isAlreadyAdded ? 'opacity: 0.5; background: var(--c-bg);' : ''}" ${isAlreadyAdded ? 'title="Už je v seznamu"' : ''}>
              <input type="checkbox"
                     name="selectedEmployee"
                     value="${emp.id}"
                     ${isAlreadyAdded ? 'disabled checked' : ''}
                     style="width: 18px; height: 18px; margin-right: 0.75rem; accent-color: var(--c-black);">
              <div style="flex: 1;">
                <div style="font-weight: 600;">${emp.name}</div>
                <div style="font-size: 0.8rem; color: var(--c-grey);">
                  ${emp.account ? emp.account + '/' + emp.bank : 'Bez účtu'}
                  ${emp.type === 'swift' ? ' (SWIFT)' : ''}
                </div>
              </div>
              ${isAlreadyAdded ? '<span style="font-size: 0.75rem; color: var(--c-grey);">v seznamu</span>' : ''}
            </label>
          `;
        }).join('')}
      </div>
      <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--c-border); display: flex; justify-content: space-between; align-items: center;">
        <span style="font-size: 0.85rem; color: var(--c-grey);">Vybráno: <strong id="selectedCount">0</strong></span>
        <div>
          <button class="btn btn-secondary" data-action="closeEmployeeSelector">Zrušit</button>
          <button class="btn" data-action="confirmEmployeeSelection" style="margin-left: 0.5rem;">Přidat vybrané</button>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(modal);
  modal.style.display = 'block';

  // Aktualizovat počet vybraných
  updateSelectedCount();

  // Event listener pro checkboxy
  modal.querySelectorAll('input[name="selectedEmployee"]').forEach(cb => {
    cb.addEventListener('change', updateSelectedCount);
  });
}

function updateSelectedCount() {
  const modal = document.getElementById('employeeSelectorModal');
  if (!modal) return;

  const checked = modal.querySelectorAll('input[name="selectedEmployee"]:checked:not(:disabled)').length;
  const countEl = modal.querySelector('#selectedCount');
  if (countEl) countEl.textContent = checked;
}

function selectAllEmployees() {
  const modal = document.getElementById('employeeSelectorModal');
  if (!modal) return;

  modal.querySelectorAll('input[name="selectedEmployee"]:not(:disabled)').forEach(cb => {
    cb.checked = true;
  });
  updateSelectedCount();
}

function deselectAllEmployees() {
  const modal = document.getElementById('employeeSelectorModal');
  if (!modal) return;

  modal.querySelectorAll('input[name="selectedEmployee"]:not(:disabled)').forEach(cb => {
    cb.checked = false;
  });
  updateSelectedCount();
}

function closeEmployeeSelector() {
  const modal = document.getElementById('employeeSelectorModal');
  if (modal) modal.remove();
}

async function confirmEmployeeSelection() {
  const modal = document.getElementById('employeeSelectorModal');
  if (!modal) return;

  const selectedIds = Array.from(modal.querySelectorAll('input[name="selectedEmployee"]:checked:not(:disabled)'))
    .map(cb => parseInt(cb.value));

  if (selectedIds.length === 0) {
    wgsToast.warning('Nevybrali jste žádné zaměstnance');
    return;
  }

  // Přidat vybrané zaměstnance
  selectedIds.forEach(id => {
    const emp = allEmployeesDatabase.find(e => e.id === id);
    if (emp && !employees.find(e => e.id === id)) {
      employees.push({
        ...emp,
        hours: 0,
        bonusAmount: 0,
        premieCastka: 0
      });
    }
  });

  closeEmployeeSelector();
  renderTable();
  updateStats();

  // Uložit na server
  try {
    saveToLocalStorage();
    await saveToServer();
    showSuccess(`Přidáno ${selectedIds.length} zaměstnanců`);
  } catch (error) {
    logger.error('Failed to save after adding employees:', error);
  }
}

// Přidat nového prázdného zaměstnance
function addNewBlankEmployee() {
  // Zavřít modal
  const modal = document.getElementById('addEmployeeModal');
  if (modal) modal.remove();

  // Vygenerovat dočasné ID (záporné, aby se nepletlo s existujícími)
  const tempId = -(Date.now());

  // Přidat prázdného zaměstnance
  employees.push({
    id: tempId,
    name: '',
    hours: 0,
    account: '',
    bank: '',
    type: 'standard',
    active: true,
    isNew: true  // Označit jako nového (ještě není v databázi)
  });

  renderTable();
  updateStats();

  // Focus na pole jméno
  setTimeout(() => {
    const lastRow = document.querySelector(`[data-index="${employees.length - 1}"][data-field="name"]`);
    if (lastRow) lastRow.focus();
  }, 100);

  showSuccess('Vyplňte údaje nového zaměstnance a uložte do databáze');
}

// Uložit nového zaměstnance do databáze
async function saveEmployeeToDatabase(index) {
  const emp = employees[index];

  if (!emp.name || emp.name.trim() === '') {
    wgsToast.error('Zadejte jméno zaměstnance');
    return;
  }

  // Vygenerovat nové ID
  const maxId = Math.max(...allEmployeesDatabase.map(e => e.id || 0), 0);
  const newId = maxId + 1;

  // Aktualizovat zaměstnance
  emp.id = newId;
  emp.isNew = false;
  emp.name = emp.name.trim();

  // Přidat do databáze
  allEmployeesDatabase.push({
    id: newId,
    name: emp.name,
    account: emp.account || '',
    bank: emp.bank || '',
    type: 'standard',
    active: true
  });

  renderTable();

  // Uložit na server
  try {
    await saveToServer();
    showSuccess(`Zaměstnanec ${emp.name} uložen do databáze`);
    logger.log('New employee saved to database:', emp.name);
  } catch (error) {
    logger.error('Failed to save new employee:', error);
    wgsToast.error('Chyba při ukládání do databáze');
  }
}

// Uložit změny jednotlivého zaměstnance (např. číslo účtu)
async function saveEmployeeChanges(index) {
  const emp = employees[index];
  if (!emp) {
    wgsToast.error('Zaměstnanec nenalezen');
    return;
  }

  // Aktualizovat v databázi zaměstnanců
  const dbIndex = allEmployeesDatabase.findIndex(e => e.id === emp.id);
  if (dbIndex !== -1) {
    allEmployeesDatabase[dbIndex].name = emp.name;
    allEmployeesDatabase[dbIndex].account = emp.account || '';
    allEmployeesDatabase[dbIndex].bank = emp.bank || '';
    allEmployeesDatabase[dbIndex].type = emp.type || 'standard';
  }

  // Uložit na server
  try {
    saveToLocalStorage();
    await saveToServer();
    showSuccess(`Změny u ${emp.name} uloženy`);
    logger.log('Employee changes saved:', emp.name);
  } catch (error) {
    logger.error('Failed to save employee changes:', error);
    wgsToast.error('Chyba při ukládání změn');
  }
}

async function confirmAddEmployee() {
  const select = document.getElementById('selectEmployeeToAdd');
  const selectedId = parseInt(select.value);

  if (!selectedId) {
    wgsToast.error('Vyberte zaměstnance');
    return;
  }

  // Najít zaměstnance v databázi
  const empToAdd = allEmployeesDatabase.find(e => e.id === selectedId);
  if (!empToAdd) {
    wgsToast.error('Zaměstnanec nenalezen');
    return;
  }

  // Přidat do seznamu s nulovými hodinami
  employees.push({
    ...empToAdd,
    hours: 0,
    bonusAmount: 0,
    premieCastka: 0
  });

  // Zavřít modal
  document.getElementById('addEmployeeModal').remove();

  renderTable();
  updateStats();

  // Automaticky uložit na server
  try {
    saveToLocalStorage();
    await saveToServer();
    logger.log('Employee added and saved:', empToAdd.name);
    showSuccess(`Zaměstnanec ${empToAdd.name} přidán`);
  } catch (error) {
    logger.error('Failed to save after adding employee:', error);
  }
}

async function updateEmployee(index, field, value, needConfirm = false) {
  if (field === 'name' && needConfirm) {
    const oldName = employees[index].name;
    if (oldName !== value && !await wgsConfirm(`Opravdu chcete změnit jméno z "${oldName}" na "${value}"?`, 'Změnit', 'Zrušit')) {
      renderTable();
      return;
    }
  }

  if (field === 'hours' || field === 'bonusAmount' || field === 'premieCastka') {
    employees[index][field] = parseInt(value) || 0;
  } else if (field === 'bank') {
    employees[index][field] = formatBankCode(value);
  } else {
    employees[index][field] = value;
  }

  renderTable();
  updateStats();

  // Automaticky uložit na server
  try {
    saveToLocalStorage();
    await saveToServer();
    logger.log('Employee updated and saved');
  } catch (error) {
    logger.error('Failed to save after updating employee:', error);
  }
}

async function removeEmployee(index) {
  const emp = employees[index];

  // Permanentní zaměstnance nelze odebrat
  if (PERMANENT_EMPLOYEE_IDS.includes(emp.id)) {
    wgsToast.error('Tento zaměstnanec je permanentní a nelze ho odebrat');
    return;
  }

  if (await wgsConfirm(`Opravdu chcete odebrat zaměstnance ${emp.name} z tohoto období?`, 'Odebrat', 'Zrušit')) {
    employees.splice(index, 1);
    renderTable();
    updateStats();

    // Automaticky uložit na server
    try {
      saveToLocalStorage();
      await saveToServer();
      logger.log('Employee removed from period:', emp.name);
      showSuccess(`${emp.name} odebrán z období`);
    } catch (error) {
      logger.error('Failed to save after removing employee:', error);
    }
  }
}

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
      // Lenka má paušální mzdu 8716 Kč (nepřepisovatelná)
      salary = 8716;
      invoice = 0;
      displayInfo = '<span class="employee-type-badge" style="background: var(--c-info);">Paušál 8716 Kč</span>';
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
          ${(isLenka || emp.type === 'special' || emp.type === 'special2') ?
            '<span style="color: var(--c-grey);">–</span>' :
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
        <td class="text-center" style="white-space: nowrap;">
          <button class="btn btn-sm" style="margin-right: 0.25rem;" data-action="saveEmployeeChanges" data-index="${index}" title="Uložit změny">Uložit</button>
          ${emp.isNew ?
            `<button class="btn btn-sm" style="background: var(--c-success); color: white; margin-right: 0.25rem;" data-action="saveEmployeeToDatabase" data-index="${index}" title="Uložit do databáze">DB</button>` :
            `<button class="btn btn-sm qr-btn" style="background: var(--c-info); color: white; margin-right: 0.25rem;" data-action="generateSingleEmployeeQR" data-index="${index}" title="Generovat QR platbu">QR</button>`
          }
          ${PERMANENT_EMPLOYEE_IDS.includes(emp.id) ? '' :
            `<button class="btn btn-danger btn-sm" data-action="removeEmployee" data-index="${index}" title="Odebrat z období">×</button>`
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
      // Lenka má paušální mzdu 8716 Kč
      salary = 8716;
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
function ensureQrLibraryLoaded() {
  // Uz je k dispozici
  if (window.QRCode && typeof window.QRCode === 'function') {
    return Promise.resolve(window.QRCode);
  }

  if (qrLibraryPromise) return qrLibraryPromise;

  qrLibraryPromise = new Promise((resolve, reject) => {
    const src = 'assets/js/qrcode.min.js';
    const existing =
      document.querySelector('script[data-qr-lib]') ||
      Array.from(document.scripts).find(s => (s.src || '').includes(src));

    const fail = (msg) => reject(new Error(msg));

    const tryResolve = () => {
      if (window.QRCode && typeof window.QRCode === 'function') {
        resolve(window.QRCode);
        return true;
      }
      return false;
    };

    // Timeout proti "cekani navěky"
    const t = setTimeout(() => {
      if (!tryResolve()) fail('Timeout načítání knihovny QR kódů (8s)');
    }, 8000);

    const cleanup = () => clearTimeout(t);

    const attachWaiters = (scriptEl) => {
      // kdyby load uz probehl driv nez pridame listener
      queueMicrotask(() => {
        if (tryResolve()) {
          cleanup();
        } else if (scriptEl.dataset.qrLoaded === '1') {
          cleanup();
          fail('QR script načten, ale QRCode API chybí');
        }
      });

      scriptEl.addEventListener('load', () => {
        scriptEl.dataset.qrLoaded = '1';
        if (tryResolve()) {
          cleanup();
        } else {
          cleanup();
          fail('Knihovna QR kódu se načetla, ale neobsahuje očekávané API');
        }
      }, { once: true });

      scriptEl.addEventListener('error', () => {
        cleanup();
        fail('Nepodařilo se načíst knihovnu QR kódů');
      }, { once: true });
    };

    if (existing) {
      // Pokud uz existuje a nahodou uz je "oznaceny jako loaded", rovnou vyhodnot stav
      if (tryResolve()) {
        cleanup();
        return;
      }
      if (existing.dataset.qrLoaded === '1') {
        cleanup();
        fail('QR script načten, ale QRCode API chybí');
        return;
      }
      attachWaiters(existing);
      return;
    }

    // Vytvorit script
    const script = document.createElement('script');
    script.src = src;
    script.defer = true;
    script.dataset.qrLib = '1';
    attachWaiters(script);
    document.head.appendChild(script);
  }).catch((err) => {
    qrLibraryPromise = null; // Reset pro retry
    throw err;
  });

  return qrLibraryPromise;
}

function buildSpaydPayload(data) {
  const account = normalizeAccount(data.account);
  const bank = formatBankCode(data.bank);
  const amount = Number(data.amount);

  if (!account || !bank) {
    throw new Error('Chybí číslo účtu nebo kód banky');
  }

  if (!Number.isFinite(amount) || amount <= 0) {
    throw new Error('Neplatná částka');
  }

  // Konverze na IBAN format pro SPAYD
  const iban = convertToIBAN(account, bank);

  // Minimalni SPAYD - pouze ucet, castka, mena
  const spayd = `SPD*1.0*ACC:${iban}*AM:${amount.toFixed(2)}*CC:CZK`;

  console.log('SPAYD delka:', spayd.length, 'text:', spayd);

  return spayd;
}

// Maximalni delky QR textu podle urovne korekce (bezpecne limity)
const QR_MAX_LENGTH = {
  L: 800,   // Low correction - vice dat, mene odolnosti
  M: 600,   // Medium
  Q: 450,   // Quartile
  H: 350    // High correction - mene dat, vice odolnosti
};

async function renderQrCode(qrElement, qrText, size, contextLabel = '') {
  await ensureQrLibraryLoaded();

  if (!window.QRCode || typeof window.QRCode !== 'function') {
    throw new Error('Knihovna pro QR kódy není načtena');
  }

  // Validace delky QR textu
  const maxLen = QR_MAX_LENGTH.L; // Pouzivame Level L
  if (!qrText || typeof qrText !== 'string') {
    throw new Error('QR text je prázdný nebo neplatný');
  }
  if (qrText.length > maxLen) {
    console.error(`QR text příliš dlouhý: ${qrText.length} > ${maxLen}`, qrText);
    throw new Error(`QR data příliš dlouhá (${qrText.length} znaků, max ${maxLen})`);
  }

  return new Promise((resolve, reject) => {
    try {
      // Vyčistit element
      qrElement.innerHTML = '';

      // qrcodejs2 API - vytvoří QR kód přímo do elementu
      new QRCode(qrElement, {
        text: qrText,
        width: size,
        height: size,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.L  // Nizsi uroven korekce = vice dat
      });

      console.log(`QR code generated${contextLabel ? ' for ' + contextLabel : ''} (${qrText.length} chars)`);
      resolve();
    } catch (err) {
      console.error(`Failed to generate QR code${contextLabel ? ' for ' + contextLabel : ''}:`, err);
      qrElement.innerHTML = `<div style="color: red; padding: 20px;">${err?.message || 'Chyba QR'}</div>`;
      reject(err);
    }
  });
}

// === NOTIFICATIONS ===
function showSuccess(message) {
  wgsToast.success(message);
}

function showError(message) {
  wgsToast.error(message);
}

// === EXPORT ===
function exportToExcel() {
  let csv = '\uFEFF';
  const periodText = document.getElementById('periodDisplay').textContent;

  csv += `PSA KALKULÁTOR - ${periodText}\n\n`;
  csv += 'Jméno;Hodiny;Výplata (Kč);Faktura (Kč);Číslo účtu;Kód banky;Typ\n';

  let totalOtherHours = 0;
  employees.forEach(emp => {
    if (emp.type !== 'special' && emp.type !== 'special2' && emp.type !== 'bonus_girls') {
      totalOtherHours += emp.hours || 0;
    }
  });

  employees.forEach(emp => {
    let salary, invoice;
    const isLenka = emp.name === 'Lenka' || emp.name.includes('Lenka');

    if (isLenka) {
      salary = 8716;
      invoice = 0;
    } else if (emp.type === 'bonus_girls') {
      salary = emp.bonusAmount || 0;
      invoice = 0;
    } else if (emp.type === 'pausalni' && emp.pausalni) {
      salary = emp.hours * salaryRate;
      invoice = Math.min(emp.hours * invoiceRate, emp.pausalni.rate / 12 - emp.pausalni.tax);
    } else if (emp.type === 'special' || emp.type === 'special2') {
      salary = totalOtherHours * 20;
      invoice = 0;
    } else {
      salary = emp.hours * salaryRate;
      invoice = emp.hours * invoiceRate;
    }

    const displayValue = emp.type === 'bonus_girls' ? `${emp.bonusAmount || 0} Kč` : (emp.hours || 0);
    csv += `${emp.name};${displayValue};${salary};${invoice};${emp.account || ''};${emp.bank || ''};${emp.type || 'standard'}\n`;
  });

  const totalHours = employees.reduce((sum, emp) => {
    if (emp.type === 'special' || emp.type === 'special2' || emp.type === 'bonus_girls') return sum;
    return sum + (emp.hours || 0);
  }, 0);

  const totalSalary = employees.reduce((sum, emp) => {
    const isLenka = emp.name === 'Lenka' || emp.name.includes('Lenka');
    if (isLenka) return sum + 8716;
    if (emp.type === 'bonus_girls') return sum + (emp.bonusAmount || 0);
    if (emp.type === 'special' || emp.type === 'special2') return sum + totalOtherHours * 20;
    return sum + emp.hours * salaryRate;
  }, 0);

  const totalInvoice = employees.reduce((sum, emp) => {
    if (emp.type === 'special' || emp.type === 'special2' || emp.type === 'bonus_girls') return sum;
    const isLenka = emp.name === 'Lenka' || emp.name.includes('Lenka');
    if (isLenka) return sum; // Lenka nemá fakturu
    return sum + emp.hours * invoiceRate;
  }, 0);

  csv += `\nCELKEM;${totalHours};${totalSalary};${totalInvoice};;;`;
  csv += `\n\nSazba výplaty: ${salaryRate} Kč/hodina`;
  csv += `\nSazba fakturace: ${invoiceRate} Kč/hodina`;
  csv += `\nZisk: ${totalInvoice - totalSalary} Kč`;

  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = `PSA_${currentPeriod.year}_${String(currentPeriod.month).padStart(2, '0')}.csv`;
  link.click();
  URL.revokeObjectURL(url);
}

function printReport() {
  window.print();
}

async function clearAll() {
  if (await wgsConfirm('Opravdu chcete vynulovat hodiny pro toto období?', 'Vynulovat', 'Zrušit')) {
    // Pouze vynulovat hodiny a bonusy, NE mazat zaměstnance!
    employees.forEach(emp => {
      emp.hours = 0;
      if (emp.type === 'bonus_girls') emp.bonusAmount = 0;
      if (emp.type === 'premie_polozka') emp.premieCastka = 0;
    });

    renderTable();
    updateStats();

    // Automaticky uložit na server
    try {
      saveToLocalStorage();
      await saveToServer();
      logger.log('Hours cleared for current period');
      showSuccess('Hodiny vynulovány pro aktuální období');
    } catch (error) {
      logger.error('Failed to save after clearing hours:', error);
    }
  }
}

// === SYNCHRONIZACE VSTUPŮ ===
// Synchronizovat data z input polí před generováním QR
function synchronizovatVstupy() {
  const inputs = document.querySelectorAll('[data-action="updateEmployeeField"]');
  inputs.forEach(input => {
    const index = parseInt(input.getAttribute('data-index'));
    const field = input.getAttribute('data-field');
    if (!isNaN(index) && field && employees[index]) {
      if (field === 'hours' || field === 'bonusAmount' || field === 'premieCastka') {
        employees[index][field] = parseInt(input.value) || 0;
      } else if (field === 'bank') {
        employees[index][field] = formatBankCode(input.value);
      } else {
        employees[index][field] = input.value;
      }
    }
  });
  // Aktualizovat statistiky
  updateStats();
}

// === QR PAYMENTS ===
function generatePaymentQR() {
  // Synchronizovat vstupy před generováním QR
  synchronizovatVstupy();
  const modal = document.getElementById('qrModal');
  const container = document.getElementById('qrCodesContainer');
  const summaryDiv = document.getElementById('paymentSummary');

  container.innerHTML = '';
  summaryDiv.innerHTML = '';

  // Zobrazit souhrn pro hromadné QR
  const summarySection = summaryDiv.closest('.payment-summary');
  if (summarySection) {
    summarySection.style.display = '';
  }

  // Odstranit třídu pro menší modal
  modal.classList.remove('single-qr-mode');

  let totalPayments = 0;
  let paymentData = [];
  let swiftPayments = [];

  // Calculate total hours for bonuses
  let totalOtherHours = 0;
  employees.forEach(emp => {
    if (emp.type !== 'special' && emp.type !== 'special2' && emp.type !== 'bonus_girls') {
      totalOtherHours += emp.hours || 0;
    }
  });

  const bonusPerSpecial = totalOtherHours * 20;

  // Process all employees
  employees.forEach(emp => {
    const isLenka = emp.name === 'Lenka' || emp.name.includes('Lenka');

    let amount = 0;

    // Výpočet částky podle typu
    if (isLenka) {
      amount = 8716;  // Paušální mzda pro Lenku
    } else if (emp.type === 'bonus_girls') {
      amount = emp.bonusAmount || 0;
    } else if (emp.type === 'special' || emp.type === 'special2') {
      amount = bonusPerSpecial;
    } else if (emp.type === 'premie_polozka') {
      amount = emp.premieCastka || 0;
    } else {
      amount = (emp.hours || 0) * salaryRate;
    }

    // Přeskočit pouze pokud je částka 0
    if (amount <= 0) return;

    // Process by payment type
    if (emp.type === 'swift' && emp.swiftData) {
      swiftPayments.push({
        name: emp.name,
        amount: amount,
        swiftData: emp.swiftData
      });
    } else {
      // Přidat všechny zaměstnance s částkou > 0
      paymentData.push({
        name: emp.name,
        amount: amount,
        account: emp.account || '',
        bank: emp.bank ? formatBankCode(emp.bank) : '',
        missingAccount: !emp.account || !emp.bank
      });
    }

    totalPayments += amount;
  });

  // Summary - seznam všech zaměstnanců
  let summaryRows = '';
  let visibleTotal = 0;

  // Domácí platby
  paymentData.forEach(payment => {
    visibleTotal += payment.amount;
    summaryRows += `<div class="summary-row"><span>${payment.name}</span><span>${formatCurrency(payment.amount)}</span></div>`;
  });

  // SWIFT platby
  swiftPayments.forEach(payment => {
    visibleTotal += payment.amount;
    summaryRows += `<div class="summary-row"><span>${payment.name} (SWIFT)</span><span>${formatCurrency(payment.amount)}</span></div>`;
  });

  summaryDiv.innerHTML = `
    ${summaryRows}
    <div class="summary-row summary-total">
      <span>CELKEM:</span>
      <span>${formatCurrency(visibleTotal)}</span>
    </div>
  `;

  // Vytvoříme jeden grid pro všechny platby
  const paymentsGrid = document.createElement('div');
  paymentsGrid.className = 'qr-grid';
  container.appendChild(paymentsGrid);

  // Domestic payments with QR codes
  paymentData.forEach((payment, index) => {
    const qrItem = document.createElement('div');
    qrItem.className = 'qr-item';

    // Pokud chybí účet, zobrazit prázdný čtverec místo QR
    const hasAccount = !payment.missingAccount;
    const accountText = hasAccount ? `${payment.account}/${payment.bank}` : 'Chybí účet';

    qrItem.innerHTML = `
      <div class="qr-employee-name">${payment.name}</div>
      <div class="qr-amount">${formatCurrency(payment.amount)}</div>
      <div class="qr-account">${accountText}</div>
      <div class="qr-code-wrapper" id="qr-${index}"></div>
      ${hasAccount ? `
        <div class="qr-item-buttons">
          <button class="btn btn-sm" data-action="downloadQR" data-qrid="qr-${index}" data-name="${payment.name}">Stáhnout</button>
          <button class="btn btn-sm btn-secondary" data-action="shareQR" data-qrid="qr-${index}" data-name="${payment.name}" data-amount="${payment.amount}">Sdílet</button>
        </div>
      ` : ''}
    `;

    paymentsGrid.appendChild(qrItem);

    // Generate QR code nebo prázdný bílý čtverec
    setTimeout(async () => {
      const qrElement = document.getElementById(`qr-${index}`);
      if (!qrElement) {
        logger.error(`QR element not found: qr-${index}`);
        return;
      }

      // Pokud chybí účet, zobrazit prázdný bílý čtverec
      if (!hasAccount) {
        qrElement.innerHTML = '<div style="width: 160px; height: 160px; background: white; border: 1px solid #ddd; margin: 0 auto;"></div>';
        return;
      }

      qrElement.innerHTML = '';

      let qrText;
      try {
        qrText = generateCzechPaymentString({
          account: payment.account,
          bank: payment.bank,
          amount: payment.amount,
          vs: currentPeriod.year * 100 + currentPeriod.month,
          message: `Výplata ${payment.name} ${currentPeriod.month}/${currentPeriod.year}`
        });
      } catch (err) {
        qrElement.innerHTML = `<div style="color: #999; font-size: 0.8rem;">${err.message}</div>`;
        return;
      }

      logger.log(`Generating QR for ${payment.name}:`, qrText);

      try {
        await renderQrCode(qrElement, qrText, 160, payment.name);
      } catch (error) {
        logger.error(`Failed to generate QR code for ${payment.name}:`, error);
        qrElement.innerHTML = '<div style="color: #999; font-size: 0.8rem;">Chyba QR</div>';
      }
    }, 100 * index);
  });

  // SWIFT payments
  swiftPayments.forEach((payment, index) => {
    const swiftItem = document.createElement('div');
    swiftItem.className = 'qr-item swift-item';

    swiftItem.innerHTML = `
      <div class="qr-employee-name">${payment.name}</div>
      <div class="qr-amount">${formatCurrency(payment.amount)}</div>
      <div class="swift-label">SWIFT</div>
      <div class="swift-details">
        <div><strong>IBAN:</strong> ${payment.swiftData.iban}</div>
        <div><strong>SWIFT:</strong> ${payment.swiftData.swift}</div>
        <div class="swift-our">Poplatky: OUR</div>
      </div>
      <div class="qr-item-buttons">
        <button class="btn btn-sm" data-action="copySWIFTDetails" data-name="${payment.name}" data-iban="${payment.swiftData.iban}" data-swift="${payment.swiftData.swift}" data-amount="${payment.amount}">Kopírovat</button>
      </div>
    `;

    paymentsGrid.appendChild(swiftItem);
  });

  modal.classList.remove('hidden');
}

function generateCzechPaymentString(data) {
  return buildSpaydPayload(data);
}

function copySWIFTDetails(name, iban, swift, amount) {
  const text = `SWIFT platba - ${name}
IBAN: ${iban}
SWIFT/BIC: ${swift}
Částka: ${formatCurrency(amount)}
Poplatky: OUR (hradí odesílatel)
Zpráva: Výplata ${name} ${currentPeriod.month}/${currentPeriod.year}`;

  navigator.clipboard.writeText(text).then(() => {
    wgsToast.success('SWIFT údaje byly zkopírovány do schránky');
  }).catch(err => {
    logger.error('Chyba při kopírování:', err);
    wgsToast.error('Nepodařilo se zkopírovat údaje');
  });
}

function downloadQR(qrId, employeeName) {
  // qrcodejs2 vytváří img element (nebo canvas jako fallback)
  const qrImg = document.querySelector(`#${qrId} img`);
  const qrCanvas = document.querySelector(`#${qrId} canvas`);

  const link = document.createElement('a');
  link.download = `QR_platba_${employeeName}_${currentPeriod.month}_${currentPeriod.year}.png`;

  if (qrImg && qrImg.src) {
    link.href = qrImg.src;
    link.click();
  } else if (qrCanvas) {
    link.href = qrCanvas.toDataURL();
    link.click();
  } else {
    wgsToast.error('QR kód nenalezen');
  }
}

async function shareQRCode(qrId, employeeName, amount) {
  // Získat QR kód jako blob
  const qrImg = document.querySelector(`#${qrId} img`);
  const qrCanvas = document.querySelector(`#${qrId} canvas`);

  let imageUrl;
  if (qrImg && qrImg.src) {
    imageUrl = qrImg.src;
  } else if (qrCanvas) {
    imageUrl = qrCanvas.toDataURL('image/png');
  } else {
    wgsToast.error('QR kód nenalezen');
    return;
  }

  // Konverze data URL na blob
  const response = await fetch(imageUrl);
  const blob = await response.blob();

  const shareData = {
    title: `QR platba - ${employeeName}`,
    text: `Platba pro ${employeeName}: ${amount} Kč (${currentPeriod.month}/${currentPeriod.year})`,
    files: [new File([blob], `QR_platba_${employeeName}.png`, { type: 'image/png' })]
  };

  // Zkontrolovat podporu Web Share API
  if (navigator.canShare && navigator.canShare(shareData)) {
    try {
      await navigator.share(shareData);
      wgsToast.success('QR kód byl sdílen');
    } catch (err) {
      if (err.name !== 'AbortError') {
        console.error('Chyba při sdílení:', err);
        // Fallback: zkopírovat do schránky
        fallbackCopyQR(imageUrl, employeeName, amount);
      }
    }
  } else {
    // Fallback pro prohlížeče bez podpory sdílení
    fallbackCopyQR(imageUrl, employeeName, amount);
  }
}

async function fallbackCopyQR(imageUrl, employeeName, amount) {
  try {
    // Zkusit zkopírovat obrázek do schránky
    const response = await fetch(imageUrl);
    const blob = await response.blob();
    await navigator.clipboard.write([
      new ClipboardItem({ 'image/png': blob })
    ]);
    wgsToast.success('QR kód byl zkopírován do schránky');
  } catch (err) {
    // Poslední fallback - stáhnout soubor
    console.warn('Nelze zkopírovat do schránky, stahuji soubor:', err);
    downloadQR(imageUrl.split('#')[0].split('?')[0], employeeName);
  }
}

function closeQRModal() {
  document.getElementById('qrModal').classList.add('hidden');
}

// === SINGLE EMPLOYEE QR GENERATION ===
function generateSingleEmployeeQR(index) {
  console.log('generateSingleEmployeeQR called with index:', index);

  // Synchronizovat vstupy před generováním QR
  synchronizovatVstupy();

  const emp = employees[index];
  if (!emp) {
    wgsToast.error('Zaměstnanec nenalezen');
    return;
  }

  const modal = document.getElementById('qrModal');
  const container = document.getElementById('qrCodesContainer');
  const summaryDiv = document.getElementById('paymentSummary');

  // Guard - kontrola existence elementů
  if (!modal || !container || !summaryDiv) {
    console.error('QR Modal: chybí elementy', { modal, container, summaryDiv });
    wgsToast.error('Chybí QR modal v HTML (qrModal/qrCodesContainer/paymentSummary)');
    return;
  }

  container.innerHTML = '';
  summaryDiv.innerHTML = '';

  // Skrýt souhrn pro jednotlivý QR
  const summarySection = summaryDiv.closest('.payment-summary');
  if (summarySection) {
    summarySection.style.display = 'none';
  }

  // Přidat třídu pro menší modal (jednotlivý QR)
  modal.classList.add('single-qr-mode');

  // Calculate total hours for bonus calculation
  let totalOtherHours = 0;
  employees.forEach(e => {
    if (e.type !== 'special' && e.type !== 'special2' && e.type !== 'bonus_girls') {
      totalOtherHours += e.hours || 0;
    }
  });

  let amount = 0;
  let isLenka = emp.name === 'Lenka' || emp.name.includes('Lenka');

  // Calculate amount
  if (isLenka) {
    amount = 8716;  // Paušální mzda pro Lenku
  } else if (emp.type === 'bonus_girls') {
    amount = emp.bonusAmount || 0;  // Manuální bonus pro holky
  } else if (emp.type === 'pausalni' && emp.pausalni) {
    amount = emp.hours * salaryRate;
  } else if (emp.type === 'special' || emp.type === 'special2') {
    amount = totalOtherHours * 20;
  } else if (emp.type === 'premie_polozka') {
    amount = emp.premieCastka || 0;
  } else {
    amount = emp.hours * salaryRate;
  }

  // Check if payment is possible
  if (amount <= 0) {
    wgsToast.warning('Částka k výplatě je 0 Kč. Zadejte prosím hodiny nebo nastavte mzdu.');
    return;
  }

  // For SWIFT payments
  if (emp.type === 'swift' && emp.swiftData) {
    const swiftItem = document.createElement('div');
    swiftItem.className = 'qr-item swift-item';

    swiftItem.innerHTML = `
      <div class="qr-employee-name">${emp.name}</div>
      <div class="qr-amount">${formatCurrency(amount)}</div>
      <div class="swift-label">SWIFT</div>
      <div class="swift-details">
        <div><strong>IBAN:</strong> ${emp.swiftData.iban}</div>
        <div><strong>SWIFT:</strong> ${emp.swiftData.swift}</div>
        <div class="swift-our">Poplatky: OUR</div>
      </div>
      <div class="qr-item-buttons">
        <button class="btn btn-sm" data-action="copySWIFTDetails" data-name="${emp.name}" data-iban="${emp.swiftData.iban}" data-swift="${emp.swiftData.swift}" data-amount="${amount}">Kopírovat</button>
      </div>
    `;

    container.appendChild(swiftItem);
    modal.classList.remove('hidden');
    return;
  }

  // For domestic payments - check account and bank
  if (!emp.account || !emp.bank) {
    wgsToast.warning('Prosím zadejte číslo účtu a kód banky pro ' + emp.name);
    return;
  }

  // Generate unique QR element ID (future-proof pro více QR najednou)
  const qrId = `qr-single-${Date.now()}-${index}`;

  // Generate QR code
  const qrItem = document.createElement('div');
  qrItem.className = 'qr-item';

  qrItem.innerHTML = `
    <div class="qr-employee-name">${emp.name}</div>
    <div class="qr-amount">${formatCurrency(amount)}</div>
    ${isLenka ? '<div style="font-size: 0.75rem; color: var(--c-info);">Paušální mzda</div>' : ''}
    <div class="qr-account">${emp.account}/${formatBankCode(emp.bank)}</div>
    <div class="qr-code-wrapper" id="${qrId}"></div>
    <div class="qr-item-buttons">
      <button class="btn btn-sm" data-action="downloadQR" data-qrid="${qrId}" data-name="${emp.name}">
        Stáhnout
      </button>
      <button class="btn btn-sm btn-secondary" data-action="shareQR" data-qrid="${qrId}" data-name="${emp.name}" data-amount="${amount}">
        Sdílet
      </button>
    </div>
  `;

  container.appendChild(qrItem);

  // Generate QR code asynchronně
  setTimeout(async () => {
    const qrElement = document.getElementById(qrId);
    if (!qrElement) {
      console.error('QR element not found:', qrId);
      wgsToast.error('QR element nenalezen v DOM');
      return;
    }

    // Vyčistit element před generováním
    qrElement.innerHTML = '';

    let qrText;
    try {
      qrText = generateCzechPaymentString({
        account: emp.account,
        bank: formatBankCode(emp.bank),
        amount: amount,
        vs: currentPeriod.year * 100 + currentPeriod.month,
        message: `Výplata ${emp.name} ${currentPeriod.month}/${currentPeriod.year}`
      });
    } catch (err) {
      console.error('QR generateCzechPaymentString failed:', err);
      qrElement.innerHTML = `<div style="color: red; padding: 20px;">${err.message}</div>`;
      return;
    }

    console.log(`Generating QR for ${emp.name}:`, qrText);

    try {
      await renderQrCode(qrElement, qrText, 220, emp.name);
    } catch (error) {
      console.error(`QR render failed for ${emp.name}:`, error);
      qrElement.innerHTML = `<div style="color: red; padding: 20px;">${error?.message || 'Chyba QR'}</div>`;
    }
  }, 100);

  modal.classList.remove('hidden');
}

// Close modal on outside click
window.onclick = function(event) {
  const modal = document.getElementById('qrModal');
  if (event.target == modal) {
    modal.classList.add('hidden');
  }
}

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