# DOPORUČENÍ PRO OPRAVU LOGICKÝCH CHYB

## PRIORITA 1: KRITICKÉ (Musí se opravit okamžitě)

### Chyba #1: ID generování bez transaction
**Umístění:** `/home/user/moje-stranky/app/controllers/save.php:11-31`

**Aktuální kód:**
```php
function generateWorkflowId(PDO $pdo): string {
    $attempts = 0;
    do {
        $candidate = 'WGS' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
        
        $stmt = $pdo->prepare('SELECT reklamace_id FROM wgs_reklamace WHERE reklamace_id = :id FOR UPDATE');
        $stmt->execute([':id' => $candidate]);
        
        if ($stmt->rowCount() === 0) {
            return $candidate;
        }
        $attempts++;
    } while ($attempts < 5);
    
    throw new Exception('Nepodařilo se vygenerovat interní ID reklamace.');
}
```

**Problém:** 
- FOR UPDATE se ignoruje bez transaction
- ID se vrátí bez skutečného vložení do DB
- Dvě konkurentní vlákna mohou vrátit stejné ID

**Oprava:**
```php
function generateWorkflowId(PDO $pdo): string {
    $attempts = 0;
    
    do {
        $pdo->beginTransaction();
        
        try {
            $candidate = 'WGS' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
            
            // FOR UPDATE nyní funguje
            $stmt = $pdo->prepare('SELECT id FROM wgs_reklamace WHERE reklamace_id = :id FOR UPDATE');
            $stmt->execute([':id' => $candidate]);
            
            if ($stmt->rowCount() === 0) {
                // IMPORTANT: Skutečně vložit ID do DB nebo alespoň lockovat
                // INSERT placeholder row nebo UPDATE dummy flag
                $pdo->commit();
                return $candidate;
            }
            
            $pdo->rollBack();
            $attempts++;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    } while ($attempts < 5);
    
    throw new Exception('Nepodařilo se vygenerovat interní ID reklamace.');
}
```

---

### Chyba #2: TOCTOU v registration_controller.php - duplicate email
**Umístění:** `/home/user/moje-stranky/app/controllers/registration_controller.php:62-138`

**Aktuální problém:**
```php
// Kontrola emailu
$existingStmt = $pdo->prepare('SELECT 1 FROM wgs_users WHERE email = :email LIMIT 1');
$existingStmt->execute([':email' => $email]);
if ($existingStmt->fetchColumn()) {
    throw new InvalidArgumentException('Uživatel s tímto emailem již existuje.');
}

// ... 70 řádků později ...

// Vložení
$insertStmt = $pdo->prepare($insertSql);
$insertStmt->execute($params);  // TOCTOU!
```

**Oprava - Řešení 1 (databázové):**
```sql
-- Přidej UNIQUE constraint
ALTER TABLE wgs_users ADD UNIQUE KEY `unique_email` (`email`);
```

**Oprava - Řešení 2 (aplikační):**
```php
$pdo->beginTransaction();

try {
    // Zkontroluj s FOR UPDATE lock
    $existingStmt = $pdo->prepare('SELECT id FROM wgs_users WHERE email = :email LIMIT 1 FOR UPDATE');
    $existingStmt->execute([':email' => $email]);
    
    if ($existingStmt->fetchColumn()) {
        throw new InvalidArgumentException('Uživatel s tímto emailem již existuje.');
    }
    
    // Teď je bezpečné vložit
    $insertStmt = $pdo->prepare($insertSql);
    $insertStmt->execute($params);
    
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

---

### Chyba #3: max_usage race condition
**Umístění:** `/home/user/moje-stranky/app/controllers/registration_controller.php:42-144`

**Aktuální:**
```php
// SELECT bez lock
$keyStmt = $pdo->prepare('SELECT * FROM wgs_registration_keys WHERE key_code = :code LIMIT 1');
$keyStmt->execute([':code' => $registrationKey]);
$keyRow = $keyStmt->fetch(PDO::FETCH_ASSOC);

