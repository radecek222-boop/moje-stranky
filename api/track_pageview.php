<?php
/**
 * Tracking API - Zaznamenávání pageviews
 * Endpoint pro JavaScript tracking skript
 *
 * SECURITY: Rate limiting chrání proti zneužití
 * POZOR: CSRF není vyžadováno - veřejný analytics endpoint pro anonymní uživatele
 */

// Nastavit error reporting pro debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Nezobrazovat chyby klientovi
ini_set('log_errors', 1);

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Pouze POST požadavky
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Pouze POST požadavky']);
    exit;
}

// SECURITY: CSRF není vyžadováno pro analytics tracking
// Důvod: Veřejný endpoint pro anonymní návštěvníky bez session
// Ochrana: Rate limiting (1000 req/h per IP) brání zneužití

// Vlastní shutdown handler pro zachycení fatálních chyb před načtením error_handler.php
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error !== null && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        // Ujistit se, že hlavičky jsou nastavené
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
            http_response_code(500);
        }
        error_log("Track pageview FATAL: " . $error['message'] . " in " . $error['file'] . ":" . $error['line']);
        echo json_encode([
            'status' => 'error',
            'message' => 'Chyba serveru'
        ]);
    }
});

try {
    // Načíst init.php s error handlingem
    $initPath = __DIR__ . '/../init.php';
    if (!file_exists($initPath)) {
        throw new Exception('Init file not found');
    }
    require_once $initPath;

    // Načíst rate limiter
    $rateLimiterPath = __DIR__ . '/../includes/rate_limiter.php';
    if (!file_exists($rateLimiterPath)) {
        throw new Exception('Rate limiter not found');
    }
    require_once $rateLimiterPath;

    // Získat IP adresu návštěvníka
    $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    if (strpos($ipAddress, ',') !== false) {
        $ipAddress = trim(explode(',', $ipAddress)[0]);
    }

    // GDPR: Anonymizovat IP adresu (poslední oktet = 0)
    if (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        $parts = explode('.', $ipAddress);
        $parts[3] = '0';
        $ipAddress = implode('.', $parts);
    } elseif (filter_var($ipAddress, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // IPv6 - anonymizovat posledních 64 bitů
        $ipAddress = preg_replace('/:[0-9a-fA-F]{1,4}:[0-9a-fA-F]{1,4}:[0-9a-fA-F]{1,4}:[0-9a-fA-F]{1,4}$/', ':0:0:0:0', $ipAddress);
    }

    // Pokusit se získat DB spojení
    $pdo = null;
    try {
        if (function_exists('getDbConnection')) {
            $pdo = getDbConnection();
        } else {
            throw new Exception('getDbConnection function not available');
        }
    } catch (Exception $dbEx) {
        error_log("Track pageview - DB connection failed: " . $dbEx->getMessage());
        // Fallback - vrátit success bez uložení
        echo json_encode([
            'status' => 'success',
            'message' => 'Tracking disabled (no DB)',
            'id' => 0
        ]);
        exit;
    }

    // Rate limiting - 1000 požadavků za hodinu per IP
    try {
        $rateLimiter = new RateLimiter($pdo);
        $rateLimitResult = $rateLimiter->checkLimit($ipAddress, 'track_pageview', [
            'max_attempts' => 1000,
            'window_minutes' => 60,
            'block_minutes' => 60
        ]);

        if (!$rateLimitResult['allowed']) {
            http_response_code(429);
            echo json_encode(['status' => 'error', 'message' => $rateLimitResult['message']]);
            exit;
        }
    } catch (Throwable $rlEx) {
        // Rate limiter selhal - pokračovat bez něj (Throwable zachytí Exception i Error)
        error_log("Track pageview - Rate limiter failed: " . $rlEx->getMessage());
    }

    // Zkontrolovat jestli IP není v ignorovaných (volitelné - tabulka nemusí existovat)
    try {
        $stmtCheck = $pdo->prepare("
            SELECT id FROM wgs_analytics_ignored_ips
            WHERE ip_address = :ip
            LIMIT 1
        ");
        $stmtCheck->execute(['ip' => $ipAddress]);

        if ($stmtCheck->fetch()) {
            // IP je ignorovaná (admin) - neukládat
            echo json_encode([
                'status' => 'ignored',
                'message' => 'IP adresa je v seznamu ignorovaných'
            ]);
            exit;
        }
    } catch (PDOException $e) {
        // Tabulka wgs_analytics_ignored_ips neexistuje - pokračovat bez kontroly
        // Není to chyba, prostě nefiltrujeme IP adresy
    }

    // Získat data z POST požadavku
    $rawInput = file_get_contents('php://input');
    $data = json_decode($rawInput, true);

    if (!$data || !is_array($data)) {
        $data = $_POST; // Fallback na klasický POST
    }

    // Extrahovat potřebná data
    $sessionId = $data['session_id'] ?? (session_status() === PHP_SESSION_ACTIVE ? session_id() : uniqid('anon_'));
    // Bezpečný přístup k $_SESSION - kontrola jestli session je aktivní
    $userId = (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['user_id'])) ? $_SESSION['user_id'] : null;
    $pageUrl = $data['page_url'] ?? $_SERVER['REQUEST_URI'] ?? '';
    $pageTitle = $data['page_title'] ?? '';
    $referrer = $data['referrer'] ?? $_SERVER['HTTP_REFERER'] ?? '';

    // FILTR: Netrackovat interní/admin stránky
    $interniStranky = [
        '/admin', '/seznam', '/statistiky', '/protokol', '/analytics',
        '/control-center', '/vsechny_tabulky', '/diagnose', '/system_check',
        '/pridej_', '/oprav_', '/migrace_', '/aktualizuj_', '/test_',
        '/api/', '/cron/', '/setup/', '/includes/'
    ];

    $skipTracking = false;
    foreach ($interniStranky as $pattern) {
        if (stripos($pageUrl, $pattern) !== false) {
            $skipTracking = true;
            break;
        }
    }

    if ($skipTracking) {
        echo json_encode([
            'status' => 'skipped',
            'message' => 'Interni stranka - netrackujeme'
        ]);
        exit;
    }
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Detekce zařízení (funkce definovány na konci souboru)
    $deviceType = detectDevice($userAgent);
    $browser = detectBrowser($userAgent);
    $os = detectOS($userAgent);

    // Screen info
    $screenResolution = $data['screen_resolution'] ?? null;
    $language = $data['language'] ?? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'cs', 0, 2);

    // Geo data (zatím základní, můžete integrovat GeoIP službu)
    $countryCode = $data['country_code'] ?? 'CZ';
    $city = $data['city'] ?? null;

    // Zkontrolovat jestli tabulka wgs_pageviews existuje
    try {
        $tableCheck = $pdo->query("SHOW TABLES LIKE 'wgs_pageviews'");
        if ($tableCheck->rowCount() === 0) {
            // Tabulka neexistuje - vytvořit ji
            $pdo->exec("
                CREATE TABLE IF NOT EXISTS wgs_pageviews (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    session_id VARCHAR(100),
                    user_id INT NULL,
                    ip_address VARCHAR(45),
                    user_agent VARCHAR(500),
                    page_url VARCHAR(500),
                    page_title VARCHAR(200),
                    referrer VARCHAR(500),
                    device_type VARCHAR(20),
                    browser VARCHAR(50),
                    os VARCHAR(50),
                    screen_resolution VARCHAR(20),
                    language VARCHAR(10),
                    country_code VARCHAR(5),
                    city VARCHAR(100),
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_session (session_id),
                    INDEX idx_created (created_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
    } catch (PDOException $tableEx) {
        error_log("Track pageview - Table check/create failed: " . $tableEx->getMessage());
    }

    // Vložit záznam
    $stmt = $pdo->prepare("
        INSERT INTO wgs_pageviews (
            session_id, user_id, ip_address, user_agent,
            page_url, page_title, referrer,
            device_type, browser, os, screen_resolution,
            language, country_code, city,
            created_at
        ) VALUES (
            :session_id, :user_id, :ip_address, :user_agent,
            :page_url, :page_title, :referrer,
            :device_type, :browser, :os, :screen_resolution,
            :language, :country_code, :city,
            NOW()
        )
    ");

    $stmt->execute([
        'session_id' => substr($sessionId, 0, 100),
        'user_id' => $userId,
        'ip_address' => substr($ipAddress, 0, 45),
        'user_agent' => substr($userAgent, 0, 500),
        'page_url' => substr($pageUrl, 0, 500),
        'page_title' => substr($pageTitle, 0, 200),
        'referrer' => substr($referrer, 0, 500),
        'device_type' => $deviceType,
        'browser' => $browser,
        'os' => $os,
        'screen_resolution' => $screenResolution ? substr($screenResolution, 0, 20) : null,
        'language' => substr($language, 0, 10),
        'country_code' => substr($countryCode, 0, 5),
        'city' => $city ? substr($city, 0, 100) : null
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Pageview zaznamenán',
        'id' => $pdo->lastInsertId()
    ]);

} catch (PDOException $e) {
    error_log("Track pageview DB error: " . $e->getMessage() . " | Code: " . $e->getCode());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Chyba databáze'
    ]);
} catch (Throwable $e) {
    // Throwable zachytí všechny Exception i Error (TypeError, ArgumentCountError, atd.)
    error_log("Track pageview error: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Chyba při ukládání'
    ]);
}

/**
 * Detekce typu zařízení z User-Agent
 */
function detectDevice(string $userAgent): string
{
    $userAgent = strtolower($userAgent);

    if (preg_match('/mobile|android|iphone|ipod|blackberry|iemobile/', $userAgent)) {
        return 'mobile';
    }

    if (preg_match('/tablet|ipad/', $userAgent)) {
        return 'tablet';
    }

    return 'desktop';
}

/**
 * Detekce prohlížeče z User-Agent
 */
function detectBrowser(string $userAgent): string
{
    if (preg_match('/Edge\/\d+/', $userAgent)) {
        return 'Edge';
    }
    if (preg_match('/Chrome\/\d+/', $userAgent)) {
        return 'Chrome';
    }
    if (preg_match('/Firefox\/\d+/', $userAgent)) {
        return 'Firefox';
    }
    if (preg_match('/Safari\/\d+/', $userAgent) && !preg_match('/Chrome/', $userAgent)) {
        return 'Safari';
    }
    if (preg_match('/Opera\/\d+|OPR\/\d+/', $userAgent)) {
        return 'Opera';
    }

    return 'Other';
}

/**
 * Detekce OS z User-Agent
 */
function detectOS(string $userAgent): string
{
    if (preg_match('/Windows NT 10/', $userAgent)) {
        return 'Windows 10';
    }
    if (preg_match('/Windows NT 6\.3/', $userAgent)) {
        return 'Windows 8.1';
    }
    if (preg_match('/Windows/', $userAgent)) {
        return 'Windows';
    }
    if (preg_match('/Mac OS X/', $userAgent)) {
        return 'macOS';
    }
    if (preg_match('/Android/', $userAgent)) {
        return 'Android';
    }
    if (preg_match('/iPhone|iPad|iPod/', $userAgent)) {
        return 'iOS';
    }
    if (preg_match('/Linux/', $userAgent)) {
        return 'Linux';
    }

    return 'Other';
}
?>
