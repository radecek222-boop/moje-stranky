<?php
require_once "init.php";

// ✅ FIX 1: Generovat CSRF token v PHP pro okamžitou dostupnost v HTML
// Eliminuje race condition s async fetch v csrf-auto-inject.js
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Registrace do portálu White Glove Service. Získejte přístup k online správě servisních požadavků Natuzzi a sledujte stav reklamací.">
  <meta name="theme-color" content="#000000">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="apple-mobile-web-app-title" content="WGS">
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">
  <title>White Glove Service – Registrace</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/login.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.css">
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<main class="main-content">
<div class="container">
  <div class="logo">
    <h1>Registrace</h1>
    <div class="subtitle">Vyplňte registrační formulář</div>
  </div>

  <div id="notification" class="notification"></div>

  <form id="registrationForm">
    <!-- ✅ FIX 1: CSRF token vložen přímo v PHP - okamžitě dostupný, žádná race condition -->
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="form-group">
      <label for="regKey">Registrační klíč *</label>
      <input type="password" id="regKey" name="registration_key" placeholder="Zadejte klíč" required>
      <div class="helper-text">Klíč dostáváte od administátora</div>
    </div>

    <div class="form-group">
      <label for="regName">Jméno a příjmení</label>
      <input type="text" id="regName" name="name" placeholder="Jan Novák" required>
    </div>

    <div class="form-group">
      <label for="regEmail">Email</label>
      <input type="email" id="regEmail" name="email" placeholder="vas@email.cz" required>
    </div>

    <div class="form-group">
      <label for="regPhone">Telefon (volitelně)</label>
      <input type="tel" id="regPhone" name="phone" placeholder="+420 777 777 777">
    </div>

    <div class="form-group">
      <label for="regPassword">Heslo</label>
      <input type="password" id="regPassword" name="password" placeholder="••••••••" required>
      <div class="helper-text">Minimálně 12 znaků (velké/malé písmena, čísla a znaky)</div>
    </div>

    <div class="form-group">
      <label for="regPasswordConfirm">Potvrzení hesla</label>
      <input type="password" id="regPasswordConfirm" name="passwordConfirm" placeholder="••••••••" required>
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%;">Zaregistrovat se</button>
  </form>

  <div class="links" style="text-align: center; margin-top: 2rem;">
    <p>Již máte účet? <a href="login.php">Přihlaste se</a></p>
  </div>

</div>
</main>

<script src="assets/js/logger.js" defer></script>
<script src="assets/js/csrf-auto-inject.js" defer></script>
<script src="assets/js/registration.js" defer></script>

</body>
</html>
