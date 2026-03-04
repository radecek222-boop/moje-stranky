<?php
/**
 * Diagnostika a oprava GDPR v emailových šablonách
 *
 * Tento skript:
 * 1. Zkontroluje aktuální obsah template_data v DB pro všechny email šablony
 * 2. Zobrazí zda pole 'gdpr' existuje
 * 3. Přidá GDPR pouze tam kde chybí (bez přepsání ostatních polí)
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/email_template_base.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor.");
}

$gdprText = 'Zpracování Vašich osobních údajů probíhá v souladu s nařízením GDPR výhradně za účelem zajištění servisního zásahu. Podrobnosti: <a href="https://www.wgs-service.cz/gdpr.php" style="color:#666;text-decoration:underline;">wgs-service.cz/gdpr.php</a>';

$sledovaneSablony = [
    'order_created',
    'appointment_confirmed',
    'appointment_reminder',
    'order_completed',
    'contact_attempt',
];

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika GDPR v emailech</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1000px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,.1); }
        h1 { color: #333; border-bottom: 2px solid #333; padding-bottom: 10px; }
        h2 { color: #444; margin-top: 30px; }
        table { border-collapse: collapse; width: 100%; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px 14px; text-align: left; font-size: 0.9rem; }
        th { background: #eee; font-weight: 600; }
        .ok    { background: #d4edda; color: #155724; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .chybi { background: #f8d7da; color: #721c24; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .warn  { background: #fff3cd; color: #856404; padding: 3px 10px; border-radius: 12px; font-size: 0.8rem; font-weight: 600; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 8px 0; }
        .error   { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 5px; margin: 8px 0; }
        .info    { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 5px; margin: 8px 0; }
        .btn { display: inline-block; padding: 10px 22px; background: #333; color: #fff; text-decoration: none; border-radius: 5px; margin: 6px 4px; border: none; cursor: pointer; font-size: 0.95rem; }
        .btn:hover { background: #000; }
        .btn-secondary { background: #666; }
        pre { background: #f8f8f8; border: 1px solid #ddd; padding: 12px; border-radius: 4px; overflow-x: auto; font-size: 0.8rem; max-height: 200px; overflow-y: auto; }
        details summary { cursor: pointer; font-weight: 600; color: #333; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Diagnostika GDPR v emailových šablonách</h1>";

try {
    $pdo = getDbConnection();

    // Načíst všechny email šablony
    $placeholders = implode(',', array_fill(0, count($sledovaneSablony), '?'));
    $stmt = $pdo->prepare("
        SELECT id, name, trigger_event, type, active,
               template_data,
               CASE WHEN template_data IS NOT NULL AND template_data != '' THEN 1 ELSE 0 END AS ma_template_data
        FROM wgs_notifications
        WHERE trigger_event IN ({$placeholders}) AND type = 'email'
        ORDER BY FIELD(trigger_event, " . implode(',', array_fill(0, count($sledovaneSablony), '?')) . ")
    ");
    $stmt->execute(array_merge($sledovaneSablony, $sledovaneSablony));
    $sablony = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // -------------------------------------------------------
    // TABULKA STAVU
    // -------------------------------------------------------
    echo "<h2>Stav šablon v databázi</h2>";
    echo "<table>
        <tr>
            <th>Trigger</th>
            <th>Název</th>
            <th>Aktivní</th>
            <th>template_data</th>
            <th>Pole 'gdpr'</th>
            <th>GDPR text</th>
        </tr>";

    $chybiGdpr = [];

    foreach ($sablony as $sablona) {
        $maData   = !empty($sablona['template_data']);
        $gdprStav = '';
        $gdprPreview = '—';

        if ($maData) {
            $data = json_decode($sablona['template_data'], true);
            if (is_array($data) && array_key_exists('gdpr', $data)) {
                $gdprHodnota = $data['gdpr'];
                if (!empty($gdprHodnota)) {
                    $gdprStav    = "<span class='ok'>Existuje</span>";
                    $gdprPreview = htmlspecialchars(substr(strip_tags($gdprHodnota), 0, 60)) . '...';
                } else {
                    $gdprStav = "<span class='chybi'>Prázdné</span>";
                    $chybiGdpr[] = $sablona;
                }
            } else {
                $gdprStav = "<span class='chybi'>Chybí pole</span>";
                $chybiGdpr[] = $sablona;
            }
        } else {
            $gdprStav = "<span class='warn'>Bez template_data</span>";
            $chybiGdpr[] = $sablona;
        }

        echo "<tr>
            <td><code>{$sablona['trigger_event']}</code></td>
            <td>" . htmlspecialchars($sablona['name'] ?? '—') . "</td>
            <td>" . ($sablona['active'] ? "<span class='ok'>Ano</span>" : "<span class='chybi'>Ne</span>") . "</td>
            <td>" . ($maData ? "<span class='ok'>Ano</span>" : "<span class='chybi'>Chybí</span>") . "</td>
            <td>{$gdprStav}</td>
            <td style='font-size:0.8rem;color:#666'>{$gdprPreview}</td>
        </tr>";
    }

    // Šablony co úplně chybí v DB
    $nalezeneTriggery = array_column($sablony, 'trigger_event');
    foreach ($sledovaneSablony as $trigger) {
        if (!in_array($trigger, $nalezeneTriggery)) {
            echo "<tr>
                <td><code>{$trigger}</code></td>
                <td colspan='5'><span class='chybi'>Šablona v DB nenalezena!</span></td>
            </tr>";
        }
    }

    echo "</table>";

    // -------------------------------------------------------
    // DETAIL - co je v template_data
    // -------------------------------------------------------
    echo "<h2>Detail obsahu template_data</h2>";
    foreach ($sablony as $sablona) {
        if (empty($sablona['template_data'])) continue;
        $data = json_decode($sablona['template_data'], true);
        $klice = is_array($data) ? implode(', ', array_keys($data)) : 'nelze dekódovat';
        echo "<details style='margin: 8px 0;'>
            <summary>{$sablona['trigger_event']} – pole v JSON: <code>{$klice}</code></summary>
            <pre>" . htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)) . "</pre>
        </details>";
    }

    // -------------------------------------------------------
    // OPRAVA – přidat GDPR pouze kde chybí
    // -------------------------------------------------------
    if (!empty($chybiGdpr)) {
        echo "<h2>Oprava – doplnit GDPR</h2>";
        echo "<div class='info'>
            Následující šablony nemají GDPR text: <strong>" .
            implode(', ', array_column($chybiGdpr, 'trigger_event')) .
            "</strong>.<br>
            Kliknutím na tlačítko se GDPR přidá <strong>pouze do chybějících šablon</strong>,
            ostatní pole (obsah, upozornění, apod.) zůstanou beze změny.
        </div>";

        if (isset($_GET['oprav']) && $_GET['oprav'] === '1') {
            // PROVEDENÍ OPRAVY
            $pdo->beginTransaction();
            try {
                $stmtUpdate = $pdo->prepare("
                    UPDATE wgs_notifications
                    SET template_data = :template_data, updated_at = NOW()
                    WHERE id = :id
                ");

                foreach ($chybiGdpr as $sablona) {
                    // Načíst stávající data nebo vytvořit prázdné pole
                    $data = [];
                    if (!empty($sablona['template_data'])) {
                        $decodovano = json_decode($sablona['template_data'], true);
                        if (is_array($decodovano)) {
                            $data = $decodovano;
                        }
                    }

                    // Přidat pouze GDPR pole
                    $data['gdpr'] = $gdprText;

                    $novyJson = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
                    $stmtUpdate->execute([
                        'template_data' => $novyJson,
                        'id'            => $sablona['id'],
                    ]);

                    echo "<div class='success'>
                        Opravena šablona <strong>{$sablona['trigger_event']}</strong> (ID: {$sablona['id']}) – přidáno pole 'gdpr'
                    </div>";
                }

                $pdo->commit();
                echo "<div class='success'><strong>Oprava dokončena. GDPR bylo přidáno do všech chybějících šablon.</strong></div>";
                echo "<br><a href='diagnostika_gdpr_email.php' class='btn'>Znovu zkontrolovat</a>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
            }

        } else {
            echo "<a href='diagnostika_gdpr_email.php?oprav=1' class='btn'>DOPLNIT GDPR DO ŠABLON</a>";
            echo "<a href='admin.php' class='btn btn-secondary'>Zpět do adminu</a>";
        }

    } else {
        echo "<div class='success'><strong>Všechny šablony mají GDPR text. Vše je v pořádku.</strong></div>";

        // Náhled jak GDPR vypadá v emailu
        echo "<h2>Náhled emailu (appointment_confirmed)</h2>";
        $nahledSablona = null;
        foreach ($sablony as $s) {
            if ($s['trigger_event'] === 'appointment_confirmed') {
                $nahledSablona = $s;
                break;
            }
        }
        if ($nahledSablona) {
            $ukazkoveProm = [
                'customer_name'    => 'Jan Novák',
                'order_id'         => 'WGS-2026-0042',
                'date'             => date('d.m.Y', strtotime('+1 day')),
                'time'             => '14:00',
                'address'          => 'Václavské náměstí 1, 110 00 Praha 1',
                'technician_name'  => 'Pavel Technik',
                'technician_phone' => '+420 777 888 999',
            ];
            $nahledHtml = renderujEmailZeSablony($nahledSablona, $ukazkoveProm);
            echo "<iframe srcdoc='" . htmlspecialchars($nahledHtml, ENT_QUOTES) . "'
                  style='width:100%;height:600px;border:1px solid #ddd;border-radius:5px;'
                  sandbox='allow-same-origin'></iframe>";
        }

        echo "<br><a href='admin.php' class='btn btn-secondary'>Zpět do adminu</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
