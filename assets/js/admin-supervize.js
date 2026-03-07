/**
 * admin-supervize.js
 * Správa supervizorského přiřazení uživatelů
 * Závisí na: admin.js (fetchCSRFToken, wgsToast, logger)
 */

// ============================================================
// SUPERVIZOR - SPRÁVA PŘIŘAZENÍ PRODEJCŮ
// ============================================================

/**
 * Načtení supervizor přiřazení pro náhled v detailu
 */
async function nactiSupervizorPrirazeni(userId) {
  try {
    const preview = document.getElementById('supervisorAssignmentsPreview');
    if (!preview) return;

    const response = await fetch(`/api/supervisor_api.php?action=getAssignments&user_id=${userId}`, {
      credentials: 'same-origin'
    });

    const data = await response.json();

    if (data.status === 'success') {
      const assignments = data.data.assignments || [];
      if (assignments.length === 0) {
        preview.innerHTML = '<span style="color: #999;">Žádní přiřazení prodejci</span>';
      } else {
        const names = assignments.map(a => escapeHtml(a.jmeno || a.email)).join(', ');
        preview.innerHTML = `<strong>Přiřazení prodejci (${assignments.length}):</strong><br>${names}`;
      }
    } else {
      preview.innerHTML = '<span style="color: #dc2626;">Chyba načítání</span>';
    }
  } catch (error) {
    logger.error('Chyba při načítání supervizor přiřazení:', error);
    const preview = document.getElementById('supervisorAssignmentsPreview');
    if (preview) {
      preview.innerHTML = '<span style="color: #dc2626;">Chyba: ' + escapeHtml(error.message) + '</span>';
    }
  }
}

/**
 * Otevření overlay pro správu supervize
 */
async function otevritSpravuSupervize(userId) {
  try {
    // Načíst všechny prodejce a aktuální přiřazení
    const [salespersonsRes, assignmentsRes] = await Promise.all([
      fetch(`/api/supervisor_api.php?action=getSalespersons&exclude_user_id=${userId}`, { credentials: 'same-origin' }),
      fetch(`/api/supervisor_api.php?action=getAssignments&user_id=${userId}`, { credentials: 'same-origin' })
    ]);

    const salespersonsData = await salespersonsRes.json();
    const assignmentsData = await assignmentsRes.json();

    if (salespersonsData.status !== 'success') {
      throw new Error(salespersonsData.message || 'Chyba načítání prodejců');
    }

    const salespersons = salespersonsData.data.salespersons || [];
    // assignedIds obsahuje INT salesperson_user_id z tabulky supervisor_assignments
    const assignedIds = (assignmentsData.data?.assignments || []).map(a => parseInt(a.salesperson_user_id));

    // Vytvořit overlay
    // Používáme numeric_id (INT) pro checkbox value, protože supervisor_assignments ukládá INT
    const overlayHTML = `
      <div id="supervisorOverlay" style="position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); z-index: 10001; display: flex; align-items: center; justify-content: center;">
        <div style="background: #1a1a1a; border-radius: 12px; max-width: 500px; width: 90%; max-height: 80vh; overflow: hidden; box-shadow: 0 10px 40px rgba(0,0,0,0.5); border: 1px solid #333;">
          <div style="background: #333; color: #fff; padding: 1.5rem; position: relative; border-bottom: 1px solid #444;">
            <h3 style="margin: 0; font-size: 1.1rem; font-weight: 600; color: #fff;">Správa supervize</h3>
            <p style="margin: 0.5rem 0 0; font-size: 0.85rem; color: #aaa;">Vyberte prodejce, jejichž zakázky bude supervizor vidět</p>
            <button data-action="zavritSupervizorOverlay" style="position: absolute; top: 1rem; right: 1rem; background: none; border: none; color: #ccc; font-size: 1.8rem; cursor: pointer; line-height: 1;">&times;</button>
          </div>
          <div style="padding: 1.5rem; max-height: 50vh; overflow-y: auto;">
            ${salespersons.length === 0 ? '<p style="color: #888; text-align: center;">Žádní další uživatelé v systému</p>' : ''}
            ${salespersons.map(s => `
              <label style="display: flex; align-items: center; padding: 0.8rem; margin-bottom: 0.5rem; background: #222; border-radius: 8px; cursor: pointer; transition: background 0.2s; border: 1px solid #333;">
                <input type="checkbox" class="supervisor-checkbox" value="${s.numeric_id}" ${assignedIds.includes(parseInt(s.numeric_id)) ? 'checked' : ''} style="width: 20px; height: 20px; margin-right: 1rem; accent-color: #39ff14;">
                <div style="flex: 1;">
                  <div style="font-weight: 600; color: #fff;">${escapeHtml(s.jmeno || 'Bez jména')}</div>
                  <div style="font-size: 0.85rem; color: #888;">${escapeHtml(s.email)} - ${escapeHtml(s.role || 'prodejce')}</div>
                </div>
              </label>
            `).join('')}
          </div>
          <div style="padding: 1rem 1.5rem; border-top: 1px solid #333; display: flex; gap: 1rem;">
            <button data-action="zavritSupervizorOverlay" style="flex: 1; padding: 0.8rem; background: transparent; color: #ccc; border: 1px solid #444; border-radius: 6px; font-weight: 600; cursor: pointer;">
              Zrušit
            </button>
            <button data-action="ulozitSupervizorPrirazeni" data-id="${userId}" style="flex: 1; padding: 0.8rem; background: #333; color: #fff; border: 1px solid #444; border-radius: 6px; font-weight: 600; cursor: pointer;">
              Uložit
            </button>
          </div>
        </div>
      </div>
    `;

    // Přidat overlay do DOM
    const overlayContainer = document.createElement('div');
    overlayContainer.innerHTML = overlayHTML;
    document.body.appendChild(overlayContainer.firstElementChild);

  } catch (error) {
    logger.error('Chyba při otevírání správy supervize:', error);
    wgsToast.error('Chyba: ' + error.message);
  }
}

