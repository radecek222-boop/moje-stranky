<?php
/**
 * Debug geocoding - Zobrazí GPS souřadnice pro testovací adresy
 * Používá přímo Nominatim a OSRM API (ne proxy)
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Přístup odepřen - pouze admin");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Debug Geocoding</title>
    <style>
        body { font-family: monospace; max-width: 1200px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        .result { background: #f0f0f0; padding: 15px; margin: 10px 0; border-left: 4px solid #2D5016; }
        .error { background: #f8d7da; border-left-color: #dc3545; }
        .success { background: #d4edda; border-left-color: #28a745; }
        .info { background: #d1ecf1; border-left-color: #17a2b8; }
        pre { background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; font-size: 0.85rem; }
        .distance { font-size: 1.5rem; font-weight: bold; color: #2D5016; }
        .warning { background: #fff3cd; border-left-color: #ffc107; color: #856404; padding: 10px; margin: 10px 0; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Debug Geocoding & Routing</h1>";

// Funkce pro geocoding přes Nominatim
function debugGeocode($address) {
    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q' => $address,
        'format' => 'geojson',
        'limit' => 1,
        'addressdetails' => 1,
        'countrycodes' => 'cz,sk',
        'email' => 'reklamace@wgs-service.cz',
        'accept-language' => 'cs'
    ]);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_USERAGENT, 'WGS Service/1.0 (contact: reklamace@wgs-service.cz)');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        return ['error' => "cURL error: {$curlError}"];
    }

    if ($httpCode !== 200) {
        return ['error' => "HTTP {$httpCode}"];
    }

    return json_decode($response, true);
}

// Testovací adresy
$testAddresses = [
    'WGS Sídlo' => 'Dubče 364, Běchovice 190 11, Česká republika',
    'Žilina' => 'Pekná 40/16, Žilina',
    'Návsí' => 'Návsí 1130, Návsí',
    'Modřice' => 'Svratecká 989, Modřice'
];

echo "<h2>1️⃣ Geocoding Test (Nominatim API)</h2>";

$coords = [];

foreach ($testAddresses as $name => $address) {
    echo "<div class='result'>";
    echo "<strong>{$name}:</strong> {$address}<br>";

    $result = debugGeocode($address);

    if (isset($result['error'])) {
        echo "<span class='error'>❌ Chyba: {$result['error']}</span>";
    } elseif (!isset($result['features']) || empty($result['features'])) {
        echo "<span class='error'>❌ Žádné výsledky z Nominatim</span>";
    } else {
        $feature = $result['features'][0];
        $c = $feature['geometry']['coordinates'];
        $lat = $c[1];
        $lon = $c[0];

        $coords[$name] = ['lat' => $lat, 'lon' => $lon];

        echo "<span class='success'>✅ GPS: <strong>{$lat}, {$lon}</strong></span><br>";

        if (isset($feature['properties'])) {
            echo "<small style='color: #666;'>Display name: " . ($feature['properties']['display_name'] ?? 'N/A') . "</small>";
        }
    }

    echo "</div>";

    // Rate limiting - Nominatim má limit 1 request/sec
    usleep(1100000); // 1.1 sekundy mezi requesty
}

echo "<h2>2️⃣ Routing Test (OSRM API)</h2>";

if (count($coords) >= 2) {
    $routes = [
        ['WGS Sídlo', 'Žilina'],
        ['WGS Sídlo', 'Návsí'],
        ['WGS Sídlo', 'Modřice']
    ];

    foreach ($routes as list($from, $to)) {
        if (!isset($coords[$from]) || !isset($coords[$to])) continue;

        echo "<div class='result'>";
        echo "<strong>Trasa: {$from} → {$to}</strong><br>";

        $startLat = $coords[$from]['lat'];
        $startLon = $coords[$from]['lon'];
        $endLat = $coords[$to]['lat'];
        $endLon = $coords[$to]['lon'];

        $osrmUrl = "https://router.project-osrm.org/route/v1/driving/{$startLon},{$startLat};{$endLon},{$endLat}?overview=false";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $osrmUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, 'WGS Service/1.0');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            echo "<span class='error'>❌ cURL error: {$curlError}</span>";
        } elseif ($httpCode !== 200) {
            echo "<span class='error'>❌ HTTP {$httpCode}</span>";
        } else {
            $data = json_decode($response, true);

            if (isset($data['code']) && $data['code'] === 'Ok' && isset($data['routes'][0])) {
                $route = $data['routes'][0];
                $distanceKm = round($route['distance'] / 1000, 1);
                $timeMin = round($route['duration'] / 60);

                echo "<div class='distance'>{$distanceKm} km ({$timeMin} min)</div>";
                echo "<small>Provider: OSRM (Open Source Routing Machine)</small><br>";
                echo "<pre>" . json_encode($route, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            } else {
                echo "<span class='error'>❌ Neplatná odpověď z OSRM</span>";
                echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
            }
        }

        echo "</div>";
    }
} else {
    echo "<div class='warning'>⚠️ Nedostatek geocoding výsledků pro test routingu</div>";
}

echo "<div class='info' style='margin-top: 2rem;'>";
echo "<strong>ℹ️ Poznámky:</strong><br>";
echo "• Nominatim má rate limit 1 request/sec (dodržujeme 1.1s mezi requesty)<br>";
echo "• OSRM je open-source routing bez omezení<br>";
echo "• Cache (APCu) se nepoužívá v debug módu<br>";
echo "</div>";

echo "</div></body></html>";
