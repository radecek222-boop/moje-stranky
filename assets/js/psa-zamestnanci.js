/**
 * psa-zamestnanci.js
 * Správa zaměstnanců v PSA kalkulátoru (přidání, editace, odebrání, selector)
 * Závisí na: psa-kalkulator.js (DATA, PERIOD_KEY, logger, wgsToast, fetchCsrfToken)
 */

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
  modal.className = 'emp-selector-overlay aktivni';
  modal.id = 'employeeSelectorModal';
  modal.innerHTML = `
    <div class="emp-selector-dialog">
      <div class="emp-selector-hlavicka">
        <span class="emp-selector-nadpis">Vybrat zaměstnance</span>
        <button class="emp-selector-zavrit" data-action="closeEmployeeSelector" aria-label="Zavřít">&times;</button>
      </div>
      <div class="emp-selector-ovladani">
        <button class="emp-selector-btn" data-action="selectAllEmployees">Vybrat vše</button>
        <button class="emp-selector-btn" data-action="deselectAllEmployees">Zrušit výběr</button>
      </div>
      <div class="emp-selector-seznam">
        ${allAvailable.map(emp => {
          const isAlreadyAdded = currentIds.includes(emp.id);
          const ucet = emp.account ? emp.account + '/' + emp.bank : 'Bez účtu';
          const swift = emp.type === 'swift' ? ' · SWIFT' : '';
          return `
            <label class="emp-selector-polozka${isAlreadyAdded ? ' emp-selector-polozka--pridano' : ''}">
              <input class="emp-selector-checkbox"
                     type="checkbox"
                     name="selectedEmployee"
                     value="${emp.id}"
                     ${isAlreadyAdded ? 'disabled checked' : ''}>
              <div class="emp-selector-info">
                <div class="emp-selector-jmeno">${emp.name}</div>
                <div class="emp-selector-ucet">${ucet}${swift}</div>
              </div>
              ${isAlreadyAdded ? '<span class="emp-selector-stitek">v seznamu</span>' : ''}
            </label>
          `;
        }).join('')}
      </div>
      <div class="emp-selector-paticka">
        <span class="emp-selector-pocet">Vybráno: <strong id="selectedCount">0</strong></span>
        <div class="emp-selector-akce">
          <button class="emp-selector-btn-zrusit" data-action="closeEmployeeSelector">Zrušit</button>
          <button class="emp-selector-btn-pridat" data-action="confirmEmployeeSelection">Přidat vybrané</button>
        </div>
      </div>
    </div>
  `;
  document.body.appendChild(modal);

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
  // Najít všechny inputy s daným indexem
  const inputs = document.querySelectorAll(`[data-action="updateEmployeeField"][data-index="${index}"]`);

  if (inputs.length > 0 && employees[index]) {
    inputs.forEach(input => {
      const field = input.getAttribute('data-field');
      if (field) {
        if (field === 'hours' || field === 'bonusAmount' || field === 'premieCastka') {
          employees[index][field] = parseInt(input.value) || 0;
        } else if (field === 'bank') {
          employees[index][field] = formatBankCode(input.value);
        } else {
          employees[index][field] = input.value;
        }
        logger.log(`Synced field ${field} = "${employees[index][field]}" for employee ${employees[index].name}`);
      }
    });
  }

  const emp = employees[index];
  if (!emp) {
    wgsToast.error('Zaměstnanec nenalezen');
    return;
  }

  logger.log('Saving employee changes:', emp.name, 'account:', emp.account, 'bank:', emp.bank);

  // Aktualizovat v databázi zaměstnanců
  const dbIndex = allEmployeesDatabase.findIndex(e => e.id === emp.id);
  if (dbIndex !== -1) {
    allEmployeesDatabase[dbIndex].name = emp.name;
    allEmployeesDatabase[dbIndex].account = emp.account || '';
    allEmployeesDatabase[dbIndex].bank = emp.bank || '';
    allEmployeesDatabase[dbIndex].type = emp.type || 'standard';
    logger.log('Updated allEmployeesDatabase at index', dbIndex);
  } else {
    // Zaměstnanec není v databázi - přidat ho
    logger.warn('Employee not found in allEmployeesDatabase, adding:', emp.name, emp.id);
    allEmployeesDatabase.push({
      id: emp.id,
      name: emp.name,
      account: emp.account || '',
      bank: emp.bank || '',
      type: emp.type || 'standard',
      active: true
    });
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
    if (oldName !== value && !await wgsConfirm(`Opravdu chcete změnit jméno z "${oldName}" na "${value}"?`, { titulek: 'Změnit jméno', btnPotvrdit: 'Změnit' })) {
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

  if (await wgsConfirm(`Opravdu chcete odebrat zaměstnance ${emp.name} z tohoto období?`, { titulek: 'Odebrat zaměstnance', btnPotvrdit: 'Odebrat', nebezpecne: true })) {
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

