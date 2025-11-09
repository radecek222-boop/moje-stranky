<?php
/**
 * Get Distance Controller
 * Počítá vzdálenost mezi dvěma adresami
 *
 * POZNÁMKA: Toto je MOCK implementace.
 * Pro produkční použití je potřeba:
 * 1. Získat Google Maps API klíč
 * 2. Povolit Distance Matrix API
 * 3. Odkomentovat skutečnou implementaci níže
 */

require_once __DIR__ . '/../../init.php';

header('Content-Type: application/json');

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

    // Načtení JSON dat
    $jsonData = file_get_contents('php://input');
    $data = json_decode($jsonData, true);

    if (!$data) {
        throw new Exception('Neplatná JSON data');
    }

    $origin = $data['origin'] ?? null;
    $destination = $data['destination'] ?? null;

    if (!$origin || !$destination) {
        throw new Exception('Chybí origin nebo destination');
    }

    // BEZPEČNOST: Validace délek
    if (strlen($origin) > 500 || strlen($destination) > 500) {
        throw new Exception('Adresy jsou příliš dlouhé');
    }

    /*
    ============================================================================
    PRODUKČNÍ IMPLEMENTACE S GOOGLE DISTANCE MATRIX API
    ============================================================================

    // Google Maps API klíč (nastavit v config.php nebo .env)
    $apiKey = getenv('GOOGLE_MAPS_API_KEY') ?: 'YOUR_API_KEY_HERE';

    if ($apiKey === 'YOUR_API_KEY_HERE') {
        throw new Exception('Google Maps API klíč není nakonfigurován');
    }

    // Sestavení URL pro Distance Matrix API
    $url = 'https://maps.googleapis.com/maps/api/distancematrix/json?' . http_build_query([
        'origins' => $origin,
        'destinations' => $destination,
        'mode' => 'driving',
        'language' => 'cs',
        'units' => 'metric',
        'key' => $apiKey
    ]);

    // Volání API
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception('Google Maps API vrátilo chybu');
    }

    $result = json_decode($response, true);

    if ($result['status'] !== 'OK') {
        throw new Exception('Distance Matrix API error: ' . $result['status']);
    }

    $element = $result['rows'][0]['elements'][0];

    if ($element['status'] !== 'OK') {
        throw new Exception('Nepodařilo se vypočítat vzdálenost');
    }

    echo json_encode([
        'status' => 'success',
        'distance' => $element['distance'],
        'duration' => $element['duration']
    ]);

    ============================================================================
    */

    // MOCK IMPLEMENTACE (pro testování bez API klíče)
    // Generuje náhodnou vzdálenost 10-100 km a čas 15-120 minut
    $distanceKm = rand(10, 100);
    $distanceMeters = $distanceKm * 1000;
    $durationMinutes = rand(15, 120);
    $durationSeconds = $durationMinutes * 60;

    echo json_encode([
        'status' => 'success',
        'distance' => [
            'value' => $distanceMeters,
            'text' => $distanceKm . ' km'
        ],
        'duration' => [
            'value' => $durationSeconds,
            'text' => $durationMinutes . ' min'
        ],
        '_mock' => true,
        '_message' => 'Toto je mock data. Pro produkci nakonfigurujte Google Maps API.'
    ]);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
