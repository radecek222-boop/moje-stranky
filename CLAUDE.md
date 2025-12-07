# CLAUDE.md - AI Assistant Guide for WGS Service

**Last Updated:** 2025-11-23
**Project:** White Glove Service (WGS) - Natuzzi Furniture Service Management System

---

## 🎯 CRITICAL: CZECH LANGUAGE REQUIREMENT

**THIS IS THE MOST IMPORTANT RULE:**

All code in this project MUST be written in **CZECH** (not English):

- ✅ **Variable names:** `$jmeno`, `$telefon`, `$adresa` (not `$name`, `$phone`, `$address`)
- ✅ **Function names:** `ulozReklamaci()`, `nactiData()` (not `saveComplaint()`, `loadData()`)
- ✅ **Comments:** `// Uložit do databáze` (not `// Save to database`)
- ✅ **Commit messages:** `FIX: Oprava validace emailu` (not `FIX: Fixed email validation`)
- ✅ **CSS classes:** `.kalendarni-mrizka`, `.vybrane-datum` (not `.calendar-grid`, `.selected-date`)

### Why Czech?

1. The entire codebase is in Czech
2. Database column names are Czech (`jmeno`, `telefon`, `popis_problemu`)
3. Business domain terms are Czech (`reklamace`, `termin`, `návštěva`)
4. The team and users are Czech speakers
5. Mixing languages creates confusion and inconsistency

### Example - CORRECT vs INCORRECT:

```javascript
// ✅ SPRÁVNĚ (CORRECT)
async function ulozTermin(datum, cas) {
  // Validace vstupních dat
  if (!datum || !cas) {
    throw new Error('Chybí povinné údaje');
  }

  const formData = new FormData();
  formData.append('termin', datum);
  formData.append('cas_navstevy', cas);
  return await fetch('/api/uloz_termin.php', { method: 'POST', body: formData });
}

// ❌ ŠPATNĚ (WRONG)
async function saveAppointment(date, time) {
  // Validate input data
  if (!date || !time) {
    throw new Error('Missing required data');
  }

  const formData = new FormData();
  formData.append('appointment', date);
  formData.append('visit_time', time);
  return await fetch('/api/save_appointment.php', { method: 'POST', body: formData });
}
```

---

## 🚫 CRITICAL: NO EMOJI OR ICONS POLICY

**ABSOLUTELY NO EMOJI OR ICONS IN CODE, UI, OR MESSAGES**

With the **SOLE EXCEPTION** of language flags (🇨🇿 🇬🇧 🇮🇹), **NO emoji or decorative icons** are allowed anywhere in the codebase:

- ❌ **NO emoji in button text:** Use "Uložit" NOT "💾 Uložit"
- ❌ **NO emoji in headings:** Use "Upravit článek" NOT "📝 Upravit článek"
- ❌ **NO emoji in labels:** Use "Fotografie" NOT "📷 Fotografie"
- ❌ **NO emoji in alerts:** Use "Chyba" NOT "❌ Chyba"
- ❌ **NO emoji in placeholders:** Use "Klikněte" NOT "📸 Klikněte"
- ❌ **NO decorative icons** (Font Awesome, Material Icons, etc.)
- ❌ **NO icon libraries** unless explicitly approved by project owner
- ❌ **NO emoji anywhere else**
- ✅ **ONLY EXCEPTION:** Language flags: 🇨🇿 🇬🇧 🇮🇹

### Why No Emoji?

1. Professional appearance for business software
2. Accessibility - screen readers struggle with emoji
3. Inconsistent rendering across devices and browsers
4. Cluttered, unprofessional user interface
5. Harder to maintain and translate
6. User preference - requested explicitly by project owner

### Example - CORRECT vs INCORRECT:

```javascript
// ✅ SPRÁVNĚ (CORRECT)
<button>Uložit změny</button>
<h1>Upravit článek</h1>
alert('Článek byl uložen');

// ❌ ŠPATNĚ (WRONG)
<button>💾 Uložit změny</button>
<h1>📝 Upravit článek</h1>
alert('✅ Článek byl uložen');

// ✅ VÝJIMKA - Pouze vlajky
<button>🇨🇿 Čeština</button>
<button>🇬🇧 English</button>
<button>🇮🇹 Italiano</button>
```

**IMPORTANT:** When generating ANY code, interface, or message, Claude AI must NEVER add emoji or decorative icons. This is a permanent project requirement.

---

## 🎨 CRITICAL: COLOR SCHEME POLICY

**ONLY BLACK, WHITE, AND SHADES OF GRAY**

All UI elements, buttons, text, backgrounds, and design components **MUST** use only:

- ✅ **Black** (`#000`, `#111`, `#222`, `#333`, etc.)
- ✅ **White** (`#fff`, `#fefefe`, `#f9f9f9`, etc.)
- ✅ **Shades of gray** (`#666`, `#999`, `#ccc`, `#ddd`, `#eee`, etc.)
- ❌ **NO other colors** without explicit approval from project owner

### Examples:

```css
/* ✅ SPRÁVNĚ (CORRECT) */
.button-primary {
  background: #333;  /* Dark gray */
  color: #fff;       /* White */
}

.button-secondary {
  background: #999;  /* Light gray */
  color: #fff;       /* White */
}

.alert-error {
  background: #222;  /* Very dark gray */
  border: 1px solid #666;  /* Medium gray */
}

/* ❌ ŠPATNĚ (WRONG) - NO COLORS! */
.button-primary {
  background: #2D5016;  /* Green - FORBIDDEN */
  color: #fff;
}

.button-danger {
  background: #ff4444;  /* Red - FORBIDDEN */
  color: #fff;
}

.alert-success {
  background: #4CAF50;  /* Green - FORBIDDEN */
}
```

