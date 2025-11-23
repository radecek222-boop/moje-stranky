<?php
/**
 * Cleanup Real-time Sessions - Cron Job
 *
 * Odstraní expirované sessions z wgs_analytics_realtime tabulky.
 * Sessions expirují po 5 minutách neaktivity.
 *
 * Spouštět: Každých 15 minut (hosting limit, ideálně každých 5 minut)
 * Cron: */15 * * * * php /path/to/scripts/cleanup_realtime_sessions.php
 *
 * @version 1.0.1
 * @date 2025-11-23
 * @module Module #11 - Real-time Dashboard
 */

require_once __DIR__ . '/../init.php';

// ========================================
// KONFIGURACE
// ========================================
$debug = true; // Výpis do konzole

// ========================================
// SPUŠTĚNÍ
// ========================================
echo "==========================================\n";
echo "CLEANUP REALTIME SESSIONS - START\n";
echo "==========================================\n";
echo "Datum: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $pdo = getDbConnection();

    // ========================================
    // 1. ODSTRANIT EXPIROVANÉ SESSIONS
    // ========================================
    echo "1️⃣  Odstraňujem expirované sessions...\n";

    $sql = "DELETE FROM wgs_analytics_realtime WHERE expires_at < NOW()";
    $deletedCount = $pdo->exec($sql);

    echo "Odstraněno: {$deletedCount} expirovaných sessions\n\n";

    // ========================================
    // 2. AKTUALIZOVAT EXPIRES_AT PRO AKTIVNÍ SESSIONS
    // ========================================
    echo "2️⃣  Aktualizuji expires_at pro aktivní sessions...\n";

    // Sessions, které měly aktivitu za posledních 5 minut
    $sql = "
    UPDATE wgs_analytics_realtime
    SET expires_at = DATE_ADD(last_activity_at, INTERVAL 5 MINUTE)
    WHERE last_activity_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    AND is_active = 1
    ";
    $updatedCount = $pdo->exec($sql);

    echo "Aktualizováno: {$updatedCount} aktivních sessions\n\n";

    // ========================================
    // 3. OZNAČIT NEAKTIVNÍ SESSIONS
    // ========================================
    echo "3️⃣  Označuji neaktivní sessions...\n";

    $sql = "
    UPDATE wgs_analytics_realtime
    SET is_active = 0
    WHERE last_activity_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)
    AND is_active = 1
    ";
    $inactiveCount = $pdo->exec($sql);

    echo "Označeno jako neaktivní: {$inactiveCount} sessions\n\n";

    // ========================================
    // 4. STATISTIKY
    // ========================================
    echo "4️⃣  Finální statistiky...\n";

    // Celkem aktivních sessions
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM wgs_analytics_realtime
        WHERE is_active = 1
        AND expires_at > NOW()
    ");
    $activeSessions = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    // Lidé vs boti
    $stmt = $pdo->query("
        SELECT
            SUM(CASE WHEN is_bot = 0 THEN 1 ELSE 0 END) as humans,
            SUM(CASE WHEN is_bot = 1 THEN 1 ELSE 0 END) as bots
        FROM wgs_analytics_realtime
        WHERE is_active = 1
        AND expires_at > NOW()
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "Aktivní sessions: {$activeSessions}\n";
    echo "Lidé: " . ($stats['humans'] ?: 0) . "\n";
    echo "Boti: " . ($stats['bots'] ?: 0) . "\n\n";

    echo "==========================================\n";
    echo "CLEANUP REALTIME SESSIONS - DOKONČENO ✅\n";
    echo "==========================================\n";
    echo "Konec: " . date('Y-m-d H:i:s') . "\n";

} catch (PDOException $e) {
    echo "\n";
    echo "==========================================\n";
    echo "CHYBA DATABÁZE ❌\n";
    echo "==========================================\n";
    echo $e->getMessage() . "\n";
    exit(1);

} catch (Exception $e) {
    echo "\n";
    echo "==========================================\n";
    echo "NEOČEKÁVANÁ CHYBA ❌\n";
    echo "==========================================\n";
    echo $e->getMessage() . "\n";
    exit(1);
}
?>
