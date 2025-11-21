<?php
require_once "init.php";

// ✅ FIX 1: Generovat CSRF token v PHP pro okamžitou dostupnost v HTML
// Eliminuje race condition s async fetch v csrf-auto-inject.js
$csrfToken = generateCSRFToken();

// ✅ FIX: Pokud je uživatel JIŽ PŘIHLÁŠEN a má redirect parametr, přesměrovat ho tam
// SCÉNÁŘ: photocustomer.php redirectuje na login.php?redirect=photocustomer.php
// ale technik JE stále přihlášen → neměl by vidět login formulář, měl by skočit na photocustomer.php
if (isset($_SESSION['user_id']) && isset($_GET['redirect'])) {
    $redirect = $_GET['redirect'];

    // ✅ BEZPEČNOST: Whitelist povolených redirect URLs (ochrana proti open redirect)
    $allowedRedirects = [
        'photocustomer.php',
        'seznam.php',
        'protokol.php',
        'statistiky.php',
        'novareklamace.php',
        'admin.php'
    ];

    // Extrahuj jen název souboru (bez path traversal)
    $redirectFile = basename($redirect);

    if (in_array($redirectFile, $allowedRedirects, true)) {
        error_log("LOGIN.PHP: Uživatel již přihlášen (user_id: {$_SESSION['user_id']}), redirectuji na: {$redirectFile}");
        header("Location: {$redirectFile}");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Přihlášení do klientského portálu White Glove Service. Správa reklamací Natuzzi, sledování servisních zásahů a přístup k dokumentům.">
  <meta name="theme-color" content="#000000">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="apple-mobile-web-app-title" content="WGS">
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">
  <title>White Glove Service – Přihlášení</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/login.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.css">
  <style>
    .admin-checkbox-group { margin-bottom: 1.5rem; padding: 1rem; background: #f5f5f5; border-radius: 4px; }
    .admin-checkbox-group input[type="checkbox"] { margin-right: 0.5rem; }
    .admin-checkbox-group label { font-weight: 500; cursor: pointer; }
    #userLoginFields { display: block; }
    #adminLoginFields { display: none; }
  </style>
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<main class="main-content">
<div class="container">
  <div class="logo">
    <h1>WGS</h1>
    <div class="subtitle">White Glove Service</div>
  </div>

  <div id="notification" class="notification"></div>

  <form id="loginForm">
    <!-- ✅ FIX 1: CSRF token vložen přímo v PHP - okamžitě dostupný, žádná race condition -->
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- CHECKBOX: Jsem administrátor -->
    <div class="admin-checkbox-group">
      <input type="checkbox" id="isAdmin" name="is_admin">
      <label for="isAdmin">Jsem administrátor</label>
    </div>

    <!-- USER LOGIN (EMAIL + HESLO) -->
    <div id="userLoginFields">
      <div class="form-group">
        <label for="userEmail">Email</label>
        <input type="email" id="userEmail" name="email" placeholder="vas@email.cz">
      </div>
      <div class="form-group">
        <label for="userPassword">Heslo</label>
        <input type="password" id="userPassword" name="password" placeholder="••••••••">
      </div>
    </div>

    <!-- ADMIN LOGIN (ADMIN KEY ONLY) -->
    <div id="adminLoginFields">
      <div class="form-group">
        <label for="adminKey">Administrátorský klíč</label>
        <input type="password" id="adminKey" name="admin_key" placeholder="Zadejte klíč">
      </div>
    </div>

    <button type="submit" id="loginButton" class="btn btn-primary" style="width: 100%; margin-top: 1rem;">Přihlásit se</button>
  </form>

  <div class="links" style="text-align: center; margin-top: 2rem;">
    <p>Zapomněl/a jsi heslo? <a href="password_reset.php">Resetovat heslo</a></p>
  <p>Nemáte účet? <a href="registration.php">Zaregistrujte se</a></p>
  </div>

</div>
</main>

<script src="assets/js/logger.js" defer></script>
<script src="assets/js/csrf-auto-inject.js" defer></script>
<script src="assets/js/login.js" defer></script>
<script src="assets/js/welcome-modal.js" defer></script>
</body>
</html>
