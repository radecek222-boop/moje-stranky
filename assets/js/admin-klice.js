/**
 * admin-klice.js
 * Správa registračních klíčů (vytvoření, smazání, akce)
 * Závisí na: admin.js (fetchCSRFToken, wgsToast, logger)
 */

async function deleteKey(keyCode) {
    const potvrdit = await wgsConfirm('Opravdu chcete smazat tento klíč?', {
        titulek: 'Smazat klíč',
        btnPotvrdit: 'Smazat',
        nebezpecne: true
    });
    if (!potvrdit) return;

    const csrfToken = await getCSRFToken();
    if (!csrfToken) {
        wgsToast.error(t('csrf_token_not_found'));
        return;
    }

    fetch('api/admin_api.php?action=delete_key', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            key_code: keyCode,
            csrf_token: csrfToken
        })
    })
    .then(r => r.json())
    .then(data => {
        if (isSuccess(data)) {
            loadKeysModal(); // Reload
        } else {
            wgsToast.error(t('error') + ': ' + (data.error || data.message || t('unknown_error')));
        }
    })
    .catch(err => {
        wgsToast.error('Chyba: ' + err.message);
    });
}

/**
 * CreateKey - zobrazí modal pro výběr typu klíče
 */
function createKey() {
    // Vytvořit modal pro výběr typu klíče
    const existujiciModal = document.getElementById('createKeyModal');
    if (existujiciModal) existujiciModal.remove();

    const modal = document.createElement('div');
    modal.id = 'createKeyModal';
    modal.style.cssText = `
        position: fixed; top: 0; left: 0; right: 0; bottom: 0;
        background: rgba(0,0,0,0.6); display: flex;
        align-items: center; justify-content: center; z-index: 10000;
    `;

    modal.innerHTML = `
        <style>
            .key-type-label {
                display: flex; align-items: center; padding: 15px;
                background: #252525; border-radius: 8px; cursor: pointer;
                border: 2px solid transparent; transition: all 0.2s;
            }
            .key-type-label:hover { border-color: #555; }
            .key-type-label.selected { border-color: #fff; }
            .key-type-label:first-child { margin-bottom: 10px; }
        </style>
        <div style="background: #1a1a1a; padding: 30px; border-radius: 12px;
                    max-width: 400px; width: 90%; box-shadow: 0 10px 40px rgba(0,0,0,0.5);
                    border: 1px solid #333;">
            <h3 style="margin: 0 0 20px 0; color: #fff; font-size: 1.3rem;">
                Vytvořit nový registrační klíč
            </h3>

            <div style="margin-bottom: 20px;">
                <label class="key-type-label">
                    <input type="radio" name="keyType" value="technik"
                           style="width: 20px; height: 20px; margin-right: 15px; accent-color: #fff;">
                    <div>
                        <div style="color: #fff; font-weight: 600; font-size: 1.1rem;">TECHNIK</div>
                        <div style="color: #888; font-size: 0.85rem;">Pro servisní techniky</div>
                    </div>
                </label>

                <label class="key-type-label">
                    <input type="radio" name="keyType" value="prodejce"
                           style="width: 20px; height: 20px; margin-right: 15px; accent-color: #fff;">
                    <div>
                        <div style="color: #fff; font-weight: 600; font-size: 1.1rem;">PRODEJCE</div>
                        <div style="color: #888; font-size: 0.85rem;">Pro prodejce a obchodníky</div>
                    </div>
                </label>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="display: block; color: #aaa; font-size: 0.85rem; margin-bottom: 8px;">
                    Prirazeny email (volitelne)
                </label>
                <input type="email" id="createKeyEmail" placeholder="jan@example.com"
                       style="width: 100%; padding: 10px; background: #252525; border: 1px solid #444;
                       border-radius: 6px; color: #fff; font-size: 0.9rem; box-sizing: border-box;">
                <div style="color: #666; font-size: 0.75rem; margin-top: 5px;">
                    Email se ulozi ke klici, ale pozvanka se NEODESLE. Pro odeslani pouzijte "Pozvat".
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button data-action="vytvorKlicZModalu" style="flex: 1; padding: 12px;
                        background: #fff; color: #000; border: none; border-radius: 6px;
                        font-weight: 600; cursor: pointer; font-size: 1rem;">
                    Vytvořit klíč
                </button>
                <button data-action="zavritCreateKeyModal"
                        style="flex: 1; padding: 12px; background: #333; color: #fff;
                        border: none; border-radius: 6px; cursor: pointer; font-size: 1rem;">
                    Zrušit
                </button>
            </div>
        </div>
    `;

    document.body.appendChild(modal);

    // Event listener pro radio buttony - toggle selected class
    modal.querySelectorAll('input[name="keyType"]').forEach(radio => {
        radio.addEventListener('change', () => {
            modal.querySelectorAll('.key-type-label').forEach(label => {
                label.classList.toggle('selected', label.contains(radio) && radio.checked);
            });
        });
    });

    // Zavřít při kliknutí na pozadí
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.remove();
    });
}

/**
 * VytvorKlicZModalu - odešle požadavek na vytvoření klíče
 */
function vytvorKlicZModalu() {
    const vybranyTyp = document.querySelector('input[name="keyType"]:checked');

    if (!vybranyTyp) {
        wgsToast.warning('Vyberte typ klíče');
        return;
    }

    const keyType = vybranyTyp.value;
    const emailInput = document.getElementById('createKeyEmail');
    const email = emailInput ? emailInput.value.trim() : '';
    const csrfToken = getCSRFToken();

    if (!csrfToken) {
        wgsToast.error(t('csrf_token_not_found'));
        return;
    }

    const payload = {
        key_type: keyType,
        csrf_token: csrfToken
    };

    // Pridat email pouze pokud je vyplnen
    if (email !== '') {
        payload.email = email;
    }

    fetch('api/admin_api.php?action=create_key', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(payload)
    })
    .then(r => r.json())
    .then(data => {
        document.getElementById('createKeyModal')?.remove();

        if (isSuccess(data)) {
            let zprava = t('key_created').replace('{key}', data.key_code);
            if (data.email) {
                zprava += ' (email: ' + data.email + ')';
            }
            wgsToast.success(zprava);
            loadKeysModal(); // Reload
        } else {
            wgsToast.error(t('error') + ': ' + (data.error || data.message || t('unknown_error')));
        }
    })
    .catch(err => {
        wgsToast.error('Chyba: ' + err.message);
    });
}

/**
 * ExecuteAction
 */
async function executeAction(actionId) {
    // Zachytit tlačítko PŘED jakýmkoliv await
    const btn = event.target;
    const originalText = btn.textContent;
