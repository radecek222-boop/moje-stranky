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

console.log('📊 Statistiky 2.0 - načítání...');

/**
 * Inicializace při načtení stránky
 */
document.addEventListener('DOMContentLoaded', () => {
    console.log('📊 Statistiky 2.0 - inicializace');

    // Inicializovat multi-select dropdowny
    inicializujMultiselect();

    // Načíst data
    nactiProdejce();
    nactiTechniky();
    nactiSummary();
    nactiZakazky();
    nactiCharty();

    // Aplikovat filtry při změně roku nebo měsíce
    document.getElementById('filter-year').addEventListener('change', () => {
        aktualniStranka = 1;
        aplikovatFiltry();
    });

    document.getElementById('filter-month').addEventListener('change', () => {
        aktualniStranka = 1;
        aplikovatFiltry();
    });

    console.log('📊 Statistiky 2.0 - inicializace dokončena');
});

/**
 * Inicializace multi-select dropdownů
 */
function inicializujMultiselect() {
    // Prodejci
    document.getElementById('prodejci-trigger').addEventListener('click', (e) => {
        e.stopPropagation();
        toggleDropdown('prodejci');
    });

    // Technici
    document.getElementById('technici-trigger').addEventListener('click', (e) => {
        e.stopPropagation();
        toggleDropdown('technici');
    });

    // Země
    document.getElementById('zeme-trigger').addEventListener('click', (e) => {
        e.stopPropagation();
        toggleDropdown('zeme');
    });

    // Zavřít dropdowny při kliknutí mimo
    document.addEventListener('click', (e) => {
        if (!e.target.closest('.filter-multiselect')) {
            document.querySelectorAll('.multiselect-dropdown').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });

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
    const jineDropdowny = document.querySelectorAll('.multiselect-dropdown');

    // Zavřít ostatní
    jineDropdowny.forEach(d => {
        if (d !== dropdown) {
            d.classList.remove('active');
        }
    });

    // Toggle aktuální
    dropdown.classList.toggle('active');
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
        container.innerHTML = '<div class="empty-state"><div class="empty-state-icon">📊</div>Žádné zakázky podle filtrů</div>';
        tableCount.textContent = '0 zakázek';
        return;
    }

    tableCount.textContent = `${data.total_count} zakázek`;

    let rows = '';
    data.zakazky.forEach(z => {
        rows += `
            <tr>
                <td>${z.cislo_reklamace || '-'}</td>
                <td>${z.adresa || '-'}</td>
                <td>${z.model || '-'}</td>
                <td>${z.technik}</td>
                <td>${z.prodejce}</td>
                <td>${parseFloat(z.castka_celkem).toFixed(2)} €</td>
                <td>${parseFloat(z.vydelek_technika).toFixed(2)} €</td>
                <td>${z.zeme}</td>
                <td>${z.datum}</td>
            </tr>
        `;
    });

    container.innerHTML = `
        <table class="stats-table">
            <thead>
                <tr>
                    <th>Reklamace ID</th>
                    <th>Adresa</th>
                    <th>Model</th>
                    <th>Technik</th>
                    <th>Prodejce</th>
                    <th>Částka celkem</th>
                    <th>Výdělek technika (33%)</th>
                    <th>Země</th>
                    <th>Datum</th>
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
        pagination.style.display = 'none';
        return;
    }

    pagination.style.display = 'flex';
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

    // 4. Statistiky techniků
    const techniciansContainer = document.getElementById('chart-technicians');
    if (data.technici && data.technici.length > 0) {
        let html = '';
        data.technici.forEach(t => {
            html += `
                <div class="chart-item">
                    <div class="chart-item-label">${t.technik} (${t.pocet} ks)</div>
                    <div class="chart-item-value">${parseFloat(t.vydelek).toFixed(2)} €</div>
                </div>
            `;
        });
        techniciansContainer.innerHTML = html;
    } else {
        techniciansContainer.innerHTML = '<div class="empty-state">Žádná data</div>';
    }
}

/**
 * Aplikovat filtry
 */
function aplikovatFiltry() {
    console.log('Aplikuji filtry...');
    aktualniStranka = 1;
    nactiSummary();
    nactiZakazky();
    nactiCharty();
}

/**
 * Resetovat filtry
 */
function resetovitFiltry() {
    console.log('Resetuji filtry...');

    // Reset year, month
    document.getElementById('filter-year').value = '2025';
    document.getElementById('filter-month').value = '11';

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

    aktualniStranka = 1;
    aplikovatFiltry();
}

/**
 * Exportovat do PDF - použití html2canvas pro správné UTF-8
 */
async function exportovatPDF() {
    try {
        console.log('📄 Exportuji PDF...');

        // Načíst VŠECHNA data (bez limitu)
        const filterParams = getFilterParams();
        const response = await fetch(`/api/statistiky_api.php?action=get_zakazky&${filterParams}&pro_export=1`);
        const result = await response.json();

        if (result.status !== 'success' || !result.data.zakazky) {
            alert('Chyba při načítání dat pro export');
            return;
        }

        const zakazky = result.data.zakazky;

        if (zakazky.length === 0) {
            alert('Žádná data k exportu podle filtrů');
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

        // Vytvořit HTML pro PDF (skrytý div)
        const pdfContainer = document.createElement('div');
        pdfContainer.style.cssText = 'position: absolute; left: -9999px; width: 1200px; height: 800px; background: white; padding: 30px; font-family: Poppins, Arial, sans-serif;';

        pdfContainer.innerHTML = `
            <div style="margin-bottom: 15px;">
                <h1 style="color: #333; font-size: 18px; margin: 0 0 8px 0; font-weight: 700;">Statistiky a reporty - WGS</h1>
                <p style="color: #666; font-size: 11px; margin: 0;">Rok: ${rok} | Měsíc: ${mesic} | Celkem: ${zakazky.length} zakázek</p>
            </div>
            <table style="width: 100%; border-collapse: collapse; font-size: 9px;">
                <thead>
                    <tr style="background: #444; color: white;">
                        <th style="padding: 5px; text-align: left; border: 1px solid #999;">Reklamace ID</th>
                        <th style="padding: 5px; text-align: left; border: 1px solid #999;">Adresa</th>
                        <th style="padding: 5px; text-align: left; border: 1px solid #999;">Model</th>
                        <th style="padding: 5px; text-align: left; border: 1px solid #999;">Technik</th>
                        <th style="padding: 5px; text-align: left; border: 1px solid #999;">Prodejce</th>
                        <th style="padding: 5px; text-align: right; border: 1px solid #999;">Částka</th>
                        ${zobrazitOdmenu ? '<th style="padding: 5px; text-align: right; border: 1px solid #999;">Výdělek (33%)</th>' : ''}
                        <th style="padding: 5px; text-align: center; border: 1px solid #999;">Země</th>
                        <th style="padding: 5px; text-align: center; border: 1px solid #999;">Datum</th>
                    </tr>
                </thead>
                <tbody>
                    ${zakazky.map((z, idx) => `
                        <tr style="background: ${idx % 2 === 0 ? '#fff' : '#f5f5f5'};">
                            <td style="padding: 4px; border: 1px solid #ddd;">${z.cislo_reklamace || '-'}</td>
                            <td style="padding: 4px; border: 1px solid #ddd;">${z.adresa || '-'}</td>
                            <td style="padding: 4px; border: 1px solid #ddd;">${z.model || '-'}</td>
                            <td style="padding: 4px; border: 1px solid #ddd;">${z.technik || '-'}</td>
                            <td style="padding: 4px; border: 1px solid #ddd;">${z.prodejce || '-'}</td>
                            <td style="padding: 4px; border: 1px solid #ddd; text-align: right;">${parseFloat(z.castka_celkem).toFixed(2)} €</td>
                            ${zobrazitOdmenu ? `<td style="padding: 4px; border: 1px solid #ddd; text-align: right;">${parseFloat(z.vydelek_technika).toFixed(2)} €</td>` : ''}
                            <td style="padding: 4px; border: 1px solid #ddd; text-align: center;">${z.zeme || '-'}</td>
                            <td style="padding: 4px; border: 1px solid #ddd; text-align: center;">${z.datum || '-'}</td>
                        </tr>
                    `).join('')}
                </tbody>
            </table>
            <div style="position: absolute; bottom: 100px; right: 30px; text-align: right;">
                <div style="background: #f0f0f0; border: 1px solid #999; padding: 8px 16px; margin-bottom: 8px; font-size: 18px; font-weight: bold; display: inline-block;">
                    Počet zakázek celkem: <span style="color: #333;">${zakazky.length} ks</span>
                </div><br>
                <div style="background: #f0f0f0; border: 1px solid #999; padding: 8px 16px; margin-bottom: 8px; font-size: 18px; font-weight: bold; display: inline-block;">
                    Celkem za zakázky k fakturaci: <span style="color: #333;">${celkemCastka.toFixed(2)} €</span>
                </div><br>
                ${zobrazitOdmenu ? `
                    <div style="background: #f0f0f0; border: 1px solid #999; padding: 8px 16px; font-size: 18px; font-weight: bold; display: inline-block;">
                        Výdělek celkem technik: <span style="color: #333;">${celkemVydelek.toFixed(2)} €</span>
                    </div><br>
                ` : ''}
            </div>
            <div style="position: absolute; bottom: 30px; left: 30px; right: 30px; padding-top: 15px; border-top: 1px solid #ddd;">
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
        console.log('📸 Renderuji HTML pomocí html2canvas...');
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
            doc.text('1/1', margin, margin - 3);

            // Přidat text o počtu stránek dole
            doc.setFontSize(8);
            doc.setTextColor(170, 170, 170);
            doc.text('Výpis se skládá z 1 stránky', margin, pageHeight - 5);
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
                doc.text(`${cisloStranky}/${celkovyPocetStranek}`, margin, margin - 3);

                // Na poslední stránce přidat text o počtu stránek dole
                if (cisloStranky === celkovyPocetStranek) {
                    doc.setFontSize(8);
                    doc.setTextColor(170, 170, 170);
                    const pocetText = celkovyPocetStranek === 1 ? 'stránky' :
                                     (celkovyPocetStranek >= 2 && celkovyPocetStranek <= 4) ? 'stránek' : 'stránek';
                    doc.text(`Výpis se skládá z ${celkovyPocetStranek} ${pocetText}`, margin, pageHeight - 5);
                }

                cisloStranky++;
                yPosition += pageHeightInPx;
            }
        }

        // Stáhnout PDF
        const nazevSouboru = `statistiky_${rok}_${mesicValue || 'vsechny'}_${new Date().toISOString().split('T')[0]}.pdf`;
        doc.save(nazevSouboru);

        console.log('✅ PDF exportováno:', nazevSouboru);

    } catch (error) {
        console.error('Chyba exportu PDF:', error);
        alert('Chyba při exportu PDF: ' + error.message);
    }
}
