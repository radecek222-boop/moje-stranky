<?php require_once "init.php"; ?>
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
  <meta name="description" content="Autorizovaný servis a opravy sedaček Natuzzi v ČR a SR. Reklamace, montáž, čalounění, renovace kožených sedaček. Spolupráce s předními českými prodejci. ☎ +420 725 965 826">
  
  <!-- PWA -->
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">
  
  <title>Servis a opravy Natuzzi | Reklamace, montáž | WGS</title>

  <!-- Preload critical resources -->
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/nasesluzby.min.css" as="style">
  <link rel="preload" href="assets/img/herman-image01.webp" as="image" type="image/webp" fetchpriority="high">

  <!-- Preconnect pro rychlejší načítání fontů -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  
  <!-- Google Fonts - Natuzzi style - optimalizované načítání -->
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional">
  </noscript>
  
  <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/nasesluzby.min.css">
</head>

<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<!-- ČERNÝ HORNÍ PANEL -->

<!-- Hlavní obsah -->
<main>

<!-- HERO SEKCE -->
<section class="hero">
  <div class="hero-content">
    <h1 class="hero-title" 
        data-lang-cs="WHITE GLOVE SERVIS"
        data-lang-en="WHITE GLOVE SERVICE"
        data-lang-it="WHITE GLOVE SERVICE">WHITE GLOVE SERVIS</h1>
    <p class="hero-brands">Natuzzi Italia • Natuzzi Editions • Natuzzi Softaly</p>
    <div class="hero-subtitle"
         data-lang-cs="Opravy • Reklamace • Montáž • Praha, Brno, Bratislava"
         data-lang-en="Repairs • Complaints • Installation • Prague, Brno, Bratislava"
         data-lang-it="Riparazioni • Reclami • Installazione • Praga, Brno, Bratislava">Opravy • Reklamace • Montáž • Praha, Brno, Bratislava</div>
  </div>
</section>

