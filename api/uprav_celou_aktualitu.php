<?php
/**
 * API endpoint pro úpravu celého obsahu aktuality
 * Umožňuje adminovi upravit celý markdown obsah článku
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatný CSRF token', 403);
}

// Kontrola admin oprávnění
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    sendJsonError('Přístup odepřen - pouze pro administrátory', 403);
}

try {
    $pdo = getDbConnection();

    // Rate limiting
    require_once __DIR__ . '/../includes/rate_limiter.php';
    $rateLimiter = new RateLimiter($pdo);
    if (!$rateLimiter->checkLimit('edit_aktualita', $_SERVER['REMOTE_ADDR'], 30, 3600)) {
        sendJsonError('Příliš mnoho požadavků na úpravu', 429);
    }

    // Validace vstupních dat
    $aktualitaId = filter_var($_POST['aktualita_id'] ?? '', FILTER_VALIDATE_INT);
    $jazyk = $_POST['jazyk'] ?? '';
    $novyObsah = $_POST['novy_obsah'] ?? '';

    if (!$aktualitaId) {
        sendJsonError('Chybí ID aktuality');
    }

    if (!in_array($jazyk, ['cz', 'en', 'it'])) {
        sendJsonError('Neplatný jazyk (povoleno: cz, en, it)');
    }

    if (empty(trim($novyObsah))) {
        sendJsonError('Obsah nesmí být prázdný');
    }

    // Získat aktuální záznam
    $stmtGet = $pdo->prepare("
        SELECT id, datum, obsah_cz, obsah_en, obsah_it
        FROM wgs_natuzzi_aktuality
        WHERE id = :id
    ");
    $stmtGet->execute(['id' => $aktualitaId]);
    $aktualita = $stmtGet->fetch(PDO::FETCH_ASSOC);

    if (!$aktualita) {
        sendJsonError('Aktualita nebyla nalezena', 404);
    }

    // Uložit starý obsah pro audit log
    $sloupecObsahu = 'obsah_' . $jazyk;
    $staryObsah = $aktualita[$sloupecObsahu];

    // Aktualizovat obsah
    $stmtUpdate = $pdo->prepare("
        UPDATE wgs_natuzzi_aktuality
        SET {$sloupecObsahu} = :obsah
        WHERE id = :id
    ");

    $stmtUpdate->execute([
        'obsah' => $novyObsah,
        'id' => $aktualitaId
    ]);

    // Audit log
    error_log(sprintf(
        "ADMIN EDIT AKTUALITA: User %d edited aktualita #%d (%s) on %s | Length: %d -> %d chars",
        $_SESSION['user_id'] ?? 0,
        $aktualitaId,
        $jazyk,
        $aktualita['datum'],
        strlen($staryObsah),
        strlen($novyObsah)
    ));

    sendJsonSuccess('Aktualita byla úspěšně upravena', [
        'aktualita_id' => $aktualitaId,
        'jazyk' => $jazyk,
        'delka_noveho_obsahu' => strlen($novyObsah)
    ]);

} catch (PDOException $e) {
    error_log("Database error in uprav_celou_aktualitu.php: " . $e->getMessage());
    sendJsonError('Chyba při ukládání změn do databáze');
} catch (Exception $e) {
    error_log("Error in uprav_celou_aktualitu.php: " . $e->getMessage());
    sendJsonError('Neočekávaná chyba při ukládání');
}
?>
