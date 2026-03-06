<?php
/**
 * API pro cenové nabídky
 *
 * Actions:
 * - cenik: Načte položky ceníku pro výběr
 * - vytvorit: Vytvoří novou nabídku
 * - odeslat: Odešle nabídku zákazníkovi emailem
 * - potvrdit: Zákazník potvrdí nabídku (veřejné)
 * - seznam: Seznam nabídek (admin)
 * - detail: Detail nabídky
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/email_sablony_nabidka.php';

header('Content-Type: application/json; charset=utf-8');
// Zakázat cachování pro PWA
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $pdo = getDbConnection();
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

    // Uvolnit session lock – paralelní requesty (load.php, notes_api) se jinak serialisují
    session_write_close();

    // Vytvořit tabulku pouze jednou za životnost PHP-FPM workeru (static přetrvá)
    static $tabulkaOverena = false;
    if (!$tabulkaOverena) {
        vytvorTabulkuNabidky($pdo);
        $tabulkaOverena = true;
    }

    $action = $_GET['action'] ?? $_POST['action'] ?? 'cenik';

    switch ($action) {
        // ========================================
        // CENÍK - Načte položky pro výběr
        // ========================================
        case 'cenik':
            $stmt = $pdo->query("
                SELECT id, category, service_name, description, price_from, price_to, price_unit
                FROM wgs_pricing
                WHERE is_active = 1
                ORDER BY category ASC, display_order ASC
            ");
            $polozky = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonSuccess('Ceník načten', ['polozky' => $polozky]);
            break;

        // ========================================
        // EMAILY_S_NABIDKOU - Seznam emailů zákazníků s aktivní CN (pro seznam.php)
        // Přístup: admin + technik (pouze čtení, technik nemůže vytvářet CN)
        // Vrací emaily + stav nabídky (potvrzena má přednost před odeslana)
        // ========================================
        case 'emaily_s_nabidkou':
            $userRole = strtolower(trim($_SESSION['role'] ?? ''));
            $isTechnik = in_array($userRole, ['technik', 'technician'], true);
            if (!$isAdmin && !$isTechnik) {
                sendJsonError('Přístup odepřen', 403);
            }

            // Načíst emaily s jejich nejvyšším stavem nabídky
            // Priorita: cekame_nd > potvrzena > zamitnuta > odeslana (použijeme MAX a CASE)
            $stmt = $pdo->query("
                SELECT
                    LOWER(zakaznik_email) as email,
                    MAX(CASE
                        WHEN cekame_nd_at IS NOT NULL THEN 4
                        WHEN stav = 'potvrzena' THEN 3
                        WHEN stav = 'zamitnuta' THEN 2
                        WHEN stav = 'odeslana' THEN 1
                        ELSE 0
                    END) as priorita_stavu
                FROM wgs_nabidky
                WHERE stav IN ('potvrzena', 'odeslana', 'zamitnuta')
                AND zakaznik_email IS NOT NULL
                AND zakaznik_email != ''
                GROUP BY LOWER(zakaznik_email)
            ");
            $vysledky = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Zpracovat do mapy email -> stav
            $emaily = [];
            $stavyNabidek = [];
            foreach ($vysledky as $radek) {
                $emaily[] = $radek['email'];
                // Převést prioritu zpět na stav
                if ($radek['priorita_stavu'] == 4) {
                    $stavyNabidek[$radek['email']] = 'cekame_nd';
                } elseif ($radek['priorita_stavu'] == 3) {
                    $stavyNabidek[$radek['email']] = 'potvrzena';
                } elseif ($radek['priorita_stavu'] == 2) {
                    $stavyNabidek[$radek['email']] = 'zamitnuta';
                } else {
                    $stavyNabidek[$radek['email']] = 'odeslana';
                }
            }

            sendJsonSuccess('Emaily načteny', [
                'emaily' => $emaily,
                'stavy' => $stavyNabidek
            ]);
            break;

        // ========================================
        // SEZNAM_PRO_EMAIL - Nabídky pro konkrétní email (pro protokol)
        // ========================================
        case 'seznam_pro_email':
            if (!$isAdmin) {
                sendJsonError('Přístup odepřen', 403);
            }

            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $email = trim($_POST['email'] ?? '');
            if (empty($email)) {
                sendJsonError('Email je povinný');
            }

            // Načíst potvrzené nebo odeslané nabídky pro daný email
            $stmt = $pdo->prepare("
                SELECT id, cislo_nabidky, celkova_cena, mena, stav, vytvoreno_at, potvrzeno_at
                FROM wgs_nabidky
                WHERE zakaznik_email = :email
                AND stav IN ('potvrzena', 'odeslana')
                ORDER BY vytvoreno_at DESC
                LIMIT 20
            ");
            $stmt->execute(['email' => $email]);
            $nabidky = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonSuccess('Nabídky načteny', $nabidky);
            break;

        // ========================================
        // VYTVORIT - Nová nabídka (admin only)
        // ========================================
        case 'vytvorit':
            error_log("nabidka_api vytvorit: Začátek zpracování");

            if (!$isAdmin) {
                error_log("nabidka_api vytvorit: Přístup odepřen - není admin");
                sendJsonError('Přístup odepřen', 403);
            }

            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                error_log("nabidka_api vytvorit: Neplatný CSRF token");
                sendJsonError('Neplatný CSRF token', 403);
            }

            $povinne = ['zakaznik_jmeno', 'zakaznik_email', 'polozky'];
            foreach ($povinne as $pole) {
                if (empty($_POST[$pole])) {
                    error_log("nabidka_api vytvorit: Chybí pole {$pole}");
                    sendJsonError("Chybí povinné pole: {$pole}");
                }
            }

            $polozky = json_decode($_POST['polozky'], true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                error_log("nabidka_api vytvorit: JSON error: " . json_last_error_msg());
                sendJsonError('Chyba při parsování položek: ' . json_last_error_msg());
            }
            if (!is_array($polozky) || empty($polozky)) {
                error_log("nabidka_api vytvorit: Prázdné nebo neplatné položky");
                sendJsonError('Musíte vybrat alespoň jednu položku');
            }

            // Vypočítat celkovou cenu
            $celkovaCena = 0;
            foreach ($polozky as $p) {
                $celkovaCena += floatval($p['cena'] ?? 0) * intval($p['pocet'] ?? 1);
            }

            // Generovat unikátní token pro potvrzení
            $token = bin2hex(random_bytes(32));

            // Platnost 30 dní
            $platnostDo = date('Y-m-d H:i:s', strtotime('+30 days'));

            // DŮLEŽITÉ: Nejprve zkontrolovat a přidat sloupec cislo_nabidky (PŘED generováním čísla!)
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky LIKE 'cislo_nabidky'");
            $maSloupec = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$maSloupec) {
                error_log("nabidka_api vytvorit: Sloupec cislo_nabidky neexistuje - přidávám");
                $pdo->exec("ALTER TABLE wgs_nabidky ADD COLUMN cislo_nabidky VARCHAR(30) NULL UNIQUE AFTER id");
                error_log("nabidka_api vytvorit: Sloupec cislo_nabidky byl přidán");
            }

            // Zkontrolovat a přidat sloupec uvodni_text (průvodní dopis emailu)
            $stmtUvodni = $pdo->query("SHOW COLUMNS FROM wgs_nabidky LIKE 'uvodni_text'");
            if (!$stmtUvodni->fetch(PDO::FETCH_ASSOC)) {
                $pdo->exec("ALTER TABLE wgs_nabidky ADD COLUMN uvodni_text TEXT NULL AFTER poznamka");
                error_log("nabidka_api vytvorit: Sloupec uvodni_text byl přidán");
            }

            // Generovat unikátní číslo nabídky: CN-RRRR-DD-M-XX (AŽ PO kontrole sloupce!)
            $cisloNabidky = generujCisloNabidky($pdo);
            error_log("nabidka_api vytvorit: Vygenerováno číslo {$cisloNabidky}");

            // Získat reklamace_id pokud existuje
            $reklamaceId = !empty($_POST['reklamace_id']) ? intval($_POST['reklamace_id']) : null;

            $stmt = $pdo->prepare("
                INSERT INTO wgs_nabidky (
                    reklamace_id, cislo_nabidky, zakaznik_jmeno, zakaznik_email, zakaznik_telefon, zakaznik_adresa,
                    polozky_json, celkova_cena, mena, platnost_do, token,
                    poznamka, uvodni_text, vytvoril_user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            error_log("nabidka_api vytvorit: Provádím INSERT s reklamace_id=" . ($reklamaceId ?? 'NULL'));

            $stmt->execute([
                $reklamaceId,
                $cisloNabidky,
                $_POST['zakaznik_jmeno'],
                $_POST['zakaznik_email'],
                $_POST['zakaznik_telefon'] ?? null,
                $_POST['zakaznik_adresa'] ?? null,
                json_encode($polozky, JSON_UNESCAPED_UNICODE),
                $celkovaCena,
                $_POST['mena'] ?? 'EUR',
                $platnostDo,
                $token,
                $_POST['poznamka'] ?? null,
                !empty($_POST['uvodni_text']) ? trim($_POST['uvodni_text']) : null,
                $_SESSION['user_id'] ?? null
            ]);

            $nabidkaId = $pdo->lastInsertId();
            error_log("nabidka_api vytvorit: Úspěch, ID={$nabidkaId}, číslo={$cisloNabidky}");

            sendJsonSuccess('Nabídka vytvořena', [
                'nabidka_id' => $nabidkaId,
                'cislo_nabidky' => $cisloNabidky,
                'token' => $token,
                'platnost_do' => $platnostDo
            ]);
            break;

        // ========================================
        // ODESLAT - Odešle nabídku emailem (admin only)
        // ========================================
        case 'odeslat':
            if (!$isAdmin) {
                sendJsonError('Přístup odepřen', 403);
            }

            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $nabidkaId = intval($_POST['nabidka_id'] ?? 0);
            if (!$nabidkaId) {
                sendJsonError('Chybí ID nabídky');
            }

            // Načíst nabídku
            $stmt = $pdo->prepare("SELECT * FROM wgs_nabidky WHERE id = ?");
            $stmt->execute([$nabidkaId]);
            $nabidka = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$nabidka) {
                sendJsonError('Nabídka nenalezena');
            }

            // Vygenerovat HTML email
            $emailBody = vygenerujEmailNabidky($nabidka);

            // Odeslat email
            require_once __DIR__ . '/../includes/EmailQueue.php';
            $emailQueue = new EmailQueue($pdo);

            // Použít číslo nabídky nebo fallback na ID
            $cisloPro = $nabidka['cislo_nabidky'] ?? ('CN-' . $nabidka['id']);

            // EmailQueue->add() automaticky načte CC/BCC ze šablony 'price_quote_sent'
            $emailQueue->add(
                $nabidka['zakaznik_email'],
                'Cenová nabídka č. ' . $cisloPro . ' - White Glove Service',
                $emailBody,
                'price_quote_sent'  // ID šablony - automaticky se načtou CC/BCC
            );

            // Uložit PDF do dokumentů pokud je k dispozici reklamace_id
            $pdfUlozeno = false;
            if (!empty($nabidka['reklamace_id']) && !empty($_POST['pdf_data'])) {
                try {
                    $pdfUlozeno = ulozNabidkuPdf($pdo, $nabidka, $_POST['pdf_data']);
                } catch (Exception $e) {
                    error_log("Chyba pri ukladani PDF nabidky: " . $e->getMessage());
                    // Pokračovat - email se odešle i když PDF selže
                }
            }

            // Aktualizovat stav
            $stmt = $pdo->prepare("UPDATE wgs_nabidky SET stav = 'odeslana', odeslano_at = NOW() WHERE id = ?");
            $stmt->execute([$nabidkaId]);

            $message = 'Nabídka odeslána na ' . $nabidka['zakaznik_email'];
            if ($pdfUlozeno) {
                $message .= ' (PDF uloženo do dokumentů)';
            }
            sendJsonSuccess($message);
            break;

        // ========================================
        // POTVRDIT - Zákazník potvrdí nabídku (veřejné)
        // ========================================
        case 'potvrdit':
            $token = $_POST['token'] ?? $_GET['token'] ?? '';
            if (empty($token)) {
                sendJsonError('Chybí token');
            }

            // Najít nabídku
            $stmt = $pdo->prepare("SELECT * FROM wgs_nabidky WHERE token = ?");
            $stmt->execute([$token]);
            $nabidka = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$nabidka) {
                sendJsonError('Nabídka nenalezena');
            }

            if ($nabidka['stav'] === 'potvrzena') {
                sendJsonError('Nabídka již byla potvrzena');
            }

            if (strtotime($nabidka['platnost_do']) < time()) {
                sendJsonError('Platnost nabídky vypršela');
            }

            // Získat čas potvrzení a IP
            $potvrzenoCas = date('Y-m-d H:i:s');
            $potvrzenoIp = $_SERVER['REMOTE_ADDR'];

            // Aktualizovat stav na potvrzeno
            $stmt = $pdo->prepare("
                UPDATE wgs_nabidky
                SET stav = 'potvrzena', potvrzeno_at = ?, potvrzeno_ip = ?
                WHERE id = ?
            ");
            $stmt->execute([$potvrzenoCas, $potvrzenoIp, $nabidka['id']]);

            require_once __DIR__ . '/../includes/EmailQueue.php';
            $emailQueue = new EmailQueue($pdo);

            // Použít číslo nabídky nebo fallback na ID
            $cisloNabidky = $nabidka['cislo_nabidky'] ?? ('CN-' . $nabidka['id']);

            // 1. Odeslat potvrzovací email adminovi
            $adminEmail = getenv('ADMIN_EMAIL') ?: 'reklamace@wgs-service.cz';
            $emailQueue->add(
                $adminEmail,
                'Nabídka č. ' . $cisloNabidky . ' byla potvrzena zákazníkem',
                "Zákazník {$nabidka['zakaznik_jmeno']} ({$nabidka['zakaznik_email']}) potvrdil cenovou nabídku č. {$cisloNabidky}.\n\nCelková cena: {$nabidka['celkova_cena']} {$nabidka['mena']}\n\nIP adresa: {$potvrzenoIp}\nČas: " . date('d.m.Y H:i:s', strtotime($potvrzenoCas)),
                'nabidka_potvrzeni_admin_' . $nabidka['id']
            );

            // 2. Odeslat potvrzovací email zákazníkovi
            $nabidka['potvrzeno_at'] = $potvrzenoCas;
            $nabidka['potvrzeno_ip'] = $potvrzenoIp;
            $emailZakaznikBody = vygenerujEmailPotvrzeniZakaznik($nabidka);

            $emailQueue->add(
                $nabidka['zakaznik_email'],
                'Potvrzení objednávky č. ' . $cisloNabidky . ' - White Glove Service',
                $emailZakaznikBody,
                'nabidka_potvrzeni_zakaznik_' . $nabidka['id']
            );

            sendJsonSuccess('Nabídka úspěšně potvrzena', [
                'nabidka_id' => $nabidka['id'],
                'potvrzeno_at' => $potvrzenoCas
            ]);
            break;

        // ========================================
        // ZAMITNUT - Zákazník odmítne nabídku (veřejné - token)
        // ========================================
        case 'zamitnut':
            $token = $_POST['token'] ?? $_GET['token'] ?? '';
            if (empty($token)) {
                sendJsonError('Chybí token');
            }

            $stmt = $pdo->prepare("SELECT * FROM wgs_nabidky WHERE token = ?");
            $stmt->execute([$token]);
            $nabidka = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$nabidka) {
                sendJsonError('Nabídka nenalezena');
            }

            if ($nabidka['stav'] === 'zamitnuta') {
                sendJsonSuccess('Nabídka již byla odmítnuta');
                break;
            }

            if ($nabidka['stav'] === 'potvrzena') {
                sendJsonError('Tuto nabídku nelze odmítnout – zákazník ji již potvrdil');
            }

            $zamitnutoCas = date('Y-m-d H:i:s');
            $zamitnutaIp  = $_SERVER['REMOTE_ADDR'] ?? '';

            $stmt = $pdo->prepare("
                UPDATE wgs_nabidky
                SET stav = 'zamitnuta', zamitnuta_at = ?, zamitnuto_ip = ?, zamitnuto_kym = 'zakaznik'
                WHERE id = ?
            ");
            $stmt->execute([$zamitnutoCas, $zamitnutaIp, $nabidka['id']]);

            // Notifikace adminovi
            require_once __DIR__ . '/../includes/EmailQueue.php';
            $emailQueue  = new EmailQueue($pdo);
            $cisloNabidky = $nabidka['cislo_nabidky'] ?? ('CN-' . $nabidka['id']);
            $adminEmail  = getenv('ADMIN_EMAIL') ?: 'reklamace@wgs-service.cz';

            $emailQueue->add(
                $adminEmail,
                'Nabídka č. ' . $cisloNabidky . ' byla odmítnuta zákazníkem',
                "Zákazník {$nabidka['zakaznik_jmeno']} ({$nabidka['zakaznik_email']}) odmítnul cenovou nabídku č. {$cisloNabidky}.\n\nCelková cena: {$nabidka['celkova_cena']} {$nabidka['mena']}\n\nIP adresa: {$zamitnutaIp}\nČas: " . date('d.m.Y H:i:s', strtotime($zamitnutoCas)),
                'nabidka_zamitnuta_admin_' . $nabidka['id']
            );

            error_log("nabidka_api: Nabídka {$cisloNabidky} odmítnuta zákazníkem z IP {$zamitnutaIp}");

            sendJsonSuccess('Nabídka odmítnuta', [
                'nabidka_id'  => $nabidka['id'],
                'zamitnuta_at' => $zamitnutoCas
            ]);
            break;

        // ========================================
        // SEZNAM - Seznam nabídek (admin only)
        // ========================================
        case 'seznam':
            if (!$isAdmin) {
                sendJsonError('Přístup odepřen', 403);
            }

            $stmt = $pdo->query("
                SELECT id, cislo_nabidky, zakaznik_jmeno, zakaznik_email, zakaznik_telefon, celkova_cena, mena,
                       stav, platnost_do, vytvoreno_at, odeslano_at, potvrzeno_at,
                       cekame_nd_at, zf_odeslana_at, zf_uhrazena_at, dokonceno_at, fa_uhrazena_at
                FROM wgs_nabidky
                ORDER BY vytvoreno_at DESC
                LIMIT 100
            ");
            $nabidky = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonSuccess('Seznam nabídek', ['nabidky' => $nabidky]);
            break;

        // ========================================
        // DETAIL - Detail jedné nabídky (admin only)
        // ========================================
        case 'detail':
            if (!$isAdmin) {
                sendJsonError('Přístup odepřen', 403);
            }

            $nabidkaId = intval($_GET['id'] ?? 0);
            if (!$nabidkaId) {
                sendJsonError('Chybí ID nabídky');
            }

            $stmt = $pdo->prepare("SELECT * FROM wgs_nabidky WHERE id = ?");
            $stmt->execute([$nabidkaId]);
            $nabidka = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$nabidka) {
                sendJsonError('Nabídka nebyla nalezena', 404);
            }

            // Dekódovat položky z JSON
            $nabidka['polozky'] = json_decode($nabidka['polozky_json'], true) ?: [];

            sendJsonSuccess('Detail nabídky', ['nabidka' => $nabidka]);
            break;

        // ========================================
        // SMAZAT - Smazání cenové nabídky (admin only)
        // ========================================
        case 'smazat':
            if (!$isAdmin) {
                sendJsonError('Přístup odepřen', 403);
            }

            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $nabidkaId = intval($_POST['nabidka_id'] ?? 0);
            if (!$nabidkaId) {
                sendJsonError('Chybí ID nabídky');
            }

            // Ověřit že nabídka existuje
            $stmt = $pdo->prepare("SELECT id, cislo_nabidky FROM wgs_nabidky WHERE id = ?");
            $stmt->execute([$nabidkaId]);
            $nabidka = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$nabidka) {
                sendJsonError('Nabídka nebyla nalezena', 404);
            }

            // Smazat nabídku
            $stmt = $pdo->prepare("DELETE FROM wgs_nabidky WHERE id = ?");
            $stmt->execute([$nabidkaId]);

            sendJsonSuccess('Nabídka ' . ($nabidka['cislo_nabidky'] ?? $nabidkaId) . ' byla smazána');
            break;

        // ========================================
        // ZMENIT_WORKFLOW - Manuální změna workflow stavu (admin only)
        // ========================================
        case 'zmenit_workflow':
            if (!$isAdmin) {
                sendJsonError('Přístup odepřen', 403);
            }

            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $nabidkaId = intval($_POST['nabidka_id'] ?? 0);
            $krok = $_POST['krok'] ?? '';

            if (!$nabidkaId) {
                sendJsonError('Chybí ID nabídky');
            }

            // Povolené kroky workflow
            $povoleneKroky = ['cekame_nd', 'zf_odeslana', 'zf_uhrazena', 'dokonceno', 'fa_uhrazena'];
            if (!in_array($krok, $povoleneKroky)) {
                sendJsonError('Neplatný workflow krok');
            }

            // Načíst nabídku
            $stmt = $pdo->prepare("SELECT * FROM wgs_nabidky WHERE id = ?");
            $stmt->execute([$nabidkaId]);
            $nabidka = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$nabidka) {
                sendJsonError('Nabídka nenalezena');
            }

            // Aktualizovat příslušný sloupec
            $sloupec = $krok . '_at';
            $hodnotaSloupce = $nabidka[$sloupec];

            if ($hodnotaSloupce) {
                // Již nastaveno - zrušit (toggle)
                $stmt = $pdo->prepare("UPDATE wgs_nabidky SET {$sloupec} = NULL WHERE id = ?");
                $stmt->execute([$nabidkaId]);
                $novaHodnota = null;
            } else {
                // Nastavit na aktuální čas
                $stmt = $pdo->prepare("UPDATE wgs_nabidky SET {$sloupec} = NOW() WHERE id = ?");
                $stmt->execute([$nabidkaId]);
                $novaHodnota = date('Y-m-d H:i:s');

                // Automatické emaily při aktivaci kroků
                require_once __DIR__ . '/../includes/EmailQueue.php';
                $emailQueue = new EmailQueue($pdo);
                $cisloNabidky = $nabidka['cislo_nabidky'] ?? ('CN-' . $nabidka['id']);

                // Uhrazena ZF - potvrzení o přijaté záloze
                if ($krok === 'zf_uhrazena') {
                    $nabidka['zf_uhrazena_at'] = $novaHodnota;
                    $emailBody = vygenerujEmailPotvrzeniZalohy($nabidka);
                    $emailQueue->add(
                        $nabidka['zakaznik_email'],
                        'Potvrzení o přijaté záloze - ' . $cisloNabidky . ' - White Glove Service',
                        $emailBody,
                        'zaloha_potvrzeni_' . $nabidka['id']
                    );
                    error_log("nabidka_api: Odeslán email potvrzení zálohy pro CN {$cisloNabidky}");
                }

                // Uhrazena FA - poděkování za úhradu a využití služeb
                if ($krok === 'fa_uhrazena') {
                    $nabidka['fa_uhrazena_at'] = $novaHodnota;
                    $emailBody = vygenerujEmailPodekovaniZaUhradu($nabidka);
                    $emailQueue->add(
                        $nabidka['zakaznik_email'],
                        'Děkujeme za úhradu - ' . $cisloNabidky . ' - White Glove Service',
                        $emailBody,
                        'fa_dekujeme_' . $nabidka['id']
                    );
                    error_log("nabidka_api: Odeslán email poděkování za úhradu pro CN {$cisloNabidky}");
                }
            }

            sendJsonSuccess('Workflow aktualizován', [
                'krok' => $krok,
                'hodnota' => $novaHodnota,
                'nabidka_id' => $nabidkaId
            ]);
            break;

        // ========================================
        // DETAIL - Detail nabídky
        // ========================================
        case 'detail':
            $nabidkaId = intval($_GET['id'] ?? 0);
            $token = $_GET['token'] ?? '';

            if ($nabidkaId && $isAdmin) {
                $stmt = $pdo->prepare("SELECT * FROM wgs_nabidky WHERE id = ?");
                $stmt->execute([$nabidkaId]);
            } elseif ($token) {
                $stmt = $pdo->prepare("SELECT * FROM wgs_nabidky WHERE token = ?");
                $stmt->execute([$token]);
            } else {
                sendJsonError('Chybí ID nebo token');
            }

            $nabidka = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$nabidka) {
                sendJsonError('Nabídka nenalezena');
            }

            // Dekódovat položky
            $nabidka['polozky'] = json_decode($nabidka['polozky_json'], true);

            sendJsonSuccess('Detail nabídky', ['nabidka' => $nabidka]);
            break;

        default:
            sendJsonError('Neznámá akce: ' . $action);
    }

} catch (PDOException $e) {
    $errorMsg = $e->getMessage();
    $errorLine = $e->getLine();
    $errorFile = basename($e->getFile());
    error_log("Nabídka API PDO error: " . $errorMsg . " | File: " . $errorFile . " | Line: " . $errorLine);

    // Pokud je to UNIQUE constraint violation, poskytnout lepší zprávu
    if (strpos($errorMsg, 'Duplicate entry') !== false && strpos($errorMsg, 'cislo_nabidky') !== false) {
        sendJsonError('Konflikt při generování čísla nabídky. Zkuste znovu.', 409);
    } else {
        sendJsonError('Chyba při zpracování požadavku', 500);
    }
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    $errorLine = $e->getLine();
    $errorFile = basename($e->getFile());
    error_log("Nabídka API error: " . $errorMsg . " | File: " . $errorFile . " | Line: " . $errorLine);
    sendJsonError('Chyba při zpracování požadavku', 500);
}

/**
 * Uloží PDF nabídky do souborového systému a databáze
 *
 * @param PDO $pdo Database connection
 * @param array $nabidka Data nabídky
 * @param string $pdfBase64 Base64 encoded PDF data
 * @return bool True pokud úspěšně uloženo
 */
