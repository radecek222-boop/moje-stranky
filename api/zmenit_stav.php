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
require_once __DIR__ . '/../includes/db_metadata.php';
require_once __DIR__ . '/../includes/notifikace_helper.php';

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
$zakladniStavy = ['wait', 'open', 'done', 'cekame_na_dily'];
$cnStavy = ['cn_poslana', 'cn_odsouhlasena', 'cn_cekame_nd'];
$vsechnyStavy = array_merge($zakladniStavy, $cnStavy);

if (!in_array($novyStav, $vsechnyStavy)) {
    sendJsonError('Neplatný stav. Povolené: ' . implode(', ', $vsechnyStavy));
}

$jeCnStav = in_array($novyStav, $cnStavy);

try {
    $pdo = getDbConnection();

    // Ověřit existenci zakázky
    $stmt = $pdo->prepare("SELECT id, stav, reklamace_id, cislo, email FROM wgs_reklamace WHERE id = ?");
    $stmt->execute([$reklamaceId]);
    $zakazka = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zakazka) {
        sendJsonError('Zakázka nenalezena');
    }

    $puvodniStav = $zakazka['stav'];
    $cisloZakazky = $zakazka['reklamace_id'] ?: $zakazka['cislo'] ?: $reklamaceId;

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

        // Zjistit strukturu tabulky wgs_nabidky před dotazem
        $hasCekameNdAt = db_table_has_column($pdo, 'wgs_nabidky', 'cekame_nd_at');
        error_log("zmenit_stav.php: hasCekameNdAt = " . ($hasCekameNdAt ? 'true' : 'false'));

        // Najít CN pro zákazníka - dynamický SELECT podle existence sloupce
        // COLLATE utf8mb4_unicode_ci řeší problém s rozdílnou kolací tabulek
        if ($hasCekameNdAt) {
            $stmt = $pdo->prepare("
                SELECT id, stav, cekame_nd_at
                FROM wgs_nabidky
                WHERE LOWER(zakaznik_email) COLLATE utf8mb4_unicode_ci = :email COLLATE utf8mb4_unicode_ci
                ORDER BY vytvoreno_at DESC
                LIMIT 1
            ");
        } else {
            $stmt = $pdo->prepare("
                SELECT id, stav
                FROM wgs_nabidky
                WHERE LOWER(zakaznik_email) COLLATE utf8mb4_unicode_ci = :email COLLATE utf8mb4_unicode_ci
                ORDER BY vytvoreno_at DESC
                LIMIT 1
            ");
        }
        $stmt->execute(['email' => strtolower($zakaznikEmail)]);
        $nabidka = $stmt->fetch(PDO::FETCH_ASSOC);

        error_log("zmenit_stav.php: Hledám CN pro email '{$zakaznikEmail}', nalezeno: " . ($nabidka ? "ID {$nabidka['id']}" : 'NIC'));

        if (!$nabidka) {
            $pdo->rollBack();
            sendJsonError('Zákazník nemá cenovou nabídku - nelze nastavit CN stav');
        }

        // Nastavit CN workflow podle zvoleného stavu
        switch ($novyStav) {
            case 'cn_poslana':
                // CN odeslána - stav='odeslana', zrušit cekame_nd
                if ($hasCekameNdAt) {
                    $stmt = $pdo->prepare("UPDATE wgs_nabidky SET stav = 'odeslana', cekame_nd_at = NULL WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("UPDATE wgs_nabidky SET stav = 'odeslana' WHERE id = ?");
                }
                $stmt->execute([$nabidka['id']]);
                $cnStavVystup = 'odeslana';
                break;

            case 'cn_odsouhlasena':
                // CN potvrzena - stav='potvrzena', zrušit cekame_nd
                if ($hasCekameNdAt) {
                    $stmt = $pdo->prepare("UPDATE wgs_nabidky SET stav = 'potvrzena', cekame_nd_at = NULL WHERE id = ?");
                } else {
                    $stmt = $pdo->prepare("UPDATE wgs_nabidky SET stav = 'potvrzena' WHERE id = ?");
                }
                $stmt->execute([$nabidka['id']]);
                $cnStavVystup = 'potvrzena';
                break;

            case 'cn_cekame_nd':
                // Čekáme ND - nastavit cekame_nd_at
                if ($hasCekameNdAt) {
                    $stmt = $pdo->prepare("UPDATE wgs_nabidky SET cekame_nd_at = NOW() WHERE id = ?");
                    $stmt->execute([$nabidka['id']]);
                    $cnStavVystup = 'cekame_nd';
                } else {
                    $pdo->rollBack();
                    sendJsonError('Stav "Čekáme ND" vyžaduje sloupec cekame_nd_at - spusťte migraci pridej_cekame_nd_sloupec.php');
                }
                break;
        }

        // Pokud je zakázka HOTOVO a nastavujeme CN stav, změnit na ČEKÁ
        if ($puvodniStav === 'done') {
            $dbStav = 'wait';
            $hasUpdatedAt = db_table_has_column($pdo, 'wgs_reklamace', 'updated_at');
            if ($hasUpdatedAt) {
                $stmt = $pdo->prepare("UPDATE wgs_reklamace SET stav = 'wait', updated_at = NOW() WHERE id = ?");
            } else {
                $stmt = $pdo->prepare("UPDATE wgs_reklamace SET stav = 'wait' WHERE id = ?");
            }
            $stmt->execute([$reklamaceId]);
        }

        error_log("zmenit_stav.php: Admin {$_SESSION['user_id']} změnil CN stav zakázky {$cisloZakazky} na '{$novyStav}' (email: {$zakaznikEmail})");

    } else {
        // === ZÁKLADNÍ STAV ===

        if ($puvodniStav !== $novyStav) {
            $hasUpdatedAt = db_table_has_column($pdo, 'wgs_reklamace', 'updated_at');
            if ($hasUpdatedAt) {
                $stmt = $pdo->prepare("UPDATE wgs_reklamace SET stav = :stav, updated_at = NOW() WHERE id = :id");
            } else {
                $stmt = $pdo->prepare("UPDATE wgs_reklamace SET stav = :stav WHERE id = :id");
            }
            $stmt->execute([
                'stav' => $novyStav,
                'id' => $reklamaceId
            ]);
            $dbStav = $novyStav;

            error_log("zmenit_stav.php: Admin {$_SESSION['user_id']} změnil stav zakázky {$cisloZakazky} z '{$puvodniStav}' na '{$novyStav}'");
        }
    }

    $pdo->commit();

    // === ODESLÁNÍ EMAILOVÉ NOTIFIKACE (dle šablony) ===
    // Šablona v wgs_notifications definuje kdo dostane email (zákazník, prodejce, admin...)
    if (!$jeCnStav && $puvodniStav !== $novyStav) {
        if ($novyStav === 'done') {
            // Dokončení zakázky → šablona order_completed
            odeslat_notifikaci_zakazky($pdo, $reklamaceId, 'order_completed');
        } elseif ($novyStav === 'open') {
            // Domluvení termínu → šablona appointment_confirmed
            odeslat_notifikaci_zakazky($pdo, $reklamaceId, 'appointment_confirmed');
        }
    }

    // Mapování pro odpověď
    $stavyMap = [
        'wait' => 'NOVÁ',
        'open' => 'DOMLUVENÁ',
        'done' => 'HOTOVO',
        'cekame_na_dily' => 'ČEKÁME NA DÍLY',
        'cn_poslana' => 'Poslána CN',
        'cn_odsouhlasena' => 'Odsouhlasena',
        'cn_cekame_nd' => 'ČEKÁME NA DÍLY'
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
    $chybaDetail = $e->getMessage();
    $chybaTrace = $e->getTraceAsString();

    // DETAILNÍ LOGOVÁNÍ
    error_log("=== zmenit_stav.php PDOException ===");
    error_log("reklamaceId: " . ($reklamaceId ?? 'NULL'));
    error_log("novyStav: " . ($novyStav ?? 'NULL'));
    error_log("zakaznikEmail: " . ($zakaznikEmail ?? 'NULL'));
    error_log("Chyba: " . $chybaDetail);
    error_log("Trace: " . $chybaTrace);
    error_log("=== END PDOException ===");

    // Vrátit více detailů pro debugging (bez citlivých SQL údajů)
    $uzivatelChyba = 'Chyba při ukládání do databáze';
    if (strpos($chybaDetail, 'Unknown column') !== false) {
        preg_match("/Unknown column '([^']+)'/", $chybaDetail, $matches);
        $missingCol = $matches[1] ?? 'neznámý';
        $uzivatelChyba = "Chybí sloupec v databázi: {$missingCol}";
    } elseif (strpos($chybaDetail, 'Illegal mix of collations') !== false) {
        $uzivatelChyba = 'Chyba kolace databáze - kontaktujte administrátora';
    } elseif (strpos($chybaDetail, "Data too long") !== false || strpos($chybaDetail, "Incorrect") !== false) {
        $uzivatelChyba = 'Neplatná hodnota pro databázi';
    }

    sendJsonError($uzivatelChyba);
} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("zmenit_stav.php: Exception - " . $e->getMessage());
    error_log("Trace: " . $e->getTraceAsString());
    sendJsonError('Chyba při zpracování požadavku: ' . $e->getMessage());
}
