<?php
/**
 * API: Přepnutí stavu odložení reklamace
 * POST: reklamace_id, hodnota (0 nebo 1), csrf_token
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
if (!isset($_SESSION['user_id']) && !isset($_SESSION['is_admin'])) {
    sendJsonError('Uživatel není přihlášen', 401);
}

// Validace vstupních dat
$reklamaceId = intval($_POST['reklamace_id'] ?? 0);
if ($reklamaceId <= 0) {
    sendJsonError('Chybí nebo neplatné ID reklamace');
}

$hodnota = intval($_POST['hodnota'] ?? 0);
$hodnota = $hodnota ? 1 : 0;

try {
    $pdo = getDbConnection();

    // Ověřit že reklamace existuje
    $stmt = $pdo->prepare("SELECT id FROM wgs_reklamace WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $reklamaceId]);
    if (!$stmt->fetch()) {
        sendJsonError('Reklamace nenalezena', 404);
    }

    // Aktualizovat příznak odložení
    $stmt = $pdo->prepare("
        UPDATE wgs_reklamace
        SET je_odlozena = :hodnota, updated_at = NOW()
        WHERE id = :id
    ");
    $stmt->execute([
        ':hodnota' => $hodnota,
        ':id' => $reklamaceId
    ]);

    $zprava = $hodnota ? 'Reklamace označena jako odložená' : 'Odložení reklamace zrušeno';
    sendJsonSuccess($zprava, ['je_odlozena' => $hodnota]);

} catch (PDOException $e) {
    error_log('API odloz_reklamaci chyba: ' . $e->getMessage());
    sendJsonError('Chyba při ukládání');
}
?>
