# CZ/SK Fakturace - Kompletní workflow

## Účel
Tento systém zajišťuje, aby informace o tom, zda je zákazník z CZ nebo SK firmy, provázela zákazníka **celým workflow** od začátku až do konce.

## Co je implementováno

### 1. ✅ Formulář nové reklamace (novareklamace.php)
- **Soubor**: `novareklamace.php` (řádek 247-251)
- Select s možností CZ 🇨🇿 nebo SK 🇸🇰
- Výchozí hodnota: CZ
- Hint se dynamicky mění podle výběru

**Jak to funguje:**
```html
<select id="fakturace_firma" name="fakturace_firma">
  <option value="CZ" selected>🇨🇿 CZ</option>
  <option value="SK">🇸🇰 SK</option>
</select>
```

### 2. ✅ Odesílání dat (novareklamace.js)
- **Soubor**: `assets/js/novareklamace.js` (řádek 457-458)
- JavaScript automaticky přidá `fakturace_firma` do FormData
- Odesílá se do `app/controllers/save.php`

### 3. ✅ Ukládání do databáze (save.php)
- **Soubor**: `app/controllers/save.php`
  - Řádek 101: `fakturace_firma` v allowedFields (pro UPDATE)
  - Řádek 243: Načtení z POST dat při CREATE
  - Řádek 353: Uložení do databáze
- Výchozí hodnota: 'CZ'
- Sanitizace: Ano (sanitizeInput)

### 4. ✅ Načítání dat (load.php)
- **Soubor**: `app/controllers/load.php` (řádek 111)
- Používá `SELECT r.*` - načte všechny sloupce včetně `fakturace_firma`

