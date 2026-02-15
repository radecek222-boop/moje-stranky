<?php
/**
 * SEO Landing Page: Oprava sedacky
 * Cilova klicova slova: oprava sedacky, oprava gauce, oprava pohovky, oprava sedaci soupravy
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
  <meta name="description" content="<?php echo getSeoDescription('oprava-sedacky'); ?>">
  <?php renderSeoMeta('oprava-sedacky'); ?>
  <?php renderSchemaOrg('oprava-sedacky'); ?>
  <?php renderFaqSchema('oprava-sedacky'); ?>

  <!-- PWA -->
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">

  <title><?php echo getSeoTitle('oprava-sedacky'); ?></title>

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
        data-lang-cs="Oprava sedacky"
        data-lang-en="Sofa Repair"
        data-lang-it="Riparazione Divano">Oprava sedacky</h1>
    <div class="hero-subtitle"
         data-lang-cs="Profesionalni servis sedacek, gaucu a pohovek"
         data-lang-en="Professional service for sofas and couches"
         data-lang-it="Servizio professionale per divani e sofà">Profesionalni servis sedacek, gaucu a pohovek</div>
  </div>
</section>

<!-- CONTENT SEKCE -->
<section class="content-section">
  <div class="container">

    <div class="section-intro">
      <h2 class="section-title"
          data-lang-cs="Oprava sedacky, pohovky a sedaci soupravy"
          data-lang-en="Repair of Sofas, Couches and Living Room Sets"
          data-lang-it="Riparazione di Divani, Sofà e Set Soggiorno">Oprava sedacky, pohovky a sedaci soupravy</h2>

      <p class="section-text"
         data-lang-cs="Potrebujete opravit sedacku, gauc nebo pohovku? Nase dilna se specializuje na profesionalni opravy vsech typu sedacek - kozenych, latkovych i kombinovanych. Opravujeme sedaci soupravy znacek Natuzzi, ale i dalsi luxusni i bezne znacky."
         data-lang-en="Need to repair a sofa, couch or settee? Our workshop specializes in professional repair of all types of sofas - leather, fabric and combined. We repair living room sets from Natuzzi as well as other luxury and standard brands."
         data-lang-it="Hai bisogno di riparare un divano, sofà o divanetto? La nostra officina è specializzata nella riparazione professionale di tutti i tipi di divani - in pelle, tessuto e combinati. Ripariamo set soggiorno di Natuzzi e di altri marchi di lusso e standard.">
        Potrebujete opravit sedacku, gauc nebo pohovku? Nase dilna se specializuje na profesionalni opravy vsech typu sedacek - kozenych, latkovych i kombinovanych. Opravujeme sedaci soupravy znacek Natuzzi, ale i dalsi luxusni i bezne znacky.
      </p>

      <p class="section-text"
         data-lang-cs="Zajistujeme svoz sedacek z cele Ceske republiky a Slovenska. Opravujeme prasklou kuzi, opotrebovane calouneni, nefunkcni mechanismy i elektricke pohony. Vsechny opravy provadime v nasi dilne profesionalnim naradim a s pouzitim kvalitnich materialu."
         data-lang-en="We arrange pickup of sofas from all over the Czech Republic and Slovakia. We repair cracked leather, worn upholstery, faulty mechanisms and electric motors. All repairs are done in our workshop with professional tools and quality materials."
         data-lang-it="Organizziamo il ritiro dei divani da tutta la Repubblica Ceca e la Slovacchia. Ripariamo pelle screpolata, rivestimenti usurati, meccanismi difettosi e motori elettrici. Tutte le riparazioni vengono eseguite nella nostra officina con strumenti professionali e materiali di qualità.">
        Zajistujeme svoz sedacek z cele Ceske republiky a Slovenska. Opravujeme prasklou kuzi, opotrebovane calouneni, nefunkcni mechanismy i elektricke pohony. Vsechny opravy provadime v nasi dilne profesionalnim naradim a s pouzitim kvalitnich materialu.
      </p>
    </div>

    <!-- TYPY OPRAV -->
    <div class="services-grid">

      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Typy oprav sedacek"
            data-lang-en="Types of Sofa Repairs"
            data-lang-it="Tipi di Riparazioni Divani">Typy oprav sedacek</h3>
        <ul class="service-list">
          <li data-lang-cs="Oprava kozene sedacky - praskliny, odreni, skvrny" data-lang-en="Leather sofa repair - cracks, abrasions, stains" data-lang-it="Riparazione divano in pelle - crepe, abrasioni, macchie">Oprava kozene sedacky - praskliny, odreni, skvrny</li>
          <li data-lang-cs="Oprava latkove sedacky - roztrzeni, fleky, vymena potahu" data-lang-en="Fabric sofa repair - tears, stains, cover replacement" data-lang-it="Riparazione divano in tessuto - strappi, macchie, sostituzione rivestimento">Oprava latkove sedacky - roztrzeni, fleky, vymena potahu</li>
          <li data-lang-cs="Vymena a renovace calouneni (sedaky, operky, podrucky)" data-lang-en="Upholstery replacement and renovation (seats, backs, armrests)" data-lang-it="Sostituzione e ristrutturazione rivestimenti (sedili, schienali, braccioli)">Vymena a renovace calouneni (sedaky, operky, podrucky)</li>
          <li data-lang-cs="Oprava mechanismu (relax, vysuv, naklápení)" data-lang-en="Mechanism repair (relax, slide, tilt)" data-lang-it="Riparazione meccanismo (relax, scorrimento, inclinazione)">Oprava mechanismu (relax, vysuv, naklápení)</li>
          <li data-lang-cs="Oprava elektrickych pohonu a ovladacu" data-lang-en="Electric motor and control repair" data-lang-it="Riparazione motori elettrici e comandi">Oprava elektrickych pohonu a ovladacu</li>
          <li data-lang-cs="Vymena vycpavek a konstrukce" data-lang-en="Padding and frame replacement" data-lang-it="Sostituzione imbottitura e struttura">Vymena vycpavek a konstrukce</li>
        </ul>
      </div>

      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Jak probiha oprava"
            data-lang-en="How Repair Works"
            data-lang-it="Come Funziona la Riparazione">Jak probiha oprava</h3>
        <ul class="service-list">
          <li data-lang-cs="1. Objednate servis online nebo telefonicky" data-lang-en="1. Order service online or by phone" data-lang-it="1. Ordina il servizio online o per telefono">1. Objednate servis online nebo telefonicky</li>
          <li data-lang-cs="2. Domluvime termin svozu vasi sedacky" data-lang-en="2. We arrange a pickup date for your sofa" data-lang-it="2. Fissiamo una data di ritiro per il tuo divano">2. Domluvime termin svozu vasi sedacky</li>
          <li data-lang-cs="3. Sedacku prevezeme do nasi dilny" data-lang-en="3. We transport the sofa to our workshop" data-lang-it="3. Trasportiamo il divano alla nostra officina">3. Sedacku prevezeme do nasi dilny</li>
          <li data-lang-cs="4. Provedeme diagnostiku a kalkulaci ceny" data-lang-en="4. We perform diagnostics and price calculation" data-lang-it="4. Eseguiamo diagnostica e calcolo del prezzo">4. Provedeme diagnostiku a kalkulaci ceny</li>
          <li data-lang-cs="5. Po odsouhlaseni provedeme opravu" data-lang-en="5. After approval, we perform the repair" data-lang-it="5. Dopo l'approvazione, eseguiamo la riparazione">5. Po odsouhlaseni provedeme opravu</li>
          <li data-lang-cs="6. Opravenou sedacku vam dovezeme zpet" data-lang-en="6. We deliver the repaired sofa back to you" data-lang-it="6. Consegniamo il divano riparato a voi">6. Opravenou sedacku vam dovezeme zpet</li>
        </ul>
      </div>

    </div>

    <!-- CENIK -->
    <div class="pricing-info">
      <h3 data-lang-cs="Kolik stoji oprava sedacky" data-lang-en="How Much Does Sofa Repair Cost" data-lang-it="Quanto Costa la Riparazione del Divano">Kolik stoji oprava sedacky</h3>
      <ul>
        <li><strong data-lang-cs="Diagnostika:" data-lang-en="Diagnostics:" data-lang-it="Diagnostica:">Diagnostika:</strong> 110 EUR</li>
        <li><strong data-lang-cs="Oprava 1 dilu (sedak/operka/podrucka):" data-lang-en="Repair of 1 part (seat/back/armrest):" data-lang-it="Riparazione 1 parte (sedile/schienale/bracciolo):">Oprava 1 dilu (sedak/operka/podrucka):</strong> 205 EUR</li>
        <li><strong data-lang-cs="Kazdy dalsi dil:" data-lang-en="Each additional part:" data-lang-it="Ogni parte aggiuntiva:">Kazdy dalsi dil:</strong> 70 EUR</li>
        <li><strong data-lang-cs="Oprava mechanismu:" data-lang-en="Mechanism repair:" data-lang-it="Riparazione meccanismo:">Oprava mechanismu:</strong> od 45 EUR + dily</li>
        <li><strong data-lang-cs="Dopravne:" data-lang-en="Transportation:" data-lang-it="Trasporto:">Dopravne:</strong> dle vzdalenosti</li>
      </ul>
      <p><a href="cenik.php#kalkulacka" class="link-cenik" data-lang-cs="Vypocitat cenu opravy online" data-lang-en="Calculate repair price online" data-lang-it="Calcola il prezzo di riparazione online">Vypocitat cenu opravy online</a></p>
    </div>

    <!-- FAQ SEKCE -->
    <div class="faq-section">
      <h2 class="section-title"
          data-lang-cs="Caste dotazy k oprave sedacek"
          data-lang-en="Frequently Asked Questions About Sofa Repair"
          data-lang-it="Domande Frequenti sulla Riparazione dei Divani">Caste dotazy k oprave sedacek</h2>

      <div class="faq-list">
        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Kolik stoji oprava sedacky nebo pohovky?"
              data-lang-en="How much does sofa or couch repair cost?"
              data-lang-it="Quanto costa la riparazione del divano o sofà?">Kolik stoji oprava sedacky nebo pohovky?</h3>
          <p class="faq-answer"
             data-lang-cs="Oprava sedacky zacina od 205 EUR za jeden dil (sedak, operka, podrucka). Kazdy dalsi dil stoji 70 EUR. Celkova cena zavisi na poctu dilu a typu opravy. Nabizime online kalkulacku pro presny vypocet."
             data-lang-en="Sofa repair starts from 205 EUR for one part (seat, back, armrest). Each additional part costs 70 EUR. The total price depends on the number of parts and type of repair. We offer an online calculator for exact calculation."
             data-lang-it="La riparazione del divano parte da 205 EUR per una parte (sedile, schienale, bracciolo). Ogni parte aggiuntiva costa 70 EUR. Il prezzo totale dipende dal numero di parti e dal tipo di riparazione. Offriamo un calcolatore online per un calcolo esatto.">
            Oprava sedacky zacina od 205 EUR za jeden dil (sedak, operka, podrucka). Kazdy dalsi dil stoji 70 EUR. Celkova cena zavisi na poctu dilu a typu opravy. Nabizime online kalkulacku pro presny vypocet.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Opravujete latkove i kozene sedacky?"
              data-lang-en="Do you repair fabric and leather sofas?"
              data-lang-it="Riparate divani in tessuto e in pelle?">Opravujete latkove i kozene sedacky?</h3>
          <p class="faq-answer"
             data-lang-cs="Ano, opravujeme vsechny typy sedacek - kozene, latkove, semisove i kombinovane. Pracujeme s originalnimi materialy od vyrobce i kvalitnymi alternativami."
             data-lang-en="Yes, we repair all types of sofas - leather, fabric, suede and combined. We work with original manufacturer materials as well as quality alternatives."
             data-lang-it="Sì, ripariamo tutti i tipi di divani - in pelle, tessuto, scamosciato e combinati. Lavoriamo con materiali originali del produttore e alternative di qualità.">
            Ano, opravujeme vsechny typy sedacek - kozene, latkove, semisove i kombinovane. Pracujeme s originalnimi materialy od vyrobce i kvalitnymi alternativami.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Svazite sedacku k oprave i z jineho mesta?"
              data-lang-en="Will you pick up the sofa for repair from another city?"
              data-lang-it="Ritirerete il divano per la riparazione da un'altra città?">Svazite sedacku k oprave i z jineho mesta?</h3>
          <p class="faq-answer"
             data-lang-cs="Ano, zajistujeme svoz sedacek z cele Ceske republiky a Slovenska. Dopravne se pocita podle vzdalenosti od nasi dilny v Praze - pouzijte nasi kalkulacku pro presny vypocet."
             data-lang-en="Yes, we arrange sofa pickup from all over the Czech Republic and Slovakia. Transportation is calculated based on the distance from our workshop in Prague - use our calculator for exact calculation."
             data-lang-it="Sì, organizziamo il ritiro dei divani da tutta la Repubblica Ceca e la Slovacchia. Il trasporto viene calcolato in base alla distanza dalla nostra officina a Praga - usa il nostro calcolatore per un calcolo esatto.">
            Ano, zajistujeme svoz sedacek z cele Ceske republiky a Slovenska. Dopravne se pocita podle vzdalenosti od nasi dilny v Praze - pouzijte nasi kalkulacku pro presny vypocet.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Opravujete i mechanismy sedacky (relax, vysuv)?"
              data-lang-en="Do you repair sofa mechanisms (relax, slide)?"
              data-lang-it="Riparate i meccanismi del divano (relax, scorrimento)?">Opravujete i mechanismy sedacky (relax, vysuv)?</h3>
          <p class="faq-answer"
             data-lang-cs="Ano, opravujeme vsechny typy mechanismu - manualni relax, elektricke polohovani, vysuvne podnozky i naklápeci operadla. Cena opravy mechanismu zacina od 45 EUR plus dily."
             data-lang-en="Yes, we repair all types of mechanisms - manual relax, electric positioning, slide-out footrests and tilting backrests. Mechanism repair price starts from 45 EUR plus parts."
             data-lang-it="Sì, ripariamo tutti i tipi di meccanismi - relax manuale, posizionamento elettrico, poggiapiedi estraibili e schienali reclinabili. Il prezzo della riparazione del meccanismo parte da 45 EUR più ricambi.">
            Ano, opravujeme vsechny typy mechanismu - manualni relax, elektricke polohovani, vysuvne podnozky i naklápeci operadla. Cena opravy mechanismu zacina od 45 EUR plus dily.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Jak dlouho trva oprava sedacky?"
              data-lang-en="How long does sofa repair take?"
              data-lang-it="Quanto tempo richiede la riparazione del divano?">Jak dlouho trva oprava sedacky?</h3>
          <p class="faq-answer"
             data-lang-cs="Bezna oprava sedacky trva 2-4 tydny. Slozitejsi opravy jako kompletni precalouneni mohou trvat 4-6 tydnu. Pri objednavce vas informujeme o predpokladanem terminu dokonceni."
             data-lang-en="Standard sofa repair takes 2-4 weeks. More complex repairs like complete reupholstery may take 4-6 weeks. When ordering, we will inform you of the expected completion date."
             data-lang-it="La riparazione standard del divano richiede 2-4 settimane. Riparazioni più complesse come la ritappezzatura completa possono richiedere 4-6 settimane. Al momento dell'ordine, vi informeremo della data di completamento prevista.">
            Bezna oprava sedacky trva 2-4 tydny. Slozitejsi opravy jako kompletni precalouneni mohou trvat 4-6 tydnu. Pri objednavce vas informujeme o predpokladanem terminu dokonceni.
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
        data-lang-cs="Potrebujete opravit sedacku?"
        data-lang-en="Need to Repair Your Sofa?"
        data-lang-it="Hai Bisogno di Riparare il Tuo Divano?">Potrebujete opravit sedacku?</h2>
    <p class="cta-text"
       data-lang-cs="Objednejte opravu online nebo nas kontaktujte. Zajistime svoz sedacky, provedeme opravu a dovezeme zpet. Profesionalni servis s 12mesicni zarukou."
       data-lang-en="Order repair online or contact us. We will arrange sofa pickup, perform the repair and deliver it back. Professional service with 12-month warranty."
       data-lang-it="Ordina la riparazione online o contattaci. Organizzeremo il ritiro del divano, eseguiremo la riparazione e lo consegneremo. Servizio professionale con garanzia di 12 mesi.">
      Objednejte opravu online nebo nas kontaktujte. Zajistime svoz sedacky, provedeme opravu a dovezeme zpet. Profesionalni servis s 12mesicni zarukou.
    </p>
    <a href="novareklamace.php" class="cta-button"
       data-lang-cs="Objednat opravu sedacky"
       data-lang-en="Order Sofa Repair"
       data-lang-it="Ordina Riparazione Divano">Objednat opravu sedacky</a>
  </div>
</section>

</main>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script src="assets/js/logger.min.js" defer></script>
<?php require_once __DIR__ . '/includes/pwa_scripts.php'; ?>
<?php require_once __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
