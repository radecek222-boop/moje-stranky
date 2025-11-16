<?php
/**
 * KOMPLETNÍ DIAGNOSTIKA GEOAPIFY
 * Zjistí přesně co se děje s API klíčem a proč mapa nefunguje
 */

echo "=== KOMPLETNÍ GEOAPIFY DIAGNOSTIKA ===\n\n";

// 1. Zkontrolovat .env soubor přímo
echo "1. ČTU .ENV SOUBOR PŘÍMO:\n";
$envPath = __DIR__ . '/.env';
if (file_exists($envPath)) {
    $envContent = file_get_contents($envPath);
    $lines = explode("\n", $envContent);
    foreach ($lines as $line) {
        if (strpos($line, 'GEOAPIFY') !== false) {
            echo "   " . $line . "\n";
        }
    }
} else {
    echo "   ❌ .env soubor neexistuje!\n";
}

echo "\n2. TESTUJI ENV_LOADER.PHP:\n";
require_once __DIR__ . '/includes/env_loader.php';

echo "   getEnvValue('GEOAPIFY_API_KEY'): ";
$fromEnv = getEnvValue('GEOAPIFY_API_KEY', 'NOT_FOUND');
echo $fromEnv . "\n";

echo "   \$_ENV['GEOAPIFY_API_KEY']: ";
echo isset($_ENV['GEOAPIFY_API_KEY']) ? $_ENV['GEOAPIFY_API_KEY'] : 'NOT SET';
echo "\n";

echo "\n3. TESTUJI CONFIG.PHP:\n";
require_once __DIR__ . '/config/config.php';

echo "   defined('GEOAPIFY_KEY'): " . (defined('GEOAPIFY_KEY') ? 'YES' : 'NO') . "\n";
if (defined('GEOAPIFY_KEY')) {
    echo "   GEOAPIFY_KEY value: " . GEOAPIFY_KEY . "\n";

    $placeholders = [
        'your_geoapify_api_key',
        'placeholder_geoapify_key',
        'change-this-in-production',
        'NOT_FOUND',
        '',
        null
    ];

    $isPlaceholder = in_array(GEOAPIFY_KEY, $placeholders, true);
    echo "   Is placeholder: " . ($isPlaceholder ? 'YES ❌' : 'NO ✅') . "\n";
}

echo "\n4. TESTUJI TILE REQUEST:\n";
$z = 7;
$x = 70;
$y = 44;

if (defined('GEOAPIFY_KEY')) {
    $tileUrl = "https://maps.geoapify.com/v1/tile/osm-carto/{$z}/{$x}/{$y}.png?apiKey=" . GEOAPIFY_KEY;

    echo "   URL: " . str_replace(GEOAPIFY_KEY, 'KEY_HIDDEN', $tileUrl) . "\n";

    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'WGS Service/1.0',
            'ignore_errors' => true
        ]
    ]);

    echo "   Fetching...\n";
    $startTime = microtime(true);
    $response = @file_get_contents($tileUrl, false, $context);
    $elapsed = round((microtime(true) - $startTime) * 1000);

    if (isset($http_response_header)) {
        echo "   HTTP Response:\n";
        foreach ($http_response_header as $header) {
            echo "      " . $header . "\n";
        }
    }

    echo "   Time: {$elapsed}ms\n";

    if ($response === false) {
        echo "   Result: ❌ FAILED\n";
        $error = error_get_last();
        if ($error) {
            echo "   Error: " . $error['message'] . "\n";
        }
    } else {
        echo "   Result: ✅ SUCCESS\n";
        echo "   Size: " . strlen($response) . " bytes\n";

        // Check if PNG
        $isPng = substr($response, 0, 8) === "\x89PNG\r\n\x1a\n";
        echo "   Is PNG: " . ($isPng ? 'YES ✅' : 'NO ❌') . "\n";

        if (!$isPng && strlen($response) < 500) {
            echo "   Content type: " . (json_decode($response) ? 'JSON' : 'TEXT') . "\n";
            echo "   Content:\n";
            echo "   " . substr($response, 0, 200) . "\n";
        }
    }
}

echo "\n5. TESTUJI AUTOCOMPLETE REQUEST:\n";
if (defined('GEOAPIFY_KEY')) {
    $autocompleteUrl = "https://api.geoapify.com/v1/geocode/autocomplete?text=Praha&apiKey=" . GEOAPIFY_KEY . "&format=geojson&limit=5";

    echo "   URL: " . str_replace(GEOAPIFY_KEY, 'KEY_HIDDEN', $autocompleteUrl) . "\n";

    echo "   Fetching...\n";
    $startTime = microtime(true);
    $response = @file_get_contents($autocompleteUrl, false, $context);
    $elapsed = round((microtime(true) - $startTime) * 1000);

    if (isset($http_response_header)) {
        echo "   HTTP Response:\n";
        foreach ($http_response_header as $header) {
            echo "      " . $header . "\n";
        }
    }

    echo "   Time: {$elapsed}ms\n";

    if ($response === false) {
        echo "   Result: ❌ FAILED\n";
        $error = error_get_last();
        if ($error) {
            echo "   Error: " . $error['message'] . "\n";
        }
    } else {
        echo "   Result: ✅ SUCCESS\n";
        echo "   Size: " . strlen($response) . " bytes\n";

        $json = json_decode($response, true);
        if ($json) {
            echo "   JSON valid: YES ✅\n";
            if (isset($json['features'])) {
                echo "   Results: " . count($json['features']) . " locations\n";
            }
            if (isset($json['error'])) {
                echo "   ❌ API ERROR: " . $json['error'] . "\n";
            }
        }
    }
}

echo "\n6. TESTUJI PROXY ENDPOINT:\n";
echo "   Testing: api/geocode_proxy.php?action=tile&z=7&x=70&y=44\n";

// Simulate request
$_GET['action'] = 'tile';
$_GET['z'] = 7;
$_GET['x'] = 70;
$_GET['y'] = 44;

ob_start();
try {
    // Don't actually include - it would output
    echo "   (Skipping actual proxy test - would send headers)\n";
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}
ob_end_clean();

echo "\n=== KONEC DIAGNOSTIKY ===\n";
