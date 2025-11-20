# 📋 Shrnutí Opravy PDF Parseru

**Datum:** 2025-11-20
**Založeno na:** Analýza 4 testovacích PDF (base64.txt, base64-2.txt, base64-3.txt, base64-4.txt)

---

## 🔍 Identifikované Problémy

### 1. **NATUZZI PROTOKOL.pdf** ❌
- ✅ Číslo reklamace: OK
- ⚠️ Ulice: NEÚPLNÁ ("Blatech 396" místo "Na Blatech 396")
- ✅ Jméno: OK
- ✅ Email: OK
- ✅ Telefon: OK
- ✅ Město: OK
- ❌ **PSČ: CHYBÍ** (25242)

### 2. **NCM-NATUZZI.pdf** ❌❌
- ✅ Číslo reklamace: OK
- ❌ **Ulice: CHYBÍ** ("Jungmannovo náměstí 76")
- ✅ Jméno: OK
- ✅ Email: OK
- ✅ Telefon: OK
- ✅ Město: OK
- ❌ **PSČ: CHYBÍ** (110 00)

### 3. **PHASE CZ.pdf** ❌❌❌
- ❌ **TOTÁLNÍ SELHÁNÍ** - vybral PHASE SK místo PHASE CZ!
- ❌ Všechna data špatně nebo prázdná
- **Důvod:** Špatná detekce (priorita PHASE SK > PHASE CZ)

### 4. **PHASE PROTOKOL SK.pdf** ❌❌
- ⚠️ Číslo reklamace: NEÚPLNÉ
- ❌ **Ulice: CHYBÍ**
- ⚠️ Jméno: ŠPATNĚ ("Česko Krajina" místo "Michaela Vachutová")
- ❌ **Email: CHYBÍ**
- ❌ **Telefon: CHYBÍ**
- ⚠️ Město: NEÚPLNÉ ("Havlíčkovo" místo "Zlín")
- ❌ **PSČ: CHYBÍ**

---

## 🧩 Analýza RAW TEXT Struktury

### KLÍČOVÉ ZJIŠTĚNÍ ⚠️

PDF protokoly mají **VELMI NELOGICKOU STRUKTURU**:
- Labely (např. "Telefon:", "Email:", "PSČ:") **neodpovídají** hodnotám za nimi!
- Data jsou v **jiných pozicích**, než naznačují labely
- Je tam **DVAKRÁT** stejná sekce - před a po "Místo reklamace"

### Skutečná Struktura NATUZZI:

```
Místo reklamace
<EMAIL_skutečný>              ← email je HNED po "Místo reklamace"
<TELEFON_skutečný>            ← telefon je PŘED labelem "Telefon:"
Telefon: <něco_nepodstatné>   ← IGNOROVAT
Česko
Stát: <PSČ>                   ← PSČ je ZA "Stát:", NE za "PSČ:"!
Email: <MĚSTO>                ← MĚSTO je ZA "Email:", NE email! ⚠️
Město: <ULICE>                ← ULICE je ZA "Město:", NE město! ⚠️
Adresa: <něco_dalšího>
```

**Příklad z PDF:**
```
Místo reklamace kmochova@petrisk.cz 725 387 868 Telefon: Česko Stát: 25242 Email: Osnice Město: Na Blatech 396 Adresa:
```

**Jak to parsovat:**
- **Email:** `kmochova@petrisk.cz` (hned po "Místo reklamace")
- **Telefon:** `725 387 868` (před "Telefon:")
- **PSČ:** `25242` (za "Stát:")
- **Město:** `Osnice` (za "Email:" ⚠️)
- **Ulice:** `Na Blatech 396` (za "Město:" ⚠️)

### Skutečná Struktura PHASE SK:

```
Miesto reklamácie
<EMAIL>
<TELEFON>
Telefón: <ignore>
Česko
Krajina: <PSČ>        ← "Krajina" = slovensky "Stát"
Email: <MĚSTO>        ← stejný problém jako NATUZZI!
Mesto: <ULICE>        ← stejný problém jako NATUZZI!
Adresa: <něco>
```

### Skutečná Struktura PHASE CZ:

**První sekce (před "Místo servisní opravy"):**
```
Jméno a příjmení: <něco_špatného>
Stát: <PSČ_první_adresy>
PSČ: <MĚSTO_první_adresy>
Město: <ULICE_první_adresy>
Adresa: <EMAIL>                          ← EMAIL je v "Adresa:" !
Jméno společnosti: <JMÉNO_skutečné>      ← JMÉNO je tady!
Poschodí: ...
```

**Druhá sekce (po "Místo servisní opravy"):**
```
Místo servisní opravy
Telefon: <ignore>
Česko
Stát: <PSČ>
Email: <MĚSTO>        ← stejný problém!
Město: <ULICE>        ← stejný problém!
Adresa: <něco>
```

---

## ✅ Vytvořené Patterns (FINÁLNÍ)

### 1. NATUZZI Protokol

```php
$natuzziPatterns = [
    'email' => '/Místo\s+reklamace\s+([a-zA-Z0-9._%-]+@[...])/s',
    'telefon' => '/Místo\s+reklamace.*?([0-9\s]{9,})\s+Telefon:/s',
    'psc' => '/Stát:\s*(\d{3}\s?\d{2})/s',
    'mesto' => '/Email:\s*([^\n]+?)\s+Město:/s',    // ⚠️ MĚSTO je za "Email:"
    'ulice' => '/Město:\s*([^\n]+?)\s+Adresa:/s',   // ⚠️ ULICE je za "Město:"
    'jmeno' => '/Jméno\s+a\s+příjmení:\s*([^\n]+?)\s+(?:Poschodí|Stát)/s',
    // ... další ...
];
```

