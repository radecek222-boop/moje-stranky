# 🗺️ Geoapify API Setup - Návod

## Problém

Mapa a našeptávač adres nefungují, protože **chybí platný Geoapify API klíč**.

### Jak to poznáte:
- V Network konzoli vidíte `Content-Type: application/json` místo `image/png` pro tile requesty
- Mapa se nenačítá, našeptávač adres nefunguje
- V response vidíte JSON error: `{"error": "GEOAPIFY_KEY není nastaveno v konfiguraci"}`

---

## ✅ Řešení

### Krok 1: Získat Geoapify API klíč (ZDARMA)

1. Jděte na https://www.geoapify.com/
2. Klikněte na **"Get Started for Free"** nebo **"Sign Up"**
3. Zaregistrujte se pomocí emailu
4. Ověřte email a přihlaste se
5. V dashboardu vytvořte nový projekt:
   - Project Name: **WGS Service**
   - Description: **White Glove Service Maps**
6. Zkopírujte **API Key** (vypadá jako `a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6`)

**Free tier limity:**
- ✅ 3,000 requestů denně (ZDARMA)
- ✅ Geocoding, Autocomplete, Map tiles
- ✅ Routing (alternativně používáme OSRM - také zdarma)

---

### Krok 2: Nastavit API klíč v .env

Otevřete soubor `.env` v root složce projektu a nastavte:

```bash
# API Keys
GEOAPIFY_KEY=VÁŠ_SKUTEČNÝ_API_KLÍČ_ZDE
```

**Příklad:**
```bash
# PŘED (nefunguje):
GEOAPIFY_KEY=your_geoapify_api_key

# PO (funguje):
GEOAPIFY_KEY=a1b2c3d4e5f6g7h8i9j0k1l2m3n4o5p6
```

---

### Krok 3: Nasazení na produkční server

#### Varianta A: Přes .env soubor (doporučeno)

1. Nahrajte aktualizovaný `.env` soubor na server
2. Umístěte ho do root složky (stejná úroveň jako `index.php`)
3. Ujistěte se, že má správná oprávnění:
   ```bash
   chmod 600 .env
   chown www-data:www-data .env  # nebo váš webserver user
   ```

#### Varianta B: Přes Environment Variables (hosting)

Pokud váš hosting podporuje environment variables (např. cPanel, Plesk):

1. Přejděte do **Environment Variables** sekce
2. Přidejte novou proměnnou:
   - **Name:** `GEOAPIFY_API_KEY`
   - **Value:** `váš_api_klíč`
3. Restartujte webserver nebo PHP-FPM

#### Varianta C: Přes .htaccess (alternativa)

Přidejte do `.htaccess`:

```apache
SetEnv GEOAPIFY_API_KEY "váš_api_klíč"
```

**⚠️ POZOR:** .htaccess je veřejně dostupný, raději použijte variantu A nebo B!

---

### Krok 4: Ověření

Po nastavení API klíče:

1. **Zkontrolujte v prohlížeči:**
   - Otevřete Developer Tools (F12)
   - Přejděte na záložku **Network**
   - Obnovte stránku s mapou (`novareklamace.php`)
   - Najděte requesty na `geocode_proxy.php?action=tile`
   - Ověřte, že Content-Type je **`image/png`** (ne `application/json`)
   - Ověřte, že Status je **`200 OK`**

2. **Testovací request:**

```bash
curl -i "https://www.wgs-service.cz/api/geocode_proxy.php?action=autocomplete&text=Praha&type=city"
```

**Očekávaný výsledek:**
```json
{
  "type": "FeatureCollection",
  "features": [
    {
      "type": "Feature",
      "properties": {
        "formatted": "Praha, Česko"
        ...
      }
    }
  ]
}
```

**Chybový výsledek (pokud klíč chybí):**
```json
{
  "error": "GEOAPIFY_KEY není nastaveno v konfiguraci"
}
```

---

## 🔒 Bezpečnost

### ✅ Co dělá geocode_proxy.php:

