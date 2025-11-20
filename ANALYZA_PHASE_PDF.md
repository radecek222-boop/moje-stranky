# ANALÝZA: PHASE PROTOKOL (Slovenština)

## 1. DŮLEŽITÉ INFO

**PDF Soubor:** `uploads/PHASE PROTOKOL.pdf`
**Jazyk:** Slovenština
**Zákazník:** Michaela Vachutová

---

## 2. DATA Z PDF (VIZUÁLNĚ):

### ZÁKLADNÍ ÚDAJE (horní tabulka):
```
Číslo reklamácie: ZL3-00003001-49/CZ371-2025
Dátum podania: 19.05.2025
Číslo objednávky: ZL3-00003001-49
Číslo faktúry: 25030017
Dátum vyhotovenia: 21.02.2025
```

### ZÁKAZNÍK (levý sloupec):
```
Meno a priezvisko: Michaela Vachutová
Meno spoločnosti: (prázdné)
Adresa: Havlíčkovo nábřeží 5357
Mesto: Zlín
PSČ: 76001
Krajina: Česko
Telefón: 731 663 780
Email: vachutova.m@gmail.com
```

### MIESTO REKLAMÁCIE (pravý sloupec):
```
Meno a priezvisko: Michaela Vachutová
Meno spoločnosti: (prázdné)
Adresa: Havlíčkovo nábřeží 5357
Mesto: Zlín
PSČ: 76001
Krajina: Česko
☑ Panelák
Poschodie: 1
```

### REKLAMOVANÝ TOVAR:
```
Model: C243 kreslo Until
Zloženie: F45 kreslo Queen (1)
Látka: DENVER A0BS koža
Kategória: kategória UNTIL
Nohy: (prázdné)
Doplnky: (prázdné)
Závada: Kreslo UNTIL sa neotáča o 270 stupňov ako by malo
```

---

## 3. MAPOVÁNÍ PDF → SQL TABULKA

| PDF Pole (slovensky) | Hodnota z PDF | → SQL Sloupec | HTML Input ID |
|---------------------|---------------|---------------|---------------|
| Číslo reklamácie | ZL3-00003001-49/CZ371-2025 | `cislo` | cislo |
| Dátum vyhotovenia | 21.02.2025 | `datum_prodeje` | datum_prodeje |
| Dátum podania | 19.05.2025 | `datum_reklamace` | datum_reklamace |
| Meno a priezvisko | Michaela Vachutová | `jmeno` | jmeno |
| Email | vachutova.m@gmail.com | `email` | email |
| Telefón | 731 663 780 | `telefon` | telefon |
| **Adresa** | **Havlíčkovo nábřeží 5357** | **`ulice`** | **ulice** |
| Mesto | Zlín | `mesto` | mesto |
| PSČ | 76001 | `psc` | psc |
| Model | C243 kreslo Until | `model` | model |
| Látka | DENVER A0BS koža | `provedeni` | provedeni |
| Látka | DENVER A0BS koža | `barva` | barva |
| Závada | Kreslo UNTIL sa neotáča... | `popis_problemu` | popis_problemu |

---

## 4. REGEX PATTERNS PRO PHASE

**DŮLEŽITÉ:** Stejně jako u NATUZZI - text z PDF.js je na JEDNOM ŘÁDKU s mezerami!

### Patterns (slovensky):

```json
{
  "cislo_reklamace": "/Číslo reklamácie:\\s+([A-Z0-9\\-\\/]+)/ui",

  "datum_vyhotovenia": "/Dátum vyhotovenia:\\s+(\\d{1,2}\\.\\d{1,2}\\.\\d{4})/ui",

  "datum_podania": "/Dátum podania:\\s+(\\d{1,2}\\.\\d{1,2}\\.\\d{4})/ui",

  "jmeno": "/Meno a priezvisko:\\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)/ui",

  "email": "/Email:\\s+([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,})/ui",

  "telefon": "/Telefón:\\s+([\\d\\s]+)/ui",

  "ulice": "/Adresa:\\s+([^\\n]+?)(?:\\s+Meno|$)/ui",

  "mesto": "/Mesto:\\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)/ui",

  "psc": "/PSČ:\\s+(\\d{3}\\s?\\d{2}|\\d{5})/ui",

  "model": "/Model:\\s+([^\\n]+?)(?:\\s+Zloženie|$)/ui",

  "latka": "/Látka:\\s+([^\\n]+?)(?:\\s+Kategória|Nohy|$)/ui",

  "zavada": "/Závada:\\s+([^\\n]+?)(?:\\s+Vyjadrenie|$)/ui"
}
```

---

## 5. POLE MAPPING PRO PHASE:

```json
{
  "cislo_reklamace": "cislo",
  "datum_vyhotovenia": "datum_prodeje",
  "datum_podania": "datum_reklamace",
  "jmeno": "jmeno",
  "email": "email",
  "telefon": "telefon",
  "ulice": "ulice",
  "mesto": "mesto",
  "psc": "psc",
  "model": "model",
  "latka": "provedeni",
  "latka_barva": "barva",
  "zavada": "popis_problemu"
}
```

---

## 6. DETEKCE PHASE PROTOKOLU:

**Pattern pro detekci:**
```
/Reklamačný list.*?PHASE|pohodlie.*?phase/uis
```

Nebo hledat slovenská slova:
- "Dátum podania"
- "Meno a priezvisko"
- "Miesto reklamácie"
- "Krajina"

---

## 7. TESTOVACÍ DATA:

**Očekávané výsledky po parsování:**

```javascript
{
  cislo: "ZL3-00003001-49/CZ371-2025",
  datum_prodeje: "21.02.2025",
  datum_reklamace: "19.05.2025",
  jmeno: "Michaela Vachutová",
  email: "vachutova.m@gmail.com",
  telefon: "731 663 780",
  ulice: "Havlíčkovo nábřeží 5357",
  mesto: "Zlín",
  psc: "76001",
  model: "C243 kreslo Until",
  provedeni: "DENVER A0BS koža",
  barva: "DENVER A0BS koža",
  popis_problemu: "Kreslo UNTIL sa neotáča o 270 stupňov ako by malo"
}
```

---

© 2025 WGS Service - Analýza PHASE protokolu
