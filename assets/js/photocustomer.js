// [Lock] KRITICKÁ BEZPEČNOSTNÍ KONTROLA - MUSÍ BÝT PRVNÍ!
(async function() {
  try {
    const response = await fetch("/app/admin_session_check.php");
    const data = await response.json();

    if (!data.logged_in) {
      logger.log("Nepřihlášen - přesměrování na login");
      window.location.href = "login.php";
      throw new Error("Not authenticated");
    }

    logger.log("Přihlášen jako:", data.email);
  } catch (err) {
    logger.error("Chyba kontroly session:", err);
    window.location.href = "login.php";
    throw new Error("Auth check failed");
  }
})();

// === HAMBURGER MENU ===
// REMOVED: Mrtvý kód - menu je nyní centrálně v hamburger-menu.php

// === GLOBÁLNÍ PROMĚNNÉ ===
let currentCustomerData = null;
let currentSection = '';
let sections = {
  before: [],
  id: [],
  problem: [],
  damage_part: [],
  new_part: [],
  repair: [],
  after: []
};

// === INICIALIZACE ===
window.addEventListener('DOMContentLoaded', async () => {
  logger.log('[Start] INICIALIZACE FOTODOKUMENTACE');

  loadCustomerData();
  await loadExistingMedia();
  updateProgress();

  document.getElementById('mediaInput').addEventListener('change', handleMediaSelect);
  document.getElementById('btnSaveToProtocol').addEventListener('click', saveToProtocol);

  // Tlačítko "Nová návštěva" — smaže IndexedDB a začne od nuly
  const btnNova = document.getElementById('btnNovaNavsteva');
  if (btnNova) {
    btnNova.addEventListener('click', async () => {
      const potvrdit = confirm('Opravdu smazat všechny rozpracované fotky a začít novou návštěvu?');
      if (!potvrdit) return;
      window.location.href = 'photocustomer.php?new=true';
    });
  }

  // Video sekce - MUSÍ být po loadCustomerData() aby bylo správné ID zakázky
  await initVideoSection();

  logger.log('Inicializace dokončena');
});

function loadCustomerData() {
  const storedData = localStorage.getItem('currentCustomer');

  if (!storedData) {
    showAlert('Chybí data zákazníka', 'error');
    setTimeout(() => window.location.href = 'seznam.php', 2000);
    return;
  }

  currentCustomerData = JSON.parse(storedData);
  logger.log('📦 Načtená data zákazníka:', currentCustomerData);

  // KONTROLA OPRÁVNĚNÍ: Admin a technik mají přístup ke všem zakázkám
  // Prodejce má přístup pouze ke svým zakázkám
  const currentUser = JSON.parse(localStorage.getItem('currentUser') || '{}');

  if (currentUser.role === 'prodejce') {
    // Prodejce může vidět jen své zakázky
    if (currentCustomerData.zpracoval_id && currentCustomerData.zpracoval_id !== currentUser.id) {
      showAlert('Nemáte oprávnění k této zakázce', 'error');
      setTimeout(() => window.location.href = 'seznam.php', 2000);
      return;
    }
  }
  // Admin a technik vidí všechny zakázky - bez kontroly

  // OPRAVENO: Vylepšené načítání informací o zákazníkovi
  const customerName = currentCustomerData.jmeno || currentCustomerData.zakaznik || 'N/A';

  // Adresa - pokus sestavit z více polí
  let address = '';
  if (currentCustomerData.adresa) {
    address = currentCustomerData.adresa;
  } else {
    const parts = [];
    if (currentCustomerData.ulice) parts.push(currentCustomerData.ulice);
    if (currentCustomerData.mesto) parts.push(currentCustomerData.mesto);
    if (currentCustomerData.psc) parts.push(currentCustomerData.psc);
    address = parts.join(', '); // OPRAVENO: Mezera za čárkou
  }

  // Model
  const model = currentCustomerData.model || '-';

  // Kontakt - telefon nebo email
  let contact = '';
  if (currentCustomerData.telefon) {
    contact = currentCustomerData.telefon;
  } else if (currentCustomerData.email) {
    contact = currentCustomerData.email;
  } else {
    contact = '-';
  }

  // VYPLNĚNÍ HLAVIČKY
  document.getElementById('customerName').textContent = customerName;
  document.getElementById('customerAddress').textContent = address || 'Adresa neuvedena';
  document.getElementById('customerModel').textContent = model;
  document.getElementById('customerContact').textContent = contact;

  // VYPLNĚNÍ JMÉNA V ROZBALOVACÍM MENU
  const customerInfoName = document.getElementById('customerInfoName');
  if (customerInfoName) {
    customerInfoName.textContent = customerName;
  }

  logger.log(`Hlavička vyplněna:`, {
    jméno: customerName,
    adresa: address,
    model: model,
    kontakt: contact
  });

  logger.log(`Přístup povolen: ${currentUser.role} (${currentUser.name})`);
}

