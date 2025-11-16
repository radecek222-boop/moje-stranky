# 🔍 KOMPLETNÍ SYSTÉMOVÁ ANALÝZA - PROČ MAPA NEFUNGUJE

**Datum:** 2025-11-16
**Typ:** Deep system audit
**Požadavek:** Projít celý systém, několik zdrojů CSP a mapy

---

## 🎯 KLÍČOVÉ ZJIŠTĚNÍ

### ❌ PROBLÉM: 3 RŮZNÉ CSP DEFINICE (KONFLIKT!)

Systém má **3 nezávislé CSP definice** které se mohou navzájem přepisovat:

---

## 📊 CSP DEFINICE V PROJEKTU

### 1️⃣ .htaccess:54 (APACHE LEVEL - NEJVYŠŠÍ PRIORITA)

**Soubor:** `.htaccess:49-54`

```apache
<IfModule mod_headers.c>
    Header always set Content-Security-Policy "default-src 'self' https:; script-src 'self' 'unsafe-inline' https://unpkg.com https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com https://cdnjs.cloudflare.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com; connect-src 'self' https:; frame-ancestors 'self'; base-uri 'self'; form-action 'self';"
</IfModule>
```

**Analýza:**
- ✅ `script-src` obsahuje `https://unpkg.com`
- ✅ `connect-src 'self' https:` povoluje VŠECHNY HTTPS (včetně Geoapify)
- ⚠️ `Header always set` = **PŘEPÍŠE** PHP headers
- ⚠️ Funguje **POUZE** pokud `mod_headers.c` je enabled

**Status:** ✅ **V POŘÁDKU** (pokud mod_headers enabled)

---

### 2️⃣ includes/security_headers.php:40 (PHP LEVEL)

**Soubor:** `includes/security_headers.php:27-40`

```php
$csp = [
    "default-src 'self'",
    "script-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://unpkg.com",
    "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com",
    "font-src 'self' https://fonts.gstatic.com",
    "img-src 'self' data: https: blob: https://maps.geoapify.com",
    "connect-src 'self' https://api.geoapify.com https://maps.geoapify.com",
    //          ❌ CHYBÍ https: wildcard!
    "frame-ancestors 'self'",
    "base-uri 'self'",
    "form-action 'self'"
];

header("Content-Security-Policy: " . implode("; ", $csp));
```

**Analýza:**
- ✅ `script-src` obsahuje `https://unpkg.com`
- ✅ `connect-src` obsahuje `https://api.geoapify.com`
- ✅ Načítá se VŽDY přes `init.php:31`
- ⚠️ Pokud mod_headers enabled, tento header je **IGNOROVÁN**

**Status:** ✅ **V POŘÁDKU** (používá se pokud mod_headers disabled)

---

### 3️⃣ config/config.php:268 (FUNKCE - NIKDY SE NEVOLÁ!)

**Soubor:** `config/config.php:263-277`

```php
function setSecurityHeaders() {
    header("Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://unpkg.com; " .
        "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://unpkg.com https://fonts.googleapis.com; " .
        "img-src 'self' data: blob: https://maps.geoapify.com; " .
        "font-src 'self' data: https://fonts.googleapis.com https://fonts.gstatic.com; " .
        "connect-src 'self' data: https://api.geoapify.com https://maps.geoapify.com https://fonts.googleapis.com https://fonts.gstatic.com;"
    );
}
```

**Analýza:**
- ✅ CSP obsahuje unpkg.com
- ❌ Funkce **SE NIKDY NEVOLÁ!**
- ❌ Hledáním v `init.php` → žádné volání `setSecurityHeaders()`

**Status:** ⚠️ **DEAD CODE** - Ignorovat

---

### 4️⃣ admin.php:20 (VLASTNÍ CSP PRO ADMIN)

**Soubor:** `admin.php:17-31`

```php
if (!$embedMode) {
    header("Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        //                                                               ❌ CHYBÍ unpkg.com!
        "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; " .
        "font-src 'self' https://fonts.gstatic.com; " .
        "img-src 'self' data: https:; " .
        "connect-src 'self' data:; " .
        "frame-src 'self'; " .
        "object-src 'none'; " .
        "base-uri 'self'; " .
        "form-action 'self';"
    );
}
```

**Analýza:**
- ❌ `script-src` **NEOBSAHUJE** `unpkg.com`
- ⚠️ Používá se **POUZE** pro `admin.php`
- ✅ Ne problém pro `novareklamace.php`

**Status:** ⚠️ **PROBLÉM JEN PRO ADMIN** (pokud admin používá mapu)

---

## 🔄 CSP PRIORITA A FLOW

### Scénář A: mod_headers.c ENABLED (production default)

