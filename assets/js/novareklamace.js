const logger = window.logger || console;
const WGS = {
  photos: [],
  map: null,
  marker: null,
  isLoggedIn: false,
  
  init() {
    logger.log('🚀 WGS init...');
    this.checkLoginStatus();
    this.initUserMode();
    this.initCalculationDisplay();
    this.initMobileMenu();
    this.initMap();
    this.initForm();
    this.initPhotos();
    this.initProvedeni();
    this.initLanguage();
    this.initCustomCalendar();
  },
  
  checkLoginStatus() {
    const userToken = localStorage.getItem('wgs_user_token') || sessionStorage.getItem('wgs_user_token');
    this.isLoggedIn = window.WGS_USER_LOGGED_IN !== undefined ? window.WGS_USER_LOGGED_IN : !!userToken;
    logger.log('👤 Logged in:', this.isLoggedIn);
  },
  
  initMap() {
    if (typeof L === 'undefined') {
      logger.error('❌ Leaflet not loaded');
      return;
    }
    
    try {
      this.map = L.map('mapContainer').setView([49.8, 15.5], 7);

      // BEZPEČNOST: API klíč je skrytý v proxy, ne v JavaScriptu
      L.tileLayer('api/geocode_proxy.php?action=tile&z={z}&x={x}&y={y}', {
        maxZoom: 20,
        attribution: '© OpenStreetMap'
      }).addTo(this.map);

      logger.log('✅ Map initialized');
      this.initAddressGeocoding();
      
    } catch (err) {
      logger.error('❌ Map error:', err);
    }
  },
  
  initAddressGeocoding() {
    const uliceInput = document.getElementById('ulice');
    const mestoInput = document.getElementById('mesto');
    const pscInput = document.getElementById('psc');
    
    const updateMapWithGPS = (lat, lon) => {
      if (this.marker) {
        this.map.removeLayer(this.marker);
      }
      this.marker = L.marker([lat, lon]).addTo(this.map);
      this.map.setView([lat, lon], 15);
      logger.log(`📍 Map updated to GPS: ${lat}, ${lon}`);
    };
    
    this.updateMapWithGPS = updateMapWithGPS;
    
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
        const response = await fetch(
          `api/geocode_proxy.php?action=search&address=${encodeURIComponent(address)}`
        );
        
        if (response.ok) {
          const data = await response.json();
          if (data.features && data.features.length > 0) {
            const [lon, lat] = data.features[0].geometry.coordinates;
            updateMapWithGPS(lat, lon);
          }
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
    
    if (uliceInput && dropdownUlice) {
      uliceInput.addEventListener('input', async (e) => {
        clearTimeout(uliceTimeout);
        const query = e.target.value.trim();
        
        if (query.length < 3) {
          dropdownUlice.style.display = 'none';
          return;
        }
        
        uliceTimeout = setTimeout(async () => {
          try {
            const mesto = document.getElementById('mesto').value.trim();
            const searchText = mesto ? `${query}, ${mesto}, Czech Republic` : `${query}, Czech Republic`;
            
            const response = await fetch(
              `api/geocode_proxy.php?action=autocomplete&text=${encodeURIComponent(searchText)}&type=street`
            );
            
            if (response.ok) {
              const data = await response.json();
              
              if (data.features && data.features.length > 0) {
                dropdownUlice.innerHTML = '';
                dropdownUlice.style.display = 'block';
                
                data.features.forEach(feature => {
                  const div = document.createElement('div');
                  div.style.padding = '0.8rem';
                  div.style.cursor = 'pointer';
                  div.style.borderBottom = '1px solid #eee';
                  div.style.fontSize = '0.9rem';
                  
                  const street = feature.properties.street || '';
                  const houseNumber = feature.properties.housenumber || '';
                  const city = feature.properties.city || '';
                  const postcode = feature.properties.postcode || '';
                  
                  div.textContent = `${street} ${houseNumber}, ${city}`;
                  
                  div.addEventListener('mouseenter', () => {
                    div.style.background = '#f5f5f5';
                  });
                  
                  div.addEventListener('mouseleave', () => {
                    div.style.background = 'white';
                  });
                  
                  div.addEventListener('click', () => {
                    uliceInput.value = `${street} ${houseNumber}`.trim();
                    document.getElementById('mesto').value = city;
                    document.getElementById('psc').value = postcode;
                    
                    const [lon, lat] = feature.geometry.coordinates;
                    this.updateMapWithGPS(lat, lon);
                    
                    dropdownUlice.style.display = 'none';
                    this.toast('✓ Adresa vyplněna', 'success');
                  });
                  
                  dropdownUlice.appendChild(div);
                });
              } else {
                dropdownUlice.style.display = 'none';
              }
            }
          } catch (err) {
            logger.error('Autocomplete error:', err);
            dropdownUlice.style.display = 'none';
          }
        }, 300);
      });
    }
    
    if (mestoInput && dropdownMesto) {
      mestoInput.addEventListener('input', async (e) => {
        clearTimeout(mestoTimeout);
        const query = e.target.value.trim();
        
        if (query.length < 2) {
          dropdownMesto.style.display = 'none';
          return;
        }
        
        mestoTimeout = setTimeout(async () => {
          try {
            const response = await fetch(
              `api/geocode_proxy.php?action=autocomplete&text=${encodeURIComponent(query)}&type=city`
            );
            
            if (response.ok) {
              const data = await response.json();
              
              if (data.features && data.features.length > 0) {
                dropdownMesto.innerHTML = '';
                dropdownMesto.style.display = 'block';
                
                data.features.forEach(feature => {
                  const div = document.createElement('div');
                  div.style.padding = '0.8rem';
                  div.style.cursor = 'pointer';
                  div.style.borderBottom = '1px solid #eee';
                  div.style.fontSize = '0.9rem';
                  
                  const city = feature.properties.city || feature.properties.name || '';
                  const postcode = feature.properties.postcode || '';
                  
                  div.textContent = postcode ? `${city} (${postcode})` : city;
                  
                  div.addEventListener('mouseenter', () => {
                    div.style.background = '#f5f5f5';
                  });
                  
                  div.addEventListener('mouseleave', () => {
                    div.style.background = 'white';
                  });
                  
                  div.addEventListener('click', () => {
                    mestoInput.value = city;
                    if (postcode) {
                      document.getElementById('psc').value = postcode;
                    }
                    
                    dropdownMesto.style.display = 'none';
                    this.toast('✓ Město vybráno', 'success');
                  });
                  
                  dropdownMesto.appendChild(div);
                });
              } else {
                dropdownMesto.style.display = 'none';
              }
            }
          } catch (err) {
            logger.error('Autocomplete error:', err);
            dropdownMesto.style.display = 'none';
          }
        }, 300);
      });
    }
    
    document.addEventListener('click', (e) => {
      if (e.target !== uliceInput && e.target !== dropdownUlice) {
        dropdownUlice.style.display = 'none';
      }
      if (e.target !== mestoInput && e.target !== dropdownMesto) {
        dropdownMesto.style.display = 'none';
      }
    });
  },
  
  checkAndUpdateMapFromAddress() {
    const uliceInput = document.getElementById('ulice');
    if (uliceInput && uliceInput.value.trim()) {
      logger.log('🔍 Checking pre-filled address...');
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
        calculatorBox.style.display = 'block';
      }
      
      if (cisloInput) {
        cisloInput.removeAttribute('readonly');
        cisloInput.value = '';
        cisloInput.placeholder = 'Číslo objednávky/reklamace od prodejce (pokud máte)';
        cisloInput.style.backgroundColor = '';
        cisloInput.style.cursor = 'text';
      }
      
      if (datumProdejeInput) {
        datumProdejeInput.value = 'nevyplňuje se';
        datumProdejeInput.readOnly = true;
        datumProdejeInput.style.backgroundColor = '#f5f5f5';
        datumProdejeInput.style.cursor = 'not-allowed';
        datumProdejeInput.classList.add('disabled-field');
      }
      
      if (datumReklamaceInput) {
        datumReklamaceInput.value = 'nevyplňuje se';
        datumReklamaceInput.readOnly = true;
        datumReklamaceInput.style.backgroundColor = '#f5f5f5';
        datumReklamaceInput.style.cursor = 'not-allowed';
        datumReklamaceInput.classList.add('disabled-field');
      }
      
      if (doplnujiciInfoTextarea) {
        doplnujiciInfoTextarea.value = 'nevyplňuje se';
        doplnujiciInfoTextarea.readOnly = true;
        doplnujiciInfoTextarea.style.backgroundColor = '#f5f5f5';
        doplnujiciInfoTextarea.style.cursor = 'not-allowed';
      }
      
      if (modeInfo) {
        modeInfo.style.display = 'block';
        document.getElementById('modeTitle').textContent = '📋 Režim: Zákazník (bez přihlášení)';
        document.getElementById('modeDescription').textContent = 'Objednáváte mimozáruční servis. Některá pole jsou předvyplněna a nelze je měnit.';
      }
      
      logger.log('📋 Mode: Customer');
    } else {
      if (calculatorBox) {
        calculatorBox.style.display = 'none';
      }
      
      if (modeInfo) {
        modeInfo.style.display = 'block';
        modeInfo.style.borderLeftColor = '#006600';
        modeInfo.style.background = '#f0fff0';
        document.getElementById('modeTitle').textContent = '✓ Režim: Prodejce (přihlášen)';
        document.getElementById('modeDescription').textContent = 'Máte plný přístup ke všem polím formuláře.';
      }
      logger.log('📋 Mode: Seller');
    }
  },
  
  initCalculationDisplay() {
    const urlParams = new URLSearchParams(window.location.search);
    const fromCalculator = urlParams.get('from_calculator');
    
    if (fromCalculator === 'true') {
      const calculationBox = document.getElementById('calculationBox');
      if (calculationBox) {
        calculationBox.style.display = 'block';
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
      logger.warn("⚠️ initMobileMenu skipped - elements not found");
      return;
    }
    
    logger.log('📱 Initializing mobile menu...');
    
    hamburger.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      hamburger.classList.toggle('active');
      nav.classList.toggle('active');
      menuOverlay.classList.toggle('active');
      document.body.style.overflow = nav.classList.contains('active') ? 'hidden' : 'auto';
    });
    
    menuOverlay.addEventListener('click', () => {
      hamburger.classList.remove('active');
      nav.classList.remove('active');
      menuOverlay.classList.remove('active');
      document.body.style.overflow = 'auto';
    });
    
    nav.querySelectorAll('a').forEach(link => {
      link.addEventListener('click', () => {
        hamburger.classList.remove('active');
        nav.classList.remove('active');
        menuOverlay.classList.remove('active');
        document.body.style.overflow = 'auto';
      });
    });
    
    logger.log('✅ Mobile menu fully initialized');
  },
  
  initForm() {
    const form = document.getElementById('reklamaceForm');
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      await this.submitForm();
    });

    // Funkce pro změnu fakturace
    const fakturaceSelect = document.getElementById('fakturace_firma');
    const fakturaHint = document.getElementById('faktura_hint');

    if (fakturaceSelect && fakturaHint) {
      fakturaceSelect.addEventListener('change', (e) => {
        const value = e.target.value;
        if (value === 'CZ') {
          fakturaHint.textContent = 'Tato objednávka se bude fakturovat na CZ firmu';
        } else if (value === 'SK') {
          fakturaHint.textContent = 'Tato objednávka se bude fakturovat na SK firmu';
        }
      });
    }
  },
  
  async submitForm() {
    const consentCheckbox = document.getElementById('gdpr_consent');
    if (consentCheckbox && !consentCheckbox.checked) {
      consentCheckbox.focus();
      this.toast('Pro odeslání je nutný souhlas se zpracováním osobních údajů.', 'error');
      return;
    }

    this.toast('Odesílám...', 'info');
    try {
      const formData = new FormData();
      formData.append('action', 'create');
      formData.append('typ', this.isLoggedIn ? 'reklamace' : 'servis');
      formData.append('cislo', document.getElementById('cislo').value || '');
      formData.append('datum_prodeje', document.getElementById('datum_prodeje').value || '');
      formData.append('datum_reklamace', document.getElementById('datum_reklamace').value || '');
      formData.append('jmeno', document.getElementById('jmeno').value || '');
      formData.append('email', document.getElementById('email').value || '');
      formData.append('telefon', document.getElementById('telefon').value || '');
      
      const ulice = document.getElementById('ulice')?.value || '';
      const mesto = document.getElementById('mesto')?.value || '';
      const psc = document.getElementById('psc')?.value || '';
      const adresa = [ulice, mesto, psc].filter(x => x).join(', ');
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

      // Získat CSRF token
      const csrfResponse = await fetch('app/controllers/get_csrf_token.php');
      const csrfData = await csrfResponse.json();
      if (csrfData.status === 'success') {
        formData.append('csrf_token', csrfData.token);
      }
      
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
          await this.uploadPhotos(workflowId);
        }

        this.toast('✓ Požadavek byl úspěšně odeslán!', 'success');
        setTimeout(() => {
          if (this.isLoggedIn) {
            window.location.href = 'seznam.php';
          } else {
            const referenceText = referenceNumber
              ? `Číslo reklamace: ${referenceNumber}`
              : 'Číslo reklamace vám zašleme e-mailem.';
            alert(`Děkujeme! Vaše objednávka byla přijata.\n\n${referenceText}\n\nBrzy vás budeme kontaktovat.`);
            window.location.href = 'index.php';
          }
        }, 1500);
      } else {
        throw new Error(result.message || 'Chyba při ukládání');
      }
    } catch (error) {
      logger.error('Chyba při odesílání formuláře:', error);
      this.toast('❌ Chyba při odesílání: ' + error.message, 'error');
    }
  },
  
  async uploadPhotos(reklamaceId) {
    console.log("🚀 uploadPhotos VOLÁNO!", reklamaceId);
    if (!this.photos || this.photos.length === 0) return;
    console.log("📸 Počet fotek:", this.photos.length);
    try {
      const formData = new FormData();
      formData.append('reklamace_id', reklamaceId);
      formData.append('photo_type', 'problem');
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
      logger.log('✓ Fotky úspěšně nahrány');
    } catch (error) {
      logger.error('Chyba při nahrávání fotek:', error);
    }
  },
  
  initPhotos() {
    const btn = document.getElementById('uploadPhotosBtn');
    const input = document.getElementById('photoInput');
    btn.addEventListener('click', () => input.click());
    input.addEventListener('change', async (e) => {
      const files = Array.from(e.target.files);
      if (this.photos.length + files.length > 10) {
        this.toast('❌ Maximálně 10 fotografií', 'error');
        return;
      }
      for (const file of files) {
        const compressed = await this.compressImage(file);
        const base64 = await this.toBase64(compressed);
        this.photos.push({ data: base64, file: compressed });
      }
      this.renderPhotos();
      this.toast(`✓ Přidáno ${files.length} fotek`, 'success');
    });
  },
  
  async compressImage(file) {
    return new Promise((resolve) => {
      const reader = new FileReader();
      reader.onload = (e) => {
        const img = new Image();
        img.onload = () => {
          const canvas = document.createElement('canvas');
          const maxW = 1200;
          const scale = Math.min(1, maxW / img.width);
          canvas.width = img.width * scale;
          canvas.height = img.height * scale;
          const ctx = canvas.getContext('2d');
          ctx.drawImage(img, 0, 0, canvas.width, canvas.height);
          canvas.toBlob((blob) => {
            resolve(new File([blob], file.name, { type: 'image/jpeg' }));
          }, 'image/jpeg', 0.85);
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
        this.toast("✓ Fotka odstraněna", "info");
      });
      container.appendChild(div);
    });
  },
  
  initProvedeni() {
    const btn = document.getElementById('selectProvedeniBtn');
    const overlay = document.getElementById('provedeniOverlay');
    const closeBtn = document.getElementById('closeProvedeni');
    const cards = document.querySelectorAll('.provedeni-card');
    
    btn.addEventListener('click', () => overlay.classList.add('active'));
    closeBtn.addEventListener('click', () => overlay.classList.remove('active'));
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) overlay.classList.remove('active');
    });
    cards.forEach(card => {
      card.addEventListener('click', () => {
        const value = card.dataset.value;
        document.getElementById('provedeni').value = value;
        overlay.classList.remove('active');
        this.toast(`✓ Provedení: ${value}`, 'info');
      });
    });
  },
  
  toast(message, type = 'info') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
  },
  

  initCustomCalendar() {
    const overlay = document.getElementById('calendarOverlay');
    if (!overlay) return;
    const grid = document.getElementById('calendarGrid');
    const monthYearDisplay = document.getElementById('calendarMonthYear');
    let currentDate = new Date();
    let selectedInput = null;
    const monthNames = ['Leden', 'Únor', 'Březen', 'Duben', 'Květen', 'Červen', 'Červenec', 'Srpen', 'Září', 'Říjen', 'Listopad', 'Prosinec'];
    const weekDays = ['Po', 'Út', 'St', 'Čt', 'Pá', 'So', 'Ne'];
    const self = this;
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
          if (selectedInput) { selectedInput.value = selected; if (selectedInput.id === 'datum_reklamace') { self.calculateWarranty(); } }
          overlay.classList.remove('active');
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
          wrapper.addEventListener('click', () => { if (!input.readOnly || self.isLoggedIn) { selectedInput = input; currentDate = new Date(); renderCalendar(); overlay.classList.add('active'); } });
        }
      }
    });
    document.getElementById('prevMonth').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() - 1); renderCalendar(); });
    document.getElementById('nextMonth').addEventListener('click', () => { currentDate.setMonth(currentDate.getMonth() + 1); renderCalendar(); });
    document.getElementById('closeCalendar').addEventListener('click', () => { overlay.classList.remove('active'); });
    overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.classList.remove('active'); });
  },

  calculateWarranty() {
    const datumProdeje = document.getElementById('datum_prodeje').value;
    const datumReklamace = document.getElementById('datum_reklamace').value;
    const warning = document.getElementById('warrantyWarning');
    if (!datumProdeje || !datumReklamace) { warning.style.display = 'none'; return; }
    const parseCzDate = (str) => { const parts = str.split('.'); return new Date(parts[2], parts[1] - 1, parts[0]); };
    const prodej = parseCzDate(datumProdeje);
    const reklamace = parseCzDate(datumReklamace);
    const warrantyEnd = new Date(prodej);
    warrantyEnd.setFullYear(warrantyEnd.getFullYear() + 2);
    const daysRemaining = Math.ceil((warrantyEnd - reklamace) / (1000 * 60 * 60 * 24));
    warning.style.display = 'block';
    if (daysRemaining > 0) {
      warning.className = '';
      warning.innerHTML = '✓ <strong>Záruka platí</strong><br>Do konce záruky zbývá <strong>' + daysRemaining + ' dní</strong> (konec: ' + warrantyEnd.toLocaleDateString('cs-CZ') + ')';
    } else {
      warning.className = 'expired';
      warning.innerHTML = '✗ <strong>Záruka vypršela</strong><br>Záruka skončila ' + Math.abs(daysRemaining) + ' dní před reklamací (konec: ' + warrantyEnd.toLocaleDateString('cs-CZ') + ')';
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

// Dynamický text pro fakturaci