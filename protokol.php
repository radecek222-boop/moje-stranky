<?php
require_once "init.php";

// BEZPEČNOST: Kontrola přihlášení (admin nebo technik)
$isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
if (!$isLoggedIn) {
    header('Location: login.php?redirect=protokol.php');
    exit;
}

/**
 * Escapes output for safe HTML rendering.
 */
function wgs_escape($value): string
{
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/**
 * Builds a printable address from individual columns.
 */
function wgs_format_address(array $record): string
{
    if (!empty($record['adresa'])) {
        return $record['adresa'];
    }

    $parts = [];
    foreach (['ulice', 'mesto', 'psc'] as $key) {
        if (!empty($record[$key])) {
            $parts[] = trim($record[$key]);
        }
    }

    return implode(', ', array_filter($parts, function ($part) {
        return $part !== '';
    }));
}

/**
 * Formats the billing destination label.
 */
function wgs_format_fakturace_label(?string $value): string
{
    $code = strtoupper(trim((string)$value));

    switch ($code) {
        case 'CZ':
            return '🇨🇿 Česká republika (CZ)';
        case 'SK':
            return '🇸🇰 Slovensko (SK)';
        default:
            return '';
    }
}

// Získat jméno přihlášeného uživatele pro pole "Technik"
$currentUserName = $_SESSION['user_name'] ?? '';

// DEBUG: Vypsat co je v session
error_log("=== PROTOKOL.PHP DEBUG ===");
error_log("SESSION user_name: " . ($currentUserName ?: 'PRÁZDNÉ'));
error_log("SESSION celá: " . print_r($_SESSION, true));
error_log("=========================");

$prefillFields = [
    'order_number' => '',
    'claim_number' => '',
    'customer' => '',
    'address' => '',
    'phone' => '',
    'email' => '',
    'brand' => '',
    'model' => '',
    'description' => '',
    'fakturace' => '',
    'technician' => $currentUserName, // Automaticky předvyplnit podle přihlášeného uživatele
];

$initialBootstrapData = null;
$initialBootstrapJson = '';

$requestedId = $_GET['id'] ?? null;
$lookupValue = null;

if (is_string($requestedId)) {
    $requestedId = trim($requestedId);

    if ($requestedId !== '') {
        // Přípustné jsou i ID se znaky jako "/" nebo "." (např. WGS-2024/001)
        $lookupValue = mb_substr($requestedId, 0, 120, 'UTF-8');
    }
}

if ($lookupValue !== null) {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare(
            "SELECT * FROM wgs_reklamace WHERE reklamace_id = :value OR cislo = :value OR id = :value LIMIT 1"
        );
        $stmt->execute([':value' => $lookupValue]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($record) {
            $address = wgs_format_address($record);
            $customerName = $record['jmeno'] ?? $record['zakaznik'] ?? '';

            if (empty($record['adresa']) && $address !== '') {
                $record['adresa'] = $address;
            }

            if (empty($record['jmeno']) && !empty($record['zakaznik'])) {
                $record['jmeno'] = $record['zakaznik'];
            }

            if (empty($record['zakaznik']) && !empty($record['jmeno'])) {
                $record['zakaznik'] = $record['jmeno'];
            }

            $prefillFields = [
                // Základní identifikátory
                // Order number = Interní WGS číslo (reklamace_id)
                'order_number' => $record['reklamace_id'] ?? '',
                // Claim number = Číslo zakázky zadané uživatelem (cislo)
                'claim_number' => $record['cislo'] ?? '',

                // Kontaktní údaje
                'customer' => $customerName,
                'address' => $record['adresa'] ?? $address,
                'phone' => $record['telefon'] ?? '',
                'email' => $record['email'] ?? '',

                // Produktové údaje
                'brand' => $record['prodejce'] ?? '', // Zadavatel = jméno prodejce
                'model' => $record['model'] ?? '',
                'typ' => $record['typ'] ?? '',
                'provedeni' => $record['provedeni'] ?? '',
                'barva' => $record['barva'] ?? '',
                'seriove_cislo' => $record['seriove_cislo'] ?? '',

                // Reklamace info
                'description' => $record['popis_problemu'] ?? '',
                'doplnujici_info' => $record['doplnujici_info'] ?? '',

                // Datumy
                'datum_prodeje' => $record['datum_prodeje'] ?? '',
                'datum_reklamace' => $record['datum_reklamace'] ?? '',
                'claim_date' => $record['datum_reklamace'] ?? '', // Pro pole id="claim-date"
                'delivery_date' => $record['datum_prodeje'] ?? '', // Pro pole id="delivery-date"

                // Technik - pokud je uložený, použít ho, jinak použít aktuálního uživatele
                'technician' => $record['technik'] ?? $currentUserName,

                // Fakturace
                'fakturace' => wgs_format_fakturace_label($record['fakturace_firma'] ?? ''),
            ];

            $initialBootstrapData = $record;
        }
    } catch (Exception $e) {
        error_log('Protokol prefill failed: ' . $e->getMessage());
    }
}

if ($initialBootstrapData) {
    $json = json_encode($initialBootstrapData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json !== false) {
        $initialBootstrapJson = str_replace('</', '<\/', $json);
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"><meta http-equiv="Pragma" content="no-cache"><meta http-equiv="Expires" content="0">
<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.js"></script>

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

<?php if ($initialBootstrapJson): ?>
<script id="initialReklamaceData" type="application/json"><?= $initialBootstrapJson; ?></script>
<?php endif; ?>

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
        <tr><td class="label">Číslo objednávky<span class="en-label">Order number</span></td><td><input type="text" id="order-number" value="<?= wgs_escape($prefillFields['order_number']); ?>" readonly></td></tr>
        <tr><td class="label">Číslo reklamace<span class="en-label">Claim number</span></td><td><input type="text" id="claim-number" value="<?= wgs_escape($prefillFields['claim_number']); ?>" readonly></td></tr>
        <tr><td class="label">Zákazník<span class="en-label">Customer</span></td><td><input type="text" id="customer" value="<?= wgs_escape($prefillFields['customer']); ?>" readonly></td></tr>
        <tr><td class="label">Adresa<span class="en-label">Address</span></td><td><input type="text" id="address" value="<?= wgs_escape($prefillFields['address']); ?>" readonly></td></tr>
        <tr><td class="label">Telefon<span class="en-label">Phone</span></td><td><input type="tel" id="phone" value="<?= wgs_escape($prefillFields['phone']); ?>" readonly></td></tr>
        <tr><td class="label">Email<span class="en-label">Email</span></td><td><input type="email" id="email" value="<?= wgs_escape($prefillFields['email']); ?>" readonly></td></tr>
        <tr><td class="label">Fakturace<span class="en-label">Billing</span></td><td><input type="text" id="fakturace-firma" value="<?= wgs_escape($prefillFields['fakturace']); ?>" readonly></td></tr>
      </table>
    </div>

    <div class="col">
      <table>
        <tr><td class="label">Technik<span class="en-label">Technician</span></td>
          <td>
            <select id="technician">
            <?php
              $technici = ['Milan Kolín', 'Radek Zikmund', 'Kolín/Zikmund'];
              $selectedTechnik = $prefillFields['technician'];

              // DEBUG výpis
              error_log("SELECT TECHNIK DEBUG: selectedTechnik = '$selectedTechnik'");

              // Přidat přihlášeného uživatele pokud není v seznamu
              if ($selectedTechnik && !in_array($selectedTechnik, $technici)) {
                $technici[] = $selectedTechnik;
                error_log("Přidávám technika do seznamu: $selectedTechnik");
              }

              foreach ($technici as $technik) {
                $selected = ($technik === $selectedTechnik) ? ' selected' : '';
                error_log("Option: '$technik' | Selected: " . ($selected ? 'ANO' : 'NE'));
                echo '<option' . $selected . '>' . wgs_escape($technik) . '</option>';
              }
            ?>
          </select></td></tr>
        <tr><td class="label">Datum návštěvy<span class="en-label">Visit date</span></td><td><input type="date" id="visit-date"></td></tr>
        <tr><td class="label">Datum doručení<span class="en-label">Delivery date</span></td><td><input type="date" id="delivery-date"></td></tr>
        <tr><td class="label">Datum reklamace<span class="en-label">Claim date</span></td><td><input type="date" id="claim-date"></td></tr>
        <tr><td class="label">Značka/Contract<span class="en-label">Brand</span></td><td><input type="text" id="brand" value="<?= wgs_escape($prefillFields['brand']); ?>"></td></tr>
        <tr><td class="label">Model<span class="en-label">Model</span></td><td><input type="text" id="model" value="<?= wgs_escape($prefillFields['model']); ?>"></td></tr>
      </table>
    </div>
  </div>

  <div class="section-title">Zákazník reklamuje<span class="en-label">CUSTOMER COMPLAINT</span></div>
  <div class="split-section">
    <textarea id="description-cz" placeholder="Popis reklamace česky..."><?= wgs_escape($prefillFields['description']); ?></textarea>
    <textarea id="description-en" placeholder="Automatický překlad..." readonly></textarea>
  </div>

  <div class="section-title">Problém zjištěný technikem<span class="en-label">DETECTED PROBLEM</span></div>
  <div class="split-section">
    <textarea id="problem-cz" placeholder="Zjištěný problém česky..."></textarea>
    <textarea id="problem-en" placeholder="Automatický překlad..." readonly></textarea>
  </div>

  <div class="section-title">Návrh opravy<span class="en-label">REPAIR PROPOSAL</span></div>
  <div class="split-section">
    <textarea id="repair-cz" placeholder="Návrh opravy česky..."></textarea>
    <textarea id="repair-en" placeholder="Automatický překlad..." readonly></textarea>
  </div>

  <div class="two-col-table">
    <div class="col">
      <table>
        <tr><td class="label">Počet dílů<span class="en-label">Parts</span></td><td><input type="text" id="parts" placeholder="0"></td></tr>
        <tr><td class="label">Práce<span class="en-label">Work</span></td><td><input type="text" id="price-work" placeholder="0.00" oninput="updateTotal()"></td></tr>
        <tr><td class="label">Materiál<span class="en-label">Materiál</span></td><td><input type="text" id="price-material" placeholder="0.00" oninput="updateTotal()"></td></tr>
        <tr><td class="label">2. technik<span class="en-label">Second tech.</span></td><td><input type="text" id="price-second" placeholder="0.00" oninput="updateTotal()"></td></tr>
        <tr><td class="label">Doprava<span class="en-label">Transport</span></td><td><input type="text" id="price-transport" placeholder="0.00" oninput="updateTotal()"></td></tr>
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

  <div class="section-title">Podpis zákazníka<span class="en-label">Customer signature</span></div>
  <div class="signature-container">
    <canvas id="signature-pad"></canvas>
    <button class="btn-clear" type="button" data-action="clearSignaturePad">Vymazat podpis</button>
  </div>
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
  <div class="loading-text" id="loadingText">Načítání...</div>
</div>
</main>

<!-- Lokální signature-pad (nahrazuje blokovaný CDN) -->
<script src="assets/js/signature-pad-simple.js"></script>
<!-- Fix pro globální scope signaturePad -->
<script src="assets/js/protokol-signature-fix.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js" defer></script>

<script src="assets/js/csrf-auto-inject.js" defer></script>

<!-- EMERGENCY DIAGNOSTIC SCRIPT -->
<script>
(function() {
  console.log('🚨 EMERGENCY DIAGNOSTICS STARTING...');

  // FORCE HIDE LOADING OVERLAY IMMEDIATELY
  window.addEventListener('DOMContentLoaded', function() {
    const overlay = document.getElementById('loadingOverlay');
    if (overlay) {
      overlay.classList.remove('show');
      overlay.style.display = 'none';
      console.log('✅ Loading overlay force-hidden');
    } else {
      console.error('❌ Loading overlay NOT FOUND');
    }

    // Check initial data
    const dataNode = document.getElementById('initialReklamaceData');
    if (dataNode) {
      console.log('✅ initialReklamaceData found');
      const raw = (dataNode.textContent || dataNode.innerText || '').trim();
      console.log('📦 Raw data length:', raw.length);
      console.log('📦 Raw data preview:', raw.substring(0, 200));

      try {
        const parsed = JSON.parse(raw);
        console.log('✅ JSON parsed successfully');
        console.log('📋 Parsed data:', parsed);
      } catch (e) {
        console.error('❌ JSON parse failed:', e);
      }
    } else {
      console.error('❌ initialReklamaceData NOT FOUND');
    }

    // Check all form fields
    const fieldIds = ['order-number', 'claim-number', 'customer', 'address', 'phone', 'email', 'brand', 'model', 'technician'];
    console.log('🔍 Checking form fields:');
    fieldIds.forEach(id => {
      const field = document.getElementById(id);
      if (field) {
        console.log(`  ✅ ${id}: "${field.value}"`);
      } else {
        console.error(`  ❌ ${id}: NOT FOUND`);
      }
    });

    // Check signature pad
    const canvas = document.getElementById('signature-pad');
    if (canvas) {
      console.log('✅ Signature pad canvas found');
      console.log('  Canvas size:', canvas.offsetWidth, 'x', canvas.offsetHeight);
    } else {
      console.error('❌ Signature pad canvas NOT FOUND');
    }

    console.log('🚨 EMERGENCY DIAGNOSTICS COMPLETE');
  });
})();
</script>

<!-- External JavaScript -->
<script src="assets/js/protokol-data-patch.js" defer></script>
<script src="assets/js/protokol.min.js" defer></script>
<script src="assets/js/protokol-fakturace-patch.js" defer></script>
<!-- Fix pro tlačítka (načíst až po protokol.min.js) -->
<script src="assets/js/protokol-buttons-fix.js" defer></script>
</body>
</html>
