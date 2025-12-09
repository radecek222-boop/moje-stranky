<?php require_once "init.php"; ?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#000000">
  <meta name="description" content="Zásady používání cookies na webu White Glove Service. Informace o typech cookies, jejich účelu a možnostech nastavení.">
  <title>Cookies | White Glove Service</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/styles.min.css">
  <style>
    .cookies-hero {
      background: linear-gradient(135deg, #000 0%, #1f2937 100%);
      color: #fff;
      padding: 6rem 2rem 4rem;
      text-align: center;
    }

    .cookies-hero h1 {
      font-size: clamp(1.8rem, 3.2vw, 2.6rem);
      letter-spacing: 0.12em;
      text-transform: uppercase;
      margin-bottom: 1rem;
      color: #fff !important;
    }

    .cookies-hero p {
      font-size: 1.1rem;
      max-width: 720px;
      margin: 0 auto;
      opacity: 0.85;
      color: #fff !important;
    }

    .cookies-content {
      padding: 3rem 1.5rem 4rem;
      background: #f9fafb;
    }

    .cookies-container {
      max-width: 1200px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: 1fr;
      gap: 2rem;
    }

    @media (min-width: 768px) {
      .cookies-container {
        grid-template-columns: repeat(2, 1fr);
      }

      .cookies-card:first-child,
      .cookies-card.full-width {
        grid-column: 1 / -1;
      }
    }

    .cookies-card {
      background: #fff;
      border-radius: 16px;
      padding: 2.5rem;
      box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
      border: 1px solid rgba(148, 163, 184, 0.15);
      display: flex;
      flex-direction: column;
      gap: 1.25rem;
    }

    .cookies-card h2 {
      font-size: 1.4rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin-bottom: 1.25rem;
      color: #111827;
      flex-shrink: 0;
    }

    .cookies-card p,
    .cookies-card li {
      font-size: 0.98rem;
      line-height: 1.75;
      color: #374151;
    }

    .cookies-card p {
      margin-bottom: 1rem;
    }

    .cookies-card p:last-child {
      margin-bottom: 0;
    }

    .cookies-card ul {
      list-style: none;
      padding-left: 0;
      margin: 0;
      display: grid;
      gap: 1rem;
      flex: 1;
    }

    .cookies-card ul li {
      position: relative;
      padding-left: 1.75rem;
      line-height: 1.75;
    }

    .cookies-card ul li::before {
      content: "";
      color: #111827;
      font-weight: 600;
      position: absolute;
      left: 0;
      top: 0.55rem;
    }

    .cookies-card ul li strong {
      display: block;
      font-weight: 600;
      margin-bottom: 0.25rem;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      font-size: 0.8rem;
      color: #111827;
    }

    .cookies-link {
      color: #0f172a;
      font-weight: 600;
      text-decoration: underline;
      text-decoration-thickness: 0.08em;
      text-underline-offset: 4px;
    }

    .cookies-link:hover,
    .cookies-link:focus {
      color: #2563eb;
    }

    .cookies-table {
      width: 100%;
      border-collapse: collapse;
      margin: 1rem 0;
    }

    .cookies-table th,
    .cookies-table td {
      padding: 0.75rem 1rem;
      text-align: left;
      border-bottom: 1px solid #e5e7eb;
      font-size: 0.9rem;
    }

    .cookies-table th {
      background: #f3f4f6;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
      color: #374151;
    }

    .cookies-table tr:last-child td {
      border-bottom: none;
    }

    .cookies-highlight {
      border-left: 4px solid #111827;
      padding: 1.25rem 1.5rem;
      margin: 0;
      background: linear-gradient(135deg, rgba(17, 24, 39, 0.08) 0%, rgba(17, 24, 39, 0.02) 100%);
      border-radius: 12px;
    }

    .cookies-meta {
      text-align: center;
      font-size: 0.85rem;
      color: #6b7280;
      margin-top: 2rem;
    }

    .cookie-type-badge {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 4px;
      font-size: 0.7rem;
      font-weight: 600;
      text-transform: uppercase;
      letter-spacing: 0.03em;
    }

    .cookie-type-nezbytne {
      background: #111827;
      color: #fff;
    }

    .cookie-type-funkcni {
      background: #6b7280;
      color: #fff;
    }

    .cookie-type-analyticke {
      background: #9ca3af;
      color: #111827;
    }

    @media (max-width: 768px) {
      .cookies-card {
        padding: 1.75rem;
      }

      .cookies-card h2 {
        font-size: 1.2rem;
      }

      .cookies-highlight {
        padding: 1rem 1.25rem;
      }

      .cookies-hero {
        padding: 5rem 1.5rem 3rem;
      }

      .cookies-table {
        display: block;
        overflow-x: auto;
      }
    }
  </style>

  <!-- Analytics Tracker - pouze se souhlasem -->
  <?php if (isset($_COOKIE['wgs_analytics_consent']) && $_COOKIE['wgs_analytics_consent'] === '1'): ?>
    <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
  <?php endif; ?>
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<main id="main-content">
  <section class="cookies-hero">
    <h1>Zásady používání cookies</h1>
    <p>Informace o tom, jak používáme cookies a podobné technologie pro zajištění funkčnosti webu a zlepšení vašeho zážitku.</p>
  </section>

  <section class="cookies-content">
    <div class="cookies-container">
      <article class="cookies-card">
        <h2>Co jsou cookies?</h2>
        <p class="cookies-highlight">
          Cookies jsou malé textové soubory, které webové stránky ukládají do vašeho prohlížeče.
          Pomáhají nám zapamatovat si vaše preference, zajistit bezpečné přihlášení a pochopit,
          jak náš web používáte.
        </p>
        <p>
          Některé cookies jsou nezbytné pro fungování webu (např. přihlášení), jiné nám pomáhají
          web vylepšovat. Můžete si vybrat, které volitelné cookies povolíte.
        </p>
      </article>

      <article class="cookies-card">
        <h2>Nezbytné cookies</h2>
        <p><span class="cookie-type-badge cookie-type-nezbytne">Vždy aktivní</span></p>
        <p>Tyto cookies jsou nutné pro základní funkce webu a nelze je vypnout. Zahrnují:</p>
        <table class="cookies-table">
          <thead>
            <tr>
              <th>Cookie</th>
              <th>Účel</th>
              <th>Platnost</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>WGS_SESSION</code></td>
              <td>Identifikace přihlášeného uživatele</td>
              <td>Do zavření prohlížeče / 7 dní (PWA)</td>
            </tr>
            <tr>
              <td><code>csrf_token</code></td>
              <td>Ochrana proti CSRF útokům</td>
              <td>Session</td>
            </tr>
            <tr>
              <td><code>wgs_cookie_consent</code></td>
              <td>Uložení vašich preferencí cookies</td>
              <td>365 dní</td>
            </tr>
          </tbody>
        </table>
      </article>

      <article class="cookies-card">
        <h2>Funkční cookies</h2>
        <p><span class="cookie-type-badge cookie-type-funkcni">Volitelné</span></p>
        <p>Tyto cookies vylepšují funkčnost webu a personalizují váš zážitek:</p>
        <table class="cookies-table">
          <thead>
            <tr>
              <th>Cookie</th>
              <th>Účel</th>
              <th>Platnost</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>remember_me</code></td>
              <td>Trvalé přihlášení (volitelné)</td>
              <td>30 dní</td>
            </tr>
            <tr>
              <td><code>wgs_pwa_mode</code></td>
              <td>Detekce instalované PWA aplikace</td>
              <td>Trvalé</td>
            </tr>
            <tr>
              <td><code>wgs_jazyk</code></td>
              <td>Preferovaný jazyk rozhraní</td>
              <td>365 dní</td>
            </tr>
          </tbody>
        </table>
      </article>

      <article class="cookies-card">
        <h2>Analytické cookies</h2>
        <p><span class="cookie-type-badge cookie-type-analyticke">Volitelné</span></p>
        <p>Tyto cookies nám pomáhají pochopit, jak návštěvníci používají náš web:</p>
        <table class="cookies-table">
          <thead>
            <tr>
              <th>Cookie</th>
              <th>Účel</th>
              <th>Platnost</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td><code>wgs_analytics_id</code></td>
              <td>Anonymní identifikátor pro statistiky návštěvnosti</td>
              <td>730 dní</td>
            </tr>
            <tr>
              <td><code>wgs_heatmap</code></td>
              <td>Sledování pohybu myši pro optimalizaci UX</td>
              <td>Session</td>
            </tr>
          </tbody>
        </table>
        <p>
          <strong>Důležité:</strong> Analytické cookies sbíráme pouze s vaším souhlasem.
          IP adresy jsou anonymizovány a data jsou automaticky mazána po 2 letech.
        </p>
      </article>

      <article class="cookies-card full-width">
        <h2>Správa cookies</h2>
        <p>Máte plnou kontrolu nad cookies. Můžete:</p>
        <ul>
          <li>
            <strong>Změnit nastavení v banneru</strong>
            Při první návštěvě se zobrazí banner, kde můžete vybrat, které cookies povolíte.
            Nastavení můžete kdykoliv změnit kliknutím na "Nastavení cookies" v patičce webu.
          </li>
          <li>
            <strong>Nastavit v prohlížeči</strong>
            Většina prohlížečů umožňuje cookies blokovat nebo mazat. Návod najdete v nápovědě
            vašeho prohlížeče (Chrome, Firefox, Safari, Edge).
          </li>
          <li>
            <strong>Smazat všechny cookies</strong>
            V nastavení prohlížeče můžete smazat všechny uložené cookies.
            Po smazání budete odhlášeni a banner se zobrazí znovu.
          </li>
        </ul>
      </article>

      <article class="cookies-card">
        <h2>Cookies třetích stran</h2>
        <p>Náš web používá minimální množství služeb třetích stran:</p>
        <ul>
          <li>
            <strong>Google Fonts</strong>
            Načítání písma Poppins. Google může sbírat anonymní data o použití.
          </li>
          <li>
            <strong>Geoapify</strong>
            Zobrazení map pro lokalizaci servisních zakázek (pouze pro přihlášené techniky).
          </li>
        </ul>
        <p>Nepoužíváme reklamní cookies ani cookies sociálních sítí.</p>
      </article>

      <article class="cookies-card">
        <h2>Kontakt</h2>
        <p>
          Máte-li dotazy ohledně cookies nebo ochrany soukromí, kontaktujte nás na
          <a href="mailto:reklamace@wgs-service.cz" class="cookies-link">reklamace@wgs-service.cz</a>.
        </p>
        <p>
          Více informací o zpracování osobních údajů najdete na stránce
          <a href="gdpr.php" class="cookies-link">Zpracování osobních údajů (GDPR)</a>.
        </p>
        <p class="cookies-meta">Poslední aktualizace: <?php echo date('d.m.Y'); ?></p>
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
</body>
</html>
