<?php
/**
 * Admin API Router
 * Centrální router pro všechny admin API endpointy
 * Nahrazuje původní control_center_api.php (3085 řádků → modulární struktura)
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

header('Content-Type: application/json');
header('Cache-Control: private, max-age=120'); // 2 minuty

try {
    // BEZPEČNOST: Pouze admin
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    if (!$isAdmin) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Neautorizovaný přístup'
        ]);
        exit;
    }

    // Rate limiting na admin API
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? 'admin';
    $identifier = "admin_api_{$ip}_{$userId}";

    try {
        $pdo = getDbConnection();
        $rateLimiter = new RateLimiter($pdo);

        $rateCheck = $rateLimiter->checkLimit($identifier, 'admin_api', [
            'max_attempts' => 300,
            'window_minutes' => 10,
            'block_minutes' => 30
        ]);

        if (!$rateCheck['allowed']) {
            http_response_code(429);
            echo json_encode([
                'status' => 'error',
                'message' => $rateCheck['message'],
                'retry_after' => $rateCheck['reset_at']
            ]);
            exit;
        }
    } catch (Exception $e) {
        error_log("Rate limiter failed in admin API: " . $e->getMessage());
    }

    // Načtení JSON dat
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);
    $action = $_GET['action'] ?? '';

    // CSRF ochrana pro POST/PUT/DELETE
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $csrfToken = $data['csrf_token'] ?? null;

        if (is_array($csrfToken)) {
            $csrfToken = null;
        }

        require_once __DIR__ . '/../includes/csrf_helper.php';
        if (!validateCSRFToken($csrfToken)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Neplatný CSRF token',
                'debug' => [
                    'action' => $action,
                    'method' => $_SERVER['REQUEST_METHOD'],
                    'csrf_received' => $csrfToken ? 'yes' : 'no',
                    'csrf_type' => gettype($csrfToken)
                ]
            ]);
            exit;
        }
    }

    // Routing do modulů podle akce
    $actionModule = getActionModule($action);

    if (!$actionModule) {
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Neznámá akce: {$action}"
        ]);
        exit;
    }

    $modulePath = __DIR__ . "/admin/{$actionModule}.php";

    if (!file_exists($modulePath)) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => "Modul {$actionModule} nebyl nalezen"
        ]);
        exit;
    }

    // Načíst a spustit modul
    require_once $modulePath;

} catch (Exception $e) {
    error_log("Admin API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Interní chyba serveru'
    ]);
}

/**
 * Určí, který modul má zpracovat danou akci
 */
function getActionModule(string $action): ?string
{
    // Theme & Content
    $themeActions = ['save_theme', 'get_content_texts', 'save_content_text'];

    // Actions & Tasks
    $actionsActions = ['get_pending_actions', 'execute_action', 'complete_action',
                       'dismiss_action', 'add_optimization_tasks'];

    // Config & SMTP
    $configActions = ['get_system_config', 'save_system_config', 'get_smtp_config',
                      'save_smtp_config', 'test_smtp_connection', 'send_test_email'];

    // Maintenance
    $maintenanceActions = ['clear_cache', 'cleanup_logs', 'archive_logs', 'optimize_database'];

    // Diagnostics (všechny check_* a ostatní)
    $diagnosticsActions = ['check_php_files', 'check_js_errors', 'check_database',
                          'get_recent_errors', 'check_permissions', 'check_security',
                          'get_system_info', 'check_html_pages', 'check_assets',
                          'check_dependencies', 'check_configuration', 'check_git_status',
                          'check_database_advanced', 'check_performance', 'check_code_quality',
                          'check_seo', 'check_workflow', 'security_scan', 'check_code_analysis',
                          'ping'];

    if (in_array($action, $themeActions)) {
        return 'theme';
    }

    if (in_array($action, $actionsActions)) {
        return 'actions';
    }

    if (in_array($action, $configActions)) {
        return 'config';
    }

    if (in_array($action, $maintenanceActions)) {
        return 'maintenance';
    }

    if (in_array($action, $diagnosticsActions)) {
        return 'diagnostics';
    }

    return null;
}
