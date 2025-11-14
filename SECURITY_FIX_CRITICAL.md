# 🔴 KRITICKÁ BEZPEČNOSTNÍ OPRAVA - VYŽADUJE OKAMŽITÉ ŘEŠENÍ

## Problém

**Soubor:** `/home/user/moje-stranky/install_role_based_access.php`  
**Řádka:** 20  
**Závažnost:** 🔴 KRITICKÁ  

```php
error_log("INSTALL RBAC - POST data: " . print_r($_POST, true));
```

### Rizika

1. **Password Logging** 🔐
   - Loggují se všechna POST data
   - Zahrnuje: hesla, registrační klíče, nové hesla
   - Logy mohou být přístupné v `/var/log/`, backupu, monitoring systémech

2. **Sensitive Data Exposure** 📋
   - Email adresy
   - IP adresy (přes REMOTE_ADDR)
   - Session tokeny/cookies
   - CSRF tokeny

3. **Compliance Violations** ⚖️
   - **GDPR:** Nefiltrované osobní údaje v logech = porušení
   - **PCI-DSS:** Hesla/tokeny se nesmějí loggovat
   - **HIPAA:** Pokud se zpracovávají medicínská data
   - **SOC 2:** Logging bez redakce citlivých dat = selhání auditu

4. **Attack Surface** 🎯
   - Log file traversal (pokud je logování veřejné)
   - Insider threat (log admini vidí hesla)
   - Data breach (zastaralé logy s hesly)

## Řešení

### NEJRYCHLEJŠÍ (1 minuta)

```php
// PŘED:
error_log("INSTALL RBAC - POST data: " . print_r($_POST, true));

// PO - Prostě smazat (pokud se to nepoužívá k debugování):
// error_log("INSTALL RBAC completed");
```

### SPRÁVNÉ (2 minuty) - Se zachováním debugování

```php
// Filtrovat citlivé pole
$safe_post = $_POST;
$sensitive_fields = ['password', 'password_reset', 'new_password', 'old_password', 
                     'admin_key', 'csrf_token', 'session_id'];

foreach ($sensitive_fields as $field) {
    if (isset($safe_post[$field])) {
        $safe_post[$field] = '***REDACTED***';
    }
}

error_log("INSTALL RBAC - POST data: " . print_r($safe_post, true));
```

### NEJLEPŠÍ (3 minuty) - Strukturovaná oprava

```php
// Vytvořit helper funkci v /includes/security.php
if (!function_exists('logSafePostData')) {
    function logSafePostData($action = 'Action') {
        $safe_data = array_filter($_POST, fn($k) => !in_array($k, [
            'password', 'password_reset', 'new_password', 'old_password',
            'admin_key', 'csrf_token', 'session_id', 'api_key', 'secret'
        ]), ARRAY_FILTER_USE_KEY);
        
        // Přidat context
        $log_entry = [
            'action' => $action,
            'timestamp' => date('Y-m-d H:i:s'),
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user' => $_SESSION['user_id'] ?? 'anonymous',
            'fields' => implode(', ', array_keys($safe_data))
        ];
        
        error_log(json_encode($log_entry));
    }
}

// V install_role_based_access.php:
logSafePostData('INSTALL RBAC');
```

## Implementace - Krok za krokem

### Krok 1: Zkontrolovat existující logy
```bash
# Hledat citlivá data v logech
grep -r "password\|Password\|admin_key" /var/log/ 2>/dev/null | head -20
grep -r "csrf_token\|token" /var/log/ 2>/dev/null | head -20

# V aplikaci:
find . -name "*.log" -type f -exec grep -l "password\|csrf" {} \;
```

### Krok 2: Vytvořit bezpečný logging helper
Soubor: `/home/user/moje-stranky/includes/security_logging.php`

