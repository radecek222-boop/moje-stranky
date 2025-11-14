# DETAILNÍ ARCHITEKTONICKÝ AUDIT - KONKRÉTNÍ FILE:LINE REFERENCES

## PROBLÉM: Email Validace (5 duplikátů)

### Lokalita 1: admin_users_api.php
**Řádek**: ~150 (dle grep)
**Kód**: `if (!filter_var($email, FILTER_VALIDATE_EMAIL))`
**Kontekst**: Vytváření/editace uživatele v admin API

### Lokalita 2: notification_api.php
**Řádek**: ~2 (CC emails) a ~3 (BCC emails)  
**Kód**: `if (!filter_var($email, FILTER_VALIDATE_EMAIL))`
**Kontekst**: Nastavení CC/BCC emailů pro notifikace

### Lokalita 3: control_center_api.php
**Řádek**: ~740 (email management)
**Kód**: `if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL))`
**Kontekst**: Ukládání emailové adresy

### Lokalita 4: password_reset_controller.php
**Řádek**: ~15
**Kód**: `if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL))`
**Kontekst**: Reset hesla - validace emailu

### Lokalita 5: registration.php
**Řádek**: (pravděpodobně v <?php sekci)
**Kód**: Validace emailu registrujícího se uživatele
**Kontekst**: Registrace nového uživatele

---

## PROBLÉM: SQL Query Duplikáty - Reklamace Lookup

### Query Template: SELECT id FROM wgs_reklamace WHERE reklamace_id = :id OR cislo = :cislo LIMIT 1

### Lokalita 1: /app/controllers/save.php
**Řádky**: ~78-98 (handleUpdate funkce)
**Funkcionalita**: Identifikace reklamace při updatu

### Lokalita 2: /api/delete_reklamace.php
**Řádky**: ?
**Funkcionalita**: Nalezení reklamace k smazání

### Lokalita 3: /api/protokol_api.php
**Řádky**: ?
**Funkcionalita**: Načtení reklamace pro protokol

---

## PROBLÉM: Session Start Redundance

### Lokalita 1: /init.php
**Řádky**: 7-8, 56-70
**Kód**: Dubitelné volání session_start()
**Issue**: Session se spouští 2x - jednou bez ochranného if check, podruhé s ním

### Lokalita 2: /config/config.php
**Řádka**: 2
**Kód**: `if (session_status() === PHP_SESSION_NONE) { session_start(); }`
**Issue**: Kolize s init.php volením

### Lokalita 3: /install_admin_control_center.php
**Řádka**: 7
**Kód**: Přímý `session_start();`
**Issue**: Žádný check

### Lokalita 4: /logout.php
**Řádka**: 6
**Kód**: Přímý `session_start();`
**Issue**: Zbytečné - logout by měl okamžitě session_destroy()

---

## GOD OBJECT #1: control_center_api.php

### Statistika
- **Soubor**: `/home/user/moje-stranky/api/control_center_api.php`
- **Řádků**: 2,960
- **Switch cases**: 48 (KAŽDÁ JE NOVÁ ODPOVĚDNOST!)

### Switch Cases (Odpovědnosti):
```
1. save_theme (řádky ~122)
2. delete_theme
3. get_pending_actions (řádky ~166)
4. execute_action (řádky ~195)
   └─ Vnořené cases:
      ├─ install_smtp
      ├─ install_phpmailer
      ├─ migration
      ├─ install
      ├─ optimize_assets
      ├─ add_db_indexes
      ├─ create_backup
      ├─ cleanup_emails
      ├─ enable_gzip
      └─ browser_cache (+ více)
5. complete_action
6. dismiss_action
7. get_content_texts (řádky ~XXX)
8. save_content_text
... + 40 dalších
```

### Problem Example (řádky 122-160)
```php
case 'save_theme':
    // Theme management logic (15 řádků)
    // SQL UPDATE query (10 řádků)
    // Response formatting (5 řádků)
    break;
```
**Issue**: Toto by mělo být ThemeController::save()

### Doporučené rozdělení:
```
api/control_center/
├── theme.php (save_theme, delete_theme, get_theme)
├── content.php (get_content_texts, save_content_text)
├── actions.php (get_pending_actions, execute_action, complete_action)
├── config.php (SMTP settings, JWT, system config)
├── maintenance.php (cleanup, backup, optimize, migrate)
└── bootstrap.php (shared auth, CSRF, routing)
```

---

## GOD OBJECT #2: control_center_console.php

### Statistika
- **Soubor**: `/home/user/moje-stranky/includes/control_center_console.php`
- **Řádků**: 2,624
- **Odpovědnosti**: 5+

