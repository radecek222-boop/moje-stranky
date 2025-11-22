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

  <!-- PDF.js Library pro parsování PDF -->
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script>
    // Nastavení workerSrc pro PDF.js
    if (typeof pdfjsLib !== 'undefined') {
      pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    }
  </script>

  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/novareklamace.css">
  <link rel="stylesheet" href="assets/css/mobile-responsive.css">


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
  padding: 1.5rem;
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
  padding: 0.7rem 0.4rem;
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
  margin-top: 1rem;
  padding: 1rem;
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
  margin-top: 0.8rem;
  padding: 0.8rem;
  border-left: 4px solid #006600;
  background: #f0fff0;
  font-size: 0.85rem;
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
    <h1 class="hero-title" data-lang-cs="Objednat Servis" data-lang-en="Order Service" data-lang-it="Ordinare Servizio">Objednat Servis</h1>
    <p class="hero-subtitle" data-lang-cs="Rychlý a profesionální servis vašeho nábytku" data-lang-en="Fast and professional furniture service" data-lang-it="Servizio mobili veloce e professionale">Rychlý a profesionální servis vašeho nábytku</p>
  </div>
</section>

<!-- FORM -->
<div class="form-container">
  <div class="form-card">
    
    <?php if (!$isLoggedIn): ?>
    <div id="calculatorBox" style="padding: 1.5rem; margin-bottom: 2rem; border: 2px solid #000000; background: #ffffff; display: none;">
      <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.5rem; font-weight: 300; letter-spacing: 0.1em; margin-bottom: 0.8rem; color: #000000; text-transform: uppercase;" data-lang-cs="Orientační cena servisu" data-lang-en="Estimated Service Price" data-lang-it="Prezzo Stimato del Servizio">Orientační cena servisu</h3>
      <p style="color: #666; font-size: 0.9rem; line-height: 1.5; margin-bottom: 1.5rem;" data-lang-cs="Spočítejte si předběžnou cenu mimozáručního servisu včetně dopravy ještě před odesláním objednávky." data-lang-en="Calculate the preliminary price of out-of-warranty service including shipping before submitting your order." data-lang-it="Calcola il prezzo preliminare del servizio fuori garanzia inclusa la spedizione prima di inviare l'ordine.">Spočítejte si předběžnou cenu mimozáručního servisu včetně dopravy ještě před odesláním objednávky.</p>
      <a href="mimozarucniceny.php" style="display: inline-block; padding: 0.7rem 2rem; background: #000000; color: white; text-decoration: none; font-family: 'Poppins', sans-serif; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; transition: all 0.3s; border: 2px solid #000000;" data-lang-cs="Kalkulačka ceny" data-lang-en="Price Calculator" data-lang-it="Calcolatore di Prezzo">Kalkulačka ceny</a>
    </div>
    <?php endif; ?>

    <?php if (!$isLoggedIn): ?>
    <div id="modeInfo" style="display:none; padding: 0.8rem; margin-bottom: 1.5rem; border-left: 4px solid #555555; background: #f5f5f5; font-size: 0.85rem;">
      <strong id="modeTitle"></strong>
      <p id="modeDescription" style="margin-top: 0.4rem; color: #666;"></p>
    </div>
    <?php endif; ?>

    <?php if (!$isLoggedIn): ?>
    <div id="calculationBox" style="display:none; padding: 1.5rem; margin-bottom: 2rem; border: 2px solid #555555; background: #f5f5f5;">
      <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.3rem; font-weight: 600; margin-bottom: 0.8rem; color: #555555;">
        ✓ <span>Vaše kalkulace z cenové kalkulačky</span>
      </h3>
      <div id="calculationDetails" style="font-size: 0.85rem; line-height: 1.6; color: #333;"></div>
      <div style="margin-top: 1rem; padding-top: 1rem; border-top: 2px solid #555555;">
        <strong style="font-size: 1.1rem; color: #555555;"><span>Celková orientační cena:</span> <span id="calculationTotal"></span></strong>
      </div>
    </div>
    <?php endif; ?>
    
    <!-- ✅ FIX M-4: Přidat action attribute pro fallback (pokud JS selže) -->
    <form id="reklamaceForm" action="app/controllers/save.php" method="POST">

      <!-- PANEL PRO NAHRÁNÍ POVĚŘENÍ - pouze pro přihlášené uživatele -->
      <?php if ($isLoggedIn): ?>
      <div id="povereniBox" style="padding: 1.5rem; margin-bottom: 1.5rem; border: 2px solid #2D5016; background: #f9fdf7; box-shadow: 0 2px 8px rgba(45,80,22,0.1);">
        <h3 style="font-family: 'Poppins', sans-serif; font-size: 1.3rem; font-weight: 600; letter-spacing: 0.08em; margin-bottom: 0.8rem; color: #2D5016; text-transform: uppercase; display: flex; align-items: center; gap: 0.5rem;">
          <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <polyline points="10 9 9 9 8 9"></polyline>
          </svg>
          <span data-lang-cs="Pověření k reklamaci" data-lang-en="Power of Attorney for Claim" data-lang-it="Procura per Reclamo">Pověření k reklamaci</span>
        </h3>
        <p style="color: #555; font-size: 0.9rem; line-height: 1.6; margin-bottom: 1.2rem;" data-lang-cs="Nahrajte pověření od prodejce k této reklamaci ve formátu PDF. Dokument bude připojen k objednávce." data-lang-en="Upload the power of attorney from the seller for this claim in PDF format. The document will be attached to the order." data-lang-it="Carica la procura del venditore per questo reclamo in formato PDF. Il documento verrà allegato all'ordine.">
          Nahrajte pověření od prodejce k této reklamaci ve formátu PDF. Dokument bude připojen k objednávce.
        </p>
        <div style="display: flex; gap: 1rem; align-items: center; flex-wrap: wrap;">
          <button type="button" id="nahrajPovereniBtn" style="display: inline-block; padding: 0.7rem 2rem; background: #2D5016; color: white; border: 2px solid #2D5016; text-decoration: none; font-family: 'Poppins', sans-serif; font-size: 0.8rem; font-weight: 600; letter-spacing: 0.12em; text-transform: uppercase; transition: all 0.3s; cursor: pointer;" data-lang-cs="📄 VYBRAT PDF SOUBOR" data-lang-en="📄 SELECT PDF FILE" data-lang-it="📄 SELEZIONA FILE PDF">
            📄 VYBRAT PDF SOUBOR
          </button>
          <span id="povereniStatus" style="font-size: 0.85rem; color: #666;"></span>
        </div>
        <input type="file" id="povereniInput" accept=".pdf,application/pdf" style="display:none;">
      </div>
      <?php endif; ?>

      <!-- ZÁKLADNÍ ÚDAJE -->
      <div class="form-section">
        <h2 class="section-title" data-lang-cs="Základní údaje" data-lang-en="Basic Information" data-lang-it="Informazioni di Base">Základní údaje</h2>
        <div class="form-grid form-grid-3">
          <div class="form-group">
            <label class="form-label" for="cislo" data-lang-cs="Číslo objednávky/reklamace" data-lang-en="Order/Claim Number" data-lang-it="Numero Ordine/Reclamo">Číslo objednávky/reklamace<?php if ($isLoggedIn) echo " *"; ?></label>
            <input type="text" class="form-control" id="cislo" name="cislo"<?php if ($isLoggedIn) { echo " required"; } else { echo " readonly placeholder='nevyplňuje se' style='background-color: #f5f5f5; cursor: not-allowed;'"; } ?>>
            <select id="fakturace_firma" name="fakturace_firma" style="margin-top:0.5rem; width:33%; height:2rem; font-size:0.85rem; padding:0.3rem; border:1px solid #ddd; border-radius:4px;">
              <option value="CZ" selected>🇨🇿 CZ</option>
              <option value="SK">🇸🇰 SK</option>
            </select>
            <p id="faktura_hint" style="margin-top:0.3rem; font-size:0.8rem; color:#059669; font-style:italic;">Tato objednávka se bude fakturovat na CZ firmu</p>
          </div>
          <div class="form-group">
            <label class="form-label" for="datum_prodeje" data-lang-cs="Datum prodeje" data-lang-en="Sale Date" data-lang-it="Data di Vendita">Datum prodeje<?php if ($isLoggedIn) echo " *"; ?></label>
            <div class="date-input-wrapper">
              <input type="text" class="form-control date-input" id="datum_prodeje" name="datum_prodeje" data-lang-cs-placeholder="DD.MM.RRRR" data-lang-en-placeholder="DD.MM.YYYY" data-lang-it-placeholder="GG.MM.AAAA" placeholder="DD.MM.RRRR"<?php if ($isLoggedIn) echo " required"; ?>>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="datum_reklamace" data-lang-cs="Datum reklamace" data-lang-en="Claim Date" data-lang-it="Data del Reclamo">Datum reklamace<?php if ($isLoggedIn) echo " *"; ?></label>
            <div class="date-input-wrapper">
              <input type="text" class="form-control date-input" id="datum_reklamace" name="datum_reklamace" data-lang-cs-placeholder="DD.MM.RRRR" data-lang-en-placeholder="DD.MM.YYYY" data-lang-it-placeholder="GG.MM.AAAA" placeholder="DD.MM.RRRR"<?php if ($isLoggedIn) echo " required"; ?>>
            </div>
          </div>
        </div>
        <div id="warrantyWarning" style="display:none;"></div>
      </div>
      
      <!-- KONTAKTNÍ ÚDAJE -->
      <div class="form-section">
        <h2 class="section-title" data-lang-cs="Kontaktní údaje" data-lang-en="Contact Information" data-lang-it="Informazioni di Contatto">Kontaktní údaje</h2>
        <div class="form-grid form-grid-3">
          <div class="form-group">
            <label class="form-label" for="jmeno" data-lang-cs="Jméno zákazníka *" data-lang-en="Customer Name *" data-lang-it="Nome Cliente *">Jméno zákazníka *</label>
            <input type="text" class="form-control" id="jmeno" name="jmeno" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="email">E-mail *</label>
            <input type="email" class="form-control" id="email" name="email" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="telefon" data-lang-cs="Telefon *" data-lang-en="Phone *" data-lang-it="Telefono *">Telefon *</label>
            <input type="tel" class="form-control" id="telefon" name="telefon" required>
          </div>
        </div>
      </div>
      
     <!-- ADRESA ZÁKAZNÍKA -->
