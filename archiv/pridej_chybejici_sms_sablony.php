<?php
/**
 * Migrace: Pridani chybejicich SMS sablon
 *
 * Pro kazdou EMAIL sablonu vytvori odpovidajici SMS sablonu (pokud neexistuje).
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Pridani chybejicich SMS sablon</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #f0f0f0; border: 1px solid #333;
                   color: #333; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #e9e9e9; border: 1px solid #999;
                color: #333; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #555; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .new { background: #e6ffe6 !important; }
        .exists { background: #fff3e6 !important; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Pridani chybejicich SMS sablon</h1>";

    // Definice SMS sablon pro kazdou udalost
    // Format: Dobry den {{customer_name}}, [text zpravy]. WGS Service - Autorizovany servis Natuzzi
    $smsSablony = [
        [
            'trigger_event' => 'order_created',
            'recipient_type' => 'admin',
            'name' => 'SMS - Nova reklamace (admin)',
            'description' => 'SMS notifikace pro admina pri vytvoreni nove reklamace',
            'subject' => 'Nova reklamace - WGS Service',
            'template' => 'Dobry den, byla vytvorena nova reklamace c. {{order_id}}. Zakaznik: {{customer_name}}, tel. {{customer_phone}}. Produkt: {{product}}. WGS Service - Autorizovany servis Natuzzi'
        ],
        [
            'trigger_event' => 'order_reopened',
            'recipient_type' => 'admin',
            'name' => 'SMS - Zakazka znovu otevrena',
            'description' => 'SMS notifikace pri znovuotevreni zakazky',
            'subject' => 'Zakazka znovu otevrena - WGS Service',
            'template' => 'Dobry den, zakazka c. {{order_id}} byla znovu otevrena. Zakaznik: {{customer_name}}, tel. {{customer_phone}}. WGS Service - Autorizovany servis Natuzzi'
        ],
        [
            'trigger_event' => 'appointment_assigned',
            'recipient_type' => 'technician',
            'name' => 'SMS - Prirazeni terminu technikovi',
            'description' => 'SMS notifikace pro technika pri prirazeni noveho terminu',
            'subject' => 'Novy termin - WGS Service',
            'template' => 'Dobry den {{technician_name}}, mate novy servisni termin. Datum: {{date}} v {{time}}. Zakaznik: {{customer_name}}, {{address}}. Zakazka c. {{order_id}}. WGS Service - Autorizovany servis Natuzzi'
        ],
        [
            'trigger_event' => 'invitation_send',
            'recipient_type' => 'seller',
            'name' => 'SMS - Pozvanka pro prodejce',
            'description' => 'SMS s pozvankou do systemu pro prodejce',
            'subject' => 'Pozvanka WGS - Prodejce',
            'template' => 'Dobry den, byli jste pozvani jako prodejce do systemu White Glove Service pro spravu servisnich zakazek Natuzzi. Registrujte se na wgs-service.cz. WGS Service - Autorizovany servis Natuzzi'
        ],
        [
            'trigger_event' => 'invitation_send',
            'recipient_type' => 'technician',
            'name' => 'SMS - Pozvanka pro technika',
            'description' => 'SMS s pozvankou do systemu pro technika',
            'subject' => 'Pozvanka WGS - Technik',
            'template' => 'Dobry den, byli jste pozvani jako servisni technik do systemu White Glove Service pro spravu servisnich zakazek Natuzzi. Registrujte se na wgs-service.cz. WGS Service - Autorizovany servis Natuzzi'
        ]
    ];

    // Zjistit ktere SMS sablony uz existuji
    $stmt = $pdo->query("SELECT trigger_event, recipient_type FROM wgs_notifications WHERE type = 'sms'");
    $existujici = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existujici[$row['trigger_event'] . '_' . $row['recipient_type']] = true;
    }

    // Zobrazit prehled
    echo "<h3>Prehled SMS sablon:</h3>";
    echo "<table>";
    echo "<tr><th>Nazev</th><th>Trigger</th><th>Prijemce</th><th>Stav</th></tr>";

    $kPridani = [];
    foreach ($smsSablony as $sablona) {
        $klic = $sablona['trigger_event'] . '_' . $sablona['recipient_type'];
        $existuje = isset($existujici[$klic]);

        $trida = $existuje ? 'exists' : 'new';
        $stav = $existuje ? 'Jiz existuje' : 'BUDE PRIDANO';

        if (!$existuje) {
            $kPridani[] = $sablona;
        }

        echo "<tr class='{$trida}'>";
        echo "<td>" . htmlspecialchars($sablona['name']) . "</td>";
        echo "<td>" . htmlspecialchars($sablona['trigger_event']) . "</td>";
        echo "<td>" . htmlspecialchars($sablona['recipient_type']) . "</td>";
        echo "<td><strong>{$stav}</strong></td>";
        echo "</tr>";
    }
    echo "</table>";

    if (empty($kPridani)) {
        echo "<div class='success'><strong>Vsechny SMS sablony jiz existuji!</strong></div>";
        echo "<a href='/admin.php' class='btn'>Zpet do admin panelu</a>";
        echo "</div></body></html>";
        exit;
    }

    // Provest pridani
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>PROVADIM PRIDANI...</strong></div>";

        // Zjistit nejvyssi ID PRED TRANSAKCI
        $maxIdStmt = $pdo->query("SELECT id FROM wgs_notifications ORDER BY CAST(id AS UNSIGNED) DESC LIMIT 1");
        $maxIdRow = $maxIdStmt->fetch(PDO::FETCH_ASSOC);
        $maxId = $maxIdRow ? (int)$maxIdRow['id'] : 0;

        echo "<div class='info'>Aktualni nejvyssi ID v tabulce: {$maxId}</div>";

        // Zobrazit vsechna ID pro debug
        $allIds = $pdo->query("SELECT id FROM wgs_notifications ORDER BY CAST(id AS UNSIGNED)")->fetchAll(PDO::FETCH_COLUMN);
        echo "<div class='info'>Vsechna ID: " . implode(', ', $allIds) . "</div>";

        $pdo->beginTransaction();

        try {
            $pridano = 0;

            $stmt = $pdo->prepare("
                INSERT INTO wgs_notifications
                (id, name, description, trigger_event, recipient_type, type, subject, template, active, created_at, updated_at)
                VALUES
                (:id, :name, :description, :trigger_event, :recipient_type, 'sms', :subject, :template, 0, NOW(), NOW())
            ");

            foreach ($kPridani as $sablona) {
                $maxId++;
                echo "<div class='info'>Vkladam ID: {$maxId} - " . htmlspecialchars($sablona['name']) . "</div>";
                $stmt->execute([
                    'id' => $maxId,
                    'name' => $sablona['name'],
                    'description' => $sablona['description'],
                    'trigger_event' => $sablona['trigger_event'],
                    'recipient_type' => $sablona['recipient_type'],
                    'subject' => $sablona['subject'],
                    'template' => $sablona['template']
                ]);
                $pridano++;
                echo "<div class='success'>Pridano (ID: {$maxId}): " . htmlspecialchars($sablona['name']) . "</div>";
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>HOTOVO!</strong> Pridano <strong>{$pridano}</strong> novych SMS sablon.";
            echo "<br><br><strong>Pozor:</strong> Nove sablony jsou VYPNUTE. Aktivujte je v admin panelu.";
            echo "</div>";

            echo "<a href='/admin.php' class='btn'>Zpet do admin panelu</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div style='background: #ffe6e6; border: 1px solid #cc0000; padding: 12px; margin: 10px 0;'>";
            echo "<strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        echo "<div class='info'>";
        echo "<strong>Pripraveno k pridani:</strong> " . count($kPridani) . " novych SMS sablon";
        echo "</div>";

        echo "<form method='get' style='margin-top: 20px;'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn'>PRIDAT SMS SABLONY</button>";
        echo "<a href='/admin.php' class='btn' style='background: #666;'>Zrusit</a>";
        echo "</form>";
    }

} catch (Exception $e) {
    echo "<div style='background: #ffe6e6; border: 1px solid #cc0000; padding: 12px;'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
