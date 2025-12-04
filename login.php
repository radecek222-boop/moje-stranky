<?php
require_once "init.php";

// FIX 1: Generovat CSRF token v PHP pro okamzitou dostupnost v HTML
// Eliminuje race condition s async fetch v csrf-auto-inject.min.js
$csrfToken = generateCSRFToken();

// FIX: Pokud je uzivatel JIZ PRIHLASEN a ma redirect parametr, presmerovat ho tam
// SCENAR: photocustomer.php redirectuje na login.php?redirect=photocustomer.php
// ale technik JE stale prihlasen -> nemel by videt login formular, mel by skocit na photocustomer.php
if (isset($_SESSION['user_id']) && isset($_GET['redirect'])) {
    $redirect = $_GET['redirect'];

    // BEZPECNOST: Whitelist povolenych redirect URLs (ochrana proti open redirect)
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
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/login.min.css" as="style">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/login.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.min.css" media="print" onload="this.media='all'">
  <noscript><link rel="stylesheet" href="assets/css/mobile-responsive.min.css"></noscript>
  <link rel="stylesheet" href="assets/css/welcome-modal.min.css">
  <!-- Univerzální tmavý styl pro všechny modály -->
  <link rel="stylesheet" href="assets/css/universal-modal-theme.min.css">
  <!-- Tmavý styl pro login box -->
  <link rel="stylesheet" href="assets/css/login-dark-theme.min.css">
  <style>
    /* Admin checkbox group - tmavý styl je v login-dark-theme.min.css */
    .admin-checkbox-group { margin-bottom: 1.5rem; padding: 1rem; border-radius: 4px; }
    .admin-checkbox-group input[type="checkbox"] { margin-right: 0.5rem; }
    .admin-checkbox-group label { font-weight: 500; cursor: pointer; }
  </style>

  <!-- Analytics Tracker -->
  <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<main id="main-content" class="main-content" x-data="rememberMeModal" x-init="init">
<div class="container">
  <div class="logo">
    <h1>WGS</h1>
    <div class="subtitle" data-lang-cs="White Glove Service" data-lang-en="White Glove Service" data-lang-it="White Glove Service">White Glove Service</div>
  </div>

  <div id="notification" class="notification" role="alert" aria-live="assertive"></div>

  <form id="loginForm">
    <!-- FIX 1: CSRF token vlozen primo v PHP - okamzite dostupny, zadna race condition -->
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

    <!-- CHECKBOX: Jsem administrátor -->
    <div class="admin-checkbox-group">
      <input type="checkbox" id="isAdmin" name="is_admin">
      <label for="isAdmin" data-lang-cs="Jsem administrátor" data-lang-en="I am administrator" data-lang-it="Sono amministratore">Jsem administrátor</label>
    </div>

    <!-- USER LOGIN (EMAIL + HESLO) -->
    <div id="userLoginFields">
      <div class="form-group">
        <label for="userEmail" data-lang-cs="Email" data-lang-en="Email" data-lang-it="Email">Email</label>
        <input type="email" id="userEmail" name="email"
               autocomplete="email"
               data-lang-cs-placeholder="vas@email.cz"
               data-lang-en-placeholder="your@email.com"
               data-lang-it-placeholder="tua@email.it"
               placeholder="vas@email.cz">
      </div>
      <div class="form-group">
        <label for="userPassword" data-lang-cs="Heslo" data-lang-en="Password" data-lang-it="Password">Heslo</label>
        <input type="password" id="userPassword" name="password"
               autocomplete="current-password"
               enterkeyhint="send"
               data-lang-cs-placeholder="••••••••"
               data-lang-en-placeholder="••••••••"
               data-lang-it-placeholder="••••••••"
               placeholder="••••••••">
      </div>

      <!-- FIX 11: Remember Me checkbox - Alpine.js handler (Step 36) -->
      <div class="form-group remember-me-group" style="margin-top: 0.5rem;">
        <div style="display: flex; align-items: center; margin-bottom: 0.3rem;">
          <input type="checkbox" id="rememberMe" name="remember_me" style="width: auto; margin-right: 8px;" @change="onCheckboxChange" aria-describedby="rememberMeHelp">
          <label for="rememberMe" style="display: inline; font-weight: 500; cursor: pointer; text-transform: uppercase; letter-spacing: 0.05em; font-size: 0.85rem;" data-lang-cs="Zapamatovat si mě (30 dní)" data-lang-en="Remember me (30 days)" data-lang-it="Ricordami (30 giorni)">
            Zapamatovat si mě (30 dní)
          </label>
        </div>
        <div class="remember-me-helper" id="rememberMeHelp" style="margin-left: 28px; font-size: 0.75rem; color: #999; font-weight: 300;">
          <span data-lang-cs="pouze na osobním zařízení" data-lang-en="personal device only" data-lang-it="solo su dispositivo personale">pouze na osobním zařízení</span>
        </div>
      </div>
    </div>

    <!-- ADMIN LOGIN (ADMIN KEY ONLY) -->
    <div id="adminLoginFields" class="hidden">
      <div class="form-group">
        <label for="adminKey" data-lang-cs="Administrátorský klíč" data-lang-en="Administrator key" data-lang-it="Chiave amministratore">Administrátorský klíč</label>
        <input type="password" id="adminKey" name="admin_key"
               autocomplete="off"
               enterkeyhint="send"
               data-lang-cs-placeholder="Zadejte klíč"
               data-lang-en-placeholder="Enter key"
               data-lang-it-placeholder="Inserisci chiave"
               placeholder="Zadejte klíč">
      </div>
    </div>

    <button type="submit" id="loginButton" class="btn btn-primary" style="width: 100%; margin-top: 1rem;" data-lang-cs="Přihlásit se" data-lang-en="Log in" data-lang-it="Accedi">Přihlásit se</button>
  </form>

  <div class="links" style="text-align: center; margin-top: 2rem;">
    <p><span data-lang-cs="Zapomněl/a jsi heslo?" data-lang-en="Forgot password?" data-lang-it="Hai dimenticato la password?">Zapomněl/a jsi heslo?</span> <a href="password_reset.php" data-lang-cs="Resetovat heslo" data-lang-en="Reset password" data-lang-it="Resetta password">Resetovat heslo</a></p>
    <p><span data-lang-cs="Nemáte účet?" data-lang-en="Don't have an account?" data-lang-it="Non hai un account?">Nemáte účet?</span> <a href="registration.php" data-lang-cs="Zaregistrujte se" data-lang-en="Sign up" data-lang-it="Registrati">Zaregistrujte se</a></p>
  </div>

</div>

<!-- Remember Me Confirmation Overlay - Alpine.js (Step 36) -->
<div id="rememberMeOverlay" @click="overlayClick">
  <div class="remember-me-confirm-box">
    <h3 data-lang-cs="Upozornění" data-lang-en="Warning" data-lang-it="Avviso">Upozornění</h3>
    <p data-lang-cs="Jste si jisti, že nepoužívá toto zařízení jiný uživatel?" data-lang-en="Are you sure this device is not used by another user?" data-lang-it="Sei sicuro che questo dispositivo non sia utilizzato da un altro utente?">
      Jste si jisti, že nepoužívá toto zařízení jiný uživatel?
    </p>
    <div class="remember-me-confirm-buttons">
      <button type="button" class="remember-me-btn-cancel" @click="cancel" data-lang-cs="Zrušit" data-lang-en="Cancel" data-lang-it="Annulla">Zrušit</button>
      <button type="button" class="remember-me-btn-confirm" @click="confirm" data-lang-cs="Potvrdit" data-lang-en="Confirm" data-lang-it="Conferma">Potvrdit</button>
    </div>
  </div>
</div>

</main>

<script src="assets/js/logger.min.js" defer></script>
<script src="assets/js/csrf-auto-inject.min.js" defer></script>
<script src="assets/js/welcome-modal.min.js" defer></script>
<script src="assets/js/login.min.js" defer></script>

<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
</body>
</html>
