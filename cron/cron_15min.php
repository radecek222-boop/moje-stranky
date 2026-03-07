#!/usr/bin/env php
<?php
/**
 * Konsolidovaný CRON - každých 15 minut
 *
 * Nahrazuje:
 *   - cron/process-email-queue.php
 *   - scripts/cleanup_realtime_sessions.php
 *
 * URL: https://www.wgs-service.cz/cron/cron_15min.php?key=KLIC
 */

$rootDir = dirname(__DIR__);
require_once $rootDir . '/includes/env_loader.php';

// === BEZPEČNOSTNÍ KONTROLA ===
if (php_sapi_name() !== 'cli') {
    $tajnyKlic = getenv('CRON_SECRET_KEY');
    if (!$tajnyKlic) {
        http_response_code(500);
        error_log("CRON cron_15min: CRON_SECRET_KEY není nastaven v .env");
        die('Chyba konfigurace: CRON_SECRET_KEY musí být nastaven v .env');
    }
    if (!isset($_GET['key']) || !hash_equals($tajnyKlic, $_GET['key'])) {
        http_response_code(403);
        error_log("CRON cron_15min: Neplatný klíč - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        die('Forbidden');
    }
}

set_time_limit(300);
header('Content-Type: text/plain; charset=utf-8');

$logFile = $rootDir . '/logs/cron_15min.log';
$tajnyKlic = getenv('CRON_SECRET_KEY') ?: '';
$zakladUrl = getenv('APP_URL') ?: 'https://www.wgs-service.cz';

function log15(string $zprava): void {
    global $logFile;
    $radek = '[' . date('Y-m-d H:i:s') . '] ' . $zprava . "\n";
    echo $radek;
    file_put_contents($logFile, $radek, FILE_APPEND);
}

function zavolej(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 120,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_USERAGENT      => 'WGS-Cron/1.0',
    ]);
    $vysledek  = curl_exec($ch);
    $httpKod   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlChyba = curl_error($ch);
    curl_close($ch);
    return ['kod' => $httpKod, 'body' => $vysledek, 'chyba' => $curlChyba];
}

log15('=== CRON 15MIN START ===');

// --- 1. Fronta emailů ---
log15('Spouštím: Fronta emailů');
$odpoved = zavolej($zakladUrl . '/cron/process-email-queue.php?key=' . urlencode($tajnyKlic));
if ($odpoved['chyba']) {
    log15('CHYBA (curl): ' . $odpoved['chyba']);
} elseif ($odpoved['kod'] !== 200) {
    log15('CHYBA HTTP ' . $odpoved['kod']);
} else {
    log15('OK - Fronta emailů dokončena');
}

sleep(2);

// --- 2. Cleanup expirovaných tokenů ---
log15('Spouštím: Cleanup tokenů');
$odpoved = zavolej($zakladUrl . '/scripts/cleanup_realtime_sessions.php?key=' . urlencode($tajnyKlic));
if ($odpoved['chyba']) {
    log15('CHYBA (curl): ' . $odpoved['chyba']);
} elseif ($odpoved['kod'] !== 200) {
    log15('CHYBA HTTP ' . $odpoved['kod']);
} else {
    log15('OK - Cleanup dokončen');
}

log15('=== CRON 15MIN KONEC ===');