### Odpovědnosti:
1. **PHP Diagnostics** (řádky 1-300?)
   - Render system info
   - Errors check
   - PHP version info
   
2. **Security Scanning** (řádky 300-700?)
   - Check for vulnerabilities
   - Scan file permissions
   - SQL injection patterns
   
3. **SQL Validation** (řádky 700-1000?)
   - Query checker
   - Index suggestions
   - Performance analysis
   
4. **API Testing** (řádky 1000-1500?)
   - Make test requests
   - Check responses
   - Validate endpoints
   
5. **Log Viewing** (řádky 1500-2624?)
   - Read log files
   - Display errors
   - Search logs

### Problem Structure
- Vše je v jednom HTML + inline CSS + inline JS
- Žádné oddělení logic od presentation

### Ideální struktura:
```
includes/diagnostics/
├── PhpDiagnostics.php (class)
├── SecurityScanner.php (class)
├── SqlValidator.php (class)
├── ApiTester.php (class)
├── LogViewer.php (class)
└── views/
    └── console.php (only rendering)
```

---

## GOD OBJECT #3: admin.php (864 řádků)

### Struktura
```php
<?php
// SECURITY CHECK & ROUTING (řádky 1-62)
- Check admin session
- Set security headers (CSP, X-Frame-Options, etc.)
- Load tab configuration
- Determine active tab

// TAB RENDERING (řádky 64-864)
- if (tab === 'dashboard'): render dashboard + stats
- elseif (tab === 'notifications'): render notifications + inline styles
- elseif (tab === 'users'): render users management
- elseif (tab === 'admin-panel'): render admin panel
- elseif (tab === 'control_center'): include control_center_unified.php
- elseif (tab === 'console'): include control_center_console.php
- ... dalších 20+ tabs
?>

<!DOCTYPE html>
<!-- HEAD: 50 řádků CSS links, meta tags -->
<!-- BODY: 600+ řádků HTML + inline CSS -->
```

### Problems:
1. **Mixed Concerns**:
   - Authentication (řádky 5-9)
   - Authorization (řádky 5-9)
   - Routing (řádky 44-62)
   - UI Rendering (řádky 64-864)
   - Styling (inline CSS v každé sekci)
   - Scripting (inline JS reference)

2. **Each Tab**: Vlastní logika, styling, HTML

3. **Inline CSS**: Řádky ~142+ - CSS by měl být v assets/css/

### Ideální struktura:
```
app/controllers/AdminController.php
├── dashboard()
├── notifications()
├── users()
├── admin_panel()
├── control_center()
└── console()

app/views/admin/
├── layout.php (base layout)
├── dashboard.php
├── notifications.php
├── users.php
├── admin_panel.php
├── control_center.php
└── console.php

admin.php (only entry point)
├── Load AdminController
├── Call action method
└── Render result
```

---

## KONTROLNÍ CENTRUM ROZPTÝLENO: 12+ SOUBORŮ

### Seznam všech control center souborů:

1. `/api/control_center_api.php` (2,960 řádků) - API endpoints
2. `/includes/control_center_actions.php` (586 řádků) - Action handlers
3. `/includes/control_center_appearance.php` (824 řádků) - Theme UI
4. `/includes/control_center_configuration.php` - Config UI
5. `/includes/control_center_console.php` (2,624 řádků) - Diagnostics
6. `/includes/control_center_content.php` - Content management UI
7. `/includes/control_center_diagnostics.php` (488 řádků) - Diagnostics display
8. `/includes/control_center_main.php` - Main layout?
9. `/includes/control_center_testing.php` (553 řádků) - Testing tools
10. `/includes/control_center_testing_interactive.php` (1,192 řádků) - Interactive testing
11. `/includes/control_center_testing_simulator.php` (781 řádků) - Simulation
12. `/includes/control_center_unified.php` (1,176 řádků) - Unified display

### Problem
Finding control center code requires searching in multiple locations:
- `/api/` - API logic
- `/includes/` - UI components  
- `/admin/` - Maybe some settings

### Total Control Center Lines: 11,184 řádků
**Rozprostřeno v**: 12 souborů
**Navigační nightmare**: Musíš vědět, kde je která část

---

## DATABASE ACCESS ISSUES

### Problem 1: Direct PDO in API Controller
**File**: `/api/control_center_api.php`
**Lines**: 168-176 (get_pending_actions case)
```php
case 'get_pending_actions':
    $stmt = $pdo->query("SELECT * FROM wgs_pending_actions ...");
    $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['success' => true, 'actions' => $actions]);
    break;
```
**Issue**: SQL query logic in API response handler

