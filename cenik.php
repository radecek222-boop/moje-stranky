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
        Odpovězte na několik jednoduchých otázek a zjistěte orientační cenu servisu.
      </p>

      <!-- Progress Indicator -->
      <div class="wizard-progress" id="wizard-progress">
        <div class="progress-step active" data-step="1">
          <span class="step-number">1</span>
          <span class="step-label">Adresa</span>
        </div>
        <div class="progress-step" data-step="2">
          <span class="step-number">2</span>
          <span class="step-label">Typ servisu</span>
        </div>
        <div class="progress-step" data-step="3">
          <span class="step-number">3</span>
          <span class="step-label">Detaily</span>
        </div>
        <div class="progress-step" data-step="4">
          <span class="step-number">4</span>
          <span class="step-label">Souhrn</span>
        </div>
      </div>

      <!-- KROK 1: Zadání adresy -->
      <div class="wizard-step" id="step-address" style="display: block;">
        <h3 class="step-title">1. Zadejte adresu zákazníka</h3>
        <p class="step-desc">Pro výpočet dopravného potřebujeme znát vaši adresu.</p>

        <div class="form-group">
          <label for="calc-address">Adresa:</label>
          <input
            type="text"
            id="calc-address"
            class="calc-input"
            placeholder="Začněte psát adresu (ulice, město)..."
            autocomplete="off"
          >
          <div id="address-suggestions" class="suggestions-dropdown" style="display: none;"></div>
        </div>

        <?php
        // Checkbox pro reklamace - viditelný jen pro přihlášené uživatele
        $isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
        if ($isLoggedIn):
        ?>
        <div class="form-group" style="margin-top: 15px;">
          <label class="checkbox-container">
            <input type="checkbox" id="reklamace-bez-dopravy">
            <span class="checkbox-label">Jedná se o reklamaci – neúčtuje se dopravné</span>
          </label>
        </div>
        <?php endif; ?>

        <div id="distance-result" class="calc-result" style="display: none;">
          <div class="result-box">
            <p><strong>Vzdálenost z dílny:</strong> <span id="distance-value">-</span> km</p>
            <p><strong>Dopravné (tam a zpět):</strong> <span id="transport-cost" class="highlight-price">-</span> €</p>
          </div>
        </div>
      </div>

      <!-- KROK 2: Typ servisu -->
      <div class="wizard-step" id="step-service-type" style="display: none;">
        <h3 class="step-title">2. Jaký typ servisu potřebujete?</h3>
        <p class="step-desc">Vyberte, co u vás potřebujeme udělat.</p>

        <div class="radio-group">
          <label class="radio-card">
            <input type="radio" name="service-type" value="diagnostika">
            <div class="radio-content">
              <div class="radio-title">Pouze diagnostika / inspekce</div>
              <div class="radio-desc">Technik provede pouze zjištění rozsahu poškození a posouzení stavu.</div>
              <div class="radio-price">155 €</div>
            </div>
          </label>

          <label class="radio-card">
            <input type="radio" name="service-type" value="calouneni" checked>
            <div class="radio-content">
              <div class="radio-title">Čalounické práce</div>
              <div class="radio-desc">Oprava včetně rozčalounění konstrukce (sedáky, opěrky, područky).</div>
              <div class="radio-price">Od 190 €</div>
            </div>
          </label>

          <label class="radio-card">
            <input type="radio" name="service-type" value="mechanika">
            <div class="radio-content">
              <div class="radio-title">Mechanické opravy</div>
              <div class="radio-desc">Oprava mechanismů (relax, výsuv) bez rozčalounění.</div>
              <div class="radio-price">155 € / díl</div>
            </div>
          </label>

          <label class="radio-card">
            <input type="radio" name="service-type" value="kombinace">
            <div class="radio-content">
              <div class="radio-title">Kombinace čalounění + mechaniky</div>
              <div class="radio-desc">Komplexní oprava zahrnující čalounění i mechanické části.</div>
              <div class="radio-price">Dle rozsahu</div>
            </div>
          </label>
        </div>

        <div class="wizard-buttons">
          <button class="btn-secondary" onclick="previousStep()">Zpět</button>
          <button class="btn-primary" onclick="nextStep()">Pokračovat</button>
        </div>
      </div>

      <!-- KROK 3A: Čalounické práce - počet dílů -->
      <div class="wizard-step" id="step-upholstery" style="display: none;">
        <h3 class="step-title">3. Kolik dílů potřebuje přečalounit?</h3>
        <p class="step-desc">Jeden díl = sedák NEBO opěrka NEBO područka NEBO panel. První díl stojí 190€, každý další 70€.</p>

        <div class="counter-group">
          <div class="counter-item">
            <label>Sedáky</label>
            <div class="counter-controls">
              <button class="btn-counter" onclick="decrementCounter('sedaky')">−</button>
              <input type="number" id="sedaky" value="0" min="0" max="20" readonly>
              <button class="btn-counter" onclick="incrementCounter('sedaky')">+</button>
            </div>
          </div>

          <div class="counter-item">
            <label>Opěrky</label>
            <div class="counter-controls">
              <button class="btn-counter" onclick="decrementCounter('operky')">−</button>
              <input type="number" id="operky" value="0" min="0" max="20" readonly>
              <button class="btn-counter" onclick="incrementCounter('operky')">+</button>
            </div>
          </div>

          <div class="counter-item">
            <label>Područky</label>
            <div class="counter-controls">
              <button class="btn-counter" onclick="decrementCounter('podrucky')">−</button>
              <input type="number" id="podrucky" value="0" min="0" max="20" readonly>
              <button class="btn-counter" onclick="incrementCounter('podrucky')">+</button>
            </div>
          </div>

          <div class="counter-item">
            <label>Panely (zadní/boční)</label>
            <div class="counter-controls">
              <button class="btn-counter" onclick="decrementCounter('panely')">−</button>
              <input type="number" id="panely" value="0" min="0" max="20" readonly>
              <button class="btn-counter" onclick="incrementCounter('panely')">+</button>
            </div>
          </div>
        </div>

        <div class="checkbox-group">
          <label class="checkbox-card">
            <input type="checkbox" id="rohovy-dil">
            <div class="checkbox-content">
              <div class="checkbox-title">Rohový díl (1 modul + 2 díly navíc)</div>
              <div class="checkbox-price">+ 330 €</div>
            </div>
          </label>

          <label class="checkbox-card">
            <input type="checkbox" id="ottoman">
            <div class="checkbox-content">
              <div class="checkbox-title">Ottoman / Lehátko</div>
              <div class="checkbox-price">+ 260 €</div>
            </div>
          </label>
        </div>

        <div class="parts-summary" id="parts-summary">
          <strong>Celkem dílů:</strong> <span id="total-parts">0</span>
          <span class="price-breakdown" id="parts-price-breakdown"></span>
        </div>

        <div class="wizard-buttons">
          <button class="btn-secondary" onclick="previousStep()">Zpět</button>
          <button class="btn-primary" onclick="nextStep()">Pokračovat</button>
        </div>
      </div>

      <!-- KROK 3B: Mechanické práce -->
      <div class="wizard-step" id="step-mechanics" style="display: none;">
        <h3 class="step-title">3. Mechanické části</h3>
        <p class="step-desc">Vyberte, které mechanické části potřebují opravu.</p>

        <div class="counter-group">
          <div class="counter-item">
            <label>Relax mechanismy</label>
            <div class="counter-controls">
              <button class="btn-counter" onclick="decrementCounter('relax')">−</button>
              <input type="number" id="relax" value="0" min="0" max="10" readonly>
              <button class="btn-counter" onclick="incrementCounter('relax')">+</button>
            </div>
            <div class="counter-price">70 € / kus</div>
          </div>

          <div class="counter-item">
            <label>Výsuvné mechanismy</label>
            <div class="counter-controls">
              <button class="btn-counter" onclick="decrementCounter('vysuv')">−</button>
              <input type="number" id="vysuv" value="0" min="0" max="10" readonly>
              <button class="btn-counter" onclick="incrementCounter('vysuv')">+</button>
            </div>
            <div class="counter-price">70 € / kus</div>
          </div>
        </div>

        <div class="wizard-buttons">
          <button class="btn-secondary" onclick="previousStep()">Zpět</button>
          <button class="btn-primary" onclick="nextStep()">Pokračovat</button>
        </div>
      </div>

      <!-- KROK 4: Další parametry -->
      <div class="wizard-step" id="step-extras" style="display: none;">
        <h3 class="step-title">4. Další parametry</h3>
        <p class="step-desc">Poslední detaily pro přesný výpočet ceny.</p>

        <div class="checkbox-group">
          <label class="checkbox-card">
            <input type="checkbox" id="tezky-nabytek">
            <div class="checkbox-content">
              <div class="checkbox-title">Nábytek je těžší než 50 kg</div>
              <div class="checkbox-desc">Bude potřeba druhá osoba pro manipulaci</div>
              <div class="checkbox-price">+ 80 €</div>
            </div>
          </label>

          <label class="checkbox-card">
            <input type="checkbox" id="material">
            <div class="checkbox-content">
              <div class="checkbox-title">Materiál dodán od WGS</div>
              <div class="checkbox-desc">Výplně (vata, pěna) z naší zásoby</div>
              <div class="checkbox-price">+ 40 €</div>
            </div>
          </label>
        </div>

        <div class="wizard-buttons">
          <button class="btn-secondary" onclick="previousStep()">Zpět</button>
          <button class="btn-primary" onclick="nextStep()">Zobrazit souhrn</button>
        </div>
      </div>

      <!-- KROK 5: Cenový souhrn -->
      <div class="wizard-step" id="step-summary" style="display: none;">
        <h3 class="step-title">Orientační cena servisu</h3>

        <div class="price-summary-box">
          <div id="summary-details">
            <!-- Načteno dynamicky JavaScriptem -->
          </div>

          <div class="summary-line total">
            <span><strong>CELKOVÁ CENA:</strong></span>
            <span id="grand-total" class="total-price"><strong>0 €</strong></span>
          </div>

          <div class="summary-note">
            <strong>Upozornění:</strong> Ceny jsou orientační a vztahují se <strong>pouze na práci</strong>.
            Originální materiál z továrny Natuzzi a náhradní mechanické díly se účtují zvlášť podle skutečné spotřeby.
          </div>
        </div>

        <div class="wizard-buttons">
          <button class="btn-secondary" onclick="previousStep()">Zpět</button>
          <button class="btn-primary" onclick="exportovatCenikPDF()">Export do PDF</button>
          <button class="btn-primary" onclick="resetovatKalkulacku()">Nová kalkulace</button>
        </div>
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

<!-- PDF Export Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>

<script src="assets/js/cenik-calculator.js" defer></script>

<?php renderHeatmapTracker(); ?>
</body>
</html>
