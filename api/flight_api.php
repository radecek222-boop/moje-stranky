<?php
/**
 * API pro sledování letů
 * Používá AviationStack API (free tier: 100 requests/month)
 *
 * Alternativně lze použít:
 * - OpenSky Network (free, omezená data)
 * - AeroDataBox via RapidAPI
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Cache-Control: no-cache, no-store, must-revalidate');

// API klíč - získejte na https://aviationstack.com/
// Free tier: 100 requests/month
$apiKey = getenv('AVIATIONSTACK_API_KEY') ?: '';

// Soubor pro cache letů (snížení počtu API volání)
$cacheDir = __DIR__ . '/../logs/flight_cache/';
if (!is_dir($cacheDir)) {
    mkdir($cacheDir, 0755, true);
}

// GET - vyhledat let
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $cisloLetu = strtoupper(trim($_GET['let'] ?? ''));

    if (empty($cisloLetu)) {
        echo json_encode(['status' => 'error', 'message' => 'Chybi cislo letu']);
        exit;
    }

    // Normalizovat číslo letu (odstranit mezery, pomlčky)
    $cisloLetu = preg_replace('/[\s\-]/', '', $cisloLetu);

    // Pokusit se najít v cache (platnost 5 minut)
    $cacheFile = $cacheDir . md5($cisloLetu) . '.json';
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile)) < 300) {
        $cached = json_decode(file_get_contents($cacheFile), true);
        if ($cached) {
            $cached['zdroj'] = 'cache';
            echo json_encode($cached);
            exit;
        }
    }

    // Pokud není API klíč, vrátit demo data nebo chybu
    if (empty($apiKey)) {
        // Demo režim - simulovat odpověď pro testování
        $demoData = getDemoFlightData($cisloLetu);
        if ($demoData) {
            echo json_encode($demoData);
        } else {
            echo json_encode([
                'status' => 'error',
                'message' => 'API klic neni nastaven. Nastavte AVIATIONSTACK_API_KEY v .env',
                'demo' => true
            ]);
        }
        exit;
    }

    // Volat AviationStack API
    $url = "http://api.aviationstack.com/v1/flights?access_key=" . urlencode($apiKey) . "&flight_iata=" . urlencode($cisloLetu);

    $context = stream_context_create([
        'http' => [
            'timeout' => 10,
            'ignore_errors' => true
        ]
    ]);

    $response = @file_get_contents($url, false, $context);

    if ($response === false) {
        echo json_encode(['status' => 'error', 'message' => 'Nelze se pripojit k API']);
        exit;
    }

    $data = json_decode($response, true);

    if (!$data || isset($data['error'])) {
        echo json_encode([
            'status' => 'error',
            'message' => $data['error']['message'] ?? 'Chyba API'
        ]);
        exit;
    }

    if (empty($data['data'])) {
        echo json_encode(['status' => 'error', 'message' => 'Let nenalezen']);
        exit;
    }

    // Zpracovat první nalezený let
    $let = $data['data'][0];

    $vysledek = [
        'status' => 'success',
        'cisloLetu' => $cisloLetu,
        'aerolinky' => $let['airline']['name'] ?? '',
        'kodAerolinky' => $let['airline']['iata'] ?? '',
        'odlet' => [
            'letiste' => $let['departure']['airport'] ?? '',
            'iata' => $let['departure']['iata'] ?? '',
            'planovano' => $let['departure']['scheduled'] ?? '',
            'odhadovano' => $let['departure']['estimated'] ?? '',
            'skutecne' => $let['departure']['actual'] ?? '',
            'terminal' => $let['departure']['terminal'] ?? '',
            'gate' => $let['departure']['gate'] ?? ''
        ],
        'prilet' => [
            'letiste' => $let['arrival']['airport'] ?? '',
            'iata' => $let['arrival']['iata'] ?? '',
            'planovano' => $let['arrival']['scheduled'] ?? '',
            'odhadovano' => $let['arrival']['estimated'] ?? '',
            'skutecne' => $let['arrival']['actual'] ?? '',
            'terminal' => $let['arrival']['terminal'] ?? '',
            'gate' => $let['arrival']['gate'] ?? '',
            'bagaz' => $let['arrival']['baggage'] ?? ''
        ],
        'stavLetu' => $let['flight_status'] ?? 'unknown',
        'zdroj' => 'api'
    ];

    // Uložit do cache
    file_put_contents($cacheFile, json_encode($vysledek, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode($vysledek);
    exit;
}

/**
 * Demo data pro testování bez API klíče
 */
