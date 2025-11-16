<?php
/**
 * Geoapify API Proxy + OSRM Routing
 * Skrývá API klíč před klienty - bezpečnostní opatření
 * Používá OSRM pro routing (zdarma, open-source)
 */

require_once __DIR__ . '/../init.php';

// ✅ FIX: Nepoužívat globální header - každý action má vlastní Content-Type
// header('Content-Type: application/json'); // MOVED to individual cases

/**
 * Výpočet vzdálenosti mezi dvěma GPS body (Haversine vzorec)
 * @param float $lat1 Latitude prvního bodu
 * @param float $lon1 Longitude prvního bodu
 * @param float $lat2 Latitude druhého bodu
 * @param float $lon2 Longitude druhého bodu
 * @return float Vzdálenost v kilometrech
 */
/**
 * HaversineDistance
 *
 * @param mixed $lat1 Lat1
 * @param mixed $lon1 Lon1
 * @param mixed $lat2 Lat2
 * @param mixed $lon2 Lon2
 */
function haversineDistance($lat1, $lon1, $lat2, $lon2) {
    $earthRadius = 6371; // Poloměr Země v km

    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);

    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLon/2) * sin($dLon/2);

    $c = 2 * atan2(sqrt($a), sqrt(1-$a));

    return $earthRadius * $c;
}

