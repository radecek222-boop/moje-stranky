<?php
/**
 * Master Cron Job - Unified Daily Cleanup & Maintenance
 *
 * Tento skript kombinuje několik cron jobů do jednoho pro splnění limitu 5 webcronů.
 *
 * Spouští se: Denně v 02:00
 * Cron: 0 2 * * * php /path/to/scripts/master_cron.php
 *
 * Co dělá:
 * 1. Cleanup replay frames (30 dní TTL) - Daily
 * 2. Cleanup geo cache (3 dny TTL) - Daily
 * 3. Recalculate user scores - Daily
 * 4. GDPR retention policy (730 dní) - Weekly (pouze v neděli)
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Cron Consolidation
 */

// Absolutní cesta k root složce
$rootDir = dirname(__DIR__);

// Načíst firemní konfiguraci
require_once $rootDir . '/includes/company_config.php';

// ========================================
// KONFIGURACE
// ========================================
$config = [
    'log_file' => $rootDir . '/logs/cron_master.log',
    'email_on_error' => false, // Nastavit na true pro email notifikace
    'admin_email' => 'radek@wgs-service.cz',
];

// ========================================
// LOGGING FUNKCE
// ========================================
function logMessage($message, $level = 'INFO') {
    global $config;

    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[{$timestamp}] [{$level}] {$message}\n";

    // Výpis do konzole
    echo $logLine;

    // Zápis do logu
    file_put_contents($config['log_file'], $logLine, FILE_APPEND);
}

function logError($message) {
    logMessage($message, 'ERROR');
}

function logSuccess($message) {
    logMessage($message, 'SUCCESS');
}

function logWarning($message) {
    logMessage($message, 'WARNING');
}

// ========================================
// EMAIL NOTIFICATION
// ========================================
function sendErrorEmail($subject, $body) {
    global $config;

    if (!$config['email_on_error']) {
        return;
    }

    $headers = "From: WGS Cron <" . WGS_EMAIL_REKLAMACE . ">\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

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

    // Spustit skript a zachytit output
    ob_start();
    try {
        include $fullPath;
        $output = ob_get_clean();

        $endTime = microtime(true);
        $duration = round($endTime - $startTime, 2);

        logSuccess("Skript dokončen: {$scriptName} (trvání: {$duration}s)");

        // Logovat output skriptu (pouze pokud není prázdný)
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

        // Odeslat email s chybou
        sendErrorEmail(
            "Master Cron Failed: {$scriptName}",
            "Skript: {$scriptName}\nChyba: {$e->getMessage()}\nTrvání: {$duration}s"
        );

        return false;
    }
}

// ========================================
// START
// ========================================

logMessage("==========================================");
logMessage("MASTER CRON JOB - START");
logMessage("==========================================");
logMessage("Datum: " . date('Y-m-d H:i:s'));
logMessage("Den v týdnu: " . date('l'));

$totalStartTime = microtime(true);
$successCount = 0;
$errorCount = 0;

// ========================================
// JOB #1: Cleanup Old Replay Frames (30 dní TTL)
// ========================================
if (executeScript('scripts/cleanup_old_replay_frames.php', 'Cleanup Replay Frames')) {
    $successCount++;
} else {
    $errorCount++;
}

sleep(1); // Pause mezi skripty

// ========================================
// JOB #2: Cleanup Geo Cache (3 dny TTL)
// ========================================
if (executeScript('scripts/cleanup_geo_cache.php', 'Cleanup Geo Cache')) {
    $successCount++;
} else {
    $errorCount++;
}

sleep(1);

// ========================================
// JOB #3: Recalculate User Scores (denně, KROMĚ neděle)
// ========================================
// V neděli se místo scores spustí retention policy
$isNedele = (date('w') == 0); // 0 = neděle

if (!$isNedele) {
    if (executeScript('scripts/recalculate_user_scores.php', 'Recalculate User Scores')) {
        $successCount++;
    } else {
        $errorCount++;
    }

    sleep(1);
}

// ========================================
// JOB #4: GDPR Retention Policy (pouze v neděli)
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
// SUMMARY
// ========================================
$totalEndTime = microtime(true);
$totalDuration = round($totalEndTime - $totalStartTime, 2);

logMessage("==========================================");
logMessage("MASTER CRON JOB - SUMMARY");
logMessage("==========================================");
logMessage("Úspěšné joby: {$successCount}");
logMessage("Neúspěšné joby: {$errorCount}");
logMessage("Celkové trvání: {$totalDuration}s");
logMessage("==========================================");

if ($errorCount > 0) {
    logError("MASTER CRON DOKONČEN S CHYBAMI ");

    // Odeslat summary email s chybami
    sendErrorEmail(
        "Master Cron - Dokončeno s chybami ({$errorCount} errors)",
        "Úspěšné joby: {$successCount}\nNeúspěšné joby: {$errorCount}\nTrvání: {$totalDuration}s\n\nKontrolujte log: {$config['log_file']}"
    );

    exit(1);
} else {
    logSuccess("MASTER CRON DOKONČEN ÚSPĚŠNĚ ");
    exit(0);
}
?>
