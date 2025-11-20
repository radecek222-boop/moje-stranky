<?php
/**
 * Migrace: Oprava mapování polí pro NATUZZI protokol
 *
 * Tento skript upraví mapování polí v konfiguraci NATUZZI protokolu
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
    <title>Migrace: Oprava NATUZZI mapování</title>
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

    echo "<h1>🔧 Migrace: Oprava NATUZZI mapování</h1>";

    // Kontrola existence konfigurace
    echo "<div class='info'><strong>KONTROLA KONFIGURACE...</strong></div>";

    $stmt = $pdo->prepare("SELECT * FROM wgs_pdf_parser_configs WHERE zdroj = 'natuzzi'");
    $stmt->execute();
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        echo "<div class='error'>";
        echo "<strong>❌ CHYBA:</strong> NATUZZI konfigurace nebyla nalezena v databázi.<br>";
        echo "Nejdříve spusťte: <a href='pridej_pdf_parser_configs.php'>pridej_pdf_parser_configs.php</a>";
        echo "</div>";
        exit;
    }

    echo "<div class='success'>✓ NATUZZI konfigurace nalezena (ID: {$config['config_id']})</div>";

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

        // NOVÉ SPRÁVNÉ MAPOVÁNÍ
        $noveMapping = [
            // Číslo reklamace z NATUZZI → Číslo objednávky/reklamace ve formuláři
            'cislo_reklamace' => 'cislo_objednavky_reklamace',

            // Datum vyhotovení z NATUZZI → Datum prodeje ve formuláři
            'datum_vyhotoveni' => 'datum_prodeje',

            // Datum podání z NATUZZI → Datum reklamace ve formuláři
            'datum_podani' => 'datum_reklamace',

            // Jméno a příjmení z NATUZZI → Jméno zákazníka ve formuláři
            'jmeno' => 'jmeno',

            // Email z NATUZZI → E-mail ve formuláři
            'email' => 'email',

            // Telefon z NATUZZI → Telefon ve formuláři
            'telefon' => 'telefon',

            // Adresa (z "Místo reklamace") → Ulice a číslo popisné ve formuláři
            'adresa' => 'ulice',

            // Město z NATUZZI → Město ve formuláři
            'mesto' => 'mesto',

            // PSČ z NATUZZI → PSČ ve formuláři
            'psc' => 'psc',

            // Model z NATUZZI → Model ve formuláři
            'model' => 'model',

            // Látka z NATUZZI → Provedení ve formuláři
            'latka' => 'provedeni',

            // Látka z NATUZZI → Označení barvy ve formuláři (STEJNÁ HODNOTA!)
            'latka_barva' => 'barva',

            // Závada z NATUZZI → Popis problému od zákazníka ve formuláři
            'zavada' => 'popis_problemu'

            // Poznámka: "Doplňující informace od prodejce" se NEPÁRUJE
        ];

        // AKTUALIZOVANÉ REGEX PATTERNS - přidáme latka_barva
        $aktualniPatterns = json_decode($config['regex_patterns'], true);

        // Přidat nový pattern pro latka_barva (stejný jako latka)
        $aktualniPatterns['latka_barva'] = $aktualniPatterns['latka'];

        // Opravit pattern pro adresu - hledat v sekci "Místo reklamace"
        $aktualniPatterns['adresa'] = '/Místo reklamace.*?Adresa:\s*\n?\s*([^\n]+)/uis';

        $pdo->beginTransaction();

        try {
            // Update mapování
            $stmt = $pdo->prepare("
                UPDATE wgs_pdf_parser_configs
                SET pole_mapping = :mapping,
                    regex_patterns = :patterns,
                    updated_at = CURRENT_TIMESTAMP
                WHERE config_id = :id
            ");

            $stmt->execute([
                'mapping' => json_encode($noveMapping, JSON_UNESCAPED_UNICODE),
                'patterns' => json_encode($aktualniPatterns, JSON_UNESCAPED_UNICODE),
                'id' => $config['config_id']
            ]);

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ MAPOVÁNÍ ÚSPĚŠNĚ OPRAVENO</strong><br>";
            echo "NATUZZI konfigurace byla aktualizována.";
            echo "</div>";

            // Zobrazit nové mapování
            echo "<h2>📋 Nové mapování:</h2>";
            echo "<table class='mapping-table'>";
            echo "<tr><th>NATUZZI protokol</th><th>→</th><th>Formulář novareklamace.php</th></tr>";
            echo "<tr><td>Číslo reklamace</td><td>→</td><td>Číslo objednávky/reklamace</td></tr>";
            echo "<tr><td>Datum vyhotovení</td><td>→</td><td>Datum prodeje</td></tr>";
            echo "<tr><td>Datum podání</td><td>→</td><td>Datum reklamace</td></tr>";
            echo "<tr><td>Jméno a příjmení</td><td>→</td><td>Jméno zákazníka</td></tr>";
            echo "<tr><td>Email</td><td>→</td><td>E-mail</td></tr>";
            echo "<tr><td>Telefon</td><td>→</td><td>Telefon</td></tr>";
            echo "<tr><td>Adresa (Místo reklamace)</td><td>→</td><td>Ulice a číslo popisné</td></tr>";
            echo "<tr><td>Město</td><td>→</td><td>Město</td></tr>";
            echo "<tr><td>PSČ</td><td>→</td><td>PSČ</td></tr>";
            echo "<tr><td>Model</td><td>→</td><td>Model</td></tr>";
            echo "<tr><td>Látka</td><td>→</td><td>Provedení</td></tr>";
            echo "<tr><td>Látka</td><td>→</td><td>Označení barvy</td></tr>";
            echo "<tr><td>Závada</td><td>→</td><td>Popis problému od zákazníka</td></tr>";
            echo "<tr><td colspan='3' style='background:#fff3cd;color:#856404;'><em>Doplňující informace od prodejce - NEPÁRUJE SE</em></td></tr>";
            echo "</table>";

            echo "<div class='info'>";
            echo "<strong>🎯 Co se změnilo:</strong><br>";
            echo "1. ✅ Látka se nyní mapuje na OBA pole: <strong>Provedení</strong> i <strong>Označení barvy</strong><br>";
            echo "2. ✅ Adresa se hledá v sekci 'Místo reklamace' (ne 'Zákazník')<br>";
            echo "3. ✅ Odstraněno mapování 'Složení' → 'Doplňující informace' (NEPÁRUJE SE)<br>";
            echo "4. ✅ Všechna mapování odpovídají požadavkům";
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
        echo "<tr><th>NATUZZI protokol</th><th>→</th><th>Formulář novareklamace.php</th></tr>";
        echo "<tr><td>Číslo reklamace</td><td>→</td><td>Číslo objednávky/reklamace</td></tr>";
        echo "<tr><td>Datum vyhotovení</td><td>→</td><td>Datum prodeje</td></tr>";
        echo "<tr><td>Datum podání</td><td>→</td><td>Datum reklamace</td></tr>";
        echo "<tr><td>Jméno a příjmení</td><td>→</td><td>Jméno zákazníka</td></tr>";
        echo "<tr><td>Email</td><td>→</td><td>E-mail</td></tr>";
        echo "<tr><td>Telefon</td><td>→</td><td>Telefon</td></tr>";
        echo "<tr><td>Adresa (Místo reklamace)</td><td>→</td><td>Ulice a číslo popisné</td></tr>";
        echo "<tr><td>Město</td><td>→</td><td>Město</td></tr>";
        echo "<tr><td>PSČ</td><td>→</td><td>PSČ</td></tr>";
        echo "<tr><td>Model</td><td>→</td><td>Model</td></tr>";
        echo "<tr><td>Látka</td><td>→</td><td><strong>Provedení</strong></td></tr>";
        echo "<tr><td>Látka</td><td>→</td><td><strong>Označení barvy</strong></td></tr>";
        echo "<tr><td>Závada</td><td>→</td><td>Popis problému od zákazníka</td></tr>";
        echo "<tr><td colspan='3' style='background:#fff3cd;color:#856404;'><em>Doplňující informace od prodejce - NEPÁRUJE SE</em></td></tr>";
        echo "</table>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ DŮLEŽITÉ ZMĚNY:</strong><br>";
        echo "• Látka z NATUZZI se bude mapovat na <strong>DVĚ</strong> pole: Provedení + Označení barvy<br>";
        echo "• Adresa se bude hledat v sekci 'Místo reklamace' místo 'Zákazník'<br>";
        echo "• Doplňující informace od prodejce se NEBUDOU párovat";
        echo "</div>";

        echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT OPRAVU</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php' class='btn' style='background:#666;'>← Zpět do admin panelu</a>";
echo "</div></body></html>";
?>
