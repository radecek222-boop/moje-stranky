# WGS SERVICE - KOMPLETNÍ DOKUMENTACE

**Verze:** 1.0
**Datum:** 2025-11-21
**Projekt:** White Glove Service - Natuzzi Furniture Service Management System

---

## 📋 OBSAH

1. [Přehled projektu](#1-přehled-projektu)
2. [Začínáme](#2-začínáme)
3. [Databáze](#3-databáze)
4. [API Dokumentace](#4-api-dokumentace)
5. [Bezpečnost](#5-bezpečnost)
6. [Frontend](#6-frontend)
7. [Vývoj](#7-vývoj)
8. [Deployment](#8-deployment)
9. [Údržba](#9-údržba)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. PŘEHLED PROJEKTU

### 1.1 O projektu

WGS Service je webová aplikace pro správu servisních reklamací a zakázek luxusního nábytku značky Natuzzi. Systém umožňuje:

- ✅ Evidenci reklamací a servisních požadavků
- ✅ Správu uživatelů (admin, technik, prodejce)
- ✅ Plánování servisních návštěv s kalendářem
- ✅ Fotodokumentaci a PDF protokoly
- ✅ Statistiky a reporty
- ✅ Email notifikace
- ✅ Trojjazyčné rozhraní (CS/EN/IT)

### 1.2 Technologie

**Backend:**
- PHP 8.4+
- MariaDB 10.11+
- PDO (prepared statements)
- PHPMailer (SMTP)

**Frontend:**
- Vanilla JavaScript (ES6+)
- Leaflet.js (mapy)
- Geoapify API (geokódování)
- Poppins font (Google Fonts)

**Server:**
- Nginx 1.26+ (preferováno)
- Apache .htaccess (fallback)
- HTTPS (Let's Encrypt)

**Deployment:**
- GitHub Actions
- SFTP deploy na český hosting
- Automatické testy před deployem

### 1.3 Struktura adresářů

```
/home/user/moje-stranky/
│
├── config/                   # Konfigurace
│   ├── config.php           # Hlavní config (DB, SMTP)
│   └── database.php         # Database singleton
│
├── app/                     # Aplikační logika
│   ├── controllers/         # Business logic
│   │   ├── save.php        # Ukládání reklamací (KRITICKÝ)
│   │   ├── load.php        # Načítání dat
│   │   ├── login_controller.php
│   │   └── ...
│   └── save_photos.php     # Upload fotek
│
├── api/                     # API endpointy
│   ├── control_center_api.php  (128KB!)
│   ├── protokol_api.php
│   ├── statistiky_api.php
│   └── ...
│
├── includes/                # Sdílené utility
│   ├── csrf_helper.php     # CSRF ochrana
│   ├── rate_limiter.php    # Rate limiting
│   ├── api_response.php    # Standardní API odpovědi
│   ├── EmailQueue.php      # Email fronta
│   └── ...
│
├── assets/                  # Frontend resources
│   ├── js/                  # 36 JavaScript souborů
│   │   ├── logger.js       # MUSÍ se načíst první
│   │   ├── translations.js # Jazykový slovník
│   │   ├── language-switcher.js
│   │   ├── seznam.js       # Seznam reklamací
│   │   └── ...
│   └── css/                 # Styly
│
├── migrations/              # DB migrace
├── setup/                   # Instalační skripty
├── scripts/                 # Údržba, backup
├── tests/                   # PHPUnit testy
├── logs/                    # Aplikační logy
├── backups/                 # DB zálohy
├── uploads/                 # Nahrané soubory
│   ├── photos/             # Fotky reklamací
│   └── protokoly/          # PDF protokoly
│
├── .env                     # Environment variables (gitignored)
├── .htaccess                # Apache config
├── init.php                 # Bootstrap soubor
├── CLAUDE.md                # AI assistant guide
└── DOKUMENTACE.md           # Tento soubor
```

---

## 2. ZAČÍNÁME

### 2.1 Požadavky

**Server:**
- PHP 8.4+ s extensions: PDO, mbstring, gd, curl, zip
- MariaDB 10.11+ nebo MySQL 8.0+
- Nginx 1.26+ nebo Apache 2.4+
- SSL certifikát (Let's Encrypt)
- Min. 2GB RAM, 20GB disk

**Lokální vývoj:**
- PHP 8.4+
- Composer 2.x
- Node.js 18+ (pro build nástroje)
- Git

### 2.2 Instalace

**Krok 1: Klonování repozitáře**
```bash
git clone https://github.com/radecek222-boop/moje-stranky.git
cd moje-stranky
```

**Krok 2: Konfigurace .env**
```bash
cp .env.example .env
nano .env
```

```env
# Databáze
DB_HOST=localhost
DB_NAME=wgs_service
DB_USER=root
DB_PASS=your_password

# SMTP
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_USER=your@email.com
SMTP_PASS=your_app_password
SMTP_FROM=noreply@wgs-service.cz

# Admin
ADMIN_KEY_HASH=your_bcrypt_hash
ADMIN_EMAIL=admin@wgs-service.cz

# API klíče
GEOAPIFY_KEY=your_geoapify_key
```

**Krok 3: Import databáze**
```bash
mysql -u root -p < setup/database_schema.sql
```

**Krok 4: Nastavení oprávnění**
```bash
chmod 755 uploads logs backups
chmod 600 .env
```

**Krok 5: Composer dependencies**
```bash
composer install
```

**Krok 6: Test**
```bash
php -S localhost:8000
# Otevřít: http://localhost:8000
```

### 2.3 První přihlášení

**URL:** `https://localhost:8000/login.php`

**Admin účet:**
- Email: `admin@wgs-service.cz`
- Heslo: (viz .env - ADMIN_KEY_HASH)

---

## 3. DATABÁZE

### 3.1 Hlavní tabulky

| Tabulka | Účel | Klíčové sloupce |
|---------|------|-----------------|
| `wgs_reklamace` | Reklamace a zakázky | `reklamace_id`, `stav`, `jmeno`, `email`, `termin` |
| `wgs_users` | Uživatelské účty | `user_id`, `email`, `role`, `is_active` |
| `wgs_photos` | Fotodokumentace | `reklamace_id`, `section_name`, `file_path` |
| `wgs_documents` | PDF protokoly | `claim_id`, `document_path` |
| `wgs_notes` | Poznámky | `claim_id`, `note_text`, `author_id` |
| `wgs_email_queue` | Email fronta | `to_email`, `status`, `retry_count` |

### 3.2 ENUM hodnoty a mapping

**KRITICKÉ: Databáze používá ANGLICKÉ lowercase, frontend ČESKÉ uppercase**

**Stav reklamace:**
```php
// Frontend (JavaScript)
'ČEKÁ'      →  Database: 'wait'
'DOMLUVENÁ' →  Database: 'open'
'HOTOVO'    →  Database: 'done'

// Mapping v save.php
$stavMapping = [
    'ČEKÁ' => 'wait',
    'DOMLUVENÁ' => 'open',
    'HOTOVO' => 'done'
];
```

**Fakturace:**
```php
'CZ' → 'cz'  // Česká republika
'SK' → 'sk'  // Slovensko
```

**SQL dotazy MUSÍ používat anglické hodnoty:**
```sql
-- ✅ SPRÁVNĚ
SELECT * FROM wgs_reklamace WHERE stav = 'wait';

-- ❌ ŠPATNĚ
SELECT * FROM wgs_reklamace WHERE stav = 'ČEKÁ';  -- Nenajde nic!
```

### 3.3 Migrace

**Vytvoření migračního skriptu:**

Všechny migrační skripty se ukládají do ROOT složky (ne do `/migrations/`).

```php
<?php
/**
 * Migrace: Přidání sloupce XYZ
 * Tento skript BEZPEČNĚ přidá sloupec XYZ do tabulky wgs_reklamace
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

try {
    $pdo = getDbConnection();

    // Kontrola jestli sloupec už existuje
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'novy_sloupec'");
    if ($stmt->rowCount() > 0) {
        echo "Sloupec již existuje, migrace není potřeba.";
        exit;
    }

    // Pokud je ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        $pdo->beginTransaction();

        $pdo->exec("
            ALTER TABLE wgs_reklamace
            ADD COLUMN novy_sloupec VARCHAR(255) NULL
        ");

        $pdo->commit();
        echo "✅ Migrace úspěšně provedena!";
    } else {
        echo "<a href='?execute=1'>SPUSTIT MIGRACI</a>";
    }

} catch (Exception $e) {
    $pdo->rollBack();
    echo "❌ CHYBA: " . $e->getMessage();
}
?>
```

**Spuštění migrace:**
1. Nahrát soubor do rootu projektu
2. Otevřít v prohlížeči: `https://wgs-service.cz/nazev_migrace.php`
3. Kliknout "SPUSTIT MIGRACI"

---

## 4. API DOKUMENTACE

### 4.1 Standardní formát odpovědí

**Všechny API používají jednotný formát:**

```json
// SUCCESS
{
  "status": "success",
  "message": "Operace úspěšná",
  "data": { ... }
}

// ERROR
{
  "status": "error",
  "message": "Chybová zpráva",
  "error": {
    "code": "ERROR_CODE",
    "details": { ... }
  }
}
```

**PHP Implementation:**
```php
require_once __DIR__ . '/../includes/api_response.php';

// Success
ApiResponse::success($data, 'Reklamace uložena');

// Error
ApiResponse::error('Neplatný vstup', 400);

// Validation error
ApiResponse::validationError([
    'email' => 'Email je povinný',
    'phone' => 'Neplatné telefonní číslo'
]);

// Not found
ApiResponse::notFound('Reklamace', '123');

// Unauthorized
ApiResponse::unauthorized();

// Rate limit
ApiResponse::rateLimitExceeded(60);
```

### 4.2 Klíčové API endpointy

| Endpoint | Metoda | Auth | Popis |
|----------|--------|------|-------|
| `/api/control_center_api.php` | GET/POST | Admin | Admin control center |
| `/api/protokol_api.php` | POST | User | Servisní protokoly |
| `/api/statistiky_api.php` | GET | User | Statistiky |
| `/api/notes_api.php` | GET/POST | User | Poznámky |
| `/api/delete_reklamace.php` | POST | Admin | Mazání reklamací |
| `/api/backup_api.php` | GET/POST | Admin | DB zálohy |
| `/app/controllers/save.php` | POST | User | Ukládání reklamací |
| `/app/controllers/load.php` | GET | User | Načítání reklamací |

### 4.3 Autentizace

**Session-based authentication:**
```php
// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    ApiResponse::unauthorized();
}

// Kontrola role
if ($_SESSION['role'] !== 'admin') {
    ApiResponse::forbidden('Nedostatečná oprávnění');
}
```

**Role:**
- `admin` - Plný přístup
- `technik` - Vidí všechny reklamace, upravuje protokoly
- `prodejce` - Vidí pouze vlastní reklamace
- `user` - Základní přístup

---

## 5. BEZPEČNOST

### 5.1 CSRF Protection

**VŠECHNY POST requesty MUSÍ mít CSRF token:**

```php
// Backend - validace
require_once __DIR__ . '/../includes/csrf_helper.php';

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    ApiResponse::forbidden('Neplatný CSRF token');
}
```

```javascript
// Frontend - auto-injected přes csrf-auto-inject.js
formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
```

### 5.2 Rate Limiting

```php
require_once __DIR__ . '/../includes/rate_limiter.php';

$rateLimiter = new RateLimiter($pdo);

$result = $rateLimiter->checkLimit('login_' . $ip, 'login', [
    'max_attempts' => 5,
    'window_minutes' => 15,
    'block_minutes' => 30
]);

if (!$result['allowed']) {
    ApiResponse::rateLimitExceeded($result['retry_after']);
}
```

### 5.3 Security Headers

Nastaveno v `includes/security_headers.php`:

```php
header("Strict-Transport-Security: max-age=31536000; includeSubDomains; preload");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: SAMEORIGIN");
header("X-XSS-Protection: 1; mode=block");
header("Content-Security-Policy: default-src 'self'; script-src 'self' 'nonce-RANDOM'");
header("Referrer-Policy: strict-origin-when-cross-origin");
```

### 5.4 SQL Injection Prevention

**VŽDY používat PDO prepared statements:**

```php
// ✅ SPRÁVNĚ
$stmt = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE email = :email");
$stmt->execute(['email' => $email]);

// ❌ ŠPATNĚ - SQL injection!
$result = $pdo->query("SELECT * FROM wgs_reklamace WHERE email = '$email'");
```

### 5.5 Input Sanitization

```php
// Sanitizace textu
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Validace emailu
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

// Validace čísla
$id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
```

---

## 6. FRONTEND

### 6.1 Trojjazyčné rozhraní

**Podporované jazyky:**
- 🇨🇿 Čeština (cs) - výchozí
- 🇬🇧 Angličtina (en)
- 🇮🇹 Italština (it)

**Struktura:**

1. **translations.js** - Centrální slovník (~140 klíčů)
```javascript
window.WGS_TRANSLATIONS = {
  'loading': {
    cs: 'Načítání...',
    en: 'Loading...',
    it: 'Caricamento...'
  },
  'save': {
    cs: 'Uložit',
    en: 'Save',
    it: 'Salva'
  }
};
```

2. **language-switcher.js** - Přepínání jazyka
```javascript
// Získat překlad
const text = t('loading');  // "Načítání..." v aktuálním jazyce

// Získat překlad v konkrétním jazyce
const textIT = tLang('loading', 'it');  // "Caricamento..."
```

3. **HTML elementy** - Data atributy
```html
<button
  data-lang-cs="Uložit"
  data-lang-en="Save"
  data-lang-it="Salva">
  Uložit
</button>
```

**Persistentní volba:**
- Uloženo v `localStorage` jako `'wgs-lang'`
- Automaticky načteno při dalším otevření

### 6.2 JavaScript moduly

**Důležité pořadí načítání:**
```html
<!-- 1. VŽDY PRVNÍ -->
<script src="assets/js/logger.js" defer></script>

<!-- 2. Translations -->
<script src="assets/js/translations.js" defer></script>

<!-- 3. Language switcher -->
<script src="assets/js/language-switcher.js" defer></script>

<!-- 4. CSRF auto-inject -->
<script src="assets/js/csrf-auto-inject.js" defer></script>

<!-- 5. Aplikační JS -->
<script src="assets/js/your-app.js" defer></script>
```

### 6.3 Mapa (wgs-map.js)

```javascript
// Inicializace mapy
const map = WGSMap.init('mapContainer', {
  center: [50.08, 14.59],
  zoom: 10
});

// Přidání markeru
WGSMap.addMarker('customer', [50.08, 14.59], {
  popup: 'Zákazník'
});

// Geocoding
const data = await WGSMap.geocode('Václavské náměstí, Praha');

// Autocomplete
const results = await WGSMap.autocomplete('Pra', {type: 'city'});

// Výpočet trasy
const route = await WGSMap.calculateRoute(start, end);
WGSMap.drawRoute(coords);
```

---

## 7. VÝVOJ

### 7.1 KRITICKÉ PRAVIDLO: ČESKY!

**VŠECHEN kód MUSÍ být v ČEŠTINĚ:**

```javascript
// ✅ SPRÁVNĚ
async function ulozReklamaci(data) {
  const formular = new FormData();
  formular.append('jmeno', data.jmeno);

  try {
    const odpoved = await fetch('/api/uloz.php', {
      method: 'POST',
      body: formular
    });

    if (odpoved.ok) {
      console.log('Reklamace uložena');
    }
  } catch (chyba) {
    console.error('Chyba:', chyba);
  }
}

// ❌ ŠPATNĚ
async function saveComplaint(data) {
  const form = new FormData();
  // ...
}
```

**Proč česky?**
1. Celý codebase je česky
2. DB sloupce jsou česky (`jmeno`, `telefon`)
3. Business doména je česká (`reklamace`, `termin`)
4. Tým je český
5. Konzistence!

### 7.2 Git workflow

**Branch naming:**
```bash
claude/help-coding-task-[SESSION_ID]
claude/fix-bug-[SESSION_ID]
```

**Commit messages (česky!):**
```bash
git commit -m "FIX: Oprava validace emailu"
git commit -m "FEATURE: Přidání SK fakturace"
git commit -m "PERFORMANCE: Optimalizace načítání seznamu"
git commit -m "SECURITY: Oprava CSRF validace"
```

**Push:**
```bash
git push -u origin claude/help-coding-task-[SESSION_ID]
```

### 7.3 Testing

**Spuštění testů:**
```bash
composer test
# nebo
vendor/bin/phpunit
```

**Test coverage:**
```bash
composer test-coverage
# Report v coverage/html/index.html
```

**Aktuální pokrytí:**
- Security: 95%+
- Business Logic: 90%+
- Email Queue: 85%+
- API: 80%+

---

## 8. DEPLOYMENT

### 8.1 GitHub Actions

**Automatický deploy při push do main:**

`.github/workflows/deploy.yml`:
```yaml
name: Deploy to Production

on:
  push:
    branches: [ main ]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Run tests
        run: vendor/bin/phpunit

  deploy:
    needs: test
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: SFTP Deploy
        uses: SamKirkland/FTP-Deploy-Action@4.3.0
        with:
          server: ftp.wgs-service.cz
          username: ${{ secrets.FTP_USER }}
          password: ${{ secrets.FTP_PASS }}
```

### 8.2 Vercel Proxy (Geoapify)

**Problém:** Hosting blokuje přístup k `api.geoapify.com`

**Řešení:** Vercel Edge Function jako proxy

**Deploy:**
```bash
cd vercel-proxy
vercel --prod

# Nastavit API klíč
vercel env add GEOAPIFY_API_KEY production
# Zadat: ea590e7e6d3640f9a63ec5a9fb1ff002

# Znovu deploy
vercel --prod
```

**Výsledná URL:**
```
https://wgs-proxy.vercel.app/api/geocode
```

**Aktualizovat frontend:**
```javascript
// V wgs-map.js
const PROXY_URL = 'https://wgs-proxy.vercel.app/api/geocode';
```

### 8.3 Produkční checklist

```markdown
- [ ] .env soubor nakonfigurován
- [ ] Databáze naimportována
- [ ] SSL certifikát aktivní
- [ ] SMTP credentials správně
- [ ] Vercel proxy deploynutá
- [ ] Testy projdou (green)
- [ ] Security headers zapnuté
- [ ] Rate limiting aktivní
- [ ] Backup cron job nastaven
- [ ] Admin účet vytvořen
- [ ] Email fronta funguje
```

---

## 9. ÚDRŽBA

### 9.1 Automatické zálohy

**Cron job (denní backup v 2:00):**
```bash
crontab -e

# Přidat:
0 2 * * * /home/user/moje-stranky/scripts/backup-database.sh >> /home/user/moje-stranky/logs/backup.log 2>&1
```

**Struktura záloh:**
```
backups/
├── daily/    # 7 denních (rotace)
├── weekly/   # 4 týdenní (každá neděle)
└── monthly/  # 12 měsíčních (1. den měsíce)
```

**Obnovení ze zálohy:**
```bash
zcat backups/daily/backup_wgs_service_2025-11-21_02-00-00.sql.gz | mysql -u USER -p DATABASE_NAME
```

### 9.2 Email fronta (cron)

**Webcron nastavení v hostingu:**
```
URL: https://www.wgs-service.cz/cron/process-email-queue.php
Interval: */15 * * * *  (každých 15 minut)
```

**Kontrola:**
```bash
tail -f logs/email_queue_cron.log
```

### 9.3 Monitoring

**Zdravotní kontrola:**
```bash
curl https://wgs-service.cz/api/health.php
```

**Odpověď:**
```json
{
  "status": "healthy",
  "checks": {
    "database": true,
    "disk": true,
    "logs": true
  },
  "version": "1.0.0"
}
```

**Logy:**
```bash
# PHP errors
tail -f logs/php_errors.log

# Security events
tail -f logs/security.log

# Backup log
tail -f logs/backup.log
```

---

## 10. TROUBLESHOOTING

### 10.1 Časté problémy

**Problém: CSRF token invalid**
```bash
# Řešení:
1. Zkontrolovat session lifetime (init.php)
2. Ověřit že csrf-auto-inject.js se načítá
3. Zkontrolovat cookie settings (secure, httponly)
```

**Problém: Databáze connection failed**
```bash
# Řešení:
1. Ověřit .env credentials
2. Zkontrolovat že DB server běží
3. Testovat: mysql -u USER -p -e "SHOW DATABASES;"
```

**Problém: Mapa se nenačítá**
```bash
# Řešení:
1. Zkontrolovat GEOAPIFY_KEY v .env
2. Ověřit Vercel proxy běží
3. Console: Zkontrolovat network errors
```

**Problém: Emaily se neodesílají**
```bash
# Řešení:
1. Zkontrolovat SMTP credentials v .env
2. Testovat SMTP připojení
3. Zkontrolovat email queue: SELECT * FROM wgs_email_queue WHERE status='failed'
4. Zkontrolovat logs/email_queue_cron.log
```

**Problém: Fotky se nezobrazují**
```bash
# Řešení:
1. Zkontrolovat oprávnění: chmod 755 uploads/photos
2. Ověřit že file_path v DB není NULL
3. Zkontrolovat že soubory existují: ls uploads/photos/[reklamace_id]/
```

### 10.2 Debug mode

**Zapnout detailní logging:**
```javascript
// V browseru console
window.logger.setLevel('debug');
localStorage.setItem('DEBUG_MODE', 'true');
```

```php
// V PHP
define('DEBUG_MODE', true);
ini_set('display_errors', 1);
error_reporting(E_ALL);
```

### 10.3 Diagnostické skripty

```bash
# Systémová kontrola
php system_check.php

# Kontrola DB struktury
php show_table_structure.php

# Komplexní diagnóza
php diagnose_system.php
```

---

## 📚 DALŠÍ DOKUMENTACE

**Původní dokumenty (archivovány):**
- `CLAUDE.md` - AI Assistant Guide (zachován pro referenci)
- `docs/DATABAZE.md` - Detailní DB dokumentace
- `docs/API_STANDARDIZATION_GUIDE.md` - API standardy
- `docs/PDF_PROTOKOL_SYSTEM.md` - PDF workflow
- `tests/README.md` - Testovací dokumentace

**SQL Struktura:**
- Vždy aktuální přes Admin Panel → SQL karta
- Export DDL: `https://wgs-service.cz/vsechny_tabulky.php`

**Migrace:**
- Umístěny v ROOT složce projektu
- Spustit přes web browser
- Viditelné v `vsechny_tabulky.php`

---

## 📞 PODPORA

**Projekt:** White Glove Service
**Maintainer:** Radek Zikmund
**Email:** radek@wgs-service.cz
**Repository:** https://github.com/radecek222-boop/moje-stranky

**Hlášení chyb:**
1. Vytvořit issue na GitHubu
2. Zahrnout: kroky k reprodukci, očekávané chování, screenshoty
3. Přiložit relevantní logy

---

## 📝 CHANGELOG

### Verze 1.0 (2025-11-21)
- ✅ Konsolidace 72 dokumentačních souborů do jednoho
- ✅ Trojjazyčné rozhraní (CS/EN/IT)
- ✅ Kompletní API dokumentace
- ✅ Security best practices
- ✅ Deployment guide
- ✅ Troubleshooting guide

---

**© 2025 White Glove Service - Všechen kód v češtině**

**Tento dokument nahrazuje:**
- 26 Markdown souborů
- 9 Text souborů
- 5 PDF dokumentů
- 14+ README souborů

**Celkem konsolidováno: 72 dokumentačních souborů → 1 soubor**
