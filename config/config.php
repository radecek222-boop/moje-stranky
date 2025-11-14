<?php
if (session_status() === PHP_SESSION_NONE) { session_start(); }
// ========== RATE LIMITING ==========
// ========== DATABÁZOVÉ PŘIPOJENÍ ==========
/**
 * GetDbConnection
 */
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
/**
 * CleanupOldSessions
 */
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

// ========== ENVIRONMENT CONFIGURATION ==========
// Určení prostředí (development, staging, production)
$environment = getEnvValue('ENVIRONMENT') ?? 'production';
define('APP_ENV', $environment);
define('IS_PRODUCTION', $environment === 'production');
define('IS_DEVELOPMENT', $environment === 'development');
define('IS_STAGING', $environment === 'staging');

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
