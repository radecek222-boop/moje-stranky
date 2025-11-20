# =====================================================
# KOMPLETNÍ ANALÝZA: PDF → SQL Mapování
# =====================================================

## 1. CO UŽIVATEL VYPLNIL RUČNĚ (podle jeho popisu):
```
cislo: NCE25-00002444-39/CZ785-2025
jmeno: Petr Kmoch
ulice: Na Blatech 396
mesto: Osnice
psc: 25242
model: C157 Intenso; LE02 Orbitale; Matrace
```

## 2. CO JE V NATUZZI PDF (RAW text z test_pdf_extrakce.php):
```
Čislo reklamace:  NCE25-00002444-39  NCE25-00002444-39/CZ785-2025  12.11.2025 Datum podání:  Číslo objednávky:  Číslo faktury:  Datum vyhotovení:  25250206  12.11.2025  0  Jméno a příjmení:  Česko Stát:  25242 PSČ:  Osnice Město:  Na Blatech 396 Adresa:  Jméno společnosti:  Petr Kmoch  Poschodí:  Rodinný dům   Panelový dům  Místo reklamace  kmochova@petrisk.cz  725 387 868 Telefon:  Česko Stát:  25242  Email:  Osnice Město:  Na Blatech 396 Adresa:  Jméno společnosti:  Petr Kmoch Jméno a příjmení:  PSČ:  Zákazník  Vyjádření prodávajícího: reklamace bude vyřešena do 30 dní od obhlídky servisního technika, který určí způsob odstránění závady reklamovaného zboží  Závada:   Tak odstáté polštáře, že se na posteli nedá spát. Prosím o rychlé řešení. Děkuji a fotky přikládám. Na webových stránkách nic takového není.  Model:   C157 Intenso; LE02 Orbitale; Matrace  Složení:   450 1,5 sed Ľ s područkou a elektr. výsuvem (1); 338 1,5 sed BP s výsuvem eletickým (1); 011 Roh (1); 291 1,5 sed BP (1); 274 1,5 sed P s područkou (1); 830 Battery Bank " LIB " (2); C04 posteľ s úložným priestorom, rošt 193 x 200 cm (1); Matrac Capri 193x200x25 cm tvrdší (1)  Látka:   TG 20JJ Light Beige; INÉ; 70.0077.02 Rose  Nohy:  Doplňky:  Reklamované zboží  Kategorie:  Reklamační list
```

## 3. SQL SLOUPCE (tabulka wgs_reklamace):
```sql
cislo VARCHAR(100)          -- Číslo objednávky/faktury
datum_prodeje DATE          -- Datum prodeje/nákupu
datum_reklamace DATE        -- Datum podání reklamace
jmeno VARCHAR(255)          -- Jméno a příjmení zákazníka
email VARCHAR(255)          -- Email zákazníka
telefon VARCHAR(50)         -- Telefonní číslo
ulice VARCHAR(255)          -- Ulice a číslo popisné
mesto VARCHAR(255)          -- Město
psc VARCHAR(20)             -- PSČ
model VARCHAR(255)          -- Model výrobku
provedeni VARCHAR(255)      -- Provedení (barva, materiál)
barva VARCHAR(100)          -- Barva výrobku
popis_problemu TEXT         -- Popis problému
doplnujici_info TEXT        -- Doplňující informace
```

## 4. MAPOVÁNÍ PDF → SQL:

### A) Číslo reklamace
**Z PDF:**
```
Čislo reklamace:  NCE25-00002444-39  NCE25-00002444-39/CZ785-2025
```
**Použít:** `NCE25-00002444-39/CZ785-2025` (druhý výskyt, s lomítkem)
**→ SQL:** `cislo`
**Pattern:** `/Čislo reklamace:\s+NCE25-\d+-\d+\s+([A-Z0-9\-\/]+)/ui`

### B) Datum prodeje
**Z PDF:**
```
Datum vyhotovení:  25250206  12.11.2025
```
**Použít:** `12.11.2025`
**→ SQL:** `datum_prodeje`
**Pattern:** `/Datum vyhotovení:\s+\d+\s+(\d{1,2}\.\d{1,2}\.\d{4})/ui`

### C) Datum reklamace
**Z PDF:**
```
12.11.2025 Datum podání:
```
**Použít:** `12.11.2025` (před "Datum podání:")
**→ SQL:** `datum_reklamace`
**Pattern:** `/(\d{1,2}\.\d{1,2}\.\d{4})\s+Datum podání:/ui`

### D) Jméno
**Z PDF (2x v textu!):**
```
1. Jméno a příjmení:  Česko Stát...  (PRVNÍ = špatně, pokračuje dál)
2. Petr Kmoch  Poschodí:  (po "Jméno společnosti:")
```
**Použít:** Text mezi "Jméno společnosti:" a "Poschodí:"
**→ SQL:** `jmeno`
**Pattern:** `/Jméno společnosti:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)\s+Poschodí:/ui`

