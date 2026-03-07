/**
 * seznam-fototeka.js
 * Správa fotek v zákazníkově kartě (výběr, komprimace, nahrávání, galerie)
 * Závisí na: seznam.js (fetchCsrfToken, logger, wgsToast, CURRENT_RECORD)
 */

// === FOTOTEKA - NAHRANI FOTEK Z DETAILU ZAKAZNIKA ===

/**
 * Otevre dialog pro vyber fotek
 * @param {string} reklamaceId - ID reklamace
 */
function otevritVyberFotek(reklamaceId) {
  logger.log('[Fototeka] Otviram vyber fotek pro reklamaci:', reklamaceId);
  let input = document.getElementById('fototeka-input-' + reklamaceId);
  if (!input) {
    // Dynamicky vytvorit input pokud neexistuje (volano z hlavniho detailu)
    input = document.createElement('input');
    input.type = 'file';
    input.id = 'fototeka-input-' + reklamaceId;
    input.accept = 'image/*';
    input.multiple = true;
    input.style.display = 'none';
    input.setAttribute('data-reklamace-id', reklamaceId);
    document.body.appendChild(input);
    input.addEventListener('change', zpracujVybraneFotky);
  }
  input.click();
}

/**
 * Zpracuje vybrane fotky a nahraje je na server
 * @param {Event} event - Change event z input file
 */
async function zpracujVybraneFotky(event) {
  const input = event.target;
  const reklamaceId = input.getAttribute('data-reklamace-id');
  const soubory = input.files;

  if (!soubory || soubory.length === 0) {
    logger.log('[Fototeka] Zadne soubory vybrane');
    return;
  }

  logger.log('[Fototeka] Vybrano souboru:', soubory.length, 'pro reklamaci:', reklamaceId);

  // Zobrazit progress bar
  const nahravaniDiv = document.getElementById('fototeka-nahravani');
  const progressBar = document.getElementById('fototeka-progress');
  if (nahravaniDiv) nahravaniDiv.style.display = 'block';
  if (progressBar) progressBar.style.width = '0%';

  try {
    // Konvertovat soubory na base64
    const fotkyBase64 = [];
    for (let i = 0; i < soubory.length; i++) {
      const soubor = soubory[i];

      // Kontrola typu souboru
      if (!soubor.type.startsWith('image/')) {
        logger.warn('[Fototeka] Preskakuji neobrazovy soubor:', soubor.name);
        continue;
      }

      // Kontrola velikosti (max 15MB pred kompresi)
      if (soubor.size > 15 * 1024 * 1024) {
        wgsToast.error('Soubor ' + soubor.name + ' je prilis velky (max 15MB)');
        continue;
      }

      // Komprimovat obrazek pred nahranim
      const komprimovany = await komprimujObrazek(soubor, 1200, 0.3);
      const base64 = await souborNaBase64(komprimovany);

      fotkyBase64.push({
        type: 'image',
        data: base64,
        size: komprimovany.size
      });

      // Aktualizovat progress
      if (progressBar) {
        const progress = Math.round(((i + 1) / soubory.length) * 50);
        progressBar.style.width = progress + '%';
      }
    }

    if (fotkyBase64.length === 0) {
      throw new Error('Zadne platne fotky k nahrani');
    }

    // Odeslat na server
    const csrfToken = await fetchCsrfToken();
    const response = await fetch('/app/save_photos.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        reklamace_id: reklamaceId,
        sections: { customer_detail: fotkyBase64 },
        csrf_token: csrfToken
      })
    });

    if (progressBar) progressBar.style.width = '80%';

    const vysledek = await response.json();

    if (!response.ok || !vysledek.success) {
      throw new Error(vysledek.error || 'Chyba pri nahravani');
    }

    if (progressBar) progressBar.style.width = '100%';

    logger.log('[Fototeka] Nahrano fotek:', vysledek.count);
    wgsToast.success('Nahrano ' + vysledek.count + ' fotek');

    // Aktualizovat grid s fotkami - galerie nebo fototéka podle kontextu
    if (input.getAttribute('data-galerie-mode') === '1' && document.getElementById('galerie-grid')) {
      const noveFotky = await loadPhotosFromDB(reklamaceId);
      renderGalerieGrid(noveFotky, reklamaceId);
    } else {
      await aktualizujFototekaGrid(reklamaceId);
    }

    // Reset inputu
    input.value = '';

  } catch (error) {
    logger.error('[Fototeka] Chyba pri nahravani:', error);
    wgsToast.error('Chyba: ' + error.message);
  } finally {
    // Skryt progress bar po chvili
    setTimeout(() => {
      if (nahravaniDiv) nahravaniDiv.style.display = 'none';
      if (progressBar) progressBar.style.width = '0%';
    }, 1000);
  }
}