// Kontrola
if (isset($keyRow['max_usage']) && $keyRow['max_usage'] !== null) {
    $max = (int) $keyRow['max_usage'];
    $used = (int) ($keyRow['usage_count'] ?? 0);
    if ($max > 0 && $used >= $max) {
        throw new InvalidArgumentException('Registrační klíč již byl vyčerpán.');
    }
}

// ... později UPDATE bez zajištění
```

**Oprava:**
```php
$pdo->beginTransaction();

try {
    // SELECT s FOR UPDATE pro lock
    $keyStmt = $pdo->prepare('SELECT * FROM wgs_registration_keys WHERE key_code = :code LIMIT 1 FOR UPDATE');
    $keyStmt->execute([':code' => $registrationKey]);
    $keyRow = $keyStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$keyRow) {
        throw new InvalidArgumentException('Registrační klíč nebyl nalezen.');
    }
    
    // Kontrola se nově načtenými daty
    if (isset($keyRow['max_usage']) && $keyRow['max_usage'] !== null) {
        $max = (int) $keyRow['max_usage'];
        $used = (int) ($keyRow['usage_count'] ?? 0);
        if ($max > 0 && $used >= $max) {
            throw new InvalidArgumentException('Registrační klíč již byl vyčerpán.');
        }
    }
    
    // ... vložení uživatele ...
    
    // UPDATE s jistotou, že jsme stále v transaction
    $updateKey = $pdo->prepare('UPDATE wgs_registration_keys SET usage_count = COALESCE(usage_count, 0) + 1 WHERE id = :id');
    $updateKey->execute([':id' => $keyRow['id']]);
    
    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $e;
}
```

---

### Chyba #4: Rate limiter race condition
**Umístění:** `/home/user/moje-stranky/includes/rate_limiter.php:76-128`

**Oprava:**
```php
public function checkLimit($identifier, $actionType, $limits = []) {
    // ... setup ...
    
    try {
        // TRANSACTION START
        $this->pdo->beginTransaction();
        
        // Načti s FOR UPDATE lock
        $stmt = $this->pdo->prepare("
            SELECT * FROM `{$this->tableName}`
            WHERE identifier = :identifier
              AND action_type = :action_type
              AND first_attempt_at >= :window_start
            ORDER BY id DESC
            LIMIT 1
            FOR UPDATE
        ");
        
        $stmt->execute([
            ':identifier' => $identifier,
            ':action_type' => $actionType,
            ':window_start' => $windowStart
        ]);
        
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($record) {
            $attemptCount = (int)$record['attempt_count'];
            
            if ($attemptCount >= $maxAttempts) {
                // Zablokovat
                $blockedUntil = date('Y-m-d H:i:s', strtotime("+{$blockMinutes} minutes"));
                
                $updateStmt = $this->pdo->prepare("
                    UPDATE `{$this->tableName}`
                    SET blocked_until = :blocked_until,
                        last_attempt_at = NOW()
                    WHERE id = :id
                ");
                
                $updateStmt->execute([
                    ':blocked_until' => $blockedUntil,
                    ':id' => $record['id']
                ]);
                
                $this->pdo->commit();
                
                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'reset_at' => $blockedUntil,
                    'message' => "Limit přesažen..."
                ];
            }
            
            // Inkrementace
            $updateStmt = $this->pdo->prepare("
                UPDATE `{$this->tableName}`
                SET attempt_count = attempt_count + 1,
                    last_attempt_at = NOW()
                WHERE id = :id
            ");
            
            $updateStmt->execute([':id' => $record['id']]);
            
            $this->pdo->commit();
            
            $remaining = $maxAttempts - ($attemptCount + 1);
            return [
                'allowed' => true,
                'remaining' => max(0, $remaining),
                'reset_at' => date('Y-m-d H:i:s', strtotime($record['first_attempt_at'] . " +{$windowMinutes} minutes")),
                'message' => "OK. Zbývá {$remaining} pokusů."
            ];
        } else {
            // Nový záznam
            $insertStmt = $this->pdo->prepare("
                INSERT INTO `{$this->tableName}` (identifier, action_type, attempt_count, first_attempt_at, last_attempt_at)
                VALUES (:identifier, :action_type, 1, NOW(), NOW())
            ");
            
            $insertStmt->execute([
                ':identifier' => $identifier,
                ':action_type' => $actionType
            ]);
            
            $this->pdo->commit();
            
            return [
                'allowed' => true,
                'remaining' => $maxAttempts - 1,
                'reset_at' => date('Y-m-d H:i:s', strtotime("+{$windowMinutes} minutes")),
                'message' => "OK. Zbývá " . ($maxAttempts - 1) . " pokusů."
            ];
        }
    } catch (Exception $e) {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        throw $e;
    }
}
```

---

## PRIORITA 2: VYSOKÁ (Měla by se opravit v nejbližším release)

### Chyba #5: Session duplikace

**Řešení:** Odstranit session_start() z config.php a ponechat jen v init.php.

```php
// config.php - ODSTRANIT řádek 2
// if (session_status() === PHP_SESSION_NONE) { session_start(); }

// init.php - PONECHAT řádky 7-71
// Zde se session iniciuje JEDENKRÁT
```

---

### Chyba #6: SQL Injection v migration_executor.php

**Řešení:**
```php
// Stávající kód (line 127-132):
$stmt = $pdo->query("SHOW TABLES LIKE '$table'");
$countStmt = $pdo->query("SELECT COUNT(*) as count FROM $table");

// Opravit na:
// Místo SHOW TABLES LIKE, použít INFORMATION_SCHEMA
$checkStmt = $pdo->prepare(
    'SELECT COUNT(*) FROM information_schema.tables 
     WHERE table_schema = DATABASE() AND table_name = ?'
);
$checkStmt->execute([$table]);

if ($checkStmt->fetchColumn() > 0) {
    $createdTables[] = $table;
    
    // Výčet záznamů - je bezpečnější
    $countStmt = $pdo->prepare("SELECT COUNT(*) as count FROM `" . str_replace('`', '``', $table) . "`");
    $countStmt->execute();
    $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
}
```

---

## PRIORITA 3: STŘEDNÍ (Code cleanup, preventivní)

### Loose comparison v admin.php
```php
// Místo:
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';

// Napsat:
$embedMode = isset($_GET['embed']) && $_GET['embed'] === '1';
```

---

## TESTY PRO VERIFIKACI OPRAV

### Test race condition - ID generování
```bash
# Otevřít 5 terminálů a spustit parallel:
for i in {1..100}; do
  curl -X POST http://localhost/api/create_test_claim.php \
    -H "Content-Type: application/json" \
    -d '{"action":"create"}'
done

# Kontrola duplicit v DB:
mysql> SELECT reklamace_id, COUNT(*) as cnt FROM wgs_reklamace 
        GROUP BY reklamace_id HAVING cnt > 1;
# Mělo by vrátit 0 řádků
```

### Test max_usage bypass
```bash
# Vytvořit key s max_usage = 1
# Spustit 5 parallel registrací se stejným klíčem
# Měl by selhat na 2., 3., 4., 5. registraci

for i in {1..5}; do
  curl -X POST http://localhost/api/register.php \
    -H "Content-Type: application/json" \
    -d '{"registration_key":"TEST_KEY_1","name":"Test'$i'","email":"test'$i'@example.com","password":"Test123456!"}'
done

# Kontrola:
mysql> SELECT usage_count FROM wgs_registration_keys WHERE key_code = 'TEST_KEY_1';
# Mělo by být 1, ne 5
```

---

## SHRNUTÍ DOPORUČENÍ

| Priorita | Chyba | Oprava čas | Dopad |
|----------|-------|-----------|-------|
| 1 | ID generation | 2 hodiny | KRITICKÝ |
| 1 | Duplicate email | 1 hodina | KRITICKÝ |
| 1 | max_usage bypass | 1 hodina | KRITICKÝ |
| 1 | Rate limiter bypass | 2 hodiny | KRITICKÝ |
| 2 | Session duplikace | 30 minut | VYSOKÝ |
| 2 | SQL Injection | 1 hodina | VYSOKÝ |
| 3 | Loose comparison | 15 minut | STŘEDNÍ |

**Celkový čas:** ~8-10 hodin pro všechny kritické opravy

