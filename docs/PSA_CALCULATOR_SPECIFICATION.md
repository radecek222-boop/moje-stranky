# PSA Kalkulátor - Technická Specifikace

**Verze:** 1.1
**Datum:** 2025-11-04
**Styl:** WGS (White Glove Service) - černá/bílá/šedá
**Přístup:** Veřejný (bez přihlášení)

---

## 📋 Přehled

PSA Kalkulátor je webová aplikace pro správu mezd a fakturace zaměstnanců. Aplikace replikuje logiku z Excel souboru PSAEXCEL.xlsx s důrazem na bezpečnost dat a sjednocený WGS design.

---

## 🔐 Bezpečnost

### Úložiště dat
- **Citlivá data:** `/www/data/psa-employees.json` (chráněno .htaccess)
- **API endpoint:** `/api/psa_api.php` (jediný přístup k datům)
- **Ochrana:** Přímý HTTP přístup k `/www/data/` je zakázán

### Tok dat
```
Browser → psa-kalkulator.html → API (psa_api.php) → Data (psa-employees.json)
                                                      ↑
                                             Protected by .htaccess
```

---

## 👥 Typy zaměstnanců a výpočty

### 1. Standard (většina zaměstnanců)
**Příklad:** Nevečný Tomáš, Stana, Anastasia, atd.

**Výpočet:**
```
Výplata = hodiny × sazba výplaty (150 Kč)
Faktura = hodiny × sazba fakturace (250 Kč)
```

**Parametry:**
- Číslo účtu + kód banky (4 číslice)
- Hodiny za období
- Standardní hodinovásazba

---

### 2. Marek (special)
**Typ:** `special`

**Výpočet:**
```
Výplata = 20 Kč × (součet hodin všech OSTATNÍCH zaměstnanců)
Faktura = 0 Kč
```

**Poznámka:**
- Nemá vlastní odpracované hodiny
- Bonus pouze z hodin ostatních (kromě Radka)

**Příklad:**
- Pokud ostatní odpracovali 1000 hodin
- Marek dostane: 1000 × 20 = 20,000 Kč

---

### 3. Radek (special2)
**Typ:** `special2`

**Výpočet:**
```
Základní bonus = 20 Kč × (součet hodin všech OSTATNÍCH zaměstnanců)
Skryté prémie = 10% z výplat všech ženských zaměstnanců
Výplata celkem = Základní bonus + Skryté prémie
Faktura = 0 Kč
```

**Seznam ženských zaměstnanců pro prémie:**
- Stana
- Anastasia
- Maryna Sosovuik
- Ivana Senynets
- Olha Shkudor
- Piven Tetiana
- Vitalina
- Tetiana
- Kataryna
- Ruslana
- Lenka

**Příklad:**
```
Ostatní odpracovali: 1000 hodin
Základní bonus: 1000 × 20 = 20,000 Kč

Ženy odpracovaly: 500 hodin
Výplata žen: 500 × 150 = 75,000 Kč
Skrytá prémie: 75,000 × 0.10 = 7,500 Kč

Radek celkem: 20,000 + 7,500 = 27,500 Kč
```

**Důležité:**
- Skryté prémie se NEZOBRAZUJÍ v tabulce
- Zobrazená částka: pouze základní bonus
- QR kód obsahuje celkovou částku včetně prémií

---

### 4. Lenka (paušální daň)
**Typ:** `pausalni`

**Parametry:**
```json
{
  "rate": 1500000,     // Max obrat za rok (1.5M Kč)
  "tax": 8716          // Pevná daň měsíčně (8,716 Kč)
}
```

**Výpočet:**
```
Max měsíční obrat = 1,500,000 / 12 = 125,000 Kč
Daň měsíčně = 8,716 Kč
Max faktura = 125,000 - 8,716 = 116,284 Kč

Výplata = hodiny × sazba výplaty (150 Kč)
Faktura = min(hodiny × 250 Kč, 116,284 Kč)
```

**Příklad:**
```
Lenka odpracovala: 500 hodin

Výplata: 500 × 150 = 75,000 Kč
Vypočtená faktura: 500 × 250 = 125,000 Kč
Skutečná faktura: min(125,000, 116,284) = 116,284 Kč
```

