# 📄 SHRNUTÍ: Implementace PDF parsování a validace

**Datum:** 2025-11-20
**Branch:** `claude/finish-novareklamace-page-01RGeQMSYUBLKfu5biKqrWEf`

---

## ✅ CO BYLO IMPLEMENTOVÁNO:

### 1. **Validace povinných polí formuláře**

Před odesláním reklamace se kontrolují **VŠECHNA povinná pole**:

- ✅ Jméno a příjmení
- ✅ E-mail
- ✅ Telefon
- ✅ Ulice a ČP
- ✅ Město
- ✅ PSČ
- ✅ Popis problému

**Funkce:**
- Prázdná pole se **červeně označí** (červený border + světle červené pozadí)
- Automatický **scroll na první chybějící pole**
- Zobrazí se **toast hláška** se seznamem chybějících polí
- Když uživatel začne psát → červené označení se **automaticky odstraní**

**Soubor:** `assets/js/novareklamace.js`
**Funkce:** `validatePovinnaPole()` (řádek ~606)

---

### 2. **Analýza PHASE protokolu (slovenský)**

Vytvořena kompletní analýza slovenského PHASE protokolu:

**Soubor:** `ANALYZA_PHASE_PDF.md`

**Obsahuje:**
- Vizuální struktura PDF
- RAW text extrakce
- Mapování polí PDF → SQL tabulka
- Regex patterns pro všechna pole
- Testovací data

**Testovací PDF:** `uploads/PHASE PROTOKOL.pdf`
**Zákazník:** Michaela Vachutová
**Adresa:** Havlíčkovo nábřeží 5357, Zlín

---

### 3. **SQL skripty pro aktualizaci patterns**

#### **A) Pro NATUZZI protokol:**

**Soubor:** `oprav_ulici_pattern.sql`

**Pattern pro ulici:**
```sql
'/adresa:\\s+([^\\n]+?)(?:\\s+(?:Meno|Jméno)|$)/ui'
```

**Spustit v phpMyAdmin:**
```sql
UPDATE wgs_pdf_parser_configs
SET regex_patterns = JSON_SET(
    regex_patterns,
    '$.ulice',
    '/adresa:\\\\s+([^\\\\n]+?)(?:\\\\s+(?:Meno|Jméno)|$)/ui'
)
WHERE zdroj = 'natuzzi';
```

#### **B) Pro PHASE protokol:**

**Soubor:** `aplikuj_phase_patterns.sql`

**Obsahuje:**
- Kompletní regex patterns pro všechna pole (slovensky)
- Pole mapping (slovenské názvy → české SQL sloupce)
- Detekční pattern pro auto-detekci PHASE PDF

**Spustit v phpMyAdmin:** Celý obsah souboru `aplikuj_phase_patterns.sql`

---

## 🚀 CO MUSÍTE UDĚLAT:

### **KROK 1: Spustit migrační skripty**

Otevřete tyto odkazy v prohlížeči (automaticky se otevře migrační rozhraní):

#### **A) Oprava patternu pro ulici:**
```
https://www.wgs-service.cz/oprav_ulici_pattern.php
```
- Zobrazí se stávající patterns pro NATUZZI a PHASE
- Klikněte **"▶️ SPUSTIT MIGRACI"**
- Opraví pattern pro pole "ulice" v obou protokolech

#### **B) Aktualizace PHASE patterns:**
```
https://www.wgs-service.cz/aplikuj_phase_patterns.php
```
- Zobrazí se náhled co bude provedeno
- Klikněte **"▶️ SPUSTIT MIGRACI"**
- Aktualizuje všechny patterns pro slovenský PHASE protokol

---

### **KROK 2: Otestovat NATUZZI PDF**

