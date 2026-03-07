// Kontrola - tato stránka je pouze pro techniky a adminy
(async function() {
    try {
        const response = await fetch("app/admin_session_check.php");
        const data = await response.json();

        if (!data.logged_in) {
            wgsToast.error(t('please_log_in'));
            window.location.href = "login.php";
            return;
        }

        if (data.role === "prodejce") {
            wgsToast.error(t('page_for_techs_admins_only'));
            window.location.href = "seznam.php";
        }
    } catch (err) {
        logger.error("Chyba kontroly přístupu:", err);
    }
})();

// === HAMBURGER MENU ===
// REMOVED: Mrtvý kód - menu je nyní centrálně v hamburger-menu.php

// === DEBOUNCE FALLBACK ===
// Fallback pokud utils.js není načten
if (typeof debounce === 'undefined') {
  window.debounce = function(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  };
}

// === CENÍK SLUŽEB (synchronizováno s cenik-calculator.js) ===
const CENY = {
    diagnostika: 110, // Inspekce/diagnostika
    prvniDil: 205, // První díl čalounění
    dalsiDil: 70, // Každý další díl
    zakladniSazba: 165, // Základní servisní sazba (mechanické opravy)
    mechanismusPriplatek: 45, // Příplatek za mechanismus (relax, výsuv)
    druhaOsoba: 95, // Druhá osoba pro těžký nábytek nad 50kg
    material: 50, // Materiál (alternativní výplně)
    vyzvednutiSklad: 10 // Vyzvednutí dílu pro reklamaci na skladě
};

// === KONTROLA PDF KNIHOVEN ===
async function zkontrolujPdfKnihovny() {
  const maxPokusy = 50; // Max 5 sekund (50 * 100ms)
  let pokusy = 0;

  // Cekej na jsPDF
  while ((!window.jspdf || !window.jspdf.jsPDF) && pokusy < maxPokusy) {
    await new Promise(resolve => setTimeout(resolve, 100));
    pokusy++;
  }

  if (!window.jspdf || !window.jspdf.jsPDF) {
    throw new Error('jsPDF knihovna se nepodařila načíst. Zkuste obnovit stránku (F5).');
  }

  // Cekej na html2canvas
  pokusy = 0;
  while (typeof html2canvas === 'undefined' && pokusy < maxPokusy) {
    await new Promise(resolve => setTimeout(resolve, 100));
    pokusy++;
  }

  if (typeof html2canvas === 'undefined') {
    throw new Error('html2canvas knihovna se nepodařila načíst. Zkuste obnovit stránku (F5).');
  }

  return true;
}

// === PDF UTF-8 HELPER ===
// Helper pro bezpečný výpis textu s českými znaky v PDF
// ŘEŠENÍ: jsPDF 2.5.1 NEPODPORUJE UTF-8 → použijeme ASCII transliteraci
window.pdfTextSafe = function(pdfObj, text, x, y, options = {}) {
  let safeText = String(text || '');

  // Mapa českých znaků → ASCII (bez diakritiky)
  const czechMap = {
    'Č': 'C', 'č': 'c', 'Ď': 'D', 'ď': 'd',
    'Ě': 'E', 'ě': 'e', 'Ň': 'N', 'ň': 'n',
    'Ř': 'R', 'ř': 'r', 'Š': 'S', 'š': 's',
    'Ť': 'T', 'ť': 't', 'Ů': 'U', 'ů': 'u',
    'Ý': 'Y', 'ý': 'y', 'Ž': 'Z', 'ž': 'z',
    'Á': 'A', 'á': 'a', 'É': 'E', 'é': 'e',
    'Í': 'I', 'í': 'i', 'Ó': 'O', 'ó': 'o',
    'Ú': 'U', 'ú': 'u'
  };

  // Převést všechny české znaky na ASCII
  let asciiText = safeText;
  for (const [czech, ascii] of Object.entries(czechMap)) {
    asciiText = asciiText.replace(new RegExp(czech, 'g'), ascii);
  }

  // Zobrazit bez diakritiky
  pdfObj.text(asciiText, x, y, options);
};

// === NOTIFIKACE ===
// showNotification() je definovana centralne v utils.js

// REMOVED: Mrtvý kód pro zavírání menu - řešeno centrálně v hamburger-menu.php

let signaturePad;
let attachedPhotos = [];
let currentReklamaceId = null;
let currentReklamace = null;
window.kalkulaceData = null; // Data kalkulace z databáze pro PDF (globální scope)

// PDF preview kontext
let pdfPreviewContext = null; // 'export' nebo 'send'
let cachedPdfDoc = null; // uložený jsPDF document
let cachedPdfBase64 = null; // uložený base64 pro odeslání

// fetchCsrfToken přesunuto do utils.js (Step 106)
// Funkce je dostupná jako window.fetchCsrfToken() nebo Utils.fetchCsrfToken()

window.addEventListener("DOMContentLoaded", async () => {
  logger.log('[Start] Inicializace protokolu...');
  initSignaturePad();

  const urlParams = new URLSearchParams(window.location.search);
  currentReklamaceId = urlParams.get('id');

  logger.log('[List] ID z URL:', currentReklamaceId);

  if (currentReklamaceId) {
    logger.log('ID nalezeno v URL');
    await loadReklamace(currentReklamaceId);
    // KRITICKÉ: Čekat na načtení fotek a kalkulace před pokračováním!
    // Bez await může uživatel kliknout na Export PDF dříve, než se fotky načtou
    await loadPhotosFromDatabase(currentReklamaceId);
    await loadKalkulaceFromDatabase(currentReklamaceId);
  } else {
    logger.warn('Chybí ID v URL - zkusím načíst z localStorage');
    await loadReklamace(null);

    if (currentReklamace && currentReklamace.id) {
      logger.log('ID nalezeno v načtených datech:', currentReklamace.id);
      currentReklamaceId = currentReklamace.id;
      // KRITICKÉ: Čekat na načtení fotek a kalkulace!
      await loadPhotosFromDatabase(currentReklamaceId);
      await loadKalkulaceFromDatabase(currentReklamaceId);
    } else {
      logger.error('ID se nepodařilo najít!');
    }
  }

  const today = new Date().toISOString().split('T')[0];
  document.getElementById("sign-date").value = today;

  setupAutoTranslate();
  setupTextareaAutoResize();

  // Spustit resize po nacteni dat s malym zpozdenim
  setTimeout(() => {
    if (window.triggerTextareaResize) {
      window.triggerTextareaResize();
    }
  }, 300);

  // Propojení polí Vyřešeno? a Nutné vyjádření prodejce
  const solvedSelect = document.getElementById("solved");
  const dealerSelect = document.getElementById("dealer");

  if (solvedSelect && dealerSelect) {
    solvedSelect.addEventListener("change", () => {
      if (solvedSelect.value === "ANO") {
        dealerSelect.value = "NE";
      } else if (solvedSelect.value === "NE") {
        dealerSelect.value = "ANO";
      }
    });
  }
});

