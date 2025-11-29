<?php
/**
 * Migrace: Smazání duplicitních notifikačních šablon
 *
 * Tento skript najde a smaže duplicitní šablony v tabulce wgs_notifications.
 * Ponechá vždy pouze jednu (nejnovější) šablonu pro každý trigger_event + type + recipient_type.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Smazání duplicitních šablon</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        .success { background: #f0f0f0; border: 1px solid #333;
                   color: #333; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #e9e9e9; border: 1px solid #999;
                color: #333; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #555; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .duplicate { background: #ffe6e6 !important; }
        .keep { background: #e6ffe6 !important; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Smazání duplicitních notifikačních šablon</h1>";

    // Najít všechny duplicity - šablony se stejným názvem
    $stmt = $pdo->query("
        SELECT name, COUNT(*) as pocet
        FROM wgs_notifications
        GROUP BY name
        HAVING COUNT(*) > 1
        ORDER BY name
    ");
    $duplicity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($duplicity)) {
        echo "<div class='success'><strong>Žádné duplicity nenalezeny!</strong> Databáze je v pořádku.</div>";
        echo "<a href='/admin.php' class='btn'>Zpět do admin panelu</a>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='info'><strong>Nalezeno " . count($duplicity) . " duplicitních skupin:</strong></div>";

    // Zobrazit duplicity
    echo "<table>";
    echo "<tr><th>Název šablony</th><th>Počet duplicit</th></tr>";
    foreach ($duplicity as $dup) {
        echo "<tr><td>" . htmlspecialchars($dup['name']) . "</td><td>" . $dup['pocet'] . "x</td></tr>";
    }
    echo "</table>";

    // Detailní výpis - které záznamy budou smazány
    echo "<h3>Detailní přehled:</h3>";

    $keSmazani = [];

    foreach ($duplicity as $dup) {
        $stmt = $pdo->prepare("
            SELECT id, name, trigger_event, type, recipient_type, active, updated_at
            FROM wgs_notifications
            WHERE name = :name
            ORDER BY updated_at DESC, id DESC
        ");
        $stmt->execute(['name' => $dup['name']]);
        $zaznamy = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h4>" . htmlspecialchars($dup['name']) . "</h4>";
        echo "<table>";
        echo "<tr><th>ID</th><th>Trigger</th><th>Typ</th><th>Příjemce</th><th>Aktivní</th><th>Aktualizováno</th><th>Akce</th></tr>";

        $prvni = true;
        foreach ($zaznamy as $z) {
            $trida = $prvni ? 'keep' : 'duplicate';
            $akce = $prvni ? '<strong>PONECHAT</strong>' : 'SMAZAT';

            if (!$prvni) {
                $keSmazani[] = $z['id'];
            }

            echo "<tr class='{$trida}'>";
            echo "<td>" . $z['id'] . "</td>";
            echo "<td>" . htmlspecialchars($z['trigger_event']) . "</td>";
            echo "<td>" . $z['type'] . "</td>";
            echo "<td>" . $z['recipient_type'] . "</td>";
            echo "<td>" . ($z['active'] ? 'Ano' : 'Ne') . "</td>";
            echo "<td>" . ($z['updated_at'] ?? '-') . "</td>";
            echo "<td>" . $akce . "</td>";
            echo "</tr>";

            $prvni = false;
        }
        echo "</table>";
    }

    // Pokud je nastaveno execute=1, provést smazání
    if (isset($_GET['execute']) && $_GET['execute'] === '1' && !empty($keSmazani)) {
        echo "<div class='info'><strong>PROVÁDÍM SMAZÁNÍ...</strong></div>";

        $pdo->beginTransaction();

        try {
            $smazano = 0;
            foreach ($keSmazani as $id) {
                $stmt = $pdo->prepare("DELETE FROM wgs_notifications WHERE id = :id");
                $stmt->execute(['id' => $id]);
                $smazano++;
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>HOTOVO!</strong> Smazáno <strong>{$smazano}</strong> duplicitních šablon.";
            echo "</div>";

            echo "<a href='/admin.php' class='btn'>Zpět do admin panelu</a>";
            echo "<a href='/smaz_duplicitni_sablony.php' class='btn'>Zkontrolovat znovu</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else if (!empty($keSmazani)) {
        // Náhled - ukázat co bude smazáno
        echo "<div class='warning'>";
        echo "<strong>Připraveno ke smazání:</strong> " . count($keSmazani) . " duplicitních záznamů (ID: " . implode(', ', $keSmazani) . ")";
        echo "</div>";

        echo "<form method='get' style='margin-top: 20px;'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn'>SMAZAT DUPLICITY</button>";
        echo "<a href='/admin.php' class='btn' style='background: #666;'>Zrušit</a>";
        echo "</form>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
