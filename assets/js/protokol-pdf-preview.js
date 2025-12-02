/**
 * PDF Preview Modal s možností sdílení
 * Zobrazí PDF v modalu s křížkem a ikonou pro sdílení/stažení
 * Step 42: Migrace na Alpine.js - close/overlay click/ESC handlery přesunuty do pdfPreviewModal komponenty
 */

// Globální reference na aktuální PDF
let aktualniPdfBlob = null;
let aktualniPdfNazev = 'protokol.pdf';

/**
 * Otevře PDF preview modal
 * @param {Blob} pdfBlob - PDF jako Blob objekt
 * @param {string} nazevSouboru - Název PDF souboru
 */
function otevritPdfPreview(pdfBlob, nazevSouboru = 'protokol.pdf') {
  try {
    logger.log('[Doc] Otevírám PDF preview modal...');
    logger.log('[Doc] PDF Blob:', pdfBlob);
    logger.log('[Doc] PDF Blob size:', pdfBlob.size, 'bytes');
    logger.log('[Doc] PDF Blob type:', pdfBlob.type);

    // Uložit referenci
    aktualniPdfBlob = pdfBlob;
    aktualniPdfNazev = nazevSouboru;

    // Vytvořit URL pro iframe
    const pdfUrl = URL.createObjectURL(pdfBlob);
    logger.log('[Doc] PDF URL vytvořena:', pdfUrl);

    // Nastavit iframe src
    const iframe = document.getElementById('pdfPreviewFrame');
    if (!iframe) {
      logger.error('iframe #pdfPreviewFrame nenalezen!');
      showNotif('error', 'Chyba: iframe nenalezen');
      return;
    }

    logger.log('[Doc] Nastavuji iframe.src...');

    // FIX: Vyčistit iframe PŘED nastavením nového src
    // srcdoc má prioritu nad src, proto musíme nejprve kompletně vyčistit iframe
    iframe.removeAttribute('srcdoc');
    iframe.src = '';  // Vyčistit starý src

    // FIX: Zobrazit loading během načítání PDF
    const pdfBody = iframe.closest('.pdf-preview-body');
    if (pdfBody) {
      pdfBody.style.position = 'relative';
      pdfBody.innerHTML = `
        <div style="position: absolute; top: 0; left: 0; right: 0; bottom: 0;
                    display: flex; align-items: center; justify-content: center;
                    background: #f5f5f5; z-index: 1;">
          <div style="text-align: center;">
            <div style="width: 50px; height: 50px; border: 4px solid #ddd;
                        border-top: 4px solid #333333; border-radius: 50%;
                        animation: spin 1s linear infinite; margin: 0 auto 15px;"></div>
            <p style="color: #666; font-size: 14px; margin: 0;">Načítám PDF...</p>
          </div>
        </div>
        <iframe id="pdfPreviewFrame" class="pdf-preview-frame"></iframe>
      `;

      // Přidat CSS animaci pro spinner
      if (!document.getElementById('pdf-spinner-style')) {
        const style = document.createElement('style');
        style.id = 'pdf-spinner-style';
        style.textContent = '@keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }';
        document.head.appendChild(style);
      }
    }

    // Znovu získat iframe (protože jsme změnili innerHTML)
    const newIframe = document.getElementById('pdfPreviewFrame');

    // Nastavit nový src
    newIframe.src = pdfUrl;
    logger.log('[Doc] iframe.src nastavena:', newIframe.src);

    // FIX: Skrýt loading po načtení PDF
    newIframe.onload = () => {
      logger.log('PDF úspěšně načten v iframe');
      const loadingDiv = pdfBody?.querySelector('div[style*="position: absolute"]');
      if (loadingDiv) {
        loadingDiv.remove();
      }
    };

    // Fallback: skrýt loading po 3 sekundách i když onload nevypálí
    setTimeout(() => {
      const loadingDiv = pdfBody?.querySelector('div[style*="position: absolute"]');
      if (loadingDiv) {
        logger.warn('Loading skrytý po timeoutu (3s)');
        loadingDiv.remove();
      }
    }, 3000);

    // Podmíněně zobrazit tlačítka podle kontextu
    const shareBtn = document.getElementById('pdfShareBtn');
    const sendBtn = document.getElementById('pdfSendBtn');

    // Získat kontext z protokol.js (globální proměnná pdfPreviewContext)
    const kontext = typeof pdfPreviewContext !== 'undefined' ? pdfPreviewContext : 'export';

    if (kontext === 'send') {
      // Režim "Odeslat zákazníkovi"
      shareBtn.style.display = 'none';
      sendBtn.style.display = 'flex';
      logger.log('📧 Režim: Odeslání zákazníkovi');
    } else {
      // Režim "Export/Sdílení"
      shareBtn.style.display = 'flex';
      sendBtn.style.display = 'none';
      logger.log('📤 Režim: Export/Sdílení');
    }

    // Step 42: Zobrazit modal přes Alpine.js API
    const overlay = document.getElementById('pdfPreviewOverlay');
    if (!overlay) {
      logger.error('overlay #pdfPreviewOverlay nenalezen!');
      showNotif('error', 'Chyba: modal nenalezen');
      return;
    }

    if (window.pdfPreviewModal && window.pdfPreviewModal.open) {
      window.pdfPreviewModal.open();
    } else {
      // Fallback pro zpětnou kompatibilitu
      overlay.classList.add('active');
    }
    logger.log('Modal zobrazen (Alpine.js API)');

    // FALLBACK: Pokud iframe nedokáže zobrazit PDF (některé browsery mají problémy),
    // zobraz tlačítko "Otevřít v novém okně"
    setTimeout(() => {
      if (!iframe.contentDocument && !iframe.contentWindow) {
        logger.warn('iframe pravděpodobně neobsahuje PDF - možná problém s CORS nebo prohlížeč');
        logger.log('[Tip] Zkuste tlačítko Sdílet/Stáhnout pro zobrazení v novém okně');
      } else {
        logger.log('PDF preview úspěšně zobrazen v iframe');
      }
    }, 1000);

  } catch (error) {
    logger.error('Chyba při otevírání PDF preview:', error);
    showNotif('error', 'Chyba při zobrazení PDF: ' + error.message);

    // Fallback: otevřít v novém okně
    if (pdfBlob) {
      logger.log('[Tip] Fallback: Otevírám PDF v novém okně...');
      const url = URL.createObjectURL(pdfBlob);
      window.open(url, '_blank');
    }
  }
}

