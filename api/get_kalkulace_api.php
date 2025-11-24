<?php
/**
 * API pro načtení kalkulace z databáze
 *
 * Načítá kalkulace_data z wgs_reklamace
 * Používá se v protokolu pro generování PDF PRICELIST
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Uživatel není přihlášen'
    ]);
    exit;
}

// PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
session_write_close();

try {
    $pdo = getDbConnection();

    // Zapnout error mode pro debugging
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Získat reklamace_id z GET parametru
    $reklamaceId = $_GET['reklamace_id'] ?? null;

    if (!$reklamaceId) {
        throw new Exception('Chybí parametr reklamace_id');
    }

    // Sanitizace
    $reklamaceId = trim($reklamaceId);

    // Log pro debugging
    error_log("GET_KALKULACE: Hledám reklamaci s ID: " . $reklamaceId);

    // Najít reklamaci v databázi
    $stmt = $pdo->prepare("
        SELECT
            id,
            reklamace_id,
            jmeno,
            adresa,
            telefon,
            email,
            model,
            kalkulace_data
        FROM wgs_reklamace
        WHERE reklamace_id = :reklamace_id OR cislo = :cislo OR id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':cislo' => $reklamaceId,
        ':id' => is_numeric($reklamaceId) ? intval($reklamaceId) : 0
    ]);

    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    // Log výsledku
    error_log("GET_KALKULACE: Nalezeno: " . ($reklamace ? 'ANO' : 'NE'));

    if (!$reklamace) {
        echo json_encode([
            'success' => false,
            'error' => 'Reklamace nenalezena',
            'reklamace_id' => $reklamaceId
        ]);
        exit;
    }

    // Pokud kalkulace_data je null nebo prázdné
    if (empty($reklamace['kalkulace_data'])) {
        echo json_encode([
            'success' => true,
            'has_kalkulace' => false,
            'reklamace_id' => $reklamaceId,
            'message' => 'Kalkulace nebyla nalezena'
        ]);
        exit;
    }

    // Dekódovat JSON kalkulace
    $kalkulaceData = json_decode($reklamace['kalkulace_data'], true);

    if (json_last_error() !== JSON_ERROR_NONE) {
        throw new Exception('Chyba při dekódování kalkulace: ' . json_last_error_msg());
    }

    // Vrátit úspěšnou odpověď
    echo json_encode([
        'success' => true,
        'has_kalkulace' => true,
        'kalkulace' => $kalkulaceData,
        'zakaznik' => [
            'jmeno' => $reklamace['jmeno'],
            'adresa' => $reklamace['adresa'],
            'telefon' => $reklamace['telefon'],
            'email' => $reklamace['email'],
            'model' => $reklamace['model']
        ],
        'reklamace_id' => $reklamaceId
    ]);

} catch (PDOException $e) {
    error_log("GET_KALKULACE API - Database error: " . $e->getMessage());
    error_log("GET_KALKULACE API - Stack trace: " . $e->getTraceAsString());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Chyba databáze',
        'detail' => $e->getMessage() // Přidat detaily pro debugging
    ]);

} catch (Exception $e) {
    error_log("GET_KALKULACE API - Error: " . $e->getMessage());
    error_log("GET_KALKULACE API - Stack trace: " . $e->getTraceAsString());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'detail' => $e->getTraceAsString()
    ]);
}
?>
