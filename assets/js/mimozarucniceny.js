const CALC = {
  map: null,
  marker: null,
  warehouseMarker: null,
  routeLayer: null,
  customerAddress: null,
  distance: 0,
  warehouse: { lat: 50.08026389885034, lon: 14.59812452579323, address: 'Do Dubče 364, Běchovice 190 11' },
  
  init() {
    logger.log('🧮 Calculator init');
    this.initMobileMenu();
    this.initMap();
    this.initEventListeners();
    this.initLanguage();
  },
  
  initMobileMenu() {
    logger.log('📱 Initializing mobile menu...');
    
    const hamburger = document.getElementById('hamburger');
    const mainNav = document.getElementById('mainNav');
    const menuOverlay = document.getElementById('menuOverlay');
    
    if (!hamburger || !mainNav || !menuOverlay) {
      logger.error('❌ Menu elements not found');
      return;
    }
    
    logger.log('✅ All menu elements found');
    
    hamburger.addEventListener('click', (e) => {
      e.preventDefault();
      e.stopPropagation();
      
      hamburger.classList.toggle('active');
      mainNav.classList.toggle('active');
      menuOverlay.classList.toggle('active');
      
      if (mainNav.classList.contains('active')) {
        document.body.style.overflow = 'hidden';
        logger.log('✅ Menu opened');
      } else {
        document.body.style.overflow = 'auto';
        logger.log('✅ Menu closed');
      }
    });
    
    menuOverlay.addEventListener('click', () => {
      logger.log('🔄 Overlay clicked - closing menu');
      hamburger.classList.remove('active');
      mainNav.classList.remove('active');
      menuOverlay.classList.remove('active');
      document.body.style.overflow = 'auto';
    });
    
    const navLinks = mainNav.querySelectorAll('a');
    navLinks.forEach(link => {
      link.addEventListener('click', () => {
        logger.log('🔗 Nav link clicked - closing menu');
        hamburger.classList.remove('active');
        mainNav.classList.remove('active');
        menuOverlay.classList.remove('active');
        document.body.style.overflow = 'auto';
      });
    });
    
    logger.log('✅ Mobile menu fully initialized');
  },
  
  initMap() {
    if (typeof L === 'undefined') return;
    
    try {
      this.map = L.map('mapContainer').setView([49.8, 15.5], 7);
      
      const key = 'a4b2955eeb674dd8b6601f54da2e80a8';
      L.tileLayer(`https://maps.geoapify.com/v1/tile/osm-carto/{z}/{x}/{y}.png?apiKey=${key}`, {
        maxZoom: 20,
        attribution: '© OpenStreetMap'
      }).addTo(this.map);
      
      // Use exact GPS coordinates for warehouse marker
      this.warehouseMarker = L.marker([this.warehouse.lat, this.warehouse.lon], {
        icon: L.divIcon({
          html: '<div style="background:#006600;color:white;padding:5px 10px;border-radius:3px;font-weight:bold;white-space:nowrap;">WGS</div>',
          className: '',
          iconSize: [50, 30],
          iconAnchor: [50, 15]
        })
      }).addTo(this.map);
      
      logger.log('✅ Map init with GPS:', this.warehouse.lat, this.warehouse.lon);
    } catch (err) {
      logger.error('❌ Map error:', err);
    }
  },
  
  initEventListeners() {
    const serviceLocationRadios = document.querySelectorAll('input[name="service_location"]');
    serviceLocationRadios.forEach(radio => {
      radio.addEventListener('change', (e) => this.handleServiceLocationChange(e.target.value));
    });
    
    const serviceTypeRadios = document.querySelectorAll('input[name="service_type"]');
    serviceTypeRadios.forEach(radio => {
      radio.addEventListener('change', (e) => this.handleServiceTypeChange(e.target.value));
    });
    
    const uliceInput = document.getElementById('ulice');
    if (uliceInput) {
      let timeout;
      uliceInput.addEventListener('input', (e) => {
        clearTimeout(timeout);
        const query = e.target.value.trim();
        
        if (query.length < 3) {
          document.getElementById('autocompleteDropdown').style.display = 'none';
          return;
        }
        
        timeout = setTimeout(() => this.searchAddress(query), 300);
      });
    }
    
    const calculateBtn = document.getElementById('calculateBtn');
    if (calculateBtn) calculateBtn.addEventListener('click', () => this.calculatePrice());
    
    const orderServiceBtn = document.getElementById('orderServiceBtn');
    if (orderServiceBtn) orderServiceBtn.addEventListener('click', (e) => { e.preventDefault(); this.orderService(); });
    
    const partCount = document.getElementById('partCount');
    if (partCount) {
      partCount.addEventListener('input', () => {
        if (document.getElementById('priceSummary').style.display !== 'none') this.calculatePrice();
      });
    }
    
    const repairTypeRadios = document.querySelectorAll('input[name="repair_type"]');
    repairTypeRadios.forEach(radio => {
      radio.addEventListener('change', () => {
        if (document.getElementById('priceSummary').style.display !== 'none') this.calculatePrice();
      });
    });
    
    // Close dropdown when clicking outside
    document.addEventListener('click', (e) => {
      const dropdown = document.getElementById('autocompleteDropdown');
      if (e.target !== uliceInput && !dropdown.contains(e.target)) {
        dropdown.style.display = 'none';
      }
    });
  },
  
  handleServiceLocationChange(value) {
    const addressSection = document.getElementById('addressSection');
    const stepNumber3 = document.getElementById('stepNumber3');
    const stepNumber4 = document.getElementById('stepNumber4');
    
    if (value === 'home') {
      addressSection.style.display = 'block';
      stepNumber3.textContent = '3';
      stepNumber4.textContent = '4';
    } else {
      addressSection.style.display = 'none';
      stepNumber3.textContent = '2';
      stepNumber4.textContent = '3';
      document.getElementById('distanceInfo').classList.remove('show');
      if (document.getElementById('priceSummary').style.display !== 'none') this.calculatePrice();
    }
  },
  
  handleServiceTypeChange(value) {
    const repairTypeSection = document.getElementById('repairTypeSection');
    
    if (value === 'diagnosis_only') {
      repairTypeSection.style.display = 'none';
    } else {
      repairTypeSection.style.display = 'block';
      const serviceLocation = document.querySelector('input[name="service_location"]:checked').value;
      document.getElementById('stepNumber4').textContent = serviceLocation === 'home' ? '4' : '3';
    }
    
    if (document.getElementById('priceSummary').style.display !== 'none') this.calculatePrice();
  },
  
  async searchAddress(query) {
    const key = 'a4b2955eeb674dd8b6601f54da2e80a8';
    try {
      const res = await fetch(`api/geocode_proxy.php?action=autocomplete&text=${encodeURIComponent(query)}&type=street`);
      const data = await res.json();
      
      const dropdown = document.getElementById('autocompleteDropdown');
      dropdown.innerHTML = '';
      
      if (data.features && data.features.length > 0) {
        data.features.forEach(f => {
          const div = document.createElement('div');
          div.style.padding = '0.8rem';
          div.style.cursor = 'pointer';
          div.style.borderBottom = '1px solid #eee';
          div.style.fontSize = '0.9rem';
          
          const street = f.properties.street || '';
          const houseNumber = f.properties.housenumber || '';
          const city = f.properties.city || '';
          const postcode = f.properties.postcode || '';
          
          div.textContent = `${street} ${houseNumber}, ${city}${postcode ? ' (' + postcode + ')' : ''}`;
          
          div.onmouseenter = () => div.style.background = '#f5f5f5';
          div.onmouseleave = () => div.style.background = 'white';
          div.onclick = () => { 
            this.selectAddress(f); 
            dropdown.style.display = 'none'; 
          };
          
          dropdown.appendChild(div);
        });
        dropdown.style.display = 'block';
      } else {
        dropdown.style.display = 'none';
      }
    } catch (err) {
      logger.error('❌ Geocoding error:', err);
    }
  },
  
  async selectAddress(feature) {
    const street = feature.properties.street || '';
    const houseNumber = feature.properties.housenumber || '';
    const city = feature.properties.city || '';
    
    document.getElementById('ulice').value = `${street} ${houseNumber}, ${city}`;
    
    this.customerAddress = {
      lat: feature.properties.lat,
      lon: feature.properties.lon,
      formatted: `${street} ${houseNumber}, ${city}`
    };
    
    if (this.marker) this.map.removeLayer(this.marker);
    
    this.marker = L.marker([this.customerAddress.lat, this.customerAddress.lon], {
      icon: L.divIcon({
        html: '<div style="background:#0066cc;color:white;padding:5px 10px;border-radius:3px;font-weight:bold;white-space:nowrap;">Zákazník</div>',
        className: '',
        iconSize: [100, 30],
        iconAnchor: [50, 15]
      })
    }).addTo(this.map);
    
    await this.calculateRoute();
    this.toast('✓ Adresa vybrána', 'success');
  },
  
  async calculateRoute() {
    const key = 'a4b2955eeb674dd8b6601f54da2e80a8';
    
    try {
      const url = `https://api.geoapify.com/v1/routing?waypoints=${this.warehouse.lat},${this.warehouse.lon}|${this.customerAddress.lat},${this.customerAddress.lon}&mode=drive&apiKey=${key}`;
      const res = await fetch(url);
      const data = await res.json();
      
      if (data.features && data.features.length > 0) {
        const route = data.features[0];
        const distanceKm = (route.properties.distance / 1000).toFixed(1);
        this.distance = parseFloat(distanceKm) * 2;
        
        if (this.routeLayer) this.map.removeLayer(this.routeLayer);
        
        const coordinates = route.geometry.coordinates[0].map(coord => [coord[1], coord[0]]);
        this.routeLayer = L.polyline(coordinates, {
          color: '#0066cc',
          weight: 4,
          opacity: 0.7
        }).addTo(this.map);
        
        this.map.fitBounds(this.routeLayer.getBounds(), { padding: [50, 50] });
        
        const distanceText = document.getElementById('distanceText');
        distanceText.innerHTML = `
          <div style="margin-top:0.5rem;">
            <strong>Vzdálenost jedním směrem:</strong> ${distanceKm} km<br>
            <strong>Vzdálenost celkem (tam a zpět):</strong> ${this.distance.toFixed(1)} km<br>
            <strong>Cena dopravy:</strong> ${(this.distance * 0.28).toFixed(2)} € (${this.distance.toFixed(1)} km × 0.28 €/km)
          </div>
        `;
        
        document.getElementById('distanceInfo').classList.add('show');
        logger.log(`✓ Route: ${this.distance.toFixed(1)} km`);
      }
    } catch (err) {
      logger.error('❌ Route error:', err);
      this.toast('❌ Nepodařilo se vypočítat trasu', 'error');
    }
  },
  
  calculatePrice() {
    const serviceLocation = document.querySelector('input[name="service_location"]:checked').value;
    const serviceType = document.querySelector('input[name="service_type"]:checked').value;
    const repairType = document.querySelector('input[name="repair_type"]:checked')?.value;
    const partCount = parseInt(document.getElementById('partCount').value) || 1;
    
    if (serviceLocation === 'home' && !this.customerAddress) {
      this.toast('⚠️ Nejdříve zadejte adresu', 'error');
      return;
    }
    
    let transportCost = 0;
    let diagnosisCost = 0;
    let repairCost = 0;
    let repairLabel = '';
    
    if (serviceLocation === 'home') {
      transportCost = parseFloat((this.distance * 0.28).toFixed(2));
    }
    
    switch (serviceType) {
      case 'diagnosis_only':
        diagnosisCost = 100;
        repairCost = 0;
        repairLabel = 'Oprava (nevybrána)';
        break;
      case 'diagnosis_and_repair':
        diagnosisCost = 0;
        switch (repairType) {
          case 'mechanical':
            repairCost = 155 * partCount;
            repairLabel = `Mechanická oprava (${partCount} díl${partCount > 1 ? 'y' : ''})`;
            break;
          case 'upholstery':
            repairCost = partCount === 1 ? 190 : 190 + (70 * (partCount - 1));
            repairLabel = `Oprava s rozčalouněním (${partCount} díl${partCount > 1 ? 'y' : ''})`;
            break;
        }
        break;
      case 'repair_only':
        diagnosisCost = 0;
        switch (repairType) {
          case 'mechanical':
            repairCost = 155 * partCount;
            repairLabel = `Mechanická oprava (${partCount} díl${partCount > 1 ? 'y' : ''})`;
            break;
          case 'upholstery':
            repairCost = partCount === 1 ? 190 : 190 + (70 * (partCount - 1));
            repairLabel = `Oprava s rozčalouněním (${partCount} díl${partCount > 1 ? 'y' : ''})`;
            break;
        }
        break;
    }
    
    const totalCost = transportCost + diagnosisCost + repairCost;
    
    const transportRow = document.getElementById('transportRow');
    transportRow.style.display = serviceLocation === 'workshop' ? 'none' : 'flex';
    if (serviceLocation === 'home') document.getElementById('transportPrice').textContent = `${transportCost.toFixed(2)} €`;
    
    const diagnosisRow = document.getElementById('diagnosisRow');
    if (serviceType === 'diagnosis_only') {
      diagnosisRow.style.display = 'flex';
      document.getElementById('diagnosisPrice').textContent = `100 €`;
    } else if (serviceType === 'diagnosis_and_repair') {
      diagnosisRow.style.display = 'flex';
      document.getElementById('diagnosisPrice').textContent = `0 € (zahrnuto)`;
    } else {
      diagnosisRow.style.display = 'none';
    }
    
    const repairRow = document.getElementById('repairRow');
    if (serviceType === 'diagnosis_only') {
      repairRow.style.display = 'none';
    } else {
      repairRow.style.display = 'flex';
      document.getElementById('repairLabel').textContent = repairLabel;
      document.getElementById('repairPrice').textContent = `${repairCost.toFixed(0)} €`;
    }
    
    document.getElementById('totalPrice').textContent = `${totalCost.toFixed(2)} €`;
    document.getElementById('priceSummary').style.display = 'block';
    document.getElementById('priceSummary').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    
    this.toast('✓ Cena vypočítána', 'success');
  },
  
  orderService() {
    const summary = document.getElementById('priceSummary');
    if (summary.style.display === 'none') {
      this.toast('⚠️ Nejdříve spočítejte cenu', 'error');
      return;
    }
    
    const serviceLocation = document.querySelector('input[name="service_location"]:checked').value;
    const serviceType = document.querySelector('input[name="service_type"]:checked').value;
    const repairType = document.querySelector('input[name="repair_type"]:checked')?.value || 'none';
    const partCount = parseInt(document.getElementById('partCount').value) || 1;
    
    const transportPrice = document.getElementById('transportPrice').textContent;
    const diagnosisPrice = document.getElementById('diagnosisPrice').textContent;
    const repairPrice = document.getElementById('repairPrice').textContent;
    const totalPrice = document.getElementById('totalPrice').textContent;
    
    let address = '';
    if (serviceLocation === 'home' && this.customerAddress) {
      address = this.customerAddress.formatted;
    }
    
    const params = new URLSearchParams({
      calc_service_location: serviceLocation,
      calc_service_type: serviceType,
      calc_repair_type: repairType,
      calc_part_count: partCount,
      calc_transport: transportPrice,
      calc_diagnosis: diagnosisPrice,
      calc_repair: repairPrice,
      calc_total: totalPrice,
      calc_address: address,
      calc_distance: this.distance.toFixed(1),
      from_calculator: 'true'
    });
    
    window.location.href = `novareklamace.php?${params.toString()}`;
  },
  
  toast(message, type = 'info') {
    const toast = document.getElementById('toast');
    toast.textContent = message;
    toast.className = 'toast ' + type;
    toast.classList.add('show');
    setTimeout(() => toast.classList.remove('show'), 3000);
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
          if (text.includes('<br>')) el.innerHTML = text;
          else el.textContent = text;
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
      
      const titles = { cs: 'Kalkulačka ceny servisu | WGS', en: 'Service Price Calculator | WGS', it: 'Calcolatore dei Prezzi | WGS' };
      document.title = titles[lang];
    };
    
    if (currentLang !== 'cs') switchLanguage(currentLang);
    
    document.querySelectorAll('.lang-flag').forEach(flag => {
      flag.addEventListener('click', () => switchLanguage(flag.dataset.lang));
    });
  }
};

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', () => CALC.init());
} else {
  CALC.init();
}