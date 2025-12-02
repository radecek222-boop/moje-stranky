<?php require_once "init.php"; ?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Obnovení hesla do portálu White Glove Service. Ověřte svůj registrační klíč a nastavte nové přístupové údaje pro správu servisních požadavků.">
  <meta name="theme-color" content="#000000">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="apple-mobile-web-app-title" content="WGS">
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">
  <title>White Glove Service – Reset Hesla</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet"></noscript>
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/login.min.css">
  <!-- Tmavý styl pro login box -->
  <link rel="stylesheet" href="assets/css/login-dark-theme.min.css">
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<main class="main-content">
<div class="container">
  <div class="logo">
    <h1>Reset Hesla</h1>
    <div class="subtitle">Obnovte si heslo pomocí registračního klíče</div>
  </div>

  <div id="notification" class="notification"></div>

  <!-- STEP 1: Verify -->
  <div id="step1-verify">
    <form id="verifyForm">
      <div class="form-group">
        <label for="resetEmail">Email</label>
        <input type="email" id="resetEmail" name="email" placeholder="vas@email.cz" required>
      </div>

      <div class="form-group">
        <label for="resetKey">Registrační klíč</label>
        <input type="password" id="resetKey" name="registration_key" autocomplete="off" placeholder="Váš registrační klíč" required>
        <div class="helper-text">Klíč, kterým jste se registrovali</div>
      </div>

      <button type="submit" class="btn btn-primary" style="width: 100%;">Ověřit identitu</button>
    </form>
  </div>

  <!-- STEP 2: Change Password -->
  <div id="step2-change" style="display: none;">
    <form id="changePasswordForm">
      <p style="color: #666; margin-bottom: 1.5rem;">
        <strong id="userNameDisplay"></strong>, nyní si můžeš nastavit nové heslo.
      </p>

      <div class="form-group">
        <label for="newPassword">Nové heslo</label>
        <input type="password" id="newPassword" name="new_password" placeholder="••••••••" required minlength="8" autocomplete="new-password">
        <div class="helper-text">Minimálně 8 znaků</div>
      </div>

      <div class="form-group">
        <label for="newPasswordConfirm">Potvrzení hesla</label>
        <input type="password" id="newPasswordConfirm" name="new_password_confirm" placeholder="••••••••" required minlength="8" autocomplete="new-password">
      </div>

      <button type="submit" class="btn btn-success" style="width: 100%;">Nastavit nové heslo</button>
      <button type="button" class="btn" style="width: 100%; margin-top: 0.5rem;" onclick="goBack()">Zpět</button>
    </form>
  </div>

  <div class="links" style="text-align: center; margin-top: 2rem;">
    <p>Chceš se přihlásit? <a href="login.php">Přihlášení</a></p>
  </div>

</div>
</main>

<script src="assets/js/logger.min.js" defer></script>
<script src="assets/js/csrf-auto-inject.js" defer></script>
<script src="assets/js/password-reset.min.js" defer></script>

</body>
</html>
