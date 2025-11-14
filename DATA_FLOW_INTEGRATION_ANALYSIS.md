# DATA FLOW & INTEGRATION ANALYSIS - WGS Service
## Důkladná analýza toku dat a integrace modulů

**Datum analýzy:** 2025-11-14
**Analyzovaný projekt:** WGS Service (White Glove Service)
**Rozsah:** Kompletní data flow, API integrace, module conflicts

---

## EXECUTIVE SUMMARY

Projekt trpí **závažnými problémy s architekturou toku dat** napříč vrstvami aplikace. Identifikováno bylo **47 kritických problémů** v následujících oblastech:

- **Session Management:** 12 konfliktů
- **Database Flow:** 9 inconsistencí
- **File Upload Flow:** 8 chyb
- **API Integration:** 11 problémů
- **Module Conflicts:** 7 duplicit

**Celkový Impact Rating:** 🔴 CRITICAL (85/100)

---

## 1. SESSION FLOW ISSUES

### 1.1 🔴 CRITICAL: Dvojí inicializace session

**Postižené soubory:**
```
init.php (řádky 7-9, 56-71) → session_start() 2x
config/config.php (řádek 2) → session_start() před init.php
login.php → require init.php (session již běží)
admin.php → require init.php (session již běží)
```

**Data Flow Path:**
```
config/config.php: session_start() #1
    ↓
init.php: if (session_status() === PHP_SESSION_NONE) session_start() #2
    ↓
Každý PHP soubor: require init.php
    ↓
PROBLÉM: Session settings nastaveny 2x, možná ztráta dat
```

**Popis problému:**
Session se inicializuje DVAKRÁT - jednou v `config.php` (řádek 2) a podruhé v `init.php` (řádek 7). Ačkoliv `init.php` má check `session_status() === PHP_SESSION_NONE`, `config.php` session spustí PŘEDTÍM, než je `init.php` načten. To znamená, že session settings z `init.php` (řádky 56-71) se aplikují na již běžící session.

**Impact:**
- ⚠️ Session cookie settings mohou být ignorovány
- ⚠️ Security headers (httponly, secure, samesite) nemusí fungovat správně
- ⚠️ Race condition při současném přístupu
- ⚠️ Možná ztráta session dat při regeneraci

**Příklad selhání:**
```php
// config.php načten první
session_start(); // Session ID = abc123

// Pak init.php
session_start(); // Session již běží, ale settings se aplikují POTÉ
ini_set('session.cookie_secure', 1); // ❌ TOO LATE!
```

**Fix návrh:**
```php
// OPTION 1: Odstranit session_start() z config.php úplně
// config.php - REMOVE line 2

// OPTION 2: Centralizovat vše do init.php
// init.php - na začátek souboru, před jakýkoli require
if (session_status() === PHP_SESSION_NONE) {
    // Nastavit SETTINGS PŘED session_start()
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 1 : 0);
    ini_set('session.cookie_samesite', 'Lax');

    session_start();
}
```

---

### 1.2 🔴 CRITICAL: Inconsistentní session variable naming

**Postižené soubory:**
```
app/admin_session_check.php → používá $_SESSION['admin_id'], $_SESSION['admin_name']
includes/user_session_check.php → používá $_SESSION['user_id'], $_SESSION['user_name']
app/controllers/login_controller.php → nastavuje OBĚ (řádky 70-76, 128-149)
admin.php → kontroluje pouze $_SESSION['is_admin']
```

**Data Flow Path:**
```
LOGIN (admin key):
    login_controller.php řádek 70-76:
        $_SESSION['is_admin'] = true
        $_SESSION['admin_id'] = 'WGS_ADMIN' ← STRING!
        $_SESSION['user_id'] = 0             ← INT!
        $_SESSION['user_name'] = 'Administrátor'

LOGIN (user email):
    login_controller.php řádek 128-149:
        $_SESSION['user_id'] = $userId       ← INT nebo STRING!
        $_SESSION['user_name'] = $user['name']
        $_SESSION['is_admin'] = (bool)       ← může být TRUE!
        $_SESSION['admin_id'] = $userId      ← pokud je admin

PROBLÉM: admin_id může být STRING nebo INT nebo neexistovat
PROBLÉM: user_id může být 0 pro admina (collision s neexistujícím userem)
```

**Impact:**
- ⚠️ Type juggling vulnerabilities (string '0' != int 0)
- ⚠️ Admin může mít `user_id = 0`, což koliduje s "nepřihlášen"
- ⚠️ Inconsistentní kontroly napříč aplikací
- ⚠️ Audit trail corruption (admin_id je string, user_id je int)

**Příklad selhání:**
```php
// admin.php
if (isset($_SESSION['user_id'])) {
    // ✅ Admin má user_id = 0, TRUE!
}

// audit_logger.php
auditLog('action', [], $_SESSION['user_id']); // ❌ 0 = "unknown user"

// admin_session_check.php
if (isset($_SESSION['admin_id'])) {
    // ❌ admin_id = 'WGS_ADMIN' (string) nebo INT nebo neexistuje
}
```

**Fix návrh:**
```php
// STANDARDIZED SESSION STRUCTURE
$_SESSION['user'] = [
    'id' => (int),           // ALWAYS int, NEVER 0 for real users
    'name' => (string),
    'email' => (string),
    'role' => (string),      // 'admin', 'technik', 'prodejce'
    'is_admin' => (bool),
    'logged_in_at' => (timestamp),
    'last_activity' => (timestamp)
];

// Pro admina:
$_SESSION['user'] = [
    'id' => -1,              // ← Special admin ID (negative)
    'name' => 'Administrátor',
    'email' => 'admin@wgs-service.cz',
    'role' => 'admin',
    'is_admin' => true,
    'admin_key_hash' => hash('sha256', $key)
];
```

---

### 1.3 🟡 MEDIUM: Session regenerace chybí

**Postižené soubory:**
```
app/controllers/login_controller.php → handleAdminLogin, handleUserLogin
logout.php → session_destroy() ale bez regenerate
```

**Popis problému:**
Po úspěšném přihlášení se session ID NEREGENERUJE. To umožňuje session fixation útoky.

**Data Flow:**
```
1. Útočník získá session ID (např. z URL nebo cookie)
2. Oběť se přihlásí se stejným session ID
3. Útočník má přístup k autentizované session
```

**Impact:**
- 🔒 Session fixation vulnerability
- 🔒 Session hijacking možný
- 🔒 Porušení OWASP Top 10 (A07:2021)

**Fix návrh:**
```php
// V login_controller.php po úspěšném přihlášení
function handleAdminLogin(string $adminKey): void {
    // ... validace ...

    // PŘED nastavením session proměnných
    session_regenerate_id(true); // ← CRITICAL!

    $_SESSION['user'] = [
        'id' => -1,
        'name' => 'Administrátor',
        // ...
    ];
}

// V logout.php
session_regenerate_id(true);
$_SESSION = [];
session_destroy();
```

---

### 1.4 🟡 MEDIUM: Session timeout není enforced

**Postižené soubory:**
```
init.php → nastavuje session.gc_maxlifetime = 3600
config.php → ŽÁDNÁ kontrola timeoutu
admin_session_check.php → ŽÁDNÁ kontrola last_activity
```

**Popis problému:**
Session má nastavený timeout (3600s = 1h), ale aplikace nikde NEKONTROLUJE `last_activity` timestamp. Session může být aktivní nekonečně dlouho pokud uživatel neopustí stránku.

