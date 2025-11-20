<?php
/**
 * Migrace: FINÁLNÍ oprava ulice - mapping + pattern
 *
 * Opravuje DVA problémy:
 * 1. Pole_mapping má klíč "adresa" místo "ulice"
 * 2. Pattern zachycuje moc textu (až do konce)
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Finální oprava ulice</title>
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
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔧 Migrace: Finální oprava ulice</h1>";

    echo "<div class='error'>";
    echo "<strong>🐛 NALEZENY DVA PROBLÉMY:</strong><br><br>";
    echo "1. V NATUZZI mapping je klíč <code>\"adresa\": \"ulice\"</code> místo <code>\"ulice\": \"ulice\"</code><br>";
    echo "2. Pattern zachycuje moc textu (celý zbytek řádku)<br>";
    echo "</div>";

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

        $pdo->beginTransaction();

        try {
            // OPRAVA PRO NATUZZI
            echo "<h2>📌 NATUZZI Protokol</h2>";

            // Nový SPRÁVNÝ pattern - zastaví na prvním slově začínajícím velkým písmenem
            $novyPattern = '/Adresa:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][^\s]+(?:\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ]?[^\s]+)*\s+\d+)/ui';

            // Načíst současný mapping
            $stmt = $pdo->prepare("SELECT pole_mapping FROM wgs_pdf_parser_configs WHERE zdroj = 'natuzzi'");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $mapping = json_decode($row['pole_mapping'], true);

            // Odstranit klíč "adresa" a přidat klíč "ulice"
            unset($mapping['adresa']);
            $mapping['ulice'] = 'ulice';

            // Aktualizovat pattern i mapping
            $stmt = $pdo->prepare("
                UPDATE wgs_pdf_parser_configs
                SET
                    regex_patterns = JSON_SET(regex_patterns, '$.ulice', :pattern),
                    pole_mapping = :mapping
                WHERE zdroj = 'natuzzi'
            ");
            $stmt->execute([
                'pattern' => $novyPattern,
                'mapping' => json_encode($mapping, JSON_UNESCAPED_UNICODE)
            ]);

            echo "<div class='success'>";
            echo "✅ Pattern aktualizován<br>";
            echo "✅ Mapping: <code>\"adresa\"</code> → <code>\"ulice\"</code>";
            echo "</div>";

            // OPRAVA PRO PHASE
            echo "<h2>📌 PHASE Protokol</h2>";

            // Pro PHASE je stejný pattern OK
            $stmt = $pdo->prepare("
                UPDATE wgs_pdf_parser_configs
                SET regex_patterns = JSON_SET(regex_patterns, '$.ulice', :pattern)
                WHERE zdroj = 'phase'
            ");
            $stmt->execute(['pattern' => $novyPattern]);

            echo "<div class='success'>✅ Pattern aktualizován</div>";

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>🎉 OPRAVA ÚSPĚŠNĚ DOKONČENA!</strong><br><br>";
            echo "Nyní otestujte nahrání PDF na <code>novareklamace.php</code>";
            echo "</div>";

            echo "<a href='test_pdf_parsing.php' class='btn'>🔍 Znovu otestovat parsing</a>";
            echo "<a href='novareklamace.php' class='btn'>📄 Otestovat PDF upload</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='warning'>";
        echo "<strong>📋 Co bude provedeno:</strong><br><br>";
        echo "1. <strong>NATUZZI:</strong> Změna mappingu <code>\"adresa\"</code> → <code>\"ulice\"</code><br>";
        echo "2. <strong>NATUZZI + PHASE:</strong> Nový pattern pro ulici<br>";
        echo "</div>";

        echo "<h3>Nový pattern:</h3>";
        echo "<pre><code>/Adresa:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][^\s]+(?:\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ]?[^\s]+)*\s+\d+)/ui</code></pre>";

        echo "<div class='info'>";
        echo "<strong>Co tento pattern dělá:</strong><br><br>";
        echo "• Hledá <code>Adresa:</code> následované mezerou<br>";
        echo "• Zachytí slovo začínající velkým písmenem<br>";
        echo "• Pak libovolný počet dalších slov<br>";
        echo "• Končí když najde číslo<br>";
        echo "• Pro \"Na Blatech 396\" zachytí přesně to: <strong>Na Blatech 396</strong>";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT OPRAVU</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
