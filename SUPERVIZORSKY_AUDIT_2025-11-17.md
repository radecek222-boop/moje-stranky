# 🔍 KOMPLETNÍ SUPERVIZORSKÝ AUDIT REPORT
## WGS Service - Projekt Reklamace
**Datum:** 2025-11-17
**Supervizor:** Claude Code AI
**Rozsah:** Kompletní analýza PHP, JavaScript, SQL, API endpointů

---

## 📋 EXECUTIVE SUMMARY

Byla provedena hloubková analýza celého projektu s fokusem na konzistenci databázové struktury `wgs_reklamace` s formulářem `novareklamace.php` a všemi souvisejícími soubory.

### ⚠️ KRITICKÁ ZJIŠTĚNÍ

1. **API statistiky používá ZASTARALÉ sloupce** (`technik_milan_kolin`, `technik_radek_zikmund`)
2. **Nekonzistence** mezi formulářem a databází
3. **P1 chyby** opraveny (sendJsonError → respondError)

---

## 🎯 REFERENCE: novareklamace.php FORMULÁŘ

### Pole která formulář ODESÍLÁ do save.php:

| #  | Název pole         | Type     | Required | Poznámka                           |
|----|--------------------|----------|----------|------------------------------------|
| 1  | `typ`              | hidden   | ❌       | Default 'servis', není ve formuláři|
| 2  | `cislo`            | text     | ⚠️       | Povinné pro přihlášené             |
| 3  | `datum_prodeje`    | date     | ⚠️       | Povinné pro přihlášené             |
| 4  | `datum_reklamace`  | date     | ⚠️       | Povinné pro přihlášené             |
| 5  | `jmeno`            | text     | ✅       | Vždy povinné                       |
| 6  | `email`            | email    | ✅       | Vždy povinné                       |
| 7  | `telefon`          | tel      | ✅       | Vždy povinné                       |
| 8  | `ulice`            | text     | ❌       | Součást adresy                     |
| 9  | `mesto`            | text     | ❌       | Součást adresy                     |
| 10 | `psc`              | text     | ❌       | Součást adresy                     |
| 11 | `model`            | text     | ❌       | Model nábytku                      |
| 12 | `provedeni`        | text     | ❌       | Provedení (vyber z overlaye)       |
| 13 | `barva`            | text     | ❌       | Barva (např. BF12)                 |
| 14 | `doplnujici_info`  | textarea | ❌       | Doplňující informace               |
| 15 | `popis_problemu`   | textarea | ✅       | Popis problému (vždy povinný)      |
| 16 | `fakturace_firma`  | select   | ❌       | CZ/SK (default: CZ)                |
| 17 | `gdpr_consent`     | checkbox | ✅       | Pouze pro neregistrované           |
| 18 | Photos             | file[]   | ❌       | Upload fotografií                  |

### Pole která formulář NEOBSAHUJE (ale save.php je podporuje):

- `seriove_cislo` - save.php ho akceptuje, ale není ve formuláři
- `technik` - nastavuje se až později (admin/protocol)
- `prodejce` - nastavuje se automaticky podle přihlášeného uživatele
- `stav`, `termin`, `cas_navstevy` - nastavuje se později

---

## 🗄️ DATABÁZOVÁ STRUKTURA wgs_reklamace

### ✅ SPRÁVNÁ STRUKTURA (z FINAL_DDL_wgs_reklamace.sql):

**Celkem 42 sloupců:**

#### Primární klíče:
- `id` INT AUTO_INCREMENT PRIMARY KEY
- `reklamace_id` VARCHAR(50) UNIQUE (AUTO generované WGSyymmdd-XXXXXX)

#### Základní údaje:
- `typ` ENUM('REKLAMACE','INSTALACE','SERVIS')
- `cislo` VARCHAR(100)
- `datum_prodeje` DATE
- `datum_reklamace` DATE

