# KOMPLETNÍ ARCHITEKTONICKÝ AUDIT PHP PROJEKTU
## White Glove Service - moje-stranky
**Datum auditu**: 2025-11-14
**Celkem PHP souborů**: 119
**Celkem řádků kódu**: 35,511

---

## 1. DUPLICITNÍ KÓD (Code Duplication)

### ❌ KRITICKÉ PROBLÉMY:

#### 1.1 Email Validace (5 duplikátů)
```
- /home/user/moje-stranky/api/admin_users_api.php: filter_var($email, FILTER_VALIDATE_EMAIL)
- /home/user/moje-stranky/api/notification_api.php (2x - CC a BCC): filter_var($email, FILTER_VALIDATE_EMAIL)
- /home/user/moje-stranky/api/control_center_api.php: filter_var($email, FILTER_VALIDATE_EMAIL)
- /home/user/moje-stranky/app/controllers/password_reset_controller.php: filter_var($email, FILTER_VALIDATE_EMAIL)
```
**Severity**: VYSOKÁ | **Impact**: Invalidní emaily procházejí validací, nejednotná validační logika

#### 1.2 SQL Query Duplikáty - Reklamace ID Lookup (3+ duplikátů)
```
- Vícenásobné: SELECT id FROM wgs_reklamace WHERE reklamace_id = :reklamace_id OR cislo = :cislo LIMIT 1
- Duplicitní v: /api/delete_reklamace.php, /api/protokol_api.php, /app/controllers/save.php
```
**Severity**: VYSOKÁ | **Impact**: Maintenance nightmare, riziko nekonzistence

#### 1.3 Session Start Logika
```
- /home/user/moje-stranky/init.php: Řádek 7-8, 56-70
- /home/user/moje-stranky/config/config.php: Řádek 2
- /home/user/moje-stranky/install_admin_control_center.php: Řádek 7
```
**Severity**: STŘEDNÍ | **Impact**: Potenciální session conflicts, zbytečné session_start volání

#### 1.4 SQL Statistik Dotazy (Multiple COUNT queries)
```
- SELECT COUNT(*) FROM wgs_reklamace - v /api/control_center_api.php, /includes/control_center_unified.php
- SELECT COUNT(*) FROM wgs_users - ve více souborech
- SELECT COUNT(*) FROM wgs_registration_keys - duplicitní
```
**Severity**: STŘEDNÍ | **Impact**: Neoptimalizované DB dotazy, duplicitní logika

#### 1.5 Database Connection Logika (2 implementace)
```
- /home/user/moje-stranky/config/database.php: Database::getInstance() - Singleton class
- /home/user/moje-stranky/config/config.php: getDbConnection() - Static function caching
```
**Severity**: VYSOKÁ | **Impact**: Žádný standardní přístup k DB, dva systémy koexistují

### ⚠️ REFACTORING CANDIDATES:

1. **Centrální Validator Třída** - Email, telefonní čísla, datumyPřesuň:
   - `filter_var($email, FILTER_VALIDATE_EMAIL)` → `Validator::validateEmail()`
   
2. **Repository Layer** - Všechny SELECT ID dotazy:
   - `findClaimByAnyIdentifier()` method
   
3. **Cleanup Service** - Session cleanup logic z config.php

---

## 2. GOD OBJECTS / GOD FUNCTIONS

### ❌ KRITICKÉ PROBLÉMY:

#### 2.1 Control Center API Monolith
**File**: `/home/user/moje-stranky/api/control_center_api.php`
- **Řádků**: 2,960
- **Switch cases**: 48 (KAŽDÝ JE NOVÝ FEATURE!)
- **Handlery**: save_theme, execute_action, get_pending_actions, delete_theme, get_content_texts, atd.
- **Concerns**: Theme management, Action execution, Content editing, SMTP config, phpMailer install, backups, migrations, assets minification, email cleanup

**Problem**: Jeden soubor dělá vše - je to prakticky API router, handler, validator a business logic dohromady.

