/**
 * protokol-schvaleni.js
 * Modal pro schválení zákazníkem (signature pad, překlad, souhrn)
 * Závisí na: protokol.js (currentReklamaceId, wgsToast, logger)
 */

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
    ctx.strokeStyle = 'var(--wgs-black)';
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

  // EXPORT funkcí do window přesunut za IIFE (níže), aby nebyl blokován early return výše


})();

// ═══════════════════════════════════════════════════════════
// EXPORT FUNKCÍ DO WINDOW (pro data-action tlačítka)
// Musí být mimo IIFE, aby bylo dostupné i bez btnPodepsatProtokol
// ═══════════════════════════════════════════════════════════
window.sendToCustomer = sendToCustomer;
window.exportBothPDFs = exportBothPDFs;
window.attachPhotos = attachPhotos;

