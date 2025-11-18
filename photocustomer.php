<?php
require_once "init.php";

// 🔍 DEBUG MODE: Zobrazí session data místo redirectu
if (isset($_GET['debug']) && $_GET['debug'] === '1') {
    header('Content-Type: text/html; charset=utf-8');
    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>DEBUG: photocustomer.php</title>";
    echo "<style>body{font-family:monospace;background:#1a1a1a;color:#00ff88;padding:20px;}";
    echo "pre{background:#000;padding:15px;border-radius:5px;}</style></head><body>";
    echo "<h1>🔍 DEBUG MODE: photocustomer.php</h1>";
    echo "<p>Datum: " . date('Y-m-d H:i:s') . "</p><hr>";

    echo "<h2>📊 \$_SESSION obsah:</h2><pre>";
    print_r($_SESSION);
    echo "</pre>";

    echo "<h2>🔑 VŠECHNY SESSION KLÍČE:</h2>";
    echo "<ul style='background:#000;padding:15px;'>";
    foreach ($_SESSION as $key => $value) {
        $displayValue = is_bool($value) ? ($value ? 'TRUE' : 'FALSE') : (is_string($value) || is_numeric($value) ? htmlspecialchars($value) : gettype($value));
        echo "<li><strong>$key</strong>: $displayValue</li>";
    }
    echo "</ul>";

    echo "<h2>🔑 Kontrolní hodnoty:</h2>";
    echo "<p>isset(\$_SESSION['user_id']): " . (isset($_SESSION['user_id']) ? '✅ TRUE' : '❌ FALSE') . "</p>";
    echo "<p>\$_SESSION['user_id']: " . ($_SESSION['user_id'] ?? 'NENÍ NASTAVENO') . "</p>";
    echo "<p>\$_SESSION['role']: " . ($_SESSION['role'] ?? 'NENÍ NASTAVENO') . "</p>";
    echo "<p>\$_SESSION['used_role']: " . ($_SESSION['used_role'] ?? '❌ NENÍ NASTAVENO') . "</p>";
    echo "<p>\$_SESSION['user_role']: " . ($_SESSION['user_role'] ?? '❌ NENÍ NASTAVENO') . "</p>";
    echo "<p>isset(\$_SESSION['is_admin']): " . (isset($_SESSION['is_admin']) ? '✅ TRUE' : '❌ FALSE') . "</p>";

    echo "<h2>🚪 Co by se stalo bez debug režimu:</h2>";

    // SIMULACE KROK 1
    if (!isset($_SESSION['user_id'])) {
        echo "<p style='color:#ff4444;'>❌ KROK 1: REDIRECT na login.php (chybí user_id)</p>";
    } else {
        echo "<p style='color:#00ff88;'>✅ KROK 1: PASS (user_id existuje)</p>";

        // SIMULACE KROK 2
        echo "<h3 style='margin-top:20px;'>🔍 KROK 2: Kontrola role</h3>";
        $rawRole = (string) ($_SESSION['role'] ?? '');
        $normalizedRole = strtolower(trim($rawRole));
        $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

        echo "<p>\$rawRole = '" . htmlspecialchars($rawRole) . "'</p>";
        echo "<p>\$normalizedRole = '" . htmlspecialchars($normalizedRole) . "'</p>";
        echo "<p>\$isAdmin = " . ($isAdmin ? 'TRUE' : 'FALSE') . "</p>";

        $technikKeywords = ['technik', 'technician'];
        $isTechnik = in_array($normalizedRole, $technikKeywords, true);
        echo "<p>in_array('{$normalizedRole}', ['technik', 'technician']): " . ($isTechnik ? 'TRUE' : 'FALSE') . "</p>";

        if (!$isTechnik) {
            echo "<p>Zkouším partial match...</p>";
            foreach ($technikKeywords as $keyword) {
                $found = strpos($normalizedRole, $keyword) !== false;
                echo "<p>  strpos('{$normalizedRole}', '{$keyword}'): " . ($found ? 'FOUND' : 'NOT FOUND') . "</p>";
                if ($found) {
                    $isTechnik = true;
                    break;
                }
            }
        }

        echo "<p><strong>\$isTechnik (final) = " . ($isTechnik ? 'TRUE' : 'FALSE') . "</strong></p>";

        echo "<h3 style='margin-top:20px;'>🚪 Finální rozhodnutí:</h3>";
        echo "<p>(!isAdmin && !isTechnik) = (!" . ($isAdmin ? 'TRUE' : 'FALSE') . " && !" . ($isTechnik ? 'TRUE' : 'FALSE') . ")</p>";
        echo "<p>= (" . ($isAdmin ? 'FALSE' : 'TRUE') . " && " . ($isTechnik ? 'FALSE' : 'TRUE') . ")</p>";
        echo "<p>= <strong>" . ((!$isAdmin && !$isTechnik) ? 'TRUE' : 'FALSE') . "</strong></p>";

        if (!$isAdmin && !$isTechnik) {
            echo "<p style='color:#ff4444;font-size:18px;'><strong>❌ KROK 2: REDIRECT na login.php (není admin ANI technik)</strong></p>";
        } else {
            echo "<p style='color:#00ff88;font-size:18px;'><strong>✅ KROK 2: PASS (je admin NEBO technik) → PŘÍSTUP POVOLEN!</strong></p>";
        }
    }

    echo "<hr><p><a href='photocustomer.php' style='color:#00ff88;'>→ Zkusit bez debug režimu</a></p>";
    echo "</body></html>";
    exit;
}

