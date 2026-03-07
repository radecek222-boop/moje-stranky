/**
 * seznam-admin.js
 * Admin operace: změna stavu, smazání reklamace, smazání fotky, potvrzovací modaly
 * Závisí na: seznam.js (fetchCsrfToken, logger, wgsToast, ModalManager, CURRENT_RECORD, loadAll)
 */

// === ADMIN: ZMĚNA STAVU ZAKÁZKY ===
async function zmenitStavZakazky(reklamaceId, novyStav, zakaznikEmail) {
  if (!reklamaceId || !novyStav) {
    console.error('[Admin] Chybí povinné parametry:', { reklamaceId, novyStav });
    wgsToast.error('Chybí ID nebo nový stav');
    return;
  }

  // Mapování pro zobrazení
  const stavyMap = {
    'wait': 'NOVÁ',
    'open': 'DOMLUVENÁ',
    'done': 'HOTOVO',
    'cekame_na_dily': 'Čekáme na díly',
    'odlozena': 'Odložená',
    'cn_poslana': 'Poslána CN',
    'cn_odsouhlasena': 'Odsouhlasena',
    'cn_cekame_nd': 'Čekáme na díly',
    'cn_zamitnuta': 'Zamítnuta'
  };

  // Rozpoznat CN stavy
  const jeCnStav = novyStav.startsWith('cn_');

  const csrfToken = document.querySelector('input[name="csrf_token"]')?.value ||
                    document.querySelector('meta[name="csrf-token"]')?.content;

  // Pomocná funkce pro volání odloz API
  const volajOdlozApi = async (hodnota) => {
    const params = new URLSearchParams();
    params.append('reklamace_id', reklamaceId);
    params.append('hodnota', hodnota);
    params.append('csrf_token', csrfToken);
    const resp = await fetch('/api/odloz_reklamaci.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: params
    });
    return resp.json();
  };

  try {
    // Speciální případ: výběr "Odložená"
    if (novyStav === 'odlozena') {
      const d = await volajOdlozApi(1);
      if (d.status === 'success') {
        if (CURRENT_RECORD) CURRENT_RECORD.je_odlozena = 1;
        const zaz = WGS_DATA_CACHE.find(r => r.id == reklamaceId);
        if (zaz) zaz.je_odlozena = 1;
        if (window.WGSToast) WGSToast.zobrazit('Reklamace odložena');
        await loadAll();
        showDetail(reklamaceId);
      } else {
        wgsToast.error(d.message || 'Chyba při odložení');
      }
      return;
    }

    // Pokud se přechází Z odložené, nejdřív zrušit příznak
    if (CURRENT_RECORD && (CURRENT_RECORD.je_odlozena == 1 || CURRENT_RECORD.je_odlozena === true)) {
      await volajOdlozApi(0);
      if (CURRENT_RECORD) CURRENT_RECORD.je_odlozena = 0;
      const zaz = WGS_DATA_CACHE.find(r => r.id == reklamaceId);
      if (zaz) zaz.je_odlozena = 0;
    }

    logger.log(`[Admin] Měním stav zakázky ${reklamaceId} na ${novyStav}` + (jeCnStav ? ` (CN workflow)` : ''));

    const formData = new FormData();
    formData.append('csrf_token', csrfToken);
    formData.append('id', reklamaceId);
    formData.append('stav', novyStav);
    if (zakaznikEmail) {
      formData.append('email', zakaznikEmail);
    }

    const response = await fetch('/api/zmenit_stav.php', {
      method: 'POST',
      body: formData
    });

    const data = await response.json();

    if (data.status === 'success') {
      // Aktualizovat lokální cache
      const record = WGS_DATA_CACHE.find(r => r.id == reklamaceId);
      if (record && data.db_stav) {
        record.stav = data.db_stav;
      }

      // Aktualizovat CURRENT_RECORD
      if (CURRENT_RECORD && CURRENT_RECORD.id == reklamaceId && data.db_stav) {
        CURRENT_RECORD.stav = data.db_stav;
      }

      // Aktualizovat CN cache pokud se změnil CN stav
      if (data.cn_stav && zakaznikEmail) {
        const emailLower = zakaznikEmail.toLowerCase();
        if (STAVY_NABIDEK) {
          STAVY_NABIDEK[emailLower] = data.cn_stav;
        }
        // Přidat email do EMAILS_S_CN pokud tam není
        if (EMAILS_S_CN && !EMAILS_S_CN.includes(emailLower) && data.cn_stav) {
          EMAILS_S_CN.push(emailLower);
        }
      }

      wgsToast.success(`Stav změněn na: ${stavyMap[novyStav] || novyStav}`);

      // Překreslit seznam (karty)
      renderOrders(WGS_DATA_CACHE);

      // Překreslit modal header (pills) okamžitě bez zavírání modalu
      const modalContent = document.getElementById('modalContent');
      if (modalContent) {
        const stavovyHeader = modalContent.querySelector('.modal-header');
        if (stavovyHeader) {
          const novyHeader = createCustomerHeader();
          const tempDiv = document.createElement('div');
          tempDiv.innerHTML = novyHeader;
          if (tempDiv.firstElementChild) {
            stavovyHeader.replaceWith(tempDiv.firstElementChild);
          }
        }
      }

      logger.log(`[Admin] Stav zakázky ${reklamaceId} změněn na ${novyStav}`);
    } else {
      wgsToast.error(data.message || 'Nepodařilo se změnit stav');
    }
  } catch (error) {
    logger.error('[Admin] Chyba při změně stavu:', error);
    wgsToast.error('Chyba při změně stavu: ' + error.message);
  }
}

