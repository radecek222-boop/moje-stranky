<?php
/**
 * Debug geocoding - Zobrazí GPS souřadnice pro testovací adresy
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
        pre { background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
        .distance { font-size: 1.5rem; font-weight: bold; color: #2D5016; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Debug Geocoding & Routing</h1>";

// Funkce pro geocoding
function debugGeocode($address) {
    $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
           '://' . $_SERVER['HTTP_HOST'] .
           '/api/geocode_proxy.php?action=search&address=' . urlencode($address);

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

    if (isset($_COOKIE[session_name()])) {
        curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . $_COOKIE[session_name()]);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

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

echo "<h2>1️⃣ Geocoding Test</h2>";

$coords = [];

foreach ($testAddresses as $name => $address) {
    echo "<div class='result'>";
    echo "<strong>{$name}:</strong> {$address}<br>";

    $result = debugGeocode($address);

    if (isset($result['error'])) {
        echo "<span class='error'>Chyba: {$result['error']}</span>";
    } elseif (!isset($result['features']) || empty($result['features'])) {
        echo "<span class='error'>Žádné výsledky</span>";
    } else {
        $feature = $result['features'][0];
        $c = $feature['geometry']['coordinates'];
        $lat = $c[1];
        $lon = $c[0];

        $coords[$name] = ['lat' => $lat, 'lon' => $lon];

        echo "<span class='success'>✓ GPS: {$lat}, {$lon}</span><br>";
        echo "<pre>" . json_encode($feature['properties'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
    }

    echo "</div>";
}

echo "<h2>2️⃣ Routing Test</h2>";

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

        $waypoints = "{$startLat},{$startLon}|{$endLat},{$endLon}";

        $url = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') .
               '://' . $_SERVER['HTTP_HOST'] .
               '/api/geocode_proxy.php?action=routing&waypoints=' . urlencode($waypoints) . '&mode=drive';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        if (isset($_COOKIE[session_name()])) {
            curl_setopt($ch, CURLOPT_COOKIE, session_name() . '=' . $_COOKIE[session_name()]);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            echo "<span class='error'>Chyba: HTTP {$httpCode}</span>";
        } else {
            $data = json_decode($response, true);

            if (isset($data['features'][0]['properties'])) {
                $props = $data['features'][0]['properties'];
                $distanceKm = round($props['distance'] / 1000, 1);
                $timeMin = round($props['time'] / 60);
                $provider = $props['provider'] ?? 'unknown';

                echo "<div class='distance'>{$distanceKm} km ({$timeMin} min)</div>";
                echo "<small>Provider: {$provider}</small><br>";
                echo "<pre>" . json_encode($props, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
            } else {
                echo "<span class='error'>Neplatná odpověď</span>";
                echo "<pre>" . htmlspecialchars(substr($response, 0, 500)) . "</pre>";
            }
        }

        echo "</div>";
    }
}

echo "</div></body></html>";
