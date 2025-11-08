<?php
/**
 * Load Controller
 * Načítání reklamací, fotek a dokumentů pro seznam.php
 */

require_once __DIR__ . '/../../init.php';

header('Content-Type: application/json');

try {
    // BEZPEČNOST: Kontrola autentizace - reklamace jsou citlivá data
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'error' => 'Neautorizovaný přístup. Přihlaste se prosím.'
        ]);
        exit;
    }

    // Získání filtru status
    $status = $_GET['status'] ?? 'all';

    // Databázové připojení
    $pdo = getDbConnection();

    // Sestavení WHERE klauzule podle statusu
    $whereClause = '';
    if ($status !== 'all') {
        $whereClause = "WHERE r.status = :status";
    }

    // Načtení reklamací
    $sql = "
        SELECT
            r.*,
            r.id as claim_id
        FROM wgs_reklamace r
        $whereClause
        ORDER BY r.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    if ($status !== 'all') {
        $stmt->execute([':status' => $status]);
    } else {
        $stmt->execute();
    }

    $reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pro každou reklamaci načíst fotky a dokumenty
    foreach ($reklamace as &$record) {
        $reklamaceId = $record['reklamace_id'] ?? $record['cislo'] ?? $record['id'];

        // Načtení fotek
        $stmt = $pdo->prepare("
            SELECT
                id, photo_id, reklamace_id, section_name,
                photo_path, file_path, file_name,
                photo_order, photo_type, uploaded_at
            FROM wgs_photos
            WHERE reklamace_id = :reklamace_id
            ORDER BY photo_order ASC, uploaded_at ASC
        ");
        $stmt->execute([':reklamace_id' => $reklamaceId]);
        $photos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Přidat fotky k záznamu
        $record['photos'] = $photos;

        // Načtení dokumentů (PDF protokoly)
        $stmt = $pdo->prepare("
            SELECT
                id, claim_id, document_name, document_path as file_path,
                document_type, file_size, uploaded_by, uploaded_at
            FROM wgs_documents
            WHERE claim_id = :claim_id
            ORDER BY uploaded_at DESC
        ");
        $stmt->execute([':claim_id' => $record['id']]);
        $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Přidat dokumenty k záznamu
        $record['documents'] = $documents;
    }

    // Vrácení dat
    echo json_encode([
        'status' => 'success',
        'data' => $reklamace,
        'count' => count($reklamace)
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