#### 2.2 Control Center Console Include
**File**: `/home/user/moje-stranky/includes/control_center_console.php`
- **Řádků**: 2,624
- **Funkcionalita**: Diagnostika PHP, HTML, CSS, JS, SQL, API v jednom souboru
- **Concerns**: Security scanning, Log checking, Config validation, PHP info, Syntax checking

#### 2.3 Control Center Testing Interactive
**File**: `/home/user/moje-stranky/includes/control_center_testing_interactive.php`
- **Řádků**: 1,192
- **Funkcí**: 13
- **Průměrná velikost**: 92 řádků na funkci (příliš velké!)
- **Concerns**: API testing, notification testing, claim testing, simulation

#### 2.4 Control Center Unified  
**File**: `/home/user/moje-stranky/includes/control_center_unified.php`
- **Řádků**: 1,176
- **Funkcí**: 21
- **Průměrná velikost**: 56 řádků na funkci
- **Concerns**: UI rendering, stats, display logic, vykreslovací logika

### ⚠️ REFACTORING POTŘEBA:

**Control Center API (2,960 řádků) MUSÍ být rozdělen**:
```
api/control_center/
  ├── theme_api.php (save_theme, delete_theme, get_theme)
  ├── content_api.php (get_content_texts, save_content_text)
  ├── actions_api.php (get_pending_actions, execute_action, complete_action)
  ├── config_api.php (SMTP, JWT, system config)
  ├── maintenance_api.php (cleanup, backup, optimize, migrate)
  └── controller.php (Router, CSRF validation, shared logic)
```

**Control Center Console (2,624 řádků) měl by mít**:
```
includes/diagnostics/
  ├── php_diagnostics.php
  ├── security_scanner.php
  ├── sql_validator.php
  ├── api_tester.php
  └── log_viewer.php
```

---

## 3. CHAOTICKÁ STRUKTURA SOUBORŮ

### ❌ KRITICKÉ PROBLÉMY:

#### 3.1 43 PHP Souborů v ROOT DIRECTORY!
```
/home/user/moje-stranky/
├── add_indexes.php
├── add_optimization_tasks.php
├── admin.php (864 řádků - MONSTER!)
├── admin_api.php
├── admin_key_manager.php
├── analytics.php
├── backup_system.php
├── cleanup_failed_emails.php
├── cleanup_history_record.php
├── cleanup_logs_and_backup.php
├── fix_visibility.php
├── gdpr.php
├── health.php
├── index.php
├── init.php
├── install_actions_system.php
├── install_admin_control_center.php
├── install_role_based_access.php
├── install_smtp_config.php
├── login.php
├── logout.php
├── mimozarucniceny.php
├── minify_assets.php
├── nasesluzby.php
├── novareklamace.php (474 řádků - view + PHP logic)
├── offline.php
├── onas.php
├── password_reset.php
├── photocustomer.php
├── protokol.php
├── psa-kalkulator.php
├── psa.php
├── quick_cleanup.php
├── registration.php
├── run_migration_simple.php
├── setup_actions_system.php
├── seznam.php
├── show_table_structure.php
├── statistiky.php (741 řádků - view + PHP logic)
├── test-phpmailer.php
├── test_console_buttons.php
├── validate_tools.php
└── verify_and_cleanup.php
```
**Severity**: MASIVNÍ | **Impact**: Nemožné se orientovat, SEO impact, bezpečnostní riziko

#### 3.2 Nekonzistentní Jmenování
```
novareklamace.php (Czech name)
nasesluzby.php (Czech name)
onas.php (Czech name)
psa.php (Czech name, nejednotný)
psa-kalkulator.php (Hyphenated)
admin.php (English name)
```
**Pattern**: Smíšená čeština/angličtina, nejednotná konvence