<div class="form-section">
  <h2 class="section-title" data-lang-cs="Adresa zákazníka" data-lang-en="Customer Address" data-lang-it="Indirizzo Cliente">Adresa zákazníka</h2>
  <div class="form-grid form-grid-address">
    <div class="form-group" style="position:relative;">
      <label class="form-label" for="ulice" data-lang-cs="Ulice a číslo popisné" data-lang-en="Street and Number" data-lang-it="Via e Numero">Ulice a číslo popisné</label>
      <input type="text" class="form-control" id="ulice" name="ulice" data-lang-cs-placeholder="Ulice a číslo popisné" data-lang-en-placeholder="Street and Number" data-lang-it-placeholder="Via e Numero" placeholder="Ulice a číslo popisné">
      <div id="autocompleteDropdownUlice" style="display:none;position:absolute;top:100%;margin-top:4px;background:white;border:1px solid #ddd;max-height:200px;overflow-y:auto;z-index:1000;width:100%;box-shadow:0 4px 12px rgba(0,0,0,0.15);border-radius:4px;"></div>
    </div>
    <div class="form-group" style="position:relative;">
      <label class="form-label" for="mesto" data-lang-cs="Město" data-lang-en="City" data-lang-it="Città">Město</label>
      <input type="text" class="form-control" id="mesto" name="mesto" data-lang-cs-placeholder="Město" data-lang-en-placeholder="City" data-lang-it-placeholder="Città" placeholder="Město">
      <div id="autocompleteDropdown" style="display:none;position:absolute;top:100%;margin-top:4px;background:white;border:1px solid #ddd;max-height:200px;overflow-y:auto;z-index:1000;width:100%;box-shadow:0 4px 12px rgba(0,0,0,0.15);border-radius:4px;"></div>
    </div>
    <div class="form-group">
      <label class="form-label" for="psc" data-lang-cs="PSČ" data-lang-en="ZIP Code" data-lang-it="CAP">PSČ</label>
      <input type="text" class="form-control" id="psc" name="psc" data-lang-cs-placeholder="PSČ" data-lang-en-placeholder="ZIP Code" data-lang-it-placeholder="CAP" placeholder="PSČ">
    </div>
    <div class="map-container">
      <div id="mapContainer"></div>
    </div>
  </div>
