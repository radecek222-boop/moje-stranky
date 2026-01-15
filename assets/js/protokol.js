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

// === NOTIFIKACE ===
function showNotification(message, type = 'info') {
  const notification = document.getElementById('notif');
  if (!notification) {
    return;
  }

  notification.textContent = message;
  notification.className = `notif ${type}`;
  notification.classList.remove('hidden');
  notification.style.opacity = '1';

  // Tap-to-dismiss (iOS touch feedback)
  const skryjNotifikaci = () => {
    notification.style.opacity = '0';
    setTimeout(() => {
      notification.classList.add('hidden');
    }, 300);
  };

  // Click pro okamžité zavření
  notification.onclick = skryjNotifikaci;

  // Auto-hide po 3 sekundách (kromě error)
  if (type !== 'error') {
    setTimeout(skryjNotifikaci, 3000);
  } else {
    // Error zprávy se skryjí po 5 sekundách
    setTimeout(skryjNotifikaci, 5000);
  }
}

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
    loadPhotosFromDatabase(currentReklamaceId);
    loadKalkulaceFromDatabase(currentReklamaceId);
  } else {
    logger.warn('Chybí ID v URL - zkusím načíst z localStorage');
    await loadReklamace(null);

    if (currentReklamace && currentReklamace.id) {
      logger.log('ID nalezeno v načtených datech:', currentReklamace.id);
      currentReklamaceId = currentReklamace.id;
      loadPhotosFromDatabase(currentReklamaceId);
      loadKalkulaceFromDatabase(currentReklamaceId);
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

function initSignaturePad() {
  const canvas = document.getElementById("signature-pad");

  // Ulozeny podpis jako data URL - pro obnoveni po resize
  let ulozenaPodpisData = null;

  const resize = () => {
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const rect = canvas.getBoundingClientRect();
    const cssWidth = rect.width;
    const cssHeight = rect.height;

    // Pred zmenou rozmeru ulozit obsah canvasu (pokud neni prazdny)
    const ctx = canvas.getContext("2d");
    if (!ulozenaPodpisData) {
      // Zkontrolovat jestli canvas neni prazdny
      const pixelData = ctx.getImageData(0, 0, canvas.width || 1, canvas.height || 1).data;
      let maPodpis = false;
      for (let i = 0; i < pixelData.length; i += 4) {
        if (pixelData[i] !== 255 || pixelData[i+1] !== 255 || pixelData[i+2] !== 255) {
          maPodpis = true;
          break;
        }
      }
      if (maPodpis) {
        ulozenaPodpisData = canvas.toDataURL('image/png');
      }
    }

    // Nastavit nove rozmery
    canvas.width = cssWidth * ratio;
    canvas.height = cssHeight * ratio;
    ctx.scale(ratio, ratio);

    // Obnovit podpis pokud byl ulozen
    if (ulozenaPodpisData) {
      const img = new Image();
      img.onload = () => {
        ctx.fillStyle = 'white';
        ctx.fillRect(0, 0, cssWidth, cssHeight);
        // Nakreslit podpis zachovany pomer stran
        const imgAspect = img.width / img.height;
        const canvasAspect = cssWidth / cssHeight;
        let drawW, drawH, drawX, drawY;
        if (imgAspect > canvasAspect) {
          drawW = cssWidth * 0.95;
          drawH = drawW / imgAspect;
        } else {
          drawH = cssHeight * 0.95;
          drawW = drawH * imgAspect;
        }
        drawX = (cssWidth - drawW) / 2;
        drawY = (cssHeight - drawH) / 2;
        ctx.drawImage(img, drawX, drawY, drawW, drawH);
      };
      img.src = ulozenaPodpisData;
    }
  };

  window.addEventListener("resize", resize, { passive: true });
  resize();

  signaturePad = new SignaturePad(canvas, {
    minWidth: 1,
    maxWidth: 2.5,
    penColor: "black",
    backgroundColor: "white",
    throttle: 8,
    velocityFilterWeight: 0.5,
    minDistance: 2
  });

  // Export do window pro globální funkci clearSignaturePad() (Step 110)
  window.signaturePad = signaturePad;

  // Funkce pro ulozeni podpisu (volano po potvrzeni z fullscreen)
  window.ulozitPodpisData = function(dataURL) {
    ulozenaPodpisData = dataURL;
  };

  // Inicializace fullscreen podpisu
  inicializovatFullscreenPodpis();
}

// ============================================================================
// FULLSCREEN PODPIS - Funkce pro landscape podpis na mobilu
// ============================================================================

let fullscreenSignaturePad = null;

function inicializovatFullscreenPodpis() {
  const btnPodepsat = document.getElementById('btnPodepsatFullscreen');
  const overlay = document.getElementById('fullscreenPodpisOverlay');
  const canvas = document.getElementById('fullscreen-signature-pad');
  const btnSmazat = document.getElementById('btnSmazatFullscreen');
  const btnPotvrdit = document.getElementById('btnPotvrdirFullscreen');

  if (!btnPodepsat || !overlay || !canvas) {
    logger.warn('[FullscreenPodpis] Elementy nenalezeny');
    return;
  }

  // Otevřít fullscreen podpis
  btnPodepsat.addEventListener('click', () => {
    otevritFullscreenPodpis();
  });

  // Smazat podpis
  btnSmazat?.addEventListener('click', () => {
    if (fullscreenSignaturePad) {
      fullscreenSignaturePad.clear();
    }
  });

  // Potvrdit podpis
  btnPotvrdit?.addEventListener('click', () => {
    potvrdirFullscreenPodpis();
  });

  logger.log('[FullscreenPodpis] Inicializace dokoncena');

  // Inicializovat červené tlačítko POTŘEBA DÍL
  inicializovatPotrebaDilButton();
}

// ============================================================================
// ČERVENÉ TLAČÍTKO - POTŘEBA DÍL K OPRAVĚ
// ============================================================================
function inicializovatPotrebaDilButton() {
  const btnPotrebaDil = document.getElementById('btnPotrebaDil');
  const potrebaDilContainer = document.getElementById('potrebaDilContainer');
  const btnSouhlasim = document.getElementById('btnSouhlasim');
  const btnNesouhlasim = document.getElementById('btnNesouhlasim');
  const souhlasDilOverlay = document.getElementById('souhlasDilOverlay');

  if (!btnPotrebaDil || !potrebaDilContainer) {
    logger.warn('[PotrebaDil] Tlačítko nenalezeno');
    return;
  }

  // Zkontrolovat typ zákazníka a zobrazit/skrýt tlačítko
  const typZakaznika = document.getElementById('typ-zakaznika')?.value || '';

  // Zobrazit pouze pro fyzické osoby (NENÍ firma/IČO)
  const jeFirma = typZakaznika.toLowerCase().includes('ičo') ||
                 typZakaznika.toLowerCase().includes('ico') ||
                 typZakaznika.toLowerCase().includes('firma') ||
                 typZakaznika.toLowerCase().includes('company');

  const jeFyzickaOsoba = !jeFirma;

  if (jeFyzickaOsoba) {
    potrebaDilContainer.style.display = 'block';
    logger.log('[PotrebaDil] Tlačítko zobrazeno (fyzická osoba)');
  } else {
    potrebaDilContainer.style.display = 'none';
    logger.log('[PotrebaDil] Tlačítko skryto (firma/IČO)');
  }

  // Globální proměnná pro uložení textu souhlasu
  window.textProdlouzeniLhutyGlobal = '';

  // Klik na červené tlačítko → zobrazit modal se souhlasem
  btnPotrebaDil.addEventListener('click', () => {
    logger.log('[PotrebaDil] Zobrazuji modal se souhlasem');
    if (souhlasDilOverlay) {
      souhlasDilOverlay.style.display = 'flex';
    }
  });

  // Klik na "SOUHLASÍM" → uložit text a otevřít fullscreen podpis
  if (btnSouhlasim) {
    btnSouhlasim.addEventListener('click', () => {
      const textSouhlas = 'Zákazník souhlasí s prodloužením lhůty pro vyřízení reklamace za účelem objednání náhradních dílů od výrobce. Dodací lhůta dílů je mimo kontrolu servisu a může se prodloužit (orientačně 3–4 týdny, v krajním případě i déle). Servis se zavazuje provést opravu bez zbytečného odkladu po doručení dílů.';

      window.textProdlouzeniLhutyGlobal = textSouhlas;
      logger.log('[PotrebaDil] Zákazník souhlasí - text uložen');

      // Zobrazit text v hlavním protokolu (GDPR sekce)
      const prodlouzeniLhutyHlavni = document.getElementById('prodlouzeniLhutyHlavni');
      if (prodlouzeniLhutyHlavni) {
        prodlouzeniLhutyHlavni.style.display = 'block';
      }

      // Schovat modal
      if (souhlasDilOverlay) {
        souhlasDilOverlay.style.display = 'none';
      }

      // Otevřít fullscreen podpis
      setTimeout(() => {
        otevritFullscreenPodpis();
      }, 300);
    });
  }

  // Klik na "NESOUHLASÍM" → uložit text nespolupráce a otevřít fullscreen podpis
  if (btnNesouhlasim) {
    btnNesouhlasim.addEventListener('click', () => {
      const textNesouhlas = 'Zákazník nesouhlasí s prodloužením lhůty za účelem objednání dílu. Tento postoj je považován za nespolupráci se servisem.';

      window.textProdlouzeniLhutyGlobal = textNesouhlas;
      logger.log('[PotrebaDil] Zákazník NEsouhlasí - text uložen');

      // Zobrazit text v hlavním protokolu
      const prodlouzeniLhutyHlavni = document.getElementById('prodlouzeniLhutyHlavni');
      if (prodlouzeniLhutyHlavni) {
        prodlouzeniLhutyHlavni.innerHTML = textNesouhlas;
        prodlouzeniLhutyHlavni.style.display = 'block';
      }

      // Schovat modal
      if (souhlasDilOverlay) {
        souhlasDilOverlay.style.display = 'none';
      }

      // Otevřít fullscreen podpis
      setTimeout(() => {
        otevritFullscreenPodpis();
      }, 300);
    });
  }

  logger.log('[PotrebaDil] Inicializace dokončena');
}

function otevritFullscreenPodpis() {
  const overlay = document.getElementById('fullscreenPodpisOverlay');
  const canvas = document.getElementById('fullscreen-signature-pad');

  if (!overlay || !canvas) return;

  // Zobrazit overlay
  overlay.classList.add('aktivni');

  // Zamknout scroll na body
  document.body.style.overflow = 'hidden';

  // Inicializovat canvas s malým zpožděním (po zobrazení)
  setTimeout(() => {
    inicializovatFullscreenCanvas(canvas);
  }, 50);

  logger.log('[FullscreenPodpis] Otevren');
}

function zavritFullscreenPodpis() {
  const overlay = document.getElementById('fullscreenPodpisOverlay');

  if (!overlay) return;

  overlay.classList.remove('aktivni');
  document.body.style.overflow = '';

  // Vyčistit canvas
  if (fullscreenSignaturePad) {
    fullscreenSignaturePad.clear();
  }

  logger.log('[FullscreenPodpis] Zavren');
}

function inicializovatFullscreenCanvas(canvas) {
  if (!canvas) return;

  // Získat rozměry kontejneru
  const kontejner = canvas.parentElement;
  const rect = kontejner.getBoundingClientRect();

  // Nastavit rozměry canvasu - plná šířka, výška = zbytek po header a footer
  const header = kontejner.querySelector('.fullscreen-podpis-header');
  const footer = kontejner.querySelector('.fullscreen-podpis-footer');

  const headerVyska = header ? header.offsetHeight : 0;
  const footerVyska = footer ? footer.offsetHeight : 0;

  // Canvas výška = kontejner - header - footer
  const canvasVyska = kontejner.offsetHeight - headerVyska - footerVyska;
  const canvasSirka = kontejner.offsetWidth;

  // Nastavit velikost
  canvas.width = canvasSirka;
  canvas.height = canvasVyska;
  canvas.style.width = canvasSirka + 'px';
  canvas.style.height = canvasVyska + 'px';

  const ctx = canvas.getContext('2d');

  // Vyplnit bílou
  ctx.fillStyle = 'white';
  ctx.fillRect(0, 0, canvas.width, canvas.height);

  // Vytvořit signature pad objekt
  fullscreenSignaturePad = {
    canvas: canvas,
    ctx: ctx,
    isDrawing: false,
    lastX: 0,
    lastY: 0,

    clear: function() {
      this.ctx.fillStyle = 'white';
      this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
    },

    isEmpty: function() {
      const pixelData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height).data;
      for (let i = 0; i < pixelData.length; i += 4) {
        if (pixelData[i] !== 255 || pixelData[i+1] !== 255 || pixelData[i+2] !== 255) {
          return false;
        }
      }
      return true;
    },

    toDataURL: function() {
      return this.canvas.toDataURL('image/png');
    }
  };

  // Nastavit styl čáry
  ctx.strokeStyle = '#000000';
  ctx.lineWidth = 3;
  ctx.lineCap = 'round';
  ctx.lineJoin = 'round';

  // Získat souřadnice (s podporou rotace)
  const ziskejSouradnice = (e) => {
    const rect = canvas.getBoundingClientRect();
    const overlay = document.getElementById('fullscreenPodpisOverlay');
    const jeRotovany = overlay.classList.contains('aktivni') &&
                       window.innerHeight > window.innerWidth &&
                       window.innerWidth <= 768;

    let clientX, clientY;
    if (e.touches && e.touches.length > 0) {
      clientX = e.touches[0].clientX;
      clientY = e.touches[0].clientY;
    } else {
      clientX = e.clientX;
      clientY = e.clientY;
    }

    if (jeRotovany) {
      // Při rotaci 90° musíme přepočítat souřadnice
      const centerX = window.innerWidth / 2;
      const centerY = window.innerHeight / 2;

      // Relativní pozice od středu obrazovky
      const relX = clientX - centerX;
      const relY = clientY - centerY;

      // Rotace o -90° (opačně k CSS rotaci)
      const rotX = relY;
      const rotY = -relX;

      // Přepočítat na canvas souřadnice
      const canvasCenterX = rect.width / 2;
      const canvasCenterY = rect.height / 2;

      return {
        x: canvasCenterX + rotX,
        y: canvasCenterY + rotY
      };
    } else {
      return {
        x: clientX - rect.left,
        y: clientY - rect.top
      };
    }
  };

  const zacitKreslit = (e) => {
    e.preventDefault();
    fullscreenSignaturePad.isDrawing = true;
    const coords = ziskejSouradnice(e);
    fullscreenSignaturePad.lastX = coords.x;
    fullscreenSignaturePad.lastY = coords.y;
  };

  const kreslit = (e) => {
    if (!fullscreenSignaturePad.isDrawing) return;
    e.preventDefault();
    const coords = ziskejSouradnice(e);

    ctx.beginPath();
    ctx.moveTo(fullscreenSignaturePad.lastX, fullscreenSignaturePad.lastY);
    ctx.lineTo(coords.x, coords.y);
    ctx.stroke();

    fullscreenSignaturePad.lastX = coords.x;
    fullscreenSignaturePad.lastY = coords.y;
  };

  const ukoncitKresleni = () => {
    fullscreenSignaturePad.isDrawing = false;
  };

  // Odstranit staré event listenery (pokud existují)
  canvas.onmousedown = null;
  canvas.onmousemove = null;
  canvas.onmouseup = null;
  canvas.onmouseout = null;
  canvas.ontouchstart = null;
  canvas.ontouchmove = null;
  canvas.ontouchend = null;

  // Mouse events
  canvas.addEventListener('mousedown', zacitKreslit);
  canvas.addEventListener('mousemove', kreslit);
  canvas.addEventListener('mouseup', ukoncitKresleni);
  canvas.addEventListener('mouseout', ukoncitKresleni);

  // Touch events
  canvas.addEventListener('touchstart', zacitKreslit, { passive: false });
  canvas.addEventListener('touchmove', kreslit, { passive: false });
  canvas.addEventListener('touchend', ukoncitKresleni);
  canvas.addEventListener('touchcancel', ukoncitKresleni);

  logger.log('[FullscreenPodpis] Canvas inicializovan:', canvasSirka, 'x', canvasVyska);
}

function potvrdirFullscreenPodpis() {
  if (!fullscreenSignaturePad || fullscreenSignaturePad.isEmpty()) {
    if (typeof wgsToast !== 'undefined') {
      wgsToast.warning('Prosim podepiste se pred potvrzenim');
    } else {
      alert('Prosim podepiste se pred potvrzenim');
    }
    return;
  }

  // Přenést podpis do hlavního canvasu
  const mainCanvas = document.getElementById('signature-pad');
  if (!mainCanvas) {
    logger.error('[FullscreenPodpis] Hlavni canvas nenalezen');
    return;
  }

  // Získat podpis jako obrázek
  const signatureDataURL = fullscreenSignaturePad.toDataURL();
  const img = new Image();

  img.onload = () => {
    const ctx = mainCanvas.getContext('2d');

    // Reset transformace
    ctx.setTransform(1, 0, 0, 1, 0, 0);

    // Vyčistit canvas bílou barvou
    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, mainCanvas.width, mainCanvas.height);

    // Vypočítat škálování - zachovat poměr stran
    const canvasW = mainCanvas.width;
    const canvasH = mainCanvas.height;
    const imgAspect = img.width / img.height;
    const canvasAspect = canvasW / canvasH;

    let drawWidth, drawHeight, drawX, drawY;

    if (imgAspect > canvasAspect) {
      // Obrázek je širší - omezit šířkou
      drawWidth = canvasW * 0.9;
      drawHeight = drawWidth / imgAspect;
    } else {
      // Obrázek je vyšší - omezit výškou
      drawHeight = canvasH * 0.9;
      drawWidth = drawHeight * imgAspect;
    }

    // Centrovat
    drawX = (canvasW - drawWidth) / 2;
    drawY = (canvasH - drawHeight) / 2;

    // Nakreslit podpis
    ctx.drawImage(img, drawX, drawY, drawWidth, drawHeight);

    // Ulozit podpis pro obnoveni pri resize/rotaci displeje
    if (typeof window.ulozitPodpisData === 'function') {
      window.ulozitPodpisData(signatureDataURL);
      logger.log('[FullscreenPodpis] Podpis ulozen pro resize');
    }

    // Označit kontejner že má podpis (skryje tlačítko PODEPSAT)
    const kontejner = document.getElementById('podpisKontejner');
    if (kontejner) {
      kontejner.classList.add('ma-podpis');
    }

    // Toast notifikace
    if (typeof WGSToast !== 'undefined') {
      WGSToast.zobrazit('Podpis byl pridan do protokolu', { titulek: 'WGS' });
    } else if (typeof wgsToast !== 'undefined') {
      wgsToast.success('Podpis byl pridan do protokolu');
    }

    // Zavrit fullscreen
    zavritFullscreenPodpis();

    logger.log('[FullscreenPodpis] Podpis prenesen do hlavniho canvasu');
  };

  img.onerror = () => {
    logger.error('[FullscreenPodpis] Chyba nacteni podpisu');
    zavritFullscreenPodpis();
  };

  img.src = signatureDataURL;
}

