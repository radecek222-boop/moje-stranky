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
  <link rel="stylesheet" href="assets/css/mobile-responsive.min.css">

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
    <h1 class="hero-title" data-lang-cs="Ceník služeb" data-lang-en="Price List" data-lang-it="Listino Prezzi">Ceník služeb</h1>
    <div class="hero-subtitle" data-lang-cs="White Glove Service - Natuzzi servis" data-lang-en="White Glove Service - Natuzzi service" data-lang-it="White Glove Service - Servizio Natuzzi">White Glove Service - Natuzzi servis</div>
  </div>
</section>

<!-- CONTENT SEKCE -->
<section class="content-section">
  <div class="container">

    <!-- KALKULAČKA CENY -->
    <div class="calculator-section" id="kalkulacka">
      <h2 class="section-title" data-lang-cs="Kalkulace ceny služby" data-lang-en="Service Price Calculation" data-lang-it="Calcolo del Prezzo del Servizio">Kalkulace ceny služby</h2>
      <p class="section-text" data-lang-cs="Odpovězte na několik jednoduchých otázek a zjistěte orientační cenu servisu." data-lang-en="Answer a few simple questions and find out the estimated price of the service." data-lang-it="Rispondi ad alcune semplici domande e scopri il prezzo stimato del servizio.">
        Odpovězte na několik jednoduchých otázek a zjistěte orientační cenu servisu.
      </p>

      <!-- Progress Indicator -->
      <div class="wizard-progress" id="wizard-progress">
        <div class="progress-step active" data-step="1">
          <span class="step-number">1</span>
          <span class="step-label" data-lang-cs="Adresa" data-lang-en="Address" data-lang-it="Indirizzo">Adresa</span>
        </div>
        <div class="progress-step" data-step="2">
          <span class="step-number">2</span>
          <span class="step-label" data-lang-cs="Typ servisu" data-lang-en="Service Type" data-lang-it="Tipo di Servizio">Typ servisu</span>
        </div>
        <div class="progress-step" data-step="3">
          <span class="step-number">3</span>
          <span class="step-label" data-lang-cs="Detaily" data-lang-en="Details" data-lang-it="Dettagli">Detaily</span>
        </div>
        <div class="progress-step" data-step="4">
          <span class="step-number">4</span>
          <span class="step-label" data-lang-cs="Souhrn" data-lang-en="Summary" data-lang-it="Riepilogo">Souhrn</span>
        </div>
      </div>

      <!-- KROK 1: Zadání adresy -->
      <div class="wizard-step" id="step-address" style="display: block;">
        <h3 class="step-title" data-lang-cs="1. Zadejte adresu zákazníka" data-lang-en="1. Enter Customer Address" data-lang-it="1. Inserisci l'Indirizzo del Cliente">1. Zadejte adresu zákazníka</h3>
        <p class="step-desc" data-lang-cs="Pro výpočet dopravného potřebujeme znát vaši adresu." data-lang-en="We need your address to calculate the transportation cost." data-lang-it="Abbiamo bisogno del tuo indirizzo per calcolare il costo del trasporto.">Pro výpočet dopravného potřebujeme znát vaši adresu.</p>

        <div class="form-group">
          <label for="calc-address" data-lang-cs="Adresa:" data-lang-en="Address:" data-lang-it="Indirizzo:">Adresa:</label>
          <input
            type="text"
            id="calc-address"
            class="calc-input"
            placeholder="Začněte psát adresu (ulice, město)..."
            data-lang-placeholder-cs="Začněte psát adresu (ulice, město)..."
            data-lang-placeholder-en="Start typing address (street, city)..."
            data-lang-placeholder-it="Inizia a digitare l'indirizzo (via, città)..."
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
            <span class="checkbox-label" data-lang-cs="Jedná se o reklamaci – neúčtuje se dopravné" data-lang-en="This is a claim – no transportation fee" data-lang-it="Questo è un reclamo – nessun costo di trasporto">Jedná se o reklamaci – neúčtuje se dopravné</span>
          </label>
        </div>
        <?php endif; ?>

        <div id="distance-result" class="calc-result" style="display: none;">
          <div class="result-box">
            <p><strong data-lang-cs="Vzdálenost z dílny:" data-lang-en="Distance from workshop:" data-lang-it="Distanza dall'officina:">Vzdálenost z dílny:</strong> <span id="distance-value">-</span> km</p>
            <p><strong data-lang-cs="Dopravné (tam a zpět):" data-lang-en="Transportation (round trip):" data-lang-it="Trasporto (andata e ritorno):">Dopravné (tam a zpět):</strong> <span id="transport-cost" class="highlight-price">-</span> €</p>
          </div>
        </div>

        <div class="wizard-buttons">
          <button class="btn-primary" onclick="nextStep()" data-lang-cs="Pokračovat" data-lang-en="Continue" data-lang-it="Continua">Pokračovat</button>
        </div>
      </div>

      <!-- KROK 2: Typ servisu -->
      <div class="wizard-step" id="step-service-type" style="display: none;">
        <h3 class="step-title" data-lang-cs="2. Jaký typ servisu potřebujete?" data-lang-en="2. What type of service do you need?" data-lang-it="2. Che tipo di servizio ti serve?">2. Jaký typ servisu potřebujete?</h3>
        <p class="step-desc" data-lang-cs="Vyberte, co u vás potřebujeme udělat." data-lang-en="Select what we need to do for you." data-lang-it="Seleziona cosa dobbiamo fare per te.">Vyberte, co u vás potřebujeme udělat.</p>

        <div class="radio-group">
          <label class="radio-card">
            <input type="radio" name="service-type" value="diagnostika">
            <div class="radio-content">
              <div class="radio-title" data-lang-cs="Pouze diagnostika / inspekce" data-lang-en="Diagnostic / Inspection Only" data-lang-it="Solo Diagnostica / Ispezione">Pouze diagnostika / inspekce</div>
              <div class="radio-desc" data-lang-cs="Technik provede pouze zjištění rozsahu poškození a posouzení stavu." data-lang-en="Technician will only assess the extent of damage and evaluate the condition." data-lang-it="Il tecnico valuterà solo l'entità del danno e valuterà le condizioni.">Technik provede pouze zjištění rozsahu poškození a posouzení stavu.</div>
              <div class="radio-price">155 €</div>
            </div>
          </label>

          <label class="radio-card">
            <input type="radio" name="service-type" value="calouneni" checked>
            <div class="radio-content">
              <div class="radio-title" data-lang-cs="Čalounické práce" data-lang-en="Upholstery Work" data-lang-it="Lavori di Tappezzeria">Čalounické práce</div>
              <div class="radio-desc" data-lang-cs="Oprava včetně rozčalounění konstrukce (sedáky, opěrky, područky)." data-lang-en="Repair including disassembly of structure (seats, backrests, armrests)." data-lang-it="Riparazione compreso smontaggio della struttura (sedili, schienali, braccioli).">Oprava včetně rozčalounění konstrukce (sedáky, opěrky, područky).</div>
              <div class="radio-price" data-lang-cs="Od 190 €" data-lang-en="From 190 €" data-lang-it="Da 190 €">Od 190 €</div>
            </div>
          </label>

          <label class="radio-card">
            <input type="radio" name="service-type" value="mechanika">
            <div class="radio-content">
              <div class="radio-title" data-lang-cs="Mechanické opravy" data-lang-en="Mechanical Repairs" data-lang-it="Riparazioni Meccaniche">Mechanické opravy</div>
              <div class="radio-desc" data-lang-cs="Oprava mechanismů (relax, výsuv) bez rozčalounění." data-lang-en="Repair of mechanisms (relax, slide) without disassembly." data-lang-it="Riparazione di meccanismi (relax, scorrimento) senza smontaggio.">Oprava mechanismů (relax, výsuv) bez rozčalounění.</div>
              <div class="radio-price" data-lang-cs="155 € / díl" data-lang-en="155 € / part" data-lang-it="155 € / parte">155 € / díl</div>
            </div>
          </label>

          <label class="radio-card">
            <input type="radio" name="service-type" value="kombinace">
            <div class="radio-content">
              <div class="radio-title" data-lang-cs="Kombinace čalounění + mechaniky" data-lang-en="Upholstery + Mechanics Combination" data-lang-it="Combinazione Tappezzeria + Meccanica">Kombinace čalounění + mechaniky</div>
              <div class="radio-desc" data-lang-cs="Komplexní oprava zahrnující čalounění i mechanické části." data-lang-en="Comprehensive repair including both upholstery and mechanical parts." data-lang-it="Riparazione completa comprendente sia tappezzeria che parti meccaniche.">Komplexní oprava zahrnující čalounění i mechanické části.</div>
              <div class="radio-price" data-lang-cs="Dle rozsahu" data-lang-en="Based on scope" data-lang-it="In base all'ambito">Dle rozsahu</div>
            </div>
          </label>
        </div>

        <div class="wizard-buttons">
          <button class="btn-secondary" onclick="previousStep()" data-lang-cs="Zpět" data-lang-en="Back" data-lang-it="Indietro">Zpět</button>
          <button class="btn-primary" onclick="nextStep()" data-lang-cs="Pokračovat" data-lang-en="Continue" data-lang-it="Continua">Pokračovat</button>
        </div>
      </div>

      <!-- KROK 3A: Čalounické práce - počet dílů -->
      <div class="wizard-step" id="step-upholstery" style="display: none;">
        <h3 class="step-title" data-lang-cs="3. Kolik dílů potřebuje přečalounit?" data-lang-en="3. How many parts need reupholstering?" data-lang-it="3. Quante parti necessitano di ritappezzatura?">3. Kolik dílů potřebuje přečalounit?</h3>
        <p class="step-desc" data-lang-cs="Jeden díl = sedák NEBO opěrka NEBO područka NEBO panel. První díl stojí 190€, každý další 70€." data-lang-en="One part = seat OR backrest OR armrest OR panel. First part costs 190€, each additional 70€." data-lang-it="Una parte = sedile O schienale O bracciolo O pannello. La prima parte costa 190€, ogni aggiuntiva 70€.">Jeden díl = sedák NEBO opěrka NEBO područka NEBO panel. První díl stojí 190€, každý další 70€.</p>

        <div class="counter-group">
          <div class="counter-item">
            <label data-lang-cs="Sedáky" data-lang-en="Seats" data-lang-it="Sedili">Sedáky</label>
            <div class="counter-controls">
              <button class="btn-counter" onclick="decrementCounter('sedaky')">−</button>
              <input type="number" id="sedaky" value="0" min="0" max="20" readonly>
              <button class="btn-counter" onclick="incrementCounter('sedaky')">+</button>
            </div>
          </div>

          <div class="counter-item">
            <label data-lang-cs="Opěrky" data-lang-en="Backrests" data-lang-it="Schienali">Opěrky</label>
            <div class="counter-controls">
              <button class="btn-counter" onclick="decrementCounter('operky')">−</button>
              <input type="number" id="operky" value="0" min="0" max="20" readonly>
              <button class="btn-counter" onclick="incrementCounter('operky')">+</button>
            </div>
          </div>

          <div class="counter-item">
            <label data-lang-cs="Područky" data-lang-en="Armrests" data-lang-it="Braccioli">Područky</label>
            <div class="counter-controls">
              <button class="btn-counter" onclick="decrementCounter('podrucky')">−</button>
              <input type="number" id="podrucky" value="0" min="0" max="20" readonly>
              <button class="btn-counter" onclick="incrementCounter('podrucky')">+</button>
            </div>
          </div>

          <div class="counter-item">
            <label data-lang-cs="Panely (zadní/boční)" data-lang-en="Panels (back/side)" data-lang-it="Pannelli (posteriore/laterale)">Panely (zadní/boční)</label>
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
              <div class="checkbox-title" data-lang-cs="Rohový díl (1 modul + 2 díly navíc)" data-lang-en="Corner piece (1 module + 2 extra parts)" data-lang-it="Pezzo angolare (1 modulo + 2 parti extra)">Rohový díl (1 modul + 2 díly navíc)</div>
              <div class="checkbox-price">+ 330 €</div>
            </div>
          </label>

          <label class="checkbox-card">
            <input type="checkbox" id="ottoman">
            <div class="checkbox-content">
              <div class="checkbox-title" data-lang-cs="Ottoman / Lehátko" data-lang-en="Ottoman / Daybed" data-lang-it="Pouf / Divano letto">Ottoman / Lehátko</div>
              <div class="checkbox-price">+ 260 €</div>
            </div>
          </label>
        </div>

        <div class="parts-summary" id="parts-summary">
          <strong data-lang-cs="Celkem dílů:" data-lang-en="Total parts:" data-lang-it="Totale parti:">Celkem dílů:</strong> <span id="total-parts">0</span>
          <span class="price-breakdown" id="parts-price-breakdown"></span>
        </div>

        <div class="wizard-buttons">
          <button class="btn-secondary" onclick="previousStep()" data-lang-cs="Zpět" data-lang-en="Back" data-lang-it="Indietro">Zpět</button>
          <button class="btn-primary" onclick="nextStep()" data-lang-cs="Pokračovat" data-lang-en="Continue" data-lang-it="Continua">Pokračovat</button>
        </div>
      </div>

      <!-- KROK 3B: Mechanické práce -->
      <div class="wizard-step" id="step-mechanics" style="display: none;">
        <h3 class="step-title" data-lang-cs="3. Mechanické části" data-lang-en="3. Mechanical Parts" data-lang-it="3. Parti Meccaniche">3. Mechanické části</h3>
        <p class="step-desc" data-lang-cs="Vyberte, které mechanické části potřebují opravu." data-lang-en="Select which mechanical parts need repair." data-lang-it="Seleziona quali parti meccaniche necessitano di riparazione.">Vyberte, které mechanické části potřebují opravu.</p>

        <div class="counter-group">
          <div class="counter-item">
            <label data-lang-cs="Relax mechanismy" data-lang-en="Relax mechanisms" data-lang-it="Meccanismi relax">Relax mechanismy</label>
            <div class="counter-controls">
              <button class="btn-counter" onclick="decrementCounter('relax')">−</button>
              <input type="number" id="relax" value="0" min="0" max="10" readonly>
              <button class="btn-counter" onclick="incrementCounter('relax')">+</button>
            </div>
            <div class="counter-price" data-lang-cs="70 € / kus" data-lang-en="70 € / piece" data-lang-it="70 € / pezzo">70 € / kus</div>
          </div>

          <div class="counter-item">
            <label data-lang-cs="Výsuvné mechanismy" data-lang-en="Sliding mechanisms" data-lang-it="Meccanismi scorrevoli">Výsuvné mechanismy</label>
            <div class="counter-controls">
              <button class="btn-counter" onclick="decrementCounter('vysuv')">−</button>
              <input type="number" id="vysuv" value="0" min="0" max="10" readonly>
              <button class="btn-counter" onclick="incrementCounter('vysuv')">+</button>
            </div>
            <div class="counter-price" data-lang-cs="70 € / kus" data-lang-en="70 € / piece" data-lang-it="70 € / pezzo">70 € / kus</div>
          </div>
        </div>

        <div class="wizard-buttons">
          <button class="btn-secondary" onclick="previousStep()" data-lang-cs="Zpět" data-lang-en="Back" data-lang-it="Indietro">Zpět</button>
          <button class="btn-primary" onclick="nextStep()" data-lang-cs="Pokračovat" data-lang-en="Continue" data-lang-it="Continua">Pokračovat</button>
        </div>
      </div>

      <!-- KROK 4: Další parametry -->
      <div class="wizard-step" id="step-extras" style="display: none;">
        <h3 class="step-title" data-lang-cs="4. Další parametry" data-lang-en="4. Additional Parameters" data-lang-it="4. Parametri Aggiuntivi">4. Další parametry</h3>
        <p class="step-desc" data-lang-cs="Poslední detaily pro přesný výpočet ceny." data-lang-en="Last details for accurate price calculation." data-lang-it="Ultimi dettagli per un calcolo preciso del prezzo.">Poslední detaily pro přesný výpočet ceny.</p>

        <div class="checkbox-group">
          <label class="checkbox-card">
            <input type="checkbox" id="tezky-nabytek">
            <div class="checkbox-content">
              <div class="checkbox-title" data-lang-cs="Nábytek je těžší než 50 kg" data-lang-en="Furniture weighs more than 50 kg" data-lang-it="Mobile pesa più di 50 kg">Nábytek je těžší než 50 kg</div>
              <div class="checkbox-desc" data-lang-cs="Bude potřeba druhá osoba pro manipulaci" data-lang-en="A second person will be needed for handling" data-lang-it="Sarà necessaria una seconda persona per la manipolazione">Bude potřeba druhá osoba pro manipulaci</div>
              <div class="checkbox-price">+ 80 €</div>
            </div>
          </label>

          <label class="checkbox-card">
            <input type="checkbox" id="material">
            <div class="checkbox-content">
              <div class="checkbox-title" data-lang-cs="Materiál dodán od WGS" data-lang-en="Material supplied by WGS" data-lang-it="Materiale fornito da WGS">Materiál dodán od WGS</div>
              <div class="checkbox-desc" data-lang-cs="Výplně (vata, pěna) z naší zásoby" data-lang-en="Fillings (batting, foam) from our stock" data-lang-it="Imbottiture (ovatta, schiuma) dal nostro magazzino">Výplně (vata, pěna) z naší zásoby</div>
              <div class="checkbox-price">+ 40 €</div>
            </div>
          </label>
        </div>

        <div class="wizard-buttons">
          <button class="btn-secondary" onclick="previousStep()" data-lang-cs="Zpět" data-lang-en="Back" data-lang-it="Indietro">Zpět</button>
          <button class="btn-primary" onclick="nextStep()" data-lang-cs="Zobrazit souhrn" data-lang-en="Show Summary" data-lang-it="Mostra Riepilogo">Zobrazit souhrn</button>
        </div>
      </div>

      <!-- KROK 5: Cenový souhrn -->
      <div class="wizard-step" id="step-summary" style="display: none;">
        <h3 class="step-title" data-lang-cs="Orientační cena servisu" data-lang-en="Estimated Service Price" data-lang-it="Prezzo Stimato del Servizio">Orientační cena servisu</h3>

        <div class="price-summary-box">
          <div id="summary-details">
            <!-- Načteno dynamicky JavaScriptem -->
          </div>

          <div class="summary-line total">
            <span><strong data-lang-cs="CELKOVÁ CENA:" data-lang-en="TOTAL PRICE:" data-lang-it="PREZZO TOTALE:">CELKOVÁ CENA:</strong></span>
            <span id="grand-total" class="total-price"><strong>0 €</strong></span>
          </div>

          <div class="summary-note">
            <strong data-lang-cs="Upozornění:" data-lang-en="Notice:" data-lang-it="Avviso:">Upozornění:</strong> <span data-lang-cs="Ceny jsou orientační a vztahují se pouze na práci. Originální materiál z továrny Natuzzi a náhradní mechanické díly se účtují zvlášť podle skutečné spotřeby." data-lang-en="Prices are indicative and apply only to labor. Original material from Natuzzi factory and replacement mechanical parts are charged separately based on actual consumption." data-lang-it="I prezzi sono indicativi e si applicano solo alla manodopera. Il materiale originale della fabbrica Natuzzi e le parti meccaniche di ricambio vengono addebitati separatamente in base al consumo effettivo.">Ceny jsou orientační a vztahují se <strong>pouze na práci</strong>.
            Originální materiál z továrny Natuzzi a náhradní mechanické díly se účtují zvlášť podle skutečné spotřeby.</span>
          </div>
        </div>

        <div class="wizard-buttons">
          <button class="btn-secondary" onclick="previousStep()" data-lang-cs="Zpět" data-lang-en="Back" data-lang-it="Indietro">Zpět</button>
          <button class="btn-primary" onclick="exportovatCenikPDF()" data-lang-cs="Export do PDF" data-lang-en="Export to PDF" data-lang-it="Esporta in PDF">Export do PDF</button>
          <button class="btn-primary" onclick="resetovatKalkulacku()" data-lang-cs="Nová kalkulace" data-lang-en="New Calculation" data-lang-it="Nuovo Calcolo">Nová kalkulace</button>
        </div>
      </div>

    </div>

    <hr style="margin: 60px 0; border: none; border-top: 2px dashed rgba(44, 62, 80, 0.2);">

    <div class="section-intro">
      <h2 class="section-title" data-lang-cs="Přehled služeb a cen" data-lang-en="Service Overview and Prices" data-lang-it="Panoramica dei Servizi e Prezzi">Přehled služeb a cen</h2>

      <p class="section-text" data-lang-cs="Níže naleznete kompletní ceník našich služeb. Všechny ceny jsou uvedeny v EUR a platí od 1.1.2026. Účtovaná cena bude přepočtena na Kč podle aktuálního kurzu. Primárně přijímáme zakázky v lokalitě 150km od dílny." data-lang-en="Below you will find a complete price list of our services. All prices are in EUR and valid from 1.1.2026. The charged price will be converted to CZK according to the current exchange rate. We primarily accept orders within 150km from our workshop." data-lang-it="Di seguito troverai un listino prezzi completo dei nostri servizi. Tutti i prezzi sono in EUR e validi dal 1.1.2026. Il prezzo addebitato sarà convertito in CZK secondo il tasso di cambio corrente. Accettiamo principalmente ordini entro 150 km dalla nostra officina.">
        Níže naleznete kompletní ceník našich služeb. Všechny ceny jsou uvedeny v EUR a platí od 1.1.2026.
        Účtovaná cena bude přepočtena na Kč podle aktuálního kurzu. Primárně přijímáme zakázky v lokalitě 150km od dílny.
      </p>

      <p class="section-text note">
        <strong data-lang-cs="Poznámka:" data-lang-en="Note:" data-lang-it="Nota:">Poznámka:</strong> <span data-lang-cs="Všechny ceny jsou uvedeny za práci BEZ materiálu. Materiál se účtuje zvlášť. Konečná cena může být ovlivněna složitostí opravy, dostupností materiálu a vzdáleností od naší dílny. Pro přesnou cenovou nabídku nás prosím kontaktujte." data-lang-en="All prices are for labor WITHOUT material. Material is charged separately. The final price may be influenced by repair complexity, material availability, and distance from our workshop. For an accurate quote, please contact us." data-lang-it="Tutti i prezzi sono per la manodopera SENZA materiale. Il materiale viene addebitato separatamente. Il prezzo finale può essere influenzato dalla complessità della riparazione, dalla disponibilità del materiale e dalla distanza dalla nostra officina. Per un preventivo accurato, contattaci.">Všechny ceny jsou uvedeny za práci BEZ materiálu. Materiál se účtuje zvlášť.
        Konečná cena může být ovlivněna složitostí opravy, dostupností materiálu a vzdáleností od naší dílny.
        Pro přesnou cenovou nabídku nás prosím kontaktujte.</span>
      </p>
    </div>

    <!-- Loading Indicator -->
    <div id="loading-indicator" style="text-align: center; padding: 40px;">
      <div class="spinner"></div>
      <p data-lang-cs="Načítám ceník..." data-lang-en="Loading price list..." data-lang-it="Caricamento listino prezzi...">Načítám ceník...</p>
    </div>

    <!-- Pricing Grid -->
    <div id="pricing-grid" style="display: none;"></div>

    <?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
    <!-- Admin Tlačítka -->
    <div class="admin-actions" style="margin-top: 40px; text-align: center;">
      <button class="btn-admin" onclick="pridatPolozku()" data-lang-cs="+ Přidat novou položku" data-lang-en="+ Add New Item" data-lang-it="+ Aggiungi Nuovo Elemento">+ Přidat novou položku</button>
    </div>
    <?php endif; ?>

    <!-- Kontaktní informace -->
    <div class="pricing-footer">
      <h3 data-lang-cs="Máte dotazy k cenám?" data-lang-en="Questions about pricing?" data-lang-it="Domande sui prezzi?">Máte dotazy k cenám?</h3>
      <p data-lang-cs="Neváhejte nás kontaktovat pro nezávaznou cenovou nabídku." data-lang-en="Feel free to contact us for a non-binding quote." data-lang-it="Non esitare a contattarci per un preventivo non vincolante.">Neváhejte nás kontaktovat pro nezávaznou cenovou nabídku.</p>
      <p class="contact-info">
        <strong data-lang-cs="Tel:" data-lang-en="Phone:" data-lang-it="Tel:">Tel:</strong> <a href="tel:+420725965826">+420 725 965 826</a><br>
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
        <h2 class="footer-title" data-lang-cs="Kontakt" data-lang-en="Contact" data-lang-it="Contatto">Kontakt</h2>
        <p class="footer-text">
          <strong data-lang-cs="Tel:" data-lang-en="Phone:" data-lang-it="Tel:">Tel:</strong> <a href="tel:+420725965826" class="footer-link">+420 725 965 826</a><br>
          <strong>Email:</strong> <a href="mailto:reklamace@wgs-service.cz" class="footer-link">reklamace@wgs-service.cz</a>
        </p>
      </div>

      <!-- ADRESA -->
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

