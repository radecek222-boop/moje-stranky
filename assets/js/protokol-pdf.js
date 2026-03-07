/**
 * protokol-pdf.js
 * Generování PDF (protokol, fotky, ceník, export obou najednou)
 * Závisí na: protokol.js (currentReklamaceId, wgsToast, logger, fetchCsrfToken)
 */

async function generateProtocolPDF() {
  // Kontrola dostupnosti PDF knihoven (jsPDF + html2canvas)
  await zkontrolujPdfKnihovny();

  const { jsPDF } = window.jspdf;
  const doc = new jsPDF("p", "mm", "a4");

  // OPRAVA: Použít custom font s UTF-8 podporou pro české znaky
  try {
    if (window.vfs && window.vfs.Roboto_Regular_normal) {
      doc.addFileToVFS("Roboto-Regular.ttf", window.vfs.Roboto_Regular_normal);
      doc.addFont("Roboto-Regular.ttf", "Roboto", "normal");
      doc.setFont("Roboto");
    } else {
      doc.setFont("courier");
    }
  } catch (e) {
    doc.setFont("courier");
  }

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
      // KRITICKÉ: Nepoužívat width: 100% - rozbíjí grid layout split-section!
      div.style.cssText = `
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

      // KRITICKÉ: Detekovat jestli je input v tabulce nebo v gridu!
      // V TABULCE (two-col-table) POTŘEBUJEME width: 100%
      // V GRIDU (split-section) NESMÍME použít width: 100%
      const jeVTabulce = cloneInput.closest('table') !== null;

      // Pevné styly pro PDF
      div.style.cssText = `
        display: block;
        ${jeVTabulce ? 'width: 100%;' : ''}
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

      // KRITICKÉ: Detekovat jestli je select v tabulce nebo v gridu!
      // V TABULCE (two-col-table) POTŘEBUJEME width: 100%
      // V GRIDU (split-section) NESMÍME použít width: 100%
      const jeVTabulce = cloneSelect.closest('table') !== null;

      div.style.cssText = `
        display: block;
        ${jeVTabulce ? 'width: 100%;' : ''}
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
    backgroundColor: "#ffffff",
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
        pdf.setFont('Roboto', 'normal');
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
        pdf.setFont('Roboto', 'normal');
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
  if (!window.kalkulaceData) {
    logger.log('Kalkulace neexistuje - PRICELIST PDF nebude vygenerovano');
    return null;
  }

  logger.log('Generuji PDF PRICELIST...');
  logger.log('📊 DEBUG: window.kalkulaceData =', JSON.stringify(window.kalkulaceData, null, 2));

  // OPRAVA: Převést data z rozpis struktury do pole služeb a dílů
  const needsTransform = !!window.kalkulaceData.rozpis &&
    (!Array.isArray(window.kalkulaceData.sluzby) || window.kalkulaceData.sluzby.length === 0 ||
     !Array.isArray(window.kalkulaceData.dilyPrace) || window.kalkulaceData.dilyPrace.length === 0);

  if (needsTransform) {
    logger.log('✅ Převádím rozpis data do služeb a dílů...');
    window.kalkulaceData.sluzby = [];
    window.kalkulaceData.dilyPrace = [];

    const rozpis = window.kalkulaceData.rozpis;
    const CENY = {
      diagnostika: 110,
      prvniDil: 205,
      dalsiDil: 70,
      zakladniSazba: 165,
      mechanismusPriplatek: 45,
      druhaOsoba: 95,
      material: 50,
      vyzvednutiSklad: 10
    };

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
      // FALLBACK: Rozpis je prázdný → vytvořit obecnou položku
      logger.log('⚠️ Rozpis je prázdný - vytvářím obecnou položku');

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

      // Dopravné
      if (window.kalkulaceData.dopravne > 0) {
        window.kalkulaceData.sluzby.push({
          nazev: `Doprava (${window.kalkulaceData.vzdalenost || 0} km)`,
          cena: window.kalkulaceData.dopravne,
          pocet: 1
        });
      }

      logger.log('✅ Fallback položka vytvořena:', window.kalkulaceData.sluzby);
    } else {
      // Normální transformace z rozpisu

      // Diagnostika
      if (rozpis.diagnostika && rozpis.diagnostika > 0) {
      window.kalkulaceData.sluzby.push({
        nazev: 'Inspekce / diagnostika',
        cena: rozpis.diagnostika,
        pocet: 1
      });
    }

    // Čalounické práce
    if (rozpis.calouneni) {
      const { sedaky, operky, podrucky, panely, pocetProduktu } = rozpis.calouneni;
      const celkemDilu = (sedaky || 0) + (operky || 0) + (podrucky || 0) + (panely || 0);

      if (celkemDilu > 0) {
        const skutecnyPocetProduktu = Math.min(pocetProduktu || 1, celkemDilu || 1);
        let cenaDilu;

        if (skutecnyPocetProduktu === 1) {
          cenaDilu = celkemDilu === 1 ?
            CENY.prvniDil :
            CENY.prvniDil + (celkemDilu - 1) * CENY.dalsiDil;
        } else {
          const dalsiDily = celkemDilu - skutecnyPocetProduktu;
          cenaDilu = (skutecnyPocetProduktu * CENY.prvniDil) + (dalsiDily * CENY.dalsiDil);
        }

        window.kalkulaceData.dilyPrace.push({
          nazev: `Čalounické práce (${celkemDilu} ${celkemDilu === 1 ? 'díl' : celkemDilu <= 4 ? 'díly' : 'dílů'})`,
          cena: cenaDilu,
          pocet: celkemDilu,
          detail: celkemDilu === 1 ?
            `První díl: ${CENY.prvniDil} EUR` :
            `První díl: ${CENY.prvniDil} EUR + ${celkemDilu - 1} dalších dílů × ${CENY.dalsiDil} EUR`
        });
      }
    }

    // Mechanické práce
    if (rozpis.mechanika) {
      const { relax, vysuv } = rozpis.mechanika;
      const celkemMechanismu = (relax || 0) + (vysuv || 0);

      if (celkemMechanismu > 0) {
        const cenaMechanismu = celkemMechanismu * CENY.mechanismusPriplatek;
        window.kalkulaceData.dilyPrace.push({
          nazev: `Mechanické opravy (${celkemMechanismu} ${celkemMechanismu === 1 ? 'mechanismus' : celkemMechanismu <= 4 ? 'mechanismy' : 'mechanismů'})`,
          cena: cenaMechanismu,
          pocet: celkemMechanismu,
          detail: `${celkemMechanismu} × ${CENY.mechanismusPriplatek} EUR`
        });
      }

      // Základní sazba pouze pro ČISTĚ mechanické práce
      if (celkemMechanismu > 0 && window.kalkulaceData.typServisu === 'mechanika') {
        window.kalkulaceData.sluzby.push({
          nazev: 'Základní servisní sazba',
          cena: CENY.zakladniSazba,
          pocet: 1
        });
      }
    }

    // Doplňky
    if (rozpis.doplnky) {
      if (rozpis.doplnky.material) {
        window.kalkulaceData.sluzby.push({
          nazev: 'Materiál dodán od WGS',
          cena: CENY.material,
          pocet: 1
        });
      }

      if (rozpis.doplnky.vyzvednutiSklad) {
        window.kalkulaceData.sluzby.push({
          nazev: 'Vyzvednutí dílu na skladě',
          cena: CENY.vyzvednutiSklad,
          pocet: 1
        });
      }
    }
    } // Konec normální transformace (else blok)

    logger.log('Převedena data z rozpis struktury:', {
      sluzby: window.kalkulaceData.sluzby,
      dilyPrace: window.kalkulaceData.dilyPrace
    });
  }

  // Kontrola dostupnosti PDF knihoven
  await zkontrolujPdfKnihovny();

  const { jsPDF } = window.jspdf;
  const pdf = new jsPDF({
    orientation: "p",
    unit: "mm",
    format: "a4",
    putOnlyUsedFonts: true,
    compress: true
  });

  // OPRAVA: Použít custom font s UTF-8 podporou pro české znaky
  try {
    if (window.vfs && window.vfs.Roboto_Regular_normal) {
      pdf.addFileToVFS("Roboto-Regular.ttf", window.vfs.Roboto_Regular_normal);
      pdf.addFont("Roboto-Regular.ttf", "Roboto", "normal");
      pdf.setFont("Roboto", "normal");
      logger.log('✅ PDF: Použit Roboto font s UTF-8 podporou');
    } else {
      logger.warn('⚠️ PDF: window.vfs.Roboto_Regular_normal NENÍ dostupný!');
      logger.log('window.vfs existuje:', !!window.vfs);
      if (window.vfs) {
        logger.log('window.vfs klíče:', Object.keys(window.vfs));
      }
      pdf.setFont("courier");
      logger.log('PDF: Použit Courier jako fallback');
    }
  } catch (e) {
    logger.error('❌ PDF: Chyba při nastavení fontu:', e);
    pdf.setFont("courier");
  }

  // DEBUG: Zobrazit dostupné fonty
  if (pdf.getFontList) {
    logger.log('📝 Dostupné fonty v PDF:', pdf.getFontList());
  } else {
    logger.warn('⚠️ getFontList() není dostupná');
  }

  // Nastavit font PŘED vykreslováním
  pdf.setFont("Roboto", "normal");

  // Helper pro bezpečný výpis textu s českými znaky
  const pdfText = (text, x, y, options = {}) => window.pdfTextSafe(pdf, text, x, y, options);

  const pageWidth = pdf.internal.pageSize.getWidth();
  const pageHeight = pdf.internal.pageSize.getHeight();
  const margin = 15;
  let yPos = margin;

  // === HLAVIČKA ===
  pdf.setFontSize(20);
  pdf.setFont('Roboto', 'normal');
  pdf.setTextColor(0, 0, 0); // Černá
  pdfText('PRICELIST', pageWidth / 2, yPos, { align: 'center' });
  yPos += 15;

  // === ÚDAJE ZÁKAZNÍKA ===
  const zakaznikJmeno = document.getElementById('customer')?.value || 'N/A';
  const zakaznikAdresa = window.kalkulaceData.adresa || document.getElementById('address')?.value || 'N/A';
  const zakaznikTelefon = document.getElementById('phone')?.value || '';
  const zakaznikEmail = document.getElementById('email')?.value || '';
  const reklamaceCislo = document.getElementById('claim-number')?.value || '';

  pdf.setFontSize(10);
  pdf.setFont('Roboto', 'normal');
  pdf.setTextColor(0, 0, 0);

  if (reklamaceCislo) {
    pdfText(`Číslo reklamace: ${reklamaceCislo}`, margin, yPos);
    yPos += 6;
  }

  pdf.setFont('Roboto', 'normal');
  pdfText(`Zákazník: ${zakaznikJmeno}`, margin, yPos);
  yPos += 6;

  pdf.setFont('Roboto', 'normal');
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
  pdf.setLineWidth(0.5);
  pdf.setDrawColor(0, 0, 0); // Černá
  pdf.line(margin, yPos, pageWidth - margin, yPos);
  yPos += 10;

  // === CENOTVORBA ===
  pdf.setFontSize(14);
  pdf.setFont('Roboto', 'normal');
  pdfText('Rozpis cen', margin, yPos);
  yPos += 10;

  pdf.setFontSize(10);
  pdf.setFont('Roboto', 'normal');

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
    pdf.setFont('Roboto', 'normal');
    pdfText('Služby:', margin, yPos);
    yPos += 7;

    pdf.setFont('Roboto', 'normal');
    window.kalkulaceData.sluzby.forEach(sluzba => {
      // Název služby
      pdfText(`  ${sluzba.nazev}`, margin, yPos);
      yPos += 6;

      // Detailní rozpis pokud má počet
      if (sluzba.pocet && sluzba.pocet > 1) {
        const jednotkovaCena = (sluzba.cena / sluzba.pocet).toFixed(2);
        const celkovaCena = sluzba.cena.toFixed(2);
        const detail = `    ${sluzba.pocet} ks × ${jednotkovaCena} EUR = ${celkovaCena} EUR`;
        pdf.setFont('Roboto', 'normal');
        pdf.setFontSize(9);
        pdfText(detail, margin + 5, yPos);
        pdf.setFontSize(10);
        pdf.setFont('Roboto', 'normal');
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
    pdf.setFont('Roboto', 'normal');
    pdfText('Díly a práce:', margin, yPos);
    yPos += 7;

    pdf.setFont('Roboto', 'normal');
    window.kalkulaceData.dilyPrace.forEach(polozka => {
      // Název položky + cena vpravo
      const celkovaCena = polozka.cena.toFixed(2);
      pdfText(`  ${polozka.nazev}`, margin, yPos);
      pdfText(`${celkovaCena} EUR`, pageWidth - margin - 30, yPos);
      yPos += 6;

      // Detailní rozpis (menším písmem, bez celkové ceny)
      const jednotkovaCena = polozka.pocet > 1 ? (polozka.cena / polozka.pocet).toFixed(2) : polozka.cena.toFixed(2);
      const detail = `    ${polozka.pocet} ks × ${jednotkovaCena} EUR`;
      pdf.setFont('Roboto', 'normal');
      pdf.setFontSize(9);
      pdfText(detail, margin + 5, yPos);
      pdf.setFontSize(10);
      pdf.setFont('Roboto', 'normal');
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
  pdf.setLineWidth(0.3);
  pdf.line(margin, yPos, pageWidth - margin, yPos);
  yPos += 8;

  // === CELKOVÁ CENA ===
  pdf.setFontSize(14);
  pdf.setFont('Roboto', 'normal');
  pdf.setTextColor(0, 0, 0); // Černá
  pdfText('CELKEM:', margin, yPos);
  pdfText(`${window.kalkulaceData.celkovaCena.toFixed(2)} EUR`, pageWidth - margin - 40, yPos);
  yPos += 10;

  // === POZNÁMKY ===
  if (window.kalkulaceData.poznamka) {
    yPos += 5;
    pdf.setFontSize(10);
    pdf.setFont('Roboto', 'normal');
    pdf.setTextColor(100, 100, 100);
    pdf.text('Poznámka:', margin, yPos);
    yPos += 6;
    pdf.setFont('Roboto', 'normal');

    const lines = pdf.splitTextToSize(window.kalkulaceData.poznamka, pageWidth - 2 * margin);
    lines.forEach(line => {
      pdf.text(line, margin, yPos);
      yPos += 5;
    });
  }

  logger.log(`PDF PRICELIST vytvořen (${window.kalkulaceData.celkovaCena.toFixed(2)} €)`);

  return pdf;
}

async function exportBothPDFs() {
  try {
    // Zobrazit WGS loading dialog
    showLoadingWithMessage(true, 'Připravuji protokol...', 'Prosím čekejte');

    logger.log('[List] Generuji kompletní PDF (protokol + PRICELIST + fotodokumentace)...');
    logger.log('💰 Kontrola kalkulace - window.kalkulaceData:', window.kalkulaceData);

    // Vytvořit JEDNO PDF s protokolem
    const doc = await generateProtocolPDF();

    // Pokud existuje kalkulace, přidat PRICELIST
    if (window.kalkulaceData) {
      showLoadingWithMessage(true, 'Přidávám pricelist...', `Celková cena: ${window.kalkulaceData.celkovaCena.toFixed(2)} €`);
      logger.log('Kalkulace nalezena - přidávám PRICELIST...');
      logger.log('[Stats] Kalkulace data:', window.kalkulaceData);

      // OPRAVA: Převést data z rozpis struktury do pole služeb a dílů
      const needsTransform2 = !!window.kalkulaceData.rozpis && (!Array.isArray(window.kalkulaceData.sluzby) || window.kalkulaceData.sluzby.length === 0 || !Array.isArray(window.kalkulaceData.dilyPrace) || window.kalkulaceData.dilyPrace.length === 0); if (needsTransform2) {
        window.kalkulaceData.sluzby = [];
        window.kalkulaceData.dilyPrace = [];
        const rozpis = window.kalkulaceData.rozpis;
        const CENY = { diagnostika: 110, prvniDil: 205, dalsiDil: 70, zakladniSazba: 165, mechanismusPriplatek: 45, druhaOsoba: 95, material: 50, vyzvednutiSklad: 10 };
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
      logger.warn('Kalkulace nenalezena - PRICELIST nebude v PDF');
      logger.warn('   Možné příčiny:');
      logger.warn('   1. Kalkulace nebyla vytvořena');
      logger.warn('   2. Kalkulace nebyla uložena do databáze');
      logger.warn('   3. Chyba při načítání z databáze');
    }

    // Pokud jsou fotky, přidat fotodokumentaci na KONEC protokolu
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
      pdfText('FOTODOKUMENTACE', pageWidth / 2, 20, { align: 'center' });

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
    showLoadingWithMessage(true, 'Ukládám do knihovny...', 'PDF bude dostupné v knihovně');
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

    // Uložit textová data do DB (bez označení jako hotové - to dělá jen "Odeslat zákazníkovi")
    await saveProtokolToDB();

  } catch (error) {
    logger.error('Chyba při generování PDF:', error);
    showNotif("error", "Chyba při vytváření PDF");
  } finally {
    showLoading(false);
  }
}

