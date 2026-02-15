<?php
require_once "init.php";
require_once __DIR__ . '/includes/csrf_helper.php';

// BEZPEČNOST: Kontrola přihlášení (admin, technik, prodejce)
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$isLoggedIn = isset($_SESSION['user_id']) || $isAdmin;

if (!$isLoggedIn) {
    header('Location: login.php?redirect=psa-kalkulator.php');
    exit;
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <title>PSA Kalkulátor | White Glove Service</title>
  <meta name="description" content="PSA kalkulačka White Glove Service. Výpočet cen a generování QR kódů pro platby PSA služeb.">

  <!-- Google Fonts - Poppins -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/psa-kalkulator.min.css?v=<?= filemtime(__DIR__ . '/assets/css/psa-kalkulator.min.css') ?>">
  <link rel="stylesheet" href="assets/css/universal-modal-theme.min.css?v=<?= filemtime(__DIR__ . '/assets/css/universal-modal-theme.min.css') ?>">
</head>
<body>
<main id="main-content">

<!-- TOP BAR -->
<div class="top-bar">
  <div class="top-bar-container">
    <a href="index.php" class="logo">
      WGS
      <span>WHITE GLOVE SERVICE</span>
    </a>
    <div class="page-title-header">PSA KALKULÁTOR</div>
  </div>
</div>

<!-- MAIN CONTENT -->
<div class="container">

  <!-- FILTR OBDOBÍ -->
  <div class="period-filter-card" style="display: flex; justify-content: flex-end; align-items: center; flex-wrap: wrap; gap: 1rem;">
    <!-- Historie -->
    <div class="period-display-wrapper">
      <div class="period-filter-label" style="margin-bottom: 0;">Historie</div>
      <div class="period-display clickable" id="periodDisplay" data-action="togglePeriodOverlay" role="button" tabindex="0" title="Klikněte pro výběr období">
        <span id="periodDisplayText">Listopad 2025</span>
        <span class="period-arrow">▼</span>
      </div>
      <!-- Mini-overlay pro výběr období -->
      <div class="period-overlay" id="periodOverlay">
        <div class="period-overlay-header">
          <span>Archiv období</span>
          <button class="period-overlay-close" data-action="closePeriodOverlay" title="Zavřít">&times;</button>
        </div>
        <div class="period-overlay-content" id="periodOverlayContent">
          <!-- Dynamicky generováno JavaScriptem -->
          <div class="period-loading">Načítám období...</div>
        </div>
        <div class="period-overlay-footer">
          <button class="btn btn-sm" data-action="showNewPeriodSelector">+ Přidat období</button>
          <button class="btn btn-sm btn-secondary" data-action="closePeriodOverlay">Zavřít</button>
        </div>
      </div>
    </div>
  </div>

  <!-- KONFIGURACE SAZEB -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Globální nastavení sazeb</h2>
    </div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Sazba výplaty (Kč/hodina)</label>
          <input type="number" class="form-input" id="salaryRate" value="150" min="0" data-onchange="updateRates">
        </div>
        <div class="form-group">
          <label class="form-label">Sazba fakturace (Kč/hodina)</label>
          <input type="number" class="form-input" id="invoiceRate" value="250" min="0" data-onchange="updateRates">
        </div>
      </div>
    </div>
  </div>

  <!-- SOUHRN -->
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-value" id="totalHours">0</div>
      <div class="stat-label">Celkem hodin</div>
    </div>

    <div class="stat-card">
      <div class="stat-value" id="totalSalary">0 Kč</div>
      <div class="stat-label">Výplaty celkem</div>
      <div class="stat-info" id="salaryRateInfo">150 Kč/hodina</div>
    </div>

    <div class="stat-card">
      <div class="stat-value" id="totalInvoice">0 Kč</div>
      <div class="stat-label">Fakturace celkem</div>
      <div class="stat-info" id="invoiceRateInfo">250 Kč/hodina</div>
    </div>

    <div class="stat-card">
      <div class="stat-value" id="employeeCount">0</div>
      <div class="stat-label">Počet zaměstnanců</div>
    </div>

    <div class="stat-card highlight">
      <div class="stat-value" id="totalProfit">0 Kč</div>
      <div class="stat-label">Zisk</div>
      <div class="stat-info" id="profitMargin">Marže: 0%</div>
    </div>

    <div class="stat-card">
      <div class="stat-value" id="avgHoursPerEmployee">0h</div>
      <div class="stat-label">Průměr/zaměstnanec</div>
      <div class="stat-info" id="avgSalaryPerEmployee">0 Kč</div>
    </div>
  </div>

  <!-- TABULKA ZAMĚSTNANCŮ -->
  <div class="card">
    <div class="card-header" style="flex-direction: column; align-items: flex-start; gap: 0.5rem;">
      <h2 class="card-title" style="margin: 0;">Seznam zaměstnanců</h2>
      <button class="btn btn-sm" data-action="addEmployee" style="font-size: 0.75rem; padding: 0.25rem 0.75rem;">+ Přidat zaměstnance</button>
    </div>
    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th scope="col">Jméno</th>
            <th scope="col" class="text-center">Hodiny</th>
            <th scope="col" class="text-right">Výplata</th>
            <th scope="col" class="text-right">Faktura</th>
            <th scope="col">Číslo účtu</th>
            <th scope="col">Kód banky</th>
            <th scope="col" class="text-center">Akce</th>
          </tr>
        </thead>
        <tbody id="employeeTableBody" aria-live="polite">
          <tr>
            <td colspan="7" class="text-center loading-cell" role="status">
              <div class="loading"></div>
              Načítání dat...
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="total-row">
            <td><strong id="footerMonthLabel">CELKEM za Listopad</strong></td>
            <td class="text-center"><strong id="footerTotalHours">0</strong></td>
            <td class="text-right"><strong id="footerTotalSalary">0 Kč</strong></td>
            <td class="text-right"><strong id="footerTotalInvoice">0 Kč</strong></td>
            <td colspan="3"></td>
          </tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- AKČNÍ TLAČÍTKA -->
  <div class="action-buttons">
    <button class="btn btn-secondary" data-action="clearAll">Vynulovat hodiny</button>
    <button class="btn" data-action="saveData">Uložit období</button>
    <button class="btn" data-action="generatePaymentQR">Generovat QR platby</button>
    <button class="btn btn-secondary" data-action="exportToExcel">Export CSV</button>
    <button class="btn btn-secondary" data-action="printReport">Tisk</button>
  </div>

  <!-- QR CODE MODAL -->
  <div id="qrModal" class="modal hidden" role="dialog" aria-modal="true" data-action="closeQRModal">
    <div class="modal-content" onclick="event.stopPropagation()">
      <div class="payment-summary">
        <div id="paymentSummary"></div>
      </div>

      <div id="qrCodesContainer" class="qr-grid"></div>
    </div>
  </div>

</div>

<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.min.js" defer></script>
<!-- Utils (wgsConfirm) -->
<script src="assets/js/utils.min.js" defer></script>
<!-- WGS Toast notifikace -->
<link rel="stylesheet" href="assets/css/wgs-toast.css">
<script src="assets/js/wgs-toast.js" defer></script>
<script>
  window.PSA_CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
</script>
<!-- QR Code library - lokální kopie -->
<script src="assets/js/qrcode.min.js" defer data-qr-lib="1"></script>

<!-- Main JavaScript -->
<script src="assets/js/psa-kalkulator.min.js?v=<?= filemtime(__DIR__ . '/assets/js/psa-kalkulator.min.js') ?>" defer></script>

</body>
</html>
