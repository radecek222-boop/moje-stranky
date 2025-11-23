<?php
/**
 * Test heatmap API - zobrazí RAW response z API
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// Simulace admin session
$_SESSION['is_admin'] = true;

// Vygenerovat CSRF token
$csrfToken = generateCSRFToken();

echo "<h1>Test Heatmap API - RAW Response</h1>";
echo "<style>body{font-family:monospace;padding:20px;} pre{background:#f5f5f5;padding:15px;border:1px solid #ddd;overflow-x:auto;}</style>";

// Parametry pro test
$pageUrl = 'https://www.wgs-service.cz/analytics';
$deviceType = 'all';
$type = 'click';

echo "<h2>Parametry:</h2>";
echo "<pre>";
echo "page_url: $pageUrl\n";
echo "device_type: $deviceType\n";
echo "type: $type\n";
echo "csrf_token: $csrfToken\n";
echo "</pre>";

// Sestavit URL
$url = "http://" . $_SERVER['HTTP_HOST'] . "/api/analytics_heatmap.php";
$url .= "?page_url=" . urlencode($pageUrl);
$url .= "&device_type=" . urlencode($deviceType);
$url .= "&type=" . urlencode($type);
$url .= "&csrf_token=" . urlencode($csrfToken);

echo "<h2>URL:</h2>";
echo "<pre>" . htmlspecialchars($url) . "</pre>";

echo "<h2>Volání API...</h2>";

// Použít cURL pro zavolání API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . session_id()); // Předat session ID
curl_setopt($ch, CURLOPT_HEADER, true); // Zahrnout HTTP headers

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<div style='color:red;'><strong>cURL Error:</strong> $error</div>";
}

// Rozdělit headers a body
$headers = substr($response, 0, $headerSize);
$body = substr($response, $headerSize);

echo "<h2>HTTP Status Code:</h2>";
echo "<pre style='font-size:20px;color:" . ($httpCode == 200 ? "green" : "red") . ";'>";
echo $httpCode;
echo "</pre>";

echo "<h2>Response Headers:</h2>";
echo "<pre>" . htmlspecialchars($headers) . "</pre>";

echo "<h2>Response Body:</h2>";
echo "<pre>" . htmlspecialchars($body) . "</pre>";

echo "<h2>Analýza:</h2>";
echo "<div style='background:#fff3cd;padding:15px;border:1px solid #ffc107;margin:10px 0;'>";

// Je to validní JSON?
$json = json_decode($body);
if (json_last_error() === JSON_ERROR_NONE) {
    echo "✓ <strong style='color:green;'>Response je validní JSON</strong><br>";
    echo "<pre>" . json_encode($json, JSON_PRETTY_PRINT) . "</pre>";
} else {
    echo "✗ <strong style='color:red;'>Response NENÍ validní JSON</strong><br>";
    echo "JSON Error: " . json_last_error_msg() . "<br><br>";

    // Zkusit najít PHP errory nebo warnings
    if (strpos($body, 'Warning:') !== false || strpos($body, 'Notice:') !== false || strpos($body, 'Fatal error:') !== false) {
        echo "<strong style='color:red;'>⚠️ Detekována PHP chyba v response!</strong><br>";
        echo "Response obsahuje PHP error/warning, který způsobuje nevalidní JSON.<br>";
    }

    if (strpos($body, '<!DOCTYPE') !== false || strpos($body, '<html') !== false) {
        echo "<strong style='color:red;'>⚠️ Response je HTML místo JSON!</strong><br>";
        echo "API vrací HTML error page místo JSON.<br>";
    }

    // Zkusit najít kde začíná JSON (pokud je tam)
    $jsonStart = strpos($body, '{');
    if ($jsonStart !== false && $jsonStart > 0) {
        echo "<strong style='color:orange;'>⚠️ Před JSON je " . $jsonStart . " znaků garbage!</strong><br>";
        echo "První část (garbage):<br>";
        echo "<pre style='background:#ffcccc;'>" . htmlspecialchars(substr($body, 0, $jsonStart)) . "</pre>";
    }
}

echo "</div>";

echo "<hr>";
echo "<a href='/analytics-heatmap' style='display:inline-block;padding:10px 20px;background:#333333;color:white;text-decoration:none;border-radius:5px;'>Zpět na Heatmap</a>";
