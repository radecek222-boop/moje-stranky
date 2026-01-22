/**
 * WGS Statistiky - NOVÁ VERZE 2.0
 * Reporty a vyúčtování
 * Datum: 2025-11-22
 */

// Globální proměnné
let aktualniStranka = 1;
let vybraneProdejci = [];
let vybraneTechnici = [];
let vybraneZeme = ['cz', 'sk']; // Defaultně obě země

/**
 * Inicializace při načtení stránky
 */
document.addEventListener('DOMContentLoaded', () => {
    // Inicializovat multi-select dropdowny
    inicializujMultiselect();

    // Načíst data
    nactiProdejce();
    nactiTechniky();
    nactiSummary();
    nactiZakazky();
    nactiCharty();

    // Aplikovat filtry při změně roku nebo měsíce
    const filterYear = document.getElementById('filter-year');
    const filterMonth = document.getElementById('filter-month');

    if (filterYear) {
        filterYear.addEventListener('change', () => {
            aktualniStranka = 1;
            aplikovatFiltry();
        });
    }

    if (filterMonth) {
        filterMonth.addEventListener('change', () => {
            aktualniStranka = 1;
            aplikovatFiltry();
        });
    }

    // ========================================
    // PŘÍMÉ EVENT LISTENERY NA TLAČÍTKA
    // ========================================
    const btnReset = document.querySelector('[data-action="resetovitFiltry"]');
    const btnExport = document.querySelector('[data-action="exportovatPDF"]');
    const btnPrev = document.querySelector('[data-action="predchoziStranka"]');
    const btnNext = document.querySelector('[data-action="dalsiStranka"]');

    if (btnReset) {
        btnReset.addEventListener('click', (e) => {
            e.preventDefault();
            resetovitFiltry();
        });
    }

    if (btnExport) {
        btnExport.addEventListener('click', (e) => {
            e.preventDefault();
            exportovatPDF();
        });
    }

    if (btnPrev) {
        btnPrev.addEventListener('click', (e) => {
            e.preventDefault();
            predchoziStranka();
        });
    }

    if (btnNext) {
        btnNext.addEventListener('click', (e) => {
            e.preventDefault();
            dalsiStranka();
        });
    }
});

/**
 * Inicializace multi-select dropdownů
 */
function inicializujMultiselect() {
    // Prodejci
    const prodejciTrigger = document.getElementById('prodejci-trigger');
    if (prodejciTrigger) {
        prodejciTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleDropdown('prodejci');
        });
    }

    // Technici
    const techniciTrigger = document.getElementById('technici-trigger');
    if (techniciTrigger) {
        techniciTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleDropdown('technici');
        });
    }

    // Země
    const zemeTrigger = document.getElementById('zeme-trigger');
    if (zemeTrigger) {
        zemeTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            toggleDropdown('zeme');
        });
    }

    // Zavřít dropdowny při kliknutí mimo
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.filter-multiselect')) {
            document.querySelectorAll('.multiselect-dropdown').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });

    // Checkbox Zobrazit mimozáruční servisy - listener
    const mimozarucniCheckbox = document.getElementById('zobrazitMimozarucni');
    if (mimozarucniCheckbox) {
        mimozarucniCheckbox.addEventListener('change', () => {
            aktualniStranka = 1;
            aplikovatFiltry();
        });
    }

    // Checkbox Zobrazit pouze dokončené - listener
    const pouzeDokonceneCheckbox = document.getElementById('zobrazitPouzeDokoncene');
    if (pouzeDokonceneCheckbox) {
        pouzeDokonceneCheckbox.addEventListener('change', () => {
            aktualniStranka = 1;
            aplikovatFiltry();
        });
    }

    // Země checkboxy - listener
    document.querySelectorAll('#zeme-dropdown input[type="checkbox"]').forEach(checkbox => {
        checkbox.addEventListener('change', () => {
            updateVyberZeme();
        });
    });
}

/**
 * Toggle dropdown
 */
function toggleDropdown(typ) {
    const dropdown = document.getElementById(`${typ}-dropdown`);
    const trigger = document.getElementById(`${typ}-trigger`);
    const jineDropdowny = document.querySelectorAll('.multiselect-dropdown');
    const jineTrigery = document.querySelectorAll('.multiselect-trigger');

    // Zavřít ostatní a resetovat aria-expanded
    jineDropdowny.forEach((d, index) => {
        if (d !== dropdown) {
            d.classList.remove('active');
        }
    });
    jineTrigery.forEach(t => {
        if (t !== trigger) {
            t.setAttribute('aria-expanded', 'false');
        }
    });

    // Toggle aktuální
    const jeOtevreno = dropdown.classList.toggle('active');
    trigger.setAttribute('aria-expanded', jeOtevreno ? 'true' : 'false');
}