#### Kontaktní údaje:
- `jmeno` VARCHAR(255) NOT NULL
- `email` VARCHAR(255) NOT NULL
- `telefon` VARCHAR(50) NOT NULL

#### Adresa (VŠECHNY 4 SLOUPCE JSOU POTŘEBA):
- `adresa` VARCHAR(500) - složená (ulice + město + PSČ)
- `ulice` VARCHAR(255) ✅ **POUŽÍVÁ SE!**
- `mesto` VARCHAR(255) ✅ **POUŽÍVÁ SE!**
- `psc` VARCHAR(20) ✅ **POUŽÍVÁ SE!**

#### Produkt:
- `model` VARCHAR(255)
- `seriove_cislo` VARCHAR(255)
- `provedeni` VARCHAR(255)
- `barva` VARCHAR(100)

#### Problém a řešení:
- `popis_problemu` TEXT
- `doplnujici_info` TEXT
- `popis_opravy` TEXT

#### Stav a termín:
- `stav` ENUM('wait','open','done') DEFAULT 'wait'
- `termin` DATE
- `cas_navstevy` VARCHAR(50)
- `vyreseno` TINYINT(1) DEFAULT 0

#### Zpracování:
- `zpracoval` VARCHAR(255) (deprecated)
- `zpracoval_id` INT(11)
- `prodejce` VARCHAR(255)
- **`technik` VARCHAR(255)** ✅ **NOVÝ SPRÁVNÝ SLOUPEC**
- `technik_milan_kolin` TINYINT(1) ⚠️ **LEGACY - DEPRECATED**
- `technik_radek_zikmund` TINYINT(1) ⚠️ **LEGACY - DEPRECATED**

#### Fakturace:
- `cena` DECIMAL(10,2)
- `castka` DECIMAL(10,2) (duplikát `cena`)
- `fakturace_firma` ENUM('cz','sk') DEFAULT 'cz'
- `zeme` VARCHAR(2) (duplikát `fakturace_firma`)

#### Protokol:
- `datum_protokolu` TIMESTAMP
- `datum_dokonceni` TIMESTAMP