function getDemoFlightData($cisloLetu) {
    // Simulovat několik demo letů pro Prague Airport
    $demoLety = [
        'OK123' => [
            'status' => 'success',
            'cisloLetu' => 'OK123',
            'aerolinky' => 'Czech Airlines',
            'kodAerolinky' => 'OK',
            'odlet' => [
                'letiste' => 'Paris Charles de Gaulle',
                'iata' => 'CDG',
                'planovano' => date('Y-m-d') . 'T18:30:00+00:00',
                'odhadovano' => date('Y-m-d') . 'T18:35:00+00:00',
                'skutecne' => null,
                'terminal' => '2E',
                'gate' => 'K45'
            ],
            'prilet' => [
                'letiste' => 'Prague Vaclav Havel',
                'iata' => 'PRG',
                'planovano' => date('Y-m-d') . 'T20:15:00+00:00',
                'odhadovano' => date('Y-m-d') . 'T20:20:00+00:00',
                'skutecne' => null,
                'terminal' => '2',
                'gate' => null,
                'bagaz' => '5'
            ],
            'stavLetu' => 'scheduled',
            'zdroj' => 'demo'
        ],
        'FR1234' => [
            'status' => 'success',
            'cisloLetu' => 'FR1234',
            'aerolinky' => 'Ryanair',
            'kodAerolinky' => 'FR',
            'odlet' => [
                'letiste' => 'London Stansted',
                'iata' => 'STN',
                'planovano' => date('Y-m-d') . 'T19:00:00+00:00',
                'odhadovano' => date('Y-m-d') . 'T19:15:00+00:00',
                'skutecne' => date('Y-m-d') . 'T19:10:00+00:00',
                'terminal' => '1',
                'gate' => '42'
            ],
            'prilet' => [
                'letiste' => 'Prague Vaclav Havel',
                'iata' => 'PRG',
                'planovano' => date('Y-m-d') . 'T22:00:00+00:00',
                'odhadovano' => date('Y-m-d') . 'T22:15:00+00:00',
                'skutecne' => null,
                'terminal' => '1',
                'gate' => null,
                'bagaz' => null
            ],
            'stavLetu' => 'active',
            'zdroj' => 'demo'
        ],
        'W62345' => [
            'status' => 'success',
            'cisloLetu' => 'W62345',
            'aerolinky' => 'Wizz Air',
            'kodAerolinky' => 'W6',
            'odlet' => [
                'letiste' => 'Budapest Ferenc Liszt',
                'iata' => 'BUD',
                'planovano' => date('Y-m-d') . 'T14:00:00+00:00',
                'odhadovano' => date('Y-m-d') . 'T14:00:00+00:00',
                'skutecne' => date('Y-m-d') . 'T14:05:00+00:00',
                'terminal' => '2B',
                'gate' => '28'
            ],
            'prilet' => [
                'letiste' => 'Prague Vaclav Havel',
                'iata' => 'PRG',
                'planovano' => date('Y-m-d') . 'T15:10:00+00:00',
                'odhadovano' => date('Y-m-d') . 'T15:05:00+00:00',
                'skutecne' => date('Y-m-d') . 'T15:02:00+00:00',
                'terminal' => '1',
                'gate' => 'C12',
                'bagaz' => '3'
            ],
            'stavLetu' => 'landed',
            'zdroj' => 'demo'
        ]
    ];

    // Vrátit demo data pokud existují
    if (isset($demoLety[$cisloLetu])) {
        return $demoLety[$cisloLetu];
    }

    // Generovat náhodná demo data pro jakékoliv číslo letu
    $statusy = ['scheduled', 'active', 'landed', 'delayed'];
    $nahodnyStatus = $statusy[array_rand($statusy)];

    $hodina = rand(6, 23);
    $minuta = rand(0, 59);
    $casPriletu = sprintf('%02d:%02d', $hodina, $minuta);

    return [
        'status' => 'success',
        'cisloLetu' => $cisloLetu,
        'aerolinky' => 'Demo Airline',
        'kodAerolinky' => substr($cisloLetu, 0, 2),
        'odlet' => [
            'letiste' => 'Demo Airport',
            'iata' => 'DEM',
            'planovano' => date('Y-m-d') . 'T' . sprintf('%02d:%02d:00+00:00', $hodina - 2, $minuta),
            'odhadovano' => null,
            'skutecne' => null,
            'terminal' => '1',
            'gate' => 'A' . rand(1, 50)
        ],
        'prilet' => [
            'letiste' => 'Prague Vaclav Havel',
            'iata' => 'PRG',
            'planovano' => date('Y-m-d') . 'T' . $casPriletu . ':00+00:00',
            'odhadovano' => date('Y-m-d') . 'T' . $casPriletu . ':00+00:00',
            'skutecne' => $nahodnyStatus === 'landed' ? date('Y-m-d') . 'T' . $casPriletu . ':00+00:00' : null,
            'terminal' => rand(1, 2),
            'gate' => null,
            'bagaz' => (string)rand(1, 8)
        ],
        'stavLetu' => $nahodnyStatus,
        'zdroj' => 'demo'
    ];
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Nepodporovana metoda']);