<!-- SEKCE SLUŽEB -->
<section class="services-section">
  <div class="container">
    
    <div class="section-intro">
      <h2 class="section-title"
          data-lang-cs="Servis a opravy Natuzzi"
          data-lang-en="Natuzzi Service and Repairs"
          data-lang-it="Assistenza e Riparazioni Natuzzi">Servis a opravy Natuzzi</h2>
      <p class="section-description"
         data-lang-cs="Jsme autorizovaný servisní partner pro nábytek Natuzzi s dlouholetými zkušenostmi v opravách luxusních sedacích souprav. Specializujeme se na opravy kožených sedaček, reklamace, renovace a profesionální čalounění nábytku. Poskytujeme servis v České republice i na Slovensku včetně Prahy, Brna, Bratislavy, Ostravy a dalších měst."
         data-lang-en="We are an authorized service partner for Natuzzi furniture with many years of experience in repairing luxury sofas. We specialize in leather sofa repairs, complaints, renovations and professional furniture upholstery. We provide service in the Czech Republic and Slovakia, including Prague, Brno, Bratislava, Ostrava and other cities."
         data-lang-it="Siamo un partner autorizzato per l'assistenza di mobili Natuzzi, con molti anni di esperienza nella riparazione di divani di lusso. Siamo specializzati in riparazioni, reclami, ristrutturazioni e rivestimenti professionali di divani in pelle. Offriamo assistenza in Repubblica Ceca e Slovacchia, tra cui Praga, Brno, Bratislava, Ostrava e altre città.">
        Jsme autorizovaný servisní partner pro nábytek Natuzzi s dlouholetými zkušenostmi v opravách luxusních sedacích souprav. Specializujeme se na opravy kožených sedaček, reklamace, renovace a profesionální čalounění nábytku. Poskytujeme servis v České republice i na Slovensku včetně Prahy, Brna, Bratislavy, Ostravy a dalších měst.
      </p>
      <p class="section-description"
         data-lang-cs="Spolupracujeme s předními českými prodejci a výrobci nábytku Natuzzi a dalších značek luxusního designového nábytku. Zajišťujeme rychlé vyřízení reklamací, odborné posouzení škod, výměnu poškozených dílů, renovaci čalounění a kompletní montáž sedacích souprav. Pracujeme s originálními náhradními díly a používáme špičkové materiály pro zajištění nejvyšší kvality oprav."
         data-lang-en="We cooperate with leading Czech furniture retailers and manufacturers of Natuzzi and other luxury designer furniture brands. We provide quick complaint handling, professional damage assessment, replacement of damaged parts, upholstery renovation and complete assembly of sofas. We work with original spare parts and use top-quality materials to ensure the highest quality repairs."
         data-lang-it="Collaboriamo con i principali rivenditori di mobili cechi e produttori di Natuzzi e di altri marchi di mobili di design di lusso. Offriamo una rapida gestione dei reclami, una valutazione professionale dei danni, la sostituzione delle parti danneggiate, il rinnovo dei rivestimenti e il montaggio completo dei divani. Utilizziamo solo ricambi originali e materiali di alta qualità per garantire riparazioni di altissima qualità.">
        Spolupracujeme s předními českými prodejci a výrobci nábytku Natuzzi a dalších značek luxusního designového nábytku. Zajišťujeme rychlé vyřízení reklamací, odborné posouzení škod, výměnu poškozených dílů, renovaci čalounění a kompletní montáž sedacích souprav. Pracujeme s originálními náhradními díly a používáme špičkové materiály pro zajištění nejvyšší kvality oprav.
      </p>
    </div>

    <div class="services-grid">
      
      <!-- OPRAVY A REKLAMACE -->
      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Opravy a reklamace sedaček"
            data-lang-en="Seat Repairs and Complaints"
            data-lang-it="Riparazioni e Reclami sui Sedili">Opravy a reklamace sedaček</h3>
        <p class="service-description"
           data-lang-cs="Specializace na opravy sedacích souprav Natuzzi a dalších značek. Opravujeme kožené i látkové sedačky, vyměňujeme poškozené mechanismy, renovujeme čalounění. Zajišťujeme rychlé vyřízení reklamací s výrobcem a pojišťovnami."
           data-lang-en="Specializing in repairs of Natuzzi sofas and other brands. We repair leather and fabric sofas, replace damaged mechanisms, renovate upholstery. We ensure quick settlement of complaints with the manufacturer and insurance companies."
           data-lang-it="Specializzati nella riparazione di divani Natuzzi e di altre marche. Ripariamo divani in pelle e tessuto, sostituiamo meccanismi danneggiati e rinnoviamo i rivestimenti. Garantiamo una rapida risoluzione dei reclami con il produttore e le compagnie assicurative.">
          Specializace na opravy sedacích souprav Natuzzi a dalších značek. Opravujeme kožené i látkové sedačky, vyměňujeme poškozené mechanismy, renovujeme čalounění. Zajišťujeme rychlé vyřízení reklamací s výrobcem a pojišťovnami.
        </p>
        <ul class="service-list">
          <li data-lang-cs="Oprava kožených a látkových sedaček všech značek"
              data-lang-en="Repair of leather and fabric seats of all brands"
              data-lang-it="Riparazione sedili in pelle e tessuto di tutte le marche">Oprava kožených a látkových sedaček všech značek</li>
          <li data-lang-cs="Výměna mechanismů, pružin a nosných konstrukcí"
              data-lang-en="Replacement of mechanisms, springs and supporting structures"
              data-lang-it="Sostituzione di meccanismi, molle e strutture di supporto">Výměna mechanismů, pružin a nosných konstrukcí</li>
          <li data-lang-cs="Renovace a čalounění sedacích souprav"
              data-lang-en="Renovation and upholstery of sofas"
              data-lang-it="Ristrutturazione e rivestimento divani">Renovace a čalounění sedacích souprav</li>
          <li data-lang-cs="Reklamační řízení Natuzzi a dalších značek"
              data-lang-en="Complaints procedure for Natuzzi and other brands"
              data-lang-it="Procedura di reclamo per Natuzzi e altri marchi">Reklamační řízení Natuzzi a dalších značek</li>
          <li data-lang-cs="Oprava relaxačních a elektrických mechanismů"
              data-lang-en="Repair of relaxation and electrical mechanisms"
              data-lang-it="Riparazione dei meccanismi di rilassamento ed elettrici">Oprava relaxačních a elektrických mechanismů</li>
          <li data-lang-cs="Posouzení a odhad škod pro pojišťovny"
              data-lang-en="Damage assessment and estimation for insurance companies"
              data-lang-it="Valutazione e stima dei danni per le compagnie assicurative">Posouzení a odhad škod pro pojišťovny</li>
        </ul>
      </div>

      <!-- MONTÁŽ A INSTALACE -->
      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Montáž sedacích souprav"
            data-lang-en="Assembly of Sofas"
            data-lang-it="Montaggio di Divani">Montáž sedacích souprav</h3>
        <p class="service-description"
           data-lang-cs="Profesionální montáž luxusního nábytku Natuzzi s garancí kvality. Zajišťujeme bezpečnou instalaci sedacích souprav, relaxačních křesel a rohových sedaček včetně elektrických mechanismů."
           data-lang-en="Professional installation of luxury Natuzzi furniture with a quality guarantee. We ensure safe installation of sofas, relaxation chairs and corner sofas, including electrical mechanisms."
           data-lang-it="Installazione professionale di mobili di lusso Natuzzi con garanzia di qualità. Garantiamo l'installazione sicura di divani, poltrone relax e divani angolari, compresi i meccanismi elettrici.">
          Profesionální montáž luxusního nábytku Natuzzi s garancí kvality. Zajišťujeme bezpečnou instalaci sedacích souprav, relaxačních křesel a rohových sedaček včetně elektrických mechanismů.
        </p>
        <ul class="service-list">
          <li data-lang-cs="Montáž sedacích souprav a rohových sedaček"
              data-lang-en="Assembly of sofas and corner sofas"
              data-lang-it="Montaggio divani e divani angolari">Montáž sedacích souprav a rohových sedaček</li>
          <li data-lang-cs="Instalace elektrických a manuálních mechanismů"
              data-lang-en="Installation of electrical and manual mechanisms"
              data-lang-it="Installazione di meccanismi elettrici e manuali">Instalace elektrických a manuálních mechanismů</li>
          <li data-lang-cs="Seřízení relaxačních funkcí křesel"
              data-lang-en="Adjusting the relaxation functions of the chairs"
              data-lang-it="Regolazione delle funzioni di rilassamento delle sedie">Seřízení relaxačních funkcí křesel</li>
          <li data-lang-cs="Kontrola stability a bezpečnosti"
              data-lang-en="Stability and safety check"
              data-lang-it="Controllo di stabilità e sicurezza">Kontrola stability a bezpečnosti</li>
          <li data-lang-cs="Instruktáž k používání a údržbě"
              data-lang-en="Instruction on use and maintenance"
              data-lang-it="Istruzioni per l'uso e la manutenzione">Instruktáž k používání a údržbě</li>
          <li data-lang-cs="Dovoz a odborná přeprava nábytku"
              data-lang-en="Import and professional transportation of furniture"
              data-lang-it="Importazione e trasporto professionale di mobili">Dovoz a odborná přeprava nábytku</li>
        </ul>
      </div>

      <!-- PORADENSTVÍ -->
      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Poradenství a expertní posudky"
            data-lang-en="Consulting and Expert Opinions"
            data-lang-it="Consulenza e Perizie">Poradenství a expertní posudky</h3>
        <p class="service-description"
           data-lang-cs="Odborné posouzení reklamací a škod na nábytku. Naše expertízy jsou uznávány pojišťovnami, výrobcem Natuzzi i soudy. Pomůžeme vám s vyřízením reklamace a získáním náhrady škody."
           data-lang-en="Professional assessment of furniture complaints and damages. Our expertise is recognized by insurance companies, the manufacturer Natuzzi and the courts. We will help you with the settlement of your complaint and obtaining compensation for damages."
           data-lang-it="Valutazione professionale di reclami e danni relativi agli arredi. La nostra competenza è riconosciuta dalle compagnie assicurative, dal produttore Natuzzi e dai tribunali. Vi aiuteremo a risolvere il vostro reclamo e a ottenere il risarcimento dei danni.">
          Odborné posouzení reklamací a škod na nábytku. Naše expertízy jsou uznávány pojišťovnami, výrobcem Natuzzi i soudy. Pomůžeme vám s vyřízením reklamace a získáním náhrady škody.
        </p>
        <ul class="service-list">
          <li data-lang-cs="Posouzení důvodnosti reklamace sedaček"
              data-lang-en="Assessment of the validity of a seat complaint"
              data-lang-it="Valutazione della validità di un reclamo relativo al posto a sedere">Posouzení důvodnosti reklamace sedaček</li>
          <li data-lang-cs="Znalecké posudky pro pojišťovny a soudy"
              data-lang-en="Expert opinions for insurance companies and courts"
              data-lang-it="Perizie per compagnie assicurative e tribunali">Znalecké posudky pro pojišťovny a soudy</li>
          <li data-lang-cs="Odhad rozsahu škod a nákladů na opravu"
              data-lang-en="Estimate the extent of damage and repair costs"
              data-lang-it="Stimare l'entità del danno e i costi di riparazione">Odhad rozsahu škod a nákladů na opravu</li>
          <li data-lang-cs="Konzultace při výběru sedací soupravy"
              data-lang-en="Consultation when choosing a sofa set"
              data-lang-it="Consulenza nella scelta di un set di divani">Konzultace při výběru sedací soupravy</li>
          <li data-lang-cs="Doporučení péče o kožený nábytek"
              data-lang-en="Leather furniture care recommendations"
              data-lang-it="Consigli per la cura dei mobili in pelle">Doporučení péče o kožený nábytek</li>
          <li data-lang-cs="Asistence při komunikaci s výrobcem"
              data-lang-en="Assistance in communicating with the manufacturer"
              data-lang-it="Assistenza nella comunicazione con il produttore">Asistence při komunikaci s výrobcem</li>
        </ul>
      </div>

    </div>
  </div>
</section>

</main>

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

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-container">
    <div class="footer-grid">
      
      <!-- FIRMA -->
      <div class="footer-column">
        <h2 class="footer-title"
            data-lang-cs="White Glove Service"
            data-lang-en="White Glove Service"
            data-lang-it="White Glove Service">White Glove Service</h2>
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
<script src="assets/js/logger.js" defer></script>

<script src="assets/js/nasesluzby.js" defer></script>
</body>
</html>