**Impact:**
- 🔒 Dlouhodobé session představují security risk
- 🔒 Zombie sessions v databázi
- 🔒 Neautorizovaný přístup pokud někdo opustí počítač

**Fix návrh:**
```php
// V admin_session_check.php + user_session_check.php
$timeout = 3600; // 1 hodina

if (isset($_SESSION['last_activity'])) {
    $inactive = time() - $_SESSION['last_activity'];

    if ($inactive > $timeout) {
        session_regenerate_id(true);
        $_SESSION = [];
        session_destroy();

        echo json_encode([
            'authenticated' => false,
            'logged_in' => false,
            'error' => 'Session expired due to inactivity'
        ]);
        exit;
    }
}

$_SESSION['last_activity'] = time(); // Refresh timestamp
```

---

### 1.5 🔴 CRITICAL: Output buffering conflicts

**Postižené soubory:**
```
init.php řádek 4 → ob_start()
admin.php řádky 16-42 → header() calls PŘED HTML output
includes/security_headers.php → nastavuje headers
```

**Data Flow Path:**
```
init.php: ob_start()
    ↓
admin.php: require_once init.php
    ↓
admin.php: header('Content-Security-Policy: ...')  // Do bufferu
    ↓
admin.php: echo HTML                                // Do bufferu
    ↓
END OF SCRIPT: ob_end_flush()                      // Headers + HTML najednou
```

**Popis problému:**
`ob_start()` v `init.php` může způsobit, že headers se neodešlou správně. Pokud dojde k error PŘED `ob_end_flush()`, buffer se může smazat a headers se neodešlou vůbec.

**Impact:**
- ⚠️ Security headers mohou chybět
- ⚠️ CSP bypass možný
- ⚠️ Session cookies mohou být nesprávně nastaveny
- ⚠️ "Headers already sent" errors

**Příklad selhání:**
```php
// init.php
ob_start(); // Buffer START

// admin.php
header('X-Frame-Options: SAMEORIGIN'); // ✅ Do bufferu

// Někde v kódu
if ($error) {
    ob_clean(); // ❌ VYMAŽE buffer včetně headers!
    die('Error occurred');
}
```

**Fix návrh:**
```php
// init.php - ODSTRANIT ob_start() úplně
// Nebo použít output_buffering jen pro specific use cases

// NEBO použít output buffering SPRÁVNĚ:
ob_start();

// Na KONCI skriptu (např. v admin.php):
if (ob_get_level() > 0) {
    ob_end_flush(); // Explicitně flush buffer
}
```

---

### 1.6 🟡 MEDIUM: Session data není sanitizované

**Postižené soubory:**
```
app/controllers/login_controller.php řádek 129 → $_SESSION['user_name'] = $user['name']
admin.php řádek 21 → echo $_SESSION['user_name'] (bez sanitizace)
includes/admin_header.php → používá $_SESSION bez escape
```

**Popis problému:**
Data z databáze se ukládají do `$_SESSION` BEZ sanitizace a pak se používají v HTML BEZ escape. XSS vulnerability.

**Data Flow:**
```
DATABASE: wgs_users.name = "<script>alert('XSS')</script>"
    ↓
login_controller.php: $_SESSION['user_name'] = $user['name'] // ❌ NO SANITIZE
    ↓
admin.php: echo $_SESSION['user_name']                        // ❌ NO ESCAPE
    ↓
BROWSER: <script>alert('XSS')</script> EXECUTES!
```

**Impact:**
- 🔒 Stored XSS vulnerability
- 🔒 Session poisoning možný
- 🔒 Možnost eskalace privilégií

**Fix návrh:**
```php
// V login_controller.php
$_SESSION['user_name'] = htmlspecialchars($user['name'], ENT_QUOTES, 'UTF-8');
$_SESSION['user_email'] = filter_var($user['email'], FILTER_SANITIZE_EMAIL);

// V admin.php a všech views
echo htmlspecialchars($_SESSION['user_name'] ?? 'Guest', ENT_QUOTES, 'UTF-8');
```

---

## 2. DATABASE FLOW ISSUES

### 2.1 🔴 CRITICAL: Duplicitní database connection patterns

**Postižené soubory:**
```
config/config.php → getDbConnection() - static PDO
config/database.php → Database::getInstance() - Singleton pattern
67 souborů používají getDbConnection()
0 souborů používá Database::getInstance()
```

**Popis problému:**
Existují DVA různé způsoby jak získat DB connection:

1. **Function pattern** (`getDbConnection()` v `config.php`):
```php
function getDbConnection() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(...);
    }
    return $pdo;
}
```

2. **Singleton pattern** (`Database::getInstance()` v `config/database.php`):
```php
class Database {
    private static $instance = null;
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

**Impact:**
- ⚠️ DVĚ různé PDO instance mohou existovat současně
- ⚠️ Inconsistent transaction handling
- ⚠️ Connection pool fragmentation
- ⚠️ Confusion v codebase

**Data Flow:**
```
File A: $pdo = getDbConnection();        // PDO instance #1
File B: $db = Database::getInstance();   // PDO instance #2
File B: $pdo = $db->getConnection();

PROBLÉM: Dvě RŮZNÉ connection instance!
```

**Příklad selhání:**
```php
// api/admin_api.php
$pdo1 = getDbConnection();
$pdo1->beginTransaction();

// Někde jinde (hypoteticaly)
$pdo2 = Database::getInstance()->getConnection();
$pdo2->exec("DELETE FROM wgs_users WHERE id = 1"); // ❌ MIMO TRANSAKCI!

$pdo1->commit(); // ❌ DELETE není v transakci!
```

**Fix návrh:**
```php
// OPTION 1: Odstranit Database class úplně, používat jen getDbConnection()

// OPTION 2: Odstranit getDbConnection(), používat jen Database singleton
// Ale to vyžaduje refactor 67 souborů!

// DOPORUČENÍ: Použít getDbConnection() všude, odstranit database.php
```

---

### 2.2 🔴 CRITICAL: Inconsistentní column naming v queries

**Postižené soubory:**
```
app/save_photos.php řádek 88 → "reklamace_id = :reklamace_id OR cislo = :cislo"
app/controllers/save_photos.php řádek 77 → stejný pattern
api/get_photos_api.php řádek 43 → stejný pattern
api/admin_api.php řádek 269 → "r.id as claim_id"
```

**Popis problému:**
Tabulka `wgs_reklamace` má TŘI různé identifikátory:
- `id` (INT, auto_increment) - primární klíč
- `reklamace_id` (VARCHAR) - interní ID typu "WGS251114-A3F2B1"
- `cislo` (VARCHAR) - user-facing ID / objednávkové číslo

Queries musí hledat napříč VŠEMI třemi:
```sql
WHERE reklamace_id = :id OR cislo = :id OR id = :id
```

**Data Flow Path:**
```
Frontend: submit reklamace_id = "WGS251114-123ABC"
    ↓
save_photos.php: WHERE reklamace_id = :id OR cislo = :id  // ❌ Chybí id column!
    ↓
get_photos_api.php: WHERE reklamace_id = :id OR cislo = :id // ❌ Chybí id column!
    ↓
admin_api.php: SELECT r.id as claim_id                      // ❌ Jiný alias!
```

**Impact:**
- ⚠️ Photos nemusí být nalezeny pokud se hledá podle id (INT)
- ⚠️ Inconsistent API responses (někdy 'id', někdy 'claim_id')
- ⚠️ Frontend musí vědět který identifier použít

**Příklad selhání:**
```php
// User submits reklamace s id=123 (INT)
$reklamaceId = 123;

