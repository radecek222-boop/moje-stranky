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

// CORS hlavicky pro herni zonu
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: https://www.wgs-service.cz');
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Kontrola přihlášení
if (!isset($_SESSION['user_id'])) {
    sendJsonError('Nejste přihlášeni', 401);
}

$userId = $_SESSION['user_id'];
$username = $_SESSION['user_name'] ?? $_SESSION['user_email'] ?? 'Hráč';

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

            // Globální chat (posledních 10)
            $stmtChat = $pdo->query("
                SELECT id, username, zprava, DATE_FORMAT(cas, '%H:%i') as cas
                FROM wgs_hry_chat
                WHERE mistnost_id IS NULL
                ORDER BY id DESC
                LIMIT 10
            ");
            $chat = array_reverse($stmtChat->fetchAll(PDO::FETCH_ASSOC));

            sendJsonSuccess('OK', ['online' => $online, 'chat' => $chat]);
            break;

        // ===== CHAT POLL - rychlé načtení nových zpráv =====
        case 'chat_poll':
            $posledniId = (int)($_GET['posledni_id'] ?? 0);

            $stmt = $pdo->prepare("
                SELECT id, username, zprava, DATE_FORMAT(cas, '%H:%i') as cas
                FROM wgs_hry_chat
                WHERE mistnost_id IS NULL AND id > :posledni_id
                ORDER BY id ASC
                LIMIT 10
            ");
            $stmt->execute(['posledni_id' => $posledniId]);
            $chat = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonSuccess('OK', ['chat' => $chat]);
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
                WHERE m.hra = :hra AND m.stav IN ('ceka', 'hra')
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

            if ($mistnost['stav'] !== 'ceka') {
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

            if ($mistnost['stav'] !== 'ceka') {
                sendJsonError('Hra již probíhá');
            }

            // Změnit stav na "hra"
            $stmt = $pdo->prepare("UPDATE wgs_hry_mistnosti SET stav = 'hra' WHERE id = :id");
            $stmt->execute(['id' => $mistnostId]);

            sendJsonSuccess('Hra spuštěna');
            break;

        // ===== PIŠKVORKY - TAH =====
        case 'piskvorky_tah':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $mistnostId = (int)($_POST['mistnost_id'] ?? 0);
            $radek = (int)($_POST['radek'] ?? -1);
            $sloupec = (int)($_POST['sloupec'] ?? -1);

            if ($radek < 0 || $radek > 14 || $sloupec < 0 || $sloupec > 14) {
                sendJsonError('Neplatná pozice');
            }

            // Načíst místnost a herní stav
            $stmt = $pdo->prepare("SELECT * FROM wgs_hry_mistnosti WHERE id = :id AND hra = 'piskvorky'");
            $stmt->execute(['id' => $mistnostId]);
            $mistnost = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mistnost || $mistnost['stav'] !== 'hra') {
                sendJsonError('Hra neexistuje nebo neprobíhá');
            }

            // Načíst hráče
            $stmt = $pdo->prepare("SELECT user_id, username, poradi FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = :id ORDER BY poradi");
            $stmt->execute(['id' => $mistnostId]);
            $hraci = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($hraci) < 2) {
                sendJsonError('Čekám na druhého hráče');
            }

            // Dekódovat herní stav
            $herniStav = json_decode($mistnost['herni_stav'] ?: '{}', true);
            if (empty($herniStav)) {
                // Inicializovat prázdnou hrací plochu 15x15
                $herniStav = [
                    'plocha' => array_fill(0, 15, array_fill(0, 15, 0)),
                    'na_tahu' => 1, // 1 = X (první hráč), 2 = O (druhý hráč)
                    'vitez' => null
                ];
            }

            // Zkontrolovat, zda je hráč na tahu
            $hracIndex = array_search($userId, array_column($hraci, 'user_id'));
            if ($hracIndex === false) {
                sendJsonError('Nejste v této hře');
            }

            $hracSymbol = ($hracIndex == 0) ? 1 : 2; // 1 = X, 2 = O

            if ($herniStav['na_tahu'] != $hracSymbol) {
                sendJsonError('Nejste na tahu');
            }

            if ($herniStav['vitez']) {
                sendJsonError('Hra již skončila');
            }

            // Zkontrolovat, zda je pole prázdné
            if ($herniStav['plocha'][$radek][$sloupec] != 0) {
                sendJsonError('Pole je obsazené');
            }

            // Provést tah
            $herniStav['plocha'][$radek][$sloupec] = $hracSymbol;
            $herniStav['na_tahu'] = ($hracSymbol == 1) ? 2 : 1;

            // Zkontrolovat výhru (5 v řadě)
            $vitez = zkontrolujVyhruPiskvorky($herniStav['plocha'], $radek, $sloupec, $hracSymbol);
            if ($vitez) {
                $herniStav['vitez'] = $hracSymbol;
                // Aktualizovat stav místnosti
                $stmt = $pdo->prepare("UPDATE wgs_hry_mistnosti SET stav = 'dokoncena' WHERE id = :id");
                $stmt->execute(['id' => $mistnostId]);
            }

            // Zkontrolovat remízu (plná plocha)
            $remiza = true;
            foreach ($herniStav['plocha'] as $r) {
                if (in_array(0, $r)) {
                    $remiza = false;
                    break;
                }
            }
            if ($remiza && !$vitez) {
                $herniStav['vitez'] = 'remiza';
                $stmt = $pdo->prepare("UPDATE wgs_hry_mistnosti SET stav = 'dokoncena' WHERE id = :id");
                $stmt->execute(['id' => $mistnostId]);
            }

            // Uložit herní stav
            $stmt = $pdo->prepare("UPDATE wgs_hry_mistnosti SET herni_stav = :stav WHERE id = :id");
            $stmt->execute(['stav' => json_encode($herniStav), 'id' => $mistnostId]);

            sendJsonSuccess('Tah proveden', [
                'plocha' => $herniStav['plocha'],
                'na_tahu' => $herniStav['na_tahu'],
                'vitez' => $herniStav['vitez'],
                'hraci' => $hraci
            ]);
            break;

        // ===== PIŠKVORKY - STAV HRY =====
        case 'piskvorky_stav':
            $mistnostId = (int)($_GET['mistnost_id'] ?? 0);

            $stmt = $pdo->prepare("SELECT * FROM wgs_hry_mistnosti WHERE id = :id AND hra = 'piskvorky'");
            $stmt->execute(['id' => $mistnostId]);
            $mistnost = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mistnost) {
                sendJsonError('Hra neexistuje');
            }

            $herniStav = json_decode($mistnost['herni_stav'] ?: '{}', true);
            if (empty($herniStav)) {
                $herniStav = [
                    'plocha' => array_fill(0, 15, array_fill(0, 15, 0)),
                    'na_tahu' => 1,
                    'vitez' => null
                ];
            }

            $stmt = $pdo->prepare("SELECT user_id, username, poradi FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = :id ORDER BY poradi");
            $stmt->execute(['id' => $mistnostId]);
            $hraci = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $hracIndex = array_search($userId, array_column($hraci, 'user_id'));
            $mujSymbol = ($hracIndex === 0) ? 1 : (($hracIndex === 1) ? 2 : null);

            sendJsonSuccess('OK', [
                'stav' => $mistnost['stav'],
                'plocha' => $herniStav['plocha'],
                'na_tahu' => $herniStav['na_tahu'],
                'vitez' => $herniStav['vitez'],
                'hraci' => $hraci,
                'muj_symbol' => $mujSymbol,
                'jsem_na_tahu' => ($mujSymbol === $herniStav['na_tahu'])
            ]);
            break;

        // ===== LODĚ - ROZMÍSTĚNÍ =====
        case 'lode_rozmisteni':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $mistnostId = (int)($_POST['mistnost_id'] ?? 0);
            $lode = json_decode($_POST['lode'] ?? '[]', true);

            $stmt = $pdo->prepare("SELECT * FROM wgs_hry_mistnosti WHERE id = :id AND hra = 'lode'");
            $stmt->execute(['id' => $mistnostId]);
            $mistnost = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mistnost) {
                sendJsonError('Hra neexistuje');
            }

            // Uložit rozmístění pro tohoto hráče
            $herniStav = json_decode($mistnost['herni_stav'] ?: '{}', true);
            if (empty($herniStav)) {
                $herniStav = [
                    'hrac1' => ['lode' => null, 'zasahy' => []],
                    'hrac2' => ['lode' => null, 'zasahy' => []],
                    'na_tahu' => 1,
                    'vitez' => null
                ];
            }

            $stmt = $pdo->prepare("SELECT user_id FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = :id ORDER BY poradi");
            $stmt->execute(['id' => $mistnostId]);
            $hraciIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $hracKlic = ($userId == $hraciIds[0]) ? 'hrac1' : 'hrac2';
            $herniStav[$hracKlic]['lode'] = $lode;

            // Pokud oba hráči rozmístili lodě, změnit stav na "hra"
            if ($herniStav['hrac1']['lode'] && $herniStav['hrac2']['lode']) {
                $stmt = $pdo->prepare("UPDATE wgs_hry_mistnosti SET stav = 'hra' WHERE id = :id");
                $stmt->execute(['id' => $mistnostId]);
            }

            $stmt = $pdo->prepare("UPDATE wgs_hry_mistnosti SET herni_stav = :stav WHERE id = :id");
            $stmt->execute(['stav' => json_encode($herniStav), 'id' => $mistnostId]);

            sendJsonSuccess('Lodě rozmístěny');
            break;

        // ===== LODĚ - STŘELA =====
        case 'lode_strela':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $mistnostId = (int)($_POST['mistnost_id'] ?? 0);
            $radek = (int)($_POST['radek'] ?? -1);
            $sloupec = (int)($_POST['sloupec'] ?? -1);

            if ($radek < 0 || $radek > 9 || $sloupec < 0 || $sloupec > 9) {
                sendJsonError('Neplatná pozice');
            }

            $stmt = $pdo->prepare("SELECT * FROM wgs_hry_mistnosti WHERE id = :id AND hra = 'lode'");
            $stmt->execute(['id' => $mistnostId]);
            $mistnost = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mistnost || $mistnost['stav'] !== 'hra') {
                sendJsonError('Hra neprobíhá');
            }

            $herniStav = json_decode($mistnost['herni_stav'], true);

            $stmt = $pdo->prepare("SELECT user_id FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = :id ORDER BY poradi");
            $stmt->execute(['id' => $mistnostId]);
            $hraciIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $jsemHrac1 = ($userId == $hraciIds[0]);
            $mojeKlic = $jsemHrac1 ? 'hrac1' : 'hrac2';
            $souperKlic = $jsemHrac1 ? 'hrac2' : 'hrac1';
            $hracCislo = $jsemHrac1 ? 1 : 2;

            if ($herniStav['na_tahu'] != $hracCislo) {
                sendJsonError('Nejste na tahu');
            }

            if ($herniStav['vitez']) {
                sendJsonError('Hra skončila');
            }

            // Zkontrolovat, zda už tam nestřílel
            $poziceKlic = "{$radek}_{$sloupec}";
            if (in_array($poziceKlic, $herniStav[$mojeKlic]['zasahy'])) {
                sendJsonError('Sem jste už stříleli');
            }

            // Zjistit, zda je zásah
            $souperovyLode = $herniStav[$souperKlic]['lode'];
            $zasah = false;
            $potopena = null;

            foreach ($souperovyLode as $lodIndex => $lod) {
                foreach ($lod['pozice'] as $poz) {
                    if ($poz[0] == $radek && $poz[1] == $sloupec) {
                        $zasah = true;
                        // Zkontrolovat, zda je loď potopená
                        $vsechnyZasazeny = true;
                        foreach ($lod['pozice'] as $kontrolaPoz) {
                            $kontrolaKlic = "{$kontrolaPoz[0]}_{$kontrolaPoz[1]}";
                            if ($kontrolaKlic !== $poziceKlic && !in_array($kontrolaKlic, $herniStav[$mojeKlic]['zasahy'])) {
                                $vsechnyZasazeny = false;
                                break;
                            }
                        }
                        if ($vsechnyZasazeny) {
                            $potopena = $lod['nazev'];
                        }
                        break 2;
                    }
                }
            }

            // Zaznamenat zásah
            $herniStav[$mojeKlic]['zasahy'][] = $poziceKlic;

            // Pokud nezasáhl, předat tah
            if (!$zasah) {
                $herniStav['na_tahu'] = $jsemHrac1 ? 2 : 1;
            }

            // Zkontrolovat výhru (všechny lodě potopeny)
            $vsechnyPotopeny = true;
            foreach ($souperovyLode as $lod) {
                foreach ($lod['pozice'] as $poz) {
                    $kontrolaKlic = "{$poz[0]}_{$poz[1]}";
                    if (!in_array($kontrolaKlic, $herniStav[$mojeKlic]['zasahy'])) {
                        $vsechnyPotopeny = false;
                        break 2;
                    }
                }
            }

            if ($vsechnyPotopeny) {
                $herniStav['vitez'] = $hracCislo;
                $stmt = $pdo->prepare("UPDATE wgs_hry_mistnosti SET stav = 'dokoncena' WHERE id = :id");
                $stmt->execute(['id' => $mistnostId]);
            }

            $stmt = $pdo->prepare("UPDATE wgs_hry_mistnosti SET herni_stav = :stav WHERE id = :id");
            $stmt->execute(['stav' => json_encode($herniStav), 'id' => $mistnostId]);

            sendJsonSuccess('Střela', [
                'zasah' => $zasah,
                'potopena' => $potopena,
                'vitez' => $herniStav['vitez'],
                'na_tahu' => $herniStav['na_tahu']
            ]);
            break;

        // ===== LODĚ - STAV =====
        case 'lode_stav':
            $mistnostId = (int)($_GET['mistnost_id'] ?? 0);

            $stmt = $pdo->prepare("SELECT * FROM wgs_hry_mistnosti WHERE id = :id AND hra = 'lode'");
            $stmt->execute(['id' => $mistnostId]);
            $mistnost = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mistnost) {
                sendJsonError('Hra neexistuje');
            }

            $herniStav = json_decode($mistnost['herni_stav'] ?: '{}', true);

            $stmt = $pdo->prepare("SELECT user_id, username FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = :id ORDER BY poradi");
            $stmt->execute(['id' => $mistnostId]);
            $hraci = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $jsemHrac1 = (count($hraci) > 0 && $userId == $hraci[0]['user_id']);
            $mojeKlic = $jsemHrac1 ? 'hrac1' : 'hrac2';
            $souperKlic = $jsemHrac1 ? 'hrac2' : 'hrac1';

            // Vrátit pouze vlastní lodě a zásahy obou hráčů (ne pozice soupeřových lodí)
            $odpoved = [
                'stav' => $mistnost['stav'],
                'hraci' => $hraci,
                'moje_lode' => $herniStav[$mojeKlic]['lode'] ?? null,
                'moje_zasahy' => $herniStav[$mojeKlic]['zasahy'] ?? [],
                'souper_zasahy' => $herniStav[$souperKlic]['zasahy'] ?? [],
                'na_tahu' => $herniStav['na_tahu'] ?? 1,
                'vitez' => $herniStav['vitez'] ?? null,
                'jsem_hrac1' => $jsemHrac1,
                'souper_pripraveny' => ($herniStav[$souperKlic]['lode'] ?? null) !== null
            ];

            sendJsonSuccess('OK', $odpoved);
            break;

        // ===== PONG - STAV =====
        case 'pong_stav':
            $mistnostId = (int)($_GET['mistnost_id'] ?? 0);

            $stmt = $pdo->prepare("SELECT * FROM wgs_hry_mistnosti WHERE id = :id AND hra = 'pong'");
            $stmt->execute(['id' => $mistnostId]);
            $mistnost = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mistnost) {
                sendJsonError('Hra neexistuje');
            }

            $herniStav = json_decode($mistnost['herni_stav'] ?: '{}', true);

            $stmt = $pdo->prepare("SELECT user_id, username FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = :id ORDER BY poradi");
            $stmt->execute(['id' => $mistnostId]);
            $hraci = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $jsemHrac1 = (count($hraci) > 0 && $userId == $hraci[0]['user_id']);

            sendJsonSuccess('OK', [
                'stav' => $mistnost['stav'],
                'hraci' => $hraci,
                'herni_stav' => $herniStav,
                'jsem_hrac1' => $jsemHrac1
            ]);
            break;

        // ===== PONG - POZICE PALKY =====
        case 'pong_pozice':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $mistnostId = (int)($_POST['mistnost_id'] ?? 0);
            $poziceY = (float)($_POST['pozice_y'] ?? 0);

            $stmt = $pdo->prepare("SELECT * FROM wgs_hry_mistnosti WHERE id = :id AND hra = 'pong'");
            $stmt->execute(['id' => $mistnostId]);
            $mistnost = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mistnost) {
                sendJsonError('Hra neexistuje');
            }

            $herniStav = json_decode($mistnost['herni_stav'] ?: '{}', true);

            $stmt = $pdo->prepare("SELECT user_id FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = :id ORDER BY poradi");
            $stmt->execute(['id' => $mistnostId]);
            $hraciIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $jsemHrac1 = ($userId == $hraciIds[0]);
            $klic = $jsemHrac1 ? 'palka1_y' : 'palka2_y';

            $herniStav[$klic] = $poziceY;

            $stmt = $pdo->prepare("UPDATE wgs_hry_mistnosti SET herni_stav = :stav WHERE id = :id");
            $stmt->execute(['stav' => json_encode($herniStav), 'id' => $mistnostId]);

            sendJsonSuccess('OK', [
                'palka1_y' => $herniStav['palka1_y'] ?? 200,
                'palka2_y' => $herniStav['palka2_y'] ?? 200
            ]);
            break;

        // ===== PONG - AKTUALIZACE MÍČKU (HOST) =====
        case 'pong_micek':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $mistnostId = (int)($_POST['mistnost_id'] ?? 0);
            $micekX = (float)($_POST['micek_x'] ?? 400);
            $micekY = (float)($_POST['micek_y'] ?? 250);
            $micekDx = (float)($_POST['micek_dx'] ?? 5);
            $micekDy = (float)($_POST['micek_dy'] ?? 3);
            $skore1 = (int)($_POST['skore1'] ?? 0);
            $skore2 = (int)($_POST['skore2'] ?? 0);

            $stmt = $pdo->prepare("SELECT * FROM wgs_hry_mistnosti WHERE id = :id AND hra = 'pong'");
            $stmt->execute(['id' => $mistnostId]);
            $mistnost = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$mistnost) {
                sendJsonError('Hra neexistuje');
            }

            // Pouze hostitel může aktualizovat míček
            if ($mistnost['vytvoril_user_id'] != $userId) {
                sendJsonError('Pouze hostitel řídí míček');
            }

            $herniStav = json_decode($mistnost['herni_stav'] ?: '{}', true);
            $herniStav['micek_x'] = $micekX;
            $herniStav['micek_y'] = $micekY;
            $herniStav['micek_dx'] = $micekDx;
            $herniStav['micek_dy'] = $micekDy;
            $herniStav['skore1'] = $skore1;
            $herniStav['skore2'] = $skore2;

            // Zkontrolovat výhru
            if ($skore1 >= 11 || $skore2 >= 11) {
                $herniStav['vitez'] = ($skore1 >= 11) ? 1 : 2;
                $stmt = $pdo->prepare("UPDATE wgs_hry_mistnosti SET stav = 'dokoncena' WHERE id = :id");
                $stmt->execute(['id' => $mistnostId]);
            }

            $stmt = $pdo->prepare("UPDATE wgs_hry_mistnosti SET herni_stav = :stav WHERE id = :id");
            $stmt->execute(['stav' => json_encode($herniStav), 'id' => $mistnostId]);

            sendJsonSuccess('OK');
            break;

        // ===== QUICK MATCH - rychlé spárování =====
        case 'quick_match':
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $hra = $_POST['hra'] ?? '';
            if (!in_array($hra, ['piskvorky', 'lode', 'pong', 'prsi'])) {
                sendJsonError('Neplatná hra');
            }

            // Najít volnou místnost čekající na hráče
            $stmt = $pdo->prepare("
                SELECT m.id, m.nazev, m.max_hracu,
                       (SELECT COUNT(*) FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = m.id) as pocet
                FROM wgs_hry_mistnosti m
                WHERE m.hra = :hra AND m.stav = 'ceka'
                HAVING pocet < max_hracu
                ORDER BY m.vytvoreno ASC
                LIMIT 1
            ");
            $stmt->execute(['hra' => $hra]);
            $volnaMistnost = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($volnaMistnost) {
                // Připojit se do existující místnosti
                $mistnostId = $volnaMistnost['id'];

                // Zkontrolovat, zda tam už nejsem
                $stmt = $pdo->prepare("SELECT id FROM wgs_hry_hraci_mistnosti WHERE mistnost_id = :mid AND user_id = :uid");
                $stmt->execute(['mid' => $mistnostId, 'uid' => $userId]);
                if (!$stmt->fetch()) {
                    $stmt = $pdo->prepare("
                        INSERT INTO wgs_hry_hraci_mistnosti (mistnost_id, user_id, username, poradi)
                        VALUES (:mistnost_id, :user_id, :username, :poradi)
                    ");
                    $stmt->execute([
                        'mistnost_id' => $mistnostId,
                        'user_id' => $userId,
                        'username' => $username,
                        'poradi' => $volnaMistnost['pocet'] + 1
                    ]);
                }

                // Pro dvouhráčové hry automaticky spustit
                if (in_array($hra, ['piskvorky', 'pong']) && $volnaMistnost['pocet'] + 1 >= 2) {
                    $stmt = $pdo->prepare("UPDATE wgs_hry_mistnosti SET stav = 'hra' WHERE id = :id");
                    $stmt->execute(['id' => $mistnostId]);
                }

                $stmt = $pdo->prepare("UPDATE wgs_hry_online SET aktualni_hra = :hra, mistnost_id = :mid WHERE user_id = :uid");
                $stmt->execute(['hra' => $hra, 'mid' => $mistnostId, 'uid' => $userId]);

                sendJsonSuccess('Připojeno', ['mistnost_id' => (int)$mistnostId]);
            } else {
                // Vytvořit novou místnost
                $maxHracu = in_array($hra, ['piskvorky', 'lode', 'pong']) ? 2 : 4;
                $nazev = $username . ' - ' . ucfirst($hra);

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

                $stmt = $pdo->prepare("
                    INSERT INTO wgs_hry_hraci_mistnosti (mistnost_id, user_id, username, poradi)
                    VALUES (:mistnost_id, :user_id, :username, 1)
                ");
                $stmt->execute([
                    'mistnost_id' => $mistnostId,
                    'user_id' => $userId,
                    'username' => $username
                ]);

                $stmt = $pdo->prepare("UPDATE wgs_hry_online SET aktualni_hra = :hra, mistnost_id = :mid WHERE user_id = :uid");
                $stmt->execute(['hra' => $hra, 'mid' => $mistnostId, 'uid' => $userId]);

                sendJsonSuccess('Místnost vytvořena, čekám na soupeře', ['mistnost_id' => (int)$mistnostId, 'cekam' => true]);
            }
            break;

        default:
            sendJsonError('Neznámá akce');
    }

    // ===== POMOCNÉ FUNKCE =====
    function zkontrolujVyhruPiskvorky($plocha, $radek, $sloupec, $symbol) {
        $smery = [
            [0, 1],   // horizontálně
            [1, 0],   // vertikálně
            [1, 1],   // diagonálně \
            [1, -1]   // diagonálně /
        ];

        foreach ($smery as $smer) {
            $pocet = 1;

            // Směr +
            for ($i = 1; $i < 5; $i++) {
                $r = $radek + $smer[0] * $i;
                $s = $sloupec + $smer[1] * $i;
                if ($r >= 0 && $r < 15 && $s >= 0 && $s < 15 && $plocha[$r][$s] == $symbol) {
                    $pocet++;
                } else {
                    break;
                }
            }

            // Směr -
            for ($i = 1; $i < 5; $i++) {
                $r = $radek - $smer[0] * $i;
                $s = $sloupec - $smer[1] * $i;
                if ($r >= 0 && $r < 15 && $s >= 0 && $s < 15 && $plocha[$r][$s] == $symbol) {
                    $pocet++;
                } else {
                    break;
                }
            }

            if ($pocet >= 5) {
                return true;
            }
        }

        return false;
    }

} catch (PDOException $e) {
    error_log("Hry API PDO error: " . $e->getMessage());
    sendJsonError('Chyba databaze: ' . $e->getMessage());
} catch (Exception $e) {
    error_log("Hry API error: " . $e->getMessage());
    sendJsonError('Chyba serveru: ' . $e->getMessage());
}
?>