// === POMOCNÉ FUNKCE PRO DELETE MODALY ===
function showDeleteConfirmModal(reklamaceNumber) {
  return new Promise((resolve) => {
    const modalDiv = document.createElement('div');
    modalDiv.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:10003;display:flex;align-items:center;justify-content:center;';

    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background:#1a1a1a;padding:25px;border-radius:12px;max-width:400px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.5);border:1px solid #333;';

    modalContent.innerHTML = `
      <h3 style="margin:0 0 15px 0;color:#fff;font-size:1.1rem;font-weight:600;">Smazat reklamaci?</h3>
      <p style="margin:0 0 15px 0;color:#ccc;line-height:1.5;font-size:0.95rem;">
        Opravdu chcete <strong style="color:#fff;">TRVALE SMAZAT</strong> reklamaci<br>
        <strong style="color:#fff;font-size:1rem;">${reklamaceNumber}</strong>?
      </p>
      <p style="margin:0 0 20px 0;color:#999;font-size:0.85rem;">
        Tato akce smaže VŠE včetně fotek a PDF!<br>
        Tuto akci NELZE vrátit zpět!
      </p>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button id="deleteConfirmNo" style="padding:10px 20px;background:transparent;color:#ccc;border:1px solid #444;border-radius:6px;cursor:pointer;font-size:0.9rem;">
          Zrušit
        </button>
        <button id="deleteConfirmYes" style="padding:10px 20px;background:#dc3545;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:500;">
          Smazat
        </button>
      </div>
    `;

    modalDiv.appendChild(modalContent);
    document.body.appendChild(modalDiv);

    const zavritConfirm = (vysledek) => {
      modalDiv.remove();
      document.removeEventListener('keydown', escConfirmHandler);
      resolve(vysledek);
    };

    document.getElementById('deleteConfirmNo').onclick = () => zavritConfirm(false);
    document.getElementById('deleteConfirmYes').onclick = () => zavritConfirm(true);
    modalDiv.addEventListener('click', (e) => { if (e.target === modalDiv) zavritConfirm(false); });
    const escConfirmHandler = (e) => { if (e.key === 'Escape') zavritConfirm(false); };
    document.addEventListener('keydown', escConfirmHandler);
  });
}

function showDeleteInputModal(reklamaceNumber) {
  return new Promise((resolve) => {
    const modalDiv = document.createElement('div');
    modalDiv.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:10003;display:flex;align-items:center;justify-content:center;';

    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background:#1a1a1a;padding:25px;border-radius:12px;max-width:400px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.5);border:1px solid #333;';

    modalContent.innerHTML = `
      <h3 style="margin:0 0 15px 0;color:#fff;font-size:1.1rem;font-weight:600;">Poslední ověření</h3>
      <p style="margin:0 0 15px 0;color:#ccc;line-height:1.5;font-size:0.95rem;">
        Pro potvrzení smazání zadejte přesně číslo reklamace:
      </p>
      <p style="margin:0 0 15px 0;color:#fff;font-size:1rem;font-weight:600;">
        ${reklamaceNumber}
      </p>
      <input type="text" id="deleteInputField"
             placeholder="Zadejte číslo reklamace"
             style="width:100%;padding:10px;background:#252525;border:1px solid #444;border-radius:6px;font-size:0.9rem;text-align:center;margin-bottom:20px;color:#fff;box-sizing:border-box;">
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button id="deleteInputCancel" style="padding:10px 20px;background:transparent;color:#ccc;border:1px solid #444;border-radius:6px;cursor:pointer;font-size:0.9rem;">
          Zrušit
        </button>
        <button id="deleteInputConfirm" style="padding:10px 20px;background:#dc3545;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:500;">
          Smazat
        </button>
      </div>
    `;

    modalDiv.appendChild(modalContent);
    document.body.appendChild(modalDiv);

    const inputField = document.getElementById('deleteInputField');
    inputField.focus();

    const zavritInput = (hodnota) => {
      modalDiv.remove();
      document.removeEventListener('keydown', escInputHandler);
      resolve(hodnota);
    };

    document.getElementById('deleteInputCancel').onclick = () => zavritInput('');
    document.getElementById('deleteInputConfirm').onclick = () => zavritInput(inputField.value.trim());

    // Enter key pro potvrzení
    inputField.addEventListener('keypress', (e) => {
      if (e.key === 'Enter') zavritInput(inputField.value.trim());
    });

    const escInputHandler = (e) => { if (e.key === 'Escape') zavritInput(''); };
    document.addEventListener('keydown', escInputHandler);
  });
}