1. Otevřete `https://www.wgs-service.cz/novareklamace.php`
2. **Přihlaste se** (tlačítko je viditelné jen pro přihlášené)
3. Klikněte **"📄 VYBRAT PDF SOUBOR"**
4. Nahrajte `uploads/NATUZZI PROTOKOL.pdf`
5. **Zkontrolujte že se vyplnila VŠECHNA pole:**
   - Číslo: `NCE25-00002444-39/CZ785-2025` ✓
   - Datum prodeje: `12.11.2025` ✓
   - Datum reklamace: `12.11.2025` ✓
   - Jméno: `Petr Kmoch` ✓
   - Email: `kmochova@petrisk.cz` ✓
   - Telefon: `725 387 868` ✓
   - **Ulice: `Na Blatech 396`** ← **NEJDŮLEŽITĚJŠÍ!**
   - Město: `Osnice` ✓
   - PSČ: `25242` ✓
   - Model: `C157 Intenso; LE02 Orbitale; Matrace` ✓
   - Provedení: `TG 20JJ Light Beige; INÉ; 70.0077.02 Rose` ✓
   - Popis problému: `Tak odstáté polštáře...` ✓

### **KROK 3: Otestovat PHASE PDF**

1. Znovu na `https://www.wgs-service.cz/novareklamace.php`
2. Klikněte **"📄 VYBRAT PDF SOUBOR"**
3. Nahrajte `uploads/PHASE PROTOKOL.pdf`
4. **Zkontrolujte že se vyplnila VŠECHNA pole:**
   - Číslo: `ZL3-00003001-49/CZ371-2025` ✓
   - Datum prodeje: `21.02.2025` ✓
   - Datum reklamace: `19.05.2025` ✓
   - Jméno: `Michaela Vachutová` ✓
   - Email: `vachutova.m@gmail.com` ✓
   - Telefon: `731 663 780` ✓
   - **Ulice: `Havlíčkovo nábřeží 5357`** ← **KLÍČOVÉ!**
   - Město: `Zlín` ✓
   - PSČ: `76001` ✓
   - Model: `C243 kreslo Until` ✓
   - Provedení: `DENVER A0BS koža` ✓
   - Popis problému: `Kreslo UNTIL sa neotáča...` ✓

### **KROK 4: Otestovat validaci prázdných polí**

1. Na `novareklamace.php` **vymažte** všechna pole
2. Klikněte **"ODESLAT REKLAMACI"**
3. **Mělo by se stát:**
   - ❌ Toast hlášká: "Vyplňte prosím všechna povinná pole: Jméno a příjmení, E-mail, Telefon, Ulice a ČP, Město, PSČ, Popis problému"
   - 🔴 Všechna prázdná pole budou **červeně** označena
   - 📜 Stránka **scrollne** na první prázdné pole
4. **Začněte psát do prvního pole** → červené označení zmizí

---

## 🐛 CO DĚLAT KDYŽ TO NEFUNGUJE:

### **Ulice se nevyplňuje:**

1. **Zkontrolujte SQL patterns:**
   ```sql
   SELECT
       zdroj,
       JSON_EXTRACT(regex_patterns, '$.ulice') AS ulice_pattern
   FROM wgs_pdf_parser_configs
   WHERE zdroj IN ('natuzzi', 'phase');
   ```

2. **Pattern pro ulici MUSÍ být:**
   ```
   "/adresa:\\s+([^\\n]+?)(?:\\s+(?:Meno|Jméno)|$)/ui"
   ```
   (s **dvojitými backslashes** `\\s` v JSON!)

3. **Otevřete konzoli** (F12) a nahrajte PDF znovu
4. Podívejte se na **výstup parsování** v console.log

### **Validace nefunguje:**

1. **Zkontrolujte že soubor byl deployován:**
   - URL: `https://www.wgs-service.cz/assets/js/novareklamace.js`
   - Hledejte funkci `validatePovinnaPole()`

2. **Vyčistěte cache prohlížeče:** Ctrl+F5

---

## 📊 STATISTIKY:

**Soubory změněny:** 4
**Řádků kódu přidáno:** 334
**Řádků kódu odebráno:** 16

**Nové soubory:**
- `ANALYZA_PHASE_PDF.md` - Analýza slovenského protokolu
- `aplikuj_phase_patterns.sql` - SQL update pro PHASE patterns

**Upravené soubory:**
- `assets/js/novareklamace.js` - Validace formuláře
- `oprav_ulici_pattern.sql` - Oprava patternu pro ulici

---

## 📞 SUPPORT:

Pokud něco nefunguje:

1. Pošlete **screenshot** z konzole (F12)
2. Uveďte **jaké PDF** jste nahrávali
3. Uveďte **co se stalo** vs. **co jste očekávali**

---

© 2025 WGS Service - PDF Parsování a Validace