### E) Email
**Z PDF:**
```
kmochova@petrisk.cz  725 387 868 Telefon:
```
**→ SQL:** `email`
**Pattern:** `/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})\s+[\d\s]+Telefon:/ui`

### F) Telefon
**Z PDF:**
```
725 387 868 Telefon:  Česko
```
**Použít:** `725 387 868` (před "Telefon:")
**→ SQL:** `telefon`
**Pattern:** `/([\d\s]+)\s+Telefon:/ui`

### G) Ulice
**Z PDF (2x v textu!):**
```
1. Na Blatech 396 Adresa:  Jméno společnosti:  (PRVNÍ výskyt)
2. Na Blatech 396 Adresa:  Jméno společnosti:  Petr Kmoch  (DRUHÝ výskyt - správný)
```
**Použít:** Druhý výskyt (po "Místo reklamace")
**→ SQL:** `ulice`
**Pattern:** `/Místo reklamace.*?Adresa:\s+Jméno společnosti:\s+Petr Kmoch.*?Adresa:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][^\s]+\s+[^\s]+\s+\d+)/uis`

Nebo jednodušeji: **Hledat "Adresa:" následované "Jméno společnosti:" následované jménem**
**Pattern:** `/Adresa:\s+Jméno společnosti:\s+Petr Kmoch.*?Město:\s+Osnice.*?Adresa:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][\w\s]+\d+)/uis`

**NEBO NEJJEDNODUŠEJI:** Najít `Na Blatech 396` před `Adresa: Jméno společnosti: Petr Kmoch`
**Pattern:** `/([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][\w\s]+\d+)\s+Adresa:\s+Jméno společnosti:\s+Petr Kmoch/ui`

### H) Město
**Z PDF:**
```
Osnice Město:  Na Blatech 396
```
**Použít:** `Osnice` (před "Město:")
**→ SQL:** `mesto`
**Pattern:** `/([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)\s+Město:/ui`

### I) PSČ
**Z PDF (3x v textu!):**
```
1. 25242 PSČ:  Osnice (PRVNÍ - dobré)
2. 25242  Email: (DRUHÝ)
3. PSČ: (TŘETÍ - prázdné)
```
**Použít:** První výskyt
**→ SQL:** `psc`
**Pattern:** `/(\d{5})\s+PSČ:/ui`

### J) Model
**Z PDF:**
```
Model:   C157 Intenso; LE02 Orbitale; Matrace  Složení:
```
**→ SQL:** `model`
**Pattern:** `/Model:\s+([^\n]+?)\s+Složení:/ui`

### K) Látka (Provedení + Barva)
**Z PDF:**
```
Látka:   TG 20JJ Light Beige; INÉ; 70.0077.02 Rose  Nohy:
```
**→ SQL:** `provedeni` + `barva` (STEJNÁ hodnota do obou!)
**Pattern:** `/Látka:\s+([^\n]+?)\s+Nohy:/ui`

### L) Závada (Popis problému)
**Z PDF:**
```
Závada:   Tak odstáté polštáře, že se na posteli nedá spát. Prosím o rychlé řešení. Děkuji a fotky přikládám. Na webových stránkách nic takového není.  Model:
```
**→ SQL:** `popis_problemu`
**Pattern:** `/Závada:\s+([^\n]+?)\s+Model:/ui`

## 5. FINÁLNÍ REGEX PATTERNS PRO DATABÁZI:

```json
{
  "cislo_reklamace": "/Čislo reklamace:\\s+NCE25-\\d+-\\d+\\s+([A-Z0-9\\-\\/]+)/ui",
  "datum_vyhotoveni": "/Datum vyhotovení:\\s+\\d+\\s+(\\d{1,2}\\.\\d{1,2}\\.\\d{4})/ui",
  "datum_podani": "/(\\d{1,2}\\.\\d{1,2}\\.\\d{4})\\s+Datum podání:/ui",
  "jmeno": "/Jméno společnosti:\\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)\\s+Poschodí:/ui",
  "email": "/([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,})\\s+[\\d\\s]+Telefon:/ui",
  "telefon": "/([\\d\\s]+)\\s+Telefon:/ui",
  "ulice": "/([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][\\w\\s]+\\d+)\\s+Adresa:\\s+Jméno společnosti:\\s+Petr Kmoch/ui",
  "mesto": "/([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)\\s+Město:/ui",
  "psc": "/(\\d{5})\\s+PSČ:/ui",
  "model": "/Model:\\s+([^\\n]+?)\\s+Složení:/ui",
  "latka": "/Látka:\\s+([^\\n]+?)\\s+Nohy:/ui",
  "latka_barva": "/Látka:\\s+([^\\n]+?)\\s+Nohy:/ui",
  "zavada": "/Závada:\\s+([^\\n]+?)\\s+Model:/ui"
}
```

## 6. POLE MAPPING (stejné jako dříve):

```json
{
  "cislo_reklamace": "cislo",
  "datum_vyhotoveni": "datum_prodeje",
  "datum_podani": "datum_reklamace",
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
