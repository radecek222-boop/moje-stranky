<?php
/**
 * Track Replay API - Příjem session replay framů
 *
 * Tento endpoint přijímá batche replay framů (mousemove, click, scroll, resize)
 * z frontendu a ukládá je do databáze pro pozdější přehrání v admin UI.
 *
 * Podporované event_type:
 * - mousemove: Pohyb myši (throttled 100ms)
 * - click: Kliknutí myši
 * - scroll: Scrollování stránky
 * - resize: Změna velikosti viewportu
 * - focus: Focus na okno
 * - blur: Ztráta focusu okna
 * - load: Načtení stránky
 * - unload: Opuštění stránky
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #7 - Session Replay Engine
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json; charset=utf-8');

// CORS headers
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

    // Rate limiting - 1000 požadavků za hodinu per session
    $clientIp = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimiter = new RateLimiter($pdo);

    $rateLimitResult = $rateLimiter->checkLimit($clientIp, 'track_replay', [
        'max_attempts' => 1000,
        'window_minutes' => 60,
        'block_minutes' => 60
    ]);

    if (!$rateLimitResult['allowed']) {
        sendJsonError($rateLimitResult['message'], 429);
    }

    // Získání JSON dat
    $inputData = json_decode(file_get_contents('php://input'), true);

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

    if (empty($inputData['page_url'])) {
        sendJsonError('Chybí povinné pole: page_url', 400);
    }

    if (!isset($inputData['page_index']) || !is_numeric($inputData['page_index'])) {
        sendJsonError('Chybí povinné pole: page_index', 400);
    }

    if (empty($inputData['frames']) || !is_array($inputData['frames'])) {
        sendJsonError('Chybí povinné pole: frames (array)', 400);
    }

    // ========================================
    // SANITIZACE A VALIDACE DAT
    // ========================================
    $sessionId = sanitizeInput($inputData['session_id']);
    $pageUrl = filter_var($inputData['page_url'], FILTER_VALIDATE_URL);

    if (!$pageUrl) {
        sendJsonError('Neplatná URL adresa', 400);
    }

    // Normalizace URL (odstranit query params a hash)
    $parsedUrl = parse_url($pageUrl);
    $normalizedUrl = $parsedUrl['scheme'] . '://' . $parsedUrl['host'] . ($parsedUrl['path'] ?? '/');

    $pageIndex = (int)$inputData['page_index'];
    if ($pageIndex < 0 || $pageIndex > 255) {
        sendJsonError('Neplatný page_index (rozsah: 0-255)', 400);
    }

    // Validace device_type
    $deviceType = sanitizeInput($inputData['device_type'] ?? null);
    $povoleneDeviceTypes = ['desktop', 'mobile', 'tablet'];
    if ($deviceType && !in_array($deviceType, $povoleneDeviceTypes)) {
        sendJsonError('Neplatný device_type (povolené: desktop, mobile, tablet)', 400);
    }

    // Viewport dimensions
    $viewportWidth = isset($inputData['viewport_width']) ? (int)$inputData['viewport_width'] : null;
    $viewportHeight = isset($inputData['viewport_height']) ? (int)$inputData['viewport_height'] : null;

    if ($viewportWidth && ($viewportWidth < 0 || $viewportWidth > 10000)) {
        sendJsonError('Neplatný viewport_width (rozsah: 0-10000)', 400);
    }

    if ($viewportHeight && ($viewportHeight < 0 || $viewportHeight > 10000)) {
        sendJsonError('Neplatný viewport_height (rozsah: 0-10000)', 400);
    }

    // Validace počtu framů v batchi
    if (count($inputData['frames']) > 50) {
        sendJsonError('Příliš mnoho framů v batchi (max 50)', 400);
    }

    // ========================================
    // VALIDACE A ULOŽENÍ FRAMŮ
    // ========================================
    $povoleneEventTypes = ['mousemove', 'click', 'scroll', 'resize', 'focus', 'blur', 'load', 'unload'];
    $framesStored = 0;

    // Calculate expires_at (30 days from now)
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

    foreach ($inputData['frames'] as $frame) {
        // Validace povinných polí framu
        if (!isset($frame['frame_index']) || !is_numeric($frame['frame_index'])) {
            continue; // Přeskočit neplatný frame
        }

        if (!isset($frame['timestamp_offset']) || !is_numeric($frame['timestamp_offset'])) {
            continue;
        }

        if (empty($frame['event_type']) || !in_array($frame['event_type'], $povoleneEventTypes)) {
            continue; // Přeskočit neplatný event_type
        }

        $frameIndex = (int)$frame['frame_index'];
        $timestampOffset = (int)$frame['timestamp_offset'];
        $eventType = $frame['event_type'];
        $eventData = $frame['event_data'] ?? [];

        // Validace timestamp (max 1 hour = 3600000ms)
        if ($timestampOffset < 0 || $timestampOffset > 3600000) {
            continue;
        }

        // Validace frame_index (max 100,000 framů per stránka)
        if ($frameIndex < 0 || $frameIndex > 100000) {
            continue;
        }

        // Sanitizace event_data (limit na velikost)
        $eventDataJson = json_encode($eventData);
        if (strlen($eventDataJson) > 5000) {
            // Příliš velká event_data, zkrátit
            $eventDataJson = substr($eventDataJson, 0, 5000);
        }

        // INSERT framu
        $stmt = $pdo->prepare("
            INSERT INTO wgs_analytics_replay_frames (
                session_id,
                page_url,
                page_index,
                frame_index,
                timestamp_offset,
                event_type,
                event_data,
                viewport_width,
                viewport_height,
                device_type,
                created_at,
                expires_at
            ) VALUES (
                :session_id,
                :page_url,
                :page_index,
                :frame_index,
                :timestamp_offset,
                :event_type,
                :event_data,
                :viewport_width,
                :viewport_height,
                :device_type,
                NOW(),
                :expires_at
            )
        ");

        $stmt->execute([
            'session_id' => $sessionId,
            'page_url' => $normalizedUrl,
            'page_index' => $pageIndex,
            'frame_index' => $frameIndex,
            'timestamp_offset' => $timestampOffset,
            'event_type' => $eventType,
            'event_data' => $eventDataJson,
            'viewport_width' => $viewportWidth,
            'viewport_height' => $viewportHeight,
            'device_type' => $deviceType,
            'expires_at' => $expiresAt
        ]);

        $framesStored++;
    }

    // ========================================
    // LOGOVÁNÍ (debug)
    // ========================================
    if (defined('DEBUG_MODE') && DEBUG_MODE === true) {
        error_log(sprintf(
            '[Track Replay] Session: %s | Page: %s (index %d) | Frames: %d',
            $sessionId,
            $normalizedUrl,
            $pageIndex,
            $framesStored
        ));
    }

    // ========================================
    // ZÍSKÁNÍ CELKOVÉHO POČTU FRAMŮ PRO STRÁNKU
    // ========================================
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as total_frames
        FROM wgs_analytics_replay_frames
        WHERE session_id = :session_id
          AND page_index = :page_index
    ");

    $stmt->execute([
        'session_id' => $sessionId,
        'page_index' => $pageIndex
    ]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalFrames = (int)$result['total_frames'];

    // ========================================
    // ODPOVĚĎ
    // ========================================
    sendJsonSuccess('Replay frames uloženy', [
        'frames_stored' => $framesStored,
        'session_id' => $sessionId,
        'page_url' => $normalizedUrl,
        'page_index' => $pageIndex,
        'total_frames' => $totalFrames,
        'viewport' => [
            'width' => $viewportWidth,
            'height' => $viewportHeight
        ],
        'device_type' => $deviceType,
        'expires_at' => $expiresAt
    ]);

} catch (PDOException $e) {
    error_log('Track Replay API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    sendJsonError('Chyba při zpracování požadavku', 500);

} catch (Exception $e) {
    error_log('Track Replay API Unexpected Error: ' . $e->getMessage());
    sendJsonError('Neočekávaná chyba serveru', 500);
}

/**
 * Pomocná funkce pro sanitizaci vstupu
 * SECURITY FIX: Zabaleno do function_exists pro prevenci konfliktu s globální definicí
 */
if (!function_exists('sanitizeInput')) {
    function sanitizeInput($input): ?string
    {
        if ($input === null || $input === '') {
            return null;
        }

        return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
    }
}
?>
