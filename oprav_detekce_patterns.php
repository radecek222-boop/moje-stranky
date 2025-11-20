<?php
/**
 * Migrace: Oprava detekčních patterns pro NATUZZI a PHASE
 *
 * Nastaví správné detekční patterns aby systém správně rozpoznal
 * který PDF parser použít.
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Detekční patterns</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Migrace: Detekční patterns</h1>";

    // Zobrazit současný stav
    echo "<h2>📊 Současný stav:</h2>";

    $stmt = $pdo->query("
        SELECT zdroj, nazev, detekce_pattern, priorita, aktivni
        FROM wgs_pdf_parser_configs
        ORDER BY priorita DESC
    ");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Zdroj</th><th>Název</th><th>Detekční pattern</th><th>Priorita</th><th>Aktivní</th></tr>";
    foreach ($configs as $config) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($config['zdroj']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($config['nazev']) . "</td>";
        echo "<td><code>" . htmlspecialchars($config['detekce_pattern'] ?: '(žádný)') . "</code></td>";
        echo "<td>" . $config['priorita'] . "</td>";
        echo "<td>" . ($config['aktivni'] ? '✅' : '❌') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

        $pdo->beginTransaction();

        try {
            // NATUZZI - detekce podle loga a českého textu
            $stmt = $pdo->prepare("
                UPDATE wgs_pdf_parser_configs
                SET
                    detekce_pattern = 'NATUZZI|EDITIONS|THE NAME OF COMFORT',
                    priorita = 100
                WHERE zdroj = 'natuzzi'
            ");
            $stmt->execute();

            echo "<div class='success'>";
            echo "✅ NATUZZI detekční pattern aktualizován<br>";
            echo "Pattern: <code>NATUZZI|EDITIONS|THE NAME OF COMFORT</code><br>";
            echo "Priorita: <strong>100</strong> (nejvyšší)";
            echo "</div>";

            // PHASE - detekce podle loga a slovenského textu
            $stmt = $pdo->prepare("
                UPDATE wgs_pdf_parser_configs
                SET
                    detekce_pattern = 'pohodlie.*phase|sedenie.*spanie|Reklamačný list|reklamácie',
                    priorita = 90
                WHERE zdroj = 'phase'
            ");
            $stmt->execute();

            echo "<div class='success'>";
            echo "✅ PHASE detekční pattern aktualizován<br>";
            echo "Pattern: <code>pohodlie.*phase|sedenie.*spanie|Reklamačný list|reklamácie</code><br>";
            echo "Priorita: <strong>90</strong> (vysoká)";
            echo "</div>";

            $pdo->commit();

            // Zobrazit nový stav
            echo "<h2>📊 Nový stav:</h2>";

            $stmt = $pdo->query("
                SELECT zdroj, nazev, detekce_pattern, priorita
                FROM wgs_pdf_parser_configs
                ORDER BY priorita DESC
            ");
            $newConfigs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<table>";
            echo "<tr><th>Zdroj</th><th>Název</th><th>Detekční pattern</th><th>Priorita</th></tr>";
            foreach ($newConfigs as $config) {
                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($config['zdroj']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($config['nazev']) . "</td>";
                echo "<td><code>" . htmlspecialchars($config['detekce_pattern']) . "</code></td>";
                echo "<td>" . $config['priorita'] . "</td>";
                echo "</tr>";
            }
            echo "</table>";

            echo "<div class='info'>";
            echo "<strong>🧪 JAK TO FUNGUJE:</strong><br><br>";
            echo "<strong>NATUZZI PDF:</strong><br>";
            echo "Pokud text obsahuje \"NATUZZI\" NEBO \"EDITIONS\" NEBO \"THE NAME OF COMFORT\"<br>";
            echo "→ Použije se NATUZZI parser (priorita 100)<br><br>";
            echo "<strong>PHASE PDF:</strong><br>";
            echo "Pokud text obsahuje \"pohodlie\" + \"phase\" NEBO \"Reklamačný list\" NEBO \"reklamácie\"<br>";
            echo "→ Použije se PHASE parser (priorita 90)";
            echo "</div>";

            echo "<div class='success'>";
            echo "<strong>🎉 MIGRACE ÚSPĚŠNĚ DOKONČENA!</strong><br><br>";
            echo "<a href='live_test_pdf.html' class='btn'>🔍 Otestovat NATUZZI PDF</a>";
            echo "<a href='live_test_pdf.html' class='btn'>🔍 Otestovat PHASE PDF</a>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='info'>";
        echo "<strong>📋 Co bude provedeno:</strong><br><br>";
        echo "1. <strong>NATUZZI</strong>: Detekční pattern <code>NATUZZI|EDITIONS|THE NAME OF COMFORT</code><br>";
        echo "2. <strong>PHASE</strong>: Detekční pattern <code>pohodlie.*phase|sedenie.*spanie|Reklamačný list|reklamácie</code><br>";
        echo "3. Nastavení priority: NATUZZI=100, PHASE=90";
        echo "</div>";

        echo "<h3>🔍 Jak funguje detekce:</h3>";

        echo "<h4>NATUZZI PDF obsahuje:</h4>";
        echo "<ul>";
        echo "<li>Logo text: <code>NATUZZI EDITIONS</code></li>";
        echo "<li>Slogan: <code>THE NAME OF COMFORT SINCE 1959</code></li>";
        echo "<li>Hlavička: <code>Reklamační list</code> (česky)</li>";
        echo "</ul>";

        echo "<h4>PHASE PDF obsahuje:</h4>";
        echo "<ul>";
        echo "<li>Logo text: <code>pohodlie a phase</code></li>";
        echo "<li>Slogan: <code>sedenie a spanie</code></li>";
        echo "<li>Hlavička: <code>Reklamačný list</code> (slovensky!)</li>";
        echo "<li>Termíny: <code>reklamácie</code>, <code>Dátum podania</code>, atd. (slovensky)</li>";
        echo "</ul>";

        echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT OPRAVU</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
