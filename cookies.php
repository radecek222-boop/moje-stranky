<?php require_once "init.php"; ?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="theme-color" content="#000000">
  <meta name="description" content="Zásady používání cookies na webu White Glove Service.">
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
      max-width: 900px;
      margin: 0 auto;
    }
    .cookies-card {
      background: #fff;
      border-radius: 16px;
      padding: 2rem;
      margin-bottom: 1.5rem;
      box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
      border: 1px solid rgba(148, 163, 184, 0.15);
    }
    .cookies-card:last-child { margin-bottom: 0; }
    .cookies-card h2 {
      font-size: 1.2rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin-bottom: 1rem;
      color: #111827;
      border-bottom: 2px solid #111827;
      padding-bottom: 0.5rem;
    }
    .cookies-card p, .cookies-card li {
      font-size: 0.9rem;
      line-height: 1.7;
      color: #374151;
    }
    .cookies-card p { margin-bottom: 0.75rem; }
    .cookies-card p:last-child { margin-bottom: 0; }
    .cookies-card ul {
      margin: 0;
      padding-left: 1.25rem;
    }
    .cookies-card li { margin-bottom: 0.4rem; }
    .cookies-link {
      color: #111827;
      font-weight: 600;
      text-decoration: none;
      border-bottom: 1px solid rgba(17, 24, 39, 0.35);
    }
    .cookies-link:hover { color: #000; border-color: #000; }
    .cookies-table {
      width: 100%;
      border-collapse: collapse;
      margin: 0.75rem 0;
      font-size: 0.85rem;
    }
    .cookies-table th, .cookies-table td {
      padding: 0.5rem 0.75rem;
      text-align: left;
      border-bottom: 1px solid #e5e7eb;
    }
    .cookies-table th {
      background: #f3f4f6;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.7rem;
      letter-spacing: 0.04em;
      color: #374151;
    }
    .cookies-table tr:last-child td { border-bottom: none; }
    .cookie-badge {
      display: inline-block;
      padding: 0.15rem 0.4rem;
      border-radius: 3px;
      font-size: 0.65rem;
      font-weight: 600;
      text-transform: uppercase;
      margin-right: 0.5rem;
    }
    .badge-required { background: #111827; color: #fff; }
    .badge-optional { background: #9ca3af; color: #111827; }
    .cookies-meta {
      text-align: center;
      font-size: 0.8rem;
      color: #6b7280;
      margin-top: 1rem;
    }
    @media (max-width: 768px) {
      .cookies-card { padding: 1.5rem; }
      .cookies-hero { padding: 5rem 1.5rem 3rem; }
    }
  </style>
  <?php if (isset($_COOKIE['wgs_analytics_consent']) && $_COOKIE['wgs_analytics_consent'] === '1'): ?>
    <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>
  <?php endif; ?>
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<main id="main-content">
  <section class="cookies-hero">
    <h1>Zásady používání cookies</h1>
    <p>Informace o tom, jak používáme cookies pro zajištění funkčnosti webu.</p>
  </section>

  <section class="cookies-content">
    <div class="cookies-container">

      <article class="cookies-card">
        <h2>1. Co jsou cookies</h2>
        <p>Cookies jsou malé textové soubory, které webové stránky ukládají do vašeho prohlížeče. Pomáhají nám zapamatovat si vaše preference a zajistit bezpečné přihlášení. Můžete si vybrat, které volitelné cookies povolíte.</p>
      </article>

      <article class="cookies-card">
        <h2>2. Nezbytné cookies</h2>
        <p><span class="cookie-badge badge-required">Vždy aktivní</span> Tyto cookies jsou nutné pro základní funkce webu a nelze je vypnout.</p>
        <table class="cookies-table">
          <thead><tr><th>Cookie</th><th>Účel</th><th>Platnost</th></tr></thead>
          <tbody>
            <tr><td><code>WGS_SESSION</code></td><td>Identifikace přihlášeného uživatele</td><td>Session / 7 dní</td></tr>
            <tr><td><code>csrf_token</code></td><td>Ochrana proti CSRF útokům</td><td>Session</td></tr>
            <tr><td><code>wgs_cookie_consent</code></td><td>Uložení preferencí cookies</td><td>365 dní</td></tr>
          </tbody>
        </table>
      </article>

      <article class="cookies-card">
        <h2>3. Funkční cookies</h2>
        <p><span class="cookie-badge badge-optional">Volitelné</span> Vylepšují funkčnost webu a personalizují váš zážitek.</p>
        <table class="cookies-table">
          <thead><tr><th>Cookie</th><th>Účel</th><th>Platnost</th></tr></thead>
          <tbody>
            <tr><td><code>remember_me</code></td><td>Trvalé přihlášení</td><td>30 dní</td></tr>
            <tr><td><code>wgs_pwa_mode</code></td><td>Detekce PWA aplikace</td><td>Trvalé</td></tr>
            <tr><td><code>wgs_jazyk</code></td><td>Preferovaný jazyk</td><td>365 dní</td></tr>
          </tbody>
        </table>
      </article>

      <article class="cookies-card">
        <h2>4. Analytické cookies</h2>
        <p><span class="cookie-badge badge-optional">Volitelné</span> Pomáhají nám pochopit, jak návštěvníci používají web. Sbíráme je pouze s vaším souhlasem. IP adresy jsou anonymizovány, data mazána po 2 letech.</p>
        <table class="cookies-table">
          <thead><tr><th>Cookie</th><th>Účel</th><th>Platnost</th></tr></thead>
          <tbody>
            <tr><td><code>wgs_analytics_id</code></td><td>Anonymní statistiky návštěvnosti</td><td>730 dní</td></tr>
          </tbody>
        </table>
      </article>

      <article class="cookies-card">
        <h2>5. Správa cookies</h2>
        <p>Máte plnou kontrolu nad cookies:</p>
        <ul>
          <li><strong>Banner:</strong> Při první návštěvě vyberte, které cookies povolíte.</li>
          <li><strong>Prohlížeč:</strong> V nastavení prohlížeče můžete cookies blokovat nebo mazat.</li>
          <li><strong>Reset:</strong> Po smazání cookies budete odhlášeni a banner se zobrazí znovu.</li>
        </ul>
      </article>

      <article class="cookies-card">
        <h2>6. Cookies třetích stran</h2>
        <p>Používáme minimální množství služeb třetích stran:</p>
        <ul>
          <li><strong>Google Fonts</strong> – načítání písma Poppins</li>
          <li><strong>Geoapify</strong> – mapy pro servisní zakázky (pouze přihlášení)</li>
        </ul>
        <p>Nepoužíváme reklamní cookies ani cookies sociálních sítí.</p>
      </article>

      <article class="cookies-card">
        <h2>7. Kontakt</h2>
        <p>Dotazy ohledně cookies: <a href="mailto:<?php echo WGS_EMAIL_INFO; ?>" class="cookies-link"><?php echo WGS_EMAIL_INFO; ?></a></p>
        <p>Více informací: <a href="gdpr.php" class="cookies-link">Zpracování osobních údajů (GDPR)</a></p>
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
          <?php echo wgsFooterKontakt('info'); ?>
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
