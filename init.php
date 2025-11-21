<?php

/**
 * WGS Service - Bootstrap File
 * Central initialization for all PHP files
 */

// Enable output buffering to prevent "headers already sent" errors
ob_start();

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
    // ✅ FIX 5: Secure cookie flag podle environmentu (ne runtime detekce)
    // Eliminuje HTTP/HTTPS cookie mismatch - environment-based secure flag
    $isProduction = (getEnvValue('ENVIRONMENT') ?? 'production') === 'production';
    $secureFlag = $isProduction ? true : false;

    // ✅ FIX 5: HTTPS redirect na produkci
    // Zajišťuje, že produkce vždy používá HTTPS → eliminuje mismatch
    if ($isProduction) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

        if (!$isHttps) {
            header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
            exit;
        }
    }

    // ✅ FIX Safari ITP: Explicitní session name
    session_name('WGS_SESSION');

    // ✅ FIX: Použití session_set_cookie_params() se STAROU syntaxí pro PHP 7.x kompatibilitu
    // Safari ITP fix: domain = NULL místo prázdného stringu, lifetime = 0 (browser session)
    // ✅ FIX 5: secure flag je nyní environment-based (ne runtime)
    session_set_cookie_params(
        0,              // lifetime - 0 = do zavření prohlížeče (lepší pro Safari ITP)
        '/',            // path - celá doména
        NULL,           // domain - NULL místo '' (Safari compatibility)
        $secureFlag,    // ✅ FIX 5: secure - environment-based (production=true, dev=false)
        true            // httponly - ochrana proti XSS
    );

    // SameSite musí být nastaven přes ini_set (není v session_set_cookie_params v PHP 7.2)
    // DŮLEŽITÉ: Použijeme 'Lax' pro lepší kompatibilitu na mobilech
    // 'Lax' umožňuje first-party cookies (login, navigace) ale blokuje cross-site POST
    // Toto je bezpečnější než 'None' a funguje lépe na mobilních zařízeních
    if (PHP_VERSION_ID >= 70300) {
        ini_set('session.cookie_samesite', 'Lax');
    }

    // Nastavení garbage collection
    // ✅ FIX 3: Zvýšení gc_maxlifetime z 1 hodiny na 24 hodin
    // Eliminuje předčasné vypršení session a ztrátu CSRF tokenu
    ini_set('session.gc_maxlifetime', 86400);  // 24 hodin (24 * 60 * 60)
    ini_set('session.use_only_cookies', 1);

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
