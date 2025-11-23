<?php
/**
 * Aggregate Campaign Stats - Denní agregace UTM campaign metrik
 *
 * Cron job pro agregaci session dat do campaign statistik.
 * Spouštět denně v 01:00.
 *
 * URL: https://www.wgs-service.cz/scripts/aggregate_campaign_stats.php
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #8 - UTM Campaign Tracking
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/CampaignAttribution.php';

// Bezpečnostní kontrola - pouze admin nebo cron
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$isCron = php_sapi_name() === 'cli' || (isset($_SERVER['HTTP_USER_AGENT']) && strpos($_SERVER['HTTP_USER_AGENT'], 'cron') !== false);

if (!$isAdmin && !$isCron) {
    die("PŘÍSTUP ODEPŘEN: Tento skript může spustit pouze administrátor nebo cron job.");
}

// HTML výstup pro web, plain text pro CLI
$isWeb = !$isCron;

if ($isWeb) {
    echo "<!DOCTYPE html>
    <html lang='cs'>
    <head>
        <meta charset='UTF-8'>
        <title>Campaign Stats Aggregation</title>
        <style>
            body { font-family: monospace; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
            .success { color: #4ec9b0; }
            .error { color: #f48771; }
            .info { color: #569cd6; }
            .timestamp { color: #858585; }
        </style>
    </head>
    <body>";
}

try {
    log_message('📊 Campaign Stats Aggregation - START', 'info');

    $pdo = getDbConnection();
    $campaignAttribution = new CampaignAttribution($pdo);

    // ========================================
    // PARAMETRY
    // ========================================

    // Pokud je zadáno date v URL, použít ho, jinak včerejší den
    $date = $_GET['date'] ?? date('Y-m-d', strtotime('-1 day'));

    // Validace data
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        throw new Exception("Neplatný formát data: {$date}");
    }

    log_message("Datum agregace: {$date}", 'info');

    // ========================================
    // AGREGACE CAMPAIGN STATISTIK
    // ========================================

    log_message('Spouštím agregaci campaign statistik...', 'info');

    $result = $campaignAttribution->agregujDenniStatistiky($date);

    log_message("✅ Agregace dokončena:", 'success');
    log_message("  - Datum: {$result['date']}", 'success');
    log_message("  - Zpracováno kampaní: {$result['campaigns_processed']}", 'success');
    log_message("  - Agregováno řádků: {$result['agregated_rows']}", 'success');

    // ========================================
    // STATISTIKY VÝSLEDKU
    // ========================================

    // Celkový počet záznamů v tabulce
    $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_analytics_utm_campaigns");
    $totalRecords = $stmt->fetchColumn();

    log_message("📈 Celková statistika:", 'info');
    log_message("  - Celkem záznamů v DB: {$totalRecords}", 'info');

    // Počet kampaní za poslední 30 dní
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT CONCAT(utm_source, '|', utm_medium, '|', utm_campaign))
        FROM wgs_analytics_utm_campaigns
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $campaigns30Days = $stmt->fetchColumn();

    log_message("  - Unikátních kampaní (30 dní): {$campaigns30Days}", 'info');

    // Top 5 kampaní podle sessions
    $stmt = $pdo->prepare("
        SELECT
            utm_campaign,
            SUM(sessions_count) as total_sessions
        FROM wgs_analytics_utm_campaigns
        WHERE date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
          AND utm_campaign IS NOT NULL
        GROUP BY utm_campaign
        ORDER BY total_sessions DESC
        LIMIT 5
    ");
    $stmt->execute();
    $topCampaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    log_message("🏆 Top 5 kampaní (30 dní):", 'info');
    foreach ($topCampaigns as $i => $campaign) {
        log_message("  " . ($i + 1) . ". {$campaign['utm_campaign']}: {$campaign['total_sessions']} sessions", 'info');
    }

    // ========================================
    // CLEANUP STARÝCH DAT (optional)
    // ========================================

    // Smazat data starší než 365 dní
    $deleteThreshold = date('Y-m-d', strtotime('-365 days'));

    $stmt = $pdo->prepare("DELETE FROM wgs_analytics_utm_campaigns WHERE date < :threshold");
    $stmt->execute(['threshold' => $deleteThreshold]);
    $deletedRows = $stmt->rowCount();

    if ($deletedRows > 0) {
        log_message("🗑️  Smazáno starých záznamů (> 365 dní): {$deletedRows}", 'info');
    }

    // ========================================
    // KONEC
    // ========================================

    log_message('📊 Campaign Stats Aggregation - DOKONČENO', 'success');

} catch (PDOException $e) {
    log_message('❌ DATABASE ERROR: ' . $e->getMessage(), 'error');
    error_log('Campaign Stats Aggregation Error: ' . $e->getMessage());
    error_log('Stack trace: ' . $e->getTraceAsString());

} catch (Exception $e) {
    log_message('❌ ERROR: ' . $e->getMessage(), 'error');
    error_log('Campaign Stats Aggregation Unexpected Error: ' . $e->getMessage());
}

if ($isWeb) {
    echo "</body></html>";
}

/**
 * Logování zpráv
 *
 * @param string $message
 * @param string $type 'info', 'success', 'error'
 */
function log_message($message, $type = 'info')
{
    global $isWeb;

    $timestamp = date('Y-m-d H:i:s');

    if ($isWeb) {
        $class = $type;
        echo "<div class='{$class}'>";
        echo "<span class='timestamp'>[{$timestamp}]</span> ";
        echo htmlspecialchars($message);
        echo "</div>\n";
    } else {
        echo "[{$timestamp}] {$message}\n";
    }
}
?>
