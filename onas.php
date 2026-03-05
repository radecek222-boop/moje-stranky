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
  <meta name="description" content="<?php echo getSeoDescription('onas'); ?>">
  <?php renderSeoMeta('onas'); ?>
  <?php renderSchemaOrg('onas'); ?>

  <!-- PWA -->
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">

  <title><?php echo getSeoTitle('onas'); ?></title>

  <!-- Preload critical resources -->
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/onas.min.css" as="style">
  <link rel="preload" href="assets/img/herman-image01.jpg" as="image" type="image/jpeg" fetchpriority="high">

  <!-- Google Fonts - Natuzzi style -->
  
  <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="stylesheet" href="assets/css/page-transitions.min.css">
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/onas.min.css">
  <link rel="stylesheet" href="assets/css/nasesluzby.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.min.css">

  <!-- Analytics Tracker -->
  <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
  <link rel="stylesheet" href="assets/css/poppins-font.css">
</head>
<body>
  <link rel="preload" as="image" href="assets/img/herman-image01.webp" fetchpriority="high">
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>
<!-- ČERNÝ HORNÍ PANEL -->

<!-- HERO SEKCE -->
<main id="main-content">
<section class="hero">
  <div class="hero-content">
    <h1 class="hero-title"
        data-lang-cs="O nás"
        data-lang-en="About Us"
        data-lang-it="Chi Siamo">O nás</h1>
    <div class="hero-subtitle">White Glove Service</div>
  </div>
</section>

