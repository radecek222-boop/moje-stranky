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

  <!-- SEO Meta Tags -->
  <meta name="description" content="<?php echo getSeoDescription('nasesluzby'); ?>">
  <?php renderSeoMeta('nasesluzby'); ?>
  <?php renderSchemaOrg('nasesluzby'); ?>

  <!-- PWA -->
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">

  <title><?php echo getSeoTitle('nasesluzby'); ?></title>

  <!-- Preload critical resources -->
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/nasesluzby.min.css" as="style">
  <link rel="preload" href="assets/img/herman-image01.webp" as="image" type="image/webp" fetchpriority="high">

  <!-- Preconnect pro rychlejší načítání fontů -->
  
  <!-- Google Fonts - Natuzzi style - optimalizované načítání -->
  <noscript>
  </noscript>
  
  <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="stylesheet" href="assets/css/page-transitions.min.css">
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/nasesluzby.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.min.css">

  <!-- Analytics Tracker -->
  <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
  <link rel="stylesheet" href="assets/css/poppins-font.css">
</head>

<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<!-- ČERNÝ HORNÍ PANEL -->

<!-- Hlavní obsah -->
<main id="main-content">

<!-- HERO SEKCE -->
<section class="hero">
  <div class="hero-content">
    <h1 class="hero-title"
        data-lang-cs="WHITE GLOVE SERVIS"
        data-lang-en="WHITE GLOVE SERVICE"
        data-lang-it="WHITE GLOVE SERVICE">WHITE GLOVE SERVIS</h1>
  </div>
</section>

<!-- SEKCE SLUŽEB -->
<section class="services-section">
  <div class="container">
    
    <div class="section-intro">
      <h2 class="section-title"
          data-lang-cs="Servis a opravy luxusního nábytku"
          data-lang-en="Luxury Furniture Service and Repairs"
          data-lang-it="Assistenza e Riparazioni di Mobili di Lusso">Servis a opravy luxusního nábytku</h2>
      <p class="section-description"
         data-lang-cs="Jsme autorizovaný servisní partner značek Natuzzi Italia, Natuzzi Editions, Natuzzi Softaly a Phase. Provádíme opravy, reklamace a montáž luxusního nábytku v Praze, Brně, Bratislavě a po celé ČR a SR."
         data-lang-en="We are an authorized service partner for Natuzzi Italia, Natuzzi Editions, Natuzzi Softaly and Phase. We carry out repairs, complaints and installation of luxury furniture in Prague, Brno, Bratislava and throughout the Czech Republic and Slovakia."
         data-lang-it="Siamo un partner autorizzato per Natuzzi Italia, Natuzzi Editions, Natuzzi Softaly e Phase. Eseguiamo riparazioni, reclami e installazioni di mobili di lusso a Praga, Brno, Bratislava e in tutta la Repubblica Ceca e Slovacchia.">
        Jsme autorizovaný servisní partner značek Natuzzi Italia, Natuzzi Editions, Natuzzi Softaly a Phase. Provádíme opravy, reklamace a montáž luxusního nábytku v Praze, Brně, Bratislavě a po celé ČR a SR.
      </p>
    </div>

    <div class="services-grid">

      <!-- OPRAVY A REKLAMACE -->
      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Opravy a reklamace"
            data-lang-en="Repairs and Complaints"
            data-lang-it="Riparazioni e Reclami">Opravy a reklamace</h3>
        <ul class="service-list">
          <li data-lang-cs="Oprava kožených a látkových sedaček"
              data-lang-en="Repair of leather and fabric sofas"
              data-lang-it="Riparazione divani in pelle e tessuto">Oprava kožených a látkových sedaček</li>
          <li data-lang-cs="Výměna mechanismů a nosných konstrukcí"
              data-lang-en="Replacement of mechanisms and structures"
              data-lang-it="Sostituzione meccanismi e strutture">Výměna mechanismů a nosných konstrukcí</li>
          <li data-lang-cs="Reklamační řízení Natuzzi a dalších značek"
              data-lang-en="Complaints procedure for Natuzzi and other brands"
              data-lang-it="Procedura reclami Natuzzi e altri marchi">Reklamační řízení Natuzzi a dalších značek</li>
          <li data-lang-cs="Posouzení škod pro pojišťovny"
              data-lang-en="Damage assessment for insurance companies"
              data-lang-it="Valutazione danni per assicurazioni">Posouzení škod pro pojišťovny</li>
        </ul>
      </div>

      <!-- MONTÁŽ A INSTALACE -->
      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Montáž a instalace"
            data-lang-en="Assembly and Installation"
            data-lang-it="Montaggio e Installazione">Montáž a instalace</h3>
        <ul class="service-list">
          <li data-lang-cs="Montáž sedacích souprav a rohových sedaček"
              data-lang-en="Assembly of sofas and corner sofas"
              data-lang-it="Montaggio divani e divani angolari">Montáž sedacích souprav a rohových sedaček</li>
          <li data-lang-cs="Instalace elektrických a manuálních mechanismů"
              data-lang-en="Installation of electrical and manual mechanisms"
              data-lang-it="Installazione meccanismi elettrici e manuali">Instalace elektrických a manuálních mechanismů</li>
          <li data-lang-cs="Seřízení relaxačních funkcí křesel"
              data-lang-en="Adjusting relaxation functions of chairs"
              data-lang-it="Regolazione funzioni relax delle poltrone">Seřízení relaxačních funkcí křesel</li>
          <li data-lang-cs="Dovoz a odborná přeprava nábytku"
              data-lang-en="Delivery and professional transportation"
              data-lang-it="Consegna e trasporto professionale">Dovoz a odborná přeprava nábytku</li>
        </ul>
      </div>

      <!-- PORADENSTVÍ -->
      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Poradenství a posudky"
            data-lang-en="Consulting and Expert Opinions"
            data-lang-it="Consulenza e Perizie">Poradenství a posudky</h3>
        <ul class="service-list">
          <li data-lang-cs="Znalecké posudky pro pojišťovny a soudy"
              data-lang-en="Expert opinions for insurance companies and courts"
              data-lang-it="Perizie per assicurazioni e tribunali">Znalecké posudky pro pojišťovny a soudy</li>
          <li data-lang-cs="Odhad rozsahu škod a nákladů na opravu"
              data-lang-en="Estimate of damage and repair costs"
              data-lang-it="Stima danni e costi di riparazione">Odhad rozsahu škod a nákladů na opravu</li>
          <li data-lang-cs="Konzultace při výběru sedací soupravy"
              data-lang-en="Consultation when choosing a sofa"
              data-lang-it="Consulenza nella scelta del divano">Konzultace při výběru sedací soupravy</li>
          <li data-lang-cs="Doporučení péče o kožený nábytek"
              data-lang-en="Leather furniture care recommendations"
              data-lang-it="Consigli per la cura dei mobili in pelle">Doporučení péče o kožený nábytek</li>
        </ul>
      </div>

    </div>
  </div>
