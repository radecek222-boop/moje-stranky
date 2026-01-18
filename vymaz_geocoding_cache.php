<?php
/**
 * Vymazání geocoding cache
 * Používej když se změní GPS souřadnice nebo adresy
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Přístup odepřen - pouze admin');
}

$deleted = 0;
$total = 0;
$keys = [];
$wasExecuted = false;

// CSRF ochrana - vymazat pouze při POST requestu s platným tokenem
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die('Neplatný CSRF token');
    }

    $wasExecuted = true;

    if (!function_exists('apcu_cache_info')) {
        // APCu není dostupné
    } else {
        try {
            $info = apcu_cache_info(true);

            if ($info) {
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
            }
        } catch (Exception $e) {
            // Error handling below
        }
    }
}

// Získat preview (seznam klíčů bez mazání)
$previewKeys = [];
$previewTotal = 0;

if (!$wasExecuted && function_exists('apcu_cache_info')) {
    try {
        $info = apcu_cache_info(true);
        if ($info) {
            foreach ($info['cache_list'] as $entry) {
                $key = $entry['info'] ?? '';
                if (strpos($key, 'geocode_') === 0) {
                    $previewTotal++;
                    $previewKeys[] = $key;
                }
            }
        }
    } catch (Exception $e) {
        // Ignore
    }
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
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 10px 0; }
        .result { font-size: 1.2rem; font-weight: bold; color: #2D5016; margin: 20px 0; }
        .key-list { background: #f8f8f8; padding: 10px; margin: 10px 0; max-height: 400px; overflow-y: auto; }
        .key-item { padding: 5px; border-bottom: 1px solid #ddd; font-size: 0.85rem; }
        .btn { display: inline-block; padding: 10px 20px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px; border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #1a300d; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
    </style>
</head>
<body>
<div class='container'>
    <h1>🧹 Vymazání Geocoding Cache</h1>

<?php if (!function_exists('apcu_cache_info')): ?>
    <div class='error'>❌ APCu není dostupné na tomto serveru</div>
    <div class='info'>Cache není aktivní nebo server nepodporuje APCu.</div>

<?php elseif ($wasExecuted): ?>
    <!-- VÝSLEDEK MAZÁNÍ -->
    <div class='result'>
        📊 Výsledek:<br>
        Celkem geocoding klíčů: <strong><?= $total ?></strong><br>
        Smazáno: <strong><?= $deleted ?></strong>
    </div>

    <?php if ($deleted > 0): ?>
        <div class='success'>✅ Cache byla úspěšně vymazána!</div>

        <?php if (!empty($keys)): ?>
            <div class='info'>Smazané klíče:</div>
            <div class='key-list'>
                <?php foreach ($keys as $item): ?>
                    <?php
                    $icon = $item['status'] === 'success' ? '✓' : '✗';
                    $color = $item['status'] === 'success' ? '#28a745' : '#dc3545';
                    ?>
                    <div class='key-item' style='color: <?= $color ?>;'><?= $icon ?> <?= htmlspecialchars($item['key']) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php else: ?>
        <div class='info'>ℹ️ Žádné geocoding klíče nebyly nalezeny v cache.</div>
    <?php endif; ?>

<?php else: ?>
    <!-- PREVIEW A POTVRZENÍ -->
    <div class='info'>
        <strong>ℹ️ Co se stane?</strong><br>
        Tato akce vymaže všechny geocoding cache záznamy z APCu.<br>
        Frontend pak bude muset znovu vypočítat všechny vzdálenosti.
    </div>

    <?php if ($previewTotal > 0): ?>
        <div class='warning'>
            <strong>⚠️ Nalezeno <?= $previewTotal ?> geocoding záznamů</strong>
        </div>

        <?php if (!empty($previewKeys)): ?>
            <div class='info'>Klíče, které budou smazány:</div>
            <div class='key-list'>
                <?php foreach ($previewKeys as $key): ?>
                    <div class='key-item'><?= htmlspecialchars($key) ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method='POST' style='margin: 20px 0;'>
            <input type='hidden' name='csrf_token' value='<?= htmlspecialchars(generateCSRFToken()) ?>'>
            <button type='submit' class='btn btn-danger'>🗑️ VYMAZAT CACHE</button>
            <a href='admin.php' class='btn'>← Zrušit</a>
        </form>
    <?php else: ?>
        <div class='info'>ℹ️ Cache je prázdná - nic k vymazání.</div>
    <?php endif; ?>

<?php endif; ?>

    <div style="margin-top: 2rem;">
        <a href="debug_geocoding.php" class="btn">🔍 Otestovat Geocoding</a>
        <a href="debug_distance_cache.php" class="btn">🔍 Frontend Cache</a>
        <a href="admin.php" class="btn">← Zpět na Admin</a>
    </div>
</div>
</body>
</html>
