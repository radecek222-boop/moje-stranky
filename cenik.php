<?php require_once "init.php"; ?>
<?php
// Načíst CSRF token pro admin edit mód
$csrfToken = '';
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    require_once __DIR__ . '/includes/csrf_helper.php';
    $csrfToken = generateCSRFToken();
}
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
  <meta name="description" content="Ceník servisu Natuzzi - White Glove Service. Transparentní ceny za opravy, reklamace, montáž. Autorizovaný servis s více než 5letou zkušeností. ☎ +420 725 965 826">

  <!-- PWA -->
  <link rel="manifest" href="./manifest.json">
  <link rel="apple-touch-icon" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="192x192" href="./icon192.png">
  <link rel="icon" type="image/png" sizes="512x512" href="./icon512.png">

  <title>Ceník služeb - White Glove Service | Servis Natuzzi | Praha, Brno</title>

  <!-- Preload critical resources -->
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/cenik.min.css" as="style">

  <!-- Google Fonts - Natuzzi style -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet"></noscript>

  <!-- External CSS -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/cenik.min.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.css">

  <!-- Analytics Tracker -->
  <?php require_once __DIR__ . '/includes/analytics_tracker.php'; ?>

  <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
  <script>
      // CSRF token pro admin operace
      window.csrfToken = '<?php echo $csrfToken; ?>';
      window.isAdmin = true;
  </script>
  <?php endif; ?>
</head>
<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<!-- HERO SEKCE -->
<main>
<section class="hero">
  <div class="hero-content">
    <h1 class="hero-title">Ceník služeb</h1>
    <div class="hero-subtitle">White Glove Service - Natuzzi servis</div>
  </div>
</section>

<!-- CONTENT SEKCE -->
<section class="content-section">
  <div class="container">

    <!-- KALKULAČKA CENY -->
    <div class="calculator-section" id="kalkulacka">
      <h2 class="section-title">Kalkulace ceny služby</h2>
      <p class="section-text">
        Zadejte vaši adresu a vyberte požadované služby pro orientační výpočet ceny.
      </p>

      <!-- Krok 1: Zadání adresy -->
      <div class="calculator-step">
        <h3>1. Zadejte adresu zákazníka</h3>
        <div class="form-group">
          <label for="calc-address">Adresa:</label>
          <input
            type="text"
            id="calc-address"
            class="calc-input"
            placeholder="Začněte psát adresu..."
            autocomplete="off"
          >
          <div id="address-suggestions" class="suggestions-dropdown" style="display: none;"></div>
        </div>

        <div id="distance-result" class="calc-result" style="display: none;">
          <p><strong>Vzdálenost z dílny:</strong> <span id="distance-value">-</span> km</p>
          <p><strong>Dopravné (tam a zpět):</strong> <span id="transport-cost">-</span> €</p>
        </div>
      </div>

      <!-- Krok 2: Výběr služeb -->
      <div class="calculator-step" id="services-selection" style="display: none;">
        <h3>2. Vyberte požadované služby</h3>
        <div id="services-checkboxes" class="services-grid">
          <!-- Načteno dynamicky z API -->
        </div>
      </div>

      <!-- Krok 3: Cenový souhrn -->
      <div class="calculator-step" id="price-summary" style="display: none;">
        <h3>3. Cenový souhrn</h3>
        <div class="price-summary-box">
          <div class="summary-line">
            <span>Služby celkem:</span>
            <span id="services-total">0 €</span>
          </div>
          <div class="summary-line">
            <span>Dopravné:</span>
            <span id="transport-total">0 €</span>
          </div>
          <div class="summary-line total">
            <span><strong>Celková cena:</strong></span>
            <span id="grand-total"><strong>0 €</strong></span>
          </div>
          <p class="summary-note">
            * Ceny jsou orientační a vztahují se pouze na práci. Materiál se účtuje zvlášť.
          </p>
        </div>
      </div>

      <!-- Reset Button -->
      <div class="calculator-actions">
        <button class="btn-reset" onclick="resetovatKalkulacku()" style="display: none;" id="reset-btn">
          Nová kalkulace
        </button>
      </div>
    </div>

    <hr style="margin: 60px 0; border: none; border-top: 2px dashed rgba(44, 62, 80, 0.2);">

    <div class="section-intro">
      <h2 class="section-title">Přehled služeb a cen</h2>

      <p class="section-text">
        Níže naleznete kompletní ceník našich služeb. Všechny ceny jsou uvedeny v EUR a platí od 1.1.2026.
        Účtovaná cena bude přepočtena na Kč podle aktuálního kurzu. Primárně přijímáme zakázky v lokalitě 150km od dílny.
      </p>

      <p class="section-text note">
        <strong>Poznámka:</strong> Všechny ceny jsou uvedeny za práci BEZ materiálu. Materiál se účtuje zvlášť.
        Konečná cena může být ovlivněna složitostí opravy, dostupností materiálu a vzdáleností od naší dílny.
        Pro přesnou cenovou nabídku nás prosím kontaktujte.
      </p>
    </div>

    <!-- Loading Indicator -->
    <div id="loading-indicator" style="text-align: center; padding: 40px;">
      <div class="spinner"></div>
      <p>Načítám ceník...</p>
    </div>

    <!-- Pricing Grid -->
    <div id="pricing-grid" style="display: none;"></div>

    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
    <!-- Admin Tlačítka -->
    <div class="admin-actions" style="margin-top: 40px; text-align: center;">
      <button class="btn-admin" onclick="pridatPolozku()">+ Přidat novou položku</button>
    </div>
    <?php endif; ?>

    <!-- Kontaktní informace -->
    <div class="pricing-footer">
      <h3>Máte dotazy k cenám?</h3>
      <p>Neváhejte nás kontaktovat pro nezávaznou cenovou nabídku.</p>
      <p class="contact-info">
        <strong>Tel:</strong> <a href="tel:+420725965826">+420 725 965 826</a><br>
        <strong>Email:</strong> <a href="mailto:reklamace@wgs-service.cz">reklamace@wgs-service.cz</a>
      </p>
    </div>

  </div>
