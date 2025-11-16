<?php
require_once __DIR__ . '/init.php';

$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Bezpečnostní kontrola: CSRF token se generuje pouze pokud je session aktivní
if (session_status() !== PHP_SESSION_ACTIVE) {
    die('Session není aktivní. Obnovte stránku.');
}
?>
<!DOCTYPE html>
<html lang="cs" data-page="novareklamace">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Reklamace luxusního nábytku Natuzzi. Rychlé a profesionální vyřízení reklamací sedaček a souprav. Autorizovaný servis v Praze, Brně, Bratislavě.">
  <meta name="theme-color" content="#000000">
  <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
  <title>Objednat servis | WGS</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  
  <!-- Leaflet Map -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
  
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/novareklamace.min.css">


<style>
/* Custom Calendar */
.calendar-overlay {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.8);
  display: none;              /* <-- PŘIDEJ TENTO ŘÁDEK */
  align-items: center;         /* <-- PŘIDEJ TENTO ŘÁDEK */
  justify-content: center;     /* <-- PŘIDEJ TENTO ŘÁDEK */
  z-index: 9999;
}
.calendar-overlay.active { display: flex; }

.calendar-box {
  background: white;
  padding: 2rem;
  max-width: 550px;
  width: 90%;
  box-shadow: 0 10px 40px rgba(0,0,0,0.3);
}

.calendar-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 1rem;
  padding-bottom: 1rem;
  border-bottom: 2px solid #000;
}

.calendar-header h3 {
  font-size: 1.2rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.1em;
}

.calendar-nav {
  display: flex;
  gap: 0.5rem;
}

.calendar-nav button {
  background: #000;
  color: white;
  border: none;
  padding: 0.5rem 1rem;
  cursor: pointer;
  font-weight: 600;
  font-size: 1.2rem;
}

.calendar-year-nav {
  display: flex;
  gap: 1rem;
  justify-content: center;
  margin-bottom: 1rem;
}

.calendar-year-nav button {
  background: #666;
  color: white;
  border: none;
  padding: 0.4rem 1.5rem;
  cursor: pointer;
  font-size: 0.9rem;
}

.calendar-grid {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
  gap: 0.5rem;
  min-height: 320px;
}

.calendar-day {
  text-align: center;
  padding: 1rem 0.5rem;
  min-width: 50px;
  cursor: pointer;
  border: 1px solid #e0e0e0;
  transition: all 0.2s;
}

.calendar-day:hover:not(.disabled) {
  background: #f5f5f5;
  border-color: #000;
}

.calendar-day.disabled {
  opacity: 0;
  cursor: default;
  pointer-events: none;
}

.calendar-day.selected {
  background: #000;
  color: white;
}

.calendar-weekday {
  font-weight: 600;
  text-align: center;
  padding: 0.5rem;
  font-size: 0.8rem;
  text-transform: uppercase;
  letter-spacing: 0.05em;
}

.form-consent {
  margin-top: 1.5rem;
  padding: 1.5rem;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  background: #f9fafb;
}

.consent-label {
  display: flex;
  gap: 1rem;
  font-size: 0.95rem;
  line-height: 1.6;
  color: #374151;
}

.consent-label input {
  margin-top: 0.3rem;
  width: 1.1rem;
  height: 1.1rem;
}

.consent-note {
  margin-top: 0.75rem;
  font-size: 0.82rem;
  color: #6b7280;
}

#warrantyWarning {
  margin-top: 1rem;
  padding: 1rem;
  border-left: 4px solid #006600;
  background: #f0fff0;
  font-size: 0.9rem;
}

#warrantyWarning.warning {
  border-left-color: #ff9900;
  background: #fff9e6;
}

#warrantyWarning.expired {
  border-left-color: #cc0000;
  background: #fff0f0;
}