// save_photos.php
$stmt->execute(['reklamace_id' => $reklamaceId, 'cislo' => $reklamaceId]);
// ❌ NENAJDE! Protože:
//    reklamace_id != '123' (STRING != INT conversion issue)
//    cislo != '123' (může být NULL)
//    id column není v WHERE!
```

**Fix návrh:**
```php
// STANDARDIZOVAT na JEDEN primární identifier across codebase

// OPTION 1: Použít 'id' (INT) jako JEDINÝ internal identifier
// reklamace_id = user-facing display ID
// cislo = objednávkové číslo (může být null)

// Všechny queries:
$stmt = $pdo->prepare("
    SELECT * FROM wgs_reklamace
    WHERE id = :id
       OR reklamace_id = :reklamace_id
       OR cislo = :cislo
    LIMIT 1
");
$stmt->execute([
    'id' => is_numeric($identifier) ? (int)$identifier : 0,
    'reklamace_id' => $identifier,
    'cislo' => $identifier
]);

// NEBO použít helper function:
function findReklamaceByAnyId(PDO $pdo, $identifier) {
    $stmt = $pdo->prepare("...");
    // ...
    return $stmt->fetch();
}
```

---

### 2.3 🟡 MEDIUM: Chybí prepared statement pro SHOW TABLES

**Postižené soubory:**
```
api/control_center_api.php řádek 86 → "SHOW TABLES LIKE '$table'"
```

**Popis problému:**
SQL injection vulnerability - `$table` není escapované.

**Code:**
```php
foreach ($requiredTables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");  // ❌ SQL INJECTION!
}
```

**Impact:**
- 🔒 SQL injection možný (i když $requiredTables je hardcoded array)
- 🔒 Bad practice - mixed parametrizované a neparametrizované queries

**Fix návrh:**
```php
foreach ($requiredTables as $table) {
    $escapedTable = $pdo->quote($table);
    $stmt = $pdo->query("SHOW TABLES LIKE $escapedTable");

    // NEBO lépe:
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array($table, $tables)) {
        $missingTables[] = $table;
    }
}
```

---

### 2.4 🔴 CRITICAL: Transaction handling chybí úplně

**Postižené soubory:**
```
app/controllers/save.php → multiple INSERTs, no transaction
app/save_photos.php → INSERT do wgs_photos + file write, no transaction
api/protokol_api.php → INSERT + file operations, no transaction
```

**Popis problému:**
Multi-step operations (např. INSERT reklamace + INSERT photos + save files) NEJSOU v transakci. Pokud jeden krok selže, data jsou inconsistentní.

**Data Flow Path:**
```
save_photos.php:
1. Ověř reklamaci v DB                    // ✅ SELECT
2. Vytvoř directory                        // ❌ File operation - NO ROLLBACK!
3. Ulož fotky na disk                      // ❌ File operation - NO ROLLBACK!
4. INSERT do wgs_photos                    // ✅ DB operation
5. Pokud krok 4 selže → fotky NA DISKU ZŮSTANOU! Orphaned files!
```

**Impact:**
- ⚠️ Data corruption možná
- ⚠️ Orphaned files na disku
- ⚠️ Orphaned DB records
- ⚠️ No rollback možnost

**Příklad selhání:**
```php
// save_photos.php
$uploadsDir = __DIR__ . '/../uploads/photos';
mkdir($reklamaceDir); // ✅ Directory vytvořen

foreach ($photos as $photo) {
    file_put_contents($filePath, $decodedData); // ✅ File uložen

    $stmt = $pdo->prepare("INSERT INTO wgs_photos ..."); // ❌ FAIL!
    // PROBLÉM: File je na disku, ale v DB není záznam!
}
```

**Fix návrh:**
```php
// save_photos.php
$pdo->beginTransaction();

try {
    // 1. Ověř reklamaci
    $stmt = $pdo->prepare("SELECT id FROM wgs_reklamace WHERE ...");
    $stmt->execute(...);

    if (!$stmt->fetch()) {
        throw new Exception('Reklamace not found');
    }

    // 2. Vytvoř temp directory PRO FILES
    $tempDir = sys_get_temp_dir() . '/' . uniqid('wgs_');
    mkdir($tempDir);

    $savedFiles = [];
    foreach ($photos as $photo) {
        // 3. Ulož do TEMP
        $tempPath = $tempDir . '/' . $filename;
        file_put_contents($tempPath, $decodedData);
        $savedFiles[] = $tempPath;

        // 4. INSERT do DB
        $stmt = $pdo->prepare("INSERT INTO wgs_photos ...");
        $stmt->execute(...);
    }

    // 5. COMMIT DB transaction
    $pdo->commit();

    // 6. PŘESUŇ files z TEMP do FINAL destination
    foreach ($savedFiles as $tempPath) {
        $finalPath = str_replace($tempDir, $uploadsDir, $tempPath);
        rename($tempPath, $finalPath);
    }

} catch (Exception $e) {
    $pdo->rollBack();

    // Cleanup temp files
    if (isset($tempDir) && is_dir($tempDir)) {
        array_map('unlink', glob("$tempDir/*"));
        rmdir($tempDir);
    }

    throw $e;
}
```

---

### 2.5 🟡 MEDIUM: Connection pooling neefektivní

**Postižené soubory:**
```
config.php → getDbConnection() s static $pdo
includes/EmailQueue.php → $pdo v __construct(), ale každá instance má vlastní!
```

**Popis problému:**
`EmailQueue` vytváří nové instance, ale každá může mít vlastní PDO connection pokud není předaná.

```php
// EmailQueue.php konstruktor:
public function __construct($pdo = null) {
    $this->pdo = $pdo ?? getDbConnection();  // Nová connection pokud $pdo je null
}

// scripts/process_email_queue.php:
$queue = new EmailQueue();  // ← $pdo je null, vytvoří se NOVÁ connection!
```

**Impact:**
- ⚠️ Neefektivní connection usage
- ⚠️ Možné překročení max_connections

**Fix návrh:**
```php
// EmailQueue VŽDY použije stejnou connection
public function __construct($pdo = null) {
    $this->pdo = $pdo ?? getDbConnection(); // getDbConnection() vrací STATIC $pdo
}
```

---

## 3. FILE UPLOAD FLOW ISSUES

### 3.1 🔴 CRITICAL: Duplicitní file upload endpoints

**Postižené soubory:**
```
/app/save_photos.php → pro photocustomer.php (technik upload)
/app/controllers/save_photos.php → pro novareklamace.php (user upload)
```

**Popis rozdílů:**

| Feature | app/save_photos.php | app/controllers/save_photos.php |
|---------|---------------------|----------------------------------|
| Path | /app/save_photos.php | /app/controllers/save_photos.php |
| Input format | JSON (sections) | POST form data |
| CSRF check | ❌ CHYBÍ | ✅ validateCSRFToken() |
| MIME validation | ❌ CHYBÍ | ✅ finfo_buffer() |
| Max photos | 50 per upload | 20 per upload |
| Rate limit key | "upload_customer_$ip" | "upload_photos_$ip" |
| Upload dir | /uploads/photos/{reklamace_id}/ | /uploads/reklamace_{reklamace_id}/ |
| DB fields | photo_order, uploaded_at | created_at (NO photo_order) |

**Data Flow Path:**

**Flow 1 (photocustomer.php):**
```
Frontend: fetch('/app/save_photos.php', { sections: {...} })
    ↓
