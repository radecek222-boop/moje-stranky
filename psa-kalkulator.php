<?php
require_once "init.php";
require_once __DIR__ . '/includes/csrf_helper.php';
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PSA Kalkulátor | White Glove Service</title>
  <meta name="description" content="PSA kalkulačka White Glove Service. Výpočet cen a generování QR kódů pro platby PSA služeb.">

  <!-- Google Fonts - Poppins -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="dns-prefetch" href="https://cdn.jsdelivr.net">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=optional" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/psa-kalkulator.min.css">
</head>
<body>
<main>

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

  <!-- PAGE HEADER -->
  <div class="page-header">
    <div>
      <h1 class="title">Kalkulátor Mezd PSA</h1>
      <p class="subtitle">Výpočet mezd a fakturace pro zaměstnance</p>
    </div>
    <div class="period-selector">
      <select id="monthSelect" class="form-select" data-onchange="updatePeriod">
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
        <option value="11">Listopad</option>
        <option value="12">Prosinec</option>
      </select>
      <select id="yearSelect" class="form-select" data-onchange="updatePeriod">
        <option value="2024">2024</option>
        <option value="2025" selected>2025</option>
        <option value="2026">2026</option>
      </select>
      <div class="period-display" id="periodDisplay">Listopad 2025</div>
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
        <div class="form-group">
          <label class="form-label">Rychlé nastavení</label>
          <select class="form-select" data-onchange="setQuickRate">
            <option value="">Vyberte sazbu...</option>
            <option value="100-200">100/200 Kč</option>
            <option value="150-250">150/250 Kč (výchozí)</option>
            <option value="200-300">200/300 Kč</option>
            <option value="250-400">250/400 Kč</option>
          </select>
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
    <div class="card-header">
      <h2 class="card-title">Seznam zaměstnanců</h2>
      <div class="btn-group">
        <button class="btn btn-sm" data-action="addEmployee">Přidat</button>
        <button class="btn btn-sm" data-action="saveData">Uložit</button>
        <button class="btn btn-sm" data-action="exportToExcel">Export CSV</button>
        <button class="btn btn-sm" data-action="printReport">Tisk</button>
      </div>
    </div>
    <div class="table-container">
      <table class="data-table">
        <thead>
          <tr>
            <th>Jméno</th>
            <th class="text-center">Hodiny</th>
            <th class="text-right">Výplata</th>
            <th class="text-right">Faktura</th>
            <th>Číslo účtu</th>
            <th>Kód banky</th>
            <th class="text-center">Akce</th>
          </tr>
        </thead>
        <tbody id="employeeTableBody">
          <tr>
            <td colspan="7" class="text-center loading-cell">
              <div class="loading"></div>
              Načítání dat...
            </td>
          </tr>
        </tbody>
        <tfoot>
          <tr class="total-row">
            <td><strong>CELKEM</strong></td>
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
    <button class="btn btn-secondary" data-action="clearAll">Vymazat vše</button>
    <button class="btn" data-action="generatePaymentQR">Generovat QR platby</button>
  </div>

  <!-- QR CODE MODAL -->
  <div id="qrModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title">QR Kódy pro platby</h2>
        <span class="close-modal" data-action="closeQRModal" role="button" tabindex="0" aria-label="Zavřít">&times;</span>
      </div>

      <div class="payment-summary">
        <h3>Souhrn plateb</h3>
        <div id="paymentSummary"></div>
      </div>

      <div id="qrCodesContainer" class="qr-grid"></div>
    </div>
  </div>

</div>

<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.min.js" defer></script>
<script>
  window.PSA_CSRF_TOKEN = '<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>';
</script>
<script src="https://cdn.jsdelivr.net/npm/qrcode@1.5.3/build/qrcode.min.js" defer data-qr-lib="1"></script>

<!-- Main JavaScript -->
<script src="assets/js/psa-kalkulator.min.js" defer></script>

</body>
</html>
