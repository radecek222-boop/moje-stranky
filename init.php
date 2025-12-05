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

// SECURITY HEADERS - načíst před jakýmkoli outputem
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
    // FIX 5: Secure cookie flag podle environmentu (ne runtime detekce)
    // Eliminuje HTTP/HTTPS cookie mismatch - environment-based secure flag
    $isProduction = (getEnvValue('ENVIRONMENT') ?? 'production') === 'production';
    $secureFlag = $isProduction ? true : false;

    // FIX 5: HTTPS redirect na produkci
    // Zajišťuje, že produkce vždy používá HTTPS → eliminuje mismatch
    if ($isProduction) {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                   || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

        if (!$isHttps) {
            header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
            exit;
        }
    }

    // FIX Safari ITP: Explicitní session name
    session_name('WGS_SESSION');

    // FIX PWA: Detekce PWA standalone módu
    // PWA může poslat custom header nebo použít specific display-mode
    // iOS Safari v PWA módu nese "Safari" v User-Agent ale nemá "CriOS" nebo "FxiOS"
    $isPWA = false;

    // Metoda 1: Custom header z PWA (nastaveno v service-worker nebo fetch interceptor)
    if (isset($_SERVER['HTTP_X_PWA_MODE']) && $_SERVER['HTTP_X_PWA_MODE'] === 'standalone') {
        $isPWA = true;
    }

    // Metoda 2: Referer obsahuje ?source=pwa (parametr z manifest.json start_url)
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    if (strpos($referer, 'source=pwa') !== false || strpos($referer, 'utm_source=pwa') !== false) {
        $isPWA = true;
    }

    // Metoda 3: Request URI obsahuje pwa parametr
    $requestUri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($requestUri, 'source=pwa') !== false || strpos($requestUri, 'utm_source=pwa') !== false) {
        $isPWA = true;
    }

    // Metoda 4: Cookie flag nastavený z JS při prvním spuštění PWA
    if (isset($_COOKIE['wgs_pwa_mode']) && $_COOKIE['wgs_pwa_mode'] === '1') {
        $isPWA = true;
    }

    // FIX PWA: Delší session lifetime pro PWA mód
    // Důvod: PWA standalone mode v iOS/Android ruší session cookie při každém "zavření" aplikace
    // Řešení: Pro PWA nastavit 7 dní, pro browser 0 (do zavření)
    $sessionLifetime = $isPWA ? (7 * 24 * 60 * 60) : 0;  // 7 dní pro PWA, 0 pro browser

    // FIX: Použití session_set_cookie_params() se STAROU syntaxí pro PHP 7.x kompatibilitu
    // Safari ITP fix: domain = NULL místo prázdného stringu
    // FIX 5: secure flag je nyní environment-based (ne runtime)
    session_set_cookie_params(
        $sessionLifetime,   // FIX PWA: lifetime - 7 dní pro PWA, 0 pro browser
        '/',                // path - celá doména
        NULL,               // domain - NULL místo '' (Safari compatibility)
        $secureFlag,        // FIX 5: secure - environment-based (production=true, dev=false)
        true                // httponly - ochrana proti XSS
    );

    // SameSite musí být nastaven přes ini_set (není v session_set_cookie_params v PHP 7.2)
    // FIX PWA: Změněno zpět na 'Lax' kvůli PWA kompatibilitě
    // 'Strict' způsoboval problémy s admin login v PWA módu (iOS Safari, Android Chrome)
    // 'Lax' stále chrání POST requesty proti CSRF, ale umožňuje session při navigaci z PWA
    // Poznámka: CSRF token validace poskytuje dodatečnou ochranu
    if (PHP_VERSION_ID >= 70300) {
        ini_set('session.cookie_samesite', 'Lax');
    }

    // Nastavení garbage collection
    // FIX 3: Zvýšení gc_maxlifetime z 1 hodiny na 24 hodin
    // Eliminuje předčasné vypršení session a ztrátu CSRF tokenu
    ini_set('session.gc_maxlifetime', 86400);  // 24 hodin (24 * 60 * 60)
    ini_set('session.use_only_cookies', 1);

    session_start();

    // FIX 6: Inactivity timeout - automatické vypršení session po 30 min neaktivity
    // Ochrana proti session hijacking na opuštěných zařízeních (Security Issue 6)
    // OWASP A07: Identification and Authentication Failures - CWE-613 mitigation
    $inactivityTimeout = 1800; // 30 minut (30 * 60 sekund)

    if (isset($_SESSION['user_id'])) {
        $lastActivity = $_SESSION['last_activity'] ?? null;

        if ($lastActivity !== null && (time() - $lastActivity) > $inactivityTimeout) {
            // Session vypršela z důvodu neaktivity
            // SECURITY FIX: Regenerovat session ID pro prevenci session fixation
            // Pokud útočník ukradl session ID, nemůže ho použít po timeout + relogin
            session_regenerate_id(true);

            // Vymazat autentizační data
            unset($_SESSION['user_id']);
            unset($_SESSION['admin_id']);
            unset($_SESSION['is_admin']);
            unset($_SESSION['role']);
            unset($_SESSION['user_name']);
            unset($_SESSION['user_email']);
            unset($_SESSION['admin_email']);
            unset($_SESSION['login_time']);
            unset($_SESSION['login_method']);
            unset($_SESSION['last_activity']);

            // Nastavit flag pro timeout
            $_SESSION['is_logged_in'] = false;
            $_SESSION['timeout_occurred'] = true;

            // CSRF token bude regenerován při příštím přihlášení
        } else {
            // Session je aktivní - aktualizovat last_activity
            $_SESSION['last_activity'] = time();

            // FIX: Aktualizovat last_activity v databázi pro online sledování
            // Throttle: aktualizovat max jednou za 10 sekund aby se nezatěžovala DB
            $posledniDbAktualizace = $_SESSION['last_db_activity_update'] ?? 0;
            if ((time() - $posledniDbAktualizace) >= 10) {
                try {
                    $pdo = getDbConnection();
                    $userId = $_SESSION['user_id'];

                    // Pokusit se aktualizovat existujiciho uzivatele
                    $stmt = $pdo->prepare("UPDATE wgs_users SET last_activity = NOW() WHERE user_id = :user_id");
                    $stmt->execute([':user_id' => $userId]);

                    // Pokud se nic neaktualizovalo (admin bez zaznamu v DB), vytvorit zaznam
                    if ($stmt->rowCount() === 0 && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
                        $stmt = $pdo->prepare("
                            INSERT INTO wgs_users (user_id, name, email, role, is_active, last_activity, created_at)
                            VALUES (:user_id, :name, :email, 'admin', 1, NOW(), NOW())
                            ON DUPLICATE KEY UPDATE last_activity = NOW()
                        ");
                        $stmt->execute([
                            ':user_id' => $userId,
                            ':name' => $_SESSION['user_name'] ?? 'Administrator',
                            ':email' => $_SESSION['user_email'] ?? 'admin@wgs-service.cz'
                        ]);
                    }

                    $_SESSION['last_db_activity_update'] = time();
                } catch (Exception $e) {
                    // Ignorovat chyby - nechceme blokovat požadavek kvůli online sledování
                    error_log("Online tracking update failed: " . $e->getMessage());
                }
            }
        }
    }

    // FIX 11: Auto-login z Remember Me tokenu
    // Funguje i po inactivity timeout - pokud session byla prázdná, Remember Me přihlásí
    if (!isset($_SESSION['user_id']) && isset($_COOKIE['remember_me'])) {
        require_once __DIR__ . '/includes/remember_me_handler.php';
        handleRememberMeLogin();
    }
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

/**
 * Heatmap Tracker - vložit na všechny veřejné stránky
 * Volat před </body> tag
 */
function renderHeatmapTracker() {
    // Skip admin a API stránky
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    if (strpos($uri, '/admin') !== false || strpos($uri, '/api/') !== false) {
        return;
    }

    // Vygenerovat CSRF token
    if (!isset($_SESSION['csrf_token'])) {
        if (function_exists('generateCSRFToken')) {
            generateCSRFToken();
        }
    }

    $csrfToken = $_SESSION['csrf_token'] ?? '';
    ?>
<!-- Heatmap Tracker -->
<meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
<script src="/assets/js/heatmap-tracker.min.js" defer></script>
<?php
}

function load_view($filename) {
    $path = VIEWS_PATH . '/' . $filename;
    if (file_exists($path)) {
        return require_once $path;
    }
    return false;
}
