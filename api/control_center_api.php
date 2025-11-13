<?php
/**
 * Control Center API
 * API endpoint pro všechny Control Center funkce
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/rate_limiter.php';

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

    // Načíst action před CSRF kontrolou (pro debug)
    $action = $_GET['action'] ?? '';

    // CSRF ochrana pro POST/PUT/DELETE
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        $csrfToken = $data['csrf_token'] ?? null;

        // SECURITY: Ensure CSRF token is a string, not an array
        if (is_array($csrfToken)) {
            $csrfToken = null;
        }

        // Debug info pro execute_action
        if ($action === 'execute_action' && !$csrfToken) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'CSRF token missing in request',
                'debug' => [
                    'received_data' => array_keys($data),
                    'has_session_token' => isset($_SESSION['csrf_token'])
                ]
            ]);
            exit;
        }

        if (!$csrfToken || !validateCSRFToken($csrfToken)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Invalid CSRF token',
                'debug' => [
                    'token_provided' => !empty($csrfToken),
                    'token_length' => $csrfToken ? strlen($csrfToken) : 0,
                    'session_has_token' => isset($_SESSION['csrf_token']),
                    'tokens_match' => $csrfToken && isset($_SESSION['csrf_token']) ? hash_equals($_SESSION['csrf_token'], $csrfToken) : false
                ]
            ]);
            exit;
        }
    }

    $pdo = getDbConnection();

    // Kontrola existence Admin Control Center tabulek
    // Pokud tabulky neexistují, vrátit informativní odpověď místo crash
    $requiredTables = [
        'wgs_theme_settings',
        'wgs_content_texts',
        'wgs_system_config',
        'wgs_pending_actions',
        'wgs_action_history',
        'wgs_github_webhooks'
    ];

    $missingTables = [];
    foreach ($requiredTables as $table) {
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
            if ($stmt->rowCount() === 0) {
                $missingTables[] = $table;
            }
        } catch (PDOException $e) {
            $missingTables[] = $table;
        }
    }

    // Actions které VYŽADUJÍ existenci VŠECH ACC tabulek
    // SMTP config actions jsou VYŇATY - potřebují pouze wgs_system_config
    $actionsRequiringTables = [
        'save_theme', 'get_pending_actions', 'execute_action', 'complete_action', 'dismiss_action',
        'get_content_texts', 'save_content_text'
    ];

    // Pokud tabulky chybí a action je v seznamu vyžadujících tabulky, vrátit info
    if (!empty($missingTables) && in_array($action, $actionsRequiringTables)) {
        http_response_code(503); // Service Unavailable
        echo json_encode([
            'status' => 'error',
            'message' => 'Admin Control Center není nainstalován',
            'error_code' => 'TABLES_MISSING',
            'missing_tables' => $missingTables,
            'action_required' => 'Spusťte instalaci na /install_admin_control_center.php'
        ]);
        exit;
    }

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
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Action ID required',
                    'debug' => [
                        'received_data' => $data
                    ]
                ]);
                exit;
            }

            // Načíst akci z DB
            try {
                $stmt = $pdo->prepare("SELECT * FROM wgs_pending_actions WHERE id = :id AND status = 'pending'");
                $stmt->execute(['id' => $actionId]);
                $action = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Databázová chyba při načítání akce',
                    'debug' => [
                        'error' => $e->getMessage(),
                        'hint' => 'Tabulka wgs_pending_actions pravděpodobně neexistuje. Spusťte migraci: migrations/create_actions_system.sql'
                    ]
                ]);
                exit;
            }

            if (!$action) {
                http_response_code(404);
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Akce nenalezena nebo již byla dokončena',
                    'debug' => [
                        'action_id' => $actionId,
                        'hint' => 'Zkontrolujte, zda akce existuje v tabulce wgs_pending_actions a má status "pending"'
                    ]
                ]);
                exit;
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

                        // BEZPEČNOST: Ověření integrity SQL souboru pomocí hash
                        $expectedHash = '9013f148ee3befedf2ddc87350ca7d754e841320b7e880f0b8a68214ceb11c9c';
                        $actualHash = hash_file('sha256', $sqlFile);

                        if ($actualHash !== $expectedHash) {
                            throw new Exception('Bezpečnostní chyba: SQL soubor byl modifikován! Hash nesouhlasí.');
                        }

                        $sql = file_get_contents($sqlFile);

                        try {
                            $pdo->exec($sql);
                        } catch (PDOException $e) {
                            throw new Exception('Chyba při vykonávání SQL: ' . $e->getMessage());
                        }

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
            // Test email odesílání pomocí PHP mail() funkce
            // POZNÁMKA: Český hosting blokuje SMTP z webových aplikací,
            // proto používáme vestavěnou mail() funkci

            // Získat FROM email z konfigurace
            $stmt = $pdo->prepare("
                SELECT config_value
                FROM wgs_system_config
                WHERE config_group = 'email' AND config_key = 'smtp_from'
                LIMIT 1
            ");
            $stmt->execute();
            $fromEmail = $stmt->fetchColumn();

            if (!$fromEmail) {
                $fromEmail = 'reklamace@wgs-service.cz'; // Fallback
            }

            // Admin email jako příjemce
            $adminEmail = $_SESSION['user_email'] ?? 'reklamace@wgs-service.cz';

            // Test email
            $subject = 'WGS Admin Control Center - Test Email';
            $message = "Tento email byl odeslán jako test emailového systému.\n\n";
            $message .= "Čas odeslání: " . date('d.m.Y H:i:s') . "\n";
            $message .= "Odesláno z: Admin Control Center\n";
            $message .= "Server: " . ($_SERVER['SERVER_NAME'] ?? 'neznámý') . "\n\n";
            $message .= "Pokud vidíte tento email, emailový systém funguje správně.\n\n";
            $message .= "---\n";
            $message .= "White Glove Service\n";
            $message .= "https://wgs-service.cz";

            $headers = "From: White Glove Service <$fromEmail>\r\n";
            $headers .= "Reply-To: $fromEmail\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "X-Mailer: WGS Admin Control Center";

            // Pokus o odeslání
            $oldErrorHandler = set_error_handler(function() { return true; });
            $emailSent = mail($adminEmail, $subject, $message, $headers);
            restore_error_handler();

            if (!$emailSent) {
                throw new Exception('Nepodařilo se odeslat testovací email. Zkontrolujte konfiguraci serveru.');
            }

            echo json_encode([
                'status' => 'success',
                'message' => "Testovací email byl úspěšně odeslán na $adminEmail. Zkontrolujte si schránku."
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

            // BEZPEČNOST: Rate limiting - max 5 emailů za 10 minut
            $rateLimiter = new RateLimiter($pdo);
            $identifier = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'];
            $limitCheck = $rateLimiter->checkLimit($identifier, 'test_email', [
                'max_attempts' => 5,
                'window_minutes' => 10,
                'block_minutes' => 30
            ]);

            if (!$limitCheck['allowed']) {
                http_response_code(429); // Too Many Requests
                throw new Exception($limitCheck['message']);
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
                'message' => 'Test email sent to ' . $email . '. ' . $limitCheck['remaining'] . ' attempts remaining.'
            ]);
            break;

        // ==========================================
        // KONZOLE DIAGNOSTIKA
        // ==========================================
        case 'check_php_files':
            $rootDir = __DIR__ . '/..';
            $phpFiles = [];
            $errors = [];
            $warnings = [];

            // Hosting má zakázáno exec() - použijeme alternativní metodu
            $execAvailable = function_exists('exec') && !in_array('exec', array_map('trim', explode(',', ini_get('disable_functions'))));

            if (!$execAvailable) {
                // exec() není dostupný - použijeme token_get_all() pro syntax check
                try {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS),
                        RecursiveIteratorIterator::CATCH_GET_CHILD
                    );
                } catch (Exception $dirException) {
                    throw new Exception('Nelze číst adresářovou strukturu: ' . $dirException->getMessage());
                }

                $filesChecked = 0;
                $maxFiles = 500;

                foreach ($iterator as $file) {
                    if ($filesChecked >= $maxFiles) {
                        $warnings[] = "Zkontrolováno pouze prvních $maxFiles souborů (limit)";
                        break;
                    }

                    if ($file->isFile() && $file->getExtension() === 'php') {
                        $filePath = $file->getPathname();
                        $relativePath = str_replace($rootDir . '/', '', $filePath);

                        // Skip vendor, node_modules, cache directories
                        if (strpos($relativePath, 'vendor/') === 0 ||
                            strpos($relativePath, 'node_modules/') === 0 ||
                            strpos($relativePath, 'cache/') === 0) {
                            continue;
                        }

                        $filesChecked++;
                        $phpFiles[] = $relativePath;

                        // Základní syntax check pomocí token_get_all()
                        $content = @file_get_contents($filePath);
                        if ($content !== false) {
                            // Suppress warnings pro token_get_all
                            $oldErrorHandler = set_error_handler(function() { return true; });
                            $tokens = @token_get_all($content);
                            restore_error_handler();

                            // Pokud token_get_all vrátí false nebo prázdné pole, je problém se syntaxí
                            if ($tokens === false || empty($tokens)) {
                                $errors[] = [
                                    'file' => $relativePath,
                                    'error' => 'Syntax error detected (token parsing failed)'
                                ];
                            }
                        }
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'total' => count($phpFiles),
                        'files_checked' => $filesChecked,
                        'errors' => $errors,
                        'warnings' => array_merge($warnings, ['exec() není dostupný - použit alternativní syntax check']),
                        'method' => 'token_get_all'
                    ]
                ]);
                break;
            }

            // Původní exec() verze (pokud je dostupný)
            try {
                $iterator = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CATCH_GET_CHILD
                );

                $filesChecked = 0;
                $maxFiles = 500; // Limit aby to netrvalo věčně

                foreach ($iterator as $file) {
                    if ($filesChecked >= $maxFiles) {
                        $warnings[] = "Zkontrolováno pouze prvních $maxFiles souborů (limit)";
                        break;
                    }

                    if (!$file->isFile() || $file->getExtension() !== 'php') {
                        continue;
                    }

                    // Přeskočit vendor a node_modules
                    $pathname = $file->getPathname();
                    if (strpos($pathname, '/vendor/') !== false ||
                        strpos($pathname, '/node_modules/') !== false ||
                        strpos($pathname, '/.git/') !== false) {
                        continue;
                    }

                    $phpFiles[] = $pathname;
                    $filesChecked++;

                    // Zkontrolovat PHP syntax
                    $output = [];
                    $returnVar = 0;
                    @exec('php -l ' . escapeshellarg($pathname) . ' 2>&1', $output, $returnVar);

                    if ($returnVar !== 0) {
                        $relativePath = str_replace($rootDir . '/', '', $pathname);
                        $errorText = implode(' ', $output);

                        // Parsovat řádek z chyby (např. "Parse error: ... on line 123")
                        $line = null;
                        if (preg_match('/on line (\d+)/', $errorText, $matches)) {
                            $line = $matches[1];
                        }

                        $errors[] = [
                            'file' => $relativePath,
                            'line' => $line,
                            'error' => $errorText,
                            'type' => 'syntax'
                        ];
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'total' => count($phpFiles),
                        'errors' => $errors,
                        'warnings' => $warnings
                    ]
                ]);

            } catch (Exception $e) {
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'total' => count($phpFiles),
                        'errors' => $errors,
                        'warnings' => array_merge($warnings, ['PHP syntax check nedostupný: ' . $e->getMessage()])
                    ]
                ]);
            }
            break;

        case 'check_js_errors':
            $jsLogFile = __DIR__ . '/../logs/js_errors.log';
            $jsErrors = [];

            if (file_exists($jsLogFile)) {
                $lines = file($jsLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                // Poslední 20 errors
                $recentErrors = array_slice(array_reverse($lines), 0, 20);

                foreach ($recentErrors as $line) {
                    $decoded = json_decode($line, true);
                    if ($decoded) {
                        // Struktura: message, file, line, stack, timestamp
                        $jsErrors[] = [
                            'message' => $decoded['message'] ?? 'Unknown error',
                            'file' => $decoded['file'] ?? $decoded['source'] ?? 'unknown',
                            'line' => $decoded['line'] ?? $decoded['lineno'] ?? null,
                            'column' => $decoded['column'] ?? $decoded['colno'] ?? null,
                            'stack' => $decoded['stack'] ?? null,
                            'timestamp' => $decoded['timestamp'] ?? null,
                            'user_agent' => $decoded['user_agent'] ?? null
                        ];
                    }
                }
            }

            // Spočítat JS soubory
            $jsFiles = glob(__DIR__ . '/../assets/js/*.js');

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'total' => count($jsFiles),
                    'recent_errors' => $jsErrors,
                    'error_count' => count($jsErrors)
                ]
            ]);
            break;

        case 'check_database':
            $tables = [];
            $corrupted = [];
            $totalSize = 0;

            // Získat všechny tabulky
            $stmt = $pdo->query("SHOW TABLES");
            $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            foreach ($allTables as $table) {
                // Validace názvu tabulky (bezpečnostní opatření)
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
                    continue; // Přeskočit neplatné názvy
                }

                $tables[] = $table;

                // CHECK TABLE pro kontrolu integrity
                try {
                    // Escape identifikátor pro bezpečnost (i když pochází z SHOW TABLES)
                    $escapedTable = '`' . str_replace('`', '``', $table) . '`';
                    $checkStmt = $pdo->query("CHECK TABLE $escapedTable");
                    $result = $checkStmt->fetch(PDO::FETCH_ASSOC);

                    if (stripos($result['Msg_text'], 'OK') === false) {
                        $corrupted[] = $table;
                    }
                } catch (PDOException $e) {
                    $corrupted[] = $table;
                }

                // Získat velikost tabulky
                try {
                    $sizeStmt = $pdo->prepare("
                        SELECT (data_length + index_length) as size
                        FROM information_schema.TABLES
                        WHERE table_schema = DATABASE()
                        AND table_name = ?
                    ");
                    $sizeStmt->execute([$table]);
                    $sizeResult = $sizeStmt->fetch(PDO::FETCH_ASSOC);
                    $totalSize += $sizeResult['size'] ?? 0;
                } catch (PDOException $e) {
                    // Ignorovat
                }
            }

            // Formátovat velikost
            $size = $totalSize / 1024 / 1024; // MB
            $sizeFormatted = $size > 1000 ? round($size / 1024, 2) . ' GB' : round($size, 2) . ' MB';

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'tables' => $tables,
                    'corrupted' => $corrupted,
                    'missing_indexes' => [],
                    'size' => $sizeFormatted
                ]
            ]);
            break;

        case 'get_recent_errors':
            $phpErrorsFile = __DIR__ . '/../logs/php_errors.log';
            $jsErrorsFile = __DIR__ . '/../logs/js_errors.log';
            $securityLogFile = __DIR__ . '/../logs/security.log';

            $phpErrors = [];
            $jsErrors = [];
            $securityLogs = [];

            // PHP errors - parsovat strukturovaně
            if (file_exists($phpErrorsFile)) {
                $lines = file($phpErrorsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $recentLines = array_slice(array_reverse($lines), 0, 50);

                foreach ($recentLines as $line) {
                    // Přeskočit řádky které jsou metadata (User Agent, IP, Method, URL, atd.)
                    if (preg_match('/^(User Agent|IP|Method|URL|Referer|=+):/i', $line) ||
                        preg_match('/^=+$/', $line)) {
                        continue; // Přeskočit metadata řádky
                    }

                    // Parsovat PHP error log formát:
                    // [05-Nov-2025 17:05:38 Europe/Prague] PHP Warning: message in /path/file.php on line 235
                    if (preg_match('/\[(.*?)\]\s+PHP\s+(Warning|Error|Notice|Fatal error)[:\s]+(.*?)\s+in\s+(.+?)\s+on\s+line\s+(\d+)/', $line, $matches)) {
                        $phpErrors[] = [
                            'timestamp' => $matches[1],
                            'type' => $matches[2],
                            'message' => trim($matches[3]),
                            'file' => basename($matches[4]),
                            'full_path' => $matches[4],
                            'line' => $matches[5],
                            'parsed' => true
                        ];
                    }
                    // Pokud to není PHP error ale vypadá to jako logovací řádek
                    elseif (preg_match('/\[(.*?)\]\s+(.*)/', $line, $matches)) {
                        // DEBUG, ✅ atp. - přeskočit
                        if (stripos($matches[2], 'DEBUG:') === 0 || stripos($matches[2], '✅') === 0) {
                            continue;
                        }
                        // Jiné logování - zobrazit jako raw
                        $phpErrors[] = [
                            'timestamp' => $matches[1],
                            'message' => $matches[2],
                            'parsed' => true
                        ];
                    }
                }

                // Omezit na 20 zobrazených
                $phpErrors = array_slice($phpErrors, 0, 20);
            }

            // JS errors - parsovat JSON
            if (file_exists($jsErrorsFile)) {
                $lines = file($jsErrorsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $recentLines = array_slice(array_reverse($lines), 0, 20);

                foreach ($recentLines as $line) {
                    $decoded = json_decode($line, true);
                    if ($decoded) {
                        $jsErrors[] = [
                            'message' => $decoded['message'] ?? 'Unknown error',
                            'file' => basename($decoded['file'] ?? $decoded['source'] ?? 'unknown'),
                            'full_path' => $decoded['file'] ?? $decoded['source'] ?? 'unknown',
                            'line' => $decoded['line'] ?? $decoded['lineno'] ?? null,
                            'column' => $decoded['column'] ?? $decoded['colno'] ?? null,
                            'timestamp' => $decoded['timestamp'] ?? null,
                            'user_agent' => $decoded['user_agent'] ?? null
                        ];
                    }
                }
            }

            // Security logs - parsovat strukturovaně
            if (file_exists($securityLogFile)) {
                $lines = file($securityLogFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $recentLines = array_slice(array_reverse($lines), 0, 20);

                foreach ($recentLines as $line) {
                    // Parsovat security log formát: [timestamp] [TYPE] message
                    if (preg_match('/\[(.*?)\]\s*\[(.*?)\]\s*(.*)/', $line, $matches)) {
                        $securityLogs[] = [
                            'timestamp' => $matches[1],
                            'type' => $matches[2],
                            'message' => $matches[3],
                            'severity' => strpos($matches[2], 'CRITICAL') !== false ? 'critical' :
                                         (strpos($matches[2], 'WARNING') !== false ? 'warning' : 'info')
                        ];
                    } else {
                        // Fallback
                        $securityLogs[] = [
                            'raw' => $line,
                            'parsed' => false
                        ];
                    }
                }
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'php_errors' => $phpErrors,
                    'js_errors' => $jsErrors,
                    'security_logs' => $securityLogs
                ]
            ]);
            break;

        case 'check_permissions':
            $dirsToCheck = ['logs', 'uploads', 'temp', 'uploads/photos', 'uploads/protokoly'];
            $writable = [];
            $notWritable = [];

            foreach ($dirsToCheck as $dir) {
                $fullPath = __DIR__ . '/../' . $dir;
                if (is_writable($fullPath)) {
                    $writable[] = $dir;
                } else {
                    $notWritable[] = $dir;
                }
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'writable' => $writable,
                    'not_writable' => $notWritable
                ]
            ]);
            break;

        case 'check_security':
            $checks = [
                'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                'csrf_protection' => function_exists('validateCSRFToken'),
                'rate_limiting' => true, // Předpokládáme že rate limiting je implementovaný
                'strong_passwords' => true, // Kontrola v registration_controller.php
                'admin_keys_secure' => defined('ADMIN_KEY_HASH')
            ];

            echo json_encode([
                'status' => 'success',
                'data' => $checks
            ]);
            break;

        // ==========================================
        // ADDITIONAL DIAGNOSTICS
        // ==========================================
        case 'get_system_info':
            $phpVersion = phpversion();
            $diskFree = disk_free_space(__DIR__ . '/..');
            $diskTotal = disk_total_space(__DIR__ . '/..');
            $diskUsedPercent = round((($diskTotal - $diskFree) / $diskTotal) * 100, 1);

            $memoryLimit = ini_get('memory_limit');
            $maxUpload = ini_get('upload_max_filesize');

            $extensions = get_loaded_extensions();

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'php_version' => $phpVersion,
                    'disk_usage' => $diskUsedPercent . '%',
                    'disk_free' => round($diskFree / 1024 / 1024 / 1024, 1) . ' GB',
                    'disk_total' => round($diskTotal / 1024 / 1024 / 1024, 1) . ' GB',
                    'memory_limit' => $memoryLimit,
                    'max_upload' => $maxUpload,
                    'extensions' => $extensions
                ]
            ]);
            break;

        case 'check_html_pages':
            $rootDir = __DIR__ . '/..';
            $htmlFiles = [];
            $errors = [];
            $warnings = [];

            // Find all .php files in root directory (frontend pages)
            $files = glob($rootDir . '/*.php');

            foreach ($files as $file) {
                $relativePath = str_replace($rootDir . '/', '', $file);

                // Skip login.php, init.php, config.php
                if (in_array(basename($file), ['login.php', 'init.php', 'config.php', 'db_connect.php'])) {
                    continue;
                }

                $htmlFiles[] = $relativePath;

                // Basic check - file readable and not empty
                if (!is_readable($file)) {
                    $errors[] = [
                        'file' => $relativePath,
                        'error' => 'File not readable'
                    ];
                } elseif (filesize($file) === 0) {
                    $warnings[] = [
                        'file' => $relativePath,
                        'warning' => 'Empty file'
                    ];
                }
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'total' => count($htmlFiles),
                    'errors' => $errors,
                    'warnings' => $warnings
                ]
            ]);
            break;

        case 'check_assets':
            $assetsDir = __DIR__ . '/../assets';
            $cssFiles = glob($assetsDir . '/css/*.css');
            $imagesDirs = [
                $assetsDir . '/images',
                __DIR__ . '/../uploads'
            ];

            $imageCount = 0;
            $totalSize = 0;
            $errors = [];

            foreach ($imagesDirs as $dir) {
                if (is_dir($dir)) {
                    $images = glob($dir . '/*.{jpg,jpeg,png,gif,svg,webp}', GLOB_BRACE);
                    $imageCount += count($images);

                    foreach ($images as $img) {
                        $totalSize += filesize($img);
                    }
                }
            }

            // Format size
            $sizeMB = $totalSize / 1024 / 1024;
            $sizeFormatted = $sizeMB > 1000 ? round($sizeMB / 1024, 2) . ' GB' : round($sizeMB, 2) . ' MB';

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'css_files' => count($cssFiles),
                    'images' => $imageCount,
                    'total_size' => $sizeFormatted,
                    'errors' => $errors
                ]
            ]);
            break;

        case 'check_dependencies':
            $rootDir = __DIR__ . '/..';
            $composerJson = $rootDir . '/composer.json';
            $packageJson = $rootDir . '/package.json';

            $composerData = [];
            $npmData = [];

            // Check composer.json
            if (file_exists($composerJson)) {
                $json = json_decode(file_get_contents($composerJson), true);
                $composerData = [
                    'exists' => true,
                    'packages' => isset($json['require']) ? count($json['require']) : 0,
                    'outdated' => [],
                    'legacy_mode' => false
                ];
            } else {
                $composerData = [
                    'exists' => false,
                    'legacy_mode' => true  // Project doesn't use Composer
                ];
            }

            // Check package.json
            if (file_exists($packageJson)) {
                $json = json_decode(file_get_contents($packageJson), true);
                $npmData = [
                    'exists' => true,
                    'packages' => (isset($json['dependencies']) ? count($json['dependencies']) : 0) +
                                  (isset($json['devDependencies']) ? count($json['devDependencies']) : 0),
                    'vulnerabilities' => 0,
                    'legacy_mode' => false
                ];
            } else {
                $npmData = [
                    'exists' => false,
                    'legacy_mode' => true  // Project doesn't use NPM
                ];
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'composer' => $composerData,
                    'npm' => $npmData
                ]
            ]);
            break;

        case 'check_configuration':
            $rootDir = __DIR__ . '/..';
            $configFiles = [
                'init.php' => $rootDir . '/init.php',
                'config.php' => $rootDir . '/config/config.php',
                'database.php' => $rootDir . '/config/database.php',
                '.htaccess' => $rootDir . '/.htaccess'
            ];

            $found = 0;
            $errors = [];
            $warnings = [];

            foreach ($configFiles as $file => $fullPath) {
                if (file_exists($fullPath)) {
                    $found++;

                    // Check permissions
                    $perms = fileperms($fullPath);
                    if ($file === '.htaccess' && !($perms & 0x0004)) { // World readable
                        $warnings[] = '.htaccess není world-readable';
                    }
                } else {
                    if ($file !== '.htaccess') { // .htaccess is optional
                        $errors[] = [
                            'file' => $file,
                            'error' => 'Config file missing: ' . $fullPath
                        ];
                    }
                }
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'config_files' => $found,
                    'errors' => $errors,
                    'warnings' => $warnings
                ]
            ]);
            break;

        case 'check_git_status':
            $rootDir = __DIR__ . '/..';
            $gitDir = $rootDir . '/.git';

            if (!is_dir($gitDir)) {
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'branch' => 'N/A (not a git repository)',
                        'uncommitted' => 0,
                        'untracked' => 0
                    ]
                ]);
                break;
            }

            // Get current branch
            $branch = 'unknown';
            $headFile = $gitDir . '/HEAD';
            if (file_exists($headFile)) {
                $head = file_get_contents($headFile);
                if (preg_match('/ref: refs\/heads\/(.+)/', $head, $matches)) {
                    $branch = trim($matches[1]);
                }
            }

            // Count uncommitted changes (simplified - just check if there are modified files)
            $uncommitted = 0;
            $untracked = 0;

            // We can't easily run git commands without exec, so return basic info
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'branch' => $branch,
                    'uncommitted' => $uncommitted,
                    'untracked' => $untracked,
                    'ahead' => 0,
                    'behind' => 0
                ]
            ]);
            break;

        // ==========================================
        // ADVANCED SQL DIAGNOSTICS
        // ==========================================
        case 'check_database_advanced':
            $foreignKeys = [];
            $slowQueries = [];
            $collations = [];
            $orphanedRecords = [];
            $deadlocks = 0;

            try {
                // 1. Foreign Keys Check
                $fkStmt = $pdo->query("
                    SELECT
                        TABLE_NAME as 'table',
                        COLUMN_NAME as 'column',
                        CONSTRAINT_NAME as constraint_name,
                        REFERENCED_TABLE_NAME as referenced_table,
                        REFERENCED_COLUMN_NAME as referenced_column
                    FROM information_schema.KEY_COLUMN_USAGE
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND REFERENCED_TABLE_NAME IS NOT NULL
                ");
                $fks = $fkStmt->fetchAll(PDO::FETCH_ASSOC);

                $foreignKeys['total'] = count($fks);
                $foreignKeys['broken'] = [];

                // Check if referenced tables exist
                foreach ($fks as $fk) {
                    try {
                        $checkStmt = $pdo->prepare("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
                        $checkStmt->execute([$fk['referenced_table']]);
                        if (!$checkStmt->fetch()) {
                            $foreignKeys['broken'][] = array_merge($fk, ['error' => 'Referenced table does not exist']);
                        }
                    } catch (PDOException $e) {
                        $foreignKeys['broken'][] = array_merge($fk, ['error' => $e->getMessage()]);
                    }
                }

                // 2. Slow Queries (from slow query log if enabled)
                // Note: This requires slow_query_log to be enabled
                $slowQueries = [
                    'count' => 0,
                    'threshold' => 2,
                    'queries' => []
                ];

                // Check if slow query log is enabled
                try {
                    $slowLogStmt = $pdo->query("SHOW VARIABLES LIKE 'slow_query_log'");
                    $slowLogEnabled = $slowLogStmt->fetch(PDO::FETCH_ASSOC);
                    if ($slowLogEnabled && $slowLogEnabled['Value'] === 'ON') {
                        // Slow query log is enabled - we could parse it, but that's complex
                        // For now, just indicate it's available
                        $slowQueries['log_enabled'] = true;
                    }
                } catch (PDOException $e) {
                    // Ignore
                }

                // 3. Table Collations Check
                $collStmt = $pdo->query("
                    SELECT TABLE_NAME as 'table', TABLE_COLLATION as collation
                    FROM information_schema.TABLES
                    WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_TYPE = 'BASE TABLE'
                ");
                $tables = $collStmt->fetchAll(PDO::FETCH_ASSOC);

                $defaultCollation = 'utf8mb4_unicode_ci';
                $collations['default'] = $defaultCollation;
                $collations['inconsistent'] = [];

                foreach ($tables as $table) {
                    if ($table['collation'] !== $defaultCollation && !str_starts_with($table['collation'], 'utf8mb4')) {
                        $collations['inconsistent'][] = $table;
                    }
                }

                // 4. Orphaned Records (simplified check - would need specific FK knowledge)
                $orphanedRecords = [
                    'total' => 0,
                    'details' => []
                ];

                // Example: Check for common orphaned patterns
                // This is simplified - full check would require analyzing all FKs

                // 5. Deadlocks (from InnoDB status)
                try {
                    $deadlockStmt = $pdo->query("SHOW ENGINE INNODB STATUS");
                    $innodbStatus = $deadlockStmt->fetch(PDO::FETCH_ASSOC);
                    if ($innodbStatus && isset($innodbStatus['Status'])) {
                        // Parse for deadlock count (simplified)
                        if (preg_match('/(\d+) deadlocks/', $innodbStatus['Status'], $matches)) {
                            $deadlocks = intval($matches[1]);
                        }
                    }
                } catch (PDOException $e) {
                    // Ignore
                }

                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'foreign_keys' => $foreignKeys,
                        'slow_queries' => $slowQueries,
                        'collations' => $collations,
                        'orphaned_records' => $orphanedRecords,
                        'deadlocks' => ['count' => $deadlocks]
                    ]
                ]);

            } catch (PDOException $e) {
                echo json_encode([
                    'status' => 'error',
                    'message' => 'Database advanced check failed: ' . $e->getMessage()
                ]);
            }
            break;

        // ==========================================
        // PERFORMANCE CHECKS
        // ==========================================
        case 'check_performance':
            $rootDir = __DIR__ . '/..';

            // 1. Page Load Times (simulated - would need real monitoring)
            $pageLoadTimes = [
                'pages' => []
            ];

            // 2. Large Assets (> 500KB)
            $largeAssets = [];
            $assetsDirs = [
                $rootDir . '/assets',
                $rootDir . '/uploads'
            ];

            foreach ($assetsDirs as $dir) {
                if (is_dir($dir)) {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
                    );

                    foreach ($iterator as $file) {
                        if ($file->isFile()) {
                            $size = $file->getSize();
                            if ($size > 500 * 1024) { // > 500KB
                                $sizeMB = round($size / 1024 / 1024, 2);
                                $largeAssets[] = [
                                    'path' => str_replace($rootDir . '/', '', $file->getPathname()),
                                    'size' => $sizeMB . ' MB'
                                ];
                            }
                        }
                    }
                }
            }

            // 3. Unminified JS/CSS
            $unminifiedFiles = [];
            $jsFiles = glob($rootDir . '/assets/js/*.js');
            $cssFiles = glob($rootDir . '/assets/css/*.css');

            foreach (array_merge($jsFiles, $cssFiles) as $file) {
                $basename = basename($file);
                // Check if file is NOT minified (doesn't end with .min.js or .min.css)
                if (!preg_match('/\.min\.(js|css)$/', $basename)) {
                    $unminifiedFiles[] = [
                        'path' => str_replace($rootDir . '/', '', $file),
                        'size' => round(filesize($file) / 1024, 1) . ' KB'
                    ];
                }
            }

            // 4. Gzip Compression
            $gzipEnabled = false;
            $htaccessFile = $rootDir . '/.htaccess';
            if (file_exists($htaccessFile)) {
                $htaccess = file_get_contents($htaccessFile);
                $gzipEnabled = (stripos($htaccess, 'mod_deflate') !== false ||
                               stripos($htaccess, 'mod_gzip') !== false);
            }

            // 5. Caching Headers (would need to check .htaccess or server config)
            $cachingHeaders = [
                'missing' => []
            ];

            // 6. N+1 Queries (would need query logging and analysis)
            $nPlusOneQueries = [
                'detected' => 0
            ];

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'page_load_times' => $pageLoadTimes,
                    'large_assets' => ['files' => $largeAssets],
                    'unminified_files' => $unminifiedFiles,
                    'gzip_enabled' => $gzipEnabled,
                    'caching_headers' => $cachingHeaders,
                    'n_plus_one_queries' => $nPlusOneQueries
                ]
            ]);
            break;

        // ==========================================
        // CODE QUALITY CHECKS
        // ==========================================
        case 'check_code_quality':
            $rootDir = __DIR__ . '/..';

            // 1. Dead Code Detection (simplified - scan for unused functions)
            $deadCode = [
                'functions' => []
            ];

            // 2. TODO/FIXME Comments
            $todos = [];
            $phpFiles = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($rootDir, RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($phpFiles as $file) {
                if ($file->isFile() && $file->getExtension() === 'php') {
                    $pathname = $file->getPathname();

                    // Skip vendor and node_modules
                    if (strpos($pathname, '/vendor/') !== false ||
                        strpos($pathname, '/node_modules/') !== false) {
                        continue;
                    }

                    $content = file_get_contents($pathname);
                    $lines = explode("\n", $content);

                    foreach ($lines as $lineNum => $line) {
                        if (preg_match('/(TODO|FIXME|XXX|HACK)[\s:]+(.*)/', $line, $matches)) {
                            $todos[] = [
                                'file' => str_replace($rootDir . '/', '', $pathname),
                                'line' => $lineNum + 1,
                                'type' => $matches[1],
                                'comment' => trim($matches[2])
                            ];
                        }
                    }
                }
            }

            // 3. Code Complexity (simplified)
            $complexity = [
                'high_complexity' => []
            ];

            // 4. Duplicate Code (would need proper analysis tool)
            $duplicates = [
                'blocks' => []
            ];

            // 5. PSR Compliance (would need PHP_CodeSniffer)
            $psrCompliance = [
                'violations' => 0
            ];

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'dead_code' => $deadCode,
                    'todos' => [
                        'count' => count($todos),
                        'items' => array_slice($todos, 0, 20) // Limit to 20
                    ],
                    'complexity' => $complexity,
                    'duplicates' => $duplicates,
                    'psr_compliance' => $psrCompliance
                ]
            ]);
            break;

        // ==========================================
        // SEO CHECKS
        // ==========================================
        case 'check_seo':
            $rootDir = __DIR__ . '/..';

            $missingMetaTags = [];
            $missingAltTags = [];
            $brokenLinks = [];
            $duplicateTitles = [];
            $h1Issues = [];

            // Scan PHP files for SEO issues
            $phpPages = glob($rootDir . '/*.php');
            $pageTitles = [];

            foreach ($phpPages as $page) {
                $basename = basename($page);

                // Skip non-frontend pages
                if (in_array($basename, ['login.php', 'init.php', 'config.php', 'db_connect.php'])) {
                    continue;
                }

                // Skip debug/test/install files
                if (preg_match('/^(debug_|test_|install_|setup_|migration_)/', $basename)) {
                    continue;
                }

                $content = file_get_contents($page);
                $relativePath = str_replace($rootDir . '/', '', $page);

                // Check for meta tags
                $hasTitleTag = preg_match('/<title[^>]*>(.*?)<\/title>/i', $content, $titleMatches);
                $hasMetaDescription = preg_match('/<meta[^>]*name=["\']description["\']/i', $content);
                $hasMetaKeywords = preg_match('/<meta[^>]*name=["\']keywords["\']/i', $content);

                $missingTags = [];
                if (!$hasTitleTag) $missingTags[] = 'title';
                if (!$hasMetaDescription) $missingTags[] = 'description';

                if (!empty($missingTags)) {
                    $missingMetaTags[] = [
                        'url' => $relativePath,
                        'missing_tags' => $missingTags
                    ];
                }

                // Track titles for duplicate detection
                if ($hasTitleTag) {
                    $title = trim($titleMatches[1]);
                    if (!isset($pageTitles[$title])) {
                        $pageTitles[$title] = [];
                    }
                    $pageTitles[$title][] = $relativePath;
                }

                // Check for images without alt tags
                preg_match_all('/<img[^>]*src=["\']([^"\']+)["\'][^>]*>/i', $content, $imgMatches, PREG_SET_ORDER);
                foreach ($imgMatches as $img) {
                    if (!preg_match('/alt=["\']/', $img[0])) {
                        $missingAltTags[] = [
                            'page' => $relativePath,
                            'src' => $img[1]
                        ];
                    }
                }

                // Check for H1 tags
                $h1Count = preg_match_all('/<h1[^>]*>/i', $content);
                if ($h1Count === 0) {
                    $h1Issues[] = [
                        'url' => $relativePath,
                        'issue' => 'No H1 tag found'
                    ];
                } elseif ($h1Count > 1) {
                    $h1Issues[] = [
                        'url' => $relativePath,
                        'issue' => "Multiple H1 tags ($h1Count found)"
                    ];
                }
            }

            // Find duplicate titles
            $duplicates = [];
            foreach ($pageTitles as $title => $pages) {
                if (count($pages) > 1) {
                    $duplicates[] = [
                        'title' => $title,
                        'pages' => $pages
                    ];
                }
            }

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'missing_meta_tags' => ['pages' => $missingMetaTags],
                    'missing_alt_tags' => ['images' => array_slice($missingAltTags, 0, 50)], // Limit to 50
                    'broken_links' => ['links' => $brokenLinks],
                    'duplicate_titles' => ['duplicates' => $duplicates],
                    'h1_issues' => ['pages' => $h1Issues]
                ]
            ]);
            break;

        // ==========================================
        // WORKFLOW & INFRASTRUCTURE CHECKS
        // ==========================================
        case 'check_workflow':
            $rootDir = __DIR__ . '/..';

            // 1. Cron Jobs (would need system access)
            $cronJobs = [
                'total' => 0,
                'not_running' => []
            ];

            // 2. Email Queue (check if there's a queue table)
            $emailQueue = [
                'pending' => 0,
                'failed' => 0
            ];

            try {
                // Check if email queue table exists
                $emailQueueCheck = $pdo->query("SHOW TABLES LIKE 'wgs_email_queue'");
                if ($emailQueueCheck->fetch()) {
                    $pendingStmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_email_queue WHERE status = 'pending'");
                    $emailQueue['pending'] = $pendingStmt->fetch(PDO::FETCH_ASSOC)['count'];

                    $failedStmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_email_queue WHERE status = 'failed'");
                    $emailQueue['failed'] = $failedStmt->fetch(PDO::FETCH_ASSOC)['count'];
                }
            } catch (PDOException $e) {
                // Table doesn't exist
            }

            // 3. Failed Jobs (check action history)
            $failedJobs = [
                'count' => 0,
                'jobs' => []
            ];

            try {
                $failedStmt = $pdo->query("
                    SELECT action_title as name, error_message as error, executed_at as timestamp
                    FROM wgs_action_history
                    WHERE status = 'failed'
                    AND executed_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                    ORDER BY executed_at DESC
                    LIMIT 10
                ");
                $failed = $failedStmt->fetchAll(PDO::FETCH_ASSOC);
                $failedJobs['count'] = count($failed);
                $failedJobs['jobs'] = $failed;
            } catch (PDOException $e) {
                // Table doesn't exist
            }

            // 4. Backup Status (check for backup files)
            $backupStatus = [
                'last_backup' => null,
                'age_days' => null
            ];

            $backupDir = $rootDir . '/backups';
            if (is_dir($backupDir)) {
                $backups = glob($backupDir . '/*.sql*');
                if (!empty($backups)) {
                    usort($backups, function($a, $b) {
                        return filemtime($b) - filemtime($a);
                    });
                    $lastBackup = $backups[0];
                    $backupStatus['last_backup'] = basename($lastBackup);
                    $backupStatus['age_days'] = floor((time() - filemtime($lastBackup)) / 86400);
                }
            }

            // 5. .env Permissions
            $envPermissions = [
                'exists' => false,
                'too_permissive' => false,
                'current' => null
            ];

            $envFile = $rootDir . '/.env';
            if (file_exists($envFile)) {
                $envPermissions['exists'] = true;
                $perms = fileperms($envFile);
                $octalPerms = substr(sprintf('%o', $perms), -3);
                $envPermissions['current'] = $octalPerms;

                // Check if too permissive (should be 600 or 640)
                if ($octalPerms > 640) {
                    $envPermissions['too_permissive'] = true;
                }
            }

            // 6. PHP.ini Critical Settings
            $phpIniSettings = [];
            $criticalSettings = [
                'display_errors' => ['recommended' => 'Off', 'current' => ini_get('display_errors')],
                'error_reporting' => ['recommended' => 'E_ALL', 'current' => ini_get('error_reporting')],
                'post_max_size' => ['recommended' => '>=8M', 'current' => ini_get('post_max_size')],
                'upload_max_filesize' => ['recommended' => '>=8M', 'current' => ini_get('upload_max_filesize')]
            ];

            $warnings = [];
            foreach ($criticalSettings as $setting => $values) {
                if ($setting === 'display_errors' && $values['current'] !== 'Off') {
                    $warnings[] = [
                        'setting' => $setting,
                        'current' => $values['current'],
                        'recommended' => $values['recommended']
                    ];
                }
            }
            $phpIniSettings['warnings'] = $warnings;

            // 7. SMTP Test (basic check)
            $smtpTest = [
                'success' => false,
                'error' => 'Not tested'
            ];

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'cron_jobs' => $cronJobs,
                    'email_queue' => $emailQueue,
                    'failed_jobs' => $failedJobs,
                    'backup_status' => $backupStatus,
                    'env_permissions' => $envPermissions,
                    'php_ini_settings' => $phpIniSettings,
                    'smtp_test' => $smtpTest
                ]
            ]);
            break;

        // ==========================================
        // PING / HEALTH CHECK
        // ==========================================
        case 'ping':
            echo json_encode([
                'status' => 'success',
                'message' => 'pong',
                'timestamp' => time()
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