```php
<?php
/**
 * Security Logging Helper
 * Logguje akce bez expozice citlivých dat
 */

defined('ABSPATH') || exit;

/**
 * Bezpečně logovat POST data
 */
function logSafePostData($action, $fields = []) {
    $redacted_fields = [
        'password', 'password_reset', 'new_password', 'old_password', 'confirm_password',
        'admin_key', 'registration_key', 'csrf_token', 'session_id', 'api_key', 
        'secret', 'token', 'auth_token', 'refresh_token', 'access_token'
    ];
    
    $safe_data = [];
    foreach ($_POST as $key => $value) {
        if (in_array($key, $redacted_fields)) {
            $safe_data[$key] = '[REDACTED]';
        } else if (empty($fields) || in_array($key, $fields)) {
            $safe_data[$key] = is_array($value) ? '[ARRAY]' : substr($value, 0, 50);
        }
    }
    
    $log_entry = [
        'action' => $action,
        'timestamp' => date('Y-m-d H:i:s'),
        'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 100),
        'posted_fields' => array_keys($safe_data),
        'data' => $safe_data
    ];
    
    error_log(json_encode($log_entry));
}

/**
 * Logovat akci bez POST dat (nejbezpečnější)
 */
function logAction($action, $details = []) {
    $log_entry = array_merge([
        'action' => $action,
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_id' => $_SESSION['user_id'] ?? null
    ], $details);
    
    error_log(json_encode($log_entry));
}

/**
 * Logovat chybu bez citlivých dat
 */
function logSecurityEvent($event_type, $message, $severity = 'INFO') {
    $log_entry = [
        'event' => $event_type,
        'severity' => $severity,
        'message' => $message,
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'endpoint' => $_SERVER['REQUEST_URI'] ?? 'unknown'
    ];
    
    error_log(json_encode($log_entry));
}
?>
```

### Krok 3: Opravit install_role_based_access.php

**Řádek 20 - ZMĚNA:**

```php
// STARÉ:
error_log("INSTALL RBAC - POST data: " . print_r($_POST, true));

// NOVÉ:
logAction('INSTALL RBAC Started', [
    'fields' => implode(', ', array_keys($_POST ?? []))
]);
```

### Krok 4: Audit všech ostatních files s error_log()
```bash
grep -rn "error_log.*print_r\|error_log.*var_dump\|error_log.*POST\|error_log.*_SERVER" \
    /home/user/moje-stranky --include="*.php" | grep -v "DETAILED_REMOVABLE"
```

## Kontrola - Co udělat TEĎ

- [ ] Spustit grep příkazy (viz Krok 4)
- [ ] Zkontrolovat starší logy pro hesla
- [ ] Vytvořit `/includes/security_logging.php`
- [ ] Aktualizovat line 20 v `install_role_based_access.php`
- [ ] Hledat všechny ostatní `error_log(...$_POST...)` + `error_log(...$_SERVER...)`
- [ ] Vytvořit linting pravidlo: "nikdy nelog $_POST bez filtrace"
- [ ] Aktualizovat `.gitignore` pro log soubory

## Preventivní opatření

Přidejte do CI/CD pipeline:

```yaml
# .github/workflows/security.yml
- name: Check for sensitive data in logs
  run: |
    if grep -r "print_r.*_POST\|var_dump.*_POST\|var_dump.*_SERVER" \
           --include="*.php" /home/user/moje-stranky; then
      echo "ERROR: Sensitive data logging detected!"
      exit 1
    fi
```

## Rizika ignorování

1. **Audit Failure** - Selhání compliance auditu
2. **Data Breach** - Pokud jsou logy hacknuty
3. **Legal Liability** - GDPR pokuty (4% obratu!)
4. **Reputation** - Novinové články o selhání bezpečnosti
5. **System Compromise** - Attacker vidí admin hesla v logech

## Timeline

- **Okamžitě (< 30 minut):** Smazat/schovat problematickou linku
- **Do 1 hodiny:** Provést audit všech loggings
- **Do 1 dne:** Opravit všechny nalezené problémy
- **Do 1 týdne:** Implementovat helper funkce + CI/CD kontroly
- **Do 1 měsíce:** Audit archive logů pro staré hesla

---

**Přiřazeno:** OKAMŽITĚ - KRITICKÁ  
**Priorita:** 🔴 HIGHEST  
**Odhad času:** 15-30 minut na fix
