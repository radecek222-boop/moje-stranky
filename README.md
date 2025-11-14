# WGS Service - White Glove Service

## 📋 O projektu

Webová aplikace pro správu reklamací a servisních zakázek nábytku Natuzzi.

**DŮLEŽITÉ:** Tento projekt používá **ČESKÝ JAZYK** pro veškerý kód, komentáře a dokumentaci.

---

## 🇨🇿 PRAVIDLA JAZYKA PROJEKTU

### ✅ SPRÁVNĚ - Používej ČEŠTINU:

```javascript
// ✅ SPRÁVNĚ - české proměnné a komentáře
function ulozTermin(datum, cas) {
  // Uložit termín návštěvy k zákazníkovi
  const formData = new FormData();
  formData.append('termin', datum);
  formData.append('cas_navstevy', cas);
  formData.append('stav', 'DOMLUVENÁ');
}
```

```php
// ✅ SPRÁVNĚ - české komentáře a názvy
function zpracujReklamaci($data) {
    // Validace vstupních dat
    $jmeno = sanitizeInput($data['jmeno']);
    $telefon = sanitizeInput($data['telefon']);
    // ...
}
```

### ❌ ŠPATNĚ - Nepoužívej ANGLIČTINU:

```javascript
// ❌ ŠPATNĚ - anglické názvy funkcí a proměnných
function saveAppointment(date, time) {
  // Save customer appointment
  const formData = new FormData();
  formData.append('appointment', date);
}
```

---

## 📐 DATABÁZE

### ENUM Hodnoty

Databáze používá **ANGLICKÉ lowercase** hodnoty pro ENUM sloupce:

```sql
-- Stav reklamace
stav ENUM('wait', 'open', 'done')

-- Fakturace
fakturace_firma ENUM('cz', 'sk')

-- Typ reklamace
typ ENUM('reklamace', 'servis')
```

### Mapping v kódu

**Frontend** (JavaScript) používá **ČESKÉ uppercase** hodnoty:
- `'ČEKÁ'`, `'DOMLUVENÁ'`, `'HOTOVO'`
- `'CZ'`, `'SK'`

**Backend** (PHP `save.php`) automaticky mapuje české hodnoty na anglické:

```php
$stavMapping = [
    'ČEKÁ' => 'wait',
    'DOMLUVENÁ' => 'open',
    'HOTOVO' => 'done'
];
```

---

## 🚀 Technologie

- **Frontend:** Vanilla JavaScript (ES6+), HTML5, CSS3
- **Backend:** PHP 8.4+
- **Databáze:** MariaDB 10.11+
- **Web server:** Nginx 1.26+
- **Deployment:** GitHub Actions + SFTP

---

## 📁 Struktura projektu

```
moje-stranky/
├── api/                    # API endpointy
├── app/
│   └── controllers/        # PHP kontrolery
├── assets/
│   ├── css/               # Styly
│   └── js/                # JavaScript soubory
├── includes/              # PHP include soubory
├── logs/                  # Logy aplikace
├── scripts/               # Skripty (backup, deploy)
├── docs/                  # Dokumentace
└── .github/
    └── workflows/         # GitHub Actions
```

---

## 🔧 Vývoj

### Lokální prostředí

```bash
# 1. Klonovat repozitář
git clone https://github.com/radecek222-boop/moje-stranky.git

# 2. Nastavit databázi
mysql -u root -p < database_schema.sql

# 3. Nakonfigurovat config/config.php
cp config/config.example.php config/config.php
# Upravit DB credentials

# 4. Spustit lokální server
php -S localhost:8000
```

### Git workflow

1. **Vytvořit branch:** `claude/work-in-progress-XXXXXX`
2. **Commitovat změny:** Commit messages v češtině
3. **Push:** `git push -u origin claude/work-in-progress-XXXXXX`
4. **Mergovat:** Přes GitHub UI (Pull Request)

---

## 📝 Konvence kódu

### JavaScript

```javascript
// ✅ Funkce v češtině
async function nactiReklamace() {
  const odpoved = await fetch('/api/reklamace');
  const data = await odpoved.json();
  return data;
}

// ✅ Proměnné v češtině
let aktivniFiltr = 'all';
const vybranyDatum = '15.11.2025';
```

### PHP

```php
// ✅ Funkce a proměnné v češtině
function zpracujFormular($data) {
    $jmeno = $data['jmeno'];
    $email = filter_var($data['email'], FILTER_VALIDATE_EMAIL);

    if (!$email) {
        throw new Exception('Neplatný email');
    }

    return ulozDoDb($jmeno, $email);
}
```

### SQL

```sql
-- ✅ České názvy sloupců
CREATE TABLE wgs_reklamace (
    id INT AUTO_INCREMENT PRIMARY KEY,
    jmeno VARCHAR(255) NOT NULL,
    telefon VARCHAR(50),
    email VARCHAR(255),
    popis_problemu TEXT,
    stav ENUM('wait', 'open', 'done') DEFAULT 'wait'
);
```

---

## 🔒 Bezpečnost

- **CSRF Protection:** Všechny POST requesty vyžadují CSRF token
- **SQL Injection:** Používáme PDO prepared statements
- **XSS Protection:** `htmlspecialchars()` pro veškerý output
- **Session Security:** Secure cookies, HTTPOnly, SameSite=Strict

---

## 📦 Deployment

Automatický deploy přes GitHub Actions při merge do main:

```yaml
# .github/workflows/deploy.yml
on:
  push:
    branches: [ main ]
  workflow_dispatch:
```

Deploy proces:
1. Checkout kódu
2. SFTP upload na produkci
3. Cleanup starých logů
4. Notifikace o úspěchu/selhání

---

## 🐛 Reporting bugů

Při reportování bugu zahrň:

1. **Popis problému** (česky)
2. **Kroky k reprodukci**
3. **Očekávané chování**
4. **Screenshot/Console log**
5. **Prohlížeč a verze**

---

## 📞 Kontakt

- **Admin:** Radek Zikmund
- **Email:** radek@wgs-service.cz
- **Web:** https://www.wgs-service.cz

---

## 📄 Licence

Proprietární software - všechna práva vyhrazena.

© 2025 White Glove Service
