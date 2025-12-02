<?php
require_once "init.php";
require_once __DIR__ . '/includes/seo_meta.php';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#000000">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black">
  <meta name="apple-mobile-web-app-title" content="WGS">
  <meta name="description" content="<?php echo getSeoDescription('index'); ?>">
  <?php renderSeoMeta('index'); ?>
  <?php renderSchemaOrg('index'); ?>
  <?php renderFaqSchema('index'); ?>

  <!-- PWA -->
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">

  <!-- Cache Control pro PWA aktualizace -->
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">

  <title><?php echo getSeoTitle('index'); ?></title>
  
  <!-- Preload critical resources -->
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/index.min.css" as="style">
  <link rel="preload" href="assets/img/natuzzi-hero.webp" as="image" type="image/webp" fetchpriority="high">

  <!-- Google Fonts - Natuzzi style - OPTIMIZED with font-display: optional -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet"></noscript>

  <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/index.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.css">

  <!-- Analytics Tracker -->
  <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
</head>

<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>


<!-- HERO SEKCE -->
<main>
  <section class="hero">
    <div class="hero-content">
      <h1 class="hero-title">WGS</h1>
      
      <div class="hero-subtitle">White Glove Service</div>
      
      <p class="hero-description" 
         data-lang-cs="Prémiový servis pro luxusní nábytek Natuzzi. Profesionální montáž, údržba a opravy s maximální péčí o každý detail."
         data-lang-en="Premium service for Natuzzi luxury furniture. Professional assembly, maintenance, and repairs with maximum attention to every detail."
         data-lang-it="Servizio premium per mobili di lusso Natuzzi. Montaggio, manutenzione e riparazioni professionali con la massima cura per ogni dettaglio.">
        Prémiový servis pro luxusní nábytek Natuzzi. Profesionální montáž, údržba a opravy s maximální péčí o každý detail.
      </p>
      
      <a href="novareklamace.php" class="cta-button"
         data-lang-cs="Objednat servis"
         data-lang-en="Order service"
         data-lang-it="Ordina assistenza">Objednat servis</a>
    </div>

    <!-- Background image je nyní v CSS (.hero background-image) -->
  </section>
</main>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-container">
    <div class="footer-grid">

      <!-- FIRMA -->
      <div class="footer-column">
        <h2 class="footer-title">White Glove Service</h2>
        <p class="footer-text"
           data-lang-cs="Specializovaný servis Natuzzi."
           data-lang-en="Natuzzi specialized service."
           data-lang-it="Servizio specializzato Natuzzi.">
          Specializovaný servis Natuzzi.
        </p>
      </div>

      <!-- KONTAKT -->
      <div class="footer-column">
        <h2 class="footer-title"
            data-lang-cs="Kontakt"
            data-lang-en="Contact"
            data-lang-it="Contatto">Kontakt</h2>
        <p class="footer-text">
          <strong data-lang-cs="Tel:" data-lang-en="Phone:" data-lang-it="Telefono:">Tel:</strong> <a href="tel:+420725965826" class="footer-link">+420 725 965 826</a><br>
          <strong>Email:</strong> <a href="mailto:reklamace@wgs-service.cz" class="footer-link">reklamace@wgs-service.cz</a>
        </p>
      </div>

      <!-- ADRESA -->
      <div class="footer-column">
        <h2 class="footer-title"
            data-lang-cs="Adresa"
            data-lang-en="Address"
            data-lang-it="Indirizzo">Adresa</h2>
        <p class="footer-text"
           data-lang-cs="Do Dubče 364, Běchovice 190 11 CZ"
           data-lang-en="Do Dubče 364, Běchovice 190 11 CZ"
           data-lang-it="Do Dubče 364, Běchovice 190 11 CZ">
          Do Dubče 364, Běchovice 190 11 CZ
        </p>
      </div>

    </div>

    <div class="footer-bottom">
      <p data-lang-cs="© 2025 White Glove Service. Všechna práva vyhrazena."
         data-lang-en="© 2025 White Glove Service. All rights reserved."
         data-lang-it="© 2025 White Glove Service. Tutti i diritti riservati.">
        &copy; 2025 White Glove Service. Všechna práva vyhrazena.
        <span aria-hidden="true"> • </span>
        <a href="gdpr.php" class="footer-link"
           data-lang-cs="Zpracování osobních údajů (GDPR)"
           data-lang-en="Personal data processing (GDPR)"
           data-lang-it="Trattamento dei dati personali (GDPR)">Zpracování osobních údajů (GDPR)</a>
      </p>
    </div>
  </div>
</footer>

<!-- External JavaScript -->
<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.min.js" defer></script>

<!-- REMOVED: index.js - veškerá funkcionalita přesunuta do hamburger-menu.php a language-switcher.js -->

<!-- PWA Service Worker Registration -->
<script src="assets/js/sw-register.min.js"></script>

<?php renderHeatmapTracker(); ?>
</body>
</html>