### Why Only Black/White/Gray?

1. **Professional minimalist design** - Clean, modern, timeless
2. **Accessibility** - High contrast ratios
3. **Consistency** - No color clashes or confusing visual hierarchy
4. **User preference** - Explicitly requested by project owner
5. **Focus on content** - No distracting colors

### When Can Other Colors Be Used?

**ONLY** with explicit written approval from the project owner (Radek Zikmund).

If you believe a feature absolutely requires color:
1. Ask the user/owner first
2. Explain why color is necessary
3. Wait for approval before implementing

**DO NOT** use colors and ask for forgiveness later. Always ask first.

---

## ✅ OFFICIAL EXCEPTION: WGS Toast Notifications (Neon Green)

**IMPORTANT:** Toast notifikace jsou schválená výjimka z černobílého designu.

### Neonově zelený toast (`#39ff14`) se MUSÍ použít pro:

| Akce | Příklad zprávy |
|------|----------------|
| Odeslání emailu | "Email úspěšně odeslán" |
| Nahrání fotky | "Fotografie nahrána" |
| Uložení dat | "Změny uloženy" |
| Push notifikace | "Nová poznámka k reklamaci" |
| Úspěšná akce | Jakákoliv potvrzující zpráva |

### Soubory:

| Soubor | Účel |
|--------|------|
| `assets/css/wgs-toast.css` | Styling - neonově zelený rámeček s pulzující září |
| `assets/js/wgs-toast.js` | JavaScript - `WGSToast.zobrazit(zprava, options)` |

### Použití v kódu:

```javascript
// ✅ SPRÁVNĚ - Použít WGSToast pro důležité akce
WGSToast.zobrazit('Email úspěšně odeslán', {
    titulek: 'WGS',
    trvani: 5000,
    claimId: 123  // Volitelné - klik přesměruje na reklamaci
});

// ✅ Pro běžné informace lze použít základní wgsToast
wgsToast.success('Uloženo');
wgsToast.error('Chyba při ukládání');

// ❌ ŠPATNĚ - Nepoužívat alert() pro úspěšné akce
alert('Email odeslán');  // Příliš invazivní
```

### Proč neonově zelená?

1. **Viditelnost** - Upoutá pozornost uživatele
2. **Profesionalita** - Moderní, technický vzhled
3. **Konzistence** - Jednotný styl pro všechny notifikace
4. **UX** - Neinvazivní, automaticky zmizí

### Design specifikace:

```css
/* Neonově zelená barva */
--wgs-neon-green: #39ff14;

/* Box shadow s pulzující září */
box-shadow:
    0 0 10px rgba(57, 255, 20, 0.4),
    0 0 20px rgba(57, 255, 20, 0.2),
    0 0 30px rgba(57, 255, 20, 0.1);

/* Border */
border: 2px solid #39ff14;
```

---

## ✅ OFFICIAL EXCEPTION: NOTIFY ME Button (Neon Green)

**IMPORTANT:** Tlačítko "NOTIFY ME OFF" v hamburger menu je DRUHÁ schválená výjimka z černobílého designu.

### Kdy se použije neonově zelená:

