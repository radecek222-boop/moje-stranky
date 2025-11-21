// Kontrola - tato stránka je pouze pro techniky a adminy
(async function() {
    try {
        const response = await fetch("app/admin_session_check.php");
        const data = await response.json();

        if (!data.logged_in) {
            alert(t('please_log_in'));
            window.location.href = "login.php";
            return;
        }

        if (data.role === "prodejce") {
            alert(t('page_for_techs_admins_only'));
            window.location.href = "seznam.php";
        }
    } catch (err) {
        logger.error("Chyba kontroly přístupu:", err);
    }
})();

// === HAMBURGER MENU ===
function toggleMenu() {
  const navMenu = document.getElementById('navMenu');
  const hamburger = document.querySelector('.hamburger');

  navMenu.classList.toggle('active');
  hamburger.classList.toggle('active');
}

// === NOTIFIKACE ===
function showNotification(message, type = 'info') {
  const notification = document.getElementById('notif');
  if (!notification) {
    console.warn('Notification element not found, falling back to console');
    console.log(`[${type.toUpperCase()}] ${message}`);
    return;
  }

  notification.textContent = message;
  notification.className = `notif ${type}`;
  notification.style.display = 'block';
  notification.style.opacity = '1';

  // Auto-hide po 3 sekundách (kromě error)
  if (type !== 'error') {
    setTimeout(() => {
      notification.style.opacity = '0';
      setTimeout(() => {
        notification.style.display = 'none';
      }, 300);
    }, 3000);
  } else {
    // Error zprávy se skryjí po 5 sekundách
    setTimeout(() => {
      notification.style.opacity = '0';
      setTimeout(() => {
        notification.style.display = 'none';
      }, 300);
    }, 5000);
  }
}

// Zavřít menu při kliknutí na odkaz
document.addEventListener('DOMContentLoaded', () => {
  const navLinks = document.querySelectorAll('.nav a');
  navLinks.forEach(link => {
    link.addEventListener('click', () => {
      const nav = document.getElementById('navMenu');
      const hamburger = document.querySelector('.hamburger');
      nav.classList.remove('active');
      hamburger.classList.remove('active');
    });
  });
});

let signaturePad;
let attachedPhotos = [];
let currentReklamaceId = null;
let currentReklamace = null;

// PDF preview kontext
let pdfPreviewContext = null; // 'export' nebo 'send'
let cachedPdfDoc = null; // uložený jsPDF document
let cachedPdfBase64 = null; // uložený base64 pro odeslání

async function fetchCsrfToken() {
  if (typeof getCSRFToken === 'function') {
    try {
      const token = await getCSRFToken();
      if (token) {
        return token;
      }
    } catch (err) {
      logger?.warn?.('CSRF token z getCSRFToken selhal:', err);
    }
  }

  if (typeof getCSRFTokenFromMeta === 'function') {
    const metaToken = getCSRFTokenFromMeta();
    if (metaToken) {
      return metaToken;
    }
  }

  const fallbackMeta = document.querySelector('meta[name="csrf-token"]');
  if (fallbackMeta) {
    const token = fallbackMeta.getAttribute('content');
    if (token) {
      window.csrfTokenCache = token;
      return token;
    }
  }

  throw new Error('CSRF token není k dispozici. Obnovte stránku a zkuste to znovu.');
}