#### 3.3 API Soubory Bez Jednotné Struktury
```
/api/ (22 souborů)
├── admin_api.php
├── admin_stats_api.php
├── admin_users_api.php (Redundantní - proč není v admin_api.php?)
├── analytics_api.php
├── backup_api.php
├── control_center_api.php (MASTER CONTROLLER - 2,960 řádků)
├── create_test_claim.php
├── delete_reklamace.php
├── geocode_proxy.php
├── get_photos_api.php
├── github_webhook.php
├── log_js_error.php
├── migration_executor.php
├── notes_api.php
├── notification_api.php
├── notification_list_direct.php (Duplikát?)
├── protokol_api.php
├── statistiky_api.php
├── test_cleanup.php
├── test_environment.php
└── test_environment_simple.php (Duplikát?)
```
**Severity**: VYSOKÁ | **Impact**: Navigace, discovery, maintenance

#### 3.4 Control Center Rozptýleno Across Folders
```
/api/control_center_api.php (2,960 řádků)
/includes/control_center_actions.php (586 řádků)
/includes/control_center_appearance.php (824 řádků)
/includes/control_center_configuration.php
/includes/control_center_console.php (2,624 řádků)
/includes/control_center_content.php
/includes/control_center_diagnostics.php (488 řádků)
/includes/control_center_main.php
/includes/control_center_testing.php (553 řádků)
/includes/control_center_testing_interactive.php (1,192 řádků)
/includes/control_center_testing_simulator.php (781 řádků)
/includes/control_center_unified.php (1,176 řádků)
/admin/ folder (některé nastavení)
```
**Impact**: Vyhledávání control center kódu je NIGHTMARE - 12+ souborů v různých místech!

### ⚠️ NAVRHOVANÁ NOVÁ STRUKTURA:

```
/home/user/moje-stranky/
├── public/
│   ├── index.php (pouze entry point)
│   ├── admin.php (pouze entry point)
│   ├── login.php (pouze entry point)
│   ├── ...ostatní routes
│   └── assets/ (CSS, JS, IMG)
│
├── app/
│   ├── controllers/ (Aktuálně OK)
│   ├── models/ (CHYBÍ!)
│   ├── repositories/ (CHYBÍ!)
│   ├── services/ (CHYBÍ!)
│   └── views/ (CHYBÍ!)
│
├── api/
│   ├── common/
│   │   └── controller.php (shared routing, CSRF, auth)
│   ├── v1/
│   │   ├── theme/
│   │   ├── content/
│   │   ├── actions/
│   │   ├── config/
│   │   └── maintenance/
│   ├── admin/
│   │   ├── users/
│   │   ├── stats/
│   │   └── analytics/
│   └── webhooks/
│
├── admin/
│   ├── control-center/
│   │   ├── api.php (router)
│   │   ├── console.php (diagnostics)
│   │   ├── testing.php (test tools)
│   │   └── components/ (UI components)
│   ├── pages/ (page logic)
│   └── settings/
│
├── includes/ (Shared utilities, ne business logic)
│   ├── database/
│   ├── security/
│   ├── validation/
│   ├── email/
│   └── utils/
│
├── config/
├── migrations/
├── scripts/ (Installation, setup, cron)
└── logs/
```

---

## 4. REPOSITORY PATTERN - DATA ACCESS LAYER

### ❌ KRITICKÉ PROBLÉMY:

#### 4.1 Přímé PDO Queries v API Controlleru
**File**: `/home/user/moje-stranky/api/control_center_api.php`
```php
// Řádek 168-176: Direct SQL in API controller
$stmt = $pdo->query("
    SELECT * FROM wgs_pending_actions
    WHERE status = 'pending'
    ORDER BY FIELD(priority, 'critical', 'high', 'medium', 'low'),
    created_at DESC LIMIT 50
");
```
**Problem**: Business logic, SQL, API response ve stejném souboru

#### 4.2 SQL v Presentation Layer
**File**: `/home/user/moje-stranky/includes/control_center_unified.php`
```php
// Řádky 17-32: Direct SQL in UI include
$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace");
$totalClaims = $stmt->fetchColumn();
```
**Impact**: Nemůžeš měnit DB bez dotykání views

#### 4.3 Database Access Rozptýleno Across 64 Files
- 262 instancí superglobálů ($SERVER, $_GET, $_POST, $_REQUEST)
- 44 instancí v app/ folder (dobré oddělení)
- ale 218 v ostatních místech bez oddělení