function setupAutoTranslate() {
  const fields = ['description', 'problem', 'repair'];

  fields.forEach(field => {
    const czField = document.getElementById(field + '-cz');
    let timeout;

    czField.addEventListener('input', () => {
      clearTimeout(timeout);
      timeout = setTimeout(() => {
        if (czField.value.trim().length > 5) {
          translateField(field, true);
        }
      }, 2500); // Zvýšeno z 1500ms - prevence lagování na pomalejších mobilech
    });
  });
}

/**
 * Auto-resize textareas podle obsahu
 * Zajistuje, ze se textarea automaticky zvetsuje podle delky textu
 * Dulezite pro PDF export - text nebude orezan
 */
function setupTextareaAutoResize() {
  const textareas = document.querySelectorAll('.split-section textarea');

  function autoResize(textarea) {
    // Ulozit puvodni hodnotu
    const minHeight = parseInt(window.getComputedStyle(textarea).minHeight) || 60;

    // Reset vysky pro spravny vypocet scrollHeight
    textarea.style.height = 'auto';

    // Nastavit novou vysku podle obsahu (minimalne minHeight)
    const newHeight = Math.max(textarea.scrollHeight, minHeight);
    textarea.style.height = newHeight + 'px';
  }

  textareas.forEach(textarea => {
    // Auto-resize pri psani
    textarea.addEventListener('input', () => autoResize(textarea));

    // Auto-resize pri nacteni obsahu (pro predvyplnena data)
    textarea.addEventListener('change', () => autoResize(textarea));

    // Pocatecni resize pokud uz je obsah
    if (textarea.value.trim().length > 0) {
      // Maly delay pro zajisteni spravneho renderingu
      setTimeout(() => autoResize(textarea), 100);
    }
  });

  // Resize pri zmene orientace obrazovky (mobil)
  window.addEventListener('orientationchange', () => {
    setTimeout(() => {
      textareas.forEach(textarea => autoResize(textarea));
    }, 200);
  });

  // Resize pri zmene velikosti okna
  window.addEventListener('resize', () => {
    textareas.forEach(textarea => autoResize(textarea));
  });

  logger.log('[AutoResize] Textarea auto-resize aktivovan pro', textareas.length, 'poli');

  // Globalni funkce pro manualni spusteni resize (volana po nacteni dat)
  window.triggerTextareaResize = function() {
    textareas.forEach(textarea => {
      if (textarea.value.trim().length > 0) {
        autoResize(textarea);
      }
    });
  };
}

// Podpis přesunut do protokol-podpis.js
async function loadPhotosFromDatabase(customerId) {
  try {
    if (!customerId) {
      logger.warn('ID zákazníka nenalezeno');
      return;
    }

    logger.log('═══════════════════════════════════════');
    logger.log('🖼️ NAČÍTÁM FOTKY Z DATABÁZE');
    logger.log('═══════════════════════════════════════');
    logger.log('customerId:', customerId);

    // Načíst z API
    const response = await fetch(`api/get_photos_api.php?reklamace_id=${customerId}`);
    const data = await response.json();

    if (!data.success || data.total_photos === 0) {
      logger.log('Fotky nenalezeny v databázi');
      showNotif("warning", "Nebyly nalezeny fotky");
      logger.log('═══════════════════════════════════════');
      return;
    }

    logger.log('Fotky načteny z databáze!');
    const sections = data.sections;

    logger.log('📦 Sekce:', Object.keys(sections));

    const sectionLabels = {
      'before': 'BEFORE',
      'id': 'ID',
      'problem': 'DETAIL BUG',
      'damage_part': 'DAMAGE PART',
      'new_part': 'NEW PART',
      'repair': 'REPAIR',
      'after': 'AFTER',
      'photocustomer': 'CUSTOMER PHOTO',
      'pricelist': 'PRICELIST'
    };

    let totalPhotos = 0;
    let totalVideos = 0;

    const orderedSections = ['before', 'id', 'problem', 'damage_part', 'new_part', 'repair', 'after', 'photocustomer', 'pricelist'];

    orderedSections.forEach(sectionKey => {
      const sectionItems = sections[sectionKey];

      if (!Array.isArray(sectionItems) || sectionItems.length === 0) return;

      logger.log(`📁 Sekce "${sectionKey}": ${sectionItems.length} položek`);

      sectionItems.forEach(item => {
        if (item.type === 'video') {
          totalVideos++;
        } else if (item.type === 'image' || !item.type) {
          if (item.data) {
            attachedPhotos.push({
              data: item.data,
              label: sectionLabels[sectionKey] || sectionKey.toUpperCase(),
              section: sectionKey
            });
            totalPhotos++;
          }
        }
      });
    });

    logger.log(`[Stats] CELKEM: ${totalPhotos} fotek, ${totalVideos} videí`);

    if (attachedPhotos.length > 0) {
      const previewPhotos = attachedPhotos.map(p => typeof p === 'string' ? p : p.data);
      renderPhotoPreview(previewPhotos);
      showNotif("success", `Načteno ${totalPhotos} fotek`);
      logger.log('Fotky úspěšně načteny s popisky');
    } else {
      logger.log('Žádné fotky k zobrazení');
      showNotif("info", "Žádné fotky");
    }

    logger.log('═══════════════════════════════════════');

  } catch (error) {
    logger.error('Chyba při načítání fotek:', error);
    showNotif("error", "Chyba načítání fotek");
  }
}