// ✅ KROK 1: Kontrola, zda je uživatel vůbec přihlášen
// DŮLEŽITÉ: Musíme zkontrolovat user_id PŘED kontrolou role!
if (!isset($_SESSION['user_id'])) {
    error_log("PHOTOCUSTOMER: Přístup odepřen - uživatel není přihlášen (chybí user_id)");
    header('Location: login.php?redirect=photocustomer.php');
    exit;
}

// ✅ DEBUG: Logování session pouze v development režimu
// BEZPEČNOST: Nelogujeme PII (osobní údaje) v produkci
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_log("=== PHOTOCUSTOMER.PHP DEBUG START ===");
    error_log("user_id: " . $_SESSION['user_id']);
    error_log("is_admin isset: " . (isset($_SESSION['is_admin']) ? 'ANO' : 'NE'));
    error_log("role: " . ($_SESSION['role'] ?? 'NENÍ NASTAVENO'));
    error_log("=== PHOTOCUSTOMER.PHP DEBUG END ===");
}

// ✅ KROK 2: Kontrola přístupu - POUZE admin a technik
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
<script src="assets/js/logger.js" defer></script>

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
</head>

<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>
<!-- ČERNÁ HORNÍ PANEL -->

<!-- HLAVNÍ OBSAH -->
<div class="main-content">
  
  <!-- HLAVIČKA STRÁNKY -->
  <div class="page-header">
    <h1 class="page-title">Fotodokumentace</h1>
    <p class="page-subtitle">Pořízení fotografií a videa ze servisu</p>
  </div>
  
  <!-- INFORMACE O ZAKÁZCE -->
  <div class="info-box">
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
  
  <!-- SEKCE FOTOGRAFIÍ -->
  <div class="photo-section" onclick="openMediaCapture('before')">
    <div class="section-header">Before</div>
    <div id="preview-before" class="photo-preview"></div>
  </div>
  
  <div class="photo-section" onclick="openMediaCapture('id')">
    <div class="section-header">ID</div>
    <div id="preview-id" class="photo-preview"></div>
  </div>
  
  <div class="photo-section" onclick="openMediaCapture('problem')">
    <div class="section-header">Detail Bug</div>
    <div id="preview-problem" class="photo-preview"></div>
  </div>
  
  <div class="photo-section" onclick="openMediaCapture('repair')">
    <div class="section-header">Repair</div>
    <div id="preview-repair" class="photo-preview"></div>
  </div>
  
  <div class="photo-section" onclick="openMediaCapture('after')">
    <div class="section-header">After</div>
    <div id="preview-after" class="photo-preview"></div>
  </div>
  
  <!-- PROGRESS BAR -->
  <div class="progress-container">
    <div class="progress-bar">
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
<div class="wait-dialog" id="waitDialog">
  <div class="wait-content">
    <div class="spinner"></div>
    <div class="wait-text" id="waitMsg">Čekejte...</div>
  </div>
</div>

<!-- ALERT -->
<div class="alert" id="alert"></div>

<!-- HIDDEN FILE INPUT -->
<!-- OPRAVENO: accept="image/*,video/*" místo špatného "assets/img/*" -->
<input type="file" id="mediaInput" accept="image/*,video/*" capture="environment" multiple>

<!-- External JavaScript -->
<script src="assets/js/photocustomer.js" defer></script>
</body>
</html>
