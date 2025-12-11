<?php
/**
 * QR Kontakt - Rychlé kontaktní centrum
 * Přístupné pouze přes přímou URL (QR kód)
 * Není odkazováno z navigace ani menu
 */
require_once "init.php";
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
  <meta name="theme-color" content="#000000">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="apple-mobile-web-app-title" content="WGS Kontakt">
  <meta name="robots" content="noindex, nofollow">
  <meta name="description" content="Rychlý kontakt na White Glove Service - telefon, email, web.">

  <!-- PWA -->
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">

  <title>WGS - Rychlý kontakt</title>

  <!-- Google Fonts - Poppins -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <!-- Externí CSS -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.min.css">

  <style>
    /* QR Kontakt - specifické styly */
    .qr-kontakt-page {
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      background: #fff;
    }

    .qr-kontakt-header {
      background: #000;
      padding: 1rem 1.5rem;
      text-align: center;
    }

    .qr-kontakt-logo {
      color: #fff;
      font-family: 'Poppins', sans-serif;
      font-size: 1.5rem;
      font-weight: 600;
      letter-spacing: 0.15em;
      text-decoration: none;
      text-transform: uppercase;
    }

    .qr-kontakt-logo span {
      display: block;
      font-size: 0.65rem;
      font-weight: 300;
      letter-spacing: 0.1em;
      margin-top: -2px;
    }

    .qr-kontakt-main {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 1rem 1.5rem 2rem;
      max-width: 500px;
      margin: 0 auto;
      width: 100%;
    }

    .qr-kontakt-title {
      font-family: 'Poppins', sans-serif;
      font-size: clamp(1.6rem, 6.5vw, 2.2rem);
      font-weight: 600;
      color: #000;
      text-align: center;
      letter-spacing: 0.05em;
      text-transform: uppercase;
      margin-bottom: 0.5rem;
      white-space: nowrap;
    }

    .qr-kontakt-subtitle {
      font-family: 'Poppins', sans-serif;
      font-size: clamp(0.65rem, 2.5vw, 0.95rem);
      font-weight: 300;
      color: #555;
      text-align: center;
      margin-bottom: 3.5rem;
      white-space: nowrap;
    }

    .qr-kontakt-buttons {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }

    .qr-kontakt-btn {
      display: block;
      width: 100%;
      padding: 0.9rem 1.5rem;
      background: #000;
      color: #fff;
      font-family: 'Poppins', sans-serif;
      font-size: 1.1rem;
      font-weight: 600;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      text-decoration: none;
      text-align: center;
      border: 2px solid #000;
      border-radius: 8px;
      cursor: pointer;
      transition: all 0.3s ease;
      min-height: 54px;
      line-height: 1.4;
      -webkit-tap-highlight-color: transparent;
    }

    .qr-kontakt-btn:hover,
    .qr-kontakt-btn:focus {
      background: #333;
      border-color: #333;
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .qr-kontakt-btn:active {
      transform: translateY(0);
      box-shadow: none;
    }

    .qr-kontakt-btn-secondary {
      background: #fff;
      color: #000;
      border: 2px solid #000;
    }

    .qr-kontakt-btn-secondary:hover,
    .qr-kontakt-btn-secondary:focus {
      background: #f5f5f5;
      border-color: #000;
    }

    .qr-kontakt-footer {
      padding: 1rem;
      text-align: center;
      font-family: 'Poppins', sans-serif;
      font-size: 0.75rem;
      color: #999;
    }

    /* Responzivní úpravy */
    @media (min-width: 768px) {
      .qr-kontakt-main {
        padding: 3rem 2rem;
      }

      .qr-kontakt-btn {
        padding: 1rem 2rem;
        font-size: 1.15rem;
        min-height: 58px;
      }
    }

    @media (max-width: 380px) {
      .qr-kontakt-btn {
        padding: 0.75rem 1.25rem;
        font-size: 1rem;
        min-height: 48px;
      }
    }

    /* Přístupnost - focus stavy */
    .qr-kontakt-btn:focus {
      outline: 3px solid #666;
      outline-offset: 2px;
    }

    .qr-kontakt-btn:focus:not(:focus-visible) {
      outline: none;
    }

    .qr-kontakt-btn:focus-visible {
      outline: 3px solid #666;
      outline-offset: 2px;
    }
  </style>
</head>
<body class="qr-kontakt-page">

  <header class="qr-kontakt-header">
    <a href="https://www.wgs-service.cz" class="qr-kontakt-logo">
      WGS
      <span>White Glove Service</span>
    </a>
  </header>

  <main class="qr-kontakt-main" id="main-content">
    <h1 class="qr-kontakt-title">White Glove Servis</h1>
    <p class="qr-kontakt-subtitle">specializovaný autorizovaný servis Natuzzi</p>

    <div class="qr-kontakt-buttons">
      <!-- Tlačítko 1: Zavolat -->
      <a href="tel:+420725965826" class="qr-kontakt-btn" aria-label="Zavolat na číslo +420 725 965 826">
        Zavolat
      </a>

      <!-- Tlačítko 2: Napsat e-mail -->
      <a href="mailto:info@wgs-service.cz?subject=Kontakt%20z%20QR" class="qr-kontakt-btn" aria-label="Napsat e-mail na info@wgs-service.cz">
        Napsat e-mail
      </a>

      <!-- Tlačítko 3: Navštívit web -->
      <a href="https://www.wgs-service.cz" class="qr-kontakt-btn qr-kontakt-btn-secondary" aria-label="Navštívit hlavní webové stránky WGS">
        Navštívit web
      </a>
    </div>
  </main>

  <footer class="qr-kontakt-footer">
    &copy; <?php echo date('Y'); ?> White Glove Service
  </footer>

</body>
</html>
