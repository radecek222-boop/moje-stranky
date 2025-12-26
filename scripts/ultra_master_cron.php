<?php
/**
 * Ultra Master Cron Job - ALL Analytics Operations
 *
 * Tento skript kombinuje VŠECHNY Analytics cron joby do jednoho
 * kvůli limitu 5 webcronů na hostingu.
 *
 * Spouští se: Denně v 02:00
 * Cron: 0 2 * * * php /path/to/scripts/ultra_master_cron.php
 *
 * Co dělá:
 * 1. Cleanup replay frames (30 dní TTL) - Daily
 * 2. Cleanup geo cache (3 dny TTL) - Daily
 * 3. Recalculate user scores - Daily (KROMĚ neděle)
 * 4. Campaign stats aggregation - Daily
 * 5. Generate AI reports - Daily
 * 6. GDPR retention policy (730 dní) - Weekly (pouze v neděli)
 * 7. Notifikace nepřečtených poznámek - Daily
 *
 * @version 1.1.0
 * @date 2025-11-24
 * @module Ultra Cron Consolidation
 */

// Absolutní cesta k root složce
$rootDir = dirname(__DIR__);

// Načíst firemní konfiguraci
require_once $rootDir . '/includes/company_config.php';

// ========================================
// KONFIGURACE
// ========================================
$config = [
    'log_file' => $rootDir . '/logs/cron_ultra_master.log',
    'email_on_error' => false,
    'admin_email' => 'radek@wgs-service.cz',
];

// ========================================
// LOGGING FUNKCE
// ========================================
function logMessage($message, $level = 'INFO') {
    global $config;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] [{$level}] {$message}\n";
    echo $logLine;
    file_put_contents($config['log_file'], $logLine, FILE_APPEND);
}

function logError($message) {
    logMessage($message, 'ERROR');
}

function logSuccess($message) {
    logMessage($message, 'SUCCESS');
}

// ========================================
// EMAIL NOTIFICATION
// ========================================
function sendErrorEmail($subject, $body) {
    global $config;
    if (!$config['email_on_error']) return;
    $headers = "From: WGS Cron <" . WGS_EMAIL_REKLAMACE . ">\r\n";
    mail($config['admin_email'], $subject, $body, $headers);
}

// ========================================
// SPUŠTĚNÍ SKRIPTU
// ========================================
function executeScript($scriptPath, $scriptName) {
    global $rootDir;
    logMessage("========================================");
    logMessage("Spouštím: {$scriptName}");
    logMessage("========================================");

    $fullPath = $rootDir . '/' . $scriptPath;
    if (!file_exists($fullPath)) {
        logError("Skript nenalezen: {$fullPath}");
        return false;
    }

    $startTime = microtime(true);
    ob_start();
    try {
        include $fullPath;
        $output = ob_get_clean();
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        logSuccess("Skript dokončen: {$scriptName} (trvání: {$duration}s)");
        if (!empty(trim($output))) {
            logMessage("Output:\n" . $output);
        }
        return true;
    } catch (Exception $e) {
        ob_end_clean();
        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);
        logError("Skript selhal: {$scriptName} (trvání: {$duration}s)");
        logError("Chyba: " . $e->getMessage());
        sendErrorEmail("Ultra Master Cron Failed: {$scriptName}", "Chyba: {$e->getMessage()}\nTrvání: {$duration}s");
        return false;
    }
}

// ========================================
// START
// ========================================
logMessage("==========================================");
logMessage("ULTRA MASTER CRON JOB - START");
logMessage("==========================================");
logMessage("Datum: " . date('Y-m-d H:i:s'));
logMessage("Den v týdnu: " . date('l'));

$totalStartTime = microtime(true);
$successCount = 0;
$errorCount = 0;
$isNedele = (date('w') == 0);

// ========================================
// JOB #1: Cleanup Old Replay Frames
// ========================================
if (executeScript('scripts/cleanup_old_replay_frames.php', 'Cleanup Replay Frames')) {
    $successCount++;
} else {
    $errorCount++;
}
sleep(1);

// ========================================
// JOB #2: Cleanup Geo Cache
// ========================================
if (executeScript('scripts/cleanup_geo_cache.php', 'Cleanup Geo Cache')) {
    $successCount++;
} else {
    $errorCount++;
}
sleep(1);

// ========================================
// JOB #3: Recalculate User Scores (KROMĚ neděle)
// ========================================
if (!$isNedele) {
    if (executeScript('scripts/recalculate_user_scores.php', 'Recalculate User Scores')) {
        $successCount++;
    } else {
        $errorCount++;
    }
    sleep(1);
}

// ========================================
// JOB #4: Campaign Stats Aggregation (denně)
// ========================================
if (executeScript('scripts/aggregate_campaign_stats.php', 'Campaign Stats Aggregation')) {
    $successCount++;
} else {
    $errorCount++;
}
sleep(1);

// ========================================
// JOB #5: Generate Scheduled Reports (denně)
// ========================================
if (executeScript('scripts/generate_scheduled_reports.php', 'Generate Scheduled Reports')) {
    $successCount++;
} else {
    $errorCount++;
}
sleep(1);

// ========================================
// JOB #6: GDPR Retention Policy (pouze v neděli)
// ========================================
if ($isNedele) {
    logMessage("----------------------------------------");
    logMessage("NEDĚLE DETEKOVÁNA - Spouštím GDPR Retention Policy");
    logMessage("----------------------------------------");
    if (executeScript('scripts/apply_retention_policy.php', 'GDPR Retention Policy')) {
        $successCount++;
    } else {
        $errorCount++;
    }
}

// ========================================
// JOB #7: Notifikace nepřečtených poznámek (denně)
// ========================================
if (executeScript('scripts/notifikovat_neprecte_poznamky.php', 'Notifikace nepřečtených poznámek')) {
    $successCount++;
} else {
    $errorCount++;
}
sleep(1);

// ========================================
// SUMMARY
// ========================================
$totalEndTime = microtime(true);
$totalDuration = round($totalEndTime - $totalStartTime, 2);

logMessage("==========================================");
logMessage("ULTRA MASTER CRON JOB - SUMMARY");
logMessage("==========================================");
logMessage("Úspěšné joby: {$successCount}");
logMessage("Neúspěšné joby: {$errorCount}");
logMessage("Celkové trvání: {$totalDuration}s");
logMessage("==========================================");

if ($errorCount > 0) {
    logError("ULTRA MASTER CRON DOKONČEN S CHYBAMI ");
    sendErrorEmail(
        "Ultra Master Cron - Dokončeno s chybami ({$errorCount} errors)",
        "Úspěšné joby: {$successCount}\nNeúspěšné joby: {$errorCount}\nTrvání: {$totalDuration}s\n\nKontrolujte log: {$config['log_file']}"
    );
    exit(1);
} else {
    logSuccess("ULTRA MASTER CRON DOKONČEN ÚSPĚŠNĚ ");
    exit(0);
}
?>
