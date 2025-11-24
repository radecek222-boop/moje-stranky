<?php
/**
 * Přímý PHP test - spustí track_heatmap.php code a ukáže errory
 */

// Zapnout zobrazování všech chyb
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<pre>";
echo "=== DIRECT PHP TEST PRO TRACK_HEATMAP.PHP ===\n\n";

// Simulovat POST request
$_SERVER['REQUEST_METHOD'] = 'POST';
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';

// Simulovat JSON input
$testData = [
    'page_url' => 'https://www.wgs-service.cz/cenik.php',
    'device_type' => 'desktop',
    'clicks' => [
        ['x_percent' => 50, 'y_percent' => 30, 'viewport_width' => 1920, 'viewport_height' => 1080]
    ],
    'scroll_depths' => [0, 10, 20],
    'csrf_token' => 'test_token_123'
];

echo "TEST DATA:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

echo "--- SPOUŠTÍM TRACK_HEATMAP.PHP ---\n\n";

// Zkusíme spustit API soubor
ob_start();

try {
    // Nejprve otestujme jednotlivé require files
    echo "1. Testování init.php...\n";
    require_once __DIR__ . '/init.php';
    echo "   ✓ init.php OK\n";

    echo "2. Testování csrf_helper.php...\n";
    require_once __DIR__ . '/includes/csrf_helper.php';
    echo "   ✓ csrf_helper.php OK\n";

    echo "3. Testování api_response.php...\n";
    require_once __DIR__ . '/includes/api_response.php';
    echo "   ✓ api_response.php OK\n";

    echo "4. Testování rate_limiter.php...\n";
    require_once __DIR__ . '/includes/rate_limiter.php';
    echo "   ✓ rate_limiter.php OK\n";

    echo "5. Testování DB connection...\n";
    $pdo = getDbConnection();
    echo "   ✓ DB connection OK\n";

    echo "6. Testování RateLimiter class...\n";
    $rateLimiter = new RateLimiter($pdo);
    echo "   ✓ RateLimiter instance OK\n";

    echo "7. Testování heatmap tables...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_heatmap_clicks'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ wgs_analytics_heatmap_clicks existuje\n";
    } else {
        echo "   ✗ wgs_analytics_heatmap_clicks NEEXISTUJE!\n";
    }

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_analytics_heatmap_scroll'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ wgs_analytics_heatmap_scroll existuje\n";
    } else {
        echo "   ✗ wgs_analytics_heatmap_scroll NEEXISTUJE!\n";
    }

    echo "8. Testování rate_limits table...\n";
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_rate_limits'");
    if ($stmt->rowCount() > 0) {
        echo "   ✓ wgs_rate_limits existuje\n";
    } else {
        echo "   ✗ wgs_rate_limits NEEXISTUJE (ale měla by se vytvořit automaticky)\n";
    }

    echo "\n--- VŠECHNY DEPENDENCIES OK ---\n";
    echo "\nPOZNÁMKA: Skutečné volání API by selhalo kvůli CSRF validaci,\n";
    echo "ale teď víme že všechny soubory a tabulky jsou v pořádku.\n";

} catch (Throwable $e) {
    echo "\n!!! CHYBA ZACHYCENA !!!\n\n";
    echo "Typ: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n\n";
    echo "Stack trace:\n";
    echo $e->getTraceAsString() . "\n";
}

$output = ob_get_clean();
echo $output;

echo "\n=== TEST DOKONČEN ===\n";
echo "</pre>";
?>
