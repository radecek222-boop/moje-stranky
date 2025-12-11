<?php
/**
 * Univerzální API pro herní zónu
 *
 * Podporuje:
 * - Online status hráčů
 * - Herní místnosti (lobby)
 * - Chat (globální i v místnostech)
 * - Heartbeat
 *
 * Použitelné pro všechny hry (Prší, Mariáš, Piškvorky...)
 */
require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';

header('Content-Type: application/json; charset=utf-8');

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    sendJsonError('Nejste přihlášeni', 401);
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['username'] ?? $_SESSION['email'] ?? 'Hráč';

try {
    $pdo = getDbConnection();

    // GET akce
    $action = $_GET['action'] ?? $_POST['action'] ?? '';

    switch ($action) {
        // ===== HEARTBEAT - udržet online status =====
        case 'heartbeat':
            $stmt = $pdo->prepare("
                INSERT INTO wgs_hry_online (user_id, username, posledni_aktivita)
                VALUES (:user_id, :username, NOW())
                ON DUPLICATE KEY UPDATE posledni_aktivita = NOW()
            ");
            $stmt->execute(['user_id' => $userId, 'username' => $username]);
            sendJsonSuccess('OK');
            break;

        // ===== STAV - načíst online hráče a chat =====
        case 'stav':
            // Smazat neaktivní
            $pdo->exec("DELETE FROM wgs_hry_online WHERE posledni_aktivita < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

            // Online hráči
            $stmtOnline = $pdo->query("SELECT user_id, username, aktualni_hra FROM wgs_hry_online ORDER BY posledni_aktivita DESC");
            $online = [];
            while ($row = $stmtOnline->fetch(PDO::FETCH_ASSOC)) {
                $online[] = [
                    'user_id' => (int)$row['user_id'],
                    'username' => $row['username'],
                    'aktualni_hra' => $row['aktualni_hra'],
                    'ja' => ($row['user_id'] == $userId)
                ];
            }

            // Globální chat (posledních 20)
            $stmtChat = $pdo->query("
                SELECT id, username, zprava, DATE_FORMAT(cas, '%H:%i') as cas
                FROM wgs_hry_chat
                WHERE mistnost_id IS NULL
                ORDER BY id DESC
                LIMIT 20
            ");
            $chat = array_reverse($stmtChat->fetchAll(PDO::FETCH_ASSOC));

            sendJsonSuccess('OK', ['online' => $online, 'chat' => $chat]);
            break;

        // ===== CHAT - odeslat zprávu =====
        case 'chat':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $zprava = trim($_POST['zprava'] ?? '');
            $mistnostId = !empty($_POST['mistnost_id']) ? (int)$_POST['mistnost_id'] : null;

            if (empty($zprava) || mb_strlen($zprava) > 200) {
                sendJsonError('Neplatná zpráva (max 200 znaků)');
            }

            $stmt = $pdo->prepare("
                INSERT INTO wgs_hry_chat (mistnost_id, user_id, username, zprava)
                VALUES (:mistnost_id, :user_id, :username, :zprava)
            ");
            $stmt->execute([
                'mistnost_id' => $mistnostId,
                'user_id' => $userId,
                'username' => $username,
                'zprava' => $zprava
            ]);

            sendJsonSuccess('Zpráva odeslána', [
                'id' => $pdo->lastInsertId(),
                'username' => $username,
                'zprava' => $zprava,
                'cas' => date('H:i')
            ]);
            break;

        // ===== MÍSTNOSTI - seznam volných místností =====
        case 'mistnosti':
            $hra = $_GET['hra'] ?? 'prsi';

            $stmt = $pdo->prepare("
                SELECT m.id, m.nazev, m.stav, m.max_hracu,
                       (SELECT COUNT(*) FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = m.id) as pocet_hracu,
                       u.username as vytvoril
                FROM wgs_hry_mistnosti m
                LEFT JOIN wgs_users u ON m.vytvoril_user_id = u.user_id
                WHERE m.hra = :hra AND m.stav IN ('cekani', 'hra')
                ORDER BY m.vytvoreno DESC
                LIMIT 20
            ");
            $stmt->execute(['hra' => $hra]);
            $mistnosti = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonSuccess('OK', ['mistnosti' => $mistnosti]);
            break;

        // ===== VYTVORIT MÍSTNOST =====
        case 'vytvorit_mistnost':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $hra = $_POST['hra'] ?? 'prsi';
            $nazev = trim($_POST['nazev'] ?? '');
            $maxHracu = (int)($_POST['max_hracu'] ?? 4);

            if (empty($nazev)) {
                $nazev = $username . ' - místnost';
            }

            if ($maxHracu < 2 || $maxHracu > 8) {
                $maxHracu = 4;
            }

            // Vytvořit místnost
            $stmt = $pdo->prepare("
                INSERT INTO wgs_hry_mistnosti (nazev, hra, max_hracu, vytvoril_user_id)
                VALUES (:nazev, :hra, :max_hracu, :user_id)
            ");
            $stmt->execute([
                'nazev' => $nazev,
                'hra' => $hra,
                'max_hracu' => $maxHracu,
                'user_id' => $userId
            ]);

            $mistnostId = $pdo->lastInsertId();

            // Přidat tvůrce jako prvního hráče
            $stmt = $pdo->prepare("
                INSERT INTO wgs_hry_hraci_mistnosti (mistnost_id, user_id, username, poradi)
                VALUES (:mistnost_id, :user_id, :username, 1)
            ");
            $stmt->execute([
                'mistnost_id' => $mistnostId,
                'user_id' => $userId,
                'username' => $username
            ]);

            // Aktualizovat online status
            $stmt = $pdo->prepare("UPDATE wgs_hry_online SET aktualni_hra = :hra, mistnost_id = :mistnost_id WHERE user_id = :user_id");
            $stmt->execute(['hra' => $hra, 'mistnost_id' => $mistnostId, 'user_id' => $userId]);

            sendJsonSuccess('Místnost vytvořena', ['mistnost_id' => (int)$mistnostId]);
            break;

        // ===== PŘIPOJIT SE DO MÍSTNOSTI =====
        case 'pripojit':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $mistnostId = (int)($_POST['mistnost_id'] ?? 0);

            if ($mistnostId <= 0) {
                sendJsonError('Neplatná místnost');
            }

            // Zkontrolovat místnost
            $stmt = $pdo->prepare("
                SELECT m.*, (SELECT COUNT(*) FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = m.id) as pocet
                FROM wgs_hry_mistnosti m
                WHERE m.id = :id
            ");
            $stmt->execute(['id' => $mistnostId]);
            $mistnost = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mistnost) {
                sendJsonError('Místnost neexistuje');
            }

            if ($mistnost['stav'] !== 'cekani') {
                sendJsonError('Hra již probíhá nebo je dokončena');
            }

            if ($mistnost['pocet'] >= $mistnost['max_hracu']) {
                sendJsonError('Místnost je plná');
            }

            // Zkontrolovat zda hráč již není v místnosti
            $stmt = $pdo->prepare("SELECT id FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = :mistnost_id AND user_id = :user_id");
            $stmt->execute(['mistnost_id' => $mistnostId, 'user_id' => $userId]);
            if ($stmt->fetch()) {
                sendJsonSuccess('Již jste v místnosti', ['mistnost_id' => $mistnostId]);
            }

            // Přidat hráče
            $stmt = $pdo->prepare("
                INSERT INTO wgs_hry_hraci_mistnosti (mistnost_id, user_id, username, poradi)
                VALUES (:mistnost_id, :user_id, :username, :poradi)
            ");
            $stmt->execute([
                'mistnost_id' => $mistnostId,
                'user_id' => $userId,
                'username' => $username,
                'poradi' => $mistnost['pocet'] + 1
            ]);

            // Aktualizovat online status
            $stmt = $pdo->prepare("UPDATE wgs_hry_online SET aktualni_hra = :hra, mistnost_id = :mistnost_id WHERE user_id = :user_id");
            $stmt->execute(['hra' => $mistnost['hra'], 'mistnost_id' => $mistnostId, 'user_id' => $userId]);

            sendJsonSuccess('Připojeno', ['mistnost_id' => $mistnostId]);
            break;

        // ===== OPUSTIT MÍSTNOST =====
        case 'opustit':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $mistnostId = (int)($_POST['mistnost_id'] ?? 0);

            // Odstranit hráče z místnosti
            $stmt = $pdo->prepare("DELETE FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = :mistnost_id AND user_id = :user_id");
            $stmt->execute(['mistnost_id' => $mistnostId, 'user_id' => $userId]);

            // Aktualizovat online status
            $stmt = $pdo->prepare("UPDATE wgs_hry_online SET aktualni_hra = NULL, mistnost_id = NULL WHERE user_id = :user_id");
            $stmt->execute(['user_id' => $userId]);

            // Zkontrolovat zda je místnost prázdná
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = :id");
            $stmt->execute(['id' => $mistnostId]);
            if ($stmt->fetchColumn() == 0) {
                // Smazat prázdnou místnost
                $pdo->prepare("DELETE FROM wgs_hry_mistnosti WHERE id = :id")->execute(['id' => $mistnostId]);
            }

            sendJsonSuccess('Opuštěno');
            break;

        // ===== STAV MÍSTNOSTI =====
        case 'stav_mistnosti':
            $mistnostId = (int)($_GET['mistnost_id'] ?? 0);

            if ($mistnostId <= 0) {
                sendJsonError('Neplatná místnost');
            }

            // Místnost
            $stmt = $pdo->prepare("SELECT * FROM wgs_hry_mistnosti WHERE id = :id");
            $stmt->execute(['id' => $mistnostId]);
            $mistnost = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mistnost) {
                sendJsonError('Místnost neexistuje');
            }

            // Hráči v místnosti
            $stmt = $pdo->prepare("
                SELECT user_id, username, poradi
                FROM wgs_hry_hraci_mistnosti
                WHERE mistnost_id = :mistnost_id
                ORDER BY poradi
            ");
            $stmt->execute(['mistnost_id' => $mistnostId]);
            $hraci = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Chat místnosti
            $stmt = $pdo->prepare("
                SELECT id, username, zprava, DATE_FORMAT(cas, '%H:%i') as cas
                FROM wgs_hry_chat
                WHERE mistnost_id = :mistnost_id
                ORDER BY id DESC
                LIMIT 50
            ");
            $stmt->execute(['mistnost_id' => $mistnostId]);
            $chat = array_reverse($stmt->fetchAll(PDO::FETCH_ASSOC));

            sendJsonSuccess('OK', [
                'mistnost' => $mistnost,
                'hraci' => $hraci,
                'chat' => $chat,
                'jsem_v_mistnosti' => in_array($userId, array_column($hraci, 'user_id'))
            ]);
            break;

        // ===== SPUSTIT HRU =====
        case 'spustit_hru':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $mistnostId = (int)($_POST['mistnost_id'] ?? 0);

            // Zkontrolovat místnost a vlastnictví
            $stmt = $pdo->prepare("SELECT * FROM wgs_hry_mistnosti WHERE id = :id");
            $stmt->execute(['id' => $mistnostId]);
            $mistnost = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mistnost) {
                sendJsonError('Místnost neexistuje');
            }

            if ($mistnost['vytvoril_user_id'] != $userId) {
                sendJsonError('Pouze tvůrce může spustit hru');
            }

            if ($mistnost['stav'] !== 'cekani') {
                sendJsonError('Hra již probíhá');
            }

            // Změnit stav na "hra"
            $stmt = $pdo->prepare("UPDATE wgs_hry_mistnosti SET stav = 'hra' WHERE id = :id");
            $stmt->execute(['id' => $mistnostId]);

            sendJsonSuccess('Hra spuštěna');
            break;

        default:
            sendJsonError('Neznámá akce');
    }

} catch (PDOException $e) {
    error_log("Hry API error: " . $e->getMessage());
    sendJsonError('Chyba databáze');
} catch (Exception $e) {
    error_log("Hry API error: " . $e->getMessage());
    sendJsonError('Chyba serveru');
}
?>
