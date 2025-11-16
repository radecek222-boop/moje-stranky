<?php
/**
 * Test skriptu pro diagnostiku tile response
 * Simuluje požadavek na tile a vypíše response
 */

// Nastavit error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "=== TEST TILE RESPONSE ===\n\n";

// Načíst config
require_once __DIR__ . '/init.php';

// Získat API klíč
$apiKey = defined('GEOAPIFY_KEY') ? GEOAPIFY_KEY : null;

echo "1. API KEY CHECK:\n";
echo "   GEOAPIFY_KEY defined: " . (defined('GEOAPIFY_KEY') ? 'YES' : 'NO') . "\n";
echo "   Value: " . ($apiKey ? substr($apiKey, 0, 15) . '...' : 'NULL') . "\n";
echo "   Is placeholder: " . (in_array($apiKey, ['your_geoapify_api_key', 'placeholder_geoapify_key', 'change-this-in-production']) ? 'YES ❌' : 'NO ✅') . "\n\n";

// Test tile URL
$z = 7;
$x = 70;
$y = 44;
$tileUrl = "https://maps.geoapify.com/v1/tile/osm-carto/{$z}/{$x}/{$y}.png?apiKey={$apiKey}";

echo "2. TILE URL:\n";
echo "   " . str_replace($apiKey, 'API_KEY_HIDDEN', $tileUrl) . "\n\n";

// Pokus o načtení tile
echo "3. FETCHING TILE:\n";
$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'user_agent' => 'WGS Service/1.0',
        'ignore_errors' => true // Důležité - získat response i při HTTP error
    ]
]);

$imageData = @file_get_contents($tileUrl, false, $context);

// Získat HTTP response headers
$headers = $http_response_header ?? [];

echo "   HTTP Headers:\n";
foreach ($headers as $header) {
    echo "   - " . $header . "\n";
}
echo "\n";

if ($imageData === false) {
    echo "   Result: ❌ FAILED\n";
    echo "   Error: " . error_get_last()['message'] ?? 'Unknown error' . "\n";
} else {
    echo "   Result: ✅ SUCCESS\n";
    echo "   Data length: " . strlen($imageData) . " bytes\n";
    echo "   First 50 bytes: " . bin2hex(substr($imageData, 0, 50)) . "\n";

    // Zkontrolovat PNG signature
    $pngSignature = "\x89PNG\r\n\x1a\n";
    $isPng = substr($imageData, 0, 8) === $pngSignature;
    echo "   Is valid PNG: " . ($isPng ? 'YES ✅' : 'NO ❌') . "\n";

    // Pokud to není PNG, vypsat jako text
    if (!$isPng && strlen($imageData) < 1000) {
        echo "   Content (text): " . htmlspecialchars($imageData) . "\n";
    }
}

echo "\n4. RECOMMENDATION:\n";
if (in_array($apiKey, ['your_geoapify_api_key', 'placeholder_geoapify_key', 'change-this-in-production', null, ''])) {
    echo "   ❌ Neplatný API klíč!\n";
    echo "   → Otevřete: check_geoapify_config.php\n";
    echo "   → Následujte instrukce v GEOAPIFY_SETUP.md\n";
} else {
    echo "   ✅ API klíč je nastaven\n";
    echo "   → Pokud tile selhal, klíč může být neplatný nebo expired\n";
    echo "   → Zkontrolujte na https://myprojects.geoapify.com/\n";
}

echo "</pre>";
