<?php
require_once "init.php";

// KROK 1: Kontrola, zda je uzivatel vubec prihlasen
// DULEZITE: Musime zkontrolovat user_id PRED kontrolou role!
if (!isset($_SESSION['user_id'])) {
    error_log("PHOTOCUSTOMER: Pristup odepren - uzivatel neni prihlasen (chybi user_id)");
    header('Location: login.php?redirect=photocustomer.php');
    exit;
}

// KROK 2: Kontrola pristupu - POUZE admin a technik
// Prodejci a nepřihlášení uživatelé NEMAJÍ přístup k fotodokumentaci
$rawRole = (string) ($_SESSION['role'] ?? '');
$normalizedRole = strtolower(trim($rawRole));
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Technik může být uložen různými variantami (např. "Technik WGS", "externi technik")
// Proto testujeme jednak přesné hodnoty, ale i to, zda role obsahuje klíčová slova
$technikKeywords = ['technik', 'technician'];
$isTechnik = in_array($normalizedRole, $technikKeywords, true);
if (!$isTechnik) {
    foreach ($technikKeywords as $keyword) {
        if (strpos($normalizedRole, $keyword) !== false) {
            $isTechnik = true;
            break;
        }
    }
}

if (!$isAdmin && !$isTechnik) {
    error_log("PHOTOCUSTOMER: Přístup odepřen");
    error_log("  - user_id: " . $_SESSION['user_id']);
    error_log("  - role (raw): '{$rawRole}'");
    error_log("  - role (normalized): '{$normalizedRole}'");
    error_log("  - is_admin: " . ($isAdmin ? 'true' : 'false'));
    error_log("  - isTechnik: " . ($isTechnik ? 'true' : 'false'));
    error_log("  - ŘEŠENÍ: Zkontrolujte, zda uživatel má v databázi roli obsahující 'technik' nebo 'technician'");
    header('Location: login.php?redirect=photocustomer.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.min.js" defer></script>

  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#000000">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="apple-mobile-web-app-title" content="WGS">
  <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">

  <!-- PWA -->
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">
  
  <title>Fotodokumentace – White Glove Service</title>
  <meta name="description" content="Fotodokumentace servisu White Glove Service. Pořizování a správa fotek před, během a po opravě nábytku.">
  
  <!-- Google Fonts - Natuzzi style -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">
  
  <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/photocustomer.min.css" as="style">

  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/photocustomer.min.css">
  <link rel="stylesheet" href="assets/css/photocustomer-collapsible.min.css">
  <!-- Univerzální tmavý styl pro všechny modály -->
  <link rel="stylesheet" href="assets/css/universal-modal-theme.min.css">
</head>

<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>
<!-- ČERNÁ HORNÍ PANEL -->

<!-- HLAVNÍ OBSAH -->
<div class="main-content">

  <!-- ROZBALOVACÍ INFORMACE O ZÁKAZNÍKOVI -->
  <div class="customer-info-collapsible">
    <div class="customer-info-header" id="customerInfoToggle" role="button" tabindex="0" aria-expanded="false" aria-controls="customerInfoContent" aria-label="Rozbalit informace o zákazníkovi" data-storage-key="photocustomer-info-expanded">
      <span class="customer-info-name" id="customerInfoName">Zákazník</span>
      <svg class="customer-info-arrow" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
        <polyline points="6 9 12 15 18 9"></polyline>
      </svg>
    </div>

    <div class="customer-info-content" id="customerInfoContent" role="region" aria-labelledby="customerInfoToggle">
      <div class="info-row">
        <span class="info-label">Zákazník</span>
        <span class="info-value" id="customerName">-</span>
      </div>
      <div class="info-row">
        <span class="info-label">Adresa</span>
        <span class="info-value" id="customerAddress">-</span>
      </div>
      <div class="info-row">
        <span class="info-label">Model</span>
        <span class="info-value" id="customerModel">-</span>
      </div>
      <div class="info-row">
        <span class="info-label">Kontakt</span>
        <span class="info-value" id="customerContact">-</span>
      </div>
    </div>
  </div>
  
  <!-- SEKCE FOTOGRAFIÍ -->
  <div class="photo-section" data-capture-type="before">
    <div class="section-header">Before</div>
    <div id="preview-before" class="photo-preview"></div>
  </div>

  <div class="photo-section" data-capture-type="id">
    <div class="section-header">ID</div>
    <div id="preview-id" class="photo-preview"></div>
  </div>

  <div class="photo-section" data-capture-type="problem">
    <div class="section-header">Detail Bug</div>
    <div id="preview-problem" class="photo-preview"></div>
  </div>

  <div class="photo-section" data-capture-type="repair">
    <div class="section-header">Repair</div>
    <div id="preview-repair" class="photo-preview"></div>
  </div>

  <div class="photo-section" data-capture-type="after">
    <div class="section-header">After</div>
    <div id="preview-after" class="photo-preview"></div>
  </div>
  
  <!-- PROGRESS BAR -->
  <div class="progress-container" role="status" aria-live="polite">
    <div class="progress-bar" role="progressbar" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100" aria-label="Průběh nahrávání">
      <div class="progress-fill" id="progressBar"></div>
    </div>
    <div class="progress-text" id="compressionInfo">Celkem nahráno: 0 souborů (max 30 doporučeno)</div>
  </div>
  
  <!-- TLAČÍTKA -->
  <div class="btn-group">
    <button class="btn" id="btnSaveToProtocol">Odeslat do protokolu</button>
    <button class="btn btn-secondary" data-navigate="seznam.php">Zpět</button>
  </div>
  
</div>

<!-- WAIT DIALOG -->
<div class="wait-dialog" id="waitDialog" role="status" aria-live="polite" aria-label="Probíhá operace">
  <div class="wait-content">
    <div class="spinner" aria-hidden="true"></div>
    <div class="wait-text" id="waitMsg">Čekejte...</div>
  </div>
</div>

<!-- ALERT -->
<div class="alert" id="alert" role="alert" aria-live="assertive"></div>

<!-- HIDDEN FILE INPUT -->
<!-- OPRAVENO: accept="image/*,video/*" místo špatného "assets/img/*" -->
<input type="file" id="mediaInput" accept="image/*,video/*" capture="environment" multiple>

<!-- External JavaScript -->
<script src="assets/js/customer-collapse.min.js" defer></script>
<script src="assets/js/photocustomer.min.js" defer></script>
</body>
</html>
