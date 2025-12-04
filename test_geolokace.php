<?php
/**
 * Test geolokačního API
 * Diagnostika proč se nesbírají města
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/GeolocationService.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Přístup odepřen - pouze pro administrátory");
}

header('Content-Type: text/html; charset=utf-8');
echo "<h1>Test Geolokace</h1>";
echo "<style>body{font-family:monospace;padding:20px;} .ok{color:green;} .error{color:red;} pre{background:#f0f0f0;padding:10px;}</style>";

$pdo = getDbConnection();

// 1. Test aktuální IP
echo "<h2>1. Aktuální IP</h2>";
$currentIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
echo "<p>Vaše IP: <strong>$currentIp</strong></p>";

// 2. Test ipapi.co API přímo
echo "<h2>2. Test ipapi.co API</h2>";
$testIp = $currentIp;
if (strpos($testIp, ',') !== false) {
    $testIp = trim(explode(',', $testIp)[0]);
}
$url = "https://ipapi.co/{$testIp}/json/";
echo "<p>Volám: <code>$url</code></p>";

$context = stream_context_create([
    'http' => [
        'method' => 'GET',
        'timeout' => 10,
        'user_agent' => 'WGS-Analytics/1.0'
    ]
]);

$response = @file_get_contents($url, false, $context);
if ($response) {
    $data = json_decode($response, true);
    echo "<pre>" . print_r($data, true) . "</pre>";

    if (!empty($data['city'])) {
        echo "<p class='ok'>Město: <strong>{$data['city']}</strong> - API vrací město správně!</p>";
    } else {
        echo "<p class='error'>API NEvrátilo město - možná VPN/datacenter IP nebo rate limit</p>";
    }
} else {
    echo "<p class='error'>API volání selhalo</p>";
}

// 3. Test ip-api.com (fallback)
echo "<h2>3. Test ip-api.com (fallback)</h2>";
$url2 = "http://ip-api.com/json/{$testIp}?fields=status,country,countryCode,region,city,lat,lon,timezone,isp,proxy,hosting";
echo "<p>Volám: <code>$url2</code></p>";

$response2 = @file_get_contents($url2, false, $context);
if ($response2) {
    $data2 = json_decode($response2, true);
    echo "<pre>" . print_r($data2, true) . "</pre>";

    if (!empty($data2['city'])) {
        echo "<p class='ok'>Město: <strong>{$data2['city']}</strong> - Fallback API vrací město!</p>";
    } else {
        echo "<p class='error'>Fallback API NEvrátilo město</p>";
    }
} else {
    echo "<p class='error'>Fallback API volání selhalo</p>";
}

// 4. Test GeolocationService
echo "<h2>4. Test GeolocationService třídy</h2>";
$geoService = new GeolocationService($pdo);
$geoData = $geoService->getLocationFromIP($testIp);
echo "<pre>" . print_r($geoData, true) . "</pre>";

if (!empty($geoData['city'])) {
    echo "<p class='ok'>GeolocationService vrátil město: <strong>{$geoData['city']}</strong></p>";
} else {
    echo "<p class='error'>GeolocationService NEvrátil město</p>";
}

// 5. Stav cache
echo "<h2>5. Stav geolokační cache</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN city IS NOT NULL AND city != '' THEN 1 ELSE 0 END) as s_mestem FROM wgs_analytics_geolocation_cache");
    $cacheStats = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Celkem záznamů v cache: <strong>{$cacheStats['total']}</strong></p>";
    echo "<p>Záznamů s městem: <strong>{$cacheStats['s_mestem']}</strong></p>";

    // Ukázka dat
    $stmt2 = $pdo->query("SELECT ip_address, country_code, city, api_source, cached_at FROM wgs_analytics_geolocation_cache ORDER BY cached_at DESC LIMIT 5");
    $cacheData = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    if ($cacheData) {
        echo "<h3>Posledních 5 záznamů:</h3>";
        echo "<pre>" . print_r($cacheData, true) . "</pre>";
    }
} catch (PDOException $e) {
    echo "<p class='error'>Chyba při čtení cache: " . $e->getMessage() . "</p>";
}

// 6. Stav pageviews
echo "<h2>6. Stav pageviews (lokace)</h2>";
try {
    $stmt = $pdo->query("SELECT COUNT(*) as total, SUM(CASE WHEN city IS NOT NULL AND city != '' THEN 1 ELSE 0 END) as s_mestem FROM wgs_pageviews");
    $pvStats = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p>Celkem pageviews: <strong>{$pvStats['total']}</strong></p>";
    echo "<p>Pageviews s městem: <strong>{$pvStats['s_mestem']}</strong></p>";
} catch (PDOException $e) {
    echo "<p class='error'>Chyba při čtení pageviews: " . $e->getMessage() . "</p>";
}

echo "<hr><p><a href='analytics.php'>Zpět na Analytics</a></p>";
?>
