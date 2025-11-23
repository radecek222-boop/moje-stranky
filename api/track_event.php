<?php
/**
 * Track Event API - Sledování uživatelských událostí
 *
 * Endpoint pro příjem a ukládání uživatelských interakcí:
 * - Kliky (click) s pozicí a CSS selectory
 * - Scrollování (scroll) s scroll depth
 * - Rage clicks (frustrace uživatele)
 * - Copy/paste události
 * - Interakce s formuláři (focus/blur)
 * - Idle/active stavy
 *
 * Podporuje batching (až 50 eventů najednou) pro optimální výkon.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #5 - Event Tracking Engine
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json; charset=utf-8');

// CORS headers pro cross-origin tracking
header('Access-Control-Allow-Origin: https://www.wgs-service.cz');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type, X-CSRF-TOKEN');

// OPTIONS request pro CORS preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Pouze POST metoda
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('Pouze POST metoda je povolena', 405);
}

try {
    $pdo = getDbConnection();

    // Rate limiting - 2000 požadavků za hodinu per IP
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimiter = new RateLimiter($pdo);

    $rateLimitResult = $rateLimiter->checkLimit($clientIp, 'track_event', [
        'max_attempts' => 2000,
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

    // CSRF validace
    $csrfToken = $inputData['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        sendJsonError('Neplatný CSRF token', 403);
    }

    // ========================================
    // VALIDACE POVINNÝCH POLÍ
    // ========================================
    if (empty($inputData['session_id'])) {
        sendJsonError('Chybí povinné pole: session_id', 400);
    }

    if (empty($inputData['fingerprint_id'])) {
        sendJsonError('Chybí povinné pole: fingerprint_id', 400);
    }

    if (empty($inputData['events']) || !is_array($inputData['events'])) {
        sendJsonError('Chybí pole events nebo není array', 400);
    }

    // Limit na počet eventů v jednom requestu (max 50)
    if (count($inputData['events']) > 50) {
        sendJsonError('Příliš mnoho eventů (max 50 najednou)', 400);
    }

    // ========================================
    // SANITIZACE A VALIDACE DAT
    // ========================================
    $sessionId = sanitizeInput($inputData['session_id']);
    $fingerprintId = sanitizeInput($inputData['fingerprint_id']);
    $events = $inputData['events'];

    // Validace délky session_id a fingerprint_id
    if (strlen($sessionId) > 64) {
        sendJsonError('session_id je příliš dlouhý (max 64 znaků)', 400);
    }

    if (strlen($fingerprintId) > 64) {
        sendJsonError('fingerprint_id je příliš dlouhý (max 64 znaků)', 400);
    }

    // Povolené typy událostí
    $povoleneTypy = ['click', 'scroll', 'rage_click', 'copy', 'paste', 'form_focus', 'form_blur', 'idle', 'active'];

    // ========================================
    // ZPRACOVÁNÍ A ULOŽENÍ EVENTŮ
    // ========================================
    $ulozenoEventu = 0;
    $typy = [];

    foreach ($events as $event) {
        // Validace event_type
        if (empty($event['event_type']) || !in_array($event['event_type'], $povoleneTypy)) {
            // Přeskočit neplatný event
            continue;
        }

        // Validace povinných polí pro každý event
        if (empty($event['page_url']) || empty($event['timestamp'])) {
            // Přeskočit event bez URL nebo timestamp
            continue;
        }

        // Sanitizace dat
        $eventType = $event['event_type'];
        $pageUrl = filter_var($event['page_url'], FILTER_VALIDATE_URL);
        $timestamp = (int)$event['timestamp'];

        if (!$pageUrl) {
            // Přeskočit event s neplatnou URL
            continue;
        }

        // Příprava dat pro INSERT (použít NULL pro nepovinné sloupce)
        $eventData = [
            'session_id' => $sessionId,
            'fingerprint_id' => $fingerprintId,
            'event_type' => $eventType,
            'page_url' => $pageUrl,
            'timestamp' => $timestamp,

            // Click data
            'click_x' => isset($event['click_x']) ? (int)$event['click_x'] : null,
            'click_y' => isset($event['click_y']) ? (int)$event['click_y'] : null,
            'click_x_percent' => isset($event['click_x_percent']) ? (float)$event['click_x_percent'] : null,
            'click_y_percent' => isset($event['click_y_percent']) ? (float)$event['click_y_percent'] : null,

            // Element data
            'element_selector' => isset($event['element_selector']) ? sanitizeInput(substr($event['element_selector'], 0, 500)) : null,
            'element_text' => isset($event['element_text']) ? sanitizeInput(substr($event['element_text'], 0, 255)) : null,
            'element_tag' => isset($event['element_tag']) ? sanitizeInput(substr($event['element_tag'], 0, 50)) : null,

            // Scroll data
            'scroll_depth' => isset($event['scroll_depth']) ? min(100, max(0, (int)$event['scroll_depth'])) : null,

            // Viewport data
            'viewport_width' => isset($event['viewport_width']) ? (int)$event['viewport_width'] : null,
            'viewport_height' => isset($event['viewport_height']) ? (int)$event['viewport_height'] : null,

            // Rage click data
            'rage_click_count' => isset($event['rage_click_count']) ? (int)$event['rage_click_count'] : null,

            // Form data
            'form_field_name' => isset($event['form_field_name']) ? sanitizeInput(substr($event['form_field_name'], 0, 255)) : null,

            // Copy/paste data
            'copied_text_length' => isset($event['copied_text_length']) ? (int)$event['copied_text_length'] : null,

            // Idle data
            'idle_duration' => isset($event['idle_duration']) ? (int)$event['idle_duration'] : null
        ];

        // INSERT do databáze
        $stmt = $pdo->prepare("
            INSERT INTO wgs_analytics_events (
                session_id,
                fingerprint_id,
                event_type,
                page_url,
                timestamp,
                click_x,
                click_y,
                click_x_percent,
                click_y_percent,
                element_selector,
                element_text,
                element_tag,
                scroll_depth,
                viewport_width,
                viewport_height,
                rage_click_count,
                form_field_name,
                copied_text_length,
                idle_duration,
                created_at
            ) VALUES (
                :session_id,
                :fingerprint_id,
                :event_type,
                :page_url,
                :timestamp,
                :click_x,
                :click_y,
                :click_x_percent,
                :click_y_percent,
                :element_selector,
                :element_text,
                :element_tag,
                :scroll_depth,
                :viewport_width,
                :viewport_height,
                :rage_click_count,
                :form_field_name,
                :copied_text_length,
                :idle_duration,
                NOW()
            )
        ");

        $stmt->execute($eventData);
        $ulozenoEventu++;

        // Sledovat typy eventů pro response
        if (!in_array($eventType, $typy)) {
            $typy[] = $eventType;
        }
    }

    // ========================================
    // LOGOVÁNÍ (debug - pouze v development)
    // ========================================
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        error_log(sprintf(
            '[Track Event] Session: %s | Events: %d | Types: %s',
            $sessionId,
            $ulozenoEventu,
            implode(', ', $typy)
        ));
    }

    // ========================================
    // ODPOVĚĎ
    // ========================================
    sendJsonSuccess(
        $ulozenoEventu === 1 ? '1 událost uložena' : "{$ulozenoEventu} události uloženy",
        [
            'stored_count' => $ulozenoEventu,
            'session_id' => $sessionId,
            'event_types' => $typy,
            'tracking' => [
                'event_tracking_enabled' => true,
                'batch_size' => count($events),
                'accepted' => $ulozenoEventu,
                'rejected' => count($events) - $ulozenoEventu
            ]
        ]
    );

} catch (PDOException $e) {
    // Logování chyby bez expozice detailů
    error_log('Track Event API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    sendJsonError('Chyba při zpracování požadavku', 500);

} catch (InvalidArgumentException $e) {
    // Validační chyba
    sendJsonError($e->getMessage(), 400);

} catch (Exception $e) {
    // Obecná chyba
    error_log('Track Event API Unexpected Error: ' . $e->getMessage());
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
?>