#### Metadata:
- `poznamky` TEXT
- `email_zadavatele` VARCHAR(255)
- `created_by` INT(11)
- `created_by_role` VARCHAR(20)
- `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
- `updated_at` TIMESTAMP ON UPDATE CURRENT_TIMESTAMP

---

## ❌ PROBLÉM #1: ZASTARALÉ SLOUPCE V STATISTIKÁCH

### Soubor: `api/statistiky_api.php`

**Řádky s problémem:**
- 100-107: Počítání aktivních techniků pomocí `technik_milan_kolin` a `technik_radek_zikmund`
- 176-210: UNION query pro statistiky techniků používá staré sloupce
- 304-306: CASE pro zobrazení jména technika
- 461-467: Filtry podle technika

**Co je špatně:**
- Používá `technik_milan_kolin > 0` a `technik_radek_zikmund > 0`
- Mělo by se používat: `technik = 'Milan Kolín'` nebo `technik = 'Radek Zikmund'`

**Dopad:**
- Statistiky nefungují správně protože očekávají boolean sloupce místo VARCHAR
- Nové záznamy (které používají `technik`) se nezobrazí ve statistikách
- Vytváří se nekonzistence

**Řešení:**
Přepsat všechny dotazy aby používaly sloupec `technik` VARCHAR(255).

---

## ❌ PROBLÉM #2: MINIFIKOVANÝ JAVASCRIPT

### Soubor: `assets/js/statistiky.min.js`

**Co je špatně:**
- Obsahuje references na `technik_milan_kolin` a `technik_radek_zikmund`
- Minifikovaný soubor → musím najít SOURCE

**Kde hledat source:**
- `assets/js/statistiky.js` (ne-minifikovaná verze)

**Řešení:**
1. Opravit source soubor
2. Re-minifikovat

---

## ✅ CO FUNGUJE SPRÁVNĚ

### `app/controllers/save.php`:
- ✅ Správně ukládá `ulice`, `mesto`, `psc`
- ✅ Podporuje `technik` sloupec (řádek 178)
- ✅ Správně mapuje ENUM hodnoty (ČEKÁ → wait, CZ → cz)
- ✅ Používá transakce
- ✅ Generuje unikátní `reklamace_id`

### `novareklamace.php`:
- ✅ Odesílá všechna potřebná pole
- ✅ GDPR consent správně implementován
- ✅ Má `ulice`, `mesto`, `psc` inputy

### `FINAL_DDL_wgs_reklamace.sql`:
- ✅ Kompletní struktura se všemi sloupci
- ✅ Obsahuje `technik` VARCHAR(255)
- ✅ Obsahuje legacy sloupce pro zpětnou kompatibilitu
- ✅ Správné indexy

---

## 🔧 SEZNAM SOUBORŮ K OPRAVĚ

### 🔴 KRITICKÉ (nefunguje kvůli tomu):

1. **`api/statistiky_api.php`**
   - Nahradit všechny `technik_milan_kolin` → `technik = 'Milan Kolín'`
   - Nahradit všechny `technik_radek_zikmund` → `technik = 'Radek Zikmund'`
   - Přepsat UNION query
   - Přepsat filtry

2. **`assets/js/statistiky.js`** (source)
   - Nahradit references na staré sloupce
   - Re-minifikovat

### 🟡 K PROVĚŘENÍ:

3. **`admin.php`** - zkontrolovat jestli nepoužívá staré sloupce
4. **`seznam.php`** - zkontrolovat jestli nepoužívá staré sloupce
5. **`protokol.php`** - zkontrolovat jak nastavuje technika
6. **`assets/js/seznam.js`** - zkontrolovat
7. **`assets/js/protokol.js`** - zkontrolovat

### ✅ UŽ OPRAVENO:

8. **`api/email_resend_api.php`** ✅ P1 chyba opravena
9. **`api/admin_users_api.php`** ✅ P1 chyba opravena

---

## 📝 OPRAVENÝ INSERT STATEMENT

### ❌ ŠPATNÝ (uživatel ukázal):
```sql
INSERT INTO `wgs_reklamace`(
  `id`, `reklamace_id`, `typ`, `cislo`, `datum_prodeje`, `datum_reklamace`,
  `jmeno`, `email`, `telefon`, `adresa`, `model`, `seriove_cislo`, `provedeni`,
  `barva`, `popis_problemu`, `stav`, `termin`, `cas_navstevy`,
  `zpracoval`, `zpracoval_id`, `created_by`, `created_by_role`, `email_zadavatele`,
  `popis_opravy`, `vyreseno`, `datum_protokolu`, `datum_dokonceni`, `poznamky`,
  `fakturace_firma`, `created_at`, `updated_at`, `cena`,
  `technik_milan_kolin`, `technik_radek_zikmund`, `doplnujici_info`  ❌ ŠPATNĚ!
)
```

### ✅ SPRÁVNÝ:
```sql
INSERT INTO `wgs_reklamace`(
  -- Primární klíče (AUTO)
  -- id - AUTO_INCREMENT
  `reklamace_id`,          -- AUTO generované WGSyymmdd-XXXXXX

  -- Základní údaje
  `typ`,                   -- 'REKLAMACE' | 'INSTALACE' | 'SERVIS'
  `cislo`,                 -- Číslo objednávky
  `datum_prodeje`,         -- Datum prodeje
  `datum_reklamace`,       -- Datum reklamace

  -- Kontaktní údaje zákazníka
  `jmeno`,                 -- Jméno zákazníka (POVINNÉ)
  `email`,                 -- Email (POVINNÉ)
  `telefon`,               -- Telefon (POVINNÉ)

  -- Adresa (složená + komponenty)
  `adresa`,                -- Složená adresa
  `ulice`,                 -- ✅ POTŘEBUJEME!
  `mesto`,                 -- ✅ POTŘEBUJEME!
  `psc`,                   -- ✅ POTŘEBUJEME!

  -- Produkt
  `model`,                 -- Model nábytku
  `seriove_cislo`,         -- Sériové číslo
  `provedeni`,             -- Provedení (barva, materiál)
  `barva`,                 -- Barva

  -- Problém a řešení
  `popis_problemu`,        -- Popis problému (POVINNÉ)
  `doplnujici_info`,       -- Doplňující info
  `popis_opravy`,          -- Popis opravy (protokol)

  -- Stav a termín
  `stav`,                  -- 'wait' | 'open' | 'done'
  `termin`,                -- Termín návštěvy
  `cas_navstevy`,          -- Čas návštěvy
  `vyreseno`,              -- Boolean (0/1)

  -- Zpracování
  `zpracoval`,             -- Deprecated text
  `zpracoval_id`,          -- ID uživatele
  `prodejce`,              -- Jméno prodejce
  `technik`,               -- ✅ SPRÁVNÝ SLOUPEC! VARCHAR(255) např. 'Milan Kolín'

  -- Fakturace
  `cena`,                  -- Cena opravy
  `castka`,                -- Duplikát ceny
  `fakturace_firma`,       -- 'cz' | 'sk'
  `zeme`,                  -- Duplikát fakturace_firma

  -- Protokol
  `datum_protokolu`,       -- Timestamp protokolu
  `datum_dokonceni`,       -- Timestamp dokončení

  -- Metadata
  `poznamky`,              -- Interní poznámky
  `email_zadavatele`,      -- Email tvůrce záznamu
  `created_by`,            -- ID tvůrce
  `created_by_role`,       -- Role tvůrce
  `created_at`,            -- Timestamp vytvoření
  `updated_at`             -- Timestamp aktualizace
) VALUES (...)
```

**Poznámka:** Sloupce `technik_milan_kolin` a `technik_radek_zikmund` **EXISTUJÍ** v tabulce (pro zpětnou kompatibilitu), ale **NEMĚLY BY SE POUŽÍVAT**. Nově se používá pouze `technik` VARCHAR(255).

---

## 🎯 AKČNÍ PLÁN OPRAV

### FÁZE 1: OPRAVA STATISTIK (P0 - Kritické)
1. ✅ Opravit `api/statistiky_api.php`
2. ✅ Najít a opravit `assets/js/statistiky.js`
3. ✅ Re-minifikovat `statistiky.min.js`

### FÁZE 2: PROVĚŘENÍ OSTATNÍCH SOUBORŮ (P1 - Důležité)
4. ✅ Zkontrolovat `admin.php`
5. ✅ Zkontrolovat `seznam.php`
6. ✅ Zkontrolovat `protokol.php`
7. ✅ Zkontrolovat všechny JS soubory

### FÁZE 3: DOKUMENTACE (P2 - Nízká priorita)
8. ✅ Aktualizovat dokumentaci
9. ✅ Vytvořit migration script (pokud potřeba)

### FÁZE 4: TESTING
10. ✅ Otestovat statistiky
11. ✅ Otestovat vytvoření nové reklamace
12. ✅ Otestovat protokol

---

## 📊 ZÁVĚR

### Hlavní problémy:
1. **Statistiky používají zastaralé sloupce** - tohle je hlavní problém
2. **Formulář je OK** - odesílá správná data
3. **save.php je OK** - ukládá správně
4. **DDL je OK** - obsahuje všechny potřebné sloupce

### Co opravit:
- **api/statistiky_api.php** - kritické
- **assets/js/statistiky.js** - kritické
- Ostatní soubory prověřit

### Správný INSERT statement:
Poskytnut výše - obsahuje `technik` VARCHAR(255) místo `technik_milan_kolin` a `technik_radek_zikmund`.

---

**KONEC AUDITU**
**Připraveno k opravám**
