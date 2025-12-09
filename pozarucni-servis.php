<?php
/**
 * SEO Landing Page: Pozarucni servis sedacky
 * Cilova klicova slova: pozarucni servis, mimozarucni servis, servis po zaruce
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
  <meta name="description" content="<?php echo getSeoDescription('pozarucni-servis'); ?>">
  <?php renderSeoMeta('pozarucni-servis'); ?>
  <?php renderSchemaOrg('pozarucni-servis'); ?>
  <?php renderFaqSchema('pozarucni-servis'); ?>

  <!-- PWA -->
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">

  <title><?php echo getSeoTitle('pozarucni-servis'); ?></title>

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
        data-lang-cs="Pozarucni servis"
        data-lang-en="Out-of-Warranty Service"
        data-lang-it="Servizio Fuori Garanzia">Pozarucni servis</h1>
    <div class="hero-subtitle"
         data-lang-cs="Opravime vas nabytek i po skonceni zaruky"
         data-lang-en="We repair your furniture even after the warranty expires"
         data-lang-it="Ripariamo i vostri mobili anche dopo la scadenza della garanzia">Opravime vas nabytek i po skonceni zaruky</div>
  </div>
</section>

<!-- CONTENT SEKCE -->
<section class="content-section">
  <div class="container">

    <div class="section-intro">
      <h2 class="section-title"
          data-lang-cs="Mimozarucni a pozarucni opravy nabytku"
          data-lang-en="Out-of-Warranty Furniture Repairs"
          data-lang-it="Riparazioni Mobili Fuori Garanzia">Mimozarucni a pozarucni opravy nabytku</h2>

      <p class="section-text"
         data-lang-cs="Skoncila vam zaruka na sedacku, kreslo nebo pohovku? Zadny problem. Poskytujeme pozarucni servis pro vsechny znacky nabytku vcetne Natuzzi, a to za fer ceny. Vas oblibeny nabytek si zaslouzi profesionalni peci i po letech pouzivani."
         data-lang-en="Has your warranty on your sofa, armchair or couch expired? No problem. We provide out-of-warranty service for all furniture brands including Natuzzi at fair prices. Your favorite furniture deserves professional care even after years of use."
         data-lang-it="La garanzia sul divano, poltrona o sofà è scaduta? Nessun problema. Forniamo un servizio fuori garanzia per tutti i marchi di mobili, incluso Natuzzi, a prezzi equi. I vostri mobili preferiti meritano cure professionali anche dopo anni di utilizzo.">
        Skoncila vam zaruka na sedacku, kreslo nebo pohovku? Zadny problem. Poskytujeme pozarucni servis pro vsechny znacky nabytku vcetne Natuzzi, a to za fer ceny. Vas oblibeny nabytek si zaslouzi profesionalni peci i po letech pouzivani.
      </p>

      <p class="section-text"
         data-lang-cs="Nase dilna je vybavena profesionalnim naradim a pouzivame originalni nahradni dily i kvalitni alternativy. Opravujeme kozene i latkove sedacky, relaxacni kresla, mechanismy a vsechny typy calouneni. Svoz nabytku zajistujeme z cele Ceske republiky a Slovenska."
         data-lang-en="Our workshop is equipped with professional tools and we use original spare parts as well as quality alternatives. We repair leather and fabric sofas, recliners, mechanisms and all types of upholstery. We arrange furniture pickup from all over the Czech Republic and Slovakia."
         data-lang-it="La nostra officina è attrezzata con strumenti professionali e utilizziamo ricambi originali e alternative di qualità. Ripariamo divani in pelle e tessuto, poltrone relax, meccanismi e tutti i tipi di rivestimenti. Organizziamo il ritiro dei mobili da tutta la Repubblica Ceca e la Slovacchia.">
        Nase dilna je vybavena profesionalnim naradim a pouzivame originalni nahradni dily i kvalitni alternativy. Opravujeme kozene i latkove sedacky, relaxacni kresla, mechanismy a vsechny typy calouneni. Svoz nabytku zajistujeme z cele Ceske republiky a Slovenska.
      </p>
    </div>

    <!-- SLUZBY GRID -->
    <div class="services-grid">

      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Co opravujeme po zaruce"
            data-lang-en="What We Repair After Warranty"
            data-lang-it="Cosa Ripariamo Dopo la Garanzia">Co opravujeme po zaruce</h3>
        <ul class="service-list">
          <li data-lang-cs="Kozene a latkove sedacky vsech znacek" data-lang-en="Leather and fabric sofas of all brands" data-lang-it="Divani in pelle e tessuto di tutte le marche">Kozene a latkove sedacky vsech znacek</li>
          <li data-lang-cs="Relaxacni a klasicka kresla" data-lang-en="Recliners and classic armchairs" data-lang-it="Poltrone relax e classiche">Relaxacni a klasicka kresla</li>
          <li data-lang-cs="Mechanismy (relax, vysuv, naklápeni)" data-lang-en="Mechanisms (relax, slide, tilt)" data-lang-it="Meccanismi (relax, scorrimento, inclinazione)">Mechanismy (relax, vysuv, naklápeni)</li>
          <li data-lang-cs="Elektricke pohony a ovladani" data-lang-en="Electric motors and controls" data-lang-it="Motori elettrici e comandi">Elektricke pohony a ovladani</li>
          <li data-lang-cs="Prasklou a odrenou kuzi" data-lang-en="Cracked and worn leather" data-lang-it="Pelle screpolata e usurata">Prasklou a odrenou kuzi</li>
          <li data-lang-cs="Poskozene calouneni a vycpavky" data-lang-en="Damaged upholstery and padding" data-lang-it="Rivestimenti e imbottiture danneggiati">Poskozene calouneni a vycpavky</li>
        </ul>
      </div>

      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Proc vyuzit pozarucni servis"
            data-lang-en="Why Use Out-of-Warranty Service"
            data-lang-it="Perché Utilizzare il Servizio Fuori Garanzia">Proc vyuzit pozarucni servis</h3>
        <ul class="service-list">
          <li data-lang-cs="Ušetrite za novy nabytek - oprava je vyhodnejsi" data-lang-en="Save on new furniture - repair is more cost-effective" data-lang-it="Risparmia sui nuovi mobili - la riparazione è più conveniente">Usetrite za novy nabytek - oprava je vyhodnejsi</li>
          <li data-lang-cs="Zachovate oblibeny design a pohodli" data-lang-en="Keep your favorite design and comfort" data-lang-it="Mantieni il tuo design e comfort preferiti">Zachovate oblibeny design a pohodli</li>
          <li data-lang-cs="Profesionalni oprava s 12mesicni zarukou" data-lang-en="Professional repair with 12-month warranty" data-lang-it="Riparazione professionale con garanzia di 12 mesi">Profesionalni oprava s 12mesicni zarukou</li>
          <li data-lang-cs="Svoz a dovoz z cele CR a SR" data-lang-en="Pickup and delivery from all over CR and SR" data-lang-it="Ritiro e consegna da tutta CR e SR">Svoz a dovoz z cele CR a SR</li>
          <li data-lang-cs="Transparentni ceny bez skrytych poplatku" data-lang-en="Transparent prices without hidden fees" data-lang-it="Prezzi trasparenti senza costi nascosti">Transparentni ceny bez skrytych poplatku</li>
          <li data-lang-cs="Ekologicka volba - prodluzujete zivotnost nabytku" data-lang-en="Ecological choice - you extend furniture lifespan" data-lang-it="Scelta ecologica - prolunghi la vita dei mobili">Ekologicka volba - prodluzujete zivotnost nabytku</li>
        </ul>
      </div>

    </div>

    <!-- FAQ SEKCE -->
    <div class="faq-section">
      <h2 class="section-title"
          data-lang-cs="Caste dotazy k pozarucnimu servisu"
          data-lang-en="Frequently Asked Questions About Out-of-Warranty Service"
          data-lang-it="Domande Frequenti sul Servizio Fuori Garanzia">Caste dotazy k pozarucnimu servisu</h2>

      <div class="faq-list">
        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Co je pozarucni servis nabytku?"
              data-lang-en="What is out-of-warranty furniture service?"
              data-lang-it="Cos'è il servizio mobili fuori garanzia?">Co je pozarucni servis nabytku?</h3>
          <p class="faq-answer"
             data-lang-cs="Pozarucni servis je oprava nabytku po skonceni zaruky. Opravujeme sedacky, kresla a pohovky vsech znacek vcetne Natuzzi i po letech pouzivani. Pouzivame originalni dily a profesionalni techniky."
             data-lang-en="Out-of-warranty service is furniture repair after the warranty expires. We repair sofas, armchairs and couches of all brands including Natuzzi even after years of use. We use original parts and professional techniques."
             data-lang-it="Il servizio fuori garanzia è la riparazione dei mobili dopo la scadenza della garanzia. Ripariamo divani, poltrone e sofà di tutte le marche, incluso Natuzzi, anche dopo anni di utilizzo. Utilizziamo parti originali e tecniche professionali.">
            Pozarucni servis je oprava nabytku po skonceni zaruky. Opravujeme sedacky, kresla a pohovky vsech znacek vcetne Natuzzi i po letech pouzivani. Pouzivame originalni dily a profesionalni techniky.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Kolik stoji pozarucni oprava sedacky?"
              data-lang-en="How much does out-of-warranty sofa repair cost?"
              data-lang-it="Quanto costa la riparazione del divano fuori garanzia?">Kolik stoji pozarucni oprava sedacky?</h3>
          <p class="faq-answer"
             data-lang-cs="Cena pozarucni opravy zacina od 205 EUR za praci. Konecna cena zavisi na typu poskozeni a potrebnych dilech. Diagnostika stoji 110 EUR a pripoctete se k cene opravy. Presnou kalkulaci obdrzite pred zahajenim prace."
             data-lang-en="The price of out-of-warranty repair starts from 205 EUR for labor. The final price depends on the type of damage and required parts. Diagnostics costs 110 EUR and is added to the repair price. You will receive an exact calculation before work begins."
             data-lang-it="Il prezzo della riparazione fuori garanzia parte da 205 EUR per la manodopera. Il prezzo finale dipende dal tipo di danno e dai ricambi necessari. La diagnostica costa 110 EUR e viene aggiunta al prezzo della riparazione. Riceverai un calcolo esatto prima dell'inizio dei lavori.">
            Cena pozarucni opravy zacina od 205 EUR za praci. Konecna cena zavisi na typu poskozeni a potrebnych dilech. Diagnostika stoji 110 EUR a pripoctete se k cene opravy. Presnou kalkulaci obdrzite pred zahajenim prace.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Opravujete nabytek po zaruce od vsech vyrobcu?"
              data-lang-en="Do you repair post-warranty furniture from all manufacturers?"
              data-lang-it="Riparate mobili fuori garanzia di tutti i produttori?">Opravujete nabytek po zaruce od vsech vyrobcu?</h3>
          <p class="faq-answer"
             data-lang-cs="Ano, poskytujeme pozarucni servis pro vsechny znacky nabytku. Specializujeme se na Natuzzi, ale opravujeme i sedacky jinych vyrobcu - kozene, latkove i kombinovane."
             data-lang-en="Yes, we provide out-of-warranty service for all furniture brands. We specialize in Natuzzi, but we also repair sofas from other manufacturers - leather, fabric and combined."
             data-lang-it="Sì, forniamo servizio fuori garanzia per tutti i marchi di mobili. Siamo specializzati in Natuzzi, ma ripariamo anche divani di altri produttori - in pelle, tessuto e combinati.">
            Ano, poskytujeme pozarucni servis pro vsechny znacky nabytku. Specializujeme se na Natuzzi, ale opravujeme i sedacky jinych vyrobcu - kozene, latkove i kombinovane.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Jak dlouho trva pozarucni oprava?"
              data-lang-en="How long does out-of-warranty repair take?"
              data-lang-it="Quanto tempo richiede la riparazione fuori garanzia?">Jak dlouho trva pozarucni oprava?</h3>
          <p class="faq-answer"
             data-lang-cs="Bezna pozarucni oprava trva 2-4 tydny. Slozitejsi zasahy jako kompletni precalouneni mohou trvat 4-6 tydnu. Termin zavisi i na dostupnosti nahradnich dilu. Pri objednávce vas informujeme o predpokladanem terminu dokonceni."
             data-lang-en="Standard out-of-warranty repair takes 2-4 weeks. More complex work like complete reupholstery may take 4-6 weeks. The timeline also depends on spare parts availability. When ordering, we will inform you of the expected completion date."
             data-lang-it="La riparazione fuori garanzia standard richiede 2-4 settimane. Lavori più complessi come la ritappezzatura completa possono richiedere 4-6 settimane. I tempi dipendono anche dalla disponibilità dei ricambi. Al momento dell'ordine, vi informeremo della data di completamento prevista.">
            Bezna pozarucni oprava trva 2-4 tydny. Slozitejsi zasahy jako kompletni precalouneni mohou trvat 4-6 tydnu. Termin zavisi i na dostupnosti nahradnich dilu. Pri objednávce vas informujeme o predpokladanem terminu dokonceni.
          </p>
        </div>
      </div>
    </div>

    <!-- CENIK ODKAZ -->
    <div class="pricing-info">
      <h3 data-lang-cs="Orientacni ceny pozarucnich oprav" data-lang-en="Indicative prices for out-of-warranty repairs" data-lang-it="Prezzi indicativi per riparazioni fuori garanzia">Orientacni ceny pozarucnich oprav</h3>
      <ul>
        <li><strong data-lang-cs="Diagnostika:" data-lang-en="Diagnostics:" data-lang-it="Diagnostica:">Diagnostika:</strong> 110 EUR</li>
        <li><strong data-lang-cs="Oprava calouneni (1 dil):" data-lang-en="Upholstery repair (1 part):" data-lang-it="Riparazione rivestimento (1 parte):">Oprava calouneni (1 dil):</strong> od 205 EUR</li>
        <li><strong data-lang-cs="Oprava mechanismu:" data-lang-en="Mechanism repair:" data-lang-it="Riparazione meccanismo:">Oprava mechanismu:</strong> od 45 EUR + dily</li>
        <li><strong data-lang-cs="Dopravne:" data-lang-en="Transportation:" data-lang-it="Trasporto:">Dopravne:</strong> dle vzdalenosti (viz <a href="cenik.php#kalkulacka">kalkulacka</a>)</li>
      </ul>
      <p><a href="cenik.php" class="link-cenik" data-lang-cs="Zobrazit kompletni cenik" data-lang-en="View complete price list" data-lang-it="Visualizza listino prezzi completo">Zobrazit kompletni cenik</a></p>
    </div>

  </div>
</section>

<!-- CTA SEKCE -->
<section class="cta-section">
  <div class="container">
    <h2 class="cta-title"
        data-lang-cs="Potrebujete opravit nabytek po zaruce?"
        data-lang-en="Need to repair furniture after warranty?"
        data-lang-it="Hai bisogno di riparare mobili fuori garanzia?">Potrebujete opravit nabytek po zaruce?</h2>
    <p class="cta-text"
       data-lang-cs="Kontaktujte nas pro nezavaznou konzultaci. Posoudime stav vaseho nabytku a pripravime cenovou nabidku. Opravime vasi sedacku rychle a profesionalne."
       data-lang-en="Contact us for a non-binding consultation. We will assess the condition of your furniture and prepare a quote. We will repair your sofa quickly and professionally."
       data-lang-it="Contattaci per una consulenza senza impegno. Valuteremo le condizioni dei tuoi mobili e prepareremo un preventivo. Ripareremo il tuo divano in modo rapido e professionale.">
      Kontaktujte nas pro nezavaznou konzultaci. Posoudime stav vaseho nabytku a pripravime cenovou nabidku. Opravime vasi sedacku rychle a profesionalne.
    </p>
    <a href="novareklamace.php" class="cta-button"
       data-lang-cs="Objednat pozarucni servis"
       data-lang-en="Order Out-of-Warranty Service"
       data-lang-it="Ordina Servizio Fuori Garanzia">Objednat pozarucni servis</a>
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
           data-lang-cs="Specializovany servis Natuzzi."
           data-lang-en="Natuzzi specialized service."
           data-lang-it="Servizio specializzato Natuzzi.">
          Specializovany servis Natuzzi.
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
        <p class="footer-text">
          Do Dubce 364, Bechovice 190 11 CZ
        </p>
      </div>

    </div>

    <div class="footer-bottom">
      <p>
        &copy; 2025 White Glove Service.
        <span data-lang-cs="Všechna práva vyhrazena." data-lang-en="All rights reserved." data-lang-it="Tutti i diritti riservati.">Všechna práva vyhrazena.</span>
        <span aria-hidden="true"> • </span>
        <a href="gdpr.php" class="footer-link">GDPR</a>
        <span aria-hidden="true"> • </span>
        <a href="cookies.php" class="footer-link">Cookies</a>
        <span aria-hidden="true"> • </span>
        <a href="podminky.php" class="footer-link" data-lang-cs="Obchodní podmínky" data-lang-en="Terms of Service" data-lang-it="Termini di servizio">Obchodní podmínky</a>
      </p>
    </div>
  </div>
</footer>

<script src="assets/js/logger.min.js" defer></script>
<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
<?php require_once __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
