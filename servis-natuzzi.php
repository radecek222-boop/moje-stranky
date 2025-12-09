<?php
/**
 * SEO Landing Page: Servis Natuzzi
 * Cilova klicova slova: servis Natuzzi, Natuzzi oprava, Natuzzi reklamace, autorizovany servis Natuzzi
 */
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

  <!-- SEO Meta Tags -->
  <meta name="description" content="<?php echo getSeoDescription('servis-natuzzi'); ?>">
  <?php renderSeoMeta('servis-natuzzi'); ?>
  <?php renderSchemaOrg('servis-natuzzi'); ?>
  <?php renderFaqSchema('servis-natuzzi'); ?>

  <!-- PWA -->
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">

  <title><?php echo getSeoTitle('servis-natuzzi'); ?></title>

  <!-- Preload critical resources -->
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/nasesluzby.min.css" as="style">

  <!-- Google Fonts -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet"></noscript>

  <!-- External CSS -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/nasesluzby.min.css">
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
    <h1 class="hero-title"
        data-lang-cs="Servis Natuzzi"
        data-lang-en="Natuzzi Service"
        data-lang-it="Servizio Natuzzi">Servis Natuzzi</h1>
    <div class="hero-subtitle"
         data-lang-cs="Autorizovany servisni partner pro Ceskou republiku a Slovensko"
         data-lang-en="Authorized service partner for Czech Republic and Slovakia"
         data-lang-it="Partner di servizio autorizzato per Repubblica Ceca e Slovacchia">Autorizovany servisni partner pro Ceskou republiku a Slovensko</div>
  </div>
</section>

