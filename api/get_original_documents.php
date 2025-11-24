<?php
/**
 * API endpoint pro načtení dokumentů z původní zakázky
 *
 * Slouží k zobrazení historie PDF z původní zakázky,
 * když je aktuální zakázka klonem (má original_reklamace_id).
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Kontrola přihlášení
    $isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);

    if (!$isLoggedIn) {
        http_response_code(401);
        die(json_encode([
            'status' => 'error',
            'message' => 'Neautorizovaný přístup'
        ]));
    }

    // Získat ID zakázky
    $reklamaceId = $_GET['reklamace_id'] ?? $_GET['id'] ?? null;

    if ($reklamaceId === null || $reklamaceId === '') {
        http_response_code(400);
        die(json_encode([
            'status' => 'error',
            'message' => 'Chybí ID zakázky'
        ]));
    }

    // Připojení k databázi
    $pdo = getDbConnection();

    // Načíst dokumenty z tabulky wgs_documents
    $stmt = $pdo->prepare("
        SELECT
            document_id,
            reklamace_id,
            document_type,
            file_path,
            file_name,
            file_size,
            created_at,
            created_by
        FROM wgs_documents
        WHERE reklamace_id = :reklamace_id
        ORDER BY created_at DESC
    ");

    $stmt->execute(['reklamace_id' => $reklamaceId]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formátovat data dokumentů
    $formattedDocuments = array_map(function($doc) {
        return [
            'document_id' => $doc['document_id'],
            'reklamace_id' => $doc['reklamace_id'],
            'type' => $doc['document_type'],
            'file_path' => $doc['file_path'],
            'file_name' => $doc['file_name'],
            'file_size' => $doc['file_size'],
            'created_at' => $doc['created_at'],
            'created_by' => $doc['created_by']
        ];
    }, $documents);

    echo json_encode([
        'status' => 'success',
        'documents' => $formattedDocuments,
        'count' => count($formattedDocuments)
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

} catch (PDOException $e) {
    error_log("Database error in get_original_documents.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Chyba při načítání dokumentů'
    ]);
} catch (Exception $e) {
    error_log("Error in get_original_documents.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Chyba serveru'
    ]);
}
?>
