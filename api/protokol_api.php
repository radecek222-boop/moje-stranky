<?php
/**
 * Protokol API
 * API pro ukládání PDF protokolů a práci s protokoly
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json');

try {
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

    // Získání akce
    $action = $data['action'] ?? '';

    switch ($action) {
        case 'save_pdf_document':
            $result = savePdfDocument($data);
            break;

        default:
            throw new Exception('Neplatná akce');
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Uložení PDF dokumentu
 */
function savePdfDocument($data) {
    $reklamaceId = $data['reklamace_id'] ?? null;
    $pdfBase64 = $data['pdf_base64'] ?? null;

    if (!$reklamaceId) {
        throw new Exception('Chybí reklamace_id');
    }

    if (!$pdfBase64) {
        throw new Exception('Chybí PDF data');
    }

    // Dekódování base64
    $pdfData = base64_decode($pdfBase64);
    if ($pdfData === false) {
        throw new Exception('Nepodařilo se dekódovat PDF');
    }

    // Vytvoření uploads/protokoly adresáře
    $uploadsDir = __DIR__ . '/../uploads/protokoly';
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    // Název souboru
    $filename = $reklamaceId . '.pdf';
    $filePath = $uploadsDir . '/' . $filename;

    // Uložení souboru
    if (file_put_contents($filePath, $pdfData) === false) {
        throw new Exception('Nepodařilo se uložit PDF soubor');
    }

    // Relativní cesta pro databázi
    $relativePathForDb = "uploads/protokoly/{$filename}";

    // Velikost souboru
    $fileSize = filesize($filePath);

    // Databázové připojení
    $pdo = getDbConnection();

    // Získání claim_id z reklamace_id
    $stmt = $pdo->prepare("SELECT id FROM wgs_reklamace WHERE reklamace_id = :reklamace_id OR cislo = :cislo LIMIT 1");
    $stmt->execute([
        ':reklamace_id' => $reklamaceId,
        ':cislo' => $reklamaceId
    ]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        throw new Exception('Reklamace nebyla nalezena');
    }

    $claimId = $reklamace['id'];

    // Kontrola zda už PDF existuje
    $stmt = $pdo->prepare("
        SELECT id FROM wgs_documents
        WHERE claim_id = :claim_id AND document_type = 'protokol_pdf'
        LIMIT 1
    ");
    $stmt->execute([':claim_id' => $claimId]);
    $existingDoc = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existingDoc) {
        // Update existujícího záznamu
        $stmt = $pdo->prepare("
            UPDATE wgs_documents
            SET document_path = :document_path,
                file_size = :file_size,
                uploaded_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            ':document_path' => $relativePathForDb,
            ':file_size' => $fileSize,
            ':id' => $existingDoc['id']
        ]);

        $documentId = $existingDoc['id'];
    } else {
        // Vložení nového záznamu do databáze
        $stmt = $pdo->prepare("
            INSERT INTO wgs_documents (
                claim_id, document_name, document_path, document_type,
                file_size, uploaded_by, uploaded_at
            ) VALUES (
                :claim_id, :document_name, :document_path, :document_type,
                :file_size, :uploaded_by, NOW()
            )
        ");

        $uploadedBy = $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? 'system';

        $stmt->execute([
            ':claim_id' => $claimId,
            ':document_name' => "Protokol_{$reklamaceId}.pdf",
            ':document_path' => $relativePathForDb,
            ':document_type' => 'protokol_pdf',
            ':file_size' => $fileSize,
            ':uploaded_by' => $uploadedBy
        ]);

        $documentId = $pdo->lastInsertId();
    }

    return [
        'success' => true,
        'message' => 'PDF úspěšně uloženo',
        'path' => $relativePathForDb,
        'document_id' => $documentId,
        'file_size' => $fileSize
    ];
}