window.addEventListener("DOMContentLoaded", async () => {
  logger.log('🚀 Inicializace protokolu...');
  initSignaturePad();

  const urlParams = new URLSearchParams(window.location.search);
  currentReklamaceId = urlParams.get('id');

  logger.log('📋 ID z URL:', currentReklamaceId);

  if (currentReklamaceId) {
    logger.log('✅ ID nalezeno v URL');
    await loadReklamace(currentReklamaceId);
    loadPhotosFromDatabase(currentReklamaceId);
  } else {
    logger.warn('⚠️ Chybí ID v URL - zkusím načíst z localStorage');
    await loadReklamace(null);

    if (currentReklamace && currentReklamace.id) {
      logger.log('✅ ID nalezeno v načtených datech:', currentReklamace.id);
      currentReklamaceId = currentReklamace.id;
      loadPhotosFromDatabase(currentReklamaceId);
    } else {
      logger.error('❌ ID se nepodařilo najít!');
    }
  }

  const today = new Date().toISOString().split('T')[0];
  document.getElementById("sign-date").value = today;
  document.getElementById("visit-date").value = today;

  setupAutoTranslate();
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
      }, 1500);
    });
  });
}

function initSignaturePad() {
  const canvas = document.getElementById("signature-pad");
  const resize = () => {
    const ratio = Math.max(window.devicePixelRatio || 1, 1);
    const rect = canvas.getBoundingClientRect();
    const cssWidth = rect.width;
    const cssHeight = rect.height;

    canvas.width = cssWidth * ratio;
    canvas.height = cssHeight * ratio;
    canvas.getContext("2d").scale(ratio, ratio);
  };
  window.addEventListener("resize", resize);
  resize();
  signaturePad = new SignaturePad(canvas, {
    minWidth: 1,
    maxWidth: 2.5,
    penColor: "black",
    backgroundColor: "white"
  });
}

async function loadPhotosFromDatabase(customerId) {
  try {
    if (!customerId) {
      logger.warn('ID zákazníka nenalezeno');
      return;
    }

    logger.log('═══════════════════════════════════════');
    logger.log('🖼️ NAČÍTÁM FOTKY Z DATABÁZE');
    logger.log('═══════════════════════════════════════');
    logger.log('🔑 customerId:', customerId);

    // Načíst z API
    const response = await fetch(`api/get_photos_api.php?reklamace_id=${customerId}`);
    const data = await response.json();

    if (!data.success || data.total_photos === 0) {
      logger.log('❌ Fotky nenalezeny v databázi');
      showNotif("warning", "Nebyly nalezeny fotky");
      logger.log('═══════════════════════════════════════');
      return;
    }

    logger.log('✅ Fotky načteny z databáze!');
    const sections = data.sections;

    logger.log('📦 Sekce:', Object.keys(sections));

    const sectionLabels = {
      'before': 'BEFORE',
      'id': 'ID',
      'problem': 'DETAIL BUG',
      'repair': 'REPAIR',
      'after': 'AFTER'
    };

    let totalPhotos = 0;
    let totalVideos = 0;

    const orderedSections = ['before', 'id', 'problem', 'repair', 'after'];

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

    logger.log(`📊 CELKEM: ${totalPhotos} fotek, ${totalVideos} videí`);

    if (attachedPhotos.length > 0) {
      const previewPhotos = attachedPhotos.map(p => typeof p === 'string' ? p : p.data);
      renderPhotoPreview(previewPhotos);
      showNotif("success", `✓ Načteno ${totalPhotos} fotek`);
      logger.log('✅ Fotky úspěšně načteny s popisky');
    } else {
      logger.log('⚠️ Žádné fotky k zobrazení');
      showNotif("info", "Žádné fotky");
    }

    logger.log('═══════════════════════════════════════');

  } catch (error) {
    logger.error('❌ Chyba při načítání fotek:', error);
    showNotif("error", "Chyba načítání fotek");
  }
}

