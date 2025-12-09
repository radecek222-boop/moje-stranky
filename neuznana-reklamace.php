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
        data-lang-cs="Zamítnuta reklamace?"
        data-lang-en="Claim Not Covered?"
        data-lang-it="Reclamo Non Coperto?">Zamítnuta reklamace?</h1>
    <div class="hero-subtitle"
         data-lang-cs="Nabízíme cenově výhodnou opravu jako řešení"
         data-lang-en="We offer affordable repair as a solution"
         data-lang-it="Offriamo riparazione conveniente come soluzione">Nabízíme cenově výhodnou opravu jako řešení</div>
  </div>
</section>

<!-- CONTENT SEKCE -->
<section class="content-section">
  <div class="container">

    <div class="section-intro">
      <h2 class="section-title"
          data-lang-cs="Reklamace nebyla uznána? Máme řešení."
          data-lang-en="Claim not covered? We have a solution."
          data-lang-it="Reclamo non coperto? Abbiamo una soluzione.">Reklamace nebyla uznána? Máme řešení.</h2>

      <p class="section-text"
         data-lang-cs="Ne každé poškození nábytku spadá do záruky - opotřebení, mechanické poškození nebo nesprávné používání jsou běžné důvody zamítnutí. To ale neznamená, že váš nábytek nejde opravit. Nabízíme profesionální opravu za férovou cenu."
         data-lang-en="Not all furniture damage is covered by warranty - wear, mechanical damage or improper use are common reasons for rejection. But that doesn't mean your furniture can't be repaired. We offer professional repair at a fair price."
         data-lang-it="Non tutti i danni ai mobili sono coperti dalla garanzia - usura, danni meccanici o uso improprio sono motivi comuni di rifiuto. Ma ciò non significa che i vostri mobili non possano essere riparati. Offriamo riparazioni professionali a un prezzo equo.">
        Ne každé poškození nábytku spadá do záruky - opotřebení, mechanické poškození nebo nesprávné používání jsou běžné důvody zamítnutí. To ale neznamená, že váš nábytek nejde opravit. Nabízíme profesionální opravu za férovou cenu.
      </p>

      <p class="section-text"
         data-lang-cs="Jako autorizovaný servis Natuzzi s více než pětiletou zkušeností dokážeme opravit prakticky jakékoliv poškození. Používáme originální díly a postupy, takže výsledek je stejně kvalitní jako záruční oprava - jen si ji hradíte sami."
         data-lang-en="As an authorized Natuzzi service with more than five years of experience, we can repair virtually any damage. We use original parts and procedures, so the result is the same quality as warranty repair - you just pay for it yourself."
         data-lang-it="Come servizio Natuzzi autorizzato con più di cinque anni di esperienza, possiamo riparare praticamente qualsiasi danno. Utilizziamo ricambi e procedure originali, quindi il risultato è della stessa qualità della riparazione in garanzia - solo che la pagate voi.">
        Jako autorizovaný servis Natuzzi s více než pětiletou zkušeností dokážeme opravit prakticky jakékoliv poškození. Používáme originální díly a postupy, takže výsledek je stejně kvalitní jako záruční oprava - jen si ji hradíte sami.
      </p>
    </div>

    <!-- POSTUP SEKCE -->
    <div class="services-grid">

      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Běžné důvody zamítnutí"
            data-lang-en="Common Reasons for Rejection"
            data-lang-it="Motivi Comuni di Rifiuto">Běžné důvody zamítnutí</h3>
        <ul class="service-list">
          <li data-lang-cs="Mechanické poškození (pořezání, propálení, škrábance)" data-lang-en="Mechanical damage (cuts, burns, scratches)" data-lang-it="Danni meccanici (tagli, bruciature, graffi)">Mechanické poškození (pořezání, propálení, škrábance)</li>
          <li data-lang-cs="Běžné opotřebení materiálu" data-lang-en="Normal material wear" data-lang-it="Normale usura del materiale">Běžné opotřebení materiálu</li>
          <li data-lang-cs="Nesprávná údržba nebo používání" data-lang-en="Improper maintenance or use" data-lang-it="Manutenzione o uso improprio">Nesprávná údržba nebo používání</li>
          <li data-lang-cs="Poškození domácími mazlíčky" data-lang-en="Damage by pets" data-lang-it="Danni causati da animali domestici">Poškození domácími mazlíčky</li>
          <li data-lang-cs="Uplynutí záruční doby" data-lang-en="Warranty period expired" data-lang-it="Periodo di garanzia scaduto">Uplynutí záruční doby</li>
        </ul>
      </div>

      <div class="service-card">
        <h3 class="service-title"
            data-lang-cs="Co vám můžeme nabídnout"
            data-lang-en="What We Can Offer You"
            data-lang-it="Cosa Possiamo Offrirvi">Co vám můžeme nabídnout</h3>
        <ul class="service-list">
          <li data-lang-cs="Bezplatnou diagnostiku závady při návštěvě" data-lang-en="Free fault diagnosis during visit" data-lang-it="Diagnosi gratuita del guasto durante la visita">Bezplatnou diagnostiku závady při návštěvě</li>
          <li data-lang-cs="Cenovou kalkulaci opravy předem" data-lang-en="Repair price calculation in advance" data-lang-it="Calcolo del prezzo di riparazione in anticipo">Cenovou kalkulaci opravy předem</li>
          <li data-lang-cs="Originální díly Natuzzi" data-lang-en="Original Natuzzi parts" data-lang-it="Ricambi originali Natuzzi">Originální díly Natuzzi</li>
          <li data-lang-cs="Profesionální opravu za férovou cenu" data-lang-en="Professional repair at a fair price" data-lang-it="Riparazione professionale a un prezzo equo">Profesionální opravu za férovou cenu</li>
          <li data-lang-cs="12měsíční záruku na všechny opravy" data-lang-en="12-month warranty on all repairs" data-lang-it="Garanzia di 12 mesi su tutte le riparazioni">12měsíční záruku na všechny opravy</li>
        </ul>
      </div>

    </div>

    <!-- POSTUP KROK ZA KROKEM -->
    <div class="process-section">
      <h2 class="section-title"
          data-lang-cs="Jak to funguje"
          data-lang-en="How It Works"
          data-lang-it="Come Funziona">Jak to funguje</h2>

      <div class="process-steps">
        <div class="step">
          <div class="step-number">1</div>
          <h3 data-lang-cs="Kontaktujte nás" data-lang-en="Contact Us" data-lang-it="Contattaci">Kontaktujte nás</h3>
          <p data-lang-cs="Zavolejte nebo napište. Popište problém s nábytkem a pošlete fotografie. Domluvíme termín návštěvy u vás doma." data-lang-en="Call or write to us. Describe the furniture problem and send photos. We'll arrange a home visit." data-lang-it="Chiamaci o scrivici. Descrivi il problema con i mobili e invia foto. Organizzeremo una visita a domicilio.">
            Zavolejte nebo napište. Popište problém s nábytkem a pošlete fotografie. Domluvíme termín návštěvy u vás doma.
          </p>
        </div>

        <div class="step">
          <div class="step-number">2</div>
          <h3 data-lang-cs="Diagnostika a kalkulace" data-lang-en="Diagnosis and Quote" data-lang-it="Diagnosi e Preventivo">Diagnostika a kalkulace</h3>
          <p data-lang-cs="Technik posoudí stav nábytku přímo u vás. Na místě vám sdělíme přesnou cenu opravy. Bez překvapení, bez skrytých poplatků." data-lang-en="The technician will assess the furniture condition at your home. We'll give you the exact repair price on the spot. No surprises, no hidden fees." data-lang-it="Il tecnico valuterà le condizioni dei mobili a casa vostra. Vi daremo il prezzo esatto della riparazione sul posto. Nessuna sorpresa, nessun costo nascosto.">
            Technik posoudí stav nábytku přímo u vás. Na místě vám sdělíme přesnou cenu opravy. Bez překvapení, bez skrytých poplatků.
          </p>
        </div>

        <div class="step">
          <div class="step-number">3</div>
          <h3 data-lang-cs="Profesionální oprava" data-lang-en="Professional Repair" data-lang-it="Riparazione Professionale">Profesionální oprava</h3>
          <p data-lang-cs="Po vašem odsouhlasení provedeme opravu s originálními díly Natuzzi. Na práci poskytujeme 12měsíční záruku." data-lang-en="After your approval, we'll perform the repair using original Natuzzi parts. We provide a 12-month warranty on our work." data-lang-it="Dopo la vostra approvazione, eseguiremo la riparazione utilizzando ricambi originali Natuzzi. Forniamo una garanzia di 12 mesi sul nostro lavoro.">
            Po vašem odsouhlasení provedeme opravu s originálními díly Natuzzi. Na práci poskytujeme 12měsíční záruku.
          </p>
        </div>
      </div>
    </div>

    <!-- FAQ SEKCE -->
    <div class="faq-section">
      <h2 class="section-title"
          data-lang-cs="Časté dotazy"
          data-lang-en="Frequently Asked Questions"
          data-lang-it="Domande Frequenti">Časté dotazy</h2>

      <div class="faq-list">
        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Opravíte i nábytek po záruce?"
              data-lang-en="Do you repair furniture after warranty?"
              data-lang-it="Riparate mobili fuori garanzia?">Opravíte i nábytek po záruce?</h3>
          <p class="faq-answer"
             data-lang-cs="Ano, opravujeme nábytek bez ohledu na stav záruky. Ať už jde o mechanické poškození, opotřebení nebo závadu po záruce - dokážeme pomoci. Kontaktujte nás s popisem problému."
             data-lang-en="Yes, we repair furniture regardless of warranty status. Whether it's mechanical damage, wear or post-warranty defects - we can help. Contact us with a description of the problem."
             data-lang-it="Sì, ripariamo i mobili indipendentemente dallo stato della garanzia. Che si tratti di danni meccanici, usura o difetti post-garanzia - possiamo aiutare. Contattaci con una descrizione del problema.">
            Ano, opravujeme nábytek bez ohledu na stav záruky. Ať už jde o mechanické poškození, opotřebení nebo závadu po záruce - dokážeme pomoci. Kontaktujte nás s popisem problému.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Používáte originální díly?"
              data-lang-en="Do you use original parts?"
              data-lang-it="Utilizzate ricambi originali?">Používáte originální díly?</h3>
          <p class="faq-answer"
             data-lang-cs="Ano, jako autorizovaný servis Natuzzi máme přístup k originálním náhradním dílům přímo od výrobce. Díky tomu je oprava stejně kvalitní jako záruční."
             data-lang-en="Yes, as an authorized Natuzzi service, we have access to original spare parts directly from the manufacturer. This ensures the repair is the same quality as warranty work."
             data-lang-it="Sì, come servizio Natuzzi autorizzato, abbiamo accesso ai ricambi originali direttamente dal produttore. Questo garantisce che la riparazione sia della stessa qualità del lavoro in garanzia.">
            Ano, jako autorizovaný servis Natuzzi máme přístup k originálním náhradním dílům přímo od výrobce. Díky tomu je oprava stejně kvalitní jako záruční.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Kolik stojí placená oprava?"
              data-lang-en="How much does paid repair cost?"
              data-lang-it="Quanto costa la riparazione a pagamento?">Kolik stojí placená oprava?</h3>
          <p class="faq-answer"
             data-lang-cs="Ceny oprav začínají od 205 EUR za práci. Před opravou vždy obdržíte přesnou kalkulaci, aby vás cena nepřekvapila. Diagnostika při návštěvě je zdarma."
             data-lang-en="Repair prices start from 205 EUR for labor. Before repair, you will always receive an exact quote so the price doesn't surprise you. On-site diagnosis is free."
             data-lang-it="I prezzi di riparazione partono da 205 EUR per la manodopera. Prima della riparazione, riceverai sempre un preventivo esatto. La diagnosi in loco è gratuita.">
            Ceny oprav začínají od 205 EUR za práci. Před opravou vždy obdržíte přesnou kalkulaci, aby vás cena nepřekvapila. Diagnostika při návštěvě je zdarma.
          </p>
        </div>

        <div class="faq-item">
          <h3 class="faq-question"
              data-lang-cs="Jak dlouho trvá oprava?"
              data-lang-en="How long does repair take?"
              data-lang-it="Quanto tempo richiede la riparazione?">Jak dlouho trvá oprava?</h3>
          <p class="faq-answer"
             data-lang-cs="Většinu oprav provádíme přímo u vás doma během jedné návštěvy. Pokud je potřeba objednat náhradní díly z Itálie, dodací lhůta je obvykle 4-8 týdnů."
             data-lang-en="Most repairs are done at your home in a single visit. If spare parts need to be ordered from Italy, delivery time is usually 4-8 weeks."
             data-lang-it="La maggior parte delle riparazioni viene eseguita a casa vostra in un'unica visita. Se è necessario ordinare ricambi dall'Italia, i tempi di consegna sono di solito 4-8 settimane.">
            Většinu oprav provádíme přímo u vás doma během jedné návštěvy. Pokud je potřeba objednat náhradní díly z Itálie, dodací lhůta je obvykle 4-8 týdnů.
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
        data-lang-cs="Potřebujete opravit nábytek?"
        data-lang-en="Need Your Furniture Repaired?"
        data-lang-it="Hai Bisogno di Riparare i Mobili?">Potřebujete opravit nábytek?</h2>
    <p class="cta-text"
       data-lang-cs="Kontaktujte nás pro cenovou nabídku na opravu. Diagnostika při návštěvě je zdarma a cenu znáte předem."
       data-lang-en="Contact us for a repair quote. On-site diagnosis is free and you'll know the price upfront."
       data-lang-it="Contattaci per un preventivo di riparazione. La diagnosi in loco è gratuita e conoscerai il prezzo in anticipo.">
      Kontaktujte nás pro cenovou nabídku na opravu. Diagnostika při návštěvě je zdarma a cenu znáte předem.
    </p>
    <a href="novareklamace.php" class="cta-button"
       data-lang-cs="Objednat opravu"
       data-lang-en="Order Repair"
       data-lang-it="Ordina Riparazione">Objednat opravu</a>
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
