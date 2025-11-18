<?php
/**
 * Delete Photo API
 * Mazání jednotlivé fotky z reklamace
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // BEZPEČNOST: Kontrola metody
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Povolena pouze POST metoda');
    }

    // BEZPEČNOST: CSRF ochrana
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        sendJsonError('Neplatný CSRF token', 403);
    }

    // BEZPEČNOST: Kontrola přihlášení (admin nebo technik)
    $isLoggedIn = isset($_SESSION['user_id']) || (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true);
    if (!$isLoggedIn) {
        sendJsonError('Neautorizovaný přístup', 401);
    }

    // BEZPEČNOST: Rate limiting
    require_once __DIR__ . '/../includes/rate_limiter.php';
    $rateLimiter = new RateLimiter(getDbConnection());
    if (!$rateLimiter->checkLimit('delete_photo', $_SERVER['REMOTE_ADDR'], 30, 3600)) {
        sendJsonError('Příliš mnoho požadavků', 429);
    }

    // Získání photo_id z POST dat
    $photoId = filter_var($_POST['photo_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$photoId) {
        throw new Exception('Chybí nebo neplatné ID fotky');
    }

    // Databázové připojení
    $pdo = getDbConnection();

    // BEZPEČNOST: Načtení informací o fotce PŘED smazáním
    $stmt = $pdo->prepare("
        SELECT id, reklamace_id, photo_path, file_path, file_name, section_name
        FROM wgs_photos
        WHERE id = :photo_id
        LIMIT 1
    ");
    $stmt->execute([':photo_id' => $photoId]);
    $photo = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$photo) {
        throw new Exception('Fotka nebyla nalezena');
    }

    // Začátek transakce
    $pdo->beginTransaction();

    try {
        // Smazání z databáze
        $deleteStmt = $pdo->prepare("DELETE FROM wgs_photos WHERE id = :photo_id LIMIT 1");
        $deleteStmt->execute([':photo_id' => $photoId]);

        if ($deleteStmt->rowCount() === 0) {
            throw new Exception('Fotku se nepodařilo smazat z databáze');
        }

        // Commit transakce
        $pdo->commit();

        // Smazání fyzického souboru (po commitu, aby se nemazal pokud DB selže)
        $fileDeleted = false;
        $filePath = $photo['file_path'] ?? $photo['photo_path'];

        if ($filePath) {
            // BEZPEČNOST: Path Traversal ochrana
            $uploadsRoot = realpath(__DIR__ . '/../uploads');
            $normalized = str_replace(['\\', '..'], ['/', ''], $filePath);
            $normalized = ltrim($normalized, '/');

            // Odstranit prefix 'uploads/' pokud existuje
            if (strpos($normalized, 'uploads/') === 0) {
                $normalized = substr($normalized, 8);
            }

            $fullPath = $uploadsRoot . '/' . $normalized;
            $realPath = realpath($fullPath);

            // Ověřit že realpath je stále v uploads/ (ochrana proti útokům)
            if ($realPath && strpos($realPath, $uploadsRoot) === 0 && is_file($realPath)) {
                if (unlink($realPath)) {
                    $fileDeleted = true;
                } else {
                    error_log("DELETE_PHOTO: Nepodařilo se smazat soubor: $realPath");
                }
            }
        }

        // Audit log (pokud tabulka existuje)
        if (function_exists('db_table_exists') && db_table_exists($pdo, 'wgs_audit_log')) {
            require_once __DIR__ . '/../includes/db_metadata.php';
            $details = json_encode([
                'photo_id' => $photoId,
                'reklamace_id' => $photo['reklamace_id'],
                'section_name' => $photo['section_name'],
                'file_path' => $filePath,
                'file_deleted' => $fileDeleted
            ], JSON_UNESCAPED_UNICODE);

            $auditStmt = $pdo->prepare('INSERT INTO wgs_audit_log (user_id, action, details, created_at) VALUES (:user_id, :action, :details, NOW())');
            $auditStmt->execute([
                ':user_id' => $_SESSION['user_id'] ?? 'admin',
                ':action' => 'delete_photo',
                ':details' => $details
            ]);
        }

        // Úspěšná odpověď
        sendJsonSuccess('Fotka byla úspěšně smazána', [
            'photo_id' => $photoId,
            'reklamace_id' => $photo['reklamace_id'],
            'file_deleted' => $fileDeleted
        ]);

    } catch (Exception $e) {
        // Rollback transakce při chybě
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    error_log("DELETE_PHOTO ERROR: " . $e->getMessage());
    sendJsonError($e->getMessage());
}