<!-- CONTENT SEKCE -->
<section class="content-section">
  <div class="container">
    
    <div class="section-intro">
      <h2 class="section-title"
          data-lang-cs="Váš partner pro servis luxusního nábytku"
          data-lang-en="Your Partner for Luxury Furniture Service"
          data-lang-it="Il Tuo Partner per il Servizio di Arredamento di Lusso">Váš partner pro servis luxusního nábytku</h2>
      
      <p class="section-text"
         data-lang-cs="White Glove Service je autorizovaný servisní partner předních výrobců luxusního nábytku s více než pětiletou zkušeností v oblasti oprav, reklamací a montáží. Specializujeme se na servis sedacích souprav značek Natuzzi, Natuzzi Italia, Natuzzi Editions, Natuzzi Softaly a Phase, ale poskytujeme servis i pro další prémiové výrobce luxusního nábytku."
         data-lang-en="White Glove Service is an authorized service partner for leading luxury furniture manufacturers with over five years of experience in repairs, complaints and installation. We specialize in servicing sofas from Natuzzi, Natuzzi Italia, Natuzzi Editions, Natuzzi Softaly and Phase, but also provide service for other premium luxury furniture manufacturers."
         data-lang-it="White Glove Service è un partner autorizzato dei principali produttori di mobili di lusso con oltre cinque anni di esperienza nel campo delle riparazioni, dei reclami e dell'installazione. Siamo specializzati nell'assistenza di divani di marchi Natuzzi, Natuzzi Italia, Natuzzi Editions, Natuzzi Softaly e Phase, ma offriamo assistenza anche ad altri produttori premium di mobili di lusso.">
        White Glove Service je autorizovaný servisní partner předních výrobců luxusního nábytku s více než pětiletou zkušeností v oblasti oprav, reklamací a montáží. Specializujeme se na servis sedacích souprav značek Natuzzi, Natuzzi Italia, Natuzzi Editions, Natuzzi Softaly a Phase, ale poskytujeme servis i pro další prémiové výrobce luxusního nábytku.
      </p>
      
      <p class="section-text"
         data-lang-cs="Jsme certifikovaní technici s odbornou kvalifikací v oblasti čalounění, renovace kožených povrchů a oprav mechanismů relaxačních křesel. Naše dílna je vybavena profesionálním nářadím a pracujeme výhradně s originálními náhradními díly a materiály schválenými výrobcem."
         data-lang-en="We are certified technicians with professional qualifications in upholstery, leather surface renovation and recliner mechanism repairs. Our workshop is equipped with professional tools and we work exclusively with original spare parts and materials approved by the manufacturer."
         data-lang-it="Siamo tecnici certificati con qualifiche professionali nella tappezzeria, nel restauro di superfici in pelle e nella riparazione di meccanismi per poltrone reclinabili. La nostra officina è dotata di strumenti professionali e lavoriamo esclusivamente con ricambi e materiali originali approvati dal produttore.">
        Jsme certifikovaní technici s odbornou kvalifikací v oblasti čalounění, renovace kožených povrchů a oprav mechanismů relaxačních křesel. Naše dílna je vybavena profesionálním nářadím a pracujeme výhradně s originálními náhradními díly a materiály schválenými výrobcem.
      </p>
      
      <p class="section-text"
         data-lang-cs="Spolupracujeme s předními českými a slovenskými prodejci luxusního nábytku a poskytujeme servis i pro další prémiové značky. Naše služby jsou dostupné v celé České republice i na Slovensku s rychlou odezvou a flexibilním přístupem k zákazníkům."
         data-lang-en="We cooperate with leading Czech and Slovak luxury furniture retailers and provide service for other premium brands. Our services are available throughout the Czech Republic and Slovakia with a quick response and flexible approach to customers."
         data-lang-it="Collaboriamo con i principali rivenditori di mobili di lusso cechi e slovacchi e forniamo assistenza anche ad altri marchi premium. I nostri servizi sono disponibili in tutta la Repubblica Ceca e in Slovacchia, con una risposta rapida e un approccio flessibile al cliente.">
        Spolupracujeme s předními českými a slovenskými prodejci luxusního nábytku a poskytujeme servis i pro další prémiové značky. Naše služby jsou dostupné v celé České republice i na Slovensku s rychlou odezvou a flexibilním přístupem k zákazníkům.
      </p>
    </div>

    <!-- HODNOTY -->
    <div class="values-grid">
      
      <div class="value-card">
        <div class="value-number">5+</div>
        <h3 class="value-title"
            data-lang-cs="Let zkušeností"
            data-lang-en="Years of Experience"
            data-lang-it="Anni di Esperienza">Let zkušeností</h3>
        <p class="value-description"
           data-lang-cs="Více než 5 let poskytujeme profesionální servis luxusního nábytku – Natuzzi, Softaly, Phase a dalších prémiových značek"
           data-lang-en="For more than 5 years we have been providing professional service for luxury furniture – Natuzzi, Softaly, Phase and other premium brands"
           data-lang-it="Da oltre 5 anni forniamo un servizio professionale per mobili di lusso – Natuzzi, Softaly, Phase e altri marchi premium">
          Více než 5 let poskytujeme profesionální servis luxusního nábytku – Natuzzi, Softaly, Phase a dalších prémiových značek
        </p>
      </div>

      <div class="value-card">
        <div class="value-number">2000+</div>
        <h3 class="value-title"
            data-lang-cs="Spokojených zákazníků"
            data-lang-en="Satisfied Customers"
            data-lang-it="Clienti Soddisfatti">Spokojených zákazníků</h3>
        <p class="value-description"
           data-lang-cs="Úspěšně jsme vyřešili stovky reklamací a oprav sedacích souprav po celé ČR a SR"
           data-lang-en="We have successfully resolved hundreds of complaints and repairs of sofas throughout the Czech Republic and Slovakia"
           data-lang-it="Abbiamo risolto con successo centinaia di reclami e riparato divani in tutta la Repubblica Ceca e in Slovacchia">
          Úspěšně jsme vyřešili stovky reklamací a oprav sedacích souprav po celé ČR a SR
        </p>
      </div>

      <div class="value-card">
        <div class="value-number">100%</div>
        <h3 class="value-title"
            data-lang-cs="Originální díly"
            data-lang-en="Original Parts"
            data-lang-it="Parti Originali">Originální díly</h3>
        <p class="value-description"
           data-lang-cs="Pracujeme výhradně s originálními náhradními díly a materiály schválenými výrobcem"
           data-lang-en="We work exclusively with original spare parts and materials approved by the manufacturer"
           data-lang-it="Lavoriamo esclusivamente con ricambi originali e materiali approvati dal produttore">
          Pracujeme výhradně s originálními náhradními díly a materiály schválenými výrobcem
        </p>
      </div>

    </div>
  </div>