/* Provedení Overlay - Minimalistický */
.overlay-provedeni {
  position: fixed;
  top: 0; left: 0; right: 0; bottom: 0;
  background: rgba(0,0,0,0.4);
  display: none;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.overlay-provedeni.active { display: flex; }

.provedeni-box {
  position: relative;
  background: white;
  padding: 1.5rem;
  max-width: 350px;
  width: 90%;
  box-shadow: 0 4px 12px rgba(0,0,0,0.15);
  border-radius: 4px;
}

.provedeni-box h3 {
  font-size: 0.85rem;
  font-weight: 500;
  margin: 0 0 1rem 0;
  color: #666;
  text-align: center;
  text-transform: none;
  letter-spacing: 0;
}

.provedeni-grid {
  display: flex;
  gap: 0.5rem;
  margin-bottom: 0;
}

.provedeni-card {
  flex: 1;
  padding: 0.7rem 0.5rem;
  text-align: center;
  border: 1px solid #ddd;
  background: white;
  cursor: pointer;
  transition: all 0.15s;
  font-size: 0.85rem;
  font-weight: 400;
  border-radius: 3px;
}

.provedeni-card:hover {
  background: #f5f5f5;
  border-color: #999;
}

.provedeni-card:active {
  transform: scale(0.98);
}

#closeProvedeni {
  position: absolute;
  top: 0.5rem;
  right: 0.5rem;
  width: 28px;
  height: 28px;
  padding: 0;
  font-size: 1.5rem;
  line-height: 1;
  border: none;
  background: transparent;
  color: #999;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
}

#closeProvedeni:hover {
  color: #333;
  background: transparent;
}
</style>

</head>

<body>
<?php require_once __DIR__ . "/includes/hamburger-menu.php"; ?>

<?php
// Admin a User header jsou zakomentované, protože hamburger-menu.php už poskytuje navigaci
// if ($isAdmin) {
//   require_once __DIR__ . "/includes/admin_header.php";
// } else {
//   require_once __DIR__ . "/includes/user_header.php";
// }
?>

<main>
<!-- HERO -->
<section class="hero">
  <div>
    <h1 class="hero-title">Objednat Servis</h1>
    <p class="hero-subtitle">Rychlý a profesionální servis vašeho nábytku</p>
  </div>
</section>

<!-- FORM -->
<div class="form-container">
  <div class="form-card">
    
    <?php if (!$isLoggedIn): ?>
    <div id="calculatorBox" style="padding: 2.5rem; margin-bottom: 3rem; border: 2px solid #000000; background: #ffffff; display: none;">
      <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.8rem; font-weight: 300; letter-spacing: 0.1em; margin-bottom: 1rem; color: #000000; text-transform: uppercase;">Orientační cena servisu</h3>
      <p style="color: #666; font-size: 0.95rem; line-height: 1.6; margin-bottom: 2rem;">Spočítejte si předběžnou cenu mimozáručního servisu včetně dopravy ještě před odesláním objednávky.</p>
      <a href="mimozarucniceny.php" style="display: inline-block; padding: 1rem 3rem; background: #000000; color: white; text-decoration: none; font-family: 'Poppins', sans-serif; font-size: 0.85rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; transition: all 0.3s; border: 2px solid #000000;">Kalkulačka ceny</a>
    </div>
    <?php endif; ?>
    
    <?php if (!$isLoggedIn): ?>
    <div id="modeInfo" style="display:none; padding: 1rem; margin-bottom: 2rem; border-left: 4px solid #555555; background: #f5f5f5; font-size: 0.9rem;">
      <strong id="modeTitle"></strong>
      <p id="modeDescription" style="margin-top: 0.5rem; color: #666;"></p>
    </div>
    <?php endif; ?>
    
    <?php if (!$isLoggedIn): ?>
    <div id="calculationBox" style="display:none; padding: 2rem; margin-bottom: 3rem; border: 2px solid #555555; background: #f5f5f5;">
      <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.5rem; font-weight: 600; margin-bottom: 1rem; color: #555555;">
        ✓ <span>Vaše kalkulace z cenové kalkulačky</span>
      </h3>
      <div id="calculationDetails" style="font-size: 0.9rem; line-height: 1.8; color: #333;"></div>
      <div style="margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #555555;">
        <strong style="font-size: 1.1rem; color: #555555;"><span>Celková orientační cena:</span> <span id="calculationTotal"></span></strong>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- ✅ FIX M-4: Přidat action attribute pro fallback (pokud JS selže) -->
    <form id="reklamaceForm" action="app/controllers/save.php" method="POST">
      
      <!-- ZÁKLADNÍ ÚDAJE -->
      <div class="form-section">
        <h2 class="section-title">Základní údaje</h2>
        <div class="form-grid form-grid-3">
          <div class="form-group">
            <label class="form-label" for="cislo">Číslo objednávky/reklamace<?php if ($isLoggedIn) echo " *"; ?></label>
            <input type="text" class="form-control" id="cislo" name="cislo"<?php if ($isLoggedIn) echo " required"; ?>>
            <select id="fakturace_firma" name="fakturace_firma" style="margin-top:0.5rem; width:33%; height:2rem; font-size:0.85rem; padding:0.3rem; border:1px solid #ddd; border-radius:4px;">
              <option value="CZ" selected>🇨🇿 CZ</option>
              <option value="SK">🇸🇰 SK</option>
            </select>
            <p id="faktura_hint" style="margin-top:0.3rem; font-size:0.8rem; color:#059669; font-style:italic;">Tato objednávka se bude fakturovat na CZ firmu</p>
          </div>
          <div class="form-group">
            <label class="form-label" for="datum_prodeje">Datum prodeje<?php if ($isLoggedIn) echo " *"; ?></label>
            <div class="date-input-wrapper">
              <input type="text" class="form-control date-input" id="datum_prodeje" name="datum_prodeje" placeholder="DD.MM.RRRR"<?php if ($isLoggedIn) echo " required"; ?>>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="datum_reklamace">Datum reklamace<?php if ($isLoggedIn) echo " *"; ?></label>
            <div class="date-input-wrapper">
              <input type="text" class="form-control date-input" id="datum_reklamace" name="datum_reklamace" placeholder="DD.MM.RRRR"<?php if ($isLoggedIn) echo " required"; ?>>
            </div>
          </div>
        </div>
        <div id="warrantyWarning" style="display:none;"></div>
      </div>
      
      <!-- KONTAKTNÍ ÚDAJE -->
      <div class="form-section">
        <h2 class="section-title">Kontaktní údaje</h2>
        <div class="form-grid form-grid-3">
          <div class="form-group">
            <label class="form-label" for="jmeno">Jméno zákazníka *</label>
            <input type="text" class="form-control" id="jmeno" name="jmeno" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="email">E-mail *</label>
            <input type="email" class="form-control" id="email" name="email" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="telefon">Telefon *</label>
            <input type="tel" class="form-control" id="telefon" name="telefon" required>
          </div>
        </div>
      </div>
      
     <!-- ADRESA ZÁKAZNÍKA -->
