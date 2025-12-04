<?php
/**
 * API endpoint pro načtení dokumentů z původní zakázky
 *
 * Slouží k zobrazení historie PDF z původní zakázky,
 * když je aktuální zakázka klonem (má original_reklamace_id).
 *
 * SECURITY FIX: Přidána IDOR ochrana
 */

require_once __DIR__ . '/../init.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Kontrola přihlášení a získání uživatelských dat
    $userId = $_SESSION['user_id'] ?? null;
    $userEmail = $_SESSION['user_email'] ?? null;
    $userRole = strtolower(trim($_SESSION['role'] ?? 'guest'));
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    $isLoggedIn = $userId || $isAdmin;

    if (!$isLoggedIn) {
        http_response_code(401);
        die(json_encode([
            'status' => 'error',
            'message' => 'Neautorizovaný přístup'
        ]));
    }

    // PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
    session_write_close();

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

    // Nejprve najít interní ID zakázky podle reklamace_id včetně informace o vlastníkovi
    $stmtClaim = $pdo->prepare("
        SELECT r.id, r.created_by, u.email as vlastnik_email
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON (u.user_id = r.created_by OR u.id = r.created_by)
        WHERE r.reklamace_id = :reklamace_id OR r.id = :id
        LIMIT 1
    ");
    $stmtClaim->execute([
        'reklamace_id' => $reklamaceId,
        'id' => $reklamaceId
    ]);
    $claim = $stmtClaim->fetch(PDO::FETCH_ASSOC);

    if (!$claim) {
        http_response_code(404);
        die(json_encode([
            'status' => 'error',
            'message' => 'Zakázka nebyla nalezena'
        ]));
    }

    // SECURITY: IDOR ochrana - kontrola oprávnění k přístupu
    $maOpravneni = false;
    if ($isAdmin || in_array($userRole, ['admin', 'technik', 'technician'])) {
        $maOpravneni = true;
    } elseif (in_array($userRole, ['prodejce', 'user'])) {
        $vlastnikId = $claim['created_by'] ?? null;
        $vlastnikEmail = $claim['vlastnik_email'] ?? null;
        if (($userId && $vlastnikId && (string)$userId === (string)$vlastnikId) ||
            ($userEmail && $vlastnikEmail && strtolower($userEmail) === strtolower($vlastnikEmail))) {
            $maOpravneni = true;
        }
    }

    if (!$maOpravneni) {
        http_response_code(403);
        die(json_encode([
            'status' => 'error',
            'message' => 'Nemáte oprávnění k této zakázce'
        ]));
    }

    $claimId = $claim['id'];

    // Načíst dokumenty z tabulky wgs_documents
    $stmt = $pdo->prepare("
        SELECT
            id,
            claim_id,
            document_type,
            document_path,
            document_name,
            file_size,
            uploaded_at,
            uploaded_by
        FROM wgs_documents
        WHERE claim_id = :claim_id
        ORDER BY uploaded_at DESC
    ");

    $stmt->execute(['claim_id' => $claimId]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formátovat data dokumentů
    $formattedDocuments = array_map(function($doc) {
        return [
            'document_id' => $doc['id'],
            'reklamace_id' => $doc['claim_id'],
            'type' => $doc['document_type'],
            'file_path' => $doc['document_path'],
            'file_name' => $doc['document_name'],
            'file_size' => $doc['file_size'],
            'created_at' => $doc['uploaded_at'],
            'created_by' => $doc['uploaded_by']
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