</section>
</main>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-container">

    <div class="footer-columns">

      <!-- KONTAKT -->
      <div class="footer-column">
        <h2 class="footer-title">Kontakt</h2>
        <p class="footer-text">
          <strong>Tel:</strong> <a href="tel:+420725965826" class="footer-link">+420 725 965 826</a><br>
          <strong>Email:</strong> <a href="mailto:reklamace@wgs-service.cz" class="footer-link">reklamace@wgs-service.cz</a>
        </p>
      </div>

      <!-- ADRESA -->
      <div class="footer-column">
        <h2 class="footer-title">Adresa</h2>
        <p class="footer-text">
          Do Dubče 364, Běchovice 190 11 CZ
        </p>
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

<!-- Edit Modal (pouze pro adminy) -->
<?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
<div id="edit-modal" class="modal" style="display: none;">
  <div class="modal-content">
    <span class="modal-close" onclick="zavritModal()">&times;</span>
    <h2 id="modal-title">Upravit položku</h2>

    <form id="edit-form" onsubmit="ulozitPolozku(event)">
      <input type="hidden" id="item-id" name="id">

      <div class="form-group">
        <label for="service-name">Název služby *</label>
        <input type="text" id="service-name" name="service_name" required>
      </div>

      <div class="form-group">
        <label for="description">Popis</label>
        <textarea id="description" name="description" rows="4"></textarea>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="price-from">Cena od</label>
          <input type="number" step="0.01" id="price-from" name="price_from">
        </div>

        <div class="form-group">
          <label for="price-to">Cena do</label>
          <input type="number" step="0.01" id="price-to" name="price_to">
        </div>

        <div class="form-group">
          <label for="price-unit">Měna</label>
          <select id="price-unit" name="price_unit">
            <option value="€">€ (EUR)</option>
            <option value="Kč">Kč (CZK)</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="category">Kategorie</label>
        <input type="text" id="category" name="category">
      </div>

      <div class="form-group">
        <label>
          <input type="checkbox" id="is-active" name="is_active" value="1" checked>
          Aktivní (zobrazit na webu)
        </label>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="zavritModal()">Zrušit</button>
        <button type="submit" class="btn-primary">Uložit</button>
        <button type="button" class="btn-danger" onclick="smazatPolozku()" id="delete-btn" style="display: none;">Smazat</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- External JavaScript -->
<script src="assets/js/logger.js" defer></script>
<script src="assets/js/wgs-map.js" defer></script>
<script src="assets/js/cenik.js" defer></script>
<script src="assets/js/cenik-calculator.js" defer></script>

<?php renderHeatmapTracker(); ?>
</body>
</html>
