<?php
/**
 * Notes API
 * API pro práci s poznámkami k reklamacím
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

header('Content-Type: application/json');

try {
    // BEZPEČNOST: Kontrola přihlášení
    $isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
    if (!$isLoggedIn) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'error' => 'Neautorizovaný přístup. Přihlaste se prosím.'
        ]);
        exit;
    }

    // Zjištění akce
    $action = '';
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $action = $_GET['action'] ?? '';
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $action = $_POST['action'] ?? '';

        // BEZPEČNOST: CSRF ochrana pro POST operace
        requireCSRF();
    } else {
        throw new Exception('Povolena pouze GET nebo POST metoda');
    }

    $pdo = getDbConnection();

    switch ($action) {
        case 'get':
            // Načtení poznámek
            $reklamaceId = $_GET['reklamace_id'] ?? null;

            if (!$reklamaceId) {
                throw new Exception('Chybí reklamace_id');
            }

            // BEZPEČNOST: Validace ID
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $reklamaceId)) {
                throw new Exception('Neplatné ID reklamace');
            }

            // Převést reklamace_id na claim_id (číselné ID)
            $stmt = $pdo->prepare("SELECT id FROM wgs_reklamace WHERE reklamace_id = :reklamace_id OR cislo = :cislo LIMIT 1");
            $stmt->execute([':reklamace_id' => $reklamaceId, ':cislo' => $reklamaceId]);
            $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reklamace) {
                throw new Exception('Reklamace nebyla nalezena');
            }

            $claimId = $reklamace['id'];

            // Načtení poznámek z databáze
            $stmt = $pdo->prepare("
                SELECT
                    id, claim_id, note_text, created_by, created_at
                FROM wgs_notes
                WHERE claim_id = :claim_id
                ORDER BY created_at DESC
            ");
            $stmt->execute([':claim_id' => $claimId]);
            $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'notes' => $notes
            ]);
            break;

        case 'add':
            // Přidání poznámky
            $reklamaceId = $_POST['reklamace_id'] ?? null;
            $text = $_POST['text'] ?? null;

            if (!$reklamaceId || !$text) {
                throw new Exception('Chybí reklamace_id nebo text');
            }

            // BEZPEČNOST: Validace ID
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $reklamaceId)) {
                throw new Exception('Neplatné ID reklamace');
            }

            // BEZPEČNOST: Validace textu
            $text = trim($text);
            if (strlen($text) < 1 || strlen($text) > 5000) {
                throw new Exception('Text poznámky musí mít 1-5000 znaků');
            }

            // BEZPEČNOST: XSS ochrana - sanitizace HTML
            $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

            // Převést reklamace_id na claim_id (číselné ID)
            $stmt = $pdo->prepare("SELECT id FROM wgs_reklamace WHERE reklamace_id = :reklamace_id OR cislo = :cislo LIMIT 1");
            $stmt->execute([':reklamace_id' => $reklamaceId, ':cislo' => $reklamaceId]);
            $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reklamace) {
                throw new Exception('Reklamace nebyla nalezena');
            }

            $claimId = $reklamace['id'];

            // Zjištění autora
            $createdBy = $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? 'system';

            // Vložení do databáze
            $stmt = $pdo->prepare("
                INSERT INTO wgs_notes (
                    claim_id, note_text, created_by, created_at
                ) VALUES (
                    :claim_id, :note_text, :created_by, NOW()
                )
            ");
            $stmt->execute([
                ':claim_id' => $claimId,
                ':note_text' => $text,
                ':created_by' => $createdBy
            ]);

            $noteId = $pdo->lastInsertId();

            echo json_encode([
                'status' => 'success',
                'message' => 'Poznámka přidána',
                'note_id' => $noteId
            ]);
            break;

        case 'delete':
            // Smazání poznámky
            $noteId = $_POST['note_id'] ?? null;

            if (!$noteId) {
                throw new Exception('Chybí note_id');
            }

            // BEZPEČNOST: Validace ID (pouze čísla)
            if (!is_numeric($noteId)) {
                throw new Exception('Neplatné ID poznámky');
            }

            // ✅ SECURITY FIX: Kontrola vlastnictví poznámky
            $currentUserId = $_SESSION['user_id'] ?? $_SESSION['admin_id'] ?? null;
            $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

            if (!$currentUserId && !$isAdmin) {
                throw new Exception('Přístup odepřen');
            }

            // Smazání z databáze - pouze vlastní poznámky nebo admin
            if ($isAdmin) {
                // Admin může smazat jakoukoliv poznámku
                $stmt = $pdo->prepare("DELETE FROM wgs_notes WHERE id = :id");
                $stmt->execute([':id' => $noteId]);
            } else {
                // Ostatní uživatelé pouze své vlastní
                $stmt = $pdo->prepare("
                    DELETE FROM wgs_notes
                    WHERE id = :id AND author_id = :user_id
                ");
                $stmt->execute([
                    ':id' => $noteId,
                    ':user_id' => $currentUserId
                ]);
            }

            // Kontrola zda byla poznámka smazána
            if ($stmt->rowCount() === 0) {
                throw new Exception('Poznámku nelze smazat - neexistuje nebo nemáte oprávnění');
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Poznámka smazána'
            ]);
            break;

        default:
            throw new Exception('Neplatná akce: ' . $action);
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'error' => $e->getMessage()
    ]);
}
