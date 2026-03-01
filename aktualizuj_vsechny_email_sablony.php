<?php
/**
 * Migrace: Kompletní aktualizace všech emailových šablon na grafický styl
 *
 * Aktualizuje template_data JSON pro šablony:
 *   1. order_created       – Potvrzení přijetí žádosti o servis
 *   2. appointment_confirmed – Potvrzení termínu návštěvy
 *   3. appointment_reminder  – Připomínka zítřejšího termínu
 *   4. order_completed       – Dokončení servisního zásahu
 *   5. contact_attempt       – Pokus o telefonický kontakt
 *
 * Grafické zásady:
 *   - Kontaktní info POUZE v patičce (ne v textu)
 *   - WiFi žádost + GDPR jako malý text v infoboxu
 *   - Bez opakujících se vět
 *   - Příprava nábytku jako upozornění (žlutý box)
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/email_template_base.php';

// Bezpečnostní kontrola – pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

// ============================================================
// DEFINICE ŠABLON
// ============================================================

$gdprText = 'Zpracování Vašich osobních údajů probíhá v souladu s nařízením GDPR výhradně za účelem zajištění servisního zásahu. Podrobnosti: <a href="https://www.wgs-service.cz/gdpr.php" style="color:#666;text-decoration:underline;">wgs-service.cz/gdpr.php</a>';

$upozorneniTermin =
    "Prosíme o přípravu před příchodem technika:\n\n" .
    "1. Odstraňte z nábytku veškeré lůžkoviny, polštáře a osobní věci, aby mohl servisní zásah proběhnout bez komplikací.\n\n" .
    "2. Zajistěte prosím bezplatné a bezpečné parkování pro technika v blízkosti Vaší adresy.\n\n" .
    "3. Umožněte technikovi přístup k Vaší WiFi síti – pracujeme výhradně s online protokoly a v některých lokalitách není dostupné mobilní pokrytí.";

$sablony = [

    // --------------------------------------------------------
    // 1. NOVÁ REKLAMACE – zákazník
    // --------------------------------------------------------
    'order_created' => [
        'trigger_event'  => ['order_created', 'complaint_created'],
        'subject'        => 'Potvrzení přijetí Vaší žádosti o servis – WGS Service č. {{order_id}}',
        'template_data'  => [
            'nadpis'    => 'Vaše žádost o servis byla přijata',
            'osloveni'  => 'Vážený/á {{customer_name}},',
            'obsah'     =>
                "děkujeme, že jste se na nás obrátili. Vaše žádost o servis nábytku Natuzzi byla úspěšně zaregistrována v našem systému.\n\n" .
                "**Číslo zakázky:** {{order_id}}\n" .
                "**Datum přijetí:** {{created_at}}\n\n" .
                "Náš technik nyní prostuduje Vaši žádost a v nejbližší době Vás kontaktujeme s návrhem termínu návštěvy. Prosíme o trpělivost – každou žádost řešíme s maximální péčí.",
            'gdpr'      => $gdprText,
        ],
    ],

    // --------------------------------------------------------
    // 2. POTVRZENÍ TERMÍNU
    // --------------------------------------------------------
    'appointment_confirmed' => [
        'trigger_event'  => ['appointment_confirmed'],
        'subject'        => 'Potvrzení termínu návštěvy – {{date}} v {{time}} | WGS Service č. {{order_id}}',
        'template_data'  => [
            'nadpis'     => 'Termín návštěvy technika potvrzen',
            'osloveni'   => 'Vážený/á {{customer_name}},',
            'obsah'      =>
                "rádi Vám potvrzujeme sjednaný termín návštěvy technika WGS Service.\n\n" .
                "**Datum:** {{date}}\n" .
                "**Čas:** {{time}}\n" .
                "**Adresa:** {{address}}\n" .
                "**Zakázka č.:** {{order_id}}\n\n" .
                "**Technik:** {{technician_name}}\n" .
                "**Telefon technika:** {{technician_phone}}",
            'upozorneni' => $upozorneniTermin,
            'gdpr'       => $gdprText,
        ],
    ],

    // --------------------------------------------------------
    // 3. PŘIPOMÍNKA TERMÍNU
    // --------------------------------------------------------
    'appointment_reminder' => [
        'trigger_event'  => ['appointment_reminder'],
        'subject'        => 'Připomínka: Zítřejší návštěva technika – {{date}} v {{time}} | WGS Service',
        'template_data'  => [
            'nadpis'     => 'Připomínka zítřejší návštěvy technika',
            'osloveni'   => 'Vážený/á {{customer_name}},',
            'obsah'      =>
                "připomínáme Vám zítřejší návštěvu technika WGS Service.\n\n" .
                "**Datum:** {{date}}\n" .
                "**Čas:** {{time}}\n" .
                "**Adresa:** {{address}}\n" .
                "**Zakázka č.:** {{order_id}}\n\n" .
                "**Technik:** {{technician_name}}\n" .
                "**Telefon technika:** {{technician_phone}}",
            'upozorneni' => $upozorneniTermin,
            'gdpr'       => $gdprText,
        ],
    ],

    // --------------------------------------------------------
    // 4. DOKONČENÍ ZAKÁZKY
    // --------------------------------------------------------
    'order_completed' => [
        'trigger_event'  => ['order_completed', 'complaint_completed'],
        'subject'        => 'Váš servisní zásah byl dokončen – WGS Service č. {{order_id}}',
        'template_data'  => [
            'nadpis'    => 'Servisní zásah dokončen',
            'osloveni'  => 'Vážený/á {{customer_name}},',
            'obsah'     =>
                "s radostí Vás informujeme, že servisní zásah na Vašem nábytku Natuzzi byl úspěšně dokončen.\n\n" .
                "**Zakázka č.:** {{order_id}}\n" .
                "**Datum dokončení:** {{completed_at}}\n\n" .
                "Děkujeme za Vaši důvěru. Těšíme se na případnou další spolupráci.",
            'gdpr'      => $gdprText,
        ],
    ],

    // --------------------------------------------------------
    // 5. POKUS O KONTAKT
    // --------------------------------------------------------
    'contact_attempt' => [
        'trigger_event'  => ['contact_attempt'],
        'subject'        => 'Pokoušeli jsme se Vás kontaktovat – WGS Service, zakázka č. {{order_id}}',
        'template_data'  => [
            'nadpis'    => 'Pokoušeli jsme se Vás kontaktovat',
            'osloveni'  => 'Vážený/á {{customer_name}},',
            'obsah'     =>
                "dne **{{date}}** jsme se pokoušeli spojit s Vámi telefonicky ohledně servisní zakázky č. **{{order_id}}**.\n\n" .
                "Bohužel se nám nepodařilo Vás zastihnout. V brzké době se pokusíme kontaktovat Vás znovu.\n\n" .
                "Pokud nás chcete kontaktovat sami, technik **{{technician_name}}** je k dispozici na tel. **{{technician_phone}}**.",
            'gdpr'      => $gdprText,
        ],
    ],

];

// ============================================================
// HTML STRÁNKA SKRIPTU
// ============================================================

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Kompletní aktualizace emailových šablon</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1100px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #444; margin-top: 30px; }
        h3 { color: #555; margin-top: 20px; font-size: 1rem; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 8px 0; }
        .error   { background: #f8d7da; border: 1px solid #f5c6cb;
                   color: #721c24; padding: 12px; border-radius: 5px; margin: 8px 0; }
        .info    { background: #d1ecf1; border: 1px solid #bee5eb;
                   color: #0c5460; padding: 12px; border-radius: 5px; margin: 8px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px; margin: 8px 0; }
        .preview-frame { width: 100%; border: 1px solid #dee2e6; border-radius: 5px;
                         margin: 10px 0; min-height: 300px; }
        .btn { display: inline-block; padding: 10px 20px; background: #333;
               color: white; text-decoration: none; border-radius: 5px;
               margin: 8px 5px 8px 0; border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #000; }
        .btn-secondary { background: #666; }
        table { border-collapse: collapse; width: 100%; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px 12px; text-align: left;
                 font-size: 0.9rem; }
        th { background: #eee; font-weight: 600; }
        .badge-ok { background: #28a745; color: #fff; padding: 2px 8px;
                    border-radius: 10px; font-size: 0.8rem; }
        .badge-update { background: #fd7e14; color: #fff; padding: 2px 8px;
                        border-radius: 10px; font-size: 0.8rem; }
        .badge-new { background: #dc3545; color: #fff; padding: 2px 8px;
                     border-radius: 10px; font-size: 0.8rem; }
        details { margin: 10px 0; }
        summary { cursor: pointer; padding: 8px; background: #f8f9fa;
                  border: 1px solid #dee2e6; border-radius: 5px; font-weight: 600; }
        .preview-wrap { padding: 10px; background: #f0f0f0; border-radius: 5px; margin: 10px 0; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Kompletní aktualizace emailových šablon</h1>";
echo "<div class='info'>
    Tento skript aktualizuje všechny hlavní emailové šablony na grafický styl s těmito principy:<br>
    – Kontaktní info pouze v patičce (žádné opakování v textu)<br>
    – WiFi žádost a GDPR jako malý text v infoboxu<br>
    – Příprava nábytku jako výrazné upozornění<br>
    – Čistá, neopakující se struktura
</div>";

try {
    $pdo = getDbConnection();

    // Přehled šablon v DB
    $stmtVsechny = $pdo->query("
        SELECT trigger_event, name, subject,
               CASE WHEN template_data IS NOT NULL AND template_data != '' THEN 1 ELSE 0 END AS ma_grafiku
        FROM wgs_notifications
        WHERE type = 'email'
        ORDER BY trigger_event
    ");
    $aktualni = $stmtVsechny->fetchAll(PDO::FETCH_ASSOC);

    $aktualniBylMap = [];
    foreach ($aktualni as $r) {
        $aktualniBylMap[$r['trigger_event']] = $r;
    }

    echo "<h2>Přehled šablon – co bude aktualizováno</h2>";
    echo "<table><tr><th>Trigger</th><th>Název v DB</th><th>Stav</th></tr>";

    foreach ($sablony as $klic => $def) {
        $trigger = $def['trigger_event'][0];
        $nalezeno = $aktualniBylMap[$trigger] ?? null;

        if (!$nalezeno) {
            // zkusit alternativní triggery
            foreach ($def['trigger_event'] as $alt) {
                if (isset($aktualniBylMap[$alt])) {
                    $nalezeno = $aktualniBylMap[$alt];
                    break;
                }
            }
        }

        if (!$nalezeno) {
            $stav = "<span class='badge-new'>Šablona nenalezena v DB</span>";
        } elseif ($nalezeno['ma_grafiku']) {
            $stav = "<span class='badge-ok'>Bude aktualizována (má grafiku)</span>";
        } else {
            $stav = "<span class='badge-update'>Bude aktualizována (přidá se grafika)</span>";
        }

        $nazev = $nalezeno['name'] ?? '—';
        echo "<tr><td><code>{$trigger}</code></td><td>" . htmlspecialchars($nazev) . "</td><td>{$stav}</td></tr>";
    }
    echo "</table>";

    // Náhled šablon
    echo "<h2>Náhled nových šablon</h2>";
    foreach ($sablony as $klic => $def) {
        $nahledData = $def['template_data'];
        // Doplnit ukázkové proměnné pro náhled
        $ukazkove = [
            'customer_name'    => 'Jan Novák',
            'order_id'         => 'WGS-2026-0042',
            'created_at'       => date('d.m.Y'),
            'completed_at'     => date('d.m.Y'),
            'date'             => date('d.m.Y', strtotime('+1 day')),
            'time'             => '14:00',
            'address'          => 'Václavské náměstí 1, 110 00 Praha 1',
            'technician_name'  => 'Pavel Technik',
            'technician_phone' => '+420 777 888 999',
            'technician_email' => 'technik@wgs-service.cz',
            'product'          => 'Natuzzi pohovka Roma',
            'description'      => 'Prosezení sedáku',
        ];
        foreach ($nahledData as $pole => $hodnota) {
            if (is_string($hodnota)) {
                foreach ($ukazkove as $k => $v) {
                    $nahledData[$pole] = str_replace('{{' . $k . '}}', $v, $nahledData[$pole]);
                }
            }
        }
        $nahledHtml = renderujGrafickyEmail($nahledData);

        echo "<details><summary>" . htmlspecialchars($def['template_data']['nadpis'] ?? $klic) . " (<code>{$klic}</code>)</summary>";
        echo "<div class='preview-wrap'>";
        echo "<p><strong>Předmět:</strong> " . htmlspecialchars($def['subject']) . "</p>";
        echo "<iframe srcdoc='" . htmlspecialchars($nahledHtml, ENT_QUOTES) . "' class='preview-frame' sandbox='allow-same-origin'></iframe>";
        echo "</div></details>";
    }

    // ============================================================
    // SPUŠTĚNÍ AKTUALIZACE
    // ============================================================
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<h2>Provádím aktualizaci...</h2>";

        $pdo->beginTransaction();
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE wgs_notifications
                SET subject       = :subject,
                    template_data = :template_data,
                    updated_at    = NOW()
                WHERE trigger_event = :trigger AND type = 'email'
            ");

            foreach ($sablony as $klic => $def) {
                $jsonData = json_encode($def['template_data'], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

                foreach ($def['trigger_event'] as $triggerVarianta) {
                    $pocetRadku = $stmtUpdate->execute([
                        'subject'       => $def['subject'],
                        'template_data' => $jsonData,
                        'trigger'       => $triggerVarianta,
                    ]);
                    $ovlivneno = $stmtUpdate->rowCount();
                    if ($ovlivneno > 0) {
                        echo "<div class='success'>Aktualizováno <strong>{$ovlivneno} záznamů</strong> pro trigger <code>{$triggerVarianta}</code></div>";
                    }
                }
            }

            $pdo->commit();
            echo "<div class='success'><strong>Všechny šablony byly úspěšně aktualizovány.</strong></div>";

        } catch (PDOException $chybaDb) {
            $pdo->rollBack();
            echo "<div class='error'><strong>CHYBA při aktualizaci:</strong><br>" . htmlspecialchars($chybaDb->getMessage()) . "</div>";
        }

        echo "<br><a href='aktualizuj_vsechny_email_sablony.php' class='btn btn-secondary'>Zpět na kontrolu</a>";

    } else {
        echo "<br><a href='aktualizuj_vsechny_email_sablony.php?execute=1' class='btn'>SPUSTIT AKTUALIZACI</a>";
        echo "<a href='admin.php' class='btn btn-secondary'>Zpět do adminu</a>";
    }

} catch (Exception $vyjimka) {
    echo "<div class='error'><strong>CHYBA:</strong><br>" . htmlspecialchars($vyjimka->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
