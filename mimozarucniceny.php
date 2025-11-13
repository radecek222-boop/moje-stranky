<?php require_once "init.php"; ?>
<!DOCTYPE html>
<html lang="cs" data-page="mimozarucniceny">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="theme-color" content="#000000">
  <meta name="description" content="Kalkulačka ceny mimozáručního servisu White Glove Service. Spočítejte si orientační náklady na opravu nábytku včetně dopravy a náhradních dílů.">
  <meta name="csrf-token" content="<?php echo generateCSRFToken(); ?>">
  <title>Kalkulačka ceny servisu | WGS</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="preconnect" href="https://maps.geoapify.com">
  <link rel="preconnect" href="https://unpkg.com">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=optional" rel="stylesheet">
  
  <!-- Leaflet Map -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
  
  <!-- Preload critical CSS -->
  <link rel="preload" href="assets/css/styles.min.css" as="style">
  <link rel="preload" href="assets/css/mimozarucniceny.min.css" as="style">

  <!-- External CSS -->
    <!-- Unified Design System -->
  <link rel="stylesheet" href="assets/css/styles.min.css">
  <link rel="stylesheet" href="assets/css/mimozarucniceny.min.css">
</head>

<body>


<div class="menu-overlay" id="menuOverlay"></div>

<main>
<section class="hero">
  <div>
    <h1 class="hero-title" data-lang-cs="Kalkulačka Ceny" data-lang-en="Price Calculator" data-lang-it="Calcolatore dei Prezzi">Kalkulačka Ceny</h1>
    <p class="hero-subtitle" data-lang-cs="Spočítejte si orientační cenu mimozáručního servisu" data-lang-en="Calculate the approximate price of out-of-warranty service" data-lang-it="Calcola il prezzo approssimativo del servizio fuori garanzia">Spočítejte si orientační cenu mimozáručního servisu</p>
  </div>
</section>

