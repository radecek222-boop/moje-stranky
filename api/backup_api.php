<?php
/**
 * Backup API
 * Automatická záloha databáze
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json');

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die(json_encode(['status' => 'error', 'message' => 'Unauthorized']));
}

$action = $_GET['action'] ?? '';

// BEZPEČNOST: CSRF ochrana pro POST operace
$postActions = ['create_backup', 'delete_backup', 'cleanup_old_backups'];
if (in_array($action, $postActions, true)) {
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
        case 'create_backup':
            $startTime = microtime(true);

            // Database name z konstanty
            $dbName = DB_NAME;

            // Vytvořit backups adresář pokud neexistuje
            $backupDir = __DIR__ . '/../backups';
            if (!is_dir($backupDir)) {
                mkdir($backupDir, 0755, true);
            }

            // Název souboru s časovou značkou
            $timestamp = date('Y-m-d_H-i-s');
            $filename = "backup_{$dbName}_{$timestamp}.sql";
            $filepath = $backupDir . '/' . $filename;

            // Získat všechny tabulky
            $stmt = $pdo->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $output = "-- Database Backup\n";
            $output .= "-- Database: $dbName\n";
            $output .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $output .= "-- ==========================================\n\n";

            $output .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
            $output .= "SET time_zone = \"+00:00\";\n\n";

            $totalRows = 0;

            foreach ($tables as $table) {
                // DROP TABLE IF EXISTS
                $output .= "\n-- ==========================================\n";
                $output .= "-- Table: $table\n";
                $output .= "-- ==========================================\n\n";
                $output .= "DROP TABLE IF EXISTS `$table`;\n\n";

                // CREATE TABLE
                $createStmt = $pdo->query("SHOW CREATE TABLE `$table`");
                $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
                $output .= $createRow['Create Table'] . ";\n\n";

                // INSERT DATA
                $dataStmt = $pdo->query("SELECT * FROM `$table`");
                $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
                $rowCount = count($rows);

                if ($rowCount > 0) {
                    $columns = array_keys($rows[0]);
                    $output .= "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES\n";

                    foreach ($rows as $index => $row) {
                        $values = array_map(function($value) use ($pdo) {
                            if ($value === null) {
                                return 'NULL';
                            }
                            return $pdo->quote($value);
                        }, array_values($row));

                        $output .= "(" . implode(', ', $values) . ")";

                        if ($index < $rowCount - 1) {
                            $output .= ",\n";
                        } else {
                            $output .= ";\n\n";
                        }

                        $totalRows++;
                    }
                } else {
                    $output .= "-- No data for table `$table`\n\n";
                }
            }

            // Zapsat do souboru
            $written = file_put_contents($filepath, $output);

            if ($written === false) {
                throw new Exception('Failed to write backup file');
            }

            // Zkomprimovat
            $gzFilepath = $filepath . '.gz';
            $gzFile = gzopen($gzFilepath, 'w9');
            gzwrite($gzFile, $output);
            gzclose($gzFile);

            // Smazat nekomprimovaný soubor
            unlink($filepath);

            $endTime = microtime(true);
            $executionTime = round(($endTime - $startTime) * 1000, 2);

            $fileSize = filesize($gzFilepath);
            $fileSizeMB = round($fileSize / 1024 / 1024, 2);

            echo json_encode([
                'status' => 'success',
                'message' => 'Backup created successfully',
                'data' => [
                    'filename' => basename($gzFilepath),
                    'filepath' => $gzFilepath,
                    'size' => $fileSizeMB . ' MB',
                    'tables' => count($tables),
                    'rows' => $totalRows,
                    'execution_time_ms' => $executionTime
                ]
            ]);
            break;

        case 'list_backups':
            $backupDir = __DIR__ . '/../backups';

            if (!is_dir($backupDir)) {
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'backups' => []
                    ]
                ]);
                break;
            }

            $backups = glob($backupDir . '/*.sql*');
            $backupList = [];

            foreach ($backups as $backup) {
                $backupList[] = [
                    'filename' => basename($backup),
                    'size' => round(filesize($backup) / 1024 / 1024, 2) . ' MB',
                    'created' => date('Y-m-d H:i:s', filemtime($backup)),
                    'age_days' => floor((time() - filemtime($backup)) / 86400)
                ];
            }

            // Seřadit podle data (nejnovější první)
            usort($backupList, function($a, $b) {
                return strtotime($b['created']) - strtotime($a['created']);
            });

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'backups' => $backupList,
                    'total' => count($backupList)
                ]
            ]);
            break;

        case 'delete_backup':
            $filename = $_POST['filename'] ?? '';

            if (empty($filename)) {
                throw new Exception('Filename not specified');
            }

            // Bezpečnostní kontrola - pouze soubory v backups adresáři
            if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
                throw new Exception('Invalid filename');
            }

            $backupDir = __DIR__ . '/../backups';
            $filepath = $backupDir . '/' . $filename;

            if (!file_exists($filepath)) {
                throw new Exception('Backup file not found');
            }

            unlink($filepath);

            echo json_encode([
                'status' => 'success',
                'message' => 'Backup deleted successfully'
            ]);
            break;

        case 'download_backup':
            $filename = $_GET['filename'] ?? '';

            if (empty($filename)) {
                throw new Exception('Filename not specified');
            }

            // Bezpečnostní kontrola
            if (strpos($filename, '..') !== false || strpos($filename, '/') !== false) {
                throw new Exception('Invalid filename');
            }

            $backupDir = __DIR__ . '/../backups';
            $filepath = $backupDir . '/' . $filename;

            if (!file_exists($filepath)) {
                throw new Exception('Backup file not found');
            }

            header('Content-Type: application/gzip');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filepath));
            readfile($filepath);
            exit;

        case 'cleanup_old_backups':
            $days = $_POST['days'] ?? 30;

            $backupDir = __DIR__ . '/../backups';

            if (!is_dir($backupDir)) {
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'deleted' => 0
                    ]
                ]);
                break;
            }

            $backups = glob($backupDir . '/*.sql*');
            $deleted = 0;

            foreach ($backups as $backup) {
                $age = floor((time() - filemtime($backup)) / 86400);
                if ($age > $days) {
                    unlink($backup);
                    $deleted++;
                }
            }

            echo json_encode([
                'status' => 'success',
                'message' => "Deleted $deleted old backups (older than $days days)",
                'data' => [
                    'deleted' => $deleted
                ]
            ]);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