async function loadReklamace(id) {
  showLoading(true);

  try {
    logger.log('🔍 Načítám data zákazníka...');
    logger.log('📋 ID z URL:', id);

    const localData = localStorage.getItem('currentCustomer');

    if (localData) {
      logger.log('✅ Data nalezena v localStorage');
      const customer = JSON.parse(localData);
      logger.log('📦 Data zákazníka:', customer);

      // ✅ KONTROLA OPRÁVNĚNÍ
      const currentUser = JSON.parse(localStorage.getItem('currentUser') || '{}');
      logger.log('👤 Aktuální uživatel:', currentUser.name, '| Role:', currentUser.role);

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
      logger.log('✅ Oprávnění potvrzeno');

      const customerName = customer.jmeno || customer.zakaznik || '';
      let ulice = '', mesto = '', psc = '';

      if (customer.adresa) {
        const parts = customer.adresa.split(',').map(s => s.trim());
        ulice = parts[0] || '';
        mesto = parts[1] || '';
        psc = parts[2] || '';
        logger.log('📍 Adresa (nový formát):', { ulice, mesto, psc });
      } else {
        ulice = customer.ulice || '';
        mesto = customer.mesto || '';
        psc = customer.psc || '';
        logger.log('📍 Adresa (starý formát):', { ulice, mesto, psc });
      }

      logger.log('📝 Vyplňuji formulář...');
      document.getElementById("order-number").value = customer.reklamace_id || "";
      document.getElementById("claim-number").value = customer.cislo || "";
      document.getElementById("customer").value = customerName;
      document.getElementById("address").value = customer.adresa || `${ulice}, ${mesto}, ${psc}`;
      document.getElementById("phone").value = customer.telefon || "";
      document.getElementById("email").value = customer.email || "";
      document.getElementById("brand").value = customer.created_by_name || customer.prodejce || "";
      document.getElementById("model").value = customer.model || "";
      document.getElementById("description-cz").value = customer.popis_problemu || "";

      currentReklamace = customer;
      currentReklamaceId = customer.reklamace_id || customer.cislo || customer.id;

      logger.log('✅ Data zákazníka úspěšně načtena a vyplněna');
      showNotif("success", "✓ Data načtena");
      showLoading(false);
      return;
    }

    logger.warn('⚠️ Data v localStorage nenalezena');

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
      logger.error('❌ Load reklamace error:', response.status, errorText);
      try {
        const errorJson = JSON.parse(errorText);
        logger.error('❌ Load error detail:', errorJson);
        throw new Error(errorJson.error || errorJson.message || `Server error ${response.status}`);
      } catch (parseErr) {
        throw new Error(`Server error ${response.status}: ${errorText.substring(0, 200)}`);
      }
    }

    const result = await response.json();

    if (result.status === 'success') {
      logger.log('✅ Data načtena z API');
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
      document.getElementById("brand").value = currentReklamace.created_by_name || currentReklamace.prodejce || "";
      document.getElementById("model").value = currentReklamace.model || "";
      document.getElementById("description-cz").value = currentReklamace.popis_problemu || "";
      showNotif("success", "Reklamace načtena");
    } else {
      showNotif("error", result.message || "Reklamace nenalezena");
    }
  } catch (error) {
    logger.error('❌ Chyba načítání:', error);
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

  overlay.classList.toggle("show", show);

  if (textElement && show) {
    textElement.textContent = message;
  }
}

function showNotif(type, message) {
  const notif = document.getElementById("notif");
  notif.className = `notif ${type}`;
  notif.textContent = message;
  notif.classList.add("show");
  setTimeout(() => notif.classList.remove("show"), 3000);
}

function updateTotal() {
  const work = parseFloat(document.getElementById("price-work").value) || 0;
  const material = parseFloat(document.getElementById("price-material").value) || 0;
  const second = parseFloat(document.getElementById("price-second").value) || 0;
  const transport = parseFloat(document.getElementById("price-transport").value) || 0;
  const total = work + material + second + transport;
  document.getElementById("price-total").value = total.toFixed(2);
}

async function attachPhotos() {
  const input = document.createElement("input");
  input.type = "file";
  input.accept = "image/*";
  input.multiple = true;
  input.capture = "environment";
  input.style.display = "none";
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

function toBase64(blob) {
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
    const img = document.createElement("img");
    img.src = photoData;
    img.onclick = () => window.open(photoData, "_blank");
    grid.appendChild(img);
  });
}