<!-- Edit Modal (pouze pro adminy) -->
<?php if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true): ?>
<div id="edit-modal" class="modal" style="display: none;">
  <div class="modal-content">
    <span class="modal-close" onclick="zavritModal()">&times;</span>
    <h2 id="modal-title" data-lang-cs="Upravit položku" data-lang-en="Edit Item" data-lang-it="Modifica Elemento">Upravit položku</h2>

    <form id="edit-form" onsubmit="ulozitPolozku(event)">
      <input type="hidden" id="item-id" name="id">

      <div class="form-group">
        <label for="service-name" data-lang-cs="Název služby *" data-lang-en="Service Name *" data-lang-it="Nome Servizio *">Název služby *</label>
        <input type="text" id="service-name" name="service_name" required>
      </div>

      <div class="form-group">
        <label for="description" data-lang-cs="Popis" data-lang-en="Description" data-lang-it="Descrizione">Popis</label>
        <textarea id="description" name="description" rows="4"></textarea>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label for="price-from" data-lang-cs="Cena od" data-lang-en="Price from" data-lang-it="Prezzo da">Cena od</label>
          <input type="number" step="0.01" id="price-from" name="price_from">
        </div>

        <div class="form-group">
          <label for="price-to" data-lang-cs="Cena do" data-lang-en="Price to" data-lang-it="Prezzo a">Cena do</label>
          <input type="number" step="0.01" id="price-to" name="price_to">
        </div>

        <div class="form-group">
          <label for="price-unit" data-lang-cs="Měna" data-lang-en="Currency" data-lang-it="Valuta">Měna</label>
          <select id="price-unit" name="price_unit">
            <option value="€">€ (EUR)</option>
            <option value="Kč">Kč (CZK)</option>
          </select>
        </div>
      </div>

      <div class="form-group">
        <label for="category" data-lang-cs="Kategorie" data-lang-en="Category" data-lang-it="Categoria">Kategorie</label>
        <input type="text" id="category" name="category">
      </div>

      <div class="form-group">
        <label>
          <input type="checkbox" id="is-active" name="is_active" value="1" checked>
          <span data-lang-cs="Aktivní (zobrazit na webu)" data-lang-en="Active (display on website)" data-lang-it="Attivo (mostra sul sito web)">Aktivní (zobrazit na webu)</span>
        </label>
      </div>

      <div class="modal-actions">
        <button type="button" class="btn-secondary" onclick="zavritModal()" data-lang-cs="Zrušit" data-lang-en="Cancel" data-lang-it="Annulla">Zrušit</button>
        <button type="submit" class="btn-primary" data-lang-cs="Uložit" data-lang-en="Save" data-lang-it="Salva">Uložit</button>
        <button type="button" class="btn-danger" onclick="smazatPolozku()" id="delete-btn" style="display: none;" data-lang-cs="Smazat" data-lang-en="Delete" data-lang-it="Elimina">Smazat</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<!-- External JavaScript -->
<script src="assets/js/logger.js" defer></script>
<script src="assets/js/wgs-map.js" defer></script>

<!-- Translations for pricing page -->
<script src="assets/js/wgs-translations-cenik.js"></script>

<script src="assets/js/cenik.js" defer></script>

<!-- PDF Export Libraries -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js" defer></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js" defer></script>

<script src="assets/js/cenik-calculator.min.js" defer></script>

<?php renderHeatmapTracker(); ?>
</body>
</html>
