/**
 * seznam-qr-platba.js
 * QR kód pro platby (generování, modal, přepočet částky, IBAN)
 * Závisí na: seznam.js (fetchCsrfToken, logger, wgsToast, ModalManager, CURRENT_RECORD)
 */

// === QR PLATBA MODAL ===
let qrLibraryPromise = null;

async function ensureQrLibraryLoaded() {
  if (qrLibraryPromise) return qrLibraryPromise;

  qrLibraryPromise = new Promise((resolve, reject) => {
    const src = 'assets/js/qrcode.min.js';
    const existing = document.querySelector('script[data-qr-lib]') ||
      Array.from(document.scripts).find(s => (s.src || '').includes(src));

    if (existing && window.QRCode && typeof window.QRCode === 'function') {
      resolve(window.QRCode);
      return;
    }

    const script = document.createElement('script');
    script.src = '/' + src;
    script.setAttribute('data-qr-lib', 'true');
    script.onload = () => {
      if (window.QRCode && typeof window.QRCode === 'function') {
        resolve(window.QRCode);
      } else {
        reject(new Error('QRCode knihovna se nenačetla správně'));
      }
    };
    script.onerror = () => reject(new Error('Nepodařilo se načíst QR knihovnu'));
    document.head.appendChild(script);
  });

  return qrLibraryPromise;
}

// Globální data pro QR platbu (pro regeneraci)
let QR_PLATBA_DATA = null;

