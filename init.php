<?php

// Enable output buffering to prevent "headers already sent" errors
ob_start();

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
/**
 * WGS Service - Bootstrap File
 * Central initialization for all PHP files
 */

// Define base paths
define('BASE_PATH', dirname(__FILE__));
define('CONFIG_PATH', BASE_PATH . '/config');
define('APP_PATH', BASE_PATH . '/app');
define('API_PATH', BASE_PATH . '/api');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('PUBLIC_PATH', BASE_PATH . '/public');
define('CONTROLLERS_PATH', APP_PATH . '/controllers');
define('MODELS_PATH', APP_PATH . '/models');
define('VIEWS_PATH', APP_PATH . '/views');
define('TEMP_PATH', BASE_PATH . '/temp');
define('LOGS_PATH', BASE_PATH . '/logs');

// Load environment variables from .env file
require_once INCLUDES_PATH . '/env_loader.php';

// Load configuration
require_once CONFIG_PATH . '/config.php';

// ✅ SECURITY HEADERS - načíst před jakýmkoli outputem
require_once INCLUDES_PATH . '/security_headers.php';

// Load helper functions
require_once INCLUDES_PATH . '/csrf_helper.php';
require_once INCLUDES_PATH . '/seo_meta.php';

// Load advanced error handler (with detailed error reporting)
require_once INCLUDES_PATH . '/error_handler.php';

// Set error reporting based on environment
if (defined('ENVIRONMENT') && ENVIRONMENT === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', LOGS_PATH . '/php_errors.log');
}

// Session configuration - nastavujeme správně a spouštíme session
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);

    // cookie_secure pouze pro HTTPS
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

    ini_set('session.cookie_secure', $isHttps ? 1 : 0);
    ini_set('session.cookie_path', '/');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.gc_maxlifetime', 3600);
    ini_set('session.cookie_lifetime', 3600);

    session_start();
}

// Helper function to include files from different directories
function load_controller($filename) {
    $path = CONTROLLERS_PATH . '/' . $filename;
    if (file_exists($path)) {
        return require_once $path;
    }
    return false;
}

function load_model($filename) {
    $path = MODELS_PATH . '/' . $filename;
    if (file_exists($path)) {
        return require_once $path;
    }
    return false;
}

function load_view($filename) {
    $path = VIEWS_PATH . '/' . $filename;
    if (file_exists($path)) {
        return require_once $path;
    }
    return false;
}