| Stav | Text | Barva |
|------|------|-------|
| Notifikace zapnuty | "NOTIFY ME OFF" | Neonově zelená (#39ff14) s pulzující září |
| Notifikace vypnuty | "NOTIFY ME ON" | Šedá (#ccc) |
| Notifikace zablokovány | "NOTIFY ME OFF" | Šedá s opacity 0.5 |

### Soubor:

| Soubor | Účel |
|--------|------|
| `includes/hamburger-menu.php` | CSS třídy `.hamburger-notif-btn` a `.notif-active` |

### Design specifikace:

```css
/* Neonově zelená barva pro aktivní notifikace */
.hamburger-notif-btn.notif-active {
  color: #39ff14 !important;
  text-shadow: 0 0 10px rgba(57, 255, 20, 0.6), 0 0 20px rgba(57, 255, 20, 0.3);
  animation: notif-pulse 2s ease-in-out infinite;
}
```

### Proč neonově zelená?

1. **Konzistence** - Stejná barva jako toast notifikace
2. **Viditelnost** - Uživatel okamžitě vidí, že má notifikace zapnuté
3. **Feedback** - Jasná vizuální odezva na aktivní stav

**TOTO JE DRUHÁ SCHVÁLENÁ BAREVNÁ VÝJIMKA V PROJEKTU (společně s WGS Toast).**

---

## ✅ OFFICIAL EXCEPTION: Danger/Delete Buttons (Red)

**IMPORTANT:** Červená barva je TŘETÍ schválená výjimka pro destruktivní akce.

### Kdy se použije červená (`#dc3545`):

| Akce | Příklad |
|------|---------|
| Smazání záznamu | Tlačítko "Smazat", "Odstranit" |
| Zrušení klíče | Tlačítko "Smazat klíč" |
| Reset hesla | Potvrzovací tlačítko v modalu |
| Deaktivace uživatele | Tlačítko "Deaktivovat" |
| Vymazání cache | Potvrzovací tlačítko |
| Jakákoliv nevratná akce | Destruktivní operace |

### Kde se používá:

| Místo | Popis |
|-------|-------|
| `wgsConfirm()` modal | Potvrzovací tlačítko s `nebezpecne: true` |
| Admin tabulky | Tlačítka pro mazání záznamů |
| Formuláře | Tlačítka pro odstranění dat |

### CSS specifikace:

```css
/* Červená pro nebezpečné akce */
--wgs-danger: #dc3545;
--wgs-danger-hover: #c82333;

/* Tlačítko */
.btn-danger {
    background: #dc3545;
    color: #fff;
    border: none;
}

.btn-danger:hover {
    background: #c82333;
}

/* V potvrzovacím modalu */
.wgs-confirm-btn-potvrdit.nebezpecne {
    background: #dc3545;
}
```

### Použití v kódu:

```javascript
// ✅ SPRÁVNĚ - Použít wgsConfirm s nebezpecne: true
const potvrdit = await wgsConfirm('Opravdu chcete smazat tento záznam?', {
    titulek: 'Smazat záznam',
    btnPotvrdit: 'Smazat',
    nebezpecne: true  // Červené tlačítko
});

// ✅ SPRÁVNĚ - HTML tlačítko pro mazání
<button class="btn-danger" onclick="smazat(id)">Smazat</button>

// ❌ ŠPATNĚ - Červená pro běžné akce
<button style="background: #dc3545">Uložit</button>  // Uložit NENÍ destruktivní!
```

### Proč červená pro destruktivní akce?

1. **UX standard** - Uživatelé očekávají červenou pro nebezpečné akce
2. **Prevence chyb** - Jasné vizuální varování před nevratnou akcí
3. **Konzistence** - Jednotný styl pro všechny destruktivní operace
4. **Přístupnost** - Červená je univerzálně spojována s "stop/pozor"

**TOTO JE TŘETÍ SCHVÁLENÁ BAREVNÁ VÝJIMKA V PROJEKTU.**

---

## 📋 PROJECT OVERVIEW

**Type:** Luxury furniture service management system
**Client:** Natuzzi (premium Italian furniture brand)
**Purpose:** Handle complaints, service requests, repair scheduling, technician management

**Tech Stack:**
- **Backend:** PHP 8.4+ with PDO
- **Database:** MariaDB 10.11+
- **Frontend:** Vanilla JavaScript (ES6+), no framework
- **Server:** Nginx 1.26+ (with Apache .htaccess fallback)
- **Maps:** Leaflet.js + Geoapify API
- **Email:** PHPMailer via SMTP
- **Deployment:** GitHub Actions + SFTP to Czech hosting

---

## 📁 DIRECTORY STRUCTURE

```
/home/user/moje-stranky/
│
├── config/                          # Configuration & DB connection
│   ├── config.php                  # Main config (loads .env, DB setup)
│   └── database.php                # Database singleton class
│
├── app/                            # Application core
│   ├── controllers/                # Business logic
│   │   ├── save.php               # Save/update complaints (CRITICAL FILE)
│   │   ├── login_controller.php   # Authentication logic
│   │   ├── registration_controller.php
│   │   ├── get_distance.php       # Distance calculation
│   │   └── get_csrf_token.php     # CSRF token generation
│   ├── save_photos.php            # Photo upload handling
│   └── notification_sender.php    # Email dispatcher
│
├── includes/                       # Shared utilities & middleware
│   ├── security_headers.php       # CSP, HSTS, X-Frame-Options
│   ├── csrf_helper.php            # CSRF token gen/validation
│   ├── error_handler.php          # Error logging & handling
│   ├── env_loader.php             # .env file parsing
│   ├── EmailQueue.php             # Email queue management
│   ├── audit_logger.php           # Action logging for compliance
│   ├── rate_limiter.php           # Rate limiting class
│   ├── api_response.php           # Standardized API responses
│   ├── security_scanner.php       # Security vulnerability scanner
│   └── user_session_check.php     # Session validation
│
├── api/                            # API endpoints
│   ├── control_center_api.php     # Admin panel operations (128KB!)
│   ├── protokol_api.php           # Service protocol CRUD
│   ├── statistiky_api.php         # Statistics & analytics
│   ├── notes_api.php              # Notes management
│   ├── delete_reklamace.php       # Complaint deletion
│   ├── geocode_proxy.php          # Geoapify proxy (CORS workaround)
│   ├── backup_api.php             # Database backups
│   ├── admin_api.php              # Registration key management
│   └── notification_api.php       # Notification operations
│
├── assets/                         # Frontend resources
│   ├── js/                         # 36 JavaScript files
│   │   ├── logger.js              # MUST load first
│   │   ├── utils.js               # Shared utilities
│   │   ├── csrf-auto-inject.js    # Auto-inject CSRF to forms
│   │   ├── novareklamace.js       # New complaint form
│   │   ├── seznam.js              # Complaint list view
│   │   ├── statistiky.js          # Statistics dashboard
│   │   ├── admin-dashboard.js     # Admin control center
│   │   ├── protokol.js            # Service protocol
│   │   └── map-integration.js     # Map functionality
│   └── css/                        # Minified + source CSS files
│
├── migrations/                     # Database schema migrations
├── setup/                          # Database initialization scripts
├── scripts/                        # Maintenance & cron jobs
├── logs/                           # Application logs
├── backups/                        # Database backups (daily/weekly/monthly)
├── uploads/                        # User-uploaded photos/documents
│
├── .env                            # Environment variables (gitignored)
├── .htaccess                       # Apache config (HTTPS, caching, security)
├── init.php                        # Bootstrap file (loaded on every page)
│
└── [Main Pages]
    ├── index.php                   # Homepage
    ├── novareklamace.php          # New complaint form
    ├── seznam.php                 # Complaint list (requires login)
    ├── statistiky.php             # Statistics (admin only)
    ├── protokol.php               # Service protocol form
    ├── admin.php                  # Admin control center
    ├── login.php                  # Login page
    └── registration.php           # User registration
```

---

## 🗄️ DATABASE CONVENTIONS

### Database Enum Mapping (CRITICAL!)

**Frontend (JavaScript)** uses **CZECH UPPERCASE** values:
- `'ČEKÁ'`, `'DOMLUVENÁ'`, `'HOTOVO'`
- `'CZ'`, `'SK'`

**Database (SQL)** uses **ENGLISH LOWERCASE** ENUM values:
- `'wait'`, `'open'`, `'done'`
- `'cz'`, `'sk'`

**The mapping happens automatically in `app/controllers/save.php`:**

```php
// In save.php - automatic mapping
$stavMapping = [
    'ČEKÁ' => 'wait',
    'DOMLUVENÁ' => 'open',
    'HOTOVO' => 'done'
];

$fakturaMapping = [
    'CZ' => 'cz',
    'SK' => 'sk'
];
```

### Core Tables

| Table | Purpose | Key Columns |
|-------|---------|-------------|
| `wgs_reklamace` | Main complaints/service requests | `reklamace_id`, `jmeno`, `telefon`, `email`, `stav`, `typ`, `datum_vytvoreni` |
| `wgs_users` | User accounts | `user_id`, `email`, `password_hash`, `role`, `is_active` |
| `wgs_registration_keys` | Registration access control | `key_code`, `key_type`, `max_usage`, `usage_count` |
| `wgs_theme_settings` | UI customization | `primary_color`, `font_family`, `logo` |
| `wgs_content_texts` | Editable page content | `page`, `section`, `text_key`, `value_cz`, `value_en`, `value_sk` |
| `wgs_system_config` | System settings | `config_key`, `config_value`, `config_type` |
| `wgs_pending_actions` | Async task queue | `action_type`, `status`, `payload`, `scheduled_at` |
| `wgs_email_queue` | Email queue | `to_email`, `subject`, `body`, `status`, `retry_count` |

### Always Use Prepared Statements

```php
// ✅ SPRÁVNĚ
$stmt = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE email = :email");
$stmt->execute(['email' => $email]);

// ❌ ŠPATNĚ - SQL injection vulnerability!
$result = $pdo->query("SELECT * FROM wgs_reklamace WHERE email = '$email'");
```

### 🎯 CRITICAL: Database Management via Control Centre

**⚠️ VŠECHNY ZMĚNY SQL DATABÁZE SE PROVÁDĚJÍ PŘES KARTU "SQL" V CONTROL CENTRE ⚠️**

**Postup pro správu databáze:**

1. **Otevřít Admin Panel:** `https://www.wgs-service.cz/admin.php`
2. **Kliknout na kartu "SQL"** - otevře se v novém okně
3. **Zobrazí se aktuální živá struktura všech tabulek** včetně:
   - CREATE TABLE DDL příkazů
   - Kompletní struktura sloupců
   - Indexy a klíče
   - Ukázka dat (3 záznamy)
   - Velikost tabulek

**Funkce SQL karty:**

| Funkce | Popis |
|--------|-------|
| **📥 Stáhnout všechny DDL** | Export celé struktury databáze do .sql souboru |
| **📋 Kopírovat do schránky** | Kopírovat CREATE TABLE DDL pro jednotlivé tabulky |
| **🖨️ Tisk** | Vytisknout dokumentaci databáze |
| **Živá data** | Vždy zobrazuje aktuální stav z produkční databáze |

**Důležité nástroje pro správu databáze:**

| Nástroj | URL | Účel |
|---------|-----|------|
| `vsechny_tabulky.php` | Hlavní SQL viewer | Zobrazení struktury všech tabulek |
| `pridej_chybejici_sloupce.php` | Migrace sloupců | Bezpečné přidání chybějících sloupců |
| `kontrola_zastaralych_sloupcu.php` | Kontrola legacy sloupců | Odstranění zastaralých sloupců |
| `pridej_chybejici_indexy.php` | Optimalizace | Přidání chybějících indexů |

**❌ NIKDY:**
- Neměňte SQL strukturu ručně přes phpMyAdmin
- Neodstraňujte sloupce bez kontroly závislostí
- Nevytvářejte tabulky mimo toto rozhraní
- Neimportujte SQL skripty bez kontroly

**✅ VŽDY:**
- Používejte kartu "SQL" pro zobrazení aktuální struktury
- Exportujte DDL před změnami (tlačítko "Stáhnout všechny DDL")
- Používejte migrační skripty pro změny struktury
- Kontrolujte závislosti před odstraněním sloupců

---

## 🔒 SECURITY PATTERNS

### 1. CSRF Protection (MANDATORY)

**All POST requests REQUIRE CSRF tokens:**

```javascript
// Frontend - automatically injected by csrf-auto-inject.js
formData.append('csrf_token', document.querySelector('input[name="csrf_token"]').value);

// Backend - validate in every POST handler
require_once __DIR__ . '/../includes/csrf_helper.php';
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token']));
}
```

### 2. Session Security

Sessions are configured in `init.php`:

```php
session_set_cookie_params([
    'lifetime' => 3600,           // 1 hour
    'path' => '/',
    'domain' => '',
    'secure' => true,             // HTTPS only
    'httponly' => true,           // No JavaScript access
    'samesite' => 'Lax'          // CSRF protection
]);

// Always regenerate session ID on login
session_regenerate_id(true);
```

### 3. Authentication Methods

| Method | Use Case | Validation |
|--------|----------|------------|
| **Admin Key** | Admin login | SHA256 hash in `.env` (ADMIN_KEY_HASH) |
| **User Login** | Regular users | `password_verify()` with PASSWORD_DEFAULT |
| **Registration Keys** | Control signup | Database table with usage limits |
| **High Key** | Admin key rotation | ADMIN_HIGH_KEY_HASH |

### 4. Input Sanitization

```php
// ✅ Always sanitize user input
function sanitizeInput($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

$jmeno = sanitizeInput($_POST['jmeno']);
$email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
```

### 5. Rate Limiting

```php
// Protects against brute force attacks
require_once __DIR__ . '/../includes/rate_limiter.php';

$rateLimiter = new RateLimiter($pdo);
if (!$rateLimiter->checkLimit('login', $_SERVER['REMOTE_ADDR'], 5, 900)) {
    die(json_encode(['status' => 'error', 'message' => 'Příliš mnoho pokusů']));
}
```

### 6. Security Headers

Set in `includes/security_headers.php`:
- **CSP:** Controls allowed resource origins
- **HSTS:** Forces HTTPS
- **X-Frame-Options:** Prevents clickjacking
- **X-Content-Type-Options:** Prevents MIME sniffing

---

## 🔌 API PATTERNS

### Standard API Response Format

```json
{
  "status": "success" | "error",
  "message": "Human-readable message in Czech",
  "data": {}  // Optional, varies by endpoint
}
```

### API Implementation Template

```php
<?php
// API template structure
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF validation
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatný CSRF token', 403);
}

// Authentication check
if (!isset($_SESSION['user_id'])) {
    sendJsonError('Uživatel není přihlášen', 401);
}

// Rate limiting
$rateLimiter = new RateLimiter($pdo);
if (!$rateLimiter->checkLimit('api_action', $_SERVER['REMOTE_ADDR'], 20, 3600)) {
    sendJsonError('Příliš mnoho požadavků', 429);
}

try {
    $pdo = getDbConnection();

    // Validate input
    $required = ['param1', 'param2'];
    foreach ($required as $field) {
        if (empty($_POST[$field])) {
            sendJsonError("Chybí povinné pole: {$field}");
        }
    }

    // Process request
    $stmt = $pdo->prepare("SELECT * FROM table WHERE id = :id");
    $stmt->execute(['id' => $_POST['param1']]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    sendJsonSuccess('Operace úspěšná', ['result' => $result]);

} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    sendJsonError('Chyba při zpracování požadavku');
}
?>
```

---

## 🌍 MULTI-LANGUAGE SUPPORT (CENÍK)

**Stránka ceník (`cenik.php`) plně podporuje 3 jazyky: Čeština (CS), Angličtina (EN), Italština (IT)**

### Architektura překladů

Systém používá **databázově-řízenou architekturu** pro všechny překlady:

| Datový typ | CS (výchozí) | EN | IT |
|------------|--------------|----|----|
| **Kategorie** | `category` | `category_en` | `category_it` |
| **Název služby** | `service_name` | `service_name_en` | `service_name_it` |
| **Popis** | `description` | `description_en` | `description_it` |
| **Předpona ceny** | JavaScript: `'Od'` | `'From'` | `'Da'` |

### Klíčové soubory

| Soubor | Účel |
|--------|------|
| `/assets/js/cenik.js` | Frontend rendering + překlad prefixu cen |
| `/api/pricing_api.php` | Backend API - vrací data včetně všech jazykových variant |
| `/assets/js/language-switcher.js` | Centrální systém přepínání jazyků |
| `/assets/css/cenik.min.css` | Styling (CSS `content` NESMÍ přidávat text, pouze JS) |
| `/doplnit_popisy_cenik_sql.php` | Migrační skript - SQL UPDATE pro překlady podle ID |

### Jak funguje překlad cen

**JavaScript (`cenik.js`) kontroluje jazyk a přidává správnou předponu:**

```javascript
const odPrefix = {
    cs: 'Od',
    en: 'From',
    it: 'Da'
};

// Pro rozpětí cen (price_from existuje, price_to neexistuje)
if (item.price_from && !item.price_to) {
    priceEl.className += ' range';
    priceEl.innerHTML = `${odPrefix[jazyk] || 'Od'} ${item.price_from} ${item.price_unit}`;
}
```

**CSS (`cenik.min.css`) NESMÍ obsahovat `content: 'od '`:**

```css
/* ✅ SPRÁVNĚ - prázdný content */
.item-price.range::before {
    content: '';
}

/* ❌ ŠPATNĚ - způsobí duplicitní zobrazení "od From 190€" */
.item-price.range::before {
    content: 'od ';
}
```

### Doplnění překladů do databáze

**Použijte migrační skript `/doplnit_popisy_cenik_sql.php`:**

1. Otevřete v prohlížeči: `https://www.wgs-service.cz/doplnit_popisy_cenik_sql.php`
2. Zkontrolujte náhled změn
3. Klikněte "SPUSTIT DOPLNĚNÍ"
4. Skript provede UPDATE podle ID (ne podle textového matchingu!)

**Struktura skriptu:**

```php
// Pole s překlady POPISŮ podle ID
$descriptionUpdates = [
    58 => [
        'en' => 'English description...',
        'it' => 'Italian description...'
    ],
    // ... další ID
];

// Pole s překlady NÁZVŮ SLUŽEB podle ID
$nameUpdates = [
    70 => [
        'en' => 'Transport to workshop...',
        'it' => 'Trasporto in officina...'
    ],
    // ... další ID
];
```

### Detekce jazyka

**Frontend používá funkci z `language-switcher.js`:**

```javascript
// ✅ SPRÁVNĚ
const jazyk = window.ziskejAktualniJazyk ? window.ziskejAktualniJazyk() : 'cs';

// ❌ ŠPATNĚ - tato proměnná neexistuje
const jazyk = window.aktualniJazyk || 'cs';
```

### Fallback mechanismus

**Inteligentní fallback pokud DB překlad chybí:**

```javascript
// Pokud DB překlad neexistuje NEBO je stejný jako český text
let dbPreklad = item.service_name_it;
if (!dbPreklad || dbPreklad === item.service_name) {
    // Zkusit slovník jako fallback
    dbPreklad = prelozitText(item.service_name, 'service');
}
nameEl.textContent = dbPreklad || item.service_name || '';
```

### Testování překladů

1. Otevřít stránku: `https://www.wgs-service.cz/cenik.php`
2. Přepnout jazyk pomocí vlajek: 🇨🇿 🇬🇧 🇮🇹
3. Zkontrolovat:
   - ✅ Kategorie přeloženy
   - ✅ Názvy služeb přeloženy
   - ✅ Popisy přeloženy
   - ✅ Předpona ceny ("Od"/"From"/"Da") přeložena
   - ✅ Žádný text v češtině v EN/IT verzích

### Časté problémy a řešení

| Problém | Příčina | Řešení |
|---------|---------|--------|
| Zobrazuje se "od From 190€" | CSS má `content: 'od '` | Změnit na `content: ''` v CSS |
| Některé popisy v češtině | DB má prázdný nebo český text | Spustit `/doplnit_popisy_cenik_sql.php` |
| Chyba "undefined function" | Špatná detekce jazyka | Použít `window.ziskejAktualniJazyk()` |
| Kategorie v češtině | Chybí `category_en`/`_it` | Importovat překlady pomocí migračního skriptu |

### Přidání nové položky ceníku

**Při přidání nové položky VŽDY vyplňte všechny jazykové varianty:**

```sql
INSERT INTO wgs_pricing (
    service_name,       service_name_en,          service_name_it,
    description,        description_en,           description_it,
    category,           category_en,              category_it,
    price_from, price_to, price_unit
) VALUES (
    'Oprava čalounění', 'Upholstery repair',      'Riparazione tappezzeria',
    'Popis česky...',   'Description English...', 'Descrizione italiana...',
    'Čalounění',        'Upholstery',             'Tappezzeria',
    190, NULL, '€'
);
```

---

## 🛠️ COMMON DEVELOPMENT TASKS

### Task 1: Creating Database Migration Scripts

**⚠️ KRITICKÉ: Když vytváříte migrační skripty pro databázi, VŽDY dodržujte tento formát:**

#### Naming Convention:
```
pridej_nazev_sloupce.php          # Pro přidání sloupců
kontrola_nazev.php                 # Pro kontrolu a validaci
migrace_nazev.php                  # Pro komplexní migrace
vycisti_nazev.php                  # Pro cleanup operace
```

#### Template migračního skriptu:
```php
<?php
/**
 * Migrace: [Popis co skript dělá]
 *
 * Tento skript BEZPEČNĚ provede [operaci].
 * Můžete jej spustit vícekrát - [neprovedese duplicitní operace].
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: [Název]</title>
    <style>
        /* Standardní styly pro migrační skripty */
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    // Kontrola před migrací
    echo "<h1>Migrace: [Název]</h1>";

    // 1. Kontrolní fáze
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    // 2. Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            // SQL operace zde
            // $pdo->exec("ALTER TABLE...");

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        // Náhled co bude provedeno
        echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
```

#### Kde uložit:
- **Všechny migrační skripty uložit do ROOT složky** (`/home/user/moje-stranky/`)
- **NIKDY** je neumísťovat do `/migrations/` nebo jiných složek
- Budou automaticky zobrazeny na stránce `vsechny_tabulky.php`

#### Po vytvoření migračního skriptu:
1. Commitnout soubor do Git
2. Dodat uživateli URL: `https://www.wgs-service.cz/[nazev_skriptu].php`
3. Skript se automaticky objeví v seznamu nástrojů na SQL kartě

### Task 2: Adding a New API Endpoint

1. **Create file in `/api/`**
2. **Include required files:** `init.php`, `csrf_helper.php`, `api_response.php`
3. **Add CSRF validation**
4. **Add authentication check** (if required)
5. **Add rate limiting** (if sensitive operation)
6. **Use PDO prepared statements**
7. **Return standardized JSON response**

### Task 2: Modifying Database Schema

1. **Create migration file** in `/migrations/`
2. **Test locally first**
3. **Add rollback script** (optional but recommended)
4. **Update relevant code** that uses the modified tables
5. **Test all affected features**

### Task 3: Adding Frontend Functionality

1. **Create/modify JS file** in `/assets/js/`
2. **Use Czech variable/function names**
3. **Add CSRF token to forms** (auto-injected if using standard forms)
4. **Use `fetch()` for AJAX calls**
5. **Handle errors gracefully**
6. **Test in production-like environment**

### Task 4: Fixing a Bug

1. **Reproduce the issue** locally
2. **Check logs:** `/logs/php_errors.log`, `/logs/security.log`
3. **Identify root cause**
4. **Write fix in Czech**
5. **Test thoroughly**
6. **Commit with descriptive message:** `FIX: Oprava [popis problému]`

---

## 🚨 COMMON PITFALLS TO AVOID

### ❌ Don't: Use English variable names

```javascript
// ❌ WRONG
const userName = 'Jan';
function saveData() { }
```

```javascript
// ✅ CORRECT
const jmenoUzivatele = 'Jan';
function ulozData() { }
```

### ❌ Don't: Concatenate SQL strings

```php
// ❌ WRONG - SQL injection vulnerability
$sql = "SELECT * FROM users WHERE email = '$email'";
$result = $pdo->query($sql);
```

```php
// ✅ CORRECT - prepared statement
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email");
$stmt->execute(['email' => $email]);
```

### ❌ Don't: Skip CSRF validation

```php
// ❌ WRONG - security vulnerability
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Process without validation
}
```

```php
// ✅ CORRECT - validate CSRF
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatný CSRF token', 403);
}
```

### ❌ Don't: Send database enum values directly from frontend

```javascript
// ❌ WRONG - database expects 'wait', not 'ČEKÁ'
formData.append('stav', 'wait');  // Don't use English DB values
```

```javascript
// ✅ CORRECT - send Czech values, backend will map
formData.append('stav', 'ČEKÁ');  // Backend converts to 'wait'
```

### ❌ Don't: Expose sensitive data in error messages

```php
// ❌ WRONG
catch (PDOException $e) {
    die("Database error: " . $e->getMessage());  // Exposes DB structure
}
```

```php
// ✅ CORRECT
catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());  // Log it
    sendJsonError('Chyba při zpracování požadavku');   // Generic message to user
}
```

### ❌ Don't: Modify session handling without understanding security

```php
// ❌ WRONG - breaks security
session_set_cookie_params(['secure' => false]);  // Allows non-HTTPS
```

```php
// ✅ CORRECT - session config is in init.php, don't modify unless necessary
require_once __DIR__ . '/init.php';  // Uses secure session config
```

---

## 🔄 GIT WORKFLOW

### Branch Naming Convention

```bash
# Always use this pattern:
claude/claude-md-mi2644dzcq7mr02m-[SESSION_ID]

# Example:
git checkout -b claude/claude-md-mi2644dzcq7mr02m-018Usf33oyhYEM8UGoCKtx2T
```

### Commit Message Format

```bash
# ✅ CORRECT - Czech commit messages
git commit -m "FIX: Oprava validace emailu v registraci"
git commit -m "FEATURE: Přidána podpora SK fakturace"
git commit -m "PERFORMANCE: Optimalizace načítání seznamu reklamací"
git commit -m "SECURITY: Oprava CSRF validace v admin API"
```

### Push to Remote

```bash
# Always use -u flag for new branches
git push -u origin claude/claude-md-mi2644dzcq7mr02m-018Usf33oyhYEM8UGoCKtx2T

# Network error retry policy:
# - Retry up to 4 times with exponential backoff (2s, 4s, 8s, 16s)
# - Only retry on network errors (not auth errors)
```

### Creating Pull Requests

1. **Push to branch** (as above)
2. **Use GitHub UI** to create PR (gh CLI not available)
3. **PR title in Czech:** `FIX: Oprava [popis]` or `FEATURE: [popis]`
4. **PR description in Czech:** Explain what was changed and why
5. **Wait for approval** before merging

---

## 📊 PERFORMANCE CONSIDERATIONS

### Database Queries

```php
// ✅ Use indexes for frequently queried columns
// Check /migrations/add_performance_indexes.sql

// ✅ Limit result sets
$stmt = $pdo->prepare("SELECT * FROM wgs_reklamace LIMIT :offset, :limit");

// ✅ Use transactions for multiple operations
$pdo->beginTransaction();
try {
    // Multiple operations
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

### Frontend Optimization

```javascript
// ✅ Debounce user input
function debounce(func, wait) {
    let timeout;
    return function(...args) {
        clearTimeout(timeout);
        timeout = setTimeout(() => func.apply(this, args), wait);
    };
}

// ✅ Cache DOM queries
const searchInput = document.getElementById('search');  // Cache once
// Don't query document.getElementById() repeatedly
```

### Asset Loading

- CSS/JS files are **minified** (`.min.css`, `.min.js`)
- Images should use **WebP format** when possible
- Fonts use `font-display: optional` to prevent blocking

---

## 🧪 TESTING CHECKLIST

Before committing code, verify:

- [ ] ✅ All variable names are in **CZECH**
- [ ] ✅ All function names are in **CZECH**
- [ ] ✅ All comments are in **CZECH**
- [ ] ✅ Commit message is in **CZECH**
- [ ] ✅ CSRF tokens are validated
- [ ] ✅ SQL queries use prepared statements
- [ ] ✅ User input is sanitized
- [ ] ✅ Error messages don't expose sensitive data
- [ ] ✅ Rate limiting is applied to sensitive operations
- [ ] ✅ No console errors in browser
- [ ] ✅ Tested locally before pushing
- [ ] ✅ Database enum mapping is correct (Czech ↔ English)

---

## 🔍 DEBUGGING

### Check Logs

```bash
# PHP errors
tail -f /home/user/moje-stranky/logs/php_errors.log

# Security events
tail -f /home/user/moje-stranky/logs/security.log
```

### Common Issues

| Issue | Cause | Solution |
|-------|-------|----------|
| CSRF token invalid | Token mismatch or expired session | Check session lifetime, ensure token is sent |
| Database connection failed | Wrong .env credentials | Verify DB_HOST, DB_USER, DB_PASS in .env |
| Map not loading | Missing/invalid Geoapify API key | Check GEOAPIFY_KEY in .env |
| Email not sending | SMTP config wrong | Check SMTP_* variables in .env, check email queue |
| 403 Forbidden on API | Missing authentication or CSRF | Check session and CSRF token |

### Diagnostic Tools

```bash
# System health check
php system_check.php

# Database structure validation
php show_table_structure.php

# Full system diagnostics
php diagnose_system.php
```

---

## 📚 KEY FILES REFERENCE

| File | Purpose | When to Modify |
|------|---------|----------------|
| `init.php` | Bootstrap file loaded on every page | Rarely (session config, includes) |
| `config/config.php` | Main configuration | When adding new config values |
| `app/controllers/save.php` | Save/update complaints (128 lines) | When changing complaint save logic |
| `includes/csrf_helper.php` | CSRF functions | Never modify (security critical) |
| `includes/security_headers.php` | HTTP security headers | When adding new CSP directives |
| `api/control_center_api.php` | Admin panel backend (128KB) | When adding admin features |
| `assets/js/logger.js` | Logging utility | Must load first in pages |
| `assets/js/csrf-auto-inject.js` | Auto-inject CSRF | Loads automatically |

---

## 🎓 QUICK REFERENCE

### Start a New Feature

```bash
# 1. Create branch
git checkout -b claude/claude-md-mi2644dzcq7mr02m-[SESSION_ID]

# 2. Write code (in CZECH!)

# 3. Test locally
php -S localhost:8000

# 4. Commit
git add -A
git commit -m "FEATURE: [popis v češtině]"

# 5. Push
git push -u origin claude/claude-md-mi2644dzcq7mr02m-[SESSION_ID]

# 6. Create PR via GitHub UI
```

### Create New API Endpoint

```php
<?php
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json; charset=utf-8');

if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    die(json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token']));
}

try {
    $pdo = getDbConnection();
    // Your logic here
    echo json_encode(['status' => 'success', 'data' => $result]);
} catch (Exception $e) {
    error_log($e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Chyba serveru']);
}
?>
```

### Frontend Fetch Pattern

```javascript
async function ulozData(data) {
  try {
    const formData = new FormData();
    formData.append('csrf_token', document.querySelector('[name="csrf_token"]').value);
    formData.append('data', JSON.stringify(data));

    const odpoved = await fetch('/api/endpoint.php', {
      method: 'POST',
      body: formData
    });

    const vysledek = await odpoved.json();

    if (vysledek.status === 'success') {
      console.log('Úspěch:', vysledek.data);
    } else {
      console.error('Chyba:', vysledek.message);
    }
  } catch (error) {
    console.error('Síťová chyba:', error);
  }
}
```

---

## 📞 GETTING HELP

1. **Check existing code:** Look at similar features (e.g., `seznam.js`, `save.php`)
2. **Read documentation:** `README.md`, `CONTRIBUTING.md`, this file
3. **Check logs:** `/logs/php_errors.log`, `/logs/security.log`
4. **Review recent commits:** `git log --oneline -20`
5. **Search codebase:** Use Grep tool to find similar patterns

---

## ⚠️ NEVER DO THIS

1. ❌ **Never use English** in code, comments, or commits
2. ❌ **Never use emoji or decorative icons** (except language flags 🇨🇿🇬🇧🇮🇹)
3. ❌ **Never use colors** other than black/white/gray without approval
4. ❌ **Never skip CSRF validation** on POST requests
5. ❌ **Never concatenate SQL** strings (always use prepared statements)
6. ❌ **Never expose sensitive data** in error messages
7. ❌ **Never commit .env file** (it's gitignored)
8. ❌ **Never push to main/master** directly (always use feature branches)
9. ❌ **Never modify session config** without understanding security implications
10. ❌ **Never trust user input** (always sanitize and validate)
11. ❌ **Never use `SELECT *`** in production queries (specify columns)
12. ❌ **Never mix Czech and English** in the same file

---

## ✅ ALWAYS DO THIS

1. ✅ **Always write in Czech** (code, comments, commits)
2. ✅ **Always use only black/white/gray colors** in UI
3. ✅ **Always avoid emoji and icons** (except language flags)
4. ✅ **Always validate CSRF tokens** on POST requests
5. ✅ **Always use PDO prepared statements** for database queries
6. ✅ **Always sanitize user input** with `htmlspecialchars()` and filters
7. ✅ **Always check authentication** before sensitive operations
8. ✅ **Always log errors** securely without exposing details to users
9. ✅ **Always test locally** before pushing
10. ✅ **Always use meaningful Czech names** for variables and functions
11. ✅ **Always commit with descriptive messages** (FIX:, FEATURE:, etc.)
12. ✅ **Always follow the enum mapping** (Czech frontend ↔ English database)
13. ✅ **Always ask for approval** before using any color other than black/white/gray

---

**Project maintained by:** Radek Zikmund
**Contact:** radek@wgs-service.cz
**Repository:** https://github.com/radecek222-boop/moje-stranky

---

© 2025 White Glove Service - All code in Czech language
