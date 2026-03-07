const logger = window.logger || console;

// Globalni funkce pro navigaci zpet
window.historyBack = function() {
  if (window.history.length > 1) {
    window.history.back();
  } else {
    // Fallback na homepage pokud neni historie
    window.location.href = '/';
  }
};

// FIX L-2: Konstanty pro magic numbers
const CONSTANTS = {
  MAX_PHOTOS: 10,              // Max počet fotek na upload
  MAX_IMAGE_WIDTH: 1200,       // Max šířka obrázku v px
  IMAGE_QUALITY: 0.85,         // JPEG kvalita (0-1)
  AUTOCOMPLETE_DEBOUNCE: 150,  // Debounce pro autocomplete (ms) - sníženo z 300ms pro rychlejší odezvu
  AUTOCOMPLETE_MIN_CHARS: 2,   // Min počet znaků pro autocomplete - zvýšeno z 1 pro méně zbytečných dotazů
  ROUTE_DEBOUNCE: 500,         // Debounce pro route calculation (ms)
  WARRANTY_YEARS: 2,           // Délka záruky v letech
  PSC_LENGTH: 5,               // Délka PSČ
  PHONE_MIN_LENGTH: 9          // Min délka telefonu
};

const WGS = {
  photos: [],
  videoSoubor: null, // Video soubor (File nebo Blob po komprimaci)
  povereniPDF: null, // PDF soubor s pověřením k reklamaci
  map: null,
  // REFACTOR: marker a routeLayer jsou nyní spravovány WGSMap modulem
  companyLocation: window.WGS_COMPANY_LOCATION || { lat: 50.080312092724114, lon: 14.598113797415476 }, // FIX M-3: Konfigurovatelná lokace
  isLoggedIn: false,
  calculateRouteTimeout: null,

  // ⚡ NOTE: Cache a controllers jsou nyní v WGSMap modulu
  // Všechny geocoding, autocomplete a routing funkce nyní používají WGSMap API
  
  init() {
    logger.log('[Start] WGS init...');
    this.checkLoginStatus();
    this.initUserMode();
    this.initCalculationDisplay();
    this.initMobileMenu();
    this.initMap();
    this.initForm();
    this.initPhotos();
    this.initVideos();
    this.initPovereniPDF(); // Inicializace nahrávání PDF pověření
    this.initProvedeni();
    this.initLanguage();
    this.initCustomCalendar();
    this.initTypZakaznika(); // Vzájemně výlučné checkboxy IČO/fyzická osoba
  },

  // Inicializace vzájemně výlučných checkboxů pro typ zákazníka
  initTypZakaznika() {
    const icoCheckbox = document.getElementById('objednavkaICO');
    const fyzickaCheckbox = document.getElementById('objednavkaFyzicka');
    const typZakaznikaWrapper = document.querySelector('.typ-zakaznika-wrapper');

    if (!icoCheckbox || !fyzickaCheckbox) return;

    // Funkce pro odstranění červeného označení při zaškrtnutí
    const odstranitCerveneOznaceni = () => {
      if (typZakaznikaWrapper && (icoCheckbox.checked || fyzickaCheckbox.checked)) {
        typZakaznikaWrapper.style.border = '';
        typZakaznikaWrapper.style.backgroundColor = '';
        typZakaznikaWrapper.style.borderRadius = '';
        typZakaznikaWrapper.style.padding = '';
      }
    };

    // Když zaškrtnu IČO, odškrtnu fyzickou osobu
    icoCheckbox.addEventListener('change', () => {
      if (icoCheckbox.checked) {
        fyzickaCheckbox.checked = false;
      }
      odstranitCerveneOznaceni();
    });

    // Když zaškrtnu fyzickou osobu, odškrtnu IČO
    fyzickaCheckbox.addEventListener('change', () => {
      if (fyzickaCheckbox.checked) {
        icoCheckbox.checked = false;
      }
      odstranitCerveneOznaceni();
    });

    logger.log('[TypZakaznika] Inicializovány vzájemně výlučné checkboxy');
  },
  
  checkLoginStatus() {
    const userToken = localStorage.getItem('wgs_user_token') || sessionStorage.getItem('wgs_user_token');
    this.isLoggedIn = window.WGS_USER_LOGGED_IN !== undefined ? window.WGS_USER_LOGGED_IN : !!userToken;
    logger.log('[User] Logged in:', this.isLoggedIn);
  },
  
  initMap() {
    // REFACTOR: Použití WGSMap modulu místo přímého Leaflet
    if (typeof WGSMap === 'undefined') {
      logger.error('WGSMap module not loaded');
      return;
    }

    this.map = WGSMap.init('mapContainer', {
      center: [49.8, 15.5],
      zoom: 7,
      onInit: (mapInstance) => {
        logger.log('Map initialized via WGSMap');
        this.initAddressGeocoding();
      }
    });

    if (!this.map) {
      logger.error('Map initialization failed');
      return;
    }
  },
  
  initAddressGeocoding() {
    const uliceInput = document.getElementById('ulice');
    const mestoInput = document.getElementById('mesto');
    const pscInput = document.getElementById('psc');
    
    // REFACTOR: Použití WGSMap.addMarker() místo přímého L.marker()
    const updateMapWithGPS = (lat, lon) => {
      WGSMap.removeMarker('customer'); // Odstranit starý marker pokud existuje
      WGSMap.addMarker('customer', [lat, lon], {
        draggable: false
      });
      WGSMap.flyTo([lat, lon], 15);
      logger.log(`[Loc] Map updated to GPS: ${lat}, ${lon}`);
    };
    
    this.updateMapWithGPS = updateMapWithGPS;
    
    // REFACTOR: Použití WGSMap.geocode() místo manuálního fetch
    const geocodeAddress = async () => {
      const ulice = uliceInput.value.trim();
      const mesto = mestoInput.value.trim();
      const psc = pscInput.value.trim();

      if (ulice.toLowerCase().includes('do dubče') && ulice.includes('364')) {
        updateMapWithGPS(50.08026389885034, 14.59812452579323);
        return;
      }

      if (!mesto && !psc) return;

      const address = `${ulice}, ${mesto}, ${psc}, Czech Republic`;

      try {
        const data = await WGSMap.geocode(address);

        if (data && data.features && data.features.length > 0) {
          const [lon, lat] = data.features[0].geometry.coordinates;
          updateMapWithGPS(lat, lon);
        }
      } catch (err) {
        logger.error('Geocoding error:', err);
      }
    };
    
    this.geocodeAddress = geocodeAddress;
    
    if (uliceInput) uliceInput.addEventListener('blur', geocodeAddress);
    if (mestoInput) mestoInput.addEventListener('blur', geocodeAddress);
    if (pscInput) pscInput.addEventListener('blur', geocodeAddress);
    
    this.initAutocomplete();
  },
  
  initAutocomplete() {
    const uliceInput = document.getElementById('ulice');
    const mestoInput = document.getElementById('mesto');
    const dropdownUlice = document.getElementById('autocompleteDropdownUlice');
    const dropdownMesto = document.getElementById('autocompleteDropdown');

    let uliceTimeout;
    let mestoTimeout;

    // Funkce pro zvýraznění shody v textu
    const escapeHighlightRegex = (value) => {
      if (typeof value !== 'string') {
        return '';
      }
      return value.replace(/[-\/\^$*+?.()|[\]{}]/g, '\\$&');
    };

    const highlightMatch = (text, query) => {
      if (!query) return escapeHtml(text);

      // SECURITY FIX: Escape HTML PŘED highlightováním
      const escapedText = escapeHtml(text);
      const escapedQuery = escapeRegex(query);

      const regex = new RegExp(`(${escapedQuery})`, 'gi');
      return escapedText.replace(regex, '<strong>$1</strong>');
    };

    const escapeRegex = (str) => {
      return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    };

    const escapeHtml = (str) => {
      if (!str) return '';
      const div = document.createElement('div');
      div.textContent = str;
      return div.innerHTML;
    };

    // REFACTOR: Použití WGSMap.autocomplete() a WGSMap.debounce()
    if (uliceInput && dropdownUlice) {
      const debouncedAutocompleteUlice = WGSMap.debounce(async (query) => {
        if (query.length < CONSTANTS.AUTOCOMPLETE_MIN_CHARS) {
          dropdownUlice.classList.add('hidden');
          return;
        }

        try {
          const mesto = document.getElementById('mesto').value.trim();
          const psc = document.getElementById('psc').value.trim();

          // Hledání bez nutnosti vyplnit město - pokud je město/PSČ vyplněné, zúží výsledky
          let searchText = query;
          if (mesto) searchText += `, ${mesto}`;
          if (psc) searchText += `, ${psc}`;

          // Více výsledků když není specifikované město (hledá v celé ČR+SK)
          const limit = mesto || psc ? 10 : 15;

          const data = await WGSMap.autocomplete(searchText, { type: 'street', limit, country: 'CZ,SK' });

          if (data && data.features && data.features.length > 0) {
            dropdownUlice.innerHTML = '';
            dropdownUlice.classList.remove('hidden');

            // Seřadit podle relevance - preferovat úplné adresy
            const sortedFeatures = data.features.sort((a, b) => {
              const aComplete = (a.properties.housenumber ? 1 : 0) + (a.properties.postcode ? 1 : 0);
              const bComplete = (b.properties.housenumber ? 1 : 0) + (b.properties.postcode ? 1 : 0);
              return bComplete - aComplete;
            });

            sortedFeatures.forEach(feature => {
              const div = document.createElement('div');
              div.style.padding = '0.8rem';
              div.style.cursor = 'pointer';
              div.style.borderBottom = '1px solid #eee';
              div.style.fontSize = '0.9rem';
              div.style.transition = 'all 0.2s';

              const street = feature.properties.street || feature.properties.name || '';
              const houseNumber = feature.properties.housenumber || '';
              const city = feature.properties.city || '';
              const postcode = feature.properties.postcode || '';
              const state = feature.properties.state || ''; // kraj
              const country = feature.properties.country || '';

              // Formátování s zvýrazněním
              const addressText = `${street} ${houseNumber}`.trim();

              // Pokud NENÍ vyplněné město v inputu, zobraz i kraj/stát pro lepší orientaci
              let locationText = '';
              if (postcode) {
                locationText = `${city} (${postcode})`;
              } else {
                locationText = city;
              }

              // Přidat kraj když hledáme globálně (bez vyplněného města)
              if (!mesto && state && state !== city) {
                locationText += `, ${state}`;
              }

              // Přidat zemi pokud je to Slovensko
              if (country && country !== 'Czechia') {
                locationText += ` • ${country}`;
              }

              div.innerHTML = `
                <div style="font-weight: 500; color: #333;">${highlightMatch(addressText, query)}</div>
                ${locationText ? `<div style="font-size: 0.85rem; color: #666; margin-top: 0.2rem;">${locationText}</div>` : ''}
              `;

              div.addEventListener('mouseenter', () => {
                div.style.background = 'var(--c-light-blue-2)';
                div.style.transform = 'translateX(4px)';
              });

              div.addEventListener('mouseleave', () => {
                div.style.background = 'white';
                div.style.transform = 'translateX(0)';
              });

              div.addEventListener('click', () => {
                uliceInput.value = addressText;
                if (city) document.getElementById('mesto').value = city;
                if (postcode) document.getElementById('psc').value = postcode;

                const [lon, lat] = feature.geometry.coordinates;
                this.updateMapWithGPS(lat, lon);

                // PERFORMANCE: Výpočet trasy vypnut kvůli problémům s API
                // if (this.calculateRoute) {
                //   this.calculateRoute(lat, lon);
                // }

                dropdownUlice.classList.add('hidden');
                this.toast('Adresa vyplněna', 'success');
              });

              dropdownUlice.appendChild(div);
            });
          } else {
            dropdownUlice.classList.add('hidden');
          }
        } catch (err) {
          logger.error('Autocomplete error:', err);
          dropdownUlice.classList.add('hidden');
        }
      }, CONSTANTS.AUTOCOMPLETE_DEBOUNCE);

      uliceInput.addEventListener('input', (e) => {
        debouncedAutocompleteUlice(e.target.value.trim());
      });
    }
    
    // REFACTOR: Použití WGSMap.autocomplete() a WGSMap.debounce()
    if (mestoInput && dropdownMesto) {
      const debouncedAutocompleteMesto = WGSMap.debounce(async (query) => {
        if (query.length < CONSTANTS.AUTOCOMPLETE_MIN_CHARS) {
          dropdownMesto.classList.add('hidden');
          return;
        }

        try {
          const data = await WGSMap.autocomplete(query, { type: 'city', limit: 5, country: 'CZ,SK' });

          if (data && data.features && data.features.length > 0) {
            dropdownMesto.innerHTML = '';
            dropdownMesto.classList.remove('hidden');

            // Seřadit podle relevance - preferovat s PSČ
            const sortedFeatures = data.features.sort((a, b) => {
              const aHasPostcode = a.properties.postcode ? 1 : 0;
              const bHasPostcode = b.properties.postcode ? 1 : 0;
              return bHasPostcode - aHasPostcode;
            });

            sortedFeatures.forEach(feature => {
              const div = document.createElement('div');
              div.style.padding = '0.8rem';
              div.style.cursor = 'pointer';
              div.style.borderBottom = '1px solid #eee';
              div.style.fontSize = '0.9rem';
              div.style.transition = 'all 0.2s';

              const city = feature.properties.city || feature.properties.name || '';
              const postcode = feature.properties.postcode || '';

              // Formátování s zvýrazněním
              div.innerHTML = `
                <div style="font-weight: 500; color: #333;">${highlightMatch(city, query)}</div>
                ${postcode ? `<div style="font-size: 0.85rem; color: #666; margin-top: 0.2rem;">PSČ: ${postcode}</div>` : ''}
              `;

              div.addEventListener('mouseenter', () => {
                div.style.background = 'var(--c-light-blue-2)';
                div.style.transform = 'translateX(4px)';
              });

              div.addEventListener('mouseleave', () => {
                div.style.background = 'white';
                div.style.transform = 'translateX(0)';
              });

              div.addEventListener('click', () => {
                mestoInput.value = city;
                if (postcode) {
                  document.getElementById('psc').value = postcode;
                }

                dropdownMesto.classList.add('hidden');
                this.toast('Město vybráno', 'success');

                // Pokud je město vybráno, pokus se najít souřadnice
                if (feature.geometry && feature.geometry.coordinates) {
                  const [lon, lat] = feature.geometry.coordinates;
                  this.updateMapWithGPS(lat, lon);
                }
              });

              dropdownMesto.appendChild(div);
            });
          } else {
            dropdownMesto.classList.add('hidden');
          }
        } catch (err) {
          logger.error('Autocomplete error:', err);
          dropdownMesto.classList.add('hidden');
        }
      }, CONSTANTS.AUTOCOMPLETE_DEBOUNCE);

      mestoInput.addEventListener('input', (e) => {
        debouncedAutocompleteMesto(e.target.value.trim());
      });
    }
    
    document.addEventListener('click', (e) => {
      const clickedInsideUlice = dropdownUlice && dropdownUlice.contains(e.target);
      const clickedInsideMesto = dropdownMesto && dropdownMesto.contains(e.target);

      if (dropdownUlice && !clickedInsideUlice && e.target !== uliceInput) {
        dropdownUlice.classList.add('hidden');
      }

      if (dropdownMesto && !clickedInsideMesto && e.target !== mestoInput) {
        dropdownMesto.classList.add('hidden');
      }
    });
  },

  // PERFORMANCE: Funkce vypnuta kvůli problémům s get_distance API
  async calculateRoute(destLat, destLon) {
    // Funkce deaktivována - žádný výpočet trasy
    logger.log('Výpočet trasy vypnut (API nefunguje)');
    return;

    /* VYPNUTO - ROUTING API NEFUNGUJE
    if (!this.map) {
      logger.warn('Mapa není inicializována');
      return;
    }

    // ⚡ DEBOUNCING: Počkat než uživatel přestane klikat
    clearTimeout(this.calculateRouteTimeout);

    this.calculateRouteTimeout = setTimeout(async () => {
      try {
        logger.log('🚗 Počítám trasu ze sídla firmy...');

        const start = this.companyLocation;
        const data = await WGSMap.calculateRoute([start.lat, start.lon], [destLat, destLon]);

        // API vrací data.features
        if (data && data.features && data.features.length > 0) {
          const feature = data.features[0];
          const properties = feature.properties;
          const coordinates = feature.geometry.coordinates.map(coord => [coord[1], coord[0]]); // GeoJSON používá [lon, lat], Leaflet [lat, lon]

          const routeData = {
            coordinates,
            distance: properties.distance,
            duration: properties.time,
            start
          };

          this.renderRoute(routeData);
        }
      } catch (err) {
        logger.error('Chyba při výpočtu trasy:', err);
        // Tiché selhání - trasa není kritická
      }
    }, CONSTANTS.ROUTE_DEBOUNCE);
    */
  },

  // REFACTOR: Použití WGSMap.drawRoute() a WGSMap.addMarker()
  renderRoute(routeData) {
    const { coordinates, distance, duration, start } = routeData;

    // Nakreslit trasu na mapu pomocí WGSMap
    WGSMap.drawRoute(coordinates, {
      color: 'var(--c-link-blue)',
      weight: 4,
      opacity: 0.7,
      layerId: 'route',
      fitBounds: true
    });

    // Přidat marker pro start (sídlo firmy)
    WGSMap.addMarker('company', [start.lat, start.lon], {
      icon: '<div style="background: #10b981; color: white; width: 30px; height: 30px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: bold; border: 3px solid white; box-shadow: 0 2px 8px rgba(0,0,0,0.3);">🏢</div>',
      iconClass: 'custom-marker-start',
      iconSize: [30, 30]
    });

    // Zobrazit info o trase
    const distanceKm = (distance / 1000).toFixed(1); // metry na kilometry
    const durationMin = Math.ceil(duration / 60); // sekundy na minuty

    this.toast(`🚗 Trasa: ${distanceKm} km, cca ${durationMin} min`, 'info');
    logger.log(`Trasa vypočítána: ${distanceKm} km, ${durationMin} min`);

    // Uložit info o trase pro pozdější použití
    this.routeInfo = { distance: distanceKm, duration: durationMin };
  },

  checkAndUpdateMapFromAddress() {
    const uliceInput = document.getElementById('ulice');
    if (uliceInput && uliceInput.value.trim()) {
      logger.log('Checking pre-filled address...');
      if (this.geocodeAddress) {
        this.geocodeAddress();
      }
    }
  },
  
  initUserMode() {
    const modeInfo = document.getElementById('modeInfo');
    const calculatorBox = document.getElementById('calculatorBox');
    const urlParams = new URLSearchParams(window.location.search);
    const fromCalculator = urlParams.get('from_calculator') === 'true';
    
    if (!this.isLoggedIn) {
      const cisloInput = document.getElementById('cislo');
      const datumProdejeInput = document.getElementById('datum_prodeje');
      const datumReklamaceInput = document.getElementById('datum_reklamace');
      const doplnujiciInfoTextarea = document.getElementById('doplnujici_info');
      
      if (calculatorBox && !fromCalculator) {
        calculatorBox.classList.remove('hidden');
      }
      
      // POZ číslo se generuje automaticky na serveru - pole zůstává readonly
      if (cisloInput) {
        cisloInput.setAttribute('readonly', 'readonly');
        cisloInput.value = 'POZ (automaticky)';
        cisloInput.style.backgroundColor = 'var(--c-active)';
        cisloInput.style.color = 'var(--wgs-gray-66)';
        cisloInput.style.cursor = 'not-allowed';
        cisloInput.style.fontStyle = 'italic';
        cisloInput.style.pointerEvents = 'none';
        cisloInput.setAttribute('tabindex', '-1');
      }
      
      if (datumProdejeInput) {
        datumProdejeInput.value = 'nevyplňuje se';
        datumProdejeInput.readOnly = true;
        datumProdejeInput.style.backgroundColor = 'var(--c-success-bg)';
        datumProdejeInput.style.cursor = 'not-allowed';
        datumProdejeInput.classList.add('disabled-field');
      }
      
      if (datumReklamaceInput) {
        datumReklamaceInput.value = 'nevyplňuje se';
        datumReklamaceInput.readOnly = true;
        datumReklamaceInput.style.backgroundColor = 'var(--c-success-bg)';
        datumReklamaceInput.style.cursor = 'not-allowed';
        datumReklamaceInput.classList.add('disabled-field');
      }
      
      if (doplnujiciInfoTextarea) {
        doplnujiciInfoTextarea.value = 'nevyplňuje se';
        doplnujiciInfoTextarea.readOnly = true;
        doplnujiciInfoTextarea.style.backgroundColor = 'var(--c-success-bg)';
        doplnujiciInfoTextarea.style.cursor = 'not-allowed';
      }
      
      if (modeInfo) {
        modeInfo.classList.remove('hidden');
        document.getElementById('modeTitle').textContent = t('mode_customer_title');
        document.getElementById('modeDescription').textContent = t('mode_customer_desc');
      }
      
      logger.log('[List] Mode: Customer');
    } else {
      if (calculatorBox) {
        calculatorBox.classList.add('hidden');
      }
      
      if (modeInfo) {
        modeInfo.classList.remove('hidden');
        modeInfo.style.borderLeftColor = 'var(--c-dark-green)';
        modeInfo.style.background = 'var(--c-light-green)';
        document.getElementById('modeTitle').textContent = t('mode_seller_title');
        document.getElementById('modeDescription').textContent = t('mode_seller_desc');
      }
      logger.log('[List] Mode: Seller');
    }
  },
  
  initCalculationDisplay() {
    const urlParams = new URLSearchParams(window.location.search);
    const fromCalculator = urlParams.get('from_calculator');
    
    if (fromCalculator === 'true') {
      const calculationBox = document.getElementById('calculationBox');
      if (calculationBox) {
        calculationBox.classList.remove('hidden');
        const totalPrice = urlParams.get('calc_total');
        document.getElementById('calculationTotal').textContent = totalPrice;
      }
    }
  },
  
  initMobileMenu() {
    const hamburger = document.getElementById("hamburger");
    const nav = document.getElementById("nav");
    const menuOverlay = document.getElementById("menuOverlay");
    
    if (!hamburger || !nav || !menuOverlay) {
      logger.warn("initMobileMenu skipped - elements not found");
      return;
    }
    
    logger.log('📱 Initializing mobile menu...');
    
    hamburger.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      hamburger.classList.toggle('active');
      nav.classList.toggle('active');
      menuOverlay.classList.toggle('active');
      // Scroll-lock pres centralizovanou utilitu (iOS kompatibilni)
      if (window.scrollLock) {
        if (nav.classList.contains('active')) {
          window.scrollLock.enable('novareklamace-menu');
        } else {
          window.scrollLock.disable('novareklamace-menu');
        }
      }
    });

    menuOverlay.addEventListener('click', () => {
      hamburger.classList.remove('active');
      nav.classList.remove('active');
      menuOverlay.classList.remove('active');
      if (window.scrollLock) {
        window.scrollLock.disable('novareklamace-menu');
      }
    });

    nav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        hamburger.classList.remove('active');
        nav.classList.remove('active');
        menuOverlay.classList.remove('active');
        if (window.scrollLock) {
          window.scrollLock.disable('novareklamace-menu');
        }
      });
    });
    
    logger.log('Mobile menu fully initialized');
  },
  
  initForm() {
    const form = document.getElementById('reklamaceForm');
    if (!form) {
      logger.error('[initForm] Formulář reklamaceForm nenalezen!');
      return;
    }

    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      e.stopPropagation();
      logger.log('[initForm] Submit zachycen, spouštím validaci...');
      await this.submitForm();
    });

    // Funkce pro změnu fakturace
    const fakturaceSelect = document.getElementById('fakturace_firma');
    const fakturaHint = document.getElementById('faktura_hint');

    if (fakturaceSelect && fakturaHint) {
      fakturaceSelect.addEventListener('change', (e) => {
        const value = e.target.value;
        if (value === 'CZ') {
          fakturaHint.textContent = t('invoice_cz_hint');
        } else if (value === 'SK') {
          fakturaHint.textContent = t('invoice_sk_hint');
        }
      });
    }

    // Event listenery pro odstranění červeného označení při psaní
    const povinnaPoleIds = ['jmeno', 'email', 'telefon', 'ulice', 'mesto', 'psc', 'popis_problemu'];
    povinnaPoleIds.forEach(poleId => {
      const element = document.getElementById(poleId);
      if (element) {
        element.addEventListener('input', () => {
          if (element.value.trim()) {
            element.style.borderColor = '';
            element.style.backgroundColor = '';
          }
        });
      }
    });
  },

  /**
   * Validace všech povinných polí formuláře
   * @returns {Object} { valid: boolean, chybejici: string[] }
   */
  validatePovinnaPole() {
    // Základní povinná pole pro všechny
    const povinnaPole = [
      { id: 'jmeno', label: 'Jméno a příjmení' },
      { id: 'email', label: 'E-mail' },
      { id: 'telefon', label: 'Telefon' },
      { id: 'ulice', label: 'Ulice a ČP' },
      { id: 'mesto', label: 'Město' },
      { id: 'psc', label: 'PSČ' },
      { id: 'popis_problemu', label: 'Popis problému' }
    ];

    // Přidat pole povinná pouze pro přihlášené uživatele
    if (this.isLoggedIn) {
      povinnaPole.unshift(
        { id: 'cislo', label: 'Číslo zakázky' },
        { id: 'datum_prodeje', label: 'Datum prodeje' },
        { id: 'datum_reklamace', label: 'Datum reklamace' }
      );
    }

    const chybejici = [];
    let prvniPrazdne = null;

    // Odebrat červené označení ze všech polí
    povinnaPole.forEach(pole => {
      const element = document.getElementById(pole.id);
      if (element) {
        element.style.borderColor = '';
        element.style.backgroundColor = '';
      }
    });

    // Zkontrolovat každé povinné pole
    povinnaPole.forEach(pole => {
      const element = document.getElementById(pole.id);
      if (!element) {
        logger.warn(`Pole ${pole.id} nebylo nalezeno v DOM`);
        return;
      }

      const hodnota = element.value.trim();
      if (!hodnota) {
        chybejici.push(pole.label);

        // Označit červeně
        element.style.borderColor = 'var(--wgs-danger)';
        element.style.backgroundColor = 'var(--c-error-bg-light)';

        // Zapamatovat první prázdné pole
        if (!prvniPrazdne) {
          prvniPrazdne = element;
        }
      }
    });

    // Scrollnout na první prázdné pole
    if (prvniPrazdne) {
      prvniPrazdne.scrollIntoView({ behavior: 'smooth', block: 'center' });
      setTimeout(() => prvniPrazdne.focus(), 500);
    }

    // Validace typu zákazníka - musí být zaškrtnut jeden z checkboxů
    const icoCheckbox = document.getElementById('objednavkaICO');
    const fyzickaCheckbox = document.getElementById('objednavkaFyzicka');
    const typZakaznikaWrapper = document.querySelector('.typ-zakaznika-wrapper');

    if (icoCheckbox && fyzickaCheckbox) {
      // Odebrat červené označení
      if (typZakaznikaWrapper) {
        typZakaznikaWrapper.style.borderColor = '';
        typZakaznikaWrapper.style.backgroundColor = '';
      }

      // Zkontrolovat, zda je zaškrtnut alespoň jeden checkbox
      if (!icoCheckbox.checked && !fyzickaCheckbox.checked) {
        chybejici.push('Typ zákazníka (IČO nebo Fyzická osoba)');

        // Označit červeně
        if (typZakaznikaWrapper) {
          typZakaznikaWrapper.style.border = '2px solid #dc3545';
          typZakaznikaWrapper.style.backgroundColor = 'var(--c-error-bg-light)';
          typZakaznikaWrapper.style.borderRadius = '8px';
          typZakaznikaWrapper.style.padding = '0.75rem';

          // Scrollnout na wrapper pokud je první chyba
          if (!prvniPrazdne) {
            typZakaznikaWrapper.scrollIntoView({ behavior: 'smooth', block: 'center' });
          }
        }
      }
    }

    return {
      valid: chybejici.length === 0,
      chybejici
    };
  },

  async submitForm() {
    // VALIDACE VŠECH POVINNÝCH POLÍ
    const validace = this.validatePovinnaPole();
    if (!validace.valid) {
      this.toast(`Vyplňte prosím všechna povinná pole: ${validace.chybejici.join(', ')}`, 'error');
      return;
    }

    // GDPR souhlas - pouze pro neregistrované uživatele
    // Checkbox neexistuje pokud je uživatel přihlášený (souhlas ošetřen smluvně)
    const consentCheckbox = document.getElementById('gdpr_consent');
    if (consentCheckbox && !consentCheckbox.checked) {
      consentCheckbox.focus();
      this.toast('Pro odeslání je nutný souhlas se zpracováním osobních údajů.', 'error');
      return;
    }

    // FIX H-2: Frontend validace telefonu
    const telefonInput = document.getElementById('telefon');

    // PSČ validace odstraněna - autocomplete vrací různé formáty ("110 00", "11000", atd.)
    // Uživatelé také píší PSČ různě, není důvod striktně validovat

    if (telefonInput && telefonInput.value.trim()) {
      const telefon = telefonInput.value.trim();
      const cleanPhone = telefon.replace(/\D/g, '');
      // FIX L-2: Použít konstantu PHONE_MIN_LENGTH
      if (cleanPhone.length < CONSTANTS.PHONE_MIN_LENGTH) {
        this.toast(`Neplatné telefonní číslo (minimálně ${CONSTANTS.PHONE_MIN_LENGTH} číslic)`, 'error');
        telefonInput.focus();
        return;
      }
    }

    this.toast('Odesílám...', 'info');
    try {
      // FIX H-1: Získat CSRF token JEDNOU pro celý submit proces
      const csrfResponse = await fetch('app/controllers/get_csrf_token.php');
      const csrfData = await csrfResponse.json();
      const csrfToken = csrfData.status === 'success' ? csrfData.token : '';

      const formData = new FormData();
      formData.append('action', 'create');
      formData.append('typ', this.isLoggedIn ? 'reklamace' : 'servis');
      formData.append('cislo', document.getElementById('cislo').value || '');
      formData.append('datum_prodeje', document.getElementById('datum_prodeje').value || '');
      formData.append('datum_reklamace', document.getElementById('datum_reklamace').value || '');
      formData.append('jmeno', document.getElementById('jmeno').value || '');
      formData.append('email', document.getElementById('email').value || '');

      // Typ zákazníka - IČO nebo fyzická osoba
      const icoCheckbox = document.getElementById('objednavkaICO');
      const fyzickaCheckbox = document.getElementById('objednavkaFyzicka');
      let typZakaznika = '';
      if (icoCheckbox?.checked) {
        typZakaznika = 'IČO';
      } else if (fyzickaCheckbox?.checked) {
        typZakaznika = 'Fyzická osoba';
      }
      formData.append('typ_zakaznika', typZakaznika);

      // Spojit předvolbu + telefonní číslo
      const phonePrefix = document.getElementById('phone-prefix')?.value || '+420';
      const phoneNumber = document.getElementById('telefon')?.value || '';
      const fullPhone = phoneNumber ? `${phonePrefix} ${phoneNumber.trim()}` : '';
      formData.append('telefon', fullPhone);

      const ulice = document.getElementById('ulice')?.value || '';
      const mesto = document.getElementById('mesto')?.value || '';
      const psc = document.getElementById('psc')?.value || '';
      const adresa = [ulice, mesto, psc].filter(x => x).join(', ');

      // Poslat jak samostatné hodnoty, tak i složenou adresu
      formData.append('ulice', ulice);
      formData.append('mesto', mesto);
      formData.append('psc', psc);
      formData.append('adresa', adresa);

      formData.append('model', document.getElementById('model')?.value || '');
      formData.append('provedeni', document.getElementById('provedeni')?.value || '');
      formData.append('barva', document.getElementById('barva')?.value || '');
      formData.append('seriove_cislo', '');
      formData.append('popis_problemu', document.getElementById('popis_problemu')?.value || '');
      formData.append('doplnujici_info', document.getElementById('doplnujici_info')?.value || '');

      const fakturaceFirma = document.getElementById('fakturace_firma')?.value || 'CZ';
      formData.append('fakturace_firma', fakturaceFirma);

      if (consentCheckbox) {
        formData.append('gdpr_consent', consentCheckbox.checked ? '1' : '0');
      }

      // Přiložení PDF pověření k reklamaci (pokud bylo nahráno)
      if (this.povereniPDF) {
        formData.append('povereni_pdf', this.povereniPDF);
        logger.log(`[Doc] Přikládám PDF pověření: ${this.povereniPDF.name}`);
      }

      // FIX H-1: Použít CSRF token získaný výše
      formData.append('csrf_token', csrfToken);

      formData.append("action", "create");
      const response = await fetch('app/controllers/save.php', {
        method: 'POST',
        body: formData
      });

      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }

      const result = await response.json();

      if (result.status === 'success') {
        const workflowId = result.reklamace_id || result.workflow_id || result.id;
        const referenceNumber = result.reference || (document.getElementById('cislo')?.value || '').trim();

        if (this.photos && this.photos.length > 0) {
          // FIX H-1: Předat stejný CSRF token do uploadPhotos
          await this.uploadPhotos(workflowId, csrfToken);
        }

        if (this.videoSoubor) {
          await this.uploadVideo(workflowId, csrfToken);
        }

        // Cleanup: Vyčistit PDF pověření po úspěšném uložení
        if (this.povereniPDF) {
          this.povereniPDF = null;
          const pdfInput = document.getElementById('povereniInput');
          const statusSpan = document.getElementById('povereniStatus');
          if (pdfInput) pdfInput.value = '';
          if (statusSpan) {
            statusSpan.textContent = '';
            statusSpan.style.color = 'var(--wgs-gray-66)';
          }
          logger.log('PDF pověření vyčištěno po úspěšném uložení');
        }

        this.toast('Požadavek byl úspěšně odeslán!', 'success');
        setTimeout(() => {
          if (this.isLoggedIn) {
            window.location.href = 'seznam.php';
          } else {
            const alertMessage = referenceNumber
              ? t('order_accepted_with_ref').replace('{reference}', referenceNumber)
              : t('order_accepted_no_ref');
            this.toast(alertMessage, 'success');
            setTimeout(() => { window.location.href = 'index.php'; }, 3000);
          }
        }, 1500);
      } else {
        throw new Error(result.message || 'Chyba při ukládání');
      }
    } catch (error) {
      logger.error('Chyba při odesílání formuláře:', error);
      this.toast(t('submit_error') + ': ' + error.message, 'error');
    }
  },
  
  /**
   * FIX H-1: Uploaduje fotky s předaným CSRF tokenem (bez duplicitního fetch)
   * FIX L-1: Změna console.log na logger.log
   * @param {string} reklamaceId - ID reklamace
   * @param {string} csrfToken - CSRF token (předaný z submitForm)
   */
  async uploadPhotos(reklamaceId, csrfToken) {
    logger.log("[Start] uploadPhotos VOLÁNO!", reklamaceId);
    if (!this.photos || this.photos.length === 0) return;
    logger.log("[Photo] Počet fotek:", this.photos.length);
    try {
      const formData = new FormData();
      formData.append('reklamace_id', reklamaceId);
      formData.append('photo_type', 'problem');
      formData.append('csrf_token', csrfToken); // Použít předaný token
      this.photos.forEach((photo, index) => {
        formData.append(`photo_${index}`, photo.data);
        formData.append(`filename_${index}`, `photo_${index + 1}.jpg`);
      });
      formData.append('photo_count', this.photos.length);

      const response = await fetch('app/controllers/save_photos.php', {
        method: 'POST',
        body: formData
      });

      if (!response.ok) {
        throw new Error('Chyba při nahrávání fotek');
      }

      const result = await response.json();
      if (result.status !== 'success') {
        throw new Error(result.error || 'Nepodařilo se nahrát fotky');
      }
      logger.log('Fotky úspěšně nahrány');
    } catch (error) {
      logger.error('Chyba při nahrávání fotek:', error);
    }
  },
  
  initPhotos() {
    const btn = document.getElementById('uploadPhotosBtn');
    const photoInput = document.getElementById('photoInput');
    if (!btn || !photoInput) {
      logger.warn('📷 Upload prvky nebyly nalezeny, initPhotos se přeskočí');
      return;
    }

    btn.addEventListener('click', () => photoInput.click());
    photoInput.addEventListener('change', async (e) => {
      const files = Array.from(e.target.files);
      // FIX L-2 & L-3: Použít konstantu MAX_PHOTOS (sjednocení s backendem)
      if (this.photos.length + files.length > CONSTANTS.MAX_PHOTOS) {
        this.toast(`Maximálně ${CONSTANTS.MAX_PHOTOS} fotografií`, 'error');
        return;
      }
      for (const file of files) {
        const compressed = await this.compressImage(file);
        const base64 = await this.toBase64(compressed);
        this.photos.push({ data: base64, file: compressed });
      }
      this.renderPhotos();
      // Neonový toast pro přidání fotek
      if (typeof WGSToast !== 'undefined') {
        WGSToast.zobrazit(`Přidáno ${files.length} fotek`, { titulek: 'WGS' });
      } else {
        this.toast(`Přidáno ${files.length} fotek`, 'success');
      }
    });
  },

  initVideos() {
    const btn = document.getElementById('uploadVideoBtn');
    const videoInput = document.getElementById('videoInput');
    if (!btn || !videoInput) return;

    btn.addEventListener('click', () => videoInput.click());
    videoInput.addEventListener('change', async (e) => {
      const soubor = e.target.files[0];
      if (!soubor) return;

      const maxVelikost = 524288000; // 500 MB (stejně jako videotéka)
      if (soubor.size > maxVelikost) {
        this.toast('Video je příliš velké. Maximum je 500 MB.', 'error');
        videoInput.value = '';
        return;
      }

      // Komprese stejně jako ve videotéce (max 1920x1080, 2.5 Mbps, 30fps)
      let zpracovanyVideo = soubor;
      if (typeof MediaRecorder !== 'undefined') {
        try {
          this.toast('Komprimuji video...', 'info');
          const komprimovany = await komprimovatVideoObjednavka(soubor);
          zpracovanyVideo = new File([komprimovany], soubor.name.replace(/\.[^.]+$/, '.webm'), { type: komprimovany.type });
        } catch (chyba) {
          logger.warn('[Video] Komprese selhala, nahrávám originál:', chyba.message);
          zpracovanyVideo = soubor;
        }
      }

      this.videoSoubor = zpracovanyVideo;
      this.renderVideo();

      if (typeof WGSToast !== 'undefined') {
        WGSToast.zobrazit('Video přidáno', { titulek: 'WGS' });
      } else {
        this.toast('Video přidáno', 'success');
      }
    });
  },

  renderVideo() {
    const kontejner = document.getElementById('videoPreviewMain');
    if (!kontejner) return;
    kontejner.innerHTML = '';
    if (!this.videoSoubor) return;

    const url = URL.createObjectURL(this.videoSoubor);
    const obal = document.createElement('div');
    obal.className = 'video-thumb';
    obal.innerHTML = `
      <video src="${url}" controls></video>
      <button class="video-remove" title="Odebrat video">×</button>
    `;
    obal.querySelector('.video-remove').addEventListener('click', () => {
      this.videoSoubor = null;
      document.getElementById('videoInput').value = '';
      this.renderVideo();
    });
    kontejner.appendChild(obal);
  },

  async uploadVideo(reklamaceId, csrfToken) {
    if (!this.videoSoubor) return;
    try {
      const formData = new FormData();
      formData.append('reklamace_id', reklamaceId);
      formData.append('csrf_token', csrfToken);
      formData.append('video', this.videoSoubor, this.videoSoubor.name);

      const odpoved = await fetch('app/controllers/save_video.php', {
        method: 'POST',
        body: formData
      });
      const vysledek = await odpoved.json();
      if (vysledek.status !== 'success') {
        logger.warn('[Video] Upload selhal:', vysledek.message);
      }
    } catch (chyba) {
      logger.warn('[Video] Chyba při uploadu:', chyba.message);
    }
  },

  // PRAVIDLO: Zadna rotace, zadna deformace, pouze komprese
  // Orientace se NIKDY nemeni - prohlizec aplikuje EXIF automaticky
  async compressImage(file) {
    return new Promise((resolve) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = new Image();
        img.onload = () => {
          const canvas = document.createElement('canvas');
          // Zachovat pomer stran - zadna deformace
          const scale = Math.min(1, CONSTANTS.MAX_IMAGE_WIDTH / Math.max(img.width, img.height));
          canvas.width = img.width * scale;
          canvas.height = img.height * scale;
          const ctx = canvas.getContext('2d');
          // Nakreslit bez rotace - orientace se nemeni
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          canvas.toBlob((blob) => {
            resolve(new File([blob], file.name, { type: 'image/jpeg' }));
          }, 'image/jpeg', CONSTANTS.IMAGE_QUALITY);
        };
        img.src = e.target.result;
      };
      reader.readAsDataURL(file);
    });
  },
  
  toBase64(file) {
    return new Promise((resolve, reject) => {
      const reader = new FileReader();
      reader.onload = () => resolve(reader.result);
      reader.onerror = reject;
      reader.readAsDataURL(file);
    });
  },
  
  renderPhotos() {
    const container = document.getElementById("photoPreviewMain");
    container.innerHTML = "";
    this.photos.forEach((photo, index) => {
      const div = document.createElement("div");
      div.className = "photo-thumb";
      div.innerHTML = `<img src="${photo.data}" alt="Photo ${index + 1}"><button class="photo-remove" data-index="${index}">×</button>`;
      div.querySelector(".photo-remove").addEventListener("click", () => {
        this.photos.splice(index, 1);
        this.renderPhotos();
        this.toast("Fotka odstraněna", "info");
      });
      container.appendChild(div);
    });
  },

  // Inicializace nahrávání PDF pověření k reklamaci
  initPovereniPDF() {
    const btn = document.getElementById('nahrajPovereniBtn');
    const pdfInput = document.getElementById('povereniInput');
    const statusSpan = document.getElementById('povereniStatus');

    if (!btn || !pdfInput || !statusSpan) {
      logger.warn('[Doc] PDF pověření prvky nebyly nalezeny, initPovereniPDF se přeskočí');
      return;
    }

    // Kliknutí na tlačítko otevře file input
    btn.addEventListener('click', () => pdfInput.click());

    // Při výběru souboru
    pdfInput.addEventListener('change', async (e) => {
      const file = e.target.files[0];

      if (!file) {
        return;
      }

      // Validace typu souboru
      if (file.type !== 'application/pdf') {
        this.toast('Pouze PDF soubory jsou povoleny', 'error');
        pdfInput.value = '';
        return;
      }

      // Validace velikosti (max 10MB)
      const maxSize = 10 * 1024 * 1024; // 10MB v bytech
      if (file.size > maxSize) {
        this.toast('Soubor je příliš velký (max 10MB)', 'error');
        pdfInput.value = '';
        return;
      }

      // Uložení PDF souboru
      this.povereniPDF = file;

      // Zobrazení názvu souboru
      const velikostMB = (file.size / (1024 * 1024)).toFixed(2);
      statusSpan.textContent = t('processing_file').replace('{filename}', file.name);
      statusSpan.style.color = 'var(--wgs-gray-66)';
      statusSpan.style.fontWeight = '600';

      this.toast(t('processing_file').replace('{filename}', 'PDF pověření'), 'info');
      logger.log(`[Doc] PDF pověření připojeno: ${file.name}, velikost: ${velikostMB} MB`);

      // Extrakce textu z PDF a parsování dat
      try {
        await this.zpracujPovereniPDF(file);
        statusSpan.textContent = t('file_processed_success')
          .replace('{filename}', file.name)
          .replace('{size}', velikostMB);
        statusSpan.style.color = 'var(--wgs-gray-33)';
        this.toast(`Formulář byl předvyplněn z PDF pověření`, 'success');
      } catch (error) {
        logger.error('Chyba při zpracování PDF:', error);
        statusSpan.textContent = t('file_processing_error')
          .replace('{filename}', file.name)
          .replace('{size}', velikostMB);
        statusSpan.style.color = 'var(--wgs-delete)';
        this.toast(`⚠ PDF nahráno, ale nepodařilo se extrahovat data`, 'error');
      }
    });
  },

  /**
   * Zpracuje PDF pověření - extrahuje text a předvyplní formulář
   * @param {File} pdfFile - PDF soubor
   */
  async zpracujPovereniPDF(pdfFile) {
    // Lazy load PDF.js library pouze když je potřeba
    try {
      await window.loadPDFJS();
    } catch (error) {
      throw new Error('Nepodařilo se načíst PDF.js library');
    }

    // Načtení PDF souboru
    const arrayBuffer = await pdfFile.arrayBuffer();
    const pdf = await pdfjsLib.getDocument({ data: arrayBuffer }).promise;

    logger.log(`[Doc] PDF má ${pdf.numPages} stránek`);

    // Extrakce textu ze všech stránek
    let celkovyText = '';
    for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
      const page = await pdf.getPage(pageNum);
      const textContent = await page.getTextContent();
      const textItems = textContent.items.map(item => item.str).join(' ');
      celkovyText += textItems + '\n';
    }

    logger.log(`[Doc] Extrahovaný text (${celkovyText.length} znaků):`, celkovyText.substring(0, 200) + '...');

    // Odeslání textu na backend pro parsování
    const csrfResponse = await fetch('app/controllers/get_csrf_token.php');
    const csrfData = await csrfResponse.json();
    const csrfToken = csrfData.status === 'success' ? csrfData.token : '';

    const formData = new FormData();
    formData.append('pdf_text', celkovyText);
    formData.append('csrf_token', csrfToken);

    const response = await fetch('api/parse_povereni_pdf.php', {
      method: 'POST',
      body: formData
    });

    if (!response.ok) {
      throw new Error(`HTTP error! status: ${response.status}`);
    }

    const result = await response.json();

    if (result.status !== 'success') {
      throw new Error(result.message || 'Chyba při parsování PDF');
    }

    // Extrakce dat z odpovědi
    // API může vracet přímo data (fallback parser) nebo strukturu { data, config_name, config_id }
    const extrahovanaData = result.data.data || result.data;
    const configName = result.data.config_name || 'Výchozí parser';

    logger.log(`Použita konfigurace: ${configName}`);

    // Předvyplnění formuláře s extrahovanými daty
    this.predvyplnFormularZPDF(extrahovanaData);
  },

  /**
   * Předvyplní formulář daty extrahovanými z PDF
   * @param {Object} data - Extrahovaná data z PDF
   */
  predvyplnFormularZPDF(data) {
    logger.log('[Edit] Předvyplňuji formulář daty z PDF:', data);

    // Helper funkce pro bezpečné nastavení hodnoty pole
    const nastavPole = (id, hodnota) => {
      if (!hodnota) return;
      const element = document.getElementById(id);
      if (element && !element.value) { // Nepřepsat pokud už je vyplněno
        element.value = hodnota;
        logger.log(`Vyplněno pole ${id}: ${hodnota}`);
      }
    };

    // Předvyplnění polí
    // Podporuje obě varianty názvů (nová API vrací cislo_objednavky_reklamace, fallback vrací cislo)
    nastavPole('cislo', data.cislo_objednavky_reklamace || data.cislo);
    nastavPole('datum_prodeje', data.datum_prodeje);
    nastavPole('datum_reklamace', data.datum_reklamace);
    nastavPole('jmeno', data.jmeno);
    nastavPole('email', data.email);
    nastavPole('telefon', data.telefon);
    nastavPole('ulice', data.ulice);
    nastavPole('mesto', data.mesto);
    nastavPole('psc', data.psc);
    nastavPole('model', data.model);
    nastavPole('provedeni', data.provedeni);
    nastavPole('barva', data.barva);
    nastavPole('popis_problemu', data.popis_problemu);
    nastavPole('doplnujici_info', data.doplnujici_info);

    // Pokud máme adresu, aktualizovat mapu
    if (data.mesto || data.psc || data.ulice) {
      if (this.geocodeAddress) {
        setTimeout(() => this.geocodeAddress(), 500);
      }
    }

    // Pokud bylo vyplněno datum prodeje nebo reklamace, zkontrolovat záruku
    if (data.datum_prodeje || data.datum_reklamace) {
      const datumProdeje = document.getElementById('datum_prodeje');
      const datumReklamace = document.getElementById('datum_reklamace');
      if (datumProdeje && datumReklamace && this.checkWarranty) {
        setTimeout(() => this.checkWarranty(), 500);
      }
    }
  },

  initProvedeni() {
    // Step 37: Migrace na Alpine.js CSP-safe
    // Logika open/close/select je nyní řízena přes Alpine komponentu provedeniModal
    // v hamburger-menu.php. Tato funkce je ponechána prázdná pro zpětnou kompatibilitu.
  },
  
  toast(message, type = 'info') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
  },
  

  initCustomCalendar() {
    // Step 38: Open/close logika migrována na Alpine.js (calendarModal)
    // Renderování kalendáře zůstává vanilla JS
    const overlay = document.getElementById('calendarOverlay');
    if (!overlay) return;
    const grid = document.getElementById('calendarGrid');
    const monthYearDisplay = document.getElementById('calendarMonthYear');
    let currentDate = new Date();
    let selectedInput = null;
    const monthNames = [t('january'), t('february'), t('march'), t('april'), t('may'), t('june'), t('july'), t('august'), t('september'), t('october'), t('november'), t('december')];
    const weekDays = [t('monday'), t('tuesday'), t('wednesday'), t('thursday'), t('friday'), t('saturday'), t('sunday')];
    const self = this;

    // Helper pro zavření modalu (Alpine nebo fallback)
    const closeModal = () => {
      if (window.calendarModal && window.calendarModal.close) {
        window.calendarModal.close();
      } else {
        overlay.classList.remove('active');
      }
    };

    // Helper pro otevření modalu (Alpine nebo fallback)
    const openModal = () => {
      if (window.calendarModal && window.calendarModal.open) {
        window.calendarModal.open();
      } else {
        overlay.classList.add('active');
      }
    };

    const renderCalendar = () => {
      const year = currentDate.getFullYear();
      const month = currentDate.getMonth();
      monthYearDisplay.textContent = monthNames[month] + ' ' + year;
      grid.innerHTML = '';
      weekDays.forEach(day => { const div = document.createElement('div'); div.className = 'calendar-weekday'; div.textContent = day; grid.appendChild(div); });
      const firstDay = new Date(year, month, 1).getDay();
      const daysInMonth = new Date(year, month + 1, 0).getDate();
      const adjustedFirstDay = firstDay === 0 ? 6 : firstDay - 1;
      for (let i = 0; i < adjustedFirstDay; i++) { const div = document.createElement('div'); div.className = 'calendar-day disabled'; grid.appendChild(div); }
      for (let day = 1; day <= daysInMonth; day++) {
        const div = document.createElement('div');
        div.className = 'calendar-day';
        div.textContent = day;
        div.addEventListener('click', () => {
          const selected = day.toString().padStart(2, '0') + '.' + (month + 1).toString().padStart(2, '0') + '.' + year;
          if (selectedInput) { selectedInput.value = selected; self.calculateWarranty(); }
          closeModal(); // Step 38: Použít Alpine close
        });
        grid.appendChild(div);
      }
    };
    ['datum_prodeje', 'datum_reklamace'].forEach(id => {
      const input = document.getElementById(id);
      if (input) {
        const wrapper = input.closest('.date-input-wrapper');
        if (wrapper) {
          wrapper.style.cursor = 'pointer';
          wrapper.addEventListener('click', () => {
            if (!input.readOnly || self.isLoggedIn) {
              selectedInput = input;
              currentDate = new Date();
              renderCalendar();
              openModal(); // Step 38: Použít Alpine open
            }
          });
        }
      }
    });
    const prevMonthBtn = document.getElementById('prevMonth');
    const nextMonthBtn = document.getElementById('nextMonth');
    if (prevMonthBtn) {
      prevMonthBtn.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(); });
    }
    if (nextMonthBtn) {
      nextMonthBtn.addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(); });
    }
    // Step 38: Close button a overlay click jsou nyní řízeny Alpine.js
  },

  /**
   * FIX M-1: Warranty calculation pouze pro přihlášené uživatele
   * Nepřihlášení mají datum_prodeje a datum_reklamace = "nevyplňuje se" (readonly)
   */
  calculateWarranty() {
    // FIX M-1: Warranty calculation pouze pro přihlášené
    if (!this.isLoggedIn) {
      return; // Nepřihlášení nemají přístup k těmto datům
    }

    const datumProdeje = document.getElementById('datum_prodeje').value;
    const datumReklamace = document.getElementById('datum_reklamace').value;
    const warning = document.getElementById('warrantyWarning');

    // FIX M-1: Zkontrolovat platnost hodnot
    if (!datumProdeje || !datumReklamace ||
        datumProdeje === 'nevyplňuje se' ||
        datumReklamace === 'nevyplňuje se') {
      if (warning) warning.classList.add('hidden');
      return;
    }

    const parseCzDate = (str) => {
      const parts = str.split('.');
      return new Date(parts[2], parts[1] - 1, parts[0]);
    };

    try {
      const prodej = parseCzDate(datumProdeje);
      const reklamace = parseCzDate(datumReklamace);
      const warrantyEnd = new Date(prodej);
      // FIX L-2: Použít konstantu WARRANTY_YEARS
      warrantyEnd.setFullYear(warrantyEnd.getFullYear() + CONSTANTS.WARRANTY_YEARS);
      const daysRemaining = Math.ceil((warrantyEnd - reklamace) / (1000 * 60 * 60 * 24));

      if (warning) {
        warning.style.display = 'block';
        if (daysRemaining > 0) {
          warning.className = '';
          warning.innerHTML = `V záruce - zbývá ${daysRemaining} dní (do ${warrantyEnd.toLocaleDateString('cs-CZ')})`;
        } else {
          warning.className = 'expired';
          warning.innerHTML = `Po záruce - ${Math.abs(daysRemaining)} dní po vypršení (${warrantyEnd.toLocaleDateString('cs-CZ')})`;
        }
      }
    } catch (err) {
      logger.error('Chyba při výpočtu záruky:', err);
      if (warning) warning.classList.add('hidden');
    }
  },

  initLanguage() {
    let currentLang = localStorage.getItem('wgs-lang') || 'cs';
    const switchLanguage = (lang) => {
      currentLang = lang;
      localStorage.setItem('wgs-lang', lang);
      document.documentElement.lang = lang;
      document.querySelectorAll('[data-lang-cs]').forEach(el => {
        const text = el.getAttribute('data-lang-' + lang);
        if (text) {
          if (text.includes('<br>')) {
            el.innerHTML = text;
          } else {
            el.textContent = text;
          }
        }
      });
      document.querySelectorAll('[data-lang-cs-placeholder]').forEach(el => {
        const placeholder = el.getAttribute('data-lang-' + lang + '-placeholder');
        if (placeholder) el.placeholder = placeholder;
      });
      document.querySelectorAll('.lang-flag').forEach(flag => {
        flag.classList.remove('active');
        if (flag.dataset.lang === lang) flag.classList.add('active');
      });
      const titles = { cs: 'Objednat servis | WGS', en: 'Order Service | WGS', it: 'Ordinare Servizio | WGS' };
      document.title = titles[lang];
    };
    if (currentLang !== 'cs') switchLanguage(currentLang);
    document.querySelectorAll('.lang-flag').forEach(flag => {
      flag.addEventListener('click', () => switchLanguage(flag.dataset.lang));
    });
  }
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => WGS.init());
} else {
  WGS.init();
}

