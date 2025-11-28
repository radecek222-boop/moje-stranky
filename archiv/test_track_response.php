<?php
/**
 * Debug skript - Ukáže přesný RAW response z track_heatmap.php
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Získat CSRF token z session
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Test Track Response</title>
    <style>
        body { font-family: monospace; margin: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 5px; max-width: 1200px; margin: 0 auto; }
        pre { background: #f0f0f0; padding: 15px; border-radius: 3px; overflow-x: auto; white-space: pre-wrap; word-wrap: break-word; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        h2 { margin-top: 20px; border-bottom: 2px solid #333; padding-bottom: 5px; }
    </style>
</head>
<body>
<div class='container'>
    <h1>Test Track Heatmap Response</h1>
    <p>Tento skript ukáže přesný RAW response z API včetně PHP errors</p>
";

$testData = [
    'page_url' => 'https://www.wgs-service.cz/test.php',
    'device_type' => 'desktop',
    'clicks' => [
        ['x_percent' => 50, 'y_percent' => 30, 'viewport_width' => 1920, 'viewport_height' => 1080]
    ],
    'scroll_depths' => [0, 10, 20],
    'csrf_token' => $csrfToken
];

echo "<h2>Request Data:</h2>";
echo "<pre>" . json_encode($testData, JSON_PRETTY_PRINT) . "</pre>";

// Inicializovat cURL
$ch = curl_init('https://www.wgs-service.cz/api/track_heatmap.php');

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($testData),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'X-CSRF-TOKEN: ' . $csrfToken
    ],
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_HEADER => true,
    CURLOPT_VERBOSE => true
]);

echo "<h2>Odesílám request...</h2>";

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);

$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

curl_close($ch);

echo "<h2>HTTP Status Code:</h2>";
echo "<pre class='" . ($httpCode === 200 ? 'success' : 'error') . "'>" . $httpCode . "</pre>";

echo "<h2>Response Headers:</h2>";
echo "<pre>" . htmlspecialchars($headers) . "</pre>";

echo "<h2>Response Body (Raw):</h2>";
echo "<pre>" . htmlspecialchars($body) . "</pre>";

echo "<h2>Response Body Length:</h2>";
echo "<pre>" . strlen($body) . " bytes</pre>";

// Zkusit parsovat jako JSON
echo "<h2>JSON Parse Test:</h2>";
$jsonData = json_decode($body, true);
if ($jsonData) {
    echo "<pre class='success'>✓ Validní JSON:</pre>";
    echo "<pre>" . json_encode($jsonData, JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "<pre class='error'>✗ Není validní JSON!</pre>";
    echo "<pre class='error'>JSON Error: " . json_last_error_msg() . "</pre>";

    // Zobrazit první řádky pro analýzu
    echo "<h3>První 3 řádky (možná PHP error):</h3>";
    $lines = explode("\n", $body);
    echo "<pre class='warning'>";
    for ($i = 0; $i < min(3, count($lines)); $i++) {
        echo htmlspecialchars($lines[$i]) . "\n";
    }
    echo "</pre>";
}

echo "</div></body></html>";
?>