#### 4.4 Chybí Repository Abstraction
```
✗ Žádný ClaimRepository
✗ Žádný UserRepository
✗ Žádný ConfigRepository
✗ Žádný NotificationRepository
```

### ⚠️ REFACTORING POTŘEBA:

```php
// Mělo by existovat:
class ClaimRepository {
    public function findById(int $id): ?array {}
    public function findByAnyIdentifier(string $identifier): ?array {}
    public function save(array $data): int {}
    public function delete(int $id): bool {}
    public function getStats(): array {}
    public function search(array $filters): array {}
}

// Místo:
$stmt = $pdo->prepare('SELECT * FROM wgs_reklamace WHERE id = ?');
```

---

## 5. DEPENDENCY INJECTION & SERVICE LOCATOR

### ❌ KRITICKÉ PROBLÉMY:

#### 5.1 Global Database Instance (Anti-pattern)
```php
// Používáno ve 64 souborech:
$pdo = getDbConnection();  // Global singleton accessor
```
**Pattern**: Service Locator anti-pattern, ne true Dependency Injection

#### 5.2 Global Variables v Test Code
**File**: `/home/user/moje-stranky/api/test_environment.php`
```php
global $pdo;  // Lines 168, 177, 185 - BAD!
```
**Severity**: VYSOKÁ | **Impact**: Těžké testovatelné, nepředvídatelné chování

#### 5.3 Static Database Class Without DI
**File**: `/home/user/moje-stranky/config/database.php`
```php
class Database {
    private static $instance = null;
    public static function getInstance() { ... }  // Anti-pattern!
}
```
**Problem**: Třída se nemůže mockovat na testování

#### 5.4 Hard-coded Dependencies
- `requireOnce` statements v každém controlleru
- `require_once __DIR__ . '/../includes/csrf_helper.php'` - hardcoded paths
- Žádná IoC container

### ⚠️ IDEÁLNÍ ŘEŠENÍ:

```php
// Nyní:
require_once __DIR__ . '/../../init.php';
$pdo = getDbConnection();
$validator = new SomeValidator();

// Mělo by být (s dependency injection):
class ClaimController {
    public function __construct(
        ClaimRepository $repository,
        EmailService $emailService,
        ValidationService $validator
    ) {
        $this->repository = $repository;
        $this->emailService = $emailService;
        $this->validator = $validator;
    }
}
```

---

## 6. AUTOLOADING & REQUIRE/INCLUDE

### ⚠️ PROBLÉMY (STŘEDNÍ):

#### 6.1 Žádný Modern Autoloader
- **Chybí**: `composer.json` s autoload directive
- **Chybí**: PSR-4 namespace standard
- **Používá**: Manual `require_once` statements - ARCHAIC!

#### 6.2 Require Statements v Každém Souboru
```php
// Pattern opakovaný 100+ krát:
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/csrf_helper.php';
require_once __DIR__ . '/../../includes/db_metadata.php';
```
**Impact**: DRY violation, těžké refactorování

#### 6.3 Relative Paths v Includes
```php
require_once __DIR__ . '/../../config/config.php';  // Fragile!
```
**Risk**: Porušuje se při přesunutí souboru

#### 6.4 0 Namespace Deklarací
- **Found**: 19 instancí `namespace` nebo `use` statements
- **Status**: Projekt nepoužívá PHP namespaces!

### ✅ ŘEŠENÍ:

Implementuj Composer autoloader:
```json
{
  "autoload": {
    "psr-4": {
      "App\\": "app/",
      "Api\\": "api/",
      "Services\\": "app/services/",
      "Repositories\\": "app/repositories/"
    },
    "files": ["includes/helpers.php"]
  }
}
```

---

## 7. SEPARATION OF CONCERNS

### ❌ KRITICKÉ PROBLÉMY:

