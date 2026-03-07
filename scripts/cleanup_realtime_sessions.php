#!/usr/bin/env php
<?php
/**
 * Cleanup: Expirované tokeny a neaktivní online záznamy
 *
 * Spouští se každých 15 minut přes webcron.
 * URL: https://www.wgs-service.cz/scripts/cleanup_realtime_sessions.php?key=KLIC
 *
 * Co dělá:
 * 1. Smaže expirované záznamy z wgs_tokens
 * 2. Smaže expirované remember-me tokeny z wgs_remember_tokens
 */

// Absolutní cesta k root složce
$rootDir = dirname(__DIR__);

// Načíst .env
require_once $rootDir . '/includes/env_loader.php';

// === BEZPEČNOSTNÍ KONTROLA (web přístup) ===
if (php_sapi_name() !== 'cli') {
    $tajnyKlic = getenv('CRON_SECRET_KEY');
    if (!$tajnyKlic) {
        http_response_code(500);
        error_log("CRON cleanup_realtime_sessions: CRON_SECRET_KEY není nastaven v .env");
        die('Chyba konfigurace: CRON_SECRET_KEY musí být nastaven v .env');
    }
    if (!isset($_GET['key']) || !hash_equals($tajnyKlic, $_GET['key'])) {
        http_response_code(403);
        error_log("CRON cleanup_realtime_sessions: Neplatný klíč - IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        die('Forbidden');
    }
    header('Content-Type: text/plain; charset=utf-8');
}

// Logování
$logFile = $rootDir . '/logs/cron_cleanup.log';

function zaloguj(string $zprava): void {
    global $logFile;
    $cas = date('Y-m-d H:i:s');
    $radek = "[{$cas}] {$zprava}\n";
    echo $radek;
    file_put_contents($logFile, $radek, FILE_APPEND);
}

try {
    require_once $rootDir . '/config/database.php';
    $pdo = Database::getInstance()->getConnection();

    zaloguj("=== START: Cleanup expirovaných tokenů ===");
    $pocetSmazano = 0;

    // 1. Smazat expirované záznamy z wgs_tokens
    $tabulkyTokenu = ['wgs_tokens'];
    foreach ($tabulkyTokenu as $tabulka) {
        try {
            $stmt = $pdo->prepare("DELETE FROM `{$tabulka}` WHERE expires_at < NOW()");
            $stmt->execute();
            $n = $stmt->rowCount();
            zaloguj("wgs_tokens: smazáno {$n} expirovaných záznamů");
            $pocetSmazano += $n;
        } catch (PDOException $e) {
            // Tabulka nemusí existovat na každém prostředí
            zaloguj("wgs_tokens: tabulka nenalezena nebo chyba - " . $e->getMessage());
        }
    }

    // 2. Smazat expirované remember-me tokeny
    try {
        $stmt = $pdo->prepare("DELETE FROM wgs_remember_tokens WHERE expires_at < NOW()");
        $stmt->execute();
        $n = $stmt->rowCount();
        zaloguj("wgs_remember_tokens: smazáno {$n} expirovaných tokenů");
        $pocetSmazano += $n;
    } catch (PDOException $e) {
        zaloguj("wgs_remember_tokens: tabulka nenalezena nebo chyba - " . $e->getMessage());
    }

    zaloguj("=== KONEC: Celkem smazáno {$pocetSmazano} záznamů ===\n");

    if (php_sapi_name() !== 'cli') {
        echo json_encode([
            'status' => 'success',
            'smazano' => $pocetSmazano,
            'cas' => date('Y-m-d H:i:s')
        ]);
    }

} catch (Exception $e) {
    $chyba = "Chyba databáze: " . $e->getMessage();
    error_log("CRON cleanup_realtime_sessions: " . $chyba);
    zaloguj("CHYBA: " . $chyba);

    if (php_sapi_name() !== 'cli') {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Chyba serveru']);
    }
}
