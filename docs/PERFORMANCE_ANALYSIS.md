# Performance Analysis - WGS Service

**Step 166** - Analýza bundle dependencies

---

## 1. JavaScript Bundle Sizes

### Před minifikací (development)

| Soubor | Velikost | Funkce | Priorita |
|--------|----------|--------|----------|
| `seznam.js` | 168 KB | 74 | HIGH - kritický |
| `protokol.js` | 84 KB | ~35 | MEDIUM |
| `admin.js` | 70 KB | ~30 | LOW (admin only) |
| `novareklamace.js` | 51 KB | ~25 | MEDIUM |
| `psa-kalkulator.js` | 46 KB | ~20 | LOW |
| `cenik-calculator.js` | 44 KB | ~20 | LOW |
| `translations.js` | 39 KB | data | LOW |

### Po minifikaci (production)

| Soubor | Minified | Komprese | Potenciál |
|--------|----------|----------|-----------|
| `seznam.min.js` | 105 KB | ~35 KB gzip | Split modules |
| `protokol.min.js` | 44 KB | ~15 KB gzip | OK |
| `admin.min.js` | 44 KB | ~15 KB gzip | OK (admin) |
| `novareklamace.min.js` | 25 KB | ~8 KB gzip | OK |

---

## 2. Analýza seznam.js (Hlavní problém)

### Identifikované moduly v seznam.js:

1. **Core** (~15 KB)
   - `loadAll()`, `renderOrders()`
   - `showDetail()`, `closeDetail()`
   - `saveData()`

2. **Search/Filter** (~10 KB)
   - `initSearch()`, `clearSearch()`
   - `matchesSearch()`, `highlightText()`
   - `initFilters()`, `updateCounts()`

3. **Calendar** (~25 KB)
   - `showCalendar()`, `renderCalendar()`
   - `previousMonth()`, `nextMonth()`
   - `showDayBookingsWithDistances()`

4. **Distance Calculation** (~15 KB)
   - `getDistance()`, `getDistancesBatch()`
   - Map integration

5. **Notes** (~10 KB)
   - `filterUnreadNotes()`
   - Notes rendering

6. **PDF/History** (~10 KB)
   - `showHistoryPDF()`
   - PDF viewer integration

7. **UI Helpers** (~10 KB)
   - `showLoading()`, `hideLoading()`
   - `showToast()`
   - Auto-refresh

### Doporučení pro split:

```
seznam.js (168 KB)
├── seznam-core.js (~30 KB) - Lazy: NO
├── seznam-calendar.js (~25 KB) - Lazy: YES (click to load)
├── seznam-distance.js (~15 KB) - Lazy: YES (on demand)
├── seznam-search.js (~10 KB) - Lazy: NO
└── seznam-pdf.js (~10 KB) - Lazy: YES (on demand)
```

---

## 3. CSS Analysis

### Hlavní CSS soubory:

| Soubor | Velikost | Účel |
|--------|----------|------|
| `styles.css` | ~80 KB | Hlavní styly |
| `styles.min.css` | ~50 KB | Minifikované |
| `admin.min.css` | ~15 KB | Admin panel |
| `cenik.min.css` | ~10 KB | Ceník |

### Critical CSS:

Pro First Contentful Paint by mělo být inlined:
- Reset styles
- Layout (header, nav)
- Typography base
- Loading states

---

## 4. Loading Strategy

### Aktuální stav:
```html
<script src="assets/js/seznam.min.js" defer></script>
```

### Doporučený přístup:

```html
<!-- Critical (inline nebo preload) -->
<link rel="preload" href="assets/js/utils.min.js" as="script">

<!-- Core module -->
<script src="assets/js/seznam-core.min.js" defer></script>

<!-- Lazy modules -->
<script>
  // Load calendar only when needed
  function loadCalendar() {
    if (!window.calendarLoaded) {
      const script = document.createElement('script');
      script.src = 'assets/js/seznam-calendar.min.js';
      document.head.appendChild(script);
      window.calendarLoaded = true;
    }
  }
</script>
```

---

## 5. Prioritizace optimalizací

### HIGH Priority (Step 167-168):

1. **Split seznam.js** - Největší impact
   - Oddělit calendar modul (25 KB)
   - Oddělit distance modul (15 KB)
   - Lazy load on demand

2. **Preload critical resources**
   - utils.js, logger.js

### MEDIUM Priority (Step 169):

3. **CSS optimization**
   - Critical CSS inline
   - Non-critical CSS lazy load

4. **Image optimization**
   - Lazy loading pro obrázky
   - WebP format

### LOW Priority (Step 170):

5. **Performance monitoring**
   - Core Web Vitals tracking
   - Bundle size CI check

---

## 6. Očekávaný výsledek

### Před optimalizací:
- Initial JS: ~150 KB (minified)
- Time to Interactive: ~2.5s (3G)

### Po optimalizaci:
- Initial JS: ~80 KB (minified)
- Lazy loaded: ~70 KB (on demand)
- Time to Interactive: ~1.5s (3G)

### Metriky k sledování:
- First Contentful Paint (FCP)
- Largest Contentful Paint (LCP)
- Time to Interactive (TTI)
- Total Blocking Time (TBT)

---

## 7. Implementační plán

### Step 167: Extract shared modules
- [ ] Identifikovat shared kód
- [ ] Vytvořit seznam-calendar.js
- [ ] Vytvořit seznam-distance.js
- [ ] Aktualizovat imports

### Step 168: Implement lazy loading
- [ ] Dynamic import pro calendar
- [ ] Dynamic import pro distance
- [ ] Testovat UX

### Step 169: CSS optimization
- [ ] Identifikovat critical CSS
- [ ] Inline critical styles
- [ ] Async load non-critical

### Step 170: Performance monitoring
- [ ] Přidat Web Vitals tracking
- [ ] CI check pro bundle size
- [ ] Dokumentace baseline

---

**Poslední aktualizace:** 2025-12-02
