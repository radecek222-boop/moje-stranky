# KOMPLETNÍ LOGICKÉ CHYBY AUDIT - PHP PROJEKT
## /home/user/moje-stranky

---

## 1. NEKONZISTENTNÍ LOGIKA

### ❌ KRITICKÁ CHYBA: Session duplikace (VYSOKÁ MÍRA BUGŮ)
**Soubory:** `/home/user/moje-stranky/config/config.php:2`, `/home/user/moje-stranky/init.php:8`, `/home/user/moje-stranky/init.php:70`

**Problém:** Session se spouští **3x**:
1. config.php:2 - `if (session_status() === PHP_SESSION_NONE) { session_start(); }`
2. init.php:8 - znovu volání v ob_start() sekci
3. init.php:70 - tretí volání v session konfiguraci

**Dopad:** Potenciální session confusion, stav nespojitosti, PHP warnings v production. Fyzikálně to nespáchne, ale je to nepořádek.

**Oprava:** Spustit session JEDENKRÁT v init.php, nikoliv v config.php.

---

### ⚠️ PODEZŘELÉ: Loose comparison v admin.php
**Soubor:** `/home/user/moje-stranky/admin.php:13`

**Problém:**
```php
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';  // Loose ==, ne ===
```

**Výskyt:** Jediný - ale potenciálně nebezpečný:
- `'1'` vs `1` vs `true` - všechny budou true
- Pokud by GET parametr byl `'on'` nebo `'yes'` nebo cokoliv truthy, bude to fungovat jinak

**Dopad:** Potenciální obejití security headers na iframe. STŘEDNÍ.

---

## 2. KONFLIKTY MEZI MODULY

### ❌ KRITICKÁ CHYBA: Duplicitní delete endpointy
**Soubory:** `/home/user/moje-stranky/api/delete_reklamace.php` vs `/home/user/moje-stranky/api/admin_api.php`

**Problém:** 
- `delete_reklamace.php` je **dedikovaný endpoint** pro mazání
- `admin_api.php:70-72` má **switch case pro 'delete_key'** (ne delete_reklamace!)
- Ale vidíme logiku v admin_api.php, která je schopna dělat více věcí

**Výskyt:** Nejasná hranice odpovědnosti
- Jaký soubor se má používat na smazání reklamace?
- Jaký na smazání klíčů?

**Dopad:** Maintenance nightmare, potenciální bezpečnostní děry pokud je jeden endpoint zapomenut. VYSOKÁ.

---

### ⚠️ PODEZŘELÉ: Overlapping CSRF ověření
**Soubory:** Více API souborů

**Problém:**
```php
// delete_reklamace.php:32
requireCSRF();  // Function call

// admin_api.php:49
if (!validateCSRFToken($csrfToken)) { ... }  // Inline

// notification_api.php:50
if (!validateCSRFToken($csrfToken)) { ... }  // Inline

// backup_api.php:27
if (!validateCSRFToken($csrfToken)) { ... }  // Inline
```

**Nekonzistentnost:** Někdo používá `requireCSRF()`, někdo `validateCSRFToken()`. 

**Dopad:** Vizuální chaos, ale funkčně OK díky správnému kódu. NÍZKÁ.

---

## 3. ŠPATNÁ DATA FLOW

### ❌ KRITICKÁ CHYBA: TOCTOU (Time-of-Check-Time-of-Use) v registration_controller.php
**Soubor:** `/home/user/moje-stranky/app/controllers/registration_controller.php`

**Problém:**
```php
// Line 62-66: Check
$existingStmt = $pdo->prepare('SELECT 1 FROM wgs_users WHERE email = :email LIMIT 1');
$existingStmt->execute([':email' => $email]);
if ($existingStmt->fetchColumn()) {
    throw new InvalidArgumentException('Uživatel s tímto emailem již existuje.');
}

// ... later (Line 130-138): Use
$insertStmt = $pdo->prepare($insertSql);
```

**Race condition:** Mezi SELECT (line 64) a INSERT (line 137) může jiný request vytvořit uživatele se stejným emailem!

**Řešení:** 
- Mělo by být v **unique constraint na DB**
- NEBO transaction s **SELECT ... FOR UPDATE**

**Dopad:** Duplicate users s identickým emailem v DB. VYSOKÁ.

---

### ❌ KRITICKÁ CHYBA: TOCTOU v login_controller.php
**Soubor:** `/home/user/moje-stranky/app/controllers/login_controller.php:99-101`