</section>

<!-- SEKCE ODKAZŮ NA KONKRÉTNÍ SLUŽBY -->
<section class="sluzby-odkazy-sekce">
  <div class="sluzby-odkazy-kontejner">

    <h2 class="sluzby-odkazy-titulek"
        data-lang-cs="Konkrétní služby"
        data-lang-en="Specific Services"
        data-lang-it="Servizi Specifici">Konkrétní služby</h2>

    <div class="sluzby-odkazy-mrizka">

      <a href="oprava-sedacky.php" class="sluzba-odkaz-karta">
        <h3 data-lang-cs="Oprava sedačky"
            data-lang-en="Sofa Repair"
            data-lang-it="Riparazione Divano">Oprava sedačky</h3>
        <p data-lang-cs="Profesionální opravy sedaček, gaučů a pohovek. Kožené i látkové."
           data-lang-en="Professional sofa, couch and settee repairs. Leather and fabric."
           data-lang-it="Riparazioni professionali di divani e sofà. Pelle e tessuto.">Profesionální opravy sedaček, gaučů a pohovek. Kožené i látkové.</p>
      </a>

      <a href="oprava-kresla.php" class="sluzba-odkaz-karta">
        <h3 data-lang-cs="Oprava křesla"
            data-lang-en="Armchair Repair"
            data-lang-it="Riparazione Poltrona">Oprava křesla</h3>
        <p data-lang-cs="Servis relaxačních a klasických křesel. Oprava mechanismu."
           data-lang-en="Service for recliners and classic armchairs. Mechanism repair."
           data-lang-it="Servizio per poltrone relax e classiche. Riparazione meccanismo.">Servis relaxačních a klasických křesel. Oprava mechanismu.</p>
      </a>

      <a href="servis-natuzzi.php" class="sluzba-odkaz-karta">
        <h3 data-lang-cs="Servis Natuzzi"
            data-lang-en="Natuzzi Service"
            data-lang-it="Servizio Natuzzi">Servis Natuzzi</h3>
        <p data-lang-cs="Autorizovaný servis Natuzzi. Reklamace, opravy, originální díly."
           data-lang-en="Authorized Natuzzi service. Warranty claims, repairs, original parts."
           data-lang-it="Servizio Natuzzi autorizzato. Reclami, riparazioni, ricambi originali.">Autorizovaný servis Natuzzi. Reklamace, opravy, originální díly.</p>
      </a>

      <a href="pozarucni-servis.php" class="sluzba-odkaz-karta">
        <h3 data-lang-cs="Pozáruční servis"
            data-lang-en="Out-of-Warranty Service"
            data-lang-it="Servizio Fuori Garanzia">Pozáruční servis</h3>
        <p data-lang-cs="Opravíme váš nábytek i po skončení záruky. Fer ceny."
           data-lang-en="We repair your furniture even after the warranty expires. Fair prices."
           data-lang-it="Ripariamo i vostri mobili anche dopo la scadenza della garanzia. Prezzi equi.">Opravíme váš nábytek i po skončení záruky. Fer ceny.</p>
      </a>

      <a href="neuznana-reklamace.php" class="sluzba-odkaz-karta">
        <h3 data-lang-cs="Zamítnutá reklamace?"
            data-lang-en="Claim Not Covered?"
            data-lang-it="Reclamo Non Coperto?">Zamítnutá reklamace?</h3>
        <p data-lang-cs="Nabízíme cenově výhodnou opravu jako alternativu. Pomůžeme vám."
           data-lang-en="We offer affordable repair as an alternative. We can help."
           data-lang-it="Offriamo riparazione conveniente come alternativa. Possiamo aiutarti.">Nabízíme cenově výhodnou opravu jako alternativu. Pomůžeme vám.</p>
      </a>

      <a href="cenik.php" class="sluzba-odkaz-karta">
        <h3 data-lang-cs="Ceník služeb"
            data-lang-en="Price List"
            data-lang-it="Listino Prezzi">Ceník služeb</h3>
        <p data-lang-cs="Přehled cen a online kalkulačka ceny opravy."
           data-lang-en="Price overview and online repair cost calculator."
           data-lang-it="Panoramica prezzi e calcolatore online del costo di riparazione.">Přehled cen a online kalkulačka ceny opravy.</p>
      </a>

    </div>
  </div>