async function loadKalkulaceFromDatabase(customerId) {
  try {
    if (!customerId) {
      logger.warn('ID zákazníka nenalezeno - kalkulace nebude načtena');
      return;
    }

    logger.log('═══════════════════════════════════════');
    logger.log('💶 NAČÍTÁM KALKULACI Z DATABÁZE');
    logger.log('═══════════════════════════════════════');
    logger.log('customerId:', customerId);

    // Načíst z API
    const response = await fetch(`api/get_kalkulace_api.php?reklamace_id=${customerId}`);
    const data = await response.json();

    if (!data.success) {
      logger.log('Kalkulace nenalezena v databázi:', data.error);
      logger.log('═══════════════════════════════════════');
      return;
    }

    if (!data.has_kalkulace) {
      logger.log('ℹ️ Kalkulace nebyla vytvořena pro tuto reklamaci');
      logger.log('═══════════════════════════════════════');
      return;
    }

    logger.log('Kalkulace načtena z databáze!');
    window.kalkulaceData = data.kalkulace;

    logger.log('📦 Kalkulace data:', window.kalkulaceData);
    logger.log('💰 Celková cena:', window.kalkulaceData.celkovaCena, '€');
    logger.log('[Loc] Adresa:', window.kalkulaceData.adresa);
    logger.log('📏 Vzdálenost:', window.kalkulaceData.vzdalenost, 'km');
    logger.log('═══════════════════════════════════════');

    // Zobrazit notifikaci
    showNotif("success", `Kalkulace načtena (${window.kalkulaceData.celkovaCena.toFixed(2)} €)`);

  } catch (error) {
    logger.error('Chyba při načítání kalkulace:', error);
    showNotif("error", "Chyba načítání kalkulace");
  }
}

async function loadReklamace(id) {
  showLoading(true);

  try {
    logger.log('Načítám data zákazníka...');
    logger.log('[List] ID z URL:', id);

    const localData = localStorage.getItem('currentCustomer');

    if (localData) {
      logger.log('Data nalezena v localStorage');
      const customer = JSON.parse(localData);
      logger.log('📦 Data zákazníka:', customer);

      // KONTROLA OPRÁVNĚNÍ
      const currentUser = JSON.parse(localStorage.getItem('currentUser') || '{}');
      logger.log('[User] Aktuální uživatel:', currentUser.name, '| Role:', currentUser.role);

      if (currentUser.role === 'prodejce') {
        // Prodejce může vidět jen své zakázky
        if (customer.zpracoval_id && customer.zpracoval_id !== currentUser.id) {
          showNotif('error', 'Nemáte oprávnění k této zakázce');
          setTimeout(() => window.location.href = 'seznam.php', 2000);
          showLoading(false);
          return;
        }
      }
      // Admin a technik vidí všechny zakázky - bez kontroly
      logger.log('Oprávnění potvrzeno');

      const customerName = customer.jmeno || customer.zakaznik || '';
      let ulice = '', mesto = '', psc = '';

      if (customer.adresa) {
        const parts = customer.adresa.split(',').map(s => s.trim());
        ulice = parts[0] || '';
        mesto = parts[1] || '';
        psc = parts[2] || '';
        logger.log('[Loc] Adresa (nový formát):', { ulice, mesto, psc });
      } else {
        ulice = customer.ulice || '';
        mesto = customer.mesto || '';
        psc = customer.psc || '';
        logger.log('[Loc] Adresa (starý formát):', { ulice, mesto, psc });
      }

      logger.log('[Edit] Vyplňuji formulář...');
      document.getElementById("order-number").value = customer.reklamace_id || "";
      document.getElementById("claim-number").value = customer.cislo || "";
      document.getElementById("customer").value = customerName;
      document.getElementById("address").value = customer.adresa || `${ulice}, ${mesto}, ${psc}`;
      document.getElementById("phone").value = customer.telefon || "";
      document.getElementById("email").value = customer.email || "";
      document.getElementById("brand").value = customer.zadavatel_jmeno || customer.created_by_name || "";
      document.getElementById("model").value = customer.model || "";
      document.getElementById("description-cz").value = customer.popis_problemu || "";
      // Nastavit technika v SELECT - buď uložený technik, nebo přihlášený uživatel
      const technikValue = customer.technik || customer.prihlaseny_technik || "";
      if (technikValue) {
        document.getElementById("technician").value = technikValue;
      }

      currentReklamace = customer;
      currentReklamaceId = customer.reklamace_id || customer.cislo || customer.id;

      logger.log('Data zákazníka úspěšně načtena a vyplněna');
      showNotif("success", "Data načtena");
      showLoading(false);
      return;
    }

    logger.warn('Data v localStorage nenalezena');

    if (!id) {
      showNotif("error", "Chybí ID reklamace");
      showLoading(false);
      return;
    }

    const csrfToken = await fetchCsrfToken();
    const response = await fetch('api/protokol_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      credentials: 'same-origin',
      body: JSON.stringify({
        action: 'load_reklamace',
        id,
        csrf_token: csrfToken
      })
    });

    if (!response.ok) {
      const errorText = await response.text();
      logger.error('Load reklamace error:', response.status, errorText);
      try {
        const errorJson = JSON.parse(errorText);
        logger.error('Load error detail:', errorJson);
        throw new Error(errorJson.error || errorJson.message || `Server error ${response.status}`);
      } catch (parseErr) {
        throw new Error(`Server error ${response.status}: ${errorText.substring(0, 200)}`);
      }
    }

    const result = await response.json();

    if (result.status === 'success') {
      logger.log('Data načtena z API');
      currentReklamace = result.reklamace;

      const customerName = currentReklamace.jmeno || currentReklamace.zakaznik || '';
      let ulice = '', mesto = '', psc = '';

      if (currentReklamace.adresa) {
        const parts = currentReklamace.adresa.split(',').map(s => s.trim());
        ulice = parts[0] || '';
        mesto = parts[1] || '';
        psc = parts[2] || '';
      } else {
        ulice = currentReklamace.ulice || '';
        mesto = currentReklamace.mesto || '';
        psc = currentReklamace.psc || '';
      }

      document.getElementById("order-number").value = currentReklamace.reklamace_id || "";
      document.getElementById("claim-number").value = currentReklamace.cislo || "";
      document.getElementById("customer").value = customerName;
      document.getElementById("address").value = currentReklamace.adresa || `${ulice}, ${mesto}, ${psc}`;
      document.getElementById("phone").value = currentReklamace.telefon || "";
      document.getElementById("email").value = currentReklamace.email || "";
      document.getElementById("brand").value = currentReklamace.zadavatel_jmeno || currentReklamace.created_by_name || "";
      document.getElementById("model").value = currentReklamace.model || "";
      document.getElementById("description-cz").value = currentReklamace.popis_problemu || "";
      // Nastavit technika v SELECT - buď uložený technik, nebo přihlášený uživatel
      const technikValueApi = currentReklamace.technik || currentReklamace.prihlaseny_technik || "";
      if (technikValueApi) {
        document.getElementById("technician").value = technikValueApi;
      }
      showNotif("success", "Reklamace načtena");
    } else {
      showNotif("error", result.message || "Reklamace nenalezena");
    }
  } catch (error) {
    logger.error('Chyba načítání:', error);
    showNotif("error", "Chyba načítání");
  } finally {
    showLoading(false);
  }
}