document.addEventListener('DOMContentLoaded', () => {
  document.addEventListener('click', (e) => {
    const target = e.target.closest('[data-action]');
    if (!target) return;
    const action = target.getAttribute('data-action');
    if (action === 'reload') {
      location.reload();
      return;
    }
    if (typeof window[action] === 'function') {
      window[action]();
    }
  });
  
  document.addEventListener('click', (e) => {
    const navigate = e.target.closest('[data-navigate]')?.getAttribute('data-navigate');
    if (navigate) {
      if (typeof navigateTo === 'function') {
        navigateTo(navigate);
      } else {
        location.href = navigate;
      }
    }
  });
  
  document.addEventListener('change', (e) => {
    const target = e.target.closest('[data-onchange]');
    if (!target) return;
    const action = target.getAttribute('data-onchange');
    const value = target.getAttribute('data-onchange-value') || target.value;
    if (typeof window[action] === 'function') {
      window[action](value);
    }
  });
});

// Komprimace videa - stejný princip jako ve videotéce
// Max 1920x1080, 2.5 Mbps, 30 FPS
async function komprimovatVideoObjednavka(videoSoubor) {
  return new Promise((resolve, reject) => {
    try {
      const video = document.createElement('video');
      video.preload = 'metadata';
      video.muted = true;
      video.playsInline = true;

      video.onloadedmetadata = () => {
        const sirka = video.videoWidth;
        const vyska = video.videoHeight;

        let cilSirka = sirka;
        let cilVyska = vyska;
        if (sirka > 1920 || vyska > 1080) {
          const pomer = Math.min(1920 / sirka, 1080 / vyska);
          cilSirka = Math.round(sirka * pomer);
          cilVyska = Math.round(vyska * pomer);
        }

        const canvas = document.createElement('canvas');
        canvas.width = cilSirka;
        canvas.height = cilVyska;
        const ctx = canvas.getContext('2d');
        const stream = canvas.captureStream(30);

        const mimeTypy = ['video/webm;codecs=vp9', 'video/webm;codecs=vp8', 'video/webm', 'video/mp4'];
        let vybranyMime = '';
        for (const mime of mimeTypy) {
          if (MediaRecorder.isTypeSupported(mime)) {
            vybranyMime = mime;
            break;
          }
        }

        if (!vybranyMime) {
          reject(new Error('Žádný video kodek není podporován'));
          return;
        }

        let recorder;
        try {
          recorder = new MediaRecorder(stream, { mimeType: vybranyMime, videoBitsPerSecond: 2500000 });
        } catch (e) {
          recorder = new MediaRecorder(stream, { mimeType: vybranyMime });
        }

        const kusy = [];
        recorder.ondataavailable = (e) => { if (e.data.size > 0) kusy.push(e.data); };
        recorder.onstop = () => resolve(new Blob(kusy, { type: vybranyMime }));

        video.onplay = () => {
          const kreslit = () => {
            if (video.paused || video.ended) { recorder.stop(); return; }
            ctx.drawImage(video, 0, 0, cilSirka, cilVyska);
            requestAnimationFrame(kreslit);
          };
          recorder.start();
          requestAnimationFrame(kreslit);
        };

        video.onended = () => { if (recorder.state === 'recording') recorder.stop(); };
        video.onerror = () => reject(new Error('Chyba načítání videa'));

        video.src = URL.createObjectURL(videoSoubor);
        video.play().catch(reject);
      };

      video.onerror = () => reject(new Error('Nepodařilo se načíst video'));
      video.src = URL.createObjectURL(videoSoubor);
    } catch (chyba) {
      reject(chyba);
    }
  });
}

// Dynamický text pro fakturaci