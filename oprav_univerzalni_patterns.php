<?php
/**
 * Migrace: Univerzální patterns pro všechny NATUZZI PDF
 *
 * Opravuje DVA problémy zjištěné z NCM-NATUZZI.pdf:
 * 1. Pattern pro číslo reklamace je příliš specifický (jen NCE25)
 * 2. Pattern pro ulici najde špatnou adresu (zákazníka místo místa reklamace)
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Univerzální patterns</title>
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
              overflow-x: auto; border: 1px solid #dee2e6; font-size: 0.85rem; }
        code { font-family: 'Courier New', monospace; background: #f8f9fa;
               padding: 2px 6px; border-radius: 3px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔧 Migrace: Univerzální patterns</h1>";

    echo "<div class='error'>";
    echo "<strong>🐛 NALEZENY PROBLÉMY v NCM-NATUZZI.pdf:</strong><br><br>";
    echo "1. Pattern pro číslo reklamace hledá jen <code>NCE25</code> ale v PDF je <code>NCM23</code><br>";
    echo "2. Pattern pro ulici najde PRVNÍ adresu (zákazníka) místo DRUHÉ (místa reklamace)<br>";
    echo "</div>";

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

        $pdo->beginTransaction();

        try {
            // NOVÉ UNIVERZÁLNÍ PATTERNS
            $novePatterns = [
                // Číslo reklamace - UNIVERZÁLNÍ (akceptuje NCE25, NCM23, atd.)
                'cislo_reklamace' => '/Čislo reklamace:\s+[A-Z]{3}\d{2}-\d+-\d+\s+([A-Z0-9\-\/]+)/ui',

                // Ulice - hledat v sekci "Místo reklamace" (ne "Zákazník")
                'ulice' => '/Místo reklamace.*?Adresa:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][^\n]+?)\s+(?:Jméno společnosti|Email)/uis'
            ];

            // Aktualizovat NATUZZI patterns
            $stmt = $pdo->prepare("SELECT regex_patterns FROM wgs_pdf_parser_configs WHERE zdroj = 'natuzzi'");
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $patterns = json_decode($row['regex_patterns'], true);

            // Nahradit problematické patterns
            $patterns['cislo_reklamace'] = $novePatterns['cislo_reklamace'];
            $patterns['ulice'] = $novePatterns['ulice'];

            $stmt = $pdo->prepare("
                UPDATE wgs_pdf_parser_configs
                SET regex_patterns = :patterns
                WHERE zdroj = 'natuzzi'
            ");
            $stmt->execute([
                'patterns' => json_encode($patterns, JSON_UNESCAPED_UNICODE)
            ]);

            echo "<div class='success'>";
            echo "✅ NATUZZI patterns aktualizovány!<br><br>";
            echo "<strong>Změněné patterns:</strong><br>";
            echo "• <code>cislo_reklamace</code>: Nyní akceptuje jakýkoliv prefix (NCE, NCM, ...)<br>";
            echo "• <code>ulice</code>: Nyní hledá adresu v sekci \"Místo reklamace\"";
            echo "</div>";

            $pdo->commit();

            // Zobrazit výsledek
            echo "<h2>📊 Nové patterns:</h2>";
            echo "<table>";
            echo "<tr><th>Pole</th><th>Pattern</th><th>Co najde</th></tr>";

            echo "<tr>";
            echo "<td><strong>Číslo reklamace</strong></td>";
            echo "<td><code>" . htmlspecialchars($novePatterns['cislo_reklamace']) . "</code></td>";
            echo "<td>NCE25-..., NCM23-..., NCL24-..., atd.</td>";
            echo "</tr>";

            echo "<tr>";
            echo "<td><strong>Ulice</strong></td>";
            echo "<td><code>" . htmlspecialchars($novePatterns['ulice']) . "</code></td>";
            echo "<td>Adresa z &quot;Místo reklamace&quot; (ne &quot;Zákazník&quot;)</td>";
            echo "</tr>";

            echo "</table>";

            echo "<div class='info'>";
            echo "<strong>🧪 TEST:</strong><br><br>";
            echo "Pro <strong>NCM-NATUZZI.pdf</strong> by mělo zachytit:<br>";
            echo "• Číslo: <code>NCM23-00000208-41/CZ709-2025</code><br>";
            echo "• Ulice: <code>Jungmannovo náměstí 76</code> (Místo reklamace)<br><br>";
            echo "NE: <code>Beranových 827</code> (to je adresa zákazníka)";
            echo "</div>";

            echo "<div class='success'>";
            echo "<strong>🎉 MIGRACE ÚSPĚŠNĚ DOKONČENA!</strong><br><br>";
            echo "<a href='live_test_pdf.html' class='btn'>🔍 Otestovat PDF upload</a>";
            echo "<a href='novareklamace.php' class='btn'>📄 Otevřít formulář</a>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='warning'>";
        echo "<strong>📋 Co bude provedeno:</strong><br><br>";
        echo "1. <strong>Číslo reklamace</strong>: Změna patternu aby fungoval pro všechny prefixy<br>";
        echo "2. <strong>Ulice</strong>: Změna patternu aby hledal v sekci \"Místo reklamace\"<br>";
        echo "</div>";

        echo "<h3>Příklad rozdílu:</h3>";

        echo "<h4>1. Číslo reklamace</h4>";
        echo "<table>";
        echo "<tr><th>Pattern</th><th>Co najde</th></tr>";
        echo "<tr>";
        echo "<td><strong>STARÝ:</strong><br><code>/Čislo reklamace:\\s+NCE25-...</code></td>";
        echo "<td>Jen NCE25-00002444-39/...</td>";
        echo "</tr>";
        echo "<tr>";
        echo "<td><strong>NOVÝ:</strong><br><code>/Čislo reklamace:\\s+[A-Z]{3}\\d{2}-...</code></td>";
        echo "<td>NCE25-..., NCM23-..., NCL24-..., atd.</td>";
        echo "</tr>";
        echo "</table>";

        echo "<h4>2. Ulice (adresa)</h4>";
        echo "<table>";
        echo "<tr><th>PDF má</th><th>STARÝ pattern najde</th><th>NOVÝ pattern najde</th></tr>";
        echo "<tr>";
        echo "<td>1. Zákazník: Beranových 827<br>2. Místo reklamace: Jungmannovo náměstí 76</td>";
        echo "<td>❌ Beranových 827<br>(první výskyt)</td>";
        echo "<td>✅ Jungmannovo náměstí 76<br>(v sekci \"Místo reklamace\")</td>";
        echo "</tr>";
        echo "</table>";

        echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT OPRAVU</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
