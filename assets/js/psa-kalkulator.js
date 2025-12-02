/**
 * PSA Kalkulátor - JavaScript
 * WGS Payroll Calculator
 */

// === GLOBAL VARIABLES ===
let employees = [];
let salaryRate = 150;
let invoiceRate = 250;
let currentPeriod = { month: 11, year: 2025 };
const API_URL = 'app/psa_data.php';
const CSRF_TOKEN = window.PSA_CSRF_TOKEN || '';

// === INITIALIZATION ===
window.addEventListener('DOMContentLoaded', () => {
  logger.log('PSA Kalkulátor initialized');
  initializePeriod();
  loadData();
});

// === PERIOD MANAGEMENT ===
function initializePeriod() {
  const now = new Date();
  currentPeriod.month = now.getMonth() + 1;
  currentPeriod.year = now.getFullYear();

  const monthSelect = document.getElementById('monthSelect');
  const yearSelect = document.getElementById('yearSelect');

  if (monthSelect) {
    monthSelect.value = currentPeriod.month;
  }
  if (yearSelect) {
    yearSelect.value = currentPeriod.year;
  }

  updatePeriodDisplay();
}

function updatePeriod() {
  currentPeriod.month = parseInt(document.getElementById('monthSelect').value);
  currentPeriod.year = parseInt(document.getElementById('yearSelect').value);
  updatePeriodDisplay();

  // Load data for the selected period
  const periodKey = `${currentPeriod.year}-${String(currentPeriod.month).padStart(2, '0')}`;
  loadData(periodKey);
}

