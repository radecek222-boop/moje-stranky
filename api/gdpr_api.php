<?php
/**
 * GDPR API
 *
 * API pro správu GDPR compliance a data subject rights.
 *
 * Public Actions (no auth):
 * - check_consent: Zkontrolovat consent status
 * - record_consent: Zaznamenat consent
 * - withdraw_consent: Odvolat consent
 * - request_export: Požádat o export dat
 * - request_deletion: Požádat o smazání dat
 *
 * Admin Actions (auth required):
 * - process_export: Zpracovat export request
 * - process_deletion: Zpracovat deletion request
 * - audit_log: Zobrazit audit log
 * - list_requests: Seznam všech data requests
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #13 - GDPR Compliance Tools
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/GDPRManager.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDbConnection();
    $gdprManager = new GDPRManager($pdo);

    // ========================================
    // PARAMETRY
    // ========================================
    $action = $_GET['action'] ?? $_POST['action'] ?? 'check_consent';

    // ========================================
    // PUBLIC ACTIONS (no auth required)
    // ========================================
    $publicActions = ['check_consent', 'record_consent', 'withdraw_consent', 'request_export', 'request_deletion'];

    if (in_array($action, $publicActions)) {
        // Public actions - no auth required, but CSRF still needed for POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $csrfToken = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (!validateCSRFToken($csrfToken)) {
                sendJsonError('Neplatný CSRF token', 403);
            }
        }

    } else {
        // Admin actions - authentication required
        if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
            sendJsonError('Přístup odepřen - pouze pro admins', 403);
        }

        // CSRF validation
        $csrfToken = $_GET['csrf_token'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!validateCSRFToken($csrfToken)) {
            sendJsonError('Neplatný CSRF token', 403);
        }
    }

    // ========================================
    // ACTION ROUTING
    // ========================================
    switch ($action) {
        // ========================================
        // CHECK_CONSENT - Zkontrolovat consent status
        // ========================================
        case 'check_consent':
            $fingerprintId = $_GET['fingerprint_id'] ?? null;

            if (!$fingerprintId) {
                sendJsonError('Chybí fingerprint_id');
            }

            $consent = $gdprManager->getConsent($fingerprintId);

            sendJsonSuccess('Consent status načten', [
                'has_consent' => $consent !== null,
                'consent' => $consent,
                'has_analytics' => $consent && (bool) $consent['consent_analytics'],
                'has_marketing' => $consent && (bool) $consent['consent_marketing']
            ]);
            break;

        // ========================================
        // RECORD_CONSENT - Zaznamenat consent
        // ========================================
        case 'record_consent':
            $fingerprintId = $_POST['fingerprint_id'] ?? null;
            $analytics = $_POST['consent_analytics'] ?? 0;
            $marketing = $_POST['consent_marketing'] ?? 0;
            $functional = $_POST['consent_functional'] ?? 1;

            if (!$fingerprintId) {
                sendJsonError('Chybí fingerprint_id');
            }

            $consents = [
                'analytics' => (int) $analytics,
                'marketing' => (int) $marketing,
                'functional' => (int) $functional
            ];

            $gdprManager->recordConsent($fingerprintId, $consents, '1.0');

            sendJsonSuccess('Consent zaznamenán', ['consents' => $consents]);
            break;

        // ========================================
        // WITHDRAW_CONSENT - Odvolat consent
        // ========================================
        case 'withdraw_consent':
            $fingerprintId = $_POST['fingerprint_id'] ?? null;

            if (!$fingerprintId) {
                sendJsonError('Chybí fingerprint_id');
            }

            $gdprManager->withdrawConsent($fingerprintId);

            sendJsonSuccess('Consent odvolán');
            break;

        // ========================================
        // REQUEST_EXPORT - Požádat o export dat
        // ========================================
        case 'request_export':
            $fingerprintId = $_POST['fingerprint_id'] ?? null;
            $email = $_POST['email'] ?? null;

            if (!$fingerprintId || !$email) {
                sendJsonError('Chybí fingerprint_id nebo email');
            }

            // Validace email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendJsonError('Neplatný email');
            }

            $requestId = $gdprManager->requestDataExport($fingerprintId, $email);

            sendJsonSuccess('Export request vytvořen', [
                'request_id' => $requestId,
                'status' => 'pending',
                'message' => 'Váš požadavek bude zpracován do 30 dnů'
            ]);
            break;

        // ========================================
        // REQUEST_DELETION - Požádat o smazání dat
        // ========================================
        case 'request_deletion':
            $fingerprintId = $_POST['fingerprint_id'] ?? null;
            $email = $_POST['email'] ?? null;

            if (!$fingerprintId || !$email) {
                sendJsonError('Chybí fingerprint_id nebo email');
            }

            // Validace email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                sendJsonError('Neplatný email');
            }

            $requestId = $gdprManager->requestDataDeletion($fingerprintId, $email);

            sendJsonSuccess('Deletion request vytvořen', [
                'request_id' => $requestId,
                'status' => 'pending',
                'message' => 'Váš požadavek bude zpracován do 30 dnů'
            ]);
            break;

        // ========================================
        // PROCESS_EXPORT - Zpracovat export (admin only)
        // ========================================
        case 'process_export':
            $requestId = $_POST['request_id'] ?? null;

            if (!$requestId) {
                sendJsonError('Chybí request_id');
            }

            $filepath = $gdprManager->processDataExport($requestId);

            sendJsonSuccess('Export zpracován', [
                'request_id' => $requestId,
                'filepath' => basename($filepath),
                'expires_in_days' => 7
            ]);
            break;

        // ========================================
        // PROCESS_DELETION - Zpracovat smazání (admin only)
        // ========================================
        case 'process_deletion':
            $requestId = $_POST['request_id'] ?? null;

            if (!$requestId) {
                sendJsonError('Chybí request_id');
            }

            $gdprManager->processDataDeletion($requestId);

            sendJsonSuccess('Data smazána', ['request_id' => $requestId]);
            break;

        // ========================================
        // LIST_REQUESTS - Seznam data requests (admin only)
        // ========================================
        case 'list_requests':
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 50;
            $offset = isset($_GET['offset']) ? (int) $_GET['offset'] : 0;
            $status = $_GET['status'] ?? null;

            $sql = "SELECT * FROM wgs_gdpr_data_requests";

            if ($status) {
                $sql .= " WHERE status = :status";
            }

            $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

            $stmt = $pdo->prepare($sql);

            if ($status) {
                $stmt->bindValue(':status', $status, PDO::PARAM_STR);
            }

            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $requests = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Total count
            $countSql = "SELECT COUNT(*) as total FROM wgs_gdpr_data_requests";
            if ($status) {
                $countSql .= " WHERE status = :status";
            }

            $countStmt = $pdo->prepare($countSql);
            if ($status) {
                $countStmt->execute(['status' => $status]);
            } else {
                $countStmt->execute();
            }
            $total = (int) $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            sendJsonSuccess('Data requests načteny', [
                'requests' => $requests,
                'total' => $total,
                'limit' => $limit,
                'offset' => $offset
            ]);
            break;

        // ========================================
        // AUDIT_LOG - Zobrazit audit log (admin only)
        // ========================================
        case 'audit_log':
            $fingerprintId = $_GET['fingerprint_id'] ?? null;
            $limit = isset($_GET['limit']) ? (int) $_GET['limit'] : 100;

            if ($fingerprintId) {
                // Audit log pro konkrétní fingerprint
                $logs = $gdprManager->getAuditLog($fingerprintId, $limit);
            } else {
                // Všechny audit logy
                $stmt = $pdo->prepare("
                    SELECT *
                    FROM wgs_gdpr_audit_log
                    ORDER BY created_at DESC
                    LIMIT :limit
                ");
                $stmt->execute(['limit' => $limit]);
                $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            // Dekódovat JSON
            foreach ($logs as &$log) {
                $log['action_details'] = json_decode($log['action_details'], true);
            }

            sendJsonSuccess('Audit log načten', [
                'logs' => $logs,
                'count' => count($logs)
            ]);
            break;

        // ========================================
        // DOWNLOAD_EXPORT - Stáhnout export soubor
        // ========================================
        case 'download_export':
            $requestId = $_GET['request_id'] ?? null;

            if (!$requestId) {
                sendJsonError('Chybí request_id');
            }

            $stmt = $pdo->prepare("SELECT * FROM wgs_gdpr_data_requests WHERE request_id = :id");
            $stmt->execute(['id' => $requestId]);
            $request = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$request || $request['status'] !== 'completed') {
                sendJsonError('Export není dostupný', 404);
            }

            // Check expiration
            if ($request['export_expires_at'] && strtotime($request['export_expires_at']) < time()) {
                sendJsonError('Export vypršel', 410);
            }

            $filepath = $request['export_file_path'];

            if (!file_exists($filepath)) {
                sendJsonError('Soubor nenalezen', 404);
            }

            // Send file
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="' . basename($filepath) . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;

        // ========================================
        // DEFAULT - Neplatná akce
        // ========================================
        default:
            sendJsonError('Neplatná akce: ' . $action);
    }

} catch (PDOException $e) {
    error_log("API GDPR - Database error: " . $e->getMessage());
    sendJsonError('Chyba databáze při zpracování požadavku');
} catch (Exception $e) {
    error_log("API GDPR - Error: " . $e->getMessage());
    sendJsonError('Chyba při zpracování požadavku: ' . $e->getMessage());
}
?>
