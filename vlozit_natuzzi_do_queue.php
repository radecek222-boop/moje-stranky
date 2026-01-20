<?php
/**
 * Vložení Natuzzi emailů do email queue
 *
 * Vloží všechny emaily z CSV do wgs_email_queue.
 * Systém je pak automaticky odešle přes existující email dispatcher.
 * Viditelné v Control Centre → EMAIL & SMS
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOSTNÍ KONTROLA
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

// Email HTML funkce
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

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Vložit Natuzzi kampaň do queue</title>
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
        .counter { font-size: 72px; font-weight: 700; color: #28a745;
                   text-align: center; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f8f9fa; font-weight: 600; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();
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

    $celkem = count($vsechnyEmaily);

    // Zjistit které už jsou v queue (ať už sent nebo pending)
    $stmt = $pdo->prepare("
        SELECT DISTINCT recipient_email
        FROM wgs_email_queue
        WHERE subject = :subject
    ");
    $stmt->execute(['subject' => $predmet]);
    $jizVQueue = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Zbývající emaily k vložení
    $zbyvajiciEmaily = array_diff($vsechnyEmaily, $jizVQueue);
    $zbyvajiciEmaily = array_values($zbyvajiciEmaily);

    $jizVlozeno = count($jizVQueue);
    $zbyva = count($zbyvajiciEmaily);

    echo "<h1>📧 Vložení Natuzzi kampaně do email queue</h1>";

    echo "<div class='info'>";
    echo "<strong>📊 Statistika:</strong><br>";
    echo "• Celkem v CSV: <strong>{$celkem}</strong><br>";
    echo "• Již ve frontě: <strong>{$jizVlozeno}</strong><br>";
    echo "• Zbývá vložit: <strong>{$zbyva}</strong>";
    echo "</div>";

    if ($zbyva === 0) {
        echo "<div class='success'>";
        echo "<strong>✅ HOTOVO!</strong><br>";
        echo "Všechny emaily už jsou ve frontě.<br>";
        echo "Sledujte progress v Control Centre → EMAIL & SMS";
        echo "</div>";

        echo "<a href='/admin.php' class='btn'>← Control Centre</a>";
    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ POZOR:</strong><br>";
        echo "Vloží se <strong>{$zbyva}</strong> emailů do email queue.<br>";
        echo "Odesílání probíhá automaticky přes existující dispatcher.<br>";
        echo "Progress uvidíte v <strong>Control Centre → EMAIL & SMS</strong>";
        echo "</div>";

        echo "<h2>Nastavení odeslání:</h2>";
        echo "<form method='POST'>";
        echo "<label>Interval mezi emaily (minuty): <input type='number' name='interval' value='1' min='1' max='60' style='padding: 8px; font-size: 16px;'></label><br><br>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn btn-danger' onclick='return confirm(\"Opravdu vložit {$zbyva} emailů do fronty?\");'>🚀 VLOŽIT DO QUEUE ({$zbyva} emailů)</button>";
        echo "</form>";

        // KROK 2: Provést vložení
        if (isset($_POST['execute']) && $_POST['execute'] === '1') {
            echo "<hr>";
            echo "<h2>⚙️ Vkládání do queue...</h2>";

            $interval = isset($_POST['interval']) ? (int)$_POST['interval'] : 1;
            $emailBody = getEmailHTML();

            $stmt = $pdo->prepare("
                INSERT INTO wgs_email_queue (
                    notification_id,
                    recipient_email,
                    recipient_name,
                    subject,
                    body,
                    priority,
                    status,
                    scheduled_at,
                    created_at
                ) VALUES (
                    :notification_id,
                    :recipient_email,
                    NULL,
                    :subject,
                    :body,
                    'normal',
                    'pending',
                    :scheduled_at,
                    NOW()
                )
            ");

            $uspesne = 0;
            $chyby = 0;
            $startTime = time();

            echo "<table>";
            echo "<tr><th>Email</th><th>Naplánováno na</th><th>Stav</th></tr>";

            foreach ($zbyvajiciEmaily as $index => $email) {
                // Scheduled_at - postupně po X minutách
                $scheduledOffset = $index * $interval * 60; // sekund
                $scheduledAt = date('Y-m-d H:i:s', $startTime + $scheduledOffset);

                try {
                    $stmt->execute([
                        'notification_id' => 'marketing_natuzzi_pozarucni',
                        'recipient_email' => $email,
                        'subject' => $predmet,
                        'body' => $emailBody,
                        'scheduled_at' => $scheduledAt
                    ]);

                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($email) . "</td>";
                    echo "<td>" . $scheduledAt . "</td>";
                    echo "<td style='color: #155724;'>✅ Vloženo</td>";
                    echo "</tr>";

                    $uspesne++;

                    // Flush každých 50 řádků
                    if ($uspesne % 50 === 0) {
                        ob_flush();
                        flush();
                    }

                } catch (PDOException $e) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($email) . "</td>";
                    echo "<td>-</td>";
                    echo "<td style='color: #721c24;'>❌ " . htmlspecialchars($e->getMessage()) . "</td>";
                    echo "</tr>";
                    $chyby++;
                }
            }

            echo "</table>";

            echo "<div class='success'>";
            echo "<strong>✅ VLOŽENÍ DOKONČENO!</strong><br>";
            echo "Úspěšně vloženo: <strong>{$uspesne}</strong> emailů<br>";
            if ($chyby > 0) {
                echo "Chyby: <strong>{$chyby}</strong><br>";
            }
            echo "Interval: <strong>{$interval} minut</strong> mezi emaily<br>";
            echo "První email: <strong>IHNED</strong><br>";
            echo "Poslední email: <strong>za " . round($uspesne * $interval / 60, 1) . " hodin</strong>";
            echo "</div>";

            echo "<div class='info'>";
            echo "📊 <strong>Sledujte progress:</strong><br>";
            echo "<a href='/admin.php' class='btn'>Control Centre → EMAIL & SMS</a>";
            echo "</div>";
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
