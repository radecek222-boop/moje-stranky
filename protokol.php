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
            "SELECT r.*, u.name as created_by_name
             FROM wgs_reklamace r
             LEFT JOIN wgs_users u ON r.created_by = u.id
             WHERE r.reklamace_id = :value OR r.cislo = :value OR r.id = :value
             LIMIT 1"
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
                'brand' => $record['created_by_name'] ?? $record['prodejce'] ?? '', // Zadavatel = kdo vytvořil zakázku
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
<script src="assets/js/logger.min.js"></script>

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
  <link rel="preload" href="assets/css/protokol.min.css" as="style">

  <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/protokol.min.css">
  <!-- protokol-mobile-fixes.css sloučen do protokol.min.css (Step 48) -->
  <link rel="stylesheet" href="assets/css/button-fixes-global.min.css">
  <link rel="stylesheet" href="assets/css/cenik.min.css">
  <link rel="stylesheet" href="assets/css/protokol-calculator-modal.min.css">
  <!-- Univerzální tmavý styl pro všechny modály -->
  <link rel="stylesheet" href="assets/css/universal-modal-theme.min.css">
  <!-- mobile-responsive.css odstraněn - protokol.min.css má vlastní mobilní styly -->
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

  <!-- Rozbalovací sekce zákaznických dat -->
  <div class="customer-info-collapsible">
    <div class="customer-info-header" id="customerInfoToggle">
      <span class="customer-info-name" id="customerInfoName"><?= wgs_escape($prefillFields['customer']) ?: 'Zákazník' ?></span>
      <svg class="customer-info-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <polyline points="6 9 12 15 18 9"></polyline>
      </svg>
    </div>

    <div class="customer-info-content" id="customerInfoContent">
      <div class="two-col-table">
        <div class="col">
          <table>
            <tr><td class="label">Číslo objednávky<span class="en-label">Order number</span></td><td><input type="text" id="order-number" value="<?= wgs_escape($prefillFields['order_number']); ?>" readonly></td></tr>
            <tr><td class="label">Číslo reklamace<span class="en-label">Claim number</span></td><td><input type="text" id="claim-number" value="<?= wgs_escape($prefillFields['claim_number']); ?>" readonly></td></tr>
            <tr><td class="label">Zákazník<span class="en-label">Customer</span></td><td><input type="text" id="customer" value="<?= wgs_escape($prefillFields['customer']); ?>" readonly></td></tr>
            <tr><td class="label">Adresa<span class="en-label">Address</span></td><td><input type="text" id="address" value="<?= wgs_escape($prefillFields['address']); ?>" readonly></td></tr>
            <tr><td class="label">Telefon<span class="en-label">Phone</span></td><td><input type="tel" id="phone" value="<?= wgs_escape($prefillFields['phone']); ?>" readonly></td></tr>
            <tr><td class="label">Email<span class="en-label">Email</span></td><td><input type="email" id="email" value="<?= wgs_escape($prefillFields['email']); ?>" readonly></td></tr>
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
            <tr><td class="label">Datum doručení<span class="en-label">Delivery date</span></td><td><input type="date" id="delivery-date"></td></tr>
            <tr><td class="label">Datum reklamace<span class="en-label">Claim date</span></td><td><input type="date" id="claim-date"></td></tr>
            <tr><td class="label">Zadavatel<span class="en-label">Requester</span></td><td><input type="text" id="brand" placeholder="Prodejce" value="<?= wgs_escape($prefillFields['brand']); ?>"></td></tr>
            <tr><td class="label">Model<span class="en-label">Model</span></td><td><input type="text" id="model" value="<?= wgs_escape($prefillFields['model']); ?>"></td></tr>
            <tr><td class="label">Fakturace<span class="en-label">Billing</span></td><td><input type="text" id="fakturace-firma" value="<?= wgs_escape($prefillFields['fakturace']); ?>" readonly></td></tr>
          </table>
        </div>
      </div>
    </div>
  </div>

  <div class="section-title">Zákazník reklamuje<span class="en-label">CUSTOMER COMPLAINT</span></div>
  <div class="split-section">
    <textarea id="description-cz" placeholder="Popis problému..."><?= wgs_escape($prefillFields['description']); ?></textarea>
    <textarea id="description-en" placeholder="Překlad..." readonly></textarea>
  </div>

  <div class="section-title">Problém zjištěný technikem<span class="en-label">DETECTED PROBLEM</span></div>
  <div class="split-section">
    <textarea id="problem-cz" placeholder="Zjištěný problém..."></textarea>
    <textarea id="problem-en" placeholder="Překlad..." readonly></textarea>
  </div>

  <div class="section-title">Návrh opravy<span class="en-label">REPAIR PROPOSAL</span></div>
  <div class="split-section">
    <textarea id="repair-cz" placeholder="Návrh..."></textarea>
    <textarea id="repair-en" placeholder="Překlad..." readonly></textarea>
  </div>

  <div class="two-col-table">
    <div class="col">
      <table>
        <tr><td class="label">Účtováno za servis<span class="en-label">Service charged</span></td><td><input type="text" id="price-total" placeholder="Kalkulačka" readonly class="calculator-input"></td></tr>
        <tr><td class="label">Platí zákazník?<span class="en-label">Customer pays?</span></td><td><select id="payment"><option>NE</option><option>ANO</option></select></td></tr>
        <tr><td class="label">Datum podpisu<span class="en-label">Signature date</span></td><td><input type="date" id="sign-date"></td></tr>
      </table>
    </div>

    <div class="col">
      <table>
        <tr><td class="label">Vyřešeno?<span class="en-label">Solved?</span></td><td><select id="solved"><option>ANO</option><option>NE</option></select></td></tr>
        <tr><td class="label">Nutné vyjádření prodejce<span class="en-label">Waiting dealer?</span></td><td><select id="dealer"><option>NE</option><option>ANO</option></select></td></tr>
        <tr><td class="label">Poškození technikem?<span class="en-label">Damage by tech?</span></td><td><select id="damage"><option>NE</option><option>ANO</option></select></td></tr>
      </table>
    </div>
  </div>

  <div class="section-title" data-lang-cs="Podpis zákazníka" data-lang-en="Customer signature" data-lang-it="Firma del cliente">Podpis zákazníka<span class="en-label">Customer signature</span></div>

  <!-- Tlačítko pro otevření modalu se souhrnem pro zákazníka -->
  <button type="button" class="btn-podepsat-protokol" id="btnPodepsatProtokol" data-lang-cs="Podepsat protokol" data-lang-en="Sign protocol" data-lang-it="Firma protocollo">Podepsat protokol</button>

  <!-- Pouze zobrazení podpisu (read-only) -->
  <canvas id="signature-pad" class="signature-display"></canvas>
  <div class="gdpr-clause" style="margin-top: 10px; padding: 8px; font-size: 8px; line-height: 1.4; color: #666; border-top: 1px solid #ddd; text-align: justify;">
    <strong>Ochrana osobních údajů (GDPR):</strong> Podpisem tohoto protokolu souhlasíte se zpracováním Vašich osobních údajů společností White Glove Service za účelem poskytování servisních služeb, komunikace s výrobcem, prodejcem a dalšími techniky. Vaše údaje budou zpracovávány v souladu s GDPR a budou použity pouze pro účely vyřízení této reklamace. Máte právo na přístup k údajům, jejich opravu nebo výmaz. Více na www.wgs-service.cz/gdpr
  </div>

  <div class="btns">
    <button class="btn btn-primary" data-action="attachPhotos" data-lang-cs="Přidat fotky" data-lang-en="Add photos" data-lang-it="Aggiungi foto">Přidat fotky</button>
    <button class="btn btn-primary" data-action="exportBothPDFs" data-lang-cs="Export do PDF" data-lang-en="Export to PDF" data-lang-it="Esporta in PDF">Export do PDF</button>
    <button class="btn btn-primary" data-action="sendToCustomer" data-lang-cs="Odeslat zákazníkovi" data-lang-en="Send to customer" data-lang-it="Invia al cliente">Odeslat zákazníkovi</button>
    <button class="btn btn-primary" data-navigate="seznam.php" data-lang-cs="Zpět" data-lang-en="Back" data-lang-it="Indietro">Zpět</button>
  </div>

  <div id="notif" class="notif"></div>