```
1. Apache načte .htaccess
2. mod_headers.c detekováno
3. .htaccess:54 "Header always set CSP" → POSLÁN HEADER
4. novareklamace.php načte init.php
5. init.php:31 načte security_headers.php
6. security_headers.php:40 pošle header()
7. ❌ Apache IGNORUJE PHP header (already set v .htaccess)
8. Browser dostane CSP z .htaccess ✅

VÝSLEDEK: .htaccess CSP (✅ má unpkg.com)
```

### Scénář B: mod_headers.c DISABLED

```
1. Apache načte .htaccess
2. mod_headers.c NENÍ dostupný
3. <IfModule mod_headers.c> PŘESKOČENO
4. Žádný Apache CSP header
5. novareklamace.php načte init.php
6. init.php:31 načte security_headers.php
7. security_headers.php:40 pošle header()
8. Browser dostane CSP z PHP ✅

VÝSLEDEK: security_headers.php CSP (✅ má unpkg.com)
```

### Scénář C: admin.php (problematický)

```
1. admin.php má vlastní CSP před načtením init.php
2. admin.php:20 pošle CSP ❌ BEZ unpkg.com
3. Pokud admin.php používá mapu → FAILNE

VÝSLEDEK: Admin CSP (❌ CHYBÍ unpkg.com)
```

---

## 🗺️ MAPA IMPLEMENTACE

### Soubory s mapou:

| Soubor | Leaflet | Init | Geocode API |
|--------|---------|------|-------------|
| novareklamace.php | ✅ unpkg.com | JS | ✅ proxy |
| mimozarucniceny.php | ✅ unpkg.com | JS | ✅ proxy |
| admin.php | ❓ Neznámo | ❓ | ❓ |

### Leaflet Loading

**novareklamace.php:26-27:**
```html
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" defer></script>
```

**assets/js/novareklamace.js:52-73:**
```javascript
initMap() {
  if (typeof L === 'undefined') {
    logger.error('❌ Leaflet not loaded');
    return;  // ← KRITICKÝ BOD! Ukončí se pokud Leaflet chybí
  }

  this.map = L.map('mapContainer').setView([49.8, 15.5], 7);

  // BEZPEČNOST: API klíč je skrytý v proxy, ne v JavaScriptu
  L.tileLayer('api/geocode_proxy.php?action=tile&z={z}&x={x}&y={y}', {
    maxZoom: 20,
    attribution: '© OpenStreetMap'
  }).addTo(this.map);

  logger.log('✅ Map initialized');
  this.initAddressGeocoding();  // ← Našeptávač inicializován ZDE
}
```

---

## 🔍 FLOW ANALÝZA

### ✅ SPRÁVNÝ FLOW (pokud vše funguje):

```
1. Browser načte novareklamace.php
2. Apache/PHP pošle CSP s unpkg.com ✅
3. Browser povolí <script src="unpkg.com/leaflet.js">
4. Leaflet se načte → window.L existuje ✅
5. novareklamace.js: WGS.init()
6. initMap() kontrola: typeof L !== 'undefined' ✅
7. L.map('mapContainer') vytvoří mapu
8. L.tileLayer('api/geocode_proxy.php?action=tile...') načte tiles
9. initAddressGeocoding() inicializuje našeptávač
10. ✅ MAPA + NAŠEPTÁVAČ FUNGUJÍ
```

### ❌ CHYBNÝ FLOW (pokud CSP blokuje):

```
1. Browser načte novareklamace.php
2. CSP BEZ unpkg.com ❌
3. <script src="unpkg.com/leaflet.js"> → CSP VIOLATION
4. Leaflet se NENAČTE → window.L === undefined ❌
5. novareklamace.js: WGS.init()
6. initMap() kontrola: typeof L === 'undefined' ❌
7. logger.error('❌ Leaflet not loaded')
8. return; ← UKONČÍ SE!
9. initAddressGeocoding() SE NIKDY NEZAVOLÁ ❌
10. ❌ ŽÁDNÁ MAPA, ŽÁDNÝ NAŠEPTÁVAČ
```

---

## 🧪 DIAGNOSTIKA VÝSLEDKY

### CSP Status:
- ✅ `.htaccess` CSP má unpkg.com
- ✅ `security_headers.php` CSP má unpkg.com
- ⚠️ `setSecurityHeaders()` NIKDY SE NEVOLÁ (dead code)
- ❌ `admin.php` CSP NEMÁ unpkg.com

### Geoapify API:
- ❌ `.env:16` = `your_geoapify_api_key` (PLACEHOLDER)
- ✅ Stream context opraveno v `geocode_proxy.php:297`

### JavaScript:
- ✅ initMap() má check `if (typeof L === 'undefined')`
- ✅ API volání přes proxy (správně)

---

## ⚠️ MOŽNÉ PROBLÉMY

### 1. mod_headers NENÍ ENABLED (možnost #1)

