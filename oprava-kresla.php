<?php
/**
 * SEO Landing Page: Oprava kresla
 * Cilova klicova slova: oprava kresla, oprava relaxacniho kresla, servis kresla
 */
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
  <meta name="description" content="<?php echo getSeoDescription('oprava-kresla'); ?>">
  <?php renderSeoMeta('oprava-kresla'); ?>
  <?php renderSchemaOrg('oprava-kresla'); ?>
  <?php renderFaqSchema('oprava-kresla'); ?>

  <!-- PWA -->
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">

  <title><?php echo getSeoTitle('oprava-kresla'); ?></title>

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
        data-lang-cs="Oprava kresla"
        data-lang-en="Armchair Repair"
        data-lang-it="Riparazione Poltrona">Oprava kresla</h1>
    <div class="hero-subtitle"
         data-lang-cs="Servis relaxacnich a klasickych kresel"
         data-lang-en="Service for recliners and classic armchairs"
         data-lang-it="Servizio per poltrone relax e classiche">Servis relaxacnich a klasickych kresel</div>
  </div>
</section>

<!-- CONTENT SEKCE -->
<section class="content-section">
  <div class="container">

    <div class="section-intro">
      <h2 class="section-title"
          data-lang-cs="Profesionalni oprava kresel vsech typu"
          data-lang-en="Professional Repair of All Types of Armchairs"
          data-lang-it="Riparazione Professionale di Tutti i Tipi di Poltrone">Profesionalni oprava kresel vsech typu</h2>

      <p class="section-text"
         data-lang-cs="Specializujeme se na opravy kresel - relaxacnich s manuálním ci elektrickym mechanismem, klasickych kozenych i latkovych. Opravujeme kresla znacky Natuzzi i jinych vyrobcu. Zajistujeme svoz z cele Ceske republiky a Slovenska."
         data-lang-en="We specialize in armchair repairs - recliners with manual or electric mechanisms, classic leather and fabric ones. We repair Natuzzi armchairs as well as other manufacturers. We arrange pickup from all over the Czech Republic and Slovakia."
         data-lang-it="Siamo specializzati nella riparazione di poltrone - relax con meccanismo manuale o elettrico, classiche in pelle e tessuto. Ripariamo poltrone Natuzzi e di altri produttori. Organizziamo il ritiro da tutta la Repubblica Ceca e la Slovacchia.">
        Specializujeme se na opravy kresel - relaxacnich s manuálním ci elektrickym mechanismem, klasickych kozenych i latkovych. Opravujeme kresla znacky Natuzzi i jinych vyrobcu. Zajistujeme svoz z cele Ceske republiky a Slovenska.
      </p>

      <p class="section-text"
         data-lang-cs="Relaxacni kresla jsou slozita zarizeni kombinujici calouneni s mechanickymi ci elektrickymi cástmi. Nasi technici maji zkusenosti s opravou vsech typu mechanismu - od jednoduchych manualnich po sofistikovane elektricke systémy s paměti poloh."
         data-lang-en="Recliners are complex devices combining upholstery with mechanical or electrical parts. Our technicians have experience repairing all types of mechanisms - from simple manual to sophisticated electric systems with position memory."
         data-lang-it="Le poltrone relax sono dispositivi complessi che combinano rivestimento con parti meccaniche o elettriche. I nostri tecnici hanno esperienza nella riparazione di tutti i tipi di meccanismi - dai semplici manuali ai sofisticati sistemi elettrici con memoria di posizione.">
        Relaxacni kresla jsou slozita zarizeni kombinujici calouneni s mechanickymi ci elektrickymi cástmi. Nasi technici maji zkusenosti s opravou vsech typu mechanismu - od jednoduchych manualnich po sofistikovane elektricke systémy s paměti poloh.
      </p>
    </div>

    <!-- TYPY KRESEL -->
    <div class="services-grid">

      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Jake kresla opravujeme"
            data-lang-en="What Armchairs We Repair"
            data-lang-it="Quali Poltrone Ripariamo">Jake kresla opravujeme</h3>
        <ul class="service-list">
          <li data-lang-cs="Relaxacni kresla s manualnim mechanismem" data-lang-en="Recliners with manual mechanism" data-lang-it="Poltrone relax con meccanismo manuale">Relaxacni kresla s manualnim mechanismem</li>
          <li data-lang-cs="Relaxacni kresla s elektrickym pohonem" data-lang-en="Recliners with electric motor" data-lang-it="Poltrone relax con motore elettrico">Relaxacni kresla s elektrickym pohonem</li>
          <li data-lang-cs="Klasicka kozena a latkova kresla" data-lang-en="Classic leather and fabric armchairs" data-lang-it="Poltrone classiche in pelle e tessuto">Klasicka kozena a latkova kresla</li>
          <li data-lang-cs="Designova a luxusni kresla (Natuzzi aj.)" data-lang-en="Designer and luxury armchairs (Natuzzi etc.)" data-lang-it="Poltrone di design e lusso (Natuzzi ecc.)">Designova a luxusni kresla (Natuzzi aj.)</li>
          <li data-lang-cs="Kancelarska a ergonomicka kresla" data-lang-en="Office and ergonomic armchairs" data-lang-it="Poltrone da ufficio ed ergonomiche">Kancelarska a ergonomicka kresla</li>
          <li data-lang-cs="Masazni a vyhrívana kresla" data-lang-en="Massage and heated armchairs" data-lang-it="Poltrone massaggianti e riscaldate">Masazni a vyhrívana kresla</li>
        </ul>
      </div>

      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Typy oprav kresel"
            data-lang-en="Types of Armchair Repairs"
            data-lang-it="Tipi di Riparazioni Poltrone">Typy oprav kresel</h3>
        <ul class="service-list">
          <li data-lang-cs="Oprava mechanismu relax (manualni i elektricky)" data-lang-en="Relax mechanism repair (manual and electric)" data-lang-it="Riparazione meccanismo relax (manuale ed elettrico)">Oprava mechanismu relax (manualni i elektricky)</li>
          <li data-lang-cs="Vymena motoru, transformatoru, ovladacu" data-lang-en="Motor, transformer, controller replacement" data-lang-it="Sostituzione motore, trasformatore, comandi">Vymena motoru, transformatoru, ovladacu</li>
          <li data-lang-cs="Oprava prasklé nebo odřené kuze" data-lang-en="Cracked or worn leather repair" data-lang-it="Riparazione pelle screpolata o usurata">Oprava prasklé nebo odřené kuze</li>
          <li data-lang-cs="Vymena calouneni a potahu" data-lang-en="Upholstery and cover replacement" data-lang-it="Sostituzione rivestimento e fodera">Vymena calouneni a potahu</li>
          <li data-lang-cs="Vymena vycpávek a konstrukce" data-lang-en="Padding and frame replacement" data-lang-it="Sostituzione imbottitura e struttura">Vymena vycpávek a konstrukce</li>
          <li data-lang-cs="Renovace kuze (cisteni, barveni, osetreni)" data-lang-en="Leather renovation (cleaning, dyeing, treatment)" data-lang-it="Ristrutturazione pelle (pulizia, tintura, trattamento)">Renovace kuze (cisteni, barveni, osetreni)</li>
        </ul>
      </div>

    </div>

    <!-- CENIK -->
    <div class="pricing-info">
      <h3 data-lang-cs="Kolik stoji oprava kresla" data-lang-en="How Much Does Armchair Repair Cost" data-lang-it="Quanto Costa la Riparazione della Poltrona">Kolik stoji oprava kresla</h3>
      <ul>
        <li><strong data-lang-cs="Oprava mechanismu (bez calouneni):" data-lang-en="Mechanism repair (without upholstery):" data-lang-it="Riparazione meccanismo (senza rivestimento):">Oprava mechanismu (bez calouneni):</strong> od 165 EUR</li>
        <li><strong data-lang-cs="Kompletni oprava vcetne calouneni:" data-lang-en="Complete repair including upholstery:" data-lang-it="Riparazione completa incluso rivestimento:">Kompletni oprava vcetne calouneni:</strong> od 205 EUR</li>
        <li><strong data-lang-cs="Oprava elektrickeho pohonu:" data-lang-en="Electric motor repair:" data-lang-it="Riparazione motore elettrico:">Oprava elektrickeho pohonu:</strong> od 45 EUR + dily</li>
        <li><strong data-lang-cs="Dopravne:" data-lang-en="Transportation:" data-lang-it="Trasporto:">Dopravne:</strong> dle vzdalenosti</li>
      </ul>
      <p><a href="cenik.php" class="link-cenik" data-lang-cs="Zobrazit kompletni cenik" data-lang-en="View complete price list" data-lang-it="Visualizza listino prezzi completo">Zobrazit kompletni cenik</a></p>
    </div>

    <!-- FAQ SEKCE -->
    <div class="faq-section">
      <h2 class="section-title"
          data-lang-cs="Caste dotazy k oprave kresel"
          data-lang-en="Frequently Asked Questions About Armchair Repair"
          data-lang-it="Domande Frequenti sulla Riparazione delle Poltrone">Caste dotazy k oprave kresel</h2>

      <div class="faq-list">
        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Kolik stoji oprava kresla?"
              data-lang-en="How much does armchair repair cost?"
              data-lang-it="Quanto costa la riparazione della poltrona?">Kolik stoji oprava kresla?</h3>
          <p class="faq-answer"
             data-lang-cs="Oprava kresla zacina od 165 EUR za mechanicke opravy bez calouneni. Kompletni oprava vcetne calouneni stoji od 205 EUR. Presnou cenu urcime po diagnostice."
             data-lang-en="Armchair repair starts from 165 EUR for mechanical repairs without upholstery. Complete repair including upholstery costs from 205 EUR. The exact price is determined after diagnostics."
             data-lang-it="La riparazione della poltrona parte da 165 EUR per riparazioni meccaniche senza rivestimento. La riparazione completa incluso rivestimento costa da 205 EUR. Il prezzo esatto viene determinato dopo la diagnostica.">
            Oprava kresla zacina od 165 EUR za mechanicke opravy bez calouneni. Kompletni oprava vcetne calouneni stoji od 205 EUR. Presnou cenu urcime po diagnostice.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Opravujete relaxacni kresla s elektrickym pohonem?"
              data-lang-en="Do you repair electric recliners?"
              data-lang-it="Riparate poltrone relax elettriche?">Opravujete relaxacni kresla s elektrickym pohonem?</h3>
          <p class="faq-answer"
             data-lang-cs="Ano, specializujeme se na opravy relaxacnich kresel vcetne elektrickych. Opravujeme motory, ovladace, transformatory i mechanicke casti. Pouzivame originalni i alternativni nahradni dily."
             data-lang-en="Yes, we specialize in recliner repairs including electric ones. We repair motors, controllers, transformers and mechanical parts. We use original and alternative spare parts."
             data-lang-it="Sì, siamo specializzati nella riparazione di poltrone relax, incluse quelle elettriche. Ripariamo motori, comandi, trasformatori e parti meccaniche. Utilizziamo ricambi originali e alternativi.">
            Ano, specializujeme se na opravy relaxacnich kresel vcetne elektrickych. Opravujeme motory, ovladace, transformatory i mechanicke casti. Pouzivame originalni i alternativni nahradni dily.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Muzete opravit prasklou kuzi na kresle?"
              data-lang-en="Can you repair cracked leather on an armchair?"
              data-lang-it="Potete riparare la pelle screpolata su una poltrona?">Muzete opravit prasklou kuzi na kresle?</h3>
          <p class="faq-answer"
             data-lang-cs="Ano, opravujeme prasklou, odrenou i jinou poskozenou kuzi na kreslech. Podle rozsahu poskozeni provedeme lokální opravu nebo kompletni precalouneni. Pouzivame kvalitni materialy."
             data-lang-en="Yes, we repair cracked, worn and other damaged leather on armchairs. Depending on the extent of damage, we perform local repair or complete reupholstery. We use quality materials."
             data-lang-it="Sì, ripariamo pelle screpolata, usurata e altri danni sulle poltrone. A seconda dell'entità del danno, eseguiamo riparazioni locali o ritappezzatura completa. Utilizziamo materiali di qualità.">
            Ano, opravujeme prasklou, odrenou i jinou poskozenou kuzi na kreslech. Podle rozsahu poskozeni provedeme lokální opravu nebo kompletni precalouneni. Pouzivame kvalitni materialy.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Jak dlouho trva oprava kresla?"
              data-lang-en="How long does armchair repair take?"
              data-lang-it="Quanto tempo richiede la riparazione della poltrona?">Jak dlouho trva oprava kresla?</h3>
          <p class="faq-answer"
             data-lang-cs="Bezna oprava kresla trva 1-3 tydny podle slozitosti. Opravy mechanismu jsou rychlejsi (1-2 tydny), precalouneni trva dele (3-4 tydny). Pri objednavce vas informujeme o predpokladanem terminu."
             data-lang-en="Standard armchair repair takes 1-3 weeks depending on complexity. Mechanism repairs are faster (1-2 weeks), reupholstery takes longer (3-4 weeks). When ordering, we will inform you of the expected timeline."
             data-lang-it="La riparazione standard della poltrona richiede 1-3 settimane a seconda della complessità. Le riparazioni del meccanismo sono più veloci (1-2 settimane), la ritappezzatura richiede più tempo (3-4 settimane). Al momento dell'ordine, vi informeremo dei tempi previsti.">
            Bezna oprava kresla trva 1-3 tydny podle slozitosti. Opravy mechanismu jsou rychlejsi (1-2 tydny), precalouneni trva dele (3-4 tydny). Pri objednavce vas informujeme o predpokladanem terminu.
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
        data-lang-cs="Potrebujete opravit kreslo?"
        data-lang-en="Need to Repair Your Armchair?"
        data-lang-it="Hai Bisogno di Riparare la Tua Poltrona?">Potrebujete opravit kreslo?</h2>
    <p class="cta-text"
       data-lang-cs="Objednejte opravu kresla online nebo nas kontaktujte. Zajistime svoz, provedeme opravu a dovezeme zpet. Profesionalni servis s 12mesicni zarukou."
       data-lang-en="Order armchair repair online or contact us. We will arrange pickup, perform the repair and deliver it back. Professional service with 12-month warranty."
       data-lang-it="Ordina la riparazione della poltrona online o contattaci. Organizzeremo il ritiro, eseguiremo la riparazione e la consegneremo. Servizio professionale con garanzia di 12 mesi.">
      Objednejte opravu kresla online nebo nas kontaktujte. Zajistime svoz, provedeme opravu a dovezeme zpet. Profesionalni servis s 12mesicni zarukou.
    </p>
    <a href="novareklamace.php" class="cta-button"
       data-lang-cs="Objednat opravu kresla"
       data-lang-en="Order Armchair Repair"
       data-lang-it="Ordina Riparazione Poltrona">Objednat opravu kresla</a>
  </div>
</section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="assets/js/logger.min.js" defer></script>
<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
<?php require_once __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