</div>

<!-- Calculator Modal - Alpine.js (Step 40) -->
<div class="calculator-modal-overlay" id="calculatorModalOverlay" style="display: none;"
     x-data="calculatorModal" x-init="init" @click="overlayClick">
  <div class="calculator-modal-container">
    <div class="calculator-modal-header">
      <h3>Kalkulace ceny servisu</h3>
      <button type="button" class="calculator-modal-close" id="calculatorModalClose" @click="close">×</button>
    </div>
    <div class="calculator-modal-body" id="calculatorModalBody">
      <!-- Kalkulačka se vloží dynamicky -->
    </div>
  </div>
</div>

<!-- PDF Preview Modal - Alpine.js (Step 42) -->
<div class="pdf-preview-overlay" id="pdfPreviewOverlay" style="display: none;"
     x-data="pdfPreviewModal" x-init="init" @click="overlayClick">
  <div class="pdf-preview-container">
    <div class="pdf-preview-header">
      <h3 class="pdf-preview-title" data-lang-cs="Náhled PDF" data-lang-en="PDF Preview" data-lang-it="Anteprima PDF">Náhled PDF</h3>
      <div class="pdf-preview-actions">
        <!-- Tlačítko pro export (sdílení/stažení) -->
        <button class="pdf-action-btn pdf-share-btn" id="pdfShareBtn" data-lang-cs-title="Sdílet / Stáhnout" data-lang-en-title="Share / Download" data-lang-it-title="Condividi / Scarica" title="Sdílet / Stáhnout" style="display: none;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
            <polyline points="16 6 12 2 8 6"></polyline>
            <line x1="12" y1="2" x2="12" y2="15"></line>
          </svg>
        </button>

        <!-- Tlačítko pro odeslání zákazníkovi -->
        <button class="pdf-action-btn pdf-send-btn" id="pdfSendBtn" data-lang-cs-title="Odeslat zákazníkovi" data-lang-en-title="Send to Customer" data-lang-it-title="Invia al Cliente" title="Odeslat zákazníkovi" style="display: none;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
          </svg>
        </button>

        <!-- Tlačítko zavřít (vždy viditelné) - Alpine.js (Step 42) -->
        <button class="pdf-action-btn pdf-close-btn" id="pdfCloseBtn" data-lang-cs-title="Zavřít" data-lang-en-title="Close" data-lang-it-title="Chiudi" title="Zavřít" @click="close">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
    </div>
    <div class="pdf-preview-body">
      <iframe id="pdfPreviewFrame" class="pdf-preview-frame"></iframe>
    </div>
  </div>
