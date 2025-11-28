<?php
/**
 * Migrace: Aktualizace sablon - pridani info o priprave nabytku
 *
 * Prida do email a SMS sablon informaci pro zakaznika,
 * aby pred prichodem technika odstranil osobni veci a luzkoviny z nabytku.
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
    <title>Migrace: Aktualizace sablon - priprava nabytku</title>
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
              overflow-x: auto; font-size: 12px; white-space: pre-wrap; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #333; color: white; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Pridani info o priprave nabytku</h1>";

    // Text ktery se ma pridat do emailovych sablon
    $emailDoplnekCZ = "\n\nDULEZITE: Prosime, pripravte nabytek pred prichodem technika. Odstrante z nej vsechny osobni veci a luzkoviny, aby technik mohl bez prekazek provest servisni zasah.";

    // Najit sablony ktere se maji aktualizovat
    $stmt = $pdo->query("
        SELECT id, name, trigger_event, type, template
        FROM wgs_notifications
        WHERE trigger_event IN ('appointment_confirmed', 'appointment_reminder')
        AND type = 'email'
        AND active = 1
        ORDER BY trigger_event, type
    ");
    $sablony = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    if (count($sablony) === 0) {
        echo "<div class='info'>Zadne emailove sablony pro appointment_confirmed nebo appointment_reminder nebyly nalezeny.</div>";
        echo "<a href='/admin.php' class='btn'>Zpet do admin panelu</a>";
        echo "</div></body></html>";
        exit;
    }

    echo "<h3>Nalezene sablony k aktualizaci:</h3>";
    echo "<table><tr><th>ID</th><th>Nazev</th><th>Trigger</th><th>Typ</th><th>Status</th></tr>";

    foreach ($sablony as $sablona) {
        $uzObsahuje = strpos($sablona['template'], 'osobni veci') !== false ||
                      strpos($sablona['template'], 'luzkoviny') !== false;
        $status = $uzObsahuje ? 'Uz obsahuje info' : 'Bude aktualizovana';

        echo "<tr>";
        echo "<td>" . htmlspecialchars($sablona['id']) . "</td>";
        echo "<td>" . htmlspecialchars($sablona['name']) . "</td>";
        echo "<td>" . htmlspecialchars($sablona['trigger_event']) . "</td>";
        echo "<td>" . htmlspecialchars($sablona['type']) . "</td>";
        echo "<td>" . $status . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h3>Text ktery bude pridan:</h3>";
    echo "<pre>" . htmlspecialchars($emailDoplnekCZ) . "</pre>";

    // Pokud je nastaveno ?execute=1, provest migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUSTIM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            $aktualizovano = 0;
            $preskoceno = 0;

            foreach ($sablony as $sablona) {
                // Kontrola jestli uz obsahuje info
                $uzObsahuje = strpos($sablona['template'], 'osobni veci') !== false ||
                              strpos($sablona['template'], 'luzkoviny') !== false;

                if ($uzObsahuje) {
                    $preskoceno++;
                    echo "<div class='info'>Preskakuji (uz obsahuje): " . htmlspecialchars($sablona['name']) . "</div>";
                    continue;
                }

                // Najit vhodne misto pro vlozeni textu (pred "S pozdravem" nebo na konec)
                $template = $sablona['template'];
                $pozice = strpos($template, 'S pozdravem');
                if ($pozice === false) {
                    $pozice = strpos($template, 'S pozdravom'); // SK verze
                }

                if ($pozice !== false) {
                    // Vlozit pred "S pozdravem"
                    $novyTemplate = substr($template, 0, $pozice) . $emailDoplnekCZ . "\n\n" . substr($template, $pozice);
                } else {
                    // Pridat na konec
                    $novyTemplate = $template . $emailDoplnekCZ;
                }

                // Aktualizovat sablonu
                $stmtUpdate = $pdo->prepare("
                    UPDATE wgs_notifications
                    SET template = :template, updated_at = NOW()
                    WHERE id = :id
                ");
                $stmtUpdate->execute([
                    'template' => $novyTemplate,
                    'id' => $sablona['id']
                ]);

                $aktualizovano++;
                echo "<div class='success'>Aktualizovana sablona: " . htmlspecialchars($sablona['name']) . "</div>";
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>MIGRACE USPESNE DOKONCENA</strong><br>";
            echo "Aktualizovano: {$aktualizovano} sablon<br>";
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
        // Nahled - tlacitko pro spusteni
        echo "<br><a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        echo "<a href='/admin.php' class='btn' style='background: #666;'>Zrusit</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