async function generateProtocolPDF() {
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF("p", "mm", "a4");

  const wrapper = document.querySelector(".wrapper");

  logger.log('📄 Vytvářím desktop clone pro PDF generování...');

  // ❗ CLONE APPROACH: Vytvoření skrytého desktop wrapper mimo viewport
  // Tento přístup zajistí identický PDF na mobilu i desktopu
  const clone = wrapper.cloneNode(true);
  clone.classList.add('pdf-clone-desktop');
  clone.id = 'pdf-clone-wrapper-temp';

  // Přidat clone do DOM (mimo viewport, neviditelný)
  document.body.appendChild(clone);

  // Zkopírovat signature pad canvas obsah do clone
  const originalCanvas = wrapper.querySelector('#signature-pad');
  const cloneCanvas = clone.querySelector('#signature-pad');
  if (originalCanvas && cloneCanvas) {
    try {
      const ctx = cloneCanvas.getContext('2d');
      ctx.drawImage(originalCanvas, 0, 0);
      logger.log('✅ Signature pad zkopírován do clone');
    } catch (e) {
      logger.warn('⚠️ Nepodařilo se zkopírovat signature pad:', e);
    }
  }

  // Počkat na reflow clone (desktop layout se aplikuje)
  await new Promise(resolve => setTimeout(resolve, 150));

  logger.log('📸 Renderuji clone pomocí html2canvas...');

  const canvas = await html2canvas(clone, {
    scale: 3,
    backgroundColor: "#fff",
    useCORS: true,
    logging: false,
    imageTimeout: 0,
    allowTaint: true,
    letterRendering: true
  });

  const imgData = canvas.toDataURL("image/jpeg", 0.98);

  const pageWidth = 210;
  const pageHeight = 297;
  const margin = 10;

  const availableWidth = pageWidth - (margin * 2);
  const availableHeight = pageHeight - (margin * 2);

  const canvasRatio = canvas.height / canvas.width;

  let imgWidth = availableWidth;
  let imgHeight = imgWidth * canvasRatio;

  if (imgHeight > availableHeight) {
    imgHeight = availableHeight;
    imgWidth = imgHeight / canvasRatio;
  }

  const xOffset = (pageWidth - imgWidth) / 2;
  const yOffset = margin;

  doc.addImage(imgData, "JPEG", xOffset, yOffset, imgWidth, imgHeight);

  // ❗ Odstranit clone z DOM
  document.body.removeChild(clone);
  logger.log('✅ Clone odstraněn, PDF vygenerováno');

  return doc;
}

async function generatePhotosPDF() {
  if (!attachedPhotos.length) return null;

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

  logger.log(`📄 Vytvářím PDF: ${attachedPhotos.length} fotek, ${Math.ceil(attachedPhotos.length / photosPerPage)} stránek`);

  for (let i = 0; i < attachedPhotos.length; i++) {
    const photo = attachedPhotos[i];

    const photoData = typeof photo === 'string' ? photo : photo.data;
    const photoLabel = typeof photo === 'object' ? photo.label : '';

    if (i > 0 && i % photosPerPage === 0) {
      pdf.addPage();
      logger.log(`📄 Přidána nová stránka (fotka ${i + 1})`);
    }

    const indexOnPage = i % photosPerPage;
    const col = indexOnPage % cols;
    const row = Math.floor(indexOnPage / cols);

    const x = margin + (col * (cellWidth + gap));
    const y = margin + (row * (cellHeight + gap));

    // Text VŽDY nahoře vlevo na horní hraně fotky
    if (photoLabel) {
      pdf.setFontSize(8);
      pdf.setFont('helvetica', 'bold');
      pdf.setTextColor(0, 0, 0);
      pdf.text(photoLabel, x + 1, y + 3);
    }

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

      pdf.addImage(photoData, "JPEG", x + offsetX, photoY + offsetY, finalWidth, finalHeight, undefined, 'MEDIUM');

      logger.log(`  📸 Fotka ${i + 1}/${attachedPhotos.length} - ${photoLabel || 'bez popisku'} (${imgWidth}x${imgHeight} → ${Math.round(finalWidth)}x${Math.round(finalHeight)}mm)`);

    } catch (err) {
      logger.warn(`⚠️ Nelze detekovat velikost fotky ${i + 1}, používám celou buňku`);
      pdf.addImage(photoData, "JPEG", x, photoY, maxPhotoWidth, maxPhotoHeight, undefined, 'MEDIUM');
    }
  }

  logger.log(`✅ PDF s fotkami vytvořeno (${attachedPhotos.length} fotek s popisky)`);

  return pdf;
}