<div class="form-section">
  <h2 class="section-title">Adresa zákazníka</h2>
  <div class="form-grid form-grid-address">
    <div class="form-group" style="position:relative;">
      <label class="form-label" for="ulice">Ulice a číslo popisné</label>
      <input type="text" class="form-control" id="ulice" name="ulice" placeholder="Ulice a číslo popisné">
      <div id="autocompleteDropdownUlice" style="display:none;position:absolute;top:100%;margin-top:4px;background:white;border:1px solid #ddd;max-height:200px;overflow-y:auto;z-index:1000;width:100%;box-shadow:0 4px 12px rgba(0,0,0,0.15);border-radius:4px;"></div>
    </div>
    <div class="form-group" style="position:relative;">
      <label class="form-label" for="mesto">Město</label>
      <input type="text" class="form-control" id="mesto" name="mesto" placeholder="Město">
      <div id="autocompleteDropdown" style="display:none;position:absolute;top:100%;margin-top:4px;background:white;border:1px solid #ddd;max-height:200px;overflow-y:auto;z-index:1000;width:100%;box-shadow:0 4px 12px rgba(0,0,0,0.15);border-radius:4px;"></div>
    </div>
    <div class="form-group">
      <label class="form-label" for="psc">PSČ</label>
      <input type="text" class="form-control" id="psc" name="psc" placeholder="PSČ">
    </div>
    <div class="map-container">
      <div id="mapContainer"></div>
    </div>
  </div>
