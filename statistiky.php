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
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <title>Statistiky a reporty | White Glove Service</title>
  <meta name="description" content="Statistiky a reporty pro vyúčtování - prodejci, technici, zakázky.">
  <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
  <link rel="preload" href="assets/css/styles.min.css" as="style">

  <!-- Google Fonts -->

  <!-- External CSS -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.min.css">

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
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
    padding: 0 0 0.6rem 0;
    border-bottom: 1px solid #ddd;
    background: none;
    box-shadow: none;
    border-radius: 0;
}

.stats-header h1 {
    margin: 0;
    font-size: 1.2rem;
    color: #333333;
    font-weight: 600;
}

.stats-header p {
    display: none;
}

/* Summary karty — analytics styl */
.stats-summary {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: 0.6rem;
    margin-bottom: 0.6rem;
}

.summary-card {
    background: white;
    border-radius: 8px;
    padding: 0.75rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    text-align: center;
    border-left: 3px solid #ccc;
}

.summary-card:first-child {
    background: #333;
    color: #fff;
    border-left-color: #111;
}

.summary-card:last-child {
    background: #1a1a1a;
    color: #fff;
    border-left-color: #444;
}

.summary-card-label {
    font-size: 0.7rem;
    color: #666;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    margin-bottom: 0.2rem;
    font-weight: 600;
    opacity: 0.8;
}

.summary-card:first-child .summary-card-label,
.summary-card:last-child .summary-card-label {
    color: rgba(255,255,255,0.65);
    opacity: 1;
}

.summary-card-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #333333;
    margin-bottom: 0.1rem;
    line-height: 1;
}

.summary-card:first-child .summary-card-value,
.summary-card:last-child .summary-card-value {
    color: #fff;
}

.summary-card-sub {
    font-size: 0.7rem;
    color: #999;
    opacity: 0.7;
}

.summary-card:first-child .summary-card-sub,
.summary-card:last-child .summary-card-sub {
    color: rgba(255,255,255,0.5);
    opacity: 1;
}

/* Filtry — dark header bar */
.stats-filters {
    background: white;
    border-radius: 8px;
    padding: 0;
    margin-bottom: 0.6rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    /* overflow: hidden odstraněno — jinak ořezává multiselect dropdown */
}

.filters-title {
    background: #333;
    color: #fff;
    padding: 0.5rem 0.85rem;
    font-weight: 500;
    font-size: 0.8rem;
    letter-spacing: 0.5px;
    margin-bottom: 0;
    border-radius: 8px 8px 0 0;
}

.filters-inner {
    padding: 0.65rem 0.85rem;
    border-radius: 0 0 8px 8px;
}

.filters-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
    gap: 0.6rem;
    margin-bottom: 0.75rem;
}

.filter-group {
    display: flex;
    flex-direction: column;
}

.filter-label {
    font-size: 0.7rem;
    font-weight: 600;
    color: #666;
    margin-bottom: 0.3rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Tmavé inputy/selecty */
.filter-select {
    padding: 0.4rem 0.6rem;
    border: 1px solid #444;
    border-radius: 4px;
    font-size: 0.85rem;
    background: #222;
    color: #fff;
    cursor: pointer;
    font-family: 'Poppins', sans-serif;
}

.filter-select:focus {
    outline: none;
    border-color: #666;
}

.filter-select option {
    background: #222;
    color: #fff;
}

/* Multi-select checkboxy */
.filter-multiselect {
    position: relative;
}

.multiselect-trigger {
    padding: 0.4rem 0.6rem;
    border: 1px solid #444;
    border-radius: 4px;
    background: #222;
    color: #fff;
    cursor: pointer;
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.85rem;
    font-family: 'Poppins', sans-serif;
}

.multiselect-trigger:hover {
    border-color: #666;
}

.multiselect-dropdown {
    position: absolute;
    top: 100%;
    left: 0;
    right: 0;
    background: #222;
    border: 1px solid #444;
    border-radius: 4px;
    margin-top: 0.25rem;
    max-height: 250px;
    overflow-y: auto;
    z-index: 9999;
    display: none;
    box-shadow: 0 6px 16px rgba(0,0,0,0.4);
}

.multiselect-dropdown.active {
    display: block;
}

.multiselect-option {
    padding: 0.45rem 0.6rem;
    display: flex;
    align-items: center;
    cursor: pointer;
    border-bottom: 1px solid #333;
    color: #fff;
    font-size: 0.8rem;
}

.multiselect-option:hover {
    background: #2a2a2a;
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
    background: #111;
}

.btn-export {
    background: #333333;
    color: white;
    border-color: #333333;
}

.btn-export:hover {
    background: #111;
}

/* Hlavní tabulka zakázek — dark header bar */
.stats-table-wrapper {
    background: white;
    border-radius: 8px;
    padding: 0;
    margin-bottom: 0.6rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    overflow: hidden;
}

.table-header {
    background: #333;
    color: #fff;
    padding: 0.5rem 0.85rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0;
}

.table-title {
    font-size: 0.8rem;
    font-weight: 500;
    color: #fff;
    letter-spacing: 0.5px;
}

.table-count {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.65);
}