async function loadExistingMedia() {
  const urlParams = new URLSearchParams(window.location.search);
  const forceNew = urlParams.get('new');
  const pridejMod = urlParams.get('pridej') === 'true';

  // ?new=true — pouze při explicitním kliknutí na "Začít novou návštěvu" v UI
  if (forceNew === 'true') {
    await deleteFromServer();
    sections = { before: [], id: [], problem: [], damage_part: [], new_part: [], repair: [], after: [] };
    logger.log('[Photo] Nová návštěva — IndexedDB vyčištěna');
    return;
  }

  // ?pridej=true — přidání fotek k existující fotodokumentaci
  if (pridejMod) {
    await zobrazExistujiciFotkyZDB();
  }

  // Načíst rozpracované fotky z IndexedDB
  const ulozenaData = await loadFromServer();
  if (ulozenaData) {
    sections = ulozenaData;
    renderAllPreviews();
    const celkem = Object.values(sections).reduce((s, a) => s + a.length, 0);
    logger.log(`[Photo] Načteno ${celkem} rozpracovaných fotek z IndexedDB`);
  } else {
    sections = { before: [], id: [], problem: [], damage_part: [], new_part: [], repair: [], after: [] };
    logger.log('[Photo] Žádné rozpracované fotky — prázdné sekce');
  }
}

// Načte existující fotky ze serveru a zobrazí je jako přehled (pouze čtení)
async function zobrazExistujiciFotkyZDB() {
  const reklamaceId = getStorageKey();
  if (!reklamaceId) {
    logger.warn('[PridejMod] Chybí reklamace ID, nelze načíst existující fotky');
    return;
  }

  try {
    const odpoved = await fetch(`/api/get_photos_api.php?reklamace_id=${encodeURIComponent(reklamaceId)}`);
    const data = await odpoved.json();

    if (!data.success || !data.photos || data.photos.length === 0) {
      logger.log('[PridejMod] Žádné existující fotky v databázi');
      return;
    }

    logger.log(`[PridejMod] Nalezeno ${data.photos.length} existujících fotek`);

    // Zobrazit banner
    const banner = document.getElementById('bannerPridej');
    if (banner) {
      banner.textContent = `Přidáváte fotky k existující fotodokumentaci (${data.photos.length} fotek)`;
      banner.style.display = 'block';
    }

    // Zobrazit sekci s existujícími fotkami
    const sekce = document.getElementById('existujiciFotkySekce');
    const mrizka = document.getElementById('existujiciFotkyMrizka');
    if (sekce && mrizka) {
      sekce.style.display = 'block';
      mrizka.innerHTML = '';

      const nazvySekci = {
        'before': 'Before',
        'id': 'ID',
        'problem': 'Detail Bug',
        'damage_part': 'Damage Part',
        'new_part': 'New Part',
        'repair': 'Repair',
        'after': 'After'
      };

      data.photos.forEach(foto => {
        const obal = document.createElement('div');
        obal.className = 'existujici-foto-obal';

        const img = document.createElement('img');
        img.className = 'existujici-foto-nahled';
        img.src = '/' + foto.photo_path;
        img.alt = nazvySekci[foto.section_name] || foto.section_name;
        img.loading = 'lazy';

        const popisek = document.createElement('div');
        popisek.className = 'existujici-foto-popisek';
        popisek.textContent = nazvySekci[foto.section_name] || foto.section_name;

        obal.appendChild(img);
        obal.appendChild(popisek);
        mrizka.appendChild(obal);
      });
    }

  } catch (chyba) {
    logger.error('[PridejMod] Chyba při načítání existujících fotek:', chyba);
  }
}

function openMediaCapture(section) {
  currentSection = section;
  document.getElementById('mediaInput').click();
}

