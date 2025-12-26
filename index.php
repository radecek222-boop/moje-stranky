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

  <!-- SEKCE SLUZEB - SEO interní prolinkování -->
  <section class="services-links-section">
    <div class="services-container">
      <h2 class="services-title"
          data-lang-cs="Nase sluzby"
          data-lang-en="Our Services"
          data-lang-it="I Nostri Servizi">Nase sluzby</h2>

      <div class="services-grid">

        <a href="oprava-sedacky.php" class="service-link-card">
          <h3 data-lang-cs="Oprava sedacky"
              data-lang-en="Sofa Repair"
              data-lang-it="Riparazione Divano">Oprava sedacky</h3>
          <p data-lang-cs="Profesionalni opravy sedacek, gaucu a pohovek. Kozene i latkove."
             data-lang-en="Professional sofa, couch and settee repairs. Leather and fabric."
             data-lang-it="Riparazioni professionali di divani e sofà. Pelle e tessuto.">Profesionalni opravy sedacek, gaucu a pohovek. Kozene i latkove.</p>
        </a>

        <a href="oprava-kresla.php" class="service-link-card">
          <h3 data-lang-cs="Oprava kresla"
              data-lang-en="Armchair Repair"
              data-lang-it="Riparazione Poltrona">Oprava kresla</h3>
          <p data-lang-cs="Servis relaxacnich a klasickych kresel. Oprava mechanismu."
             data-lang-en="Service for recliners and classic armchairs. Mechanism repair."
             data-lang-it="Servizio per poltrone relax e classiche. Riparazione meccanismo.">Servis relaxacnich a klasickych kresel. Oprava mechanismu.</p>
        </a>

        <a href="servis-natuzzi.php" class="service-link-card">
          <h3 data-lang-cs="Servis Natuzzi"
              data-lang-en="Natuzzi Service"
              data-lang-it="Servizio Natuzzi">Servis Natuzzi</h3>
          <p data-lang-cs="Autorizovany servis Natuzzi. Reklamace, opravy, originalni dily."
             data-lang-en="Authorized Natuzzi service. Warranty claims, repairs, original parts."
             data-lang-it="Servizio Natuzzi autorizzato. Reclami, riparazioni, ricambi originali.">Autorizovany servis Natuzzi. Reklamace, opravy, originalni dily.</p>
        </a>

        <a href="pozarucni-servis.php" class="service-link-card">
          <h3 data-lang-cs="Pozarucni servis"
              data-lang-en="Out-of-Warranty Service"
              data-lang-it="Servizio Fuori Garanzia">Pozarucni servis</h3>
          <p data-lang-cs="Opravime vas nabytek i po skonceni zaruky. Fer ceny."
             data-lang-en="We repair your furniture even after the warranty expires. Fair prices."
             data-lang-it="Ripariamo i vostri mobili anche dopo la scadenza della garanzia. Prezzi equi.">Opravime vas nabytek i po skonceni zaruky. Fer ceny.</p>
        </a>

        <a href="neuznana-reklamace.php" class="service-link-card">
          <h3 data-lang-cs="Zamítnuta reklamace?"
              data-lang-en="Claim Not Covered?"
              data-lang-it="Reclamo Non Coperto?">Zamítnuta reklamace?</h3>
          <p data-lang-cs="Nabízíme cenově výhodnou opravu jako alternativu. Pomůžeme vám."
             data-lang-en="We offer affordable repair as an alternative. We can help."
             data-lang-it="Offriamo riparazione conveniente come alternativa. Possiamo aiutarti.">Nabízíme cenově výhodnou opravu jako alternativu. Pomůžeme vám.</p>
        </a>

        <a href="cenik.php" class="service-link-card">
          <h3 data-lang-cs="Cenik sluzeb"
              data-lang-en="Price List"
              data-lang-it="Listino Prezzi">Cenik sluzeb</h3>
          <p data-lang-cs="Prehled cen a online kalkulacka ceny opravy."
             data-lang-en="Price overview and online repair cost calculator."
             data-lang-it="Panoramica prezzi e calcolatore online del costo di riparazione.">Prehled cen a online kalkulacka ceny opravy.</p>
        </a>

      </div>
    </div>
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
          <?php echo wgsFooterKontakt('info'); ?>
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
      <p>
        &copy; 2025 White Glove Service.
        <span data-lang-cs="Všechna práva vyhrazena."
              data-lang-en="All rights reserved."
              data-lang-it="Tutti i diritti riservati.">Všechna práva vyhrazena.</span>
        <span aria-hidden="true"> • </span>
        <a href="gdpr.php" class="footer-link"
           data-lang-cs="GDPR"
           data-lang-en="GDPR"
           data-lang-it="GDPR">GDPR</a>
        <span aria-hidden="true"> • </span>
        <a href="cookies.php" class="footer-link"
           data-lang-cs="Cookies"
           data-lang-en="Cookies"
           data-lang-it="Cookie">Cookies</a>
        <span aria-hidden="true"> • </span>
        <a href="podminky.php" class="footer-link"
           data-lang-cs="Obchodní podmínky"
           data-lang-en="Terms of Service"
           data-lang-it="Termini di servizio">Obchodní podmínky</a>
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

<?php require_once __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