.table-inner {
    padding: 0.65rem 0.85rem;
}

.stats-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.75rem;
}

.stats-table thead {
    background: #f9f9f9;
    border-bottom: 2px solid #333333;
}

.stats-table th {
    padding: 0.5rem 0.4rem;
    text-align: left;
    font-weight: 600;
    color: #333333;
    text-transform: uppercase;
    font-size: 0.65rem;
    letter-spacing: 0.3px;
    white-space: nowrap;
}

.stats-table td {
    padding: 0.5rem 0.4rem;
    border-bottom: 1px solid #f0f0f0;
    font-size: 0.75rem;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 150px;
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

/* Grafy sekce — dark header bar */
.stats-charts {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 0.6rem;
    margin-bottom: 0.6rem;
}

.chart-card {
    background: white;
    border-radius: 8px;
    padding: 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    overflow: hidden;
}

.chart-title {
    background: #333;
    color: #fff;
    padding: 0.5rem 0.85rem;
    font-size: 0.8rem;
    font-weight: 500;
    letter-spacing: 0.5px;
    margin-bottom: 0;
}

.chart-content {
    max-height: 200px;
    overflow-y: auto;
    padding: 0.5rem 0.85rem;
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
    padding: 1rem;
    color: #666;
}

/* Empty state */
.empty-state {
    text-align: center;
    padding: 1.5rem;
    color: #999;
}

.empty-state-icon {
    font-size: 2rem;
    margin-bottom: 0.5rem;
    opacity: 0.5;
}

/* Responsive */
@media (max-width: 768px) {
    .stats-summary {
        grid-template-columns: 1fr 1fr;
        gap: 0.4rem;
    }

    .summary-card {
        padding: 0.6rem;
    }

    .summary-card-value {
        font-size: 1.25rem;
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

/* === PWA OPTIMALIZACE === */
@media (display-mode: standalone),
       (display-mode: fullscreen),
       (max-width: 480px) {

    .stats-container {
        padding: 0.5rem;
    }

    /* Header kompaktní */
    .stats-header {
        padding: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .stats-header h1 {
        font-size: 1.25rem;
    }

    .stats-header p {
        font-size: 0.75rem;
        display: none;
    }

    /* Summary karty - 2 sloupce, kompaktní */
    .stats-summary {
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .summary-card {
        padding: 0.75rem;
        border-left-width: 3px;
    }

    .summary-card-label {
        font-size: 0.65rem;
        margin-bottom: 0.25rem;
    }

    .summary-card-value {
        font-size: 1.25rem;
        margin-bottom: 0.15rem;
    }

    .summary-card-sub {
        font-size: 0.65rem;
        display: none;
    }

    /* Filtry kompaktní */
    .stats-filters {
        padding: 0.75rem;
        margin-bottom: 0.75rem;
    }

    .filters-title {
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
    }

    .filters-grid {
        gap: 0.5rem;
        margin-bottom: 0.75rem;
    }

    .filter-label {
        font-size: 0.65rem;
        margin-bottom: 0.25rem;
    }

    .filter-select,
    .multiselect-trigger {
        padding: 0.5rem;
        font-size: 16px; /* Prevent iOS zoom */
        min-height: 40px;
    }

    /* TLAČÍTKA - PWA optimalizace */
    .filter-actions {
        flex-direction: column;
        gap: 0.5rem;
    }

    .btn {
        width: 100%;
        padding: 0.75rem 1rem;
        font-size: 0.85rem;
        min-height: 44px;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .btn:active {
        transform: scale(0.98);
        opacity: 0.9;
    }

    /* Tabulka */
    .stats-table-wrapper {
        padding: 0.75rem;
        margin-bottom: 0.75rem;
        overflow-x: auto;
    }

    .table-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.25rem;
        margin-bottom: 0.5rem;
    }

    .table-title {
        font-size: 0.85rem;
    }

    .table-count {
        font-size: 0.7rem;
    }

    .stats-table {
        font-size: 0.7rem;
        min-width: 600px;
    }

    .stats-table th,
    .stats-table td {
        padding: 0.4rem 0.3rem;
        white-space: nowrap;
    }

    /* Paginace */
    .pagination {
        flex-wrap: wrap;
        gap: 0.5rem;
        justify-content: center;
    }

    .pagination button,
    #prev-page,
    #next-page {
        padding: 0.6rem 0.75rem;
        font-size: 0.75rem;
        min-height: 40px;
        flex: 1;
        min-width: 100px;
    }

    /* Grafy */
    .stats-charts {
        gap: 0.5rem;
    }

    .chart-card {
        padding: 0.75rem;
    }

    .chart-title {
        font-size: 0.85rem;
        margin-bottom: 0.5rem;
    }

    /* Loading a empty state */
    .loading,
    .empty-state {
        padding: 1.5rem;
    }

    .empty-state-icon {
        font-size: 2rem;
    }
}

/* Extra malé displeje */
@media (max-width: 360px) {
    .stats-summary {
        grid-template-columns: 1fr;
    }

    .summary-card-value {
        font-size: 1.5rem;
    }

    .summary-card-sub {
        display: block;
    }

    .btn {
        font-size: 0.8rem;
        padding: 0.6rem;
    }
}

/* Touch feedback */
@media (hover: none) and (pointer: coarse) {
    .btn:hover {
        transform: none;
    }

    .btn:active {
        transform: scale(0.97);
        opacity: 0.85;
    }

    .summary-card:active,
    .chart-card:active {
        background: #f5f5f5;
    }
}

/* ==================================================
   MODAL OVERLAY - Editace zakázky
   ================================================== */
.wgs-modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.7);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 10000;
    animation: fadeIn 0.2s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.wgs-modal-content {
    background: white;
    border-radius: 12px;
    padding: 0;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 10px 40px rgba(0,0,0,0.3);
    animation: slideIn 0.3s ease;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.wgs-modal-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 16px;
    border-bottom: 1px solid #ddd;
}

.wgs-modal-header h2 {
    margin: 0;
    font-size: 1rem;
    color: #333;
}

.wgs-modal-close {
    background: none;
    border: none;
    font-size: 2rem;
    color: #999;
    cursor: pointer;
    padding: 0;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: color 0.2s;
}

.wgs-modal-close:hover {
    color: #333;
}

.wgs-modal-body {
    padding: 14px 16px;
}
  </style>
  <link rel="stylesheet" href="assets/css/poppins-font.css">
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

  <!-- Summary karty (Step 146: HTMX target) -->
  <div class="stats-summary" id="stats-summary-container">
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
    <div class="filters-inner">
    <div class="filters-grid">
      <!-- Rok -->
      <div class="filter-group">
        <label class="filter-label" for="filter-year">Rok</label>
        <select class="filter-select" id="filter-year">
          <option value="">Všechny</option>
          <option value="2024">2024</option>
          <option value="2025">2025</option>
          <option value="2026" <?php echo (date('Y') == '2026') ? 'selected' : ''; ?>>2026</option>
        </select>
      </div>

      <!-- Měsíc -->
      <div class="filter-group">
        <label class="filter-label" for="filter-month">Měsíc</label>
        <select class="filter-select" id="filter-month">
          <option value="">Všechny</option>
          <option value="1" <?php echo (date('n') == 1) ? 'selected' : ''; ?>>Leden</option>
          <option value="2" <?php echo (date('n') == 2) ? 'selected' : ''; ?>>Únor</option>
          <option value="3" <?php echo (date('n') == 3) ? 'selected' : ''; ?>>Březen</option>
          <option value="4" <?php echo (date('n') == 4) ? 'selected' : ''; ?>>Duben</option>
          <option value="5" <?php echo (date('n') == 5) ? 'selected' : ''; ?>>Květen</option>
          <option value="6" <?php echo (date('n') == 6) ? 'selected' : ''; ?>>Červen</option>
          <option value="7" <?php echo (date('n') == 7) ? 'selected' : ''; ?>>Červenec</option>
          <option value="8" <?php echo (date('n') == 8) ? 'selected' : ''; ?>>Srpen</option>
          <option value="9" <?php echo (date('n') == 9) ? 'selected' : ''; ?>>Září</option>
          <option value="10" <?php echo (date('n') == 10) ? 'selected' : ''; ?>>Říjen</option>
          <option value="11" <?php echo (date('n') == 11) ? 'selected' : ''; ?>>Listopad</option>
          <option value="12" <?php echo (date('n') == 12) ? 'selected' : ''; ?>>Prosinec</option>
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
              <label for="zeme-cz"><img src="/assets/img/flags/cz.svg" alt="CZ" width="18" height="12" style="vertical-align: middle; margin-right: 4px;">Česko</label>
            </div>
            <div class="multiselect-option" role="option">
              <input type="checkbox" id="zeme-sk" value="sk" checked>
              <label for="zeme-sk">SK Slovensko</label>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div class="filter-actions">
      <div style="display: flex; align-items: center; gap: 12px; margin-right: auto; flex-wrap: wrap;">
        <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:0.75rem;color:#666;white-space:nowrap;">
          <input type="checkbox" id="zobrazitOdmenu" checked style="cursor:pointer;margin:0;">
          Odměna technika v PDF
        </label>
        <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:0.75rem;color:#666;white-space:nowrap;">
          <input type="checkbox" id="zobrazitMimozarucni" checked style="cursor:pointer;margin:0;">
          Mimozáruční servisy
        </label>
        <label style="display:flex;align-items:center;gap:5px;cursor:pointer;font-size:0.75rem;color:#666;white-space:nowrap;">
          <input type="checkbox" id="zobrazitPouzeDokoncene" style="cursor:pointer;margin:0;">
          Pouze dokončené
        </label>
      </div>
      <button class="btn" data-action="resetovitFiltry">Reset</button>
      <button class="btn btn-export" data-action="exportovatPDF">Exportovat PDF</button>
    </div>
    </div><!-- /.filters-inner -->
  </div>

  <!-- Hlavní tabulka zakázek -->
  <div class="stats-table-wrapper">
    <div class="table-header">
      <div class="table-title">Zakázky podle filtrů</div>
      <div class="table-count" id="table-count">0 zakázek</div>
    </div>

    <div class="table-inner">
    <div id="table-container" aria-live="polite">
      <div class="loading" role="status">Načítání dat...</div>
    </div>

    <!-- Stránkování -->
    <nav class="pagination" id="pagination" style="display: none;" aria-label="Stránkování">
      <button id="prev-page" data-action="predchoziStranka" aria-label="Předchozí strana">← Předchozí</button>
      <span class="page-info" id="page-info" aria-live="polite">Strana 1 z 1</span>
      <button id="next-page" data-action="dalsiStranka" aria-label="Další strana">Další →</button>
    </nav>
    </div><!-- /.table-inner -->
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

    <!-- Statistiky techniků - REKLAMACE -->
    <div class="chart-card">
      <div class="chart-title">Statistiky techniků - REKLAMACE</div>
      <div class="chart-content" id="chart-technicians-reklamace" aria-live="polite">
        <div class="loading" role="status">Načítání...</div>
      </div>
    </div>

    <!-- Statistiky techniků - POZ -->
    <div class="chart-card">
      <div class="chart-title">Statistiky techniků - POZ</div>
      <div class="chart-content" id="chart-technicians-poz" aria-live="polite">
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

<script src="https://unpkg.com/htmx.org@2.0.4/dist/htmx.min.js" defer
        integrity="sha384-HGfztofotfshcF7+8n44JQL2oJmowVChPTg48S+jvZoztPfvwD79OC/LTtG6dMp+"
        crossorigin="anonymous"></script>
<script src="assets/js/logger.min.js" defer></script>
<script src="assets/js/utils.min.js" defer></script>
<script src="assets/js/statistiky.min.js?v=<?= filemtime(__DIR__ . '/assets/js/statistiky.min.js') ?>" defer></script>

<!-- HTMX: Přepsat nactiSummary pro server-rendered HTML karty (Step 146)
     Pořadí listenerů DOMContentLoaded:
     1. Tento inline script (registrován dříve) → přepíše nactiSummary
     2. statistiky.min.js listener (registrován po deferred načtení) → zavolá nactiSummary()
     Výsledek: statistiky.min.js zavolá HTMX verzi — žádné dvojité volání. -->
<script>
window.addEventListener('DOMContentLoaded', function() {
    if (typeof htmx === 'undefined' || typeof getFilterParams === 'undefined') return;

    // Přepsat PŘED voláním ze statistiky.min.js listeneru (ten se spustí jako druhý)
    window.nactiSummary = function() {
        var filterParams = getFilterParams();
        htmx.ajax('GET', '/api/statistiky_html.php?' + filterParams, {
            target: '#stats-summary-container',
            swap: 'innerHTML'
        });
    };
    // NEvolat zde — statistiky.min.js ji zavolá sám ve svém DOMContentLoaded listeneru
});
</script>

<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
</body>
</html>