<div class="calculator-container">
  <div class="calculator-card">
    
    <!-- STEP 1 -->
    <div class="step-section">
      <div class="step-header">
        <div class="step-number">1</div>
        <div>
          <div class="step-title" data-lang-cs="Místo servisu" data-lang-en="Service Location" data-lang-it="Posizione del Servizio">Místo servisu</div>
        </div>
      </div>
      
      <p class="step-description" data-lang-cs="Vyberte, zda si přejete servis u vás doma, nebo zda nám výrobek dovezete na naši adresu." data-lang-en="Choose whether you want service at your home or whether you want the product delivered to our address." data-lang-it="Scegli se desideri ricevere il servizio a domicilio oppure se preferisci che il prodotto venga consegnato al nostro indirizzo.">Vyberte, zda si přejete servis u vás doma, nebo zda nám výrobek dovezete na naši adresu.</p>
      
      <div class="radio-group">
        <label class="radio-option">
          <input type="radio" name="service_location" value="home" checked>
          <div class="radio-checkmark"></div>
          <div class="radio-content">
            <div class="radio-title" data-lang-cs="Servis u zákazníka doma" data-lang-en="Customer service at home" data-lang-it="Servizio clienti a domicilio">Servis u zákazníka doma</div>
            <div class="radio-description" data-lang-cs="Naši technici přijedou k vám domů a provedou opravu na místě. Zahrnuje dopravu tam i zpět." data-lang-en="Our technicians will come to your home and perform the repair on site. This includes transportation there and back." data-lang-it="I nostri tecnici verranno a casa tua ed eseguiranno la riparazione in loco. Il servizio include il trasporto andata e ritorno.">Naši technici přijedou k vám domů a provedou opravu na místě. Zahrnuje dopravu tam i zpět.</div>
          </div>
        </label>
        
        <label class="radio-option">
          <input type="radio" name="service_location" value="workshop">
          <div class="radio-checkmark"></div>
          <div class="radio-content">
            <div class="radio-title" data-lang-cs="Dovoz na naši adresu" data-lang-en="Delivery to our address" data-lang-it="Consegna al nostro indirizzo">Dovoz na naši adresu</div>
            <div class="radio-description" data-lang-cs="Dovezete nám výrobek na adresu: Do Dubče 364, Běchovice 190 11. Doprava se neúčtuje." data-lang-en="You will deliver the product to us at the address: Do Dubče 364, Běchovice 190 11. Shipping is not charged." data-lang-it="Il prodotto ci verrà consegnato all'indirizzo: Do Dubče 364, Běchovice 190 11. La spedizione è gratuita.">Dovezete nám výrobek na adresu: Do Dubče 364, Běchovice 190 11. Doprava se neúčtuje.</div>
          </div>
        </label>
      </div>
    </div>
    
    <!-- STEP 2 - Address -->
    <div class="step-section" id="addressSection">
      <div class="step-header">
        <div class="step-number">2</div>
        <div>
          <div class="step-title" data-lang-cs="Vaše adresa" data-lang-en="Your Address" data-lang-it="Il Tuo Indirizzo">Vaše adresa</div>
        </div>
      </div>
      
      <p class="step-description" data-lang-cs="Zadejte vaši adresu pro výpočet nákladů na dopravu." data-lang-en="Enter your address to calculate shipping costs." data-lang-it="Inserisci il tuo indirizzo per calcolare le spese di spedizione.">Zadejte vaši adresu pro výpočet nákladů na dopravu.</p>
      
      <div class="form-group" style="position:relative;">
        <label class="form-label" data-lang-cs="Ulice a číslo popisné" data-lang-en="Street and House Number" data-lang-it="Numero Civico e Civico">Ulice a číslo popisné</label>
        <input type="text" class="form-control" id="ulice" data-lang-cs-placeholder="Začněte psát adresu..." data-lang-en-placeholder="Start typing address..." data-lang-it-placeholder="Inizia a digitare l'indirizzo..." placeholder="Začněte psát adresu...">
        <div id="autocompleteDropdown" style="display:none;position:absolute;top:100%;margin-top:4px;background:white;border:1px solid #ddd;max-height:200px;overflow-y:auto;z-index:1000;width:100%;box-shadow:0 4px 12px rgba(0,0,0,0.15);border-radius:4px;"></div>
      </div>
      
      <div class="map-container">
        <div id="mapContainer"></div>
      </div>
      
      <div class="distance-info" id="distanceInfo">
        <strong data-lang-cs="Vypočtená vzdálenost" data-lang-en="Calculated distance" data-lang-it="Distanza calcolata">Vypočtená vzdálenost</strong>
        <div id="distanceText"></div>
      </div>
    </div>
    
    <!-- STEP 3 - Service Type -->
    <div class="step-section">
      <div class="step-header">
        <div class="step-number" id="stepNumber3">3</div>
        <div>
          <div class="step-title" data-lang-cs="Typ servisu" data-lang-en="Service Type" data-lang-it="Tipo di Servizio">Typ servisu</div>
        </div>
      </div>
      
      <p class="step-description" data-lang-cs="Vyberte, jaký typ servisu potřebujete." data-lang-en="Choose what type of service you need." data-lang-it="Scegli il tipo di servizio di cui hai bisogno.">Vyberte, jaký typ servisu potřebujete.</p>
      
      <div class="radio-group">
        <label class="radio-option">
          <input type="radio" name="service_type" value="diagnosis_and_repair" checked>
          <div class="radio-checkmark"></div>
          <div class="radio-content">
            <div class="radio-title" data-lang-cs="Diagnostika + oprava při jedné cestě" data-lang-en="Diagnostics + repair in one trip" data-lang-it="Diagnostica + riparazione in un unico viaggio">Diagnostika + oprava při jedné cestě</div>
            <div class="radio-description" data-lang-cs="Technik provede diagnostiku a ihned zahájí opravu. Diagnostika se v tomto případě neúčtuje samostatně." data-lang-en="The technician will perform diagnostics and begin the repair immediately. Diagnostics are not charged separately in this case." data-lang-it="Il tecnico eseguirà la diagnostica e inizierà immediatamente la riparazione. In questo caso, la diagnostica non verrà addebitata separatamente.">Technik provede diagnostiku a ihned zahájí opravu. <strong>Diagnostika se v tomto případě neúčtuje samostatně.</strong></div>
            <div class="radio-price" data-lang-cs="Diagnostika zdarma (zahrnuto v ceně opravy)" data-lang-en="Free diagnostics (included in the repair price)" data-lang-it="Diagnosi gratuita (inclusa nel prezzo della riparazione)">Diagnostika zdarma (zahrnuto v ceně opravy)</div>
          </div>
        </label>
        
        <label class="radio-option">
          <input type="radio" name="service_type" value="diagnosis_only">
          <div class="radio-checkmark"></div>
          <div class="radio-content">
            <div class="radio-title" data-lang-cs="Pouze diagnostika" data-lang-en="Diagnostics only" data-lang-it="Solo diagnostica">Pouze diagnostika</div>
            <div class="radio-description" data-lang-cs="Technik provede pouze diagnostiku a posouzení stavu. Po diagnostice vám zašleme nabídku s cenou opravy." data-lang-en="The technician will only perform diagnostics and condition assessment. After the diagnostics, we will send you a quote." data-lang-it="Il tecnico eseguirà solo la diagnosi e la valutazione delle condizioni. Dopo la diagnosi, ti invieremo un preventivo.">Technik provede pouze diagnostiku a posouzení stavu. Po diagnostice vám zašleme nabídku s cenou opravy a termínem.</div>
            <div class="radio-price">100 €</div>
          </div>
        </label>
        
        <label class="radio-option">
          <input type="radio" name="service_type" value="repair_only">
          <div class="radio-checkmark"></div>
          <div class="radio-content">
            <div class="radio-title" data-lang-cs="Pouze oprava" data-lang-en="Repair only" data-lang-it="Solo riparazione">Pouze oprava</div>
            <div class="radio-description" data-lang-cs="Oprava bez předchozí diagnostiky. Vhodné pouze pokud již znáte přesný rozsah poškození." data-lang-en="Repair without prior diagnostics. Only suitable if you already know the exact extent of the damage." data-lang-it="Riparazione senza diagnosi preventiva. Adatto solo se si conosce già l'entità esatta del danno.">Oprava bez předchozí diagnostiky. Vhodné pouze pokud již znáte přesný rozsah poškození a máte potvrzenou schválenou nabídku.</div>
            <div class="radio-price" data-lang-cs="Podle typu opravy" data-lang-en="By type of repair" data-lang-it="Per tipo di riparazione">Podle typu opravy</div>
          </div>
        </label>
      </div>
    </div>
    
    <!-- STEP 4 - Repair Type -->
    <div class="step-section" id="repairTypeSection">
      <div class="step-header">
        <div class="step-number" id="stepNumber4">4</div>
        <div>
          <div class="step-title" data-lang-cs="Typ opravy" data-lang-en="Repair Type" data-lang-it="Tipo di Riparazione">Typ opravy</div>
        </div>
      </div>
      
      <p class="step-description" data-lang-cs="Vyberte typ poškození, které odpovídá vašemu případu." data-lang-en="Select the type of damage that corresponds to your case." data-lang-it="Seleziona il tipo di danno che corrisponde al tuo caso.">Vyberte typ poškození, které odpovídá vašemu případu.</p>
      
      <div class="radio-group">
        <label class="radio-option">
          <input type="radio" name="repair_type" value="mechanical" checked>
          <div class="radio-checkmark"></div>
          <div class="radio-content">
            <div class="radio-title" data-lang-cs="Mechanické poškození bez rozčalounění" data-lang-en="Mechanical damage without upholstery" data-lang-it="Danni meccanici senza rivestimento">Mechanické poškození bez rozčalounění</div>
            <div class="radio-description" data-lang-cs="Oprava mechanismu, pružin, rámové konstrukce bez nutnosti rozebírání čalounění." data-lang-en="Repair of mechanism, springs, frame structure without the need to disassemble the upholstery." data-lang-it="Riparazione di meccanismi, molle, struttura del telaio senza la necessità di smontare il rivestimento.">Oprava mechanismu, pružin, rámové konstrukce nebo jiných mechanických částí bez nutnosti rozebírání čalounění. <em>Cena nezahrnuje náhradní díly (pokud je nutná výměna).</em></div>
            <div class="radio-price">155 € / díl</div>
          </div>
        </label>
        
        <label class="radio-option">
          <input type="radio" name="repair_type" value="upholstery">
          <div class="radio-checkmark"></div>
          <div class="radio-content">
            <div class="radio-title" data-lang-cs="Oprava včetně rozčalounění" data-lang-en="Repair including reupholstery" data-lang-it="Riparazione inclusa la tappezzeria">Oprava včetně rozčalounění</div>
            <div class="radio-description" data-lang-cs="Oprava vyžadující rozčalounění a následné opětovné čalounění dílů." data-lang-en="Repair requiring dismantling and subsequent re-upholstering of parts." data-lang-it="Riparazione che richiede lo smontaggio e il successivo rivestimento di alcune parti.">Oprava vyžadující rozčalounění a následné opětovné čalounění dílů. <strong>První díl: 190 €, každý další díl: +70 €</strong> (např. 2 dílná sedací souprava = 260 €, 3 díly = 330 €). <em>Cena nezahrnuje materiál a náhradní díly.</em></div>
            <div class="radio-price">190 € + 70 € <span data-lang-cs="za každý další díl" data-lang-en="for each additional part" data-lang-it="per ogni parte aggiuntiva">za každý další díl</span></div>
          </div>
        </label>
      </div>
      
      <div class="form-group" style="margin-top: 1.5rem;">
        <label for="partCount" class="form-label" data-lang-cs="Počet poškozených dílů" data-lang-en="Number of damaged parts" data-lang-it="Numero di parti danneggiate">Počet poškozených dílů</label>
        <input type="number" class="form-control" id="partCount" value="1" min="1" max="20">
      </div>
    </div>
    
    <button class="btn-calculate" id="calculateBtn" data-lang-cs="Spočítat orientační cenu" data-lang-en="Calculate approximate price" data-lang-it="Calcola il prezzo approssimativo">Spočítat orientační cenu</button>
    
    <!-- PRICE SUMMARY -->
    <div class="price-summary" id="priceSummary" style="display:none;">
      <div class="price-summary-title" data-lang-cs="Orientační cena" data-lang-en="Approximate Price" data-lang-it="Prezzo Approssimativo">Orientační cena</div>
      
      <div class="price-row" id="transportRow">
        <span class="price-label" data-lang-cs="Doprava (tam a zpět)" data-lang-en="Transportation (round trip)" data-lang-it="Trasporto (andata e ritorno)">Doprava (tam a zpět)</span>
        <span class="price-value" id="transportPrice">0 €</span>
      </div>
      
      <div class="price-row" id="diagnosisRow">
        <span class="price-label" data-lang-cs="Zjištění rozsahu poškození" data-lang-en="Damage assessment" data-lang-it="Valutazione dei danni">Zjištění rozsahu poškození</span>
        <span class="price-value" id="diagnosisPrice">100 €</span>
      </div>
      
      <div class="price-row" id="repairRow">
        <span class="price-label" id="repairLabel" data-lang-cs="Oprava" data-lang-en="Repair" data-lang-it="Riparazione">Oprava</span>
        <span class="price-value" id="repairPrice">0 €</span>
      </div>
      
      <div class="price-row">
        <span class="price-label price-total" data-lang-cs="Celkem" data-lang-en="Total" data-lang-it="Totale">Celkem</span>
        <span class="price-value price-total" id="totalPrice">0 €</span>
      </div>
      
      <div style="margin-top: 1.5rem; padding: 1rem; background: #fff9f0; border-left: 3px solid #ff9900; font-size: 0.85rem; color: #666;">
        <strong data-lang-cs="Pozor:" data-lang-en="Warning:" data-lang-it="Attenzione:">Pozor:</strong> <span data-lang-cs="Cena nezahrnuje materiál (originální potahy z továrny Natuzzi) a náhradní mechanické díly." data-lang-en="Price does not include materials (original Natuzzi factory covers) and spare mechanical parts." data-lang-it="Il prezzo non include materiali (rivestimenti originali Natuzzi) e ricambi meccanici.">Cena <strong>nezahrnuje</strong> materiál (originální potahy z továrny Natuzzi) a náhradní mechanické díly. Tyto položky budou upřesněny po diagnostice.</span>
      </div>
      
      <a href="#" class="btn-order" id="orderServiceBtn" data-lang-cs="Objednat servis" data-lang-en="Order service" data-lang-it="Ordinare servizio">Objednat servis</a>
    </div>
    
    <!-- INFO BOX -->
    <div class="info-box">
      <div class="info-box-title" data-lang-cs="Důležité informace" data-lang-en="Important information" data-lang-it="Informazioni importanti">Důležité informace</div>
      <div class="info-box-text">
        <strong data-lang-cs="Uvedené ceny nezahrnují materiál a náhradní díly:" data-lang-en="The prices listed do not include materials and spare parts:" data-lang-it="I prezzi indicati non includono materiali e pezzi di ricambio:">Uvedené ceny nezahrnují materiál a náhradní díly:</strong><br><br>
        
        <span data-lang-cs="Pro bližší informace a konzultaci nás kontaktujte na tel." data-lang-en="For more information and consultation, contact us at" data-lang-it="Per ulteriori informazioni e consulenza, contattateci al numero">Pro bližší informace a konzultaci nás kontaktujte na tel.</span> <a href="tel:+420725965826">+420 725 965 826</a> <span data-lang-cs="nebo e-mail" data-lang-en="or email" data-lang-it="o via e-mail">nebo e-mail</span> <a href="mailto:reklamace@wgs-service.cz">reklamace@wgs-service.cz</a>.
      </div>
    </div>
    
  </div>