async function exportBothPDFs() {
  try {
    showLoading(true);

    logger.log('📋 Generuji kompletní PDF (protokol + fotodokumentace)...');

    // Vytvořit JEDNO PDF s protokolem
    const doc = await generateProtocolPDF();

    // Pokud jsou fotky, přidat fotodokumentaci na KONEC protokolu
    if (attachedPhotos.length > 0) {
      logger.log('📸 Přidávám fotodokumentaci...');

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
        `Datum: ${document.getElementById('visit-date')?.value || new Date().toLocaleDateString('cs-CZ')}`
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
          logger.warn(`⚠️ Nelze přidat miniaturu ${i + 1}`);
        }
      }

      logger.log(`✅ Index ${attachedPhotos.length} fotek vytvořen`);

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

        if (photoLabel) {
          doc.setFontSize(8);
          doc.setFont('helvetica', 'bold');
          doc.setTextColor(0, 0, 0);
          doc.text(photoLabel, x + 1, y + 3);
        }

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

          doc.addImage(photoData, "JPEG", x + offsetX, photoY + offsetY, finalWidth, finalHeight, undefined, 'MEDIUM');

          logger.log(`  📸 Fotka ${i + 1}/${attachedPhotos.length} - ${photoLabel}`);

        } catch (err) {
          logger.warn(`⚠️ Chyba fotky ${i + 1}`);
          doc.addImage(photoData, "JPEG", x, photoY, maxPhotoWidth, maxPhotoHeight, undefined, 'MEDIUM');
        }
      }

      logger.log(`✅ Fotodokumentace přidána (${attachedPhotos.length} fotek)`);
      showNotif("success", `✓ PDF vytvořeno (protokol + ${attachedPhotos.length} fotek)`);

    } else {
      showNotif("success", "✓ Protokol vytvořen (bez fotek)");
    }

    // Uložit PDF do databáze (stejně jako při odeslání emailem)
    logger.log('💾 Ukládám PDF do databáze...');
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
          logger.log('✅ PDF úspěšně uložen do databáze');
        } else {
          logger.warn('⚠️ PDF se nepodařilo uložit:', saveResult.message);
        }
      }
    } catch (err) {
      logger.error('❌ Chyba při ukládání PDF:', err);
      // Pokračujeme i přes chybu - alespoň zobrazíme PDF
    }

    // Zobrazit PDF v preview modalu místo window.open
    const pdfBlob = doc.output("blob");
    const cisloReklamace = document.getElementById('claim-number')?.value || 'protokol';
    const nazevSouboru = `WGS_Protokol_${cisloReklamace.replace(/\s+/g, '_')}.pdf`;

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
    logger.log('📋 Označuji reklamaci jako hotovou...');
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
        logger.log('✅ Reklamace označena jako hotová');
      }
    } catch (err) {
      logger.error('❌ Chyba při označování:', err);
    }

  } catch (error) {
    logger.error('❌ Chyba při generování PDF:', error);
    showNotif("error", "Chyba při vytváření PDF");
  } finally {
    showLoading(false);
  }
}