---

### 5. Olha Shkudor (SWIFT)
**Typ:** `swift`

**Speciální parametry:**
```json
{
  "iban": "UA913052990000026207520148665",
  "swift": "PBANUA2XXXX",
  "bankName": "JSC CB PRIVATBANK",
  "bankAddress": "1D HRUSHEVSKOHO STR., KYIV, 01001, UKRAINE",
  "beneficiary": "Olha Shkudor",
  "fees": "OUR"
}
```

**Výpočet:**
```
Výplata = hodiny × sazba výplaty (150 Kč)
Faktura = hodiny × sazba fakturace (250 Kč)
```

**Typ platby:**
- Mezinárodní převod přes SWIFT
- **Poplatky: OUR** (odesílatel hradí všechny poplatky)
- Namísto QR kódu se zobrazí tlačítko "Kopírovat SWIFT údaje"

---

## 💳 Generování plateb

### QR Kódy (domácí platby)
**Formát:** SPAYD (Czech Payment Standard)

**Struktura:**
```
SPD*1.0*
ACC:CZ{kódBanky}+{čísloÚčtu}*
AM:{částka}*
CC:CZK*
X-VS:{variabilníSymbol}*
MSG:{zpráva}
```

**Variabilní symbol:**
```
VS = rok × 100 + měsíc
Příklad: Listopad 2025 → 202511
```

**Zpráva:**
```
Výplata {jméno} {měsíc}/{rok}
Příklad: "Výplata Tomáš Nevečný 11/2025"
```

### SWIFT Platby (mezinárodní)
**Zobrazení:**
- IBAN
- SWIFT/BIC kód
- Název banky
- Adresa banky
- Jméno příjemce
- Typ poplatků: OUR

**Funkce:**
- Tlačítko "📋 Kopírovat údaje" → zkopíruje všechny údaje do schránky

---

## 📊 Statistiky

### Zobrazované metriky

**Celkem hodin:**
```
Součet hodin všech standardních zaměstnanců
(Marek a Radek se nepočítají)
```

**Výplaty celkem:**
```
Součet výplat všech zaměstnanců
(včetně bonusů Marka a Radka)
```

**Fakturace celkem:**
```
Součet fakturace všech zaměstnanců
(Marek a Radek nemají fakturu)
```

**Zisk:**
```
Zisk = Fakturace celkem - Výplaty celkem
Marže = (Zisk / Fakturace celkem) × 100%
```

**Průměry:**
```
Průměr hodin = Celkem hodin / Počet standardních zaměstnanců
Průměr výplata = Výplaty celkem / Všichni zaměstnanci
```

---

## 📅 Správa období

### Ukládání dat
- **Aktuální období:** Uloženo v `/www/data/psa-employees.json`
- **Historie:** Posledních 5 měsíců uloženo v `periods` objektu
- **Formát klíče:** `YYYY-MM` (např. `2025-11`)

### Struktura JSON
```json
{
  "config": {
    "salaryRate": 150,
    "invoiceRate": 250
  },
  "employees": [...],
  "periods": {
    "2025-11": {
      "employees": [...],
      "lastModified": "2025-11-04T10:30:00Z"
    },
    "2025-10": { ... }
  }
}
```

### API Operace

**Načtení dat pro období:**
```
GET /api/psa_api.php?period=2025-11
```

**Uložení aktuálního období:**
```
POST /api/psa_api.php
Body: { "currentPeriod": "2025-11", "employees": [...] }
```

**Automatické čištění:**
- Při uložení se zachová pouze posledních 5 období
- Starší období se automaticky mažou

---

## 🎨 Design System (WGS)

### Barvy
```css
--c-black: #1a1a1a      /* Top bar, buttons, text */
--c-white: #ffffff      /* Backgrounds, cards */
--c-grey: #666666       /* Secondary text */
--c-light-grey: #999999 /* Disabled, hints */
--c-bg: #f5f5f5         /* Page background */
--c-border: #e0e0e0     /* Borders, dividers */
--c-success: #2d5016    /* Success states */
--c-error: #8b0000      /* Error states */
--c-warning: #b8860b    /* Warning states */
```

### Typografie
- **Font:** Poppins (všechny elementy)
- **Nadpisy:** 700 weight, uppercase, letter-spacing 0.05em
- **Text:** 400-600 weight