/**
 * Prevede soubor na base64
 * @param {File|Blob} soubor - Soubor k prevodu
 * @returns {Promise<string>} - Base64 string
 */
function souborNaBase64(soubor) {
  return new Promise((resolve, reject) => {
    const reader = new FileReader();
    reader.onload = () => resolve(reader.result);
    reader.onerror = (error) => reject(error);
    reader.readAsDataURL(soubor);
  });
}

/**
 * Komprimuje obrazek na max velikost a kvalitu
 * @param {File} soubor - Obrazkovy soubor
 * @param {number} maxSirka - Maximalni sirka (default 1200px)
 * @param {number} maxMB - Maximalni velikost v MB (default 0.3)
 * @returns {Promise<Blob>} - Komprimovany blob
 */
function komprimujObrazek(soubor, maxSirka = 1200, maxMB = 0.3) {
  return new Promise((resolve) => {
    const reader = new FileReader();

    reader.onload = (e) => {
      const img = new Image();

      img.onload = async () => {
        const canvas = document.createElement('canvas');
        const ctx = canvas.getContext('2d');

        // Zachovat pomer stran
        const scale = Math.min(1, maxSirka / Math.max(img.width, img.height));
        canvas.width = img.width * scale;
        canvas.height = img.height * scale;

        // Nakreslit obrazek
        ctx.drawImage(img, 0, 0, canvas.width, canvas.height);

        // Komprimovat s postupne snizovanou kvalitou
        let kvalita = 0.7;
        let blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', kvalita));

        while (blob.size > maxMB * 1024 * 1024 && kvalita > 0.3) {
          kvalita -= 0.05;
          blob = await new Promise(r => canvas.toBlob(r, 'image/jpeg', kvalita));
        }

        const puvodniKB = Math.round(soubor.size / 1024);
        const novaKB = Math.round(blob.size / 1024);
        logger.log(`[Fototeka] Komprese: ${puvodniKB} KB -> ${novaKB} KB (kvalita ${Math.round(kvalita * 100)}%)`);

        resolve(blob);
      };

      img.onerror = () => {
        // Fallback - vratit original
        logger.warn('[Fototeka] Nelze nacist obrazek pro kompresi, vracim original');
        resolve(soubor);
      };

      img.src = e.target.result;
    };

    reader.onerror = () => resolve(soubor);
    reader.readAsDataURL(soubor);
  });
}

/**
 * Aktualizuje grid fotek v fototece
 * @param {string} reklamaceId - ID reklamace
 */
async function aktualizujFototekaGrid(reklamaceId) {
  try {
    const fotky = await loadPhotosFromDB(reklamaceId);
    const grid = document.getElementById('fototeka-grid');
    const nadpis = document.getElementById('fototeka-nadpis');

    if (!grid) return;

    // Aktualizovat nadpis
    if (nadpis) {
      nadpis.textContent = 'Fototeka (' + fotky.length + ')';
    }

    // Aktualizovat grid
    if (fotky.length > 0) {
      grid.innerHTML = fotky.map((f, i) => {
        const photoPath = typeof f === 'object' ? f.photo_path : f;
        const photoId = typeof f === 'object' ? f.id : null;
        const escapedUrl = photoPath.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/'/g, "\\'");

        return `
          <div class="foto-wrapper" style="position:relative;aspect-ratio:1;min-width:0;">
            <img src='${photoPath}'
                 style='width:100%;height:100%;object-fit:cover;border:1px solid #444;cursor:pointer;border-radius:4px;display:block;'
                 alt='Fotka ${i+1}'
                 loading="lazy"
                 data-action="showPhotoFullscreen"
                 data-url="${escapedUrl}">
            ${photoId ? `
              <button class="foto-delete-btn"
                      data-action="smazatFotku"
                      data-photo-id="${photoId}"
                      data-url="${escapedUrl}"
                      title="Smazat fotku">
                x
              </button>
            ` : ''}
          </div>
        `;
      }).join('');
    } else {
      grid.innerHTML = '<p style="color: #666; font-size: 0.85rem; margin: 0; padding: 0.5rem 0;">Zadne fotografie</p>';
    }

  } catch (error) {
    logger.error('[Fototeka] Chyba pri aktualizaci gridu:', error);
  }
}

