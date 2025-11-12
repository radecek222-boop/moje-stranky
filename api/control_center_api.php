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
        case 'get_pending_actions':
            try {
                $stmt = $pdo->query("
                    SELECT *
                    FROM wgs_pending_actions
                    WHERE status = 'pending'
                    ORDER BY
                        FIELD(priority, 'critical', 'high', 'medium', 'low'),
                        created_at DESC
                    LIMIT 50
                ");

                $actions = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode([
                    'success' => true,
                    'actions' => $actions
                ]);
            } catch (PDOException $e) {
                // Table doesn't exist or other DB error - return empty array
                error_log('[Control Center API] get_pending_actions error: ' . $e->getMessage());
                echo json_encode([
                    'success' => true,
                    'actions' => [],
                    'note' => 'Actions table not available'
                ]);
            }
            break;

        case 'execute_action':
            // Spustit akci (např. instalační script)
            $actionId = $data['action_id'] ?? null;

            if (!$actionId) {
                throw new Exception('Action ID required');
            }

            // Načíst akci z DB
            $stmt = $pdo->prepare("SELECT * FROM wgs_pending_actions WHERE id = :id AND status = 'pending'");
            $stmt->execute(['id' => $actionId]);
            $action = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$action) {
                throw new Exception('Akce nenalezena nebo již byla dokončena');
            }

            $startTime = microtime(true);
            $executeResult = ['success' => false, 'message' => ''];

            // Podle action_type spustit příslušnou akci
            try {
                switch ($action['action_type']) {
                    case 'install_smtp':
                        // Spustit SMTP instalaci
                        $sqlFile = __DIR__ . '/../add_smtp_password.sql';
                        if (!file_exists($sqlFile)) {
                            throw new Exception('SQL soubor nenalezen: ' . $sqlFile);
                        }

                        $sql = file_get_contents($sqlFile);
                        $pdo->exec($sql);

                        $executeResult = [
                            'success' => true,
                            'message' => 'SMTP konfigurace úspěšně nainstalována. Přidány klíče smtp_password a smtp_encryption.'
                        ];
                        break;

                    case 'migration':
                    case 'install':
                        // Obecná instalace - spustit URL jako PHP script
                        if (!empty($action['action_url'])) {
                            $scriptPath = __DIR__ . '/../' . ltrim($action['action_url'], '/');
                            if (file_exists($scriptPath)) {
                                ob_start();
                                include $scriptPath;
                                $output = ob_get_clean();

                                $executeResult = [
                                    'success' => true,
                                    'message' => 'Script vykonán: ' . basename($scriptPath),
                                    'output' => $output
                                ];
                            } else {
                                throw new Exception('Script nenalezen: ' . $scriptPath);
                            }
                        } else {
                            throw new Exception('Akce nemá definovaný action_url');
                        }
                        break;

                    default:
                        throw new Exception('Neznámý typ akce: ' . $action['action_type']);
                }
            } catch (Exception $e) {
                $executeResult = [
                    'success' => false,
                    'message' => 'Chyba při vykonávání: ' . $e->getMessage()
                ];
            }

            $executionTime = round((microtime(true) - $startTime) * 1000); // ms

            if ($executeResult['success']) {
                // Označit jako completed
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

                // Přidat do historie
                $pdo->prepare("
                    INSERT INTO wgs_action_history (action_id, action_type, action_title, status, executed_by, execution_time)
                    VALUES (:action_id, :action_type, :action_title, 'completed', :user_id, :exec_time)
                ")->execute([
                    'action_id' => $actionId,
                    'action_type' => $action['action_type'],
                    'action_title' => $action['action_title'],
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'exec_time' => $executionTime
                ]);

                echo json_encode([
                    'status' => 'success',
                    'message' => $executeResult['message'],
                    'execution_time' => $executionTime . 'ms'
                ]);
            } else {
                // Označit jako failed
                $stmt = $pdo->prepare("
                    UPDATE wgs_pending_actions
                    SET status = 'failed'
                    WHERE id = :action_id
                ");
                $stmt->execute(['action_id' => $actionId]);

                // Přidat do historie jako failed
                $pdo->prepare("
                    INSERT INTO wgs_action_history (action_id, action_type, action_title, status, executed_by, execution_time, error_message)
                    VALUES (:action_id, :action_type, :action_title, 'failed', :user_id, :exec_time, :error)
                ")->execute([
                    'action_id' => $actionId,
                    'action_type' => $action['action_type'],
                    'action_title' => $action['action_title'],
                    'user_id' => $_SESSION['user_id'] ?? null,
                    'exec_time' => $executionTime,
                    'error' => $executeResult['message']
                ]);

                throw new Exception($executeResult['message']);
            }
            break;

        case 'complete_action':
            // Pouze označit jako dokončené (manuálně)
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

        // ==========================================
        // SMTP CONFIGURATION
        // ==========================================
        case 'get_smtp_config':
            // Načíst SMTP nastavení z databáze
            $stmt = $pdo->prepare("
                SELECT config_key, config_value, is_sensitive
                FROM wgs_system_config
                WHERE config_group = 'email'
                ORDER BY config_key
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $smtpConfig = [];
            foreach ($rows as $row) {
                // Pro citlivé údaje vracíme placeholder pokud jsou vyplněné
                if ($row['is_sensitive'] && !empty($row['config_value'])) {
                    $smtpConfig[$row['config_key']] = '••••••••';
                } else {
                    $smtpConfig[$row['config_key']] = $row['config_value'];
                }
            }

            echo json_encode([
                'status' => 'success',
                'data' => $smtpConfig
            ]);
            break;

        case 'save_smtp_config':
            // Uložit SMTP nastavení
            $smtpHost = $data['smtp_host'] ?? '';
            $smtpPort = $data['smtp_port'] ?? '587';
            $smtpUsername = $data['smtp_username'] ?? '';
            $smtpPassword = $data['smtp_password'] ?? '';
            $smtpEncryption = $data['smtp_encryption'] ?? 'tls';
            $smtpFrom = $data['smtp_from'] ?? 'reklamace@wgs-service.cz';
            $smtpFromName = $data['smtp_from_name'] ?? 'White Glove Service';

            // Pokud je password placeholder, necháme původní hodnotu
            if ($smtpPassword === '••••••••') {
                $smtpPassword = null; // Nebude se updatovat
            }

            $userId = $_SESSION['user_id'] ?? null;

            // Update jednotlivých hodnot
            $configs = [
                'smtp_host' => $smtpHost,
                'smtp_port' => $smtpPort,
                'smtp_username' => $smtpUsername,
                'smtp_encryption' => $smtpEncryption,
                'smtp_from' => $smtpFrom,
                'smtp_from_name' => $smtpFromName
            ];

            // Přidat password pouze pokud není placeholder
            if ($smtpPassword !== null) {
                $configs['smtp_password'] = $smtpPassword;
            }

            $stmt = $pdo->prepare("
                UPDATE wgs_system_config
                SET config_value = :value,
                    updated_at = CURRENT_TIMESTAMP,
                    updated_by = :user_id
                WHERE config_key = :key
            ");

            foreach ($configs as $key => $value) {
                $stmt->execute([
                    'key' => $key,
                    'value' => $value,
                    'user_id' => $userId
                ]);
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'SMTP konfigurace uložena'
            ]);
            break;

        case 'test_smtp_connection':
            // Test SMTP připojení
            // Načíst aktuální SMTP nastavení
            $stmt = $pdo->prepare("
                SELECT config_key, config_value
                FROM wgs_system_config
                WHERE config_group = 'email' AND config_key IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption')
            ");
            $stmt->execute();
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $config = [];
            foreach ($rows as $row) {
                $config[$row['config_key']] = $row['config_value'];
            }

            // Kontrola že jsou vyplněné všechny údaje
            if (empty($config['smtp_host']) || empty($config['smtp_username']) || empty($config['smtp_password'])) {
                throw new Exception('SMTP údaje nejsou kompletně vyplněné');
            }

            // Pro základní test použijeme PHPMailer nebo fsockopen
            $host = $config['smtp_host'];
            $port = intval($config['smtp_port'] ?? 587);
            $timeout = 10;

            // Pokus o připojení
            $errno = 0;
            $errstr = '';
            $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

            if (!$socket) {
                throw new Exception("Nelze se připojit k SMTP serveru: $errstr ($errno)");
            }

            fclose($socket);

            echo json_encode([
                'status' => 'success',
                'message' => "Připojení k SMTP serveru {$host}:{$port} proběhlo úspěšně"
            ]);
            break;

        // ==========================================
        // TEST EMAIL
        // ==========================================
        case 'send_test_email':
            $email = $data['email'] ?? null;

            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Valid email required');
            }

            // Simple test email
            $subject = 'WGS Control Center - Test Email';
            $message = "Hello!\n\nThis is a test email from WGS Control Center.\n\nIf you received this email, your SMTP settings are working correctly.\n\nTimestamp: " . date('Y-m-d H:i:s') . "\n\nBest regards,\nWhite Glove Service";
            $headers = "From: White Glove Service <reklamace@wgs-service.cz>\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

            $sent = mail($email, $subject, $message, $headers);

            if (!$sent) {
                throw new Exception('Failed to send email. Check SMTP settings.');
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Test email sent to ' . $email
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