function showLoading(show) {
  document.getElementById("loadingOverlay").classList.toggle("show", show);
}

/**
 * Zobrazí WGS loading dialog s přesýpacími hodinami
 * @param {boolean} show - Zobrazit/skrýt dialog
 * @param {string} message - Hlavní zpráva (např. "Připravuji fotky...")
 * @param {string} submessage - Volitelná sekundární zpráva (např. "15 fotografií")
 */
function showLoadingWithMessage(show, message = 'Načítání...', submessage = '') {
  const overlay = document.getElementById("loadingOverlay");
  const textElement = document.getElementById("loadingText");
  const subtextElement = document.getElementById("loadingSubtext");

  if (show) {
    // Odebrat inline style aby CSS fungoval
    overlay.style.display = '';
    overlay.classList.add("show");

    // Nastavit hlavní zprávu
    if (textElement) {
      textElement.textContent = message;
    }

    // Nastavit sekundární zprávu (pokud existuje)
    if (subtextElement) {
      if (submessage) {
        subtextElement.textContent = submessage;
        subtextElement.style.display = 'block';
      } else {
        subtextElement.style.display = 'none';
      }
    }
  } else {
    overlay.classList.remove("show");
  }
}

function showNotif(type, message) {
  const notif = document.getElementById("notif");
  notif.className = `notif ${type}`;
  notif.textContent = message;
  notif.classList.add("show");
  setTimeout(() => notif.classList.remove("show"), 3000);
}

async function attachPhotos() {
  // Přejít na photocustomer pro přidání fotek k existující fotodokumentaci
  window.location.href = 'photocustomer.php?pridej=true';
}

async function compressImage(file, maxMB = 0.6) {
  const img = await loadImage(URL.createObjectURL(file));
  const canvas = document.createElement("canvas");
  const ctx = canvas.getContext("2d");
  const maxW = 1200;
  const s = Math.min(1, maxW / img.width);
  canvas.width = img.width * s;
  canvas.height = img.height * s;
  ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
  let q = 0.85;
  let blob = await new Promise(r => canvas.toBlob(r, "image/jpeg", q));
  while (blob.size > maxMB * 1024 * 1024 && q > 0.4) {
    q -= 0.05;
    blob = await new Promise(r => canvas.toBlob(r, "image/jpeg", q));
  }
  return blob;
}

function loadImage(src) {
  return new Promise((r, j) => {
    const i = new Image();
    i.onload = () => r(i);
    i.onerror = j;
    i.src = src;
  });
}

// Step 134: Use centralized toBase64 from utils.js if available
function toBase64(blob) {
  if (window.Utils && window.Utils.toBase64) {
    return window.Utils.toBase64(blob);
  }
  // Fallback
  return new Promise((r, j) => {
    const fr = new FileReader();
    fr.onload = () => r(fr.result);
    fr.onerror = j;
    fr.readAsDataURL(blob);
  });
}

function renderPhotoPreview(arr) {
  let cont = document.getElementById("photoPreviewContainer");
  if (!cont) {
    cont = document.createElement("div");
    cont.id = "photoPreviewContainer";
    document.querySelector(".wrapper").appendChild(cont);
  }
  cont.innerHTML = `<h3>${t('attached_photos_count').replace('{count}', arr.length)}</h3><div id="photoGrid"></div>`;
  const grid = cont.querySelector("#photoGrid");
  arr.forEach(src => {
    const photoData = typeof src === 'string' ? src : src.data;

    // Wrapper pro touch feedback (scale 0.95 on :active)
    const wrapper = document.createElement("div");
    wrapper.className = "photo-thumb-wrapper";

    const img = document.createElement("img");
    img.src = photoData;

    // Event delegation místo inline onclick
    wrapper.addEventListener('click', () => {
      window.open(photoData, "_blank");
    });

    wrapper.appendChild(img);
    grid.appendChild(wrapper);
  });
}