/**
 * Načíst prodejce do multi-selectu
 */
async function nactiProdejce() {
    try {
        const response = await fetch('/api/statistiky_api.php?action=load_prodejci');
        const result = await response.json();

        if (result.status === 'success') {
            const dropdown = document.getElementById('prodejci-dropdown');
            dropdown.innerHTML = '';

            result.data.forEach(prodejce => {
                const option = document.createElement('div');
                option.className = 'multiselect-option';
                option.innerHTML = `
                    <input type="checkbox" id="prodejce-${prodejce.id}" value="${prodejce.id}">
                    <label for="prodejce-${prodejce.id}">${prodejce.name}</label>
                `;

                // Listener na checkbox
                option.querySelector('input').addEventListener('change', () => {
                    updateVyberProdejci();
                });

                dropdown.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Chyba načítání prodejců:', error);
    }
}

/**
 * Načíst techniky do multi-selectu
 */
async function nactiTechniky() {
    try {
        const response = await fetch('/api/statistiky_api.php?action=load_technici');
        const result = await response.json();

        if (result.status === 'success') {
            const dropdown = document.getElementById('technici-dropdown');
            dropdown.innerHTML = '';

            result.data.forEach(technik => {
                const option = document.createElement('div');
                option.className = 'multiselect-option';
                option.innerHTML = `
                    <input type="checkbox" id="technik-${technik.id}" value="${technik.id}">
                    <label for="technik-${technik.id}">${technik.name}</label>
                `;

                // Listener na checkbox
                option.querySelector('input').addEventListener('change', () => {
                    updateVyberTechnici();
                });

                dropdown.appendChild(option);
            });
        }
    } catch (error) {
        console.error('Chyba načítání techniků:', error);
    }
}

/**
 * Update vybraných prodejců
 */
function updateVyberProdejci() {
    const checkboxy = document.querySelectorAll('#prodejci-dropdown input[type="checkbox"]:checked');
    vybraneProdejci = Array.from(checkboxy).map(cb => cb.value);

    const label = document.getElementById('prodejci-label');
    if (vybraneProdejci.length === 0) {
        label.textContent = 'Všichni';
    } else if (vybraneProdejci.length === 1) {
        const checkbox = document.querySelector(`#prodejci-dropdown input[value="${vybraneProdejci[0]}"]`);
        const labelElement = checkbox.nextElementSibling;
        label.textContent = labelElement.textContent;
    } else {
        label.textContent = `Vybráno (${vybraneProdejci.length})`;
    }

    // Automaticky načíst data při změně filtru
    aktualniStranka = 1;
    aplikovatFiltry();
}

/**
 * Update vybraných techniků
 */
function updateVyberTechnici() {
    const checkboxy = document.querySelectorAll('#technici-dropdown input[type="checkbox"]:checked');
    vybraneTechnici = Array.from(checkboxy).map(cb => cb.value);

    const label = document.getElementById('technici-label');
    if (vybraneTechnici.length === 0) {
        label.textContent = 'Všichni';
    } else if (vybraneTechnici.length === 1) {
        const checkbox = document.querySelector(`#technici-dropdown input[value="${vybraneTechnici[0]}"]`);
        const labelElement = checkbox.nextElementSibling;
        label.textContent = labelElement.textContent;
    } else {
        label.textContent = `Vybráno (${vybraneTechnici.length})`;
    }

    // Automaticky načíst data při změně filtru
    aktualniStranka = 1;
    aplikovatFiltry();
}

/**
 * Update vybraných zemí
 */
function updateVyberZeme() {
    const checkboxy = document.querySelectorAll('#zeme-dropdown input[type="checkbox"]:checked');
    vybraneZeme = Array.from(checkboxy).map(cb => cb.value);

    const label = document.getElementById('zeme-label');
    if (vybraneZeme.length === 0) {
        label.textContent = 'Žádná';
    } else if (vybraneZeme.length === 2) {
        label.textContent = 'Všechny';
    } else {
        const checkbox = document.querySelector(`#zeme-dropdown input[value="${vybraneZeme[0]}"]`);
        const labelElement = checkbox.nextElementSibling;
        label.textContent = labelElement.textContent;
    }

    // Automaticky načíst data při změně filtru
    aktualniStranka = 1;
    aplikovatFiltry();
}

/**
 * Získat URL parametry filtrů
 */
function getFilterParams() {
    const params = new URLSearchParams();

    const rok = document.getElementById('filter-year').value;
    const mesic = document.getElementById('filter-month').value;

    if (rok) params.append('rok', rok);
    if (mesic) params.append('mesic', mesic);

    // Multi-select prodejci
    vybraneProdejci.forEach(p => params.append('prodejci[]', p));

    // Multi-select technici
    vybraneTechnici.forEach(t => params.append('technici[]', t));

    // Multi-select země
    vybraneZeme.forEach(z => params.append('zeme[]', z));

    // Checkbox mimozáruční servisy
    const mimozarucniCheckbox = document.getElementById('zobrazitMimozarucni');
    if (mimozarucniCheckbox && mimozarucniCheckbox.checked) {
        params.append('zobrazit_mimozarucni', '1');
    }

    // Checkbox pouze dokončené
    const pouzeDokonceneCheckbox = document.getElementById('zobrazitPouzeDokoncene');
    if (pouzeDokonceneCheckbox && pouzeDokonceneCheckbox.checked) {
        params.append('pouze_dokoncene', '1');
    }

    return params.toString();
}

/**
 * Načíst summary statistiky (4 karty)
 */
async function nactiSummary() {
    try {
        const filterParams = getFilterParams();
        const response = await fetch(`/api/statistiky_api.php?action=summary&${filterParams}`);
        const result = await response.json();

        if (result.status === 'success') {
            document.getElementById('total-all').textContent = result.data.total_all;
            document.getElementById('total-month').textContent = result.data.total_month;
            document.getElementById('revenue-all').textContent = result.data.revenue_all.toFixed(2) + ' €';
            document.getElementById('revenue-month').textContent = result.data.revenue_month.toFixed(2) + ' €';
        }
    } catch (error) {
        console.error('Chyba načítání summary:', error);
    }
}

/**
 * Načíst zakázky podle filtrů
 */
async function nactiZakazky() {
    try {
        const container = document.getElementById('table-container');
        container.innerHTML = '<div class="loading">Načítání zakázek...</div>';

        const filterParams = getFilterParams();
        const response = await fetch(`/api/statistiky_api.php?action=get_zakazky&${filterParams}&stranka=${aktualniStranka}`);
        const result = await response.json();

        if (result.status === 'success') {
            renderTabulka(result.data);
            updateStrankovani(result.data);
        } else {
            container.innerHTML = '<div class="empty-state">Chyba načítání dat</div>';
        }
    } catch (error) {
        console.error('Chyba načítání zakázek:', error);
        document.getElementById('table-container').innerHTML = '<div class="empty-state">Chyba načítání dat</div>';
    }
}

/**
 * Renderovat tabulku zakázek
 */
function renderTabulka(data) {
    const container = document.getElementById('table-container');
    const tableCount = document.getElementById('table-count');

    if (!data.zakazky || data.zakazky.length === 0) {
        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon"></div>Žádné zakázky podle filtrů</div>';
        tableCount.textContent = '0 zakázek';
        return;
    }

    tableCount.textContent = `${data.total_count} zakázek`;

    let rows = '';
    data.zakazky.forEach(z => {
        rows += `
            <tr>
                <td>${z.cislo_reklamace || '-'}</td>
                <td>${z.jmeno_zakaznika || '-'}</td>
                <td>${z.adresa || '-'}</td>
                <td>${z.model || '-'}</td>
                <td>${z.technik}</td>
                <td>${z.prodejce}</td>
                <td>${parseFloat(z.castka_celkem).toFixed(2)} €</td>
                <td>${parseFloat(z.vydelek_technika).toFixed(2)} €</td>
                <td>${z.zeme}</td>
                <td>${z.datum}</td>
                <td>
                    <button data-action="upravitZakazku" data-zakazka-id="${z.id}" data-reklamace-id="${z.cislo_reklamace}" style="
                        padding: 6px 12px; font-size: 0.85rem; font-weight: 600;
                        background: #555; color: white; border: none;
                        border-radius: 4px; cursor: pointer;
                    ">Upravit</button>
                </td>
            </tr>
        `;
    });

    container.innerHTML = `
        <table class="stats-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Jméno</th>
                    <th>Adresa</th>
                    <th>Model</th>
                    <th>Technik</th>
                    <th>Prodejce</th>
                    <th>Částka</th>
                    <th>Výdělek</th>
                    <th>Země</th>
                    <th>Datum</th>
                    <th>Akce</th>
                </tr>
            </thead>
            <tbody>
                ${rows}
            </tbody>
        </table>
    `;
}

/**
 * Update stránkování
 */
function updateStrankovani(data) {
    const pagination = document.getElementById('pagination');
    const pageInfo = document.getElementById('page-info');
    const prevBtn = document.getElementById('prev-page');
    const nextBtn = document.getElementById('next-page');

    if (data.celkem_stranek <= 1) {
        pagination.classList.add('hidden');
        return;
    }

    pagination.classList.remove('hidden');
    pageInfo.textContent = `Strana ${data.stranka} z ${data.celkem_stranek}`;

    prevBtn.disabled = data.stranka === 1;
    nextBtn.disabled = data.stranka >= data.celkem_stranek;
}

/**
 * Předchozí stránka
 */
function predchoziStranka() {
    if (aktualniStranka > 1) {
        aktualniStranka--;
        nactiZakazky();
    }
}

/**
 * Další stránka
 */
function dalsiStranka() {
    aktualniStranka++;
    nactiZakazky();
}

/**
 * Načíst grafy
 */
async function nactiCharty() {
    try {
        const filterParams = getFilterParams();
        const response = await fetch(`/api/statistiky_api.php?action=get_charts&${filterParams}`);
        const result = await response.json();

        if (result.status === 'success') {
            renderCharty(result.data);
        }
    } catch (error) {
        console.error('Chyba načítání grafů:', error);
    }
}

/**
 * Renderovat grafy
 */
function renderCharty(data) {
    // 1. Nejporuchovější modely
    const modelsContainer = document.getElementById('chart-models');
    if (data.modely && data.modely.length > 0) {
        let html = '';
        data.modely.forEach(m => {
            html += `
                <div class="chart-item">
                    <div class="chart-item-label">${m.model}</div>
                    <div class="chart-item-value">${m.pocet} ks</div>
                </div>
            `;
        });
        modelsContainer.innerHTML = html;
    } else {
        modelsContainer.innerHTML = '<div class="empty-state">Žádná data</div>';
    }

    // 2. Lokality (města)
    const citiesContainer = document.getElementById('chart-cities');
    if (data.mesta && data.mesta.length > 0) {
        let html = '';
        data.mesta.forEach(m => {
            html += `
                <div class="chart-item">
                    <div class="chart-item-label">${m.mesto}</div>
                    <div class="chart-item-value">${m.pocet} ks</div>
                </div>
            `;
        });
        citiesContainer.innerHTML = html;
    } else {
        citiesContainer.innerHTML = '<div class="empty-state">Žádná data</div>';
    }

    // 3. Statistiky prodejců
    const salespersonsContainer = document.getElementById('chart-salespersons');
    if (data.prodejci && data.prodejci.length > 0) {
        let html = '';
        data.prodejci.forEach(p => {
            html += `
                <div class="chart-item">
                    <div class="chart-item-label">${p.prodejce} (${p.pocet} ks)</div>
                    <div class="chart-item-value">${parseFloat(p.celkem).toFixed(2)} €</div>
                </div>
            `;
        });
        salespersonsContainer.innerHTML = html;
    } else {
        salespersonsContainer.innerHTML = '<div class="empty-state">Žádná data</div>';
    }

    // 4a. Statistiky techniků - REKLAMACE
    const techniciansReklamaceContainer = document.getElementById('chart-technicians-reklamace');
    if (techniciansReklamaceContainer) {
        if (data.techniciReklamace && data.techniciReklamace.length > 0) {
            let html = '';
            data.techniciReklamace.forEach(t => {
                html += `
                    <div class="chart-item">
                        <div class="chart-item-label">${t.technik} (${t.pocet} ks)</div>
                        <div class="chart-item-value">${parseFloat(t.vydelek).toFixed(2)} €</div>
                    </div>
                `;
            });
            techniciansReklamaceContainer.innerHTML = html;
        } else {
            techniciansReklamaceContainer.innerHTML = '<div class="empty-state">Žádná data</div>';
        }
    }

    // 4b. Statistiky techniků - POZ
    const techniciansPozContainer = document.getElementById('chart-technicians-poz');
    if (techniciansPozContainer) {
        if (data.techniciPoz && data.techniciPoz.length > 0) {
            let html = '';
            data.techniciPoz.forEach(t => {
                html += `
                    <div class="chart-item">
                        <div class="chart-item-label">${t.technik} (${t.pocet} ks)</div>
                        <div class="chart-item-value">${parseFloat(t.vydelek).toFixed(2)} €</div>
                    </div>
                `;
            });
            techniciansPozContainer.innerHTML = html;
        } else {
            techniciansPozContainer.innerHTML = '<div class="empty-state">Žádná data</div>';
        }
    }
}

/**
 * Aplikovat filtry
 */
function aplikovatFiltry() {
    aktualniStranka = 1;
    nactiSummary();
    nactiZakazky();
    nactiCharty();
}

/**
 * Resetovat filtry
 */
function resetovitFiltry() {
    // Reset year, month - na "Všechny" (prázdná hodnota)
    document.getElementById('filter-year').value = '';
    document.getElementById('filter-month').value = '';

    // Reset prodejci
    document.querySelectorAll('#prodejci-dropdown input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
    vybraneProdejci = [];
    document.getElementById('prodejci-label').textContent = 'Všichni';

    // Reset technici
    document.querySelectorAll('#technici-dropdown input[type="checkbox"]').forEach(cb => {
        cb.checked = false;
    });
    vybraneTechnici = [];
    document.getElementById('technici-label').textContent = 'Všichni';

    // Reset země
    document.querySelectorAll('#zeme-dropdown input[type="checkbox"]').forEach(cb => {
        cb.checked = true;
    });
    vybraneZeme = ['cz', 'sk'];
    document.getElementById('zeme-label').textContent = 'Všechny';

    // Reset checkboxu mimozáruční servisy na checked
    const mimozarucniCheckbox = document.getElementById('zobrazitMimozarucni');
    if (mimozarucniCheckbox) {
        mimozarucniCheckbox.checked = true;
    }

    aktualniStranka = 1;
    aplikovatFiltry();
}

/**
 * Exportovat do PDF - použití html2canvas pro správné UTF-8
 */
async function exportovatPDF() {
    try {
        // Načíst VŠECHNA data (bez limitu)
        const filterParams = getFilterParams();
        const response = await fetch(`/api/statistiky_api.php?action=get_zakazky&${filterParams}&pro_export=1`);
        const result = await response.json();

        if (result.status !== 'success' || !result.data.zakazky) {
            wgsToast.error('Chyba při načítání dat pro export');
            return;
        }

        const zakazky = result.data.zakazky;

        if (zakazky.length === 0) {
            wgsToast.warning('Žádná data k exportu podle filtrů');
            return;
        }

        // Připravit informace o filtru
        const rok = document.getElementById('filter-year').value || 'Všechny';
        const mesicValue = document.getElementById('filter-month').value;
        const mesicNazvy = ['', 'Leden', 'Únor', 'Březen', 'Duben', 'Květen', 'Červen',
                           'Červenec', 'Srpen', 'Září', 'Říjen', 'Listopad', 'Prosinec'];
        const mesic = mesicValue ? mesicNazvy[parseInt(mesicValue)] : 'Všechny';
        const datum = new Date().toLocaleDateString('cs-CZ');

        // Kontrola checkboxu pro zobrazení odměny technika
        const zobrazitOdmenu = document.getElementById('zobrazitOdmenu').checked;

        // Spočítat součty
        const celkemCastka = zakazky.reduce((sum, z) => sum + parseFloat(z.castka_celkem), 0);
        const celkemVydelek = zakazky.reduce((sum, z) => sum + parseFloat(z.vydelek_technika), 0);

        // Vytvořit HTML pro PDF (skrytý div) - bez fixní výšky, aby se přizpůsobil obsahu
        const pdfContainer = document.createElement('div');
        pdfContainer.style.cssText = 'position: absolute; left: -9999px; width: 1200px; background: white; padding: 30px; font-family: Poppins, Arial, sans-serif;';

        // Souhrn VŽDY na konci za tabulkou (ne absolutní pozicování)
        pdfContainer.innerHTML = `
            <div style="margin-bottom: 15px;">
                <h1 style="color: #333; font-size: 18px; margin: 0 0 8px 0; font-weight: 700;">Statistiky a reporty - WGS</h1>
                <p style="color: #666; font-size: 11px; margin: 0;">Rok: ${rok} | Měsíc: ${mesic} | Celkem: ${zakazky.length} zakázek</p>
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 8px;">
                <thead>
                    <tr style="background: #f0f0f0; color: #333;">
                        <th style="padding: 4px; text-align: left; border: 1px solid #999; font-size: 7px;">ID</th>
                        <th style="padding: 4px; text-align: left; border: 1px solid #999; font-size: 7px;">Jméno</th>
                        <th style="padding: 4px; text-align: left; border: 1px solid #999; font-size: 7px;">Adresa</th>
                        <th style="padding: 4px; text-align: left; border: 1px solid #999; font-size: 7px;">Model</th>
                        <th style="padding: 4px; text-align: left; border: 1px solid #999; font-size: 7px;">Technik</th>
                        <th style="padding: 4px; text-align: left; border: 1px solid #999; font-size: 7px;">Prodejce</th>
                        <th style="padding: 4px; text-align: right; border: 1px solid #999; font-size: 7px;">Částka</th>
                        ${zobrazitOdmenu ? '<th style="padding: 4px; text-align: right; border: 1px solid #999; font-size: 7px;">Výdělek</th>' : ''}
                        <th style="padding: 4px; text-align: center; border: 1px solid #999; font-size: 7px;">Země</th>
                        <th style="padding: 4px; text-align: center; border: 1px solid #999; font-size: 7px;">Datum</th>
                    </tr>
                </thead>
                <tbody>
                    ${zakazky.map((z, idx) => `
                        <tr style="background: ${idx % 2 === 0 ? '#fff' : '#f5f5f5'};">
                            <td style="padding: 3px; border: 1px solid #ddd; font-size: 8px;">${z.cislo_reklamace || '-'}</td>
                            <td style="padding: 3px; border: 1px solid #ddd; font-size: 8px;">${z.jmeno_zakaznika || '-'}</td>
                            <td style="padding: 3px; border: 1px solid #ddd; font-size: 8px;">${z.adresa || '-'}</td>
                            <td style="padding: 3px; border: 1px solid #ddd; font-size: 8px;">${z.model || '-'}</td>
                            <td style="padding: 3px; border: 1px solid #ddd; font-size: 8px;">${z.technik || '-'}</td>
                            <td style="padding: 3px; border: 1px solid #ddd; font-size: 8px;">${z.prodejce || '-'}</td>
                            <td style="padding: 3px; border: 1px solid #ddd; text-align: right; font-size: 8px;">${parseFloat(z.castka_celkem).toFixed(2)} €</td>
                            ${zobrazitOdmenu ? `<td style="padding: 3px; border: 1px solid #ddd; text-align: right; font-size: 8px;">${parseFloat(z.vydelek_technika).toFixed(2)} €</td>` : ''}
                            <td style="padding: 3px; border: 1px solid #ddd; text-align: center; font-size: 8px;">${z.zeme || '-'}</td>
                            <td style="padding: 3px; border: 1px solid #ddd; text-align: center; font-size: 8px;">${z.datum || '-'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>

            <!-- SOUHRN - vždy za tabulkou s mezerou, nikdy se nepřepíše -->
            <div style="margin-top: 40px; padding-top: 20px; border-top: 2px solid #333; text-align: right;">
                <div style="background: #f0f0f0; border: 1px solid #999; padding: 8px 16px; margin-bottom: 8px; font-size: 14px; display: inline-block;">
                    Počet zakázek celkem: <span style="color: #333; font-weight: bold;">${zakazky.length} ks</span>
                </div><br>
                <div style="background: #f0f0f0; border: 1px solid #999; padding: 8px 16px; margin-bottom: 8px; font-size: 14px; display: inline-block;">
                    Celkem za zakázky k fakturaci: <span style="color: #333; font-weight: bold;">${celkemCastka.toFixed(2)} €</span>
                </div><br>
                ${zobrazitOdmenu ? `
                    <div style="background: #f0f0f0; border: 1px solid #999; padding: 8px 16px; font-size: 14px; display: inline-block;">
                        Výdělek celkem technik: <span style="color: #333; font-weight: bold;">${celkemVydelek.toFixed(2)} €</span>
                    </div>
                ` : ''}
            </div>

            <!-- PATIČKA - vždy na úplném konci -->
            <div style="margin-top: 30px; padding-top: 15px; border-top: 1px solid #ddd;">
                <div style="font-size: 10px; color: #999; margin-bottom: 5px;">
                    Vygenerováno: ${datum}
                </div>
                <div style="font-size: 9px; color: #aaa; font-style: italic;">
                    Report byl vytvořen pomocí systému WGS (White Glove Service) – Nástroj pro správu servisních zakázek Natuzzi
                </div>
            </div>
        `;

        document.body.appendChild(pdfContainer);

        // Renderovat pomocí html2canvas
        const canvas = await html2canvas(pdfContainer, {
            scale: 2,
            backgroundColor: '#fff',
            useCORS: true,
            logging: false
        });

        // Odstranit z DOMu
        document.body.removeChild(pdfContainer);

        // Vytvořit PDF
        const { jsPDF } = window.jspdf;
        const doc = new jsPDF('l', 'mm', 'a4');

        const imgData = canvas.toDataURL('image/jpeg', 0.95);
        const pageWidth = doc.internal.pageSize.width;
        const pageHeight = doc.internal.pageSize.height;
        const margin = 10;

        const availableWidth = pageWidth - (margin * 2);
        const availableHeight = pageHeight - (margin * 2);

        // Vypočítat rozměry obrázku
        const canvasRatio = canvas.height / canvas.width;
        let imgWidth = availableWidth;
        let imgHeight = imgWidth * canvasRatio;

        // Spočítat celkový počet stránek
        const pageHeightInPx = canvas.width * (availableHeight / imgWidth);
        const celkovyPocetStranek = Math.ceil(canvas.height / pageHeightInPx);

        // Pokud se vejde na jednu stránku
        if (imgHeight <= availableHeight) {
            doc.addImage(imgData, 'JPEG', margin, margin, imgWidth, imgHeight);

            // Přidat číslo stránky nahoře vlevo
            doc.setFontSize(9);
            doc.setTextColor(100, 100, 100);
            doc.text('1/1', margin, 8);
        } else {
            // Rozdělení na více stránek
            let yPosition = 0;
            let cisloStranky = 1;

            while (yPosition < canvas.height) {
                if (yPosition > 0) {
                    doc.addPage();
                }

                const sliceCanvas = document.createElement('canvas');
                sliceCanvas.width = canvas.width;
                sliceCanvas.height = Math.min(pageHeightInPx, canvas.height - yPosition);

                const ctx = sliceCanvas.getContext('2d');
                ctx.drawImage(
                    canvas,
                    0, yPosition,  // source x, y
                    canvas.width, sliceCanvas.height,  // source width, height
                    0, 0,  // dest x, y
                    canvas.width, sliceCanvas.height  // dest width, height
                );

                const sliceData = sliceCanvas.toDataURL('image/jpeg', 0.95);
                const sliceHeight = (sliceCanvas.height / canvas.width) * imgWidth;

                doc.addImage(sliceData, 'JPEG', margin, margin, imgWidth, sliceHeight);

                // Přidat číslo stránky nahoře vlevo
                doc.setFontSize(9);
                doc.setTextColor(100, 100, 100);
                doc.text(`${cisloStranky}/${celkovyPocetStranek}`, margin, 8);

                cisloStranky++;
                yPosition += pageHeightInPx;
            }
        }

        // Stáhnout PDF
        const nazevSouboru = `statistiky_${rok}_${mesicValue || 'vsechny'}_${new Date().toISOString().split('T')[0]}.pdf`;
        doc.save(nazevSouboru);

    } catch (error) {
        console.error('Chyba exportu PDF:', error);
        wgsToast.error('Chyba při exportu PDF: ' + error.message);
    }
}

// ========================================
// EDITACE ZAKÁZKY
// ========================================

/**
 * Otevřít modal pro editaci zakázky
 * Umožňuje změnit: technika, prodejce, zemi
 */
async function otevritEditaciZakazky(zakazkaId, reklamaceId) {
    try {
        // Načíst detaily zakázky
        const response = await fetch(`/api/statistiky_api.php?action=detail_zakazky&id=${zakazkaId}`);
        const result = await response.json();

        if (result.status !== 'success') {
            wgsToast.error('Chyba při načítání zakázky');
            return;
        }

        const zakazka = result.zakazka;

        // Načíst seznamy techniků a prodejců
        const [techniciResp, prodejciResp] = await Promise.all([
            fetch('/api/statistiky_api.php?action=seznam_techniku'),
            fetch('/api/statistiky_api.php?action=seznam_prodejcu')
        ]);

        const techniciData = await techniciResp.json();
        const prodejciData = await prodejciResp.json();

        const technici = techniciData.technici || [];
        const prodejci = prodejciData.prodejci || [];

        // Vytvořit modal
        const modal = document.createElement('div');
        modal.id = 'edit-zakazka-modal';
        modal.className = 'wgs-modal-overlay';
        modal.innerHTML = `
            <div class="wgs-modal-content" style="max-width: 600px;">
                <div class="wgs-modal-header">
                    <h2>Upravit zakázku</h2>
                    <button class="wgs-modal-close" id="close-edit-modal">&times;</button>
                </div>
                <div class="wgs-modal-body">
                    <div style="background: #f5f5f5; padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                        <strong style="font-size: 1.1rem;">${reklamaceId}</strong><br>
                        <span style="color: #666; font-size: 0.9rem;">${zakazka.jmeno_zakaznika || ''}</span>
                    </div>

                    <form id="form-edit-zakazka">
                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">Technik:</label>
                            <select id="edit-technik" name="technik" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
                                <option value="">-- Nepřiřazeno --</option>
                                ${technici.map(t => `
                                    <option value="${t.id}" ${zakazka.assigned_to == t.id ? 'selected' : ''}>
                                        ${t.name} (${t.user_id})
                                    </option>
                                `).join('')}
                            </select>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">Prodejce:</label>
                            <select id="edit-prodejce" name="prodejce" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
                                <option value="">-- Nepřiřazeno --</option>
                                ${prodejci.map(p => `
                                    <option value="${p.user_id}" ${zakazka.created_by == p.user_id ? 'selected' : ''}>
                                        ${p.name}
                                    </option>
                                `).join('')}
                            </select>
                        </div>

                        <div style="margin-bottom: 20px;">
                            <label style="display: block; font-weight: 600; margin-bottom: 8px;">Země:</label>
                            <select id="edit-zeme" name="zeme" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 1rem;">
                                <option value="CZ" ${zakazka.faktura_zeme === 'CZ' ? 'selected' : ''}>Česká republika (CZ)</option>
                                <option value="SK" ${zakazka.faktura_zeme === 'SK' ? 'selected' : ''}>Slovensko (SK)</option>
                            </select>
                        </div>

                        <div style="display: flex; gap: 10px; justify-content: flex-end; margin-top: 30px;">
                            <button type="button" id="cancel-edit-zakazka" style="padding: 10px 20px; background: #999; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem;">
                                Zrušit
                            </button>
                            <button type="submit" style="padding: 10px 20px; background: #2D5016; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 1rem; font-weight: 600;">
                                Uložit změny
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        `;

        document.body.appendChild(modal);

        // Event listenery
        document.getElementById('close-edit-modal').onclick = () => modal.remove();
        document.getElementById('cancel-edit-zakazka').onclick = () => modal.remove();

        // Zavření modalu kliknutím mimo
        modal.onclick = (e) => {
            if (e.target === modal) modal.remove();
        };

        // Submit formuláře
        document.getElementById('form-edit-zakazka').onsubmit = async (e) => {
            e.preventDefault();

            const technikId = document.getElementById('edit-technik').value;
            const prodejceId = document.getElementById('edit-prodejce').value;
            const zeme = document.getElementById('edit-zeme').value;

            try {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

                const formData = new FormData();
                formData.append('action', 'upravit_zakazku');
                formData.append('id', zakazkaId);
                formData.append('assigned_to', technikId);
                formData.append('created_by', prodejceId);
                formData.append('faktura_zeme', zeme);
                formData.append('csrf_token', csrfToken);

                const response = await fetch('/api/statistiky_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    wgsToast.success('Zakázka úspěšně upravena');
                    modal.remove();
                    // Reload dat
                    nactiZakazky();
                } else {
                    wgsToast.error(result.message || 'Chyba při ukládání');
                }

            } catch (error) {
                console.error('Chyba při ukládání:', error);
                wgsToast.error('Chyba při ukládání změn');
            }
        };

    } catch (error) {
        console.error('Chyba při otevření editace:', error);
        wgsToast.error('Chyba při otevření formuláře');
    }
}

// ========================================
// EXPORT FUNKCÍ NA WINDOW PRO DATA-ACTION
// ========================================
window.aplikovatFiltry = aplikovatFiltry;
window.resetovitFiltry = resetovitFiltry;
window.exportovatPDF = exportovatPDF;
window.predchoziStranka = predchoziStranka;
window.dalsiStranka = dalsiStranka;

// ========================================
// ACTION REGISTRY - Registrace akcí pro event delegation
// ========================================
if (typeof window.Utils !== 'undefined' && window.Utils.registerAction) {
    window.Utils.registerAction('aplikovatFiltry', () => {
        aplikovatFiltry();
    });

    window.Utils.registerAction('resetovitFiltry', () => {
        resetovitFiltry();
    });

    window.Utils.registerAction('exportovatPDF', () => {
        exportovatPDF();
    });

    window.Utils.registerAction('predchoziStranka', () => {
        predchoziStranka();
    });

    window.Utils.registerAction('dalsiStranka', () => {
        dalsiStranka();
    });

    window.Utils.registerAction('upravitZakazku', (element) => {
        const zakazkaId = element.getAttribute('data-zakazka-id');
        const reklamaceId = element.getAttribute('data-reklamace-id');
        otevritEditaciZakazky(zakazkaId, reklamaceId);
    });
}
