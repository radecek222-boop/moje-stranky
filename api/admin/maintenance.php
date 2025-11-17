<?php
/**
 * Admin API - Maintenance Module
 * Zpracování údržbových operací (cache, logy, optimalizace)
 * Extrahováno z control_center_api.php
 */

// Tento soubor je načítán přes api/admin.php router
// Proměnné $pdo, $data, $action jsou již k dispozici

switch ($action) {
    case 'clear_cache':
        $tempPath = __DIR__ . '/../../temp';
        $cachePath = __DIR__ . '/../../cache';

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

    case 'cleanup_logs':
        $results = [];
        $logsDir = __DIR__ . '/../../logs';

        // 1. Smazat .gz a archivované logy
        $deletedFiles = 0;
        $gzFiles = glob($logsDir . '/*.gz');
        foreach ($gzFiles as $file) {
            if (unlink($file)) $deletedFiles++;
        }
        $archivedLogs = glob($logsDir . '/*.20*.log');
        foreach ($archivedLogs as $file) {
            if (basename($file) !== 'php_errors.log' && unlink($file)) $deletedFiles++;
        }
        $results['deleted_files'] = $deletedFiles;

        // 2. Smazat php_errors.log
        $errorLog = $logsDir . '/php_errors.log';
        if (file_exists($errorLog)) {
            unlink($errorLog);
            $results['log_deleted'] = true;
        } else {
            $results['log_deleted'] = false;
        }

        // 3. Vymazat cache
        $cacheDir = __DIR__ . '/../../temp/cache';
        $cacheDeleted = 0;
        if (is_dir($cacheDir)) {
            foreach (glob($cacheDir . '/*') as $file) {
                if (is_file($file) && unlink($file)) $cacheDeleted++;
            }
        }
        $results['cache_deleted'] = $cacheDeleted;

        // 4. Vytvořit backup adresáře
        $backupDirs = ['backups', 'backups/daily', 'backups/weekly', 'backups/monthly'];
        foreach ($backupDirs as $dir) {
            $fullPath = __DIR__ . '/../../' . $dir;
            if (!is_dir($fullPath)) mkdir($fullPath, 0755, true);
        }

        // 5. Backup check
        $dailyBackups = glob(__DIR__ . '/../../backups/daily/*.sql.gz');
        if (empty($dailyBackups)) {
            $results['backup_exists'] = false;
            $results['backup_note'] = 'Nastavte cron: 0 2 * * * /path/to/backup-database.sh';
        } else {
            $results['backup_exists'] = true;
            $results['backup_file'] = basename(end($dailyBackups));
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Cleanup completed',
            'results' => $results
        ]);
        break;

    case 'archive_logs':
        $logsPath = __DIR__ . '/../../logs';
        $archivePath = __DIR__ . '/../../logs/archive';

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
                // Přeskočit pokud tabulka nelze optimalizovat
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

    default:
        http_response_code(404);
        echo json_encode([
            'status' => 'error',
            'message' => "Unknown maintenance action: {$action}"
        ]);
}
