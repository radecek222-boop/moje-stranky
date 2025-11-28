<?php
/**
 * JEDNODUCHÝ TEST - User Scores API přímé volání
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background:#000;color:#0f0;padding:20px;'>";
echo "=== USER SCORES API TEST ===\n\n";

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// Nastavit admin session
$_SESSION['is_admin'] = true;
$csrfToken = generateCSRFToken();

echo "✓ Admin session: true\n";
echo "✓ CSRF token: {$csrfToken}\n\n";

// Nastavit GET parametry (simulovat frontend request)
$_GET['action'] = 'list';
$_GET['date_from'] = date('Y-m-d', strtotime('-30 days'));
$_GET['date_to'] = date('Y-m-d');
$_GET['limit'] = 10;
$_GET['csrf_token'] = $csrfToken;

echo "GET parametry:\n";
print_r($_GET);
echo "\n";

// Zachytit output
ob_start();

try {
    echo "Volám API...\n";
    include __DIR__ . '/api/analytics_user_scores.php';

    $output = ob_get_clean();

    echo "\nAPI PROBĚHLO\n";
    echo "OUTPUT:\n";
    echo $output . "\n";

    // Parse JSON
    $json = json_decode($output, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "\nValidní JSON\n";
        print_r($json);
    } else {
        echo "\nJSON Parse Error: " . json_last_error_msg() . "\n";
    }

} catch (Throwable $e) {
    ob_end_clean();

    echo "\nCHYBA:\n";
    echo "Type: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n</pre>";
?>