</section>

<!-- CTA SEKCE -->
<section class="cta-section">
  <div class="container">
    <h2 class="cta-title"
        data-lang-cs="Hledáte servis nebo opravu?"
        data-lang-en="Are you looking for service or repair?"
        data-lang-it="Cerchi assistenza o riparazioni?">Hledáte servis nebo opravu?</h2>
    <p class="cta-text"
       data-lang-cs="Kontaktujte nás pro nezávaznou konzultaci nebo objednejte servisní zásah. Opravíme vaši sedačku rychle a profesionálně. Servis v České republice i na Slovensku."
       data-lang-en="Contact us for a non-binding consultation or order a service intervention. We will repair your seat quickly and professionally. Service in the Czech Republic and Slovakia."
       data-lang-it="Contattateci per una consulenza senza impegno o prenotate un intervento di assistenza. Ripareremo il vostro sedile in modo rapido e professionale. Assistenza in Repubblica Ceca e Slovacchia.">
      Kontaktujte nás pro nezávaznou konzultaci nebo objednejte servisní zásah. Opravíme vaši sedačku rychle a profesionálně. Servis v České republice i na Slovensku.
    </p>
    <a href="novareklamace.php" class="cta-button"
       data-lang-cs="Objednat servis sedačky"
       data-lang-en="Order seat service"
       data-lang-it="Ordina il servizio di posti a sedere">Objednat servis sedačky</a>
  </div>
</section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="assets/js/logger.min.js" defer></script>
<script src="assets/js/page-transitions.min.js" defer></script>
<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
<?php require_once __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
