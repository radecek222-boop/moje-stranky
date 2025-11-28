/**
 * WGS Map Module
 * Společný modul pro práci s mapou a geokódováním
 * Používá Leaflet.js a Geoapify API přes proxy
 *
 * @version 1.0.0
 * @requires Leaflet.js
 * @requires logger (window.logger)
 */

const WGSMap = {
  // Konfigurace
  config: {
    defaultCenter: [49.8, 15.5],
    defaultZoom: 7,
    maxZoom: 19,
    // PERFORMANCE FIX: Přímé OSM tiles místo proxy
    // Důvod: Proxy pro tiles je extrémně pomalá (stovky PHP requestů)
    // OSM tiles jsou veřejné, zdarma a optimalizované pro rychlé načítání
    tileUrl: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
    debounceAutocomplete: 300,
    debounceRoute: 500,
    minCharsAutocomplete: 2
  },

  // Instance
  map: null,
  markers: {},
  layers: {},

  // Request controllers (pro cancellation)
  controllers: {
    autocomplete: null,
    geocode: null,
    route: null
  },

  // Cache
  cache: {
    geocode: new Map(),
    route: new Map()
  },

  /**
   * Inicializace mapy
   * @param {string} containerId - ID elementu pro mapu
   * @param {Object} options - Volitelné nastavení {center, zoom, onInit}
   * @returns {Object|null} - Map instance nebo null pokud Leaflet chybí
   */
  init(containerId, options = {}) {
    const logger = window.logger || console;

    // Kontrola Leaflet
    if (typeof L === 'undefined') {
      logger.error('Leaflet not loaded');
      return null;
    }

    const container = document.getElementById(containerId);
    if (!container) {
      logger.error(`Map container #${containerId} not found`);
      return null;
    }

    try {
      const center = options.center || this.config.defaultCenter;
      const zoom = options.zoom || this.config.defaultZoom;

      // Vytvoření mapy
      this.map = L.map(containerId).setView(center, zoom);

      // Tile layer (přes proxy - API klíč je skrytý)
      L.tileLayer(this.config.tileUrl, {
        maxZoom: this.config.maxZoom,
        attribution: this.config.attribution
      }).addTo(this.map);

      logger.log('WGSMap initialized:', containerId);

      // Callback po inicializaci
      if (typeof options.onInit === 'function') {
        options.onInit(this.map);
      }

      return this.map;

    } catch (err) {
      logger.error('Map init error:', err);
      return null;
    }
  },

  /**
   * Přidání markeru
   * @param {string} id - Unikátní ID markeru
   * @param {Array} latLng - [lat, lon]
   * @param {Object} options - {icon, popup, draggable, ...}
   * @returns {Object|null} - Marker instance
   */
  addMarker(id, latLng, options = {}) {
    if (!this.map) {
      console.error('Map not initialized');
      return null;
    }

    try {
      const markerOptions = {};

      // Custom icon
      if (options.icon) {
        if (typeof options.icon === 'string') {
          // HTML icon
          markerOptions.icon = L.divIcon({
            html: options.icon,
            className: options.iconClass || '',
            iconSize: options.iconSize || [50, 30],
            iconAnchor: options.iconAnchor || [25, 15]
          });
        } else {
          markerOptions.icon = options.icon;
        }
      }

      if (options.draggable) {
        markerOptions.draggable = true;
      }

      const marker = L.marker(latLng, markerOptions).addTo(this.map);

      // Popup
      if (options.popup) {
        marker.bindPopup(options.popup);
      }

      // Event listeners
      if (options.onClick) {
        marker.on('click', options.onClick);
      }
      if (options.onDragEnd) {
        marker.on('dragend', options.onDragEnd);
      }

      this.markers[id] = marker;
      return marker;

    } catch (err) {
      console.error('Add marker error:', err);
      return null;
    }
  },

  /**
   * Odstranění markeru
   * @param {string} id - ID markeru
   */
  removeMarker(id) {
    if (this.markers[id]) {
      this.map.removeLayer(this.markers[id]);
      delete this.markers[id];
    }
  },

  /**
   * Geokódování adresy
   * @param {string} address - Adresa k vyhledání
   * @returns {Promise} - Promise s výsledky
   */
  async geocode(address) {
    const logger = window.logger || console;

    // Cache check
    if (this.cache.geocode.has(address)) {
      logger.log('📦 Geocode cache hit:', address);
      return this.cache.geocode.get(address);
    }

    // Cancel previous request
    if (this.controllers.geocode) {
      this.controllers.geocode.abort();
      this.controllers.geocode = null;
    }
    this.controllers.geocode = new AbortController();

    try {
      const response = await fetch(
        `api/geocode_proxy.php?action=search&address=${encodeURIComponent(address)}`,
        { signal: this.controllers.geocode.signal }
      );

      if (response.ok) {
        const data = await response.json();
        this.cache.geocode.set(address, data);
        return data;
      } else {
        throw new Error(`Geocoding failed: ${response.status}`);
      }

    } catch (err) {
      if (err.name !== 'AbortError') {
        logger.error('Geocode error:', err);
        throw err;
      }
    } finally {
      this.controllers.geocode = null;
    }
  },

  /**
   * Autocomplete adres
   * @param {string} text - Text k našeptání
   * @param {Object} options - {type: 'street'|'city'|'postcode', limit: 5}
   * @returns {Promise} - Promise s návrhy
   */
  async autocomplete(text, options = {}) {
    const logger = window.logger || console;

    // Cancel previous request
    if (this.controllers.autocomplete) {
      this.controllers.autocomplete.abort();
      this.controllers.autocomplete = null;
    }
    this.controllers.autocomplete = new AbortController();

    try {
      const type = options.type || 'street';
      const limit = options.limit || 5;
      const country = options.country ? String(options.country).toUpperCase() : '';

      // ŘEŠENÍ: Direct API call z browseru (obchází serverový firewall)
      // API klíč je free tier (3000 req/den), client-side použití je povoleno
      const API_KEY = 'ea590e7e6d3640f9a63ec5a9fb1ff002';

      const params = new URLSearchParams({
        text,
        format: 'geojson',
        limit,
        apiKey: API_KEY,
        lang: 'cs' // FIX: České názvy míst (Praha místo Capital city)
      });

      // Type filtering
      // Pro 'street' NESPECIFIKUJEME type - vrátí přesné adresy včetně house numbers
      if (type === 'city') {
        params.append('type', 'city');
      }
      // Pro street/address necháme bez type filtru - API vrátí nejrelevantnější výsledky včetně čísel popisných

      // Country filtering (podporuje ČR + SK)
      if (country) {
        const countryCodes = country.split(',').map(c => c.trim().toLowerCase());
        params.append('filter', `countrycode:${countryCodes.join(',')}`);
      }

      // Direct call to Geoapify API (browser nemá firewall omezení)
      const response = await fetch(
        `https://api.geoapify.com/v1/geocode/autocomplete?${params.toString()}`,
        { signal: this.controllers.autocomplete.signal }
      );

      if (response.ok) {
        return await response.json();
      } else {
        throw new Error(`Autocomplete failed: ${response.status}`);
      }

    } catch (err) {
      if (err.name !== 'AbortError') {
        logger.error('Autocomplete error:', err);
        throw err;
      }
    } finally {
      this.controllers.autocomplete = null;
    }
  },

  /**
   * Výpočet trasy (pomocí OSRM)
   * @param {Array} start - [lat, lon] start
   * @param {Array} end - [lat, lon] cíl
   * @returns {Promise} - Promise s trasou
   */
  async calculateRoute(start, end) {
    const logger = window.logger || console;

    const cacheKey = `${start.join(',')}-${end.join(',')}`;

    // Cache check
    if (this.cache.route.has(cacheKey)) {
      logger.log('📦 Route cache hit');
      return this.cache.route.get(cacheKey);
    }

    // Cancel previous request
    if (this.controllers.route) {
      this.controllers.route.abort();
      this.controllers.route = null;
    }
    this.controllers.route = new AbortController();

    try {
      const response = await fetch(
        `api/geocode_proxy.php?action=route&start_lon=${start[1]}&start_lat=${start[0]}&end_lon=${end[1]}&end_lat=${end[0]}`,
        { signal: this.controllers.route.signal }
      );

      if (response.ok) {
        const data = await response.json();
        this.cache.route.set(cacheKey, data);
        return data;
      } else {
        throw new Error(`Route calculation failed: ${response.status}`);
      }

    } catch (err) {
      if (err.name !== 'AbortError') {
        logger.error('Route error:', err);
        throw err;
      }
    } finally {
      this.controllers.route = null;
    }
  },

  /**
   * Vykreslení trasy na mapě
   * @param {Array} coordinates - Array of [lat, lon]
   * @param {Object} options - {color, weight, layerId}
   * @returns {Object} - Polyline layer
   */
  drawRoute(coordinates, options = {}) {
    if (!this.map) {
      console.error('Map not initialized');
      return null;
    }

    // Remove old route if layerId specified
    if (options.layerId && this.layers[options.layerId]) {
      this.map.removeLayer(this.layers[options.layerId]);
    }

    const polyline = L.polyline(coordinates, {
      color: options.color || '#006600',
      weight: options.weight || 4,
      opacity: options.opacity || 0.7
    }).addTo(this.map);

    // Fit map to route
    if (options.fitBounds !== false) {
      this.map.fitBounds(polyline.getBounds());
    }

    if (options.layerId) {
      this.layers[options.layerId] = polyline;
    }

    return polyline;
  },

  /**
   * Odstranění layer
   * @param {string} layerId - ID layeru
   */
  removeLayer(layerId) {
    if (this.layers[layerId]) {
      this.map.removeLayer(this.layers[layerId]);
      delete this.layers[layerId];
    }
  },

  /**
   * Zaměření mapy na souřadnice
   * @param {Array} latLng - [lat, lon]
   * @param {number} zoom - Zoom level (volitelný)
   */
  flyTo(latLng, zoom) {
    if (!this.map) return;
    this.map.flyTo(latLng, zoom || this.map.getZoom());
  },

  /**
   * Vyčištění všech markerů a layerů
   */
  clear() {
    // Remove all markers
    Object.keys(this.markers).forEach(id => {
      this.removeMarker(id);
    });

    // Remove all layers
    Object.keys(this.layers).forEach(id => {
      this.removeLayer(id);
    });

    // Clear cache
    this.cache.geocode.clear();
    this.cache.route.clear();
  },

  /**
   * Destroy map instance
   */
  destroy() {
    this.clear();

    // Cancel all requests
    Object.keys(this.controllers).forEach(key => {
      if (this.controllers[key]) {
        this.controllers[key].abort();
        this.controllers[key] = null;
      }
    });

    if (this.map) {
      this.map.remove();
      this.map = null;
    }
  },

  /**
   * Helper: Debounce funkce
   * @param {Function} func - Funkce k debounce
   * @param {number} wait - Čekací doba v ms
   * @returns {Function} - Debounced funkce
   */
  debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
      const later = () => {
        clearTimeout(timeout);
        func(...args);
      };
      clearTimeout(timeout);
      timeout = setTimeout(later, wait);
    };
  }
};

// Export pro použití jako modul
if (typeof module !== 'undefined' && module.exports) {
  module.exports = WGSMap;
}