<!-- CONTENT SEKCE -->
<section class="content-section">
  <div class="container">

    <div class="section-intro">
      <h2 class="section-title"
          data-lang-cs="Autorizovany servis nabytku Natuzzi"
          data-lang-en="Authorized Natuzzi Furniture Service"
          data-lang-it="Servizio Mobili Natuzzi Autorizzato">Autorizovany servis nabytku Natuzzi</h2>

      <p class="section-text"
         data-lang-cs="White Glove Service je autorizovany servisni partner znacky Natuzzi pro Ceskou republiku a Slovensko. Poskytujeme kompletni servis nabytku Natuzzi Italia, Natuzzi Editions a Natuzzi Softaly - od reklamaci pres opravy az po pravidelnou udrzbu."
         data-lang-en="White Glove Service is an authorized Natuzzi service partner for the Czech Republic and Slovakia. We provide complete service for Natuzzi Italia, Natuzzi Editions and Natuzzi Softaly furniture - from warranty claims through repairs to regular maintenance."
         data-lang-it="White Glove Service è un partner di servizio autorizzato Natuzzi per la Repubblica Ceca e la Slovacchia. Forniamo un servizio completo per i mobili Natuzzi Italia, Natuzzi Editions e Natuzzi Softaly - dai reclami in garanzia alle riparazioni alla manutenzione regolare.">
        White Glove Service je autorizovany servisni partner znacky Natuzzi pro Ceskou republiku a Slovensko. Poskytujeme kompletni servis nabytku Natuzzi Italia, Natuzzi Editions a Natuzzi Softaly - od reklamaci pres opravy az po pravidelnou udrzbu.
      </p>

      <p class="section-text"
         data-lang-cs="Jako autorizovany servis máme přímý pristup k originalnim nahradnim dílům od vyrobce z Italie. Nasi technici jsou skoleni na vsechny typy sedacek a kresel Natuzzi vcetne relaxacnich modelu s elektrickym pohonem. Garantujeme profesionalni provedeni podle standardu vyrobce."
         data-lang-en="As an authorized service, we have direct access to original spare parts from the manufacturer in Italy. Our technicians are trained on all types of Natuzzi sofas and armchairs including recliner models with electric motors. We guarantee professional execution according to manufacturer standards."
         data-lang-it="Come servizio autorizzato, abbiamo accesso diretto ai ricambi originali del produttore in Italia. I nostri tecnici sono formati su tutti i tipi di divani e poltrone Natuzzi, inclusi i modelli relax con motore elettrico. Garantiamo un'esecuzione professionale secondo gli standard del produttore.">
        Jako autorizovany servis máme přímý pristup k originalnim nahradnim dílům od vyrobce z Italie. Nasi technici jsou skoleni na vsechny typy sedacek a kresel Natuzzi vcetne relaxacnich modelu s elektrickym pohonem. Garantujeme profesionalni provedeni podle standardu vyrobce.
      </p>
    </div>

    <!-- SLUZBY NATUZZI -->
    <div class="services-grid">

      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Sluzby pro Natuzzi"
            data-lang-en="Services for Natuzzi"
            data-lang-it="Servizi per Natuzzi">Sluzby pro Natuzzi</h3>
        <ul class="service-list">
          <li data-lang-cs="Reklamace v zarucni dobe" data-lang-en="Warranty claims" data-lang-it="Reclami in garanzia">Reklamace v zarucni dobe</li>
          <li data-lang-cs="Pozarucni opravy a servis" data-lang-en="Post-warranty repairs and service" data-lang-it="Riparazioni e servizio post-garanzia">Pozarucni opravy a servis</li>
          <li data-lang-cs="Montaz a instalace nabytku" data-lang-en="Furniture assembly and installation" data-lang-it="Montaggio e installazione mobili">Montaz a instalace nabytku</li>
          <li data-lang-cs="Oprava kozenych a latkovych potahu" data-lang-en="Leather and fabric cover repair" data-lang-it="Riparazione rivestimenti in pelle e tessuto">Oprava kozenych a latkovych potahu</li>
          <li data-lang-cs="Oprava mechanismu (relax, elektrické)" data-lang-en="Mechanism repair (relax, electric)" data-lang-it="Riparazione meccanismo (relax, elettrico)">Oprava mechanismu (relax, elektrické)</li>
          <li data-lang-cs="Dodani originalnich nahradnich dilu" data-lang-en="Supply of original spare parts" data-lang-it="Fornitura ricambi originali">Dodani originalnich nahradnich dilu</li>
        </ul>
      </div>

      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Proc zvolit autorizovany servis"
            data-lang-en="Why Choose Authorized Service"
            data-lang-it="Perché Scegliere il Servizio Autorizzato">Proc zvolit autorizovany servis</h3>
        <ul class="service-list">
          <li data-lang-cs="Originalni nahradni dily z Italie" data-lang-en="Original spare parts from Italy" data-lang-it="Ricambi originali dall'Italia">Originalni nahradni dily z Italie</li>
          <li data-lang-cs="Skoleni technici na produkty Natuzzi" data-lang-en="Technicians trained on Natuzzi products" data-lang-it="Tecnici formati sui prodotti Natuzzi">Skoleni technici na produkty Natuzzi</li>
          <li data-lang-cs="Postupy schvalene vyrobcem" data-lang-en="Manufacturer-approved procedures" data-lang-it="Procedure approvate dal produttore">Postupy schvalene vyrobcem</li>
          <li data-lang-cs="Zachovani zaruky na nabytek" data-lang-en="Preservation of furniture warranty" data-lang-it="Conservazione della garanzia sui mobili">Zachovani zaruky na nabytek</li>
          <li data-lang-cs="Prima komunikace s tovarnou Natuzzi" data-lang-en="Direct communication with Natuzzi factory" data-lang-it="Comunicazione diretta con la fabbrica Natuzzi">Prima komunikace s tovarnou Natuzzi</li>
          <li data-lang-cs="12mesicni zaruka na opravy" data-lang-en="12-month warranty on repairs" data-lang-it="Garanzia di 12 mesi sulle riparazioni">12mesicni zaruka na opravy</li>
        </ul>
      </div>

    </div>

    <!-- PRODUKTOVE RADY -->
    <div class="product-lines">
      <h2 class="section-title"
          data-lang-cs="Servisujeme vsechny rady Natuzzi"
          data-lang-en="We Service All Natuzzi Lines"
          data-lang-it="Effettuiamo la Manutenzione di Tutte le Linee Natuzzi">Servisujeme vsechny rady Natuzzi</h2>

      <div class="services-grid">
        <div class="service-card">
          <h3 class="service-title">Natuzzi Italia</h3>
          <p data-lang-cs="Prémiovÿ italský design, rucni zpracovani, luxusni materialy. Kompletni servis od reklamaci po renovace." data-lang-en="Premium Italian design, handcrafted, luxury materials. Complete service from warranty claims to renovations." data-lang-it="Design italiano premium, lavorazione artigianale, materiali di lusso. Servizio completo dai reclami in garanzia alle ristrutturazioni.">
            Prémiovÿ italský design, rucni zpracovani, luxusni materialy. Kompletni servis od reklamaci po renovace.
          </p>
        </div>

        <div class="service-card">
          <h3 class="service-title">Natuzzi Editions</h3>
          <p data-lang-cs="Moderni design v dostupnejsi cenove hladine. Opravy kozeneho i latkoveho calouneni, mechanismu." data-lang-en="Modern design at a more accessible price point. Leather and fabric upholstery repairs, mechanism repairs." data-lang-it="Design moderno a un prezzo più accessibile. Riparazioni rivestimenti in pelle e tessuto, riparazioni meccanismo.">
            Moderni design v dostupnejsi cenove hladine. Opravy kozeneho i latkoveho calouneni, mechanismu.
          </p>
        </div>

        <div class="service-card">
          <h3 class="service-title">Natuzzi Softaly</h3>
          <p data-lang-cs="Pohodlne sedacky a kresla s durazem na komfort. Servis relaxacnich modelu, elektrickych pohonu." data-lang-en="Comfortable sofas and armchairs with emphasis on comfort. Recliner model service, electric motor repairs." data-lang-it="Divani e poltrone confortevoli con enfasi sul comfort. Servizio modelli relax, riparazioni motori elettrici.">
            Pohodlne sedacky a kresla s durazem na komfort. Servis relaxacnich modelu, elektrickych pohonu.
          </p>
        </div>
      </div>
    </div>

    <!-- FAQ SEKCE -->
    <div class="faq-section">
      <h2 class="section-title"
          data-lang-cs="Caste dotazy k servisu Natuzzi"
          data-lang-en="Frequently Asked Questions About Natuzzi Service"
          data-lang-it="Domande Frequenti sul Servizio Natuzzi">Caste dotazy k servisu Natuzzi</h2>

      <div class="faq-list">
        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Jste autorizovany servis Natuzzi?"
              data-lang-en="Are you an authorized Natuzzi service?"
              data-lang-it="Siete un servizio Natuzzi autorizzato?">Jste autorizovany servis Natuzzi?</h3>
          <p class="faq-answer"
             data-lang-cs="Ano, jsme autorizovany servisni partner znacky Natuzzi pro Ceskou republiku a Slovensko. Pouzivame originalni dily a postupy schvalene vyrobcem."
             data-lang-en="Yes, we are an authorized Natuzzi service partner for the Czech Republic and Slovakia. We use original parts and manufacturer-approved procedures."
             data-lang-it="Sì, siamo un partner di servizio autorizzato Natuzzi per la Repubblica Ceca e la Slovacchia. Utilizziamo parti originali e procedure approvate dal produttore.">
            Ano, jsme autorizovany servisni partner znacky Natuzzi pro Ceskou republiku a Slovensko. Pouzivame originalni dily a postupy schvalene vyrobcem.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Jak vyridit reklamaci Natuzzi?"
              data-lang-en="How to process a Natuzzi warranty claim?"
              data-lang-it="Come elaborare un reclamo in garanzia Natuzzi?">Jak vyridit reklamaci Natuzzi?</h3>
          <p class="faq-answer"
             data-lang-cs="Reklamaci Natuzzi podejte pres nas online formular. Jako autorizovany servis vyrizujeme reklamace primo s vyrobcem. Potrebujete doklad o koupi a fotografie problemu."
             data-lang-en="Submit a Natuzzi warranty claim through our online form. As an authorized service, we process claims directly with the manufacturer. You need proof of purchase and photos of the problem."
             data-lang-it="Invia un reclamo in garanzia Natuzzi tramite il nostro modulo online. Come servizio autorizzato, elaboriamo i reclami direttamente con il produttore. Hai bisogno della prova d'acquisto e delle foto del problema.">
            Reklamaci Natuzzi podejte pres nas online formular. Jako autorizovany servis vyrizujeme reklamace primo s vyrobcem. Potrebujete doklad o koupi a fotografie problemu.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Kde sehnat nahradni dily Natuzzi?"
              data-lang-en="Where to get Natuzzi spare parts?"
              data-lang-it="Dove trovare ricambi Natuzzi?">Kde sehnat nahradni dily Natuzzi?</h3>
          <p class="faq-answer"
             data-lang-cs="Originalni nahradni dily Natuzzi objednavame primo od vyrobce z Italie. Dodaci lhuta je obvykle 2-4 tydny. Jako autorizovany servis mame pristup ke kompletnimu sortimentu dilu."
             data-lang-en="We order original Natuzzi spare parts directly from the manufacturer in Italy. Delivery time is usually 2-4 weeks. As an authorized service, we have access to the complete range of parts."
             data-lang-it="Ordiniamo ricambi originali Natuzzi direttamente dal produttore in Italia. I tempi di consegna sono solitamente 2-4 settimane. Come servizio autorizzato, abbiamo accesso alla gamma completa di ricambi.">
            Originalni nahradni dily Natuzzi objednavame primo od vyrobce z Italie. Dodaci lhuta je obvykle 2-4 tydny. Jako autorizovany servis mame pristup ke kompletnimu sortimentu dilu.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Opravujete vsechny rady Natuzzi?"
              data-lang-en="Do you repair all Natuzzi lines?"
              data-lang-it="Riparate tutte le linee Natuzzi?">Opravujete vsechny rady Natuzzi?</h3>
          <p class="faq-answer"
             data-lang-cs="Ano, opravujeme nabytek vsech rad - Natuzzi Italia, Natuzzi Editions i Natuzzi Softaly. Mame zkusenosti se vsemi typy sedacek, kresel a doplnku."
             data-lang-en="Yes, we repair furniture of all lines - Natuzzi Italia, Natuzzi Editions and Natuzzi Softaly. We have experience with all types of sofas, armchairs and accessories."
             data-lang-it="Sì, ripariamo mobili di tutte le linee - Natuzzi Italia, Natuzzi Editions e Natuzzi Softaly. Abbiamo esperienza con tutti i tipi di divani, poltrone e accessori.">
            Ano, opravujeme nabytek vsech rad - Natuzzi Italia, Natuzzi Editions i Natuzzi Softaly. Mame zkusenosti se vsemi typy sedacek, kresel a doplnku.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Poskytujete zaruku na opravy Natuzzi?"
              data-lang-en="Do you provide warranty on Natuzzi repairs?"
              data-lang-it="Fornite garanzia sulle riparazioni Natuzzi?">Poskytujete zaruku na opravy Natuzzi?</h3>
          <p class="faq-answer"
             data-lang-cs="Ano, na vsechny opravy poskytujeme zaruku 12 mesicu. Zaruka se vztahuje na provedenou praci i pouzite originalni dily."
             data-lang-en="Yes, we provide a 12-month warranty on all repairs. The warranty covers the work performed and the original parts used."
             data-lang-it="Sì, forniamo una garanzia di 12 mesi su tutte le riparazioni. La garanzia copre il lavoro eseguito e i ricambi originali utilizzati.">
            Ano, na vsechny opravy poskytujeme zaruku 12 mesicu. Zaruka se vztahuje na provedenou praci i pouzite originalni dily.
          </p>
        </div>
      </div>
    </div>

  </div>
