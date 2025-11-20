<?php
/**
 * Migrace: Oprava mapování polí pro PHASE protokol
 *
 * Tento skript upraví mapování polí v konfiguraci PHASE protokolu
 * podle správných požadavků uživatele.
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
    <title>Migrace: Oprava PHASE mapování</title>
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
        table { width: 100%; border-collapse: collapse; margin: 20px 0;
                font-size: 0.9rem; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; font-weight: 600; }
        tr:hover { background: #f5f5f5; }
        .mapping-table td:first-child { font-weight: 600; color: #2D5016; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #dee2e6; font-size: 0.85rem; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔧 Migrace: Oprava PHASE mapování</h1>";

    // Kontrola existence konfigurace
    echo "<div class='info'><strong>KONTROLA KONFIGURACE...</strong></div>";

    $stmt = $pdo->prepare("SELECT * FROM wgs_pdf_parser_configs WHERE zdroj = 'phase'");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        echo "<div class='error'>";
        echo "<strong>❌ CHYBA:</strong> PHASE konfigurace nebyla nalezena v databázi.<br>";
        echo "Nejdříve spusťte: <a href='pridej_pdf_parser_configs.php'>pridej_pdf_parser_configs.php</a>";
        echo "</div>";
        exit;
    }

    echo "<div class='success'>✓ PHASE konfigurace nalezena (ID: {$config['config_id']})</div>";

    // Zobrazit aktuální mapování
    echo "<h2>📋 Aktuální mapování:</h2>";
    $aktualniMapping = json_decode($config['pole_mapping'], true);
    echo "<table class='mapping-table'>";
    echo "<tr><th>Klíč v PDF</th><th>→</th><th>Pole ve formuláři</th></tr>";
    foreach ($aktualniMapping as $klic => $hodnota) {
        echo "<tr><td>{$klic}</td><td>→</td><td>{$hodnota}</td></tr>";
    }
    echo "</table>";

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM OPRAVU...</strong></div>";

        // NOVÉ SPRÁVNÉ MAPOVÁNÍ PRO PHASE
        $noveMapping = [
            // Číslo reklamácie z PHASE → Číslo objednávky/reklamace ve formuláři
            'cislo_reklamace' => 'cislo_objednavky_reklamace',

            // Dátum vyhotovenia z PHASE → Datum prodeje ve formuláři
            'datum_vyhotovenia' => 'datum_prodeje',

            // Dátum podania z PHASE → Datum reklamace ve formuláři
            'datum_podania' => 'datum_reklamace',

            // Meno a priezvisko z PHASE → Jméno zákazníka ve formuláři
            'jmeno' => 'jmeno',

            // Email z PHASE → E-mail ve formuláři
            'email' => 'email',

            // Telefón z PHASE → Telefon ve formuláři
            'telefon' => 'telefon',

            // Adresa (z "Miesto reklamácie") → Ulice a číslo popisné ve formuláři
            'adresa' => 'ulice',

            // Mesto z PHASE → Město ve formuláři
            'mesto' => 'mesto',

            // PSČ z PHASE → PSČ ve formuláři
            'psc' => 'psc',

            // Model z PHASE → Model ve formuláři
            'model' => 'model',

            // Látka z PHASE → Provedení ve formuláři
            'latka' => 'provedeni',

            // Látka z PHASE → Označení barvy ve formuláři (STEJNÁ HODNOTA!)
            'latka_barva' => 'barva',

            // Závada z PHASE → Popis problému od zákazníka ve formuláři
            'zavada' => 'popis_problemu'

            // Poznámka: "Doplňující informace od prodejce" se NEPÁRUJE
        ];

        // AKTUALIZOVANÉ REGEX PATTERNS PRO PHASE (slovenština!)
        $novePatterns = [
            // Číslo reklamácie (slovensky)
            'cislo_reklamace' => '/Číslo reklamácie:\s*\n?\s*([A-Z0-9\-\/]+)/ui',

            // Dátum vyhotovenia (slovensky) - 21.02.2025
            'datum_vyhotovenia' => '/Dátum vyhotovenia:\s*\n?\s*(\d{1,2}\.\d{1,2}\.\d{4})/ui',

            // Dátum podania (slovensky) - 19.05.2025
            'datum_podania' => '/Dátum podania:\s*\n?\s*(\d{1,2}\.\d{1,2}\.\d{4})/ui',

            // Číslo objednávky
            'cislo_objednavky' => '/Číslo objednávky:\s*\n?\s*(\d+)/ui',

            // Číslo faktúry
            'cislo_faktury' => '/Číslo faktúry:\s*\n?\s*(\d+)/ui',

            // Meno a priezvisko (ze sloupce "Miesto reklamácie")
            'jmeno' => '/Miesto reklamácie.*?Meno a priezvisko:\s*\n?\s*([^\n]+)/uis',

            // Adresa (ze sloupce "Miesto reklamácie")
            'adresa' => '/Miesto reklamácie.*?Adresa:\s*\n?\s*([^\n]+)/uis',

            // Mesto
            'mesto' => '/Miesto reklamácie.*?Mesto:\s*\n?\s*([^\n]+)/uis',

            // PSČ
            'psc' => '/Miesto reklamácie.*?PSČ:\s*\n?\s*(\d{3}\s?\d{2})/uis',

            // Krajina
            'krajina' => '/Krajina:\s*\n?\s*([^\n]+)/ui',

            // Telefón (slovensky)
            'telefon' => '/Telefón:\s*\n?\s*([\+\d\s]+)/ui',

            // Email
            'email' => '/Email:\s*\n?\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/ui',

            // Model
            'model' => '/Model:\s*\n?\s*([^\n]+)/ui',

            // Zloženie (slovensky)
            'zlozenie' => '/Zloženie:\s*\n?\s*([^\n]+)/ui',

            // Látka
            'latka' => '/Látka:\s*\n?\s*([^\n]+)/ui',

            // Látka (pro barvu - STEJNÝ pattern!)
            'latka_barva' => '/Látka:\s*\n?\s*([^\n]+)/ui',

            // Kategória
            'kategoria' => '/Kategória:\s*\n?\s*([^\n]+)/ui',

            // Závada
            'zavada' => '/Závada:\s*\n?\s*([^\n]+(?:\n(?!Vyjadrenie|Vyjádření)[^\n]+)*)/ui',

            // Typ objektu (slovensky)
            'typ_objektu' => '/(Rodinný dom|Panelák)/ui',

            // Poschodie
            'poschodie' => '/Poschodie:\s*\n?\s*(\d+)/ui'
        ];

        $pdo->beginTransaction();

        try {
            // Update mapování a patterns
            $stmt = $pdo->prepare("
                UPDATE wgs_pdf_parser_configs
                SET pole_mapping = :mapping,
                    regex_patterns = :patterns,
                    updated_at = CURRENT_TIMESTAMP
                WHERE config_id = :id
            ");

            $stmt->execute([
                'mapping' => json_encode($noveMapping, JSON_UNESCAPED_UNICODE),
                'patterns' => json_encode($novePatterns, JSON_UNESCAPED_UNICODE),
                'id' => $config['config_id']
            ]);

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ MAPOVÁNÍ ÚSPĚŠNĚ OPRAVENO</strong><br>";
            echo "PHASE konfigurace byla aktualizována.";
            echo "</div>";

            // Zobrazit nové mapování
            echo "<h2>📋 Nové mapování:</h2>";
            echo "<table class='mapping-table'>";
            echo "<tr><th>PHASE protokol (slovensky)</th><th>→</th><th>Formulář novareklamace.php</th></tr>";
            echo "<tr><td>Číslo reklamácie</td><td>→</td><td>Číslo objednávky/reklamace</td></tr>";
            echo "<tr><td><strong>Dátum vyhotovenia</strong></td><td>→</td><td><strong>Datum prodeje</strong> ✅</td></tr>";
            echo "<tr><td>Dátum podania</td><td>→</td><td>Datum reklamace</td></tr>";
            echo "<tr><td>Meno a priezvisko (Miesto reklamácie)</td><td>→</td><td>Jméno zákazníka</td></tr>";
            echo "<tr><td>Email</td><td>→</td><td>E-mail</td></tr>";
            echo "<tr><td>Telefón</td><td>→</td><td>Telefon</td></tr>";
            echo "<tr><td>Adresa (Miesto reklamácie)</td><td>→</td><td>Ulice a číslo popisné</td></tr>";
            echo "<tr><td>Mesto</td><td>→</td><td>Město</td></tr>";
            echo "<tr><td>PSČ</td><td>→</td><td>PSČ</td></tr>";
            echo "<tr><td>Model</td><td>→</td><td>Model</td></tr>";
            echo "<tr><td><strong>Látka</strong></td><td>→</td><td><strong>Provedení</strong> ✅</td></tr>";
            echo "<tr><td><strong>Látka</strong></td><td>→</td><td><strong>Označení barvy</strong> ✅</td></tr>";
            echo "<tr><td>Závada</td><td>→</td><td>Popis problému od zákazníka</td></tr>";
            echo "<tr><td colspan='3' style='background:#fff3cd;color:#856404;'><em>Doplňující informace od prodejce - NEPÁRUJE SE</em></td></tr>";
            echo "</table>";

            echo "<div class='info'>";
            echo "<strong>🎯 Co se změnilo:</strong><br>";
            echo "1. ✅ Látka se nyní mapuje na OBA pole: <strong>Provedení</strong> i <strong>Označení barvy</strong><br>";
            echo "2. ✅ <strong>Dátum vyhotovenia</strong> (ne dátum podania!) → Datum prodeje<br>";
            echo "3. ✅ Jméno a Adresa se hledají v sekci 'Miesto reklamácie' (ne 'Zákazník')<br>";
            echo "4. ✅ Odstraněno mapování 'Kategória' → 'Barva' (Barva = Látka!)<br>";
            echo "5. ✅ Všechny patterns upraveny pro slovenštinu (dátum, meno, telefón, ...)";
            echo "</div>";

            echo "<a href='novareklamace.php' class='btn'>📄 Vyzkoušet nahrání PDF</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>❌ CHYBA PŘI AKTUALIZACI:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        // Náhled změn
        echo "<h2>📝 Co se změní:</h2>";
        echo "<table class='mapping-table'>";
        echo "<tr><th>PHASE protokol (slovensky)</th><th>→</th><th>Formulář novareklamace.php</th></tr>";
        echo "<tr><td>Číslo reklamácie</td><td>→</td><td>Číslo objednávky/reklamace</td></tr>";
        echo "<tr><td><strong>Dátum vyhotovenia</strong></td><td>→</td><td><strong>Datum prodeje</strong> ✅</td></tr>";
        echo "<tr><td>Dátum podania</td><td>→</td><td>Datum reklamace</td></tr>";
        echo "<tr><td>Meno a priezvisko (Miesto reklamácie)</td><parameter>→</td><td>Jméno zákazníka</td></tr>";
        echo "<tr><td>Email</td><td>→</td><td>E-mail</td></tr>";
        echo "<tr><td>Telefón</td><td>→</td><td>Telefon</td></tr>";
        echo "<tr><td>Adresa (Miesto reklamácie)</td><td>→</td><td>Ulice a číslo popisné</td></tr>";
        echo "<tr><td>Mesto</td><td>→</td><td>Město</td></tr>";
        echo "<tr><td>PSČ</td><td>→</td><td>PSČ</td></tr>";
        echo "<tr><td>Model</td><td>→</td><td>Model</td></tr>";
        echo "<tr><td><strong>Látka</strong></td><td>→</td><td><strong>Provedení</strong> ✅</td></tr>";
        echo "<tr><td><strong>Látka</strong></td><td>→</td><td><strong>Označení barvy</strong> ✅</td></tr>";
        echo "<tr><td>Závada</td><td>→</td><td>Popis problému od zákazníka</td></tr>";
        echo "<tr><td colspan='3' style='background:#fff3cd;color:#856404;'><em>Doplňující informace od prodejce - NEPÁRUJE SE</em></td></tr>";
        echo "</table>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ DŮLEŽITÉ ZMĚNY:</strong><br>";
        echo "• Látka z PHASE se bude mapovat na <strong>DVĚ</strong> pole: Provedení + Označení barvy<br>";
        echo "• <strong>Dátum vyhotovenia</strong> (ne dátum podania!) → Datum prodeje<br>";
        echo "• Jméno a Adresa se budou hledat v sekci 'Miesto reklamácie'<br>";
        echo "• Regex patterns upraveny pro slovenštinu";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT OPRAVU</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php' class='btn' style='background:#666;'>← Zpět do admin panelu</a>";
echo "</div></body></html>";
?>
