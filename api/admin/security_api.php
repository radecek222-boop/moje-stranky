<?php
/**
 * Security API
 * API endpoint pro bezpečnostní operace - audit logy, klíče, atd.
 */

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/csrf_helper.php';
require_once __DIR__ . '/../../includes/audit_logger.php';

header('Content-Type: application/json; charset=utf-8');

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Přístup odepřen']);
    exit;
}

// CSRF validace
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['status' => 'error', 'message' => 'Neplatný CSRF token']);
        exit;
    }
}

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    switch ($action) {
        case 'get_audit_logs':
            handleGetAuditLogs();
            break;

        default:
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Neznámá akce']);
            break;
    }

} catch (Exception $e) {
    error_log("Security API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Chyba serveru']);
}

/**
 * Načíst audit logy
 */
function handleGetAuditLogs() {
    try {
        $dateFrom = $_POST['date_from'] ?? date('Y-m-d', strtotime('-7 days')) . ' 00:00:00';
        $dateTo = $_POST['date_to'] ?? date('Y-m-d') . ' 23:59:59';
        $filterAction = $_POST['filter_action'] ?? null;
        $filterUserId = $_POST['filter_user_id'] ?? null;

        // Validace datumů
        if (!strtotime($dateFrom) || !strtotime($dateTo)) {
            throw new InvalidArgumentException('Neplatný formát data');
        }

        // Načíst logy pomocí funkce z audit_logger.php
        $logs = getAuditLogs($dateFrom, $dateTo, $filterAction, $filterUserId);

        // Seřadit od nejnovějších
        usort($logs, function($a, $b) {
            return strtotime($b['timestamp']) - strtotime($a['timestamp']);
        });

        echo json_encode([
            'status' => 'success',
            'logs' => $logs,
            'count' => count($logs),
            'filter' => [
                'from' => $dateFrom,
                'to' => $dateTo,
                'action' => $filterAction,
                'user_id' => $filterUserId
            ]
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    } catch (Exception $e) {
        error_log("Error loading audit logs: " . $e->getMessage());
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => 'Chyba při načítání audit logů: ' . $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
}
?>