function ulozNabidkuPdf($pdo, $nabidka, $pdfBase64) {
    $reklamaceId = $nabidka['reklamace_id'];
    $cisloNabidky = $nabidka['cislo_nabidky'] ?? ('CN-' . $nabidka['id']);

    if (!$reklamaceId) {
        throw new Exception('Nabídka není propojena s reklamací');
    }

    // Dekódovat base64 data
    // Odstranit prefix "data:application/pdf;base64," pokud existuje
    if (strpos($pdfBase64, 'base64,') !== false) {
        $pdfBase64 = explode('base64,', $pdfBase64)[1];
    }

    $pdfData = base64_decode($pdfBase64);
    if ($pdfData === false) {
        throw new Exception('Neplatná base64 data PDF');
    }

    // Vytvořit adresář pro nabídky pokud neexistuje
    $uploadDir = __DIR__ . '/../uploads/nabidky';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    // Vytvořit název souboru: nabidka_CN-2025-12-1-01_reklamace_123.pdf
    $safeNabidkaCislo = preg_replace('/[^a-zA-Z0-9\-]/', '_', $cisloNabidky);
    $filename = "nabidka_{$safeNabidkaCislo}_reklamace_{$reklamaceId}.pdf";
    $filePath = $uploadDir . '/' . $filename;
    $relativePathForDb = '/uploads/nabidky/' . $filename;

    // Uložit soubor
    $bytesWritten = file_put_contents($filePath, $pdfData);
    if ($bytesWritten === false) {
        throw new Exception('Nepodařilo se uložit PDF soubor');
    }

    // Smazat starý záznam pokud existuje
    $stmt = $pdo->prepare("
        DELETE FROM wgs_documents
        WHERE claim_id = :claim_id AND document_type = 'nabidka_pdf'
    ");
    $stmt->execute(['claim_id' => $reklamaceId]);

    // Vložit nový záznam do databáze
    $stmt = $pdo->prepare("
        INSERT INTO wgs_documents (
            claim_id, document_name, document_path, document_type,
            file_size, uploaded_by, uploaded_at
        ) VALUES (
            :claim_id, :document_name, :document_path, :document_type,
            :file_size, :uploaded_by, NOW()
        )
    ");

    $stmt->execute([
        ':claim_id' => $reklamaceId,
        ':document_name' => 'Cenová nabídka ' . $cisloNabidky,
        ':document_path' => $relativePathForDb,
        ':document_type' => 'nabidka_pdf',
        ':file_size' => $bytesWritten,
        ':uploaded_by' => $_SESSION['user_id'] ?? null
    ]);

    error_log("PDF nabídky uloženo: {$filePath} ({$bytesWritten} bytes)");

    return true;
}

/**
 * Vytvoří tabulku wgs_nabidky pokud neexistuje
 */
function vytvorTabulkuNabidky($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wgs_nabidky (
            id INT AUTO_INCREMENT PRIMARY KEY,
            cislo_nabidky VARCHAR(30) NULL UNIQUE,
            zakaznik_jmeno VARCHAR(255) NOT NULL,
            zakaznik_email VARCHAR(255) NOT NULL,
            zakaznik_telefon VARCHAR(50) NULL,
            zakaznik_adresa TEXT NULL,
            polozky_json TEXT NOT NULL,
            celkova_cena DECIMAL(10,2) NOT NULL DEFAULT 0,
            mena VARCHAR(10) NOT NULL DEFAULT 'EUR',
            platnost_do DATETIME NOT NULL,
            token VARCHAR(64) NOT NULL UNIQUE,
            stav ENUM('nova', 'odeslana', 'potvrzena', 'expirovana', 'zrusena') DEFAULT 'nova',
            poznamka TEXT NULL,
            vytvoril_user_id VARCHAR(50) NULL,
            vytvoreno_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            odeslano_at DATETIME NULL,
            potvrzeno_at DATETIME NULL,
            potvrzeno_ip VARCHAR(45) NULL,
            zf_odeslana_at DATETIME NULL,
            zf_uhrazena_at DATETIME NULL,
            dokonceno_at DATETIME NULL,
            fa_uhrazena_at DATETIME NULL,
            INDEX idx_token (token),
            INDEX idx_stav (stav),
            INDEX idx_email (zakaznik_email),
            INDEX idx_cislo (cislo_nabidky)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    // Přidat chybějící sloupce (pro existující tabulky)
    $sloupce = [
        'zf_odeslana_at'   => 'DATETIME NULL',
        'zf_uhrazena_at'   => 'DATETIME NULL',
        'dokonceno_at'     => 'DATETIME NULL',
        'fa_uhrazena_at'   => 'DATETIME NULL',
        'cislo_nabidky'    => 'VARCHAR(30) NULL UNIQUE AFTER id',
        'zamitnuta_at'     => 'DATETIME NULL',
        'zamitnuto_ip'     => 'VARCHAR(45) NULL',
        'zamitnuto_kym'    => "ENUM('zakaznik','admin') NULL",
        'pripominka_7d_at' => 'DATETIME NULL',
        'reklamace_id'     => 'INT NULL'
    ];

    // Aktualizovat ENUM stav pokud neobsahuje 'zamitnuta'
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky LIKE 'stav'");
        $stavSloupec = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($stavSloupec && strpos($stavSloupec['Type'], 'zamitnuta') === false) {
            $pdo->exec("ALTER TABLE wgs_nabidky MODIFY COLUMN stav ENUM('nova','odeslana','potvrzena','zamitnuta','expirovana','zrusena') DEFAULT 'nova'");
            error_log("nabidka_api: ENUM stav rozšířen o 'zamitnuta'");
        }
    } catch (PDOException $e) {
        error_log("nabidka_api: Chyba při úpravě ENUM stav: " . $e->getMessage());
    }

    foreach ($sloupce as $sloupec => $definice) {
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky LIKE '{$sloupec}'");
            if (!$stmt->fetch()) {
                $sql = "ALTER TABLE wgs_nabidky ADD COLUMN {$sloupec} {$definice}";
                $pdo->exec($sql);
                error_log("nabidka_api: Přidán sloupec {$sloupec} do wgs_nabidky");
            }
        } catch (PDOException $e) {
            error_log("nabidka_api: Chyba při přidávání sloupce {$sloupec}: " . $e->getMessage());
        }
    }

    // Přidat index na cislo_nabidky pokud neexistuje
    try {
        $stmt = $pdo->query("SHOW INDEX FROM wgs_nabidky WHERE Key_name = 'idx_cislo'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE wgs_nabidky ADD INDEX idx_cislo (cislo_nabidky)");
            error_log("nabidka_api: Přidán index idx_cislo na wgs_nabidky");
        }
    } catch (PDOException $e) {
        // Index už existuje - OK
    }

    // Migrace: Změnit vytvoril_user_id z INT na VARCHAR(50) pokud je INT
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky LIKE 'vytvoril_user_id'");
        $sloupec = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($sloupec && stripos($sloupec['Type'], 'int') !== false) {
            $pdo->exec("ALTER TABLE wgs_nabidky MODIFY COLUMN vytvoril_user_id VARCHAR(50) NULL");
            error_log("nabidka_api: Změněn typ vytvoril_user_id z INT na VARCHAR(50)");
        }
    } catch (PDOException $e) {
        error_log("nabidka_api: Chyba při změně typu vytvoril_user_id: " . $e->getMessage());
    }
}

