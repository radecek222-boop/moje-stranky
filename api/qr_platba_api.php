<?php
/**
 * API pro generování QR platebních dat
 *
 * GET parametry:
 * - id: ID reklamace
 *
 * Vrací:
 * - iban: Firemní IBAN
 * - ucet: Číslo účtu v českém formátu
 * - castka: Celková cena
 * - vs: Variabilní symbol (číslo reklamace)
 * - qr_string: SPD string pro QR kód
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/qr_payment_helper.php';

header('Content-Type: application/json; charset=utf-8');

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    sendJsonError('Uživatel není přihlášen', 401);
}

// Kontrola práv (admin nebo technik)
$isAdmin = !empty($_SESSION['is_admin']);
$rawRole = strtolower((string)($_SESSION['role'] ?? ''));
$isTechnik = strpos($rawRole, 'technik') !== false || strpos($rawRole, 'technician') !== false;

if (!$isAdmin && !$isTechnik) {
    sendJsonError('Přístup odepřen - pouze pro administrátory a techniky', 403);
}

// Validace vstupů
$reklamaceId = intval($_GET['id'] ?? 0);

if (!$reklamaceId) {
    sendJsonError('Chybí ID reklamace');
}

try {
    $pdo = getDbConnection();

    // Firemní účet - pevně nastavený
    // Číslo účtu: 188784838/0300 (ČSOB)
    // IBAN: CZ6503000000000188784838
    $iban = 'CZ6503000000000188784838';
    $ucetCislo = '188784838/0300';

    // Načíst data reklamace
    $stmt = $pdo->prepare("
        SELECT id, reklamace_id, cislo, jmeno, email,
               COALESCE(cena_celkem, cena, 0) as cena_celkem
        FROM wgs_reklamace
        WHERE id = ?
    ");
    $stmt->execute([$reklamaceId]);
    $reklamace = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$reklamace) {
        sendJsonError('Reklamace nenalezena', 404);
    }

    // Variabilní symbol = číslo reklamace nebo ID
    $vs = $reklamace['reklamace_id'] ?: ($reklamace['cislo'] ?: $reklamace['id']);
    // Odstranit nečíselné znaky z VS
    $vs = preg_replace('/[^0-9]/', '', $vs);

    // Částka (může být 0 - uživatel ji zadá v modalu)
    $castka = floatval($reklamace['cena_celkem']);

    // Generovat SPD string pouze pokud je částka > 0
    $spdString = null;
    if ($castka > 0) {
        try {
            $spdString = QRPaymentHelper::generateSPD([
                'acc' => $iban,
                'am' => $castka,
                'cc' => 'CZK',
                'vs' => $vs,
                'msg' => 'WGS servis - zakázka ' . $vs
            ]);
        } catch (Exception $e) {
            error_log("QR platba - chyba SPD: " . $e->getMessage());
        }
    }

    // Formátovat IBAN pro zobrazení (s mezerami)
    $ibanFormatovany = implode(' ', str_split($iban, 4));

    sendJsonSuccess('QR platební data načtena', [
        'reklamace_id' => $reklamaceId,
        'cislo' => $vs,
        'jmeno' => $reklamace['jmeno'],
        'ucet' => $ucetCislo,
        'iban' => $iban,
        'iban_formatovany' => $ibanFormatovany,
        'castka' => $castka,
        'castka_formatovana' => number_format($castka, 2, ',', ' ') . ' CZK',
        'vs' => $vs,
        'mena' => 'CZK',
        'qr_string' => $spdString
    ]);

} catch (PDOException $e) {
    error_log("QR platba API - DB chyba: " . $e->getMessage());
    sendJsonError('Chyba při načítání dat', 500);
} catch (Exception $e) {
    error_log("QR platba API - chyba: " . $e->getMessage());
    sendJsonError('Neočekávaná chyba', 500);
}
?>