// PDF generování přesunuto do protokol-pdf.js
async function sendToCustomer() {
  try {
    // FÁZE 1: Generování kompletního PDF (protokol + fotky) pro NÁHLED
    showLoadingWithMessage(true, 'Připravuji protokol...', 'Prosím čekejte');
    logger.log('[List] Generuji kompletní PDF pro náhled před odesláním...');
    logger.log('💰 Kontrola kalkulace - window.kalkulaceData:', window.kalkulaceData);

    // Vytvořit JEDNO PDF s protokolem
    const doc = await generateProtocolPDF();

    // Pokud existuje kalkulace, přidat PRICELIST
    if (window.kalkulaceData) {
      showLoadingWithMessage(true, 'Přidávám pricelist...', `Celková cena: ${window.kalkulaceData.celkovaCena.toFixed(2)} €`);
      logger.log('Kalkulace nalezena - přidávám PRICELIST...');
      logger.log('[Stats] Kalkulace data:', window.kalkulaceData);

      // OPRAVA: Převést data z rozpis struktury do pole služeb a dílů
      const needsTransform3 = !!window.kalkulaceData.rozpis && (!Array.isArray(window.kalkulaceData.sluzby) || window.kalkulaceData.sluzby.length === 0 || !Array.isArray(window.kalkulaceData.dilyPrace) || window.kalkulaceData.dilyPrace.length === 0); if (needsTransform3) {
        window.kalkulaceData.sluzby = [];
        window.kalkulaceData.dilyPrace = [];
        const rozpis = window.kalkulaceData.rozpis;
        const CENY = { diagnostika: 110, prvniDil: 205, dalsiDil: 70, zakladniSazba: 165, mechanismusPriplatek: 45, druhaOsoba: 95, material: 50, vyzvednutiSklad: 10 };

        // FALLBACK: Kontrola jestli rozpis je prázdný
        const maPrazdnyRozpis =
          (!rozpis.diagnostika || rozpis.diagnostika === 0) &&
          (!rozpis.calouneni || (typeof rozpis.calouneni === 'object' &&
            ((rozpis.calouneni.sedaky || 0) + (rozpis.calouneni.operky || 0) +
             (rozpis.calouneni.podrucky || 0) + (rozpis.calouneni.panely || 0)) === 0)) &&
          (!rozpis.mechanika || (typeof rozpis.mechanika === 'object' &&
            ((rozpis.mechanika.relax || 0) + (rozpis.mechanika.vysuv || 0)) === 0)) &&
          (!rozpis.doplnky || (typeof rozpis.doplnky === 'object' &&
            !rozpis.doplnky.material && !rozpis.doplnky.vyzvednutiSklad));

        if (maPrazdnyRozpis) {
          // Rozpis je prázdný → vytvořit obecnou položku
          logger.log('⚠️ Rozpis je prázdný v sendToCustomer() - vytvářím obecnou položku');
          const cenaBezDopravy = window.kalkulaceData.celkovaCena - (window.kalkulaceData.dopravne || 0);
          const typServisuText = {
            'calouneni': 'Servis čalounění',
            'mechanika': 'Servis mechaniky',
            'doplnky': 'Další služby'
          }[window.kalkulaceData.typServisu] || 'Servisní práce';

          if (cenaBezDopravy > 0) {
            window.kalkulaceData.sluzby.push({
              nazev: typServisuText,
              cena: cenaBezDopravy,
              pocet: 1
            });
          }
        } else {
          // Normální transformace z rozpisu
          if (rozpis.diagnostika && rozpis.diagnostika > 0) { window.kalkulaceData.sluzby.push({ nazev: 'Inspekce / diagnostika', cena: rozpis.diagnostika, pocet: 1 }); }
          if (rozpis.calouneni) {
            const { sedaky, operky, podrucky, panely, pocetProduktu } = rozpis.calouneni;
            const celkemDilu = (sedaky || 0) + (operky || 0) + (podrucky || 0) + (panely || 0);
            if (celkemDilu > 0) {
              const skutecnyPocetProduktu = Math.min(pocetProduktu || 1, celkemDilu || 1);
              let cenaDilu = skutecnyPocetProduktu === 1 ? (celkemDilu === 1 ? CENY.prvniDil : CENY.prvniDil + (celkemDilu - 1) * CENY.dalsiDil) : (skutecnyPocetProduktu * CENY.prvniDil) + ((celkemDilu - skutecnyPocetProduktu) * CENY.dalsiDil);
              window.kalkulaceData.dilyPrace.push({ nazev: `Čalounické práce (${celkemDilu} ${celkemDilu === 1 ? 'díl' : celkemDilu <= 4 ? 'díly' : 'dílů'})`, cena: cenaDilu, pocet: celkemDilu });
            }
          }
          if (rozpis.mechanika) {
            const { relax, vysuv } = rozpis.mechanika;
            const celkemMechanismu = (relax || 0) + (vysuv || 0);
            if (celkemMechanismu > 0) { window.kalkulaceData.dilyPrace.push({ nazev: `Mechanické opravy (${celkemMechanismu} ${celkemMechanismu === 1 ? 'mechanismus' : 'mechanismů'})`, cena: celkemMechanismu * CENY.mechanismusPriplatek, pocet: celkemMechanismu }); }
            if (celkemMechanismu > 0 && window.kalkulaceData.typServisu === 'mechanika') { window.kalkulaceData.sluzby.push({ nazev: 'Základní servisní sazba', cena: CENY.zakladniSazba, pocet: 1 }); }
          }
          if (rozpis.doplnky) {
            if (rozpis.doplnky.material) { window.kalkulaceData.sluzby.push({ nazev: 'Materiál dodán od WGS', cena: CENY.material, pocet: 1 }); }
            if (rozpis.doplnky.vyzvednutiSklad) { window.kalkulaceData.sluzby.push({ nazev: 'Vyzvednutí dílu na skladě', cena: CENY.vyzvednutiSklad, pocet: 1 }); }
          }
        }
      }

      // NOVÁ STRÁNKA: PRICELIST
      doc.addPage();

      const pageWidth = doc.internal.pageSize.getWidth();
      const pageHeight = doc.internal.pageSize.getHeight();
      const margin = 15;
      let yPos = margin;

      // Helper pro bezpečný výpis textu s českými znaky
      const pdfText = (text, x, y, options = {}) => window.pdfTextSafe(doc, text, x, y, options);

      // === HLAVIČKA ===
      doc.setFontSize(20);
      doc.setFont('Roboto', 'normal');
      doc.setTextColor(0, 0, 0);
      pdfText('PRICELIST', pageWidth / 2, yPos, { align: 'center' });
      yPos += 15;

      // === ÚDAJE ZÁKAZNÍKA ===
      const zakaznikJmeno = document.getElementById('customer')?.value || 'N/A';
      const zakaznikAdresa = window.kalkulaceData.adresa || document.getElementById('address')?.value || 'N/A';
      const zakaznikTelefon = document.getElementById('phone')?.value || '';
      const zakaznikEmail = document.getElementById('email')?.value || '';
      const reklamaceCislo = document.getElementById('claim-number')?.value || '';

      doc.setFontSize(10);
      doc.setFont('Roboto', 'normal');
      doc.setTextColor(0, 0, 0);

      if (reklamaceCislo) {
        pdfText(`Číslo reklamace: ${reklamaceCislo}`, margin, yPos);
        yPos += 6;
      }

      doc.setFont('Roboto', 'normal');
      pdfText(`Zákazník: ${zakaznikJmeno}`, margin, yPos);
      yPos += 6;

      doc.setFont('Roboto', 'normal');
      pdfText(`Adresa: ${zakaznikAdresa}`, margin, yPos);
      yPos += 6;

      if (zakaznikTelefon) {
        pdfText(`Telefon: ${zakaznikTelefon}`, margin, yPos);
        yPos += 6;
      }

      if (zakaznikEmail) {
        pdfText(`Email: ${zakaznikEmail}`, margin, yPos);
        yPos += 6;
      }

      yPos += 5;

      // Čára oddělení
      doc.setLineWidth(0.5);
      doc.setDrawColor(0, 0, 0);
      doc.line(margin, yPos, pageWidth - margin, yPos);
      yPos += 10;

      // === CENOTVORBA ===
      doc.setFontSize(14);
      doc.setFont('Roboto', 'normal');
      pdfText('Rozpis cen', margin, yPos);
      yPos += 10;

      doc.setFontSize(10);
      doc.setFont('Roboto', 'normal');

      // Dopravné
      if (!window.kalkulaceData.reklamaceBezDopravy) {
        const dopravneText = `Dopravné (${window.kalkulaceData.vzdalenost} km)`;
        const dopravneCena = window.kalkulaceData.dopravne.toFixed(2);
        pdfText(dopravneText, margin, yPos);
        pdfText(`${dopravneCena} EUR`, pageWidth - margin - 30, yPos);
        yPos += 7;
      } else {
        pdfText('Dopravné (reklamace)', margin, yPos);
        pdfText('0.00 EUR', pageWidth - margin - 30, yPos);
        yPos += 7;
      }

      // Služby - DETAILNÍ ROZPIS
      logger.log('🖨️ Vykreslování SLUŽBY:', window.kalkulaceData.sluzby);
      logger.log('🖨️ Vykreslování DÍLY:', window.kalkulaceData.dilyPrace);

      if (window.kalkulaceData.sluzby && window.kalkulaceData.sluzby.length > 0) {
        logger.log('✅ Vykresluju SLUŽBY (počet:', window.kalkulaceData.sluzby.length + ')');
        yPos += 3;
        doc.setFont('Roboto', 'normal');
        pdfText('Služby:', margin, yPos);
        yPos += 7;

        doc.setFont('Roboto', 'normal');
        window.kalkulaceData.sluzby.forEach(sluzba => {
          // Název služby
          pdfText(`  ${sluzba.nazev}`, margin, yPos);
          yPos += 6;

          // Detailní rozpis pokud má počet
          if (sluzba.pocet && sluzba.pocet > 1) {
            const jednotkovaCena = (sluzba.cena / sluzba.pocet).toFixed(2);
            const celkovaCena = sluzba.cena.toFixed(2);
            const detail = `    ${sluzba.pocet} ks × ${jednotkovaCena} EUR = ${celkovaCena} EUR`;
            doc.setFont('Roboto', 'normal');
            doc.setFontSize(9);
            pdfText(detail, margin + 5, yPos);
            doc.setFontSize(10);
            doc.setFont('Roboto', 'normal');
            yPos += 7;
          } else {
            const cena = sluzba.cena.toFixed(2);
            pdfText(`${cena} EUR`, pageWidth - margin - 30, yPos - 6);
            yPos += 1;
          }
        });

        yPos += 3;
      } else {
        logger.warn('❌ SLUŽBY nejsou vykresleny (prázdné pole nebo neexistuje)');
      }

      // Díly a práce - DETAILNÍ ROZPIS
      if (window.kalkulaceData.dilyPrace && window.kalkulaceData.dilyPrace.length > 0) {
        logger.log('✅ Vykresluju DÍLY A PRÁCE (počet:', window.kalkulaceData.dilyPrace.length + ')');
        yPos += 3;
        doc.setFont('Roboto', 'normal');
        pdfText('Díly a práce:', margin, yPos);
        yPos += 7;

        doc.setFont('Roboto', 'normal');
        window.kalkulaceData.dilyPrace.forEach(polozka => {
          // Název položky + cena vpravo
          const celkovaCena = polozka.cena.toFixed(2);
          pdfText(`  ${polozka.nazev}`, margin, yPos);
          pdfText(`${celkovaCena} EUR`, pageWidth - margin - 30, yPos);
          yPos += 6;

          // Detailní rozpis (menším písmem, bez celkové ceny)
          const jednotkovaCena = polozka.pocet > 1 ? (polozka.cena / polozka.pocet).toFixed(2) : polozka.cena.toFixed(2);
          const detail = `    ${polozka.pocet} ks × ${jednotkovaCena} EUR`;
          doc.setFont('Roboto', 'normal');
          doc.setFontSize(9);
          pdfText(detail, margin + 5, yPos);
          doc.setFontSize(10);
          doc.setFont('Roboto', 'normal');
          yPos += 7;
        });

        yPos += 3;
      } else {
        logger.warn('❌ DÍLY A PRÁCE nejsou vykresleny (prázdné pole nebo neexistuje)');
      }

      // Příplatky
      if (window.kalkulaceData.tezkyNabytek) {
        pdfText('Příplatek: Těžký nábytek (nad 50 kg)', margin, yPos);
        pdfText(`${CENY.druhaOsoba.toFixed(2)} EUR`, pageWidth - margin - 30, yPos);
        yPos += 7;
      }

      if (window.kalkulaceData.druhaOsoba) {
        pdfText('Příplatek: Druhá osoba', margin, yPos);
        pdfText(`${CENY.druhaOsoba.toFixed(2)} EUR`, pageWidth - margin - 30, yPos);
        yPos += 7;
      }

      yPos += 5;

      // Čára před celkovou cenou
      doc.setLineWidth(0.3);
      doc.line(margin, yPos, pageWidth - margin, yPos);
      yPos += 8;

      // === CELKOVÁ CENA ===
      doc.setFontSize(14);
      doc.setFont('Roboto', 'normal');
      doc.setTextColor(0, 0, 0);
      pdfText('CELKEM:', margin, yPos);
      pdfText(`${window.kalkulaceData.celkovaCena.toFixed(2)} EUR`, pageWidth - margin - 40, yPos);

      logger.log(`PRICELIST přidán (${window.kalkulaceData.celkovaCena.toFixed(2)} €)`);
    } else {
      logger.warn('Kalkulace nenalezena - PRICELIST nebude v emailu');
      logger.warn('   Zkontrolujte, zda byla kalkulace vytvořena a uložena');
    }

    // Pokud jsou fotky, přidat fotodokumentaci na KONEC protokolu (stejně jako exportBothPDFs)
    if (attachedPhotos.length > 0) {
      showLoadingWithMessage(true, 'Přidávám fotografie...', `${attachedPhotos.length} fotografií`);
      logger.log('[Photo] Přidávám fotodokumentaci...');

      const pageWidth = doc.internal.pageSize.getWidth();
      const pageHeight = doc.internal.pageSize.getHeight();
      const margin = 10;

      // NOVÁ STRÁNKA: Fotodokumentace začíná
      doc.addPage();

      // Hlavička fotodokumentace
      doc.setFontSize(16);
      doc.setFont('Roboto', 'normal');
      doc.text('FOTODOKUMENTACE', pageWidth / 2, 20, { align: 'center' });

      let yPos = 35;

      // Informace o zákazníkovi
      doc.setFontSize(10);
      doc.setFont('Roboto', 'normal');

      const customerInfo = [
        `Cislo reklamace: ${document.getElementById('claim-number')?.value || 'N/A'}`,
        `Datum: ${document.getElementById('sign-date')?.value || new Date().toLocaleDateString('cs-CZ')}`
      ];

      customerInfo.forEach(line => {
        doc.text(line, margin, yPos);
        yPos += 6;
      });

      yPos += 5;

      // Čára
      doc.setLineWidth(0.5);
      doc.line(margin, yPos, pageWidth - margin, yPos);
      yPos += 10;

      // Nadpis indexu
      doc.setFontSize(12);
      doc.setFont('Roboto', 'normal');
      doc.text('INDEX PHOTO', margin, yPos);
      yPos += 8;

      // Index fotek - miniaturní náhledy
      doc.setFontSize(8);
      doc.setFont('Roboto', 'normal');

      const thumbSize = 25;
      const thumbGap = 5;
      const thumbsPerRow = Math.floor((pageWidth - 2 * margin) / (thumbSize + thumbGap));

      for (let i = 0; i < attachedPhotos.length; i++) {
        const photo = attachedPhotos[i];
        const photoData = typeof photo === 'string' ? photo : photo.data;
        const photoLabel = typeof photo === 'object' ? photo.label : `Fotka ${i + 1}`;

        const col = i % thumbsPerRow;
        const row = Math.floor(i / thumbsPerRow);

        const x = margin + (col * (thumbSize + thumbGap));
        const y = yPos + (row * (thumbSize + thumbGap + 4));

        if (y + thumbSize > pageHeight - margin) {
          doc.addPage();
          yPos = 20;
          continue;
        }

        try {
          doc.addImage(photoData, "JPEG", x, y, thumbSize, thumbSize, undefined, 'FAST');
          doc.setFontSize(7);
          doc.text(`${i + 1}. ${photoLabel}`, x, y + thumbSize + 3, { maxWidth: thumbSize });
        } catch (err) {
          logger.warn(`Nelze přidat miniaturu ${i + 1}`);
        }
      }

      logger.log(`Index ${attachedPhotos.length} fotek vytvořen`);

      // DALŠÍ STRÁNKY: Velké fotky 4 na stránku
      doc.addPage();

      const gap = 5;
      const labelHeight = 5;
      const photosPerPage = 4;
      const cols = 2;
      const rows = 2;

      const availableWidth = pageWidth - (2 * margin) - gap;
      const availableHeight = pageHeight - (2 * margin) - gap;
      const cellWidth = availableWidth / cols;
      const cellHeight = availableHeight / rows;

      for (let i = 0; i < attachedPhotos.length; i++) {
        const photo = attachedPhotos[i];
        const photoData = typeof photo === 'string' ? photo : photo.data;
        const photoLabel = typeof photo === 'object' ? photo.label : '';

        if (i > 0 && i % photosPerPage === 0) {
          doc.addPage();
        }

        const indexOnPage = i % photosPerPage;
        const col = indexOnPage % cols;
        const row = Math.floor(indexOnPage / cols);

        const x = margin + (col * (cellWidth + gap));
        const y = margin + (row * (cellHeight + gap));

        const photoY = y + labelHeight;
        const maxPhotoWidth = cellWidth;
        const maxPhotoHeight = cellHeight - labelHeight;

        try {
          const img = new Image();
          img.src = photoData;

          await new Promise((resolve) => {
            img.onload = resolve;
            setTimeout(resolve, 100);
          });

          let imgWidth = img.width || 1000;
          let imgHeight = img.height || 1000;

          const imgRatio = imgWidth / imgHeight;
          const cellRatio = maxPhotoWidth / maxPhotoHeight;

          let finalWidth, finalHeight;

          if (imgRatio > cellRatio) {
            finalWidth = maxPhotoWidth;
            finalHeight = maxPhotoWidth / imgRatio;
          } else {
            finalHeight = maxPhotoHeight;
            finalWidth = maxPhotoHeight * imgRatio;
          }

          const offsetX = (maxPhotoWidth - finalWidth) / 2;
          const offsetY = (maxPhotoHeight - finalHeight) / 2;

          // Label přesně nad fotkou (ne nad buňkou)
          if (photoLabel) {
            doc.setFontSize(8);
            doc.setFont('Roboto', 'normal');
            doc.setTextColor(0, 0, 0);
            doc.text(photoLabel, x + offsetX, photoY + offsetY - 2);
          }

          doc.addImage(photoData, "JPEG", x + offsetX, photoY + offsetY, finalWidth, finalHeight, undefined, 'MEDIUM');

          logger.log(`  [Photo] Fotka ${i + 1}/${attachedPhotos.length} - ${photoLabel}`);

        } catch (err) {
          logger.warn(`Chyba fotky ${i + 1}`);

          // Fallback: label ve středu buňky
          if (photoLabel) {
            doc.setFontSize(8);
            doc.setFont('Roboto', 'normal');
            doc.setTextColor(0, 0, 0);
            doc.text(photoLabel, x, photoY - 2);
          }

          doc.addImage(photoData, "JPEG", x, photoY, maxPhotoWidth, maxPhotoHeight, undefined, 'MEDIUM');
        }
      }

      logger.log(`Fotodokumentace přidána (${attachedPhotos.length} fotek)`);
    }

    // Konverze na base64 a uložení pro odeslání
    const completePdfBase64 = doc.output("datauristring").split(",")[1];

    // Uložit pro odeslání
    cachedPdfDoc = doc;
    cachedPdfBase64 = completePdfBase64;
    pdfPreviewContext = 'send';

    // PERFORMANCE: Rovnou odeslat bez preview modalu
    logger.log('📧 Odesílám email přímo bez náhledu...');
    await potvrditAOdeslat();

  } catch (error) {
    logger.error('Chyba při generování PDF:', error);
    showNotif("error", "Chyba při vytváření PDF");
    showLoadingWithMessage(false);
  }
}

