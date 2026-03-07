<?php
/**
 * Hromadné odeslání emailu Natuzzi pozáruční servis - Z CSV SOUBORU
 *
 * Načte emaily z contacts_all.csv a odešle každému samostatný email
 * s 1 sekundovou pauzou (anti-spam).
 *
 * Použití:
 * 1. Otevřít v prohlížeči: https://www.wgs-service.cz/odeslat_email_z_csv.php
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
                                <p style='margin: 0 0 18px 0; font-size: 15px; color: #333;'>{$osloveni}</p>
                                <p style='margin: 0 0 18px 0; font-size: 13px; color: #666; line-height: 1.6; font-style: italic;'>
                                    Dovolujeme si Vás kontaktovat, protože Váš email máme z naší předchozí spolupráce – ať už z doručení nábytku Natuzzi nebo z poskytnutého servisu.
                                </p>
                                <p style='margin: 0 0 18px 0; font-size: 14px; color: #555; line-height: 1.6;'>
                                    Rádi bychom Vás informovali, že pro společnost <strong>Natuzzi</strong> poskytujeme komplexní <strong>pozáruční servisní služby</strong> a jsme tu pro Vás i nadále.
                                </p>
                                <h3 style='margin: 15px 0 8px 0; font-size: 15px; font-weight: 600; color: #333;'>Naše služby zahrnují:</h3>
                                <ul style='margin: 0 0 12px 0; padding-left: 20px; font-size: 14px; color: #555; line-height: 1.5;'>
                                    <li style='margin-bottom: 4px;'><strong>Řešení vad prosezení</strong> – obnova komfortu sedacích ploch</li>
                                    <li style='margin-bottom: 4px;'><strong>Profesionální přečalounění</strong> – včetně výběru kvalitních materiálů</li>
                                    <li style='margin-bottom: 4px;'><strong>Opravy elektrických prvků</strong> – ovládání polohování, LED osvětlení, USB nabíječky, výměna spínačů, výměna motoru apod.</li>
                                    <li style='margin-bottom: 4px;'><strong>Opravy mechanismů</strong> – výsuvné mechanismy, polohování, otočné hlavy</li>
                                    <li style='margin-bottom: 4px;'><strong>Čištění kožených sedaček</strong> – výhradně originálními prostředky Natuzzi</li>
                                </ul>
                                <p style='margin: 12px 0; font-size: 14px; color: #333; line-height: 1.6; background: #f8f9fa; padding: 12px 15px; border-left: 3px solid #333; border-radius: 4px;'>
                                    <strong>Prosezení sedačky není vada, se kterou se musíte smířit!</strong> Většinu problémů vyřešíme během jediné návštěvy přímo u Vás doma – <strong>bez nutnosti odvážet nábytek</strong>. Nemusíte mít obavu z přepravy ani z toho, že byste zůstali bez místa k sezení. Přes 90 % našich oprav lze provést na místě a Vaše sedačka bude vypadat a fungovat jako nová.
                                </p>
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
                                <a href='{$baseUrl}/objednatservis.php' style='display: inline-block; background: linear-gradient(135deg, #333 0%, #1a1a1a 100%); color: #ffffff; padding: 12px 35px; text-decoration: none; border-radius: 5px; font-weight: 600; font-size: 14px; letter-spacing: 0.5px;'>
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
    <title>Hromadné odeslání emailu - CSV</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0;
               border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #1a1a1a; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        .btn-secondary { background: #6c757d; }
        .counter { font-size: 48px; font-weight: 700; color: #333;
                   text-align: center; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f8f9fa; font-weight: 600; }
    </style>
</head>
<body>
<div class='container'>";

try {
    // CSV soubor
    $csvFile = __DIR__ . '/contacts_all.csv';

    if (!file_exists($csvFile)) {
        throw new Exception("CSV soubor <code>contacts_all.csv</code> nebyl nalezen v root složce.");
    }

    // === KROK 1: Zobrazit náhled a počet příjemců ===
    if (!isset($_POST['execute']) || $_POST['execute'] !== '1') {
        echo "<h1>Hromadné odeslání emailu - Natuzzi pozáruční servis (CSV)</h1>";

        echo "<div class='warning'>";
        echo "<strong>POZOR: UPOZORNĚNÍ:</strong><br>";
        echo "Tento skript odešle email <strong>VŠEM emailům</strong> z CSV souboru <code>contacts_all.csv</code>.<br>";
        echo "Před odesláním si zkontrolujte náhled emailu a počet příjemců!<br><br>";
        echo "<strong>🛡️ Anti-spam ochrana:</strong><br>";
        echo "• Každý email je odesílán samostatně (ne BCC)<br>";
        echo "• 1 sekunda pauza mezi emaily<br>";
        echo "• Plně personalizovaný obsah";
        echo "</div>";

        // Načíst příjemce z CSV souboru
        $prijemci = [];
        $handle = fopen($csvFile, 'r');

        // Přeskočit header
        fgetcsv($handle, 1000, ';');

        // Načíst všechny emaily
        while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
            if (!empty($data[0]) && filter_var($data[0], FILTER_VALIDATE_EMAIL)) {
                $prijemci[] = [
                    'email' => trim($data[0]),
                    'domain' => $data[1] ?? '',
                    'segment' => $data[2] ?? ''
                ];
            }
        }
        fclose($handle);

        $pocet = count($prijemci);
        $casCelkem = round($pocet / 60, 1);

        echo "<div class='counter'>{$pocet} příjemců</div>";

        echo "<div class='info'>";
        echo "<strong>Informace o odeslání:</strong><br>";
        echo "• <strong>Zdroj:</strong> contacts_all.csv<br>";
        echo "• <strong>Předmět:</strong> {$predmet}<br>";
        echo "• <strong>Od:</strong> WGS Service (reklamace@wgs-service.cz)<br>";
        echo "• <strong>Typ:</strong> HTML grafický email<br>";
        echo "• <strong>Odeslání:</strong> Postupně, jeden po druhém (1 sekunda pauza)<br>";
        echo "• <strong>Odhadovaný čas:</strong> cca {$casCelkem} minut";
        echo "</div>";

        echo "<h2>Seznam příjemců (prvních 30):</h2>";
        echo "<table>";
        echo "<tr><th>Email</th><th>Doména</th><th>Segment</th></tr>";

        foreach (array_slice($prijemci, 0, 30) as $p) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($p['email']) . "</td>";
            echo "<td>" . htmlspecialchars($p['domain']) . "</td>";
            echo "<td>" . htmlspecialchars($p['segment']) . "</td>";
            echo "</tr>";
        }

        if ($pocet > 30) {
            echo "<tr><td colspan='3' style='text-align: center; color: #666;'>... a dalších " . ($pocet - 30) . " příjemců</td></tr>";
        }

        echo "</table>";

        echo "<div style='margin-top: 30px; padding: 20px; background: #f8f9fa; border-radius: 8px;'>";
        echo "<h3>Možnosti:</h3>";
        echo "<a href='/nahled_email_pozarucni_servis.php' target='_blank' class='btn btn-secondary'>Zobrazit náhled emailu</a>";
        echo "<br><br>";

        echo "<form method='POST' onsubmit='return confirm(\"Opravdu chcete odeslat email {$pocet} příjemcům?\\n\\nKaždý email půjde samostatně s 1 sekundovou pauzou.\\nCelkový čas: cca {$casCelkem} minut.\\n\\nTato akce je nevratná!\");'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn btn-danger'>ODESLAT EMAILY ({$pocet} příjemců)</button>";
        echo "</form>";
        echo "</div>";

    }
    // === KROK 2: Provést odeslání ===
    else {
        echo "<h1>Odesílání emailů...</h1>";

        // Načíst příjemce z CSV souboru
        $prijemci = [];
        $handle = fopen($csvFile, 'r');

        // Přeskočit header
        fgetcsv($handle, 1000, ';');

        // Načíst všechny emaily
        while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
            if (!empty($data[0]) && filter_var($data[0], FILTER_VALIDATE_EMAIL)) {
                $prijemci[] = [
                    'email' => trim($data[0])
                ];
            }
        }
        fclose($handle);

        $pocet = count($prijemci);
        $uspesne = 0;
        $chyby = 0;

        echo "<div class='info'>Celkem k odeslání: <strong>{$pocet} emailů</strong></div>";

        $emailClient = new EmailClient();

        echo "<table>";
        echo "<tr><th>Email</th><th>Stav</th></tr>";

        foreach ($prijemci as $index => $prijemce) {
            $email = $prijemce['email'];

            // Generický email bez personalizace (nemáme jména z CSV)
            $html = getEmailHTML();

            try {
                $vysledek = $emailClient->odeslat([
                    'to' => $email,
                    'subject' => $predmet,
                    'html' => $html
                ]);

                if ($vysledek === true) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($email) . "</td>";
                    echo "<td style='color: #155724;'>OK: Odesláno</td>";
                    echo "</tr>";
                    $uspesne++;
                } else {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($email) . "</td>";
                    echo "<td style='color: #721c24;'>CHYBA: Chyba</td>";
                    echo "</tr>";
                    $chyby++;
                }

            } catch (Exception $e) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($email) . "</td>";
                echo "<td style='color: #721c24;'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</td>";
                echo "</tr>";
                $chyby++;
            }

            // Anti-spam: 1 sekunda pauza mezi emaily
            if ($index < count($prijemci) - 1) {
                sleep(1);
            }

            // Flush výstupu, aby uživatel viděl progress
            ob_flush();
            flush();
        }

        echo "</table>";

        echo "<div class='success'>";
        echo "<strong>OK: HOTOVO!</strong><br>";
        echo "Úspěšně odesláno: <strong>{$uspesne}</strong> emailů<br>";
        if ($chyby > 0) {
            echo "Chyby: <strong>{$chyby}</strong> emailů<br>";
        }
        echo "</div>";

        echo "<a href='/admin.php' class='btn'>← Zpět do Control Centre</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