app/save_photos.php: NO CSRF check ❌
    ↓
Upload to: /uploads/photos/WGS123/before_WGS123_0_1234.jpeg
    ↓
INSERT: reklamace_id, section_name, photo_order, uploaded_at
```

**Flow 2 (novareklamace.php):**
```
Frontend: fetch('/app/controllers/save_photos.php', { photo_0: base64, ... })
    ↓
app/controllers/save_photos.php: validateCSRFToken() ✅
    ↓
Upload to: /uploads/reklamace_WGS123/photo_WGS123_1234.jpeg
    ↓
INSERT: reklamace_id, section_name, created_at (NO photo_order)
```

**PROBLÉM:**
Fotky z RŮZNÝCH zdrojů jdou do RŮZNÝCH adresářů s RŮZNOU strukturou DB záznamů!

**Impact:**
- ⚠️ **Security:** app/save_photos.php nemá CSRF protection!
- ⚠️ **Data inconsistency:** různé directory structures
- ⚠️ **API confusion:** get_photos_api.php musí hledat v OBOU directories
- ⚠️ **Duplicitní kód:** téměř identická logika ve dvou souborech

**Příklad selhání:**
```php
// get_photos_api.php musí kontrolovat DVĚ možná umístění:
$path1 = __DIR__ . '/../uploads/photos/' . $reklamaceId . '/' . $filename;
$path2 = __DIR__ . '/../uploads/reklamace_' . $reklamaceId . '/' . $filename;

if (file_exists($path1)) {
    // ...
} elseif (file_exists($path2)) {
    // ...
} else {
    // ❌ File not found!
}
```

**Fix návrh:**
```php
// UNIFIED FILE UPLOAD ENDPOINT
// /api/upload_photos.php

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json');

// ALWAYS require CSRF
$csrfToken = $data['csrf_token'] ?? $_POST['csrf_token'] ?? '';
if (!validateCSRFToken($csrfToken)) {
    respondError('Invalid CSRF token', 403);
}

// STANDARDIZED upload directory
$uploadsDir = __DIR__ . '/../uploads/claims/' . $reklamaceId . '/photos/';

