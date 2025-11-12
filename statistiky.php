<?php
require_once "init.php";

// BEZPEČNOST: Kontrola admin přihlášení
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    header('Location: login.php?redirect=statistiky.php');
    exit;
}

// Embed mode - skrýt navigaci
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Statistiky | White Glove Service</title>
  <meta name="description" content="Statistiky a přehledy White Glove Service. Sledujte metriky servisu, úspěšnost oprav, spokojenost zákazníků a výkonnost techniků.">
  <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">

  <!-- External CSS -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/statistiky.min.css">
  <link rel="stylesheet" href="assets/css/statistiky-fixes.css">
  <link rel="stylesheet" href="assets/css/control-center-modal.css">

  <style>
/* Kompaktní statistiky */
.stats-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 1rem;
}

/* User bar - kompaktní */
.stats-user-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    padding: 0.5rem 1rem;
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 6px;
}

.stats-title {
    font-size: 1.2rem;
    font-weight: 500;
    margin: 0;
}

.stats-subtitle {
    font-size: 0.75rem;
    color: #666;
    margin: 0;
}

.stats-user {
    font-size: 0.75rem;
    color: #2D5016;
    font-weight: 600;
}

/* Kompaktní filtry */
.stats-filters {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 0.75rem;
    margin-bottom: 0.75rem;
}

.stats-filters-title {
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
    margin-bottom: 0.5rem;
    color: #666;
}

.stats-filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.5rem;
    margin-bottom: 0.5rem;
}

.stats-filter-group {
    display: flex;
    flex-direction: column;
    gap: 0.25rem;
}

.stats-filter-label {
    font-size: 0.65rem;
    color: #666;
    font-weight: 500;
}

.stats-filter-input,
.stats-filter-select {
    padding: 0.4rem 0.5rem;
    border: 1px solid #e0e0e0;
    border-radius: 4px;
    font-size: 0.7rem;
    width: 100%;
}

.stats-filter-actions {
    display: flex;
    gap: 0.5rem;
    justify-content: flex-end;
}

.stats-btn {
    padding: 0.4rem 0.8rem;
    border: 1px solid #e0e0e0;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.7rem;
    transition: all 0.2s;
}

.stats-btn:hover {
    border-color: #2D5016;
}

.stats-btn-primary {
    background: #2D5016;
    color: white;
    border-color: #2D5016;
}

/* Summary stats - kompaktní */
.stats-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
    gap: 0.5rem;
    margin-bottom: 0.75rem;
}

.stats-summary-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-left: 3px solid #2D5016;
    padding: 0.75rem;
    border-radius: 4px;
}

.stats-summary-label {
    font-size: 0.65rem;
    color: #666;
    text-transform: uppercase;
    margin-bottom: 0.3rem;
}

.stats-summary-value {
    font-size: 1.5rem;
    font-weight: 600;
    margin-bottom: 0.2rem;
}

.stats-summary-sub {
    font-size: 0.65rem;
    color: #999;
}

/* Card Grid - jako Control Center */
.stats-card-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 1rem;
}

.stats-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 1.5rem;
    cursor: pointer;
    transition: all 0.3s ease;
    position: relative;
}

.stats-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: #2D5016;
}

.stats-card-icon {
    font-size: 2rem;
    margin-bottom: 0.75rem;
    color: #2D5016;
}

.stats-card-title {
    font-size: 1rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #000;
}

.stats-card-description {
    font-size: 0.8rem;
    color: #666;
    line-height: 1.4;
}

/* Modal styling již existuje v control-center-modal.css */

/* Tabulky v modalech */
.cc-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.cc-table thead {
    background: #f5f5f5;
    border-bottom: 2px solid #2D5016;
}

.cc-table th {
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #2D5016;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.05em;
}

.cc-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #e0e0e0;
}

.cc-table tbody tr:hover {
    background: #f9f9f9;
}

.cc-table tbody tr:last-child td {
    border-bottom: none;
}
</style>
</head>

<body>
<?php if (!$embedMode): ?>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>
<?php endif; ?>