// Globální funkce pro zavření (pokud by bylo potřeba)
window.zavritFullscreenPodpis = zavritFullscreenPodpis;

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
      'after': 'AFTER'
    };

    let totalPhotos = 0;
    let totalVideos = 0;

    const orderedSections = ['before', 'id', 'problem', 'damage_part', 'new_part', 'repair', 'after'];

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
    kalkulaceData = data.kalkulace;

    logger.log('📦 Kalkulace data:', kalkulaceData);
    logger.log('💰 Celková cena:', kalkulaceData.celkovaCena, '€');
    logger.log('[Loc] Adresa:', kalkulaceData.adresa);
    logger.log('📏 Vzdálenost:', kalkulaceData.vzdalenost, 'km');
    logger.log('═══════════════════════════════════════');

    // Zobrazit notifikaci
    showNotif("success", `Kalkulace načtena (${kalkulaceData.celkovaCena.toFixed(2)} €)`);

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

function showLoadingWithMessage(show, message = 'Načítání...') {
  const overlay = document.getElementById("loadingOverlay");
  const textElement = document.getElementById("loadingText");

  if (show) {
    // Odebrat inline style (z EMERGENCY DIAGNOSTIC) aby CSS fungoval
    overlay.style.display = '';
    overlay.classList.add("show");
    if (textElement) {
      textElement.textContent = message;
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
  const input = document.createElement("input");
  input.type = "file";
  input.accept = "image/*";
  input.multiple = true;
  input.capture = "environment";
  input.classList.add("hidden");
  document.body.appendChild(input);
  input.onchange = async (e) => {
    const files = Array.from(e.target.files || []);
    if (!files.length) return;
    showNotif("success", "Zpracovávám fotky...");
    for (const file of files) {
      const compressed = await compressImage(file, 0.6);
      const base64 = await toBase64(compressed);
      attachedPhotos.push(base64);
    }
    renderPhotoPreview(attachedPhotos);
    showNotif("success", `${files.length} fotek přidáno`);
    input.remove();
  };
  input.click();
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

async function generateProtocolPDF() {
  // Kontrola dostupnosti PDF knihoven (jsPDF + html2canvas)
  await zkontrolujPdfKnihovny();

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF("p", "mm", "a4");

  const wrapper = document.querySelector(".wrapper");

  logger.log('[Doc] Vytvářím desktop clone pro PDF generování...');

  // ❗ CLONE APPROACH: Vytvoření skrytého desktop wrapper mimo viewport
  // Tento přístup zajistí identický PDF na mobilu i desktopu
  const clone = wrapper.cloneNode(true);
  clone.classList.add('pdf-clone-desktop');
  clone.id = 'pdf-clone-wrapper-temp';

  // Přidat clone do DOM (mimo viewport, neviditelný)
  document.body.appendChild(clone);

  // FIX: Odstranit interaktivní prvky z PDF (tlačítka, akce)
  // Odstranit celý kontejner signature-actions (tlačítko + label)
  const signatureActions = clone.querySelector('.signature-actions');
  if (signatureActions) {
    signatureActions.remove();
    logger.log('Signature actions (tlačítko "Vymazat podpis" + label) odstraněny z PDF');
  }

  // Odstranit tlacitko "Podepsat protokol"
  const btnPodepsatProtokol = clone.querySelector('.btn-podepsat-protokol');
  if (btnPodepsatProtokol) {
    btnPodepsatProtokol.remove();
    logger.log('Tlacitko "Podepsat protokol" odstraneno z PDF');
  }

  // Odstranit overlay s tlacitkem PODEPSAT
  const podpisOverlay = clone.querySelector('.podpis-overlay');
  if (podpisOverlay) {
    podpisOverlay.remove();
    logger.log('Podpis overlay (tlacitko PODEPSAT) odstranen z PDF');
  }

  // Odstranit ramecek z podpis kontejneru a canvasu pro PDF
  const podpisKontejner = clone.querySelector('.podpis-kontejner');
  if (podpisKontejner) {
    podpisKontejner.style.border = 'none';
    podpisKontejner.style.background = 'transparent';
  }
  const signatureCanvas = clone.querySelector('#signature-pad');
  if (signatureCanvas) {
    signatureCanvas.style.border = 'none';
  }

  // Odstranit dolní tlačítka (Export, Odeslat, Zpět)
  const btnsContainer = clone.querySelector('.btns');
  if (btnsContainer) {
    btnsContainer.remove();
    logger.log('Dolní tlačítka odstraněna z PDF');
  }

  // Odstranit photoPreviewContainer pokud existuje
  const photoPreview = clone.querySelector('#photoPreviewContainer');
  if (photoPreview) {
    photoPreview.remove();
    logger.log('Photo preview odstraněn z PDF (fotky jsou v samostatné sekci)');
  }

  // Odstranit šipku u rozbalovací hlavičky (není interaktivní v PDF)
  const customerInfoArrow = clone.querySelector('.customer-info-arrow');
  if (customerInfoArrow) {
    customerInfoArrow.remove();
    logger.log('Šipka u zákaznické hlavičky odstraněna z PDF');
  }

  // Ujistit se, že customer-info-content je viditelný (není skrytý)
  const customerInfoContent = clone.querySelector('.customer-info-content');
  if (customerInfoContent) {
    customerInfoContent.classList.remove('hidden');
    customerInfoContent.style.maxHeight = 'none';
    customerInfoContent.style.overflow = 'visible';
    logger.log('Zákaznický obsah nastaven jako viditelný v PDF');
  }

  // Zkopírovat signature pad canvas obsah do clone
  const originalCanvas = wrapper.querySelector('#signature-pad');
  const cloneCanvas = clone.querySelector('#signature-pad');
  logger.log('[PDF] Original canvas:', originalCanvas ? 'nalezen' : 'NENALEZEN');
  logger.log('[PDF] Clone canvas:', cloneCanvas ? 'nalezen' : 'NENALEZEN');

  if (originalCanvas && cloneCanvas) {
    try {
      // Zjistit skutecne rozmery originalniho canvasu
      const origWidth = originalCanvas.width;
      const origHeight = originalCanvas.height;
      logger.log('[PDF] Original canvas rozmery:', origWidth, 'x', origHeight);

      // Nastavit pevne rozmery pro clone canvas (bez devicePixelRatio)
      const pdfCanvasWidth = 800;
      const pdfCanvasHeight = 160;
      cloneCanvas.width = pdfCanvasWidth;
      cloneCanvas.height = pdfCanvasHeight;
      cloneCanvas.style.width = '100%';
      cloneCanvas.style.height = '180px';

      const ctx = cloneCanvas.getContext('2d');
      // Vyplnit bilou barvou
      ctx.fillStyle = 'white';
      ctx.fillRect(0, 0, pdfCanvasWidth, pdfCanvasHeight);

      // Nakreslit original - skalovany na nove rozmery
      ctx.drawImage(originalCanvas, 0, 0, origWidth, origHeight, 0, 0, pdfCanvasWidth, pdfCanvasHeight);
      logger.log('[PDF] Signature pad zkopirovan do clone:', pdfCanvasWidth, 'x', pdfCanvasHeight);
    } catch (e) {
      logger.warn('[PDF] Nepodarilo se zkopirovat signature pad:', e);
    }
  } else {
    logger.error('[PDF] Canvas pro podpis nenalezen!');
  }

  // Počkat na reflow clone (desktop layout se aplikuje)
  await new Promise(resolve => setTimeout(resolve, 150));

  // FIX: Nahradit textarea za DIV elementy pro správné zalamování v PDF
  // html2canvas má problémy s renderováním textarea hodnot
  // DŮLEŽITÉ: Použít 100% šířku a pevné styly pro správné zalamování na mobilu
  const originalTextareas = wrapper.querySelectorAll('textarea');
  const cloneTextareas = clone.querySelectorAll('textarea');
  originalTextareas.forEach((original, index) => {
    const cloneTextarea = cloneTextareas[index];
    if (cloneTextarea) {
      const div = document.createElement('div');
      // Pevné styly pro PDF - nezávislé na computed styles
      div.style.cssText = `
        width: 100%;
        min-height: 60px;
        padding: 4px 8px;
        border: 1px solid #999;
        background: #fff;
        font-family: 'Poppins', sans-serif;
        font-size: 12px;
        line-height: 1.4;
        color: #1a1a1a;
        white-space: pre-wrap;
        word-wrap: break-word;
        overflow-wrap: break-word;
        word-break: break-word;
        box-sizing: border-box;
        overflow: visible;
      `;
      div.textContent = original.value;
      cloneTextarea.parentNode.replaceChild(div, cloneTextarea);
    }
  });
  logger.log('Textarea nahrazeny DIV elementy pro PDF');

  // FIX: Nahradit input pole za DIV elementy pro správné zobrazení v PDF
  // DIV místo SPAN pro lepší zalamování dlouhého textu
  const originalInputs = wrapper.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="number"], input:not([type])');
  const cloneInputs = clone.querySelectorAll('input[type="text"], input[type="email"], input[type="tel"], input[type="number"], input:not([type])');
  originalInputs.forEach((original, index) => {
    const cloneInput = cloneInputs[index];
    if (cloneInput) {
      const div = document.createElement('div');
      // Pevné styly pro PDF
      div.style.cssText = `
        display: block;
        width: 100%;
        padding: 4px 8px;
        border: 1px solid #999;
        background: #fff;
        font-family: 'Poppins', sans-serif;
        font-size: 12px;
        line-height: 1.4;
        color: #1a1a1a;
        white-space: pre-wrap;
        word-wrap: break-word;
        overflow-wrap: break-word;
        word-break: break-word;
        box-sizing: border-box;
        min-height: 24px;
        overflow: visible;
      `;
      div.textContent = original.value;
      cloneInput.parentNode.replaceChild(div, cloneInput);
    }
  });
  logger.log('Input pole nahrazeny DIV elementy pro PDF');

  // FIX: Nahradit select elementy za DIV s vybranou hodnotou
  const originalSelects = wrapper.querySelectorAll('select');
  const cloneSelects = clone.querySelectorAll('select');
  originalSelects.forEach((original, index) => {
    const cloneSelect = cloneSelects[index];
    if (cloneSelect) {
      const div = document.createElement('div');
      div.style.cssText = `
        display: block;
        width: 100%;
        padding: 4px 8px;
        border: 1px solid #999;
        background: #fff;
        font-family: 'Poppins', sans-serif;
        font-size: 12px;
        line-height: 1.4;
        color: #1a1a1a;
        box-sizing: border-box;
        min-height: 24px;
      `;
      // Získat text vybrané možnosti
      const selectedOption = original.options[original.selectedIndex];
      div.textContent = selectedOption ? selectedOption.text : '';
      cloneSelect.parentNode.replaceChild(div, cloneSelect);
    }
  });
  logger.log('Select elementy nahrazeny DIV elementy pro PDF');

  // Zjistit pozice sekcí pro inteligentní stránkování
  const cloneRect = clone.getBoundingClientRect();
  const sectionTitles = clone.querySelectorAll('.section-title');
  const breakPoints = [0]; // Začátek

  sectionTitles.forEach(title => {
    const titleRect = title.getBoundingClientRect();
    // Relativní pozice od začátku clone
    const relativeTop = titleRect.top - cloneRect.top;
    if (relativeTop > 0) {
      breakPoints.push(relativeTop);
    }
  });

  logger.log('[PDF] Nalezeno break points:', breakPoints.length);

  logger.log('[Photo] Renderuji clone pomocí html2canvas...');

  const canvas = await html2canvas(clone, {
    scale: 3,
    backgroundColor: "#fff",
    useCORS: true,
    logging: false,
    imageTimeout: 0,
    allowTaint: true,
    letterRendering: true,
    windowWidth: 900,  // FIX: Fixní šířka pro desktop layout (ignoruje mobilní media queries)
    windowHeight: clone.scrollHeight
  });

  const imgData = canvas.toDataURL("image/jpeg", 0.98);

  const pageWidth = 210;
  const pageHeight = 297;
  const margin = 10;

  const availableWidth = pageWidth - (margin * 2);
  const availableHeight = pageHeight - (margin * 2);

  // Šířka obrázku = dostupná šířka stránky
  const imgWidth = availableWidth;
  // Výška obrázku podle poměru stran
  const imgHeight = (canvas.height * imgWidth) / canvas.width;

  // Poměr pro převod CSS pixelů na canvas pixely
  const cssToCanvasRatio = canvas.height / cloneRect.height;

  // Převést break points na canvas pixely
  const canvasBreakPoints = breakPoints.map(bp => Math.round(bp * cssToCanvasRatio));
  canvasBreakPoints.push(canvas.height); // Konec

  logger.log('[PDF] Canvas break points:', canvasBreakPoints);

  // VŽDY JEDNA STRÁNKA A4: Pokud je obsah vyšší než A4, proporcionálně zmenšit
  if (imgHeight > availableHeight) {
    // NOVÝ PŘÍSTUP: Proporcionální zmenšení na A4
    const scale = availableHeight / imgHeight;
    const scaledWidth = imgWidth * scale;
    const scaledHeight = availableHeight;  // Maximální výška A4

    // Vycentrovat horizontálně
    const xOffset = (pageWidth - scaledWidth) / 2;

    // Přidat celý protokol jako JEDEN obrázek zmenšený proporcionálně
    doc.addImage(imgData, "JPEG", xOffset, margin, scaledWidth, scaledHeight);

    logger.log(`[Doc] Obsah zmenšen proporcionálně na 1 stránku A4: ${imgHeight.toFixed(0)}mm → ${scaledHeight.toFixed(0)}mm (scale: ${(scale * 100).toFixed(1)}%)`);
  } else {
    // Obsah se vejde na jednu stránku bez změny
    const xOffset = (pageWidth - imgWidth) / 2;
    doc.addImage(imgData, "JPEG", xOffset, margin, imgWidth, imgHeight);
    logger.log('[Doc] PDF má 1 stránku (obsah se vejde bez zmenšení)');
  }

  // ❗ Odstranit clone z DOM
  document.body.removeChild(clone);
  logger.log('Clone odstraněn, PDF vygenerováno');

  return doc;
}

async function generatePhotosPDF() {
  if (!attachedPhotos.length) return null;

  // Kontrola dostupnosti PDF knihoven
  await zkontrolujPdfKnihovny();

  const { jsPDF } = window.jspdf;
  const pdf = new jsPDF("p", "mm", "a4");

  const pageWidth = pdf.internal.pageSize.getWidth();
  const pageHeight = pdf.internal.pageSize.getHeight();
  const margin = 10;
  const gap = 5;
  const labelHeight = 5;

  const photosPerPage = 4;
  const cols = 2;
  const rows = 2;

  const availableWidth = pageWidth - (2 * margin) - gap;
  const availableHeight = pageHeight - (2 * margin) - gap;
  const cellWidth = availableWidth / cols;
  const cellHeight = availableHeight / rows;

  logger.log(`[Doc] Vytvářím PDF: ${attachedPhotos.length} fotek, ${Math.ceil(attachedPhotos.length / photosPerPage)} stránek`);

  for (let i = 0; i < attachedPhotos.length; i++) {
    const photo = attachedPhotos[i];

    const photoData = typeof photo === 'string' ? photo : photo.data;
    const photoLabel = typeof photo === 'object' ? photo.label : '';

    if (i > 0 && i % photosPerPage === 0) {
      pdf.addPage();
      logger.log(`[Doc] Přidána nová stránka (fotka ${i + 1})`);
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
        pdf.setFontSize(8);
        pdf.setFont('helvetica', 'bold');
        pdf.setTextColor(0, 0, 0);
        pdf.text(photoLabel, x + offsetX, photoY + offsetY - 2);
      }

      pdf.addImage(photoData, "JPEG", x + offsetX, photoY + offsetY, finalWidth, finalHeight, undefined, 'MEDIUM');

      logger.log(`  [Photo] Fotka ${i + 1}/${attachedPhotos.length} - ${photoLabel || 'bez popisku'} (${imgWidth}x${imgHeight} → ${Math.round(finalWidth)}x${Math.round(finalHeight)}mm)`);

    } catch (err) {
      logger.warn(`Nelze detekovat velikost fotky ${i + 1}, používám celou buňku`);

      // Fallback: label ve středu buňky
      if (photoLabel) {
        pdf.setFontSize(8);
        pdf.setFont('helvetica', 'bold');
        pdf.setTextColor(0, 0, 0);
        pdf.text(photoLabel, x, photoY - 2);
      }

      pdf.addImage(photoData, "JPEG", x, photoY, maxPhotoWidth, maxPhotoHeight, undefined, 'MEDIUM');
    }
  }

  logger.log(`PDF s fotkami vytvořeno (${attachedPhotos.length} fotek s popisky)`);

  return pdf;
}

async function generatePricelistPDF() {
  if (!kalkulaceData) {
    logger.log('Kalkulace neexistuje - PRICELIST PDF nebude vygenerovano');
    return null;
  }

  logger.log('Generuji PDF PRICELIST...');

  // Kontrola dostupnosti PDF knihoven
  await zkontrolujPdfKnihovny();

  const { jsPDF } = window.jspdf;
  const pdf = new jsPDF("p", "mm", "a4");

  const pageWidth = pdf.internal.pageSize.getWidth();
  const pageHeight = pdf.internal.pageSize.getHeight();
  const margin = 15;
  let yPos = margin;

  // === HLAVIČKA ===
  pdf.setFontSize(20);
  pdf.setFont('helvetica', 'bold');
  pdf.setTextColor(0, 0, 0); // Černá
  pdf.text('PRICELIST', pageWidth / 2, yPos, { align: 'center' });
  yPos += 15;

  // === ÚDAJE ZÁKAZNÍKA ===
  const zakaznikJmeno = document.getElementById('customer')?.value || 'N/A';
  const zakaznikAdresa = kalkulaceData.adresa || document.getElementById('address')?.value || 'N/A';
  const zakaznikTelefon = document.getElementById('phone')?.value || '';
  const zakaznikEmail = document.getElementById('email')?.value || '';
  const reklamaceCislo = document.getElementById('claim-number')?.value || '';

  pdf.setFontSize(10);
  pdf.setFont('helvetica', 'normal');
  pdf.setTextColor(0, 0, 0);

  if (reklamaceCislo) {
    pdf.text(`Cislo reklamace: ${reklamaceCislo}`, margin, yPos);
    yPos += 6;
  }

  pdf.setFont('helvetica', 'bold');
  pdf.text(`Zakaznik: ${zakaznikJmeno}`, margin, yPos);
  yPos += 6;

  pdf.setFont('helvetica', 'normal');
  pdf.text(`Adresa: ${zakaznikAdresa}`, margin, yPos);
  yPos += 6;

  if (zakaznikTelefon) {
    pdf.text(`Telefon: ${zakaznikTelefon}`, margin, yPos);
    yPos += 6;
  }

  if (zakaznikEmail) {
    pdf.text(`Email: ${zakaznikEmail}`, margin, yPos);
    yPos += 6;
  }

  yPos += 5;

  // Čára oddělení
  pdf.setLineWidth(0.5);
  pdf.setDrawColor(0, 0, 0); // Černá
  pdf.line(margin, yPos, pageWidth - margin, yPos);
  yPos += 10;

  // === CENOTVORBA ===
  pdf.setFontSize(14);
  pdf.setFont('helvetica', 'bold');
  pdf.text('Rozpis cen', margin, yPos);
  yPos += 10;

  pdf.setFontSize(10);
  pdf.setFont('helvetica', 'normal');

  // Dopravné
  if (!kalkulaceData.reklamaceBezDopravy) {
    const dopravneText = `Dopravne (${kalkulaceData.vzdalenost} km)`;
    const dopravneCena = kalkulaceData.dopravne.toFixed(2);
    pdf.text(dopravneText, margin, yPos);
    pdf.text(`${dopravneCena} EUR`, pageWidth - margin - 30, yPos);
    yPos += 7;
  } else {
    pdf.text('Dopravne (reklamace)', margin, yPos);
    pdf.text('0.00 EUR', pageWidth - margin - 30, yPos);
    yPos += 7;
  }

  // Služby
  if (kalkulaceData.sluzby && kalkulaceData.sluzby.length > 0) {
    yPos += 3;
    pdf.setFont('helvetica', 'bold');
    pdf.text('Sluzby:', margin, yPos);
    yPos += 7;

    pdf.setFont('helvetica', 'normal');
    kalkulaceData.sluzby.forEach(sluzba => {
      const text = `  ${sluzba.nazev}`;
      const cena = sluzba.cena.toFixed(2);
      pdf.text(text, margin, yPos);
      pdf.text(`${cena} EUR`, pageWidth - margin - 30, yPos);
      yPos += 6;
    });

    yPos += 3;
  }

  // Díly a práce
  if (kalkulaceData.dilyPrace && kalkulaceData.dilyPrace.length > 0) {
    yPos += 3;
    pdf.setFont('helvetica', 'bold');
    pdf.text('Dily a prace:', margin, yPos);
    yPos += 7;

    pdf.setFont('helvetica', 'normal');
    kalkulaceData.dilyPrace.forEach(polozka => {
      const text = `  ${polozka.nazev} (${polozka.pocet}x)`;
      const cena = polozka.cena.toFixed(2);
      pdf.text(text, margin, yPos);
      pdf.text(`${cena} EUR`, pageWidth - margin - 30, yPos);
      yPos += 6;
    });

    yPos += 3;
  }

  // Příplatky
  if (kalkulaceData.tezkyNabytek) {
    pdf.text('Priplatek: Tezky nabytek (nad 50 kg)', margin, yPos);
    pdf.text('80.00 EUR', pageWidth - margin - 30, yPos);
    yPos += 7;
  }

  if (kalkulaceData.druhaOsoba) {
    pdf.text('Priplatek: Druha osoba', margin, yPos);
    pdf.text('80.00 EUR', pageWidth - margin - 30, yPos);
    yPos += 7;
  }

  yPos += 5;

  // Čára před celkovou cenou
  pdf.setLineWidth(0.3);
  pdf.line(margin, yPos, pageWidth - margin, yPos);
  yPos += 8;

  // === CELKOVÁ CENA ===
  pdf.setFontSize(14);
  pdf.setFont('helvetica', 'bold');
  pdf.setTextColor(0, 0, 0); // Černá
  pdf.text('CELKEM:', margin, yPos);
  pdf.text(`${kalkulaceData.celkovaCena.toFixed(2)} EUR`, pageWidth - margin - 40, yPos);
  yPos += 10;

  // === POZNÁMKY ===
  if (kalkulaceData.poznamka) {
    yPos += 5;
    pdf.setFontSize(10);
    pdf.setFont('helvetica', 'italic');
    pdf.setTextColor(100, 100, 100);
    pdf.text('Poznamka:', margin, yPos);
    yPos += 6;
    pdf.setFont('helvetica', 'normal');

    const lines = pdf.splitTextToSize(kalkulaceData.poznamka, pageWidth - 2 * margin);
    lines.forEach(line => {
      pdf.text(line, margin, yPos);
      yPos += 5;
    });
  }

  logger.log(`PDF PRICELIST vytvořen (${kalkulaceData.celkovaCena.toFixed(2)} €)`);

  return pdf;
}

async function exportBothPDFs() {
  try {
    showLoading(true);

    logger.log('[List] Generuji kompletní PDF (protokol + PRICELIST + fotodokumentace)...');
    logger.log('💰 Kontrola kalkulace - kalkulaceData:', kalkulaceData);

    // Vytvořit JEDNO PDF s protokolem
    const doc = await generateProtocolPDF();

    // Pokud existuje kalkulace, přidat PRICELIST
    if (kalkulaceData) {
      logger.log('Kalkulace nalezena - přidávám PRICELIST...');
      logger.log('[Stats] Kalkulace data:', kalkulaceData);

      // NOVÁ STRÁNKA: PRICELIST
      doc.addPage();

      const pageWidth = doc.internal.pageSize.getWidth();
      const pageHeight = doc.internal.pageSize.getHeight();
      const margin = 15;
      let yPos = margin;

      // === HLAVIČKA ===
      doc.setFontSize(20);
      doc.setFont('helvetica', 'bold');
      doc.setTextColor(0, 0, 0);
      doc.text('PRICELIST', pageWidth / 2, yPos, { align: 'center' });
      yPos += 15;

      // === ÚDAJE ZÁKAZNÍKA ===
      const zakaznikJmeno = document.getElementById('customer')?.value || 'N/A';
      const zakaznikAdresa = kalkulaceData.adresa || document.getElementById('address')?.value || 'N/A';
      const zakaznikTelefon = document.getElementById('phone')?.value || '';
      const zakaznikEmail = document.getElementById('email')?.value || '';
      const reklamaceCislo = document.getElementById('claim-number')?.value || '';

      doc.setFontSize(10);
      doc.setFont('helvetica', 'normal');
      doc.setTextColor(0, 0, 0);

      if (reklamaceCislo) {
        doc.text(`Cislo reklamace: ${reklamaceCislo}`, margin, yPos);
        yPos += 6;
      }

      doc.setFont('helvetica', 'bold');
      doc.text(`Zakaznik: ${zakaznikJmeno}`, margin, yPos);
      yPos += 6;

      doc.setFont('helvetica', 'normal');
      doc.text(`Adresa: ${zakaznikAdresa}`, margin, yPos);
      yPos += 6;

      if (zakaznikTelefon) {
        doc.text(`Telefon: ${zakaznikTelefon}`, margin, yPos);
        yPos += 6;
      }

      if (zakaznikEmail) {
        doc.text(`Email: ${zakaznikEmail}`, margin, yPos);
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
      doc.setFont('helvetica', 'bold');
      doc.text('Rozpis cen', margin, yPos);
      yPos += 10;

      doc.setFontSize(10);
      doc.setFont('helvetica', 'normal');

      // Dopravné
      if (!kalkulaceData.reklamaceBezDopravy) {
        const dopravneText = `Dopravne (${kalkulaceData.vzdalenost} km)`;
        const dopravneCena = kalkulaceData.dopravne.toFixed(2);
        doc.text(dopravneText, margin, yPos);
        doc.text(`${dopravneCena} EUR`, pageWidth - margin - 30, yPos);
        yPos += 7;
      } else {
        doc.text('Dopravne (reklamace)', margin, yPos);
        doc.text('0.00 EUR', pageWidth - margin - 30, yPos);
        yPos += 7;
      }

      // Díly a práce
      if (kalkulaceData.dilyPrace && kalkulaceData.dilyPrace.length > 0) {
        yPos += 3;
        doc.setFont('helvetica', 'bold');
        doc.text('Dily a prace:', margin, yPos);
        yPos += 7;

        doc.setFont('helvetica', 'normal');
        kalkulaceData.dilyPrace.forEach(polozka => {
          const text = `  ${polozka.nazev} (${polozka.pocet}x)`;
          const cena = polozka.cena.toFixed(2);
          doc.text(text, margin, yPos);
          doc.text(`${cena} EUR`, pageWidth - margin - 30, yPos);
          yPos += 6;
        });

        yPos += 3;
      }

      // Příplatky
      if (kalkulaceData.tezkyNabytek) {
        doc.text('Priplatek: Tezky nabytek (nad 50 kg)', margin, yPos);
        doc.text('80.00 EUR', pageWidth - margin - 30, yPos);
        yPos += 7;
      }

      if (kalkulaceData.druhaOsoba) {
        doc.text('Priplatek: Druha osoba', margin, yPos);
        doc.text('80.00 EUR', pageWidth - margin - 30, yPos);
        yPos += 7;
      }

      yPos += 5;

      // Čára před celkovou cenou
      doc.setLineWidth(0.3);
      doc.line(margin, yPos, pageWidth - margin, yPos);
      yPos += 8;

      // === CELKOVÁ CENA ===
      doc.setFontSize(14);
      doc.setFont('helvetica', 'bold');
      doc.setTextColor(0, 0, 0);
      doc.text('CELKEM:', margin, yPos);
      doc.text(`${kalkulaceData.celkovaCena.toFixed(2)} EUR`, pageWidth - margin - 40, yPos);

      logger.log(`PRICELIST přidán (${kalkulaceData.celkovaCena.toFixed(2)} €)`);
    } else {
      logger.warn('Kalkulace nenalezena - PRICELIST nebude v PDF');
      logger.warn('   Možné příčiny:');
      logger.warn('   1. Kalkulace nebyla vytvořena');
      logger.warn('   2. Kalkulace nebyla uložena do databáze');
      logger.warn('   3. Chyba při načítání z databáze');
    }

    // Pokud jsou fotky, přidat fotodokumentaci na KONEC protokolu
    if (attachedPhotos.length > 0) {
      logger.log('[Photo] Přidávám fotodokumentaci...');

      const pageWidth = doc.internal.pageSize.getWidth();
      const pageHeight = doc.internal.pageSize.getHeight();
      const margin = 10;

      // NOVÁ STRÁNKA: Fotodokumentace začíná
      doc.addPage();

      // Hlavička fotodokumentace
      doc.setFontSize(16);
      doc.setFont('helvetica', 'bold');
      doc.text('FOTODOKUMENTACE', pageWidth / 2, 20, { align: 'center' });

      let yPos = 35;

      // Informace o zákazníkovi
      doc.setFontSize(10);
      doc.setFont('helvetica', 'normal');

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
      doc.setFont('helvetica', 'bold');
      doc.text('INDEX PHOTO', margin, yPos);
      yPos += 8;

      // Index fotek - miniaturní náhledy
      doc.setFontSize(8);
      doc.setFont('helvetica', 'normal');

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
            doc.setFont('helvetica', 'bold');
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
            doc.setFont('helvetica', 'bold');
            doc.setTextColor(0, 0, 0);
            doc.text(photoLabel, x, photoY - 2);
          }

          doc.addImage(photoData, "JPEG", x, photoY, maxPhotoWidth, maxPhotoHeight, undefined, 'MEDIUM');
        }
      }

      logger.log(`Fotodokumentace přidána (${attachedPhotos.length} fotek)`);
      // Neonový toast pro vytvoření PDF
      if (typeof WGSToast !== 'undefined') {
        WGSToast.zobrazit(`PDF vytvořeno (protokol + ${attachedPhotos.length} fotek)`, { titulek: 'WGS' });
      } else {
        showNotif("success", `PDF vytvořeno (protokol + ${attachedPhotos.length} fotek)`);
      }

    } else {
      // Neonový toast pro protokol bez fotek
      if (typeof WGSToast !== 'undefined') {
        WGSToast.zobrazit("Protokol vytvořen", { titulek: 'WGS' });
      } else {
        showNotif("success", "Protokol vytvořen (bez fotek)");
      }
    }

    // Uložit PDF do databáze (stejně jako při odeslání emailem)
    logger.log('[Save] Ukládám PDF do databáze...');
    try {
      const csrfToken = await fetchCsrfToken();
      const completePdfBase64 = doc.output("datauristring").split(",")[1];

      const saveResponse = await fetch("api/protokol_api.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "save_pdf_only",
          reklamace_id: currentReklamaceId,
          complete_pdf: completePdfBase64,
          csrf_token: csrfToken
        })
      });

      if (saveResponse.ok) {
        const saveResult = await saveResponse.json();
        if (saveResult.status === 'success') {
          logger.log('PDF úspěšně uložen do databáze');
        } else {
          logger.warn('PDF se nepodařilo uložit:', saveResult.message);
        }
      }
    } catch (err) {
      logger.error('Chyba při ukládání PDF:', err);
      // Pokračujeme i přes chybu - alespoň zobrazíme PDF
    }

    // Zobrazit PDF v preview modalu místo window.open
    const pdfBlob = doc.output("blob");
    // Název souboru = ručně zadané číslo reklamace/zakázky (bez prefixu WGS)
    const cisloReklamace = document.getElementById('claim-number')?.value || 'protokol';
    const nazevSouboru = `${cisloReklamace.replace(/[\/\s]+/g, '_')}.pdf`;

    // Nastavit kontext na 'export' a uložit doc
    pdfPreviewContext = 'export';
    cachedPdfDoc = doc;
    cachedPdfBase64 = null; // není potřeba pro export

    // Použít novou funkci pro zobrazení PDF preview
    if (typeof otevritPdfPreview === 'function') {
      otevritPdfPreview(pdfBlob, nazevSouboru);
    } else {
      // Fallback na původní window.open pokud funkce není dostupná
      window.open(URL.createObjectURL(pdfBlob), "_blank");
    }

    // Uložit textová data do DB
    await saveProtokolToDB();

    // Označit jako hotovou
    logger.log('[List] Označuji reklamaci jako hotovou...');
    try {
      const csrfToken = await fetchCsrfToken();
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
      }
    } catch (err) {
      logger.error('Chyba při označování:', err);
    }

  } catch (error) {
    logger.error('Chyba při generování PDF:', error);
    showNotif("error", "Chyba při vytváření PDF");
  } finally {
    showLoading(false);
  }
}