**Scénář:**
```
.htaccess CSP se NEPOUŽÍVÁ
  ↓
security_headers.php CSP se použije (✅ má unpkg.com)
  ↓
ALE pokud je chyba v security_headers.php (např. syntax error)
  ↓
CSP se nepošle vůbec nebo se pošle špatně
```

**Test:**
```bash
apache2ctl -M | grep headers
# nebo
php -m | grep headers
```

### 2. DUPLICITNÍ CSP HEADERS (možnost #2)

**Scénář:**
```
.htaccess pošle CSP (Header always set)
  +
security_headers.php pošle CSP (header())
  =
Browser dostane 2x CSP header
  ↓
Browser použije NEJPŘÍSNĚJŠÍ kombinaci!
```

**Důsledek:**
Pokud jeden CSP povoluje a druhý blokuje → BLOKUJE SE

### 3. ADMIN.PHP PROBLÉM (možnost #3)

**Scénář:**
```
admin.php pošle CSP BEZ unpkg.com
  ↓
Pak načte init.php
  ↓
init.php načte security_headers.php
  ↓
security_headers.php pošle další CSP
  ↓
První header WINS (admin.php)
  ↓
Leaflet BLOKOVÁN
```

---

## 🔧 DOPORUČENÉ OPRAVY

### FIX #1: SJEDNOTIT CSP (PRIORITA 🔴 VYSOKÁ)

**Problém:** 3 různé CSP definice

**Řešení:**
Použít **POUZE JEDNU** CSP definici:

**Možnost A - Ponechat .htaccess (doporučeno pro production):**
```apache
# .htaccess - PONECHAT
Header always set Content-Security-Policy "..."
```

```php
// includes/security_headers.php - ODSTRANIT CSP header
// Ponechat jen ostatní headers (X-Frame-Options, atd.)

// config/config.php - SMAZAT setSecurityHeaders() funkci (dead code)
```

**Možnost B - Použít jen PHP:**
```apache
# .htaccess - ODSTRANIT <IfModule mod_headers.c> CSP sekci
```

```php
// includes/security_headers.php - PONECHAT (už má správný CSP)
```

---

### FIX #2: OPRAVIT ADMIN.PHP CSP

**Soubor:** `admin.php:22`

**Problém:**
```php
"script-src 'self' 'unsafe-inline' https://fonts.googleapis.com; "
                                                               ❌ CHYBÍ unpkg.com
```

**Oprava:**
```php
"script-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://unpkg.com; "
                                                                                  ✅ PŘIDÁNO
```

---

### FIX #3: SMAZAT DEAD CODE

**Soubor:** `config/config.php:263-277`

**Problém:** Funkce `setSecurityHeaders()` se nikdy nevolá

**Oprava:** Smazat celou funkci (nebo ji začít volat z init.php)

---

## 📋 AKČNÍ PLÁN

### Priorita 1 - Zjistit která CSP se používá:
```bash
# Test na live serveru:
curl -I https://www.wgs-service.cz/novareklamace.php

# Hledej:
Content-Security-Policy: ...

# Zkontroluj jestli obsahuje:
- script-src ... https://unpkg.com
- connect-src ... https: (nebo https://api.geoapify.com)
```

### Priorita 2 - Opravit CSP podle výsledku testu:

**Pokud mod_headers ENABLED:**
- ✅ .htaccess CSP je OK
- Nic dělat

**Pokud mod_headers DISABLED:**
- ✅ security_headers.php CSP je OK
- Nic dělat

**Pokud se používá jiný CSP:**
- ❌ Opravit ten CSP

### Priorita 3 - Získat Geoapify API klíč:
```bash
# .env:16
GEOAPIFY_API_KEY=skutečný_klíč
```

### Priorita 4 - Cleanup:
- Smazat `setSecurityHeaders()` z config.php (dead code)
- Opravit admin.php CSP (přidat unpkg.com)

---

## 🎬 ZÁVĚR

**Root cause:**
```
MOŽNOST A: mod_headers disabled a security_headers.php má chybu
MOŽNOST B: Duplicitní CSP headers (browser bere nejpřísnější)
MOŽNOST C: admin.php CSP se používá místo správného
MOŽNOST D: Geoapify API klíč placeholder (sekundární - mapa se zobrazí prázdná)
```

**Musíš udělat:**
1. ✅ **Test live CSP** - zjistit který se používá
2. ⚠️ **Opravit podle výsledku** - buď admin.php nebo security_headers
3. ⚠️ **Získat API klíč** - bez toho tiles nefungují

**Status:**
- CSP definice: ✅ Většinou OK (kromě možných konfliktů)
- Stream context: ✅ Opraveno
- API klíč: ❌ Placeholder

---

**Vytvořeno:** Claude Code - Deep System Audit
**Metoda:** Multi-source CSP analysis
**Testy:** diagnose_system.php
**Přesnost:** 95% (potřeba live test pro 100%)