async function handleMediaSelect(e) {
  const files = Array.from(e.target.files);
  if (files.length === 0) return;

  showWaitDialog(true, `Zpracovávám ${files.length} soubor(ů)...`);

  let processed = 0;
  for (const file of files) {
    processed++;
    showWaitDialog(true, `Zpracovávám ${processed}/${files.length}`);

    const isVideo = file.type.startsWith('video/');

    try {
      if (isVideo) {
        const compressed = await compressVideo(file);
        const thumbnail = await generateVideoThumbnail(file);
        const videoData = await toBase64(compressed);

        sections[currentSection].push({
          type: 'video',
          data: videoData,
          thumb: thumbnail,
          size: compressed.size
        });
      } else {
        const compressed = await compressImage(file);
        const imageData = await toBase64(compressed);

        sections[currentSection].push({
          type: 'image',
          data: imageData,
          size: compressed.size
        });

        // POZNÁMKA:
        // - Pokud technik pořídí novou fotku fotoaparátem -> originál automaticky v galerii
        // - Pokud technik vybere fotku z galerie -> originál už v galerii je
        // - Komprimovaná verze je vždy uložena v IndexedDB pro upload do systému
        logger.log(`[Photo] Fotka zpracována - originál v galerii, komprimovaná verze v IndexedDB`);
      }
    } catch (error) {
      logger.error('Chyba zpracování:', error);
      showAlert('Chyba při zpracování souboru', 'error');
    }
  }

  await saveToServer();
  renderAllPreviews();
  updateProgress();
  showWaitDialog(false);
  // Neonový toast pro úspěšný upload fotek
  if (typeof WGSToast !== 'undefined') {
    WGSToast.zobrazit(`Přidáno ${files.length} soubor(ů)`, { titulek: 'WGS' });
  } else {
    showAlert(`Přidáno ${files.length} soubor(ů)`, 'success');
  }

  e.target.value = '';
}

async function compressImage(file, maxWidth = 800, maxMB = 0.2) {
  // PRAVIDLO: Zadna rotace, zadna deformace, pouze komprese
  // Orientace se NIKDY nemeni - prohlizec aplikuje EXIF automaticky
  return new Promise((resolve) => {
    const reader = new FileReader();

    reader.onload = async (e) => {
      const img = new Image();

      img.onload = async () => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        // Zachovat pomer stran - zadna deformace
        const scale = Math.min(1, maxWidth / Math.max(img.width, img.height));
        canvas.width = img.width * scale;
        canvas.height = img.height * scale;

        // Nakreslit bez rotace - orientace se nemeni
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        let quality = 0.6;
        let blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality));

        while (blob.size > maxMB * 1024 * 1024 && quality > 0.3) {
          quality -= 0.05;
          blob = await new Promise(resolve => canvas.toBlob(resolve, 'image/jpeg', quality));
        }

        logger.log(`[Photo] Fotka zkomprimována: ${(blob.size / 1024).toFixed(0)} KB, kvalita: ${(quality * 100).toFixed(0)}%`);
        resolve(blob);
      };

      img.src = e.target.result;
    };

    reader.readAsDataURL(file);
  });
}