async function showQrPlatbaModal(reklamaceId) {
  if (!reklamaceId && CURRENT_RECORD) {
    reklamaceId = CURRENT_RECORD.id;
  }

  if (!reklamaceId) {
    wgsToast.error('Chybí ID reklamace');
    return;
  }

  // Zobrazit loading modal
  const loadingContent = `
    ${createCustomerHeader('showDetail')}
    <div class="modal-body" style="text-align: center; padding: 3rem;">
      <div style="font-size: 1.2rem; color: #888;">Načítám QR platební data...</div>
    </div>
  `;
  ModalManager.show(loadingContent);

  try {
    // Načíst QR data z API
    const response = await fetch(`/api/qr_platba_api.php?id=${reklamaceId}`);
    const result = await response.json();

    if (result.status === 'error') {
      throw new Error(result.message || 'Chyba při načítání dat');
    }

    // Data jsou přímo v result (ne v result.data)
    const data = result;
    QR_PLATBA_DATA = data; // Uložit pro regeneraci

    // Načíst QR knihovnu
    await ensureQrLibraryLoaded();

    // Vytvořit modal s QR kódem a editovatelnou částkou
    // Počáteční částka: pokud je záloha uhrazena, zobrazit zbývající částku
    const initialCastka = data.castka_platba ?? data.castka ?? 0;
    const maZalohu = data.zf_uhrazena && data.zalohova_castka_czk > 0;
    const zalohaOdeslana = data.zf_odeslana && !data.zf_uhrazena && data.zalohova_castka_czk > 0;

    // Blok zálohy - zobrazit pouze pokud je ZF odeslána nebo uhrazena
    const celkemCzk = data.nabidka_celkem_czk > 0 ? data.nabidka_celkem_czk : (data.castka || 0);
    const celkemEur = data.nabidka_celkem_eur || 0;
    const zalohaCzk = data.zalohova_castka_czk || 0;
    const zalohaEur = data.zalohova_castka_eur || 0;
    const doplatekCzk = data.doplatek_czk ?? (data.castka_platba || 0);
    const doplatekEur = data.doplatek_eur ?? 0;

    let zalohaBlok = '';
    if (maZalohu) {
      zalohaBlok = `
        <div style="background: #111; border: 1px solid #39ff14; border-radius: 6px; padding: 0.85rem; margin-bottom: 1rem; font-size: 0.87rem;">
          <div style="color: #39ff14; font-weight: bold; margin-bottom: 0.6rem; font-size: 0.9rem;">Vypocet doplatku (ZF uhrazena)</div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem; padding-bottom: 0.35rem; border-bottom: 1px solid #222;">
            <span style="color: #888;">Celkova cena zakázky:</span>
            <span style="color: #ccc; font-weight: 500;">${celkemCzk.toLocaleString('cs-CZ')} Kč&nbsp;<span style="color:#666;">(${celkemEur.toFixed(2)} €)</span></span>
          </div>
          <div style="display: flex; justify-content: space-between; margin-bottom: 0.35rem; padding-bottom: 0.35rem; border-bottom: 1px solid #222;">
            <span style="color: #888;">Záloha za náhradní díly (uhrazeno):</span>
            <span style="color: #aaa; font-weight: 500;">- ${zalohaCzk.toLocaleString('cs-CZ')} Kč&nbsp;<span style="color:#666;">(${zalohaEur.toFixed(2)} €)</span></span>
          </div>
          <div style="display: flex; justify-content: space-between; padding-top: 0.1rem;">
            <span style="color: #fff; font-weight: bold; font-size: 0.95rem;">Zbývá k doplacení:</span>
            <span style="color: #39ff14; font-weight: bold; font-size: 0.95rem;">${doplatekCzk.toLocaleString('cs-CZ')} Kč&nbsp;<span style="font-size:0.8rem; color:#39ff14; opacity:0.8;">(${doplatekEur.toFixed(2)} €)</span></span>
          </div>
        </div>`;
    } else if (zalohaOdeslana) {
      zalohaBlok = `
        <div style="background: #111; border: 1px solid #666; border-radius: 6px; padding: 0.75rem; margin-bottom: 1rem; font-size: 0.88rem;">
          <div style="color: #ccc; font-weight: bold; margin-bottom: 0.35rem;">Záloha (ZF) odeslána – čeká na úhradu</div>
          <div style="display: flex; justify-content: space-between;">
            <span style="color: #888;">Výše zálohy za náhradní díly:</span>
            <span style="color: #fff; font-weight: 500;">${zalohaCzk.toLocaleString('cs-CZ')} Kč&nbsp;<span style="color:#888;">(${zalohaEur.toFixed(2)} €)</span></span>
          </div>
        </div>`;
    }

    const content = `
      ${createCustomerHeader('showDetail')}

      <div class="modal-body" style="padding: 1.5rem;">
        <div style="text-align: center; margin-bottom: 1.5rem;">
          <h3 style="color: #39ff14; margin: 0 0 0.5rem 0; font-size: 1.2rem;">QR kód pro platbu</h3>
          <p style="color: #888; margin: 0; font-size: 0.85rem;">Naskenujte QR kód bankovní aplikací</p>
        </div>
        ${zalohaBlok}

        <!-- QR kód -->
        <div id="qrPlatbaContainer" style="
          display: flex;
          justify-content: center;
          align-items: center;
          margin: 1.5rem 0;
          padding: 1rem;
          background: #fff;
          border-radius: 8px;
          min-height: 220px;
        ">
          ${initialCastka <= 0 ? '<div style="color: #666; font-size: 0.9rem;">Zadejte částku pro vygenerování QR kódu</div>' : ''}
        </div>

        <!-- Platební údaje -->
        <div style="
          background: #1a1a1a;
          border: 1px solid #333;
          border-radius: 8px;
          padding: 1rem;
          margin-top: 1rem;
        ">
          <div style="display: grid; gap: 0.75rem;">
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 0.5rem;">
              <span style="color: #888;">Číslo účtu:</span>
              <span style="color: #fff; font-family: monospace; font-weight: bold;">${Utils.escapeHtml(data.ucet || data.iban_formatovany)}</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 0.5rem;">
              <span style="color: #888;">Částka CZK:</span>
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="number" id="qrCastkaCzkInput" class="wgs-input wgs-input--cislo" value="${initialCastka}" min="0" step="1" style="width: 100px;" onchange="prepoctiCastku('czk')" onkeyup="prepoctiCastku('czk')">
                <span style="color: #888;">Kč</span>
              </div>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 0.5rem;">
              <span style="color: #888;">Částka EUR:</span>
              <div style="display: flex; align-items: center; gap: 0.5rem;">
                <input type="number" id="qrCastkaEurInput" class="wgs-input wgs-input--small" value="${initialCastka > 0 ? (initialCastka / 25).toFixed(0) : ''}" min="0" step="1" style="width: 100px; text-align: right;" onchange="prepoctiCastku('eur')" onkeyup="prepoctiCastku('eur')">
                <span style="color: #888;">€</span>
              </div>
            </div>
            <div style="text-align: right; font-size: 0.75rem; color: #666; margin-top: -0.5rem;">
              Kurz: 1 EUR = 25 CZK
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #333; padding-bottom: 0.5rem;">
              <span style="color: #888;">Variabilní symbol:</span>
              <span style="color: #fff; font-family: monospace;">${Utils.escapeHtml(data.vs)}</span>
            </div>
            <div style="display: flex; justify-content: space-between; align-items: center;">
              <span style="color: #888;">Splatnost:</span>
              <span style="color: #fff;">Ihned</span>
            </div>
          </div>
        </div>

      </div>
    `;

    ModalManager.show(content);

    // Vygenerovat QR kód po zobrazení modalu (pokud je částka > 0)
    if (initialCastka > 0) {
      setTimeout(() => regenerovatQrKod(), 100);
    }

  } catch (error) {
    logger.error('QR platba - chyba:', error);

    const errorContent = `
      ${createCustomerHeader('showDetail')}
      <div class="modal-body" style="padding: 2rem; text-align: center;">
        <div style="color: #ff4444; font-size: 1.1rem; margin-bottom: 1rem;">
          ${Utils.escapeHtml(error.message)}
        </div>
      </div>
    `;
    ModalManager.show(errorContent);
  }
}

