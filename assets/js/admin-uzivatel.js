/**
 * admin-uzivatel.js
 * Detail uživatele — zobrazení, editace, změna hesla, aktivace/deaktivace
 * Závisí na: admin.js (fetchCSRFToken, wgsToast, logger)
 */

// ============================================================
// SPRÁVA UŽIVATELŮ - DETAIL
// ============================================================

/**
 * Zobrazení detailu uživatele s možností úprav
 */
async function zobrazDetailUzivatele(userId) {
  try {
    const response = await fetch(`/api/admin_users_api.php?action=get&user_id=${userId}`, {
      credentials: 'same-origin'
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (data.status !== 'success' || !data.user) {
      throw new Error(data.message || 'Nepodařilo se načíst detail uživatele');
    }

    const user = data.user;

    // Vytvoření modalu s detailem
    const modalHTML = `
      <div class="user-detail-modal" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.7); z-index: 10000; display: flex; align-items: center; justify-content: center;">
        <div style="background: #1a1a1a; border-radius: 12px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; box-shadow: 0 10px 40px rgba(0,0,0,0.5); border: 1px solid #333;">
          <!-- Header -->
          <div style="background: #333; color: #fff; padding: 1.5rem; border-radius: 12px 12px 0 0; position: relative; border-bottom: 1px solid #444;">
            <h2 style="margin: 0; font-size: 1.1rem; font-weight: 600; color: #fff;">Detail uživatele #${user.id}</h2>
            <button data-action="zavritDetailUzivatele" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; color: #ccc; font-size: 2rem; cursor: pointer; line-height: 1; padding: 0; width: 32px; height: 32px;">&times;</button>
          </div>

          <!-- Body -->
          <div style="padding: 2rem;">
            <!-- Základní informace -->
            <div style="margin-bottom: 2rem;">
              <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: #fff; border-bottom: 1px solid #444; padding-bottom: 0.5rem;">Základní údaje</h3>

              <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.85rem; color: #aaa;">Jméno a příjmení</label>
                <input type="text" id="edit-user-name" value="${escapeHtml(user.name)}" style="width: 100%; padding: 0.6rem; border: 1px solid #444; border-radius: 6px; font-size: 1rem; background: #222; color: #fff;">
              </div>

              <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.85rem; color: #aaa;">Email</label>
                <input type="email" id="edit-user-email" value="${escapeHtml(user.email)}" style="width: 100%; padding: 0.6rem; border: 1px solid #444; border-radius: 6px; font-size: 1rem; background: #222; color: #fff;">
              </div>

              <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.85rem; color: #aaa;">Telefon</label>
                <input type="tel" id="edit-user-phone" value="${escapeHtml(user.phone || '')}" placeholder="+420123456789" style="width: 100%; padding: 0.6rem; border: 1px solid #444; border-radius: 6px; font-size: 1rem; background: #222; color: #fff;">
              </div>

              <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.85rem; color: #aaa;">Adresa</label>
                <input type="text" id="edit-user-address" value="${escapeHtml(user.address || '')}" placeholder="Ulice 123, Město" style="width: 100%; padding: 0.6rem; border: 1px solid #444; border-radius: 6px; font-size: 1rem; background: #222; color: #fff;">
              </div>

              <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.85rem; color: #aaa;">Role</label>
                <select id="edit-user-role" style="width: 100%; padding: 0.6rem; border: 1px solid #444; border-radius: 6px; font-size: 1rem; background: #222; color: #fff;">
                  <option value="prodejce" ${user.role === 'prodejce' ? 'selected' : ''}>Prodejce</option>
                  <option value="technik" ${user.role === 'technik' ? 'selected' : ''}>Technik</option>
                  <option value="admin" ${user.role === 'admin' ? 'selected' : ''}>Administrátor</option>
                </select>
              </div>

              <!-- Provize (pouze pro techniky) -->
              <div id="provize-container" style="margin-bottom: 1rem; display: ${user.role === 'technik' ? 'block' : 'none'}; padding: 1rem; background: #222; border-radius: 8px; border: 1px solid #444;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.85rem; color: #aaa;">
                  Provize technika (%)
                  <span style="color: #666; font-weight: 400; font-size: 0.8rem;"> – procento z ceny reklamace</span>
                </label>
                <input type="number" id="edit-user-provize" value="${user.provize_procent || 33}" min="0" max="100" step="0.01" style="width: 100%; padding: 0.6rem; border: 1px solid #444; border-radius: 6px; font-size: 1rem; background: #1a1a1a; color: #fff;">
                <div style="margin-top: 0.5rem; font-size: 0.8rem; color: #888;">
                  Výchozí hodnota: 33% | Rozsah: 0-100%
                </div>
              </div>

              <!-- Provize POZ (pouze pro techniky) -->
              <div id="provize-poz-container" style="margin-bottom: 1rem; display: ${user.role === 'technik' ? 'block' : 'none'}; padding: 1rem; background: #222; border-radius: 8px; border: 1px solid #444;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.85rem; color: #aaa;">
                  Provize POZ (%)
                  <span style="color: #666; font-weight: 400; font-size: 0.8rem;"> – procento z mimozáručních servisů</span>
                </label>
                <input type="number" id="edit-user-provize-poz" value="${user.provize_poz_procent || 50}" min="0" max="100" step="0.01" style="width: 100%; padding: 0.6rem; border: 1px solid #444; border-radius: 6px; font-size: 1rem; background: #1a1a1a; color: #fff;">
                <div style="margin-top: 0.5rem; font-size: 0.8rem; color: #888;">
                  Výchozí hodnota: 50% | Rozsah: 0-100%
                </div>
              </div>

              <button data-action="ulozitZmenyUzivatele" data-id="${user.id}" style="width: 100%; padding: 0.8rem; background: #333; color: #fff; border: 1px solid #444; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s;">
                Uložit změny
              </button>
            </div>

            <!-- Změna hesla -->
            <div style="margin-bottom: 2rem; padding: 1rem; background: #222; border-radius: 8px; border: 1px solid #444;">
              <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: #ccc;">Změna hesla</h3>

              <div style="margin-bottom: 1rem;">
                <label style="display: block; font-weight: 500; margin-bottom: 0.3rem; font-size: 0.85rem; color: #aaa;">Nové heslo (min. 8 znaků)</label>
                <input type="password" id="edit-user-password" placeholder="••••••••" style="width: 100%; padding: 0.6rem; border: 1px solid #444; border-radius: 6px; font-size: 1rem; background: #1a1a1a; color: #fff;">
              </div>

              <button data-action="zmenitHesloUzivatele" data-id="${user.id}" style="width: 100%; padding: 0.8rem; background: #444; color: #fff; border: 1px solid #555; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s;">
                Změnit heslo
              </button>
            </div>

            <!-- Status a akce -->
            <div style="padding: 1rem; background: #222; border-radius: 8px; border: 1px solid ${user.status === 'active' ? 'var(--wgs-gray-33)' : 'var(--wgs-danger)'};">
              <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: #fff;">Stav účtu</h3>

              <div style="margin-bottom: 1rem; color: #ccc;">
                <strong>Aktuální stav:</strong>
                <span style="font-weight: bold; color: ${user.status === 'active' ? 'var(--wgs-neon-green)' : 'var(--wgs-danger)'};">
                  ${user.status === 'active' ? 'AKTIVNÍ' : 'NEAKTIVNÍ'}
                </span>
              </div>

              ${user.created_at ? `
                <div style="margin-bottom: 1rem; font-size: 0.9rem; color: #888;">
                  <strong style="color: #aaa;">Vytvořen:</strong> ${new Date(user.created_at).toLocaleString('cs-CZ')}
                </div>
              ` : ''}

              ${user.last_login ? `
                <div style="margin-bottom: 1rem; font-size: 0.9rem; color: #888;">
                  <strong style="color: #aaa;">Poslední přihlášení:</strong> ${new Date(user.last_login).toLocaleString('cs-CZ')}
                </div>
              ` : ''}

              <button data-action="prepnoutStatusUzivatele" data-id="${user.id}" data-status="${user.status === 'active' ? 'inactive' : 'active'}" style="width: 100%; padding: 0.8rem; background: ${user.status === 'active' ? 'var(--wgs-danger)' : 'var(--wgs-gray-33)'}; color: #fff; border: none; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer;">
                ${user.status === 'active' ? 'Deaktivovat uživatele' : 'Aktivovat uživatele'}
              </button>
            </div>

            <!-- Supervizor sekce -->
            <div style="margin-top: 2rem; padding: 1rem; background: #222; border-radius: 8px; border: 1px solid #444;">
              <h3 style="font-size: 1rem; font-weight: 600; margin-bottom: 1rem; color: #fff;">Supervizor</h3>
              <p style="font-size: 0.85rem; color: #888; margin-bottom: 1rem;">
                Jako supervizor tento uživatel uvidí zakázky vybraných prodejců.
              </p>
              <div id="supervisorAssignmentsPreview" style="margin-bottom: 1rem; font-size: 0.9rem; color: #ccc;">
                Načítám přiřazení...
              </div>
              <button data-action="otevritSpravuSupervize" data-id="${user.id}" style="width: 100%; padding: 0.8rem; background: #333; color: #fff; border: 1px solid #444; border-radius: 6px; font-weight: 600; font-size: 1rem; cursor: pointer; transition: background 0.2s;">
                Spravovat přiřazení
              </button>
            </div>
          </div>
        </div>
      </div>
    `;

    // Načíst supervizor přiřazení po vykreslení
    setTimeout(() => nactiSupervizorPrirazeni(user.id), 100);

    // Přidat modal do DOM
    const modalContainer = document.createElement('div');
    modalContainer.id = 'userDetailModal';
    modalContainer.innerHTML = modalHTML;
    document.body.appendChild(modalContainer);

    // Event listener pro změnu role - zobrazit/skrýt pole provize
    const roleSelect = document.getElementById('edit-user-role');
    const provizeContainer = document.getElementById('provize-container');
    const provizePozContainer = document.getElementById('provize-poz-container');
    if (roleSelect && provizeContainer && provizePozContainer) {
      roleSelect.addEventListener('change', (e) => {
        if (e.target.value === 'technik') {
          provizeContainer.style.display = 'block';
          provizePozContainer.style.display = 'block';
        } else {
          provizeContainer.style.display = 'none';
          provizePozContainer.style.display = 'none';
        }
      });
    }

  } catch (error) {
    logger.error('Chyba při načítání detailu uživatele:', error);
    wgsToast.error('Chyba při načítání detailu: ' + error.message);
  }
}

/**
 * Zavření detailu uživatele
 */
function zavritDetailUzivatele() {
  const modal = document.getElementById('userDetailModal');
  if (modal) {
    modal.remove();
  }
}

/**
 * Uložení změn uživatele
 */
async function ulozitZmenyUzivatele(userId) {
  try {
    const name = document.getElementById('edit-user-name').value.trim();
    const email = document.getElementById('edit-user-email').value.trim();
    const phone = document.getElementById('edit-user-phone').value.trim();
    const address = document.getElementById('edit-user-address').value.trim();
    const role = document.getElementById('edit-user-role').value;
    const provizeProcent = document.getElementById('edit-user-provize') ?
                          document.getElementById('edit-user-provize').value : null;
    const provizePozProcent = document.getElementById('edit-user-provize-poz') ?
                          document.getElementById('edit-user-provize-poz').value : null;

    if (!name || !email) {
      wgsToast.warning('Jméno a email jsou povinné');
      return;
    }

    // Validace provize (pouze pokud je pole viditelné a vyplněné)
    if (role === 'technik' && provizeProcent !== null) {
      const provizeNum = parseFloat(provizeProcent);
      if (isNaN(provizeNum) || provizeNum < 0 || provizeNum > 100) {
        wgsToast.warning('Provize musí být číslo mezi 0 a 100');
        return;
      }
    }

    // Validace provize POZ (pouze pokud je pole viditelné a vyplněné)
    if (role === 'technik' && provizePozProcent !== null) {
      const provizePozNum = parseFloat(provizePozProcent);
      if (isNaN(provizePozNum) || provizePozNum < 0 || provizePozNum > 100) {
        wgsToast.warning('Provize POZ musí být číslo mezi 0 a 100');
        return;
      }
    }

    const csrfToken = await getCSRFToken();
    if (!csrfToken) {
      throw new Error('CSRF token není k dispozici');
    }

    const payload = {
      user_id: userId,
      name,
      email,
      phone,
      address,
      role,
      csrf_token: csrfToken
    };

    // Přidat provizi pouze pokud je role technik a hodnota je zadána
    if (role === 'technik' && provizeProcent !== null) {
      payload.provize_procent = provizeProcent;
    }

    // Přidat provizi POZ pouze pokud je role technik a hodnota je zadána
    if (role === 'technik' && provizePozProcent !== null) {
      payload.provize_poz_procent = provizePozProcent;
    }

    const response = await fetch('/api/admin_users_api.php?action=update', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify(payload)
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (data.status === 'success') {
      wgsToast.success('Změny byly uloženy');
      zavritDetailUzivatele();
      loadUsers(); // Obnovit tabulku
    } else {
      wgsToast.error('Chyba: ' + (data.message || 'Nepodařilo se uložit změny'));
    }
  } catch (error) {
    logger.error('Chyba při ukládání změn:', error);
    wgsToast.error('Chyba při ukládání: ' + error.message);
  }
}

/**
 * Změna hesla uživatele
 */
async function zmenitHesloUzivatele(userId) {
  try {
    const newPassword = document.getElementById('edit-user-password').value;

    if (!newPassword) {
      wgsToast.warning('Zadejte nové heslo');
      return;
    }

    if (newPassword.length < 8) {
      wgsToast.warning('Heslo musí mít alespoň 8 znaků');
      return;
    }

    const potvrdit = await wgsConfirm('Opravdu chcete změnit heslo tohoto uživatele?', {
        titulek: 'Změna hesla',
        btnPotvrdit: 'Změnit heslo',
        nebezpecne: true
    });
    if (!potvrdit) return;

    const csrfToken = await getCSRFToken();
    if (!csrfToken) {
      throw new Error('CSRF token není k dispozici');
    }

    const response = await fetch('/api/admin_users_api.php?action=update_password', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        user_id: userId,
        new_password: newPassword,
        csrf_token: csrfToken
      })
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (data.status === 'success') {
      wgsToast.success('Heslo bylo změněno');
      document.getElementById('edit-user-password').value = ''; // Vymazat pole
    } else {
      wgsToast.error('Chyba: ' + (data.message || 'Nepodařilo se změnit heslo'));
    }
  } catch (error) {
    logger.error('Chyba při změně hesla:', error);
    wgsToast.error('Chyba při změně hesla: ' + error.message);
  }
}

/**
 * Přepnutí statusu uživatele (aktivní/neaktivní)
 */
async function prepnoutStatusUzivatele(userId, newStatus) {
  try {
    const statusText = newStatus === 'active' ? 'aktivovat' : 'deaktivovat';
    const potvrdit = await wgsConfirm(`Opravdu chcete ${statusText} tohoto uživatele?`, {
        titulek: newStatus === 'active' ? 'Aktivovat uživatele' : 'Deaktivovat uživatele',
        btnPotvrdit: newStatus === 'active' ? 'Aktivovat' : 'Deaktivovat',
        nebezpecne: newStatus !== 'active'
    });
    if (!potvrdit) return;

    const csrfToken = await getCSRFToken();
    if (!csrfToken) {
      throw new Error('CSRF token není k dispozici');
    }

    const response = await fetch('/api/admin_users_api.php?action=update_status', {
      method: 'POST',
      credentials: 'same-origin',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        user_id: userId,
        status: newStatus,
        csrf_token: csrfToken
      })
    });

    if (!response.ok) {
      throw new Error(`HTTP ${response.status}`);
    }

    const data = await response.json();

    if (data.status === 'success') {
      wgsToast.success(`Uživatel byl ${newStatus === 'active' ? 'aktivován' : 'deaktivován'}`);
      zavritDetailUzivatele();
      loadUsers(); // Obnovit tabulku
    } else {
      wgsToast.error('Chyba: ' + (data.message || 'Nepodařilo se změnit status'));
    }
  } catch (error) {
    logger.error('Chyba při změně statusu:', error);
    wgsToast.error('Chyba při změně statusu: ' + error.message);
  }
}

