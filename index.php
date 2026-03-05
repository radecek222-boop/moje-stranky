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

  <!-- Google Fonts - Natuzzi style - OPTIMIZED with font-display: optional -->

  <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="stylesheet" href="assets/css/page-transitions.min.css">
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/index.min.css">
  <link rel="stylesheet" href="assets/css/nasesluzby.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.min.css">

  <!-- Analytics Tracker -->
  <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
  <link rel="stylesheet" href="assets/css/poppins-font.css">
</head>

<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>


<!-- HERO SEKCE -->
<main id="main-content">
  <section class="hero">
    <div class="hero-content">
      <div class="hero-subtitle">White Glove Service</div>
      
      <p class="hero-description"
         data-lang-cs="Prémiový servis pro luxusní nábytek.<br>Profesionální montáž, údržba a opravy s maximální péčí o každý detail."
         data-lang-en="Premium service for luxury furniture.<br>Professional assembly, maintenance, and repairs with maximum attention to every detail."
         data-lang-it="Servizio premium per mobili di lusso.<br>Montaggio, manutenzione e riparazioni professionali con la massima cura per ogni dettaglio.">
        Prémiový servis pro luxusní nábytek.<br>Profesionální montáž, údržba a opravy s maximální péčí o každý detail.
      </p>
      
      <a href="novareklamace.php" class="cta-button"
         data-lang-cs="Objednat servis"
         data-lang-en="Order service"
         data-lang-it="Ordina assistenza">Objednat servis</a>
    </div>

    <!-- Background image je nyní v CSS (.hero background-image) -->
  </section>

<!-- SEKCE SLUŽEB -->
<section class="services-section">
  <div class="container">

    <div class="section-intro">
      <h2 class="section-title"
          data-lang-cs="SERVIS A OPRAVY LUXUSNÍHO NÁBYTKU"
          data-lang-en="LUXURY FURNITURE SERVICE AND REPAIRS"
          data-lang-it="ASSISTENZA E RIPARAZIONI DI MOBILI DI LUSSO">SERVIS A OPRAVY LUXUSNÍHO NÁBYTKU</h2>
      <p class="section-description"
         data-lang-cs="Jsme autorizovaný servisní partner značek Natuzzi Italia, Natuzzi Editions, Natuzzi Softaly a Phase."
         data-lang-en="We are an authorized service partner for Natuzzi Italia, Natuzzi Editions, Natuzzi Softaly and Phase."
         data-lang-it="Siamo un partner autorizzato per Natuzzi Italia, Natuzzi Editions, Natuzzi Softaly e Phase.">
        Jsme autorizovaný servisní partner značek Natuzzi Italia, Natuzzi Editions, Natuzzi Softaly a Phase.
      </p>
      <p class="section-description"
         data-lang-cs="Provádíme opravy, reklamace a montáž luxusního nábytku v Praze, Brně, Bratislavě a po celé ČR a SR."
         data-lang-en="We carry out repairs, complaints and installation of luxury furniture in Prague, Brno, Bratislava and throughout the Czech Republic and Slovakia."
         data-lang-it="Eseguiamo riparazioni, reclami e installazioni di mobili di lusso a Praga, Brno, Bratislava e in tutta la Repubblica Ceca e Slovacchia.">
        Provádíme opravy, reklamace a montáž luxusního nábytku v Praze, Brně, Bratislavě a po celé ČR a SR.
      </p>
    </div>

    <div class="services-grid">

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