/**
 * Zavře PDF preview modal
 */
function zavritPdfPreview() {
  logger.log('[Lock] Zavírám PDF preview...');

  const overlay = document.getElementById('pdfPreviewOverlay');
  overlay.classList.remove('active');

  // FIX: Vyčistit iframe a PDF URL
  const iframe = document.getElementById('pdfPreviewFrame');
  if (iframe) {
    if (iframe.src) {
      URL.revokeObjectURL(iframe.src);
      iframe.src = '';
    }

    // Odstranit onload handler
    iframe.onload = null;
  }

  // Vyčistit loading div pokud existuje
  const pdfBody = document.querySelector('.pdf-preview-body');
  if (pdfBody) {
    const loadingDiv = pdfBody.querySelector('div[style*="position: absolute"]');
    if (loadingDiv) {
      loadingDiv.remove();
    }
  }

  // Vyčistit reference
  aktualniPdfBlob = null;
  aktualniPdfNazev = 'protokol.pdf';

  logger.log('PDF preview zavřen');
}

/**
 * Sdílí nebo stáhne PDF
 * Na mobilu: Web Share API
 * Na desktopu: Stažení souboru
 */
async function sdiletNeboStahnutPdf() {
  if (!aktualniPdfBlob) {
    showNotif('error', 'PDF není dostupné');
    return;
  }

  try {
    logger.log('📤 Zpracovávám sdílení/stažení PDF...');

    // Pokus o Web Share API (mobil)
    if (navigator.share && navigator.canShare) {
      // Vytvořit File objekt pro sdílení
      const soubor = new File([aktualniPdfBlob], aktualniPdfNazev, {
        type: 'application/pdf'
      });

      // Zkontrolovat zda můžeme sdílet soubory
      if (navigator.canShare({ files: [soubor] })) {
        logger.log('📱 Používám Web Share API...');

        await navigator.share({
          files: [soubor],
          title: 'Servisní protokol WGS',
          text: 'Servisní protokol White Glove Service'
        });

        logger.log('PDF úspěšně sdílen pomocí Web Share API');
        showNotif('success', 'PDF sdílen');
        return;
      }
    }

    // Fallback: Stáhnout soubor (desktop nebo starší mobily)
    logger.log('[Save] Stahuji PDF...');

    const url = URL.createObjectURL(aktualniPdfBlob);
    const odkaz = document.createElement('a');
    odkaz.href = url;
    odkaz.download = aktualniPdfNazev;
    odkaz.style.display = 'none';

    document.body.appendChild(odkaz);
    odkaz.click();
    document.body.removeChild(odkaz);

    // Uvolnit URL po krátké prodlevě
    setTimeout(() => URL.revokeObjectURL(url), 100);

    logger.log('PDF úspěšně stažen');
    showNotif('success', 'PDF stažen');

  } catch (error) {
    // Pokud uživatel zruší sdílení, nezobrazovat chybu
    if (error.name === 'AbortError') {
      logger.log('ℹ️ Sdílení PDF zrušeno uživatelem');
      return;
    }

    logger.error('Chyba při sdílení/stahování PDF:', error);
    showNotif('error', 'Chyba při zpracování PDF');
  }
}

/**
 * Inicializace PDF preview event listenerů
 * Step 42: Zavírání modalu (close btn, overlay click, ESC) řeší Alpine.js
 *          Zde zůstávají pouze business tlačítka (sdílet, odeslat)
 */
function initPdfPreview() {
  logger.log('[Fix] Inicializuji PDF preview...');

  // Step 42: Tlačítko Zavřít nyní řeší Alpine.js (@click="close")

  // Tlačítko Sdílet/Stáhnout (pro export)
  const sdiletBtn = document.getElementById('pdfShareBtn');
  if (sdiletBtn) {
    sdiletBtn.addEventListener('click', sdiletNeboStahnutPdf);
  }

  // Tlačítko Odeslat zákazníkovi (pro email)
  const odeslatBtn = document.getElementById('pdfSendBtn');
  if (odeslatBtn) {
    odeslatBtn.addEventListener('click', () => {
      logger.log('📧 Potvrzuji odeslání zákazníkovi...');
      // Zavolat funkci z protokol.js
      if (typeof potvrditAOdeslat === 'function') {
        potvrditAOdeslat();
      } else {
        logger.error('Funkce potvrditAOdeslat není dostupná');
        showNotif('error', 'Chyba při odesílání');
      }
    });
  }

  // Step 42: Overlay click a ESC nyní řeší Alpine.js komponenta

  logger.log('PDF preview inicializován (Alpine.js Step 42)');
}

// Inicializovat po načtení DOMu
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPdfPreview);
} else {
  initPdfPreview();
}
