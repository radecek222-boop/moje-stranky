<?php
/**
 * EMAIL RESEND API
 * Znovu odeslání vybraných emailů - změní status z 'failed' na 'pending'
 * BEZPEČNOST: Admin only, CSRF ochrana, rate limiting
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// KRITICKÉ: Pouze pro administrátory
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    respondError('Nedostatečná oprávnění', 403);
    exit;
}

// PERFORMANCE: Uvolnění session zámku pro paralelní požadavky
session_write_close();

// Přečíst JSON data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    respondError('Neplatná JSON data', 400);
    exit;
}

// CSRF ochrana
if (!validateCSRFToken($data['csrf_token'] ?? '')) {
    respondError('Neplatný CSRF token', 403);
    exit;
}

// Validace vstupních dat
if (!isset($data['email_ids']) || !is_array($data['email_ids'])) {
    respondError('Chybí email_ids pole', 400);
    exit;
}

$emailIds = array_filter($data['email_ids'], 'is_numeric');

if (count($emailIds) === 0) {
    respondError('Nebyly poskytnuty žádné platné ID emailů', 400);
    exit;
}

// Limit max 100 emailů najednou
if (count($emailIds) > 100) {
    respondError('Maximální počet emailů je 100 najednou', 400);
    exit;
}

try {
    $pdo = getDbConnection();

    // Připravit placeholders pro IN clause
    $placeholders = implode(',', array_fill(0, count($emailIds), '?'));

    // Update emailů - změní status na 'pending', resetuje retry_count a vymaže last_error
    $sql = "
        UPDATE wgs_email_queue
        SET
            status = 'pending',
            retry_count = 0,
            last_error = NULL,
            updated_at = NOW()
        WHERE id IN ($placeholders)
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($emailIds);

    $affectedRows = $stmt->rowCount();

    // Log akce
    error_log("Admin resent $affectedRows emails: " . implode(',', $emailIds));

    respondSuccess([
        'count' => $affectedRows,
        'email_ids' => $emailIds
    ], "Úspěšně přesunuto $affectedRows emailů zpět do fronty", 200);

} catch (PDOException $e) {
    error_log("Email resend API error: " . $e->getMessage());
    respondError('Chyba při zpracování požadavku', 500);
}