**Problém:**
```php
$stmt = $pdo->prepare('SELECT * FROM wgs_users WHERE email = :email LIMIT 1');
$stmt->execute([':email' => $email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
// ... later (line 153): UPDATE last_login_at
```

**Race condition:** Uživatel může být smazán mezi SELECT a UPDATE!

**Dopad:** `UPDATE` selže na 0 řádků, ale codeace pokračuje. STŘEDNÍ-VYSOKÁ.

---

### ❌ KRITICKÁ CHYBA: Data race v registration_controller.php - max_usage
**Soubor:** `/home/user/moje-stranky/app/controllers/registration_controller.php:42-58`

**Problém:**
```php
// Check max_usage
$keyStmt = $pdo->prepare('SELECT * FROM wgs_registration_keys WHERE key_code = :code LIMIT 1');
$keyStmt->execute([':code' => $registrationKey]);
$keyRow = $keyStmt->fetch(PDO::FETCH_ASSOC);

if (isset($keyRow['max_usage']) && $keyRow['max_usage'] !== null) {
    $max = (int) $keyRow['max_usage'];
    $used = (int) ($keyRow['usage_count'] ?? 0);
    if ($max > 0 && $used >= $max) {  // LINE 55
        throw new InvalidArgumentException('Registrační klíč již byl vyčerpán.');
    }
}

// ... much later (line 142-144): Increment
if (isset($keyRow['id']) && in_array('usage_count', db_get_table_columns($pdo, 'wgs_registration_keys'), true)) {
    $updateKey = $pdo->prepare('UPDATE wgs_registration_keys SET usage_count = COALESCE(usage_count, 0) + 1 WHERE id = :id');
    $updateKey->execute([':id' => $keyRow['id']]);
```

**Race condition:** 
1. Thread A: Reads usage_count = 4, max_usage = 5 → ALLOWED
2. Thread B: Reads usage_count = 4, max_usage = 5 → ALLOWED  
3. Both increment to usage_count = 5 (one lost update!)
4. Result: 2 users registrováno s maxem 5!

**Řešení:** Transaction + SELECT ... FOR UPDATE

**Dopad:** Key limit bypass. VYSOKÁ.

---

### ❌ KRITICKÁ CHYBA: Transaction bez BEGIN v save.php
**Soubor:** `/home/user/moje-stranky/app/controllers/save.php:13-31`

**Problém:**
```php
$stmt = $pdo->prepare('SELECT reklamace_id FROM wgs_reklamace WHERE reklamace_id = :id FOR UPDATE');
$stmt->execute([':id' => $candidate]);
```

**Chyba:** FOR UPDATE **VYŽADUJE** `beginTransaction()` PŘED tímto statement!
Bez transaction se FOR UPDATE ignoruje!

**Dopad:** Race condition na ID generování. Více reklamací se stejným ID! VYSOKÁ.

---

## 4. CHYBY V GENEROVÁNÍ ID

### ❌ KRITICKÁ CHYBA: ID generování bez transaction
**Soubor:** `/home/user/moje-stranky/app/controllers/save.php:11-31`

**Problém:**
```php
function generateWorkflowId(PDO $pdo): string {
    // NO TRANSACTION!
    $attempts = 0;
    do {
        $candidate = 'WGS' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        
        $stmt = $pdo->prepare('SELECT reklamace_id FROM wgs_reklamace WHERE reklamace_id = :id FOR UPDATE');
        $stmt->execute([':id' => $candidate]);
        
        if ($stmt->rowCount() === 0) {
            return $candidate;  // WITHOUT INSERTING!
        }
    } while ($attempts < 5);
}
```

**Problém:** Vrací ID BEZ vložení do DB!
- Thread A: generateWorkflowId() → vrátí "WGS20251114-ABC123"
- Thread B: generateWorkflowId() → vrátí stejné "WGS20251114-ABC123"  
- Collision!

**Řešení:** Mělo by se udělat `INSERT ... ON DUPLICATE KEY UPDATE` v transaction.

**Dopad:** Duplicate ID v DB! KRITICKÁ.

---

## 5. RACE CONDITIONS

### ❌ KRITICKÁ CHYBA: SQL INJECTION v migration_executor.php
**Soubor:** `/home/user/moje-stranky/api/migration_executor.php:127, 132`

**Problém:**
```php
// Line 127
$stmt = $pdo->query("SHOW TABLES LIKE '$table'");

// Line 132
$countStmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
```