// Galerie: zobrazí fotky zakázky v overlay (stejné chování jako fototéka v showCustomerDetail)
async function otevritGalerii(reklamaceId) {
  // Správné ID: použít reklamace_id z CURRENT_RECORD pokud dostupné
  const effectiveId = CURRENT_RECORD ? (CURRENT_RECORD.reklamace_id || CURRENT_RECORD.id) : reklamaceId;

  // Zobrazit loading stav uvnitř modalu (ne jako samostatnou overlay)
  ModalManager.show(`
    ${ModalManager.createHeader('Galerie', '', 'zpetDoDetailu', effectiveId)}
    <div class="modal-body"><p style="color:#aaa;padding:1rem 0;">Načítání fotek...</p></div>
  `);

  const fotky = await loadPhotosFromDB(effectiveId);

  const content = `
    ${ModalManager.createHeader(`Galerie (${fotky.length})`, '', 'zpetDoDetailu', reklamaceId)}
    <div class="modal-body">
      <div class="detail-buttons" style="margin-bottom:1rem;">
        <button class="detail-btn detail-btn-primary" id="galerie-pridat-btn">Přidat fotky</button>
      </div>
      <div id="galerie-grid" style="display:flex;flex-wrap:wrap;gap:8px;min-height:60px;">
        ${renderGalerieGridHtml(fotky)}
      </div>
      <div id="galerie-nahravani" style="display:none;padding:0.5rem;background:#222;border-radius:4px;margin-top:0.75rem;">
        <p style="color:#aaa;font-size:0.8rem;margin:0;">Nahrávání fotek...</p>
        <div style="background:#333;height:4px;border-radius:2px;margin-top:0.5rem;overflow:hidden;">
          <div id="galerie-progress" style="background:#fff;height:100%;width:0%;transition:width 0.3s;"></div>
        </div>
      </div>
    </div>
  `;
  ModalManager.show(content);

  // File input pro přidání fotek
  let input = document.getElementById('galerie-input-' + effectiveId);
  if (!input) {
    input = document.createElement('input');
    input.type = 'file';
    input.id = 'galerie-input-' + effectiveId;
    input.accept = 'image/*';
    input.multiple = true;
    input.style.display = 'none';
    input.setAttribute('data-reklamace-id', effectiveId);
    input.setAttribute('data-galerie-mode', '1');
    document.body.appendChild(input);
    input.addEventListener('change', zpracujVybraneFotky);
  }

  const pridatBtn = document.getElementById('galerie-pridat-btn');
  if (pridatBtn) pridatBtn.onclick = () => input.click();
}

function renderGalerieGridHtml(fotky) {
  if (fotky.length === 0) return '<p style="color:#666;font-size:0.85rem;margin:0;padding:0.5rem 0;">Žádné fotografie</p>';
  return fotky.map((f, i) => {
    const photoPath = typeof f === 'object' ? f.photo_path : f;
    const photoId = typeof f === 'object' ? f.id : null;
    const escapedUrl = photoPath.replace(/\\/g, '\\\\').replace(/"/g, '\\"').replace(/'/g, "\\'");
    return `
      <div class="foto-wrapper" style="position:relative;width:130px;height:130px;flex-shrink:0;">
        <img src='${photoPath}'
             style='width:130px;height:130px;object-fit:cover;border:1px solid #444;cursor:pointer;border-radius:4px;'
             alt='Fotka ${i+1}' loading="lazy"
             data-action="showPhotoFullscreen" data-url="${escapedUrl}">
        ${photoId ? `
          <button class="foto-delete-btn"
                  data-action="smazatFotku"
                  data-photo-id="${photoId}"
                  data-url="${escapedUrl}"
                  title="Smazat fotku">x</button>
        ` : ''}
      </div>`;
  }).join('');
}

function renderGalerieGrid(fotky, reklamaceId) {
  const grid = document.getElementById('galerie-grid');
  if (!grid) return;
  const nadpis = document.querySelector('.modal-title');
  if (nadpis && nadpis.textContent.startsWith('Galerie')) nadpis.textContent = `Galerie (${fotky.length})`;
  grid.innerHTML = renderGalerieGridHtml(fotky);
}

// Globalni pristup k funkcim fototéky
window.otevritVyberFotek = otevritVyberFotek;
window.otevritGalerii = otevritGalerii;
window.zpracujVybraneFotky = zpracujVybraneFotky;