</div>
      
      <!-- INFORMACE O PRODUKTU -->
      <div class="form-section">
        <h2 class="section-title">Informace o produktu</h2>
        <div class="form-grid form-grid-3">
          <div class="form-group">
            <label class="form-label" for="model">Model</label>
            <input type="text" class="form-control" id="model" name="model">
          </div>
          <div class="form-group">
            <label class="form-label" for="provedeni">Provedení</label>
            <div class="provedeni-group">
              <input type="text" class="form-control provedeni-input" id="provedeni" name="provedeni" placeholder="Vyberte..." readonly>
              <button type="button" class="btn-select" id="selectProvedeniBtn">VYBRAT</button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="barva">Označení barvy</label>
            <input type="text" class="form-control" id="barva" name="barva" placeholder="Např. BF12">
          </div>
        </div>
        
        <?php if ($isLoggedIn): ?>
        <div class="form-grid" style="margin-top:1.5rem;">
          <div class="form-group">
            <label class="form-label" for="info_prodejce">Doplňující informace od prodejce</label>
            <textarea class="form-control" id="doplnujici_info" name="doplnujici_info" placeholder="Doplňující informace od prodejce..."></textarea>
          </div>
        </div>
        <?php endif; ?>
      </div>
      
      <!-- POPIS PROBLÉMU -->
      <div class="form-section">
        <h2 class="section-title">Popis problému</h2>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label" for="popis_problemu">Popis problému od zákazníka</label>
            <textarea class="form-control" id="popis_problemu" name="popis_problemu" placeholder="Popis problému od zákazníka..." required></textarea>
          </div>
        </div>
      </div>
      
      <!-- FOTODOKUMENTACE -->
      <div class="form-section">
        <h2 class="section-title">Fotodokumentace</h2>
        <div class="photo-upload-area">
          <button type="button" class="btn-photo" id="uploadPhotosBtn">VYBRAT FOTOGRAFIE</button>
          <p class="photo-info">Max. 10 fotografií • automatická komprese</p>
          <input type="file" id="photoInput" accept="image/*" multiple style="display:none;">
        </div>
        <div id="photoPreviewMain"></div>
      </div>

      <!-- GDPR CONSENT - pouze pro neregistrované uživatele -->
      <?php if (!$isLoggedIn): ?>
      <div class="form-consent" style="margin: 1.5rem 0 1rem 0; padding: 0.5rem 1rem; border: 1px solid #ddd; background: #f9f9f9; text-align: center;">
        <label style="display: inline-flex; align-items: center; cursor: pointer; font-size: 0.55rem; line-height: 1.3; color: #666;">
          <input type="checkbox" id="gdpr_consent" name="gdpr_consent" required style="margin-right: 0.5rem; flex-shrink: 0;">
          <span>Souhlasím se zpracováním osobních údajů společností White Glove Service, s.r.o.</span>
        </label>
      </div>
      <?php endif; ?>

      <!-- BUTTONS -->
      <div class="form-actions">
        <button type="submit" class="btn">ODESLAT POŽADAVEK</button>
        <button type="button" class="btn btn-secondary" onclick="window.history.back()">ZPĚT</button>
      </div>
      
    </form>
  </div>
</div>
</main>

<!-- FOOTER -->
<footer class="footer">
  <div class="footer-container">
    <div class="footer-grid">
      <div class="footer-column">
        <h2 class="footer-title">White Glove Service</h2>
        <p class="footer-text">
          Specializovaný servis Natuzzi.
        </p>
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

<!-- PROVEDENI OVERLAY -->
<div class="overlay-provedeni" id="provedeniOverlay">
  <div class="provedeni-box">
    <h3>Provedení</h3>
    <div class="provedeni-grid">
      <div class="provedeni-card" data-value="Látka">Látka</div>
      <div class="provedeni-card" data-value="Kůže">Kůže</div>
      <div class="provedeni-card" data-value="Kombinace">Kombinace</div>
    </div>
    <button class="btn btn-secondary" id="closeProvedeni">×</button>
  </div>
</div>

<!-- CUSTOM CALENDAR -->
<div class="calendar-overlay" id="calendarOverlay">
  <div class="calendar-box">
    <div class="calendar-header">
      <h3 id="calendarTitle">Vyberte datum</h3>
      <div class="calendar-nav">
        <button id="prevMonth">&larr;</button>
        <button id="nextMonth">&rarr;</button>
      </div>
    </div>
    <div id="calendarMonthYear" style="text-align:center;margin-bottom:1rem;font-weight:600;font-size:1.1rem;"></div>
    <div class="calendar-grid" id="calendarGrid"></div>
    <button class="btn btn-secondary" style="display:block;margin:1.5rem auto 0;width:100%;" id="closeCalendar">Zavřít</button>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
  window.WGS_USER_LOGGED_IN = <?php echo $isLoggedIn ? "true" : "false"; ?>;
</script>
<script src="assets/js/logger.js" defer></script>
<script src="assets/js/wgs-map.js" defer></script>
<script src="assets/js/csrf-auto-inject.js" defer></script>
<script src="assets/js/novareklamace.js?v=1762458261" defer></script>
</body>
</html>
<script>
window.addEventListener('load', () => {
  // Pouze hamburger-overlay a menuOverlay, NE calendar-overlay!
  document.querySelectorAll('.hamburger-overlay, #menuOverlay').forEach(el => {
    if (el) {
      el.classList.remove('active');
      el.style.display = 'none';
      el.style.opacity = '0';
      el.style.visibility = 'hidden';
    }
  });
  document.body.classList.remove('hamburger-menu-open');
});
</script>