<?php
/**
 * API pro změnu stavu zakázky (pouze admin)
 *
 * POST parametry:
 * - csrf_token: CSRF token
 * - id: ID zakázky
 * - stav: Nový stav (wait, open, done)
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// Pouze POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonError('Pouze POST požadavky', 405);
}

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatný CSRF token', 403);
}

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    sendJsonError('Uživatel není přihlášen', 401);
}

// Kontrola admin práv
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    sendJsonError('Přístup odepřen - pouze pro administrátory', 403);
}

// Validace vstupů
$reklamaceId = intval($_POST['id'] ?? 0);
$novyStav = $_POST['stav'] ?? '';

if (!$reklamaceId) {
    sendJsonError('Chybí ID zakázky');
}

// Povolené stavy
$povoleneStavy = ['wait', 'open', 'done'];
if (!in_array($novyStav, $povoleneStavy)) {
    sendJsonError('Neplatný stav. Povolené: ' . implode(', ', $povoleneStavy));
}

try {
    $pdo = getDbConnection();

    // Ověřit existenci zakázky
    $stmt = $pdo->prepare("SELECT id, stav, reklamace_id, cislo_objednavky FROM wgs_reklamace WHERE id = ?");
    $stmt->execute([$reklamaceId]);
    $zakazka = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zakazka) {
        sendJsonError('Zakázka nenalezena');
    }

    $puvodni = $zakazka['stav'];
    $cisloZakazky = $zakazka['reklamace_id'] ?: $zakazka['cislo_objednavky'] ?: $reklamaceId;

    // Pokud je stav stejný, nic nedělat
    if ($puvodni === $novyStav) {
        sendJsonSuccess('Stav je již nastaven', [
            'id' => $reklamaceId,
            'stav' => $novyStav,
            'beze_zmeny' => true
        ]);
    }

    // Aktualizovat stav
    $stmt = $pdo->prepare("UPDATE wgs_reklamace SET stav = :stav, updated_at = NOW() WHERE id = :id");
    $stmt->execute([
        'stav' => $novyStav,
        'id' => $reklamaceId
    ]);

    // Zalogovat změnu
    error_log("zmenit_stav.php: Admin {$_SESSION['user_id']} změnil stav zakázky {$cisloZakazky} z '{$puvodni}' na '{$novyStav}'");

    // Audit log (pokud existuje)
    if (function_exists('logAuditAction')) {
        logAuditAction('zmena_stavu', [
            'reklamace_id' => $reklamaceId,
            'cislo' => $cisloZakazky,
            'puvodni_stav' => $puvodni,
            'novy_stav' => $novyStav,
            'admin_id' => $_SESSION['user_id']
        ]);
    }

    // Mapování pro odpověď
    $stavyMap = [
        'wait' => 'NOVÁ',
        'open' => 'DOMLUVENÁ',
        'done' => 'HOTOVO'
    ];

    sendJsonSuccess("Stav změněn na: {$stavyMap[$novyStav]}", [
        'id' => $reklamaceId,
        'cislo' => $cisloZakazky,
        'puvodni_stav' => $puvodni,
        'novy_stav' => $novyStav
    ]);

} catch (PDOException $e) {
    error_log("zmenit_stav.php: Chyba databáze - " . $e->getMessage());
    sendJsonError('Chyba při ukládání do databáze');
} catch (Exception $e) {
    error_log("zmenit_stav.php: Chyba - " . $e->getMessage());
    sendJsonError('Chyba při zpracování požadavku');
}
