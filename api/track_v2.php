<?php
/**
 * Track V2 API - Pokročilé sledování pageviews s relacemi
 *
 * Endpoint pro tracking pageviews s integrací:
 * - Device fingerprinting (Modul #1)
 * - Session tracking (Modul #2)
 * - Bot detection (Modul #3)
 * - Geolocation service (Modul #4)
 * - UTM parametry
 * - Device info
 * - Engagement metrics
 *
 * @version 1.1.0
 * @date 2025-11-23
 * @module Module #4 - Geolocation Service Integration
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/SessionMerger.php';
require_once __DIR__ . '/../includes/BotDetector.php';
require_once __DIR__ . '/../includes/GeolocationService.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json; charset=utf-8');

// CORS headers pro cross-origin tracking (pokud potřeba)
header('Access-Control-Allow-Origin: https://www.wgs-service.cz');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// OPTIONS request pro CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Pouze POST metoda
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('Pouze POST metoda je povolena', 405);
}

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '')) {
    sendJsonError('Neplatný CSRF token', 403);
}

try {
    // FIX P1: PDO musí být inicializováno PŘED vytvořením RateLimiter instance
    $pdo = getDbConnection();

    // Rate limiting - 1000 požadavků za hodinu per IP
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimiter = new RateLimiter($pdo);

    // FIX P1: checkLimit() vrací pole s klíčem 'allowed', ne boolean
    // Správné parametry: (identifier, actionType, limits array)
    $rateLimitResult = $rateLimiter->checkLimit($clientIp, 'track_v2', [
        'max_attempts' => 1000,
        'window_minutes' => 60,
        'block_minutes' => 60
    ]);

    if (!$rateLimitResult['allowed']) {
        sendJsonError($rateLimitResult['message'], 429);
    }

    // Získání JSON dat z request body
    $inputData = json_decode(file_get_contents('php://input'), true);

    // Fallback na $_POST pokud není JSON
    if (!$inputData) {
        $inputData = $_POST;
    }

    // ========================================
    // VALIDACE POVINNÝCH POLÍ
    // ========================================
    $povinnaPolaChyby = [];

    if (empty($inputData['session_id'])) {
        $povinnaPolaChyby[] = 'session_id';
    }

    if (empty($inputData['fingerprint_id'])) {
        $povinnaPolaChyby[] = 'fingerprint_id';
    }

    if (empty($inputData['page_url'])) {
        $povinnaPolaChyby[] = 'page_url';
    }

    if (!empty($povinnaPolaChyby)) {
        sendJsonError('Chybí povinná pole: ' . implode(', ', $povinnaPolaChyby), 400);
    }

    // ========================================
    // SANITIZACE A VALIDACE DAT
    // ========================================
    $sessionId = sanitizeInput($inputData['session_id']);
    $fingerprintId = sanitizeInput($inputData['fingerprint_id']);
    $pageUrl = filter_var($inputData['page_url'], FILTER_VALIDATE_URL);

    if (!$pageUrl) {
        sendJsonError('Neplatná URL adresa', 400);
    }

    // Validace délky session_id a fingerprint_id
    if (strlen($sessionId) > 64) {
        sendJsonError('session_id je příliš dlouhý (max 64 znaků)', 400);
    }

    if (strlen($fingerprintId) > 64) {
        sendJsonError('fingerprint_id je příliš dlouhý (max 64 znaků)', 400);
    }

    // ========================================
    // PŘÍPRAVA SESSION DATA
    // ========================================
    $sessionData = [
        'page_url' => $pageUrl,
        'page_title' => sanitizeInput($inputData['page_title'] ?? ''),
        'referrer' => filter_var($inputData['referrer'] ?? '', FILTER_SANITIZE_URL),

        // UTM parametry
        'utm_source' => sanitizeInput($inputData['utm_source'] ?? null),
        'utm_medium' => sanitizeInput($inputData['utm_medium'] ?? null),
        'utm_campaign' => sanitizeInput($inputData['utm_campaign'] ?? null),
        'utm_term' => sanitizeInput($inputData['utm_term'] ?? null),
        'utm_content' => sanitizeInput($inputData['utm_content'] ?? null),

        // Device info
        'device_type' => sanitizeInput($inputData['device_type'] ?? null),
        'browser' => sanitizeInput($inputData['browser'] ?? null),
        'os' => sanitizeInput($inputData['os'] ?? null)
    ];

    // Validace device_type (pouze povolené hodnoty)
    $povolenoDeviceType = ['desktop', 'mobile', 'tablet'];
    if ($sessionData['device_type'] && !in_array($sessionData['device_type'], $povolenoDeviceType)) {
        $sessionData['device_type'] = null;
    }

    // ========================================
    // VYTVOŘENÍ/AKTUALIZACE RELACE
    // ========================================
    $sessionMerger = new SessionMerger($pdo);

    $vysledekRelace = $sessionMerger->vytvorNeboAktualizujRelaci(
        $sessionId,
        $fingerprintId,
        $sessionData
    );

    $jeNovaRelace = $vysledekRelace['is_new'];
    $pocetPageviews = $vysledekRelace['pageview_count'];

    // ========================================
    // IP ANONYMIZACE (pro GDPR compliance)
    // ========================================
    $ipAdresa = $clientIp;

    // IP anonymizace (poslední oktet)
    if (strpos($ipAdresa, '.') !== false) {
        // IPv4
        $parts = explode('.', $ipAdresa);
        $parts[3] = '0';
        $ipAdresaAnonymni = implode('.', $parts);
    } else {
        // IPv6 - maskovat posledních 80 bitů
        $ipAdresaAnonymni = substr($ipAdresa, 0, 19) . '::';
    }

    // ========================================
    // GEOLOCATION (Modul #4)
    // ========================================
    $geoService = new GeolocationService($pdo);
    $geoData = $geoService->getLocationFromIP($ipAdresa);

    // Aktualizovat relaci s geolokačními daty
    $sessionMerger->aktualizujGeoData($sessionId, $geoData);

    // ========================================
    // BOT DETECTION (Modul #3)
    // ========================================
    $botDetector = new BotDetector($pdo);

    // Příprava bot detection dat
    $botSignals = [];

    // Pokud frontend poslal bot_signals (JSON nebo pole)
    if (isset($inputData['bot_signals'])) {
        if (is_string($inputData['bot_signals'])) {
            $botSignals = json_decode($inputData['bot_signals'], true) ?? [];
        } elseif (is_array($inputData['bot_signals'])) {
            $botSignals = $inputData['bot_signals'];
        }
    }

    // Request data pro bot detection
    $botRequestData = [
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address' => $ipAdresaAnonymni ?? $clientIp,
        'signals' => $botSignals
    ];

    // Detekce bota
    $botDetectionResult = $botDetector->detekujBota($sessionId, $fingerprintId, $botRequestData);

    $jeBot = $botDetectionResult['is_bot'];
    $botScore = $botDetectionResult['bot_score'];
    $threatLevel = $botDetectionResult['threat_level'];
    $jeWhitelisted = $botDetectionResult['is_whitelisted'];

    // ========================================
    // REAL-TIME TRACKING (Modul #11)
    // ========================================
    // UPSERT do wgs_analytics_realtime pro real-time dashboard
    // Session expirvuje po 5 minutách neaktivity

    // Získat session_start z wgs_analytics_sessions
    $stmtSessionStart = $pdo->prepare("SELECT session_start FROM wgs_analytics_sessions WHERE session_id = :session_id");
    $stmtSessionStart->execute(['session_id' => $sessionId]);
    $sessionStartRow = $stmtSessionStart->fetch(PDO::FETCH_ASSOC);
    $sessionStart = $sessionStartRow['session_start'] ?? date('Y-m-d H:i:s');

    // Vypočítat session duration
    $sessionStartTimestamp = strtotime($sessionStart);
    $sessionDuration = time() - $sessionStartTimestamp;

    // Extract referrer domain
    $referrerDomain = null;
    if (!empty($sessionData['referrer'])) {
        $parsedReferrer = parse_url($sessionData['referrer']);
        $referrerDomain = $parsedReferrer['host'] ?? null;
    }

    $stmtRealtime = $pdo->prepare("
        INSERT INTO wgs_analytics_realtime (
            session_id,
            fingerprint_id,
            is_bot,
            visitor_type,
            current_page,
            current_page_title,
            country_code,
            city,
            latitude,
            longitude,
            device_type,
            browser,
            os,
            referrer_domain,
            utm_source,
            utm_medium,
            utm_campaign,
            pageviews,
            events_count,
            session_duration,
            is_active,
            last_activity_at,
            session_start,
            expires_at
        ) VALUES (
            :session_id,
            :fingerprint_id,
            :is_bot,
            :visitor_type,
            :current_page,
            :current_page_title,
            :country_code,
            :city,
            :latitude,
            :longitude,
            :device_type,
            :browser,
            :os,
            :referrer_domain,
            :utm_source,
            :utm_medium,
            :utm_campaign,
            :pageviews,
            0,
            :session_duration,
            1,
            NOW(),
            :session_start,
            DATE_ADD(NOW(), INTERVAL 5 MINUTE)
        )
        ON DUPLICATE KEY UPDATE
            current_page = VALUES(current_page),
            current_page_title = VALUES(current_page_title),
            pageviews = :pageviews_update,
            session_duration = VALUES(session_duration),
            last_activity_at = NOW(),
            expires_at = DATE_ADD(NOW(), INTERVAL 5 MINUTE),
            is_active = 1
    ");

    $stmtRealtime->execute([
        'session_id' => $sessionId,
        'fingerprint_id' => $fingerprintId,
        'is_bot' => $jeBot ? 1 : 0,
        'visitor_type' => $jeBot ? 'bot' : 'human',
        'current_page' => substr($pageUrl, 0, 500),
        'current_page_title' => substr($sessionData['page_title'], 0, 200),
        'country_code' => $geoData['country_code'],
        'city' => $geoData['city'],
        'latitude' => $geoData['latitude'],
        'longitude' => $geoData['longitude'],
        'device_type' => $sessionData['device_type'],
        'browser' => $sessionData['browser'],
        'os' => $sessionData['os'],
        'referrer_domain' => $referrerDomain,
        'utm_source' => $sessionData['utm_source'],
        'utm_medium' => $sessionData['utm_medium'],
        'utm_campaign' => $sessionData['utm_campaign'],
        'pageviews' => $pocetPageviews,
        'session_duration' => $sessionDuration,
        'session_start' => $sessionStart,
        'pageviews_update' => $pocetPageviews
    ]);

    // ========================================
    // ULOŽENÍ PAGEVIEW DO TABULKY wgs_pageviews
    // ========================================
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    $stmt = $pdo->prepare("
        INSERT INTO wgs_pageviews (
            session_id,
            fingerprint_id,
            url,
            ip,
            user_agent,
            datum
        ) VALUES (
            :session_id,
            :fingerprint_id,
            :url,
            :ip,
            :user_agent,
            NOW()
        )
    ");

    $stmt->execute([
        'session_id' => $sessionId,
        'fingerprint_id' => $fingerprintId,
        'url' => $pageUrl,
        'ip' => $ipAdresaAnonymni,
        'user_agent' => substr($userAgent, 0, 255)
    ]);

    $pageviewId = $pdo->lastInsertId();

    // ========================================
    // VÝPOČET ENGAGEMENT SCORE (pouze pro existující relace s 2+ pageviews)
    // ========================================
    $engagementScore = null;

    if (!$jeNovaRelace && $pocetPageviews >= 2) {
        $engagementScore = $sessionMerger->vypocitejEngagementScore($sessionId);
    }

    // ========================================
    // LOGOVÁNÍ (debug - pouze v development)
    // ========================================
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        error_log(sprintf(
            '[Track V2] Session: %s | Fingerprint: %s | Page: %s | Pageviews: %d | New: %s',
            $sessionId,
            $fingerprintId,
            $pageUrl,
            $pocetPageviews,
            $jeNovaRelace ? 'YES' : 'NO'
        ));
    }

    // ========================================
    // ODPOVĚĎ
    // ========================================
    sendJsonSuccess('Pageview zaznamenán', [
        'session' => [
            'session_id' => $sessionId,
            'is_new_session' => $jeNovaRelace,
            'pageview_count' => $pocetPageviews,
            'engagement_score' => $engagementScore
        ],
        'pageview' => [
            'pageview_id' => $pageviewId,
            'page_url' => $pageUrl
        ],
        'bot_detection' => [
            'is_bot' => $jeBot,
            'bot_score' => $botScore,
            'threat_level' => $threatLevel,
            'is_whitelisted' => $jeWhitelisted
        ],
        'geolocation' => [
            'country_code' => $geoData['country_code'],
            'country_name' => $geoData['country_name'],
            'city' => $geoData['city'],
            'from_cache' => $geoData['from_cache'] ?? false
        ],
        'tracking' => [
            'fingerprint_linked' => true,
            'utm_tracked' => !empty($sessionData['utm_source']),
            'bot_detection_enabled' => true,
            'geolocation_enabled' => true
        ]
    ]);

} catch (PDOException $e) {
    // Logování chyby bez expozice detailů
    error_log('Track V2 API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    sendJsonError('Chyba při zpracování požadavku', 500);

} catch (InvalidArgumentException $e) {
    // Validační chyba
    sendJsonError($e->getMessage(), 400);

} catch (Exception $e) {
    // Obecná chyba
    error_log('Track V2 API Unexpected Error: ' . $e->getMessage());
    sendJsonError('Neočekávaná chyba serveru', 500);
}

/**
 * Pomocná funkce pro sanitizaci vstupu
 *
 * @param mixed $input
 * @return string|null
 */
function sanitizeInput($input): ?string
{
    if ($input === null || $input === '') {
        return null;
    }

    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