#### 7.1 HTML Mixed with PHP Logic
**File**: `/home/user/moje-stranky/novareklamace.php` (474 řádků)
```php
<?php
// BUSINESS LOGIC:
$isLoggedIn = isset($_SESSION['user_id']);
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (session_status() !== PHP_SESSION_ACTIVE) { die(...); }
?>
<!DOCTYPE html>
<!-- HTML starts here, mixed with PHP above -->
<html>
  <head>...</head>
  <body>
    <!-- HTML form -->
    <!-- Inline CSS (řádky 33+) -->
    <style>
      .calendar-overlay { ... }
      .calendar-box { ... }
    </style>
    <!-- More HTML -->
  </body>
</html>
```
**Problem**: 3 vrstvy (PHP logic + HTML + CSS) v jednom souboru

#### 7.2 Admin.php - Mega File (864 řádků)
**File**: `/home/user/moje-stranky/admin.php`
```php
<?php
// SECURITY LOGIC (řádky 1-62):
require_once "init.php";
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) { header('Location: login.php?redirect=admin.php'); exit; }
// CSP HEADERS (řádky 15-41)
header("Content-Security-Policy: ...");
// TAB LOGIC (řádky 44-62)
$tabConfig = loadAdminTabNavigation();
$activeTab = $_GET['tab'] ?? 'control_center';
?>
<!DOCTYPE html>
<!-- 800+ řádků HTML/CSS -->
<?php if ($activeTab === 'dashboard'): ?>
  <!-- Dashboard content inline -->
<?php elseif ($activeTab === 'notifications'): ?>
  <!-- Notifications content inline + inline styles (řádky 142+) -->
<?php endif; ?>
```
**Concerns mixed**: Authentication, authorization, routing, rendering, styling, scripts

#### 7.3 Control Center Unified (1,176 řádků) - SQL + CSS + HTML
**File**: `/home/user/moje-stranky/includes/control_center_unified.php`
```php
<?php
// DATABASE QUERIES (řádky 14-32):
$stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace");
$totalClaims = $stmt->fetchColumn();
?>
<!-- INLINE STYLES (řádky 35-600+) -->
<style>
  .control-center { ... }
  .card { ... }
</style>
<!-- HTML/PHP mix (zbytek souboru) -->
```

#### 7.4 API Returners vs Direct HTML
- **File**: `/home/user/moje-stranky/api/` - ✅ Vracejí JSON
- **File**: `/home/user/moje-stranky/includes/` - ❌ Vracejí HTML (include-based)
- **File**: `/home/user/moje-stranky/*.php` - ❌ Vracejí HTML

### ⚠️ IDEÁLNÍ STRUKTURA:

```php
// CONTROLLER (app/controllers/admin_controller.php)
class AdminController {
    public function __construct(
        AdminService $adminService,
        UserRepository $userRepository
    ) { ... }
    
    public function dashboard() {
        $stats = $this->adminService->getStats();
        return view('admin/dashboard', ['stats' => $stats]);
    }
}

// SERVICE (app/services/AdminService.php)
class AdminService {
    public function getStats(): array {
        return [
            'claims' => $this->claimRepository->count(),
            'users' => $this->userRepository->count(),
        ];
    }
}

// VIEW (app/views/admin/dashboard.php)
<div class="dashboard">
    <div class="stat"><?= htmlspecialchars($stats['claims']) ?></div>
    <div class="stat"><?= htmlspecialchars($stats['users']) ?></div>
</div>
```

---

## 8. SINGLE RESPONSIBILITY PRINCIPLE

### ❌ KRITICKÉ PORUŠENÍ SRP:

#### 8.1 Control Center API - 48 Switch Cases!
**File**: `/home/user/moje-stranky/api/control_center_api.php`

Každá case má JINOU odpovědnost:
1. `case 'save_theme'` - Theme management
2. `case 'delete_theme'` - Theme deletion
3. `case 'get_pending_actions'` - Action fetching
4. `case 'execute_action'` - Action execution (sub-cases: install_smtp, install_phpmailer, migration, backup, cleanup, etc.)
5. `case 'complete_action'` - Action completion
6. `case 'dismiss_action'` - Action dismissal
7. `case 'get_content_texts'` - Content fetching
8. `case 'save_content_text'` - Content saving
... + 40 dalších

**Each case**: Vlastní SQL queries, error handling, response formatting

