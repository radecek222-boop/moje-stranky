<?php
/**
 * Test Real-time API
 */

require_once __DIR__ . '/init.php';

// Simulovat admin session
$_SESSION['is_admin'] = true;
$_SESSION['csrf_token'] = 'test_token_12345';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test Real-time API</title></head><body>";
echo "<h1>Test Real-time API</h1>";

$actions = ['active_visitors', 'active_sessions', 'live_events'];

foreach ($actions as $action) {
    echo "<h2>Testing action: {$action}</h2>";

    // Simulovat GET parametry
    $_GET['action'] = $action;
    $_GET['csrf_token'] = 'test_token_12345';
    $_GET['limit'] = 50;

    ob_start();

    try {
        include __DIR__ . '/api/analytics_realtime.php';
        $output = ob_get_clean();

        echo "<pre>Result: " . htmlspecialchars($output) . "</pre>";

        $json = json_decode($output, true);
        if ($json) {
            echo "<p>Status: " . ($json['status'] ?? 'unknown') . "</p>";
            if (isset($json['message'])) {
                echo "<p>Message: " . htmlspecialchars($json['message']) . "</p>";
            }
        }
    } catch (Exception $e) {
        ob_end_clean();
        echo "<p style='color: red'>ERROR: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
    }

    echo "<hr>";
}

echo "</body></html>";
?>