</div>
      
      <!-- INFORMACE O PRODUKTU -->
      <div class="form-section">
        <h2 class="section-title" data-lang-cs="Informace o produktu" data-lang-en="Product Information" data-lang-it="Informazioni sul Prodotto">Informace o produktu</h2>
        <div class="form-grid form-grid-3">
          <div class="form-group">
            <label class="form-label" for="model" data-lang-cs="Model" data-lang-en="Model" data-lang-it="Modello">Model</label>
            <input type="text" class="form-control" id="model" name="model">
          </div>
          <div class="form-group">
            <label class="form-label" for="provedeni" data-lang-cs="Provedení" data-lang-en="Version" data-lang-it="Versione">Provedení</label>
            <div class="provedeni-group">
              <input type="text" class="form-control provedeni-input" id="provedeni" name="provedeni" data-lang-cs-placeholder="Vyberte..." data-lang-en-placeholder="Select..." data-lang-it-placeholder="Seleziona..." placeholder="Vyberte..." readonly>
              <button type="button" class="btn-select" id="selectProvedeniBtn" data-lang-cs="VYBRAT" data-lang-en="SELECT" data-lang-it="SELEZIONA">VYBRAT</button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label" for="barva" data-lang-cs="Označení barvy" data-lang-en="Color Code" data-lang-it="Codice Colore">Označení barvy</label>
            <input type="text" class="form-control" id="barva" name="barva" data-lang-cs-placeholder="Např. BF12" data-lang-en-placeholder="E.g. BF12" data-lang-it-placeholder="Es. BF12" placeholder="Např. BF12">
          </div>
        </div>

        <?php if ($isLoggedIn): ?>
        <div class="form-grid" style="margin-top:1.5rem;">
          <div class="form-group">
            <label class="form-label" for="info_prodejce" data-lang-cs="Doplňující informace od prodejce" data-lang-en="Additional Information from Seller" data-lang-it="Informazioni Aggiuntive dal Venditore">Doplňující informace od prodejce</label>
            <textarea class="form-control" id="doplnujici_info" name="doplnujici_info" data-lang-cs-placeholder="Doplňující informace od prodejce..." data-lang-en-placeholder="Additional information from seller..." data-lang-it-placeholder="Informazioni aggiuntive dal venditore..." placeholder="Doplňující informace od prodejce..."></textarea>
          </div>
        </div>
        <?php endif; ?>
      </div>
      
      <!-- POPIS PROBLÉMU -->
      <div class="form-section">
        <h2 class="section-title" data-lang-cs="Popis problému" data-lang-en="Problem Description" data-lang-it="Descrizione del Problema">Popis problému</h2>
        <div class="form-grid">
          <div class="form-group">
            <label class="form-label" for="popis_problemu" data-lang-cs="Popis problému od zákazníka" data-lang-en="Problem Description from Customer" data-lang-it="Descrizione del Problema dal Cliente">Popis problému od zákazníka</label>
            <textarea class="form-control" id="popis_problemu" name="popis_problemu" data-lang-cs-placeholder="Popis problému od zákazníka..." data-lang-en-placeholder="Problem description from customer..." data-lang-it-placeholder="Descrizione del problema dal cliente..." placeholder="Popis problému od zákazníka..." required></textarea>
          </div>
        </div>
      </div>
      
      <!-- FOTODOKUMENTACE -->
      <div class="form-section">
        <h2 class="section-title" data-lang-cs="Fotodokumentace" data-lang-en="Photo Documentation" data-lang-it="Documentazione Fotografica">Fotodokumentace</h2>
        <div class="photo-upload-area">
          <button type="button" class="btn-photo" id="uploadPhotosBtn" data-lang-cs="VYBRAT FOTOGRAFIE" data-lang-en="SELECT PHOTOS" data-lang-it="SELEZIONA FOTO">VYBRAT FOTOGRAFIE</button>
          <p class="photo-info" data-lang-cs="Max. 10 fotografií • automatická komprese" data-lang-en="Max. 10 photos • automatic compression" data-lang-it="Max. 10 foto • compressione automatica">Max. 10 fotografií • automatická komprese</p>
          <input type="file" id="photoInput" accept="image/*" multiple style="display:none;">
        </div>
        <div id="photoPreviewMain"></div>
      </div>

      <!-- GDPR CONSENT - pouze pro neregistrované uživatele -->
      <?php if (!$isLoggedIn): ?>
      <div class="form-consent" style="margin: 1rem 0 0.8rem 0; padding: 0.5rem 0.8rem; border: 1px solid #ddd; background: #f9f9f9; text-align: center;">
        <label style="display: inline-flex; align-items: center; cursor: pointer; font-size: 0.55rem; line-height: 1.3; color: #666;">
          <input type="checkbox" id="gdpr_consent" name="gdpr_consent" required style="margin-right: 0.5rem; flex-shrink: 0;">
          <span data-lang-cs="Souhlasím se zpracováním osobních údajů společností White Glove Service, s.r.o." data-lang-en="I agree with the processing of personal data by White Glove Service, s.r.o." data-lang-it="Acconsento al trattamento dei dati personali da parte di White Glove Service, s.r.o.">Souhlasím se zpracováním osobních údajů společností White Glove Service, s.r.o.</span>
        </label>
      </div>
      <?php endif; ?>

      <!-- BUTTONS -->
      <div class="form-actions">
        <button type="submit" class="btn" data-lang-cs="ODESLAT POŽADAVEK" data-lang-en="SUBMIT REQUEST" data-lang-it="INVIA RICHIESTA">ODESLAT POŽADAVEK</button>
        <button type="button" class="btn btn-secondary" onclick="window.history.back()" data-lang-cs="ZPĚT" data-lang-en="BACK" data-lang-it="INDIETRO">ZPĚT</button>
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
        <p class="footer-text" data-lang-cs="Specializovaný servis Natuzzi." data-lang-en="Specialized Natuzzi Service." data-lang-it="Servizio Specializzato Natuzzi.">
          Specializovaný servis Natuzzi.
        </p>
      </div>
      <div class="footer-column">
        <h2 class="footer-title" data-lang-cs="Kontakt" data-lang-en="Contact" data-lang-it="Contatto">Kontakt</h2>
        <p class="footer-text">
          <strong>Tel:</strong> <a href="tel:+420725965826" class="footer-link">+420 725 965 826</a><br>
          <strong>Email:</strong> <a href="mailto:reklamace@wgs-service.cz" class="footer-link">reklamace@wgs-service.cz</a>
        </p>
      </div>
      <div class="footer-column">
        <h2 class="footer-title" data-lang-cs="Adresa" data-lang-en="Address" data-lang-it="Indirizzo">Adresa</h2>
        <p class="footer-text">
          Do Dubče 364, Běchovice 190 11 CZ
        </p>
      </div>
    </div>
    <div class="footer-bottom">
      <p>
        <span data-lang-cs="&copy; 2025 White Glove Service. Všechna práva vyhrazena." data-lang-en="&copy; 2025 White Glove Service. All rights reserved." data-lang-it="&copy; 2025 White Glove Service. Tutti i diritti riservati.">&copy; 2025 White Glove Service. Všechna práva vyhrazena.</span>
        <span aria-hidden="true"> • </span>
        <a href="gdpr.php" class="footer-link" data-lang-cs="Zpracování osobních údajů (GDPR)" data-lang-en="Personal Data Processing (GDPR)" data-lang-it="Trattamento dei Dati Personali (GDPR)">Zpracování osobních údajů (GDPR)</a>
      </p>
    </div>
  </div>
