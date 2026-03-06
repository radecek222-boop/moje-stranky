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

    // Načíst spojenou cenovou nabídku (pokud existuje)
    $nabidka = null;
    $nabidkaPolozky = [];
    $nabidkaZalohaEur = 0.0;
    try {
        $nStmt = $pdo->prepare('
            SELECT cislo_nabidky, celkova_cena, mena, polozky_json,
                   zf_odeslana_at, zf_uhrazena_at, potvrzeno_at, stav
            FROM wgs_nabidky
            WHERE reklamace_id = :rid
              AND stav NOT IN (\'zamitnuta\', \'expirovana\', \'zrusena\')
            ORDER BY vytvoreno_at DESC
            LIMIT 1
        ');
        $nStmt->execute([':rid' => $reklamaceId]);
        $nabidka = $nStmt->fetch(PDO::FETCH_ASSOC);
        if ($nabidka) {
            $nabidkaPolozky = json_decode($nabidka['polozky_json'] ?? '[]', true) ?? [];
            foreach ($nabidkaPolozky as $pol) {
                $jeNahradniDil = ($pol['skupina'] ?? '') === 'dily'
                    || str_starts_with($pol['nazev'] ?? '', 'Náhradní díl:');
                if ($jeNahradniDil) {
                    $nabidkaZalohaEur += floatval($pol['cena']) * intval($pol['pocet'] ?? 1);
                }
            }
        }
    } catch (PDOException $e) {
        // Nabídka nemusí existovat — ignorovat
    }

    // Email zákazníka
    $emailZakaznika = trim($zakázka['email'] ?? '');
    if (!$emailZakaznika || !filter_var($emailZakaznika, FILTER_VALIDATE_EMAIL)) {
        sendJsonError('Zákazník nemá platný email');
    }

    // Email přihlášeného uživatele (odesílatele)
    $emailOdesílatele = $_SESSION['user_email'] ?? '';

    // Číslo zakázky pro předmět
    $cisloZakazky = $zakázka['reklamace_id'] ?? $zakázka['cislo'] ?? '#' . $reklamaceId;

    // Sestavit HTML email
    $emailTelo = sestavEmailVytisku($zakázka, $nabidka, $nabidkaPolozky, $nabidkaZalohaEur, $cisloZakazky);

    $emailQueue = new EmailQueue($pdo);
    $predmet = 'Přehled zakázky ' . $cisloZakazky . ' – White Glove Service';

    // Odeslat zákazníkovi
    $emailQueue->add($emailZakaznika, $predmet, $emailTelo);

    // Odeslat kopii přihlášenému uživateli (pokud má platný email a liší se od zákazníka)
    if ($emailOdesílatele
        && filter_var($emailOdesílatele, FILTER_VALIDATE_EMAIL)
        && strtolower($emailOdesílatele) !== strtolower($emailZakaznika)
    ) {
        $emailQueue->add($emailOdesílatele, '[Kopie] ' . $predmet, $emailTelo);
    }

    $zprava = 'Email odeslán zákazníkovi (' . $emailZakaznika . ')';
    if ($emailOdesílatele && filter_var($emailOdesílatele, FILTER_VALIDATE_EMAIL)) {
        $zprava .= ' a kopie na ' . $emailOdesílatele;
    }

    sendJsonSuccess($zprava);

} catch (PDOException $e) {
    error_log('odeslat_tisk_email.php chyba DB: ' . $e->getMessage() . ' v ' . $e->getFile() . ':' . $e->getLine());
    sendJsonError('Chyba při zpracování požadavku');
} catch (\Throwable $e) {
    $podrobnost = get_class($e) . ': ' . $e->getMessage() . ' v ' . basename($e->getFile()) . ':' . $e->getLine();
    error_log('odeslat_tisk_email.php chyba: ' . $podrobnost);
    sendJsonError('Chyba: ' . $podrobnost);
}

/**
 * Sestaví grafické HTML tělo emailu se shrnutím zakázky
 */
function sestavEmailVytisku(array $zakázka, ?array $nabidka, array $nabidkaPolozky, float $nabidkaZalohaEur, string $cisloZakazky): string {
    $baseUrl  = 'https://www.wgs-service.cz';

    $jmeno   = htmlspecialchars($zakázka['jmeno']   ?? '', ENT_QUOTES, 'UTF-8');
    $telefon = htmlspecialchars($zakázka['telefon'] ?? '', ENT_QUOTES, 'UTF-8');
    $email   = htmlspecialchars($zakázka['email']   ?? '', ENT_QUOTES, 'UTF-8');
    $adresa  = htmlspecialchars(trim(
        ($zakázka['adresa'] ?? '')
        ?: (($zakázka['ulice'] ?? '') . ' ' . ($zakázka['mesto'] ?? '') . ' ' . ($zakázka['psc'] ?? ''))
    ), ENT_QUOTES, 'UTF-8');
    $model   = htmlspecialchars($zakázka['model']   ?? '', ENT_QUOTES, 'UTF-8');
    $popis   = htmlspecialchars($zakázka['popis_problemu'] ?? $zakázka['popis'] ?? '', ENT_QUOTES, 'UTF-8');

    $stavMapa = [
        'wait'           => 'Čeká na zpracování',
        'open'           => 'Domluvená návštěva',
        'done'           => 'Hotovo',
        'cekame_na_dily' => 'Čekáme na díly',
    ];
    $stav = htmlspecialchars($stavMapa[$zakázka['stav'] ?? ''] ?? ($zakázka['stav'] ?? ''), ENT_QUOTES, 'UTF-8');

    // ── Tabulka položek cenové nabídky ──────────────────────────────────────
    $tabulkaNabidkyHtml = '';
    if ($nabidka && !empty($nabidkaPolozky)) {
        $mena = htmlspecialchars($nabidka['mena'] ?? 'EUR', ENT_QUOTES, 'UTF-8');
        $cisloNabidky = htmlspecialchars($nabidka['cislo_nabidky'] ?? '', ENT_QUOTES, 'UTF-8');
        $celkovaCena  = number_format(floatval($nabidka['celkova_cena']), 2, ',', ' ');

        $polozkyHtml  = '';
        $aktualniSkupina = '';
        foreach ($nabidkaPolozky as $pol) {
            $nazev    = htmlspecialchars($pol['nazev']  ?? '', ENT_QUOTES, 'UTF-8');
            $pocet    = intval($pol['pocet'] ?? 1);
            $cenaKs   = floatval($pol['cena'] ?? 0);
            $cenaCelk = $cenaKs * $pocet;
            $skupina  = $pol['skupina'] ?? '';

            if ($skupina && $skupina !== $aktualniSkupina) {
                $aktualniSkupina = $skupina;
                $skupinaLabel = htmlspecialchars(strtoupper($skupina), ENT_QUOTES, 'UTF-8');
                $polozkyHtml .= "
                <tr>
                    <td colspan='4' style='padding: 8px 16px 4px; font-size: 11px; font-weight: 700; color: #888; text-transform: uppercase; letter-spacing: 1px; background: #f8f9fa; border-bottom: 1px solid #e5e5e5;'>{$skupinaLabel}</td>
                </tr>";
            }

            $polozkyHtml .= "
            <tr>
                <td style='padding: 12px 16px; border-bottom: 1px solid #f0f0f0; font-size: 13px; color: #333;'>{$nazev}</td>
                <td style='padding: 12px 16px; border-bottom: 1px solid #f0f0f0; text-align: center; font-size: 13px; color: #666;'>{$pocet}</td>
                <td style='padding: 12px 16px; border-bottom: 1px solid #f0f0f0; text-align: right; font-size: 13px; color: #666;'>" . number_format($cenaKs, 2, ',', ' ') . " {$mena}</td>
                <td style='padding: 12px 16px; border-bottom: 1px solid #f0f0f0; text-align: right; font-size: 13px; font-weight: 600; color: #333;'>" . number_format($cenaCelk, 2, ',', ' ') . " {$mena}</td>
            </tr>";
        }

        // Řádek zálohy (pokud existuje)
        $zalohaRadek = '';
        if ($nabidkaZalohaEur > 0) {
            $zalohaFormatovana = number_format($nabidkaZalohaEur, 2, ',', ' ');
            $zalohaRadek = "
            <tr>
                <td colspan='3' style='padding: 10px 16px; text-align: right; font-size: 13px; color: #555;'>Záloha – náhradní díly:</td>
                <td style='padding: 10px 16px; text-align: right; font-size: 13px; color: #555;'>- {$zalohaFormatovana} {$mena}</td>
            </tr>";
        }

        $tabulkaNabidkyHtml = "
        <!-- Cenová nabídka -->
        <tr>
            <td style='background: #fff; padding: 0 40px 30px;'>
                <p style='margin: 0 0 14px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #111; border-bottom: 2px solid #111; padding-bottom: 6px;'>
                    Cenová nabídka {$cisloNabidky}
                </p>
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='border: 1px solid #e5e5e5; border-radius: 8px; overflow: hidden;'>
                    <thead>
                        <tr style='background: #f8f9fa;'>
                            <th style='padding: 12px 16px; text-align: left; font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Název</th>
                            <th style='padding: 12px 16px; text-align: center; font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Ks</th>
                            <th style='padding: 12px 16px; text-align: right; font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Cena/ks</th>
                            <th style='padding: 12px 16px; text-align: right; font-size: 11px; font-weight: 600; color: #666; text-transform: uppercase; letter-spacing: 0.5px; border-bottom: 2px solid #e5e5e5;'>Celkem</th>
                        </tr>
                    </thead>
                    <tbody>
                        {$polozkyHtml}
                    </tbody>
                    <tfoot>
                        {$zalohaRadek}
                        <tr style='background: #1a1a1a;'>
                            <td colspan='3' style='padding: 16px; text-align: right; font-size: 13px; font-weight: 600; color: #fff;'>Celková cena (bez DPH):</td>
                            <td style='padding: 16px; text-align: right; font-size: 18px; font-weight: 700; color: #fff;'>{$celkovaCena} {$mena}</td>
                        </tr>
                    </tfoot>
                </table>
            </td>
        </tr>";
    }

    // ── Popis problému ───────────────────────────────────────────────────────
    $popisHtml = '';
    if ($popis) {
        $popisHtml = "
        <tr>
            <td style='background: #fff; padding: 0 40px 25px;'>
                <p style='margin: 0 0 10px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #111; border-bottom: 2px solid #111; padding-bottom: 6px;'>Popis problému</p>
                <p style='margin: 0; font-size: 14px; color: #444; line-height: 1.7;'>{$popis}</p>
            </td>
        </tr>";
    }

    return "
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Přehled zakázky {$cisloZakazky} – White Glove Service</title>
</head>
<body style='margin: 0; padding: 0; background-color: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;'>

<table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='background-color: #f4f4f4;'>
<tr><td style='padding: 30px 20px;'>
<table role='presentation' cellspacing='0' cellpadding='0' border='0' width='600' style='margin: 0 auto; max-width: 600px;'>

    <!-- HEADER -->
    <tr>
        <td style='background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); padding: 35px 40px; text-align: center; border-radius: 12px 12px 0 0;'>
            <h1 style='margin: 0; font-size: 28px; font-weight: 700; color: #ffffff; letter-spacing: 2px;'>WHITE GLOVE SERVICE</h1>
            <p style='margin: 8px 0 0 0; font-size: 12px; color: #888; text-transform: uppercase; letter-spacing: 1px;'>Premium Furniture Care</p>
        </td>
    </tr>

    <!-- Číslo zakázky + stav -->
    <tr>
        <td style='background: #f8f9fa; padding: 22px 40px; border-bottom: 1px solid #e5e5e5;'>
            <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%'>
                <tr>
                    <td>
                        <p style='margin: 0; font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;'>Přehled zakázky</p>
                        <p style='margin: 5px 0 0; font-size: 20px; font-weight: 700; color: #333;'>{$cisloZakazky}</p>
                    </td>
                    <td style='text-align: right;'>
                        <span style='display: inline-block; background: #1a1a1a; color: #fff; font-size: 11px; font-weight: 600; padding: 5px 14px; border-radius: 4px; text-transform: uppercase; letter-spacing: 0.5px;'>{$stav}</span>
                        <p style='margin: 6px 0 0; font-size: 11px; color: #aaa;'>Odesláno: " . date('d.m.Y H:i') . "</p>
                    </td>
                </tr>
            </table>
        </td>
    </tr>

    <!-- HLAVNÍ OBSAH -->
    <tr>
        <td style='background: #ffffff; padding: 0;'>

            <!-- Zákazník -->
            <div style='padding: 25px 40px 20px;'>
                <p style='margin: 0 0 14px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #111; border-bottom: 2px solid #111; padding-bottom: 6px;'>Zákazník</p>
                <div style='background: #f8f9fa; border-radius: 8px; padding: 18px 20px;'>
                    <p style='margin: 0; font-size: 15px; font-weight: 600; color: #333;'>{$jmeno}</p>
                    " . ($telefon ? "<p style='margin: 6px 0 0; font-size: 13px; color: #666;'>Tel: {$telefon}</p>" : '') . "
                    " . ($email   ? "<p style='margin: 4px 0 0; font-size: 13px; color: #666;'>{$email}</p>" : '') . "
                    " . ($adresa  ? "<p style='margin: 4px 0 0; font-size: 13px; color: #666;'>{$adresa}</p>" : '') . "
                </div>
            </div>

            " . ($model ? "
            <!-- Produkt -->
            <div style='padding: 0 40px 20px;'>
                <p style='margin: 0 0 14px; font-size: 13px; font-weight: 700; text-transform: uppercase; letter-spacing: 1px; color: #111; border-bottom: 2px solid #111; padding-bottom: 6px;'>Produkt</p>
                <div style='background: #f8f9fa; border-radius: 8px; padding: 14px 20px;'>
                    <p style='margin: 0; font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.5px;'>Model</p>
                    <p style='margin: 5px 0 0; font-size: 14px; font-weight: 600; color: #333;'>{$model}</p>
                </div>
            </div>" : '') . "

        </td>
    </tr>

    {$tabulkaNabidkyHtml}

    {$popisHtml}

    <!-- FOOTER -->
    <tr>
        <td style='background: #1a1a1a; padding: 30px 40px; border-radius: 0 0 12px 12px; text-align: center;'>
            <p style='margin: 0; font-size: 14px; font-weight: 600; color: #fff;'>White Glove Service, s.r.o.</p>
            <p style='margin: 8px 0 0; font-size: 13px; color: #888;'>Do Dubče 364, 190 11 Praha 9 – Běchovice</p>
            <p style='margin: 8px 0 0; font-size: 13px; color: #888;'>
                Tel: <a href='tel:+420725965826' style='color: #888; text-decoration: none;'>+420 725 965 826</a> |
                Email: <a href='mailto:reklamace@wgs-service.cz' style='color: #888; text-decoration: none;'>reklamace@wgs-service.cz</a>
            </p>
            <p style='margin: 14px 0 0; font-size: 12px; color: #555;'>
                <a href='{$baseUrl}' style='color: #39ff14; text-decoration: none;'>www.wgs-service.cz</a>
            </p>
        </td>
    </tr>

    <!-- Právní poznámka -->
    <tr>
        <td style='padding: 16px 40px; text-align: center;'>
            <p style='margin: 0; font-size: 10px; color: #bbb; line-height: 1.7;'>
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