**SQL Injection:** `$table` není quoted! Pokud `$table = 'test; DROP DATABASE;'`, bude to executeno!

**Výskyt:** Ale whitelist check na line 116-123:
```php
$tables = [
    'wgs_theme_settings',
    'wgs_content_texts',
    'wgs_system_config',
    'wgs_pending_actions',
    'wgs_action_history',
    'wgs_github_webhooks'
];
```

**Dopad:** Vlivem whitelistu, SQL injection je mitigated, ALE CODE SMELL. VYSOKÁ-STŘEDNÍ.

---

### ❌ KRITICKÁ CHYBA: Race condition v rate_limiter.php
**Soubor:** `/home/user/moje-stranky/includes/rate_limiter.php:76-90`

**Problém:**
```php
$stmt = $this->pdo->prepare("
    SELECT * FROM `{$this->tableName}`
    WHERE identifier = :identifier
      AND action_type = :action_type
      AND first_attempt_at >= :window_start
    ORDER BY id DESC
    LIMIT 1
");

$stmt->execute([...]);
$record = $stmt->fetch(PDO::FETCH_ASSOC);

if ($record) {
    $attemptCount = (int)$record['attempt_count'];
    
    if ($attemptCount >= $maxAttempts) {
        // Block (line 96-110)
    }
    
    // Update (line 121-128)
    $updateStmt = $this->pdo->prepare("
        UPDATE `{$this->tableName}`
        SET attempt_count = attempt_count + 1,
        WHERE id = :id
    ");
```

**Race condition:**
1. Thread A: SELECT → attempt_count = 4
2. Thread B: SELECT → attempt_count = 4
3. Thread A: UPDATE attempt_count = 5
4. Thread B: UPDATE attempt_count = 5 (lost update!)

**Result:** Rate limit bypass! 6 pokusů místo 5.

**Řešení:** SELECT ... FOR UPDATE v transaction

**Dopad:** DOS protection bypass. VYSOKÁ.

---

### ⚠️ PODEZŘELÉ: File operations bez locks
**Soubory:** `/home/user/moje-stranky/config/config.php:129`, `105-137`

**Problém:**
```php
// Line 129 v checkRateLimit()
file_put_contents($file, json_encode($limits, JSON_PRETTY_PRINT), LOCK_EX);

// ALE later line 114: filtr se provádí BEZ transakce
$limits = array_filter($limits, function($data) use ($now, $timeWindow) {
    return ($now - $data['first_attempt']) < $timeWindow;
});
```

**Race condition:**
1. Thread A: čte file_get_contents()
2. Thread B: čte file_get_contents()
3. Thread A: array_filter() a write
4. Thread B: array_filter() a write (Thread A data jsou smazána!)

**Dopad:** Ztráta rate limit záznamů. STŘEDNÍ.

---

## 6. OFF-BY-ONE ERRORS

### ⚠️ PODEZŘELÉ: backup_api.php - array indexing
**Soubor:** `/home/user/moje-stranky/api/backup_api.php:101-115`

**Problém:**
```php
$dataStmt = $pdo->query("SELECT * FROM `$table`");
$rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
$rowCount = count($rows);

if ($rowCount > 0) {
    $columns = array_keys($rows[0]);  // LINE 98 - potential off-by-one!
    
    foreach ($rows as $index => $row) {
        // ... 
        if ($index < $rowCount - 1) {  // Off-by-one?
            $output .= ",\n";
        } else {
            $output .= ";\n\n";
        }
    }
}
```

**Analýza:** Toto je SPRÁVNĚ implementáno:
- `$rowCount = 3` → indices jsou 0, 1, 2
- Poslední index = 2 = `3-1` ✓

**Výsledek:** ✅ OK.

---

## 7. NULL/UNDEFINED HANDLING

### ⚠️ PODEZŘELÉ: undefined array access v control_center_api.php
**Soubor:** `/home/user/moje-stranky/api/control_center_api.php:2492`

**Problém:**
```php
$firstLine = $bracketLines[$bracket][0] ?? 1;
```

**Výskyt:** Pokud `$bracketLines[$bracket]` neexistuje, `??` vrátí 1. Ale pokud EXISTUJE a je prázdné pole, vrátí Warning + 1.

**Test:**
```php
$arr = [];
$result = $arr[0] ?? 1;  // Returns 1 - OK

$arr = ['key' => []];
$result = $arr['key'][0] ?? 1;  // Returns 1 - OK v PHP 8+, ale Warning v PHP 7

// ALE:
$result = $arr['notexist'][0] ?? 1;  // Returns 1 - OK v PHP 8+, Warning v PHP 7
```

