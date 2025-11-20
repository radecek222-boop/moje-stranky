/**
 * WGS Statistiky - Kompletní systém statistik a reportů
 * Verze: 2.0
 * Datum: 2025-11-15
 */

// ==================================================
// MODAL SYSTÉM
// ==================================================

/**
 * Otevření modalu se statistikami
 */
function openStatsModal(type) {
    const overlay = document.getElementById('statsModalOverlay');
    const modal = overlay.querySelector('.cc-modal');
    const title = document.getElementById('statsModalTitle');
    const body = document.getElementById('statsModalBody');

    // Nastavit title
    const titles = {
        'salesperson': 'Statistiky prodejců',
        'technician': 'Statistiky techniků',
        'models': 'Nejporuchovější modely',
        'orders': 'Filtrované zakázky',
        'charts': 'Grafy a vizualizace'
    };

    title.textContent = titles[type] || 'Statistiky';

    // Načíst obsah podle typu
    loadStatsContent(type, body);

    // Zobrazit modal - přidat třídu active k overlay i modalu
    overlay.classList.add('active');
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

/**
 * Zavření modalu
 */
function closeStatsModal() {
    const overlay = document.getElementById('statsModalOverlay');
    const modal = overlay.querySelector('.cc-modal');

    // Odebrat třídu active z overlay i modalu
    overlay.classList.remove('active');
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
}

// ==================================================
// NAČÍTÁNÍ DAT Z API
// ==================================================

/**
 * Načtení obsahu pro modal
 */
async function loadStatsContent(type, body) {
    // Zobrazit loading
    body.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;">Načítání...</div>';

    try {
        const filterParams = getFilterParams();
        const response = await fetch(`api/statistiky_api.php?action=${type}&${filterParams}`);

        // Pokusit se přečíst JSON response i při chybě
        let result;
        try {
            result = await response.json();
        } catch (e) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Zkontrolovat jestli API vrátilo error
        if (!response.ok || result.status === 'error') {
            const errorMsg = result.message || `HTTP error! status: ${response.status}`;
            throw new Error(errorMsg);
        }

        if (result.status === 'success') {
            switch(type) {
                case 'salesperson':
                    renderSalespersonTable(body, result.data);
                    break;
                case 'technician':
                    renderTechnicianTable(body, result.data);
                    break;
                case 'models':
                    renderModelsTable(body, result.data);
                    break;
                case 'orders':
                    renderOrdersTable(body, result.data);
                    break;
                case 'charts':
                    renderCharts(body, result.data);
                    break;
                default:
                    body.innerHTML = '<div style="padding: 2rem; color: #d32f2f; text-align: center;">Neznámý typ statistiky</div>';
            }
        } else {
            body.innerHTML = '<div style="padding: 2rem; color: #d32f2f; text-align: center;">Chyba načítání dat: ' + escapeHtml(result.message) + '</div>';
        }
    } catch (error) {
        console.error('Chyba načítání statistik:', error);
        body.innerHTML = '<div style="padding: 2rem; color: #d32f2f; text-align: center;">Chyba načítání dat: ' + escapeHtml(error.message) + '</div>';
    }
}

/**
 * Načtení summary statistik
 */
async function loadSummaryStats() {
    try {
        const filterParams = getFilterParams();
        const response = await fetch(`api/statistiky_api.php?action=summary&${filterParams}`);

        // Pokusit se přečíst JSON response i při chybě
        let result;
        try {
            result = await response.json();
        } catch (e) {
            throw new Error(`HTTP error! status: ${response.status}`);
        }

        // Zkontrolovat jestli API vrátilo error
        if (!response.ok || result.status === 'error') {
            const errorMsg = result.message || `HTTP error! status: ${response.status}`;
            console.error('Chyba načítání summary statistik:', errorMsg);
            throw new Error(errorMsg);
        }

        if (result.status === 'success') {
            document.getElementById('total-orders').textContent = parseInt(result.data.total_orders) || 0;
            document.getElementById('total-revenue').textContent = (parseFloat(result.data.total_revenue) || 0).toFixed(2) + ' €';
            document.getElementById('avg-order').textContent = (parseFloat(result.data.avg_order) || 0).toFixed(2) + ' €';
            document.getElementById('active-techs').textContent = parseInt(result.data.active_techs) || 0;
        }
    } catch (error) {
        console.error('Chyba načítání summary statistik:', error);
    }
}

/**
 * Načtení seznamu prodejců pro filtr
 */
async function loadSalespersonFilter() {
    try {
        const response = await fetch('api/statistiky_api.php?action=list_salespersons');

        if (!response.ok) {
            console.warn('Nelze načíst seznam prodejců');
            return;
        }

        const result = await response.json();

        if (result.status === 'success' && result.data) {
            const select = document.getElementById('filter-salesperson');

            // Vymazat existující možnosti (kromě první "Všichni")
            while (select.options.length > 1) {
                select.remove(1);
            }

            // Přidat prodejce
            result.data.forEach(salesperson => {
                const option = document.createElement('option');
                option.value = salesperson;
                option.textContent = salesperson;
                select.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Chyba načítání prodejců:', error);
    }
}

// ==================================================
// RENDER FUNKCE PRO TABULKY
// ==================================================

/**
 * Renderování tabulky prodejců
 */
function renderSalespersonTable(body, data) {
    let rows = '';

    if (!Array.isArray(data) || data.length === 0) {
        rows = '<tr><td colspan="7" style="text-align: center; color: #999;">Žádná data k zobrazení</td></tr>';
    } else {
        data.forEach(row => {
            const prodejce = escapeHtml(row.prodejce || '-');
            rows += `
                <tr>
                    <td>${prodejce}</td>
                    <td>${parseInt(row.pocet_zakazek) || 0}</td>
                    <td>${parseFloat(row.celkova_castka || 0).toFixed(2)} €</td>
                    <td>${parseFloat(row.prumer_zakazka || 0).toFixed(2)} €</td>
                    <td>${parseInt(row.cz_count) || 0} / ${parseInt(row.sk_count) || 0}</td>
                    <td>${parseFloat(row.hotove_procento || 0).toFixed(1)}%</td>
                    <td>
                        <button
                            onclick="exportProdejcePDF('${prodejce}')"
                            style="padding: 0.4rem 0.8rem; background: #2D5016; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.75rem;"
                            title="Exportovat fakturaci do PDF">
                            📄 PDF
                        </button>
                    </td>
                </tr>
            `;
        });
    }

    body.innerHTML = `
        <div style="padding: 1rem;">
            <table class="cc-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Prodejce</th>
                        <th>Počet zakázek</th>
                        <th>Celková částka</th>
                        <th>Průměr/zakázka</th>
                        <th>CZ / SK</th>
                        <th>Hotové %</th>
                        <th>Export</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
    `;
}

/**
 * Renderování tabulky techniků
 */
function renderTechnicianTable(body, data) {
    let rows = '';

    if (!Array.isArray(data) || data.length === 0) {
        rows = '<tr><td colspan="9" style="text-align: center; color: #999;">Žádná data k zobrazení</td></tr>';
    } else {
        data.forEach(row => {
            const technik = escapeHtml(row.technik || '-');
            rows += `
                <tr>
                    <td>${technik}</td>
                    <td>${parseInt(row.pocet_zakazek) || 0}</td>
                    <td>${parseInt(row.pocet_dokonceno) || 0}</td>
                    <td>${parseFloat(row.celkova_castka_dokonceno || 0).toFixed(2)} €</td>
                    <td>${parseFloat(row.vydelek || 0).toFixed(2)} €</td>
                    <td>${parseFloat(row.prumer_zakazka || 0).toFixed(2)} €</td>
                    <td>${parseInt(row.cz_count) || 0} / ${parseInt(row.sk_count) || 0}</td>
                    <td>${parseFloat(row.uspesnost || 0).toFixed(1)}%</td>
                    <td>
                        <button
                            onclick="exportTechnikPDF('${technik}')"
                            style="padding: 0.4rem 0.8rem; background: #2D5016; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 0.75rem;"
                            title="Exportovat report do PDF">
                            📄 PDF
                        </button>
                    </td>
                </tr>
            `;
        });
    }

    body.innerHTML = `
        <div style="padding: 1rem;">
            <table class="cc-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Technik</th>
                        <th>Celkem zakázek</th>
                        <th>Dokončeno</th>
                        <th>Částka dokončeno</th>
                        <th>Výdělek (33%)</th>
                        <th>Průměr/zakázka</th>
                        <th>CZ / SK</th>
                        <th>Úspěšnost</th>
                        <th>Export</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
    `;
}

/**
 * Renderování tabulky modelů
 */
function renderModelsTable(body, data) {
    let rows = '';

    if (!Array.isArray(data) || data.length === 0) {
        rows = '<tr><td colspan="5" style="text-align: center; color: #999;">Žádná data k zobrazení</td></tr>';
    } else {
        data.forEach(row => {
            rows += `
                <tr>
                    <td>${escapeHtml(row.model || '-')}</td>
                    <td>${parseInt(row.pocet_reklamaci) || 0}</td>
                    <td>${parseFloat(row.podil_procent || 0).toFixed(2)}%</td>
                    <td>${parseFloat(row.prumerna_castka || 0).toFixed(2)} €</td>
                    <td>${parseFloat(row.celkova_castka || 0).toFixed(2)} €</td>
                </tr>
            `;
        });
    }

    body.innerHTML = `
        <div style="padding: 1rem;">
            <table class="cc-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Model / Výrobek</th>
                        <th>Počet reklamací</th>
                        <th>Podíl %</th>
                        <th>Průměrná částka</th>
                        <th>Celková částka</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
    `;
}

/**
 * Renderování tabulky zakázek
 */
function renderOrdersTable(body, data) {
    let rows = '';

    if (!Array.isArray(data) || data.length === 0) {
        rows = '<tr><td colspan="8" style="text-align: center; color: #999;">Žádná data k zobrazení</td></tr>';
    } else {
        data.forEach(row => {
            // Použít stav_text z API (již přeloženo) nebo fallback na mapping
            let stav = row.stav_text || row.stav || '-';
            if (!row.stav_text && row.stav) {
                const stavMapping = {
                    'wait': 'ČEKÁ',
                    'open': 'DOMLUVENÁ',
                    'done': 'HOTOVO'
                };
                stav = stavMapping[row.stav] || row.stav;
            }

            rows += `
                <tr>
                    <td>${escapeHtml(row.cislo || '')}</td>
                    <td>${escapeHtml(row.jmeno || '')}</td>
                    <td>${escapeHtml(row.prodejce || '-')}</td>
                    <td>${escapeHtml(row.technik || '-')}</td>
                    <td>${parseFloat(row.castka || 0).toFixed(2)} €</td>
                    <td>${escapeHtml(stav)}</td>
                    <td>${escapeHtml(row.zeme || 'CZ')}</td>
                    <td>${escapeHtml(row.datum || '')}</td>
                </tr>
            `;
        });
    }

    body.innerHTML = `
        <div style="padding: 1rem;">
            <table class="cc-table" style="width: 100%;">
                <thead>
                    <tr>
                        <th>Číslo</th>
                        <th>Zákazník</th>
                        <th>Prodejce</th>
                        <th>Technik</th>
                        <th>Částka</th>
                        <th>Stav</th>
                        <th>Země</th>
                        <th>Datum</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
    `;
}

/**
 * Renderování grafů
 */
function renderCharts(body, data) {
    let citiesHtml = '';
    let countriesHtml = '';
    let modelsHtml = '';

    // Města
    if (data.cities && Array.isArray(data.cities) && data.cities.length > 0) {
        citiesHtml = data.cities.map(c =>
            `<div style="padding: 0.5rem; border-bottom: 1px solid #eee;">
                ${escapeHtml(c.mesto)}: <strong>${parseInt(c.pocet) || 0}</strong>
            </div>`
        ).join('');
    } else {
        citiesHtml = '<div style="color: #999; padding: 1rem; text-align: center;">Žádná data</div>';
    }

    // Země
    if (data.countries && Array.isArray(data.countries) && data.countries.length > 0) {
        countriesHtml = data.countries.map(c => {
            const countryName = c.zeme === 'CZ' ? '🇨🇿 Česko' : c.zeme === 'SK' ? '🇸🇰 Slovensko' : c.zeme;
            return `<div style="padding: 0.5rem; border-bottom: 1px solid #eee;">
                ${escapeHtml(countryName)}: <strong>${parseInt(c.pocet) || 0}</strong>
            </div>`;
        }).join('');
    } else {
        countriesHtml = '<div style="color: #999; padding: 1rem; text-align: center;">Žádná data</div>';
    }

    // Modely
    if (data.models && Array.isArray(data.models) && data.models.length > 0) {
        modelsHtml = data.models.map(m =>
            `<div style="padding: 0.5rem; border-bottom: 1px solid #eee;">
                ${escapeHtml(m.model)}: <strong>${parseInt(m.pocet) || 0}</strong>
            </div>`
        ).join('');
    } else {
        modelsHtml = '<div style="color: #999; padding: 1rem; text-align: center;">Žádná data</div>';
    }

    body.innerHTML = `
        <div style="padding: 1rem;">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 1rem;">
                <div style="background: #f5f5f5; padding: 1rem; border-radius: 8px;">
                    <h3 style="font-size: 0.9rem; margin-bottom: 0.5rem;">Rozdělení podle měst</h3>
                    <div style="max-height: 300px; overflow-y: auto;">${citiesHtml}</div>
                </div>
                <div style="background: #f5f5f5; padding: 1rem; border-radius: 8px;">
                    <h3 style="font-size: 0.9rem; margin-bottom: 0.5rem;">Rozdělení podle zemí</h3>
                    <div style="max-height: 300px; overflow-y: auto;">${countriesHtml}</div>
                </div>
                <div style="background: #f5f5f5; padding: 1rem; border-radius: 8px;">
                    <h3 style="font-size: 0.9rem; margin-bottom: 0.5rem;">Nejporuchovější modely</h3>
                    <div style="max-height: 300px; overflow-y: auto;">${modelsHtml}</div>
                </div>
            </div>
        </div>
    `;
}

// ==================================================
// FILTRY
// ==================================================

/**
 * Změna měsíce - nastaví datum podle vybraného měsíce
 */
function handleMonthChange() {
    const monthSelect = document.getElementById('filter-month');
    const dateFrom = document.getElementById('filter-date-from');
    const dateTo = document.getElementById('filter-date-to');
    const value = monthSelect.value;

    if (value === 'all') {
        // Všechny - žádný datumový filtr
        dateFrom.value = '';
        dateTo.value = '';
        dateFrom.disabled = true;
        dateTo.disabled = true;
    } else if (value === 'current') {
        // Aktuální měsíc - od 1. dne do dneška
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth(), 1);
        dateFrom.value = firstDay.toISOString().split('T')[0];
        dateTo.value = now.toISOString().split('T')[0];
        dateFrom.disabled = true;
        dateTo.disabled = true;
    } else if (value === 'last') {
        // Minulý měsíc - od 1. dne do posledního dne
        const now = new Date();
        const firstDay = new Date(now.getFullYear(), now.getMonth() - 1, 1);
        const lastDay = new Date(now.getFullYear(), now.getMonth(), 0);
        dateFrom.value = firstDay.toISOString().split('T')[0];
        dateTo.value = lastDay.toISOString().split('T')[0];
        dateFrom.disabled = true;
        dateTo.disabled = true;
    } else if (value && value.match(/^\d{4}-\d{2}$/)) {
        // Konkrétní měsíc (např. 2024-11)
        const [year, month] = value.split('-').map(Number);
        const firstDay = new Date(year, month - 1, 1);
        const lastDay = new Date(year, month, 0);
        dateFrom.value = firstDay.toISOString().split('T')[0];
        dateTo.value = lastDay.toISOString().split('T')[0];
        dateFrom.disabled = true;
        dateTo.disabled = true;
    } else {
        // Vlastní rozsah
        dateFrom.disabled = false;
        dateTo.disabled = false;
    }

    // Automaticky aplikovat filtry
    applyFilters();
}

/**
 * Reset všech filtrů
 */
function resetFilters() {
    document.getElementById('filter-month').value = 'all';

    const salespersonSelect = document.getElementById('filter-salesperson');
    if (salespersonSelect) salespersonSelect.selectedIndex = 0;

    const countrySelect = document.getElementById('filter-country');
    if (countrySelect) countrySelect.selectedIndex = 0;

    const statusSelect = document.getElementById('filter-status');
    if (statusSelect) statusSelect.selectedIndex = 0;

    // Nastavit aktuální měsíc
    handleMonthChange();
}

/**
 * Aplikování filtrů
 */
function applyFilters() {
    console.log('Aplikuji filtry...');
    loadSummaryStats();
}

/**
 * Získání parametrů filtrů pro API
 */
function getFilterParams() {
    const params = new URLSearchParams();

    const salesperson = document.getElementById('filter-salesperson')?.value;
    const country = document.getElementById('filter-country')?.value;
    const status = document.getElementById('filter-status')?.value;
    const dateFrom = document.getElementById('filter-date-from')?.value;
    const dateTo = document.getElementById('filter-date-to')?.value;

    if (salesperson) params.append('salesperson', salesperson);
    if (country) params.append('country', country);
    if (status) params.append('status', status);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);

    return params.toString();
}

// ==================================================
// PDF EXPORT FUNKCE
// ==================================================

/**
 * Export fakturace prodejce do PDF
 */
async function exportProdejcePDF(prodejce) {
    try {
        console.log('Načítám data pro prodejce:', prodejce);

        // Získat filtry
        const filterParams = getFilterParams();
        const params = new URLSearchParams(filterParams);
        params.append('prodejce', prodejce);

        // Načíst detailní data z API
        const response = await fetch(`api/statistiky_api.php?action=export_salesperson_detail&${params.toString()}`);
        const result = await response.json();

        if (result.status !== 'success') {
            alert('Chyba načítání dat: ' + result.message);
            return;
        }

        const data = result.data;

        // Inicializovat jsPDF
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // Nastavení fontu
        doc.setFont('helvetica');

        // Titulek
        doc.setFontSize(18);
        doc.setTextColor(45, 80, 22); // #2D5016
        doc.text('Fakturace prodejce', 105, 20, { align: 'center' });

        // Informace o prodejci
        doc.setFontSize(14);
        doc.setTextColor(0, 0, 0);
        doc.text(`Prodejce: ${prodejce}`, 20, 35);

        // Období
        const datumOd = document.getElementById('filter-date-from')?.value || '-';
        const datumDo = document.getElementById('filter-date-to')?.value || '-';
        doc.setFontSize(10);
        doc.setTextColor(100, 100, 100);
        doc.text(`Období: ${datumOd} až ${datumDo}`, 20, 42);

        // Summary
        doc.setFontSize(11);
        doc.setTextColor(0, 0, 0);
        let yPos = 52;
        doc.text(`Celkem zakázek: ${data.summary.total_orders}`, 20, yPos);
        doc.text(`CZ: ${data.summary.cz_count} | SK: ${data.summary.sk_count}`, 120, yPos);
        yPos += 7;
        doc.text(`Celkový obrat: ${parseFloat(data.summary.total_revenue).toFixed(2)} €`, 20, yPos);

        // Čára oddělovače
        yPos += 5;
        doc.setDrawColor(200, 200, 200);
        doc.line(20, yPos, 190, yPos);

        // Tabulka zakázek
        yPos += 10;
        doc.setFontSize(9);
        doc.setTextColor(45, 80, 22);
        doc.setFont('helvetica', 'bold');

        // Hlavička tabulky
        doc.text('Číslo', 20, yPos);
        doc.text('Zákazník', 45, yPos);
        doc.text('Technik', 90, yPos);
        doc.text('Práce', 120, yPos);
        doc.text('Materiál', 140, yPos);
        doc.text('Celkem', 165, yPos);

        yPos += 2;
        doc.setDrawColor(45, 80, 22);
        doc.line(20, yPos, 190, yPos);

        // Data zakázek
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(0, 0, 0);

        data.orders.forEach((order, index) => {
            yPos += 6;

            // Kontrola přetečení stránky
            if (yPos > 270) {
                doc.addPage();
                yPos = 20;
            }

            const cislo = order.cislo_reklamace || '-';
            const jmeno = (order.jmeno || '').substring(0, 15);
            const technik = (order.technik || '-').substring(0, 15);
            const cenaPrace = parseFloat(order.cena_prace || 0).toFixed(2);
            const cenaMaterial = parseFloat(order.cena_material || 0).toFixed(2);
            const cenaCelkem = parseFloat(order.cena_celkem || 0).toFixed(2);

            doc.text(cislo, 20, yPos);
            doc.text(jmeno, 45, yPos);
            doc.text(technik, 90, yPos);
            doc.text(`${cenaPrace} €`, 120, yPos);
            doc.text(`${cenaMaterial} €`, 140, yPos);
            doc.text(`${cenaCelkem} €`, 165, yPos);
        });

        // Patička
        const pageCount = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pageCount; i++) {
            doc.setPage(i);
            doc.setFontSize(8);
            doc.setTextColor(150, 150, 150);
            doc.text(`Strana ${i} z ${pageCount}`, 105, 290, { align: 'center' });
            doc.text(`Vygenerováno: ${new Date().toLocaleDateString('cs-CZ')}`, 20, 290);
        }

        // Stáhnout PDF
        const nazevSouboru = `fakturace_${prodejce.replace(/\s+/g, '_')}_${datumOd}_${datumDo}.pdf`;
        doc.save(nazevSouboru);

        console.log('PDF exportováno:', nazevSouboru);

    } catch (error) {
        console.error('Chyba exportu PDF:', error);
        alert('Chyba exportu PDF: ' + error.message);
    }
}

/**
 * Export reportu technika do PDF
 */
async function exportTechnikPDF(technik) {
    try {
        console.log('Načítám data pro technika:', technik);

        // Získat filtry
        const filterParams = getFilterParams();
        const params = new URLSearchParams(filterParams);
        params.append('technik', technik);

        // Načíst detailní data z API
        const response = await fetch(`api/statistiky_api.php?action=export_technician_detail&${params.toString()}`);
        const result = await response.json();

        if (result.status !== 'success') {
            alert('Chyba načítání dat: ' + result.message);
            return;
        }

        const data = result.data;

        // Inicializovat jsPDF
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF();

        // Nastavení fontu
        doc.setFont('helvetica');

        // Titulek
        doc.setFontSize(18);
        doc.setTextColor(45, 80, 22); // #2D5016
        doc.text('Report technika', 105, 20, { align: 'center' });

        // Informace o technikovi
        doc.setFontSize(14);
        doc.setTextColor(0, 0, 0);
        doc.text(`Technik: ${technik}`, 20, 35);

        // Období
        const datumOd = document.getElementById('filter-date-from')?.value || '-';
        const datumDo = document.getElementById('filter-date-to')?.value || '-';
        doc.setFontSize(10);
        doc.setTextColor(100, 100, 100);
        doc.text(`Období: ${datumOd} až ${datumDo}`, 20, 42);

        // Summary
        doc.setFontSize(11);
        doc.setTextColor(0, 0, 0);
        let yPos = 52;
        doc.text(`Celkem zakázek: ${data.summary.total_orders}`, 20, yPos);
        doc.text(`Dokončeno: ${data.summary.completed_count}`, 100, yPos);
        yPos += 7;
        doc.text(`Celkový obrat: ${parseFloat(data.summary.total_revenue).toFixed(2)} €`, 20, yPos);

        // Výdělek technika (33%)
        doc.setFontSize(13);
        doc.setTextColor(45, 80, 22);
        doc.setFont('helvetica', 'bold');
        yPos += 10;
        doc.text(`Výdělek technika (33%): ${parseFloat(data.summary.vydelek_technika_33).toFixed(2)} €`, 20, yPos);

        // Čára oddělovače
        yPos += 5;
        doc.setDrawColor(200, 200, 200);
        doc.line(20, yPos, 190, yPos);

        // Tabulka zakázek
        yPos += 10;
        doc.setFontSize(9);
        doc.setTextColor(45, 80, 22);
        doc.setFont('helvetica', 'bold');

        // Hlavička tabulky
        doc.text('Číslo', 20, yPos);
        doc.text('Zákazník', 45, yPos);
        doc.text('Stav', 85, yPos);
        doc.text('Práce', 110, yPos);
        doc.text('Celkem', 135, yPos);
        doc.text('Výdělek', 160, yPos);

        yPos += 2;
        doc.setDrawColor(45, 80, 22);
        doc.line(20, yPos, 190, yPos);

        // Data zakázek
        doc.setFont('helvetica', 'normal');
        doc.setTextColor(0, 0, 0);

        data.orders.forEach((order, index) => {
            yPos += 6;

            // Kontrola přetečení stránky
            if (yPos > 270) {
                doc.addPage();
                yPos = 20;
            }

            const cislo = order.cislo_reklamace || '-';
            const jmeno = (order.jmeno || '').substring(0, 12);
            const stav = order.stav_text || '-';
            const cenaPrace = parseFloat(order.cena_prace || 0).toFixed(2);
            const cenaCelkem = parseFloat(order.cena_celkem || 0).toFixed(2);
            const vydelekTechnika = parseFloat(order.vydelek_technika_33 || 0).toFixed(2);

            // Zvýraznit dokončené zakázky
            if (order.stav === 'done') {
                doc.setFont('helvetica', 'bold');
                doc.setTextColor(45, 80, 22);
            }

            doc.text(cislo, 20, yPos);
            doc.text(jmeno, 45, yPos);
            doc.text(stav, 85, yPos);
            doc.text(`${cenaPrace} €`, 110, yPos);
            doc.text(`${cenaCelkem} €`, 135, yPos);
            doc.text(`${vydelekTechnika} €`, 160, yPos);

            // Reset font pro další řádek
            doc.setFont('helvetica', 'normal');
            doc.setTextColor(0, 0, 0);
        });

        // Patička
        const pageCount = doc.internal.getNumberOfPages();
        for (let i = 1; i <= pageCount; i++) {
            doc.setPage(i);
            doc.setFontSize(8);
            doc.setTextColor(150, 150, 150);
            doc.text(`Strana ${i} z ${pageCount}`, 105, 290, { align: 'center' });
            doc.text(`Vygenerováno: ${new Date().toLocaleDateString('cs-CZ')}`, 20, 290);
        }

        // Stáhnout PDF
        const nazevSouboru = `report_technik_${technik.replace(/\s+/g, '_')}_${datumOd}_${datumDo}.pdf`;
        doc.save(nazevSouboru);

        console.log('PDF exportováno:', nazevSouboru);

    } catch (error) {
        console.error('Chyba exportu PDF:', error);
        alert('Chyba exportu PDF: ' + error.message);
    }
}

// ==================================================
// UTILITY FUNKCE
// ==================================================

/**
 * Escape HTML pro bezpečné zobrazení
 */
function escapeHtml(text) {
    if (text === null || text === undefined) {
        return '';
    }

    const div = document.createElement('div');
    div.textContent = String(text);
    return div.innerHTML;
}

// ==================================================
// EVENT LISTENERS
// ==================================================

// ESC key zavře modal
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeStatsModal();
    }
});

// Inicializace při načtení stránky
document.addEventListener('DOMContentLoaded', () => {
    console.log('WGS Statistiky - Inicializace...');

    // Nastavit výchozí datum (aktuální měsíc)
    handleMonthChange();

    // Načíst seznam prodejců do filtru
    loadSalespersonFilter();

    console.log('WGS Statistiky - Inicializace dokončena');
});