<!-- MAIN CONTENT -->
<main<?php if ($embedMode) echo ' style="margin-top: 0; padding-top: 0;"'; ?>>
<div class="stats-container">

  <!-- USER INFO BAR - kompaktní -->
  <div class="stats-user-bar">
    <div>
      <h1 class="stats-title">Statistiky</h1>
      <p class="stats-subtitle">Analýza a reporty reklamací</p>
    </div>
    <div class="stats-user">
      <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Administrátor'); ?>
    </div>
  </div>

  <!-- KOMPAKTNÍ FILTRY -->
  <div class="stats-filters">
    <div class="stats-filters-title">Filtry</div>

    <div class="stats-filters-grid">
      <div class="stats-filter-group">
        <label class="stats-filter-label">Země</label>
        <select class="stats-filter-select" id="filter-country" multiple>
          <option>CZ</option>
          <option>SK</option>
        </select>
      </div>

      <div class="stats-filter-group">
        <label class="stats-filter-label">Stav</label>
        <select class="stats-filter-select" id="filter-status">
          <option value="">Všechny</option>
          <option>ČEKÁ</option>
          <option>DOMLUVENÁ</option>
          <option>HOTOVO</option>
        </select>
      </div>

      <div class="stats-filter-group">
        <label class="stats-filter-label">Od data</label>
        <input type="date" class="stats-filter-input" id="filter-date-from">
      </div>

      <div class="stats-filter-group">
        <label class="stats-filter-label">Do data</label>
        <input type="date" class="stats-filter-input" id="filter-date-to">
      </div>
    </div>

    <div class="stats-filter-actions">
      <button class="stats-btn" onclick="resetFilters()">Reset</button>
      <button class="stats-btn stats-btn-primary" onclick="applyFilters()">Aplikovat</button>
    </div>
  </div>

  <!-- SUMMARY STATISTIKY - kompaktní -->
  <div class="stats-summary">
    <div class="stats-summary-card">
      <div class="stats-summary-label">Celkem zakázek</div>
      <div class="stats-summary-value" id="total-orders">0</div>
      <div class="stats-summary-sub">Filtrovaných</div>
    </div>

    <div class="stats-summary-card">
      <div class="stats-summary-label">Celkový obrat</div>
      <div class="stats-summary-value" id="total-revenue">0 €</div>
      <div class="stats-summary-sub">Všechny zakázky</div>
    </div>

    <div class="stats-summary-card">
      <div class="stats-summary-label">Průměrná zakázka</div>
      <div class="stats-summary-value" id="avg-order">0 €</div>
      <div class="stats-summary-sub">Na zakázku</div>
    </div>

    <div class="stats-summary-card">
      <div class="stats-summary-label">Aktivní technici</div>
      <div class="stats-summary-value" id="active-techs">0</div>
      <div class="stats-summary-sub">V období</div>
    </div>
  </div>

  <!-- CARD GRID S MODALY -->
  <div class="stats-card-grid">

    <!-- Statistiky prodejců -->
    <div class="stats-card" onclick="openStatsModal('salesperson')">
      <div class="stats-card-title">Statistiky prodejců</div>
      <div class="stats-card-description">Detailní přehled výkonnosti prodejců, počet zakázek a obraty</div>
    </div>

    <!-- Statistiky techniků -->
    <div class="stats-card" onclick="openStatsModal('technician')">
      <div class="stats-card-title">Statistiky techniků</div>
      <div class="stats-card-description">Výkonnost techniků, úspěšnost oprav a výdělky</div>
    </div>

    <!-- Nejporuchovější modely -->
    <div class="stats-card" onclick="openStatsModal('models')">
      <div class="stats-card-title">Nejporuchovější modely</div>
      <div class="stats-card-description">Analýza nejčastěji porouchaných modelů a výrobků</div>
    </div>

    <!-- Filtrované zakázky -->
    <div class="stats-card" onclick="openStatsModal('orders')">
      <div class="stats-card-title">Filtrované zakázky</div>
      <div class="stats-card-description">Přehled všech zakázek podle aktuálních filtrů</div>
    </div>

    <!-- Grafy a vizualizace -->
    <div class="stats-card" onclick="openStatsModal('charts')">
      <div class="stats-card-title">Grafy a vizualizace</div>
      <div class="stats-card-description">Grafická analýza rozdělení podle měst, zemí a modelů</div>
    </div>

  </div>