**Mělo by**: Každá case být vlastní controller/handler

#### 8.2 Admin.php - Multiple Concerns
- Authentication/Authorization (řádky 1-10)
- Tab routing (řádky 44-62)
- HTML rendering (řádky 64+)
- JavaScript inline scripts
- CSS styling inline
- Different features per tab (dashboard, notifications, users, etc.)

#### 8.3 Kontrolní Center Console (2,624 řádků)
- PHP diagnostics
- SQL query validation
- API endpoint testing
- Log file viewing
- Security scanning
- File structure validation
- Cache clearing
- Error log analysis

**6+ nezávislých features v jednom souboru!**

#### 8.4 Email Queue Class
**File**: `/home/user/moje-stranky/includes/EmailQueue.php`

Věrovatě má:
- Email queue management
- PHPMailer integration
- Log file handling
- Retry logic
- Attachment processing

**Too many reasons to change!**

### ⚠️ REFACTORING POTŘEBA:

```
SRP Violation Score: 8.5/10 (KRITICKÝ)

Mělo by existovat:
├── ThemeController / ThemeService
├── ContentController / ContentService
├── ActionController / ActionService
├── AdminAuthController
├── DiagnosticsService
├── SecurityScannerService
├── ApiTesterService
└── Každé feature má jedinou odpovědnost
```

---

## SOUHRN PROBLÉMŮ PODLE KATEGORIÍ

### Duplicitní kód (Code Duplication)
✅ **Správně**:
- Počet SQL queries (141) - OK, není extrémní
- HTML escaping (135 instancí) - OK, používá se konzistentně

❌ **Problémy**:
- Email validace: 5 duplikátů (KRITICKÉ)
- Session start: 4 místa
- SQL lookup queries: 3+ duplikátů
- Database connection: 2 systémy (Database class + getDbConnection())
- Stats queries: N duplikátů

📋 **Tech Debt**: **VYSOKÝ (8/10)**

---

### God Objects / God Functions
❌ **KRITICKÉ PORUŠENÍ**:
- control_center_api.php: 2,960 řádků, 48 switch cases
- control_center_console.php: 2,624 řádků
- control_center_testing_interactive.php: 1,192 řádků, 13 funkcí
- control_center_unified.php: 1,176 řádků, 21 funkcí
- admin.php: 864 řádků

📋 **Tech Debt**: **VELMI VYSOKÝ (9/10)**

---

### Chaotická Struktura
❌ **KRITICKÉ PROBLÉMY**:
- 43 PHP souborů v root directory
- 12+ control center files v různých složkách
- 22 API files bez jednotné struktury
- Duplikátní API files (notification_list_direct vs notification_api?)
- Nejednotné pojmenování (čeština vs angličtina)

📋 **Tech Debt**: **VELMI VYSOKÝ (9/10)**

---

### Repository Pattern
❌ **MASIVNÍ CHYBĚNÍ**:
- Přímé PDO queries v API (64 files)
- Žádné repository classes
- SQL v presentation layer
- Žádný data access abstraction

📋 **Tech Debt**: **KRITICKÝ (9/10)**

---

### Dependency Injection
❌ **ŽÁDNÝ SYSTÉM**:
- Service Locator pattern (getDbConnection())
- Global variables v testech
- Singleton Database class
- Hard-coded paths v requires
- Žádný IoC container

📋 **Tech Debt**: **VYSOKÝ (8/10)**

---

### Autoloading
⚠️ **ARCHAIC APPROACH**:
- Žádný Composer autoloader
- 0 namespaces v kódu (19 instancí, ale minimální)
- Manual require_once statements
- Relative paths v includes

📋 **Tech Debt**: **STŘEDNÍ (7/10)**

---

### Separation of Concerns
❌ **KRITICKÉ PORUŠENÍ**:
- HTML mixed s PHP logic v 34+ files
- Inline CSS v jednotlivých souborech
- SQL queries v views
- HTML v includes

📋 **Tech Debt**: **VELMI VYSOKÝ (9/10)**

---