### Komponenty
- **Inputy:** 1px border, 0px radius, clean focus
- **Buttony:** Black background, white text, uppercase
- **Cards:** White background, 1px grey border
- **Tabulka:** Grey header, white rows, 1px borders

### Responzivita
- **Mobile:** Single column, stacked elements
- **Tablet:** 2-column grid
- **Desktop:** 3-column grid, full table

---

## 🔧 Technické detaily

### Soubory
```
www/public/psa-kalkulator.html          (7.4 KB)
www/public/assets/css/psa-kalkulator.css  (11.4 KB)
www/public/assets/js/psa-kalkulator.js    (25 KB)
www/api/psa_api.php                       (4.2 KB)
www/data/psa-employees.json               (5 KB)
www/data/.htaccess                        (deny all)
```

### API Endpointy

**GET /api/psa_api.php**
- Načte aktuální data
- Parametr: `?period=YYYY-MM` (volitelný)

**POST /api/psa_api.php**
- Uloží data
- Body: `{ "employees": [...], "currentPeriod": "YYYY-MM" }`

### Offline režim
- Data ukládána do `localStorage`
- Automatické synchronizace při připojení
- Fallback při výpadku API

---

## ✅ Testovací scénáře

### Test 1: Standardní zaměstnanec
```
Vstup: Tomáš, 80 hodin
Očekávaný výsledek:
  Výplata: 80 × 150 = 12,000 Kč
  Faktura: 80 × 250 = 20,000 Kč
```

### Test 2: Marek (bonus)
```
Vstup: Ostatní celkem 1000 hodin
Očekávaný výsledek:
  Výplata: 1000 × 20 = 20,000 Kč
  Faktura: 0 Kč
```

### Test 3: Radek (bonus + prémie)
```
Vstup:
  Ostatní celkem: 1000 hodin
  Ženy celkem: 500 hodin
Očekávaný výsledek:
  Základní: 1000 × 20 = 20,000 Kč
  Prémie: 500 × 150 × 0.10 = 7,500 Kč
  Celkem: 27,500 Kč
  QR kód: 27,500 Kč (skrytě)
  Tabulka: 20,000 Kč (zobrazeno)
```

### Test 4: Lenka (paušál)
```
Vstup: 500 hodin
Očekávaný výsledek:
  Výplata: 500 × 150 = 75,000 Kč
  Vypočtená faktura: 500 × 250 = 125,000 Kč
  Skutečná faktura: min(125,000, 116,284) = 116,284 Kč
```

### Test 5: Olha (SWIFT)
```
Vstup: 185 hodin
Očekávaný výsledek:
  Výplata: 185 × 150 = 27,750 Kč
  Faktura: 185 × 250 = 46,250 Kč
  Platba: SWIFT + IBAN zobrazení
  Poplatky: OUR
```

---

## 📝 Changelog

### Verze 1.1 (2025-11-04)
- ✅ Oprava Lenka: daň 8,716 Kč (bylo 6,208 Kč)
- ✅ Přidání period-based storage (poslední 5 měsíců)
- ✅ API podpora pro načítání historických dat
- ✅ Automatické načítání dat při změně období
- ✅ Sjednocený WGS design (black/white/grey)
- ✅ Bezpečné úložiště dat (.htaccess ochrana)

### Verze 1.0 (2025-11-04)
- První implementace
- Všechny typy zaměstnanců
- QR kódy + SWIFT platby
- Export do CSV
- Print support

---

## 🚀 Použití

### Přístup
```
https://your-domain.com/psa-kalkulator.html
```
**Bez přihlášení** - stránka je veřejně přístupná!

### Workflow
1. Vybrat období (měsíc/rok)
2. Zadat hodiny pro každého zaměstnance
3. Kliknout **"💾 Uložit"**
4. Kliknout **"📱 Generovat QR platby"**
5. Stáhnout QR kódy nebo zkopírovat SWIFT údaje

### Export
- **CSV:** Export do Excelu
- **Print:** Tisková verze reportu
- **JSON:** Záloha dat (import/export)

---

*Dokument vytvořen: 2025-11-04*
*WGS - White Glove Service*
