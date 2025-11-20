<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>FINÁLNÍ OPRAVA: NATUZZI + PHASE Mapování</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724;
                   padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
                 padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460;
                padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404;
                   padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #2D5016;
               color: white; text-decoration: none; border-radius: 5px;
               margin: 10px 5px 10px 0; font-weight: 600; }
        .btn:hover { background: #1a300d; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 0.9rem; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; font-weight: 600; }
        tr:hover { background: #f5f5f5; }
        .highlight { background: #ffeb3b; font-weight: 600; }
    </style>
</head>
<body>
<div class='container'>
<?php
/**
 * FINÁLNÍ OPRAVA: Správné mapování podle SQL struktury
 *
 * PROBLÉM: Používali jsme názvy HTML inputů, ale potřebujeme SQL názvy sloupců!
 * ŘEŠENÍ: Mapovat na skutečné SQL sloupce z tabulky wgs_reklamace
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<h1>✅ FINÁLNÍ OPRAVA: SQL Mapování</h1>";

try {
    $pdo = getDbConnection();

    $stmt = $pdo->query("SELECT * FROM wgs_pdf_parser_configs WHERE zdroj IN ('natuzzi', 'phase') ORDER BY zdroj");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($configs) < 2) {
        echo "<div class='error'>❌ Konfigurace nebyly nalezeny.</div>";
        exit;
    }

    echo "<div class='warning'>";
    echo "<strong>⚠️ DŮLEŽITÉ:</strong><br>";
    echo "SQL tabulka <code>wgs_reklamace</code> má sloupce:<br>";
    echo "• <span class='highlight'>cislo</span> (ne cislo_objednavky_reklamace!)<br>";
    echo "• <span class='highlight'>datum_prodeje</span><br>";
    echo "• <span class='highlight'>datum_reklamace</span><br>";
    echo "• <span class='highlight'>provedeni</span>, <span class='highlight'>barva</span>, <span class='highlight'>model</span><br>";
    echo "HTML inputy mají stejné ID jako SQL sloupce!";
    echo "</div>";

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>🔧 SPOUŠTÍM FINÁLNÍ OPRAVU...</strong></div>";

        $pdo->beginTransaction();

        try {
            foreach ($configs as $config) {
                $zdroj = $config['zdroj'];

                // ============================================
                // SPRÁVNÉ MAPOVÁNÍ NA SQL SLOUPCE!
                // ============================================
                $spravneMapping = [
                    // PDF klíč → SQL sloupec (HTML input ID)
                    'cislo_reklamace' => 'cislo',                    // ✅ ne "cislo_objednavky_reklamace"!
                    'datum_vyhotoveni' => 'datum_prodeje',           // ✅ datum_prodeje (SQL sloupec)
                    'datum_vyhotovenia' => 'datum_prodeje',          // ✅ pro PHASE (slovensky)
                    'datum_podani' => 'datum_reklamace',             // ✅ datum_reklamace (SQL sloupec)
                    'datum_podania' => 'datum_reklamace',            // ✅ pro PHASE (slovensky)
                    'jmeno' => 'jmeno',                              // ✅ jmeno (SQL sloupec)
                    'email' => 'email',                              // ✅ email (SQL sloupec)
                    'telefon' => 'telefon',                          // ✅ telefon (SQL sloupec)
                    'adresa' => 'ulice',                             // ✅ ulice (SQL sloupec)
                    'mesto' => 'mesto',                              // ✅ mesto (SQL sloupec)
                    'psc' => 'psc',                                  // ✅ psc (SQL sloupec)
                    'model' => 'model',                              // ✅ model (SQL sloupec)
                    'latka' => 'provedeni',                          // ✅ provedeni (SQL sloupec)
                    'latka_barva' => 'barva',                        // ✅ barva (SQL sloupec)
                    'zavada' => 'popis_problemu'                     // ✅ popis_problemu (SQL sloupec)
                ];

                if ($zdroj === 'natuzzi') {
                    // NATUZZI použije: datum_vyhotoveni, datum_podani
                    $natuzziMapping = [
                        'cislo_reklamace' => 'cislo',
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
                    if (!isset($aktualniPatterns['latka_barva'])) {
                        $aktualniPatterns['latka_barva'] = $aktualniPatterns['latka'];
                    }
                    if (!isset($aktualniPatterns['adresa']) || strpos($aktualniPatterns['adresa'], 'Místo reklamace') === false) {
                        $aktualniPatterns['adresa'] = '/Místo reklamace.*?Adresa:\s*\n?\s*([^\n]+)/uis';
                    }

                    $stmt = $pdo->prepare("UPDATE wgs_pdf_parser_configs SET pole_mapping = :mapping, regex_patterns = :patterns WHERE config_id = :id");
                    $stmt->execute([
                        'mapping' => json_encode($natuzziMapping, JSON_UNESCAPED_UNICODE),
                        'patterns' => json_encode($aktualniPatterns, JSON_UNESCAPED_UNICODE),
                        'id' => $config['config_id']
                    ]);

                    echo "<div class='success'>✅ <strong>NATUZZI</strong> - Mapování na SQL sloupce opraveno</div>";

                } elseif ($zdroj === 'phase') {
                    // PHASE použije: datum_vyhotovenia, datum_podania
                    $phaseMapping = [
                        'cislo_reklamace' => 'cislo',
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
                        'mapping' => json_encode($phaseMapping, JSON_UNESCAPED_UNICODE),
                        'patterns' => json_encode($novePatterns, JSON_UNESCAPED_UNICODE),
                        'id' => $config['config_id']
                    ]);

                    echo "<div class='success'>✅ <strong>PHASE</strong> - Mapování na SQL sloupce opraveno</div>";
                }
            }

            $pdo->commit();

            echo "<div class='success'><strong>🎉 FINÁLNÍ OPRAVA DOKONČENA!</strong></div>";

            echo "<h2>📋 Správné SQL mapování:</h2>";
            echo "<table>";
            echo "<tr><th>PDF Protokol</th><th>→</th><th>SQL Sloupec (HTML ID)</th></tr>";
            echo "<tr><td>Číslo reklamace</td><td>→</td><td><span class='highlight'>cislo</span></td></tr>";
            echo "<tr><td>Datum vyhotovení/vyhotovenia</td><td>→</td><td><span class='highlight'>datum_prodeje</span></td></tr>";
            echo "<tr><td>Datum podání/podania</td><td>→</td><td><span class='highlight'>datum_reklamace</span></td></tr>";
            echo "<tr><td>Jméno/Meno</td><td>→</td><td><span class='highlight'>jmeno</span></td></tr>";
            echo "<tr><td>Email</td><td>→</td><td><span class='highlight'>email</span></td></tr>";
            echo "<tr><td>Telefon/Telefón</td><td>→</td><td><span class='highlight'>telefon</span></td></tr>";
            echo "<tr><td>Adresa</td><td>→</td><td><span class='highlight'>ulice</span></td></tr>";
            echo "<tr><td>Mesto/Město</td><td>→</td><td><span class='highlight'>mesto</span></td></tr>";
            echo "<tr><td>PSČ</td><td>→</td><td><span class='highlight'>psc</span></td></tr>";
            echo "<tr><td>Model</td><td>→</td><td><span class='highlight'>model</span></td></tr>";
            echo "<tr><td>Látka</td><td>→</td><td><span class='highlight'>provedeni</span></td></tr>";
            echo "<tr><td>Látka (kopie)</td><td>→</td><td><span class='highlight'>barva</span></td></tr>";
            echo "<tr><td>Závada</td><td>→</td><td><span class='highlight'>popis_problemu</span></td></tr>";
            echo "</table>";

            echo "<div class='info'>";
            echo "<strong>✅ Klíčová oprava:</strong><br>";
            echo "• <code>cislo_objednavky_reklamace</code> → <code>cislo</code><br>";
            echo "• Všechna pole nyní mapují na skutečné SQL sloupce<br>";
            echo "• HTML inputy mají stejná ID jako SQL sloupce";
            echo "</div>";

            echo "<a href='novareklamace.php' class='btn'>📄 Vyzkoušet nahrání PDF</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>❌ CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } else {
        echo "<div class='info'>";
        echo "<strong>📝 Co se opraví:</strong><br>";
        echo "• <code>cislo_objednavky_reklamace</code> → <code>cislo</code> ✅<br>";
        echo "• Všechna pole budou mapovat na správné SQL sloupce<br>";
        echo "• NATUZZI i PHASE budou fungovat správně";
        echo "</div>";
        echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT FINÁLNÍ OPRAVU</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}
?>
<br><a href='admin.php' class='btn' style='background:#666;'>← Zpět</a>
</div>
</body>
</html>
