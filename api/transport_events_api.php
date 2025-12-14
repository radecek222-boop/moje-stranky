<?php
/**
 * API pro správu transportních eventů a transportů
 *
 * Endpoint: /api/transport_events_api.php
 *
 * Akce pro EVENTY:
 * - eventy_list, event_detail, event_create, event_update, event_delete
 *
 * Akce pro RIDICE:
 * - ridici_list, ridic_create, ridic_update, ridic_delete
 *
 * Akce pro TRANSPORTY:
 * - list, create, update, delete, zmena_stavu
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
        // EVENTY - Seznam
        // =============================================
        case 'eventy_list':
            $stmt = $pdo->query("
                SELECT
                    e.*,
                    (SELECT COUNT(*) FROM wgs_transport_events t WHERE t.parent_event_id = e.event_id) as pocet_transportu
                FROM wgs_transport_akce e
                ORDER BY e.datum_od DESC, e.nazev ASC
            ");
            $eventy = $stmt->fetchAll(PDO::FETCH_ASSOC);
            sendJsonSuccess('Seznam eventu', ['eventy' => $eventy]);
            break;

        // =============================================
        // EVENT - Detail
        // =============================================
        case 'event_detail':
            if (empty($_GET['event_id'])) {
                sendJsonError('Chybi event_id');
            }
            $stmt = $pdo->prepare("SELECT * FROM wgs_transport_akce WHERE event_id = :id");
            $stmt->execute(['id' => (int)$_GET['event_id']]);
            $event = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$event) {
                sendJsonError('Event nenalezen');
            }
            sendJsonSuccess('Detail eventu', ['event' => $event]);
            break;

        // =============================================
        // EVENT - Vytvoření
        // =============================================
        case 'event_create':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }
            if (empty($_POST['nazev'])) {
                sendJsonError('Chybi nazev eventu');
            }

            $stmt = $pdo->prepare("
                INSERT INTO wgs_transport_akce (nazev, datum_od, datum_do, popis)
                VALUES (:nazev, :datum_od, :datum_do, :popis)
            ");
            $stmt->execute([
                'nazev' => trim($_POST['nazev']),
                'datum_od' => $_POST['datum_od'] ?: null,
                'datum_do' => $_POST['datum_do'] ?: null,
                'popis' => trim($_POST['popis'] ?? '') ?: null,
            ]);

            sendJsonSuccess('Event vytvoren', ['event_id' => $pdo->lastInsertId()]);
            break;

        // =============================================
        // EVENT - Aktualizace
        // =============================================
        case 'event_update':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }
            if (empty($_POST['event_id']) || empty($_POST['nazev'])) {
                sendJsonError('Chybi povinne pole');
            }

            $stmt = $pdo->prepare("
                UPDATE wgs_transport_akce
                SET nazev = :nazev, datum_od = :datum_od, datum_do = :datum_do, popis = :popis
                WHERE event_id = :event_id
            ");
            $stmt->execute([
                'nazev' => trim($_POST['nazev']),
                'datum_od' => $_POST['datum_od'] ?: null,
                'datum_do' => $_POST['datum_do'] ?: null,
                'popis' => trim($_POST['popis'] ?? '') ?: null,
                'event_id' => (int)$_POST['event_id'],
            ]);

            sendJsonSuccess('Event aktualizovan');
            break;

        // =============================================
        // EVENT - Smazání
        // =============================================
        case 'event_delete':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }
            if (empty($_POST['event_id'])) {
                sendJsonError('Chybi event_id');
            }

            // Smazat všechny transporty v eventu
            $stmt = $pdo->prepare("DELETE FROM wgs_transport_events WHERE parent_event_id = :id");
            $stmt->execute(['id' => (int)$_POST['event_id']]);

            // Smazat všechny řidiče v eventu
            $stmt = $pdo->prepare("DELETE FROM wgs_transport_ridici WHERE event_id = :id");
            $stmt->execute(['id' => (int)$_POST['event_id']]);

            // Smazat event
            $stmt = $pdo->prepare("DELETE FROM wgs_transport_akce WHERE event_id = :id");
            $stmt->execute(['id' => (int)$_POST['event_id']]);

            sendJsonSuccess('Event smazan');
            break;

        // =============================================
        // RIDICI - Seznam pro event
        // =============================================
        case 'ridici_list':
            if (empty($_GET['event_id'])) {
                sendJsonError('Chybi event_id');
            }

            $stmt = $pdo->prepare("
                SELECT * FROM wgs_transport_ridici
                WHERE event_id = :event_id
                ORDER BY jmeno ASC
            ");
            $stmt->execute(['event_id' => (int)$_GET['event_id']]);
            $ridici = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonSuccess('Seznam ridicu', ['ridici' => $ridici]);
            break;

        // =============================================
        // RIDIC - Vytvoření
        // =============================================
        case 'ridic_create':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }
            if (empty($_POST['event_id']) || empty($_POST['jmeno'])) {
                sendJsonError('Chybi povinne pole');
            }

            $stmt = $pdo->prepare("
                INSERT INTO wgs_transport_ridici (event_id, jmeno, telefon, auto, spz, poznamka)
                VALUES (:event_id, :jmeno, :telefon, :auto, :spz, :poznamka)
            ");
            $stmt->execute([
                'event_id' => (int)$_POST['event_id'],
                'jmeno' => trim($_POST['jmeno']),
                'telefon' => trim($_POST['telefon'] ?? '') ?: null,
                'auto' => trim($_POST['auto'] ?? '') ?: null,
                'spz' => trim($_POST['spz'] ?? '') ?: null,
                'poznamka' => trim($_POST['poznamka'] ?? '') ?: null,
            ]);

            sendJsonSuccess('Ridic vytvoren', ['ridic_id' => $pdo->lastInsertId()]);
            break;

        // =============================================
        // RIDIC - Aktualizace
        // =============================================
        case 'ridic_update':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }
            if (empty($_POST['ridic_id']) || empty($_POST['jmeno'])) {
                sendJsonError('Chybi povinne pole');
            }

            $stmt = $pdo->prepare("
                UPDATE wgs_transport_ridici
                SET jmeno = :jmeno, telefon = :telefon, auto = :auto, spz = :spz, poznamka = :poznamka
                WHERE ridic_id = :ridic_id
            ");
            $stmt->execute([
                'jmeno' => trim($_POST['jmeno']),
                'telefon' => trim($_POST['telefon'] ?? '') ?: null,
                'auto' => trim($_POST['auto'] ?? '') ?: null,
                'spz' => trim($_POST['spz'] ?? '') ?: null,
                'poznamka' => trim($_POST['poznamka'] ?? '') ?: null,
                'ridic_id' => (int)$_POST['ridic_id'],
            ]);

            sendJsonSuccess('Ridic aktualizovan');
            break;

        // =============================================
        // RIDIC - Smazání
        // =============================================
        case 'ridic_delete':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }
            if (empty($_POST['ridic_id'])) {
                sendJsonError('Chybi ridic_id');
            }

            $stmt = $pdo->prepare("DELETE FROM wgs_transport_ridici WHERE ridic_id = :id");
            $stmt->execute(['id' => (int)$_POST['ridic_id']]);

            sendJsonSuccess('Ridic smazan');
            break;

        // =============================================
        // TRANSPORTY - Seznam
        // =============================================
        case 'list':
            $eventId = $_GET['event_id'] ?? null;
            $datum = $_GET['datum'] ?? null;
            $stav = $_GET['stav'] ?? null;

            $sql = "SELECT t.*, r.jmeno as ridic_jmeno, r.telefon as ridic_telefon, r.auto as ridic_auto
                    FROM wgs_transport_events t
                    LEFT JOIN wgs_transport_ridici r ON t.ridic_id = r.ridic_id
                    WHERE 1=1";
            $params = [];

            if ($eventId) {
                $sql .= " AND t.parent_event_id = :event_id";
                $params['event_id'] = (int)$eventId;
            }

            if ($datum) {
                $sql .= " AND t.datum = :datum";
                $params['datum'] = $datum;
            }

            if ($stav) {
                $sql .= " AND t.stav = :stav";
                $params['stav'] = $stav;
            }

            $sql .= " ORDER BY t.datum ASC, t.cas ASC";

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $transporty = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mapování stavu
            $stavyMapovani = ['wait' => 'WAIT', 'onway' => 'ON THE WAY', 'drop' => 'DROP OFF'];
            foreach ($transporty as &$transport) {
                $transport['stav_text'] = $stavyMapovani[$transport['stav']] ?? $transport['stav'];
            }

            sendJsonSuccess('Seznam transportu', ['transporty' => $transporty]);
            break;

        // =============================================
        // TRANSPORT - Vytvoření
        // =============================================
        case 'create':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }

            $povinne = ['jmeno_prijmeni', 'cas', 'datum'];
            foreach ($povinne as $pole) {
                if (empty($_POST[$pole])) {
                    sendJsonError("Chybi povinne pole: {$pole}");
                }
            }

            $stmt = $pdo->prepare("
                INSERT INTO wgs_transport_events
                (parent_event_id, jmeno_prijmeni, cas, cislo_letu, destinace, cas_priletu, telefon, email, ridic_id, stav, datum, poznamka)
                VALUES
                (:parent_event_id, :jmeno_prijmeni, :cas, :cislo_letu, :destinace, :cas_priletu, :telefon, :email, :ridic_id, 'wait', :datum, :poznamka)
            ");
            $stmt->execute([
                'parent_event_id' => $_POST['parent_event_id'] ?: null,
                'jmeno_prijmeni' => trim($_POST['jmeno_prijmeni']),
                'cas' => $_POST['cas'],
                'cislo_letu' => trim($_POST['cislo_letu'] ?? '') ?: null,
                'destinace' => trim($_POST['destinace'] ?? '') ?: null,
                'cas_priletu' => $_POST['cas_priletu'] ?: null,
                'telefon' => trim($_POST['telefon'] ?? '') ?: null,
                'email' => trim($_POST['email'] ?? '') ?: null,
                'ridic_id' => $_POST['ridic_id'] ?: null,
                'datum' => $_POST['datum'],
                'poznamka' => trim($_POST['poznamka'] ?? '') ?: null,
            ]);

            sendJsonSuccess('Transport vytvoren', ['event_id' => $pdo->lastInsertId()]);
            break;

        // =============================================
        // TRANSPORT - Aktualizace
        // =============================================
        case 'update':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }
            if (empty($_POST['event_id'])) {
                sendJsonError('Chybi event_id');
            }

            $povinne = ['jmeno_prijmeni', 'cas', 'datum'];
            foreach ($povinne as $pole) {
                if (empty($_POST[$pole])) {
                    sendJsonError("Chybi povinne pole: {$pole}");
                }
            }

            $stmt = $pdo->prepare("
                UPDATE wgs_transport_events SET
                jmeno_prijmeni = :jmeno_prijmeni,
                cas = :cas,
                cislo_letu = :cislo_letu,
                destinace = :destinace,
                cas_priletu = :cas_priletu,
                telefon = :telefon,
                email = :email,
                ridic_id = :ridic_id,
                datum = :datum,
                poznamka = :poznamka
                WHERE event_id = :event_id
            ");
            $stmt->execute([
                'jmeno_prijmeni' => trim($_POST['jmeno_prijmeni']),
                'cas' => $_POST['cas'],
                'cislo_letu' => trim($_POST['cislo_letu'] ?? '') ?: null,
                'destinace' => trim($_POST['destinace'] ?? '') ?: null,
                'cas_priletu' => $_POST['cas_priletu'] ?: null,
                'telefon' => trim($_POST['telefon'] ?? '') ?: null,
                'email' => trim($_POST['email'] ?? '') ?: null,
                'ridic_id' => $_POST['ridic_id'] ?: null,
                'datum' => $_POST['datum'],
                'poznamka' => trim($_POST['poznamka'] ?? '') ?: null,
                'event_id' => (int)$_POST['event_id'],
            ]);

            sendJsonSuccess('Transport aktualizovan');
            break;

        // =============================================
        // TRANSPORT - Smazání
        // =============================================
        case 'delete':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }
            if (empty($_POST['event_id'])) {
                sendJsonError('Chybi event_id');
            }

            $stmt = $pdo->prepare("DELETE FROM wgs_transport_events WHERE event_id = :id");
            $stmt->execute(['id' => (int)$_POST['event_id']]);

            sendJsonSuccess('Transport smazan');
            break;

        // =============================================
        // TRANSPORT - Změna stavu
        // =============================================
        case 'zmena_stavu':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatny CSRF token', 403);
            }
            if (empty($_POST['event_id'])) {
                sendJsonError('Chybi event_id');
            }

            // Načíst aktuální stav
            $stmt = $pdo->prepare("SELECT stav FROM wgs_transport_events WHERE event_id = :id");
            $stmt->execute(['id' => (int)$_POST['event_id']]);
            $transport = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$transport) {
                sendJsonError('Transport nenalezen');
            }

            // Cyklická změna: wait -> onway -> drop -> wait
            $stavyPoradi = ['wait', 'onway', 'drop'];
            $aktualniIndex = array_search($transport['stav'], $stavyPoradi);
            $novyIndex = ($aktualniIndex + 1) % count($stavyPoradi);
            $novyStav = $stavyPoradi[$novyIndex];

            $stmt = $pdo->prepare("
                UPDATE wgs_transport_events
                SET stav = :stav, cas_zmeny_stavu = NOW()
                WHERE event_id = :id
            ");
            $stmt->execute(['stav' => $novyStav, 'id' => (int)$_POST['event_id']]);

            $stavyMapovani = ['wait' => 'WAIT', 'onway' => 'ON THE WAY', 'drop' => 'DROP OFF'];
            sendJsonSuccess('Stav zmenen', [
                'novy_stav' => $novyStav,
                'novy_stav_text' => $stavyMapovani[$novyStav]
            ]);
            break;

        default:
            sendJsonError('Neznama akce: ' . htmlspecialchars($akce));
    }

} catch (PDOException $e) {
    error_log("Transport Events API Error: " . $e->getMessage());
    sendJsonError('Chyba databaze: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Transport Events API Error: " . $e->getMessage());
    sendJsonError('Chyba serveru');
}