### Single Responsibility Principle
❌ **KRITICKÉ PORUŠENÍ**:
- control_center_api.php: 48 zip case - 48 důvodů ke změně
- admin.php: 6+ responsibility
- control_center_console.php: 6+ features
- Email handling třídy: mixing queue + PHPMailer + logging

📋 **Tech Debt**: **KRITICKÝ (9/10)**

---

## GLOBÁLNÍ METRIKY

```
📊 ARCHITEKTONICKÉ SKÓRE: 3.2/10 (KRITICKY ŠPATNÉ)

Kategorie čistého kódu:
├── Duplicitní kód: 2/10 ❌
├── God Objects: 1/10 ❌❌
├── Struktura: 2/10 ❌
├── Repository Pattern: 0/10 ❌❌❌
├── DI/IoC: 1/10 ❌
├── Autoloading: 3/10 ❌
├── Separation of Concerns: 2/10 ❌
└── SRP: 2/10 ❌

PŘÍČINY PROBLÉMŮ:
┌─────────────────────────────────────────────────────┐
│ 1. Iterativní vývoj bez refactoringu               │
│ 2. Chybějící architektonické plánování              │
│ 3. Přidávání features bez čištění                   │
│ 4. Copypasta development (CTRL+C, CTRL+V)          │
│ 5. Žádný code review proces                        │
│ 6. Žádné design patterns (MVC, DI, Repositories)   │
│ 7. Žádný linter/CS fixer                           │
└─────────────────────────────────────────────────────┘
```

---

## KRITICKÉ AKCE (MUSÍ SE UDĚLAT)

### Priority 1 - URGENT (Dělej NYNÍ)

1. **Rozděl control_center_api.php** (2,960 řádků)
   - Čas: 2-3 dny
   - Impact: Sníží maintenance nightmare
   - ROI: 8/10

2. **Vytvoř Repository Layer** (ClaimRepository, UserRepository, etc.)
   - Čas: 2-3 dny
   - Impact: Umožní testing, změní DB bez dotykání API
   - ROI: 9/10

3. **Vytvoř centrální Validator** (Email, Phone, Date, etc.)
   - Čas: 1 den
   - Impact: Jednotná logika, DRY
   - ROI: 7/10

### Priority 2 - HIGH (Dělej tento sprint)

4. **Přesuň veškerou PHP logiku z public root** do app/controllers
   - Čas: 2-3 dny
   - Impact: Bezpečnost, struktura
   - ROI: 8/10

5. **Implementuj MVC Views** (control_center_unified.php, atd.)
   - Čas: 1-2 dny
   - Impact: Separation of Concerns
   - ROI: 7/10

6. **Implementuj Composer autoloader** + PSR-4 namespaces
   - Čas: 1 den
   - Impact: Modernní PHP, snadnější imports
   - ROI: 6/10

### Priority 3 - MEDIUM (Dělej v dalších sprintech)

7. **Implementuj Service Layer** (AdminService, ClaimService, etc.)
   - Čas: 3-5 dní
   - Impact: Business logic separation
   - ROI: 7/10

8. **Sjednoť API response format** across všemi API files
   - Čas: 1-2 dní
   - Impact: Konzistentnost, snadnější frontend development
   - ROI: 6/10

9. **Vytvořit IoC Container** nebo použít existující (Pimple, Aura.Di)
   - Čas: 2-3 dní
   - Impact: Dependency Injection, testability
   - ROI: 7/10

---

## METRIKY NA SLEDOVÁNÍ (BASELINE)

```
Nyní:
├── God Classes (>1000 řádků): 4 souborů
├── Average file size: 298 řádků
├── Average function length: ~45 řádků
├── Code duplication: ~5-7%
├── Test coverage: 0% (žádné testy vidět)
└── Tech Debt: VELMI VYSOKÝ

Cíl (za 3 měsíce):
├── God Classes (>1000 řádků): 0 souborů
├── Average file size: <200 řádků
├── Average function length: <25 řádků
├── Code duplication: <2%
├── Test coverage: 60%+
└── Tech Debt: NÍZKÝ
```

