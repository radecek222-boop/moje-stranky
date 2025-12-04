<?php
require_once "init.php";

// BEZPEČNOST: Kontrola admin přihlášení
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    header('Location: login.php?redirect=statistiky.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Statistiky a reporty | White Glove Service</title>
  <meta name="description" content="Statistiky a reporty pro vyúčtování - prodejci, technici, zakázky.">
  <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
  <link rel="preload" href="assets/css/styles.min.css" as="style">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- External CSS -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.min.css" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="assets/css/mobile-responsive.min.css"></noscript>

  <style>
/* ==================================================
   STATISTIKY - NOVÝ DESIGN
   ================================================== */

body {
    background: #f5f5f5;
    font-family: 'Poppins', sans-serif;
}

.stats-container {
    max-width: 1600px;
    margin: 0 auto;
    padding: 1rem;
}

/* Header */
.stats-header {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.stats-header h1 {
    margin: 0;
    font-size: 1.8rem;
    color: #333333;
    font-weight: 600;
}

.stats-header p {
    margin: 0.25rem 0 0 0;
    color: #666;
    font-size: 0.9rem;
}

/* Summary karty */
.stats-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.summary-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    border-left: 4px solid #333333;
}

.summary-card-label {
    font-size: 0.75rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.summary-card-value {
    font-size: 2rem;
    font-weight: 700;
    color: #333333;
    margin-bottom: 0.25rem;
}

.summary-card-sub {
    font-size: 0.8rem;
    color: #999;
}

/* Filtry */
.stats-filters {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.filters-title {
    font-size: 1rem;
    font-weight: 600;
    color: #333333;
    margin-bottom: 1rem;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-label {
    font-size: 0.75rem;
    font-weight: 600;
    color: #666;
    margin-bottom: 0.5rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.filter-select {
    padding: 0.6rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    font-size: 0.9rem;
    background: white;
    cursor: pointer;
}

.filter-select:focus {
    outline: none;
    border-color: #333333;
}

/* Multi-select checkboxy */
.filter-multiselect {
    position: relative;
}

.multiselect-trigger {
    padding: 0.6rem;
    border: 1px solid #ddd;
    border-radius: 4px;
    background: white;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.9rem;
}

.multiselect-trigger:hover {
    border-color: #333333;
}

.multiselect-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
    margin-top: 0.25rem;
    max-height: 250px;
    overflow-y: auto;
    z-index: 1000;
    display: none;
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.multiselect-dropdown.active {
    display: block;
}

.multiselect-option {
    padding: 0.6rem;
    display: flex;
    align-items: center;
    cursor: pointer;
    border-bottom: 1px solid #f0f0f0;
}

.multiselect-option:hover {
    background: #f9f9f9;
}

.multiselect-option:last-child {
    border-bottom: none;
}

.multiselect-option input[type="checkbox"] {
    margin-right: 0.5rem;
    cursor: pointer;
}

.multiselect-option label {
    cursor: pointer;
    flex: 1;
    font-size: 0.85rem;
}

/* Akční tlačítka */
.filter-actions {
    display: flex;
    gap: 0.75rem;
    justify-content: flex-end;
}

.btn {
    padding: 0.6rem 1.5rem;
    border-radius: 4px;
    border: 1px solid #ddd;
    background: white;
    cursor: pointer;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.2s;
}

.btn:hover {
    border-color: #333333;
}

.btn-primary {
    background: #333333;
    color: white;
    border-color: #333333;
}

.btn-primary:hover {
    background: #1a300d;
}

.btn-export {
    background: #0066cc;
    color: white;
    border-color: #0066cc;
}

.btn-export:hover {
    background: #004499;
}

/* Hlavní tabulka zakázek */
.stats-table-wrapper {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    margin-bottom: 1rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.table-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
}

.table-title {
    font-size: 1rem;
    font-weight: 600;
    color: #333333;
}

.table-count {
    font-size: 0.85rem;
    color: #666;
}

.stats-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}

.stats-table thead {
    background: #f9f9f9;
    border-bottom: 2px solid #333333;
}

.stats-table th {
    padding: 0.75rem;
    text-align: left;
    font-weight: 600;
    color: #333333;
    text-transform: uppercase;
    font-size: 0.7rem;
    letter-spacing: 0.5px;
}

.stats-table td {
    padding: 0.75rem;
    border-bottom: 1px solid #f0f0f0;
}

.stats-table tbody tr:hover {
    background: #f9f9f9;
}

.stats-table tbody tr:last-child td {
    border-bottom: none;
}

/* Stránkování */
.pagination {
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 0.5rem;
    margin-top: 1rem;
}

.pagination button {
    padding: 0.5rem 0.75rem;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.85rem;
}

.pagination button:hover:not(:disabled) {
    border-color: #333333;
    background: #f9f9f9;
}

.pagination button:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.pagination .page-info {
    font-size: 0.85rem;
    color: #666;
}

/* Grafy sekce */
.stats-charts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
    gap: 1rem;
    margin-bottom: 1rem;
}

.chart-card {
    background: white;
    border-radius: 8px;
    padding: 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.chart-title {
    font-size: 1rem;
    font-weight: 600;
    color: #333333;
    margin-bottom: 1rem;
}

.chart-content {
    max-height: 300px;
    overflow-y: auto;
}

.chart-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.5rem 0;
    border-bottom: 1px solid #f0f0f0;
}

.chart-item:last-child {
    border-bottom: none;
}

.chart-item-label {
    font-size: 0.85rem;
    color: #333;
}

.chart-item-value {
    font-size: 0.85rem;
    font-weight: 600;
    color: #333333;
}

/* Loading */
.loading {
    text-align: center;
    padding: 2rem;
    color: #666;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 3rem;
    color: #999;
}

.empty-state-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
    opacity: 0.5;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-summary {
        grid-template-columns: 1fr 1fr;
    }

    .filters-grid {
        grid-template-columns: 1fr;
    }

    .stats-charts {
        grid-template-columns: 1fr;
    }

    .stats-table {
        font-size: 0.75rem;
    }

    .stats-table th,
    .stats-table td {
        padding: 0.5rem;
    }
}
  </style>
</head>

<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<main id="main-content">
<div class="stats-container">

  <!-- Header -->
  <div class="stats-header">
    <h1>Statistiky a reporty</h1>
    <p>Vyúčtování zakázek, prodejci, technici - <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Administrátor'); ?></p>
  </div>

  <!-- Summary karty -->
  <div class="stats-summary">
    <div class="summary-card">
      <div class="summary-card-label">Celkem reklamací</div>
      <div class="summary-card-value" id="total-all">0</div>
      <div class="summary-card-sub">Všechny v systému</div>
    </div>

    <div class="summary-card">
      <div class="summary-card-label">Reklamací v měsíci</div>
      <div class="summary-card-value" id="total-month">0</div>
      <div class="summary-card-sub">Podle filtrů</div>
    </div>

    <div class="summary-card">
      <div class="summary-card-label">Částka celkem</div>
      <div class="summary-card-value" id="revenue-all">0 €</div>
      <div class="summary-card-sub">Všechny zakázky</div>
    </div>

    <div class="summary-card">
      <div class="summary-card-label">Částka v měsíci</div>
      <div class="summary-card-value" id="revenue-month">0 €</div>
      <div class="summary-card-sub">Podle filtrů</div>
    </div>
  </div>

  <!-- Filtry -->
  <div class="stats-filters">
    <div class="filters-title">Filtry</div>

    <div class="filters-grid">
      <!-- Rok -->
      <div class="filter-group">
        <label class="filter-label" for="filter-year">Rok</label>
        <select class="filter-select" id="filter-year">
          <option value="">Všechny</option>
          <option value="2024">2024</option>
          <option value="2025" selected>2025</option>
          <option value="2026">2026</option>
        </select>
      </div>

      <!-- Měsíc -->
      <div class="filter-group">
        <label class="filter-label" for="filter-month">Měsíc</label>
        <select class="filter-select" id="filter-month">
          <option value="">Všechny</option>
          <option value="1">Leden</option>
          <option value="2">Únor</option>
          <option value="3">Březen</option>
          <option value="4">Duben</option>
          <option value="5">Květen</option>
          <option value="6">Červen</option>
          <option value="7">Červenec</option>
          <option value="8">Srpen</option>
          <option value="9">Září</option>
          <option value="10">Říjen</option>
          <option value="11" selected>Listopad</option>
          <option value="12">Prosinec</option>
        </select>
      </div>

      <!-- Prodejci (multi-select) -->
      <div class="filter-group">
        <label class="filter-label" id="label-prodejci">Prodejci</label>
        <div class="filter-multiselect">
          <div class="multiselect-trigger" id="prodejci-trigger" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="label-prodejci">
            <span id="prodejci-label">Všichni</span>
            <span aria-hidden="true">▼</span>
          </div>
          <div class="multiselect-dropdown" id="prodejci-dropdown" role="listbox" aria-labelledby="label-prodejci">
            <!-- Načte se dynamicky -->
          </div>
        </div>
      </div>

      <!-- Technici (multi-select) -->
      <div class="filter-group">
        <label class="filter-label" id="label-technici">Technici</label>
        <div class="filter-multiselect">
          <div class="multiselect-trigger" id="technici-trigger" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="label-technici">
            <span id="technici-label">Všichni</span>
            <span aria-hidden="true">▼</span>
          </div>
          <div class="multiselect-dropdown" id="technici-dropdown" role="listbox" aria-labelledby="label-technici">
            <!-- Načte se dynamicky -->
          </div>
        </div>
      </div>

      <!-- Země -->
      <div class="filter-group">
        <label class="filter-label" id="label-zeme">Země</label>
        <div class="filter-multiselect">
          <div class="multiselect-trigger" id="zeme-trigger" role="button" tabindex="0" aria-haspopup="listbox" aria-expanded="false" aria-labelledby="label-zeme">
            <span id="zeme-label">Všechny</span>
            <span aria-hidden="true">▼</span>
          </div>
          <div class="multiselect-dropdown" id="zeme-dropdown" role="listbox" aria-labelledby="label-zeme">
            <div class="multiselect-option" role="option">
              <input type="checkbox" id="zeme-cz" value="cz" checked>
              <label for="zeme-cz">🇨🇿 Česko</label>
            </div>
            <div class="multiselect-option" role="option">
              <input type="checkbox" id="zeme-sk" value="sk" checked>
              <label for="zeme-sk">🇸🇰 Slovensko</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="filter-actions">
      <div style="display: flex; align-items: center; gap: 10px; margin-right: auto;">
        <input type="checkbox" id="zobrazitOdmenu" checked style="cursor: pointer;">
        <label for="zobrazitOdmenu" style="cursor: pointer; font-size: 0.9rem; color: #666;">Zobrazit odměnu technika v PDF</label>
      </div>
      <button class="btn" data-action="resetovitFiltry">Reset</button>
      <button class="btn btn-primary" data-action="aplikovatFiltry">Aplikovat filtry</button>
      <button class="btn btn-export" data-action="exportovatPDF">Exportovat PDF</button>
    </div>
  </div>

  <!-- Hlavní tabulka zakázek -->
  <div class="stats-table-wrapper">
    <div class="table-header">
      <div class="table-title">Zakázky podle filtrů</div>
      <div class="table-count" id="table-count">0 zakázek</div>
    </div>

    <div id="table-container" aria-live="polite">
      <div class="loading" role="status">Načítání dat...</div>
    </div>

    <!-- Stránkování -->
    <nav class="pagination" id="pagination" style="display: none;" aria-label="Stránkování">
      <button id="prev-page" data-action="predchoziStranka" aria-label="Předchozí strana">← Předchozí</button>
      <span class="page-info" id="page-info" aria-live="polite">Strana 1 z 1</span>
      <button id="next-page" data-action="dalsiStranka" aria-label="Další strana">Další →</button>
    </nav>
  </div>

  <!-- Grafy a statistiky -->
  <div class="stats-charts">
    <!-- Nejporuchovější modely -->
    <div class="chart-card">
      <div class="chart-title">Nejporuchovější modely</div>
      <div class="chart-content" id="chart-models" aria-live="polite">
        <div class="loading" role="status">Načítání...</div>
      </div>
    </div>

    <!-- Lokality -->
    <div class="chart-card">
      <div class="chart-title">Lokality (města)</div>
      <div class="chart-content" id="chart-cities" aria-live="polite">
        <div class="loading" role="status">Načítání...</div>
      </div>
    </div>

    <!-- Statistiky prodejců -->
    <div class="chart-card">
      <div class="chart-title">Statistiky prodejců</div>
      <div class="chart-content" id="chart-salespersons" aria-live="polite">
        <div class="loading" role="status">Načítání...</div>
      </div>
    </div>

    <!-- Statistiky techniků -->
    <div class="chart-card">
      <div class="chart-title">Statistiky techniků</div>
      <div class="chart-content" id="chart-technicians" aria-live="polite">
        <div class="loading" role="status">Načítání...</div>
      </div>
    </div>
  </div>

</div>
</main>

<!-- jsPDF pro PDF export -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>

<script src="assets/js/logger.min.js" defer></script>
<script src="assets/js/statistiky.min.js?v=2.3" defer></script>

<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
</body>
</html>
