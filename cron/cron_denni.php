#!/usr/bin/env php
<?php
/**
 * Konsolidovaný CRON - denní úlohy (02:00)
 *
 * Nahrazuje:
 *   - scripts/ultra_master_cron.php    (cleanup, skóre, GDPR, AI)
 *   - api/generuj_aktuality.php        (aktuality Natuzzi)
 *   - webcron-send-reminders.php       (připomínky termínů)
 *
 * URL: https://www.wgs-service.cz/cron/cron_denni.php?key=KLIC
 * Nastavit: každý den v 02:00
 */

$rootDir = dirname(__DIR__);
require_once $rootDir . '/includes/env_loader.php';

// === BEZPEČNOSTNÍ KONTROLA ===
if (php_sapi_name() !== 'cli') {
    $tajnyKlic = getenv('CRON_SECRET_KEY');
    if (!$tajnyKlic) {
        http_response_code(500);
        error_log("CRON cron_denni: CRON_SECRET_KEY není nastaven v .env");
        die('Chyba konfigurace: CRON_SECRET_KEY musí být nastaven v .env');
    }
    if (!isset($_GET['key']) || !hash_equals($tajnyKlic, $_GET['key'])) {
        http_response_code(403);
        error_log("CRON cron_denni: Neplatný klíč - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        die('Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

set_time_limit(600);

$logFile   = $rootDir . '/logs/cron_denni.log';
$tajnyKlic = getenv('CRON_SECRET_KEY') ?: '';
$zakladUrl = getenv('APP_URL') ?: 'https://www.wgs-service.cz';

function logDenni(string $zprava): void {
    global $logFile;
    $radek = '[' . date('Y-m-d H:i:s') . '] ' . $zprava . "\n";
    echo $radek;
    file_put_contents($logFile, $radek, FILE_APPEND);
}

function zavolejDenni(string $url, int $timeout = 180): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => $timeout,
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

function spustUlohu(string $nazev, string $url): void {
    logDenni("Spouštím: {$nazev}");
    $start    = microtime(true);
    $odpoved  = zavolejDenni($url);
    $trvani   = round(microtime(true) - $start, 1);

    if ($odpoved['chyba']) {
        logDenni("CHYBA (curl) [{$nazev}]: " . $odpoved['chyba']);
    } elseif ($odpoved['kod'] !== 200) {
        logDenni("CHYBA HTTP {$odpoved['kod']} [{$nazev}]");
    } else {
        logDenni("OK [{$nazev}] - trvání: {$trvani}s");
    }
}

logDenni('=== CRON DENNÍ START - ' . date('Y-m-d') . ' ===');
$klic = urlencode($tajnyKlic);

// --- 1. Ultra Master (cleanup, skóre uživatelů, GDPR, AI reporty) ---
spustUlohu(
    'Ultra Master Cron',
    $zakladUrl . '/scripts/ultra_master_cron.php?key=' . $klic
);
sleep(5);

// --- 2. Aktuality Natuzzi ---
spustUlohu(
    'Aktuality Natuzzi',
    $zakladUrl . '/api/generuj_aktuality.php?key=' . $klic
);
sleep(3);

// --- 3. Připomínky termínů zákazníkům ---
spustUlohu(
    'Připomínky termínů',
    $zakladUrl . '/webcron-send-reminders.php?key=' . $klic
);

logDenni('=== CRON DENNÍ KONEC ===');
