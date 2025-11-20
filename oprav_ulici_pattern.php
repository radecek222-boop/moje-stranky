<?php
/**
 * Migrace: Oprava patternu pro ulici
 *
 * Tento skript opraví regex pattern pro pole "ulice"
 * v NATUZZI i PHASE protokolech.
 * Můžete jej spustit vícekrát - je idempotentní.
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
    <title>Migrace: Oprava patternu pro ulici</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; }
        h2 { color: #2D5016; margin-top: 30px; }
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
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #dee2e6; }
        code { font-family: 'Courier New', monospace; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; font-weight: 600; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔧 Migrace: Oprava patternu pro ulici</h1>";

    // 1. KONTROLNÍ FÁZE
    echo "<div class='info'><strong>KONTROLA STÁVAJÍCÍCH PATTERNS...</strong></div>";

    $stmt = $pdo->prepare("
        SELECT
            config_id,
            nazev,
            zdroj,
            JSON_EXTRACT(regex_patterns, '$.ulice') AS ulice_pattern
        FROM wgs_pdf_parser_configs
        WHERE zdroj IN ('natuzzi', 'phase')
        ORDER BY zdroj
    ");
    $stmt->execute();
    $konfigurace = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($konfigurace)) {
        echo "<div class='error'>";
        echo "<strong>❌ CHYBA:</strong> Nenalezeny žádné konfigurace pro NATUZZI nebo PHASE!";
        echo "</div>";
        die();
    }

    echo "<h2>📊 Stávající patterns:</h2>";
    echo "<table>";
    echo "<tr><th>Zdroj</th><th>Název</th><th>Pattern pro ulici</th></tr>";
    foreach ($konfigurace as $config) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($config['zdroj']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($config['nazev']) . "</td>";
        echo "<td><code>" . htmlspecialchars($config['ulice_pattern'] ?? 'NENÍ') . "</code></td>";
        echo "</tr>";
    }
    echo "</table>";

    // 2. POKUD JE NASTAVENO ?execute=1, PROVÉST MIGRACI
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            // Nový pattern pro ulici (funguje pro NATUZZI i PHASE)
            $novyPattern = '/Adresa:\s+([^\n]+?)(?:\s+Meno|$)/ui';

            // Opravit NATUZZI
            $stmt = $pdo->prepare("
                UPDATE wgs_pdf_parser_configs
                SET regex_patterns = JSON_SET(
                    regex_patterns,
                    '$.ulice',
                    :pattern
                )
                WHERE zdroj = 'natuzzi'
            ");
            $stmt->execute(['pattern' => $novyPattern]);
            $natuzziUpdated = $stmt->rowCount();

            // Opravit PHASE
            $stmt = $pdo->prepare("
                UPDATE wgs_pdf_parser_configs
                SET regex_patterns = JSON_SET(
                    regex_patterns,
                    '$.ulice',
                    :pattern
                )
                WHERE zdroj = 'phase'
            ");
            $stmt->execute(['pattern' => $novyPattern]);
            $phaseUpdated = $stmt->rowCount();

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ PATTERNS ÚSPĚŠNĚ AKTUALIZOVÁNY!</strong><br><br>";
            echo "NATUZZI: " . ($natuzziUpdated > 0 ? "✅ Aktualizováno" : "⚠️ Žádná změna") . "<br>";
            echo "PHASE: " . ($phaseUpdated > 0 ? "✅ Aktualizováno" : "⚠️ Žádná změna");
            echo "</div>";

            // Zobrazit výsledek
            echo "<h2>📊 Nové patterns:</h2>";
            $stmt = $pdo->prepare("
                SELECT
                    config_id,
                    nazev,
                    zdroj,
                    JSON_EXTRACT(regex_patterns, '$.ulice') AS ulice_pattern
                FROM wgs_pdf_parser_configs
                WHERE zdroj IN ('natuzzi', 'phase')
                ORDER BY zdroj
            ");
            $stmt->execute();
            $vysledky = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "<table>";
            echo "<tr><th>Zdroj</th><th>Název</th><th>Pattern pro ulici</th></tr>";
            foreach ($vysledky as $config) {
                echo "<tr>";
                echo "<td><strong>" . htmlspecialchars($config['zdroj']) . "</strong></td>";
                echo "<td>" . htmlspecialchars($config['nazev']) . "</td>";
                echo "<td><code>" . htmlspecialchars($config['ulice_pattern']) . "</code></td>";
                echo "</tr>";
            }
            echo "</table>";

            echo "<div class='info'>";
            echo "<strong>🧪 TESTOVÁNÍ:</strong><br><br>";
            echo "1. Otevřete <a href='novareklamace.php' target='_blank'>novareklamace.php</a><br>";
            echo "2. Nahrajte <strong>NATUZZI PROTOKOL.pdf</strong><br>";
            echo "3. Zkontrolujte že pole <strong>Ulice</strong> = \"Na Blatech 396\" ✓<br>";
            echo "4. Nahrajte <strong>PHASE PROTOKOL.pdf</strong><br>";
            echo "5. Zkontrolujte že pole <strong>Ulice</strong> = \"Havlíčkovo nábřeží 5357\" ✓";
            echo "</div>";

            echo "<div class='success'>";
            echo "<strong>🎉 MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
            echo "<a href='novareklamace.php' class='btn'>→ Otestovat PDF upload</a>";
            echo "<a href='admin.php' class='btn'>→ Admin panel</a>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>❌ CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        // NÁHLED - CO BUDE PROVEDENO
        echo "<h2>📋 Co bude provedeno:</h2>";
        echo "<div class='info'>";
        echo "<strong>Oprava patternu pro pole 'ulice':</strong><br><br>";
        echo "✅ <strong>NATUZZI</strong>: Pattern aktualizován<br>";
        echo "✅ <strong>PHASE</strong>: Pattern aktualizován<br>";
        echo "</div>";

        echo "<h3>Nový pattern:</h3>";
        echo "<pre><code>/Adresa:\s+([^\n]+?)(?:\s+Meno|$)/ui</code></pre>";

        echo "<div class='info'>";
        echo "<strong>📌 Co tento pattern dělá:</strong><br><br>";
        echo "• Hledá text <strong>PO</strong> \"Adresa:\" (s velkým A)<br>";
        echo "• Zachytí všechno až do dalšího pole nebo konce řádku<br>";
        echo "• Funguje pro <strong>NATUZZI</strong> (český) i <strong>PHASE</strong> (slovenský)<br>";
        echo "• Case-insensitive (díky 'i' flagu)<br>";
        echo "</div>";

        echo "<h3>Příklady zachycení:</h3>";
        echo "<table>";
        echo "<tr><th>Protokol</th><th>RAW text z PDF</th><th>Zachycená hodnota</th></tr>";
        echo "<tr>";
        echo "<td><strong>NATUZZI</strong></td>";
        echo "<td><code>Adresa: Na Blatech 396 Meno...</code></td>";
        echo "<td><strong>Na Blatech 396</strong></td>";
        echo "</tr>";
        echo "<tr>";
        echo "<td><strong>PHASE</strong></td>";
        echo "<td><code>Adresa: Havlíčkovo nábřeží 5357 Meno...</code></td>";
        echo "<td><strong>Havlíčkovo nábřeží 5357</strong></td>";
        echo "</tr>";
        echo "</table>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ DŮLEŽITÉ:</strong> Tento skript je bezpečný - můžete ho spustit vícekrát.";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT MIGRACI</a>";
        echo "<a href='admin.php' class='btn' style='background: #6c757d;'>← Zpět na Admin</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