1. **Skrývá API klíč** před klienty (JavaScript ho nikdy nevidí)
2. **Rate limiting** - omezuje počet requestů per IP
3. **Validace parametrů** - kontroluje vstupní data
4. **Session locking prevence** - pro tile requesty zavírá session

### ❌ Co NEDĚLAT:

- ❌ **NIKDY** nepřidávejte API klíč přímo do JavaScriptu
- ❌ **NIKDY** necommitujte `.env` soubor do gitu
- ❌ **NIKDY** nezveřejňujte API klíč na GitHubu nebo veřejně

---

## 🐛 Troubleshooting

### Problém: "GEOAPIFY_KEY není nastaveno"

**Řešení:**
1. Zkontrolujte, že `.env` soubor existuje v root složce
2. Zkontrolujte, že řádek s `GEOAPIFY_KEY` není zakomentovaný (`#`)
3. Zkontrolujte, že není mezera kolem `=` znaku
4. Restartujte PHP-FPM nebo webserver

### Problém: "Chyba při komunikaci s Geoapify API"

**Řešení:**
1. Zkontrolujte, že API klíč je platný (zkopírujte znovu z Geoapify dashboardu)
2. Zkontrolujte, že nemáte vyčerpaný free tier limit (3,000/den)
3. Zkontrolujte, že projekt na Geoapify není pozastavený

### Problém: Mapa se načítá, ale autocomplete ne

**Řešení:**
1. Otevřete browser console (F12 → Console)
2. Hledejte JavaScript chyby
3. Zkontrolujte Network tab - které requesty failují
4. Ověřte, že `novareklamace.js` správně volá `/api/geocode_proxy.php?action=autocomplete`

### Problém: Content-Type stále `application/json` pro tiles

**Řešení:**
1. Vyprázdněte browser cache (Ctrl+Shift+R nebo Cmd+Shift+R)
2. Vyprázdněte server cache (pokud používáte Varnish/Redis)
3. Ověřte, že máte nejnovější verzi `geocode_proxy.php` (commit `7af8e35`)

---

## 📊 Monitoring

### Sledování API použití:

1. Přihlaste se na https://myprojects.geoapify.com/
2. Vyberte projekt **WGS Service**
3. Klikněte na **Usage** záložku
4. Sledujte:
   - **Denní requesty** (max 3,000 na free tier)
   - **API latency** (měla by být <500ms)
   - **Error rate** (měl by být <1%)

### Upozornění na limity:

Pokud se blížíte k 3,000 requestům denně:

1. **Optimalizujte cachování** map tiles v browseru
2. **Zvažte upgrade** na paid tier ($0.001 per request)
3. **Implementujte CDN** pro statické map tiles

---

## 🚀 Alternativy (pokud Geoapify nefunguje)

### Routing: OSRM (již implementováno)

Aplikace primárně používá **OSRM** (Open Source Routing Machine) pro výpočet tras:
- ✅ **ZDARMA** - bez API klíče
- ✅ **Open source** - běží na OpenStreetMap datech
- ✅ **Rychlé** - optimalizované pro automotive routing

Geoapify se používá pouze jako fallback.

### Map Tiles: OpenStreetMap (requires proxy)

Pokud chcete používat OSM tiles místo Geoapify:

1. Upravte `novareklamace.js`:
   ```javascript
   L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
     attribution: '© OpenStreetMap contributors'
   })
   ```

**⚠️ POZOR:** OSM má striktní usage policy - musíte respektovat tile usage limits!

---

## 📝 Checklist

- [ ] Vytvořen Geoapify účet
- [ ] Zkopírován API klíč
- [ ] Aktualizován `.env` soubor
- [ ] Soubor nahrán na server
- [ ] Oprávnění nastavena (`chmod 600 .env`)
- [ ] Webserver restartován
- [ ] Mapa se načítá ✅
- [ ] Autocomplete funguje ✅
- [ ] Network requests vracejí `image/png` pro tiles ✅
- [ ] Žádné chyby v browser console ✅

---

**Autor:** Claude AI
**Datum:** 2025-11-16
**Verze:** 1.0
