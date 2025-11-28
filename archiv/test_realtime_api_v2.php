<?php
/**
 * Test Real-time API v2 - HTTP requests
 */

require_once __DIR__ . '/init.php';

// Simulovat admin session
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    echo "Přihlaste se jako admin";
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test Real-time API v2</title>";
echo "<style>body{font-family:monospace;padding:20px;} h2{color:#333333;} pre{background:#f4f4f4;padding:10px;border-left:4px solid #333333;} .success{color:green;} .error{color:red;}</style>";
echo "</head><body>";
echo "<h1>Test Real-time API v2</h1>";

$baseUrl = 'https://www.wgs-service.cz/api/analytics_realtime.php';
$actions = ['active_visitors', 'active_sessions', 'live_events'];

foreach ($actions as $action) {
    echo "<h2>Testing action: {$action}</h2>";

    $url = $baseUrl . '?' . http_build_query([
        'action' => $action,
        'csrf_token' => $csrfToken,
        'limit' => 50
    ]);

    echo "<p><strong>URL:</strong> " . htmlspecialchars($url) . "</p>";

    // Použít file_get_contents s context pro zachování cookies
    $context = stream_context_create([
        'http' => [
            'header' => "Cookie: PHPSESSID=" . session_id() . "\r\n"
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        echo "<p class='error'>ERROR: Nepodařilo se načíst URL</p>";
    } else {
        echo "<p class='success'>Response received (" . strlen($response) . " bytes)</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";

        $json = json_decode($response, true);
        if ($json) {
            echo "<p><strong>Parsed JSON:</strong></p>";
            echo "<pre>" . print_r($json, true) . "</pre>";

            if (isset($json['status'])) {
                echo "<p><strong>Status:</strong> " . ($json['status'] === 'success' ? '<span class="success">SUCCESS</span>' : '<span class="error">ERROR</span>') . "</p>";
            }
        } else {
            echo "<p class='error'>Failed to parse JSON</p>";
        }
    }

    echo "<hr>";
}

echo "</body></html>";
?>