/**
 * Potvrzení a odeslání emailu se zákazníkovi
 * Volá se ROVNOU z sendToCustomer() bez preview modalu
 */
async function potvrditAOdeslat() {
  if (!cachedPdfBase64) {
    showNotif("error", "PDF není dostupné");
    return;
  }

  try {
    // PERFORMANCE: Preview modal vypnut, rovnou odesílání emailu
    showLoadingWithMessage(true, 'Odesílám email...', 'Zákazníkovi se odesílá kompletní PDF');
    logger.log('📧 Odesílám PDF zákazníkovi...');

    const csrfToken = await fetchCsrfToken();

    const response = await fetch("api/protokol_api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "send_email",
        reklamace_id: currentReklamaceId,
        complete_pdf: cachedPdfBase64,
        csrf_token: csrfToken
      })
    });

    // Detailní výpis chyby pokud response není OK
    if (!response.ok) {
      const errorText = await response.text();
      logger.error('Server error:', response.status, errorText);
      try {
        const errorJson = JSON.parse(errorText);
        logger.error('Error detail:', errorJson);
        throw new Error(errorJson.error || errorJson.message || `Server error ${response.status}`);
      } catch (parseErr) {
        throw new Error(`Server error ${response.status}: ${errorText.substring(0, 200)}`);
      }
    }

    const result = await response.json();

    if (result.status === 'success') {
      // Neonový toast pro odeslání emailu
      if (typeof WGSToast !== 'undefined') {
        WGSToast.zobrazit('Email odeslán zákazníkovi', { titulek: 'WGS' });
      } else {
        showNotif("success", "Email odeslán zákazníkovi");
      }
      await saveProtokolToDB();

      logger.log('[List] Označuji reklamaci jako hotovou...');
      const markResponse = await fetch('app/controllers/save.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({
          action: 'update',
          id: currentReklamaceId,
          mark_as_completed: '1',
          csrf_token: csrfToken
        })
      });

      const markResult = await markResponse.json();

      if (markResult.status === 'success') {
        logger.log('Reklamace označena jako hotová');
      } else {
        logger.warn('Nepodařilo se označit jako hotovou:', markResult.message);
      }

      if (currentReklamaceId) {
        const key = 'photoSections_' + currentReklamaceId;
        const pdfKey = 'photosPDF_' + currentReklamaceId;
        localStorage.removeItem(key);
        localStorage.removeItem(pdfKey);
        localStorage.removeItem('photosReadyForProtocol');
        localStorage.removeItem('photosCustomerId');
        logger.log('Fotky a PDF vymazány z localStorage');
      }

      setTimeout(() => {
        window.location.href = 'seznam.php';
      }, 2000);

    } else {
      showNotif("error", result.message || "Chyba odesílání");
    }

  } catch (error) {
    logger.error(error);
    showNotif("error", "Chyba odesílání: " + error.message);
  } finally {
    showLoadingWithMessage(false);
  }
}