// Konstanty pro přepočet měn
const QR_KURZ_EUR_CZK = 25;

// Funkce pro přepočet částky mezi CZK a EUR
let prepocetTimeout = null;
function prepoctiCastku(zdroj) {
  clearTimeout(prepocetTimeout);
  prepocetTimeout = setTimeout(() => {
    const inputCzk = document.getElementById('qrCastkaCzkInput');
    const inputEur = document.getElementById('qrCastkaEurInput');

    if (!inputCzk || !inputEur) return;

    if (zdroj === 'czk') {
      // CZK → EUR
      const czk = parseFloat(inputCzk.value) || 0;
      inputEur.value = czk > 0 ? Math.round(czk / QR_KURZ_EUR_CZK) : '';
    } else {
      // EUR → CZK
      const eur = parseFloat(inputEur.value) || 0;
      inputCzk.value = eur > 0 ? Math.round(eur * QR_KURZ_EUR_CZK) : '';
    }

    // Regenerovat QR kód
    regenerovatQrKod();
  }, 200);
}

// Funkce pro regeneraci QR kódu při změně částky
let qrRegenerateTimeout = null;
function regenerovatQrKod() {
  // Debounce - počkat 300ms po posledním stisku
  clearTimeout(qrRegenerateTimeout);
  qrRegenerateTimeout = setTimeout(() => {
    const inputCzk = document.getElementById('qrCastkaCzkInput');
    const container = document.getElementById('qrPlatbaContainer');

    if (!inputCzk || !container || !QR_PLATBA_DATA) return;

    const castka = parseFloat(inputCzk.value) || 0;

    if (castka <= 0) {
      container.innerHTML = '<div style="color: #666; font-size: 0.9rem;">Zadejte částku pro vygenerování QR kódu</div>';
      return;
    }

    // Generovat SPD string (vždy v CZK)
    // Použít účet ve formátu 188784838/0300, convertToIBAN ho převede na IBAN (jako PSA)
    // Přidat zprávu s číslem objednávky a jménem zákazníka
    const spdString = generujSpdString(
      QR_PLATBA_DATA.ucet,
      castka,
      QR_PLATBA_DATA.vs,
      QR_PLATBA_DATA.cislo,
      QR_PLATBA_DATA.jmeno
    );

    // Vygenerovat QR kód
    container.innerHTML = '';
    if (window.QRCode) {
      new QRCode(container, {
        text: spdString,
        width: 220,
        height: 220,
        colorDark: '#000000',
        colorLight: '#ffffff',
        correctLevel: QRCode.CorrectLevel.L
      });
    }
  }, 300);
}

