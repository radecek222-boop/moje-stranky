<?php
/**
 * Hromadné odeslání emailu: Pozáruční servis Natuzzi
 *
 * UPOZORNĚNÍ: Tento skript odešle email VŠEM zákazníkům z databáze!
 * Před spuštěním VŽDY otestujte na několika testovacích adresách.
 *
 * Použití:
 * 1. Otevřít v prohlížeči: https://www.wgs-service.cz/odeslat_email_pozarucni_servis.php
 * 2. Zkontrolovat počet příjemců
 * 3. Kliknout "ODESLAT EMAILY" (jen když jste si jistí!)
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/emailClient.php';

// BEZPEČNOSTNÍ KONTROLA - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může odeslat hromadný email.");
}

// Kompaktní email HTML (bez velkých mezer)
function getEmailHTML($osloveni = 'Vážená paní, vážený pane,') {
    $baseUrl = 'https://www.wgs-service.cz';

    return "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>White Glove Service</title>
</head>
<body style='margin: 0; padding: 0; background-color: #f4f4f4; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif;'>
    <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='100%' style='background-color: #f4f4f4;'>
        <tr>
            <td style='padding: 20px 15px;'>
                <table role='presentation' cellspacing='0' cellpadding='0' border='0' width='600' style='margin: 0 auto; max-width: 600px;'>
                    <tr>
                        <td style='background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%); padding: 20px 30px; text-align: center; border-radius: 8px 8px 0 0;'>
                            <h1 style='margin: 0; font-size: 24px; font-weight: 700; color: #ffffff; letter-spacing: 1.5px;'>WHITE GLOVE SERVICE</h1>
                            <p style='margin: 5px 0 0 0; font-size: 11px; color: #888; text-transform: uppercase; letter-spacing: 0.8px;'>Premium Furniture Care</p>
                        </td>
                    </tr>
                    <tr>
                        <td style='background: #ffffff; padding: 0;'>
                            <div style='background: #f8f9fa; padding: 15px 30px; border-bottom: 1px solid #e5e5e5;'>
                                <h2 style='margin: 0; font-size: 18px; font-weight: 600; color: #333;'>NATUZZI – Pozáruční servis</h2>
                            </div>
                            <div style='padding: 20px 30px 15px 30px;'>
                                <p style='margin: 0 0 10px 0; font-size: 15px; color: #333;'>{$osloveni}</p>
                                <p style='margin: 0 0 10px 0; font-size: 14px; color: #555; line-height: 1.6;'>
                                    rádi bychom Vás informovali, že pro společnost <strong>Natuzzi</strong> poskytujeme komplexní <strong>pozáruční servisní služby</strong>.
                                </p>
                                <p style='margin: 0 0 10px 0; font-size: 14px; color: #555; line-height: 1.6;'>
                                    Jelikož jsme u Vás již v minulosti byli (ať už při doručení nábytku, řešení reklamace nebo pozáručního servisu), rádi bychom Vám připomněli, že jsme tu pro Vás i nadále.
                                </p>
                                <h3 style='margin: 15px 0 8px 0; font-size: 15px; font-weight: 600; color: #333;'>Naše služby zahrnují:</h3>
                                <ul style='margin: 0 0 12px 0; padding-left: 20px; font-size: 14px; color: #555; line-height: 1.5;'>
                                    <li style='margin-bottom: 4px;'><strong>Opravy elektrických prvků</strong> – ovládání polohování, LED osvětlení, USB nabíječky</li>
                                    <li style='margin-bottom: 4px;'><strong>Opravy mechanismů</strong> – výsuvné mechanismy, polohování, otočné hlavy</li>
                                    <li style='margin-bottom: 4px;'><strong>Řešení vad prosezení</strong> – obnova komfortu sedacích ploch</li>
                                    <li style='margin-bottom: 4px;'><strong>Profesionální přečalounění</strong> – včetně výběru kvalitních materiálů</li>
                                    <li style='margin-bottom: 4px;'><strong>Čištění kožených sedaček</strong> – výhradně originálními prostředky Natuzzi</li>
                                </ul>
                                <p style='margin: 0; font-size: 14px; color: #555; line-height: 1.6;'>
                                    Pro čištění používáme <strong>pouze produkty Natuzzi</strong>, které jsou chemicky sladěné s impregnací a povrchovou úpravou Vašeho nábytku. Tím zajišťujeme maximální péči a dlouhou životnost sedacích souprav.
                                </p>
                            </div>
                            <div style='padding: 0 30px 15px 30px;'>
                                <div style='background: #fff3cd; border: 1px solid #f59e0b; border-radius: 6px; padding: 12px 15px;'>
                                    <p style='margin: 0; font-size: 13px; color: #92400e; line-height: 1.5;'>
                                        <strong>Máte zájem o více informací?</strong><br>
                                        Navštivte naše webové stránky <a href='{$baseUrl}' style='color: #92400e; text-decoration: underline;'>www.wgs-service.cz</a>, kde najdete:<br>
                                        • Kompletní přehled našich služeb<br>
                                        • Cenové podmínky<br>
                                        • Online objednávkový formulář<br>
                                        • Kontaktní údaje a provozní dobu
                                    </p>
                                </div>
                            </div>
                            <div style='padding: 0 30px 15px 30px;'>
                                <div style='background: #f8f9fa; border: 1px solid #dee2e6; border-radius: 6px; padding: 12px 15px;'>
                                    <p style='margin: 0; font-size: 13px; color: #666; line-height: 1.5;'>
                                        💡 <strong>Tip:</strong> Pravidelné čištění a údržba kožených sedaček 1-2× ročně výrazně prodlouží jejich životnost a zachová luxusní vzhled.
                                    </p>
                                </div>
                            </div>
                            <div style='padding: 5px 30px 20px 30px; text-align: center;'>
                                <a href='{$baseUrl}/novareklamace.php' style='display: inline-block; background: linear-gradient(135deg, #333 0%, #1a1a1a 100%); color: #ffffff; padding: 12px 35px; text-decoration: none; border-radius: 5px; font-weight: 600; font-size: 14px; letter-spacing: 0.5px;'>
                                    Objednat servis online
                                </a>
                            </div>
                            <div style='padding: 15px 30px; background: #f8f9fa; border-top: 1px solid #e5e5e5;'>
                                <p style='margin: 0; font-size: 13px; color: #666; line-height: 1.5;'>
                                    S pozdravem,<br>
                                    <strong>Tým White Glove Service</strong>
                                </p>
                            </div>
                        </td>
                    </tr>
                    <tr>
                        <td style='background: #1a1a1a; padding: 20px 30px; border-radius: 0 0 8px 8px; text-align: center;'>
                            <p style='margin: 0; font-size: 14px; font-weight: 600; color: #fff;'>White Glove Service, s.r.o.</p>
                            <p style='margin: 6px 0 0 0; font-size: 12px; color: #888;'>Do Dubče 364, 190 11 Praha 9 – Běchovice</p>
                            <p style='margin: 6px 0 0 0; font-size: 12px; color: #888;'>
                                Tel: <a href='tel:+420725965826' style='color: #888; text-decoration: none;'>+420 725 965 826</a> |
                                Email: <a href='mailto:reklamace@wgs-service.cz' style='color: #888; text-decoration: none;'>reklamace@wgs-service.cz</a>
                            </p>
                            <p style='margin: 10px 0 0 0; font-size: 11px; color: #555;'>
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

$predmet = 'NATUZZI – Pozáruční servis | WGS Service';

// HTML výstup
echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Hromadné odeslání emailu</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto; max-width: 1000px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .warning { background: #fff3cd; border: 2px solid #ffc107; color: #856404; padding: 20px; border-radius: 8px; margin: 20px 0; font-weight: 500; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .btn { display: inline-block; padding: 12px 30px; background: #333; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #1a1a1a; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #5a6268; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f8f9fa; font-weight: 600; }
        .counter { font-size: 48px; font-weight: 700; color: #333; text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    // === KROK 1: Načíst příjemce ===
    if (!isset($_POST['execute']) || $_POST['execute'] !== '1') {
        echo "<h1>📧 Hromadné odeslání emailu: Pozáruční servis Natuzzi</h1>";

        echo "<div class='warning'>
            <strong>⚠️ UPOZORNĚNÍ:</strong><br>
            Tento skript odešle email <strong>VŠEM zákazníkům</strong>, kteří mají v databázi dokončený servis Natuzzi produktů.<br>
            Před spuštěním zkontrolujte:<br>
            • Náhled emailu<br>
            • Počet příjemců<br>
            • Předmět emailu<br>
            • Že jsou všechny údaje správně
        </div>";

        // Načíst seznam příjemců z DB
        $stmt = $pdo->query("
            SELECT DISTINCT
                email,
                jmeno,
                telefon,
                datum_dokonceni
            FROM wgs_reklamace
            WHERE stav = 'done'
              AND email IS NOT NULL
              AND email != ''
              AND email LIKE '%@%'
            ORDER BY datum_dokonceni DESC
        ");

        $prijemci = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pocet = count($prijemci);

        echo "<div class='counter'>{$pocet} příjemců</div>";

        echo "<div class='info'>
            <strong>📋 Informace o odeslání:</strong><br>
            • <strong>Předmět:</strong> {$predmet}<br>
            • <strong>Od:</strong> WGS Service (reklamace@wgs-service.cz)<br>
            • <strong>Typ:</strong> HTML grafický email<br>
            • <strong>Odeslání:</strong> Postupně (rate limiting)<br>
        </div>";

        echo "<h2>Seznam příjemců (prvních 20):</h2>";
        echo "<table>";
        echo "<tr><th>Email</th><th>Jméno</th><th>Telefon</th><th>Poslední servis</th></tr>";

        foreach (array_slice($prijemci, 0, 20) as $p) {
            $datumServis = $p['datum_dokonceni'] ? date('d.m.Y', strtotime($p['datum_dokonceni'])) : 'N/A';
            echo "<tr>";
            echo "<td>" . htmlspecialchars($p['email']) . "</td>";
            echo "<td>" . htmlspecialchars($p['jmeno'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($p['telefon'] ?? '-') . "</td>";
            echo "<td>" . $datumServis . "</td>";
            echo "</tr>";
        }

        if ($pocet > 20) {
            echo "<tr><td colspan='4' style='text-align: center; color: #666;'>... a dalších " . ($pocet - 20) . " příjemců</td></tr>";
        }

        echo "</table>";

        echo "<div style='margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;'>";
        echo "<h3>Možnosti:</h3>";
        echo "<a href='/nahled_email_pozarucni_servis.php' target='_blank' class='btn btn-secondary'>📧 Zobrazit náhled emailu</a>";
        echo "<a href='/email_pozarucni_servis_info.md' target='_blank' class='btn btn-secondary'>📖 Přečíst dokumentaci</a>";
        echo "<br><br>";

        echo "<form method='POST' onsubmit='return confirm(\"Opravdu chcete odeslat email {$pocet} příjemcům? Tato akce je nevratná!\");'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn btn-danger'>🚀 ODESLAT EMAILY ({$pocet} příjemců)</button>";
        echo "</form>";
        echo "</div>";

    }
    // === KROK 2: Provést odeslání ===
    else {
        echo "<h1>📧 Odesílání emailů...</h1>";

        // Načíst příjemce
        $stmt = $pdo->query("
            SELECT DISTINCT
                email,
                jmeno
            FROM wgs_reklamace
            WHERE stav = 'done'
              AND email IS NOT NULL
              AND email != ''
              AND email LIKE '%@%'
        ");

        $prijemci = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $pocet = count($prijemci);
        $uspesne = 0;
        $chyby = 0;

        echo "<div class='info'>Celkem k odeslání: <strong>{$pocet} emailů</strong></div>";

        $emailClient = new EmailClient();

        echo "<table>";
        echo "<tr><th>Email</th><th>Stav</th></tr>";

        foreach ($prijemci as $index => $prijemce) {
            $email = $prijemce['email'];
            $jmeno = $prijemce['jmeno'] ?? '';

            // Personalizovat oslovení pokud máme jméno
            if (!empty($jmeno)) {
                $osloveni = "Vážený/á " . htmlspecialchars($jmeno) . ",";
                $html = getEmailHTML($osloveni);
            } else {
                $html = getEmailHTML();
            }

            try {
                $vysledek = $emailClient->odeslat([
                    'to' => $email,
                    'subject' => $predmet,
                    'html' => $html
                ]);

                if ($vysledek) {
                    echo "<tr><td>{$email}</td><td style='color: green;'>✅ Odesláno</td></tr>";
                    $uspesne++;
                } else {
                    echo "<tr><td>{$email}</td><td style='color: red;'>❌ Chyba odeslání</td></tr>";
                    $chyby++;
                }

                // Rate limiting - 1 email za vteřinu
                if (($index + 1) < $pocet) {
                    sleep(1);
                }

                // Flush output aby bylo vidět průběh
                ob_flush();
                flush();

            } catch (Exception $e) {
                echo "<tr><td>{$email}</td><td style='color: red;'>❌ " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                $chyby++;
            }
        }

        echo "</table>";

        echo "<div class='success'>";
        echo "<h2>✅ Dokončeno!</h2>";
        echo "<p><strong>Úspěšně odesláno:</strong> {$uspesne} emailů</p>";
        if ($chyby > 0) {
            echo "<p><strong>Selhalo:</strong> {$chyby} emailů</p>";
        }
        echo "</div>";

        echo "<a href='/odeslat_email_pozarucni_servis.php' class='btn'>⬅️ Zpět na přehled</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