async function saveProtokolToDB() {
  try {
    const csrfToken = await fetchCsrfToken();

    // Získat celkovou cenu z formuláře
    const cenaCelkem = parseFloat(document.getElementById("price-total").value) || 0;

    const response = await fetch("api/protokol_api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "save_protokol",
        reklamace_id: currentReklamaceId,
        problem_description: document.getElementById("problem-cz").value,
        repair_proposal: document.getElementById("repair-cz").value,
        solved: document.getElementById("solved").value,
        dealer: document.getElementById("dealer")?.value || "NE",
        technician: document.getElementById("technician").value,
        cena_celkem: cenaCelkem,
        csrf_token: csrfToken
      })
    });

    const result = await response.json();

    if (result.status === 'success') {
      logger.log("Protokol uložen do DB (včetně cenových údajů)");
    }
  } catch (error) {
    logger.error("Chyba ukládání:", error);
  }
}

// debounce přesunuto do utils.js (Step 108)
// Funkce je dostupná jako window.debounce() nebo Utils.debounce()

// Funkce pro překlad textu přes server-side proxy (MyMemory API)
// Překlad přesunut do protokol-preklad.js

// === UNIVERSAL EVENT DELEGATION FOR REMOVED INLINE HANDLERS ===
document.addEventListener('DOMContentLoaded', () => {
  // Handle data-action buttons
  // POZOR: ActionRegistry v utils.js již obsluhuje data-action!
  // Tento handler zpracovává pouze akce NEZAREGISTROVANÉ v ActionRegistry
  document.addEventListener('click', (e) => {
    const target = e.target.closest('[data-action]');
    if (!target) return;

    const action = target.getAttribute('data-action');

    // Special cases
    if (action === 'reload') {
      location.reload();
      return;
    }

    // Přeskočit akce registrované v ActionRegistry (ty už obsluhuje utils.js)
    if (typeof window.Utils !== 'undefined' &&
        window.Utils.ActionRegistry &&
        window.Utils.ActionRegistry.handlers &&
        window.Utils.ActionRegistry.handlers[action]) {
      return; // ActionRegistry to už zpracoval
    }

    // Try to call function if it exists (pouze pro nezaregistrované akce)
    if (typeof window[action] === 'function') {
      window[action]();
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

// Modal schválení zákazníkem přesunut do protokol-schvaleni.js
// Autosave přesunut do protokol-autosave.js