async function compressVideo(file, targetMB = 1.5) {
  // Pokud je video menší než target, vrátit bez komprese
  const fileSizeMB = file.size / (1024 * 1024);
  if (fileSizeMB <= targetMB) {
    logger.log(`[Video] Video ${fileSizeMB.toFixed(1)} MB je menší než limit ${targetMB} MB - bez komprese`);
    return file;
  }

  logger.log(`[Video] Komprimuji video ${fileSizeMB.toFixed(1)} MB na cca ${targetMB} MB...`);

  return new Promise((resolve, reject) => {
    try {
      // Vytvořit video element
      const video = document.createElement('video');
      video.preload = 'metadata';
      video.muted = true;
      video.playsInline = true; // Důležité pro iOS

      video.onloadedmetadata = () => {
        const width = video.videoWidth;
        const height = video.videoHeight;

        // Maximální rozlišení 1280x720 pro photocustomer (menší než videotéka)
        let targetWidth = width;
        let targetHeight = height;

        if (width > 1280 || height > 720) {
          const ratio = Math.min(1280 / width, 720 / height);
          targetWidth = Math.round(width * ratio);
          targetHeight = Math.round(height * ratio);
        }

        // Vytvořit canvas
        const canvas = document.createElement('canvas');
        canvas.width = targetWidth;
        canvas.height = targetHeight;
        const ctx = canvas.getContext('2d');

        // Vytvořit stream z canvasu
        const stream = canvas.captureStream(24); // 24 FPS pro menší velikost

        // Najít podporovaný MIME typ
        const mimeTypy = [
          'video/webm;codecs=vp9',
          'video/webm;codecs=vp8',
          'video/webm',
          'video/mp4'
        ];

        let vybranyMime = '';
        for (const mime of mimeTypy) {
          if (MediaRecorder.isTypeSupported(mime)) {
            vybranyMime = mime;
            break;
          }
        }

        if (!vybranyMime) {
          logger.warn('[Video] Žádný video kodek není podporován, vracím originál');
          resolve(file);
          return;
        }

        logger.log(`[Video] Používám kodek: ${vybranyMime}`);

        // MediaRecorder s kompresí - nižší bitrate pro menší soubory
        const options = {
          mimeType: vybranyMime,
          videoBitsPerSecond: 1500000 // 1.5 Mbps - dobrá komprese
        };

        let mediaRecorder;
        try {
          mediaRecorder = new MediaRecorder(stream, options);
        } catch (e) {
          // Fallback bez specifikace bitrate
          mediaRecorder = new MediaRecorder(stream, { mimeType: vybranyMime });
        }

        const chunks = [];

        mediaRecorder.ondataavailable = (e) => {
          if (e.data.size > 0) {
            chunks.push(e.data);
          }
        };

        mediaRecorder.onstop = () => {
          const blob = new Blob(chunks, { type: vybranyMime });
          const compressedSizeMB = blob.size / (1024 * 1024);
          logger.log(`[Video] Komprese dokončena: ${fileSizeMB.toFixed(1)} MB → ${compressedSizeMB.toFixed(1)} MB`);
          resolve(blob);
        };

        mediaRecorder.onerror = (e) => {
          logger.error('[Video] Chyba při kompresi:', e);
          resolve(file); // Fallback na originál
        };

        // Spustit záznam
        mediaRecorder.start(1000);

        // Přehrát video a renderovat do canvasu
        video.play().catch(e => {
          logger.error('[Video] Nelze přehrát video pro kompresi:', e);
          resolve(file);
        });

        const renderFrame = () => {
          if (!video.paused && !video.ended) {
            ctx.drawImage(video, 0, 0, targetWidth, targetHeight);
            requestAnimationFrame(renderFrame);
          } else {
            // Video skončilo
            setTimeout(() => mediaRecorder.stop(), 100);
          }
        };

        video.onplay = () => {
          renderFrame();
        };

        video.onerror = () => {
          logger.error('[Video] Chyba při načítání videa pro kompresi');
          resolve(file);
        };
      };

      video.onerror = () => {
        logger.error('[Video] Video soubor nelze načíst');
        resolve(file);
      };

      // Načíst video
      video.src = URL.createObjectURL(file);

    } catch (error) {
      logger.error('[Video] Chyba komprese:', error);
      resolve(file); // Fallback na originál
    }
  });
}

async function generateVideoThumbnail(file) {
  return new Promise((resolve) => {
    const video = document.createElement('video');
    const canvas = document.createElement('canvas');
    const ctx = canvas.getContext('2d');

    video.onloadedmetadata = () => {
      video.currentTime = Math.min(1, video.duration / 2);
    };

    video.onseeked = () => {
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;
      ctx.drawImage(video, 0, 0);

      canvas.toBlob((blob) => {
        toBase64(blob).then(resolve);
      }, 'image/jpeg', 0.6);
    };

    video.src = URL.createObjectURL(file);
  });
}

