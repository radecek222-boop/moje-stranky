/**
 * psa-qr-platby.js
 * QR kódy pro výplaty zaměstnanců (CZ platební řetězec, SWIFT, IBAN)
 * Závisí na: psa-kalkulator.js (DATA, PERIOD_KEY, logger, wgsToast)
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
        colorDark: 'var(--wgs-black)',
        colorLight: 'var(--wgs-white)',
        correctLevel: QRCode.CorrectLevel.L  // Nizsi uroven korekce = vice dat
      });

      resolve();
    } catch (err) {
      console.error(`Failed to generate QR code${contextLabel ? ' for ' + contextLabel : ''}:`, err);
      qrElement.innerHTML = `<div style="color: #dc3545; padding: 20px;">${err?.message || 'Chyba QR'}</div>`;
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
  if (await wgsConfirm('Opravdu chcete vynulovat hodiny pro toto období?', { titulek: 'Vynulovat hodiny', btnPotvrdit: 'Vynulovat', nebezpecne: true })) {
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
      qrElement.innerHTML = `<div style="color: #dc3545; padding: 20px;">${err.message}</div>`;
      return;
    }

    try {
      await renderQrCode(qrElement, qrText, 220, emp.name);
    } catch (error) {
      console.error(`QR render failed for ${emp.name}:`, error);
      qrElement.innerHTML = `<div style="color: #dc3545; padding: 20px;">${error?.message || 'Chyba QR'}</div>`;
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

