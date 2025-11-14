# Includes - Helper Functions & Classes

Sdílené helper funkce, třídy a utilities pro WGS Service.

## 📁 Hlavní Komponenty

### 🔐 Security & Authentication
- `csrf_helper.php` - CSRF token generování a validace
- `rate_limiter.php` - Rate limiting třída (ochrana proti brute-force)
- `audit_logger.php` - Security audit logging

### 📧 Email & Notifications
- `EmailQueue.php` - Email fronta s retry mechanikou
- `phpmailer_config.php` - PHPMailer konfigurace
- `qr_payment_helper.php` - QR kódy pro platby

### 🎨 UI & Frontend
- `admin_navigation.php` - Admin menu struktura
- `control_center_appearance.php` - Control Center vzhled
- `control_center_console.php` - Diagnostická konzole
- `user_header.php` - User hlavička

### 🗄️ Database & Data
- `db.php` / `init.php` - Database připojení
- `database_helpers.php` - DB utility funkce

### 🛠️ Utilities (NOVÉ!)
- `api_response.php` - **Standardizované API responses**
- `safe_file_operations.php` - **Bezpečné file operace (náhrada za @)**

### 🔍 Diagnostics & Testing
- `control_center_testing.php` - Testovací funkce
- `control_center_actions.php` - Akce pro Control Center
- `security_scanner.php` - Security scanner

## 🎯 Nejpoužívanější Funkce

### CSRF Protection
```php
require_once __DIR__ . '/includes/csrf_helper.php';

// Vygenerovat token
$token = generateCSRFToken();

// Validovat token
if (!validateCSRFToken($_POST['csrf_token'])) {
    die('Invalid CSRF token');
}
```

### Rate Limiting
```php
require_once __DIR__ . '/includes/rate_limiter.php';

$rateLimiter = new RateLimiter(getDbConnection());
$result = $rateLimiter->checkLimit('login_' . $ip, 'login', [
    'max_attempts' => 5,
    'window_minutes' => 15,
    'block_minutes' => 30
]);

if (!$result['allowed']) {
    die('Too many attempts');
}
```

### API Responses (NOVÉ!)
```php
require_once __DIR__ . '/includes/api_response.php';

// Success
ApiResponse::success($data, 'Operace úspěšná');

// Error
ApiResponse::error('Chyba', 400);

// Validation
ApiResponse::validationError([
    'email' => 'Email je povinný',
    'password' => 'Heslo je příliš krátké'
]);

// Not found
ApiResponse::notFound('User', 123);

// Unauthorized
ApiResponse::unauthorized();

// Rate limit
ApiResponse::rateLimitExceeded(60);
```

### Safe File Operations (NOVÉ!)
```php
require_once __DIR__ . '/includes/safe_file_operations.php';

// Místo @file_get_contents($path)
$content = safeFileGetContents($path);
if ($content === false) {
    // Handle error
}

// Místo @file_put_contents($path, $data)
if (!safeFilePutContents($path, $data)) {
    // Handle error
}

// JSON operace
$data = safeJsonDecode($path);
safeJsonEncode($path, $data);

// Ostatní
$lines = safeFileToArray($path);
$size = safeFileSize($path);
safeFileDelete($path);
safeMkdir($path);
```

### Email Queue
```php
require_once __DIR__ . '/includes/EmailQueue.php';

$emailQueue = new EmailQueue(getDbConnection());

// Přidat do fronty
$emailQueue->enqueue([
    'to' => 'user@example.com',
    'to_name' => 'Jan Novák',
    'subject' => 'Test Email',
    'body' => 'Email obsah',
    'priority' => 'high'
]);

// Zpracovat frontu (cron job)
$results = $emailQueue->processQueue(10);
```

## 🔧 Best Practices

### 1. Error Suppression (@)
❌ **NEPOUŽÍVAT:**
```php
$content = @file_get_contents($path);
$result = @json_decode($data);
```

✅ **POUŽÍVAT:**
```php
$content = safeFileGetContents($path);
if ($content === false) {
    error_log("Failed to read: $path");
    // Handle error
}

$data = json_decode($json, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    error_log("JSON error: " . json_last_error_msg());
    // Handle error
}
```

### 2. Database Queries
❌ **NEPOUŽÍVAT SELECT *:**
```php
$stmt = $pdo->query("SELECT * FROM users");
```

✅ **SPECIFIKOVAT SLOUPCE:**
```php
$stmt = $pdo->query("SELECT id, email, name FROM users");
```

### 3. Security
✅ **VŽDY validovat input:**
```php
// CSRF pro POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Invalid CSRF token');
    }
}

// Rate limiting
$rateLimiter->checkLimit(...);

// Input validation
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Invalid email');
}
```

## 📊 Code Quality Metrics

### Current Status
- **Legacy Functions:** 195 issues (@ operator, deprecated funkce)
- **Documentation Coverage:** 12.5% (cíl: 30%+)
- **Dead Code:** 25 potenciálně nepoužívaných funkcí

### Priority Fixes
1. Nahradit @ operator za `safe_file_operations.php` funkce
2. Přidat PHPDoc komentáře k veřejným funkcím
3. Refactorovat duplicitní kód do shared helpers

## 🆕 Přidání Nového Helperu

1. Vytvořit nový soubor `my_helper.php`
2. Přidat file header:
```php
<?php
/**
 * My Helper
 * Stručný popis účelu
 */
```

3. Dokumentovat funkce:
```php
/**
 * Popis funkce
 *
 * @param string $param Popis parametru
 * @return bool Popis návratové hodnoty
 */
function myFunction($param) {
    // Implementation
}
```

4. Include v `init.php` pokud je globálně potřeba
5. Dokumentovat v tomto README

## 📚 Související Dokumentace

- `/docs/API_STANDARDIZATION_GUIDE.md` - API standardy
- `/FINAL_AUDIT_SUMMARY.md` - Kompletní audit přehled
- `/scripts/README.md` - Utility skripty

## 🐛 Debugging

Pro debugging helper funkcí:
1. Zkontrolovat PHP error log
2. Přidat `error_log()` volání
3. Použít `var_dump()` pro debug output
4. Zkontrolovat že je helper správně included

## ⚠️ Varování

- **NIKDY** nepoužívat `eval()`
- **NIKDY** nepoužívat `extract()` na user input
- **VŽDY** validovat input
- **VŽDY** použít prepared statements pro SQL
- **VŽDY** logovat chyby, ne je skrývat s @