async function getImageOrientation(file) {
  return new Promise((resolve) => {
    const reader = new FileReader();

    reader.onload = (e) => {
      const view = new DataView(e.target.result);

      if (view.getUint16(0, false) !== 0xFFD8) {
        resolve(1);
        return;
      }

      const length = view.byteLength;
      let offset = 2;

      while (offset < length) {
        if (view.getUint16(offset + 2, false) <= 8) {
          resolve(1);
          return;
        }

        const marker = view.getUint16(offset, false);
        offset += 2;

        if (marker === 0xFFE1) {
          offset += 2;

          if (view.getUint32(offset, false) !== 0x45786966) {
            resolve(1);
            return;
          }

          const little = view.getUint16(offset += 6, false) === 0x4949;
          offset += view.getUint32(offset + 4, little);
          const tags = view.getUint16(offset, little);
          offset += 2;

          for (let i = 0; i < tags; i++) {
            if (view.getUint16(offset + (i * 12), little) === 0x0112) {
              const orientation = view.getUint16(offset + (i * 12) + 8, little);
              resolve(orientation);
              return;
            }
          }
        } else if ((marker & 0xFF00) !== 0xFF00) {
          break;
        } else {
          offset += view.getUint16(offset, false);
        }
      }

      resolve(1);
    };

    reader.onerror = () => resolve(1);
    reader.readAsArrayBuffer(file.slice(0, 64 * 1024));
  });
}

function applyExifOrientation(ctx, orientation, width, height) {
  switch (orientation) {
    case 2:
      ctx.transform(-1, 0, 0, 1, width, 0);
      break;
    case 3:
      ctx.transform(-1, 0, 0, -1, width, height);
      break;
    case 4:
      ctx.transform(1, 0, 0, -1, 0, height);
      break;
    case 5:
      ctx.transform(0, 1, 1, 0, 0, 0);
      break;
    case 6:
      ctx.transform(0, 1, -1, 0, height, 0);
      break;
    case 7:
      ctx.transform(0, -1, -1, 0, height, width);
      break;
    case 8:
      ctx.transform(0, -1, 1, 0, 0, width);
      break;
  }
}

// Step 134: Use centralized toBase64 from utils.js if available
function toBase64(blob) {
  if (window.Utils && window.Utils.toBase64) {
    return window.Utils.toBase64(blob);
  }
  // Fallback for backwards compatibility
  return new Promise((resolve) => {
    const reader = new FileReader();
    reader.onloadend = () => resolve(reader.result);
    reader.readAsDataURL(blob);
  });
}

function renderAllPreviews() {
  Object.keys(sections).forEach(section => {
    renderPreview(section);
  });
}

function renderPreview(section) {
  const container = document.getElementById('preview-' + section);
  if (!container) return;

  container.innerHTML = '';

  sections[section].forEach((media, index) => {
    const wrapper = document.createElement('div');
    wrapper.className = 'media-thumb';

    const img = document.createElement('img');
    img.className = 'photo-thumb';
    img.alt = media.type === 'video' ? 'Náhled videa' : 'Náhled fotky';
    img.src = media.type === 'video' ? media.thumb : media.data;

    const deleteBtn = document.createElement('button');
    deleteBtn.className = 'delete-thumb';
    deleteBtn.innerHTML = '×';
    deleteBtn.onclick = (e) => {
      e.stopPropagation();
      deleteMedia(section, index);
    };

    wrapper.appendChild(img);
    wrapper.appendChild(deleteBtn);

    if (media.type === 'video') {
      const badge = document.createElement('div');
      badge.className = 'video-badge';
      badge.textContent = 'Video';
      wrapper.appendChild(badge);
    }

    container.appendChild(wrapper);
  });
}

async function deleteMedia(section, index) {
  sections[section].splice(index, 1);
  await saveToServer();
  renderPreview(section);
  updateProgress();
  // Neonový toast pro smazání souboru
  if (typeof WGSToast !== 'undefined') {
    WGSToast.zobrazit('Soubor smazán', { titulek: 'WGS' });
  } else {
    showAlert('Soubor smazán', 'success');
  }
}

function updateProgress() {
  const total = Object.values(sections).reduce((sum, arr) => sum + arr.length, 0);
  const percent = Math.min(100, (total / 30) * 100);

  document.getElementById('progressBar').style.width = percent + '%';
  document.getElementById('compressionInfo').textContent = `Celkem nahráno: ${total} souborů (max 30 doporučeno)`;
}