function updatePeriodDisplay() {
  const months = ['', 'Leden', 'Únor', 'Březen', 'Duben', 'Květen', 'Červen',
                  'Červenec', 'Srpen', 'Září', 'Říjen', 'Listopad', 'Prosinec'];
  const periodText = `${months[currentPeriod.month]} ${currentPeriod.year}`;
  document.getElementById('periodDisplay').textContent = periodText;
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

    // Load configuration
    if (data.config) {
      salaryRate = data.config.salaryRate || 150;
      invoiceRate = data.config.invoiceRate || 250;
      document.getElementById('salaryRate').value = salaryRate;
      document.getElementById('invoiceRate').value = invoiceRate;
    }

    // Load employees for specific period or current data
    if (period && data.periods && data.periods[period]) {
      // Load historical period data
      const periodData = data.periods[period];
      employees = data.employees.map(emp => {
        const periodEmp = periodData.employees.find(pe => pe.id === emp.id);
        return {
          ...emp,
          bank: formatBankCode(emp.bank),
          hours: periodEmp ? (periodEmp.hours || 0) : 0,
          bonusAmount: emp.bonusAmount || 0
        };
      });
      logger.log(`Loaded period ${period} with ${employees.length} employees`);
    } else {
      // Load current data
      if (data.employees) {
        employees = data.employees.map(emp => ({
          ...emp,
          bank: formatBankCode(emp.bank),
          hours: emp.hours || 0,
          bonusAmount: emp.bonusAmount || 0
        }));
      }
      logger.log(`Loaded ${employees.length} employees`);
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
  const data = {
    config: {
      salaryRate: salaryRate,
      invoiceRate: invoiceRate,
      company: 'White Glove Service',
      currency: 'CZK'
    },
    employees: employees
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

    logger.log('Data saved to server successfully', result);
    return result;
  } catch (error) {
    logger.error('Server save failed:', error);
    throw error;
  }
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
        document.getElementById('monthSelect').value = currentPeriod.month;
        document.getElementById('yearSelect').value = currentPeriod.year;
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
async function addEmployee() {
  const name = prompt('Zadejte jméno nového zaměstnance:');
  if (!name || name.trim() === '') return;

  const newId = employees.length > 0 ? Math.max(...employees.map(e => e.id || 0)) + 1 : 1;

  employees.push({
    id: newId,
    name: name.trim(),
    hours: 0,
    bonusAmount: 0,
    account: '',
    bank: '',
    type: 'standard',
    active: true
  });

  renderTable();
  updateStats();

  // Automaticky uložit na server
  try {
    saveToLocalStorage();
    await saveToServer();
    logger.log('Employee added and saved');
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

  if (field === 'hours' || field === 'bonusAmount') {
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
  if (await wgsConfirm(`Opravdu chcete odstranit zaměstnance ${employees[index].name}?`, 'Odstranit', 'Zrušit')) {
    employees.splice(index, 1);
    renderTable();
    updateStats();

    // Automaticky uložit na server
    try {
      saveToLocalStorage();
      await saveToServer();
      logger.log('Employee removed and saved');
    } catch (error) {
      logger.error('Failed to save after removing employee:', error);
    }
  }
}

// === TABLE RENDERING ===
function renderTable() {
  const tbody = document.getElementById('employeeTableBody');

  if (employees.length === 0) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding: 2rem; color: var(--c-grey);">Žádní zaměstnanci - použijte tlačítko "Přidat"</td></tr>';
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
      salary = totalOtherHours * 20;
      invoice = 0;
      displayInfo = '<span class="employee-type-badge">Pouze bonus</span>';
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
                 style="font-weight: 600; min-width: 150px;"
                 data-action="updateEmployeeField"
                 data-index="${index}"
                 data-field="name"
                 data-recalculate="true">
          ${displayInfo}
        </td>
        <td class="text-center">
          ${(emp.type === 'special' || emp.type === 'special2' || isLenka) ?
            '<span style="color: var(--c-grey);">–</span>' :
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
            '<br><span style="font-size: 0.75rem; color: var(--c-grey);">' + totalOtherHours + 'h × 20 Kč</span>' : ''}
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
        <td class="text-center">
          <button class="btn btn-sm" style="background: var(--c-info); color: white; margin-right: 0.25rem;" data-action="generateSingleEmployeeQR" data-index="${index}" title="Generovat QR platbu">QR</button>
          <button class="btn btn-danger btn-sm" data-action="removeEmployee" data-index="${index}">×</button>
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
  let totalEmployeeHours = 0;

  // Calculate total hours (excluding special employees and bonus_girls)
  employees.forEach(emp => {
    if (emp.type !== 'special' && emp.type !== 'special2' && emp.type !== 'bonus_girls') {
      totalEmployeeHours += emp.hours || 0;
      totalHours += emp.hours || 0;
    }
  });

  // Bonus for special employees
  const bonusPerSpecial = totalEmployeeHours * 20;

  // Count only active employees with hours in current month
  // VYLOUČIT: Lenka, Marek, Radek, Bonus pro holky (nepočítají se do zaměstnanců)
  let activeEmployeesCount = 0;

  // Calculate totals - ONLY for employees with hours > 0 or Lenka (paušál)
  employees.forEach(emp => {
    const isLenka = emp.name === 'Lenka' || emp.name.includes('Lenka');

    // Lenka má vždy paušál, ostatní jen pokud mají hodiny > 0
    if (isLenka) {
      // Lenka má paušální mzdu 8716 Kč (NEPOČÍTÁ SE do zaměstnanců)
      totalSalary += 8716;
      totalInvoice += 0;  // Paušál nemá fakturu
      // activeEmployeesCount++; ← ODSTRANĚNO, Lenka se nepočítá
    } else if (emp.type === 'bonus_girls') {
      // Bonus pro holky - editovatelná částka (NEPOČÍTÁ SE do zaměstnanců)
      totalSalary += (emp.bonusAmount || 0);
      // activeEmployeesCount++; ← ODSTRANĚNO, bonus_girls se nepočítá
    } else if ((emp.type === 'special' || emp.type === 'special2') && bonusPerSpecial > 0) {
      // Special zaměstnanci (Marek, Radek) - NEPOČÍTAJÍ SE do zaměstnanců
      totalSalary += bonusPerSpecial;
      // activeEmployeesCount++; ← ODSTRANĚNO, special se nepočítají
    } else if (emp.hours > 0) {
      // Ostatní zaměstnanci jen pokud mají hodiny > 0
      if (emp.type === 'pausalni' && emp.pausalni) {
        const monthlyRate = emp.pausalni.rate / 12;
        const monthlyTax = emp.pausalni.tax;
        totalSalary += emp.hours * salaryRate;
        totalInvoice += Math.min(emp.hours * invoiceRate, monthlyRate - monthlyTax);
      } else {
        totalSalary += emp.hours * salaryRate;
        totalInvoice += emp.hours * invoiceRate;
      }
      activeEmployeesCount++; // ← JEN BĚŽNÍ ZAMĚSTNANCI S HODINAMI
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

  // Averages - ONLY from active employees (excluding Lenka, special, bonus_girls)
  const activeStandardEmployees = employees.filter(e => {
    const isLenka = e.name === 'Lenka' || e.name.includes('Lenka');
    return (e.type !== 'special' && e.type !== 'special2' && e.type !== 'bonus_girls' && !isLenka) && e.hours > 0;
  }).length;

  const avgHours = activeStandardEmployees > 0
    ? totalHours / activeStandardEmployees
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

function setQuickRate(value) {
  if (!value) return;

  const [salary, invoice] = value.split('-').map(Number);
  document.getElementById('salaryRate').value = salary;
  document.getElementById('invoiceRate').value = invoice;
  updateRates();
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

function formatNumber(num) {
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

function ensureQrLibraryLoaded() {
  if (window.QRCode && typeof QRCode.toCanvas === 'function') {
    return Promise.resolve(window.QRCode);
  }

  if (!qrLibraryPromise) {
    qrLibraryPromise = new Promise((resolve, reject) => {
      const existing = document.querySelector('script[data-qr-lib]');

      if (existing) {
        if (window.QRCode && typeof QRCode.toCanvas === 'function') {
          resolve(window.QRCode);
          return;
        }

        existing.addEventListener('load', () => resolve(window.QRCode));
        existing.addEventListener('error', () => reject(new Error('Nepodařilo se načíst knihovnu QR kódů')));
        return;
      }

      const script = document.createElement('script');
      script.src = 'https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js';
      script.defer = true;
      script.dataset.qrLib = '1';
      script.onload = () => {
        if (window.QRCode && typeof QRCode.toCanvas === 'function') {
          resolve(window.QRCode);
        } else {
          reject(new Error('Knihovna QR kódu se načetla, ale neobsahuje očekávané API'));
        }
      };
      script.onerror = () => reject(new Error('Nepodařilo se načíst knihovnu QR kódů'));
      document.head.appendChild(script);
    }).catch((err) => {
      qrLibraryPromise = null;
      throw err;
    });
  }

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

  const vs = data.vs ? String(data.vs).replace(/\D/g, '').slice(0, 10) : '';
  const message = sanitizeMessage(data.message || '');

  const parts = [
    'SPD',
    '1.0',
    `ACC:${account}/${bank}`,
    `AM:${amount.toFixed(2)}`,
    'CC:CZK'
  ];

  if (vs) parts.push(`X-VS:${vs}`);
  if (message) parts.push(`MSG:${message}`);

  return parts.join('*');
}

async function renderQrCode(qrElement, qrText, size, contextLabel = '') {
  await ensureQrLibraryLoaded();

  if (!window.QRCode || typeof QRCode.toCanvas !== 'function') {
    throw new Error('Knihovna pro QR kódy není načtena');
  }

  const drawWithLevel = (level) => new Promise((resolve, reject) => {
    const canvas = document.createElement('canvas');

    QRCode.toCanvas(
      canvas,
      qrText,
      {
        width: size,
        height: size,
        margin: 1,
        errorCorrectionLevel: level
      },
      (err) => {
        if (err) {
          const isOverflow = /overflow/i.test(err.message || '');

          if (level === 'M' && isOverflow) {
            logger.warn(`QR payload příliš dlouhý${contextLabel ? ' (' + contextLabel + ')' : ''}, zkouším nižší úroveň korekce (L)`);
            return drawWithLevel('L').then(resolve).catch(reject);
          }

          logger.error(`Failed to generate QR code${contextLabel ? ' for ' + contextLabel : ''}:`, err);
          qrElement.innerHTML = '<div style="color: red; padding: 20px;">Chyba generování QR kódu</div>';
          reject(err);
          return;
        }

        qrElement.innerHTML = '';
        qrElement.appendChild(canvas);
        resolve();
      }
    );
  });

  await drawWithLevel('M');
}

// === NOTIFICATIONS ===
function showSuccess(message) {
  alert(message);
}

function showError(message) {
  alert(message);
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
  if (await wgsConfirm('Opravdu chcete vymazat všechna data?', 'Vymazat vše', 'Zrušit')) {
    employees = [];
    renderTable();
    updateStats();

    // Automaticky uložit na server
    try {
      saveToLocalStorage();
      await saveToServer();
      logger.log('All data cleared and saved');
    } catch (error) {
      logger.error('Failed to save after clearing data:', error);
    }
  }
}

// === QR PAYMENTS ===
function generatePaymentQR() {
  const modal = document.getElementById('qrModal');
  const container = document.getElementById('qrCodesContainer');
  const summaryDiv = document.getElementById('paymentSummary');

  container.innerHTML = '';
  summaryDiv.innerHTML = '';

  let totalPayments = 0;
  let femaleBonus = 0;
  let radekPayment = 0;
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

    // Lenka má vždy paušální mzdu bez ohledu na hodiny
    if (isLenka || emp.type === 'bonus_girls' || (emp.type === 'special' || emp.type === 'special2') || emp.hours > 0) {
      let amount = 0;

      if (isLenka) {
        amount = 8716;  // Paušální mzda pro Lenku
      } else if (emp.type === 'bonus_girls') {
        amount = emp.bonusAmount || 0;  // Manuální bonus pro holky
      } else if (emp.type === 'pausalni' && emp.pausalni) {
        amount = emp.hours * salaryRate;
      } else if (emp.type === 'special' || emp.type === 'special2') {
        amount = bonusPerSpecial;
      } else {
        amount = emp.hours * salaryRate;
      }

      // Female bonus calculation (hidden) - Lenka už má paušál, nemá bonus
      const femaleNames = ['Stana', 'Anastasia', 'Maryna', 'Ivana', 'Olha', 'Piven Tetiana',
                          'Vitalina', 'Tetiana', 'Kataryna', 'Ruslana'];

      if (!isLenka && emp.hours > 0 && femaleNames.some(name => emp.name.includes(name))) {
        const bonus = (emp.hours * salaryRate) * 0.1;
        femaleBonus += bonus;
      }

      // Process by payment type
      if (emp.type === 'swift' && emp.swiftData) {
        swiftPayments.push({
          name: emp.name,
          amount: amount,
          swiftData: emp.swiftData
        });
      } else if (emp.name === 'Radek') {
        radekPayment = amount;
      } else if (emp.account && emp.bank) {
        paymentData.push({
          name: emp.name,
          amount: amount,
          account: emp.account,
          bank: formatBankCode(emp.bank)
        });
      }

      totalPayments += amount;
    }
  });

  // Add Radek with hidden bonus
  const radekData = employees.find(emp => emp.name === 'Radek');
  if (radekData && radekData.account && radekData.bank) {
    const radekTotalAmount = radekPayment + femaleBonus;
    paymentData.push({
      name: 'Radek',
      amount: radekTotalAmount,
      displayAmount: radekPayment,
      realAmount: radekTotalAmount,
      account: radekData.account,
      bank: formatBankCode(radekData.bank),
      isSpecial: true
    });
  }

  // Summary
  summaryDiv.innerHTML = `
    <div class="summary-row">
      <span>Počet domácích plateb:</span>
      <span>${paymentData.length}</span>
    </div>
    <div class="summary-row">
      <span>Počet SWIFT plateb:</span>
      <span>${swiftPayments.length}</span>
    </div>
    <div class="summary-row">
      <span>Základní výplaty:</span>
      <span>${formatCurrency(totalPayments)}</span>
    </div>
    ${femaleBonus > 0 ? `
    <div class="summary-row" style="color: var(--c-grey); font-size: 0.85rem;">
      <span>Skryté prémie (→ Radek):</span>
      <span>${formatCurrency(femaleBonus)}</span>
    </div>` : ''}
    <div class="summary-row">
      <span>CELKEM K VÝPLATĚ:</span>
      <span>${formatCurrency(totalPayments + femaleBonus)}</span>
    </div>
  `;

  // SWIFT payments section
  if (swiftPayments.length > 0) {
    const swiftSection = document.createElement('div');
    swiftSection.innerHTML = `<h3 style="margin: 2rem 0 1rem; padding-top: 1rem; border-top: 1px solid var(--c-border);">SWIFT platby (poplatky: OUR)</h3>`;
    container.appendChild(swiftSection);

    swiftPayments.forEach((payment, index) => {
      const swiftItem = document.createElement('div');
      swiftItem.className = 'qr-item';
      swiftItem.style.background = 'rgba(0,0,0,0.02)';

      swiftItem.innerHTML = `
        <div class="qr-employee-name">${payment.name}</div>
        <div class="qr-amount">${formatCurrency(payment.amount)}</div>
        <div style="font-size: 0.75rem; color: var(--c-grey); margin: 0.5rem 0;">Mezinárodní SWIFT platba</div>
        <div style="text-align: left; font-size: 0.8rem; padding: 1rem; background: white; border: 1px solid var(--c-border); margin: 1rem 0;">
          <div><strong>IBAN:</strong> ${payment.swiftData.iban}</div>
          <div><strong>SWIFT/BIC:</strong> ${payment.swiftData.swift}</div>
          <div><strong>Banka:</strong> ${payment.swiftData.bankName}</div>
          <div><strong>Adresa banky:</strong> ${payment.swiftData.bankAddress}</div>
          <div><strong>Příjemce:</strong> ${payment.swiftData.beneficiary}</div>
          <div style="color: var(--c-error); margin-top: 0.5rem;">
            <strong>Poplatky: OUR</strong> (všechny poplatky hradí odesílatel)
          </div>
        </div>
        <button class="btn btn-sm" data-action="copySWIFTDetails" data-name="${payment.name}" data-iban="${payment.swiftData.iban}" data-swift="${payment.swiftData.swift}" data-amount="${payment.amount}">
          Kopírovat údaje
        </button>
      `;

      container.appendChild(swiftItem);
    });
  }

  // Domestic payments with QR codes
  if (paymentData.length > 0) {
    const domesticSection = document.createElement('div');
    domesticSection.innerHTML = `
      <h3 style="margin: 2rem 0 1rem; padding-top: 1rem; border-top: 1px solid var(--c-border);">Domácí platby (QR kódy)</h3>
      <div class="qr-grid" id="domesticPaymentsGrid"></div>
    `;
    container.appendChild(domesticSection);

    const domesticGrid = domesticSection.querySelector('#domesticPaymentsGrid');

    paymentData.forEach((payment, index) => {
      const qrItem = document.createElement('div');
      qrItem.className = 'qr-item';

      const displayAmount = payment.displayAmount || payment.amount;
      const qrAmount = payment.realAmount || payment.amount;

      qrItem.innerHTML = `
        <div class="qr-employee-name">${payment.name}</div>
        <div class="qr-amount">${formatCurrency(displayAmount)}</div>
        ${payment.isSpecial ? '<div style="font-size: 0.75rem; color: var(--c-success);">Včetně prémií</div>' : ''}
        <div class="qr-account">${payment.account}/${payment.bank}</div>
        <div class="qr-code-wrapper" id="qr-${index}"></div>
        <button class="btn btn-sm" style="margin-top: 1rem;" data-action="downloadQR" data-qrid="qr-${index}" data-name="${payment.name}">
          Stáhnout QR
        </button>
      `;

      domesticGrid.appendChild(qrItem);

      // Generate QR code
      setTimeout(async () => {
        const qrElement = document.getElementById(`qr-${index}`);
        if (!qrElement) {
          logger.error(`QR element not found: qr-${index}`);
          return;
        }

        // BUGFIX: Clear element before generating new QR code
        qrElement.innerHTML = '';

        let qrText;
        try {
          qrText = generateCzechPaymentString({
            account: payment.account,
            bank: payment.bank,
            amount: qrAmount,
            vs: currentPeriod.year * 100 + currentPeriod.month,
            message: `Výplata ${payment.name} ${currentPeriod.month}/${currentPeriod.year}`
          });
        } catch (err) {
          qrElement.innerHTML = `<div style="color: red; padding: 20px;">${err.message}</div>`;
          return;
        }

        logger.log(`Generating QR for ${payment.name}:`, qrText);

        try {
          await renderQrCode(qrElement, qrText, 180, payment.name);
        } catch (error) {
          logger.error(`Failed to generate QR code for ${payment.name}:`, error);
          qrElement.innerHTML = '<div style="color: red; padding: 20px;">Chyba generování QR kódu</div>';
        }
      }, 100 * index);
    });
  }

  modal.style.display = 'block';
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
    alert('SWIFT údaje byly zkopírovány do schránky');
  }).catch(err => {
    logger.error('Chyba při kopírování:', err);
    alert('Nepodařilo se zkopírovat údaje');
  });
}

function downloadQR(qrId, employeeName) {
  const qrCanvas = document.querySelector(`#${qrId} canvas`);
  if (qrCanvas) {
    const link = document.createElement('a');
    link.download = `QR_platba_${employeeName}_${currentPeriod.month}_${currentPeriod.year}.png`;
    link.href = qrCanvas.toDataURL();
    link.click();
  }
}

function closeQRModal() {
  document.getElementById('qrModal').style.display = 'none';
}

// === SINGLE EMPLOYEE QR GENERATION ===
function generateSingleEmployeeQR(index) {
  const emp = employees[index];
  if (!emp) {
    alert('Zaměstnanec nenalezen');
    return;
  }

  const modal = document.getElementById('qrModal');
  const container = document.getElementById('qrCodesContainer');
  const summaryDiv = document.getElementById('paymentSummary');

  container.innerHTML = '';
  summaryDiv.innerHTML = '';

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
  } else {
    amount = emp.hours * salaryRate;
  }

  // Check if payment is possible
  if (amount <= 0) {
    alert('Částka k výplatě je 0 Kč. Zadejte prosím hodiny nebo nastavte mzdu.');
    return;
  }

  // For SWIFT payments
  if (emp.type === 'swift' && emp.swiftData) {
    summaryDiv.innerHTML = `
      <h3>SWIFT platba</h3>
      <div class="summary-row">
        <span>Zaměstnanec:</span>
        <span>${emp.name}</span>
      </div>
      <div class="summary-row">
        <span>Částka:</span>
        <span>${formatCurrency(amount)}</span>
      </div>
    `;

    const swiftItem = document.createElement('div');
    swiftItem.className = 'qr-item';
    swiftItem.style.background = 'rgba(0,0,0,0.02)';

    swiftItem.innerHTML = `
      <div class="qr-employee-name">${emp.name}</div>
      <div class="qr-amount">${formatCurrency(amount)}</div>
      <div style="font-size: 0.75rem; color: var(--c-grey); margin: 0.5rem 0;">Mezinárodní SWIFT platba</div>
      <div style="text-align: left; font-size: 0.8rem; padding: 1rem; background: white; border: 1px solid var(--c-border); margin: 1rem 0;">
        <div><strong>IBAN:</strong> ${emp.swiftData.iban}</div>
        <div><strong>SWIFT/BIC:</strong> ${emp.swiftData.swift}</div>
        <div><strong>Banka:</strong> ${emp.swiftData.bankName}</div>
        <div><strong>Adresa banky:</strong> ${emp.swiftData.bankAddress}</div>
        <div><strong>Příjemce:</strong> ${emp.swiftData.beneficiary}</div>
        <div style="color: var(--c-error); margin-top: 0.5rem;">
          <strong>Poplatky: OUR</strong> (všechny poplatky hradí odesílatel)
        </div>
      </div>
      <button class="btn btn-sm" data-action="copySWIFTDetails" data-name="${emp.name}" data-iban="${emp.swiftData.iban}" data-swift="${emp.swiftData.swift}" data-amount="${amount}">
        Kopírovat údaje
      </button>
    `;

    container.appendChild(swiftItem);
    modal.style.display = 'block';
    return;
  }

  // For domestic payments - check account and bank
  if (!emp.account || !emp.bank) {
    alert('Prosím zadejte číslo účtu a kód banky pro ' + emp.name);
    return;
  }

  // Summary
  summaryDiv.innerHTML = `
    <h3>Platba pro zaměstnance</h3>
    <div class="summary-row">
      <span>Zaměstnanec:</span>
      <span>${emp.name}</span>
    </div>
    <div class="summary-row">
      <span>Částka k výplatě:</span>
      <span>${formatCurrency(amount)}</span>
    </div>
    ${isLenka ? '<div class="summary-row" style="color: var(--c-info); font-size: 0.85rem;"><span>Paušální mzda</span><span>8716 Kč/měsíc</span></div>' : ''}
  `;

  // Generate QR code
  const qrItem = document.createElement('div');
  qrItem.className = 'qr-item';

  qrItem.innerHTML = `
    <div class="qr-employee-name">${emp.name}</div>
    <div class="qr-amount">${formatCurrency(amount)}</div>
    ${isLenka ? '<div style="font-size: 0.75rem; color: var(--c-info);">Paušální mzda</div>' : ''}
    <div class="qr-account">${emp.account}/${formatBankCode(emp.bank)}</div>
    <div class="qr-code-wrapper" id="qr-single"></div>
    <button class="btn btn-sm" style="margin-top: 1rem;" data-action="downloadQR" data-qrid="qr-single" data-name="${emp.name}">
      Stáhnout QR
    </button>
  `;

  container.appendChild(qrItem);

  // Generate QR code
  setTimeout(async () => {
    const qrElement = document.getElementById('qr-single');
    if (!qrElement) {
      logger.error('QR element not found: qr-single');
      return;
    }

    // BUGFIX: Clear element before generating new QR code
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
      qrElement.innerHTML = `<div style="color: red; padding: 20px;">${err.message}</div>`;
      return;
    }

    logger.log(`Generating single QR for ${emp.name}:`, qrText);

    try {
      await renderQrCode(qrElement, qrText, 220, emp.name);
    } catch (error) {
      logger.error(`Failed to generate QR code for ${emp.name}:`, error);
      qrElement.innerHTML = '<div style="color: red; padding: 20px;">Chyba generování QR kódu</div>';
    }
  }, 100);

  modal.style.display = 'block';
}

// Close modal on outside click
window.onclick = function(event) {
  const modal = document.getElementById('qrModal');
  if (event.target == modal) {
    modal.style.display = 'none';
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
      case 'generateSingleEmployeeQR':
        const gIndex = target.getAttribute('data-index');
        if (gIndex !== null && typeof generateSingleEmployeeQR === 'function') {
          generateSingleEmployeeQR(parseInt(gIndex));
        }
        return;

      case 'removeEmployee':
        const rIndex = target.getAttribute('data-index');
        if (rIndex !== null && typeof removeEmployee === 'function') {
          removeEmployee(parseInt(rIndex));
        }
        return;

      case 'copySWIFTDetails':
        const name = target.getAttribute('data-name');
        const iban = target.getAttribute('data-iban');
        const swift = target.getAttribute('data-swift');
        const amount = parseFloat(target.getAttribute('data-amount'));
        if (name && iban && swift && typeof copySWIFTDetails === 'function') {
          copySWIFTDetails(name, iban, swift, amount);
        }
        return;

      case 'downloadQR':
        const qrid = target.getAttribute('data-qrid');
        const dName = target.getAttribute('data-name');
        if (qrid && dName && typeof downloadQR === 'function') {
          downloadQR(qrid, dName);
        }
        return;

      case 'updateEmployeeField':
        const empIndex = parseInt(target.getAttribute('data-index'));
        const empField = target.getAttribute('data-field');
        const empRecalculate = target.getAttribute('data-recalculate') === 'true';
        if (!isNaN(empIndex) && empField && typeof updateEmployee === 'function') {
          updateEmployee(empIndex, empField, target.value, empRecalculate);
        }
        return;
    }

    // Try to call function if it exists
    if (typeof window[action] === 'function') {
      window[action]();
    }
  }

  // Handle data-action buttons - kliknutí
  document.addEventListener('click', (e) => {
    const target = e.target.closest('[data-action]');
    if (!target) return;
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