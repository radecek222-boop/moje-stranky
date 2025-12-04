<?php
/**
 * Tracking API - Zaznamenávání pageviews
 * Endpoint pro JavaScript tracking skript
 *
 * SECURITY: Rate limiting chrání proti zneužití
 * POZOR: CSRF není vyžadováno - veřejný analytics endpoint pro anonymní uživatele
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');

// Pouze POST požadavky
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Pouze POST požadavky']);
    exit;
}

// SECURITY: CSRF není vyžadováno pro analytics tracking
// Důvod: Veřejný endpoint pro anonymní návštěvníky bez session
// Ochrana: Rate limiting (1000 req/h per IP) brání zneužití

try {
    $pdo = getDbConnection();

    // Získat IP adresu návštěvníka
    $ipAddress = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '';
    if (strpos($ipAddress, ',') !== false) {
        $ipAddress = trim(explode(',', $ipAddress)[0]);
    }

    // Rate limiting - 1000 požadavků za hodinu per IP
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
    $data = json_decode(file_get_contents('php://input'), true);

    if (!$data) {
        $data = $_POST; // Fallback na klasický POST
    }

    // Extrahovat potřebná data
    $sessionId = $data['session_id'] ?? session_id();
    $userId = $_SESSION['user_id'] ?? null;
    $pageUrl = $data['page_url'] ?? $_SERVER['REQUEST_URI'] ?? '';
    $pageTitle = $data['page_title'] ?? '';
    $referrer = $data['referrer'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    // Detekce zařízení
    $deviceType = detectDevice($userAgent);
    $browser = detectBrowser($userAgent);
    $os = detectOS($userAgent);

    // Screen info
    $screenResolution = $data['screen_resolution'] ?? null;
    $language = $data['language'] ?? substr($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? 'cs', 0, 2);

    // Geo data (zatím základní, můžete integrovat GeoIP službu)
    $countryCode = $data['country_code'] ?? 'CZ';
    $city = $data['city'] ?? null;

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
        'session_id' => $sessionId,
        'user_id' => $userId,
        'ip_address' => $ipAddress,
        'user_agent' => substr($userAgent, 0, 500),
        'page_url' => substr($pageUrl, 0, 500),
        'page_title' => substr($pageTitle, 0, 200),
        'referrer' => substr($referrer, 0, 500),
        'device_type' => $deviceType,
        'browser' => $browser,
        'os' => $os,
        'screen_resolution' => $screenResolution,
        'language' => $language,
        'country_code' => $countryCode,
        'city' => $city
    ]);

    echo json_encode([
        'status' => 'success',
        'message' => 'Pageview zaznamenán',
        'id' => $pdo->lastInsertId()
    ]);

} catch (Exception $e) {
    error_log("Track pageview error: " . $e->getMessage());
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