</div>
</main>

<!-- MODAL OVERLAY -->
<div class="cc-modal-overlay" id="statsModalOverlay" onclick="closeStatsModal()">
    <div class="cc-modal" onclick="event.stopPropagation()">
        <div class="cc-modal-header">
            <h2 class="cc-modal-title" id="statsModalTitle">Statistiky</h2>
            <button class="cc-modal-close" onclick="closeStatsModal()">×</button>
        </div>
        <div class="cc-modal-body" id="statsModalBody">
            <!-- Obsah se načte dynamicky -->
        </div>
    </div>
</div>

<script src="assets/js/logger.js" defer></script>
<script src="assets/js/statistiky.min.js" defer></script>

<script>
// Modal systém
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

function closeStatsModal() {
    const overlay = document.getElementById('statsModalOverlay');
    const modal = overlay.querySelector('.cc-modal');

    // Odebrat třídu active z overlay i modalu
    overlay.classList.remove('active');
    modal.classList.remove('active');
    document.body.style.overflow = 'auto';
}

async function loadStatsContent(type, body) {
    // Zobrazit loading
    body.innerHTML = '<div style="text-align: center; padding: 2rem; color: #666;">Načítání...</div>';

    try {
        const filterParams = getFilterParams();
        const response = await fetch(`api/statistiky_api.php?action=${type}&${filterParams}`);
        const result = await response.json();

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
            }
        } else {
            body.innerHTML = '<div style="padding: 2rem; color: #d32f2f; text-align: center;">Chyba načítání dat: ' + result.message + '</div>';
        }
    } catch (error) {
        console.error('Chyba načítání statistik:', error);
        body.innerHTML = '<div style="padding: 2rem; color: #d32f2f; text-align: center;">Chyba načítání dat</div>';
    }
}