</section>

<!-- CTA SEKCE -->
<section class="cta-section">
  <div class="container">
    <h2 class="cta-title"
        data-lang-cs="Potrebujete servis Natuzzi?"
        data-lang-en="Need Natuzzi Service?"
        data-lang-it="Hai Bisogno del Servizio Natuzzi?">Potrebujete servis Natuzzi?</h2>
    <p class="cta-text"
       data-lang-cs="Kontaktujte autorizovany servis. Vyridime reklamaci, provedeme opravu nebo zajistime nahradni dily. Profesionalni pece o vas nabytek Natuzzi."
       data-lang-en="Contact authorized service. We will handle warranty claims, perform repairs or supply spare parts. Professional care for your Natuzzi furniture."
       data-lang-it="Contatta il servizio autorizzato. Gestiremo i reclami in garanzia, eseguiremo riparazioni o forniremo ricambi. Cura professionale per i vostri mobili Natuzzi.">
      Kontaktujte autorizovany servis. Vyridime reklamaci, provedeme opravu nebo zajistime nahradni dily. Profesionalni pece o vas nabytek Natuzzi.
    </p>
    <a href="novareklamace.php" class="cta-button"
       data-lang-cs="Kontaktovat servis Natuzzi"
       data-lang-en="Contact Natuzzi Service"
       data-lang-it="Contatta Servizio Natuzzi">Kontaktovat servis Natuzzi</a>
  </div>
