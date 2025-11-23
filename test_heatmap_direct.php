<?php
/**
 * PŘÍMÝ TEST analytics_heatmap.php s plným error outputem
 */

// Zapnout VŠECHNY errory
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<pre style='background:#1a1a1a;color:#0f0;padding:20px;font-family:monospace;'>";
echo "=== DIRECT TEST analytics_heatmap.php ===\n\n";

// Nastavit GET parametry
$_GET['page_url'] = 'https://www.wgs-service.cz/analytics';
$_GET['type'] = 'click';
$_GET['device_type'] = 'desktop';

// Vytvořit fake session pro test
session_start();
$_SESSION['is_admin'] = true;
$_SESSION['csrf_token'] = 'test_token_12345';
$_GET['csrf_token'] = 'test_token_12345';

echo "✓ Session started\n";
echo "✓ Admin: " . ($_SESSION['is_admin'] ? 'YES' : 'NO') . "\n";
echo "✓ CSRF token: " . $_SESSION['csrf_token'] . "\n";
echo "✓ GET params: page_url=" . $_GET['page_url'] . ", type=" . $_GET['type'] . "\n\n";

echo "Loading analytics_heatmap.php...\n";
echo str_repeat('-', 60) . "\n";

// Zachytit output
ob_start();

try {
    // Include API file
    include __DIR__ . '/api/analytics_heatmap.php';

    $output = ob_get_clean();

    echo "\n" . str_repeat('-', 60) . "\n";
    echo "✅ API PROBĚHLO BEZ CHYBY!\n\n";
    echo "OUTPUT:\n";
    echo $output;

    // Zkusit parsovat jako JSON
    $json = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "\n✅ Validní JSON:\n";
        print_r($json);
    } else {
        echo "\n❌ JSON Parse Error: " . json_last_error_msg() . "\n";
    }

} catch (Throwable $e) {
    ob_end_clean();

    echo "\n❌ FATAL ERROR ZACHYCEN!\n";
    echo str_repeat('=', 60) . "\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "</pre>";
