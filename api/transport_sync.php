<?php
/**
 * API pro synchronizaci stavů transportů
 * Dočasné API pro akci Techmission - bude odstraněno po akci
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

// Soubor pro uložení stavů (jednoduchá implementace bez databáze)
$stavyFile = __DIR__ . '/../logs/transport_stavy.json';

// GET - načíst stavy
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    if (file_exists($stavyFile)) {
        $stavy = json_decode(file_get_contents($stavyFile), true);
        echo json_encode(['status' => 'success', 'stavy' => $stavy]);
    } else {
        echo json_encode(['status' => 'success', 'stavy' => []]);
    }
    exit;
}

// POST - uložit stav
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_POST['id'] ?? null;
    $stav = $_POST['stav'] ?? null;
    $cas = $_POST['cas'] ?? null;

    if (!$id || !$stav) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Chybí id nebo stav']);
        exit;
    }

    // Načíst existující stavy
    $stavy = [];
    if (file_exists($stavyFile)) {
        $stavy = json_decode(file_get_contents($stavyFile), true) ?: [];
    }

    // Aktualizovat stav
    $stavy[$id] = [
        'stav' => $stav,
        'cas' => $cas,
        'aktualizovano' => date('Y-m-d H:i:s')
    ];

    // Uložit
    file_put_contents($stavyFile, json_encode($stavy, JSON_PRETTY_PRINT));

    echo json_encode(['status' => 'success', 'message' => 'Stav uložen']);
    exit;
}

http_response_code(405);
echo json_encode(['status' => 'error', 'message' => 'Nepodporovaná metoda']);
