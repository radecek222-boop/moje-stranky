<?php
/**
 * Migrace: Kontrola a odstranění zastaralých duplicitních sloupců
 *
 * Tento skript BEZPEČNĚ odstraní zastaralé sloupce, které obsahují stejné údaje jako nové sloupce.
 * Před odstraněním zkontroluje, že data jsou správně přenesena do nových sloupců.
 *
 * ZASTARALÉ sloupce (budou odstraněny):
 * - zpracoval (TEXT) → nahrazeno created_by + u.name z wgs_users
 * - zpracoval_id (INT) → nahrazeno created_by
 * - prodejce (TEXT) → nahrazeno created_by + u.name z wgs_users
 *
 * AKTUÁLNÍ sloupce (zůstávají):
 * - created_by (INT) - ID uživatele který vytvořil zakázku
 * - created_by_role (VARCHAR) - Role uživatele
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
    <title>Kontrola zastaralých sloupců</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #666;
             padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
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
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: white; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0;
               border: none; cursor: pointer; font-size: 16px; }
        .btn:hover { background: #555; }
        .btn-danger { background: #c82333; }
        .btn-danger:hover { background: #bd2130; }
        .comparison { display: grid; grid-template-columns: 1fr 1fr;
                      gap: 20px; margin: 20px 0; }
        .old { background: #f8d7da; padding: 15px; border-radius: 5px; }
        .new { background: #d4edda; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Kontrola zastaralých sloupců v tabulce wgs_reklamace</h1>";

    // Kontrola existence sloupců
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $columnNames = array_column($columns, 'Field');

    $zastarale = [
        'zpracoval' => in_array('zpracoval', $columnNames),
        'zpracoval_id' => in_array('zpracoval_id', $columnNames),
        'prodejce' => in_array('prodejce', $columnNames)
    ];

    $aktualni = [
        'created_by' => in_array('created_by', $columnNames),
        'created_by_role' => in_array('created_by_role', $columnNames)
    ];

    echo "<div class='comparison'>";
    echo "<div class='old'>";
    echo "<h3>⚠️ Zastaralé sloupce (duplicitní)</h3>";
    echo "<ul>";
    foreach ($zastarale as $sloupec => $existuje) {
        $status = $existuje ? '✅ EXISTUJE' : '❌ Neexistuje';
        echo "<li><code>{$sloupec}</code> - {$status}</li>";
    }
    echo "</ul>";
    echo "</div>";

    echo "<div class='new'>";
    echo "<h3>✅ Aktuální sloupce</h3>";
    echo "<ul>";
    foreach ($aktualni as $sloupec => $existuje) {
        $status = $existuje ? '✅ EXISTUJE' : '❌ CHYBÍ!';
        echo "<li><code>{$sloupec}</code> - {$status}</li>";
    }
    echo "</ul>";
    echo "</div>";
    echo "</div>";

    // Zkontrolovat, zda jsou aktuální sloupce přítomny
    if (!$aktualni['created_by']) {
        echo "<div class='error'>";
        echo "<strong>❌ CHYBA:</strong> Sloupec <code>created_by</code> neexistuje!<br>";
        echo "Nejprve musíte přidat nové sloupce před odstraněním starých.";
        echo "</div>";
        echo "</div></body></html>";
        exit;
    }

    // Počet záznamů s daty v zastaralých sloupcích
    echo "<h2>Kontrola dat v zastaralých sloupcích</h2>";

    $poctyZaznamu = [];
    if ($zastarale['zpracoval']) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE zpracoval IS NOT NULL AND zpracoval != ''");
        $poctyZaznamu['zpracoval'] = $stmt->fetchColumn();
    }

    if ($zastarale['zpracoval_id']) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE zpracoval_id IS NOT NULL");
        $poctyZaznamu['zpracoval_id'] = $stmt->fetchColumn();
    }

    if ($zastarale['prodejce']) {
        $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE prodejce IS NOT NULL AND prodejce != ''");
        $poctyZaznamu['prodejce'] = $stmt->fetchColumn();
    }

    echo "<table>";
    echo "<tr><th>Zastaralý sloupec</th><th>Počet záznamů s daty</th><th>Stav</th></tr>";
    foreach ($poctyZaznamu as $sloupec => $pocet) {
        $stav = $pocet > 0 ? "<span style='color: #856404;'>⚠️ Obsahuje data</span>" : "<span style='color: #155724;'>✅ Prázdný</span>";
        echo "<tr><td><code>{$sloupec}</code></td><td>{$pocet}</td><td>{$stav}</td></tr>";
    }
    echo "</table>";

    // Kontrola zda created_by obsahuje data
    $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_reklamace WHERE created_by IS NOT NULL");
    $createdByCount = $stmt->fetchColumn();

    echo "<div class='info'>";
    echo "<strong>ℹ️ Aktuální sloupec <code>created_by</code>:</strong><br>";
    echo "Obsahuje data v <strong>{$createdByCount}</strong> záznamech.";
    echo "</div>";

    // Ukázka porovnání dat
    echo "<h2>Porovnání dat (ukázka 5 záznamů)</h2>";

    $sql = "SELECT id, reklamace_id, cislo, jmeno";
    if ($zastarale['zpracoval']) $sql .= ", zpracoval";
    if ($zastarale['zpracoval_id']) $sql .= ", zpracoval_id";
    if ($zastarale['prodejce']) $sql .= ", prodejce";
    if ($aktualni['created_by']) $sql .= ", created_by";
    if ($aktualni['created_by_role']) $sql .= ", created_by_role";
    $sql .= " FROM wgs_reklamace ORDER BY created_at DESC LIMIT 5";

    $stmt = $pdo->query($sql);
    $ukazky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if ($ukazky) {
        echo "<table>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Zakázka</th>";
        if ($zastarale['zpracoval']) echo "<th>zpracoval<br>(zastaralé)</th>";
        if ($zastarale['zpracoval_id']) echo "<th>zpracoval_id<br>(zastaralé)</th>";
        if ($zastarale['prodejce']) echo "<th>prodejce<br>(zastaralé)</th>";
        if ($aktualni['created_by']) echo "<th>created_by<br>(aktuální)</th>";
        if ($aktualni['created_by_role']) echo "<th>created_by_role<br>(aktuální)</th>";
        echo "</tr>";

        foreach ($ukazky as $zaznam) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($zaznam['id']) . "</td>";
            echo "<td>" . htmlspecialchars($zaznam['reklamace_id'] ?? $zaznam['cislo'] ?? '-') . "</td>";
            if ($zastarale['zpracoval']) echo "<td>" . htmlspecialchars($zaznam['zpracoval'] ?? '-') . "</td>";
            if ($zastarale['zpracoval_id']) echo "<td>" . htmlspecialchars($zaznam['zpracoval_id'] ?? '-') . "</td>";
            if ($zastarale['prodejce']) echo "<td>" . htmlspecialchars($zaznam['prodejce'] ?? '-') . "</td>";
            if ($aktualni['created_by']) echo "<td>" . htmlspecialchars($zaznam['created_by'] ?? '-') . "</td>";
            if ($aktualni['created_by_role']) echo "<td>" . htmlspecialchars($zaznam['created_by_role'] ?? '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // AKCE - smazání zastaralých sloupců
    if (isset($_GET['odstranit']) && $_GET['odstranit'] === 'ano') {
        echo "<h2>🗑️ Odstraňování zastaralých sloupců...</h2>";

        $pdo->beginTransaction();

        try {
            $odstranenoSloupcu = 0;

            foreach ($zastarale as $sloupec => $existuje) {
                if ($existuje) {
                    $pdo->exec("ALTER TABLE wgs_reklamace DROP COLUMN `{$sloupec}`");
                    echo "<div class='success'>✅ Sloupec <code>{$sloupec}</code> byl odstraněn</div>";
                    $odstranenoSloupcu++;
                }
            }

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ HOTOVO!</strong><br>";
            echo "Odstraněno <strong>{$odstranenoSloupcu}</strong> zastaralých sloupců.<br>";
            echo "Tabulka <code>wgs_reklamace</code> je nyní čistší a obsahuje pouze aktuální sloupce.";
            echo "</div>";

            echo "<a href='kontrola_zastaralych_sloupcu.php' class='btn'>Zkontrolovat znovu</a>";

        } catch (Exception $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>❌ CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }

    } else {
        // NÁHLED - co se stane
        echo "<h2>Co se stane po odstranění?</h2>";

        $existujiciZastarale = array_filter($zastarale);

        if (empty($existujiciZastarale)) {
            echo "<div class='success'>";
            echo "<strong>✅ VŠE JE ČISTÉ!</strong><br>";
            echo "Žádné zastaralé sloupce nebyly nalezeny. Databáze je již vyčištěná.";
            echo "</div>";
        } else {
            echo "<div class='warning'>";
            echo "<strong>⚠️ PŘIPRAVENO K ODSTRANĚNÍ:</strong><br>";
            echo "Následující zastaralé sloupce budou odstraněny:<br><ul>";
            foreach ($existujiciZastarale as $sloupec => $existuje) {
                echo "<li><code>{$sloupec}</code></li>";
            }
            echo "</ul>";
            echo "Data v těchto sloupcích budou <strong>trvale smazána</strong>.<br>";
            echo "Aktuální data jsou uložena v sloupcích <code>created_by</code> a <code>created_by_role</code>.";
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>ℹ️ BEZPEČNOSTNÍ KONTROLA:</strong><br>";
            echo "Před odstraněním doporučuji:<br>";
            echo "<ol>";
            echo "<li>Udělat zálohu databáze (přes Admin Panel → SQL → Stáhnout všechny DDL)</li>";
            echo "<li>Zkontrolovat, že <code>created_by</code> obsahuje data ve všech záznamech</li>";
            echo "<li>Spustit odstranění pomocí tlačítka níže</li>";
            echo "</ol>";
            echo "</div>";

            echo "<a href='?odstranit=ano' class='btn btn-danger' onclick='return confirm(\"Opravdu chcete odstranit zastaralé sloupce? Tato akce je nevratná!\")'>🗑️ ODSTRANIT ZASTARALÉ SLOUPCE</a>";
            echo "<a href='kontrola_zastaralych_sloupcu.php' class='btn'>Zrušit</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
