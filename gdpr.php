<?php require_once "init.php"; ?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#000000">
  <meta name="description" content="Zpracování osobních údajů ve společnosti White Glove Service, s.r.o. Informace o účelech, právním základu, době uchování a právech subjektů údajů.">
  <title>GDPR | White Glove Service</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/styles.min.css">
  <style>
    .gdpr-hero {
      background: linear-gradient(135deg, #000 0%, #1f2937 100%);
      color: #fff;
      padding: 6rem 2rem 4rem;
      text-align: center;
    }

    .gdpr-hero h1 {
      font-size: clamp(1.9rem, 3.4vw, 2.6rem);
      letter-spacing: 0.1em;
      text-transform: uppercase;
      margin-bottom: 1rem;
      color: #fff !important;
    }

    .gdpr-hero p {
      font-size: 1.1rem;
      max-width: 720px;
      margin: 0 auto;
      opacity: 0.85;
      color: #fff !important;
    }

    .gdpr-content {
      padding: 3rem 1.5rem 4rem;
      background: #f9fafb;
    }

    .gdpr-container {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr;
      gap: 2rem;
    }

    /* Na střední obrazovkách 2 sloupce */
    @media (min-width: 768px) {
      .gdpr-container {
        grid-template-columns: repeat(2, 1fr);
      }

      /* První karta přes celou šířku */
      .gdpr-card:first-child {
        grid-column: 1 / -1;
      }
    }

    .gdpr-card {
      background: #fff;
      border-radius: 16px;
      padding: 2.5rem;
      box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
      border: 1px solid rgba(148, 163, 184, 0.15);
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
    }

    .gdpr-card h2 {
      font-size: 1.4rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin-bottom: 1.25rem;
      color: #111827;
      flex-shrink: 0;
    }

    .gdpr-card p,
    .gdpr-card li {
      font-size: 0.98rem;
      line-height: 1.75;
      color: #374151;
    }

    .gdpr-card p {
      margin-bottom: 1rem;
    }

    .gdpr-card p:last-child {
      margin-bottom: 0;
    }

    .gdpr-card ul {
      list-style: disc;
      padding-left: 1.5rem;
      margin: 0;
      display: grid;
      gap: 0.75rem;
      flex: 1;
    }

    .gdpr-card li {
      margin: 0;
    }

    .gdpr-highlight {
      border-left: 4px solid #111827;
      padding: 1.25rem 1.5rem;
      margin: 0;
      background: linear-gradient(135deg, rgba(17, 24, 39, 0.08) 0%, rgba(17, 24, 39, 0.02) 100%);
      border-radius: 12px;
    }

    .gdpr-meta {
      text-align: center;
      font-size: 0.85rem;
      color: #6b7280;
      margin-top: 2rem;
    }

    .gdpr-link {
      color: #111827;
      font-weight: 600;
      text-decoration: none;
      border-bottom: 1px solid rgba(17, 24, 39, 0.35);
      transition: color 0.2s ease, border-color 0.2s ease;
    }

    .gdpr-link:hover {
      color: #000000;
      border-color: #000000;
    }

    .gdpr-card .gdpr-link {
      align-self: flex-start;
    }

    .gdpr-contact-callout {
      border-radius: 12px;
      border: 1px solid rgba(17, 24, 39, 0.08);
      background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
      padding: 1.25rem 1.5rem;
    }

    .gdpr-contact-callout strong {
      display: block;
      font-size: 0.95rem;
      letter-spacing: 0.04em;
      color: #111827;
      margin-bottom: 0.35rem;
    }

    .gdpr-contact-callout span {
      display: block;
    }

    @media (max-width: 768px) {
      .gdpr-card {
        padding: 1.75rem;
      }

      .gdpr-card h2 {
        font-size: 1.2rem;
      }

      .gdpr-highlight {
        padding: 1rem 1.25rem;
      }

      .gdpr-hero {
        padding: 5rem 1.5rem 3rem;
      }
    }
  </style>
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<main>
  <section class="gdpr-hero">
    <h1>Zpracování osobních údajů (GDPR)</h1>
    <p>Transparentně vysvětlujeme, jaké informace při poskytování servisu shromažďujeme, proč je potřebujeme a jak chráníme vaše práva.</p>
  </section>

  <section class="gdpr-content">
    <div class="gdpr-container">
      <article class="gdpr-card">
        <h2>Správce údajů</h2>
        <p class="gdpr-highlight">
          White Glove Service, s.r.o., Do Dubče 364, 190 11 Praha 9 – Běchovice, IČ: 177 51 781<br>
          E-mail: <a href="mailto:reklamace@wgs-service.cz" class="gdpr-link">reklamace@wgs-service.cz</a>, Tel.: <a href="tel:+420725965826" class="gdpr-link">+420 725 965 826</a>
        </p>
        <p>Správce zajišťuje servisní služby značky Natuzzi a dalších prémiových výrobců nábytku v České republice a na Slovensku.</p>
      </article>

      <article class="gdpr-card">
        <h2>Jaké údaje zpracováváme</h2>
        <ul>
          <li>Identifikační údaje: jméno, případně název společnosti a fakturační údaje.</li>
          <li>Kontaktní údaje: e-mail, telefon, doručovací adresa, preferovaný jazyk komunikace.</li>
          <li>Údaje o zakázce: číslo reklamace/servisu, datum nákupu, popis závady, fotodokumentace a čísla modelů.</li>
          <li>Provozní metadata: IP adresa, čas odeslání formuláře a použité zařízení pro prokázání uděleného souhlasu.</li>
        </ul>
      </article>

      <article class="gdpr-card">
        <h2>Účely a právní důvody</h2>
        <ul>
          <li>Vyřízení servisní zakázky, reklamace nebo technické konzultace (plnění smlouvy).</li>
          <li>Komunikace s výrobcem, prodejcem nebo importérem nutná k vyřízení požadavku (oprávněný zájem).</li>
          <li>Evidence plnění, fakturace a účetnictví (právní povinnost).</li>
          <li>Uchování souhlasu uděleného ve formuláři <em>Objednat servis</em> pro doložení splnění povinností dle GDPR.</li>
        </ul>
      </article>

      <article class="gdpr-card">
        <h2>Doba uchování</h2>
        <p>Osobní údaje spojené s konkrétní zakázkou uchováváme po dobu nezbytnou k jejímu vyřízení a následně po dobu zákonných lhůt (obvykle 5 let) pro případné reklamace, účetní a daňové kontroly. Fotodokumentace je uchovávána maximálně po dobu řešení závady, nejdéle 24 měsíců, pokud právní předpisy nestanoví jinak.</p>
      </article>

      <article class="gdpr-card">
        <h2>Příjemci osobních údajů</h2>
        <ul>
          <li>Výrobce nábytku a jeho oficiální importéři, pokud je nutné autorizovat zásah nebo dodat náhradní díly.</li>
          <li>Smluvní technici a servisní partneři, kteří provádějí opravu u vás doma.</li>
          <li>Účetní a daňoví poradci při zpracování fakturace a legislativních povinností.</li>
          <li>IT poskytovatelé hostingu a bezpečnostních služeb, kteří zajišťují provoz informačních systémů.</li>
        </ul>
      </article>

      <article class="gdpr-card">
        <h2>Vaše práva</h2>
        <ul>
          <li>Požadovat přístup k osobním údajům a získat kopii ve strojově čitelné podobě.</li>
          <li>Požádat o opravu nepřesných nebo neaktuálních údajů.</li>
          <li>Vznést námitku proti zpracování nebo požádat o omezení zpracování, pokud jsou splněny zákonné podmínky.</li>
          <li>Požádat o výmaz údajů, jakmile pominou důvody jejich zpracování.</li>
          <li>Odvolat souhlas poskytnutý ve formuláři <em>Objednat servis</em>.</li>
          <li>Kontaktovat nás na <a href="mailto:reklamace@wgs-service.cz" class="gdpr-link">reklamace@wgs-service.cz</a> pro odvolání souhlasu nebo jakékoli dotazy.</li>
          <li>Podat stížnost u dozorového úřadu: Úřad pro ochranu osobních údajů, Pplk. Sochora 27, 170 00 Praha 7.</li>
        </ul>
      </article>

      <article class="gdpr-card">
        <h2>Jak nás kontaktovat</h2>
        <div class="gdpr-contact-callout">
          <strong>Potřebujete něco upřesnit?</strong>
          <span>Napište na <a href="mailto:reklamace@wgs-service.cz" class="gdpr-link">reklamace@wgs-service.cz</a> nebo volejte <a href="tel:+420725965826" class="gdpr-link">+420 725 965 826</a>.</span>
          <span>Vaše požadavky vyřizujeme bez zbytečného odkladu, nejpozději do 30 dnů.</span>
        </div>
        <p class="gdpr-meta">Poslední aktualizace: <?php echo date('d.m.Y'); ?></p>
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
        <a href="gdpr.php" class="footer-link">Zpracování osobních údajů (GDPR)</a>
      </p>
    </div>
  </div>
</footer>

<script src="assets/js/logger.js" defer></script>
</body>
</html>