function renderSalespersonTable(body, data) {
    let rows = '';
    if (data.length === 0) {
        rows = '<tr><td colspan="6" style="text-align: center; color: #999;">Žádná data k zobrazení</td></tr>';
    } else {
        data.forEach(row => {
            rows += `
                <tr>
                    <td>${escapeHtml(row.prodejce)}</td>
                    <td>${row.pocet_zakazek}</td>
                    <td>${row.celkova_castka} €</td>
                    <td>${row.prumer_zakazka} €</td>
                    <td>${row.cz_count} / ${row.sk_count}</td>
                    <td>${row.hotove_procento}%</td>
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
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
    `;
}

function renderTechnicianTable(body, data) {
    let rows = '';
    if (data.length === 0) {
        rows = '<tr><td colspan="6" style="text-align: center; color: #999;">Žádná data k zobrazení</td></tr>';
    } else {
        data.forEach(row => {
            rows += `
                <tr>
                    <td>${escapeHtml(row.technik)}</td>
                    <td>${row.pocet_zakazek}</td>
                    <td>${row.vydelek} €</td>
                    <td>${row.prumer_zakazka} €</td>
                    <td>${row.cz_count} / ${row.sk_count}</td>
                    <td>${row.uspesnost}%</td>
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
                        <th>Zakázky</th>
                        <th>Výdělek (33%)</th>
                        <th>Průměr/zakázka</th>
                        <th>CZ / SK</th>
                        <th>Úspěšnost</th>
                    </tr>
                </thead>
                <tbody>${rows}</tbody>
            </table>
        </div>
    `;
}

function renderModelsTable(body, data) {
    let rows = '';
    if (data.length === 0) {
        rows = '<tr><td colspan="5" style="text-align: center; color: #999;">Žádná data k zobrazení</td></tr>';
    } else {
        data.forEach(row => {
            rows += `
                <tr>
                    <td>${escapeHtml(row.model)}</td>
                    <td>${row.pocet_reklamaci}</td>
                    <td>${row.podil_procent}%</td>
                    <td>${row.prumerna_castka} €</td>
                    <td>${row.celkova_castka} €</td>
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

function renderOrdersTable(body, data) {
    let rows = '';
    if (data.length === 0) {
        rows = '<tr><td colspan="8" style="text-align: center; color: #999;">Žádná data k zobrazení</td></tr>';
    } else {
        data.forEach(row => {
            rows += `
                <tr>
                    <td>${escapeHtml(row.cislo || '')}</td>
                    <td>${escapeHtml(row.jmeno || '')}</td>
                    <td>${escapeHtml(row.prodejce || '-')}</td>
                    <td>${escapeHtml(row.technik || '-')}</td>
                    <td>${row.castka} €</td>
                    <td>${escapeHtml(row.stav || '')}</td>
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

function renderCharts(body, data) {
    let citiesHtml = '';
    let countriesHtml = '';
    let modelsHtml = '';

    if (data.cities && data.cities.length > 0) {
        citiesHtml = data.cities.map(c => `<div style="padding: 0.5rem; border-bottom: 1px solid #eee;">${escapeHtml(c.mesto)}: <strong>${c.pocet}</strong></div>`).join('');
    } else {
        citiesHtml = '<div style="color: #999;">Žádná data</div>';
    }

    if (data.countries && data.countries.length > 0) {
        countriesHtml = data.countries.map(c => `<div style="padding: 0.5rem; border-bottom: 1px solid #eee;">${escapeHtml(c.zeme)}: <strong>${c.pocet}</strong></div>`).join('');
    } else {
        countriesHtml = '<div style="color: #999;">Žádná data</div>';
    }

    if (data.models && data.models.length > 0) {
        modelsHtml = data.models.map(m => `<div style="padding: 0.5rem; border-bottom: 1px solid #eee;">${escapeHtml(m.model)}: <strong>${m.pocet}</strong></div>`).join('');
    } else {
        modelsHtml = '<div style="color: #999;">Žádná data</div>';
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

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// Filter funkce
function resetFilters() {
    document.getElementById('filter-country').selectedIndex = -1;
    document.getElementById('filter-status').selectedIndex = 0;
    document.getElementById('filter-date-from').value = '';
    document.getElementById('filter-date-to').value = '';

    // Reload stats
    loadSummaryStats();
}

function applyFilters() {
    console.log('Aplikuji filtry...');
    loadSummaryStats();
}

function getFilterParams() {
    const params = new URLSearchParams();

    const country = document.getElementById('filter-country').value;
    const status = document.getElementById('filter-status').value;
    const dateFrom = document.getElementById('filter-date-from').value;
    const dateTo = document.getElementById('filter-date-to').value;

    if (country) params.append('country', country);
    if (status) params.append('status', status);
    if (dateFrom) params.append('date_from', dateFrom);
    if (dateTo) params.append('date_to', dateTo);

    return params.toString();
}

async function loadSummaryStats() {
    try {
        const filterParams = getFilterParams();
        const response = await fetch(`api/statistiky_api.php?action=summary&${filterParams}`);
        const result = await response.json();

        if (result.status === 'success') {
            document.getElementById('total-orders').textContent = result.data.total_orders;
            document.getElementById('total-revenue').textContent = result.data.total_revenue.toFixed(2) + ' €';
            document.getElementById('avg-order').textContent = result.data.avg_order.toFixed(2) + ' €';
            document.getElementById('active-techs').textContent = result.data.active_techs;
        } else {
            console.error('Chyba načítání summary statistik:', result.message);
        }
    } catch (error) {
        console.error('Chyba načítání summary statistik:', error);
    }
}

// ESC key zavře modal
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        closeStatsModal();
    }
});

// Načíst data při načtení stránky
document.addEventListener('DOMContentLoaded', () => {
    loadSummaryStats();
});
</script>

</body>
</html>
