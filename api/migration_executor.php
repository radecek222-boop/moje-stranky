<?php
/**
 * Migration Executor API
 * Bezpečné spuštění SQL migrací
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json');

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// BEZPEČNOST: CSRF ochrana pro POST operace (run_migration je nebezpečná operace)
if ($action === 'run_migration') {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (is_array($csrfToken)) {
        $csrfToken = '';
    }
    if (!validateCSRFToken($csrfToken)) {
        http_response_code(403);
        die(json_encode([
            'status' => 'error',
            'message' => 'Neplatný bezpečnostní token. Obnovte stránku a zkuste to znovu.'
        ]));
    }
}

try {
    // Vytvořit PDO připojení
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    switch ($action) {
        case 'run_migration':
            $migrationFile = $_POST['migration_file'] ?? '';

            if (empty($migrationFile)) {
                throw new Exception('Migration file not specified');
            }

            // Bezpečnostní kontrola - pouze povolené migrace
            $allowedMigrations = [
                'migration_admin_control_center.sql'
            ];

            if (!in_array($migrationFile, $allowedMigrations)) {
                throw new Exception('Migration file not allowed');
            }

            $migrationPath = __DIR__ . '/../' . $migrationFile;

            if (!file_exists($migrationPath)) {
                throw new Exception("Migration file not found: $migrationFile");
            }

            // Načíst SQL obsah
            $sql = file_get_contents($migrationPath);

            if ($sql === false) {
                throw new Exception('Failed to read migration file');
            }

            // Log začátku migrace
            $startTime = microtime(true);
            $executedStatements = 0;
            $results = [];

            // Rozdělit SQL na jednotlivé příkazy
            $statements = array_filter(
                array_map('trim', explode(';', $sql)),
                function($stmt) {
                    return !empty($stmt) && !preg_match('/^--/', $stmt);
                }
            );

            // Spustit v transakci
            $pdo->beginTransaction();

            try {
                foreach ($statements as $statement) {
                    // Přeskočit komentáře a prázdné řádky
                    if (empty(trim($statement)) || preg_match('/^--/', trim($statement))) {
                        continue;
                    }

                    $stmt = $pdo->prepare($statement);
                    $stmt->execute();
                    $executedStatements++;

                    // Zachytit výsledky SELECT statements
                    if (preg_match('/^SELECT/i', trim($statement))) {
                        $results[] = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    }
                }

                $pdo->commit();

                $endTime = microtime(true);
                $executionTime = round(($endTime - $startTime) * 1000, 2);

                // Ověřit, že tabulky byly vytvořeny
                $tables = [
                    'wgs_theme_settings',
                    'wgs_content_texts',
                    'wgs_system_config',
                    'wgs_pending_actions',
                    'wgs_action_history',
                    'wgs_github_webhooks'
                ];

                $createdTables = [];
                foreach ($tables as $table) {
                    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                    if ($stmt->rowCount() > 0) {
                        $createdTables[] = $table;

                        // Zjistit počet záznamů
                        $countStmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                        $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
                        $results[] = [
                            'table' => $table,
                            'status' => 'created',
                            'rows' => $count
                        ];
                    }
                }

                echo json_encode([
                    'status' => 'success',
                    'message' => 'Migration completed successfully',
                    'data' => [
                        'migration_file' => $migrationFile,
                        'statements_executed' => $executedStatements,
                        'execution_time_ms' => $executionTime,
                        'tables_created' => count($createdTables),
                        'tables' => $createdTables,
                        'details' => $results
                    ]
                ]);

            } catch (Exception $e) {
                $pdo->rollBack();
                throw new Exception('Migration failed: ' . $e->getMessage());
            }

            break;

        case 'check_migration_status':
            // Zkontrolovat, zda jsou tabulky již vytvořeny
            $tables = [
                'wgs_theme_settings',
                'wgs_content_texts',
                'wgs_system_config',
                'wgs_pending_actions',
                'wgs_action_history',
                'wgs_github_webhooks'
            ];

            $status = [];
            foreach ($tables as $table) {
                $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
                $exists = $stmt->rowCount() > 0;

                $rowCount = 0;
                if ($exists) {
                    $countStmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
                    $rowCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
                }

                $status[] = [
                    'table' => $table,
                    'exists' => $exists,
                    'rows' => $rowCount
                ];
            }

            $allTablesExist = count(array_filter($status, function($s) {
                return $s['exists'];
            })) === count($tables);

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'migration_needed' => !$allTablesExist,
                    'tables_status' => $status
                ]
            ]);

            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
