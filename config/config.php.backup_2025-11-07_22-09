<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
/**
 * WHITE GLOVE SERVICE - CONFIG - OPRAVENÁ VERZE
 * SESSION musí být nastaven PRVNÍ, před jakýmkoli outputem!
 */

// ========== NAČTENÍ .ENV SOUBORU ==========
require_once __DIR__ . '/../includes/env_loader.php';

// ========== SESSION KONFIGURACE ==========
// Session je spouštěna v init.php, zde už není třeba
// Tato konfigurace byla přesunuta do init.php pro zajištění správného pořadí načítání

/* =============================================================
   WHITE GLOVE SERVICE – KONFIGURACE
   ============================================================= */

// ========== DATABÁZE ==========
// Načítáme z environment variables (.env soubor)
define('DB_HOST', getenv('DB_HOST') ?: $_ENV['DB_HOST'] ?: '127.0.0.1');
define('DB_NAME', getenv('DB_NAME') ?: $_ENV['DB_NAME'] ?: 'wgs-servicecz01');
define('DB_USER', getenv('DB_USER') ?: $_ENV['DB_USER'] ?: 'wgs-servicecz002');
define('DB_PASS', getenv('DB_PASS') ?: $_ENV['DB_PASS'] ?: die('CHYBA: DB_PASS není nastaveno v prostředí! Zkontrolujte .env soubor.'));

// ========== ADMIN KLÍČ ==========
// Admin se přihlašuje pouze registračním klíčem (hashovaný v .env)
define('ADMIN_KEY_HASH', getenv('ADMIN_KEY_HASH') ?: $_ENV['ADMIN_KEY_HASH'] ?: die('CHYBA: ADMIN_KEY_HASH není nastaveno v prostředí! Zkontrolujte .env soubor.'));

// ========== EMAIL / SMTP ==========
define('SMTP_HOST', getenv('SMTP_HOST') ?: $_ENV['SMTP_HOST'] ?: 'smtp.ceskyhosting.cz');
define('SMTP_PORT', getenv('SMTP_PORT') ?: $_ENV['SMTP_PORT'] ?: 587);
define('SMTP_FROM', getenv('SMTP_FROM') ?: $_ENV['SMTP_FROM'] ?: 'reklamace@wgs-service.cz');
define('SMTP_FROM_NAME', 'White Glove Service');
define('SMTP_USER', getenv('SMTP_USER') ?: $_ENV['SMTP_USER'] ?: 'reklamace@wgs-service.cz');
define('SMTP_PASS', getenv('SMTP_PASS') ?: $_ENV['SMTP_PASS'] ?: die('CHYBA: SMTP_PASS není nastaveno v prostředí! Zkontrolujte .env soubor.'));

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

function isStrongPassword($password) {
    if (strlen($password) < 8) {
        return false;
    }
    
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        return false;
    }
    
    return true;
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
    header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://unpkg.com; style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com https://unpkg.com; img-src 'self' data: blob: https://maps.geoapify.com; font-src 'self' data: https://fonts.googleapis.com; connect-src 'self' https://api.geoapify.com https://maps.geoapify.com;");
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
define('DEEPL_API_KEY', getenv('DEEPL_API_KEY') ?: $_ENV['DEEPL_API_KEY'] ?: 'optional_later');

// ========== JWT SECRET ==========
define('JWT_SECRET', getenv('JWT_SECRET') ?: $_ENV['JWT_SECRET'] ?: die('CHYBA: JWT_SECRET není nastaveno v prostředí! Zkontrolujte .env soubor.'));
// ========== GEOAPIFY API (MAPY) ==========
define('GEOAPIFY_KEY', getenv('GEOAPIFY_KEY') ?: ($_ENV["GEOAPIFY_KEY"] ?? "a4b2955eeb674dd8b6601f54da2e80a8"));