### Problem 2: Direct PDO in View
**File**: `/includes/control_center_unified.php`
**Lines**: 17-32
```php
<?php
// DATABASE QUERIES IN VIEW!
$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace");
$totalClaims = $stmt->fetchColumn();
?>
<!-- Poté HTML rendering s těmito daty -->
```
**Issue**: Data access logic mixed with rendering

### Problem 3: Database Access Scattered
**Affected Files**: 64 files s PDO/SQL access
**Pattern**: Každý controller/API/view má svůj SQL
**Impact**: 
- Nelze změnit DB schéma bez změny všech souborů
- Nelze unifikovat error handling
- Nelze centralizovat query logging

### Solution Needed
```php
// Místo:
$stmt = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE id = ?");

// Mělo by být:
$claim = $claimRepository->findById($id);
```

---

## VALIDATION DUPLICATION

### Email Validation (5 míst)
1. `/api/admin_users_api.php`
2. `/api/notification_api.php` (CC)
3. `/api/notification_api.php` (BCC)
4. `/api/control_center_api.php`
5. `/app/controllers/password_reset_controller.php`

**Pattern**: `filter_var($email, FILTER_VALIDATE_EMAIL)`

### Date Validation
Pravděpodobně v:
- `/app/controllers/save.php` (normalizeDateInput - existuje!)
- Ostatní místa?

### Phone Validation
Pravděpodobně rozptýleno

### URL Validation
Pravděpodobně rozptýleno

---

## GLOBAL VARIABLES USAGE

### global $pdo; (BAD PRACTICE)
**File**: `/api/test_environment.php`
**Lines**: 168, 177, 185
**Issue**: Service Locator anti-pattern v test code

### getDbConnection() (SERVICE LOCATOR)
**File**: `/config/config.php`
**Definice**: Řádka 284
**Používáno**: 64 souborů
**Issue**: Global singleton accessor namísto true DI

### $_SESSION (OK for this context)
**Usage**: 262 instancí
**Status**: Acceptabilní (session je global state, ale je to OK v PHP)

---

## SECURITY ISSUES FOUND

### 1. CSP Headers v admin.php
**File**: `/admin.php`
**Lines**: 15-41
**Issue**: CSP s 'unsafe-inline' a 'unsafe-eval'
```
script-src 'self' 'unsafe-inline' 'unsafe-eval' https://fonts.googleapis.com
```
**Severity**: VYSOKÁ - Umožňuje XSS

### 2. SQL Injection Risk
**Problem**: Direct SQL queries v 64 souborech
**Risk**: Pokud nejsou všechny prepared statements
**Need**: Audit všech SQL queries

### 3. CSRF Token Handling
**File**: `/api/control_center_api.php`
**Lines**: 40-52
**Status**: ✅ CSRF validation existuje, ale...
**Issue**: Debug info v JSON response!
```php
'debug' => [
    'tokens_match' => $csrfToken && isset($_SESSION['csrf_token']) 
        ? hash_equals($_SESSION['csrf_token'], $csrfToken) : false
]
```
**Severity**: STŘEDNÍ - Security info v response

---

## AUTOLOADING ANALYSIS

### Current State
```
✗ Žádný composer.json s autoload
✗ Žádné namespaces (0 v kódu)
✗ Manual require_once v každém souboru
✗ Relative paths s __DIR__
```

### Example Require Chains
**File**: `/app/controllers/save.php`
**Lines**: 7-9
```php
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/csrf_helper.php';
require_once __DIR__ . '/../../includes/db_metadata.php';
```

**File**: `/app/controllers/login_controller.php`
**Lines**: 2-5
```php
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/csrf_helper.php';
require_once __DIR__ . '/../../includes/db_metadata.php';
require_once __DIR__ . '/../../includes/audit_logger.php';
```
**Issue**: Copy-paste same includes

---

## RECOMMENDATIONS SUMMARY

### IMMEDIATE (1-2 týdny)
1. ✅ Vytvoř `/app/repositories/ClaimRepository.php`
2. ✅ Vytvoř `/app/validators/EmailValidator.php`
3. ✅ Refactor: control_center_api.php → api/control_center/

### SHORT-TERM (1-2 měsíce)
4. ✅ Move all root PHP files to app/controllers/
5. ✅ Create /app/services/ for business logic
6. ✅ Implement Composer + PSR-4 namespaces
7. ✅ Remove inline CSS from PHP files

### MEDIUM-TERM (2-3 měsíce)
8. ✅ Create IoC Container / use Pimple/Aura.Di
9. ✅ Implement full MVC (controllers + views separation)
10. ✅ Add unit tests for repositories/services
11. ✅ Consolidate control center into single folder

### LONG-TERM (3-6 měsíců)
12. ✅ Consider framework migration (Laravel/Symfony) if building from scratch
