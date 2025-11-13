<?php
/**
 * Cleanup History Record - API endpoint pro mazání záznamů
 */

require_once __DIR__ . '/init.php';

// Clear any output buffer
if (ob_get_level()) {
    ob_end_clean();
}

header('Content-Type: application/json');

// Bezpečnost - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

try {
    $pdo = getDbConnection();

    // Načíst JSON data
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    // Log pro debugging
    error_log('[Cleanup] Received data: ' . $jsonData);
    error_log('[Cleanup] Parsed data: ' . json_encode($data));

    if (isset($data['record_id'])) {
        // Smazat konkrétní záznam
        $recordId = (int)$data['record_id'];

        error_log('[Cleanup] Deleting record ID: ' . $recordId);

        $stmt = $pdo->prepare("DELETE FROM wgs_action_history WHERE id = :id");
        $stmt->execute(['id' => $recordId]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Record deleted successfully',
            'deleted_count' => $stmt->rowCount()
        ]);

    } elseif (isset($data['delete_all_failed']) && $data['delete_all_failed'] === true) {
        // Smazat všechny selhavší záznamy
        error_log('[Cleanup] Deleting all failed records');

        $stmt = $pdo->query("DELETE FROM wgs_action_history WHERE status = 'failed'");

        echo json_encode([
            'status' => 'success',
            'message' => 'All failed records deleted successfully',
            'deleted_count' => $stmt->rowCount()
        ]);

    } else {
        error_log('[Cleanup] Invalid request - data: ' . json_encode($data));
        throw new Exception('Invalid request - no valid action specified');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
