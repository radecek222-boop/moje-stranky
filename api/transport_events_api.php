<?php
/**
 * API pro správu transportních eventů
 *
 * Endpoint: /api/transport_events_api.php
 * Akce: list, create, update, delete, zmena_stavu
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// Ověření admin přihlášení
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    sendJsonError('Nejste prihlaseni jako administrator', 401);
}

// Získat akci
$akce = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    $pdo = getDbConnection();

    switch ($akce) {
        // =============================================
        // SEZNAM - Načíst všechny transporty
        // =============================================
        case 'list':
            $datum = $_GET['datum'] ?? null;
            $ridic = $_GET['ridic'] ?? null;
            $stav = $_GET['stav'] ?? null;

            $sql = "SELECT * FROM wgs_transport_events WHERE 1=1";
            $params = [];

            if ($datum) {
                $sql .= " AND datum = :datum";
                $params['datum'] = $datum;
            }

            if ($ridic) {
                $sql .= " AND ridic = :ridic";
                $params['ridic'] = $ridic;
            }

            if ($stav) {
                $sql .= " AND stav = :stav";
                $params['stav'] = $stav;
            }

            $sql .= " ORDER BY datum ASC, cas ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $transporty = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mapování stavu na české názvy pro frontend
            $stavyMapovani = [
                'wait' => 'WAIT',
                'onway' => 'ON THE WAY',
                'drop' => 'DROP OFF'
            ];

            foreach ($transporty as &$transport) {
                $transport['stav_text'] = $stavyMapovani[$transport['stav']] ?? $transport['stav'];
            }

            sendJsonSuccess('Seznam transportu', ['transporty' => $transporty]);
            break;

        // =============================================
        // VYTVOŘENÍ - Nový transport
        // =============================================
        case 'create':
            // CSRF validace
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }

            // Validace povinných polí
            $povinne = ['jmeno_prijmeni', 'cas', 'datum'];
            foreach ($povinne as $pole) {
                if (empty($_POST[$pole])) {
                    sendJsonError("Chybi povinne pole: {$pole}");
                }
            }

            $sql = "INSERT INTO wgs_transport_events
                    (jmeno_prijmeni, cas, cislo_letu, destinace, cas_priletu, telefon, email, ridic, stav, datum, poznamka)
                    VALUES
                    (:jmeno_prijmeni, :cas, :cislo_letu, :destinace, :cas_priletu, :telefon, :email, :ridic, :stav, :datum, :poznamka)";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'jmeno_prijmeni' => trim($_POST['jmeno_prijmeni']),
                'cas' => $_POST['cas'],
                'cislo_letu' => trim($_POST['cislo_letu'] ?? '') ?: null,
                'destinace' => trim($_POST['destinace'] ?? '') ?: null,
                'cas_priletu' => $_POST['cas_priletu'] ?: null,
                'telefon' => trim($_POST['telefon'] ?? '') ?: null,
                'email' => trim($_POST['email'] ?? '') ?: null,
                'ridic' => trim($_POST['ridic'] ?? '') ?: null,
                'stav' => 'wait',
                'datum' => $_POST['datum'],
                'poznamka' => trim($_POST['poznamka'] ?? '') ?: null,
            ]);

            $novyId = $pdo->lastInsertId();

            sendJsonSuccess('Transport vytvoren', ['event_id' => $novyId]);
            break;

        // =============================================
        // AKTUALIZACE - Upravit transport
        // =============================================
        case 'update':
            // CSRF validace
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }

            if (empty($_POST['event_id'])) {
                sendJsonError('Chybi event_id');
            }

            // Validace povinných polí
            $povinne = ['jmeno_prijmeni', 'cas', 'datum'];
            foreach ($povinne as $pole) {
                if (empty($_POST[$pole])) {
                    sendJsonError("Chybi povinne pole: {$pole}");
                }
            }

            $sql = "UPDATE wgs_transport_events SET
                    jmeno_prijmeni = :jmeno_prijmeni,
                    cas = :cas,
                    cislo_letu = :cislo_letu,
                    destinace = :destinace,
                    cas_priletu = :cas_priletu,
                    telefon = :telefon,
                    email = :email,
                    ridic = :ridic,
                    datum = :datum,
                    poznamka = :poznamka
                    WHERE event_id = :event_id";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                'jmeno_prijmeni' => trim($_POST['jmeno_prijmeni']),
                'cas' => $_POST['cas'],
                'cislo_letu' => trim($_POST['cislo_letu'] ?? '') ?: null,
                'destinace' => trim($_POST['destinace'] ?? '') ?: null,
                'cas_priletu' => $_POST['cas_priletu'] ?: null,
                'telefon' => trim($_POST['telefon'] ?? '') ?: null,
                'email' => trim($_POST['email'] ?? '') ?: null,
                'ridic' => trim($_POST['ridic'] ?? '') ?: null,
                'datum' => $_POST['datum'],
                'poznamka' => trim($_POST['poznamka'] ?? '') ?: null,
                'event_id' => (int)$_POST['event_id'],
            ]);

            sendJsonSuccess('Transport aktualizovan');
            break;

        // =============================================
        // SMAZÁNÍ - Odstranit transport
        // =============================================
        case 'delete':
            // CSRF validace
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }

            if (empty($_POST['event_id'])) {
                sendJsonError('Chybi event_id');
            }

            $stmt = $pdo->prepare("DELETE FROM wgs_transport_events WHERE event_id = :event_id");
            $stmt->execute(['event_id' => (int)$_POST['event_id']]);

            if ($stmt->rowCount() === 0) {
                sendJsonError('Transport nenalezen');
            }

            sendJsonSuccess('Transport smazan');
            break;

        // =============================================
        // ZMĚNA STAVU - Cyklicky přepnout stav
        // =============================================
        case 'zmena_stavu':
            // CSRF validace
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }

            if (empty($_POST['event_id'])) {
                sendJsonError('Chybi event_id');
            }

            // Načíst aktuální stav
            $stmt = $pdo->prepare("SELECT stav FROM wgs_transport_events WHERE event_id = :event_id");
            $stmt->execute(['event_id' => (int)$_POST['event_id']]);
            $transport = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transport) {
                sendJsonError('Transport nenalezen');
            }

            // Cyklická změna stavu: wait -> onway -> drop -> wait
            $stavyPoradi = ['wait', 'onway', 'drop'];
            $aktualniIndex = array_search($transport['stav'], $stavyPoradi);
            $novyIndex = ($aktualniIndex + 1) % count($stavyPoradi);
            $novyStav = $stavyPoradi[$novyIndex];

            // Aktualizovat stav
            $stmt = $pdo->prepare("UPDATE wgs_transport_events SET stav = :stav, cas_zmeny_stavu = NOW() WHERE event_id = :event_id");
            $stmt->execute([
                'stav' => $novyStav,
                'event_id' => (int)$_POST['event_id'],
            ]);

            $stavyMapovani = [
                'wait' => 'WAIT',
                'onway' => 'ON THE WAY',
                'drop' => 'DROP OFF'
            ];

            sendJsonSuccess('Stav zmenen', [
                'novy_stav' => $novyStav,
                'novy_stav_text' => $stavyMapovani[$novyStav]
            ]);
            break;

        // =============================================
        // NASTAVIT STAV - Přímo nastavit konkrétní stav
        // =============================================
        case 'nastavit_stav':
            // CSRF validace
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }

            if (empty($_POST['event_id']) || empty($_POST['stav'])) {
                sendJsonError('Chybi event_id nebo stav');
            }

            $povoleneStavy = ['wait', 'onway', 'drop'];
            if (!in_array($_POST['stav'], $povoleneStavy)) {
                sendJsonError('Neplatny stav');
            }

            $stmt = $pdo->prepare("UPDATE wgs_transport_events SET stav = :stav, cas_zmeny_stavu = NOW() WHERE event_id = :event_id");
            $stmt->execute([
                'stav' => $_POST['stav'],
                'event_id' => (int)$_POST['event_id'],
            ]);

            if ($stmt->rowCount() === 0) {
                sendJsonError('Transport nenalezen');
            }

            $stavyMapovani = [
                'wait' => 'WAIT',
                'onway' => 'ON THE WAY',
                'drop' => 'DROP OFF'
            ];

            sendJsonSuccess('Stav nastaven', [
                'novy_stav' => $_POST['stav'],
                'novy_stav_text' => $stavyMapovani[$_POST['stav']]
            ]);
            break;

        // =============================================
        // SEZNAM ŘIDIČŮ - Pro dropdown
        // =============================================
        case 'ridici':
            $stmt = $pdo->query("SELECT DISTINCT ridic FROM wgs_transport_events WHERE ridic IS NOT NULL AND ridic != '' ORDER BY ridic");
            $ridici = $stmt->fetchAll(PDO::FETCH_COLUMN);

            sendJsonSuccess('Seznam ridicu', ['ridici' => $ridici]);
            break;

        // =============================================
        // STATISTIKY - Počty podle stavu
        // =============================================
        case 'statistiky':
            $datum = $_GET['datum'] ?? date('Y-m-d');

            $stmt = $pdo->prepare("
                SELECT
                    stav,
                    COUNT(*) as pocet
                FROM wgs_transport_events
                WHERE datum = :datum
                GROUP BY stav
            ");
            $stmt->execute(['datum' => $datum]);
            $statistiky = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            // Doplnit chybějící stavy
            $vysledek = [
                'wait' => (int)($statistiky['wait'] ?? 0),
                'onway' => (int)($statistiky['onway'] ?? 0),
                'drop' => (int)($statistiky['drop'] ?? 0),
                'celkem' => 0
            ];
            $vysledek['celkem'] = $vysledek['wait'] + $vysledek['onway'] + $vysledek['drop'];

            sendJsonSuccess('Statistiky', ['statistiky' => $vysledek]);
            break;

        default:
            sendJsonError('Neznama akce: ' . htmlspecialchars($akce));
    }

} catch (PDOException $e) {
    error_log("Transport Events API Error: " . $e->getMessage());
    sendJsonError('Chyba databaze');
} catch (Exception $e) {
    error_log("Transport Events API Error: " . $e->getMessage());
    sendJsonError('Chyba serveru');
}