try {
    // Získání API klíče - použít konstantu z config.php
    $apiKey = defined('GEOAPIFY_KEY') ? GEOAPIFY_KEY : null;

    if (!$apiKey) {
        throw new Exception('GEOAPIFY_KEY není nastaveno v konfiguraci');
    }

    // Stream context pro HTTP requesty
    $context = stream_context_create([
        'http' => [
            'timeout' => 5,
            'user_agent' => 'WGS Service/1.0'
        ]
    ]);

    // Získání akce
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'search':
            // Geocoding - převod adresy na GPS souřadnice
            header('Content-Type: application/json');
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

        case 'autocomplete':
            // Našeptávač adres
            header('Content-Type: application/json');
            $text = $_GET['text'] ?? '';
            $type = $_GET['type'] ?? 'street'; // street, city, postcode
            $country = $_GET['country'] ?? ''; // CZ, SK, etc.

            if (empty($text)) {
                throw new Exception('Chybí parametr text');
            }

            // Validace - max 100 znaků
            if (strlen($text) > 100) {
                throw new Exception('Text je příliš dlouhý');
            }

            $params = [
                'text' => $text,
                'apiKey' => $apiKey,
                'format' => 'geojson',
                'limit' => 5
            ];

            // Filtr podle typu
            if ($type === 'street') {
                $params['type'] = 'street';
            } elseif ($type === 'city') {
                $params['type'] = 'city';
            }

            // Filtr podle země (CZ/SK)
            if (!empty($country)) {
                $params['filter'] = 'countrycode:' . strtolower($country);
            }

            $url = 'https://api.geoapify.com/v1/geocode/autocomplete?' . http_build_query($params);

            break;

        case 'route':
            // Výpočet trasy - jednodušší rozhraní
            header('Content-Type: application/json');
            $startLat = $_GET['start_lat'] ?? '';
            $startLon = $_GET['start_lon'] ?? '';
            $endLat = $_GET['end_lat'] ?? '';
            $endLon = $_GET['end_lon'] ?? '';
            $mode = $_GET['mode'] ?? 'drive';

            if (empty($startLat) || empty($startLon) || empty($endLat) || empty($endLon)) {
                throw new Exception('Chybí parametry start_lat, start_lon, end_lat, end_lon');
            }

            // Validace souřadnic
            if (!is_numeric($startLat) || !is_numeric($startLon) || !is_numeric($endLat) || !is_numeric($endLon)) {
                throw new Exception('Neplatné souřadnice');
            }

            // Formát pro Geoapify: lat1,lon1|lat2,lon2
            $waypoints = "{$startLat},{$startLon}|{$endLat},{$endLon}";

            $url = 'https://api.geoapify.com/v1/routing?' . http_build_query([
                'waypoints' => $waypoints,
                'mode' => $mode,
                'apiKey' => $apiKey
            ]);

            break;

        case 'routing':
            // Výpočet trasy mezi dvěma body - POUŽITÍ OSRM (open-source, ZDARMA)
            header('Content-Type: application/json');
            $waypoints = $_GET['waypoints'] ?? '';
            $mode = $_GET['mode'] ?? 'drive';

            if (empty($waypoints)) {
                throw new Exception('Chybí parametr waypoints');
            }

            // Validace waypoints formátu: lat1,lon1|lat2,lon2
            if (!preg_match('/^-?\d+\.?\d*,-?\d+\.?\d*\|-?\d+\.?\d*,-?\d+\.?\d*$/', $waypoints)) {
                throw new Exception('Neplatný formát waypoints');
            }

            // Rozdělit waypoints
            list($start, $end) = explode('|', $waypoints);
            list($startLat, $startLon) = explode(',', $start);
            list($endLat, $endLon) = explode(',', $end);

            // ============================================
            // PRIMARY: OSRM (Open Source Routing Machine)
            // + ZDARMA, bez API klíče
            // + Rychlé, přesné
            // + Používá OpenStreetMap data
            // ============================================
            $osrmUrl = "https://router.project-osrm.org/route/v1/driving/{$startLon},{$startLat};{$endLon},{$endLat}?overview=full&geometries=geojson";

            $context = stream_context_create([
                'http' => [
                    'timeout' => 10, // Zvýšen timeout z 5s na 10s
                    'user_agent' => 'WGS Service/1.0'
                ]
            ]);

            $osrmResponse = @file_get_contents($osrmUrl, false, $context);

            if ($osrmResponse !== false) {
                $osrmData = json_decode($osrmResponse, true);

                if (isset($osrmData['code']) && $osrmData['code'] === 'Ok' && isset($osrmData['routes'][0])) {
                    $route = $osrmData['routes'][0];

                    // Konverze OSRM formátu na GeoJSON (kompatibilní s frontendem)
                    $coordinates = [];
                    if (isset($route['geometry']['coordinates']) && is_array($route['geometry']['coordinates'])) {
                        $coordinates = array_map(static function ($point) {
                            if (!is_array($point) || count($point) < 2) {
                                return $point;
                            }
                            return [
                                (float) $point[0],
                                (float) $point[1]
                            ];
                        }, $route['geometry']['coordinates']);
                    }

                    $geojson = [
                        'type' => 'FeatureCollection',
                        'features' => [[
                            'type' => 'Feature',
                            'properties' => [
                                'distance' => $route['distance'], // v metrech
                                'time' => $route['duration'], // v sekundách (přejmenováno z duration)
                                'provider' => 'OSRM'
                            ],
                            'geometry' => [
                                'type' => 'LineString',
                                'coordinates' => $coordinates
                            ]
                        ]]
                    ];

                    echo json_encode($geojson);
                    exit;
                }
            }

            // ============================================
            // FALLBACK: Geoapify (pokud je API klíč)
            // ============================================
            if ($apiKey) {
                $url = 'https://api.geoapify.com/v1/routing?' . http_build_query([
                    'waypoints' => $waypoints,
                    'mode' => $mode,
                    'apiKey' => $apiKey
                ]);

                $geoResponse = @file_get_contents($url, false, $context);

                if ($geoResponse !== false) {
                    echo $geoResponse;
                    exit;
                }
            }

            // ============================================
            // FALLBACK 2: Vzdušná čára (jako poslední možnost)
            // ============================================
            $distance = haversineDistance($startLat, $startLon, $endLat, $endLon);

            $geojson = [
                'type' => 'FeatureCollection',
                'features' => [[
                    'type' => 'Feature',
                    'properties' => [
                        'distance' => $distance * 1000, // konverze km → metry
                        'provider' => 'haversine',
                        'warning' => 'Vzdušná čára - routing API nedostupné'
                    ],
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [
                                (float) $startLon,
                                (float) $startLat
                            ],
                            [
                                (float) $endLon,
                                (float) $endLat
                            ]
                        ]
                    ]
                ]]
            ];

            echo json_encode($geojson);
            exit;

        case 'tile':
            // Map tiles - pro Leaflet
            $z = intval($_GET['z'] ?? 0);
            $x = intval($_GET['x'] ?? 0);
            $y = intval($_GET['y'] ?? 0);

            if ($z < 0 || $z > 20 || $x < 0 || $y < 0) {
                throw new Exception('Neplatné tile souřadnice');
            }

            // BEZPEČNOST: Uzavřít session pro tile requesty
            // Prevence session locking - Leaflet načítá mnoho tiles současně
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $url = "https://maps.geoapify.com/v1/tile/osm-carto/{$z}/{$x}/{$y}.png?apiKey={$apiKey}";

            // Pro tiles vracíme přímo obrázek
            header('Content-Type: image/png');
            $imageData = @file_get_contents($url, false, $context);

            if ($imageData === false) {
                throw new Exception('Chyba při načítání tile');
            }

            echo $imageData;
            exit;

        default:
            header('Content-Type: application/json');
            throw new Exception('Neplatná akce');
    }

    // Fetch data z Geoapify API
    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        throw new Exception('Chyba při komunikaci s Geoapify API');
    }

    // Vrácení odpovědi
    echo $response;

} catch (Exception $e) {
    // ✅ FIX: Ensure JSON Content-Type for error responses
    if (!headers_sent()) {
        header('Content-Type: application/json');
    }
    http_response_code(400);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
}
