<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
/**
 * WHITE GLOVE SERVICE - CONFIG - OPRAVENÁ VERZE
 * SESSION musí být nastaven PRVNÍ, před jakýmkoli outputem!
 */

// ========== NAČTENÍ .ENV SOUBORU ==========
require_once __DIR__ . '/../includes/env_loader.php';

if (!function_exists('getEnvValue')) {
    /**
     * Vrátí hodnotu proměnné prostředí, pokud je k dispozici.
     */
    function getEnvValue($key) {
        $value = getenv($key);
        if ($value !== false && $value !== '') {
            return $value;
        }

        if (isset($_ENV[$key]) && $_ENV[$key] !== '') {
            return $_ENV[$key];
        }

        if (isset($_SERVER[$key]) && $_SERVER[$key] !== '') {
            return $_SERVER[$key];
        }

        return null;
    }
}

if (!function_exists('requireEnvValue')) {
    /**
     * Vrátí hodnotu povinné proměnné prostředí nebo ukončí aplikaci s chybou.
     */
    function requireEnvValue($key, $message) {
        $value = getEnvValue($key);

        if ($value === null) {
            error_log("CRITICAL: Missing required environment variable: {$key}");
            die($message);
        }

        return $value;
    }
}

// ========== SESSION KONFIGURACE ==========
// Session je spouštěna v init.php, zde už není třeba
// Tato konfigurace byla přesunuta do init.php pro zajištění správného pořadí načítání

/* =============================================================
   WHITE GLOVE SERVICE – KONFIGURACE
   ============================================================= */

// ========== DATABÁZE ==========
// Načítáme z environment variables (.env soubor)
// BEZPEČNOST: Žádné fallbacky pro credentials - pokud chybí, aplikace musí spadnout
define('DB_HOST', requireEnvValue('DB_HOST', 'CHYBA: DB_HOST není nastaveno v prostředí! Zkontrolujte konfiguraci serveru.'));
define('DB_NAME', requireEnvValue('DB_NAME', 'CHYBA: DB_NAME není nastaveno v prostředí! Zkontrolujte konfiguraci serveru.'));
define('DB_USER', requireEnvValue('DB_USER', 'CHYBA: DB_USER není nastaveno v prostředí! Zkontrolujte konfiguraci serveru.'));
define('DB_PASS', requireEnvValue('DB_PASS', 'CHYBA: DB_PASS není nastaveno v prostředí! Zkontrolujte konfiguraci serveru.'));

// ========== ADMIN KLÍČ ==========
// Admin se přihlašuje pouze registračním klíčem (hashovaný v .env)
define('ADMIN_KEY_HASH', requireEnvValue('ADMIN_KEY_HASH', 'CHYBA: ADMIN_KEY_HASH není nastaveno v prostředí! Zkontrolujte konfiguraci serveru.'));
$adminHighKeyHash = getEnvValue('ADMIN_HIGH_KEY_HASH');
define('ADMIN_HIGH_KEY_HASH', $adminHighKeyHash ?: null);

// ========== EMAIL / SMTP ==========
// BEZPEČNOST: Žádné fallbacky pro SMTP credentials
define('SMTP_HOST', requireEnvValue('SMTP_HOST', 'CHYBA: SMTP_HOST není nastaveno v prostředí! Zkontrolujte konfiguraci serveru.'));
define('SMTP_PORT', requireEnvValue('SMTP_PORT', 'CHYBA: SMTP_PORT není nastaveno v prostředí! Zkontrolujte konfiguraci serveru.'));
define('SMTP_FROM', requireEnvValue('SMTP_FROM', 'CHYBA: SMTP_FROM není nastaveno v prostředí! Zkontrolujte konfiguraci serveru.'));
define('SMTP_FROM_NAME', 'White Glove Service');
define('SMTP_USER', requireEnvValue('SMTP_USER', 'CHYBA: SMTP_USER není nastaveno v prostředí! Zkontrolujte konfiguraci serveru.'));
define('SMTP_PASS', requireEnvValue('SMTP_PASS', 'CHYBA: SMTP_PASS není nastaveno v prostředí! Zkontrolujte konfiguraci serveru.'));

// ========== PATHS ==========
define('ROOT_PATH', dirname(__DIR__));
define('LOGS_PATH', ROOT_PATH . '/logs');
// TEMP_PATH je již definována v init.php - nemusíme ji zde znovu definovat

// ========== SECURITY LOGGING ==========
function logSecurity($message) {
    $file = LOGS_PATH . '/security.log';
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $message = mb_convert_encoding($message, 'UTF-8', 'UTF-8');
    $line = "[$timestamp] [$ip] $message\n";

    if (!file_exists($file)) {
        touch($file);
        chmod($file, 0600);
    }

    file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
}

// ========== RATE LIMITING ==========
function checkRateLimit($identifier, $maxAttempts = 5, $timeWindow = 900) {
    $file = TEMP_PATH . '/rate_limits.json';
    $now = time();
    
    $limits = [];
    if (file_exists($file)) {
        $limits = json_decode(file_get_contents($file), true) ?: [];
    }
    
    $limits = array_filter($limits, function($data) use ($now, $timeWindow) {
        return ($now - $data['first_attempt']) < $timeWindow;
    });
    
    if (!isset($limits[$identifier])) {
        $limits[$identifier] = [
            'attempts' => 0,
            'first_attempt' => $now
        ];
    }
    
    $attempts = $limits[$identifier]['attempts'];
    $remaining = max(0, $maxAttempts - $attempts);
    $resetAt = $limits[$identifier]['first_attempt'] + $timeWindow;
    
    file_put_contents($file, json_encode($limits, JSON_PRETTY_PRINT), LOCK_EX);
    
    return [
        'allowed' => $attempts < $maxAttempts,
        'remaining' => $remaining,
        'reset_at' => $resetAt,
        'retry_after' => max(0, $resetAt - $now)
    ];
}

