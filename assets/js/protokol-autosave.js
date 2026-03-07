/**
 * protokol-autosave.js
 * Autosave protokolu do localStorage každých 30 sekund
 * Závisí na: protokol.js (currentReklamaceId, sebraData, nactiData)
 */

// ==========================================
// AUTOSAVE PROTOKOLU DO LOCALSTORAGE (U6)
// ==========================================
const AUTOSAVE_INTERVAL_MS = 30000; // 30 sekund
let autosaveTimer = null;

function autosaveKlic() {
    return currentReklamaceId ? 'wgs_protokol_autosave_' + currentReklamaceId : null;
}

function autosaveUloz() {
    const klic = autosaveKlic();
    if (!klic) return;

    const data = {
        problemCz:  document.getElementById('problem-cz')?.value  || '',
        repairCz:   document.getElementById('repair-cz')?.value   || '',
        solved:     document.getElementById('solved')?.value      || '',
        dealer:     document.getElementById('dealer')?.value      || '',
        technician: document.getElementById('technician')?.value  || '',
        cas: new Date().toISOString()
    };

    try {
        localStorage.setItem(klic, JSON.stringify(data));
        const indikator = document.getElementById('protokolAutosaveIndikator');
        if (indikator) {
            const cas = new Date(data.cas).toLocaleTimeString('cs-CZ', { hour: '2-digit', minute: '2-digit' });
            indikator.textContent = 'Lokálně uloženo v ' + cas;
            indikator.style.opacity = '1';
            setTimeout(() => { indikator.style.opacity = '0.4'; }, 3000);
        }
    } catch (e) {
        logger.warn('Autosave protokolu selhalo:', e);
    }
}

function autosaveNacti() {
    const klic = autosaveKlic();
    if (!klic) return null;
    try {
        const ulozeno = localStorage.getItem(klic);
        if (!ulozeno) return null;
        const data = JSON.parse(ulozeno);
        return (data && data.cas) ? data : null;
    } catch (e) {
        return null;
    }
}

function autosaveVymaz() {
    const klic = autosaveKlic();
    if (klic) localStorage.removeItem(klic);
}

function autosaveObnovit() {
    const data = autosaveNacti();
    if (!data) return;
    if (data.problemCz  && document.getElementById('problem-cz'))  document.getElementById('problem-cz').value  = data.problemCz;
    if (data.repairCz   && document.getElementById('repair-cz'))   document.getElementById('repair-cz').value   = data.repairCz;
    if (data.solved     && document.getElementById('solved'))     document.getElementById('solved').value     = data.solved;
    if (data.dealer     && document.getElementById('dealer'))     document.getElementById('dealer').value     = data.dealer;
    if (data.technician && document.getElementById('technician')) document.getElementById('technician').value = data.technician;
    const banner = document.getElementById('autosaveObnovaBanner');
    if (banner) banner.remove();
    wgsToast && wgsToast.success('Formulář obnoven z lokálního uložení.');
}

function autosaveZahodit() {
    autosaveVymaz();
    const banner = document.getElementById('autosaveObnovaBanner');
    if (banner) banner.remove();
}

function autosaveNabidniObnovu() {
    const data = autostiNacti ? autosaveNacti() : autosaveNacti();
    if (!data) return;
    const cas = new Date(data.cas).toLocaleString('cs-CZ');

    // Vložit banner nad první input
    const banner = document.createElement('div');
    banner.id = 'autosaveObnovaBanner';
    banner.style.cssText = 'background:#f9f9f9;border:1px solid #ccc;padding:0.7rem 1rem;border-radius:4px;margin-bottom:1rem;font-size:0.85rem;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;';
    banner.innerHTML = `
        <span>Nalezeno automatické uložení z <strong>${cas}</strong>.</span>
        <span style="display:flex;gap:0.5rem;">
            <button onclick="autosaveObnovit()" style="background:#000;color:#fff;border:none;padding:0.3rem 0.8rem;border-radius:3px;cursor:pointer;font-size:0.8rem;">Obnovit</button>
            <button onclick="autosaveZahodit()" style="background:none;border:1px solid #ccc;padding:0.3rem 0.8rem;border-radius:3px;cursor:pointer;font-size:0.8rem;">Zahodit</button>
        </span>
    `;

    // Vložit na začátek formuláře (za případný autosaveIndikator)
    const indikator = document.getElementById('protokolAutosaveIndikator');
    const target = indikator ? indikator.parentNode : document.querySelector('form, .protokol-form, main, body');
    if (target) {
        target.insertBefore(banner, indikator ? indikator.nextSibling : target.firstChild);
    }
}

function autosaveSpustit() {
    if (autosaveTimer) clearInterval(autosaveTimer);
    autosaveTimer = setInterval(autosaveUloz, AUTOSAVE_INTERVAL_MS);
}

// Injektovat indikátor do stránky dynamicky
function autosaveVlozIndikator() {
    if (document.getElementById('protokolAutosaveIndikator')) return;
    const el = document.createElement('div');
    el.id = 'protokolAutosaveIndikator';
    el.style.cssText = 'font-size:0.75rem;color:#999;text-align:right;margin-bottom:0.5rem;opacity:0.4;transition:opacity 0.3s;';
    el.textContent = '';
    const form = document.querySelector('form, .protokol-form, main');
    if (form) form.prepend(el);
}

// Spustit autosave a nabídnout obnovu po načtení dat
window.addEventListener('DOMContentLoaded', () => {
    // Krátká prodleva aby se načetlo currentReklamaceId
    setTimeout(() => {
        if (!currentReklamaceId) return;
        autosaveVlozIndikator();
        autosaveNabidniObnovu();
        autosaveSpustit();
    }, 1500);
});

// Po úspěšném uložení do DB vymazat lokální zálohu
const _puvodniSaveProtokolToDB = saveProtokolToDB;
saveProtokolToDB = async function(...args) {
    const vysledek = await _puvodniSaveProtokolToDB.apply(this, args);
    autosaveVymaz();
    const indikator = document.getElementById('protokolAutosaveIndikator');
    if (indikator) indikator.textContent = '';
    return vysledek;
};