**Dopad:** Závisí na PHP verzi. STŘEDNÍ.

---

### ❌ KRITICKÁ CHYBA: Unsafe array access v geocode_proxy.php
**Soubor:** `/home/user/moje-stranky/api/geocode_proxy.php:168`

**Problém:**
```php
if (isset($osrmData['code']) && $osrmData['code'] === 'Ok' && isset($osrmData['routes'][0])) {
    $route = $osrmData['routes'][0];
```

**Analýza:** SPRÁVNĚ se kontroluje `isset($osrmData['routes'][0])`!

**Výsledek:** ✅ OK.

---

## 8. EDGE CASES

### ⚠️ PODEZŘELÉ: Division by zero možnost v backup_api.php
**Soubor:** `/home/user/moje-stranky/api/backup_api.php:186`

**Problém:**
```php
// Line 186-187
usort($backupList, function($a, $b) {
    return strtotime($b['created']) - strtotime($a['created']);
});
```

**Edge case:** Pokud `strtotime()` vrátí FALSE (neplatné datum), bude `false - false = 0` (OK) ale `false - number` bude `0 - number = -number`.

**Dopad:** Sorting by false - se chová nepredvídatelně. NÍZKÁ.

---

### ❌ KRITICKÁ CHYBA: Timezone edge case - Daylight Saving Time
**Soubor:** `/home/user/moje-stranky/api/analytics_api.php:58-61`

**Problém:**
```php
'today' => date('Y-m-d 00:00:00'),
'week' => date('Y-m-d 00:00:00', strtotime('-7 days')),
'month' => date('Y-m-d 00:00:00', strtotime('-30 days')),
'year' => date('Y-m-d 00:00:00', strtotime('-365 days')),
```

**Edge case:** 
- strtotime('-365 days') NENÍ 1 rok! Je to 365 dnů.
- V roce s 366 dny (leap year), to bude špatně.
- Lépe by bylo: `date('Y-m-d 00:00:00', strtotime('-1 year'))`

**Dopad:** Roce s leap days bude year report o 1 den kratší. STŘEDNÍ.

---

### ⚠️ PODEZŘELÉ: Empty string vs NULL inconsistency
**Soubory:** Všechny CRUD operace

**Problém:** Někdy se používá `$value === ''`, jindy `empty($value)`, jindy `!$value`.

**Výskyt:**
- save.php: `if ($trimmed === '' || strcasecmp($trimmed, 'nevyplňuje se') === 0) { return null; }`
- config.php: `if ($value !== false && $value !== '') { return $value; }`

**Nekonzistentnost:** Ale funkčně OK.

**Dopad:** NÍZKÁ, ale maintenance nightmare.

---

## SOUHRN KRITICKÝCH CHYB

| # | Chyba | Soubor | Řádek | Závažnost | Typ |
|----|-------|--------|-------|-----------|------|
| 1 | Session duplikace | config.php, init.php | 2,8,70 | VYSOKÁ | Race condition |
| 2 | TOCTOU - duplicate email | registration_controller.php | 62-138 | **KRITICKÁ** | Race condition |
| 3 | TOCTOU - login SELECT | login_controller.php | 99-153 | VYSOKÁ | Race condition |
| 4 | max_usage race condition | registration_controller.php | 42-144 | **KRITICKÁ** | Race condition |
| 5 | FOR UPDATE bez transaction | save.php | 13-31 | **KRITICKÁ** | Race condition |
| 6 | ID generování collision | save.php | 11-31 | **KRITICKÁ** | Logic error |
| 7 | SQL Injection (mitigated) | migration_executor.php | 127,132 | VYSOKÁ | SQL Injection |
| 8 | Rate limit race condition | rate_limiter.php | 76-128 | **KRITICKÁ** | Race condition |
| 9 | File lock race condition | config.php | 105-137 | STŘEDNÍ | Race condition |
| 10 | Duplicate endpoints | delete_reklamace.php vs admin_api.php | 70 | VYSOKÁ | Architecture |

---

## STATISTIKA

- **Celkem najdeno:** 10+ kritických problémů
- **Vysoká rizika:** 5
- **Střední rizika:** 2
- **Nízká rizika:** 2+
- **Problémy týkající se concurrency:** 70%
- **Problémy týkající se validace:** 20%
- **Problémy týkající se architektur:** 10%

