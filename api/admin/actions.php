<?php
/**
 * Admin API - Actions & Tasks Module
 * Zpracování akcí, úkolů a systémových operací
 * Extrahováno z control_center_api.php
 */

// Tento soubor je načítán přes api/admin.php router
// Proměnné $pdo, $data, $action jsou již k dispozici

switch ($action) {
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
            error_log('[Admin API] get_pending_actions error: ' . $e->getMessage());
            echo json_encode([
                'success' => true,
                'actions' => [],
                'note' => 'Actions table not available'
            ]);
        }
        break;

    case 'execute_action':
        $actionId = $data['action_id'] ?? null;

        if (!$actionId) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => 'Action ID required',
                'debug' => ['received_data' => $data]
            ]);
            exit;
        }

        // Načíst akci z DB
        try {
            $stmt = $pdo->prepare("SELECT * FROM wgs_pending_actions WHERE id = :id AND status = 'pending'");
            $stmt->execute(['id' => $actionId]);
            $actionData = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => 'Databázová chyba při načítání akce',
                'debug' => [
                    'error' => $e->getMessage(),
                    'hint' => 'Tabulka wgs_pending_actions pravděpodobně neexistuje'
                ]
            ]);
            exit;
        }

        if (!$actionData) {
            http_response_code(404);
            echo json_encode([
                'status' => 'error',
                'message' => 'Akce nenalezena nebo již byla dokončena'
            ]);
            exit;
        }

        $startTime = microtime(true);
        $executeResult = ['success' => false, 'message' => ''];

        // Podle action_type spustit příslušnou akci
        try {
            switch ($actionData['action_type']) {
                case 'install_smtp':
                    $sqlFile = __DIR__ . '/../../add_smtp_password.sql';
                    if (!file_exists($sqlFile)) {
                        throw new Exception('SQL soubor nenalezen: ' . $sqlFile);
                    }

                    $expectedHash = '9013f148ee3befedf2ddc87350ca7d754e841320b7e880f0b8a68214ceb11c9c';
                    $actualHash = hash_file('sha256', $sqlFile);

                    if ($actualHash !== $expectedHash) {
                        throw new Exception('Bezpečnostní chyba: SQL soubor byl modifikován!');
                    }

                    $sql = file_get_contents($sqlFile);
                    $pdo->exec($sql);

                    $executeResult = [
                        'success' => true,
                        'message' => 'SMTP konfigurace úspěšně nainstalována'
                    ];
                    break;

                case 'install_phpmailer':
                    $scriptPath = __DIR__ . '/../../scripts/install_phpmailer.php';
                    if (!file_exists($scriptPath)) {
                        throw new Exception('Instalační script nenalezen');
                    }

                    ob_start();
                    $installSuccess = include $scriptPath;
                    $output = ob_get_clean();

                    if ($installSuccess === false) {
                        throw new Exception('Instalace selhala');
                    }

                    $executeResult = [
                        'success' => true,
                        'message' => '✅ PHPMailer byl úspěšně nainstalován',
                        'output' => $output
                    ];
                    break;

                case 'migration':
                case 'install':
                case 'config':
                case 'optimize_assets':
                case 'add_db_indexes':
                case 'create_backup':
                case 'cleanup_emails':
                    if (!empty($actionData['action_url'])) {
                        $scriptPath = __DIR__ . '/../../' . ltrim($actionData['action_url'], '/');

                        // BEZPEČNOST: Whitelist povolených directories
                        $allowedDirs = [
                            realpath(__DIR__ . '/../..'),
                            realpath(__DIR__ . '/../../scripts'),
                            realpath(__DIR__ . '/../../migrations'),
                            realpath(__DIR__ . '/../../install'),
                            realpath(__DIR__ . '/../../setup')
                        ];

                        $realScriptPath = realpath($scriptPath);
                        if (!$realScriptPath) {
                            throw new Exception("Script nenalezen: {$scriptPath}");
                        }

                        $isAllowed = false;
                        foreach ($allowedDirs as $allowedDir) {
                            if ($allowedDir && strpos($realScriptPath, $allowedDir) === 0) {
                                $isAllowed = true;
                                break;
                            }
                        }

                        if (!$isAllowed) {
                            throw new Exception("Bezpečnostní chyba: Script není v povoleném adresáři");
                        }

                        // .md soubory = dokumentace
                        if (pathinfo($scriptPath, PATHINFO_EXTENSION) === 'md') {
                            $executeResult = [
                                'success' => true,
                                'message' => 'Dokumentace: ' . basename($scriptPath),
                                'action' => 'open_documentation',
                                'url' => $actionData['action_url']
                            ];
                            break;
                        }

                        // Spustit PHP script
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
                            throw new Exception('Script nenalezen');
                        }
                    } else {
                        throw new Exception('Akce nemá definovaný action_url');
                    }
                    break;

                case 'enable_gzip':
                case 'browser_cache':
                    $executeResult = [
                        'success' => true,
                        'message' => 'Otevřete dokumentaci a proveďte změny ručně',
                        'action' => 'open_documentation',
                        'url' => $actionData['action_url'] ?? '/OPTIMIZATION_ANALYSIS.md'
                    ];
                    break;

                default:
                    throw new Exception("Neznámý typ akce: {$actionData['action_type']}");
            }
        } catch (Exception $e) {
            $executeResult = [
                'success' => false,
                'message' => 'Chyba při vykonávání: ' . $e->getMessage()
            ];
        }

        $executionTime = round((microtime(true) - $startTime) * 1000);

        if ($executeResult['success']) {
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

            echo json_encode([
                'status' => 'success',
                'message' => $executeResult['message'],
                'execution_time' => $executionTime . 'ms'
            ]);
        } else {
            $stmt = $pdo->prepare("UPDATE wgs_pending_actions SET status = 'failed' WHERE id = :action_id");
            $stmt->execute(['action_id' => $actionId]);

            throw new Exception($executeResult['message']);
        }
        break;

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

    case 'add_optimization_tasks':
        $tasks = [
            [
                'action_title' => '🗜️ Minifikovat JS/CSS soubory',
                'action_description' => 'Spustit /minify_assets.php pro optimalizaci rychlosti',
                'action_type' => 'optimize_assets',
                'action_url' => '/minify_assets.php',
                'priority' => 'high'
            ],
            [
                'action_title' => '📊 Přidat chybějící DB indexy',
                'action_description' => 'Spustit /add_indexes.php pro přidání 21 indexů',
                'action_type' => 'add_db_indexes',
                'action_url' => '/add_indexes.php',
                'priority' => 'high'
            ],
            [
                'action_title' => '💾 Vytvořit první backup',
                'action_description' => 'Spustit /backup_system.php',
                'action_type' => 'create_backup',
                'action_url' => '/backup_system.php',
                'priority' => 'medium'
            ],
            [
                'action_title' => '🧹 Vyčistit selhavší emaily',
                'action_description' => 'Spustit /cleanup_failed_emails.php',
                'action_type' => 'cleanup_emails',
                'action_url' => '/cleanup_failed_emails.php',
                'priority' => 'low'
            ],
            [
                'action_title' => '⚙️ Povolit Gzip kompresi',
                'action_description' => 'Přidat Gzip do .htaccess',
                'action_type' => 'enable_gzip',
                'action_url' => '/OPTIMIZATION_ANALYSIS.md',
                'priority' => 'high'
            ],
            [
                'action_title' => '📦 Nastavit Browser Cache',
                'action_description' => 'Přidat cache headers do .htaccess',
                'action_type' => 'browser_cache',
                'action_url' => '/OPTIMIZATION_ANALYSIS.md',
                'priority' => 'high'
            ]
        ];

        $added = 0;
        $skipped = 0;

        foreach ($tasks as $task) {
            $stmt = $pdo->prepare("
                SELECT id FROM wgs_pending_actions
                WHERE action_type = ? AND status IN ('pending', 'in_progress')
            ");
            $stmt->execute([$task['action_type']]);

            if ($stmt->rowCount() > 0) {
                $skipped++;
                continue;
            }

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO wgs_pending_actions (
                        action_title, action_description, action_type,
                        action_url, priority, status, created_at
                    ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ");

                $stmt->execute([
                    $task['action_title'],
                    $task['action_description'],
                    $task['action_type'],
                    $task['action_url'],
                    $task['priority']
                ]);

                $added++;
            } catch (PDOException $e) {
                continue;
            }
        }

        echo json_encode([
            'status' => 'success',
            'message' => "Přidáno: {$added} úkolů, Přeskočeno: {$skipped} úkolů",
            'data' => ['added' => $added, 'skipped' => $skipped]
        ]);
        break;

    default:
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Unknown actions action: {$action}"
        ]);
}