</section>

<!-- CERTIFIKACE -->
<section class="certifications">
  <div class="container">
    <h2 class="section-title"
        data-lang-cs="Certifikace a partnerství"
        data-lang-en="Certification and Partnership"
        data-lang-it="Certificazione e Partnership">Certifikace a partnerství</h2>
    <div class="partner-loga">
      <a href="https://www.natuzzi.cz/kolekce-kresla-detail?logos" target="_blank" rel="noopener noreferrer" class="partner-logo-polozka">
        <img src="assets/img/partners/logo4.png" alt="Logo Natuzzi Italia – autorizovaný servisní partner pro opravy a reklamace luxusního nábytku Natuzzi v ČR a SR" loading="lazy">
      </a>
      <a href="https://natuzzidesign.cz" target="_blank" rel="noopener noreferrer" class="partner-logo-polozka">
        <img src="assets/img/partners/logo3.png" alt="Logo Natuzzi Editions – servis, opravy a reklamace sedacích souprav Natuzzi Editions, autorizovaný partner White Glove Service" loading="lazy" style="filter: invert(1);">
      </a>
      <a href="https://www.italydesign.cz" target="_blank" rel="noopener noreferrer" class="partner-logo-polozka">
        <img src="assets/img/partners/logo2.png" alt="Logo Softaly Natuzzi – prémiové čalouněné sedačky a křesla, servis a opravy v České republice" loading="lazy">
      </a>
      <a href="https://pohodliphase.cz/?utm_source=google&utm_medium=cpc&utm_campaign=%5Bptagroup%5D%20-%20SRCH%20-%20Brand%20CZ%20(Len%20Praha)&utm_id=21517294317&gad_source=1&gad_campaignid=21517294317&gbraid=0AAAAApDVrQvLiox0FEbSo7LR8Zyi8auxu&gclid=CjwKCAiAh5XNBhAAEiwA_Bu8FTKRUDDzD5h2I0Mt_fUuLIrkP5wmcZod2X-tjgT5fcRtH2UhPaCAmhoCbeYQAvD_BwE" target="_blank" rel="noopener noreferrer" class="partner-logo-polozka">
        <img src="assets/img/partners/logo1.png" alt="Logo Phase – prodejce luxusního nábytku a sedacích souprav, partner White Glove Service" loading="lazy">
      </a>
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
        data-lang-cs="Nábytek v rukách profesionálů"
        data-lang-en="Furniture in the Hands of Professionals"
        data-lang-it="Mobili nelle Mani dei Professionisti">Nábytek v rukách profesionálů</h2>
    <p class="cta-text"
       data-lang-cs="Kontaktujte nás pro nezávaznou konzultaci nebo objednejte servisní zásah. Garantujeme rychlou a kvalitní opravu vaší sedačky."
       data-lang-en="Contact us for a non-binding consultation or order a service intervention. We guarantee a fast and high-quality repair of your seat."
       data-lang-it="Contattaci per una consulenza senza impegno o prenota un intervento di assistenza. Garantiamo una riparazione rapida e di alta qualità del tuo sedile.">
      Kontaktujte nás pro nezávaznou konzultaci nebo objednejte servisní zásah. Garantujeme rychlou a kvalitní opravu vaší sedačky.
    </p>
    <a href="novareklamace.php" class="cta-button"
       data-lang-cs="Objednat servis"
       data-lang-en="Order Service"
       data-lang-it="Servizio di Ordinazione">Objednat servis</a>
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