// Odstranění diakritiky pro SPAYD zprávu
function odstranDiakritiku(text) {
  if (!text) return '';
  const mapa = {
    'á': 'a', 'č': 'c', 'ď': 'd', 'é': 'e', 'ě': 'e', 'í': 'i', 'ň': 'n',
    'ó': 'o', 'ř': 'r', 'š': 's', 'ť': 't', 'ú': 'u', 'ů': 'u', 'ý': 'y', 'ž': 'z',
    'Á': 'A', 'Č': 'C', 'Ď': 'D', 'É': 'E', 'Ě': 'E', 'Í': 'I', 'Ň': 'N',
    'Ó': 'O', 'Ř': 'R', 'Š': 'S', 'Ť': 'T', 'Ú': 'U', 'Ů': 'U', 'Ý': 'Y', 'Ž': 'Z'
  };
  return text.split('').map(c => mapa[c] || c).join('');
}

// Sanitizace zprávy pro SPAYD (bez diakritiky, bez speciálních znaků)
function sanitizujZpravu(zprava) {
  if (!zprava) return '';
  return odstranDiakritiku(zprava)
    .replace(/[\r\n]+/g, ' ')  // Odstranit nové řádky
    .replace(/\*/g, '')        // Odstranit hvězdičky (rezervováno v SPAYD)
    .replace(/[^\w\s\-\.]/g, '') // Pouze alfanumerické, mezery, pomlčky, tečky
    .trim()
    .slice(0, 60);             // Max 60 znaků pro MSG pole
}

// Generování SPD stringu pro QR platbu
function generujSpdString(ucet, castka, vs, cisloObj, jmeno) {
  // Pokud přijde účet ve formátu 188784838/0300, konvertuj na IBAN
  let cleanIban = ucet;
  if (ucet && ucet.includes('/')) {
    const parts = ucet.split('/');
    cleanIban = convertToIBAN(parts[0], parts[1]);
  } else {
    cleanIban = ucet.replace(/\s/g, '').toUpperCase();
  }

  // Částka - vždy 2 desetinná místa s tečkou
  const amountStr = castka.toFixed(2);

  // Zpráva pro příjemce - číslo objednávky a jméno zákazníka
  const zprava = sanitizujZpravu(`Obj ${cisloObj || vs} ${jmeno || ''}`);

  // SPAYD formát s MSG
  const spd = `SPD*1.0*ACC:${cleanIban}*AM:${amountStr}*CC:CZK*MSG:${zprava}`;

  return spd;
}

// Konverze čísla účtu na IBAN (z PSA kalkulátoru)
function convertToIBAN(account, bankCode) {
  let rawAccount = (account || '').toString().replace(/\s/g, '');
  bankCode = (bankCode || '').toString().replace(/\D/g, '').padStart(4, '0');

  let predcisli = '';
  let cisloUctu = '';

  if (rawAccount.includes('-')) {
    const parts = rawAccount.split('-');
    predcisli = (parts[0] || '').replace(/\D/g, '');
    cisloUctu = (parts[1] || '').replace(/\D/g, '');
  } else {
    const digits = rawAccount.replace(/\D/g, '');
    if (digits.length > 10) {
      predcisli = digits.slice(0, -10);
      cisloUctu = digits.slice(-10);
    } else {
      predcisli = '';
      cisloUctu = digits;
    }
  }

  predcisli = predcisli.padStart(6, '0');
  cisloUctu = cisloUctu.padStart(10, '0');

  const bban = bankCode + predcisli + cisloUctu;

  if (bban.length !== 20) {
    throw new Error(`Neplatná délka BBAN: ${bban.length}`);
  }

  // Výpočet kontrolních číslic (ISO 7064 Mod 97-10)
  const checkString = bban + '123500';
  let remainder = BigInt(checkString) % 97n;
  const checkDigits = String(98n - remainder).padStart(2, '0');

  const iban = 'CZ' + checkDigits + bban;

  // Kontrola IBAN délky (CZ má vždy 24 znaků)
  if (iban.length !== 24) {
    throw new Error(`Neplatná délka IBAN: ${iban.length} (očekáváno 24)`);
  }

  return iban;
}

