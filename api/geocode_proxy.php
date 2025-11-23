<?php
/**
 * Geoapify API Proxy + OSRM Routing
 * Skrývá API klíč před klienty - bezpečnostní opatření
 * Používá OSRM pro routing (zdarma, open-source)
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

// ========== HELPERS ==========
/**
 * Kontrola, jestli je Geoapify klíč reálně nastavený
 */
function resolveGeoapifyKey() {
    if (!defined('GEOAPIFY_KEY')) {
        return null;
    }

    $key = trim((string) GEOAPIFY_KEY);
    $placeholderValues = [
        '', 'null', 'undefined', 'not-set',
        'your_geoapify_api_key',
        'change-this-in-production',
        'placeholder_geoapify_key',
        'skutecny_api_klic',
        'skutečný_api_klíč_z_geoapify',
        'add-real-key'
    ];

    $lowerKey = strtolower($key);
    $isPlaceholder = in_array($lowerKey, $placeholderValues, true);

    if ($key === '' || $isPlaceholder || strlen($key) < 15) {
        return null;
    }

    return $key;
}

function buildEmptyFeatureCollection($provider) {
    return [
        'type' => 'FeatureCollection',
        'provider' => $provider,
        'features' => []
    ];
}

function normalizeNominatimFeatures(array $data) {
    $collection = buildEmptyFeatureCollection('nominatim');

    if (!isset($data['features']) || !is_array($data['features'])) {
        return $collection;
    }

    foreach ($data['features'] as $feature) {
        $properties = $feature['properties'] ?? [];
        $address = $properties['address'] ?? [];

        $collection['features'][] = [
            'type' => 'Feature',
            'geometry' => $feature['geometry'] ?? null,
            'properties' => [
                'street' => $address['road']
                    ?? $address['pedestrian']
                    ?? $properties['name']
                    ?? '',
                'housenumber' => $address['house_number'] ?? '',
                'city' => $address['city']
                    ?? $address['town']
                    ?? $address['village']
                    ?? $address['hamlet']
                    ?? '',
                'postcode' => $address['postcode'] ?? '',
                'name' => $properties['display_name'] ?? ($properties['name'] ?? ''),
                'provider' => 'nominatim'
            ]
        ];
    }

    return $collection;
}

function normalizePhotonFeatures(array $data, $type = 'street') {
    $collection = buildEmptyFeatureCollection('photon');

    if (!isset($data['features']) || !is_array($data['features'])) {
        return $collection;
    }

    foreach ($data['features'] as $feature) {
        $properties = $feature['properties'] ?? [];
        $collection['features'][] = [
            'type' => 'Feature',
            'geometry' => $feature['geometry'] ?? null,
            'properties' => [
                'street' => $properties['street'] ?? ($type === 'street' ? ($properties['name'] ?? '') : ''),
                'housenumber' => $properties['housenumber'] ?? '',
                'city' => $properties['city'] ?? $properties['name'] ?? '',
                'postcode' => $properties['postcode'] ?? '',
                'name' => $properties['name'] ?? '',
                'provider' => 'photon'
            ]
        ];
    }

    return $collection;
}