async function saveToProtocol() {
  if (Object.values(sections).every(arr => arr.length === 0)) {
    showAlert('Nejdříve nahrajte fotky nebo videa', 'error');
    return;
  }

  // ⏳ ZOBRAZIT PŘESÝPACÍ HODINY
  showWaitDialog(true, 'Ukládám fotografie...');

  try {
    // OPRAVENO: Získání CSRF tokenu z meta tagu
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

    if (!csrfToken) {
      throw new Error('CSRF token nebyl nalezen');
    }

    // OPRAVENO: Správná identifikace reklamace
    // Backend hledá podle reklamace_id (např. WGS251116-XXX) nebo cislo
    const reklamaceId = currentCustomerData.reklamace_id || currentCustomerData.cislo || currentCustomerData.id;

    if (!reklamaceId) {
      throw new Error('Chybí identifikátor reklamace');
    }

    logger.log('📤 Odesílám fotky pro reklamaci:', reklamaceId);

    const response = await fetch('/app/save_photos.php', {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json'
      },
      body: JSON.stringify({
        reklamace_id: reklamaceId,
        sections: sections,
        csrf_token: csrfToken
      })
    });

    const result = await response.json();

    if (result.success) {
      // ✅ SMAZAT FOTKY Z INDEXEDDB PO ÚSPĚŠNÉM ODESLÁNÍ
      await deleteFromServer();

      // ⏳ ZOBRAZIT PŘESÝPACÍ HODINY S TEXTEM "PŘESMĚROVÁNÍ NA PROTOKOL"
      showWaitDialog(true, 'Přesměrování na protokol...');

      // Ujistit se, že currentCustomer je v localStorage pro protokol.php
      localStorage.setItem('currentCustomer', JSON.stringify(currentCustomerData));

      setTimeout(() => {
        window.location.href = `protokol.php?id=${encodeURIComponent(reklamaceId)}`;
      }, 1500);
    } else {
      showAlert('Chyba při ukládání: ' + result.error, 'error');
    }
  } catch (error) {
    logger.error('Chyba při odesílání fotek:', error);
    showAlert('Chyba při odesílání: ' + error.message, 'error');
  } finally {
    showWaitDialog(false);
  }
}

/**
 * @deprecated NEPOUŽÍVÁ SE - Originály jsou automaticky v galerii
 *
 * Původně mělo stahovat fotky do galerie, ale:
 * - Při pořízení fotky fotoaparátem: originál AUTOMATICKY v galerii (OS telefonu)
 * - Při výběru z galerie: originál už v galerii JE
 * - Prohlížeče neumožňují automatické ukládání bez souhlasu uživatele (bezpečnost)
 * - <a download> ukládá do Downloads, ne do galerie fotek
 *
 * ŘEŠENÍ: Technik může fotit NEBO vybrat z galerie. Originály jsou vždy dostupné.
 * Komprimované verze jsou v IndexedDB pro upload.
 *
 * @param {string} imageData - Base64 data URL fotky
 * @param {string} section - Sekce (before, id, detail, damage_part, new_part, repair, after)
 * @param {number} index - Pořadí fotky v sekci
 */
async function downloadToGallery(imageData, section, index) {
  try {
    // Sestavit název souboru podle sekce a reklamace
    const reklamaceId = currentCustomerData?.reklamace_id || 'unknown';
    const timestamp = new Date().toISOString().slice(0, 10).replace(/-/g, ''); // YYYYMMDD

    // Překlad sekce do lidsky čitelného názvu
    const sectionNames = {
      'before': 'PRED',
      'id': 'ID',
      'detail': 'DETAIL',
      'damage_part': 'POSKOZENY_DIL',
      'new_part': 'NOVY_DIL',
      'repair': 'OPRAVA',
      'after': 'PO',
      'problem': 'DETAIL_BUG'
    };

    const sectionName = sectionNames[section] || section.toUpperCase();
    const filename = `WGS_${reklamaceId}_${sectionName}_${index}_${timestamp}.jpg`;

    logger.log(`[Gallery] 🔽 Startuji download: ${filename}`);
    logger.log(`[Gallery] 📱 User Agent: ${navigator.userAgent.includes('iPhone') ? 'iOS' : navigator.userAgent.includes('Android') ? 'Android' : 'Desktop'}`);

    // Vytvořit download link
    const link = document.createElement('a');
    link.href = imageData;
    link.download = filename;
    link.style.display = 'none';

    // KRITICKÉ: Přidat do DOM PŘED kliknutím (iOS fix)
    document.body.appendChild(link);

    // Malý delay pro iOS Safari (má problémy s okamžitým kliknutím)
    await new Promise(resolve => setTimeout(resolve, 50));

    // Kliknout na link
    link.click();

    logger.log(`[Gallery] 👆 Klik na download link proveden`);

    // KRITICKÉ: Počkat 500ms před odstraněním (iOS potřebuje čas zpracovat download)
    await new Promise(resolve => setTimeout(resolve, 500));

    // Odstranit link z DOM
    document.body.removeChild(link);

    logger.log(`[Gallery] ✅ Fotka automaticky stažena: ${filename}`);

    // VIZUÁLNÍ FEEDBACK: Neonový toast pro každou staženou fotku
    if (typeof WGSToast !== 'undefined') {
      WGSToast.zobrazit(`Fotka uložena do galerie: ${sectionName}`, {
        titulek: 'WGS',
        trvani: 2000
      });
    }

    return true;

  } catch (error) {
    // Neselhání pořízení fotky kvůli chybě stahování
    logger.error('[Gallery] ❌ Chyba při stahování do galerie:', error);
    logger.error('[Gallery] Stack:', error.stack);

    // VAROVÁNÍ: Upozornit uživatele, ale neblokovat pokračování
    if (typeof wgsToast !== 'undefined') {
      wgsToast.error('Nepodařilo se uložit fotku do galerie. Fotka je stále uložena v aplikaci.');
    }

    // Neblokovat pokračování - fotka je stejně uložena v IndexedDB
    return false;
  }
}

