<?php
/**
 * Get Distance Controller
 * Počítá vzdálenost mezi dvěma adresami pomocí Geoapify API
 */

require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/csrf_helper.php';

header('Content-Type: application/json');
// PERFORMANCE: Cache-Control header (5 minut cache pro distance calculations)
// Vzdálenost mezi adresami se nemění často
header('Cache-Control: private, max-age=300'); // 5 minut

/**
 * Převede adresu na GPS souřadnice pomocí Nominatim (OpenStreetMap)
 * PERFORMANCE FIX: Přidán APCu cache (TTL 24h)
 * @param string $address Adresa k převodu
 * @return array|null ['lat' => float, 'lon' => float] nebo null při chybě
 */
function geocodeAddress($address) {
    try {
        // CACHE: Kontrola APCu cache (TTL 24 hodin)
        $cacheKey = 'geocode_' . md5(strtolower(trim($address)));

        if (function_exists('apcu_fetch')) {
            $cached = apcu_fetch($cacheKey);
            if ($cached !== false) {
                error_log("📦 Cache HIT for geocoding: $address");
                return $cached;
            }
        }

        error_log("🌐 Cache MISS - Fetching geocoding for: $address");

        // FIX: Přímo volat Nominatim API místo proxy
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
            error_log("cURL error for geocoding {$address}: {$curlError}");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("HTTP {$httpCode} error for geocoding {$address}");
            return null;
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['features']) || empty($data['features'])) {
            error_log("No geocoding results for: {$address}");
            return null;
        }

        // Vrátí první (nejrelevantnější) výsledek
        $feature = $data['features'][0];
        $coords = $feature['geometry']['coordinates'];

        $result = [
            'lat' => $coords[1],
            'lon' => $coords[0]
        ];

        // CACHE: Uložit do APCu cache (TTL 24 hodin = 86400 sekund)
        if (function_exists('apcu_store')) {
            apcu_store($cacheKey, $result, 86400);
            error_log("💾 Cached geocoding result for: {$address} -> {$result['lat']}, {$result['lon']}");
        }

        return $result;

    } catch (Exception $e) {
        error_log('Geocoding error: ' . $e->getMessage());
        return null;
    }
}

/**
 * Vypočítá trasu mezi dvěma body pomocí OSRM (Open Source Routing Machine)
 * @param float $startLat Začátek - zeměpisná šířka
 * @param float $startLon Začátek - zeměpisná délka
 * @param float $endLat Konec - zeměpisná šířka
 * @param float $endLon Konec - zeměpisná délka
 * @return array|null ['distance' => int (metry), 'time' => int (sekundy)] nebo null
 */
function calculateRoute($startLat, $startLon, $endLat, $endLon) {
    try {
        // FIX: Přímo volat OSRM API místo proxy
        // OSRM je open-source routing engine (ZDARMA, bez API klíče)
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
            error_log("cURL error for routing: {$curlError}");
            return null;
        }

        if ($httpCode !== 200) {
            error_log("HTTP {$httpCode} error for routing");
            return null;
        }

        $data = json_decode($response, true);

        if (!$data || !isset($data['code']) || $data['code'] !== 'Ok' || !isset($data['routes'][0])) {
            error_log("Invalid OSRM response or no route found");
            return null;
        }

        $route = $data['routes'][0];

        return [
            'distance' => (int)$route['distance'], // metry
            'time' => (int)$route['duration'] // sekundy
        ];

    } catch (Exception $e) {
        error_log('Routing error: ' . $e->getMessage());
        return null;
    }
}

try {
    // BEZPEČNOST: Kontrola přihlášení
    $isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'error' => 'Neautorizovaný přístup'
        ]);
        exit;
    }

    // Kontrola metody
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Povolena pouze POST metoda');
    }

    // Načtení JSON dat (MUSÍ být PŘED requireCSRF pro JSON API)
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    if (!$data) {
        throw new Exception('Neplatná JSON data');
    }

    // Extrakce CSRF tokenu z JSON pro requireCSRF()
    if (isset($data['csrf_token'])) {
        $_POST['csrf_token'] = $data['csrf_token'];
    }

    // BEZPEČNOST: CSRF ochrana
    requireCSRF();

    $origin = $data['origin'] ?? null;
    $destination = $data['destination'] ?? null;

    if (!$origin || !$destination) {
        throw new Exception('Chybí origin nebo destination');
    }

    // BEZPEČNOST: Validace délek
    if (strlen($origin) > 500 || strlen($destination) > 500) {
        throw new Exception('Adresy jsou příliš dlouhé');
    }

    // FIX: Uvolnit session PŘED cURL requesty (zabraňuje session locking)
    // Session již není potřeba - autentizace a CSRF validace proběhly
    session_write_close();

    // Krok 1: Geocoding - převod obou adres na GPS souřadnice
    $originCoords = geocodeAddress($origin);
    $destCoords = geocodeAddress($destination);

    if (!$originCoords || !$destCoords) {
        throw new Exception('Nepodařilo se převést adresy na GPS souřadnice');
    }

    // Krok 2: Routing - výpočet trasy mezi body
    $routeData = calculateRoute(
        $originCoords['lat'],
        $originCoords['lon'],
        $destCoords['lat'],
        $destCoords['lon']
    );

    if (!$routeData) {
        throw new Exception('Nepodařilo se vypočítat trasu');
    }

    // Formátování výstupu
    $distanceKm = round($routeData['distance'] / 1000, 1);
    $durationMinutes = round($routeData['time'] / 60);

    echo json_encode([
        'status' => 'success',
        'distance' => [
            'value' => $routeData['distance'],
            'text' => $distanceKm . ' km'
        ],
        'duration' => [
            'value' => $routeData['time'],
            'text' => $durationMinutes . ' min'
        ]
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
