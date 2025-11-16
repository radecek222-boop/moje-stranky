<?php
/**
 * Jednoduchý test tile response
 */

// Načíst .env
require_once __DIR__ . '/includes/env_loader.php';

// Získat API klíč z env
$apiKey = getEnvValue('GEOAPIFY_API_KEY', 'not-set');

echo "=== TILE TEST ===\n\n";

echo "1. API KEY:\n";
echo "   Value: " . substr($apiKey, 0, 20) . "...\n";
echo "   Is placeholder: " . (in_array($apiKey, ['your_geoapify_api_key', 'placeholder_geoapify_key', 'change-this-in-production', 'not-set']) ? 'YES ❌' : 'NO ✅') . "\n\n";

// Test tile URL
$z = 7;
$x = 70;
$y = 44;
$tileUrl = "https://maps.geoapify.com/v1/tile/osm-carto/{$z}/{$x}/{$y}.png?apiKey={$apiKey}";

echo "2. TESTING TILE REQUEST:\n";

$context = stream_context_create([
    'http' => [
        'timeout' => 5,
        'user_agent' => 'WGS Service/1.0',
        'ignore_errors' => true
    ]
]);

$imageData = @file_get_contents($tileUrl, false, $context);

// Response headers
if (isset($http_response_header)) {
    echo "   HTTP Response:\n";
    foreach ($http_response_header as $header) {
        echo "   " . $header . "\n";
    }
}

echo "\n3. RESULT:\n";
if ($imageData === false) {
    echo "   Status: ❌ FAILED\n";
    $error = error_get_last();
    if ($error) {
        echo "   Error: " . $error['message'] . "\n";
    }
} else {
    echo "   Status: ✅ SUCCESS\n";
    echo "   Size: " . strlen($imageData) . " bytes\n";

    // Check PNG signature
    $pngSignature = "\x89PNG\r\n\x1a\n";
    $isPng = substr($imageData, 0, 8) === $pngSignature;
    echo "   Is PNG: " . ($isPng ? 'YES ✅' : 'NO ❌') . "\n";

    if (!$isPng && strlen($imageData) < 500) {
        echo "\n   Content:\n   " . $imageData . "\n";
    }
}

echo "\n4. DIAGNOSIS:\n";
if (in_array($apiKey, ['your_geoapify_api_key', 'placeholder_geoapify_key', 'change-this-in-production', 'not-set'])) {
    echo "   ⚠️  HLAVNÍ PROBLÉM: Neplatný API klíč!\n";
    echo "   \n";
    echo "   ŘEŠENÍ:\n";
    echo "   1. Otevřete .env soubor\n";
    echo "   2. Změňte GEOAPIFY_API_KEY='{$apiKey}'\n";
    echo "   3. Na platný klíč z https://www.geoapify.com/\n";
    echo "   4. Viz GEOAPIFY_SETUP.md pro návod\n";
} else {
    echo "   API klíč je nastaven správně\n";
}
