<?php
/**
 * API pro uložení kalkulace do databáze
 *
 * Ukládá kalkulaci do wgs_reklamace.kalkulace_data jako JSON
 * Používá se z ceník kalkulátoru
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatný CSRF token', 403);
}

// Kontrola přihlášení
$userId = $_SESSION['user_id'] ?? null;
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

if (!$userId && !$isAdmin) {
    sendJsonError('Uživatel není přihlášen', 401);
}

// Rate limiting
$rateLimiter = new RateLimiter($pdo);
if (!$rateLimiter->checkLimit('save_kalkulace', $_SERVER['REMOTE_ADDR'], 30, 3600)) {
    sendJsonError('Příliš mnoho požadavků', 429);
}

try {
    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Získat parametry
    $reklamaceId = $_POST['reklamace_id'] ?? null;
    $kalkulaceJson = $_POST['kalkulace_data'] ?? null;

    if (!$reklamaceId) {
        sendJsonError('Chybí parametr reklamace_id');
    }

    if (!$kalkulaceJson) {
        sendJsonError('Chybí parametr kalkulace_data');
    }

    // Validace JSON
    $kalkulaceData = json_decode($kalkulaceJson, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        sendJsonError('Neplatný JSON formát: ' . json_last_error_msg());
    }

    // Najít reklamaci
    $stmt = $pdo->prepare("
        SELECT id, reklamace_id, created_by
        FROM wgs_reklamace
        WHERE reklamace_id = :rek_id OR cislo = :cislo OR id = :id
        LIMIT 1
    ");
    $stmt->execute([
        ':rek_id' => $reklamaceId,
        ':cislo' => $reklamaceId,
        ':id' => is_numeric($reklamaceId) ? intval($reklamaceId) : 0
    ]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        sendJsonError('Reklamace nenalezena');
    }

    // SECURITY: IDOR ochrana - kontrola oprávnění
    $userRole = strtolower(trim($_SESSION['role'] ?? 'guest'));
    $maOpravneni = false;

    if ($isAdmin || in_array($userRole, ['admin', 'technik', 'technician'])) {
        $maOpravneni = true;
    } elseif (in_array($userRole, ['prodejce', 'user'])) {
        // Prodejce vidí jen své reklamace
        $vlastnikId = $reklamace['created_by'] ?? null;
        if ($userId && $vlastnikId && (string)$userId === (string)$vlastnikId) {
            $maOpravneni = true;
        }
    }

    if (!$maOpravneni) {
        sendJsonError('Nemáte oprávnění k této reklamaci', 403);
    }

    // Uložit kalkulaci do databáze
    $stmt = $pdo->prepare("
        UPDATE wgs_reklamace
        SET kalkulace_data = :kalkulace_data,
            updated_at = NOW()
        WHERE id = :id
    ");

    $stmt->execute([
        ':kalkulace_data' => $kalkulaceJson,
        ':id' => $reklamace['id']
    ]);

    // Audit log
    if (function_exists('logAction')) {
        logAction(
            $pdo,
            $userId ?? 0,
            'kalkulace_ulozena',
            'wgs_reklamace',
            $reklamace['id'],
            null,
            ['reklamace_id' => $reklamaceId]
        );
    }

    sendJsonSuccess('Kalkulace uložena', [
        'reklamace_id' => $reklamaceId,
        'db_id' => $reklamace['id']
    ]);

} catch (PDOException $e) {
    error_log("SAVE_KALKULACE API - Database error: " . $e->getMessage());
    sendJsonError('Chyba při ukládání dat', 500);

} catch (Exception $e) {
    error_log("SAVE_KALKULACE API - Error: " . $e->getMessage());
    sendJsonError('Chyba při zpracování požadavku', 400);
}
?>
