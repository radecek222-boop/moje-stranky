<?php
/**
 * Vymazání geocoding cache
 * Používej když se změní GPS souřadnice nebo adresy
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Přístup odepřen - pouze admin');
}

?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Vymazání Geocoding Cache</title>
    <style>
        body { font-family: monospace; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        .success { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 10px 0; }
        .error { background: #f8d7da; border-left: 4px solid #dc3545; padding: 15px; margin: 10px 0; }
        .info { background: #d1ecf1; border-left: 4px solid #17a2b8; padding: 15px; margin: 10px 0; }
        .result { font-size: 1.2rem; font-weight: bold; color: #2D5016; margin: 20px 0; }
        .key-list { background: #f8f8f8; padding: 10px; margin: 10px 0; max-height: 400px; overflow-y: auto; }
        .key-item { padding: 5px; border-bottom: 1px solid #ddd; font-size: 0.85rem; }
        .btn { display: inline-block; padding: 10px 20px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #1a300d; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🧹 Vymazání Geocoding Cache</h1>

<?php

if (!function_exists('apcu_cache_info')) {
    echo "<div class='error'>❌ APCu není dostupné na tomto serveru</div>";
    echo "<div class='info'>Cache není aktivní nebo server nepodporuje APCu.</div>";
    echo "</div></body></html>";
    exit;
}

try {
    $info = apcu_cache_info(true);

    if (!$info) {
        echo "<div class='error'>❌ Nelze získat informace o APCu cache</div>";
        echo "</div></body></html>";
        exit;
    }

    $deleted = 0;
    $total = 0;
    $keys = [];

    // Získat seznam všech klíčů v cache
    foreach ($info['cache_list'] as $entry) {
        $key = $entry['info'] ?? '';

        // Mazat pouze geocoding cache (klíče začínající na 'geocode_')
        if (strpos($key, 'geocode_') === 0) {
            $total++;
            if (apcu_delete($key)) {
                $deleted++;
                $keys[] = ['key' => $key, 'status' => 'success'];
            } else {
                $keys[] = ['key' => $key, 'status' => 'error'];
            }
        }
    }

    echo "<div class='result'>";
    echo "📊 Výsledek:<br>";
    echo "Celkem geocoding klíčů: <strong>{$total}</strong><br>";
    echo "Smazáno: <strong>{$deleted}</strong>";
    echo "</div>";

    if ($deleted > 0) {
        echo "<div class='success'>✅ Cache byla úspěšně vymazána!</div>";

        if (!empty($keys)) {
            echo "<div class='info'>Smazané klíče:</div>";
            echo "<div class='key-list'>";
            foreach ($keys as $item) {
                $icon = $item['status'] === 'success' ? '✓' : '✗';
                $color = $item['status'] === 'success' ? '#28a745' : '#dc3545';
                echo "<div class='key-item' style='color: {$color};'>{$icon} {$item['key']}</div>";
            }
            echo "</div>";
        }
    } else {
        echo "<div class='info'>ℹ️ Žádné geocoding klíče nebyly nalezeny v cache.</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

?>

    <div style="margin-top: 2rem;">
        <a href="debug_geocoding.php" class="btn">🔍 Otestovat Geocoding</a>
        <a href="admin.php" class="btn">← Zpět na Admin</a>
    </div>
</div>
</body>
</html>
