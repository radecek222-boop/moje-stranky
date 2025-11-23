<?php
/**
 * Track V2 API - Pokročilé sledování pageviews s relacemi
 *
 * Endpoint pro tracking pageviews s integrací:
 * - Device fingerprinting (Modul #1)
 * - Session tracking (Modul #2)
 * - UTM parametry
 * - Device info
 * - Engagement metrics
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #2 - Advanced Session Tracking
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/SessionMerger.php';
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

// Rate limiting - 1000 požadavků za hodinu per IP
$rateLimiter = new RateLimiter($pdo);
$clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

if (!$rateLimiter->checkLimit('track_v2', $clientIp, 1000, 3600)) {
    sendJsonError('Příliš mnoho požadavků. Zkuste to později.', 429);
}

try {
    $pdo = getDbConnection();

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
    // ULOŽENÍ PAGEVIEW DO TABULKY wgs_pageviews
    // ========================================

    // Extrakce dodatečných informací
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
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
        'tracking' => [
            'fingerprint_linked' => true,
            'utm_tracked' => !empty($sessionData['utm_source'])
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
