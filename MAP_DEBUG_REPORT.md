# 🗺️ DIAGNOSTICKÁ ZPRÁVA - MAPA NEFUNGUJE

**Datum:** 2025-11-16
**Stránka:** novareklamace.php
**Problém:** Mapa se nezobrazuje (prázdná oblast)

---

## 🎯 HLAVNÍ PŘÍČINY (2 kritické chyby)

### ❌ CHYBA #1: Nesoulad názvů proměnných

**Problém:**
- **.env soubor** obsahuje: `GEOAPIFY_KEY=your_geoapify_api_key`
- **config.php** hledá: `getEnvValue('GEOAPIFY_API_KEY', ...)`
- Názvy se **neshodují** (`GEOAPIFY_KEY` ≠ `GEOAPIFY_API_KEY`)

**Důsledek:**
- `getEnvValue()` nenajde hodnotu v .env
- Použije se fallback: `'change-this-in-production'`
- API requesty failují s neplatným klíčem

**Umístění:**
- `.env:15` - `GEOAPIFY_KEY=...`
- `config/config.php:327` - `getEnvValue('GEOAPIFY_API_KEY', ...)`

---

### ❌ CHYBA #2: Placeholder hodnota API klíče

**Problém:**
- I kdybychom opravili název, hodnota je: `your_geoapify_api_key`
- To je placeholder, ne skutečný API klíč
- Geoapify API vrací `401 Unauthorized`

**Důsledek:**
- Tile requesty failují
- `geocode_proxy.php` zachytí exception
- Vrátí JSON error místo PNG obrázku
- Prohlížeč nemůže zobrazit JSON jako `<img>`

---

## 🔬 TECHNICKÁ ANALÝZA

### 1. Leaflet inicializace
✅ **FUNGUJE** - Mapa je správně inicializována:
```html
<div id="mapContainer" class="leaflet-container leaflet-touch ...">
  <div class="leaflet-pane leaflet-map-pane">
    <img src="api/geocode_proxy.php?action=tile&z=7&x=70&y=44" />
```

### 2. Tile requesty
✅ **PROBÍHAJÍ** - 15 tile elementů vytvořeno
❌ **SELHÁVAJÍ** - Response: `Stav: —`, žádná data

### 3. API Key flow
```
.env: GEOAPIFY_KEY=your_geoapify_api_key
  ↓
env_loader.php: Načte jako $_ENV['GEOAPIFY_KEY']
  ↓
config.php: getEnvValue('GEOAPIFY_API_KEY') → nenajde!
  ↓
Fallback: 'change-this-in-production'
  ↓
geocode_proxy.php: používá neplatný klíč
  ↓
Geoapify API: 401 Unauthorized
  ↓
file_get_contents() vrací false
  ↓
Exception → JSON error response
  ↓
Prohlížeč: ❌ Nemůže zobrazit JSON jako PNG
```

### 4. Test tile response
```bash
$ php test_tile_simple.php

=== TILE TEST ===
1. API KEY:
   Value: not-set...
   Is placeholder: YES ❌

3. RESULT:
   Status: ❌ FAILED
```

---

## ✅ ŘEŠENÍ

### Krok 1: Opravit název proměnné v .env

**PŘED:**
```bash
GEOAPIFY_KEY=your_geoapify_api_key
```

**PO:**
```bash
GEOAPIFY_API_KEY=your_geoapify_api_key
```

### Krok 2: Získat platný API klíč

1. Jděte na: https://www.geoapify.com/
2. Klikněte **"Get Started for Free"**
3. Zaregistrujte se (email)
4. Vytvořte projekt
5. Zkopírujte API klíč (např. `abc123def456...`)

### Krok 3: Nastavit platný klíč

Upravte `.env`:
```bash
GEOAPIFY_API_KEY=váš_skutečný_api_klíč_zde
```

### Krok 4: Ověření

1. Otevřete: `check_geoapify_config.php`
2. Zkontrolujte: ✅ zelený status
3. Otevřete: `novareklamace.php`
4. Mapa by se měla zobrazit

---

## 📊 DALŠÍ ZJIŠTĚNÍ

### CSS - ✅ V POŘÁDKU
- `#mapContainer` má správné styly
- Žádné `height: 0` nebo `display: none`
- Z-index konflikty nebyly nalezeny

### JavaScript - ✅ V POŘÁDKU
- Leaflet správně inicializován
- Tile requesty generovány korektně
- Transform3d souřadnice správné

### Overlapping - ✅ V POŘÁDKU
- Žádné překrývající se elementy
- Grid layout funguje správně

### Content-Type - ⚠️ PROBLÉM PŘI ERROR
- Úspěšné tiles: `Content-Type: image/png` ✅
- Chybné tiles: `Content-Type: application/json` ❌
- Prohlížeč nemůže zobrazit JSON jako obrázek

---

## 🎬 ZÁVĚR

**Root cause:** Kombinace dvou chyb:
1. Název proměnné: `GEOAPIFY_KEY` → `GEOAPIFY_API_KEY`
2. Placeholder hodnota → Skutečný API klíč

**Priorita:** 🔴 KRITICKÁ
**Impact:** Mapa kompletně nefunkční
**Difficulty:** 🟢 Snadné (změna 1 řádku + registrace API klíče)
**ETA:** 5-10 minut

---

## 📚 DOKUMENTACE

Pro kompletní návod viz:
- `GEOAPIFY_SETUP.md` - Detailní setup guide
- `check_geoapify_config.php` - Interaktivní diagnostika

---

**Vytvořeno:** Claude Code Forensic Analysis
**Metoda:** Systematická analýza bez hádání
**Ověřeno:** test_tile_simple.php
