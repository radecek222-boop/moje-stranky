<?php require_once "init.php"; ?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#020611">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="WGS">
  <meta name="description" content="Aplikace White Glove Service je momentálně offline. Zkontrolujte připojení k internetu a zkuste to znovu.">

  <!-- PWA -->
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">

  <title>Offline – White Glove Service</title>
  
    <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/offline.css">
</head>
<body>
<main>
  <div class="offline-container">
    <div class="offline-icon">
      <div class="wifi-icon"></div>
    </div>
    
    <h1>Jste offline</h1>
    
    <p>
      Aplikace White Glove Service vyžaduje připojení k internetu.
      Zkontrolujte prosím své připojení a zkuste to znovu.
    </p>
    
    <div class="status" id="status">
      ⚠️ Nelze se připojit k serveru
    </div>
    
    <button class="retry-btn" id="retryBtn">
      🔄 Zkusit znovu
    </button>

    <p style="margin-top: 2rem; font-size: 0.85rem; opacity: 0.7;">
      Pokud problém přetrvává, kontaktujte správce systému.
    </p>
  </div>

  <!-- Google Translate Widget -->
  <div id="google_translate_element" style="position:fixed;top:15px;right:15px;z-index:9999;"></div>
  <script type="text/javascript">  </script>
  <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>
</main>

<!-- External JavaScript -->
<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.js" defer></script>

<script src="assets/js/offline.js" defer></script>
</body>
</html>