function recordLoginAttempt($identifier) {
    $file = TEMP_PATH . '/rate_limits.json';
    $limits = [];
    
    if (file_exists($file)) {
        $limits = json_decode(file_get_contents($file), true) ?: [];
    }
    
    if (!isset($limits[$identifier])) {
        $limits[$identifier] = [
            'attempts' => 0,
            'first_attempt' => time()
        ];
    }
    
    $limits[$identifier]['attempts']++;
    file_put_contents($file, json_encode($limits, JSON_PRETTY_PRINT), LOCK_EX);
}

function resetRateLimit($identifier) {
    $file = TEMP_PATH . '/rate_limits.json';
    
    if (file_exists($file)) {
        $limits = json_decode(file_get_contents($file), true) ?: [];
        unset($limits[$identifier]);
        file_put_contents($file, json_encode($limits, JSON_PRETTY_PRINT), LOCK_EX);
    }
}

// ========== UTILITY FUNKCE ==========
function generateRegistrationKey($prefix = 'KEY') {
    $year = date('Y');
    $random = strtoupper(bin2hex(random_bytes(4)));
    return $prefix . $year . $random;
}

function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Kontrola síly hesla podle bezpečnostních standardů
 * @param string $password Heslo ke kontrole
 * @return true|array True pokud je heslo silné, jinak array s chybami
 */
function isStrongPassword($password) {
    $errors = [];

    // Minimální délka 12 znaků
    if (strlen($password) < 12) {
        $errors[] = 'Minimálně 12 znaků';
    }

    // Alespoň jedno velké písmeno
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = 'Alespoň 1 velké písmeno (A-Z)';
    }

    // Alespoň jedno malé písmeno
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = 'Alespoň 1 malé písmeno (a-z)';
    }

    // Alespoň jedno číslo
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = 'Alespoň 1 číslo (0-9)';
    }

    // Alespoň jeden speciální znak
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        $errors[] = 'Alespoň 1 speciální znak (!@#$%^&*...)';
    }

    // Kontrola proti běžným heslům
    $commonPasswords = [
        'password', 'password123', 'heslo', 'heslo123', 'admin123',
        '12345678', '123456789', 'qwerty123', 'abc123456',
        'password1234', 'admin1234', 'natuzzi123'
    ];

    if (in_array(strtolower($password), $commonPasswords)) {
        $errors[] = 'Heslo je příliš běžné, zvolte unikátnější';
    }

    // Vrátit true nebo pole s chybami
    return empty($errors) ? true : $errors;
}

function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ========== ČASOVÁ ZÓNA ==========
date_default_timezone_set('Europe/Prague');

// ========== ERROR REPORTING ==========
if (($_SERVER['SERVER_NAME'] ?? 'localhost') === 'localhost' || 
    ($_SERVER['SERVER_NAME'] ?? '127.0.0.1') === '127.0.0.1') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOGS_PATH . '/php_errors.log');
}

// ========== BEZPEČNOSTNÍ HLAVIČKY ==========
function setSecurityHeaders() {
    header("X-Content-Type-Options: nosniff");
    header("X-Frame-Options: SAMEORIGIN");
    header("X-XSS-Protection: 1; mode=block");
    // CSP - odstraněn 'unsafe-eval' pro lepší bezpečnost
    header("Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://unpkg.com; " .
        "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://unpkg.com https://fonts.googleapis.com; " .
        "img-src 'self' data: blob: https://maps.geoapify.com; " .
        "font-src 'self' data: https://fonts.googleapis.com https://fonts.gstatic.com; " .
        "connect-src 'self' https://api.geoapify.com https://maps.geoapify.com https://fonts.googleapis.com https://fonts.gstatic.com;"
    );
    header("Referrer-Policy: strict-origin-when-cross-origin");
}

// ========== DATABÁZOVÉ PŘIPOJENÍ ==========
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
            ];
            
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please contact administrator.");
        }
    }
    
    return $pdo;
}

// ========== AUTOMATICKÉ ČIŠTĚNÍ STARÝCH SESSIONS ==========
function cleanupOldSessions() {
    try {
        $pdo = getDbConnection();
        $stmt = $pdo->prepare("DELETE FROM wgs_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
        $stmt->execute();
    } catch (Exception $e) {
        error_log("Session cleanup failed: " . $e->getMessage());
    }
}

if (rand(1, 100) === 1) {
    cleanupOldSessions();
}

// ========== DEEPL API PRO PŘEKLADY ==========
define('DEEPL_API_KEY', getEnvValue('DEEPL_API_KEY') ?: 'optional_later');

// ========== JWT SECRET ==========
define('JWT_SECRET', requireEnvValue('JWT_SECRET', 'CHYBA: JWT_SECRET není nastaveno v prostředí! Zkontrolujte konfiguraci serveru.'));
// ========== GEOAPIFY API (MAPY) ==========
// BEZPEČNOST: Žádný hardcodovaný API klíč - musí být v .env
define('GEOAPIFY_KEY', requireEnvValue('GEOAPIFY_API_KEY', 'CHYBA: GEOAPIFY_API_KEY není nastaveno v prostředí! Zkontrolujte konfiguraci serveru.'));
