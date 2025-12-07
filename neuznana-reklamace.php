<?php
/**
 * SEO Landing Page: Neuznana reklamace
 * Cilova klicova slova: neuznana reklamace, zamitnuta reklamace, co delat kdyz neuznali reklamaci
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
  <meta name="description" content="<?php echo getSeoDescription('neuznana-reklamace'); ?>">
  <?php renderSeoMeta('neuznana-reklamace'); ?>
  <?php renderSchemaOrg('neuznana-reklamace'); ?>
  <?php renderFaqSchema('neuznana-reklamace'); ?>

  <!-- PWA -->
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">

  <title><?php echo getSeoTitle('neuznana-reklamace'); ?></title>

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
        data-lang-cs="Neuznana reklamace?"
        data-lang-en="Rejected Warranty Claim?"
        data-lang-it="Reclamo Rifiutato?">Neuznana reklamace?</h1>
    <div class="hero-subtitle"
         data-lang-cs="Poradime vam, jak postupovat dal"
         data-lang-en="We will advise you on how to proceed"
         data-lang-it="Ti consiglieremo come procedere">Poradime vam, jak postupovat dal</div>
  </div>
</section>

<!-- CONTENT SEKCE -->
<section class="content-section">
  <div class="container">

    <div class="section-intro">
      <h2 class="section-title"
          data-lang-cs="Neuznali vam reklamaci sedacky nebo kresla?"
          data-lang-en="Was your sofa or armchair warranty claim rejected?"
          data-lang-it="Il vostro reclamo per divano o poltrona è stato rifiutato?">Neuznali vam reklamaci sedacky nebo kresla?</h2>

      <p class="section-text"
         data-lang-cs="Zamitnuti reklamace nabytku muze byt frustrujici, ale nezoufejte. Existuje vice moznosti, jak situaci resit. Muzete pozadat o prezkum, obratit se na Ceskou obchodni inspekci, nebo si nechat nabytek opravit u nezavisleho servisu za fer cenu."
         data-lang-en="Having a furniture warranty claim rejected can be frustrating, but don't despair. There are several options for resolving the situation. You can request a review, contact the Czech Trade Inspection, or have your furniture repaired by an independent service at a fair price."
         data-lang-it="Avere un reclamo in garanzia per mobili rifiutato può essere frustrante, ma non disperarti. Ci sono diverse opzioni per risolvere la situazione. Puoi richiedere una revisione, contattare l'Ispezione del Commercio Ceca o far riparare i tuoi mobili da un servizio indipendente a un prezzo equo.">
        Zamitnuti reklamace nabytku muze byt frustrujici, ale nezoufejte. Existuje vice moznosti, jak situaci resit. Muzete pozadat o prezkum, obratit se na Ceskou obchodni inspekci, nebo si nechat nabytek opravit u nezavisleho servisu za fer cenu.
      </p>

      <p class="section-text"
         data-lang-cs="Jako profesionalni servis s vice nez petilou zkusenosti nabizime nezavisle posouzeni stavu vaseho nabytku. Muzeme vam sdelit, zda bylo zamitnuti reklamace opravnene, a navrhnout reseni - at uz je to oprava, renovace nebo jen poradenstvi."
         data-lang-en="As a professional service with more than five years of experience, we offer an independent assessment of your furniture's condition. We can tell you whether the warranty rejection was justified and suggest solutions - whether it's repair, renovation or just advice."
         data-lang-it="Come servizio professionale con più di cinque anni di esperienza, offriamo una valutazione indipendente delle condizioni dei vostri mobili. Possiamo dirvi se il rifiuto della garanzia era giustificato e suggerire soluzioni - che si tratti di riparazione, ristrutturazione o solo consulenza.">
        Jako profesionalni servis s vice nez petilou zkusenosti nabizime nezavisle posouzeni stavu vaseho nabytku. Muzeme vam sdelit, zda bylo zamitnuti reklamace opravnene, a navrhnout reseni - at uz je to oprava, renovace nebo jen poradenstvi.
      </p>
    </div>

    <!-- POSTUP SEKCE -->
    <div class="services-grid">

      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Jake mate moznosti"
            data-lang-en="What Are Your Options"
            data-lang-it="Quali Sono le Vostre Opzioni">Jake mate moznosti</h3>
        <ul class="service-list">
          <li data-lang-cs="Pozadat prodejce o prezkum rozhodnuti" data-lang-en="Ask the seller to review the decision" data-lang-it="Chiedere al venditore di rivedere la decisione">Pozadat prodejce o prezkum rozhodnuti</li>
          <li data-lang-cs="Obratit se na Ceskou obchodni inspekci (COI)" data-lang-en="Contact the Czech Trade Inspection (COI)" data-lang-it="Contattare l'Ispezione del Commercio Ceca (COI)">Obratit se na Ceskou obchodni inspekci (COI)</li>
          <li data-lang-cs="Zadat znalecky posudek" data-lang-en="Request an expert assessment" data-lang-it="Richiedere una perizia">Zadat znalecky posudek</li>
          <li data-lang-cs="Resit spor mimosoudne nebo soudne" data-lang-en="Resolve the dispute out of court or in court" data-lang-it="Risolvere la controversia in via extragiudiziale o giudiziale">Resit spor mimosoudne nebo soudne</li>
          <li data-lang-cs="Nechat si nabytek opravit za vlastni naklady" data-lang-en="Have furniture repaired at your own expense" data-lang-it="Far riparare i mobili a proprie spese">Nechat si nabytek opravit za vlastni naklady</li>
        </ul>
      </div>

      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Co vam muzeme nabidnout"
            data-lang-en="What We Can Offer You"
            data-lang-it="Cosa Possiamo Offrirvi">Co vam muzeme nabidnout</h3>
        <ul class="service-list">
          <li data-lang-cs="Nezavisle posouzeni stavu nabytku (110 EUR)" data-lang-en="Independent furniture condition assessment (110 EUR)" data-lang-it="Valutazione indipendente delle condizioni dei mobili (110 EUR)">Nezavisle posouzeni stavu nabytku (110 EUR)</li>
          <li data-lang-cs="Odborny nazor na opravnenost reklamace" data-lang-en="Expert opinion on the validity of the claim" data-lang-it="Parere esperto sulla validità del reclamo">Odborny nazor na opravnenost reklamace</li>
          <li data-lang-cs="Cenovou kalkulaci opravy pred zahajenim praci" data-lang-en="Repair price calculation before starting work" data-lang-it="Calcolo del prezzo di riparazione prima dell'inizio dei lavori">Cenovou kalkulaci opravy pred zahajenim praci</li>
          <li data-lang-cs="Profesionalni opravu za fer cenu" data-lang-en="Professional repair at a fair price" data-lang-it="Riparazione professionale a un prezzo equo">Profesionalni opravu za fer cenu</li>
          <li data-lang-cs="12mesicni zaruku na vsechny opravy" data-lang-en="12-month warranty on all repairs" data-lang-it="Garanzia di 12 mesi su tutte le riparazioni">12mesicni zaruku na vsechny opravy</li>
        </ul>
      </div>

    </div>

    <!-- POSTUP KROK ZA KROKEM -->
    <div class="process-section">
      <h2 class="section-title"
          data-lang-cs="Jak postupovat pri zamitnute reklamaci"
          data-lang-en="How to Proceed with a Rejected Claim"
          data-lang-it="Come Procedere con un Reclamo Rifiutato">Jak postupovat pri zamitnute reklamaci</h2>

      <div class="process-steps">
        <div class="step">
          <div class="step-number">1</div>
          <h3 data-lang-cs="Zdokumentujte stav" data-lang-en="Document the Condition" data-lang-it="Documenta le Condizioni">Zdokumentujte stav</h3>
          <p data-lang-cs="Nafotte poskozeni z vice uhlu. Zaznamenejte kdy a jak k poskozeni doslo. Uschovejte doklad o koupi a pisemne zamitnuti reklamace." data-lang-en="Photograph the damage from multiple angles. Record when and how the damage occurred. Keep the proof of purchase and written rejection of the claim." data-lang-it="Fotografa il danno da più angolazioni. Registra quando e come si è verificato il danno. Conserva la prova d'acquisto e il rifiuto scritto del reclamo.">
            Nafotte poskozeni z vice uhlu. Zaznamenejte kdy a jak k poskozeni doslo. Uschovejte doklad o koupi a pisemne zamitnuti reklamace.
          </p>
        </div>

        <div class="step">
          <div class="step-number">2</div>
          <h3 data-lang-cs="Pozadejte o prezkum" data-lang-en="Request a Review" data-lang-it="Richiedi una Revisione">Pozadejte o prezkum</h3>
          <p data-lang-cs="Pisemne pozadejte prodejce o prezkum rozhodnuti. Uvedte duvody, proc s rozhodnutim nesouhlasíte. Prodejce ma povinnost se vyjadrit." data-lang-en="Request in writing from the seller to review the decision. State why you disagree with the decision. The seller is obliged to respond." data-lang-it="Richiedi per iscritto al venditore di rivedere la decisione. Indica i motivi per cui non sei d'accordo con la decisione. Il venditore è obbligato a rispondere.">
            Pisemne pozadejte prodejce o prezkum rozhodnuti. Uvedte duvody, proc s rozhodnutim nesouhlasíte. Prodejce ma povinnost se vyjadrit.
          </p>
        </div>

        <div class="step">
          <div class="step-number">3</div>
          <h3 data-lang-cs="Zvazte dalsi kroky" data-lang-en="Consider Next Steps" data-lang-it="Considera i Prossimi Passi">Zvazte dalsi kroky</h3>
          <p data-lang-cs="Pokud prodejce trva na zamitnuti, muzete se obratit na COI, zadat znalecky posudek nebo zvazit mimosoudni/soudni reseni. Nebo si nechte nabytek opravit u nas." data-lang-en="If the seller insists on the rejection, you can contact COI, request an expert assessment or consider out-of-court/court resolution. Or have your furniture repaired by us." data-lang-it="Se il venditore insiste sul rifiuto, puoi contattare COI, richiedere una perizia o considerare una risoluzione extragiudiziale/giudiziale. Oppure fai riparare i tuoi mobili da noi.">
            Pokud prodejce trva na zamitnuti, muzete se obratit na COI, zadat znalecky posudek nebo zvazit mimosoudni/soudni reseni. Nebo si nechte nabytek opravit u nas.
          </p>
        </div>
      </div>
    </div>

    <!-- FAQ SEKCE -->
    <div class="faq-section">
      <h2 class="section-title"
          data-lang-cs="Caste dotazy k neuznane reklamaci"
          data-lang-en="Frequently Asked Questions About Rejected Claims"
          data-lang-it="Domande Frequenti sui Reclami Rifiutati">Caste dotazy k neuznane reklamaci</h2>

      <div class="faq-list">
        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Co delat kdyz mi neuznali reklamaci sedacky?"
              data-lang-en="What to do when my sofa warranty claim was rejected?"
              data-lang-it="Cosa fare quando il reclamo in garanzia per il divano è stato rifiutato?">Co delat kdyz mi neuznali reklamaci sedacky?</h3>
          <p class="faq-answer"
             data-lang-cs="Pokud vam byla reklamace zamitnuta, mate vice moznosti: 1) Pozadat o prezkum ci nezavisle posouzeni, 2) Obratit se na Ceskou obchodni inspekci, 3) Nechat si nabytek opravit u nas za fer cenu. Poradime vam s dalsim postupem."
             data-lang-en="If your warranty claim was rejected, you have several options: 1) Request a review or independent assessment, 2) Contact the Czech Trade Inspection, 3) Have your furniture repaired by us at a fair price. We will advise you on the next steps."
             data-lang-it="Se il tuo reclamo in garanzia è stato rifiutato, hai diverse opzioni: 1) Richiedere una revisione o valutazione indipendente, 2) Contattare l'Ispezione del Commercio Ceca, 3) Far riparare i tuoi mobili da noi a un prezzo equo. Ti consiglieremo sui prossimi passi.">
            Pokud vam byla reklamace zamitnuta, mate vice moznosti: 1) Pozadat o prezkum ci nezavisle posouzeni, 2) Obratit se na Ceskou obchodni inspekci, 3) Nechat si nabytek opravit u nas za fer cenu. Poradime vam s dalsim postupem.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Muzete posoudit opravnenost zamitnute reklamace?"
              data-lang-en="Can you assess the validity of a rejected claim?"
              data-lang-it="Potete valutare la validità di un reclamo rifiutato?">Muzete posoudit opravnenost zamitnute reklamace?</h3>
          <p class="faq-answer"
             data-lang-cs="Ano, nabizime odborne posouzeni stavu nabytku. Na zaklade prohlidky vam sdelime, zda bylo zamitnuti reklamace opravnene a jake mate moznosti. Posouzeni stoji 110 EUR."
             data-lang-en="Yes, we offer an expert assessment of the furniture condition. Based on the inspection, we will tell you whether the rejection was justified and what your options are. The assessment costs 110 EUR."
             data-lang-it="Sì, offriamo una valutazione esperta delle condizioni dei mobili. Sulla base dell'ispezione, vi diremo se il rifiuto era giustificato e quali sono le vostre opzioni. La valutazione costa 110 EUR.">
            Ano, nabizime odborne posouzeni stavu nabytku. Na zaklade prohlidky vam sdelime, zda bylo zamitnuti reklamace opravnene a jake mate moznosti. Posouzeni stoji 110 EUR.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Kolik stoji oprava po neuznane reklamaci?"
              data-lang-en="How much does repair cost after a rejected claim?"
              data-lang-it="Quanto costa la riparazione dopo un reclamo rifiutato?">Kolik stoji oprava po neuznane reklamaci?</h3>
          <p class="faq-answer"
             data-lang-cs="Ceny oprav zacinaji od 205 EUR za praci. Nabizime fer ceny a kvalitni provedeni. Pred opravou vzdy obdrzite presnou kalkulaci, aby vas cena neprekvapila."
             data-lang-en="Repair prices start from 205 EUR for labor. We offer fair prices and quality workmanship. Before repair, you will always receive an exact calculation so the price doesn't surprise you."
             data-lang-it="I prezzi di riparazione partono da 205 EUR per la manodopera. Offriamo prezzi equi e lavorazione di qualità. Prima della riparazione, riceverai sempre un calcolo esatto in modo che il prezzo non ti sorprenda.">
            Ceny oprav zacinaji od 205 EUR za praci. Nabizime fer ceny a kvalitni provedeni. Pred opravou vzdy obdrzite presnou kalkulaci, aby vas cena neprekvapila.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Jak postupovat pri odvolani proti zamitnute reklamaci?"
              data-lang-en="How to proceed with an appeal against a rejected claim?"
              data-lang-it="Come procedere con un ricorso contro un reclamo rifiutato?">Jak postupovat pri odvolani proti zamitnute reklamaci?</h3>
          <p class="faq-answer"
             data-lang-cs="Doporucujeme: 1) Zdokumentovat stav nabytku fotografiemi, 2) Pisemne pozadat prodejce o prezkum, 3) Pripadne se obratit na COI nebo soudniho znalce. Muzeme vam pripravit odborny posudek."
             data-lang-en="We recommend: 1) Document the condition of the furniture with photos, 2) Request in writing from the seller for review, 3) Possibly contact COI or a court expert. We can prepare an expert assessment for you."
             data-lang-it="Consigliamo: 1) Documentare le condizioni dei mobili con foto, 2) Richiedere per iscritto al venditore una revisione, 3) Eventualmente contattare COI o un perito. Possiamo preparare una valutazione esperta per voi.">
            Doporucujeme: 1) Zdokumentovat stav nabytku fotografiemi, 2) Pisemne pozadat prodejce o prezkum, 3) Pripadne se obratit na COI nebo soudniho znalce. Muzeme vam pripravit odborny posudek.
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
        data-lang-cs="Potrebujete pomoc s neuznanou reklamaci?"
        data-lang-en="Need Help with a Rejected Claim?"
        data-lang-it="Hai Bisogno di Aiuto con un Reclamo Rifiutato?">Potrebujete pomoc s neuznanou reklamaci?</h2>
    <p class="cta-text"
       data-lang-cs="Kontaktujte nas pro nezavisly posudek nebo cenovou nabidku na opravu. Pomozteme vam najit nejlepsi reseni pro vas nabytek."
       data-lang-en="Contact us for an independent assessment or repair quote. We will help you find the best solution for your furniture."
       data-lang-it="Contattaci per una valutazione indipendente o un preventivo di riparazione. Ti aiuteremo a trovare la soluzione migliore per i tuoi mobili.">
      Kontaktujte nas pro nezavisly posudek nebo cenovou nabidku na opravu. Pomozteme vam najit nejlepsi reseni pro vas nabytek.
    </p>
    <a href="novareklamace.php" class="cta-button"
       data-lang-cs="Kontaktovat servis"
       data-lang-en="Contact Service"
       data-lang-it="Contatta il Servizio">Kontaktovat servis</a>
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
      <p data-lang-cs="© 2025 White Glove Service. Vsechna prava vyhrazena."
         data-lang-en="© 2025 White Glove Service. All rights reserved."
         data-lang-it="© 2025 White Glove Service. Tutti i diritti riservati.">
        &copy; 2025 White Glove Service. Vsechna prava vyhrazena.
        <span aria-hidden="true"> • </span>
        <a href="gdpr.php" class="footer-link"
           data-lang-cs="Zpracovani osobnich udaju (GDPR)"
           data-lang-en="Personal data processing (GDPR)"
           data-lang-it="Trattamento dei dati personali (GDPR)">Zpracovani osobnich udaju (GDPR)</a>
      </p>
    </div>
  </div>
</footer>

<script src="assets/js/logger.min.js" defer></script>

<?php if (function_exists('renderHeatmapTracker')) renderHeatmapTracker(); ?>
</body>
</html>
