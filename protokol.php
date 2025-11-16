<?php
require_once "init.php";

// BEZPEČNOST: Kontrola přihlášení (admin nebo technik)
$isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
if (!$isLoggedIn) {
    header('Location: login.php?redirect=protokol.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"><meta http-equiv="Pragma" content="no-cache"><meta http-equiv="Expires" content="0">
<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.js"></script>

<script src="assets/js/admin-auth.js"></script>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#020611">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="WGS">
<meta name="description" content="Servisní protokol White Glove Service pro záznám údajů o reklamacích, opravách a údržbě nábytku Natuzzi. Profesionální dokumentace servisu.">
<meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">

<!-- PWA -->
<link rel="manifest" href="./manifest.json">
<link rel="apple-touch-icon" href="./icon192.png">
<link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
<link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">

<title>Protokol – White Glove Service</title>

<!-- Google Fonts - Natuzzi style -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">

  <!-- Preload critical CSS -->
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/protokol.css" as="style">

  <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/protokol.css">
</head>

<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>
<!-- ČERNÁ HORNÍ PANEL -->

<main>
<div class="wrapper">
  <div class="header">
    <div>WHITE GLOVE SERVICE</div>
    <div>Do Dubče 364, Běchovice 190 11 · +420 725 965 826 · reklamace@wgs-service.cz · IČO 09769684</div>
  </div>

  <div class="two-col-table">
    <div class="col">
      <table>
        <tr><td class="label">Číslo objednávky<span class="en-label">Order number</span></td><td><input type="text" id="order-number" readonly></td></tr>
        <tr><td class="label">Číslo reklamace<span class="en-label">Claim number</span></td><td><input type="text" id="claim-number" readonly></td></tr>
        <tr><td class="label">Zákazník<span class="en-label">Customer</span></td><td><input type="text" id="customer" readonly></td></tr>
        <tr><td class="label">Adresa<span class="en-label">Address</span></td><td><input type="text" id="address" readonly></td></tr>
        <tr><td class="label">Telefon<span class="en-label">Phone</span></td><td><input type="tel" id="phone" readonly></td></tr>
        <tr><td class="label">Email<span class="en-label">Email</span></td><td><input type="email" id="email" readonly></td></tr>
        <tr><td class="label">Fakturace<span class="en-label">Billing</span></td><td><input type="text" id="fakturace-firma" readonly style="font-weight: 600;"></td></tr>
      </table>
    </div>
    
    <div class="col">
      <table>
        <tr><td class="label">Technik<span class="en-label">Technician</span></td>
          <td><select id="technician"><option>Milan Kolín</option><option>Radek Zikmund</option><option>Kolín/Zikmund</option></select></td></tr>
        <tr><td class="label">Datum návštěvy<span class="en-label">Visit date</span></td><td><input type="date" id="visit-date"></td></tr>
        <tr><td class="label">Datum doručení<span class="en-label">Delivery date</span></td><td><input type="date" id="delivery-date"></td></tr>
        <tr><td class="label">Datum reklamace<span class="en-label">Claim date</span></td><td><input type="date" id="claim-date"></td></tr>
        <tr><td class="label">Značka/Contract<span class="en-label">Brand</span></td><td><input type="text" id="brand"></td></tr>
        <tr><td class="label">Model<span class="en-label">Model</span></td><td><input type="text" id="model"></td></tr>
      </table>
    </div>
  </div>

  <div class="section-title">Zákazník reklamuje<span class="en-label">Customer complaint</span></div>
  <div class="split-section">
    <textarea id="description-cz" placeholder="Popis reklamace česky..."></textarea>
    <textarea id="description-en" placeholder="Automatický překlad..." readonly></textarea>
  </div>

  <div class="section-title">Problém zjištěný technikem<span class="en-label">Detected problem</span></div>
  <div class="split-section">
    <textarea id="problem-cz" placeholder="Zjištěný problém česky..."></textarea>
    <textarea id="problem-en" placeholder="Automatický překlad..." readonly></textarea>
  </div>

  <div class="section-title">Návrh opravy<span class="en-label">Repair proposal</span></div>
  <div class="split-section">
    <textarea id="repair-cz" placeholder="Návrh opravy česky..."></textarea>
    <textarea id="repair-en" placeholder="Automatický překlad..." readonly></textarea>
  </div>

  <div class="two-col-table">
    <div class="col">
      <table>
        <tr><td class="label">Počet dílů<span class="en-label">Parts</span></td><td><input type="number" id="parts" min="0"></td></tr>
        <tr><td class="label">Práce<span class="en-label">Work</span></td><td><input type="number" id="price-work" step="0.01" min="0" oninput="updateTotal()"></td></tr>
        <tr><td class="label">Materiál<span class="en-label">Materiál</span></td><td><input type="number" id="price-material" step="0.01" min="0" oninput="updateTotal()"></td></tr>
        <tr><td class="label">2. technik<span class="en-label">Second tech.</span></td><td><input type="number" id="price-second" step="0.01" min="0" oninput="updateTotal()"></td></tr>
        <tr><td class="label">Doprava<span class="en-label">Transport</span></td><td><input type="number" id="price-transport" step="0.01" min="0" oninput="updateTotal()"></td></tr>
        <tr><td class="label"><strong>Celkem</strong><span class="en-label">Total</span></td><td><input type="text" id="price-total" readonly style="font-weight:700;"></td></tr>
      </table>
    </div>
    
    <div class="col">
      <table>
        <tr><td class="label">Vyřešeno?<span class="en-label">Solved?</span></td><td><select id="solved"><option>ANO</option><option>NE</option></select></td></tr>
        <tr><td class="label">Čeká se na prodejce?<span class="en-label">Waiting dealer?</span></td><td><select id="dealer"><option>NE</option><option>ANO</option></select></td></tr>
        <tr><td class="label">Poškození technikem?<span class="en-label">Damage by tech?</span></td><td><select id="damage"><option>NE</option><option>ANO</option></select></td></tr>
        <tr><td class="label">Platí zákazník?<span class="en-label">Customer pays?</span></td><td><select id="payment"><option>NE</option><option>ANO</option></select></td></tr>
        <tr><td class="label">Datum podpisu<span class="en-label">Signature date</span></td><td><input type="date" id="sign-date"></td></tr>
      </table>
    </div>
  </div>

  <button class="btn-clear" type="button" data-action="signaturePad.clear">Vymazat podpis</button>
  <div class="section-title">Podpis zákazníka<span class="en-label">Customer signature</span></div>
  <canvas id="signature-pad"></canvas>
  <div class="signature-label">Podepište se prstem nebo myší</div>
  <div class="gdpr-clause" style="margin-top: 10px; padding: 8px; font-size: 8px; line-height: 1.4; color: #666; border-top: 1px solid #ddd; text-align: justify;">
    <strong>Ochrana osobních údajů (GDPR):</strong> Podpisem tohoto protokolu souhlasíte se zpracováním Vašich osobních údajů společností White Glove Service za účelem poskytování servisních služeb, komunikace s výrobcem, prodejcem a dalšími techniky. Vaše údaje budou zpracovávány v souladu s GDPR a budou použity pouze pro účely vyřízení této reklamace. Máte právo na přístup k údajům, jejich opravu nebo výmaz. Více na www.wgs-service.cz/gdpr
  </div>

  <div class="btns">
    <button class="btn btn-primary" data-action="attachPhotos">Přidat fotky</button>
    <button class="btn btn-primary" data-action="exportBothPDFs">Export 2x PDF</button>
    
    <button class="btn" data-action="sendToCustomer">Odeslat zákazníkovi</button>
    <button class="btn" data-navigate="seznam.php">Zpět</button>
  </div>

  <div id="notif" class="notif"></div>
</div>

<div class="loading-overlay" id="loadingOverlay">
  <div class="loading-spinner"></div>
</div>
</main>

<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.6/dist/signature_pad.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js" defer></script>

<script src="assets/js/csrf-auto-inject.js" defer></script>

<!-- Data Loading Fix: Clear localStorage if URL has different ID -->
<script>
// PATCH 1: Tento script se spustí PŘED protokol.min.js aby vyčistil zastaralá data
(function() {
  const urlParams = new URLSearchParams(window.location.search);
  const urlId = urlParams.get('id');

  if (urlId) {
    const storedData = localStorage.getItem('currentCustomer');
    if (storedData) {
      try {
        const customer = JSON.parse(storedData);
        const storedId = customer.reklamace_id || customer.cislo || customer.id;

        // Pokud URL ID ≠ localStorage ID, vymazat localStorage
        if (storedId !== urlId) {
          console.log('🗑️ FIX: Mazání zastaralých dat z localStorage (', storedId, '≠', urlId, ')');
          localStorage.removeItem('currentCustomer');
        } else {
          console.log('✅ FIX: localStorage obsahuje správná data pro', urlId);
        }
      } catch (e) {
        console.warn('⚠️ FIX: Chyba při parsování localStorage, mažu data');
        localStorage.removeItem('currentCustomer');
      }
    }
  }
})();

// PATCH 2: Oprava currentReklamaceId po načtení dat z API
window.addEventListener('DOMContentLoaded', function() {
  setTimeout(function() {
    // Opravit currentReklamaceId pokud používá špatné ID
    if (window.currentReklamace) {
      const correctId = window.currentReklamace.reklamace_id || window.currentReklamace.cislo || window.currentReklamace.id;
      if (window.currentReklamaceId !== correctId) {
        console.log('🔧 FIX: Opravuji currentReklamaceId z', window.currentReklamaceId, 'na', correctId);
        window.currentReklamaceId = correctId;

        // Reload fotek se správným ID
        if (typeof window.loadPhotosFromDatabase === 'function') {
          console.log('📸 FIX: Reload fotek s opraveným ID:', correctId);
          window.loadPhotosFromDatabase(correctId);
        }
      }
    }
  }, 500); // Čekat 500ms až se protokol.min.js načte a spustí
});
</script>

<!-- External JavaScript -->
<script src="assets/js/protokol.min.js" defer></script>
<script src="assets/js/protokol-fakturace-patch.js" defer></script>
</body>
</html>
