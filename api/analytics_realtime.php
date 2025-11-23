<?php
/**
 * Analytics Real-time API
 *
 * Read API pro real-time dashboard s aktivními návštěvníky.
 *
 * Actions:
 * - active_visitors: Počet aktivních návštěvníků (humans vs bots)
 * - active_sessions: Seznam aktivních sessions s detaily
 * - live_events: Stream nejnovějších eventů (posledních 50)
 * - cleanup: Odstranění expirovaných sessions
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #11 - Real-time Dashboard
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// DEBUG: Log request
error_log("=== REALTIME API DEBUG ===");
error_log("Action: " . ($_GET['action'] ?? 'none'));
error_log("Session ID: " . session_id());
error_log("Is Admin: " . (isset($_SESSION['is_admin']) ? 'yes' : 'no'));
error_log("CSRF Token received: " . ($_GET['csrf_token'] ?? 'none'));
error_log("CSRF Token session: " . ($_SESSION['csrf_token'] ?? 'none'));

try {
    // ========================================
    // AUTHENTICATION CHECK (admin only)
    // ========================================
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        error_log("AUTH FAILED: Not admin");
        sendJsonError('Přístup odepřen - pouze pro admins', 403);
    }

    // ========================================
    // CSRF VALIDACE
    // ========================================
    $csrfToken = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!validateCSRFToken($csrfToken)) {
        sendJsonError('Neplatný CSRF token', 403);
    }

    $pdo = getDbConnection();

    // ========================================
    // PARAMETRY
    // ========================================
    $action = $_GET['action'] ?? 'active_visitors';
    $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
    $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;

    // ========================================
    // ACTION ROUTING
    // ========================================
    switch ($action) {
        // ========================================
        // ACTIVE_VISITORS - Počet aktivních návštěvníků
        // ========================================
        case 'active_visitors':
            // Cleanup expirovaných sessions před počítáním
            $pdo->exec("DELETE FROM wgs_analytics_realtime WHERE expires_at < NOW()");

            // Počet aktivních lidí (ne botů)
            $stmt = $pdo->query("
                SELECT COUNT(*) as count
                FROM wgs_analytics_realtime
                WHERE is_active = 1
                AND is_bot = 0
                AND expires_at > NOW()
            ");
            $humans = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Počet aktivních botů
            $stmt = $pdo->query("
                SELECT COUNT(*) as count
                FROM wgs_analytics_realtime
                WHERE is_active = 1
                AND is_bot = 1
                AND expires_at > NOW()
            ");
            $bots = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Celkem aktivních
            $total = $humans + $bots;

            // Počet countries
            $stmt = $pdo->query("
                SELECT COUNT(DISTINCT country_code) as count
                FROM wgs_analytics_realtime
                WHERE is_active = 1
                AND expires_at > NOW()
                AND country_code IS NOT NULL
            ");
            $countries = (int) $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Průměrná doba trvání sessions (aktivních)
            $stmt = $pdo->query("
                SELECT AVG(session_duration) as avg_duration
                FROM wgs_analytics_realtime
                WHERE is_active = 1
                AND expires_at > NOW()
            ");
            $avgDuration = round((float) $stmt->fetch(PDO::FETCH_ASSOC)['avg_duration'], 2);

            sendJsonSuccess('Aktivní návštěvníci načteni', [
                'total' => $total,
                'humans' => $humans,
                'bots' => $bots,
                'countries' => $countries,
                'avg_duration' => $avgDuration,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        // ========================================
        // ACTIVE_SESSIONS - Seznam aktivních sessions
        // ========================================
        case 'active_sessions':
            // Cleanup expirovaných sessions
            $pdo->exec("DELETE FROM wgs_analytics_realtime WHERE expires_at < NOW()");

            $stmt = $pdo->prepare("
                SELECT *
                FROM wgs_analytics_realtime
                WHERE is_active = 1
                AND expires_at > NOW()
                ORDER BY last_activity_at DESC
                LIMIT :limit OFFSET :offset
            ");
            $stmt->execute([
                'limit' => $limit,
                'offset' => $offset
            ]);
            $sessions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Total count
            $countStmt = $pdo->query("
                SELECT COUNT(*) as total
                FROM wgs_analytics_realtime
                WHERE is_active = 1
                AND expires_at > NOW()
            ");
            $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            sendJsonSuccess('Aktivní sessions načteny', [
                'sessions' => $sessions,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        // ========================================
        // LIVE_EVENTS - Stream nejnovějších eventů
        // ========================================
        case 'live_events':
            // Zkontrolovat existenci tabulky wgs_analytics_events
            $tableCheck = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_events'");

            if ($tableCheck->rowCount() === 0) {
                // Tabulka neexistuje - vrátit prázdné pole
                sendJsonSuccess('Live eventy načteny (tabulka ještě neexistuje)', [
                    'events' => [],
                    'count' => 0,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
            }

            // Získat nejnovější eventy z wgs_analytics_events
            $stmt = $pdo->prepare("
                SELECT
                    e.event_id,
                    e.session_id,
                    e.event_type,
                    e.event_timestamp,
                    e.page_url,
                    e.event_data,
                    r.country_code,
                    r.city,
                    r.device_type,
                    r.current_page_title,
                    r.is_bot
                FROM wgs_analytics_events e
                LEFT JOIN wgs_analytics_realtime r ON e.session_id = r.session_id AND r.is_active = 1
                WHERE e.event_timestamp >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                ORDER BY e.event_timestamp DESC
                LIMIT :limit
            ");
            $stmt->execute(['limit' => $limit]);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Dekódovat JSON data
            foreach ($events as &$event) {
                if (isset($event['event_data'])) {
                    $event['event_data'] = json_decode($event['event_data'], true);
                }
            }

            sendJsonSuccess('Live eventy načteny', [
                'events' => $events,
                'count' => count($events),
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        // ========================================
        // CLEANUP - Odstranění expirovaných sessions
        // ========================================
        case 'cleanup':
            $stmt = $pdo->exec("DELETE FROM wgs_analytics_realtime WHERE expires_at < NOW()");
            $deletedCount = $stmt;

            sendJsonSuccess('Cleanup dokončen', [
                'deleted_count' => $deletedCount,
                'timestamp' => date('Y-m-d H:i:s')
            ]);
            break;

        // ========================================
        // DEFAULT - Neplatná akce
        // ========================================
        default:
            sendJsonError('Neplatná akce: ' . $action);
    }

} catch (PDOException $e) {
    error_log("API Realtime - Database error: " . $e->getMessage());
    sendJsonError('Chyba databáze při zpracování požadavku');
} catch (Exception $e) {
    error_log("API Realtime - Error: " . $e->getMessage());
    sendJsonError('Chyba při zpracování požadavku: ' . $e->getMessage());
}
?>