async function sendToCustomer() {
  try {
    // FÁZE 1: Generování kompletního PDF (protokol + fotky) pro NÁHLED
    showLoadingWithMessage(true, '📄 Generuji náhled PDF...');
    logger.log('📋 Generuji kompletní PDF pro náhled před odesláním...');

    // Vytvořit JEDNO PDF s protokolem
    const doc = await generateProtocolPDF();

    // Pokud jsou fotky, přidat fotodokumentaci na KONEC protokolu (stejně jako exportBothPDFs)
    if (attachedPhotos.length > 0) {
      showLoadingWithMessage(true, `📸 Přidávám fotodokumentaci (${attachedPhotos.length} fotek)...`);
      logger.log('📸 Přidávám fotodokumentaci...');

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
        `Datum: ${document.getElementById('visit-date')?.value || new Date().toLocaleDateString('cs-CZ')}`
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
          logger.warn(`⚠️ Nelze přidat miniaturu ${i + 1}`);
        }
      }

      logger.log(`✅ Index ${attachedPhotos.length} fotek vytvořen`);

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

        if (photoLabel) {
          doc.setFontSize(8);
          doc.setFont('helvetica', 'bold');
          doc.setTextColor(0, 0, 0);
          doc.text(photoLabel, x + 1, y + 3);
        }

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

          doc.addImage(photoData, "JPEG", x + offsetX, photoY + offsetY, finalWidth, finalHeight, undefined, 'MEDIUM');

          logger.log(`  📸 Fotka ${i + 1}/${attachedPhotos.length} - ${photoLabel}`);

        } catch (err) {
          logger.warn(`⚠️ Chyba fotky ${i + 1}`);
          doc.addImage(photoData, "JPEG", x, photoY, maxPhotoWidth, maxPhotoHeight, undefined, 'MEDIUM');
        }
      }

      logger.log(`✅ Fotodokumentace přidána (${attachedPhotos.length} fotek)`);
    }

    // Konverze na base64 a uložení pro pozdější odeslání
    const completePdfBase64 = doc.output("datauristring").split(",")[1];

    // Uložit pro pozdější odeslání
    cachedPdfDoc = doc;
    cachedPdfBase64 = completePdfBase64;
    pdfPreviewContext = 'send';

    // Zobrazit náhled PDF PŘED odesláním
    showLoadingWithMessage(false); // Skrýt loading

    const pdfBlob = doc.output("blob");
    const cisloReklamace = document.getElementById('claim-number')?.value || 'protokol';
    const nazevSouboru = `WGS_Protokol_${cisloReklamace.replace(/\s+/g, '_')}.pdf`;

    logger.log('📄 Zobrazuji náhled PDF před odesláním...');

    // Použít funkci pro zobrazení PDF preview
    if (typeof otevritPdfPreview === 'function') {
      otevritPdfPreview(pdfBlob, nazevSouboru);
    } else {
      // Fallback - rovnou odeslat pokud preview není dostupný
      await potvrditAOdeslat();
    }

  } catch (error) {
    logger.error('❌ Chyba při generování PDF:', error);
    showNotif("error", "Chyba při vytváření PDF");
    showLoadingWithMessage(false);
  }
}

/**
 * Potvrzení a odeslání emailu se zákazníkovi
 * Volá se z preview modalu po kliknutí na "Odeslat zákazníkovi"
 */
