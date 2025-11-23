<?php
/**
 * Cron Job: Čištění vypršené geolokační cache
 *
 * Tento skript odstraňuje záznamy z wgs_analytics_geolocation_cache,
 * které mají expires_at < NOW().
 *
 * Doporučené nastavení cron:
 * 0 4 * * * /usr/bin/php /cesta/k/scripts/cleanup_geo_cache.php
 * (denně ve 4:00 ráno)
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #4 - Geolocation Service
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/GeolocationService.php';

try {
    $pdo = getDbConnection();
    $geoService = new GeolocationService($pdo);

    echo "[" . date('Y-m-d H:i:s') . "] Spouštím čištění vypršené geolokační cache...\n";

    // Zavolat cleanup metodu
    $smazanoZaznamu = $geoService->cleanExpiredCache();

    if ($smazanoZaznamu > 0) {
        echo "[" . date('Y-m-d H:i:s') . "] Vyčištěno {$smazanoZaznamu} vypršených cache záznamů.\n";
    } else {
        echo "[" . date('Y-m-d H:i:s') . "] Žádné vypršené záznamy k vyčištění.\n";
    }

    // Volitelně: Zobrazit statistiky cache
    $stats = $geoService->getCacheStats();
    echo "[" . date('Y-m-d H:i:s') . "] Cache statistiky: {$stats['aktivni_cache']} aktivních, {$stats['celkem_cache']} celkem.\n";

    exit(0); // Úspěch

} catch (Exception $e) {
    echo "[" . date('Y-m-d H:i:s') . "] CHYBA: " . $e->getMessage() . "\n";
    error_log("Cleanup geo cache error: " . $e->getMessage());
    exit(1); // Chyba
}
?>
