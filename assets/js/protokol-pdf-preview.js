/**
 * PDF Preview Modal s možností sdílení
 * Zobrazí PDF v modalu s křížkem a ikonou pro sdílení/stažení
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
    logger.log('📄 Otevírám PDF preview modal...');
    logger.log('📄 PDF Blob:', pdfBlob);
    logger.log('📄 PDF Blob size:', pdfBlob.size, 'bytes');
    logger.log('📄 PDF Blob type:', pdfBlob.type);

    // Uložit referenci
    aktualniPdfBlob = pdfBlob;
    aktualniPdfNazev = nazevSouboru;

    // Vytvořit URL pro iframe
    const pdfUrl = URL.createObjectURL(pdfBlob);
    logger.log('📄 PDF URL vytvořena:', pdfUrl);

    // Nastavit iframe src
    const iframe = document.getElementById('pdfPreviewFrame');
    if (!iframe) {
      logger.error('❌ iframe #pdfPreviewFrame nenalezen!');
      showNotif('error', 'Chyba: iframe nenalezen');
      return;
    }

    logger.log('📄 Nastavuji iframe.src...');

    // Přidat loading text do iframe před načtením PDF
    iframe.srcdoc = '<html><body style="margin:0;padding:0;display:flex;align-items:center;justify-content:center;height:100vh;font-family:sans-serif;background:#f0f0f0;"><div style="text-align:center;"><h2 style="color:#333;">Načítám PDF...</h2><p style="color:#666;">Chvíli strpení</p></div></body></html>';

    // Nastavit iframe src (MUSÍ odstranit srcdoc atribut, jinak má srcdoc prioritu!)
    setTimeout(() => {
      iframe.removeAttribute('srcdoc');  // ❗ ODSTRANIT srcdoc atribut - má prioritu nad src!
      iframe.src = pdfUrl;
      logger.log('📄 iframe.src nastavena:', iframe.src);
    }, 100);

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

    // Zobrazit modal
    const overlay = document.getElementById('pdfPreviewOverlay');
    if (!overlay) {
      logger.error('❌ overlay #pdfPreviewOverlay nenalezen!');
      showNotif('error', 'Chyba: modal nenalezen');
      return;
    }

    overlay.classList.add('active');
    logger.log('✅ Modal zobrazen (active class přidána)');

    // FALLBACK: Pokud iframe nedokáže zobrazit PDF (některé browsery mají problémy),
    // zobraz tlačítko "Otevřít v novém okně"
    setTimeout(() => {
      if (!iframe.contentDocument && !iframe.contentWindow) {
        logger.warn('⚠️ iframe pravděpodobně neobsahuje PDF - možná problém s CORS nebo prohlížeč');
        logger.log('💡 Zkuste tlačítko Sdílet/Stáhnout pro zobrazení v novém okně');
      } else {
        logger.log('✅ PDF preview úspěšně zobrazen v iframe');
      }
    }, 1000);

  } catch (error) {
    logger.error('❌ Chyba při otevírání PDF preview:', error);
    showNotif('error', 'Chyba při zobrazení PDF: ' + error.message);

    // Fallback: otevřít v novém okně
    if (pdfBlob) {
      logger.log('💡 Fallback: Otevírám PDF v novém okně...');
      const url = URL.createObjectURL(pdfBlob);
      window.open(url, '_blank');
    }
  }
}

/**
 * Zavře PDF preview modal
 */
function zavritPdfPreview() {
  logger.log('🔒 Zavírám PDF preview...');

  const overlay = document.getElementById('pdfPreviewOverlay');
  overlay.classList.remove('active');

  // Vyčistit iframe
  const iframe = document.getElementById('pdfPreviewFrame');
  if (iframe.src) {
    URL.revokeObjectURL(iframe.src);
    iframe.src = '';
  }

  // Vyčistit reference
  aktualniPdfBlob = null;
  aktualniPdfNazev = 'protokol.pdf';

  logger.log('✅ PDF preview zavřen');
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

        logger.log('✅ PDF úspěšně sdílen pomocí Web Share API');
        showNotif('success', '✓ PDF sdílen');
        return;
      }
    }

    // Fallback: Stáhnout soubor (desktop nebo starší mobily)
    logger.log('💾 Stahuji PDF...');

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

    logger.log('✅ PDF úspěšně stažen');
    showNotif('success', '✓ PDF stažen');

  } catch (error) {
    // Pokud uživatel zruší sdílení, nezobrazovat chybu
    if (error.name === 'AbortError') {
      logger.log('ℹ️ Sdílení PDF zrušeno uživatelem');
      return;
    }

    logger.error('❌ Chyba při sdílení/stahování PDF:', error);
    showNotif('error', 'Chyba při zpracování PDF');
  }
}

/**
 * Inicializace PDF preview event listenerů
 */
function initPdfPreview() {
  logger.log('🔧 Inicializuji PDF preview...');

  // Tlačítko Zavřít
  const zavritBtn = document.getElementById('pdfCloseBtn');
  if (zavritBtn) {
    zavritBtn.addEventListener('click', zavritPdfPreview);
  }

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
        logger.error('❌ Funkce potvrditAOdeslat není dostupná');
        showNotif('error', 'Chyba při odesílání');
      }
    });
  }

  // Zavřít při kliknutí mimo modal
  const overlay = document.getElementById('pdfPreviewOverlay');
  if (overlay) {
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) {
        zavritPdfPreview();
      }
    });
  }

  // Zavřít ESC klávesou
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && overlay && overlay.classList.contains('active')) {
      zavritPdfPreview();
    }
  });

  logger.log('✅ PDF preview inicializován');
}

// Inicializovat po načtení DOMu
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initPdfPreview);
} else {
  initPdfPreview();
}
