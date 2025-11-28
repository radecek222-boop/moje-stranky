<?php
require_once "init.php";

// FIX 1: Generovat CSRF token v PHP pro okamzitou dostupnost v HTML
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
  <!-- Tmavý styl pro login box -->
  <link rel="stylesheet" href="assets/css/login-dark-theme.css">
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<main class="main-content">
<div class="container">
  <div class="logo">
    <h1 data-lang-cs="Registrace" data-lang-en="Registration" data-lang-it="Registrazione">Registrace</h1>
    <div class="subtitle" data-lang-cs="Vyplňte registrační formulář" data-lang-en="Fill in the registration form" data-lang-it="Compila il modulo di registrazione">Vyplňte registrační formulář</div>
  </div>

  <div id="notification" class="notification"></div>

  <form id="registrationForm">
    <!-- FIX 1: CSRF token vlozen primo v PHP - okamzite dostupny, zadna race condition -->
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8'); ?>">

    <div class="form-group">
      <label for="regKey" data-lang-cs="Registrační klíč *" data-lang-en="Registration Key *" data-lang-it="Chiave di Registrazione *">Registrační klíč *</label>
      <input type="password" id="regKey" name="registration_key" data-lang-cs-placeholder="Zadejte klíč" data-lang-en-placeholder="Enter key" data-lang-it-placeholder="Inserisci chiave" placeholder="Zadejte klíč" required>
      <div class="helper-text" data-lang-cs="Klíč dostáváte od administátora" data-lang-en="You receive the key from the administrator" data-lang-it="Ricevi la chiave dall'amministratore">Klíč dostáváte od administátora</div>
    </div>

    <div class="form-group">
      <label for="regName" data-lang-cs="Jméno a příjmení" data-lang-en="Full Name" data-lang-it="Nome Completo">Jméno a příjmení</label>
      <input type="text" id="regName" name="name" data-lang-cs-placeholder="Jan Novák" data-lang-en-placeholder="John Doe" data-lang-it-placeholder="Mario Rossi" placeholder="Jan Novák" required>
    </div>

    <div class="form-group">
      <label for="regEmail">Email</label>
      <input type="email" id="regEmail" name="email" data-lang-cs-placeholder="vas@email.cz" data-lang-en-placeholder="your@email.com" data-lang-it-placeholder="tua@email.it" placeholder="vas@email.cz" required>
    </div>

    <div class="form-group">
      <label for="regPhone" data-lang-cs="Telefon (volitelně)" data-lang-en="Phone (optional)" data-lang-it="Telefono (opzionale)">Telefon (volitelně)</label>
      <input type="tel" id="regPhone" name="phone" placeholder="+420 777 777 777">
    </div>

    <div class="form-group">
      <label for="regPassword" data-lang-cs="Heslo" data-lang-en="Password" data-lang-it="Password">Heslo</label>
      <input type="password" id="regPassword" name="password" placeholder="••••••••" required>
      <div class="helper-text" data-lang-cs="Minimálně 12 znaků (velké/malé písmena, čísla a znaky)" data-lang-en="At least 12 characters (uppercase/lowercase letters, numbers and symbols)" data-lang-it="Almeno 12 caratteri (lettere maiuscole/minuscole, numeri e simboli)">Minimálně 12 znaků (velké/malé písmena, čísla a znaky)</div>
    </div>

    <div class="form-group">
      <label for="regPasswordConfirm" data-lang-cs="Potvrzení hesla" data-lang-en="Password Confirmation" data-lang-it="Conferma Password">Potvrzení hesla</label>
      <input type="password" id="regPasswordConfirm" name="passwordConfirm" placeholder="••••••••" required>
    </div>

    <button type="submit" class="btn btn-primary" style="width: 100%;" data-lang-cs="Zaregistrovat se" data-lang-en="Register" data-lang-it="Registrati">Zaregistrovat se</button>
  </form>

  <div class="links" style="text-align: center; margin-top: 2rem;">
    <p><span data-lang-cs="Již máte účet?" data-lang-en="Already have an account?" data-lang-it="Hai già un account?">Již máte účet?</span> <a href="login.php" data-lang-cs="Přihlaste se" data-lang-en="Log in" data-lang-it="Accedi">Přihlaste se</a></p>
  </div>

</div>
</main>

<script src="assets/js/logger.js" defer></script>
<script src="assets/js/csrf-auto-inject.js" defer></script>
<script src="assets/js/registration.js" defer></script>

<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
</body>
</html>
