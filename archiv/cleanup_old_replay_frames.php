<?php
/**
 * Cleanup Old Replay Frames - Cron Job
 *
 * Tento skript maže session replay frames starší než 30 dní (TTL policy).
 * Měl by běžet denně ve 2:00 AM pomocí webcronu.
 *
 * Webcron URL: https://www.wgs-service.cz/scripts/cleanup_old_replay_frames.php
 * Schedule: Daily 02:00
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #7 - Session Replay Engine
 */

require_once __DIR__ . '/../init.php';

// Bezpečnostní hlavičky
header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getDbConnection();

    // Logování startu
    $startTime = microtime(true);
    echo "[" . date('Y-m-d H:i:s') . "] Cleanup Old Replay Frames - START\n";

    // ========================================
    // MAZÁNÍ STARÝCH FRAMŮ (expires_at < NOW)
    // ========================================
    $stmt = $pdo->prepare("
        DELETE FROM wgs_analytics_replay_frames
        WHERE expires_at < NOW()
        LIMIT 10000
    ");

    $stmt->execute();
    $deletedRows = $stmt->rowCount();

    echo "[" . date('Y-m-d H:i:s') . "] Deleted {$deletedRows} expired replay frames\n";

    // ========================================
    // POKUD BYLO SMAZÁNO 10K ŘÁDKŮ, BĚŽET ZNOVU (chunking)
    // ========================================
    $totalDeleted = $deletedRows;

    while ($deletedRows === 10000) {
        $stmt->execute();
        $deletedRows = $stmt->rowCount();
        $totalDeleted += $deletedRows;

        echo "[" . date('Y-m-d H:i:s') . "] Deleted additional {$deletedRows} rows (total: {$totalDeleted})\n";

        // Prevent infinite loop
        if ($totalDeleted > 1000000) {
            echo "[" . date('Y-m-d H:i:s') . "] WARNING: Reached 1M deleted rows limit, stopping\n";
            break;
        }
    }

    // ========================================
    // STATISTIKY ZBÝVAJÍCÍCH FRAMŮ
    // ========================================
    $stmt = $pdo->query("
        SELECT
            COUNT(*) as total_frames,
            COUNT(DISTINCT session_id) as total_sessions,
            MIN(created_at) as oldest_frame,
            MAX(created_at) as newest_frame
        FROM wgs_analytics_replay_frames
    ");

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "\n--- STATISTICS ---\n";
    echo "Total replay frames: " . number_format($stats['total_frames']) . "\n";
    echo "Total sessions: " . number_format($stats['total_sessions']) . "\n";
    echo "Oldest frame: " . ($stats['oldest_frame'] ?? 'N/A') . "\n";
    echo "Newest frame: " . ($stats['newest_frame'] ?? 'N/A') . "\n";

    // ========================================
    // VÝPOČET VELIKOSTI TABULKY
    // ========================================
    $stmt = $pdo->query("
        SELECT
            ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
        FROM information_schema.TABLES
        WHERE table_schema = DATABASE()
          AND table_name = 'wgs_analytics_replay_frames'
    ");

    $tableSize = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "Table size: " . ($tableSize['size_mb'] ?? '0') . " MB\n";

    // ========================================
    // KONEC
    // ========================================
    $endTime = microtime(true);
    $executionTime = round($endTime - $startTime, 2);

    echo "\n[" . date('Y-m-d H:i:s') . "] Cleanup Old Replay Frames - COMPLETE\n";
    echo "Execution time: {$executionTime} seconds\n";
    echo "Total deleted: {$totalDeleted} frames\n";

    // Logování do souboru
    $logMessage = sprintf(
        "[%s] Cleanup Replay Frames: Deleted %d frames in %.2fs\n",
        date('Y-m-d H:i:s'),
        $totalDeleted,
        $executionTime
    );

    error_log($logMessage, 3, __DIR__ . '/../logs/cron.log');

} catch (PDOException $e) {
    $errorMsg = "[" . date('Y-m-d H:i:s') . "] DATABASE ERROR: " . $e->getMessage() . "\n";
    echo $errorMsg;
    error_log($errorMsg, 3, __DIR__ . '/../logs/cron_errors.log');
    exit(1);

} catch (Exception $e) {
    $errorMsg = "[" . date('Y-m-d H:i:s') . "] UNEXPECTED ERROR: " . $e->getMessage() . "\n";
    echo $errorMsg;
    error_log($errorMsg, 3, __DIR__ . '/../logs/cron_errors.log');
    exit(1);
}

exit(0);
?>