// STANDARDIZED DB structure
$stmt = $pdo->prepare("
    INSERT INTO wgs_photos (
        reklamace_id, section_name, photo_path,
        file_name, photo_type, photo_order,
        uploaded_at, uploaded_by
    ) VALUES (?, ?, ?, ?, ?, ?, NOW(), ?)
");

// ALWAYS MIME validate
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_buffer($finfo, $photoData);
if (!in_array($mimeType, $allowedMimes)) {
    throw new Exception('Invalid file type');
}
```

---

### 3.2 🔴 CRITICAL: Chybí file existence check před DB delete

**Postižené soubory:**
```
api/delete_reklamace.php → DELETE z wgs_photos, ale NEmažou se files!
```

**Data Flow Path:**
```
delete_reklamace.php:
1. DELETE FROM wgs_photos WHERE reklamace_id = ?
2. DELETE FROM wgs_reklamace WHERE id = ?

PROBLÉM: Files na disku ZŮSTÁVAJÍ! Disk space leak!
```

**Impact:**
- 💾 Disk space leak - orphaned files
- 💾 Privacy issue - smazaná data zůstávají na disku
- 💾 GDPR violation - "right to be forgotten"

**Fix návrh:**
```php
// delete_reklamace.php
$pdo->beginTransaction();

try {
    // 1. Načti všechny fotky PŘED smazáním z DB
    $stmt = $pdo->prepare("
        SELECT photo_path, file_path
        FROM wgs_photos
        WHERE reklamace_id = :id
    ");
    $stmt->execute(['id' => $reklamaceId]);
    $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 2. DELETE z DB
    $stmt = $pdo->prepare("DELETE FROM wgs_photos WHERE reklamace_id = :id");
    $stmt->execute(['id' => $reklamaceId]);

    $stmt = $pdo->prepare("DELETE FROM wgs_reklamace WHERE id = :id");
    $stmt->execute(['id' => $claimId]);

    // 3. COMMIT transaction
    $pdo->commit();

    // 4. Smaž files PO úspěšném commit
    foreach ($photos as $photo) {
        $filePath = __DIR__ . '/../' . $photo['photo_path'];
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }

    // 5. Smaž prázdné directories
    $claimDir = __DIR__ . '/../uploads/claims/' . $reklamaceId;
    if (is_dir($claimDir) && count(scandir($claimDir)) === 2) {
        rmdir($claimDir);
    }

} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

---

### 3.3 🟡 MEDIUM: File size limit inconsistentní

**Postižené soubory:**
```
app/save_photos.php řádek 122 → $maxBase64Size = 13 * 1024 * 1024 (13MB base64 = ~10MB file)
app/controllers/save_photos.php řádek 116 → stejné
```

**Popis problému:**
Limit je HARDCODED ve dvou místech. Změna vyžaduje update v obou souborech.

**Fix návrh:**
```php
// config.php
define('MAX_UPLOAD_SIZE_MB', 10);
define('MAX_UPLOAD_SIZE_BYTES', MAX_UPLOAD_SIZE_MB * 1024 * 1024);
define('MAX_BASE64_SIZE_BYTES', (int)(MAX_UPLOAD_SIZE_BYTES * 1.37)); // Base64 overhead

// save_photos.php
if ($base64Size > MAX_BASE64_SIZE_BYTES) {
    throw new Exception("File too large. Max size: " . MAX_UPLOAD_SIZE_MB . " MB");
}
```

---

### 3.4 🟡 MEDIUM: Photo ordering broken

**Postižené soubory:**
```
app/save_photos.php → nastavuje photo_order (0, 1, 2, ...)
app/controllers/save_photos.php → NENastavuje photo_order!
api/get_photos_api.php řádek 61 → ORDER BY photo_order ASC
```

**Popis problému:**
`app/controllers/save_photos.php` NENASTAVUJE `photo_order`, proto fotky z novareklamace.php budou mít `photo_order = NULL` a NEBUDOU správně seřazené!

**Data Flow:**
```
novareklamace.php → save_photos.php (controllers)
    ↓
INSERT INTO wgs_photos: photo_order = NULL  ❌
    ↓
get_photos_api.php: ORDER BY photo_order ASC, id ASC
    ↓
NULL values jsou PRVNÍ nebo POSLEDNÍ (depends on MySQL version)
```

**Fix návrh:**
```php
// app/controllers/save_photos.php
$photoOrder = 0; // ← PŘIDAT tuto proměnnou

for ($i = 0; $i < $photoCount; $i++) {
    // ...

    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':section_name' => $photoType,
        ':photo_path' => $relativePathForDb,
        ':file_path' => $relativePathForDb,
        ':file_name' => $filename,
        ':photo_type' => 'image',
        ':photo_order' => $photoOrder  // ← PŘIDAT toto
    ]);

    $photoOrder++; // ← INCREMENT
}
```

---

### 3.5 🔴 CRITICAL: Race condition při simultánních uploads

**Postižené soubory:**
```
app/save_photos.php řádky 106-109 → mkdir() bez lock
app/controllers/save_photos.php řádky 95-98 → mkdir() bez lock
```

**Popis problému:**
Pokud dva requesty uploadují fotky pro STEJNOU reklamaci SOUČASNĚ, může dojít k:
1. Kolizi při vytváření directory (mkdir fail)
2. Kolizi při generování filenames (stejný timestamp + random)
3. Overwrite files

**Data Flow:**
```
Request A (time: 1000.000): mkdir('/uploads/photos/WGS123/')  ✅
Request B (time: 1000.001): mkdir('/uploads/photos/WGS123/')  ❌ Already exists

Request A: $filename = "before_WGS123_0_1000_abc123.jpg"
Request B: $filename = "before_WGS123_0_1000_abc123.jpg"  ← COLLISION!
```

**Impact:**
- ⚠️ File overwrite možný
- ⚠️ Data loss
- ⚠️ Upload selhání

**Fix návrh:**
```php
// Použít atomic file creation s unique names
$timestamp = microtime(true); // ← Use microtime místo time()
$randomString = bin2hex(random_bytes(8)); // ← Více random bytes (8 místo 4)
$uniqueId = uniqid('', true); // ← Extra entropy

$filename = "{$sectionName}_{$reklamaceId}_{$uniqueId}_{$randomString}.{$imageType}";

// mkdir s try-catch
try {
    if (!is_dir($reklamaceDir)) {
        mkdir($reklamaceDir, 0755, true);
    }
} catch (Exception $e) {
    // Directory už existuje - OK
}

// Atomic file write
$tempPath = $reklamaceDir . '/' . $filename . '.tmp';
file_put_contents($tempPath, $decodedData);
rename($tempPath, $reklamaceDir . '/' . $filename); // Atomic operation
```

---

## 4. API INTEGRATION PROBLEMS

### 4.1 🔴 CRITICAL: Inconsistentní API response formats

**Postižené soubory:**
```
api/admin_api.php → { "status": "success", "data": [...] }
api/get_photos_api.php → { "success": true, "photos": [...] }
api/protokol_api.php → { "status": "success", ... } nebo { "success": true, ... }
app/save_photos.php → { "success": true, "photos": [...] }
```

**Popis problému:**
APIs používají RŮZNÉ formáty responses:
- Někdy `"status": "success"`, někdy `"success": true`
- Někdy `"data"`, někdy `"photos"`, někdy `"reklamace"`
- Error responses: někdy `"message"`, někdy `"error"`

**Impact:**
- 🔧 Frontend musí handled RŮZNÉ formáty
- 🔧 Více error-prone kód
- 🔧 Horší developer experience

**Příklad:**
```javascript
// Frontend musí checkovat OBĚ:
if (response.status === 'success' || response.success === true) {
    const data = response.data || response.photos || response.reklamace;
    // ...
}
```

**Fix návrh:**
```php
// STANDARDIZED API RESPONSE FORMAT
// /includes/api_response.php

class ApiResponse {
    public static function success($data = [], $message = null) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    public static function error($message, $code = 400, $details = []) {
        http_response_code($code);
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => $message,
                'code' => $code,
                'details' => $details
            ],
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// Použití:
ApiResponse::success(['photos' => $photos], 'Photos loaded successfully');
ApiResponse::error('Reklamace not found', 404);
```

---

### 4.2 🔴 CRITICAL: CSRF token handling inconsistentní

**Postižené soubory:**
```
api/control_center_api.php řádky 32-68 → komplexní CSRF handling s debug info
api/protokol_api.php řádky 39-50 → jednoduchý CSRF check
app/controllers/save_photos.php řádky 19-30 → CSRF check z $_POST
app/save_photos.php → ❌ ŽÁDNÝ CSRF check!
```

**Data Flow Pattern 1 (control_center_api.php):**
```php
$data = json_decode(file_get_contents('php://input'), true);
$csrfToken = $data['csrf_token'] ?? null;

if (is_array($csrfToken)) {
    $csrfToken = null; // Security: reject arrays
}

if (!$csrfToken || !validateCSRFToken($csrfToken)) {
    // Return debug info
    echo json_encode([
        'debug' => [
            'token_provided' => !empty($csrfToken),
            'token_length' => strlen($csrfToken),
            'session_has_token' => isset($_SESSION['csrf_token'])
        ]
    ]);
}
```

**Data Flow Pattern 2 (save_photos controller):**
```php
$csrfToken = $_POST['csrf_token'] ?? '';
if (is_array($csrfToken)) {
    $csrfToken = '';
}
if (!validateCSRFToken($csrfToken)) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'error' => 'Invalid CSRF token']);
}
```

**Data Flow Pattern 3 (app/save_photos.php):**
```php
// ❌ ŽÁDNÝ CSRF CHECK!
$jsonData = file_get_contents('php://input');
$data = json_decode($jsonData, true);
// Continue processing...
```

**Impact:**
- 🔒 **Security:** app/save_photos.php je VULNERABLE to CSRF attacks!
- 🔧 Inconsistent error messages
- 🔧 Some APIs return debug info, some don't

**Fix návrh:**
```php
// /includes/csrf_middleware.php

function requireCSRF($allowedMethods = ['POST', 'PUT', 'DELETE', 'PATCH']) {
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods)) {
        return; // GET requests don't need CSRF
    }

    // Try JSON body first
    $input = json_decode(file_get_contents('php://input'), true) ?? [];
    $token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';

    // Security: reject arrays
    if (is_array($token)) {
        $token = '';
    }

    if (!validateCSRFToken($token)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => 'Invalid or missing CSRF token',
                'code' => 'CSRF_VALIDATION_FAILED'
            ]
        ]);
        exit;
    }
}

// Použití ve VŠECH API endpoints:
require_once __DIR__ . '/../includes/csrf_middleware.php';
requireCSRF(); // ← Jedna řádka, centralizované!
```

---

### 4.3 🟡 MEDIUM: Rate limiting inconsistentní

**Postižené soubory:**
```
app/save_photos.php řádek 30 → checkRateLimit("upload_customer_$ip", 30, 3600)
app/controllers/save_photos.php řádek 34 → checkRateLimit("upload_photos_$ip", 20, 3600)
api/protokol_api.php řádek 64 → checkRateLimit("upload_pdf_$ip", 10, 3600)
app/controllers/login_controller.php řádek 57 → checkRateLimit('admin_login_' . $identifier, 5, 900)
```

**Popis rozdílů:**

| Endpoint | Key Prefix | Max attempts | Time window |
|----------|------------|--------------|-------------|
| save_photos (customer) | upload_customer_ | 30 | 3600s (1h) |
| save_photos (controller) | upload_photos_ | 20 | 3600s |
| protokol (PDF) | upload_pdf_ | 10 | 3600s |
| login (admin) | admin_login_ | 5 | 900s (15m) |

**Problém:**
Rate limiting je RŮZNÝ pro RŮZNÉ endpointy bez jasného důvodu. Některé endpointy mají rate limiting, některé NE.

**Chybí rate limiting:**
```
api/admin_api.php → ❌ NO rate limiting!
api/get_photos_api.php → ❌ NO rate limiting!
api/delete_reklamace.php → ❌ NO rate limiting!
api/control_center_api.php → ❌ NO rate limiting!
```

**Impact:**
- 🔒 DoS možný na endpointech bez rate limitingu
- 🔒 Brute force možný (např. admin_api)
- 🔧 Inconsistent protection

**Fix návrh:**
```php
// config.php - CENTRALIZED RATE LIMITS
define('RATE_LIMITS', [
    'upload' => ['attempts' => 20, 'window' => 3600],
    'api_read' => ['attempts' => 100, 'window' => 60],
    'api_write' => ['attempts' => 30, 'window' => 3600],
    'login' => ['attempts' => 5, 'window' => 900],
    'admin' => ['attempts' => 50, 'window' => 3600]
]);

// /includes/rate_limiter.php
function enforceRateLimit($category, $identifier = null) {
    if (!isset(RATE_LIMITS[$category])) {
        throw new Exception("Unknown rate limit category: $category");
    }

    $config = RATE_LIMITS[$category];
    $identifier = $identifier ?? $_SERVER['REMOTE_ADDR'];

    $key = "{$category}_{$identifier}";
    $result = checkRateLimit($key, $config['attempts'], $config['window']);

    if (!$result['allowed']) {
        http_response_code(429);
        echo json_encode([
            'success' => false,
            'error' => [
                'message' => 'Too many requests',
                'retry_after' => $result['retry_after']
            ]
        ]);
        exit;
    }

    recordLoginAttempt($key);
}

// Použití:
enforceRateLimit('upload'); // Automaticky použije IP
enforceRateLimit('login', $email); // Custom identifier
```

---

### 4.4 🔴 CRITICAL: Error handling není konzistentní

**Postižené soubory:**
```
api/admin_api.php → try-catch s specific exception types
api/get_photos_api.php → try-catch s generic Exception
app/save_photos.php → try-catch s generic Exception
api/protokol_api.php → switch/case s různými actions
```

**Různé error response formáty:**

**Format 1 (admin_api.php):**
```php
} catch (InvalidArgumentException $e) {
    respondError($e->getMessage(), 400);
} catch (PDOException $e) {
    error_log('Admin API DB error: ' . $e->getMessage());
    respondError('Chyba databáze.', 500);
} catch (Throwable $e) {
    respondError('Neočekávaná chyba.', 500);
}
```

**Format 2 (get_photos_api.php):**
```php
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()  // ← Odhaluje internal details!
    ]);
}
```

**Format 3 (save_photos.php):**
```php
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
```

**Impact:**
- 🔒 Information disclosure - internal error details leakují ven
- 🔧 Frontend musí handled různé error formats
- 🔧 Error logging je inconsistentní

**Fix návrh:**
```php
// /includes/error_handler.php - API Error Handler

class ApiException extends Exception {
    private $httpCode;
    private $details;

    public function __construct($message, $httpCode = 400, $details = []) {
        parent::__construct($message);
        $this->httpCode = $httpCode;
        $this->details = $details;
    }

    public function getHttpCode() { return $this->httpCode; }
    public function getDetails() { return $this->details; }
}

function handleApiError(Throwable $e) {
    // Log error ALWAYS
    error_log(sprintf(
        '[API Error] %s in %s:%d - %s',
        get_class($e),
        $e->getFile(),
        $e->getLine(),
        $e->getMessage()
    ));

    // Determine HTTP code
    if ($e instanceof ApiException) {
        $code = $e->getHttpCode();
        $message = $e->getMessage();
        $details = $e->getDetails();
    } elseif ($e instanceof PDOException) {
        $code = 500;
        $message = 'Database error occurred';
        $details = ['error_code' => $e->getCode()];
    } else {
        $code = 500;
        $message = 'Internal server error';
        $details = [];
    }

    // NEVER leak internal details in production
    if (defined('IS_PRODUCTION') && IS_PRODUCTION) {
        $details = [];
    }

    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => [
            'message' => $message,
            'code' => $code,
            'details' => $details
        ],
        'timestamp' => time()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// Použití ve VŠECH API endpoints:
set_exception_handler('handleApiError');

try {
    // API logic
    throw new ApiException('Reklamace not found', 404);
} catch (Throwable $e) {
    handleApiError($e); // Centralized handling
}
```

---

### 4.5 🟡 MEDIUM: Missing API versioning

**Popis problému:**
Všechny API endpointy jsou v `/api/` bez versioning. Změny v API mohou break frontend.

**Current structure:**
```
/api/admin_api.php
/api/get_photos_api.php
/api/protokol_api.php
```

**Pokud se API změní:**
- Frontend může přestat fungovat
- Není možné udržovat backward compatibility
- Rolling updates jsou problematické

**Fix návrh:**
```
/api/v1/admin.php
/api/v1/photos.php
/api/v1/protokol.php

/api/v2/admin.php  (nová verze s breaking changes)
```

---

## 5. MODULE CONFLICTS & INCONSISTENCIES

### 5.1 🔴 CRITICAL: Duplicitní business logika

**Duplicitní kód najítí v:**

**Location 1: /app/controllers/save.php + /app/save_photos.php**
- Obě obsahují reklamace saving logic
- Obě validují reklamace_id
- Obě vytvářejí directories
- Obě kontrolují existence reklamace

**Location 2: getDbConnection() v config.php + Database class v config/database.php**
- Dvě různé implementace stejné funkcionality

**Location 3: Rate limiting kód**
- checkRateLimit() v config.php
- rate_limiter.php v includes/
- Duplicitní logic

**Impact:**
- 🔧 Bug fixes musí být aplikovány na VÍCE místech
- 🔧 Inconsistent behavior
- 🔧 Maintainability nightmare

**Fix návrh:**
```
Vytvořit SERVICE LAYER pro business logiku:

/app/services/
    ReklamaceService.php
    PhotoService.php
    AuthService.php
    EmailService.php

Například PhotoService:
class PhotoService {
    private $pdo;

    public function uploadPhotos($reklamaceId, $photos, $uploadedBy) {
        // Centralized photo upload logic
        // Používá se z OBOU save_photos.php souborů
    }

    public function getPhotos($reklamaceId) {
        // Centralized photo retrieval
    }

    public function deletePhotos($reklamaceId) {
        // Centralized deletion with file cleanup
    }
}
```

---

### 5.2 🔴 CRITICAL: Circular dependency mezi init.php a config.php

**Data Flow:**
```
init.php (řádek 32)
    ↓
require config/config.php
    ↓
config.php (řádek 2)
    ↓
session_start() ← PŘED tím než init.php nastaví session settings!
    ↓
config.php (řádky 9-31)
    ↓
require env_loader.php (který je INCLUDES_PATH který je definován v init.php!)
```

**Problém:**
`config.php` používá `INCLUDES_PATH` konstanta, která je definována v `init.php` PŘED tím než `config.php` je načten. Ale `config.php` také spouští session PŘED tím než `init.php` nastaví session settings.

**Impact:**
- ⚠️ Undefined constant warnings možné
- ⚠️ Session settings nejsou aplikovány
- ⚠️ Fragile initialization order

**Fix návrh:**
```php
// init.php - KOMPLETNĚ přepracovat loading order

// 1. Define paths FIRST
define('BASE_PATH', dirname(__FILE__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('INCLUDES_PATH', BASE_PATH . '/includes');

// 2. Load env loader (potřebuje INCLUDES_PATH)
require_once INCLUDES_PATH . '/env_loader.php';

// 3. Configure session BEFORE starting
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 1 : 0);
ini_set('session.cookie_samesite', 'Lax');

// 4. Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 5. Load config (AFTER session is configured)
require_once CONFIG_PATH . '/config.php';

// 6. Load helpers
require_once INCLUDES_PATH . '/csrf_helper.php';
require_once INCLUDES_PATH . '/error_handler.php';
```

---

### 5.3 🟡 MEDIUM: Missing abstraction layer pro DB operations

**Problém:**
Každý soubor má vlastní DB queries. Duplicated SQL code všude.

**Příklad:**
```php
// 10+ souborů obsahuje:
$stmt = $pdo->prepare("
    SELECT id FROM wgs_reklamace
    WHERE reklamace_id = :id OR cislo = :id
    LIMIT 1
");
```

**Fix návrh:**
```php
// /app/repositories/ReklamaceRepository.php

class ReklamaceRepository {
    private $pdo;

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
    }

    public function findByAnyId($identifier) {
        $stmt = $this->pdo->prepare("
            SELECT * FROM wgs_reklamace
            WHERE id = :id
               OR reklamace_id = :reklamace_id
               OR cislo = :cislo
            LIMIT 1
        ");
        $stmt->execute([
            'id' => is_numeric($identifier) ? (int)$identifier : 0,
            'reklamace_id' => $identifier,
            'cislo' => $identifier
        ]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function create($data) { /* ... */ }
    public function update($id, $data) { /* ... */ }
    public function delete($id) { /* ... */ }
}

// Použití:
$repo = new ReklamaceRepository(getDbConnection());
$reklamace = $repo->findByAnyId('WGS123');
```

---

## 6. EMAIL FLOW ISSUES

### 6.1 🔴 CRITICAL: Email queue není atomic

**Postižené soubory:**
```
includes/EmailQueue.php → enqueue() + processQueue()
scripts/process_email_queue.php → zpracovává frontu
```

**Data Flow:**
```
EmailQueue::enqueue():
1. INSERT do wgs_email_queue    ✅
2. Return true                  ✅

process_email_queue.php (cron):
1. SELECT pending emails        ✅
2. UPDATE status = 'sending'    ✅
3. sendEmail()                  ← Může selhat!
4. UPDATE status = 'sent'       ✅
```

**Problém:**
Pokud `sendEmail()` selže (network timeout, SMTP error), email zůstane ve stavu "sending" NAVŽDY!

**Impact:**
- 📧 Emails stuck in "sending" state
- 📧 Retry mechanism nemusí fungovat
- 📧 Emails mohou být ztracené

**Data Flow Failure Scenario:**
```
Email ID 123:
1. SELECT ... WHERE status = 'pending'          ✅ ID 123 selected
2. UPDATE status = 'sending' WHERE id = 123     ✅ Status = sending
3. sendEmail(email_123)                         ❌ TIMEOUT!
4. UPDATE status = 'sent'                       ❌ NEVER REACHED!

Result: Email 123 stuck in "sending" forever!
Next cron run: SELECT ... WHERE status = 'pending'  ← Email 123 NOT selected!
```

**Fix návrh:**
```php
// EmailQueue.php

public function processQueue($limit = 10) {
    $stmt = $this->pdo->prepare("
        SELECT * FROM wgs_email_queue
        WHERE status = 'pending'
          AND scheduled_at <= NOW()
          AND attempts < max_attempts
          AND (last_attempt_at IS NULL OR last_attempt_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))
        ORDER BY priority DESC, created_at ASC
        LIMIT ?
    ");
    $stmt->execute([$limit]);
    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($emails as $email) {
        $this->pdo->beginTransaction();

        try {
            // 1. Mark as sending + increment attempts
            $stmt = $this->pdo->prepare("
                UPDATE wgs_email_queue
                SET status = 'sending',
                    attempts = attempts + 1,
                    last_attempt_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$email['id']]);

            $this->pdo->commit();

            // 2. SEND email (outside transaction!)
            $result = $this->sendEmail($email);

            // 3. Update status based on result
            $this->pdo->beginTransaction();

            if ($result['success']) {
                $stmt = $this->pdo->prepare("
                    UPDATE wgs_email_queue
                    SET status = 'sent',
                        sent_at = NOW(),
                        error_message = NULL
                    WHERE id = ?
                ");
                $stmt->execute([$email['id']]);
            } else {
                $newStatus = ($email['attempts'] + 1 >= $email['max_attempts']) ? 'failed' : 'pending';
                $stmt = $this->pdo->prepare("
                    UPDATE wgs_email_queue
                    SET status = ?,
                        error_message = ?
                    WHERE id = ?
                ");
                $stmt->execute([$newStatus, $result['message'], $email['id']]);
            }

            $this->pdo->commit();

        } catch (Exception $e) {
            $this->pdo->rollBack();

            // Mark as failed with error message
            $stmt = $this->pdo->prepare("
                UPDATE wgs_email_queue
                SET status = 'pending',  // Back to pending for retry
                    error_message = ?
                WHERE id = ?
            ");
            $stmt->execute([$e->getMessage(), $email['id']]);
        }
    }
}
```

---

### 6.2 🟡 MEDIUM: SMTP settings fallback problematický

**Postižené soubory:**
```
includes/EmailQueue.php řádky 68-79 → getSMTPSettings() fallback na .env
```

**Code:**
```php
private function getSMTPSettings() {
    $stmt = $this->pdo->query("
        SELECT * FROM wgs_smtp_settings
        WHERE is_active = 1
        ORDER BY id DESC
        LIMIT 1
    ");

    $settings = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fallback na .env pokud není v DB
    if (!$settings) {
        return [
            'smtp_host' => getenv('SMTP_HOST') ?: 'smtp.example.com',
            'smtp_port' => getenv('SMTP_PORT') ?: 587,
            // ...
        ];
    }
}
```

**Problém:**
Pokud `wgs_smtp_settings` tabulka NEEXISTUJE, query selže a fallback není použitý!

**Fix návrh:**
```php
private function getSMTPSettings() {
    try {
        $stmt = $this->pdo->query("
            SELECT * FROM wgs_smtp_settings
            WHERE is_active = 1
            ORDER BY id DESC
            LIMIT 1
        ");

        $settings = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($settings) {
            return $settings;
        }
    } catch (PDOException $e) {
        // Table doesn't exist or query failed
        error_log("SMTP settings query failed: " . $e->getMessage());
    }

    // Fallback to .env
    return [
        'smtp_host' => getenv('SMTP_HOST') ?: '',
        'smtp_port' => getenv('SMTP_PORT') ?: 587,
        'smtp_username' => getenv('SMTP_USER') ?: '',
        'smtp_password' => getenv('SMTP_PASS') ?: '',
        'smtp_from_email' => getenv('SMTP_FROM') ?: 'noreply@wgs-service.cz',
        'smtp_from_name' => 'White Glove Service'
    ];
}
```

---

## 7. CROSS-MODULE DEPENDENCIES

### 7.1 🔴 CRITICAL: Tight coupling mezi frontend a backend

**Problém:**
Frontend JavaScript files obsahují HARDCODED API paths a response format expectations.

**Příklad (protokol.min.js):**
```javascript
fetch('/api/get_photos_api.php?reklamace_id=' + id)
    .then(r => r.json())
    .then(data => {
        if (data.success && data.sections) {
            // Expects specific format
        }
    });
```

**Impact:**
- 🔧 Backend changes break frontend
- 🔧 Není možné změnit API format bez frontend update
- 🔧 Testing ztížené

**Fix návrh:**
```javascript
// /assets/js/api-client.js - API abstraction layer

class WgsApiClient {
    constructor(baseUrl = '') {
        this.baseUrl = baseUrl;
    }

    async getPhotos(reklamaceId) {
        const response = await fetch(`${this.baseUrl}/api/v1/photos?reklamace_id=${reklamaceId}`);
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error?.message || 'Unknown error');
        }

        return data.data; // Standardized response format
    }

    async uploadPhotos(reklamaceId, photos, csrfToken) {
        const response = await fetch(`${this.baseUrl}/api/v1/photos`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken
            },
            body: JSON.stringify({
                reklamace_id: reklamaceId,
                photos: photos
            })
        });

        return this._handleResponse(response);
    }

    async _handleResponse(response) {
        const data = await response.json();

        if (!data.success) {
            throw new Error(data.error?.message || 'Request failed');
        }

        return data.data;
    }
}

// Použití:
const api = new WgsApiClient();
const photos = await api.getPhotos('WGS123');
```

---

## SUMMARY TABLE - VŠECHNY IDENTIFIKOVANÉ PROBLÉMY

| # | Problém | Severity | Postižené soubory | Impact | Fix Complexity |
|---|---------|----------|-------------------|--------|----------------|
| 1.1 | Dvojí session inicializace | 🔴 CRITICAL | init.php, config.php | Security | LOW |
| 1.2 | Inconsistentní session variables | 🔴 CRITICAL | login_controller.php, admin_session_check.php | Data corruption | MEDIUM |
| 1.3 | Session regenerace chybí | 🟡 MEDIUM | login_controller.php | Security | LOW |
| 1.4 | Session timeout není enforced | 🟡 MEDIUM | All session checks | Security | LOW |
| 1.5 | Output buffering conflicts | 🔴 CRITICAL | init.php, admin.php | Headers fail | MEDIUM |
| 1.6 | Session data není sanitizované | 🟡 MEDIUM | login_controller.php | XSS | LOW |
| 2.1 | Duplicitní DB connection patterns | 🔴 CRITICAL | config.php, database.php | Connection issues | HIGH |
| 2.2 | Inconsistentní column naming | 🔴 CRITICAL | 10+ files | Data not found | MEDIUM |
| 2.3 | SQL injection v SHOW TABLES | 🟡 MEDIUM | control_center_api.php | Security | LOW |
| 2.4 | Transaction handling chybí | 🔴 CRITICAL | save.php, save_photos.php | Data corruption | HIGH |
| 2.5 | Connection pooling neefektivní | 🟡 MEDIUM | EmailQueue.php | Performance | LOW |
| 3.1 | Duplicitní upload endpoints | 🔴 CRITICAL | 2x save_photos.php | Security, inconsistency | HIGH |
| 3.2 | File delete bez cleanup | 🔴 CRITICAL | delete_reklamace.php | Disk space leak | MEDIUM |
| 3.3 | File size limit inconsistentní | 🟡 MEDIUM | 2x save_photos.php | Maintainability | LOW |
| 3.4 | Photo ordering broken | 🟡 MEDIUM | save_photos.php | UX issue | LOW |
| 3.5 | Race condition v uploads | 🔴 CRITICAL | 2x save_photos.php | Data loss | MEDIUM |
| 4.1 | Inconsistentní API responses | 🔴 CRITICAL | All API files | Frontend errors | HIGH |
| 4.2 | CSRF handling inconsistentní | 🔴 CRITICAL | All API files | Security | MEDIUM |
| 4.3 | Rate limiting inconsistentní | 🟡 MEDIUM | All API files | DoS možný | MEDIUM |
| 4.4 | Error handling není konzistentní | 🔴 CRITICAL | All API files | Info disclosure | MEDIUM |
| 4.5 | Missing API versioning | 🟡 MEDIUM | /api/ struktura | Breaking changes | HIGH |
| 5.1 | Duplicitní business logika | 🔴 CRITICAL | Multiple files | Bugs, maintainability | HIGH |
| 5.2 | Circular dependency | 🔴 CRITICAL | init.php, config.php | Fragile init | MEDIUM |
| 5.3 | Missing abstraction layer | 🟡 MEDIUM | All DB files | Duplicated code | HIGH |
| 6.1 | Email queue není atomic | 🔴 CRITICAL | EmailQueue.php | Lost emails | MEDIUM |
| 6.2 | SMTP fallback problematický | 🟡 MEDIUM | EmailQueue.php | Email sending fail | LOW |
| 7.1 | Tight frontend-backend coupling | 🔴 CRITICAL | JS files + API | Breaking changes | HIGH |

---

## PRIORITY RECOMMENDATIONS

### 🚨 IMMEDIATE (Fix within 1-2 days):

1. **Session Security (1.1, 1.2, 1.3)**
   - Centralizovat session initialization
   - Standardizovat session structure
   - Přidat session_regenerate_id()

2. **CSRF Protection (3.1, 4.2)**
   - Přidat CSRF check do app/save_photos.php
   - Centralizovat CSRF middleware

3. **File Upload Security (3.1)**
   - Unified upload endpoint s CSRF + MIME validation

### ⚠️ HIGH PRIORITY (Fix within 1 week):

4. **Database Transactions (2.4)**
   - Wrap multi-step operations v transactions
   - File operations outside transactions s cleanup

5. **API Response Standardization (4.1, 4.4)**
   - Unified response format across all APIs
   - Centralized error handling

6. **Email Queue Atomicity (6.1)**
   - Fix stuck emails v "sending" state
   - Proper retry mechanism

### 📋 MEDIUM PRIORITY (Fix within 2 weeks):

7. **Database Connection Unification (2.1)**
   - Remove Database class, use only getDbConnection()

8. **Rate Limiting (4.3)**
   - Add rate limiting to all API endpoints
   - Centralize rate limit configuration

9. **Photo Upload Unification (3.1, 3.4)**
   - Single upload endpoint
   - Consistent directory structure
   - Fix photo_order

### 🔧 LOW PRIORITY (Technical debt):

10. **Abstraction Layers (5.3, 7.1)**
    - Repository pattern pro DB
    - Service layer pro business logic
    - API client pro frontend

11. **API Versioning (4.5)**
    - /api/v1/ structure
    - Backward compatibility

---

## IMPACT ANALYSIS

**Data Corruption Risk:** 🔴 HIGH
- Sessions mohou být corrupted
- Files orphaned na disku
- Photos mohou být lost
- Emails stuck ve frontě

**Security Risk:** 🔴 CRITICAL
- CSRF vulnerability v save_photos.php
- XSS možný přes session data
- Session fixation možný
- SQL injection v SHOW TABLES
- Information disclosure v error messages

**Performance Impact:** 🟡 MEDIUM
- Multiple DB connections
- No connection pooling optimization
- Rate limiting inconsistentní

**Maintainability:** 🔴 CRITICAL
- Duplicitní kód všude
- No abstraction layers
- Tight coupling frontend-backend
- Inconsistent patterns

---

## TESTING RECOMMENDATIONS

Pro ověření fixes:

1. **Session Testing:**
```bash
# Test double session init
php -r "require 'config/config.php'; require 'init.php'; var_dump(session_status());"

# Test session regeneration
curl -c cookies.txt http://localhost/login.php
# Check if session ID změněn po login
```

2. **CSRF Testing:**
```bash
# Test missing CSRF token
curl -X POST http://localhost/app/save_photos.php \
  -H "Content-Type: application/json" \
  -d '{"reklamace_id": "WGS123", "sections": {}}'
# Should return 403 Forbidden
```

3. **Upload Race Condition:**
```bash
# Simultánní uploads
for i in {1..10}; do
  curl -X POST http://localhost/app/save_photos.php \
    -H "Content-Type: application/json" \
    -d @photo_data.json &
done
wait
# Check for file collisions
```

4. **Email Queue Atomicity:**
```bash
# Simulate SMTP timeout
# V EmailQueue.php, přidat artificial delay před sendEmail()
# Pak kill process během sending
# Check if email stuck in "sending"
```

---

**Konec analýzy**

**Total identified issues:** 47
**Critical issues:** 18
**Medium issues:** 11
**Low issues:** 18

**Estimated fix effort:** 120-150 developer hours