// === SMAZÁNÍ REKLAMACE (ADMIN ONLY) ===
async function deleteReklamace(reklamaceId) {
  logger.log('[deleteReklamace] Zobrazuji 1. confirmation modal');

  const reklamaceNumber = CURRENT_RECORD.reklamace_id || CURRENT_RECORD.id || reklamaceId;

  // 1. KROK: První potvrzení
  const firstConfirm = await showDeleteConfirmModal(reklamaceNumber);
  if (!firstConfirm) {
    logger.log('Mazání zrušeno (1. krok)');
    return;
  }

  // 2. KROK: Zadání čísla reklamace
  const userInput = await showDeleteInputModal(reklamaceNumber);
  if (userInput !== reklamaceNumber) {
    logger.log('Mazání zrušeno - špatné číslo (2. krok)');

    // Zobrazit chybovou hlášku
    const errorModal = document.createElement('div');
    errorModal.id = 'errorModal';
    errorModal.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:10003;display:flex;align-items:center;justify-content:center;';
    errorModal.innerHTML = `
      <div style="position:relative;background:#1a1a1a;padding:25px;border-radius:12px;max-width:400px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.5);border:1px solid #333;">
        <button onclick="document.getElementById('errorModal').remove();" style="position:absolute;top:10px;right:10px;z-index:1;width:28px;height:28px;border-radius:50%;background:rgba(180,180,180,0.25);color:#cc0000;border:none;font-size:1.2rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;line-height:1;">&times;</button>
        <h3 style="margin:0 0 15px 0;color:#fff;font-size:1.1rem;font-weight:600;">Nesprávné číslo!</h3>
        <p style="margin:0 0 20px 0;color:#ccc;font-size:0.95rem;line-height:1.5;">Zadali jste nesprávné číslo reklamace.<br>Mazání bylo zrušeno.</p>
        <button onclick="document.getElementById('errorModal').remove();" style="padding:10px 20px;background:#fff;color:#000;border:none;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:500;">
          OK
        </button>
      </div>
    `;
    errorModal.addEventListener('click', (e) => { if (e.target === errorModal) errorModal.remove(); });
    const escErrorHandler = (e) => { if (e.key === 'Escape') { errorModal.remove(); document.removeEventListener('keydown', escErrorHandler); } };
    document.addEventListener('keydown', escErrorHandler);
    document.body.appendChild(errorModal);
    return;
  }

  logger.log('🗑️ Mazání reklamace:', reklamaceId);

  try {
    // Získat CSRF token
    const csrfToken = await fetchCsrfToken();

    const response = await fetch('api/delete_reklamace.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        reklamace_id: reklamaceId,
        csrf_token: csrfToken
      })
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${result.message || result.error || response.statusText}`);
    }

    if (result.success || result.status === 'success') {
      logger.log('Smazáno!');
      wgsToast.success(t('claim_deleted_successfully'));
      closeDetail();
      setTimeout(() => location.reload(), 500);
    } else {
      const errorMsg = result.message || result.error || t('delete_failed');
      logger.error('Chyba:', errorMsg);
      wgsToast.error(t('error') + ': ' + errorMsg);
    }
  } catch (error) {
    logger.error('Chyba při mazání:', error);
    wgsToast.error(t('delete_error') + ': ' + error.message);
  }
}

// === SMAZÁNÍ JEDNOTLIVÉ FOTKY ===
async function smazatFotku(photoId, photoUrl) {
  logger.log('[smazatFotku] Vytvářím confirmation modal pro ID:', photoId);

  // Vlastní confirmation modal (viditelný nad vším)
  return new Promise((resolve) => {
    const modalDiv = document.createElement('div');
    modalDiv.id = 'deleteFotoModal';
    modalDiv.style.cssText = 'position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.85);z-index:10003;display:flex;align-items:center;justify-content:center;';

    const modalContent = document.createElement('div');
    modalContent.style.cssText = 'background:#1a1a1a;padding:25px;border-radius:12px;max-width:400px;width:90%;text-align:center;box-shadow:0 10px 40px rgba(0,0,0,0.5);border:1px solid #333;';

    modalContent.innerHTML = `
      <h3 style="margin:0 0 15px 0;color:#fff;font-size:1.1rem;font-weight:600;">Smazat fotku?</h3>
      <p style="margin:0 0 20px 0;color:#ccc;line-height:1.5;font-size:0.95rem;">
        Opravdu chcete smazat tuto fotografii?<br>
        <strong style="color:#999;">Tato akce je nevratná!</strong>
      </p>
      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button id="deleteFotoNo" style="padding:10px 20px;background:transparent;color:#ccc;border:1px solid #444;border-radius:6px;cursor:pointer;font-size:0.9rem;">
          Zrušit
        </button>
        <button id="deleteFotoYes" style="padding:10px 20px;background:#dc3545;color:#fff;border:none;border-radius:6px;cursor:pointer;font-size:0.9rem;font-weight:500;">
          Smazat
        </button>
      </div>
    `;

    modalDiv.appendChild(modalContent);
    document.body.appendChild(modalDiv);

    const zavritFoto = (vysledek) => {
      modalDiv.remove();
      document.removeEventListener('keydown', escFotoHandler);
      resolve(vysledek);
    };

    document.getElementById('deleteFotoNo').onclick = () => {
      logger.log('[smazatFotku] Uživatel zrušil');
      zavritFoto(false);
    };

    document.getElementById('deleteFotoYes').onclick = async () => {
      logger.log('[smazatFotku] Uživatel potvrdil, mazám...');
      modalDiv.remove();
      document.removeEventListener('keydown', escFotoHandler);
      await pokracovatSmazaniFotky(photoId, photoUrl);
      resolve(true);
    };

    modalDiv.addEventListener('click', (e) => { if (e.target === modalDiv) zavritFoto(false); });
    const escFotoHandler = (e) => { if (e.key === 'Escape') zavritFoto(false); };
    document.addEventListener('keydown', escFotoHandler);
  });
}

async function pokracovatSmazaniFotky(photoId, photoUrl) {
  logger.log('🗑️ Mazání fotky ID:', photoId);

  try {
    // Získat CSRF token
    const csrfToken = await fetchCsrfToken();

    const formData = new FormData();
    formData.append('photo_id', photoId);
    formData.append('csrf_token', csrfToken);

    const response = await fetch('api/delete_photo.php', {
      method: 'POST',
      body: formData
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}: ${result.message || result.error || response.statusText}`);
    }

    if (result.status === 'success') {
      logger.log('Fotka smazána!');

      // Odstranit fotku z DOM
      const fotoElements = document.querySelectorAll('.foto-wrapper img');
      for (const img of fotoElements) {
        if (img.src.includes(photoUrl.replace(/\\/g, ''))) {
          img.closest('.foto-wrapper').remove();
          break;
        }
      }

      // Aktualizovat počet fotek v nadpisu
      const fotkyNadpis = document.querySelector('[style*="Fotografie"]');
      if (fotkyNadpis) {
        const zbyvajiciFotky = document.querySelectorAll('.foto-wrapper').length;
        fotkyNadpis.textContent = `Fotografie (${zbyvajiciFotky})`;

        // Pokud nezbyla žádná fotka, zobrazit "Žádné fotografie"
        if (zbyvajiciFotky === 0) {
          const fotoContainer = fotkyNadpis.closest('div');
          const grid = fotoContainer.querySelector('[style*="grid"]');
          if (grid) {
            grid.innerHTML = `<p style="color: var(--c-grey); text-align: center; padding: 1rem; font-size: 0.9rem;">${t('no_photos')}</p>`;
          }
        }
      }

      wgsToast.success(t('photo_deleted_successfully'));
    } else {
      const errorMsg = result.message || result.error || t('delete_failed');
      logger.error('Chyba:', errorMsg);
      wgsToast.error(t('error') + ': ' + errorMsg);
    }
  } catch (error) {
    logger.error('Chyba při mazání fotky:', error);
    wgsToast.error(t('photo_delete_error') + ': ' + error.message);
  }
}

// Načti fotky z databáze
async function loadPhotosFromDB(reklamaceId) {
  try {
    const response = await fetch(`api/get_photos_api.php?reklamace_id=${reklamaceId}`);
    if (!response.ok) return [];

    const data = await response.json();
    if (data.success && data.photos) {
      // Vrátit celé objekty včetně ID pro možnost mazání
      return data.photos.map(p => ({
        id: p.id,
        photo_path: p.photo_path,
        section_name: p.section_name
      }));
    }
    return [];
  } catch (err) {
    logger.error('Chyba načítání fotek:', err);
    return [];
  }
}


// Export admin funkcí do globálního scope
window.zmenitStavZakazky = zmenitStavZakazky;
window.deleteReklamace = deleteReklamace;
window.autoAssignTechnician = autoAssignTechnician;
