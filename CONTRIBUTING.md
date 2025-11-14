# Pravidla přispívání do projektu

## 🇨🇿 POVINNÉ POUŽITÍ ČEŠTINY

**KRITICKÉ:** Tento projekt používá **VÝHRADNĚ ČESKÝ JAZYK** pro:

- ✅ Názvy proměnných
- ✅ Názvy funkcí
- ✅ Komentáře v kódu
- ✅ Commit messages
- ✅ Dokumentaci
- ✅ Error messages
- ✅ UI texty

### Proč čeština?

1. **Konzistence:** Celý tým mluví česky
2. **Business logika:** Doménové pojmy jsou české (reklamace, termin, návštěva)
3. **Databáze:** Názvy sloupců jsou české (`jmeno`, `telefon`, `adresa`)
4. **UX:** Aplikace je pro české uživatele

---

## ✅ SPRÁVNÉ PŘÍKLADY

### JavaScript

```javascript
// ✅ SPRÁVNĚ
async function ulozReklamaci(data) {
  // Validace vstupních dat
  if (!data.jmeno || !data.telefon) {
    throw new Error('Chybí povinné údaje');
  }

  // Odeslat na server
  const odpoved = await fetch('/app/controllers/save.php', {
    method: 'POST',
    body: JSON.stringify(data)
  });

  return odpoved.json();
}

// ✅ SPRÁVNĚ - české proměnné
const vybranyDatum = '15.11.2025';
const vybranyTermin = '14:30';
const stavReklamace = 'DOMLUVENÁ';
```

### PHP

```php
// ✅ SPRÁVNĚ
function zpracujReklamaci($data) {
    // Sanitizace vstupních dat
    $jmeno = sanitizeInput($data['jmeno']);
    $telefon = sanitizeInput($data['telefon']);
    $email = trim($data['email']);

    // Validace emailu
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Neplatný formát emailu');
    }

    // Uložení do databáze
    return ulozDoDb($jmeno, $telefon, $email);
}
```

### CSS

```css
/* ✅ SPRÁVNĚ - české třídy */
.kalendarni-mrizka {
  display: grid;
  grid-template-columns: repeat(7, 1fr);
}

.vybrane-datum {
  background: #0066cc;
  color: white;
}

.casovy-slot {
  padding: 0.5rem;
  cursor: pointer;
}
```

---

## ❌ ŠPATNÉ PŘÍKLADY

### JavaScript

```javascript
// ❌ ŠPATNĚ - anglické názvy
async function saveComplaint(data) {
  // Validate input data
  if (!data.name || !data.phone) {
    throw new Error('Missing required fields');
  }

  // Send to server
  const response = await fetch('/app/controllers/save.php', {
    method: 'POST',
    body: JSON.stringify(data)
  });

  return response.json();
}

// ❌ ŠPATNĚ - anglické proměnné
const selectedDate = '15.11.2025';
const selectedTime = '14:30';
const complaintStatus = 'SCHEDULED';
```

### PHP

```php
// ❌ ŠPATNĚ - anglické názvy
function processComplaint($data) {
    // Sanitize input
    $name = sanitizeInput($data['name']);
    $phone = sanitizeInput($data['phone']);
    $email = trim($data['email']);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }

    // Save to database
    return saveToDb($name, $phone, $email);
}
```

---

## 🗄️ DATABÁZE - SPECIÁLNÍ PRAVIDLA

### ENUM hodnoty

Databáze používá **ANGLICKÉ lowercase** hodnoty pro ENUM:

```sql
stav ENUM('wait', 'open', 'done')  -- anglicky!
fakturace_firma ENUM('cz', 'sk')    -- lowercase!
```

### Mapping v kódu

Frontend posílá **ČESKÉ uppercase** hodnoty:

```javascript
// Frontend posílá
formData.append('stav', 'DOMLUVENÁ');  // česky uppercase
formData.append('fakturace_firma', 'CZ');  // uppercase
```

Backend automaticky mapuje:

```php
// save.php automaticky konvertuje
'DOMLUVENÁ' → 'open'   // do databáze
'ČEKÁ' → 'wait'
'HOTOVO' → 'done'
'CZ' → 'cz'
'SK' → 'sk'
```

### Proč tento systém?

1. **UX:** Uživatel vidí české texty
2. **DB:** ENUM je validováno na DB úrovni
3. **Kompatibilita:** Funguje s existující databází

---

## 📝 COMMIT MESSAGES

### ✅ SPRÁVNĚ - česky

```bash
git commit -m "FIX: Oprava uložení termínu s českými znaky"
git commit -m "FEATURE: Přidána fakturace CZ/SK"
git commit -m "PERFORMANCE: Kalendář 27s → okamžitě"
```

### ❌ ŠPATNĚ - anglicky

