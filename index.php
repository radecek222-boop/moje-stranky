<?php
require_once "init.php";
require_once __DIR__ . '/includes/seo_meta.php';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
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

  <!-- Google Fonts - Natuzzi style - OPTIMIZED with font-display: optional -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>

  <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/index.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.min.css">

  <!-- Analytics Tracker -->
  <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
</head>

<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>


<!-- HERO SEKCE -->
<main id="main-content">
  <section class="hero">
    <div class="hero-content">
      <h1 class="hero-title">WGS</h1>
      
      <div class="hero-subtitle">White Glove Service</div>
      
      <p class="hero-description"
         data-lang-cs="Prémiový servis pro luxusní nábytek.<br>Profesionální montáž, údržba a opravy s maximální péčí o každý detail."
         data-lang-en="Premium service for luxury furniture.<br>Professional assembly, maintenance, and repairs with maximum attention to every detail."
         data-lang-it="Servizio premium per mobili di lusso.<br>Montaggio, manutenzione e riparazioni professionali con la massima cura per ogni dettaglio.">
        Prémiový servis pro luxusní nábytek.<br>Profesionální montáž, údržba a opravy s maximální péčí o každý detail.
      </p>
      
      <a href="novareklamace.php" class="cta-button"
         data-lang-cs="Objednat servis"
         data-lang-en="Order service"
         data-lang-it="Ordina assistenza">Objednat servis</a>
    </div>

    <!-- Background image je nyní v CSS (.hero background-image) -->
  </section>


</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- External JavaScript -->
<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.min.js" defer></script>

<!-- REMOVED: index.js - veškerá funkcionalita přesunuta do hamburger-menu.php a language-switcher.js -->

<!-- PWA Service Worker Registration -->
<script src="assets/js/sw-register.min.js"></script>

<?php require_once __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