</footer>

<!-- PROVEDENI OVERLAY -->
<div class="overlay-provedeni" id="provedeniOverlay">
  <div class="provedeni-box">
    <h3 data-lang-cs="Provedení" data-lang-en="Version" data-lang-it="Versione">Provedení</h3>
    <div class="provedeni-grid">
      <div class="provedeni-card" data-value="Látka" data-lang-cs="Látka" data-lang-en="Fabric" data-lang-it="Tessuto">Látka</div>
      <div class="provedeni-card" data-value="Kůže" data-lang-cs="Kůže" data-lang-en="Leather" data-lang-it="Pelle">Kůže</div>
      <div class="provedeni-card" data-value="Kombinace" data-lang-cs="Kombinace" data-lang-en="Combination" data-lang-it="Combinazione">Kombinace</div>
    </div>
    <button class="btn btn-secondary" id="closeProvedeni">×</button>
  </div>
</div>

<!-- CUSTOM CALENDAR -->
<div class="calendar-overlay" id="calendarOverlay">
  <div class="calendar-box">
    <div class="calendar-header">
      <h3 id="calendarTitle" data-lang-cs="Vyberte datum" data-lang-en="Select Date" data-lang-it="Seleziona Data">Vyberte datum</h3>
      <div class="calendar-nav">
        <button id="prevMonth">&larr;</button>
        <button id="nextMonth">&rarr;</button>
      </div>
    </div>
    <div id="calendarMonthYear" style="text-align:center;margin-bottom:1rem;font-weight:600;font-size:1.1rem;"></div>
    <div class="calendar-grid" id="calendarGrid"></div>
    <button class="btn btn-secondary" style="display:block;margin:1.5rem auto 0;width:100%;" id="closeCalendar" data-lang-cs="Zavřít" data-lang-en="Close" data-lang-it="Chiudi">Zavřít</button>
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