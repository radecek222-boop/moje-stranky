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
  <title>Statistiky | White Glove Service</title>
  <meta name="description" content="Statistiky a přehledy White Glove Service. Sledujte metriky servisu, úspěšnost oprav, spokojenost zákazníků a výkonnost techniků.">
  
  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">

  <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/statistiky.min.css" as="style">

  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/statistiky.min.css">
  <link rel="stylesheet" href="assets/css/statistiky-fixes.css">
</head>

<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<!-- MAIN CONTENT -->
<main>
<div class="container">

  <!-- USER INFO BAR -->
  <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; padding: 1rem; background: var(--c-white); border: 1px solid var(--c-border);">
    <div>
      <h1 class="page-title" style="margin-bottom: 0;">Statistiky</h1>
      <p class="page-subtitle" style="margin-bottom: 0;">Analýza a reporty reklamací</p>
    </div>
    <div class="user-info">
      <span class="user-name" id="userName">
        <?php echo htmlspecialchars($_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Admin'); ?>
      </span>
    </div>
  </div>
  
  
  <!-- FILTRY -->
  <div class="filters-section">
    <h3 class="filters-section-title">Filtry</h3>
    
    <!-- První řada: ZEMĚ, STAV, OD DATA -->
    <div class="filters-grid">
      <div class="filter-group">
        <label class="filter-label">Země</label>
        <div class="multi-select-wrapper">
          <div class="multi-select-display empty" id="display-country" data-multiselect="country">
            Vyberte země...
          </div>
        </div>
      </div>
      
      <div class="filter-group">
        <label class="filter-label">Stav</label>
        <div class="multi-select-wrapper">
          <div class="multi-select-display empty" id="display-status" data-multiselect="status">
            Vyberte stavy...
          </div>
        </div>
      </div>
      
      <div class="filter-group">
        <label class="filter-label">Od data</label>
        <div class="date-input-wrapper">
          <input type="text" class="filter-input date-input" id="filter-date-from" placeholder="DD.MM.RRRR" readonly>
          <div class="custom-calendar" id="calendar-date-from"></div>
        </div>
      </div>
    </div>
    
    <!-- Druhá řada: TECHNIK, PRODEJCE, DO DATA -->
    <div class="filters-grid">
      <div class="filter-group">
        <label class="filter-label">Technik</label>
        <div class="multi-select-wrapper">
          <div class="multi-select-display empty" id="display-technician" data-multiselect="technician">
            Vyberte techniky...
          </div>
        </div>
      </div>
      
      <div class="filter-group">
        <label class="filter-label">Prodejce</label>
        <div class="multi-select-wrapper">
          <div class="multi-select-display empty" id="display-salesperson" data-multiselect="salesperson">
            Vyberte prodejce...
          </div>
        </div>
      </div>
      
      <div class="filter-group">
        <label class="filter-label">Do data</label>
        <div class="date-input-wrapper">
          <input type="text" class="filter-input date-input" id="filter-date-to" placeholder="DD.MM.RRRR" readonly>
          <div class="custom-calendar" id="calendar-date-to"></div>
        </div>
      </div>
    </div>
    
    <div class="filter-actions">
      <button class="btn btn-sm" data-action="resetFilters">Reset</button>
      <button class="btn btn-sm btn-success" data-action="applyFilters">Aplikovat</button>
    </div>
  </div>
  
  <!-- CELKOVÉ STATISTIKY -->
  <div class="stats-summary">
    <div class="summary-card">
      <div class="summary-label">Celkem zakázek</div>
      <div class="summary-value" id="total-orders">0</div>
      <div class="summary-subtext">Filtrovaných</div>
    </div>
    
    <div class="summary-card">
      <div class="summary-label">Celkový obrat</div>
      <div class="summary-value" id="total-revenue">0 €</div>
      <div class="summary-subtext">Všechny zakázky</div>
    </div>
    
    <div class="summary-card">
      <div class="summary-label">Průměrná zakázka</div>
      <div class="summary-value" id="avg-order">0 €</div>
      <div class="summary-subtext">Na zakázku</div>
    </div>
    
    <div class="summary-card">
      <div class="summary-label">Aktivní technici</div>
      <div class="summary-value" id="active-techs">0</div>
      <div class="summary-subtext">V období</div>
    </div>
  </div>
  
  <!-- STATISTIKY PRODEJCŮ -->
  <div class="table-container collapsed">
    <div class="table-header" data-action="toggleSection">
      <h3 class="table-title">Statistiky prodejců</h3>
      <div class="table-actions" data-action="stopPropagation">
        <button class="btn btn-sm" data-action="exportSalesStatsExcel">Export Excel</button>
        <button class="btn btn-sm btn-success" data-action="exportSalesStatsPDF">Export PDF</button>
      </div>
    </div>
    
    <div class="table-content">
      <table>
        <thead>
          <tr>
            <th>Prodejce</th>
            <th>Počet zakázek</th>
            <th>Celková částka</th>
            <th>Průměr na zakázku</th>
            <th>CZ / SK</th>
            <th>Hotové %</th>
          </tr>
        </thead>
        <tbody id="sales-stats-table">
          <tr>
            <td colspan="6" style="text-align: center; color: var(--c-grey);">Načítání...</td>
          </tr>
        </tbody>
        <tfoot id="sales-stats-footer"></tfoot>
      </table>
    </div>
  </div>
  
  <!-- STATISTIKY TECHNIKŮ -->
  <div class="table-container collapsed">
    <div class="table-header" data-action="toggleSection">
      <h3 class="table-title">Statistiky techniků</h3>
      <div class="table-actions" data-action="stopPropagation">
        <button class="btn btn-sm" data-action="exportTechStatsExcel">Export Excel</button>
        <button class="btn btn-sm btn-success" data-action="exportTechStatsPDF">Export PDF</button>
      </div>
    </div>
    
    <div class="table-content">
      <table>
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
        <tbody id="tech-stats-table">
          <tr>
            <td colspan="6" style="text-align: center; color: var(--c-grey);">Načítání...</td>
          </tr>
        </tbody>
        <tfoot id="tech-stats-footer"></tfoot>
      </table>
    </div>
  </div>
  
  <!-- STATISTIKY MODELŮ -->
  <div class="table-container collapsed">
    <div class="table-header" data-action="toggleSection">
      <h3 class="table-title">Nejporuchovější modely</h3>
      <div class="table-actions" data-action="stopPropagation">
        <button class="btn btn-sm" data-action="exportModelsStatsExcel">Export Excel</button>
        <button class="btn btn-sm btn-success" data-action="exportModelsStatsPDF">Export PDF</button>
      </div>
    </div>
    
    <div class="table-content">
      <table>
        <thead>
          <tr>
            <th>Model / Výrobek</th>
            <th>Počet reklamací</th>
            <th>Podíl %</th>
            <th>Průměrná částka</th>
            <th>Celková částka</th>
          </tr>
        </thead>
        <tbody id="models-stats-table">
          <tr>
            <td colspan="5" style="text-align: center; color: var(--c-grey);">Načítání...</td>
          </tr>
        </tbody>
        <tfoot id="models-stats-footer"></tfoot>
      </table>
    </div>
  </div>
  
  <!-- FILTROVANÉ ZAKÁZKY -->
  <div class="table-container collapsed">
    <div class="table-header" data-action="toggleSection">
      <h3 class="table-title">Filtrované zakázky</h3>
      <div class="table-actions" data-action="stopPropagation">
        <button class="btn btn-sm" data-action="exportFilteredOrdersExcel">Export Excel</button>
        <button class="btn btn-sm btn-success" data-action="exportFilteredOrdersPDF">Export PDF</button>
      </div>
    </div>
    
    <div class="table-content">
      <table>
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
        <tbody id="filtered-orders-table">
          <tr>
            <td colspan="8" style="text-align: center; color: var(--c-grey);">Načítání...</td>
          </tr>
        </tbody>
        <tfoot id="filtered-orders-footer"></tfoot>
      </table>
    </div>
  </div>
  
  <!-- GRAFY (na konci) -->
  <div class="table-container collapsed">
    <div class="table-header" data-action="toggleSection">
      <h3 class="table-title">Grafy a vizualizace</h3>
      <div class="table-actions" data-action="stopPropagation">
        <button class="btn btn-sm" data-action="exportChartsExcel">Export Excel</button>
        <button class="btn btn-sm btn-success" data-action="exportChartsPDF">Export PDF</button>
      </div>
    </div>
    
    <div class="table-content">
      <div class="charts-section">
        <div class="charts-grid">
          
          <div class="chart-card">
            <h3 class="chart-title">Rozdělení podle měst</h3>
            <div class="chart-container" id="chart-cities"></div>
          </div>
          
          <div class="chart-card">
            <h3 class="chart-title">Rozdělení podle zemí</h3>
            <div class="chart-container" id="chart-countries"></div>
          </div>
          
          <div class="chart-card">
            <h3 class="chart-title">Nejporuchovější modely</h3>
            <div class="chart-container" id="chart-models"></div>
          </div>
          
        </div>
      </div>
    </div>
  </div>
  
</div>

<!-- MULTI-SELECT MODALS -->
<div class="multi-select-modal" id="modal-country">
  <div class="multi-select-box">
    <div class="multi-select-header">
      <h3 class="multi-select-title">Vyberte země</h3>
    </div>
    <div class="multi-select-list" id="list-country"></div>
    <div class="multi-select-footer">
      <div class="multi-select-count" id="count-country">Vybráno: 0</div>
      <div class="multi-select-actions">
        <button class="btn btn-sm btn-secondary" data-multiselect-clear="country">Zrušit výběr</button>
        <button class="btn btn-sm" data-multiselect-close="country">Potvrdit</button>
      </div>
    </div>
  </div>
</div>

<div class="multi-select-modal" id="modal-status">
  <div class="multi-select-box">
    <div class="multi-select-header">
      <h3 class="multi-select-title">Vyberte stavy</h3>
    </div>
    <div class="multi-select-list" id="list-status"></div>
    <div class="multi-select-footer">
      <div class="multi-select-count" id="count-status">Vybráno: 0</div>
      <div class="multi-select-actions">
        <button class="btn btn-sm btn-secondary" data-multiselect-clear="status">Zrušit výběr</button>
        <button class="btn btn-sm" data-multiselect-close="status">Potvrdit</button>
      </div>
    </div>
  </div>
</div>

<div class="multi-select-modal" id="modal-salesperson">
  <div class="multi-select-box">
    <div class="multi-select-header">
      <h3 class="multi-select-title">Vyberte prodejce</h3>
    </div>
    <div class="multi-select-search">
      <input type="text" placeholder="Hledat prodejce..." id="search-salesperson" onkeyup="filterMultiSelect('salesperson')">
    </div>
    <div class="multi-select-list" id="list-salesperson"></div>
    <div class="multi-select-footer">
      <div class="multi-select-count" id="count-salesperson">Vybráno: 0</div>
      <div class="multi-select-actions">
        <button class="btn btn-sm btn-secondary" data-multiselect-clear="salesperson">Zrušit výběr</button>
        <button class="btn btn-sm" data-multiselect-close="salesperson">Potvrdit</button>
      </div>
    </div>
  </div>
</div>

<div class="multi-select-modal" id="modal-technician">
  <div class="multi-select-box">
    <div class="multi-select-header">
      <h3 class="multi-select-title">Vyberte techniky</h3>
    </div>
    <div class="multi-select-search">
      <input type="text" placeholder="Hledat technika..." id="search-technician" onkeyup="filterMultiSelect('technician')">
    </div>
    <div class="multi-select-list" id="list-technician"></div>
    <div class="multi-select-footer">
      <div class="multi-select-count" id="count-technician">Vybráno: 0</div>
      <div class="multi-select-actions">
        <button class="btn btn-sm btn-secondary" data-multiselect-clear="technician">Zrušit výběr</button>
        <button class="btn btn-sm" data-multiselect-close="technician">Potvrdit</button>
      </div>
    </div>
  </div>
</div>

</div>
</main>

<!-- External JavaScript -->
<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.js" defer></script>

<script src="assets/js/statistiky.min.js" defer></script>
</body>
</html>
