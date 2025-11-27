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
// DB_* konstanty jsou již definovány v env_loader.php s fallbacky
// Pokud nejsou definovány, definujeme je zde (backup)
if (!defined('DB_HOST')) {
    define('DB_HOST', getEnvValue('DB_HOST', 'localhost'));
}
if (!defined('DB_NAME')) {
    define('DB_NAME', getEnvValue('DB_NAME', 'wgs-servicecz01'));
}
if (!defined('DB_USER')) {
    define('DB_USER', getEnvValue('DB_USER', 'root'));
}
if (!defined('DB_PASS')) {
    define('DB_PASS', getEnvValue('DB_PASS', ''));
}

// ========== ADMIN KLÍČ ==========
// Admin se přihlašuje pouze registračním klíčem (hashovaný v .env)
// FALLBACK: Development hodnota pokud není v env
if (!defined('ADMIN_KEY_HASH')) {
    define('ADMIN_KEY_HASH', getEnvValue('ADMIN_KEY_HASH', 'change-in-production'));
}
$adminHighKeyHash = getEnvValue('ADMIN_HIGH_KEY_HASH');
if (!defined('ADMIN_HIGH_KEY_HASH')) {
    define('ADMIN_HIGH_KEY_HASH', $adminHighKeyHash ?: null);
}

// ========== EMAIL / SMTP ==========
// FALLBACK: Development hodnoty pokud nejsou v env
if (!defined('SMTP_HOST')) {
    define('SMTP_HOST', getEnvValue('SMTP_HOST', 'localhost'));
}
if (!defined('SMTP_PORT')) {
    define('SMTP_PORT', getEnvValue('SMTP_PORT', '587'));
}
if (!defined('SMTP_FROM')) {
    define('SMTP_FROM', getEnvValue('SMTP_FROM', 'noreply@localhost'));
}
if (!defined('SMTP_FROM_NAME')) {
    define('SMTP_FROM_NAME', 'White Glove Service');
}
if (!defined('SMTP_USER')) {
    define('SMTP_USER', getEnvValue('SMTP_USER', ''));
}
if (!defined('SMTP_PASS')) {
    define('SMTP_PASS', getEnvValue('SMTP_PASS', ''));
}

// ========== PATHS ==========
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}
if (!defined('LOGS_PATH')) {
    define('LOGS_PATH', ROOT_PATH . '/logs');
}
// TEMP_PATH je již definována v init.php - nemusíme ji zde znovu definovat

// ========== SECURITY LOGGING ==========
if (!function_exists('logSecurity')) {
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
}

// ========== UTILITY FUNKCE ==========
if (!function_exists('generateRegistrationKey')) {
    function generateRegistrationKey($prefix = 'KEY') {
        $year = date('Y');
        $random = strtoupper(bin2hex(random_bytes(4)));
        return $prefix . $year . $random;
    }
}

if (!function_exists('sanitizeInput')) {
    function sanitizeInput($data) {
        if (is_array($data)) {
            return array_map('sanitizeInput', $data);
        }

        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
        return $data;
    }
}

/**
 * Kontrola síly hesla podle bezpečnostních standardů
 * @param string $password Heslo ke kontrole
 * @return true|array True pokud je heslo silné, jinak array s chybami
 */
if (!function_exists('isStrongPassword')) {
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
}

// ========== CSRF FUNKCE ==========
// POZNÁMKA: generateCSRFToken() a validateCSRFToken() jsou definovány v includes/csrf_helper.php
// Nemusíme je zde duplikovat

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
if (!function_exists('setSecurityHeaders')) {
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
            "connect-src 'self' data: https://api.geoapify.com https://maps.geoapify.com https://fonts.googleapis.com https://fonts.gstatic.com;"
        );
        header("Referrer-Policy: strict-origin-when-cross-origin");
    }
}

// ========== DATABÁZOVÉ PŘIPOJENÍ ==========
if (!function_exists('getDbConnection')) {
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
}

// ========== AUTOMATICKÉ ČIŠTĚNÍ STARÝCH SESSIONS ==========
// POZNÁMKA: wgs_sessions tabulka byla odstraněna - používáme PHP file-based sessions
// Cleanup již není potřeba, PHP sessions se čistí automaticky přes garbage collection

// function cleanupOldSessions() {
//     try {
//         $pdo = getDbConnection();
//         $stmt = $pdo->prepare("DELETE FROM wgs_sessions WHERE last_activity < DATE_SUB(NOW(), INTERVAL 24 HOUR)");
//         $stmt->execute();
//     } catch (Exception $e) {
//         error_log("Session cleanup failed: " . $e->getMessage());
//     }
// }
//
// if (rand(1, 100) === 1) {
//     cleanupOldSessions();
// }

// ========== DEEPL API PRO PŘEKLADY ==========
if (!defined('DEEPL_API_KEY')) {
    define('DEEPL_API_KEY', getEnvValue('DEEPL_API_KEY') ?: 'optional_later');
}

// ========== JWT SECRET ==========
// FALLBACK: Development hodnota pokud není v env
if (!defined('JWT_SECRET')) {
    define('JWT_SECRET', getEnvValue('JWT_SECRET', 'change-this-in-production-INSECURE'));
}

// ========== GEOAPIFY API (MAPY) ==========
// FALLBACK: Development hodnota pokud není v env
$geoapifyKey = getEnvValue('GEOAPIFY_API_KEY');
if ($geoapifyKey === null || $geoapifyKey === '') {
    // Backward compatibility: původní název proměnné v .env byl GEOAPIFY_KEY
    $geoapifyKey = getEnvValue('GEOAPIFY_KEY');
}
if (!defined('GEOAPIFY_KEY')) {
    define('GEOAPIFY_KEY', $geoapifyKey !== null && $geoapifyKey !== '' ? $geoapifyKey : 'change-this-in-production');
}

// ========== ENVIRONMENT CONFIGURATION ==========
// Určení prostředí (development, staging, production)
$environment = getEnvValue('ENVIRONMENT') ?? 'production';
if (!defined('APP_ENV')) {
    define('APP_ENV', $environment);
}
if (!defined('IS_PRODUCTION')) {
    define('IS_PRODUCTION', $environment === 'production');
}
if (!defined('IS_DEVELOPMENT')) {
    define('IS_DEVELOPMENT', $environment === 'development');
}
if (!defined('IS_STAGING')) {
    define('IS_STAGING', $environment === 'staging');
}

// Error reporting podle prostředí
if (IS_DEVELOPMENT) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ALL);
    ini_set('display_errors', 0);
    ini_set('display_startup_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOGS_PATH . '/php_errors.log');
}
