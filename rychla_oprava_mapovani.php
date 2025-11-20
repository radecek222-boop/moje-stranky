<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Rychlá oprava mapování - NATUZZI + PHASE</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; font-weight: 600; }
        .btn:hover { background: #1a300d; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 0.9rem; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; font-weight: 600; }
        tr:hover { background: #f5f5f5; }
    </style>
</head>
<body>
<div class='container'>
<?php
/**
 * RYCHLÁ OPRAVA: Aktualizace mapování pro NATUZZI i PHASE najednou
 * Tento skript opraví obě konfigurace jedním kliknutím
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<h1>⚡ Rychlá oprava: NATUZZI + PHASE</h1>";

try {
    $pdo = getDbConnection();

    // Načíst obě konfigurace
    $stmt = $pdo->query("SELECT * FROM wgs_pdf_parser_configs WHERE zdroj IN ('natuzzi', 'phase') ORDER BY zdroj");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($configs) < 2) {
        echo "<div class='error'>❌ Konfigurace nebyly nalezeny. Spusťte nejdříve: <a href='pridej_pdf_parser_configs.php'>pridej_pdf_parser_configs.php</a></div>";
        exit;
    }

    echo "<div class='info'><strong>✓ Nalezeno {count($configs)} konfigurací</strong></div>";

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>🔧 SPOUŠTÍM OPRAVU OBOU KONFIGURACÍ...</strong></div>";

        $pdo->beginTransaction();

        try {
            foreach ($configs as $config) {
                $zdroj = $config['zdroj'];

                if ($zdroj === 'natuzzi') {
                    // === NATUZZI MAPOVÁNÍ ===
                    $noveMapping = [
                        'cislo_reklamace' => 'cislo_objednavky_reklamace',
                        'datum_vyhotoveni' => 'datum_prodeje',
                        'datum_podani' => 'datum_reklamace',
                        'jmeno' => 'jmeno',
                        'email' => 'email',
                        'telefon' => 'telefon',
                        'adresa' => 'ulice',
                        'mesto' => 'mesto',
                        'psc' => 'psc',
                        'model' => 'model',
                        'latka' => 'provedeni',
                        'latka_barva' => 'barva',
                        'zavada' => 'popis_problemu'
                    ];

                    $aktualniPatterns = json_decode($config['regex_patterns'], true);
                    $aktualniPatterns['latka_barva'] = $aktualniPatterns['latka'];
                    $aktualniPatterns['adresa'] = '/Místo reklamace.*?Adresa:\s*\n?\s*([^\n]+)/uis';

                    $stmt = $pdo->prepare("UPDATE wgs_pdf_parser_configs SET pole_mapping = :mapping, regex_patterns = :patterns WHERE config_id = :id");
                    $stmt->execute([
                        'mapping' => json_encode($noveMapping, JSON_UNESCAPED_UNICODE),
                        'patterns' => json_encode($aktualniPatterns, JSON_UNESCAPED_UNICODE),
                        'id' => $config['config_id']
                    ]);

                    echo "<div class='success'>✅ <strong>NATUZZI</strong> aktualizováno</div>";

                } elseif ($zdroj === 'phase') {
                    // === PHASE MAPOVÁNÍ ===
                    $noveMapping = [
                        'cislo_reklamace' => 'cislo_objednavky_reklamace',
                        'datum_vyhotovenia' => 'datum_prodeje',
                        'datum_podania' => 'datum_reklamace',
                        'jmeno' => 'jmeno',
                        'email' => 'email',
                        'telefon' => 'telefon',
                        'adresa' => 'ulice',
                        'mesto' => 'mesto',
                        'psc' => 'psc',
                        'model' => 'model',
                        'latka' => 'provedeni',
                        'latka_barva' => 'barva',
                        'zavada' => 'popis_problemu'
                    ];

                    $novePatterns = [
                        'cislo_reklamace' => '/Číslo reklamácie:\s*\n?\s*([A-Z0-9\-\/]+)/ui',
                        'datum_vyhotovenia' => '/Dátum vyhotovenia:\s*\n?\s*(\d{1,2}\.\d{1,2}\.\d{4})/ui',
                        'datum_podania' => '/Dátum podania:\s*\n?\s*(\d{1,2}\.\d{1,2}\.\d{4})/ui',
                        'cislo_objednavky' => '/Číslo objednávky:\s*\n?\s*(\d+)/ui',
                        'cislo_faktury' => '/Číslo faktúry:\s*\n?\s*(\d+)/ui',
                        'jmeno' => '/Miesto reklamácie.*?Meno a priezvisko:\s*\n?\s*([^\n]+)/uis',
                        'adresa' => '/Miesto reklamácie.*?Adresa:\s*\n?\s*([^\n]+)/uis',
                        'mesto' => '/Miesto reklamácie.*?Mesto:\s*\n?\s*([^\n]+)/uis',
                        'psc' => '/Miesto reklamácie.*?PSČ:\s*\n?\s*(\d{3}\s?\d{2})/uis',
                        'krajina' => '/Krajina:\s*\n?\s*([^\n]+)/ui',
                        'telefon' => '/Telefón:\s*\n?\s*([\+\d\s]+)/ui',
                        'email' => '/Email:\s*\n?\s*([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/ui',
                        'model' => '/Model:\s*\n?\s*([^\n]+)/ui',
                        'zlozenie' => '/Zloženie:\s*\n?\s*([^\n]+)/ui',
                        'latka' => '/Látka:\s*\n?\s*([^\n]+)/ui',
                        'latka_barva' => '/Látka:\s*\n?\s*([^\n]+)/ui',
                        'kategoria' => '/Kategória:\s*\n?\s*([^\n]+)/ui',
                        'zavada' => '/Závada:\s*\n?\s*([^\n]+(?:\n(?!Vyjadrenie|Vyjádření)[^\n]+)*)/ui',
                        'typ_objektu' => '/(Rodinný dom|Panelák)/ui',
                        'poschodie' => '/Poschodie:\s*\n?\s*(\d+)/ui'
                    ];

                    $stmt = $pdo->prepare("UPDATE wgs_pdf_parser_configs SET pole_mapping = :mapping, regex_patterns = :patterns WHERE config_id = :id");
                    $stmt->execute([
                        'mapping' => json_encode($noveMapping, JSON_UNESCAPED_UNICODE),
                        'patterns' => json_encode($novePatterns, JSON_UNESCAPED_UNICODE),
                        'id' => $config['config_id']
                    ]);

                    echo "<div class='success'>✅ <strong>PHASE</strong> aktualizováno</div>";
                }
            }

            $pdo->commit();

            echo "<div class='success'><strong>🎉 OBĚ KONFIGURACE ÚSPĚŠNĚ OPRAVENY!</strong></div>";

            echo "<h2>📋 Co bylo opraveno:</h2>";
            echo "<table>";
            echo "<tr><th>Protokol</th><th>Změny</th></tr>";
            echo "<tr><td><strong>NATUZZI</strong></td><td>
                • Látka → Provedení + Označení barvy<br>
                • Datum vyhotovení → Datum prodeje<br>
                • Adresa z 'Místo reklamace'
            </td></tr>";
            echo "<tr><td><strong>PHASE</strong></td><td>
                • Látka → Provedení + Označení barvy<br>
                • Dátum vyhotovenia → Datum prodeje<br>
                • Jméno a Adresa z 'Miesto reklamácie'
            </td></tr>";
            echo "</table>";

            echo "<a href='novareklamace.php' class='btn'>📄 Vyzkoušet nahrání PDF</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>❌ CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } else {
        echo "<div class='info'><strong>📝 Tato migrace opraví:</strong><br>";
        echo "✅ NATUZZI - Látka → Provedení + Barva, Datum vyhotovení → Datum prodeje<br>";
        echo "✅ PHASE - Látka → Provedení + Barva, Dátum vyhotovenia → Datum prodeje";
        echo "</div>";
        echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT OPRAVU</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
<br><a href='admin.php' class='btn' style='background:#666;'>← Zpět</a>
</div>
</body>
</html>