</div>

<!-- Modal pro schválení zákazníkem - Alpine.js (Step 39) -->
<div class="zakaznik-schvaleni-overlay" id="zakaznikSchvaleniOverlay" style="display: none;"
     x-data="zakaznikSchvaleniModal" x-init="init" @click="overlayClick">
  <div class="zakaznik-schvaleni-container">
    <div class="zakaznik-schvaleni-header">
      <h2 data-lang-cs="Souhrn protokolu" data-lang-en="Protocol Summary" data-lang-it="Riepilogo protocollo">Souhrn protokolu</h2>
      <button type="button" class="zakaznik-schvaleni-close" id="zakaznikSchvaleniClose" @click="close">×</button>
    </div>

    <div class="zakaznik-schvaleni-body">
      <!-- Text návrhu opravy -->
      <div class="zakaznik-schvaleni-sekce">
        <label data-lang-cs="Návrh opravy:" data-lang-en="Repair proposal:" data-lang-it="Proposta di riparazione:">Návrh opravy:</label>
        <div class="zakaznik-schvaleni-text" id="zakaznikSchvaleniText">
          <!-- Text se naplní z repair-cz -->
        </div>
      </div>

      <!-- Souhrn důležitých informací -->
      <div class="zakaznik-schvaleni-sekce">
        <label data-lang-cs="Informace o servisu:" data-lang-en="Service information:" data-lang-it="Informazioni servizio:">Informace o servisu:</label>
        <table class="zakaznik-schvaleni-tabulka">
          <tr>
            <td class="tabulka-label" data-lang-cs="Platí zákazník?" data-lang-en="Customer pays?" data-lang-it="Paga il cliente?">Platí zákazník?</td>
            <td class="tabulka-hodnota" id="souhrn-plati-zakaznik">-</td>
          </tr>
          <tr>
            <td class="tabulka-label" data-lang-cs="Datum podpisu" data-lang-en="Signature date" data-lang-it="Data firma">Datum podpisu</td>
            <td class="tabulka-hodnota" id="souhrn-datum-podpisu">-</td>
          </tr>
          <tr>
            <td class="tabulka-label" data-lang-cs="Vyřešeno?" data-lang-en="Solved?" data-lang-it="Risolto?">Vyřešeno?</td>
            <td class="tabulka-hodnota" id="souhrn-vyreseno">-</td>
          </tr>
          <tr>
            <td class="tabulka-label" data-lang-cs="Nutné vyjádření prodejce" data-lang-en="Dealer statement needed" data-lang-it="Dichiarazione rivenditore">Nutné vyjádření prodejce</td>
            <td class="tabulka-hodnota" id="souhrn-prodejce">-</td>
          </tr>
          <tr>
            <td class="tabulka-label" data-lang-cs="Poškození technikem?" data-lang-en="Damage by technician?" data-lang-it="Danno tecnico?">Poškození technikem?</td>
            <td class="tabulka-hodnota" id="souhrn-poskozeni">-</td>
          </tr>
        </table>
      </div>

      <!-- Podpisové pole -->
      <div class="zakaznik-schvaleni-sekce zakaznik-schvaleni-podpis-sekce">
        <label data-lang-cs="Podpis zákazníka:" data-lang-en="Customer signature:" data-lang-it="Firma cliente:">Podpis zákazníka:</label>
        <canvas id="zakaznikSchvaleniPad"></canvas>
        <div class="zakaznik-schvaleni-podpis-akce">
          <span class="zakaznik-schvaleni-hint" data-lang-cs="Podepište se prstem nebo myší" data-lang-en="Sign with finger or mouse" data-lang-it="Firma con dito o mouse">Podepište se prstem nebo myší</span>
          <button type="button" class="btn-vymazat-podpis" id="zakaznikVymazatPodpis" data-lang-cs="Vymazat" data-lang-en="Clear" data-lang-it="Cancella">Vymazat</button>
        </div>
      </div>
    </div>

    <div class="zakaznik-schvaleni-footer">
      <button type="button" class="btn-zrusit" id="zakaznikSchvaleniZrusit" @click="close" data-lang-cs="Zrušit" data-lang-en="Cancel" data-lang-it="Annulla">Zrušit</button>
      <button type="button" class="btn-pouzit" id="zakaznikSchvaleniPouzit" data-lang-cs="Potvrdit podpis" data-lang-en="Confirm signature" data-lang-it="Conferma firma">Potvrdit podpis</button>
    </div>
  </div>
