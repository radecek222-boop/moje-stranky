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
            return 'Česká republika (CZ)';
        case 'SK':
            return 'Slovensko (SK)';
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
    'typ_zakaznika' => '', // IČO nebo fyzická osoba
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
            "SELECT r.*, u.name as zadavatel_jmeno
             FROM wgs_reklamace r
             LEFT JOIN wgs_users u ON r.created_by = u.user_id
             WHERE r.reklamace_id = :val1 OR r.cislo = :val2 OR r.id = :val3
             LIMIT 1"
        );
        $stmt->execute([':val1' => $lookupValue, ':val2' => $lookupValue, ':val3' => $lookupValue]);
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
                'typ_zakaznika' => $record['typ_zakaznika'] ?? '',

                // Produktové údaje
                'brand' => $record['zadavatel_jmeno'] ?? '', // Zadavatel = kdo vytvořil zakázku
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
    // Přidat jméno přihlášeného technika pro JS fallback
    $initialBootstrapData['prihlaseny_technik'] = $currentUserName;
    $json = json_encode($initialBootstrapData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json !== false) {
        $initialBootstrapJson = str_replace('</', '<\/', $json);
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate"><meta http-equiv="Pragma" content="no-cache"><meta http-equiv="Expires" content="0">
<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.min.js"></script>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
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
<link rel="dns-prefetch" href="https://cdnjs.cloudflare.com">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <?php $cssVersion = time(); ?>
  <!-- Preload critical CSS -->
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/protokol.css?v=<?= $cssVersion ?>" as="style">

  <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/protokol.css?v=<?= $cssVersion ?>">
  <!-- protokol-mobile-fixes.css sloučen do protokol.min.css (Step 48) -->
  <link rel="stylesheet" href="assets/css/button-fixes-global.min.css">
  <link rel="stylesheet" href="assets/css/cenik.min.css">
  <link rel="stylesheet" href="assets/css/protokol-calculator-modal.min.css">
  <!-- Univerzální tmavý styl pro všechny modály -->
  <link rel="stylesheet" href="assets/css/universal-modal-theme.min.css">
  <!-- Oprava kalkulacky - musi byt posledni -->
  <link rel="stylesheet" href="assets/css/cenik-wizard-fix.css">
  <!-- WGS Loading Dialog - hezký loading s přesýpacími hodinami -->
  <link rel="stylesheet" href="assets/css/wgs-loading.min.css">
  <!-- mobile-responsive.css odstraněn - protokol.min.css má vlastní mobilní styly -->
</head>

<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>
<!-- ČERNÁ HORNÍ PANEL -->

<main id="main-content">
<div class="wrapper">
  <div class="header">
    <div>WHITE GLOVE SERVICE</div>
    <div>Do Dubče 364, Běchovice 190 11 · +420 725 965 826 · reklamace@wgs-service.cz · IČO 09769684</div>
  </div>

  <!-- Rozbalovací sekce zákaznických dat -->
  <div class="customer-info-collapsible">
    <div class="customer-info-header" id="customerInfoToggle" role="button" tabindex="0" aria-expanded="false" aria-controls="customerInfoContent" aria-label="Rozbalit informace o zákazníkovi" data-storage-key="customer-info-expanded">
      <span class="customer-info-name" id="customerInfoName"><?= wgs_escape($prefillFields['customer']) ?: 'Zákazník' ?></span>
      <svg class="customer-info-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="6 9 12 15 18 9"></polyline>
      </svg>
    </div>

    <div class="customer-info-content" id="customerInfoContent" role="region" aria-labelledby="customerInfoToggle">
      <div class="two-col-table">
        <div class="col">
          <table>
            <tr><td class="label">Číslo objednávky<span class="en-label">Order number</span></td><td><input type="text" id="order-number" value="<?= wgs_escape($prefillFields['order_number']); ?>" readonly></td></tr>
            <tr><td class="label">Číslo reklamace<span class="en-label">Claim number</span></td><td><input type="text" id="claim-number" value="<?= wgs_escape($prefillFields['claim_number']); ?>" readonly></td></tr>
            <tr><td class="label">Zákazník<span class="en-label">Customer</span></td><td><input type="text" id="customer" value="<?= wgs_escape($prefillFields['customer']); ?>" readonly></td></tr>
            <tr><td class="label">Adresa<span class="en-label">Address</span></td><td><input type="text" id="address" value="<?= wgs_escape($prefillFields['address']); ?>" readonly></td></tr>
            <tr><td class="label">Telefon<span class="en-label">Phone</span></td><td><input type="tel" id="phone" value="<?= wgs_escape($prefillFields['phone']); ?>" readonly></td></tr>
            <tr><td class="label">Email<span class="en-label">Email</span></td><td><input type="email" id="email" value="<?= wgs_escape($prefillFields['email']); ?>" readonly></td></tr>
            <tr><td class="label">Typ zákazníka<span class="en-label">Customer type</span></td><td>
              <input type="text" id="typ-zakaznika" value="<?= wgs_escape($prefillFields['typ_zakaznika']); ?>" readonly>
              <div id="ico-upozorneni" class="ico-upozorneni" style="display: <?= (strpos($prefillFields['typ_zakaznika'] ?? '', 'IČO') !== false) ? 'block' : 'none'; ?>;" data-lang-cs="Kupující byl seznámen, že se neuplatní spotřebitelská 30denní lhůta; vyřízení proběhne v přiměřené době neodkladně" data-lang-en="The buyer has been informed that the 30-day consumer period does not apply; processing will be done promptly" data-lang-it="L'acquirente è stato informato che non si applica il periodo di 30 giorni; l'elaborazione avverrà tempestivamente">Kupující byl seznámen, že se neuplatní spotřebitelská 30denní lhůta; vyřízení proběhne v přiměřené době neodkladně</div>
            </td></tr>
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
            <tr><td class="label">Zadavatel<span class="en-label">Requester</span></td><td><input type="text" id="brand" placeholder="Jméno zadavatele" value="<?= wgs_escape($prefillFields['brand']); ?>"></td></tr>
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

  <!-- NOVÉ: Červené tlačítko pro prodloužení lhůty - zobrazí se jen pro fyzické osoby -->
  <div id="potrebaDilContainer" style="display: none; margin-bottom: 15px;">
    <button type="button" class="btn-potreba-dil" id="btnPotrebaDil" data-lang-cs="POTŘEBA DÍL K OPRAVĚ" data-lang-en="PART NEEDED FOR REPAIR" data-lang-it="PEZZO NECESSARIO PER RIPARAZIONE">
      POTŘEBA DÍL K OPRAVĚ
    </button>
  </div>

  <!-- Kontejner pro podpis s tlačítkem PODEPSAT uprostřed -->
  <div class="podpis-kontejner" id="podpisKontejner">
    <canvas id="signature-pad" class="signature-display"></canvas>
    <div class="podpis-overlay" id="podpisOverlay">
      <button type="button" class="btn-podepsat-fullscreen" id="btnPodepsatFullscreen" data-lang-cs="PODEPSAT" data-lang-en="SIGN" data-lang-it="FIRMA">PODEPSAT</button>
    </div>
  </div>

  <!-- Fullscreen overlay pro podpis na mobilu -->
  <div class="fullscreen-podpis-overlay" id="fullscreenPodpisOverlay">
    <div class="fullscreen-podpis-kontejner">
      <div class="fullscreen-podpis-header">
        <span data-lang-cs="Podpis zákazníka" data-lang-en="Customer signature" data-lang-it="Firma del cliente">Podpis zákazníka</span>
      </div>
      <canvas id="fullscreen-signature-pad"></canvas>
      <div class="fullscreen-podpis-footer">
        <button type="button" class="btn-smazat-podpis" id="btnSmazatFullscreen" data-lang-cs="Smazat" data-lang-en="Clear" data-lang-it="Cancella">Smazat</button>
        <button type="button" class="btn-potvrdit-podpis" id="btnPotvrdirFullscreen" data-lang-cs="Potvrdit" data-lang-en="Confirm" data-lang-it="Conferma">Potvrdit</button>
      </div>
    </div>
  </div>
  <div class="gdpr-clause" style="margin-top: 10px; padding: 8px; font-size: 8px; line-height: 1.4; color: #666; border-top: 1px solid #ddd; text-align: justify;">
    <span data-lang-cs="Podpisem stvrzuji, že jsem byl(a) seznámen(a) s obsahem." data-lang-en="By signing, I confirm that I have been informed of the content." data-lang-it="Con la firma confermo di essere stato informato del contenuto.">Podpisem stvrzuji, že jsem byl(a) seznámen(a) s obsahem.</span>
    <!-- Text prodloužení lhůty - zobrazí se po potvrzení podpisu s checkboxem -->
    <div class="prodlouzeni-lhuty-hlavni" id="prodlouzeniLhutyHlavni" style="display: none; color: #cc0000; margin-top: 8px;">
      <span data-lang-cs="K úplnému dořešení reklamace je nezbytné objednat náhradní díly od výrobce. Zákazník je informován, že dodací lhůta dílů je mimo kontrolu servisu a může se prodloužit (orientačně 3–4 týdny, v krajním případě i déle). Zákazník tímto výslovně souhlasí s prodloužením lhůty pro vyřízení reklamace nad rámec zákonné lhůty, a to do doby dodání potřebných dílů a provedení opravy. Servis se zavazuje provést opravu a reklamaci uzavřít bez zbytečného odkladu po doručení dílů." data-lang-en="To fully resolve the complaint, it is necessary to order spare parts from the manufacturer. The customer is informed that the delivery time of parts is beyond the control of the service and may be extended (approximately 3-4 weeks, in extreme cases even longer). The customer hereby expressly agrees to extend the complaint resolution deadline beyond the statutory period until the necessary parts are delivered and the repair is completed. The service undertakes to carry out the repair and close the complaint without undue delay after receiving the parts." data-lang-it="Per risolvere completamente il reclamo, è necessario ordinare i pezzi di ricambio dal produttore. Il cliente è informato che i tempi di consegna dei pezzi sono al di fuori del controllo del servizio e possono essere prolungati (circa 3-4 settimane, in casi estremi anche di più). Il cliente accetta espressamente di prorogare il termine per la risoluzione del reclamo oltre il termine legale, fino alla consegna dei pezzi necessari e al completamento della riparazione. Il servizio si impegna a effettuare la riparazione e a chiudere il reclamo senza indebito ritardo dopo la ricezione dei pezzi.">K úplnému dořešení reklamace je nezbytné objednat náhradní díly od výrobce. Zákazník je informován, že dodací lhůta dílů je mimo kontrolu servisu a může se prodloužit (orientačně 3–4 týdny, v krajním případě i déle). Zákazník tímto výslovně souhlasí s prodloužením lhůty pro vyřízení reklamace nad rámec zákonné lhůty, a to do doby dodání potřebných dílů a provedení opravy. Servis se zavazuje provést opravu a reklamaci uzavřít bez zbytečného odkladu po doručení dílů.</span>
    </div>
    <br><br>
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
      <button type="button" class="calculator-modal-close" id="calculatorModalClose" @click="close" aria-label="Zavřít">×</button>
    </div>
    <div class="calculator-modal-body" id="calculatorModalBody">
      <!-- Kalkulačka vložena přímo (ne dynamicky) - Step 116 -->
      <div class="calculator-section" id="kalkulacka">
        <h2 class="section-title" data-lang-cs="Kalkulace ceny služby" data-lang-en="Service Price Calculation" data-lang-it="Calcolo del Prezzo del Servizio">Kalkulace ceny služby</h2>
        <p class="section-text" data-lang-cs="Odpovězte na několik jednoduchých otázek a zjistěte orientační cenu servisu." data-lang-en="Answer a few simple questions and find out the estimated price of the service." data-lang-it="Rispondi ad alcune semplici domande e scopri il prezzo stimato del servizio.">
          Odpovězte na několik jednoduchých otázek a zjistěte orientační cenu servisu.
        </p>

        <!-- Progress Indicator -->
        <div class="wizard-progress" id="wizard-progress">
          <div class="progress-step active" data-step="1">
            <span class="step-number">1</span>
            <span class="step-label" data-lang-cs="Adresa" data-lang-en="Address" data-lang-it="Indirizzo">Adresa</span>
          </div>
          <div class="progress-step" data-step="2">
            <span class="step-number">2</span>
            <span class="step-label" data-lang-cs="Typ servisu" data-lang-en="Service Type" data-lang-it="Tipo di Servizio">Typ servisu</span>
          </div>
          <div class="progress-step" data-step="3">
            <span class="step-number">3</span>
            <span class="step-label" data-lang-cs="Detaily" data-lang-en="Details" data-lang-it="Dettagli">Detaily</span>
          </div>
          <div class="progress-step" data-step="4">
            <span class="step-number">4</span>
            <span class="step-label" data-lang-cs="Souhrn" data-lang-en="Summary" data-lang-it="Riepilogo">Souhrn</span>
          </div>
        </div>

        <!-- KROK 1: Zadání adresy -->
        <div class="wizard-step" id="step-address">
          <h3 class="step-title" data-lang-cs="1. Zadejte adresu zákazníka" data-lang-en="1. Enter Customer Address" data-lang-it="1. Inserisci l'Indirizzo del Cliente">1. Zadejte adresu zákazníka</h3>
          <p class="step-desc" data-lang-cs="Pro výpočet dopravného potřebujeme znát vaši adresu." data-lang-en="We need your address to calculate the transportation cost." data-lang-it="Abbiamo bisogno del tuo indirizzo per calcolare il costo del trasporto.">Pro výpočet dopravného potřebujeme znát vaši adresu.</p>

          <div class="form-group">
            <label for="calc-address" data-lang-cs="Adresa:" data-lang-en="Address:" data-lang-it="Indirizzo:">Adresa:</label>
            <input
              type="text"
              id="calc-address"
              class="calc-input"
              placeholder="Začněte psát adresu (ulice, město)..."
              data-lang-placeholder-cs="Začněte psát adresu (ulice, město)..."
              data-lang-placeholder-en="Start typing address (street, city)..."
              data-lang-placeholder-it="Inizia a digitare l'indirizzo (via, città)..."
              autocomplete="off"
            >
            <div id="address-suggestions" class="suggestions-dropdown hidden"></div>
          </div>

          <!-- Checkbox pro reklamace - viditelný jen pro přihlášené uživatele (protokol vždy vyžaduje přihlášení) -->
          <div class="form-group" style="margin-top: 15px;">
            <label class="checkbox-container">
              <input type="checkbox" id="reklamace-bez-dopravy">
              <span class="checkbox-label" data-lang-cs="Jedná se o reklamaci – neúčtuje se dopravné" data-lang-en="This is a claim – no transportation fee" data-lang-it="Questo è un reclamo – nessun costo di trasporto">Jedná se o reklamaci – neúčtuje se dopravné</span>
            </label>
          </div>
          <div class="form-group" style="margin-top: 10px;">
            <label class="checkbox-container">
              <input type="checkbox" id="vyzvednuti-sklad">
              <span class="checkbox-label" data-lang-cs="Vyzvednutí dílu pro reklamaci na skladě + 10 €" data-lang-en="Part pickup for claim at warehouse + 10 €" data-lang-it="Ritiro del pezzo per reclamo presso magazzino + 10 €">Vyzvednutí dílu pro reklamaci na skladě + 10 €</span>
            </label>
          </div>

          <!-- Vlastní cena - pouze pro přihlášené (protokol vždy vyžaduje přihlášení) -->
          <div class="form-group vlastni-cena-wrapper" style="margin-top: 15px; padding: 15px; background: #f5f5f5; border-radius: 8px; border: 1px dashed #ccc;">
            <label class="checkbox-container" style="margin-bottom: 10px;">
              <input type="checkbox" id="vlastni-cena-checkbox">
              <span class="checkbox-label" data-lang-cs="Zadat vlastní cenu (přeskočit kalkulaci)" data-lang-en="Enter custom price (skip calculation)" data-lang-it="Inserisci prezzo personalizzato (salta calcolo)">Zadat vlastní cenu (přeskočit kalkulaci)</span>
            </label>
            <div id="vlastni-cena-input-wrapper" style="display: none; margin-top: 10px;">
              <div style="display: flex; align-items: center; gap: 10px;">
                <input type="number" id="vlastni-cena-input" min="0" step="0.01" placeholder="0.00" style="width: 150px; padding: 10px; font-size: 16px; border: 1px solid #ccc; border-radius: 5px;">
                <span style="font-size: 18px; font-weight: bold;">€</span>
              </div>
              <p style="font-size: 12px; color: #666; margin-top: 8px;" data-lang-cs="Zadejte celkovou cenu včetně všech služeb" data-lang-en="Enter total price including all services" data-lang-it="Inserisci il prezzo totale inclusi tutti i servizi">Zadejte celkovou cenu včetně všech služeb</p>
            </div>
          </div>

          <div id="distance-result" class="calc-result" style="display: none;">
            <div class="result-box">
              <p><strong data-lang-cs="Vzdálenost z dílny:" data-lang-en="Distance from workshop:" data-lang-it="Distanza dall'officina:">Vzdálenost z dílny:</strong> <span id="distance-value">-</span> km</p>
              <p><strong data-lang-cs="Dopravné (tam a zpět):" data-lang-en="Transportation (round trip):" data-lang-it="Trasporto (andata e ritorno):">Dopravné (tam a zpět):</strong> <span id="transport-cost" class="highlight-price">-</span> €</p>
            </div>
          </div>

          <div class="wizard-buttons">
            <button class="btn-primary" data-action="nextStep" data-lang-cs="Pokračovat" data-lang-en="Continue" data-lang-it="Continua">Pokračovat</button>
          </div>
        </div>

        <!-- KROK 2: Typ servisu -->
        <div class="wizard-step hidden" id="step-service-type">
          <h3 class="step-title" data-lang-cs="2. Jaký typ servisu potřebujete?" data-lang-en="2. What type of service do you need?" data-lang-it="2. Che tipo di servizio ti serve?">2. Jaký typ servisu potřebujete?</h3>
          <p class="step-desc" data-lang-cs="Vyberte, co u vás potřebujeme udělat." data-lang-en="Select what we need to do for you." data-lang-it="Seleziona cosa dobbiamo fare per te.">Vyberte, co u vás potřebujeme udělat.</p>

          <div class="radio-group">
            <label class="radio-card">
              <input type="radio" name="service-type" value="diagnostika">
              <div class="radio-content">
                <div class="radio-title" data-lang-cs="Pouze diagnostika / inspekce" data-lang-en="Diagnostic / Inspection Only" data-lang-it="Solo Diagnostica / Ispezione">Pouze diagnostika / inspekce</div>
                <div class="radio-desc" data-lang-cs="Technik provede pouze zjištění rozsahu poškození a posouzení stavu." data-lang-en="Technician will only assess the extent of damage and evaluate the condition." data-lang-it="Il tecnico valuterà solo l'entità del danno e valuterà le condizioni.">Technik provede pouze zjištění rozsahu poškození a posouzení stavu.</div>
                <div class="radio-price">110 €</div>
              </div>
            </label>

            <label class="radio-card">
              <input type="radio" name="service-type" value="calouneni" checked>
              <div class="radio-content">
                <div class="radio-title" data-lang-cs="Čalounické práce" data-lang-en="Upholstery Work" data-lang-it="Lavori di Tappezzeria">Čalounické práce</div>
                <div class="radio-desc" data-lang-cs="Oprava včetně rozčalounění konstrukce (sedáky, opěrky, područky)." data-lang-en="Repair including disassembly of structure (seats, backrests, armrests)." data-lang-it="Riparazione compreso smontaggio della struttura (sedili, schienali, braccioli).">Oprava včetně rozčalounění konstrukce (sedáky, opěrky, područky).</div>
                <div class="radio-price" data-lang-cs="Od 205 €" data-lang-en="From 205 €" data-lang-it="Da 205 €">Od 205 €</div>
              </div>
            </label>

            <label class="radio-card">
              <input type="radio" name="service-type" value="mechanika">
              <div class="radio-content">
                <div class="radio-title" data-lang-cs="Mechanické opravy" data-lang-en="Mechanical Repairs" data-lang-it="Riparazioni Meccaniche">Mechanické opravy</div>
                <div class="radio-desc" data-lang-cs="Oprava mechanismů (relax, výsuv) bez rozčalounění." data-lang-en="Repair of mechanisms (relax, slide) without disassembly." data-lang-it="Riparazione di meccanismi (relax, scorrimento) senza smontaggio.">Oprava mechanismů (relax, výsuv) bez rozčalounění.</div>
                <div class="radio-price" data-lang-cs="Od 165 €" data-lang-en="From 165 €" data-lang-it="Da 165 €">Od 165 €</div>
              </div>
            </label>

            <label class="radio-card">
              <input type="radio" name="service-type" value="kombinace">
              <div class="radio-content">
                <div class="radio-title" data-lang-cs="Kombinace čalounění + mechaniky" data-lang-en="Upholstery + Mechanics Combination" data-lang-it="Combinazione Tappezzeria + Meccanica">Kombinace čalounění + mechaniky</div>
                <div class="radio-desc" data-lang-cs="Komplexní oprava zahrnující čalounění i mechanické části." data-lang-en="Comprehensive repair including both upholstery and mechanical parts." data-lang-it="Riparazione completa comprendente sia tappezzeria che parti meccaniche.">Komplexní oprava zahrnující čalounění i mechanické části.</div>
                <div class="radio-price" data-lang-cs="Dle rozsahu" data-lang-en="Based on scope" data-lang-it="In base all'ambito">Dle rozsahu</div>
              </div>
            </label>
          </div>

          <div class="wizard-buttons">
            <button class="btn-secondary" data-action="previousStep" data-lang-cs="Zpět" data-lang-en="Back" data-lang-it="Indietro">Zpět</button>
            <button class="btn-primary" data-action="nextStep" data-lang-cs="Pokračovat" data-lang-en="Continue" data-lang-it="Continua">Pokračovat</button>
          </div>
        </div>

        <!-- KROK 3A: Čalounické práce - počet dílů -->
        <div class="wizard-step hidden" id="step-upholstery">
          <h3 class="step-title" data-lang-cs="3. Kolik dílů potřebuje přečalounit?" data-lang-en="3. How many parts need reupholstering?" data-lang-it="3. Quante parti necessitano di ritappezzatura?">3. Kolik dílů potřebuje přečalounit?</h3>
          <p class="step-desc" data-lang-cs="Jeden díl = sedák NEBO opěrka NEBO područka NEBO panel. První díl stojí 205€, každý další 70€." data-lang-en="One part = seat OR backrest OR armrest OR panel. First part costs 205€, each additional 70€." data-lang-it="Una parte = sedile O schienale O bracciolo O pannello. La prima parte costa 205€, ogni aggiuntiva 70€.">Jeden díl = sedák NEBO opěrka NEBO područka NEBO panel. První díl stojí 205€, každý další 70€.</p>

          <div class="counter-group">
            <div class="counter-item">
              <label data-lang-cs="Sedáky" data-lang-en="Seats" data-lang-it="Sedili">Sedáky</label>
              <div class="counter-controls">
                <button class="btn-counter" data-action="decrementCounter" data-counter="sedaky" aria-label="Snížit počet sedáků">−</button>
                <input type="number" id="sedaky" value="0" min="0" max="20" readonly aria-label="Počet sedáků">
                <button class="btn-counter" data-action="incrementCounter" data-counter="sedaky" aria-label="Zvýšit počet sedáků">+</button>
              </div>
            </div>

            <div class="counter-item">
              <label data-lang-cs="Opěrky" data-lang-en="Backrests" data-lang-it="Schienali">Opěrky</label>
              <div class="counter-controls">
                <button class="btn-counter" data-action="decrementCounter" data-counter="operky" aria-label="Snížit počet opěrek">−</button>
                <input type="number" id="operky" value="0" min="0" max="20" readonly aria-label="Počet opěrek">
                <button class="btn-counter" data-action="incrementCounter" data-counter="operky" aria-label="Zvýšit počet opěrek">+</button>
              </div>
            </div>

            <div class="counter-item">
              <label data-lang-cs="Područky" data-lang-en="Armrests" data-lang-it="Braccioli">Područky</label>
              <div class="counter-controls">
                <button class="btn-counter" data-action="decrementCounter" data-counter="podrucky" aria-label="Snížit počet područek">−</button>
                <input type="number" id="podrucky" value="0" min="0" max="20" readonly aria-label="Počet područek">
                <button class="btn-counter" data-action="incrementCounter" data-counter="podrucky" aria-label="Zvýšit počet područek">+</button>
              </div>
            </div>

            <div class="counter-item">
              <label data-lang-cs="Panely (zadní/boční)" data-lang-en="Panels (back/side)" data-lang-it="Pannelli (posteriore/laterale)">Panely (zadní/boční)</label>
              <div class="counter-controls">
                <button class="btn-counter" data-action="decrementCounter" data-counter="panely" aria-label="Snížit počet panelů">−</button>
                <input type="number" id="panely" value="0" min="0" max="20" readonly aria-label="Počet panelů">
                <button class="btn-counter" data-action="incrementCounter" data-counter="panely" aria-label="Zvýšit počet panelů">+</button>
              </div>
            </div>
          </div>

          <div class="parts-summary" id="parts-summary">
            <strong data-lang-cs="Celkem dílů:" data-lang-en="Total parts:" data-lang-it="Totale parti:">Celkem dílů:</strong> <span id="total-parts">0</span>
            <span class="price-breakdown" id="parts-price-breakdown"></span>
          </div>

          <div class="wizard-buttons">
            <button class="btn-secondary" data-action="previousStep" data-lang-cs="Zpět" data-lang-en="Back" data-lang-it="Indietro">Zpět</button>
            <button class="btn-primary" data-action="nextStep" data-lang-cs="Pokračovat" data-lang-en="Continue" data-lang-it="Continua">Pokračovat</button>
          </div>
        </div>

        <!-- KROK 3B: Mechanické práce -->
        <div class="wizard-step hidden" id="step-mechanics">
          <h3 class="step-title" data-lang-cs="3. Mechanické části" data-lang-en="3. Mechanical Parts" data-lang-it="3. Parti Meccaniche">3. Mechanické části</h3>
          <p class="step-desc" data-lang-cs="Vyberte, které mechanické části potřebují opravu." data-lang-en="Select which mechanical parts need repair." data-lang-it="Seleziona quali parti meccaniche necessitano di riparazione.">Vyberte, které mechanické části potřebují opravu.</p>

          <div class="counter-group">
            <div class="counter-item">
              <label data-lang-cs="Relax mechanismy" data-lang-en="Relax mechanisms" data-lang-it="Meccanismi relax">Relax mechanismy</label>
              <div class="counter-controls">
                <button class="btn-counter" data-action="decrementCounter" data-counter="relax" aria-label="Snížit počet relax mechanismů">−</button>
                <input type="number" id="relax" value="0" min="0" max="10" readonly aria-label="Počet relax mechanismů">
                <button class="btn-counter" data-action="incrementCounter" data-counter="relax" aria-label="Zvýšit počet relax mechanismů">+</button>
              </div>
              <div class="counter-price" data-lang-cs="45 € / kus" data-lang-en="45 € / piece" data-lang-it="45 € / pezzo">45 € / kus</div>
            </div>

            <div class="counter-item">
              <label data-lang-cs="Elektrické díly" data-lang-en="Electrical parts" data-lang-it="Parti elettriche">Elektrické díly</label>
              <div class="counter-controls">
                <button class="btn-counter" data-action="decrementCounter" data-counter="vysuv" aria-label="Snížit počet elektrických dílů">−</button>
                <input type="number" id="vysuv" value="0" min="0" max="10" readonly aria-label="Počet elektrických dílů">
                <button class="btn-counter" data-action="incrementCounter" data-counter="vysuv" aria-label="Zvýšit počet elektrických dílů">+</button>
              </div>
              <div class="counter-price" data-lang-cs="45 € / kus" data-lang-en="45 € / piece" data-lang-it="45 € / pezzo">45 € / kus</div>
            </div>
          </div>

          <div class="wizard-buttons">
            <button class="btn-secondary" data-action="previousStep" data-lang-cs="Zpět" data-lang-en="Back" data-lang-it="Indietro">Zpět</button>
            <button class="btn-primary" data-action="nextStep" data-lang-cs="Pokračovat" data-lang-en="Continue" data-lang-it="Continua">Pokračovat</button>
          </div>
        </div>

        <!-- KROK 4: Další parametry -->
        <div class="wizard-step hidden" id="step-extras">
          <h3 class="step-title" data-lang-cs="4. Další parametry" data-lang-en="4. Additional Parameters" data-lang-it="4. Parametri Aggiuntivi">4. Další parametry</h3>
          <p class="step-desc" data-lang-cs="Poslední detaily pro přesný výpočet ceny." data-lang-en="Last details for accurate price calculation." data-lang-it="Ultimi dettagli per un calcolo preciso del prezzo.">Poslední detaily pro přesný výpočet ceny.</p>

          <div class="checkbox-group">
            <label class="checkbox-card">
              <input type="checkbox" id="tezky-nabytek">
              <div class="checkbox-content">
                <div class="checkbox-title" data-lang-cs="Nábytek je těžší než 50 kg" data-lang-en="Furniture weighs more than 50 kg" data-lang-it="Mobile pesa più di 50 kg">Nábytek je těžší než 50 kg</div>
                <div class="checkbox-desc" data-lang-cs="Bude potřeba druhá osoba pro manipulaci" data-lang-en="A second person will be needed for handling" data-lang-it="Sarà necessaria una seconda persona per la manipolazione">Bude potřeba druhá osoba pro manipulaci</div>
                <div class="checkbox-price">+ 95 €</div>
              </div>
            </label>

            <label class="checkbox-card">
              <input type="checkbox" id="material">
              <div class="checkbox-content">
                <div class="checkbox-title" data-lang-cs="Materiál dodán od WGS" data-lang-en="Material supplied by WGS" data-lang-it="Materiale fornito da WGS">Materiál dodán od WGS</div>
                <div class="checkbox-desc" data-lang-cs="Výplně (vata, pěna) z naší zásoby" data-lang-en="Fillings (batting, foam) from our stock" data-lang-it="Imbottiture (ovatta, schiuma) dal nostro magazzino">Výplně (vata, pěna) z naší zásoby</div>
                <div class="checkbox-price">+ 50 €</div>
              </div>
            </label>
          </div>

          <div class="wizard-buttons">
            <button class="btn-secondary" data-action="previousStep" data-lang-cs="Zpět" data-lang-en="Back" data-lang-it="Indietro">Zpět</button>
            <button class="btn-primary" data-action="nextStep" data-lang-cs="Zobrazit souhrn" data-lang-en="Show Summary" data-lang-it="Mostra Riepilogo">Zobrazit souhrn</button>
          </div>
        </div>

        <!-- KROK 5: Cenový souhrn -->
        <div class="wizard-step hidden" id="step-summary">
          <h3 class="step-title" data-lang-cs="Orientační cena servisu" data-lang-en="Estimated Service Price" data-lang-it="Prezzo Stimato del Servizio">Orientační cena servisu</h3>

          <div class="price-summary-box">
            <div id="summary-details">
              <!-- Načteno dynamicky JavaScriptem -->
            </div>

            <div class="summary-line total">
              <span><strong data-lang-cs="CELKOVÁ CENA:" data-lang-en="TOTAL PRICE:" data-lang-it="PREZZO TOTALE:">CELKOVÁ CENA:</strong></span>
              <span id="grand-total" class="total-price"><strong>0 €</strong></span>
            </div>

            <div class="summary-note">
              <strong data-lang-cs="Upozornění:" data-lang-en="Notice:" data-lang-it="Avviso:">Upozornění:</strong> <span data-lang-cs="Ceny jsou orientační a vztahují se pouze na práci. Originální materiál z továrny Natuzzi a náhradní mechanické díly se účtují zvlášť podle skutečné spotřeby." data-lang-en="Prices are indicative and apply only to labor. Original material from Natuzzi factory and replacement mechanical parts are charged separately based on actual consumption." data-lang-it="I prezzi sono indicativi e si applicano solo alla manodopera. Il materiale originale della fabbrica Natuzzi e le parti meccaniche di ricambio vengono addebitati separatamente in base al consumo effettivo.">Ceny jsou orientační a vztahují se <strong>pouze na práci</strong>.
              Originální materiál z továrny Natuzzi a náhradní mechanické díly se účtují zvlášť podle skutečné spotřeby.</span>
            </div>
          </div>

          <div class="wizard-buttons">
            <button class="btn-secondary" data-action="previousStep" data-lang-cs="Zpět" data-lang-en="Back" data-lang-it="Indietro">Zpět</button>
            <button class="btn-primary" data-action="zapocitatDoProtokolu" data-lang-cs="Započítat do protokolu" data-lang-en="Apply to protocol" data-lang-it="Applica al protocollo">Započítat do protokolu</button>
          </div>
        </div>

      </div>
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
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path d="M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8"></path>
            <polyline points="16 6 12 2 8 6"></polyline>
            <line x1="12" y1="2" x2="12" y2="15"></line>
          </svg>
        </button>

        <!-- Tlačítko pro odeslání zákazníkovi -->
        <button class="pdf-action-btn pdf-send-btn" id="pdfSendBtn" data-lang-cs-title="Odeslat zákazníkovi" data-lang-en-title="Send to Customer" data-lang-it-title="Invia al Cliente" title="Odeslat zákazníkovi" style="display: none;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <line x1="22" y1="2" x2="11" y2="13"></line>
            <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
          </svg>
        </button>

        <!-- Tlačítko zavřít (vždy viditelné) - Alpine.js (Step 42) -->
        <button class="pdf-action-btn pdf-close-btn" id="pdfCloseBtn" data-lang-cs-title="Zavřít" data-lang-en-title="Close" data-lang-it-title="Chiudi" title="Zavřít" @click="close">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <line x1="18" y1="6" x2="6" y2="18"></line>
            <line x1="6" y1="6" x2="18" y2="18"></line>
          </svg>
        </button>
      </div>
    </div>
    <div class="pdf-preview-body">
      <iframe id="pdfPreviewFrame" class="pdf-preview-frame" title="Náhled PDF protokolu"></iframe>
    </div>
  </div>
</div>

<!-- Modal pro schválení zákazníkem - Alpine.js (Step 39) -->
<div class="zakaznik-schvaleni-overlay" id="zakaznikSchvaleniOverlay" style="display: none;"
     x-data="zakaznikSchvaleniModal" x-init="init" @click="overlayClick">
  <div class="zakaznik-schvaleni-container">
    <div class="zakaznik-schvaleni-header">
      <h2 data-lang-cs="PODPIS ZÁKAZNÍKA" data-lang-en="CUSTOMER SIGNATURE" data-lang-it="FIRMA DEL CLIENTE">PODPIS ZÁKAZNÍKA</h2>
      <button type="button" class="zakaznik-schvaleni-close" id="zakaznikSchvaleniClose" @click="close" aria-label="Zavřít">×</button>
    </div>

    <div class="zakaznik-schvaleni-body">
      <!-- Text návrhu opravy -->
      <div class="zakaznik-schvaleni-sekce">
        <label data-lang-cs="Návrh opravy:" data-lang-en="Repair proposal:" data-lang-it="Proposta di riparazione:">Návrh opravy:</label>
        <div class="zakaznik-schvaleni-text" id="zakaznikSchvaleniText">
          <!-- Text se naplní z repair-cz -->
        </div>
      </div>

      <!-- Volba typu podpisu - PŘESUNUTO NAHORU PRO LEPŠÍ VIDITELNOST -->
      <div class="zakaznik-schvaleni-sekce zakaznik-volba-podpisu" id="zakaznikVolbaPodpisu">
        <label data-lang-cs="Vyberte typ podpisu:" data-lang-en="Select signature type:" data-lang-it="Seleziona tipo di firma:">Vyberte typ podpisu:</label>
        <div class="volba-podpisu-tlacitka">
          <button type="button" class="btn-volba-podpisu btn-nutno-objednat-dil" id="btnNutnoObjednatDil" data-lang-cs="NUTNO OBJEDNAT DÍL" data-lang-en="PART ORDER REQUIRED" data-lang-it="ORDINE PEZZO NECESSARIO">NUTNO OBJEDNAT DÍL</button>
          <button type="button" class="btn-volba-podpisu btn-pouze-podpis" id="btnPouzePodpis" data-lang-cs="PODPIS" data-lang-en="SIGNATURE" data-lang-it="FIRMA">PODPIS</button>
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

      <!-- Podpisové pole (zobrazí se po výběru) -->
      <div class="zakaznik-schvaleni-sekce zakaznik-schvaleni-podpis-sekce" id="zakaznikPodpisSekce" style="display: none;">
        <label data-lang-cs="Podpis zákazníka:" data-lang-en="Customer signature:" data-lang-it="Firma cliente:">Podpis zákazníka:</label>

        <!-- Info text o prodloužení lhůty (zobrazí se pokud je potřeba) -->
        <div class="prodlouzeni-lhuty-info" id="prodlouzeniLhutyInfo" style="display: none;">
          <span id="prodlouzeniLhutyInfoText"></span>
        </div>

        <canvas id="zakaznikSchvaleniPad"></canvas>
        <div class="zakaznik-schvaleni-podpis-akce">
          <span class="zakaznik-schvaleni-hint" data-lang-cs="Podepište se prstem nebo myší" data-lang-en="Sign with finger or mouse" data-lang-it="Firma con dito o mouse">Podepište se prstem nebo myší</span>
          <button type="button" class="btn-vymazat-podpis" id="zakaznikVymazatPodpis" data-lang-cs="Vymazat" data-lang-en="Clear" data-lang-it="Cancella">Vymazat</button>
        </div>
      </div>
    </div>

    <div class="zakaznik-schvaleni-footer">

      <!-- Tlačítka -->
      <div class="footer-tlacitka">
        <button type="button" class="btn-zrusit" id="zakaznikSchvaleniZrusit" @click="close" data-lang-cs="Zrušit" data-lang-en="Cancel" data-lang-it="Annulla">Zrušit</button>
        <button type="button" class="btn-pouzit" id="zakaznikSchvaleniPouzit" data-lang-cs="Potvrdit podpis" data-lang-en="Confirm signature" data-lang-it="Conferma firma">Potvrdit podpis</button>
      </div>
    </div>
  </div>
</div>

<!-- Modal pro souhlas s objednáním dílu -->
<div class="souhlas-dil-overlay" id="souhlasDilOverlay" style="display: none;">
  <div class="souhlas-dil-container">
    <div class="souhlas-dil-header">
      <h2 data-lang-cs="Prodloužení lhůty - objednání dílu" data-lang-en="Deadline extension - part order" data-lang-it="Proroga termine - ordine pezzo">Prodloužení lhůty - objednání dílu</h2>
    </div>

    <div class="souhlas-dil-body">
      <div class="souhlas-dil-text">
        <p data-lang-cs="K úplnému dořešení reklamace je nezbytné objednat náhradní díly od výrobce. Zákazník je informován, že dodací lhůta dílů je mimo kontrolu servisu a může se prodloužit (orientačně 3–4 týdny, v krajním případě i déle)."
           data-lang-en="To fully resolve the complaint, it is necessary to order spare parts from the manufacturer. The customer is informed that the delivery time of parts is beyond the control of the service and may be extended (approximately 3-4 weeks, in extreme cases even longer)."
           data-lang-it="Per risolvere completamente il reclamo, è necessario ordinare i pezzi di ricambio dal produttore. Il cliente è informato che i tempi di consegna dei pezzi sono al di fuori del controllo del servizio e possono essere prolungati (circa 3-4 settimane, in casi estremi anche di più).">
          K úplnému dořešení reklamace je nezbytné objednat náhradní díly od výrobce. Zákazník je informován, že dodací lhůta dílů je mimo kontrolu servisu a může se prodloužit (orientačně 3–4 týdny, v krajním případě i déle).
        </p>
        <p data-lang-cs="Zákazník tímto výslovně souhlasí s prodloužením lhůty pro vyřízení reklamace nad rámec zákonné lhůty, a to do doby dodání potřebných dílů a provedení opravy. Servis se zavazuje provést opravu a reklamaci uzavřít bez zbytečného odkladu po doručení dílů."
           data-lang-en="The customer hereby expressly agrees to extend the complaint resolution deadline beyond the statutory period until the necessary parts are delivered and the repair is completed. The service undertakes to carry out the repair and close the complaint without undue delay after receiving the parts."
           data-lang-it="Il cliente accetta espressamente di prorogare il termine per la risoluzione del reclamo oltre il termine legale, fino alla consegna dei pezzi necessari e al completamento della riparazione. Il servizio si impegna a effettuare la riparazione e a chiudere il reclamo senza indebito ritardo dopo la ricezione dei pezzi.">
          Zákazník tímto výslovně souhlasí s prodloužením lhůty pro vyřízení reklamace nad rámec zákonné lhůty, a to do doby dodání potřebných dílů a provedení opravy. Servis se zavazuje provést opravu a reklamaci uzavřít bez zbytečného odkladu po doručení dílů.
        </p>
      </div>

      <div class="souhlas-dil-otazka">
        <h3 data-lang-cs="Souhlasíte s prodloužením lhůty?" data-lang-en="Do you agree to the deadline extension?" data-lang-it="Accetta la proroga del termine?">Souhlasíte s prodloužením lhůty?</h3>
      </div>
    </div>

    <div class="souhlas-dil-footer">
      <button type="button" class="btn-nesouhlas" id="btnNesouhlasim" data-lang-cs="NESOUHLASÍM" data-lang-en="I DISAGREE" data-lang-it="NON ACCETTO">NESOUHLASÍM</button>
      <button type="button" class="btn-souhlas" id="btnSouhlasim" data-lang-cs="SOUHLASÍM" data-lang-en="I AGREE" data-lang-it="ACCETTO">SOUHLASÍM</button>
    </div>
  </div>
</div>

<!-- WGS Loading Dialog - Hezký loading s přesýpacími hodinami -->
<div class="wgs-loading-overlay" id="loadingOverlay">
  <div class="wgs-loading-box">
    <!-- Animované přesýpací hodiny (zelené) -->
    <div class="wgs-loading-hourglass">
      <svg class="wgs-hourglass-svg" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
        <!-- Obrys přesýpacích hodin -->
        <path d="M20,10 L80,10 L80,20 L60,45 L60,55 L80,80 L80,90 L20,90 L20,80 L40,55 L40,45 L20,20 Z"
              fill="none" stroke="#39ff14" stroke-width="3" stroke-linejoin="round"/>

        <!-- Horní komora - písek -->
        <path d="M25,15 L75,15 L75,20 L57,42 L43,42 L25,20 Z"
              fill="#39ff14" opacity="0.6"/>

        <!-- Dolní komora - písek -->
        <path d="M25,85 L75,85 L75,80 L57,58 L43,58 L25,80 Z"
              fill="#39ff14" opacity="0.3"/>

        <!-- Padající částice písku (animované) -->
        <circle class="wgs-sand-particle" cx="50" cy="45" r="1.5" fill="#39ff14"/>
        <circle class="wgs-sand-particle" cx="48" cy="43" r="1.2" fill="#39ff14"/>
        <circle class="wgs-sand-particle" cx="52" cy="44" r="1.3" fill="#39ff14"/>
        <circle class="wgs-sand-particle" cx="49" cy="46" r="1.1" fill="#39ff14"/>
        <circle class="wgs-sand-particle" cx="51" cy="45" r="1.4" fill="#39ff14"/>
      </svg>
    </div>

    <!-- Hlavní zpráva -->
    <div class="wgs-loading-message" id="loadingText">Načítání...</div>

    <!-- Sekundární zpráva (volitelná) -->
    <div class="wgs-loading-submessage" id="loadingSubtext" style="display: none;"></div>
  </div>
</div>
</main>

<!-- Lokální signature-pad (nahrazuje blokovaný CDN) -->
<script src="assets/js/signature-pad-simple.min.js"></script>
<!-- protokol-signature-fix.js odstraněn - window.signaturePad je nyní v protokol.js (Step 110) -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<!-- Custom font pro české znaky -->
<script src="https://unpkg.com/jspdf-customfonts@latest/dist/default_vfs.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js" defer></script>

<script src="assets/js/csrf-auto-inject.min.js" defer></script>
<!-- Utils - obsahuje fetchCsrfToken a další pomocné funkce -->
<script src="assets/js/utils.js?v=<?= time() ?>" defer></script>

<!-- External JavaScript -->
<script src="assets/js/protokol-pdf-preview.min.js" defer></script>
<script src="assets/js/customer-collapse.min.js" defer></script>
<script src="assets/js/protokol-data-patch.min.js" defer></script>
<script src="assets/js/protokol.js?v=<?= time() ?>" defer></script>
<!-- protokol-fakturace-patch.js byl sloučen do protokol-data-patch.min.js (Step 47) -->
<!-- protokol-buttons-fix.js odstraněn - handlery jsou již v protokol.js (Step 109) -->
<!-- Překlady pro kalkulačku -->
<script src="assets/js/wgs-translations-cenik.min.js" defer></script>
<script src="assets/js/language-switcher.min.js" defer></script>
<!-- Mapa pro autocomplete adres v kalkulačce -->
<script src="assets/js/wgs-map.min.js" defer></script>
<!-- Kalkulačka integrace -->
<script src="assets/js/cenik-calculator.js?v=<?= time() ?>" defer></script>
<script src="assets/js/protokol-calculator-integration.js?v=<?= time() ?>" defer></script>

<!-- Session Keep-Alive - KRITICKÉ: Brání vypršení session při práci -->
<script src="assets/js/session-keepalive.js?v=<?= time() ?>" defer></script>

<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
</body>
</html>
