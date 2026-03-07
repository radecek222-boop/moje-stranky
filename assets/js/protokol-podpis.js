/**
 * protokol-podpis.js
 * Signature pad — inicializace, fullscreen podpis na mobilu, potvrzení
 * Závisí na: protokol.js (wgsToast, logger)
 */

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

  // FIX: Přeinicializovat canvas při změně orientace mobilu
  const handleOrientationChange = () => {
    // Pouze pokud je overlay aktivní
    if (overlay.classList.contains('aktivni')) {
      logger.log('[FullscreenPodpis] Změna orientace - reinicializuji canvas');

      // Počkat na dokončení rotace
      setTimeout(() => {
        inicializovatFullscreenCanvas(canvas);
      }, 100);
    }
  };

  // Listener pro změnu orientace (pro mobily)
  window.addEventListener('orientationchange', handleOrientationChange);

  // Listener pro resize (fallback pro desktop a některé mobily)
  let resizeTimeout;
  const handleResize = () => {
    clearTimeout(resizeTimeout);
    resizeTimeout = setTimeout(() => {
      if (overlay.classList.contains('aktivni')) {
        logger.log('[FullscreenPodpis] Resize - reinicializuji canvas');
        inicializovatFullscreenCanvas(canvas);
      }
    }, 200);
  };
  window.addEventListener('resize', handleResize);

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
  ctx.strokeStyle = 'var(--wgs-black)';
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
    showNotif("error", 'Prosím podepište se před potvrzením');
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