function buildQuery(array $params) {
    return http_build_query($params, '', '&', PHP_QUERY_RFC3986);
}

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
    $apiKey = resolveGeoapifyKey();
    $contactEmail = defined('NOMINATIM_CONTACT_EMAIL') ? NOMINATIM_CONTACT_EMAIL : 'reklamace@wgs-service.cz';
    $countryCodes = 'cz,sk';
    $fallbackBbox = '11.5,47.0,24.5,51.5'; // CZ + SK

    // ✅ FIX: Helper pro HTTP requesty s cURL (podporuje proxy)
    function fetchUrl($url, $userAgent = 'WGS Service/1.0', $timeout = 8) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

        // Automatická detekce proxy z environment
        if (getenv('https_proxy') || getenv('HTTPS_PROXY')) {
            $proxy = getenv('https_proxy') ?: getenv('HTTPS_PROXY');
            curl_setopt($ch, CURLOPT_PROXY, $proxy);
            error_log("Using proxy: {$proxy} for URL: {$url}");
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($response === false) {
            error_log("cURL error for {$url}: {$curlError}");
            return false;
        }

        if ($httpCode >= 400) {
            error_log("HTTP {$httpCode} error for {$url}");
            return false;
        }

        return $response;
    }

    $userAgent = 'WGS Service/1.0';
    if (!empty($contactEmail)) {
        $userAgent .= ' (contact: ' . $contactEmail . ')';
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

            if (!$apiKey) {
                $url = 'https://nominatim.openstreetmap.org/search?' . buildQuery([
                    'q' => $address,
                    'format' => 'geojson',
                    'limit' => 5,
                    'addressdetails' => 1,
                    'countrycodes' => $countryCodes,
                    'email' => $contactEmail,
                    'accept-language' => 'cs'
                ]);

                $response = fetchUrl($url, $userAgent);

                if ($response === false) {
                    throw new Exception('Chyba při komunikaci s Nominatim API');
                }

                $data = json_decode($response, true);

                if (!is_array($data)) {
                    throw new Exception('Neplatná odpověď z Nominatim API');
                }

                echo json_encode(normalizeNominatimFeatures($data));
                exit;
            }

            $url = 'https://api.geoapify.com/v1/geocode/search?' . buildQuery([
                'text' => $address,
                'apiKey' => $apiKey,
                'format' => 'geojson'
            ]);

            break;

        case 'autocomplete':
            // Našeptávač adres
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

            if (!$apiKey) {
                $layers = $type === 'city' ? 'city,locality' : 'street,address';
                $url = 'https://photon.komoot.io/api/?' . buildQuery([
                    'q' => $text,
                    'limit' => 5,
                    'lang' => 'cs',
                    'layer' => $layers,
                    'bbox' => $fallbackBbox
                ]);

                $response = fetchUrl($url, $userAgent);

                if ($response === false) {
                    throw new Exception('Chyba při komunikaci s Photon API');
                }

                $data = json_decode($response, true);

                if (!is_array($data)) {
                    throw new Exception('Neplatná odpověď z Photon API');
                }

                echo json_encode(normalizePhotonFeatures($data, $type));
                exit;
            }

            $params = [
                'text' => $text,
                'apiKey' => $apiKey,
                'format' => 'geojson',
                'limit' => 5,
                'lang' => 'cs' // ✅ FIX: České názvy míst (Praha místo Capital city)
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
            // Výpočet trasy - jednodušší rozhraní (alias pro 'routing' s kompatibilitou parametrů)
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

            // ============================================
            // PRIMARY: OSRM (Open Source Routing Machine)
            // + ZDARMA, bez API klíče
            // + Rychlé, přesné
            // + Používá OpenStreetMap data
            // ============================================
            $osrmUrl = "https://router.project-osrm.org/route/v1/driving/{$startLon},{$startLat};{$endLon},{$endLat}?overview=full&geometries=geojson";

            $osrmResponse = fetchUrl($osrmUrl, $userAgent, 10);

            if ($osrmResponse !== false) {
                $osrmData = json_decode($osrmResponse, true);

                if (isset($osrmData['code']) && $osrmData['code'] === 'Ok' && isset($osrmData['routes'][0])) {
                    $route = $osrmData['routes'][0];

                    // Konverze OSRM formátu na kompatibilní GeoJSON
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
                        'routes' => [[
                            'distance' => $route['distance'], // v metrech
                            'duration' => $route['duration'] // v sekundách
                        ]],
                        'type' => 'FeatureCollection',
                        'features' => [[
                            'type' => 'Feature',
                            'properties' => [
                                'distance' => $route['distance'],
                                'time' => $route['duration'],
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
                $waypoints = "{$startLat},{$startLon}|{$endLat},{$endLon}";
                $url = 'https://api.geoapify.com/v1/routing?' . http_build_query([
                    'waypoints' => $waypoints,
                    'mode' => $mode,
                    'apiKey' => $apiKey
                ]);

                $geoResponse = fetchUrl($url, $userAgent);

                if ($geoResponse !== false) {
                    $geoData = json_decode($geoResponse, true);

                    // Přidat kompatibilní routes[] formát
                    if (isset($geoData['features'][0]['properties'])) {
                        $props = $geoData['features'][0]['properties'];
                        $geoData['routes'] = [[
                            'distance' => $props['distance'] ?? 0,
                            'duration' => $props['time'] ?? 0
                        ]];
                    }

                    echo json_encode($geoData);
                    exit;
                }
            }

            // ============================================
            // FALLBACK 2: Vzdušná čára (jako poslední možnost)
            // ============================================
            $distance = haversineDistance($startLat, $startLon, $endLat, $endLon);

            $geojson = [
                'routes' => [[
                    'distance' => $distance * 1000, // konverze km → metry
                    'duration' => 0
                ]],
                'type' => 'FeatureCollection',
                'features' => [[
                    'type' => 'Feature',
                    'properties' => [
                        'distance' => $distance * 1000,
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

        case 'routing':
            // Výpočet trasy mezi dvěma body - POUŽITÍ OSRM (open-source, ZDARMA)
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

            $osrmResponse = fetchUrl($osrmUrl, $userAgent, 10);

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

                $geoResponse = fetchUrl($url, $userAgent);

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

            if (!$apiKey && $z > 19) {
                $z = 19; // OSM standard tiles podporují max zoom 19
            }

            $url = $apiKey
                ? "https://maps.geoapify.com/v1/tile/osm-carto/{$z}/{$x}/{$y}.png?apiKey={$apiKey}"
                : "https://tile.openstreetmap.org/{$z}/{$x}/{$y}.png";

            // Pro tiles vracíme přímo obrázek
            header('Content-Type: image/png');
            $imageData = fetchUrl($url, $userAgent);

            if ($imageData === false) {
                throw new Exception('Chyba při načítání tile');
            }

            echo $imageData;
            exit;

        default:
            throw new Exception('Neplatná akce');
    }

    // Fetch data z Geoapify API
    $response = fetchUrl($url, $userAgent);

    if ($response === false) {
        // ✅ FALLBACK: Pokud Geoapify selže (např. síťový problém), zkusit alternativu
        error_log('⚠️ Geoapify API failed, trying fallback for action: ' . $action);

        // Pro autocomplete zkusit Photon API jako fallback
        if ($action === 'autocomplete') {
            $layers = $type === 'city' ? 'city,locality' : 'street,address';
            $fallbackUrl = 'https://photon.komoot.io/api/?' . buildQuery([
                'q' => $text,
                'limit' => 5,
                'lang' => 'cs',
                'layer' => $layers,
                'bbox' => $fallbackBbox
            ]);

            $fallbackResponse = fetchUrl($fallbackUrl, $userAgent);

            if ($fallbackResponse !== false) {
                $fallbackData = json_decode($fallbackResponse, true);
                if (is_array($fallbackData)) {
                    echo json_encode(normalizePhotonFeatures($fallbackData, $type));
                    exit;
                }
            }
        }

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
