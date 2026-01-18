<?php
/**
 * Vymazání geocoding cache
 * Používej když se změní GPS souřadnice nebo adresy
 */

// Bezpečnostní kontrola - pouze z příkazové řádky nebo z localhost
if (php_sapi_name() !== 'cli' && $_SERVER['REMOTE_ADDR'] !== '127.0.0.1') {
    die('Přístup odepřen');
}

echo "🧹 Mazání geocoding cache...\n\n";

if (!function_exists('apcu_cache_info')) {
    die("❌ APCu není dostupné na tomto serveru\n");
}

try {
    $info = apcu_cache_info(true);

    if (!$info) {
        die("❌ Nelze získat informace o APCu cache\n");
    }

    $deleted = 0;
    $total = 0;

    // Získat seznam všech klíčů v cache
    foreach ($info['cache_list'] as $entry) {
        $key = $entry['info'] ?? '';

        // Mazat pouze geocoding cache (klíče začínající na 'geocode_')
        if (strpos($key, 'geocode_') === 0) {
            $total++;
            if (apcu_delete($key)) {
                $deleted++;
                echo "✓ Smazán: {$key}\n";
            } else {
                echo "✗ Chyba při mazání: {$key}\n";
            }
        }
    }

    echo "\n📊 Výsledek:\n";
    echo "   Celkem geocoding klíčů: {$total}\n";
    echo "   Smazáno: {$deleted}\n";
    echo "\n✅ Cache vymazána!\n";

} catch (Exception $e) {
    echo "❌ Chyba: " . $e->getMessage() . "\n";
}
