/**
 * seznam-hromadne.js
 * Hromadné akce (multi-select, toolbar, změna stavu více zakázek najednou)
 * Závisí na: seznam.js (fetchCsrfToken, logger, wgsToast, WGS_DATA_CACHE, loadAll)
 */

// ==========================================
// HROMADNÉ AKCE (U8)
// ==========================================
let VYBRANE_IDS = new Set();
let HROMADNE_MOD = false;

function hromadneToggle(id, zaskrtnuto) {
    if (zaskrtnuto) {
        VYBRANE_IDS.add(id);
    } else {
        VYBRANE_IDS.delete(id);
    }
    hromadneAktualizujToolbar();
}

function hromadneZapnout() {
    HROMADNE_MOD = true;
    VYBRANE_IDS.clear();
    document.querySelectorAll('.hromadne-check').forEach(el => { el.style.display = ''; el.checked = false; });
    document.querySelectorAll('[data-action="showDetailById"]').forEach(el => {
        el.addEventListener('click', hromadneZabranDetailu, true);
    });
    hromadneAktualizujToolbar();
    const btn = document.getElementById('btnHromadneToggle');
    if (btn) btn.textContent = 'Zrušit výběr';
}

function hromadneVypnout() {
    HROMADNE_MOD = false;
    VYBRANE_IDS.clear();
    document.querySelectorAll('.hromadne-check').forEach(el => { el.style.display = 'none'; el.checked = false; });
    document.querySelectorAll('[data-action="showDetailById"]').forEach(el => {
        el.removeEventListener('click', hromadneZabranDetailu, true);
    });
    const toolbar = document.getElementById('hromadneToolbar');
    if (toolbar) toolbar.style.display = 'none';
    const btn = document.getElementById('btnHromadneToggle');
    if (btn) btn.textContent = 'Výběr';
}

function hromadneZabranDetailu(e) {
    if (HROMADNE_MOD) e.stopImmediatePropagation();
}

function hromadneAktualizujToolbar() {
    let toolbar = document.getElementById('hromadneToolbar');
    if (!toolbar) {
        toolbar = document.createElement('div');
        toolbar.id = 'hromadneToolbar';
        toolbar.style.cssText = 'position:fixed;bottom:1.5rem;left:50%;transform:translateX(-50%);background:#000;color:#fff;padding:0.75rem 1.25rem;border-radius:6px;display:flex;align-items:center;gap:0.75rem;z-index:9000;box-shadow:0 4px 20px rgba(0,0,0,0.4);font-size:0.85rem;flex-wrap:wrap;';
        document.body.appendChild(toolbar);
    }
    if (VYBRANE_IDS.size === 0) {
        toolbar.style.display = 'none';
        return;
    }
    toolbar.style.display = 'flex';
    toolbar.innerHTML = `
        <span style="font-weight:600;">Vybráno: ${VYBRANE_IDS.size}</span>
        <button onclick="hromadneZmenStav('done')"   style="background:#555;color:#fff;border:none;padding:0.4rem 0.9rem;border-radius:4px;cursor:pointer;font-size:0.8rem;">Hotovo</button>
        <button onclick="hromadneZmenStav('open')"   style="background:#555;color:#fff;border:none;padding:0.4rem 0.9rem;border-radius:4px;cursor:pointer;font-size:0.8rem;">Domluvená</button>
        <button onclick="hromadneZmenStav('wait')"   style="background:#555;color:#fff;border:none;padding:0.4rem 0.9rem;border-radius:4px;cursor:pointer;font-size:0.8rem;">Čeká</button>
        <button onclick="hromadneVypnout()"          style="background:#333;color:#ccc;border:none;padding:0.4rem 0.9rem;border-radius:4px;cursor:pointer;font-size:0.8rem;">Zrušit</button>
    `;
}

async function hromadneZmenStav(novyStav) {
    if (VYBRANE_IDS.size === 0) return;
    const ids = Array.from(VYBRANE_IDS);
    const csrfToken = await (typeof fetchCsrfToken === 'function' ? fetchCsrfToken() : Promise.resolve(''));
    let uspesnych = 0;
    for (const id of ids) {
        try {
            const resp = await fetch('/app/controllers/save.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ id, stav: novyStav, csrf_token: csrfToken })
            });
            const data = await resp.json();
            if (data.status === 'success' || data.id) uspesnych++;
        } catch (e) {
            logger.warn('Hromadná změna stavu selhala pro ID ' + id + ':', e);
        }
    }
    wgsToast && wgsToast.success('Změněno ' + uspesnych + ' z ' + ids.length + ' zakázek.');
    hromadneVypnout();
    if (typeof loadOrders === 'function') loadOrders();
    else if (typeof renderOrders === 'function') renderOrders();
}

window.hromadneToggle  = hromadneToggle;
window.hromadneZapnout = hromadneZapnout;
window.hromadneVypnout = hromadneVypnout;
window.hromadneZmenStav = hromadneZmenStav;
