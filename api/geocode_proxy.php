<?php
/**
 * Geoapify API Proxy
 * Skrývá API klíč před klienty - bezpečnostní opatření
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

try {
    // Získání API klíče z environment variables
    $apiKey = getenv('GEOAPIFY_API_KEY') ?: $_ENV['GEOAPIFY_API_KEY'] ?? null;

    if (!$apiKey) {
        throw new Exception('GEOAPIFY_API_KEY není nastaveno');
    }

    // Získání akce
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'search':
            // Geocoding - převod adresy na GPS souřadnice
            $address = $_GET['address'] ?? '';

            if (empty($address)) {
                throw new Exception('Chybí parametr address');
            }

            // Validace - max 200 znaků
            if (strlen($address) > 200) {
                throw new Exception('Adresa je příliš dlouhá');
            }

            $url = 'https://api.geoapify.com/v1/geocode/search?' . http_build_query([
                'text' => $address,
                'apiKey' => $apiKey,
                'format' => 'geojson'
            ]);

            break;

        case 'routing':
            // Výpočet trasy mezi dvěma body
            $waypoints = $_GET['waypoints'] ?? '';
            $mode = $_GET['mode'] ?? 'drive';

            if (empty($waypoints)) {
                throw new Exception('Chybí parametr waypoints');
            }

            // Validace waypoints formátu: lat1,lon1|lat2,lon2
            if (!preg_match('/^-?\d+\.?\d*,-?\d+\.?\d*\|-?\d+\.?\d*,-?\d+\.?\d*$/', $waypoints)) {
                throw new Exception('Neplatný formát waypoints');
            }

            $url = 'https://api.geoapify.com/v1/routing?' . http_build_query([
                'waypoints' => $waypoints,
                'mode' => $mode,
                'apiKey' => $apiKey
            ]);

            break;

        case 'tile':
            // Map tiles - pro Leaflet
            $z = intval($_GET['z'] ?? 0);
            $x = intval($_GET['x'] ?? 0);
            $y = intval($_GET['y'] ?? 0);

            if ($z < 0 || $z > 20 || $x < 0 || $y < 0) {
                throw new Exception('Neplatné tile souřadnice');
            }

            $url = "https://maps.geoapify.com/v1/tile/osm-carto/{$z}/{$x}/{$y}.png?apiKey={$apiKey}";

            // Pro tiles vracíme přímo obrázek
            header('Content-Type: image/png');
            $imageData = @file_get_contents($url);

            if ($imageData === false) {
                throw new Exception('Chyba při načítání tile');
            }

            echo $imageData;
            exit;

        default:
            throw new Exception('Neplatná akce');
    }

    // Fetch data z Geoapify API
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'WGS Service/1.0'
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        throw new Exception('Chyba při komunikaci s Geoapify API');
    }

    // Vrácení odpovědi
    echo $response;

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
