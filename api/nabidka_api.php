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

header('Content-Type: application/json; charset=utf-8');

try {
    $pdo = getDbConnection();
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

    // Vytvořit tabulku pokud neexistuje
    vytvorTabulkuNabidky($pdo);

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

            // Generovat unikátní číslo nabídky: CN-RRRR-DD-M-XX (AŽ PO kontrole sloupce!)
            $cisloNabidky = generujCisloNabidky($pdo);
            error_log("nabidka_api vytvorit: Vygenerováno číslo {$cisloNabidky}");

            $stmt = $pdo->prepare("
                INSERT INTO wgs_nabidky (
                    cislo_nabidky, zakaznik_jmeno, zakaznik_email, zakaznik_telefon, zakaznik_adresa,
                    polozky_json, celkova_cena, mena, platnost_do, token,
                    poznamka, vytvoril_user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            error_log("nabidka_api vytvorit: Provádím INSERT");

            $stmt->execute([
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

            $emailQueue->add(
                $nabidka['zakaznik_email'],
                'Cenová nabídka č. ' . $cisloPro . ' - White Glove Service',
                $emailBody,
                'nabidka_' . $nabidka['id']
            );

            // Aktualizovat stav
            $stmt = $pdo->prepare("UPDATE wgs_nabidky SET stav = 'odeslana', odeslano_at = NOW() WHERE id = ?");
            $stmt->execute([$nabidkaId]);

            sendJsonSuccess('Nabídka odeslána na ' . $nabidka['zakaznik_email']);
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
        // SEZNAM - Seznam nabídek (admin only)
        // ========================================
        case 'seznam':
            if (!$isAdmin) {
                sendJsonError('Přístup odepřen', 403);
            }

            $stmt = $pdo->query("
                SELECT id, cislo_nabidky, zakaznik_jmeno, zakaznik_email, zakaznik_telefon, celkova_cena, mena,
                       stav, platnost_do, vytvoreno_at, odeslano_at, potvrzeno_at,
                       zf_odeslana_at, zf_uhrazena_at, dokonceno_at, fa_uhrazena_at
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
            $povoleneKroky = ['zf_odeslana', 'zf_uhrazena', 'dokonceno', 'fa_uhrazena'];
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
        // DOČASNĚ: Vždy vracet detaily pro debugging
        sendJsonError('Chyba databáze: ' . $errorMsg, 500, [
            'file' => $errorFile,
            'line' => $errorLine
        ]);
    }
} catch (Exception $e) {
    $errorMsg = $e->getMessage();
    $errorLine = $e->getLine();
    $errorFile = basename($e->getFile());
    error_log("Nabídka API error: " . $errorMsg . " | File: " . $errorFile . " | Line: " . $errorLine);
    // DOČASNĚ: Vždy vracet detaily pro debugging
    sendJsonError('Chyba serveru: ' . $errorMsg, 500, [
        'file' => $errorFile,
        'line' => $errorLine
    ]);
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
        'zf_odeslana_at' => 'DATETIME NULL',
        'zf_uhrazena_at' => 'DATETIME NULL',
        'dokonceno_at' => 'DATETIME NULL',
        'fa_uhrazena_at' => 'DATETIME NULL',
        'cislo_nabidky' => 'VARCHAR(30) NULL UNIQUE AFTER id'
    ];

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

/**
 * Vygeneruje HTML email s nabídkou - profesionální design
 */
function vygenerujEmailNabidky($nabidka) {
    $polozky = json_decode($nabidka['polozky_json'], true);
    $baseUrl = 'https://www.wgs-service.cz';
    $potvrzeniUrl = $baseUrl . '/potvrzeni-nabidky.php?token=' . urlencode($nabidka['token']);

    // Zjistit zda nabídka obsahuje náhradní díly
    $obsahujeDily = false;
    if (is_array($polozky)) {
        foreach ($polozky as $p) {
            $nazev = $p['nazev'] ?? '';
            // Detekce náhradních dílů - prefix "Náhradní díl:" nebo skupina "dily"
            if (stripos($nazev, 'Náhradní díl') !== false || ($p['skupina'] ?? '') === 'dily') {
                $obsahujeDily = true;
                break;
            }
        }
    }

    // Poznámka o zálohové faktuře - zobrazí se pouze pokud jsou náhradní díly
    $poznamkaOZaloze = '';
    if ($obsahujeDily) {
        $poznamkaOZaloze = "<p style='margin: 12px 0 0 0; font-size: 12px; color: #d97706; line-height: 1.6;'>
            <strong>Záloha na náhradní díly:</strong> Po odsouhlasení této nabídky Vám zašleme zálohovou fakturu na náhradní díly.
            Po přijetí zálohy objednáme díly u výrobce.
        </p>";
    }

    // Sestavení tabulky položek
    $polozkyHtml = '';
    foreach ($polozky as $p) {
        $nazev = htmlspecialchars($p['nazev'] ?? '');
        $pocet = intval($p['pocet'] ?? 1);
        $cenaJednotka = floatval($p['cena'] ?? 0);
        $cenaCelkem = $cenaJednotka * $pocet;

        $cenaFormatovana = number_format($cenaJednotka, 2, ',', ' ');
        $celkemFormatovane = number_format($cenaCelkem, 2, ',', ' ');

        $polozkyHtml .= "
        <tr>
            <td style='padding: 14px 16px; border-bottom: 1px solid #e5e5e5; font-size: 14px; color: #333;'>{$nazev}</td>
            <td style='padding: 14px 16px; border-bottom: 1px solid #e5e5e5; text-align: center; font-size: 14px; color: #666;'>{$pocet}</td>
            <td style='padding: 14px 16px; border-bottom: 1px solid #e5e5e5; text-align: right; font-size: 14px; color: #666;'>{$cenaFormatovana} {$nabidka['mena']}</td>
            <td style='padding: 14px 16px; border-bottom: 1px solid #e5e5e5; text-align: right; font-size: 14px; font-weight: 600; color: #333;'>{$celkemFormatovane} {$nabidka['mena']}</td>
        </tr>";
    }

    $celkovaCena = number_format(floatval($nabidka['celkova_cena']), 2, ',', ' ');
    $datumVytvoreni = date('d.m.Y', strtotime($nabidka['vytvoreno_at'] ?? 'now'));
    $platnostDo = date('d.m.Y', strtotime($nabidka['platnost_do']));
    // Použít číslo nabídky nebo fallback na padované ID
    $nabidkaCislo = $nabidka['cislo_nabidky'] ?? ('CN-' . str_pad($nabidka['id'], 6, '0', STR_PAD_LEFT));

    // Adresa zákazníka (pokud existuje)
    $adresaHtml = '';
    if (!empty($nabidka['zakaznik_adresa'])) {
        $adresaHtml = "<p style='margin: 4px 0 0 0; font-size: 13px; color: #666;'>" . nl2br(htmlspecialchars($nabidka['zakaznik_adresa'])) . "</p>";
    }

    // Telefon zákazníka
    $telefonHtml = '';
    if (!empty($nabidka['zakaznik_telefon'])) {
        $telefonHtml = "<p style='margin: 4px 0 0 0; font-size: 13px; color: #666;'>Tel: {$nabidka['zakaznik_telefon']}</p>";
    }

    return "
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Cenová nabídka č. {$nabidkaCislo} - White Glove Service</title>
</head>
<body style='margin: 0; padding: 0; background-color: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;'>

    <!-- Hlavní kontejner -->
    <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='background-color: #f4f4f4;'>
        <tr>
            <td style='padding: 30px 20px;'>
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='600' style='margin: 0 auto; max-width: 600px;'>

                    <!-- HEADER -->
                    <tr>
                        <td style='background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); padding: 35px 40px; text-align: center; border-radius: 12px 12px 0 0;'>
                            <h1 style='margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; letter-spacing: 2px;'>WHITE GLOVE SERVICE</h1>
                            <p style='margin: 8px 0 0 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px;'>Premium Furniture Care</p>
                        </td>
                    </tr>

                    <!-- HLAVNÍ OBSAH -->
                    <tr>
                        <td style='background: #ffffff; padding: 0;'>

                            <!-- Nadpis nabídky -->
                            <div style='background: #f8f9fa; padding: 25px 40px; border-bottom: 1px solid #e5e5e5;'>
                                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%'>
                                    <tr>
                                        <td>
                                            <h2 style='margin: 0; font-size: 20px; font-weight: 600; color: #333;'>Cenová nabídka</h2>
                                            <p style='margin: 5px 0 0 0; font-size: 13px; color: #888;'>č. {$nabidkaCislo}</p>
                                        </td>
                                        <td style='text-align: right;'>
                                            <p style='margin: 0; font-size: 13px; color: #666;'>Datum: <strong>{$datumVytvoreni}</strong></p>
                                            <p style='margin: 5px 0 0 0; font-size: 13px; color: #666;'>Platnost: <strong style='color: #d97706;'>{$platnostDo}</strong></p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Oslovení -->
                            <div style='padding: 30px 40px 20px 40px;'>
                                <p style='margin: 0; font-size: 15px; color: #333;'>Vážený/á <strong>{$nabidka['zakaznik_jmeno']}</strong>,</p>
                                <p style='margin: 15px 0 0 0; font-size: 14px; color: #555; line-height: 1.6;'>
                                    děkujeme za Váš zájem o naše služby. Na základě Vašeho požadavku jsme pro Vás připravili následující cenovou nabídku:
                                </p>
                            </div>

                            <!-- Údaje zákazníka -->
                            <div style='padding: 0 40px 25px 40px;'>
                                <div style='background: #f8f9fa; border-radius: 8px; padding: 18px 20px;'>
                                    <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;'>Zákazník</p>
                                    <p style='margin: 8px 0 0 0; font-size: 15px; font-weight: 600; color: #333;'>{$nabidka['zakaznik_jmeno']}</p>
                                    <p style='margin: 4px 0 0 0; font-size: 13px; color: #666;'>{$nabidka['zakaznik_email']}</p>
                                    {$telefonHtml}
                                    {$adresaHtml}
                                </div>
                            </div>

                            <!-- Tabulka položek -->
                            <div style='padding: 0 40px 30px 40px;'>
                                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='border: 1px solid #e5e5e5; border-radius: 8px; overflow: hidden;'>
                                    <thead>
                                        <tr style='background: #f8f9fa;'>
                                            <th style='padding: 14px 16px; text-align: left; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Služba</th>
                                            <th style='padding: 14px 16px; text-align: center; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Počet</th>
                                            <th style='padding: 14px 16px; text-align: right; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Cena/ks</th>
                                            <th style='padding: 14px 16px; text-align: right; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Celkem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {$polozkyHtml}
                                    </tbody>
                                    <tfoot>
                                        <tr style='background: #1a1a1a;'>
                                            <td colspan='3' style='padding: 18px 16px; text-align: right; font-size: 14px; font-weight: 600; color: #fff;'>Celková cena (bez DPH):</td>
                                            <td style='padding: 18px 16px; text-align: right; font-size: 20px; font-weight: 700; color: #39ff14;'>{$celkovaCena} {$nabidka['mena']}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Upozornění platnost -->
                            <div style='padding: 0 40px 30px 40px;'>
                                <div style='background: #fef3c7; border: 1px solid #f59e0b; border-radius: 8px; padding: 16px 20px;'>
                                    <p style='margin: 0; font-size: 14px; color: #92400e;'>
                                        <strong>Platnost nabídky:</strong> Tato nabídka je platná do <strong>{$platnostDo}</strong>.
                                        Po tomto datu bude automaticky zrušena.
                                    </p>
                                </div>
                            </div>

                            <!-- CTA Tlačítko -->
                            <div style='padding: 0 40px 35px 40px; text-align: center;'>
                                <p style='margin: 0 0 20px 0; font-size: 14px; color: #555;'>
                                    Pokud s nabídkou souhlasíte, potvrďte ji kliknutím na tlačítko níže:
                                </p>
                                <a href='{$potvrzeniUrl}' style='display: inline-block; background: linear-gradient(135deg, #28a745 0%, #218838 100%); color: #ffffff; padding: 18px 50px; text-decoration: none; border-radius: 8px; font-weight: 700; font-size: 16px; text-transform: uppercase; letter-spacing: 1px; box-shadow: 0 4px 15px rgba(40, 167, 69, 0.3);'>
                                    Potvrdit nabídku
                                </a>
                            </div>

                            <!-- Právní upozornění -->
                            <div style='padding: 25px 40px; background: #f8f9fa; border-top: 1px solid #e5e5e5;'>
                                <p style='margin: 0; font-size: 12px; color: #666; line-height: 1.6;'>
                                    <strong>Důležité upozornění:</strong> Kliknutím na tlačítko \"Potvrdit nabídku\" potvrzujete, že souhlasíte s touto cenovou nabídkou
                                    a uzavíráte tím závaznou smlouvu o dílo dle § 2586 občanského zákoníku s White Glove Service, s.r.o.
                                    Podrobnosti naleznete v našich <a href='{$baseUrl}/podminky.php' style='color: #333;'>obchodních podmínkách</a>.
                                </p>
                                {$poznamkaOZaloze}
                                <p style='margin: 12px 0 0 0; font-size: 12px; color: #888;'>
                                    Ceny jsou uvedeny bez DPH. Doba dodání originálních dílů z továrny Natuzzi je 4–8 týdnů.
                                </p>
                            </div>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style='background: #1a1a1a; padding: 30px 40px; border-radius: 0 0 12px 12px; text-align: center;'>
                            <p style='margin: 0; font-size: 14px; font-weight: 600; color: #fff;'>White Glove Service, s.r.o.</p>
                            <p style='margin: 8px 0 0 0; font-size: 13px; color: #888;'>Do Dubče 364, 190 11 Praha 9 – Běchovice</p>
                            <p style='margin: 8px 0 0 0; font-size: 13px; color: #888;'>
                                Tel: <a href='tel:+420725965826' style='color: #888; text-decoration: none;'>+420 725 965 826</a> |
                                Email: <a href='mailto:reklamace@wgs-service.cz' style='color: #888; text-decoration: none;'>reklamace@wgs-service.cz</a>
                            </p>
                            <p style='margin: 15px 0 0 0; font-size: 12px; color: #555;'>
                                <a href='{$baseUrl}' style='color: #39ff14; text-decoration: none;'>www.wgs-service.cz</a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>";
}

/**
 * Vygeneruje HTML email s potvrzením objednávky pro zákazníka
 */
function vygenerujEmailPotvrzeniZakaznik($nabidka) {
    $polozky = json_decode($nabidka['polozky_json'], true);
    $baseUrl = 'https://www.wgs-service.cz';
    $potvrzeniUrl = $baseUrl . '/potvrzeni-nabidky.php?token=' . urlencode($nabidka['token']);

    // Sestavení tabulky položek
    $polozkyHtml = '';
    foreach ($polozky as $p) {
        $nazev = htmlspecialchars($p['nazev'] ?? '');
        $pocet = intval($p['pocet'] ?? 1);
        $cenaJednotka = floatval($p['cena'] ?? 0);
        $cenaCelkem = $cenaJednotka * $pocet;

        $cenaFormatovana = number_format($cenaJednotka, 2, ',', ' ');
        $celkemFormatovane = number_format($cenaCelkem, 2, ',', ' ');

        $polozkyHtml .= "
        <tr>
            <td style='padding: 12px 14px; border-bottom: 1px solid #e5e5e5; font-size: 14px; color: #333;'>{$nazev}</td>
            <td style='padding: 12px 14px; border-bottom: 1px solid #e5e5e5; text-align: center; font-size: 14px; color: #666;'>{$pocet}</td>
            <td style='padding: 12px 14px; border-bottom: 1px solid #e5e5e5; text-align: right; font-size: 14px; font-weight: 600; color: #333;'>{$celkemFormatovane} {$nabidka['mena']}</td>
        </tr>";
    }

    $celkovaCena = number_format(floatval($nabidka['celkova_cena']), 2, ',', ' ');
    // Použít číslo nabídky nebo fallback na padované ID
    $nabidkaCislo = $nabidka['cislo_nabidky'] ?? ('CN-' . str_pad($nabidka['id'], 6, '0', STR_PAD_LEFT));

    // Datum a čas potvrzení
    $potvrzenoAt = isset($nabidka['potvrzeno_at']) ? date('d.m.Y H:i:s', strtotime($nabidka['potvrzeno_at'])) : date('d.m.Y H:i:s');
    $potvrzenoIp = $nabidka['potvrzeno_ip'] ?? $_SERVER['REMOTE_ADDR'] ?? 'N/A';

    return "
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Potvrzení objednávky č. {$nabidkaCislo}</title>
</head>
<body style='margin: 0; padding: 0; background-color: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;'>

    <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='background-color: #f4f4f4;'>
        <tr>
            <td style='padding: 30px 20px;'>
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='600' style='margin: 0 auto; max-width: 600px;'>

                    <!-- HEADER -->
                    <tr>
                        <td style='background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); padding: 35px 40px; text-align: center; border-radius: 12px 12px 0 0;'>
                            <h1 style='margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; letter-spacing: 2px;'>WHITE GLOVE SERVICE</h1>
                            <p style='margin: 8px 0 0 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px;'>Potvrzení objednávky</p>
                        </td>
                    </tr>

                    <!-- HLAVNÍ OBSAH -->
                    <tr>
                        <td style='background: #ffffff; padding: 0;'>

                            <!-- Úspěšné potvrzení -->
                            <div style='background: #d4edda; padding: 25px 40px; border-bottom: 1px solid #c3e6cb;'>
                                <h2 style='margin: 0; font-size: 20px; font-weight: 600; color: #155724;'>Vaše objednávka byla úspěšně potvrzena</h2>
                                <p style='margin: 8px 0 0 0; font-size: 14px; color: #155724;'>Děkujeme za Vaši důvěru. Níže naleznete shrnutí Vaší objednávky.</p>
                            </div>

                            <!-- Číslo objednávky -->
                            <div style='padding: 25px 40px; border-bottom: 1px solid #e5e5e5;'>
                                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%'>
                                    <tr>
                                        <td>
                                            <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase;'>Číslo objednávky</p>
                                            <p style='margin: 5px 0 0 0; font-size: 24px; font-weight: 700; color: #333;'>{$nabidkaCislo}</p>
                                        </td>
                                        <td style='text-align: right;'>
                                            <p style='margin: 0; font-size: 12px; color: #888;'>Potvrzeno:</p>
                                            <p style='margin: 5px 0 0 0; font-size: 14px; color: #333;'>{$potvrzenoAt}</p>
                                        </td>
                                    </tr>
                                </table>
                            </div>

                            <!-- Údaje zákazníka -->
                            <div style='padding: 25px 40px; border-bottom: 1px solid #e5e5e5;'>
                                <p style='margin: 0; font-size: 12px; color: #888; text-transform: uppercase;'>Zákazník</p>
                                <p style='margin: 8px 0 0 0; font-size: 15px; font-weight: 600; color: #333;'>{$nabidka['zakaznik_jmeno']}</p>
                                <p style='margin: 4px 0 0 0; font-size: 13px; color: #666;'>{$nabidka['zakaznik_email']}</p>
                            </div>

                            <!-- Tabulka položek -->
                            <div style='padding: 25px 40px;'>
                                <p style='margin: 0 0 15px 0; font-size: 12px; color: #888; text-transform: uppercase;'>Objednané služby</p>
                                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='border: 1px solid #e5e5e5; border-radius: 8px; overflow: hidden;'>
                                    <thead>
                                        <tr style='background: #f8f9fa;'>
                                            <th style='padding: 12px 14px; text-align: left; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; border-bottom: 2px solid #e5e5e5;'>Služba</th>
                                            <th style='padding: 12px 14px; text-align: center; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; border-bottom: 2px solid #e5e5e5;'>Ks</th>
                                            <th style='padding: 12px 14px; text-align: right; font-size: 12px; font-weight: 600; color: #666; text-transform: uppercase; border-bottom: 2px solid #e5e5e5;'>Cena</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {$polozkyHtml}
                                    </tbody>
                                    <tfoot>
                                        <tr style='background: #1a1a1a;'>
                                            <td colspan='2' style='padding: 15px 14px; text-align: right; font-size: 14px; font-weight: 600; color: #fff;'>Celková cena (bez DPH):</td>
                                            <td style='padding: 15px 14px; text-align: right; font-size: 18px; font-weight: 700; color: #39ff14;'>{$celkovaCena} {$nabidka['mena']}</td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>

                            <!-- Technické údaje potvrzení -->
                            <div style='padding: 20px 40px; background: #f8f9fa; border-top: 1px solid #e5e5e5;'>
                                <p style='margin: 0; font-size: 12px; color: #666; line-height: 1.5;'>
                                    Toto elektronické potvrzení bylo zaznamenáno dne <strong>{$potvrzenoAt}</strong>
                                    a má právní platnost dle § 2586 občanského zákoníku (smlouva o dílo).
                                </p>
                            </div>

                            <!-- Tlačítko pro stažení PDF -->
                            <div style='padding: 25px 40px; text-align: center;'>
                                <p style='margin: 0 0 15px 0; font-size: 14px; color: #555;'>
                                    PDF potvrzení si můžete stáhnout na stránce objednávky:
                                </p>
                                <a href='{$potvrzeniUrl}' style='display: inline-block; background: #333; color: #fff; padding: 14px 35px; text-decoration: none; border-radius: 8px; font-weight: 600; font-size: 14px;'>
                                    Zobrazit objednávku a stáhnout PDF
                                </a>
                            </div>

                            <!-- Právní upozornění -->
                            <div style='padding: 20px 40px; background: #f8f9fa; border-top: 1px solid #e5e5e5;'>
                                <p style='margin: 0; font-size: 12px; color: #666; line-height: 1.6;'>
                                    Tímto potvrzením jste uzavřeli závaznou smlouvu o dílo dle § 2586 občanského zákoníku
                                    s White Glove Service, s.r.o. Ceny jsou uvedeny bez DPH.
                                </p>
                            </div>

                        </td>
                    </tr>

                    <!-- FOOTER -->
                    <tr>
                        <td style='background: #1a1a1a; padding: 30px 40px; border-radius: 0 0 12px 12px; text-align: center;'>
                            <p style='margin: 0; font-size: 14px; font-weight: 600; color: #fff;'>White Glove Service, s.r.o.</p>
                            <p style='margin: 8px 0 0 0; font-size: 13px; color: #888;'>Do Dubče 364, 190 11 Praha 9 – Běchovice</p>
                            <p style='margin: 8px 0 0 0; font-size: 13px; color: #888;'>
                                Tel: <a href='tel:+420725965826' style='color: #888; text-decoration: none;'>+420 725 965 826</a> |
                                Email: <a href='mailto:reklamace@wgs-service.cz' style='color: #888; text-decoration: none;'>reklamace@wgs-service.cz</a>
                            </p>
                            <p style='margin: 15px 0 0 0; font-size: 12px; color: #555;'>
                                <a href='{$baseUrl}' style='color: #39ff14; text-decoration: none;'>www.wgs-service.cz</a>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>

</body>
</html>";
}