<!-- B2B SEKCE -->
<section class="b2b-sekce">
  <div class="b2b-kontejner">

    <div class="b2b-uvod">
      <h2 class="b2b-titulek"
          data-lang-cs="Hledáte spolehlivého partnera pro reklamační servis?"
          data-lang-en="Looking for a reliable warranty service partner?"
          data-lang-it="Cercate un partner affidabile per il servizio di garanzia?">
        Hledáte spolehlivého partnera pro reklamační servis?
      </h2>
      <p class="b2b-popis"
         data-lang-cs="Spolupracujeme s prodejci, výrobci a dovozci čalouněného nábytku. Přebíráme reklamační agendu, zajišťujeme technické posudky a servisní zásahy po celé ČR a SR. Oslovte nás."
         data-lang-en="We cooperate with retailers, manufacturers and importers of upholstered furniture. We handle warranty agendas, provide technical assessments and service interventions throughout the Czech Republic and Slovakia. Contact us."
         data-lang-it="Collaboriamo con rivenditori, produttori e importatori di mobili imbottiti. Gestiamo le pratiche di garanzia, forniamo valutazioni tecniche e interventi di assistenza in tutta la Repubblica Ceca e Slovacchia. Contattateci.">
        Spolupracujeme s prodejci, výrobci a dovozci čalouněného nábytku. Přebíráme reklamační agendu, zajišťujeme technické posudky a servisní zásahy po celé ČR a SR. Oslovte nás.
      </p>
    </div>

    <form class="b2b-formular" id="b2bFormular">
      <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
      <!-- Honeypot proti spamu - roboti toto pole vyplní, lidé ne -->
      <input type="text" name="web" style="display:none;position:absolute;left:-9999px" tabindex="-1" autocomplete="off">
      <input type="hidden" name="cas_zobrazeni" value="<?php echo time(); ?>">

      <div class="b2b-radek">
        <div class="b2b-pole">
          <label for="b2bFirma"
                 data-lang-cs="Název firmy"
                 data-lang-en="Company name"
                 data-lang-it="Nome azienda">Název firmy</label>
          <input type="text" id="b2bFirma" name="firma" required
                 placeholder="např. Nábytek s.r.o."
                 data-placeholder-cs="např. Nábytek s.r.o."
                 data-placeholder-en="e.g. Furniture Ltd."
                 data-placeholder-it="es. Mobili s.r.l.">
        </div>
        <div class="b2b-pole">
          <label for="b2bKontakt"
                 data-lang-cs="Kontaktní osoba"
                 data-lang-en="Contact person"
                 data-lang-it="Persona di contatto">Kontaktní osoba</label>
          <input type="text" id="b2bKontakt" name="kontakt" required
                 placeholder="Jméno a příjmení"
                 data-placeholder-cs="Jméno a příjmení"
                 data-placeholder-en="First and last name"
                 data-placeholder-it="Nome e cognome">
        </div>
      </div>

      <div class="b2b-radek">
        <div class="b2b-pole">
          <label for="b2bEmail"
                 data-lang-cs="E-mail"
                 data-lang-en="E-mail"
                 data-lang-it="E-mail">E-mail</label>
          <input type="email" id="b2bEmail" name="email" required
                 placeholder="firma@example.cz"
                 data-placeholder-cs="firma@example.cz"
                 data-placeholder-en="company@example.com"
                 data-placeholder-it="azienda@example.it">
        </div>
        <div class="b2b-pole">
          <label for="b2bTelefon"
                 data-lang-cs="Telefon"
                 data-lang-en="Phone"
                 data-lang-it="Telefono">Telefon</label>
          <input type="tel" id="b2bTelefon" name="telefon"
                 placeholder="+420 123 456 789"
                 data-placeholder-cs="+420 123 456 789"
                 data-placeholder-en="+420 123 456 789"
                 data-placeholder-it="+420 123 456 789">
        </div>
      </div>

      <div class="b2b-pole b2b-pole--plna-sirka">
        <label for="b2bZprava"
               data-lang-cs="Stručný popis spolupráce"
               data-lang-en="Brief description of cooperation"
               data-lang-it="Breve descrizione della collaborazione">Stručný popis spolupráce</label>
        <textarea id="b2bZprava" name="zprava" rows="4" required
                  placeholder="Popište, jaký typ spolupráce hledáte – počet reklamací měsíčně, značky nábytku, oblast působení..."
                  data-placeholder-cs="Popište, jaký typ spolupráce hledáte – počet reklamací měsíčně, značky nábytku, oblast působení..."
                  data-placeholder-en="Describe what type of cooperation you are looking for – number of complaints per month, furniture brands, area of operation..."
                  data-placeholder-it="Descrivete il tipo di collaborazione che cercate – numero di reclami al mese, marchi di mobili, area operativa..."></textarea>
      </div>

      <div class="b2b-odeslat">
        <button type="submit" class="b2b-tlacitko"
                data-lang-cs="Odeslat poptávku"
                data-lang-en="Send inquiry"
                data-lang-it="Invia richiesta">Odeslat poptávku</button>
      </div>
    </form>

  </div>
</section>

</main>

<script src="assets/js/wgs-toast.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const formular = document.getElementById('b2bFormular');
  if (!formular) return;

  formular.addEventListener('submit', async function(e) {
    e.preventDefault();

    const tlacitko = formular.querySelector('.b2b-tlacitko');
    tlacitko.disabled = true;

    const formData = new FormData(formular);

    try {
      const odpoved = await fetch('/api/b2b-poptavka.php', {
        method: 'POST',
        body: formData
      });

      const vysledek = await odpoved.json();

      if (vysledek.status === 'success') {
        if (typeof WGSToast !== 'undefined') {
          WGSToast.zobrazit('Poptávka odeslána. Ozveme se vám brzy.', { titulek: 'WGS', trvani: 6000 });
        }
        formular.reset();
      } else {
        alert(vysledek.message || 'Chyba při odesílání. Zkuste to prosím znovu.');
      }
    } catch (chyba) {
      alert('Síťová chyba. Zkuste to prosím znovu.');
    } finally {
      tlacitko.disabled = false;
    }
  });
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<!-- External JavaScript -->
<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.min.js" defer></script>
<script src="assets/js/page-transitions.min.js" defer></script>

<!-- REMOVED: index.js - veškerá funkcionalita přesunuta do hamburger-menu.php a language-switcher.js -->

<!-- PWA Service Worker Registration -->
<script src="assets/js/sw-register.min.js"></script>

<?php require_once __DIR__ . '/includes/cookie_consent.php'; ?>
</body>
</html>