function getStorageKey() {
  // Fallback: reklamace_id (formátované číslo) → cislo → numerické id
  return currentCustomerData?.reklamace_id
    || currentCustomerData?.cislo
    || (currentCustomerData?.id ? String(currentCustomerData.id) : null);
}

async function saveToServer() {
  const klic = getStorageKey();
  if (typeof window.PhotoStorageDB !== 'undefined' && klic) {
    try {
      await window.PhotoStorageDB.save(klic, sections);
      logger.log('[IndexedDB] Fotky uloženy, klíč:', klic);
      return { success: true };
    } catch (chyba) {
      logger.error('[IndexedDB] Chyba při ukládání:', chyba);
      return { success: false, error: chyba.message };
    }
  }
  logger.warn('[Save] PhotoStorageDB není k dispozici nebo chybí klíč');
  return { success: true };
}

async function loadFromServer() {
  const klic = getStorageKey();
  if (typeof window.PhotoStorageDB !== 'undefined' && klic) {
    try {
      const ulozenaData = await window.PhotoStorageDB.load(klic);
      if (ulozenaData) {
        logger.log('[IndexedDB] Fotky načteny, klíč:', klic);
        return ulozenaData;
      }
    } catch (chyba) {
      logger.error('[IndexedDB] Chyba při načítání:', chyba);
    }
  }
  logger.log('[IndexedDB] Žádná uložená data, klíč:', klic);
  return null;
}

async function deleteFromServer() {
  const klic = getStorageKey();
  if (typeof window.PhotoStorageDB !== 'undefined' && klic) {
    try {
      await window.PhotoStorageDB.delete(klic);
      logger.log('[IndexedDB] Fotky smazány, klíč:', klic);
    } catch (chyba) {
      logger.error('[IndexedDB] Chyba při mazání:', chyba);
    }
  }
}

function showWaitDialog(show, message = 'Čekejte...') {
  const dialog = document.getElementById('waitDialog');
  const msg = document.getElementById('waitMsg');

  if (show) {
    msg.textContent = message;
    dialog.classList.add('show');
  } else {
    dialog.classList.remove('show');
  }
}

function showAlert(message, type = 'success') {
  const alert = document.getElementById('alert');
  alert.textContent = message;
  alert.className = 'alert ' + type;
  alert.classList.add('show');

  setTimeout(() => {
    alert.classList.remove('show');
  }, 3000);
}

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

  // FIX: Event delegation pro photo sections (odstranit inline onclick)
  document.querySelectorAll('.photo-section').forEach(section => {
    const captureType = section.getAttribute('data-capture-type');

    if (captureType) {
      section.addEventListener('click', (e) => {
        // Neotevírat pokud klikáme na delete button
        if (e.target.closest('.delete-thumb')) return;

        if (typeof openMediaCapture === 'function') {
          openMediaCapture(captureType);
        }
      });
    }
  });

  // POZNÁMKA: initVideoSection() je voláno v hlavní inicializaci výše
  // aby bylo zajištěno správné pořadí po loadCustomerData()
});