</section>

</main>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-container">
    <div class="footer-grid">

      <div class="footer-column">
        <h2 class="footer-title">White Glove Service</h2>
        <p class="footer-text"
           data-lang-cs="Specializovany servis Natuzzi."
           data-lang-en="Natuzzi specialized service."
           data-lang-it="Servizio specializzato Natuzzi.">
          Specializovany servis Natuzzi.
        </p>
      </div>

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

      <div class="footer-column">
        <h2 class="footer-title"
            data-lang-cs="Adresa"
            data-lang-en="Address"
            data-lang-it="Indirizzo">Adresa</h2>
        <p class="footer-text">
          Do Dubce 364, Bechovice 190 11 CZ
        </p>
      </div>

    </div>

    <div class="footer-bottom">
      <p>
        &copy; 2025 White Glove Service. Všechna práva vyhrazena.
        <span aria-hidden="true"> • </span>
        <a href="gdpr.php" class="footer-link">GDPR</a>
        <span aria-hidden="true"> • </span>
        <a href="cookies.php" class="footer-link">Cookies</a>
        <span aria-hidden="true"> • </span>
        <a href="podminky.php" class="footer-link">Obchodní podmínky</a>
      </p>
    </div>
  </div>
</footer>

<script src="assets/js/logger.min.js" defer></script>
<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
<?php require_once __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
