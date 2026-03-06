<?php
/**
 * API: Odeslání výtisku zakázky emailem
 *
 * Odešle shrnutí zakázky na email zákazníka
 * a zároveň kopii přihlášenému uživateli (odesílateli).
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/api_response.php';
require_once __DIR__ . '/../includes/EmailQueue.php';

header('Content-Type: application/json; charset=utf-8');

// Pouze přihlášený uživatel
if (!isset($_SESSION['user_id']) && !(isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true)) {
    sendJsonError('Přístup odepřen', 401);
}

// CSRF validace
if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
    sendJsonError('Neplatný CSRF token', 403);
}

// Validace vstupního parametru
$reklamaceId = filter_input(INPUT_POST, 'reklamace_id', FILTER_VALIDATE_INT);
if (!$reklamaceId) {
    sendJsonError('Chybí platné ID zakázky');
}

try {
    $pdo = getDbConnection();

    // Načíst zakázku
    $stmt = $pdo->prepare('
        SELECT r.*,
               u.name  AS zadavatel_jmeno,
               u.email AS zadavatel_email
        FROM wgs_reklamace r
        LEFT JOIN wgs_users u ON r.created_by = u.user_id
        WHERE r.id = :id
        LIMIT 1
    ');
    $stmt->execute([':id' => $reklamaceId]);
    $zakázka = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$zakázka) {
        sendJsonError('Zakázka nenalezena', 404);
    }

    // Email zákazníka
    $emailZakaznika = trim($zakázka['email'] ?? '');
    if (!$emailZakaznika || !filter_var($emailZakaznika, FILTER_VALIDATE_EMAIL)) {
        sendJsonError('Zákazník nemá platný email');
    }

    // Email přihlášeného uživatele (odesílatele)
    $emailOdesílatele = $_SESSION['user_email'] ?? '';
    $jmenoOdesílatele = $_SESSION['user_name'] ?? $emailOdesílatele;

    // Číslo zakázky pro předmět
    $cisloZakazky = htmlspecialchars(
        $zakázka['reklamace_id'] ?? $zakázka['cislo'] ?? '#' . $reklamaceId,
        ENT_QUOTES,
        'UTF-8'
    );

    // Sestavit HTML email
    $emailTelo = sestavEmailVytisku($zakázka, $cisloZakazky);

    $emailQueue = new EmailQueue($pdo);
    $predmet = 'Servisní výtisk zakázky ' . $cisloZakazky . ' - White Glove Service';

    // Odeslat zákazníkovi
    $emailQueue->add($emailZakaznika, $predmet, $emailTelo);

    // Odeslat kopii přihlášenému uživateli (pokud má platný email a liší se od zákazníka)
    if ($emailOdesílatele
        && filter_var($emailOdesílatele, FILTER_VALIDATE_EMAIL)
        && strtolower($emailOdesílatele) !== strtolower($emailZakaznika)
    ) {
        $predmetKopie = '[Kopie] ' . $predmet;
        $emailQueue->add($emailOdesílatele, $predmetKopie, $emailTelo);
    }

    $zprava = 'Email odeslán zákazníkovi (' . $emailZakaznika . ')';
    if ($emailOdesílatele && filter_var($emailOdesílatele, FILTER_VALIDATE_EMAIL)) {
        $zprava .= ' a kopie na ' . $emailOdesílatele;
    }

    sendJsonSuccess($zprava);

} catch (PDOException $e) {
    error_log('odeslat_tisk_email.php chyba DB: ' . $e->getMessage());
    sendJsonError('Chyba při zpracování požadavku');
} catch (Exception $e) {
    error_log('odeslat_tisk_email.php chyba: ' . $e->getMessage());
    sendJsonError('Chyba při odesílání emailu');
}

/**
 * Sestaví HTML tělo emailu se shrnutím zakázky
 */
