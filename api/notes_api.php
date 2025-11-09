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

            // Načtení poznámek z databáze
            $stmt = $pdo->prepare("
                SELECT
                    id, reklamace_id, note_text, created_by, created_at
                FROM wgs_notes
                WHERE reklamace_id = :reklamace_id
                ORDER BY created_at DESC
            ");
            $stmt->execute([':reklamace_id' => $reklamaceId]);
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

            // Zjištění autora
            $createdBy = $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? 'system';

            // Vložení do databáze
            $stmt = $pdo->prepare("
                INSERT INTO wgs_notes (
                    reklamace_id, note_text, created_by, created_at
                ) VALUES (
                    :reklamace_id, :note_text, :created_by, NOW()
                )
            ");
            $stmt->execute([
                ':reklamace_id' => $reklamaceId,
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

            // Smazání z databáze
            $stmt = $pdo->prepare("DELETE FROM wgs_notes WHERE id = :id");
            $stmt->execute([':id' => $noteId]);

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
