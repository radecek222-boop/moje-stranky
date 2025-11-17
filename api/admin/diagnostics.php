<?php
/**
 * Admin API - Diagnostics Module
 * Zpracování diagnostických kontrol a health checks
 * Extrahováno z control_center_api.php
 */

// Tento soubor je načítán přes api/admin.php router
// Proměnné $pdo, $data, $action jsou již k dispozici

switch ($action) {
    case 'ping':
        echo json_encode([
            'status' => 'success',
            'message' => 'pong',
            'timestamp' => time(),
            'server_time' => date('Y-m-d H:i:s')
        ]);
        break;

    case 'check_php_files':
        // Zkrácená verze - vrátí jen základní info
        echo json_encode([
            'status' => 'success',
            'message' => 'PHP files check not implemented in modular version',
            'note' => 'Use legacy control_center_api.php for full diagnostics'
        ]);
        break;

    case 'check_js_errors':
        echo json_encode([
            'status' => 'success',
            'message' => 'JS errors check not implemented in modular version'
        ]);
        break;

    case 'check_database':
        try {
            // Základní DB check
            $stmt = $pdo->query("SELECT VERSION() as version");
            $dbVersion = $stmt->fetchColumn();

            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            echo json_encode([
                'status' => 'success',
                'database_version' => $dbVersion,
                'tables_count' => count($tables),
                'tables' => $tables
            ]);
        } catch (PDOException $e) {
            throw new Exception('Database check failed: ' . $e->getMessage());
        }
        break;

    case 'get_recent_errors':
        $errorLog = __DIR__ . '/../../logs/php_errors.log';
        $errors = [];

        if (file_exists($errorLog)) {
            $lines = file($errorLog, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $errors = array_slice(array_reverse($lines), 0, 50);
        }

        echo json_encode([
            'status' => 'success',
            'errors' => $errors,
            'count' => count($errors)
        ]);
        break;

    case 'check_permissions':
        $directories = [
            'logs' => __DIR__ . '/../../logs',
            'uploads' => __DIR__ . '/../../uploads',
            'backups' => __DIR__ . '/../../backups',
            'temp' => __DIR__ . '/../../temp'
        ];

        $results = [];
        foreach ($directories as $name => $path) {
            $results[$name] = [
                'exists' => is_dir($path),
                'writable' => is_writable($path),
                'readable' => is_readable($path)
            ];
        }

        echo json_encode([
            'status' => 'success',
            'permissions' => $results
        ]);
        break;

    case 'check_security':
        $checks = [
            'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
            'session_secure' => ini_get('session.cookie_secure') == '1',
            'session_httponly' => ini_get('session.cookie_httponly') == '1',
            'display_errors' => ini_get('display_errors') == '0',
            'expose_php' => ini_get('expose_php') == '0'
        ];

        echo json_encode([
            'status' => 'success',
            'security_checks' => $checks
        ]);
        break;

    case 'get_system_info':
        $info = [
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'unknown',
            'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'unknown',
            'server_name' => $_SERVER['SERVER_NAME'] ?? 'unknown',
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size')
        ];

        echo json_encode([
            'status' => 'success',
            'system_info' => $info
        ]);
        break;

    case 'check_html_pages':
    case 'check_assets':
    case 'check_dependencies':
    case 'check_configuration':
    case 'check_git_status':
    case 'check_database_advanced':
    case 'check_performance':
    case 'check_code_quality':
    case 'check_seo':
    case 'check_workflow':
    case 'security_scan':
    case 'check_code_analysis':
        // Placeholder pro komplexní diagnostiky
        echo json_encode([
            'status' => 'success',
            'message' => "Diagnostic '{$action}' not implemented in modular version",
            'note' => 'Use legacy control_center_api.php for full diagnostics'
        ]);
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Unknown diagnostics action: {$action}"
        ]);
}
