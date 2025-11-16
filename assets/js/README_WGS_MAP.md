# WGS Map Module

Společný JavaScript modul pro práci s mapou a geokódováním napříč všemi stránkami WGS Service.

## 📦 Obsah

- **Soubor:** `assets/js/wgs-map.js`
- **Závislosti:** Leaflet.js 1.9.4+, logger.js
- **Použití:** novareklamace.php, mimozarucniceny.php

---

## 🚀 Použití

### 1. Načtení modulu

```html
<!-- Načíst PŘED vlastním JS souborem -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
<script src="assets/js/logger.js" defer></script>
<script src="assets/js/wgs-map.js" defer></script>
<script src="assets/js/your-app.js" defer></script>
```

### 2. Inicializace mapy

```javascript
// Základní použití
const map = WGSMap.init('mapContainer');

// S vlastním nastavením
const map = WGSMap.init('mapContainer', {
  center: [50.08, 14.59],
  zoom: 12,
  onInit: (mapInstance) => {
    console.log('Mapa inicializována!', mapInstance);
  }
});
```

### 3. Přidání markeru

```javascript
// Jednoduchý marker
WGSMap.addMarker('company', [50.08, 14.59]);

// Marker s ikonou a popupem
WGSMap.addMarker('customer', [49.8, 15.5], {
  icon: '<div style="background:#006600;color:white;padding:5px;">WGS</div>',
  iconClass: 'custom-marker',
  iconSize: [50, 30],
  popup: '<b>Náš sklad</b><br>Do Dubče 364',
  draggable: true,
  onDragEnd: (e) => {
    console.log('Nová pozice:', e.target.getLatLng());
  }
});
```

### 4. Geokódování

```javascript
// Převod adresy na souřadnice
const data = await WGSMap.geocode('Václavské náměstí, Praha');

if (data.features && data.features.length > 0) {
  const coords = data.features[0].geometry.coordinates;
  const [lon, lat] = coords; // GeoJSON je [lon, lat]!

  WGSMap.addMarker('result', [lat, lon]);
  WGSMap.flyTo([lat, lon], 15);
}
```

### 5. Autocomplete (našeptávač)

```javascript
// Našeptávač ulic
const results = await WGSMap.autocomplete('Václavské', {
  type: 'street',
  limit: 5
});

// Našeptávač měst
const cities = await WGSMap.autocomplete('Pra', {
  type: 'city',
  limit: 10
});

// Zobrazení v dropdownu
results.features.forEach(feature => {
  const address = feature.properties.formatted;
  console.log(address);
});
```

### 6. Výpočet trasy

```javascript
// Výpočet trasy mezi dvěma body
const start = [50.08, 14.59]; // [lat, lon]
const end = [49.8, 15.5];

const routeData = await WGSMap.calculateRoute(start, end);

if (routeData.routes && routeData.routes.length > 0) {
  const route = routeData.routes[0];
  const coords = route.geometry.coordinates.map(c => [c[1], c[0]]); // Převod na [lat,lon]

  // Vykreslení trasy
  WGSMap.drawRoute(coords, {
    color: '#006600',
    weight: 4,
    layerId: 'main-route',
    fitBounds: true
  });

  // Informace o trase
  const distance = (route.distance / 1000).toFixed(1); // km
  const duration = Math.ceil(route.duration / 60); // min
  console.log(`Trasa: ${distance} km, ${duration} min`);
}
```

### 7. Odstranění a vyčištění

```javascript
// Odstranit konkrétní marker
WGSMap.removeMarker('customer');

// Odstranít layer (např. trasu)
WGSMap.removeLayer('main-route');

// Vyčistit vše (všechny markery, layery, cache)
WGSMap.clear();

// Zničit celou mapu
WGSMap.destroy();
```

---

## 🔧 API Reference

### Metody

| Metoda | Parametry | Vrací | Popis |
|--------|-----------|-------|-------|
| `init(containerId, options)` | containerId: string, options: Object | L.Map \| null | Inicializuje mapu |
| `addMarker(id, latLng, options)` | id: string, latLng: [lat,lon], options: Object | L.Marker \| null | Přidá marker |
| `removeMarker(id)` | id: string | void | Odstraní marker |
| `geocode(address)` | address: string | Promise<Object> | Geokódování adresy |
| `autocomplete(text, options)` | text: string, options: Object | Promise<Object> | Našeptávač adres |
| `calculateRoute(start, end)` | start: [lat,lon], end: [lat,lon] | Promise<Object> | Výpočet trasy |
| `drawRoute(coords, options)` | coords: [[lat,lon]...], options: Object | L.Polyline | Vykreslí trasu |
| `removeLayer(layerId)` | layerId: string | void | Odstraní layer |
| `flyTo(latLng, zoom)` | latLng: [lat,lon], zoom: number | void | Animovaný přesun |
| `clear()` | - | void | Vyčistí vše |
| `destroy()` | - | void | Zničí mapu |
| `debounce(func, wait)` | func: Function, wait: number | Function | Helper pro debounce |

### Konfigurace