```bash
git commit -m "FIX: Fixed appointment save with Czech characters"
git commit -m "FEATURE: Added CZ/SK invoicing"
git commit -m "PERFORMANCE: Calendar 27s → instant"
```

---

## 🔄 GIT WORKFLOW

### 1. Vytvoř branch

```bash
git checkout -b claude/work-in-progress-XXXXXX
```

**Branch naming:** Vždy `claude/work-in-progress-*` + session ID

### 2. Proveď změny

- Piš kód **ČESKY**
- Komentuj **ČESKY**
- Testuj lokálně

### 3. Commit

```bash
git add -A
git commit -m "FIX: Popis opravy česky"
```

### 4. Push

```bash
git push -u origin claude/work-in-progress-XXXXXX
```

### 5. Merge přes GitHub

- Použij GitHub UI pro merge
- Pull Request title **ČESKY**
- Popis změn **ČESKY**

---

## 🧪 TESTOVÁNÍ

### Před commitem zkontroluj:

1. ✅ Všechny funkce mají **ČESKÉ názvy**
2. ✅ Všechny proměnné mají **ČESKÉ názvy**
3. ✅ Všechny komentáře jsou **ČESKY**
4. ✅ Error messages jsou **ČESKY**
5. ✅ Konzole neobsahuje errory
6. ✅ Kód funguje v produkčním prostředí

### Checklist pro Pull Request:

```markdown
- [ ] Veškerý kód je v češtině
- [ ] Komentáře jsou v češtině
- [ ] Commit messages jsou v češtině
- [ ] Otestováno lokálně
- [ ] Žádné console.error v produkci
- [ ] CSRF tokeny správně nastaveny
- [ ] SQL injection prevention (PDO prepared statements)
- [ ] XSS protection (htmlspecialchars)
```

---

## 🚫 CO NEDĚLAT

### ❌ Nepoužívej anglické názvy

```javascript
// ❌ ŠPATNĚ
function saveData() { }
const userName = 'Jan';
let selectedItem = null;
```

### ❌ Nemíchej češtinu a angličtinu

```javascript
// ❌ ŠPATNĚ - míchání jazyků
function ulozUser(userData) {
  const jmeno = userData.name;  // míchání!
}
```

### ❌ Nepoužívej anglické komentáře

```javascript
// ❌ ŠPATNĚ
// Save appointment to database
function ulozTermin() { }
```

---

## 📚 DOKUMENTACE

### README soubory

Všechny README soubory **MUSÍ být v češtině**:

```markdown
# Název modulu

## Popis

Tento modul slouží k...

## Použití

\```javascript
const vysledek = zpracujData(vstupniData);
\```
```

### Inline dokumentace

```javascript
/**
 * Uloží termín návštěvy k zákazníkovi
 *
 * @param {string} datum - Datum ve formátu DD.MM.RRRR
 * @param {string} cas - Čas ve formátu HH:MM
 * @param {number} zakaznikId - ID zákazníka
 * @returns {Promise<Object>} Výsledek uložení
 */
async function ulozTermin(datum, cas, zakaznikId) {
  // ...
}
```

---

## 🎯 PŘÍKLADY Z PROJEKTU

### Seznam.js - správně implementováno

```javascript
// ✅ SPRÁVNĚ
async function nactiVsechnyReklamace(status = 'all') {
  const odpoved = await fetch(`/app/controllers/load.php?status=${status}`);
  const data = await odpoved.json();

  WGS_DATA_CACHE = data.data;
  vykreslitObjednavky(data.data);
}

function zobrazitDetail(id) {
  const zaznam = WGS_DATA_CACHE.find(x => x.id == id);
  if (!zaznam) {
    alert('Záznam nenalezen');
    return;
  }

  CURRENT_RECORD = zaznam;
  ModalManager.show(vytvorDetailObsah(zaznam));
}
```

### Save.php - správně implementováno

```php
// ✅ SPRÁVNĚ
function handleUpdate(PDO $pdo, array $input): array {
    // Mapping českých hodnot na anglické pro DB
    $stavMapping = [
        'ČEKÁ' => 'wait',
        'DOMLUVENÁ' => 'open',
        'HOTOVO' => 'done'
    ];

    $stavValue = $input['stav'];
    if (isset($stavMapping[$stavValue])) {
        $updateData['stav'] = $stavMapping[$stavValue];
    }

    return ['status' => 'success'];
}
```

---

## 📞 Otázky?

Pokud si nejsi jistý:

1. Podívej se do existujícího kódu (seznam.js, save.php)
2. Zkontroluj README.md
3. Konzultuj s hlavním vývojářem

**Pamatuj:** Když používáš češtinu konzistentně, kód je čitelnější a srozumitelnější pro celý tým!

---

© 2025 White Glove Service - Veškerý kód v češtině