/**
 * Generuje unikátní číslo nabídky ve formátu CN-RRRR-DD-M-XX
 * Příklad: CN-2026-23-1-01 (první nabídka 23. ledna 2026)
 *
 * Používá MAX(cislo_nabidky) pro bezpečnější generování při race conditions
 */
function generujCisloNabidky($pdo) {
    $rok = date('Y');
    $den = date('j');    // Den bez úvodní nuly (1-31)
    $mesic = date('n');  // Měsíc bez úvodní nuly (1-12)

    // Prefix pro dnešní den
    $prefix = "CN-{$rok}-{$den}-{$mesic}-";

    // Najít nejvyšší číslo nabídky s tímto prefixem
    $stmt = $pdo->prepare("
        SELECT cislo_nabidky
        FROM wgs_nabidky
        WHERE cislo_nabidky LIKE ?
        ORDER BY cislo_nabidky DESC
        LIMIT 1
    ");
    $stmt->execute([$prefix . '%']);
    $posledni = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($posledni && !empty($posledni['cislo_nabidky'])) {
        // Extrahovat poslední 2 číslice
        $cislo = $posledni['cislo_nabidky'];
        $posledniCislo = intval(substr($cislo, -2));
        $poradiDnes = $posledniCislo + 1;
    } else {
        $poradiDnes = 1;
    }

    $poradiFormatovane = str_pad($poradiDnes, 2, '0', STR_PAD_LEFT);

    // Formát: CN-RRRR-DD-M-XX
    return $prefix . $poradiFormatovane;
}
