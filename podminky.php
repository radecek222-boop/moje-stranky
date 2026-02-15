<?php require_once "init.php"; ?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="theme-color" content="#000000">
  <meta name="description" content="Obchodní podmínky společnosti White Glove Service pro poskytování servisních služeb prémiového nábytku Natuzzi.">
  <title>Obchodní podmínky | White Glove Service</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/styles.min.css">
  <style>
    .podminky-hero {
      background: linear-gradient(135deg, #000 0%, #1f2937 100%);
      color: #fff;
      padding: 6rem 2rem 4rem;
      text-align: center;
    }

    .podminky-hero h1 {
      font-size: clamp(1.8rem, 3.2vw, 2.6rem);
      letter-spacing: 0.12em;
      text-transform: uppercase;
      margin-bottom: 1rem;
      color: #fff !important;
    }

    .podminky-hero p {
      font-size: 1.1rem;
      max-width: 720px;
      margin: 0 auto;
      opacity: 0.85;
      color: #fff !important;
    }

    .podminky-content {
      padding: 3rem 1.5rem 4rem;
      background: #f9fafb;
    }

    .podminky-container {
      max-width: 900px;
      margin: 0 auto;
    }

    .podminky-card {
      background: #fff;
      border-radius: 16px;
      padding: 2.5rem;
      margin-bottom: 2rem;
      box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
      border: 1px solid rgba(148, 163, 184, 0.15);
    }

    .podminky-card:last-child {
      margin-bottom: 0;
    }

    .podminky-card h2 {
      font-size: 1.3rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin-bottom: 1.25rem;
      color: #111827;
      border-bottom: 2px solid #111827;
      padding-bottom: 0.75rem;
    }

    .podminky-card h3 {
      font-size: 1rem;
      font-weight: 600;
      color: #374151;
      margin: 1.5rem 0 0.75rem 0;
    }

    .podminky-card p,
    .podminky-card li {
      font-size: 0.95rem;
      line-height: 1.8;
      color: #374151;
    }

    .podminky-card p {
      margin-bottom: 1rem;
    }

    .podminky-card ol,
    .podminky-card ul {
      margin: 0 0 1rem 0;
      padding-left: 1.5rem;
    }

    .podminky-card li {
      margin-bottom: 0.5rem;
    }

    .podminky-card strong {
      color: #111827;
    }

    .podminky-link {
      color: #111827;
      font-weight: 600;
      text-decoration: none;
      border-bottom: 1px solid rgba(17, 24, 39, 0.35);
      transition: color 0.2s ease, border-color 0.2s ease;
    }

    .podminky-link:hover {
      color: #000000;
      border-color: #000000;
    }

    .podminky-highlight {
      border-left: 4px solid #111827;
      padding: 1rem 1.5rem;
      margin: 1rem 0;
      background: linear-gradient(135deg, rgba(17, 24, 39, 0.08) 0%, rgba(17, 24, 39, 0.02) 100%);
      border-radius: 0 12px 12px 0;
    }

    .podminky-meta {
      text-align: center;
      font-size: 0.85rem;
      color: #6b7280;
      margin-top: 2rem;
    }

    @media (max-width: 768px) {
      .podminky-card {
        padding: 1.75rem;
      }

      .podminky-card h2 {
        font-size: 1.15rem;
      }

      .podminky-hero {
        padding: 5rem 1.5rem 3rem;
      }
    }
  </style>

  <!-- Analytics Tracker -->
  <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<main id="main-content">
  <section class="podminky-hero">
    <h1>Obchodní podmínky</h1>
    <p>Podmínky poskytování servisních služeb společnosti White Glove Service, s.r.o.</p>
  </section>

  <section class="podminky-content">
    <div class="podminky-container">

      <article class="podminky-card">
        <h2>1. Základní ustanovení</h2>
        <p>
          Tyto obchodní podmínky upravují vztahy mezi společností <strong>White Glove Service, s.r.o.</strong>,
          IČ: 177 51 781, se sídlem Do Dubče 364, 190 11 Praha 9 – Běchovice (dále jen „Poskytovatel")
          a zákazníky využívajícími servisní služby (dále jen „Zákazník").
        </p>
        <p>
          Poskytovatel zajišťuje specializované servisní služby pro prémiový nábytek značky Natuzzi
          a dalších prémiových výrobců na území České republiky a Slovenské republiky.
        </p>
      </article>

      <article class="podminky-card">
        <h2>2. Objednávka služeb</h2>

        <h3>2.1 Způsob objednání</h3>
        <p>Servisní služby lze objednat:</p>
        <ul>
          <li>Prostřednictvím online formuláře na webových stránkách <a href="novareklamace.php" class="podminky-link">Objednat servis</a></li>
          <li>E-mailem na adrese <a href="mailto:reklamace@wgs-service.cz" class="podminky-link">reklamace@wgs-service.cz</a></li>
          <li>Telefonicky na čísle <a href="tel:+420725965826" class="podminky-link">+420 725 965 826</a></li>
        </ul>

        <h3>2.2 Náležitosti objednávky</h3>
        <p>Objednávka musí obsahovat:</p>
        <ul>
          <li>Identifikační údaje zákazníka (jméno, kontaktní údaje, adresa)</li>
          <li>Popis závady nebo požadované služby</li>
          <li>Informace o produktu (model, rok nákupu, číslo objednávky)</li>
          <li>Fotodokumentaci závady (je-li relevantní)</li>
        </ul>

        <h3>2.3 Potvrzení objednávky</h3>
        <p>
          Po přijetí objednávky Poskytovatel kontaktuje Zákazníka do 2 pracovních dnů
          pro upřesnění detailů a domluvení termínu servisního zásahu.
        </p>

        <h3>2.4 Cenová nabídka</h3>
        <p>
          Poskytovatel může Zákazníkovi zaslat cenovou nabídku elektronicky na uvedenou e-mailovou adresu.
          Cenová nabídka obsahuje specifikaci služeb, cenu bez DPH a dobu platnosti (standardně 30 dní).
        </p>
        <p>
          <strong>Potvrzením cenové nabídky</strong> (kliknutím na tlačítko "Potvrdit nabídku" v zaslaném e-mailu)
          Zákazník vyjadřuje souhlas s nabídkou a <strong>uzavírá tím závaznou smlouvu o dílo</strong>
          s Poskytovatelem dle § 2586 a násl. občanského zákoníku.
        </p>
        <p>
          Elektronické potvrzení cenové nabídky má stejné právní účinky jako písemně podepsaná smlouva
          ve smyslu § 562 občanského zákoníku. Zákazník bere na vědomí, že po potvrzení nabídky
          je smlouva závazná a zavazuje se k úhradě ceny uvedené v nabídce.
        </p>
      </article>

      <article class="podminky-card">
        <h2>3. Ceny a platební podmínky</h2>

        <h3>3.1 Ceník služeb</h3>
        <p>
          Aktuální ceník servisních služeb je dostupný na stránce
          <a href="cenik.php" class="podminky-link">Ceník</a>.
          Ceny jsou uvedeny bez DPH.
        </p>

        <h3>3.2 Záruční opravy</h3>
        <p>
          Záruční opravy jsou prováděny bezplatně po předložení dokladu o koupi.
          Záruka se nevztahuje na poškození způsobené nesprávným užíváním,
          mechanickým poškozením nebo zásahem třetí strany.
        </p>

        <h3>3.3 Pozáruční opravy</h3>
        <p>
          U pozáručních oprav je Zákazník informován o předběžné ceně před zahájením opravy.
          Konečná cena může být upravena na základě skutečného rozsahu práce.
        </p>

        <h3>3.4 Platba</h3>
        <p>Platbu lze provést:</p>
        <ul>
          <li>Hotově při převzetí opravy</li>
          <li>Bankovním převodem na základě vystavené faktury</li>
          <li>QR kódem</li>
        </ul>
      </article>

      <article class="podminky-card">
        <h2>4. Realizace služeb</h2>

        <h3>4.1 Servis u zákazníka</h3>
        <p>
          Většina servisních zásahů je prováděna přímo u Zákazníka.
          Technik se dostaví v dohodnutém termínu.
          Zákazník je povinen zajistit přístup k nábytku a přítomnost dospělé osoby.
        </p>

        <h3>4.2 Dílenská oprava</h3>
        <p>
          V případě nutnosti dílenské opravy zajistí Poskytovatel odvoz a dovoz nábytku.
          Cena dopravy je účtována dle ceníku nebo individuální dohody.
        </p>

        <h3>4.3 Dodací lhůty</h3>
        <p>
          Standardní lhůta pro provedení servisního zásahu je 14–30 pracovních dnů od potvrzení objednávky.
          U složitějších oprav nebo při nutnosti objednání náhradních dílů může být lhůta delší.
        </p>
      </article>

      <article class="podminky-card">
        <h2>5. Reklamace a záruka na služby</h2>

        <h3>5.1 Záruka na provedené práce</h3>
        <p>
          Na provedené servisní práce poskytuje Poskytovatel záruku 6 měsíců od dokončení opravy.
          Záruka se vztahuje na kvalitu provedených prací, nikoliv na další opotřebení nebo nové závady.
        </p>

        <h3>5.2 Uplatnění reklamace</h3>
        <p>
          Reklamaci je třeba uplatnit bez zbytečného odkladu po zjištění vady,
          nejpozději do konce záruční doby. Reklamaci lze podat:
        </p>
        <ul>
          <li>E-mailem na <a href="mailto:reklamace@wgs-service.cz" class="podminky-link">reklamace@wgs-service.cz</a></li>
          <li>Telefonicky na <a href="tel:+420725965826" class="podminky-link">+420 725 965 826</a></li>
        </ul>

        <h3>5.3 Vyřízení reklamace</h3>
        <p>
          Poskytovatel vyřídí reklamaci bez zbytečného odkladu, nejpozději do 30 dnů od jejího uplatnění.
        </p>
      </article>

      <article class="podminky-card">
        <h2>6. Odpovědnost</h2>

        <h3>6.1 Odpovědnost Poskytovatele</h3>
        <p>
          Poskytovatel odpovídá za škody způsobené při provádění servisních služeb
          v rozsahu stanoveném platnými právními předpisy.
          Maximální výše náhrady škody je omezena na hodnotu poskytnuté služby.
        </p>

        <h3>6.2 Vyloučení odpovědnosti</h3>
        <p>Poskytovatel neodpovídá za:</p>
        <ul>
          <li>Škody způsobené nesprávnými informacemi poskytnutými Zákazníkem</li>
          <li>Škody vzniklé v důsledku vyšší moci</li>
          <li>Nepřímé škody nebo ušlý zisk</li>
          <li>Škody na majetku nesouvisejícím s prováděnou opravou</li>
        </ul>
      </article>

      <article class="podminky-card">
        <h2>7. Ochrana osobních údajů</h2>
        <p>
          Zpracování osobních údajů se řídí
          <a href="gdpr.php" class="podminky-link">Zásadami ochrany osobních údajů (GDPR)</a>.
        </p>
        <p class="podminky-highlight">
          Odesláním objednávky Zákazník potvrzuje, že se seznámil s těmito obchodními podmínkami
          a zásadami ochrany osobních údajů a souhlasí s nimi.
        </p>
      </article>

      <article class="podminky-card">
        <h2>8. Práva spotřebitele</h2>

        <h3>8.1 Odstoupení od smlouvy</h3>
        <p>
          Spotřebitel má právo odstoupit od smlouvy bez udání důvodu ve lhůtě 14 dnů
          od uzavření smlouvy, pokud služba dosud nebyla zahájena.
          Po zahájení služby právo na odstoupení zaniká.
        </p>

        <h3>8.2 Mimosoudní řešení sporů</h3>
        <p>
          V případě sporu má spotřebitel právo obrátit se na
          <strong>Českou obchodní inspekci</strong> (www.coi.cz) jako subjekt
          mimosoudního řešení spotřebitelských sporů.
        </p>
      </article>

      <article class="podminky-card">
        <h2>9. Závěrečná ustanovení</h2>
        <p>
          Tyto obchodní podmínky nabývají platnosti dnem jejich zveřejnění na webových stránkách.
          Poskytovatel si vyhrazuje právo tyto podmínky kdykoli změnit.
          Změny jsou účinné okamžikem jejich zveřejnění.
        </p>
        <p>
          Právní vztahy neupravené těmito podmínkami se řídí právním řádem České republiky,
          zejména zákonem č. 89/2012 Sb., občanský zákoník, a zákonem č. 634/1992 Sb., o ochraně spotřebitele.
        </p>
        <p class="podminky-meta">Platnost od: 1. ledna 2025 | Poslední aktualizace: <?php echo date('d.m.Y'); ?></p>
      </article>

    </div>
  </section>
</main>

<footer class="footer">
  <div class="footer-container">
    <div class="footer-grid">
      <div class="footer-column">
        <h2 class="footer-title">White Glove Service</h2>
        <p class="footer-text">Specializovaný servis Natuzzi.</p>
      </div>
      <div class="footer-column">
        <h2 class="footer-title">Kontakt</h2>
        <p class="footer-text">
          <strong>Tel:</strong> <a href="tel:+420725965826" class="footer-link">+420 725 965 826</a><br>
          <strong>Email:</strong> <a href="mailto:reklamace@wgs-service.cz" class="footer-link">reklamace@wgs-service.cz</a>
        </p>
      </div>
      <div class="footer-column">
        <h2 class="footer-title">Adresa</h2>
        <p class="footer-text">Do Dubče 364, Běchovice 190 11 CZ</p>
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
