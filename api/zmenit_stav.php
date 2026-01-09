<?php
/**
 * API pro změnu stavu zakázky a CN workflow (pouze admin)
 *
 * POST parametry:
 * - csrf_token: CSRF token
 * - id: ID zakázky
 * - stav: Nový stav:
 *   - Základní: wait, open, done
 *   - CN workflow: cn_poslana, cn_odsouhlasena, cn_cekame_nd
 * - email: Email zákazníka (pro CN workflow)
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
$zakaznikEmail = strtolower(trim($_POST['email'] ?? ''));

if (!$reklamaceId) {
    sendJsonError('Chybí ID zakázky');
}

// Povolené stavy
$zakladniStavy = ['wait', 'open', 'done'];
$cnStavy = ['cn_poslana', 'cn_odsouhlasena', 'cn_cekame_nd'];
$vsechnyStavy = array_merge($zakladniStavy, $cnStavy);

if (!in_array($novyStav, $vsechnyStavy)) {
    sendJsonError('Neplatný stav. Povolené: ' . implode(', ', $vsechnyStavy));
}

$jeCnStav = in_array($novyStav, $cnStavy);

try {
    $pdo = getDbConnection();

    // Ověřit existenci zakázky
    $stmt = $pdo->prepare("SELECT id, stav, reklamace_id, cislo_objednavky, email FROM wgs_reklamace WHERE id = ?");
    $stmt->execute([$reklamaceId]);
    $zakazka = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zakazka) {
        sendJsonError('Zakázka nenalezena');
    }

    $puvodniStav = $zakazka['stav'];
    $cisloZakazky = $zakazka['reklamace_id'] ?: $zakazka['cislo_objednavky'] ?: $reklamaceId;

    // Použít email ze zakázky pokud nebyl předán
    if (!$zakaznikEmail) {
        $zakaznikEmail = strtolower(trim($zakazka['email'] ?? ''));
    }

    $pdo->beginTransaction();

    $dbStav = $puvodniStav; // Výchozí = beze změny
    $cnStavVystup = null;

    if ($jeCnStav) {
        // === CN WORKFLOW STAV ===

        if (!$zakaznikEmail) {
            $pdo->rollBack();
            sendJsonError('Zákazník nemá email - nelze nastavit CN stav');
        }

        // Najít CN pro zákazníka
        $stmt = $pdo->prepare("
            SELECT id, stav, cekame_nd_at
            FROM wgs_nabidky
            WHERE LOWER(zakaznik_email) = :email
            ORDER BY vytvoreno_at DESC
            LIMIT 1
        ");
        $stmt->execute(['email' => $zakaznikEmail]);
        $nabidka = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$nabidka) {
            $pdo->rollBack();
            sendJsonError('Zákazník nemá cenovou nabídku - nelze nastavit CN stav');
        }

        // Nastavit CN workflow podle zvoleného stavu
        switch ($novyStav) {
            case 'cn_poslana':
                // CN odeslána - stav='odeslana', zrušit cekame_nd
                $stmt = $pdo->prepare("UPDATE wgs_nabidky SET stav = 'odeslana', cekame_nd_at = NULL WHERE id = ?");
                $stmt->execute([$nabidka['id']]);
                $cnStavVystup = 'odeslana';
                break;

            case 'cn_odsouhlasena':
                // CN potvrzena - stav='potvrzena', zrušit cekame_nd
                $stmt = $pdo->prepare("UPDATE wgs_nabidky SET stav = 'potvrzena', cekame_nd_at = NULL WHERE id = ?");
                $stmt->execute([$nabidka['id']]);
                $cnStavVystup = 'potvrzena';
                break;

            case 'cn_cekame_nd':
                // Čekáme ND - nastavit cekame_nd_at
                $stmt = $pdo->prepare("UPDATE wgs_nabidky SET cekame_nd_at = NOW() WHERE id = ?");
                $stmt->execute([$nabidka['id']]);
                $cnStavVystup = 'cekame_nd';
                break;
        }

        // Pokud je zakázka HOTOVO a nastavujeme CN stav, změnit na ČEKÁ
        if ($puvodniStav === 'done') {
            $dbStav = 'wait';
            $stmt = $pdo->prepare("UPDATE wgs_reklamace SET stav = 'wait', updated_at = NOW() WHERE id = ?");
            $stmt->execute([$reklamaceId]);
        }

        error_log("zmenit_stav.php: Admin {$_SESSION['user_id']} změnil CN stav zakázky {$cisloZakazky} na '{$novyStav}' (email: {$zakaznikEmail})");

    } else {
        // === ZÁKLADNÍ STAV ===

        if ($puvodniStav !== $novyStav) {
            $stmt = $pdo->prepare("UPDATE wgs_reklamace SET stav = :stav, updated_at = NOW() WHERE id = :id");
            $stmt->execute([
                'stav' => $novyStav,
                'id' => $reklamaceId
            ]);
            $dbStav = $novyStav;

            error_log("zmenit_stav.php: Admin {$_SESSION['user_id']} změnil stav zakázky {$cisloZakazky} z '{$puvodniStav}' na '{$novyStav}'");
        }
    }

    $pdo->commit();

    // Mapování pro odpověď
    $stavyMap = [
        'wait' => 'NOVÁ',
        'open' => 'DOMLUVENÁ',
        'done' => 'HOTOVO',
        'cn_poslana' => 'Poslána CN',
        'cn_odsouhlasena' => 'Odsouhlasena',
        'cn_cekame_nd' => 'Čekáme ND'
    ];

    sendJsonSuccess("Stav změněn na: {$stavyMap[$novyStav]}", [
        'id' => $reklamaceId,
        'cislo' => $cisloZakazky,
        'puvodni_stav' => $puvodniStav,
        'novy_stav' => $novyStav,
        'db_stav' => $dbStav,
        'cn_stav' => $cnStavVystup,
        'email' => $zakaznikEmail
    ]);

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("zmenit_stav.php: Chyba databáze - " . $e->getMessage());
    sendJsonError('Chyba při ukládání do databáze');
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("zmenit_stav.php: Chyba - " . $e->getMessage());
    sendJsonError('Chyba při zpracování požadavku');
}
