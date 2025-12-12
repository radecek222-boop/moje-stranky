<?php
/**
 * API pro synchronizaci stavů a dat transportů
 * Dočasné API pro akci Techmission - bude odstraněno po akci
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Soubory pro uložení dat (jednoduchá implementace bez databáze)
$dataFile = __DIR__ . '/../logs/transport_data.json';

// GET - načíst data
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($dataFile)) {
        $data = json_decode(file_get_contents($dataFile), true);
        echo json_encode([
            'status' => 'success',
            'stavy' => $data['stavy'] ?? [],
            'transporty' => $data['transporty'] ?? null
        ]);
    } else {
        echo json_encode(['status' => 'success', 'stavy' => [], 'transporty' => null]);
    }
    exit;
}

// POST - uložit data
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Načíst existující data
    $data = [];
    if (file_exists($dataFile)) {
        $data = json_decode(file_get_contents($dataFile), true) ?: [];
    }

    // Aktualizovat stavy
    if (isset($_POST['stavy'])) {
        $stavy = json_decode($_POST['stavy'], true);
        if (is_array($stavy)) {
            $data['stavy'] = $stavy;
        }
    }

    // Aktualizovat transporty
    if (isset($_POST['transporty'])) {
        $transporty = json_decode($_POST['transporty'], true);
        if (is_array($transporty)) {
            $data['transporty'] = $transporty;
        }
    }

    $data['aktualizovano'] = date('Y-m-d H:i:s');

    // Uložit
    file_put_contents($dataFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

    echo json_encode(['status' => 'success', 'message' => 'Data ulozena']);
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Nepodporovana metoda']);