```javascript
WGSMap.config = {
  defaultCenter: [49.8, 15.5],        // Výchozí střed mapy
  defaultZoom: 7,                      // Výchozí zoom
  maxZoom: 20,                         // Maximální zoom
  tileUrl: 'api/geocode_proxy.php...', // URL pro tiles
  attribution: '© OpenStreetMap',      // Attribution
  debounceAutocomplete: 300,           // Debounce autocomplete (ms)
  debounceRoute: 500,                  // Debounce route (ms)
  minCharsAutocomplete: 2              // Min znaků pro autocomplete
};
```

### Properties

```javascript
WGSMap.map         // L.Map instance
WGSMap.markers     // Object s markery {id: L.Marker}
WGSMap.layers      // Object s layery {id: L.Layer}
WGSMap.controllers // AbortControllers pro request cancellation
WGSMap.cache       // Map cache pro geocode a route
```

---

## 🎯 Výhody

### ✅ Centralizace
- Jeden soubor pro všechny mapy
- Snadná údržba
- Konzistentní API

### ✅ Performance
- Request cancellation (AbortController)
- Cache pro geocoding a routing
- Debounce pro autocomplete

### ✅ Bezpečnost
- API klíč je skrytý v proxy (`api/geocode_proxy.php`)
- Nikdy není v klientském JavaScriptu

### ✅ Modularita
- Lze použít na jakékoliv stránce
- Nezávislé na specifické aplikační logice
- Čistý API design

---

## 📝 Příklad: Kompletní implementace

```javascript
// 1. Inicializace mapy
const map = WGSMap.init('mapContainer', {
  center: [50.08, 14.59],
  zoom: 10
});

// 2. Marker skladu
WGSMap.addMarker('warehouse', [50.08, 14.59], {
  icon: '<div class="warehouse-icon">WGS</div>',
  popup: 'Náš sklad'
});

// 3. Autocomplete input
const input = document.getElementById('address');
const dropdown = document.getElementById('suggestions');

const debouncedAutocomplete = WGSMap.debounce(async (text) => {
  if (text.length < 2) {
    dropdown.innerHTML = '';
    return;
  }

  const results = await WGSMap.autocomplete(text, {type: 'street'});

  dropdown.innerHTML = '';
  results.features.forEach(feature => {
    const div = document.createElement('div');
    div.textContent = feature.properties.formatted;
    div.onclick = () => selectAddress(feature);
    dropdown.appendChild(div);
  });
}, 300);

input.addEventListener('input', (e) => {
  debouncedAutocomplete(e.target.value);
});

// 4. Výběr adresy
async function selectAddress(feature) {
  const [lon, lat] = feature.geometry.coordinates;

  // Přidat marker
  WGSMap.addMarker('customer', [lat, lon], {
    popup: feature.properties.formatted
  });

  // Vypočítat trasu
  const route = await WGSMap.calculateRoute([50.08, 14.59], [lat, lon]);

  if (route.routes && route.routes.length > 0) {
    const coords = route.routes[0].geometry.coordinates.map(c => [c[1], c[0]]);
    WGSMap.drawRoute(coords, {layerId: 'route'});

    const distance = (route.routes[0].distance / 1000).toFixed(1);
    console.log(`Vzdálenost: ${distance} km`);
  }
}
```

---

## 🔒 Bezpečnost

### Proxy Pattern
Všechny API volání jdou přes `api/geocode_proxy.php`:

```javascript
// ❌ ŠPATNĚ - API klíč v JavaScriptu
L.tileLayer('https://maps.geoapify.com/...?apiKey=SECRET_KEY')

// ✅ SPRÁVNĚ - API klíč skrytý v proxy
L.tileLayer('api/geocode_proxy.php?action=tile&z={z}&x={x}&y={y}')
```

Proxy `geocode_proxy.php`:
- Načte API klíč z `config.php` (server-side)
- Přepošle request na Geoapify
- Vrátí response klientovi
- Klíč nikdy není v klientském kódu

---

## 🐛 Debugging

```javascript
// Zapnout detailní logging
window.logger.setLevel('debug');

// Zkontrolovat cache
console.log('Geocode cache:', WGSMap.cache.geocode);
console.log('Route cache:', WGSMap.cache.route);

// Zkontrolovat aktivní markery
console.log('Markers:', Object.keys(WGSMap.markers));

// Zkontrolovat layery
console.log('Layers:', Object.keys(WGSMap.layers));
```

---

## 📚 Odkazy

- **Leaflet.js dokumentace:** https://leafletjs.com/
- **Geoapify API:** https://www.geoapify.com/
- **OSRM routing:** https://project-osrm.org/

---

## ✨ Changelog

### v1.0.0 (2025-11-16)
- ✅ Initial release
- ✅ Map initialization
- ✅ Markers management
- ✅ Geocoding & Autocomplete
- ✅ Route calculation & rendering
- ✅ Cache & request cancellation
- ✅ Debounce helper

---

**Autor:** Claude Code
**Datum:** 2025-11-16
**Použití:** novareklamace.php, mimozarucniceny.php