/**
 * Zavření supervizor overlay
 */
function zavritSupervizorOverlay() {
  const overlay = document.getElementById('supervisorOverlay');
  if (overlay) {
    overlay.remove();
  }
}

/**
 * Uložení supervizor přiřazení
 */
async function ulozitSupervizorPrirazeni(userId) {
  try {
    // Získat zaškrtnuté prodejce
    const checkboxes = document.querySelectorAll('.supervisor-checkbox:checked');
    const salespersonIds = Array.from(checkboxes).map(cb => parseInt(cb.value));

    const csrfToken = await getCSRFToken();
    if (!csrfToken) {
      throw new Error('CSRF token není k dispozici');
    }

    const formData = new FormData();
    formData.append('action', 'saveAssignments');
    formData.append('supervisor_id', userId);
    formData.append('salesperson_ids', JSON.stringify(salespersonIds));
    formData.append('csrf_token', csrfToken);

    const response = await fetch('/api/supervisor_api.php', {
      method: 'POST',
      credentials: 'same-origin',
      body: formData
    });

    const data = await response.json();

    if (data.status === 'success') {
      wgsToast.success(data.message || 'Přiřazení uloženo');
      zavritSupervizorOverlay();
      // Aktualizovat náhled v detailu
      nactiSupervizorPrirazeni(userId);
    } else {
      wgsToast.error('Chyba: ' + (data.message || 'Nepodařilo se uložit'));
    }
  } catch (error) {
    logger.error('Chyba při ukládání supervizor přiřazení:', error);
    wgsToast.error('Chyba: ' + error.message);
  }
}

// Zpřístupnit supervizor funkce globálně
window.nactiSupervizorPrirazeni = nactiSupervizorPrirazeni;
window.otevritSpravuSupervize = otevritSpravuSupervize;
window.zavritSupervizorOverlay = zavritSupervizorOverlay;
window.ulozitSupervizorPrirazeni = ulozitSupervizorPrirazeni;

// Posluchač postMessage pro přepínání tabů z iframe
window.addEventListener('message', function(event) {
  // Bezpečnostní kontrola - pouze zprávy z naší domény
  if (event.origin !== window.location.origin) {
    return;
  }

  const message = event.data;

  if (message.action === 'switchTab' && message.tab) {
    // Přepnout na požadovaný tab
    let url = '/admin.php?tab=' + message.tab;
    if (message.highlightId) {
      url += '#' + message.highlightId;
    }
    window.location.href = url;
  }
});