### 5. ✅ Zobrazení v seznamu (seznam.js)
- **Soubor**: `assets/js/seznam.js` (řádek 1390, 1453-1454)
- Zobrazuje s vlajkami a barvami:
  - 🇨🇿 Česká republika (CZ) - modrá barva (#0066cc)
  - 🇸🇰 Slovensko (SK) - zelená barva (#059669)

**Ukázka:**
```javascript
const fakturace_firma = CURRENT_RECORD.fakturace_firma || 'CZ';
// ...
<div style="color: ${fakturace_firma === 'SK' ? '#059669' : '#0066cc'};">
  ${fakturace_firma === 'CZ' ? '🇨🇿 Česká republika (CZ)' : '🇸🇰 Slovensko (SK)'}
</div>
```

### 6. ✅ Zobrazení v protokolu (protokol.php)
- **Soubor HTML**: `protokol.php` (řádek 72)
- **Soubor JS**: `assets/js/protokol-fakturace-patch.js`
- Nové pole "Fakturace" v levém sloupci formuláře
- Automaticky se vyplní při načtení dat
- Zobrazuje s vlajkami a barvami (stejně jako v seznamu)

### 7. ✅ PDF Protokol
- Informace CZ/SK se zobrazuje v HTML protokolu
- Při exportu do PDF (html2canvas) se zachová vizuální zobrazení včetně vlajek

## Instalace - DŮLEŽITÉ! 🔧

### Krok 1: Spustit migraci databáze
```sql
-- Spustit soubor migration_add_fakturace_firma.sql
-- Cesta: /migration_add_fakturace_firma.sql

-- Nebo přímo v MySQL:
ALTER TABLE wgs_reklamace
ADD COLUMN IF NOT EXISTS fakturace_firma VARCHAR(2) DEFAULT 'CZ'
COMMENT 'CZ nebo SK firma pro fakturaci';

CREATE INDEX IF NOT EXISTS idx_fakturace_firma ON wgs_reklamace(fakturace_firma);

UPDATE wgs_reklamace
SET fakturace_firma = 'CZ'
WHERE fakturace_firma IS NULL OR fakturace_firma = '';
```

### Krok 2: Ověřit funkčnost
1. Otevřít `novareklamace.php`
2. Vybrat SK nebo CZ v selectu
3. Vyplnit a odeslat formulář
4. Zkontrolovat v `seznam.php` - měla by se zobrazit vlajka a správná země
5. Otevřít protokol - měla by se zobrazit fakturace

### Krok 3: Testování
```javascript
// V browser console po odeslání formuláře:
// Zkontrolovat localStorage
const customer = JSON.parse(localStorage.getItem('currentCustomer'));
console.log('Fakturace:', customer.fakturace_firma); // Mělo by být 'CZ' nebo 'SK'
```

## Výhody řešení

1. **Zpětná kompatibilita**: Všechny existující reklamace dostanou výchozí hodnotu 'CZ'
2. **Konzistentní zobrazení**: Jednotný design s vlajkami a barvami všude
3. **Automatické**: Uživatel vyplní jednou, systém si pamatuje
4. **Viditelné všude**: Formulář → DB → Seznam → Protokol → PDF
5. **Indexováno**: Rychlé filtrování podle země (pokud potřeba v budoucnu)

## Soubory změněné/vytvořené

### Nové soubory:
- `migration_add_fakturace_firma.sql` - Migrace databáze
- `assets/js/protokol-fakturace-patch.js` - Patch pro protokol
- `CZ_SK_FAKTURACE_README.md` - Tato dokumentace

### Upravené soubory:
- `protokol.php` - Přidáno pole pro fakturaci (řádek 72)
- `protokol.php` - Načtení patch JS (řádek 167)

### Existující soubory (BEZ ZMĚN):
- ✅ `novareklamace.php` - Select už tam byl
- ✅ `assets/js/novareklamace.js` - Odesílání už fungovalo
- ✅ `app/controllers/save.php` - Ukládání už fungovalo
- ✅ `app/controllers/load.php` - Načítání všech sloupců
- ✅ `assets/js/seznam.js` - Zobrazení už fungovalo

## Technické detaily

### Datový tok
```
Formulář (novareklamace.php)
    ↓ [fakturace_firma: 'CZ' nebo 'SK']
JavaScript (novareklamace.js:457)
    ↓ [FormData append]
API (save.php:243,353)
    ↓ [INSERT INTO wgs_reklamace]
Databáze (wgs_reklamace.fakturace_firma)
    ↓ [VARCHAR(2), indexed]
API (load.php:111)
    ↓ [SELECT r.*]
Frontend (seznam.js:1390,1453)
    ↓ [Zobrazení s vlajkami]
Protokol (protokol.php + patch.js)
    ↓ [Vyplnění pole]
PDF Export (html2canvas)
    ✓ [Kompletní workflow]
```

### Bezpečnost
- ✅ Sanitizace vstupu (sanitizeInput)
- ✅ Validace formátu ('CZ' nebo 'SK')
- ✅ Výchozí hodnota pro NULL
- ✅ Index pro performance

## Řešení problémů

### Fakturace se nezobrazuje v seznamu
- Zkontrolovat, jestli migrace proběhla
- Zkontrolovat v DB: `SELECT fakturace_firma FROM wgs_reklamace LIMIT 10;`

### Fakturace se nezobrazuje v protokolu
- Zkontrolovat načtení `protokol-fakturace-patch.js` v konzoli
- Zkontrolovat `currentReklamace` objekt: `console.log(currentReklamace)`

### Staré reklamace nemají CZ/SK
- Spustit UPDATE z migrace:
  ```sql
  UPDATE wgs_reklamace
  SET fakturace_firma = 'CZ'
  WHERE fakturace_firma IS NULL OR fakturace_firma = '';
  ```

## Další rozšíření (budoucnost)

Pokud by bylo potřeba:
- Filtrování podle země v seznam.php
- Statistiky CZ vs SK zakázek
- Různé ceny/sazby pro CZ/SK
- Automatické nastavení měny podle země
- Export do účetnictví s označením země

---

**Datum implementace**: 2025-01-11
**Verze**: 1.0
**Autor**: Claude (AI Assistant)