function sestavEmailVytisku(array $zakázka, string $cisloZakazky): string {
    $jmeno   = htmlspecialchars($zakázka['jmeno']   ?? '', ENT_QUOTES, 'UTF-8');
    $telefon = htmlspecialchars($zakázka['telefon'] ?? '', ENT_QUOTES, 'UTF-8');
    $email   = htmlspecialchars($zakázka['email']   ?? '', ENT_QUOTES, 'UTF-8');
    $adresa  = htmlspecialchars(trim(
        ($zakázka['adresa'] ?? '')
        ?: (($zakázka['ulice'] ?? '') . ' ' . ($zakázka['mesto'] ?? '') . ' ' . ($zakázka['psc'] ?? ''))
    ), ENT_QUOTES, 'UTF-8');

    $model    = htmlspecialchars($zakázka['model']    ?? '', ENT_QUOTES, 'UTF-8');
    $popis    = htmlspecialchars($zakázka['popis_problemu'] ?? $zakázka['popis'] ?? '', ENT_QUOTES, 'UTF-8');
    $datum    = htmlspecialchars($zakázka['datum_vytvoreni'] ?? $zakázka['vytvoreno_at'] ?? '', ENT_QUOTES, 'UTF-8');

    $stavMapa = [
        'wait'           => 'Čeká na zpracování',
        'open'           => 'Domluvená návštěva',
        'done'           => 'Hotovo',
        'cekame_na_dily' => 'Čekáme na díly',
    ];
    $stav = htmlspecialchars($stavMapa[$zakázka['stav'] ?? ''] ?? ($zakázka['stav'] ?? ''), ENT_QUOTES, 'UTF-8');

    $radekStyle = "border-bottom: 1px solid #f0f0f0; padding: 8px 0;";
    $labelStyle = "font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;";
    $hodnotaStyle = "font-size: 14px; color: #333; font-weight: 500; margin-top: 2px;";

    return "
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Servisní výtisk zakázky {$cisloZakazky}</title>
</head>
<body style='margin:0;padding:0;background:#f4f4f4;font-family:\"Segoe UI\",Arial,sans-serif;'>
<table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='background:#f4f4f4;'>
<tr><td style='padding:30px 20px;'>
<table role='presentation' cellspacing='0' cellpadding='0' border='0' width='600' style='margin:0 auto;max-width:600px;'>

    <!-- Hlavička -->
    <tr>
        <td style='background:#111;padding:30px 40px;text-align:center;border-radius:12px 12px 0 0;'>
            <h1 style='margin:0;font-size:24px;font-weight:700;color:#fff;letter-spacing:2px;'>WHITE GLOVE SERVICE</h1>
            <p style='margin:6px 0 0;font-size:11px;color:#888;text-transform:uppercase;letter-spacing:1px;'>Servisní výtisk zakázky</p>
        </td>
    </tr>

    <!-- Číslo zakázky -->
    <tr>
        <td style='background:#f8f9fa;padding:20px 40px;border-bottom:1px solid #e5e5e5;'>
            <table width='100%' cellspacing='0' cellpadding='0' border='0'>
                <tr>
                    <td>
                        <p style='margin:0;font-size:18px;font-weight:700;color:#333;'>Zakázka č. {$cisloZakazky}</p>
                    </td>
                    <td style='text-align:right;'>
                        <span style='background:#111;color:#fff;font-size:12px;padding:4px 12px;border-radius:3px;font-weight:600;'>{$stav}</span>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Zákazník -->
    <tr>
        <td style='background:#fff;padding:25px 40px;'>
            <p style='margin:0 0 15px;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#111;border-bottom:2px solid #111;padding-bottom:6px;'>Zákazník</p>
            <table width='100%' cellspacing='0' cellpadding='0' border='0'>
                <tr>
                    <td width='50%' style='{$radekStyle}'>
                        <div style='{$labelStyle}'>Jméno</div>
                        <div style='{$hodnotaStyle}'>{$jmeno}</div>
                    </td>
                    <td width='50%' style='{$radekStyle}'>
                        <div style='{$labelStyle}'>Telefon</div>
                        <div style='{$hodnotaStyle}'>{$telefon}</div>
                    </td>
                </tr>
                <tr>
                    <td width='50%' style='{$radekStyle}'>
                        <div style='{$labelStyle}'>Email</div>
                        <div style='{$hodnotaStyle}'>{$email}</div>
                    </td>
                    <td width='50%' style='{$radekStyle}'>
                        <div style='{$labelStyle}'>Adresa</div>
                        <div style='{$hodnotaStyle}'>{$adresa}</div>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- Produkt a popis -->
    <tr>
        <td style='background:#fff;padding:0 40px 25px;'>
            <p style='margin:0 0 15px;font-size:13px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#111;border-bottom:2px solid #111;padding-bottom:6px;'>Produkt</p>
            <table width='100%' cellspacing='0' cellpadding='0' border='0'>
                <tr>
                    <td style='{$radekStyle}'>
                        <div style='{$labelStyle}'>Model</div>
                        <div style='{$hodnotaStyle}'>{$model}</div>
                    </td>
                </tr>
                " . ($popis ? "
                <tr>
                    <td style='padding:8px 0;'>
                        <div style='{$labelStyle}'>Popis problému</div>
                        <div style='font-size:14px;color:#333;margin-top:4px;line-height:1.6;'>{$popis}</div>
                    </td>
                </tr>" : "") . "
            </table>
        </td>
    </tr>

    <!-- Patička -->
    <tr>
        <td style='background:#f8f9fa;padding:20px 40px;border-top:1px solid #e5e5e5;border-radius:0 0 12px 12px;text-align:center;'>
            <p style='margin:0;font-size:12px;color:#888;'>White Glove Service — Natuzzi Italy</p>
            <p style='margin:4px 0 0;font-size:11px;color:#aaa;'>Odesláno: " . date('d.m.Y H:i') . "</p>
        </td>
    </tr>

    <!-- Právní poznámka -->
    <tr>
        <td style='padding:16px 40px;text-align:center;'>
            <p style='margin:0;font-size:10px;color:#bbb;line-height:1.6;'>
                Tento dokument má pouze informační charakter a nepředstavuje výzvu k úhradě.
                Fakturu nebo potvrzení o provedené platbě Vám zašleme obratem na základě Vaší žádosti.
            </p>
        </td>
    </tr>

</table>
</td></tr>
</table>
</body>
</html>";
}
?>