### 2. PHASE CZ Parser

```php
$phaseCzPatterns = [
    'jmeno' => '/Jméno\s+společnosti:\s*([^\n]+?)\s+(?:Poschodí|Rodinný|Panelák)/s',
    'email' => '/Adresa:\s*([a-zA-Z0-9._%-]+@[...])/s',  // ⚠️ EMAIL je v "Adresa:"
    'telefon' => '/((?:\+420)?\s*[67]\d{2}\s*\d{3}\s*\d{3})/',
    'psc' => '/Stát:\s*(\d{3}\s?\d{2})/s',
    'mesto' => '/Email:\s*([^\n]+?)\s+Město:/s',    // ⚠️ MĚSTO je za "Email:"
    'ulice' => '/Město:\s*([^\n]+?)\s+Adresa:/s',   // ⚠️ ULICE je za "Město:"
    // ... další ...
];
```

**Detekční pattern:**
```php
'/(Místo\s+servisní\s+opravy|Číslo\s+serv\.\s+opravy)/i'
```

### 3. PHASE SK Parser

```php
$phaseSkPatterns = [
    'jmeno' => '/Meno\s+spoločnosti:\s*([^\n]+?)\s+(?:Poschodie|Rodinný|Panelák)/s',
    'email' => '/Miesto\s+reklamácie\s+([a-zA-Z0-9._%-]+@[...])/s',
    'telefon' => '/Miesto\s+reklamácie.*?([0-9\s]{9,})\s+Telefón:/s',  // ⚠️ Slovenské "Telefón"
    'psc' => '/Krajina:\s*(\d{3}\s?\d{2})/s',                          // ⚠️ "Krajina" = "Stát"
    'mesto' => '/Email:\s*([^\n]+?)\s+Mesto:/s',
    'ulice' => '/Mesto:\s*([^\n]+?)\s+Adresa:/s',
    // ... další ...
];
```

**Detekční pattern:**
```php
'/(Miesto\s+reklamácie|Meno\s+a\s+priezvisko|Dátum\s+podania)/i'
```

---

## 🎯 Priority (KRITICKÉ!)

```
NATUZZI:  100  (nejvyšší - default)
PHASE CZ: 95   (vyšší než PHASE SK!)
PHASE SK: 90   (nejnižší)
```

**Důvod:**
- PHASE CZ musí mít **VYŠŠÍ** prioritu než PHASE SK
- Jinak se PHASE CZ PDF detekuje jako PHASE SK (protože SK patterns jsou méně specifické)

---

## 📦 Migrační Skript

**Soubor:** `finalni_oprava_pdf_parseru.php`

**Co opravuje:**
1. ✅ NATUZZI - PSČ a ulice patterns (+ město pattern)
2. ✅ PHASE CZ - detekční pattern + všechny field patterns + priorita 95
3. ✅ PHASE SK - všechny field patterns (jméno, ulice, email, telefon, PSČ, město)
4. ✅ Priority - správné pořadí (NATUZZI > PHASE CZ > PHASE SK)

**Jak spustit:**
1. Přihlásit se jako admin
2. Otevřít: `https://www.wgs-service.cz/finalni_oprava_pdf_parseru.php`
3. Kliknout "SPUSTIT OPRAVU"
4. Otestovat na: `test_pdf_parsing.php`

---

## 🧪 Testování

Po aplikaci migračního skriptu **POVINNĚ otestovat** všechny 4 PDF:

1. **NATUZZI PROTOKOL.pdf** → očekáváme všechna pole vyplněná (včetně PSČ)
2. **NCM-NATUZZI.pdf** → očekáváme všechna pole vyplněná (včetně ulice a PSČ)
3. **PHASE CZ.pdf** → očekáváme detekci "PHASE CZ Parser" (NE "PHASE SK"!)
4. **PHASE PROTOKOL SK.pdf** → očekáváme všechna pole vyplněná

---

## 📌 Poznámky pro Budoucnost

### Proč jsou Patterns tak Složité?

1. **Nelogická struktura PDF** - labely neodpovídají hodnotám
2. **Duplicitní sekce** - data jsou na začátku i v sekci "Místo reklamace"
3. **Slovenské vs. České** - různé labely ("Krajina" vs. "Stát", "Telefón" vs. "Telefon")
4. **Chybějící hodnoty** - některé labely nemají hodnoty vůbec

### Pokud Patterns Selžou v Budoucnu:

1. **Podívej se na RAW TEXT** z PDF (pomocí `analyzuj_pdf_strukturu.php`)
2. **Najdi skutečnou pozici dat** (ignoruj labely!)
3. **Uprav regex patterns** v migračním skriptu
4. **Otestuj na všech 4 PDF** před commitnutím

---

## 🔗 Související Soubory

- `finalni_oprava_pdf_parseru.php` - migrační skript
- `analyzuj_pdf_strukturu.php` - analýza PDF struktury
- `diagnostika_pdf_parseru.php` - diagnostika aktuálního stavu
- `test_pdf_parsing.php` - live testování
- `api/parse_povereni_pdf.php` - API endpoint
- `uploads/base64*.txt` - testovací PDF v Base64

---

**Autor:** Claude
**Session:** claude/test-pdf-parsing-01M1zjcPLu3Jbtby8AdCfTNa
