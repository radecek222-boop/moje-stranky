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
            if (!$isAdmin) {
                sendJsonError('Přístup odepřen', 403);
            }

            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                sendJsonError('Neplatný CSRF token', 403);
            }

            $povinne = ['zakaznik_jmeno', 'zakaznik_email', 'polozky'];
            foreach ($povinne as $pole) {
                if (empty($_POST[$pole])) {
                    sendJsonError("Chybí povinné pole: {$pole}");
                }
            }

            $polozky = json_decode($_POST['polozky'], true);
            if (!is_array($polozky) || empty($polozky)) {
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

            $stmt = $pdo->prepare("
                INSERT INTO wgs_nabidky (
                    zakaznik_jmeno, zakaznik_email, zakaznik_telefon, zakaznik_adresa,
                    polozky_json, celkova_cena, mena, platnost_do, token,
                    poznamka, vytvoril_user_id
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $stmt->execute([
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

            sendJsonSuccess('Nabídka vytvořena', [
                'nabidka_id' => $nabidkaId,
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

            $emailQueue->add(
                $nabidka['zakaznik_email'],
                'Cenová nabídka č. ' . $nabidkaId . ' - White Glove Service',
                $emailBody,
                'nabidka_' . $nabidkaId
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

            // Aktualizovat stav na potvrzeno
            $stmt = $pdo->prepare("
                UPDATE wgs_nabidky
                SET stav = 'potvrzena', potvrzeno_at = NOW(), potvrzeno_ip = ?
                WHERE id = ?
            ");
            $stmt->execute([$_SERVER['REMOTE_ADDR'], $nabidka['id']]);

            // Odeslat potvrzovací email adminovi
            require_once __DIR__ . '/../includes/EmailQueue.php';
            $emailQueue = new EmailQueue($pdo);

            $adminEmail = getenv('ADMIN_EMAIL') ?: 'reklamace@wgs-service.cz';
            $emailQueue->add(
                $adminEmail,
                'Nabídka č. ' . $nabidka['id'] . ' byla potvrzena zákazníkem',
                "Zákazník {$nabidka['zakaznik_jmeno']} ({$nabidka['zakaznik_email']}) potvrdil cenovou nabídku č. {$nabidka['id']}.\n\nCelková cena: {$nabidka['celkova_cena']} {$nabidka['mena']}\n\nIP adresa: {$_SERVER['REMOTE_ADDR']}\nČas: " . date('d.m.Y H:i:s'),
                'nabidka_potvrzeni_' . $nabidka['id']
            );

            sendJsonSuccess('Nabídka úspěšně potvrzena', [
                'nabidka_id' => $nabidka['id'],
                'potvrzeno_at' => date('Y-m-d H:i:s')
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
                SELECT id, zakaznik_jmeno, zakaznik_email, celkova_cena, mena,
                       stav, platnost_do, vytvoreno_at, odeslano_at, potvrzeno_at
                FROM wgs_nabidky
                ORDER BY vytvoreno_at DESC
                LIMIT 100
            ");
            $nabidky = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonSuccess('Seznam nabídek', ['nabidky' => $nabidky]);
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

} catch (Exception $e) {
    error_log("Nabídka API error: " . $e->getMessage());
    sendJsonError('Chyba serveru');
}

/**
 * Vytvoří tabulku wgs_nabidky pokud neexistuje
 */
function vytvorTabulkuNabidky($pdo) {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS wgs_nabidky (
            id INT AUTO_INCREMENT PRIMARY KEY,
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
            vytvoril_user_id INT NULL,
            vytvoreno_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            odeslano_at DATETIME NULL,
            potvrzeno_at DATETIME NULL,
            potvrzeno_ip VARCHAR(45) NULL,
            INDEX idx_token (token),
            INDEX idx_stav (stav),
            INDEX idx_email (zakaznik_email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

/**
 * Vygeneruje HTML email s nabídkou
 */
function vygenerujEmailNabidky($nabidka) {
    $polozky = json_decode($nabidka['polozky_json'], true);
    $baseUrl = 'https://www.wgs-service.cz';
    $potvrzeniUrl = $baseUrl . '/potvrzeni-nabidky.php?token=' . urlencode($nabidka['token']);

    $polozkyHtml = '';
    foreach ($polozky as $p) {
        $nazev = htmlspecialchars($p['nazev'] ?? '');
        $pocet = intval($p['pocet'] ?? 1);
        $cena = number_format(floatval($p['cena'] ?? 0), 2, ',', ' ');
        $celkem = number_format(floatval($p['cena'] ?? 0) * $pocet, 2, ',', ' ');
        $polozkyHtml .= "<tr>
            <td style='padding: 10px; border-bottom: 1px solid #eee;'>{$nazev}</td>
            <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: center;'>{$pocet}</td>
            <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>{$cena} {$nabidka['mena']}</td>
            <td style='padding: 10px; border-bottom: 1px solid #eee; text-align: right;'>{$celkem} {$nabidka['mena']}</td>
        </tr>";
    }

    $celkovaCena = number_format(floatval($nabidka['celkova_cena']), 2, ',', ' ');
    $platnostDo = date('d.m.Y', strtotime($nabidka['platnost_do']));

    return "
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Cenová nabídka č. {$nabidka['id']}</title>
</head>
<body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;'>
    <div style='background: #1a1a1a; padding: 20px; text-align: center;'>
        <h1 style='color: #fff; margin: 0; font-size: 24px;'>WHITE GLOVE SERVICE</h1>
        <p style='color: #888; margin: 5px 0 0 0; font-size: 12px;'>Cenová nabídka č. {$nabidka['id']}</p>
    </div>

    <div style='padding: 30px 20px;'>
        <p>Vážený/á <strong>{$nabidka['zakaznik_jmeno']}</strong>,</p>

        <p>děkujeme za Váš zájem o naše služby. Níže naleznete cenovou nabídku na požadované práce:</p>

        <table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>
            <thead>
                <tr style='background: #f5f5f5;'>
                    <th style='padding: 10px; text-align: left; border-bottom: 2px solid #ddd;'>Služba</th>
                    <th style='padding: 10px; text-align: center; border-bottom: 2px solid #ddd;'>Počet</th>
                    <th style='padding: 10px; text-align: right; border-bottom: 2px solid #ddd;'>Cena/ks</th>
                    <th style='padding: 10px; text-align: right; border-bottom: 2px solid #ddd;'>Celkem</th>
                </tr>
            </thead>
            <tbody>
                {$polozkyHtml}
            </tbody>
            <tfoot>
                <tr style='background: #f9f9f9;'>
                    <td colspan='3' style='padding: 15px; text-align: right; font-weight: bold; border-top: 2px solid #ddd;'>Celková cena (bez DPH):</td>
                    <td style='padding: 15px; text-align: right; font-weight: bold; font-size: 18px; border-top: 2px solid #ddd;'>{$celkovaCena} {$nabidka['mena']}</td>
                </tr>
            </tfoot>
        </table>

        <div style='background: #fffde7; border: 1px solid #ffd600; padding: 15px; border-radius: 5px; margin: 20px 0;'>
            <strong>Platnost nabídky:</strong> do {$platnostDo}
        </div>

        <div style='text-align: center; margin: 30px 0;'>
            <a href='{$potvrzeniUrl}' style='display: inline-block; background: #28a745; color: #fff; padding: 15px 40px; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;'>
                POTVRDIT NABÍDKU
            </a>
        </div>

        <p style='font-size: 12px; color: #666; border-top: 1px solid #eee; padding-top: 20px; margin-top: 30px;'>
            Kliknutím na tlačítko \"POTVRDIT NABÍDKU\" potvrzujete, že souhlasíte s touto cenovou nabídkou a
            uzavíráte tím smlouvu o dílo s White Glove Service, s.r.o. dle
            <a href='{$baseUrl}/podminky.php' style='color: #666;'>obchodních podmínek</a>.
        </p>
    </div>

    <div style='background: #f5f5f5; padding: 20px; text-align: center; font-size: 12px; color: #666;'>
        <p style='margin: 0;'><strong>White Glove Service, s.r.o.</strong></p>
        <p style='margin: 5px 0;'>Do Dubče 364, 190 11 Praha 9 – Běchovice</p>
        <p style='margin: 5px 0;'>Tel: +420 725 965 826 | Email: reklamace@wgs-service.cz</p>
    </div>
</body>
</html>";
}
