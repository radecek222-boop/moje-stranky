<?php
/**
 * Control Center API
 * API endpoint pro všechny Control Center funkce
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

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

    // Načtení JSON dat
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    // CSRF ochrana pro POST/PUT/DELETE
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $csrfToken = $data['csrf_token'] ?? null;
        if (!$csrfToken || !validateCSRFToken($csrfToken)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid CSRF token'
            ]);
            exit;
        }
    }

    $action = $_GET['action'] ?? '';
    $pdo = getDbConnection();

    switch ($action) {

        // ==========================================
        // THEME SETTINGS
        // ==========================================
        case 'save_theme':
            $settings = $data['settings'] ?? [];

            if (empty($settings)) {
                throw new Exception('No settings provided');
            }

            // Update theme settings v databázi
            $stmt = $pdo->prepare("
                INSERT INTO wgs_theme_settings (setting_key, setting_value, setting_type, setting_group, updated_by)
                VALUES (:key, :value, :type, :group, :user_id)
                ON DUPLICATE KEY UPDATE
                    setting_value = VALUES(setting_value),
                    updated_at = CURRENT_TIMESTAMP,
                    updated_by = VALUES(updated_by)
            ");

            $userId = $_SESSION['user_id'] ?? null;

            foreach ($settings as $key => $value) {
                $type = strpos($key, 'color') !== false ? 'color' :
                        ($key === 'font_family' ? 'font' : 'size');
                $group = strpos($key, 'color') !== false ? 'colors' :
                         ($key === 'font_family' || $key === 'font_size_base' ? 'typography' : 'layout');

                $stmt->execute([
                    'key' => $key,
                    'value' => $value,
                    'type' => $type,
                    'group' => $group,
                    'user_id' => $userId
                ]);
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Theme settings saved',
                'settings' => $settings
            ]);
            break;

        // ==========================================
        // PENDING ACTIONS
        // ==========================================
        case 'complete_action':
            $actionId = $data['action_id'] ?? null;

            if (!$actionId) {
                throw new Exception('Action ID required');
            }

            $stmt = $pdo->prepare("
                UPDATE wgs_pending_actions
                SET status = 'completed',
                    completed_at = CURRENT_TIMESTAMP,
                    completed_by = :user_id
                WHERE id = :action_id
            ");

            $stmt->execute([
                'action_id' => $actionId,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);

            // Add to history
            $pdo->prepare("
                INSERT INTO wgs_action_history (action_id, action_type, action_title, status, executed_by)
                SELECT id, action_type, action_title, 'completed', :user_id
                FROM wgs_pending_actions
                WHERE id = :action_id
            ")->execute([
                'action_id' => $actionId,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Action marked as completed'
            ]);
            break;

        case 'dismiss_action':
            $actionId = $data['action_id'] ?? null;

            if (!$actionId) {
                throw new Exception('Action ID required');
            }

            $stmt = $pdo->prepare("
                UPDATE wgs_pending_actions
                SET status = 'dismissed',
                    completed_at = CURRENT_TIMESTAMP,
                    completed_by = :user_id
                WHERE id = :action_id
            ");

            $stmt->execute([
                'action_id' => $actionId,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Action dismissed'
            ]);
            break;

        // ==========================================
        // DIAGNOSTICS
        // ==========================================
        case 'clear_cache':
            $tempPath = __DIR__ . '/../temp';
            $cachePath = __DIR__ . '/../cache';

            $filesDeleted = 0;

            // Clear temp files
            if (is_dir($tempPath)) {
                $files = glob($tempPath . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && basename($file) !== '.gitkeep') {
                        unlink($file);
                        $filesDeleted++;
                    }
                }
            }

            // Clear cache if exists
            if (is_dir($cachePath)) {
                $files = glob($cachePath . '/*');
                foreach ($files as $file) {
                    if (is_file($file) && basename($file) !== '.gitkeep') {
                        unlink($file);
                        $filesDeleted++;
                    }
                }
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Cache cleared',
                'files_deleted' => $filesDeleted
            ]);
            break;

        case 'archive_logs':
            $logsPath = __DIR__ . '/../logs';
            $archivePath = __DIR__ . '/../logs/archive';

            if (!is_dir($archivePath)) {
                mkdir($archivePath, 0755, true);
            }

            $cutoffDate = strtotime('-90 days');
            $archivedCount = 0;

            $logFiles = glob($logsPath . '/*.log');
            foreach ($logFiles as $file) {
                if (filemtime($file) < $cutoffDate) {
                    $newPath = $archivePath . '/' . basename($file);
                    rename($file, $newPath);
                    $archivedCount++;
                }
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Logs archived',
                'count' => $archivedCount
            ]);
            break;

        case 'optimize_database':
            $startTime = microtime(true);
            $tablesOptimized = 0;

            // Get all tables
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($tables as $table) {
                try {
                    $pdo->exec("OPTIMIZE TABLE `$table`");
                    $tablesOptimized++;
                } catch (PDOException $e) {
                    // Skip if table can't be optimized
                }
            }

            $endTime = microtime(true);
            $timeMs = round(($endTime - $startTime) * 1000);

            echo json_encode([
                'status' => 'success',
                'message' => 'Database optimized',
                'tables_optimized' => $tablesOptimized,
                'time_ms' => $timeMs
            ]);
            break;

        // ==========================================
        // CONTENT TEXTS
        // ==========================================
        case 'get_content_texts':
            $page = $_GET['page'] ?? null;

            $query = "SELECT * FROM wgs_content_texts";
            if ($page) {
                $query .= " WHERE page = :page";
            }
            $query .= " ORDER BY page, section, text_key";

            $stmt = $pdo->prepare($query);
            if ($page) {
                $stmt->execute(['page' => $page]);
            } else {
                $stmt->execute();
            }

            $texts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data' => $texts
            ]);
            break;

        case 'save_content_text':
            $id = $data['id'] ?? null;
            $valueCz = $data['value_cz'] ?? '';
            $valueEn = $data['value_en'] ?? '';
            $valueSk = $data['value_sk'] ?? '';

            if (!$id) {
                throw new Exception('Text ID required');
            }

            $stmt = $pdo->prepare("
                UPDATE wgs_content_texts
                SET value_cz = :value_cz,
                    value_en = :value_en,
                    value_sk = :value_sk,
                    updated_at = CURRENT_TIMESTAMP,
                    updated_by = :user_id
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $id,
                'value_cz' => $valueCz,
                'value_en' => $valueEn,
                'value_sk' => $valueSk,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Content text saved'
            ]);
            break;

        // ==========================================
        // SYSTEM CONFIG
        // ==========================================
        case 'get_system_config':
            $group = $_GET['group'] ?? null;

            $query = "SELECT * FROM wgs_system_config";
            if ($group) {
                $query .= " WHERE config_group = :group";
            }
            $query .= " ORDER BY config_group, config_key";

            $stmt = $pdo->prepare($query);
            if ($group) {
                $stmt->execute(['group' => $group]);
            } else {
                $stmt->execute();
            }

            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mask sensitive values
            foreach ($configs as &$config) {
                if ($config['is_sensitive']) {
                    $value = $config['config_value'];
                    if (strlen($value) > 8) {
                        $config['config_value_masked'] = substr($value, 0, 4) . '••••••••' . substr($value, -4);
                    } else {
                        $config['config_value_masked'] = '••••••••';
                    }
                }
            }

            echo json_encode([
                'status' => 'success',
                'data' => $configs
            ]);
            break;

        case 'save_system_config':
            $key = $data['key'] ?? null;
            $value = $data['value'] ?? '';

            if (!$key) {
                throw new Exception('Config key required');
            }

            $stmt = $pdo->prepare("
                UPDATE wgs_system_config
                SET config_value = :value,
                    updated_at = CURRENT_TIMESTAMP,
                    updated_by = :user_id
                WHERE config_key = :key
            ");

            $stmt->execute([
                'key' => $key,
                'value' => $value,
                'user_id' => $_SESSION['user_id'] ?? null
            ]);

            echo json_encode([
                'status' => 'success',
                'message' => 'Config saved'
            ]);
            break;

        default:
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Unknown action: ' . $action
            ]);
            break;
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
