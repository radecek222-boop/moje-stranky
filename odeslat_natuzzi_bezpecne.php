<?php
/**
 * BEZPEČNÉ odeslání Natuzzi emailů - NEOPAKUJE odeslané
 *
 * Sleduje které emaily už byly odeslány a neopakuje je.
 * Ukládá progress do souboru natuzzi_kampan_progress.txt
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/emailClient.php';

// BEZPEČNOSTNÍ KONTROLA
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

// Email HTML
function getEmailHTML() {
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
                                <p style='margin: 0 0 18px 0; font-size: 15px; color: #333;'>Vážená paní, vážený pane,</p>
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
$progressFile = __DIR__ . '/natuzzi_kampan_progress.txt';

set_time_limit(600);

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta http-equiv='refresh' content='2'>
    <title>Bezpečné odeslání Natuzzi</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1200px;
               margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724;
                   padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460;
                padding: 15px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404;
                   padding: 15px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #333;
               color: white; text-decoration: none; border-radius: 5px;
               margin: 10px 5px 10px 0; border: none; cursor: pointer;
               font-size: 16px; font-weight: 600; }
        .btn:hover { background: #1a1a1a; }
        .btn-danger { background: #dc3545; }
        .progress { background: #f8f9fa; border: 2px solid #333;
                    border-radius: 8px; height: 40px; overflow: hidden;
                    margin: 20px 0; }
        .progress-bar { background: linear-gradient(90deg, #39ff14 0%, #28a745 100%);
                        height: 100%; transition: width 0.3s ease; color: #000;
                        display: flex; align-items: center; justify-content: center;
                        font-weight: 700; }
        .counter { font-size: 72px; font-weight: 700; color: #28a745;
                   text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $csvFile = __DIR__ . '/contacts_all.csv';

    if (!file_exists($csvFile)) {
        throw new Exception("CSV soubor nenalezen.");
    }

    // Načíst všechny emaily z CSV
    $vsechnyEmaily = [];
    $handle = fopen($csvFile, 'r');
    fgetcsv($handle, 1000, ';'); // header

    while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
        if (!empty($data[0]) && filter_var($data[0], FILTER_VALIDATE_EMAIL)) {
            $vsechnyEmaily[] = trim($data[0]);
        }
    }
    fclose($handle);

    // Načíst již odeslané emaily
    $odeslanéEmaily = [];
    if (file_exists($progressFile)) {
        $odeslanéEmaily = file($progressFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $odeslanéEmaily = array_map('trim', $odeslanéEmaily);
    }

    // Zjistit které zbývá odeslat
    $zbyvajiciEmaily = array_diff($vsechnyEmaily, $odeslanéEmaily);
    $zbyvajiciEmaily = array_values($zbyvajiciEmaily); // Re-index

    $celkem = count($vsechnyEmaily);
    $odeslano = count($odeslanéEmaily);
    $zbyva = count($zbyvajiciEmaily);

    echo "<h1>📧 Bezpečné odeslání Natuzzi kampaně</h1>";

    // Progress
    $procento = ($celkem > 0) ? round(($odeslano / $celkem) * 100, 1) : 0;

    echo "<div class='counter'>{$odeslano} / {$celkem}</div>";

    echo "<div class='progress'>";
    echo "<div class='progress-bar' style='width: {$procento}%'>{$procento}%</div>";
    echo "</div>";

    if ($zbyva === 0) {
        echo "<div class='success'>";
        echo "<strong>🎉 HOTOVO!</strong><br>";
        echo "Všechny emaily byly úspěšně odeslány.<br>";
        echo "Celkem: <strong>{$celkem}</strong> emailů";
        echo "</div>";

        echo "<a href='/kontrola_odeslenych_emailu.php' class='btn'>📊 Zobrazit statistiky</a>";
        echo "<a href='/admin.php' class='btn'>← Control Centre</a>";

    } else {
        echo "<div class='info'>";
        echo "<strong>📊 Statistika:</strong><br>";
        echo "• Celkem v CSV: {$celkem}<br>";
        echo "• Již odesláno: {$odeslano}<br>";
        echo "• Zbývá odeslat: <strong>{$zbyva}</strong>";
        echo "</div>";

        // Aktuální dávka
        $davkaVelikost = 50;
        $aktuálníDavka = array_slice($zbyvajiciEmaily, 0, $davkaVelikost);
        $pocetVDavce = count($aktuálníDavka);

        echo "<div class='warning'>";
        echo "<strong>📦 Další dávka:</strong> {$pocetVDavce} emailů<br>";
        echo "<strong>⏱️ Odhadovaný čas:</strong> cca " . round($pocetVDavce / 60, 1) . " minut";
        echo "</div>";

        // Automatické spuštění
        if (isset($_GET['auto']) && $_GET['auto'] === '1') {
            echo "<div class='info'><strong>⚙️ ODESÍLÁM...</strong></div>";

            $emailClient = new EmailClient();
            $uspesne = 0;
            $chyby = 0;

            echo "<table style='width: 100%; border-collapse: collapse; margin: 20px 0;'>";
            echo "<tr><th style='border: 1px solid #ddd; padding: 8px;'>Email</th><th style='border: 1px solid #ddd; padding: 8px;'>Stav</th></tr>";

            foreach ($aktuálníDavka as $email) {
                $html = getEmailHTML();

                try {
                    $vysledek = $emailClient->odeslat([
                        'to' => $email,
                        'subject' => $predmet,
                        'html' => $html
                    ]);

                    if ($vysledek === true) {
                        echo "<tr>";
                        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($email) . "</td>";
                        echo "<td style='border: 1px solid #ddd; padding: 8px; color: #155724;'>✅ OK</td>";
                        echo "</tr>";

                        // Uložit do progress souboru
                        file_put_contents($progressFile, $email . PHP_EOL, FILE_APPEND);

                        $uspesne++;
                    } else {
                        echo "<tr>";
                        echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($email) . "</td>";
                        echo "<td style='border: 1px solid #ddd; padding: 8px; color: #721c24;'>❌ Chyba</td>";
                        echo "</tr>";
                        $chyby++;
                    }

                } catch (Exception $e) {
                    echo "<tr>";
                    echo "<td style='border: 1px solid #ddd; padding: 8px;'>" . htmlspecialchars($email) . "</td>";
                    echo "<td style='border: 1px solid #ddd; padding: 8px; color: #721c24;'>❌ " . htmlspecialchars($e->getMessage()) . "</td>";
                    echo "</tr>";
                    $chyby++;
                }

                sleep(1);

                ob_flush();
                flush();
            }

            echo "</table>";

            echo "<div class='success'>";
            echo "<strong>✅ Dávka dokončena</strong><br>";
            echo "Úspěšně: {$uspesne} | Chyby: {$chyby}";
            echo "</div>";

            $novyZbytek = $zbyva - $uspesne;

            if ($novyZbytek > 0) {
                echo "<div class='info'>";
                echo "🔄 <strong>Pokračuji na další dávku za 3 sekundy...</strong><br>";
                echo "Zbývá: {$novyZbytek} emailů";
                echo "</div>";

                echo "<script>
                    setTimeout(function() {
                        window.location.href = '?auto=1';
                    }, 3000);
                </script>";
            } else {
                echo "<div class='success'>";
                echo "<strong>🎉 VŠECHNO ODESLÁNO!</strong>";
                echo "</div>";

                echo "<a href='/kontrola_odeslenych_emailu.php' class='btn'>📊 Zobrazit statistiky</a>";
            }

        } else {
            // Tlačítko pro spuštění
            echo "<div class='warning'>";
            echo "<strong>⚠️ POZOR:</strong><br>";
            echo "Kliknutím spustíte automatické odesílání.<br>";
            echo "Proces bude pokračovat až do konce (neopakuje odeslané).";
            echo "</div>";

            echo "<a href='?auto=1' class='btn btn-danger'>🚀 SPUSTIT ODESÍLÁNÍ ({$zbyva} emailů)</a>";
        }
    }

} catch (Exception $e) {
    echo "<div style='background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px;'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
