<?php require_once "init.php"; ?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PSA Kalkulátor | White Glove Service</title>
  <meta name="description" content="PSA modul White Glove Service. Správa a kalkulace PSA (Prodej Služeb po Aplikaci) pro servisní techniky.">

  <!-- Google Fonts - Poppins -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=optional" rel="stylesheet">

  <!-- QR Code Library -->
  <script src="https://unpkg.com/qrcodejs@1.0.0/qrcode.min.js"></script>

  <link rel="stylesheet" href="assets/css/psa-kalkulator.min.css">
  <link rel="stylesheet" href="assets/css/admin-header.min.css">
  <!-- Univerzální tmavý styl pro všechny modály -->
  <link rel="stylesheet" href="assets/css/universal-modal-theme.min.css">
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<main id="main-content" class="main-content">
<div class="container">

  <!-- HEADER -->
  <div class="page-header">
    <h1 class="page-title">PSA Kalkulátor</h1>
    <div class="period-selector">
      <select class="form-select" id="periodSelect" data-onchange="changePeriod">
        <option value="2025-11">Listopad 2025</option>
        <option value="2025-12">Prosinec 2025</option>
      </select>
      <div class="period-display" id="periodDisplay">Listopad 2025</div>
    </div>
  </div>

  <!-- KONFIGURACE SAZEB -->
  <div class="card">
    <div class="card-header">
      <h2 class="card-title">Konfigurace sazeb</h2>
    </div>
    <div class="card-body">
      <div class="rates-grid">
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
  <div id="qrModal" class="modal" role="dialog" aria-modal="true" aria-labelledby="qrModalTitle">
    <div class="modal-content">
      <div class="modal-header">
        <h2 class="modal-title" id="qrModalTitle">QR Kódy pro platby</h2>
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
</main>

<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.min.js" defer></script>

<!-- Main JavaScript -->
<script src="assets/js/psa-kalkulator.min.js" defer></script>

</body>
</html>
