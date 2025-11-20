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

    // Uložit referenci
    aktualniPdfBlob = pdfBlob;
    aktualniPdfNazev = nazevSouboru;

    // Vytvořit URL pro iframe
    const pdfUrl = URL.createObjectURL(pdfBlob);

    // Nastavit iframe src
    const iframe = document.getElementById('pdfPreviewFrame');
    iframe.src = pdfUrl;

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
    overlay.classList.add('active');

    logger.log('✅ PDF preview zobrazen');

  } catch (error) {
    logger.error('❌ Chyba při otevírání PDF preview:', error);
    showNotif('error', 'Chyba při zobrazení PDF');
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