async function sendToCustomer() {
  try {
    // FÁZE 1: Generování kompletního PDF (protokol + fotky) pro NÁHLED
    showLoadingWithMessage(true, 'Generuji protokol... Prosím čekejte');
    logger.log('[List] Generuji kompletní PDF pro náhled před odesláním...');
    logger.log('💰 Kontrola kalkulace - kalkulaceData:', kalkulaceData);

    // Vytvořit JEDNO PDF s protokolem
    const doc = await generateProtocolPDF();

    // Pokud existuje kalkulace, přidat PRICELIST
    if (kalkulaceData) {
      showLoadingWithMessage(true, `Přidávám PRICELIST (${kalkulaceData.celkovaCena.toFixed(2)} €)... Prosím čekejte`);
      logger.log('Kalkulace nalezena - přidávám PRICELIST...');
      logger.log('[Stats] Kalkulace data:', kalkulaceData);

      // NOVÁ STRÁNKA: PRICELIST
      doc.addPage();

      const pageWidth = doc.internal.pageSize.getWidth();
      const pageHeight = doc.internal.pageSize.getHeight();
      const margin = 15;
      let yPos = margin;

      // === HLAVIČKA ===
      doc.setFontSize(20);
      doc.setFont('helvetica', 'bold');
      doc.setTextColor(0, 0, 0);
      doc.text('PRICELIST', pageWidth / 2, yPos, { align: 'center' });
      yPos += 15;

      // === ÚDAJE ZÁKAZNÍKA ===
      const zakaznikJmeno = document.getElementById('customer')?.value || 'N/A';
      const zakaznikAdresa = kalkulaceData.adresa || document.getElementById('address')?.value || 'N/A';
      const zakaznikTelefon = document.getElementById('phone')?.value || '';
      const zakaznikEmail = document.getElementById('email')?.value || '';
      const reklamaceCislo = document.getElementById('claim-number')?.value || '';

      doc.setFontSize(10);
      doc.setFont('helvetica', 'normal');
      doc.setTextColor(0, 0, 0);

      if (reklamaceCislo) {
        doc.text(`Cislo reklamace: ${reklamaceCislo}`, margin, yPos);
        yPos += 6;
      }

      doc.setFont('helvetica', 'bold');
      doc.text(`Zakaznik: ${zakaznikJmeno}`, margin, yPos);
      yPos += 6;

      doc.setFont('helvetica', 'normal');
      doc.text(`Adresa: ${zakaznikAdresa}`, margin, yPos);
      yPos += 6;

      if (zakaznikTelefon) {
        doc.text(`Telefon: ${zakaznikTelefon}`, margin, yPos);
        yPos += 6;
      }

      if (zakaznikEmail) {
        doc.text(`Email: ${zakaznikEmail}`, margin, yPos);
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
      doc.setFont('helvetica', 'bold');
      doc.text('Rozpis cen', margin, yPos);
      yPos += 10;

      doc.setFontSize(10);
      doc.setFont('helvetica', 'normal');

      // Dopravné
      if (!kalkulaceData.reklamaceBezDopravy) {
        const dopravneText = `Dopravne (${kalkulaceData.vzdalenost} km)`;
        const dopravneCena = kalkulaceData.dopravne.toFixed(2);
        doc.text(dopravneText, margin, yPos);
        doc.text(`${dopravneCena} EUR`, pageWidth - margin - 30, yPos);
        yPos += 7;
      } else {
        doc.text('Dopravne (reklamace)', margin, yPos);
        doc.text('0.00 EUR', pageWidth - margin - 30, yPos);
        yPos += 7;
      }

      // Díly a práce
      if (kalkulaceData.dilyPrace && kalkulaceData.dilyPrace.length > 0) {
        yPos += 3;
        doc.setFont('helvetica', 'bold');
        doc.text('Dily a prace:', margin, yPos);
        yPos += 7;

        doc.setFont('helvetica', 'normal');
        kalkulaceData.dilyPrace.forEach(polozka => {
          const text = `  ${polozka.nazev} (${polozka.pocet}x)`;
          const cena = polozka.cena.toFixed(2);
          doc.text(text, margin, yPos);
          doc.text(`${cena} EUR`, pageWidth - margin - 30, yPos);
          yPos += 6;
        });

        yPos += 3;
      }

      // Příplatky
      if (kalkulaceData.tezkyNabytek) {
        doc.text('Priplatek: Tezky nabytek (nad 50 kg)', margin, yPos);
        doc.text('80.00 EUR', pageWidth - margin - 30, yPos);
        yPos += 7;
      }

      if (kalkulaceData.druhaOsoba) {
        doc.text('Priplatek: Druha osoba', margin, yPos);
        doc.text('80.00 EUR', pageWidth - margin - 30, yPos);
        yPos += 7;
      }

      yPos += 5;

      // Čára před celkovou cenou
      doc.setLineWidth(0.3);
      doc.line(margin, yPos, pageWidth - margin, yPos);
      yPos += 8;

      // === CELKOVÁ CENA ===
      doc.setFontSize(14);
      doc.setFont('helvetica', 'bold');
      doc.setTextColor(0, 0, 0);
      doc.text('CELKEM:', margin, yPos);
      doc.text(`${kalkulaceData.celkovaCena.toFixed(2)} EUR`, pageWidth - margin - 40, yPos);

      logger.log(`PRICELIST přidán (${kalkulaceData.celkovaCena.toFixed(2)} €)`);
    } else {
      logger.warn('Kalkulace nenalezena - PRICELIST nebude v emailu');
      logger.warn('   Zkontrolujte, zda byla kalkulace vytvořena a uložena');
    }

    // Pokud jsou fotky, přidat fotodokumentaci na KONEC protokolu (stejně jako exportBothPDFs)
    if (attachedPhotos.length > 0) {
      showLoadingWithMessage(true, `Přidávám ${attachedPhotos.length} fotografií... Prosím čekejte`);
      logger.log('[Photo] Přidávám fotodokumentaci...');

      const pageWidth = doc.internal.pageSize.getWidth();
      const pageHeight = doc.internal.pageSize.getHeight();
      const margin = 10;

      // NOVÁ STRÁNKA: Fotodokumentace začíná
      doc.addPage();

      // Hlavička fotodokumentace
      doc.setFontSize(16);
      doc.setFont('helvetica', 'bold');
      doc.text('FOTODOKUMENTACE', pageWidth / 2, 20, { align: 'center' });

      let yPos = 35;

      // Informace o zákazníkovi
      doc.setFontSize(10);
      doc.setFont('helvetica', 'normal');

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
      doc.setFont('helvetica', 'bold');
      doc.text('INDEX PHOTO', margin, yPos);
      yPos += 8;

      // Index fotek - miniaturní náhledy
      doc.setFontSize(8);
      doc.setFont('helvetica', 'normal');

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
            doc.setFont('helvetica', 'bold');
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
            doc.setFont('helvetica', 'bold');
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
    showLoadingWithMessage(true, 'Odesílám email zákazníkovi... Prosím čekejte');
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
async function translateTextApi(text, sourceLang = 'cs', targetLang = 'en') {
  if (!text || text.trim() === '') return '';

  try {
    // Použití server-side proxy místo přímého volání externího API
    const response = await fetch('api/translate_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        text: text,
        source: sourceLang,
        target: targetLang
      })
    });

    const data = await response.json();

    if (data.status === 'success' && data.translated) {
      return data.translated;
    }

    logger.warn('Překlad selhal:', data.message || 'Neznámá chyba');
    return '';
  } catch (err) {
    logger.error('Chyba překladu:', err);
    return '';
  }
}

// Wrapper funkce pro překlad mezi textovými poli
async function translateText(sourceId, targetId) {
  const sourceField = document.getElementById(sourceId);
  const targetField = document.getElementById(targetId);

  if (!sourceField || !targetField) {
    logger.error('Pole pro překlad nenalezeno:', sourceId, targetId);
    return;
  }

  const text = sourceField.value.trim();
  if (!text) {
    showNotification('Nejdříve napište text pro překlad', 'error');
    return;
  }

  // Najít tlačítko pro animaci
  const button = sourceField.parentElement.querySelector('.translate-btn');
  if (button) {
    button.classList.add('loading');
    button.disabled = true;
  }

  try {
    logger.log('[Sync] Překládám:', text.substring(0, 50) + '...');
    const translated = await translateTextApi(text, 'cs', 'en');

    if (translated) {
      targetField.value = translated;
      logger.log('Přeloženo:', translated.substring(0, 50) + '...');
      showNotification('Text přeložen', 'success');
    } else {
      showNotification('Překlad selhal', 'error');
    }
  } catch (err) {
    logger.error('Chyba při překladu:', err);
    showNotification('Chyba při překladu', 'error');
  } finally {
    if (button) {
      button.classList.remove('loading');
      button.disabled = false;
    }
  }
}

// Automatický překlad pro konkrétní pole
async function autoTranslateField(fieldId) {
  const field = document.getElementById(fieldId);
  if (!field) return;

  const text = field.value.trim();
  if (!text) return;

  logger.log('[Sync] Překládám pole:', fieldId);

  let enLabel = field.parentElement.querySelector('.en-label');

  if (!enLabel) {
    const container = field.closest('.input-group, .form-group, div');
    if (container) {
      enLabel = container.querySelector('.en-label');
    }
  }

  if (!enLabel) {
    logger.warn('En-label pro', fieldId, 'nenalezen');
    return;
  }

  const translated = await translateTextApi(text, 'cs', 'en');

  if (translated) {
    enLabel.textContent = translated;
    logger.log('Přeloženo:', fieldId, '->', translated.substring(0, 50) + '...');
  }
}

// Inicializace auto-překladu
function initAutoTranslation() {
  const fieldsToTranslate = [
    { source: 'description-cz', target: 'description-en' },
    { source: 'problem-cz', target: 'problem-en' },
    { source: 'repair-cz', target: 'repair-en' }
  ];

  fieldsToTranslate.forEach(({ source, target }) => {
    const sourceField = document.getElementById(source);
    if (!sourceField) {
      logger.warn('Auto-překlad: Pole nenalezeno:', source);
      return;
    }

    const debouncedTranslate = debounce(() => {
      translateText(source, target);
    }, 1500);

    sourceField.addEventListener('input', debouncedTranslate);

    sourceField.addEventListener('blur', () => {
      translateText(source, target);
    });

    logger.log('Auto-překlad aktivován pro:', source, '→', target);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAutoTranslation);
} else {
  initAutoTranslation();
}

logger.log('Automatický překlad aktivován');

async function translateField(fieldName, silent = false) {
  const czField = document.getElementById(fieldName + '-cz');
  const enField = document.getElementById(fieldName + '-en');
  if (!czField || !enField) return;
  const text = czField.value.trim();
  if (!text || text.length < 5) return;
  try {
    enField.value = 'Prekladam...';
    const response = await fetch('api/translate_api.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ text: text, engine: 'mymemory' })
    });
    const result = await response.json();
    if (result.status === 'success') {
      enField.value = result.translated;
      logger.log('OK:', fieldName);
    } else {
      enField.value = '';
    }
  } catch (e) {
    logger.error('Err:', e);
    enField.value = '';
  }
}

window.addEventListener('load', () => {
  ['description', 'problem', 'repair'].forEach(f => {
    const el = document.getElementById(f + '-cz');
    if (!el) return;
    let t;
    el.addEventListener('input', () => {
      clearTimeout(t);
      t = setTimeout(() => {
        if (el.value.trim().length > 10) translateField(f, true);
      }, 2000);
    });
  });
  logger.log('Translate ready');
});

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

// === MODAL PRO SCHVÁLENÍ ZÁKAZNÍKEM ===
// Step 39: Migrace na Alpine.js - open/close logika přesunuta do zakaznikSchvaleniModal komponenty
// Business logika (překlad, signature pad, souhrn) zůstává zde
(function() {
  let zakaznikSignaturePad = null;

  // Inicializace při načtení stránky
  document.addEventListener('DOMContentLoaded', () => {
    const btnPodepsat = document.getElementById('btnPodepsatProtokol');
    const overlay = document.getElementById('zakaznikSchvaleniOverlay');
    const btnPouzit = document.getElementById('zakaznikSchvaleniPouzit');
    const btnVymazat = document.getElementById('zakaznikVymazatPodpis');
    const canvas = document.getElementById('zakaznikSchvaleniPad');

    if (!btnPodepsat || !overlay || !canvas) {
      return;
    }

    // Otevření modalu - async kvuli pojistce prekladu
    btnPodepsat.addEventListener('click', async () => {
      // Zobrazit loading behem prekladu
      btnPodepsat.disabled = true;
      btnPodepsat.textContent = 'Pripravuji...';

      try {
        await otevritZakaznikModal();
      } finally {
        // Obnovit tlacitko
        btnPodepsat.disabled = false;
        btnPodepsat.textContent = 'Podepsat protokol';
      }
    });

    // Step 39: Zavírání modalu nyní řeší Alpine.js (btnClose, btnZrusit, overlay click, ESC)
    // Vanilla JS event listenery pro close/cancel/overlay odstraněny

    // Vymazat podpis
    btnVymazat?.addEventListener('click', () => {
      if (zakaznikSignaturePad) {
        zakaznikSignaturePad.clear();
      }
    });

    // Potvrdit podpis
    btnPouzit?.addEventListener('click', () => {
      potvrditPodpis();
    });

    // Volba typu podpisu
    const btnNutnoObjednatDil = document.getElementById('btnNutnoObjednatDil');
    const btnPouzePodpis = document.getElementById('btnPouzePodpis');
    const btnSouhlasim = document.getElementById('btnSouhlasim');
    const btnNesouhlasim = document.getElementById('btnNesouhlasim');

    // Globální proměnná pro text prodloužení lhůty
    window.textProdlouzeniLhuty = '';

    // Funkce pro zobrazení podpisové sekce (globální)
    window.zobrazitPodpisSekci = function(infoText) {
      const zakaznikVolbaPodpisu = document.getElementById('zakaznikVolbaPodpisu');
      const zakaznikPodpisSekce = document.getElementById('zakaznikPodpisSekce');
      const prodlouzeniLhutyInfo = document.getElementById('prodlouzeniLhutyInfo');
      const prodlouzeniLhutyInfoText = document.getElementById('prodlouzeniLhutyInfoText');
      const canvas = document.getElementById('zakaznikSchvaleniPad');

      logger.log('[Podpis] zobrazitPodpisSekci volána s textem:', infoText);

      // Schovat volbu typu podpisu
      if (zakaznikVolbaPodpisu) {
        zakaznikVolbaPodpisu.style.display = 'none';
        logger.log('[Podpis] Volba podpisu schována');
      }

      // Zobrazit podpisovou sekci
      if (zakaznikPodpisSekce) {
        zakaznikPodpisSekce.style.display = 'block';
        logger.log('[Podpis] Sekce podpisu zobrazena');
      }

      // Uložit text pro pozdější použití při generování PDF
      window.textProdlouzeniLhuty = infoText;

      // Zobrazit info text pokud existuje
      if (infoText && prodlouzeniLhutyInfo && prodlouzeniLhutyInfoText) {
        prodlouzeniLhutyInfoText.textContent = infoText;
        prodlouzeniLhutyInfo.style.display = 'block';
        logger.log('[Podpis] Info text zobrazen');
      } else if (prodlouzeniLhutyInfo) {
        prodlouzeniLhutyInfo.style.display = 'none';
      }

      // Inicializovat canvas
      setTimeout(() => {
        if (canvas) {
          logger.log('[Podpis] Inicializuji canvas...');
          inicializovatZakaznikPad(canvas);
        } else {
          logger.error('[Podpis] Canvas nenalezen!');
        }
      }, 100);
    };

    // Klik na "NUTNO OBJEDNAT DÍL" → zobrazit modal se souhlasem
    if (btnNutnoObjednatDil) {
      btnNutnoObjednatDil.addEventListener('click', () => {
        const souhlasDilOverlay = document.getElementById('souhlasDilOverlay');
        logger.log('[Podpis] Kliknuto na NUTNO OBJEDNAT DÍL');
        if (souhlasDilOverlay) {
          souhlasDilOverlay.style.display = 'flex';
          logger.log('[Podpis] Modal souhlasu zobrazen');
        }
      });
    }

    // Klik na "PODPIS" → rovnou zobrazit canvas (bez souhlasu)
    if (btnPouzePodpis) {
      btnPouzePodpis.addEventListener('click', () => {
        logger.log('[Podpis] Kliknuto na PODPIS');
        window.zobrazitPodpisSekci('');
      });
    }

    // Klik na "SOUHLASÍM" → schovat modal, zobrazit canvas, vyplnit text o souhlasu
    if (btnSouhlasim) {
      btnSouhlasim.addEventListener('click', () => {
        const textSouhlas = 'Zákazník souhlasí s prodloužením lhůty pro vyřízení reklamace za účelem objednání náhradních dílů od výrobce. Dodací lhůta dílů je mimo kontrolu servisu a může se prodloužit (orientačně 3–4 týdny, v krajním případě i déle). Servis se zavazuje provést opravu bez zbytečného odkladu po doručení dílů.';
        logger.log('[Podpis] Kliknuto na SOUHLASÍM');
        window.zobrazitPodpisSekci(textSouhlas);
        const souhlasDilOverlay = document.getElementById('souhlasDilOverlay');
        if (souhlasDilOverlay) {
          souhlasDilOverlay.style.display = 'none';
          logger.log('[Podpis] Modal souhlasu schován');
        }
      });
    }

    // Klik na "NESOUHLASÍM" → schovat modal, zobrazit canvas, vyplnit text o nespolupráci
    if (btnNesouhlasim) {
      btnNesouhlasim.addEventListener('click', () => {
        const textNesouhlas = 'Zákazník nesouhlasí s prodloužením lhůty za účelem objednání dílu. Tento postoj je považován za nespolupráci se servisem.';
        logger.log('[Podpis] Kliknuto na NESOUHLASÍM');
        window.zobrazitPodpisSekci(textNesouhlas);
        const souhlasDilOverlay = document.getElementById('souhlasDilOverlay');
        if (souhlasDilOverlay) {
          souhlasDilOverlay.style.display = 'none';
          logger.log('[Podpis] Modal souhlasu schován');
        }
      });
    }
  });

  async function otevritZakaznikModal() {
    const canvas = document.getElementById('zakaznikSchvaleniPad');

    // POJISTKA: Vynutit preklad vsech poli pred podpisem
    // Aby anglicke preklady byly vzdy aktualni v PDF
    logger.log('[Podpis] Spoustim pojistku prekladu pred podpisem...');
    const fieldsToTranslate = ['description', 'problem', 'repair'];

    for (const field of fieldsToTranslate) {
      const czField = document.getElementById(field + '-cz');
      const enField = document.getElementById(field + '-en');

      if (czField && enField && czField.value.trim().length > 5) {
        // Pokud anglicke pole je prazdne nebo obsahuje "Prekladam...", vynutit preklad
        if (!enField.value || enField.value === 'Prekladam...' || enField.value.trim() === '') {
          logger.log('[Podpis] Prekladam pole:', field);
          try {
            await translateField(field, true);
          } catch (e) {
            logger.warn('[Podpis] Preklad selhal pro:', field, e);
          }
        }
      }
    }
    logger.log('[Podpis] Pojistka prekladu dokoncena');

    // Naplnit souhrn daty z formuláře
    naplnitSouhrn();

    // Zobrazit/skrýt tlačítko "NUTNO OBJEDNAT DÍL" podle typu zákazníka
    // Tlačítko se zobrazí pouze pro fyzické osoby (ne pro IČO)
    const typZakaznika = document.getElementById('typ-zakaznika')?.value || '';
    const btnNutnoObjednatDilElement = document.getElementById('btnNutnoObjednatDil');

    // Zobrazit pouze pro fyzické osoby (hodnota obsahuje "Fyzická" nebo NENÍ IČO/firma)
    // Robustnější detekce - zobrazit pokud NENÍ firma/IČO
    const jeFirma = typZakaznika.toLowerCase().includes('ičo') ||
                   typZakaznika.toLowerCase().includes('ico') ||
                   typZakaznika.toLowerCase().includes('firma') ||
                   typZakaznika.toLowerCase().includes('company');

    const jeFyzickaOsoba = !jeFirma;  // Pokud není firma, je fyzická osoba

    logger.log('[Podpis] Typ zákazníka:', typZakaznika, '| Je fyzická osoba:', jeFyzickaOsoba);

    if (btnNutnoObjednatDilElement) {
      if (jeFyzickaOsoba) {
        btnNutnoObjednatDilElement.style.display = 'block';
        logger.log('[Podpis] Tlačítko NUTNO OBJEDNAT DÍL zobrazeno (fyzická osoba)');
      } else {
        btnNutnoObjednatDilElement.style.display = 'none';
        logger.log('[Podpis] Tlačítko NUTNO OBJEDNAT DÍL skryto (firma/IČO)');
      }
    }

    // Reset stavu modalu - zobrazit volbu, schovat canvas
    const zakaznikVolbaPodpisuElement = document.getElementById('zakaznikVolbaPodpisu');
    const zakaznikPodpisSekceElement = document.getElementById('zakaznikPodpisSekce');

    if (zakaznikVolbaPodpisuElement) {
      zakaznikVolbaPodpisuElement.style.display = 'block';
    }
    if (zakaznikPodpisSekceElement) {
      zakaznikPodpisSekceElement.style.display = 'none';
    }

    // Vyčistit info text
    const prodlouzeniLhutyInfoElement = document.getElementById('prodlouzeniLhutyInfo');
    if (prodlouzeniLhutyInfoElement) {
      prodlouzeniLhutyInfoElement.style.display = 'none';
    }

    // Starý kód pro checkbox - odstraněno
    /*
    const checkboxRow = document.querySelector('.tabulka-checkbox-row');
    const checkboxProdlouzeni = document.getElementById('checkboxProdlouzeniLhuty');
    const textProdlouzeniModal = document.getElementById('prodlouzeniLhutyText');

    if (checkboxRow) {
    */

    logger.log('[ZakaznikSchvaleni] Fyzická osoba:', jeFyzickaOsoba, '| Typ:', typZakaznika);

    // Step 39: Zobrazit modal přes Alpine.js API (scroll lock je v Alpine komponentě)
    if (window.zakaznikSchvaleniModal && window.zakaznikSchvaleniModal.open) {
      window.zakaznikSchvaleniModal.open();
    } else {
      // Fallback pro zpětnou kompatibilitu
      const overlay = document.getElementById('zakaznikSchvaleniOverlay');
      if (overlay) {
        overlay.classList.remove('hidden');
      }
      if (window.scrollLock) {
        window.scrollLock.enable('zakaznik-schvaleni-overlay');
      }
    }

    // NEBUDEME inicializovat canvas hned - pouze po výběru typu podpisu
    // Inicializace je v zobrazitPodpisSekci()
  }

  function zavritZakaznikModal() {
    // Step 39: Zavřít modal přes Alpine.js API (scroll lock je v Alpine komponentě)
    if (window.zakaznikSchvaleniModal && window.zakaznikSchvaleniModal.close) {
      window.zakaznikSchvaleniModal.close();
    } else {
      // Fallback pro zpětnou kompatibilitu
      const overlay = document.getElementById('zakaznikSchvaleniOverlay');
      if (overlay) {
        overlay.classList.add('hidden');
      }
      if (window.scrollLock) {
        window.scrollLock.disable('zakaznik-schvaleni-overlay');
      }
    }

    // Vyčistit signature pad
    if (zakaznikSignaturePad) {
      zakaznikSignaturePad.clear();
    }

    // Reset stavu - zobrazit volbu, schovat canvas a modal souhlasu
    const zakaznikVolbaPodpisuElement = document.getElementById('zakaznikVolbaPodpisu');
    const zakaznikPodpisSekceElement = document.getElementById('zakaznikPodpisSekce');
    const souhlasDilOverlayElement = document.getElementById('souhlasDilOverlay');
    const prodlouzeniLhutyInfoElement = document.getElementById('prodlouzeniLhutyInfo');

    if (zakaznikVolbaPodpisuElement) {
      zakaznikVolbaPodpisuElement.style.display = 'block';
    }
    if (zakaznikPodpisSekceElement) {
      zakaznikPodpisSekceElement.style.display = 'none';
    }
    if (souhlasDilOverlayElement) {
      souhlasDilOverlayElement.style.display = 'none';
    }
    if (prodlouzeniLhutyInfoElement) {
      prodlouzeniLhutyInfoElement.style.display = 'none';
    }
  }

  function naplnitSouhrn() {
    // Návrh opravy
    const repairText = document.getElementById('repair-cz')?.value || '';
    const textEl = document.getElementById('zakaznikSchvaleniText');
    if (textEl) {
      textEl.textContent = repairText || '(Není vyplněno)';
    }

    // Platí zákazník?
    const payment = document.getElementById('payment')?.value || '-';
    document.getElementById('souhrn-plati-zakaznik').textContent = payment;

    // Datum podpisu
    const signDate = document.getElementById('sign-date')?.value || '-';
    let formattedDate = '-';
    if (signDate && signDate !== '-') {
      const d = new Date(signDate);
      if (!isNaN(d.getTime())) {
        formattedDate = d.toLocaleDateString('cs-CZ');
      } else {
        formattedDate = signDate;
      }
    }
    document.getElementById('souhrn-datum-podpisu').textContent = formattedDate;

    // Vyřešeno?
    const solved = document.getElementById('solved')?.value || '-';
    document.getElementById('souhrn-vyreseno').textContent = solved;

    // Nutné vyjádření prodejce
    const dealer = document.getElementById('dealer')?.value || '-';
    document.getElementById('souhrn-prodejce').textContent = dealer;

    // Poškození technikem?
    const damage = document.getElementById('damage')?.value || '-';
    document.getElementById('souhrn-poskozeni').textContent = damage;
  }

  function inicializovatZakaznikPad(canvas) {
    if (!canvas) return;

    // Pokud už je inicializován, jen vyčistit
    if (zakaznikSignaturePad && zakaznikSignaturePad.canvas === canvas) {
      zakaznikSignaturePad.clear();
      return;
    }

    // Nastavit rozměry canvasu - BEZ devicePixelRatio pro jednoduchost
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width;
    canvas.height = rect.height;

    const ctx = canvas.getContext('2d');

    // Vyplnit bílou
    ctx.fillStyle = 'white';
    ctx.fillRect(0, 0, canvas.width, canvas.height);

    // Vytvořit jednoduchý signature pad
    zakaznikSignaturePad = {
      canvas: canvas,
      ctx: ctx,
      isDrawing: false,
      lastX: 0,
      lastY: 0,

      clear: function() {
        this.ctx.fillStyle = 'white';
        this.ctx.fillRect(0, 0, this.canvas.width, this.canvas.height);
      },

      isEmpty: function() {
        // FIX: Kontrolovat RGB hodnoty, ne alpha kanál
        // Bílý pixel = (255, 255, 255), jakýkoli jiný = podpis
        const pixelData = this.ctx.getImageData(0, 0, this.canvas.width, this.canvas.height).data;
        for (let i = 0; i < pixelData.length; i += 4) {
          // Pokud pixel není bílý (RGB != 255,255,255), canvas není prázdný
          if (pixelData[i] !== 255 || pixelData[i+1] !== 255 || pixelData[i+2] !== 255) {
            return false;
          }
        }
        return true;
      },

      toDataURL: function() {
        return this.canvas.toDataURL('image/png');
      }
    };

    // Nastavit styl čáry
    ctx.strokeStyle = '#000000';
    ctx.lineWidth = 2;
    ctx.lineCap = 'round';
    ctx.lineJoin = 'round';

    // Event listenery pro kreslení
    const getCoords = (e) => {
      const rect = canvas.getBoundingClientRect();
      if (e.touches && e.touches.length > 0) {
        return {
          x: e.touches[0].clientX - rect.left,
          y: e.touches[0].clientY - rect.top
        };
      }
      return {
        x: e.clientX - rect.left,
        y: e.clientY - rect.top
      };
    };

    const startDrawing = (e) => {
      e.preventDefault();
      zakaznikSignaturePad.isDrawing = true;
      const coords = getCoords(e);
      zakaznikSignaturePad.lastX = coords.x;
      zakaznikSignaturePad.lastY = coords.y;
    };

    const draw = (e) => {
      if (!zakaznikSignaturePad.isDrawing) return;
      e.preventDefault();
      const coords = getCoords(e);

      ctx.beginPath();
      ctx.moveTo(zakaznikSignaturePad.lastX, zakaznikSignaturePad.lastY);
      ctx.lineTo(coords.x, coords.y);
      ctx.stroke();

      zakaznikSignaturePad.lastX = coords.x;
      zakaznikSignaturePad.lastY = coords.y;
    };

    const stopDrawing = () => {
      zakaznikSignaturePad.isDrawing = false;
    };

    // Mouse events
    canvas.addEventListener('mousedown', startDrawing);
    canvas.addEventListener('mousemove', draw);
    canvas.addEventListener('mouseup', stopDrawing);
    canvas.addEventListener('mouseout', stopDrawing);

    // Touch events
    canvas.addEventListener('touchstart', startDrawing, { passive: false });
    canvas.addEventListener('touchmove', draw, { passive: false });
    canvas.addEventListener('touchend', stopDrawing);
    canvas.addEventListener('touchcancel', stopDrawing);
  }

  function potvrditPodpis() {
    if (!zakaznikSignaturePad || zakaznikSignaturePad.isEmpty()) {
      if (typeof showNotif === 'function') {
        showNotif('error', 'Prosím podepište se před potvrzením');
      } else {
        wgsToast.warning('Prosím podepište se před potvrzením');
      }
      return;
    }

    // Přenést podpis do hlavního canvasu
    const mainCanvas = document.getElementById('signature-pad');

    if (!mainCanvas) {
      console.error('[ZakaznikSchvaleni] Hlavní canvas nenalezen');
      if (typeof showNotif === 'function') {
        showNotif('error', 'Chyba při přenosu podpisu');
      }
      return;
    }

    // Získat podpis jako obrázek
    const signatureDataURL = zakaznikSignaturePad.toDataURL();
    const img = new Image();

    img.onload = () => {
      const ctx = mainCanvas.getContext('2d');

      // Reset transformace
      ctx.setTransform(1, 0, 0, 1, 0, 0);

      // Vyčistit canvas bílou barvou
      ctx.fillStyle = 'white';
      ctx.fillRect(0, 0, mainCanvas.width, mainCanvas.height);

      // Pracovat přímo s fyzickými pixely canvasu
      const canvasW = mainCanvas.width;
      const canvasH = mainCanvas.height;

      // Vypočítat škálování - zachovat poměr stran
      const imgAspect = img.width / img.height;
      const canvasAspect = canvasW / canvasH;

      let drawWidth, drawHeight, drawX, drawY;

      if (imgAspect > canvasAspect) {
        // Obrázek je širší - omezit šířkou
        drawWidth = canvasW * 0.9;
        drawHeight = drawWidth / imgAspect;
      } else {
        // Obrázek je vyšší - omezit výškou
        drawHeight = canvasH * 0.9;
        drawWidth = drawHeight * imgAspect;
      }

      // Centrovat
      drawX = (canvasW - drawWidth) / 2;
      drawY = (canvasH - drawHeight) / 2;

      // Nakreslit podpis
      ctx.drawImage(img, drawX, drawY, drawWidth, drawHeight);

      // Neonový toast pro přenesení podpisu
      if (typeof WGSToast !== 'undefined') {
        WGSToast.zobrazit('Podpis byl přenesen do protokolu', { titulek: 'WGS' });
      } else if (typeof showNotif === 'function') {
        showNotif('success', 'Podpis byl přenesen do protokolu');
      }

      // FIX: Zavřít modal AŽ PO úspěšném přenosu podpisu (ne před ním!)
      // Zkontrolovat checkbox prodloužení lhůty
      const checkboxProdlouzeni = document.getElementById('checkboxProdlouzeniLhuty');
      const textProdlouzeniHlavni = document.getElementById('prodlouzeniLhutyHlavni');

      if (checkboxProdlouzeni && textProdlouzeniHlavni) {
        if (checkboxProdlouzeni.checked) {
          textProdlouzeniHlavni.style.display = 'block';
          logger.log('[ZakaznikSchvaleni] Text prodloužení lhůty zobrazen v hlavním formuláři');
        } else {
          textProdlouzeniHlavni.style.display = 'none';
        }
      }

      // Zavřít modal až po úspěšném přenosu
      zavritZakaznikModal();

      // Vynutit překlad všech textových polí
      vynutitPreklad();
    };

    img.onerror = () => {
      console.error('[ZakaznikSchvaleni] Chyba načtení podpisu');
      if (typeof showNotif === 'function') {
        showNotif('error', 'Chyba při přenosu podpisu');
      }
      // I při chybě zavřít modal
      zavritZakaznikModal();
    };

    img.src = signatureDataURL;
  }

  // Funkce pro vynucení překladu všech polí
  function vynutitPreklad() {
    const fieldsToTranslate = [
      { source: 'description-cz', target: 'description-en' },
      { source: 'problem-cz', target: 'problem-en' },
      { source: 'repair-cz', target: 'repair-en' }
    ];

    fieldsToTranslate.forEach(({ source, target }) => {
      const sourceField = document.getElementById(source);
      if (sourceField && sourceField.value.trim()) {
        // Použít globální funkci translateText pokud existuje
        if (typeof translateText === 'function') {
          translateText(source, target);
        }
      }
    });
  }
})();
