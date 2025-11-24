<?php
/**
 * Analytics Replay API - Načtení session replay dat pro přehrání
 *
 * Read API pro admin UI - vrací replay frames pro konkrétní session a stránku.
 *
 * Query params:
 * - session_id (required): Session ID
 * - page_index (optional): Konkrétní stránka (0, 1, 2...), pokud není specifikováno, vrací všechny stránky
 * - csrf_token (required): CSRF token
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #7 - Session Replay Engine
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// Pouze GET metoda
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendJsonError('Pouze GET metoda je povolena', 405);
}

try {
    // ========================================
    // AUTHENTICATION CHECK (admin only)
    // ========================================
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        sendJsonError('Přístup odepřen - pouze pro admins', 403);
    }

    // ========================================
    // CSRF VALIDACE
    // ========================================
    $csrfToken = $_GET['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        sendJsonError('Neplatný CSRF token', 403);
    }

    // PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
    session_write_close();

    $pdo = getDbConnection();

    // ========================================
    // VALIDACE PARAMETRŮ
    // ========================================
    if (empty($_GET['session_id'])) {
        sendJsonError('Chybí povinný parametr: session_id', 400);
    }

    $sessionId = sanitizeInput($_GET['session_id']);
    $pageIndex = isset($_GET['page_index']) && is_numeric($_GET['page_index'])
        ? (int)$_GET['page_index']
        : null;

    // ========================================
    // POKUD JE SPECIFIKOVÁN PAGE_INDEX - VRÁTIT KONKRÉTNÍ STRÁNKU
    // ========================================
    if ($pageIndex !== null) {
        $stmt = $pdo->prepare("
            SELECT
                frame_index,
                timestamp_offset,
                event_type,
                event_data,
                viewport_width,
                viewport_height,
                device_type,
                created_at
            FROM wgs_analytics_replay_frames
            WHERE session_id = :session_id
              AND page_index = :page_index
            ORDER BY frame_index ASC
            LIMIT 10000
        ");

        $stmt->execute([
            'session_id' => $sessionId,
            'page_index' => $pageIndex
        ]);

        $frames = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($frames)) {
            sendJsonError('Žádné frames nenalezeny pro daný session_id a page_index', 404);
        }

        // Dekódovat JSON event_data
        foreach ($frames as &$frame) {
            $frame['frame_index'] = (int)$frame['frame_index'];
            $frame['timestamp_offset'] = (int)$frame['timestamp_offset'];
            $frame['event_data'] = json_decode($frame['event_data'], true) ?? [];
            $frame['viewport_width'] = $frame['viewport_width'] ? (int)$frame['viewport_width'] : null;
            $frame['viewport_height'] = $frame['viewport_height'] ? (int)$frame['viewport_height'] : null;
        }

        // Získat page_url a další metadata
        $stmt = $pdo->prepare("
            SELECT
                page_url,
                viewport_width,
                viewport_height,
                device_type
            FROM wgs_analytics_replay_frames
            WHERE session_id = :session_id
              AND page_index = :page_index
            LIMIT 1
        ");

        $stmt->execute([
            'session_id' => $sessionId,
            'page_index' => $pageIndex
        ]);

        $pageMetadata = $stmt->fetch(PDO::FETCH_ASSOC);

        // Vypočítat duration (max timestamp)
        $durationMs = 0;
        foreach ($frames as $frame) {
            if ($frame['timestamp_offset'] > $durationMs) {
                $durationMs = $frame['timestamp_offset'];
            }
        }

        sendJsonSuccess('Replay data načtena', [
            'session_id' => $sessionId,
            'page_url' => $pageMetadata['page_url'] ?? '',
            'page_index' => $pageIndex,
            'total_frames' => count($frames),
            'duration_ms' => $durationMs,
            'viewport' => [
                'width' => $pageMetadata['viewport_width'] ? (int)$pageMetadata['viewport_width'] : 1920,
                'height' => $pageMetadata['viewport_height'] ? (int)$pageMetadata['viewport_height'] : 1080
            ],
            'device_type' => $pageMetadata['device_type'] ?? 'desktop',
            'frames' => $frames
        ]);
    }

    // ========================================
    // POKUD NENÍ SPECIFIKOVÁN PAGE_INDEX - VRÁTIT SEZNAM STRÁNEK V SESSION
    // ========================================
    else {
        $stmt = $pdo->prepare("
            SELECT
                page_index,
                page_url,
                COUNT(*) as frame_count,
                MAX(timestamp_offset) as duration_ms,
                MIN(created_at) as first_frame_at,
                MAX(created_at) as last_frame_at,
                viewport_width,
                viewport_height,
                device_type
            FROM wgs_analytics_replay_frames
            WHERE session_id = :session_id
            GROUP BY page_index, page_url, viewport_width, viewport_height, device_type
            ORDER BY page_index ASC
        ");

        $stmt->execute(['session_id' => $sessionId]);
        $pages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pages)) {
            sendJsonError('Žádné replay data nenalezeny pro daný session_id', 404);
        }

        // Formátovat výsledky
        foreach ($pages as &$page) {
            $page['page_index'] = (int)$page['page_index'];
            $page['frame_count'] = (int)$page['frame_count'];
            $page['duration_ms'] = (int)$page['duration_ms'];
            $page['viewport_width'] = $page['viewport_width'] ? (int)$page['viewport_width'] : null;
            $page['viewport_height'] = $page['viewport_height'] ? (int)$page['viewport_height'] : null;
        }

        sendJsonSuccess('Seznam stránek v session načten', [
            'session_id' => $sessionId,
            'total_pages' => count($pages),
            'pages' => $pages
        ]);
    }

} catch (PDOException $e) {
    error_log('Analytics Replay API Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

    sendJsonError('Chyba při načítání dat', 500);

} catch (Exception $e) {
    error_log('Analytics Replay API Unexpected Error: ' . $e->getMessage());
    sendJsonError('Neočekávaná chyba serveru', 500);
}

/**
 * Pomocná funkce pro sanitizaci vstupu
 */
function sanitizeInput($input): ?string
{
    if ($input === null || $input === '') {
        return null;
    }

    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
?>
