<?php
/**
 * Migrace: Přidání SMS šablon do databáze
 *
 * SMS se odesílají pomocí sms: protokolu (otevře nativní aplikaci na iPhone/Android)
 * Tento skript přidá editovatelné SMS šablony do wgs_notifications
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: SMS sablony</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        .success { background: #e5e5e5; border: 1px solid #999;
                   color: #333; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f0f0f0; border: 1px solid #666;
                 color: #333; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .info { background: #f9f9f9; border: 1px solid #ccc;
                color: #333; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #555; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px;
              overflow-x: auto; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #333; color: white; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: SMS sablony</h1>";

    // SMS šablony pro přidání
    $smsSablony = [
        [
            'name' => 'SMS - Pokus o kontakt',
            'description' => 'SMS zprava kdyz se technik pokusil kontaktovat zakaznika',
            'trigger_event' => 'contact_attempt',
            'recipient_type' => 'customer',
            'type' => 'sms',
            'subject' => 'Pokus o kontakt - WGS Service',
            'template' => 'Dobry den {{customer_name}}, kontaktujeme Vas v zastoupeni Natuzzi ohledne servisni zakazky c. {{order_id}}. Nepodarilo se nam Vas zastihnout. Zavolejte prosim zpet {{technician_name}} na tel. {{technician_phone}}. Dekujeme, WGS Service',
            'active' => 1
        ],
        [
            'name' => 'SMS - Potvrzeni terminu',
            'description' => 'SMS zprava s potvrzenim domluveneho terminu navstevy',
            'trigger_event' => 'appointment_confirmed',
            'recipient_type' => 'customer',
            'type' => 'sms',
            'subject' => 'Termin navstevy - WGS Service',
            'template' => 'Dobry den {{customer_name}}, potvrzujeme termin navstevy na {{date}} v {{time}}. Adresa: {{address}}. Technik: {{technician_name}}, tel. {{technician_phone}}. Prosime, pripravte nabytek - odstrante z nej osobni veci a luzkoviny. WGS Service',
            'active' => 1
        ],
        [
            'name' => 'SMS - Pripominka terminu',
            'description' => 'SMS pripominka den pred terminem navstevy',
            'trigger_event' => 'appointment_reminder',
            'recipient_type' => 'customer',
            'type' => 'sms',
            'subject' => 'Pripominka - WGS Service',
            'template' => 'Dobry den {{customer_name}}, pripominame zitrejsi navstevu technika WGS Service. Termin: {{date}} v {{time}}. Prosime, pripravte nabytek - odstrante osobni veci a luzkoviny. V pripade zmeny volejte {{technician_phone}}. Dekujeme.',
            'active' => 1
        ],
        [
            'name' => 'SMS - Zakazka dokoncena',
            'description' => 'SMS zprava po dokonceni zakazky',
            'trigger_event' => 'complaint_completed',
            'recipient_type' => 'customer',
            'type' => 'sms',
            'subject' => 'Zakazka dokoncena - WGS Service',
            'template' => 'Dobry den {{customer_name}}, vase zakazka c. {{order_id}} byla uspesne dokoncena. Dekujeme za duveru. WGS Service - Autorizovany servis Natuzzi',
            'active' => 1
        ]
    ];

    // Kontrolní fáze
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    // Zjistit existující SMS šablony
    $stmt = $pdo->query("SELECT name, type FROM wgs_notifications WHERE type = 'sms'");
    $existujici = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<h3>Existujici SMS sablony:</h3>";
    if (count($existujici) > 0) {
        echo "<ul>";
        foreach ($existujici as $nazev) {
            echo "<li>" . htmlspecialchars($nazev) . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p><em>Zadne SMS sablony zatim neexistuji</em></p>";
    }

    echo "<h3>SMS sablony k pridani:</h3>";
    echo "<table><tr><th>Nazev</th><th>Trigger</th><th>Status</th></tr>";
    foreach ($smsSablony as $sablona) {
        $existuje = in_array($sablona['name'], $existujici);
        $status = $existuje ? 'Uz existuje' : 'Bude pridana';
        echo "<tr><td>" . htmlspecialchars($sablona['name']) . "</td><td>" . htmlspecialchars($sablona['trigger_event']) . "</td><td>" . $status . "</td></tr>";
    }
    echo "</table>";

    // Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUSTIM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            $pridano = 0;
            $preskoceno = 0;

            // Zjistit aktualni max ID
            $stmtMaxId = $pdo->query("SELECT COALESCE(MAX(id), 0) as max_id FROM wgs_notifications");
            $maxIdRow = $stmtMaxId->fetch(PDO::FETCH_ASSOC);
            $nextId = (int)$maxIdRow['max_id'] + 1;

            foreach ($smsSablony as $sablona) {
                // Kontrola jestli už existuje
                $stmt = $pdo->prepare("SELECT id FROM wgs_notifications WHERE name = :name");
                $stmt->execute(['name' => $sablona['name']]);

                if ($stmt->fetch()) {
                    $preskoceno++;
                    echo "<div class='info'>Preskakuji (uz existuje): " . htmlspecialchars($sablona['name']) . "</div>";
                    continue;
                }

                // Vložit novou šablonu s explicitním ID
                $stmt = $pdo->prepare("
                    INSERT INTO wgs_notifications
                    (id, name, description, trigger_event, recipient_type, type, subject, template, active, created_at, updated_at)
                    VALUES
                    (:id, :name, :description, :trigger_event, :recipient_type, :type, :subject, :template, :active, NOW(), NOW())
                ");

                $stmt->execute([
                    'id' => $nextId,
                    'name' => $sablona['name'],
                    'description' => $sablona['description'],
                    'trigger_event' => $sablona['trigger_event'],
                    'recipient_type' => $sablona['recipient_type'],
                    'type' => $sablona['type'],
                    'subject' => $sablona['subject'],
                    'template' => $sablona['template'],
                    'active' => $sablona['active']
                ]);

                $nextId++;
                $pridano++;
                echo "<div class='success'>Pridana sablona: " . htmlspecialchars($sablona['name']) . "</div>";
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>MIGRACE USPESNE DOKONCENA</strong><br>";
            echo "Pridano: {$pridano} sablon<br>";
            echo "Preskoceno: {$preskoceno} sablon";
            echo "</div>";

            echo "<a href='/admin.php' class='btn'>Zpet do admin panelu</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        // Náhled - tlačítko pro spuštění
        echo "<br><a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='/admin.php' class='btn' style='background: #666;'>Zrusit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