async function potvrditAOdeslat() {
  if (!cachedPdfBase64) {
    showNotif("error", "PDF není dostupné");
    return;
  }

  try {
    // Zavřít preview modal
    if (typeof zavritPdfPreview === 'function') {
      zavritPdfPreview();
    }

    // FÁZE 2: Odesílání emailu
    showLoadingWithMessage(true, '📧 Odesílám email zákazníkovi...');
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
      logger.error('❌ Server error:', response.status, errorText);
      try {
        const errorJson = JSON.parse(errorText);
        logger.error('❌ Error detail:', errorJson);
        throw new Error(errorJson.error || errorJson.message || `Server error ${response.status}`);
      } catch (parseErr) {
        throw new Error(`Server error ${response.status}: ${errorText.substring(0, 200)}`);
      }
    }

    const result = await response.json();

    if (result.status === 'success') {
      showNotif("success", "✓ Email odeslán zákazníkovi");
      await saveProtokolToDB();

      logger.log('📋 Označuji reklamaci jako hotovou...');
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
        logger.log('✅ Reklamace označena jako hotová');
      } else {
        logger.warn('⚠️ Nepodařilo se označit jako hotovou:', markResult.message);
      }

      if (currentReklamaceId) {
        const key = 'photoSections_' + currentReklamaceId;
        const pdfKey = 'photosPDF_' + currentReklamaceId;
        localStorage.removeItem(key);
        localStorage.removeItem(pdfKey);
        localStorage.removeItem('photosReadyForProtocol');
        localStorage.removeItem('photosCustomerId');
        logger.log('✅ Fotky a PDF vymazány z localStorage');
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

    // Získat cenové údaje z formuláře
    const pocetDilu = parseInt(document.getElementById("parts").value) || 0;
    const cenaPrace = parseFloat(document.getElementById("price-work").value) || 0;
    const cenaMaterial = parseFloat(document.getElementById("price-material").value) || 0;
    const cenaDruhyTechnik = parseFloat(document.getElementById("price-second").value) || 0;
    const cenaDoprava = parseFloat(document.getElementById("price-transport").value) || 0;
    const cenaCelkem = cenaPrace + cenaMaterial + cenaDruhyTechnik + cenaDoprava;

    const response = await fetch("api/protokol_api.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        action: "save_protokol",
        reklamace_id: currentReklamaceId,
        problem_description: document.getElementById("problem-cz").value,
        repair_proposal: document.getElementById("repair-cz").value,
        solved: document.getElementById("solved").value,
        technician: document.getElementById("technician").value,
        pocet_dilu: pocetDilu,
        cena_prace: cenaPrace,
        cena_material: cenaMaterial,
        cena_druhy_technik: cenaDruhyTechnik,
        cena_doprava: cenaDoprava,
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

// Debounce funkce
function debounce(func, wait) {
  let timeout;
  return function executedFunction(...args) {
    const later = () => {
      clearTimeout(timeout);
      func(...args);
    };
    clearTimeout(timeout);
    timeout = setTimeout(later, wait);
  };
}

// Funkce pro překlad textu přes Google Translate API
async function translateTextApi(text, sourceLang = 'cs', targetLang = 'en') {
  if (!text || text.trim() === '') return '';

  try {
    const url = `https://translate.googleapis.com/translate_a/single?client=gtx&sl=${sourceLang}&tl=${targetLang}&dt=t&q=` + encodeURIComponent(text);
    const response = await fetch(url);
    const data = await response.json();

    if (data && data[0] && data[0][0] && data[0][0][0]) {
      return data[0][0][0];
    }

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
    logger.log('🔄 Překládám:', text.substring(0, 50) + '...');
    const translated = await translateTextApi(text, 'cs', 'en');

    if (translated) {
      targetField.value = translated;
      logger.log('✅ Přeloženo:', translated.substring(0, 50) + '...');
      showNotification('✅ Text přeložen', 'success');
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

  logger.log('🔄 Překládám pole:', fieldId);

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
    logger.log('✅ Přeloženo:', fieldId, '->', translated.substring(0, 50) + '...');
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

    logger.log('✅ Auto-překlad aktivován pro:', source, '→', target);
  });
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initAutoTranslation);
} else {
  initAutoTranslation();
}

logger.log('🌐 Automatický překlad aktivován');

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
  document.addEventListener('click', (e) => {
    const target = e.target.closest('[data-action]');
    if (!target) return;

    const action = target.getAttribute('data-action');

    // Special cases
    if (action === 'reload') {
      location.reload();
      return;
    }

    // Try to call function if it exists
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