</div>

<div class="loading-overlay" id="loadingOverlay">
  <div class="loading-spinner"></div>
  <div class="loading-text" id="loadingText">Načítání...</div>
</div>
</main>

<!-- Lokální signature-pad (nahrazuje blokovaný CDN) -->
<script src="assets/js/signature-pad-simple.min.js"></script>
<!-- Fix pro globální scope signaturePad -->
<script src="assets/js/protokol-signature-fix.min.js"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js" defer></script>

<script src="assets/js/csrf-auto-inject.min.js" defer></script>

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
      console.log('[OK] Loading overlay force-hidden');
    } else {
      console.error('[ERROR] Loading overlay NOT FOUND');
    }

    // Check initial data
    const dataNode = document.getElementById('initialReklamaceData');
    if (dataNode) {
      console.log('[OK] initialReklamaceData found');
      const raw = (dataNode.textContent || dataNode.innerText || '').trim();
      console.log('[DATA] Raw data length:', raw.length);
      console.log('[DATA] Raw data preview:', raw.substring(0, 200));

      try {
        const parsed = JSON.parse(raw);
        console.log('[OK] JSON parsed successfully');
        console.log('[DATA] Parsed data:', parsed);
      } catch (e) {
        console.error('[ERROR] JSON parse failed:', e);
      }
    } else {
      console.error('[ERROR] initialReklamaceData NOT FOUND');
    }

    // Check all form fields
    const fieldIds = ['order-number', 'claim-number', 'customer', 'address', 'phone', 'email', 'brand', 'model', 'technician'];
    console.log('[CHECK] Checking form fields:');
    fieldIds.forEach(id => {
      const field = document.getElementById(id);
      if (field) {
        console.log(`  [OK] ${id}: "${field.value}"`);
      } else {
        console.error(`  [ERROR] ${id}: NOT FOUND`);
      }
    });

    // Check signature pad
    const canvas = document.getElementById('signature-pad');
    if (canvas) {
      console.log('[OK] Signature pad canvas found');
      console.log('  Canvas size:', canvas.offsetWidth, 'x', canvas.offsetHeight);
    } else {
      console.error('[ERROR] Signature pad canvas NOT FOUND');
    }

    console.log('[DIAGNOSTICS] EMERGENCY DIAGNOSTICS COMPLETE');
  });
})();
</script>

<!-- External JavaScript -->
<script src="assets/js/protokol-pdf-preview.min.js" defer></script>
<script src="assets/js/protokol-customer-collapse.min.js" defer></script>
<script src="assets/js/protokol-data-patch.min.js" defer></script>
<script src="assets/js/protokol.js?v=20251202" defer></script>
<!-- protokol-fakturace-patch.js byl sloučen do protokol-data-patch.js (Step 47) -->
<!-- Fix pro tlačítka (načíst až po protokol.js) -->
<script src="assets/js/protokol-buttons-fix.min.js" defer></script>
<!-- Překlady pro kalkulačku -->
<script src="assets/js/wgs-translations-cenik.min.js"></script>
<script src="assets/js/language-switcher.min.js"></script>
<!-- Kalkulačka integrace -->
<script src="assets/js/cenik-calculator.min.js" defer></script>
<script src="assets/js/protokol-calculator-integration.min.js" defer></script>
<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
</body>
</html>