</div>

<footer class="footer">
  <div class="footer-container">
    <div class="footer-grid">
      <div class="footer-column">
        <h2 class="footer-title">White Glove Service</h2>
        <p class="footer-text" data-lang-cs="Specializovaný servis Natuzzi." data-lang-en="Natuzzi specialized service." data-lang-it="Servizio specializzato Natuzzi.">
          Specializovaný servis Natuzzi.
        </p>
      </div>
      <div class="footer-column">
        <h2 class="footer-title" data-lang-cs="Kontakt" data-lang-en="Contact" data-lang-it="Contatto">Kontakt</h2>
        <p class="footer-text">
          <strong data-lang-cs="Tel:" data-lang-en="Phone:" data-lang-it="Telefono:">Tel:</strong> <a href="tel:+420725965826" class="footer-link">+420 725 965 826</a><br>
          <strong>Email:</strong> <a href="mailto:reklamace@wgs-service.cz" class="footer-link">reklamace@wgs-service.cz</a>
        </p>
      </div>
      <div class="footer-column">
        <h2 class="footer-title" data-lang-cs="Adresa" data-lang-en="Address" data-lang-it="Indirizzo">Adresa</h2>
        <p class="footer-text" data-lang-cs="Do Dubče 364, Běchovice 190 11 CZ" data-lang-en="Do Dubče 364, Běchovice 190 11 CZ" data-lang-it="Do Dubče 364, Běchovice 190 11 CZ">
          Do Dubče 364, Běchovice 190 11 CZ
        </p>
      </div>
    </div>
    <div class="footer-bottom">
      <p data-lang-cs="© 2025 White Glove Service. Všechna práva vyhrazena." data-lang-en="© 2025 White Glove Service. All rights reserved." data-lang-it="© 2025 White Glove Service. Tutti i diritti riservati.">
        &copy; 2025 White Glove Service. Všechna práva vyhrazena.
        <span aria-hidden="true"> • </span>
        <a href="gdpr.php" class="footer-link"
           data-lang-cs="Zpracování osobních údajů (GDPR)"
           data-lang-en="Personal data processing (GDPR)"
           data-lang-it="Trattamento dei dati personali (GDPR)">Zpracování osobních údajů (GDPR)</a>
      </p>
    </div>
  </div>
</footer>

<div class="toast" id="toast"></div>
</main>

<!-- External JavaScript -->
<!-- Logger Utility (must be loaded first) -->
<script src="assets/js/logger.js" defer></script>

<script src="assets/js/mimozarucniceny.js" defer></script>
</body>
</html>