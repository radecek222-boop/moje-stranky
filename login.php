<?php require_once "init.php"; ?>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>White Glove Service – Přihlášení</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/login.min.css">
  <style>
    .admin-checkbox-group { margin-bottom: 1.5rem; padding: 1rem; background: #f5f5f5; border-radius: 4px; }
    .admin-checkbox-group input[type="checkbox"] { margin-right: 0.5rem; }
    .admin-checkbox-group label { font-weight: 500; cursor: pointer; }
    #userLoginFields { display: block; }
    #adminLoginFields { display: none; }
  </style>
</head>
<body>


<main class="main-content">
<div class="container">
  <div class="logo">
    <h1>WGS</h1>
    <div class="subtitle">White Glove Service</div>
  </div>

  <div id="notification" class="notification"></div>

  <form id="loginForm">
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

<script src="assets/js/logger.js"></script>
<script src="assets/js/csrf-auto-inject.js"></script>
<script src="assets/js/login.js"></script>

<script src="assets/js/welcome-modal.js"></script>
</body>
</html>
