<?php
/**
 * Migrace: Aktualizace PHASE protokolu patterns
 *
 * Tento skript BEZPEČNĚ aktualizuje regex patterns a pole mapping
 * pro slovenský PHASE protokol v databázi.
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
    <title>Migrace: PHASE Patterns</title>
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

    echo "<h1>📄 Migrace: Aktualizace PHASE Patterns</h1>";

    // 1. KONTROLNÍ FÁZE
    echo "<div class='info'><strong>KONTROLA STÁVAJÍCÍ KONFIGURACE...</strong></div>";

    $stmt = $pdo->prepare("SELECT * FROM wgs_pdf_parser_configs WHERE zdroj = 'phase'");
    $stmt->execute();
    $existujici = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existujici) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ PHASE konfigurace již existuje:</strong><br>";
        echo "Název: " . htmlspecialchars($existujici['nazev']) . "<br>";
        echo "Aktivní: " . ($existujici['aktivni'] ? 'ANO' : 'NE') . "<br>";
        echo "Priorita: " . $existujici['priorita'];
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ PHASE konfigurace NEEXISTUJE!</strong><br>";
        echo "Bude vytvořena nová konfigurace.";
        echo "</div>";
    }

    // 2. POKUD JE NASTAVENO ?execute=1, PROVÉST MIGRACI
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            // Nové patterns pro PHASE (slovenský protokol)
            $patterns = [
                'cislo_reklamace' => '/Číslo reklamácie:\s+([A-Z0-9\-\/]+)/ui',
                'datum_vyhotovenia' => '/Dátum vyhotovenia:\s+(\d{1,2}\.\d{1,2}\.\d{4})/ui',
                'datum_podania' => '/Dátum podania:\s+(\d{1,2}\.\d{1,2}\.\d{4})/ui',
                'jmeno' => '/Meno a priezvisko:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+\s+[A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)/ui',
                'email' => '/Email:\s+([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,})/ui',
                'telefon' => '/Telefón:\s+([\d\s]+)/ui',
                'ulice' => '/Adresa:\s+([^\n]+?)(?:\s+Meno|$)/ui',
                'mesto' => '/Mesto:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ][a-záčďéěíňóřšťúůýž]+)/ui',
                'psc' => '/PSČ:\s+(\d{3}\s?\d{2}|\d{5})/ui',
                'model' => '/Model:\s+([^\n]+?)(?:\s+Zloženie|$)/ui',
                'latka' => '/Látka:\s+([^\n]+?)(?:\s+Kategória|Nohy|$)/ui',
                'latka_barva' => '/Látka:\s+([^\n]+?)(?:\s+Kategória|Nohy|$)/ui',
                'zavada' => '/Závada:\s+([^\n]+?)(?:\s+Vyjadrenie|$)/ui'
            ];

            // Pole mapping (slovenské názvy → české SQL sloupce)
            $mapping = [
                'cislo_reklamace' => 'cislo',
                'datum_vyhotovenia' => 'datum_prodeje',
                'datum_podania' => 'datum_reklamace',
                'jmeno' => 'jmeno',
                'email' => 'email',
                'telefon' => 'telefon',
                'ulice' => 'ulice',
                'mesto' => 'mesto',
                'psc' => 'psc',
                'model' => 'model',
                'latka' => 'provedeni',
                'latka_barva' => 'barva',
                'zavada' => 'popis_problemu'
            ];

            if ($existujici) {
                // UPDATE existující konfigurace
                $stmt = $pdo->prepare("
                    UPDATE wgs_pdf_parser_configs
                    SET
                        regex_patterns = :patterns,
                        pole_mapping = :mapping,
                        detekce_pattern = 'Dátum podania|Miesto reklamácie|Telefón|Krajina',
                        priorita = 10,
                        aktivni = 1
                    WHERE zdroj = 'phase'
                ");
                $stmt->execute([
                    'patterns' => json_encode($patterns, JSON_UNESCAPED_UNICODE),
                    'mapping' => json_encode($mapping, JSON_UNESCAPED_UNICODE)
                ]);

                echo "<div class='success'>";
                echo "<strong>✅ PHASE konfigurace aktualizována!</strong><br>";
                echo "Upraveno řádků: " . $stmt->rowCount();
                echo "</div>";
            } else {
                // INSERT nové konfigurace
                $stmt = $pdo->prepare("
                    INSERT INTO wgs_pdf_parser_configs
                    (nazev, zdroj, regex_patterns, pole_mapping, detekce_pattern, priorita, aktivni)
                    VALUES
                    ('PHASE Protokol (Slovenský)', 'phase', :patterns, :mapping, 'Dátum podania|Miesto reklamácie|Telefón|Krajina', 10, 1)
                ");
                $stmt->execute([
                    'patterns' => json_encode($patterns, JSON_UNESCAPED_UNICODE),
                    'mapping' => json_encode($mapping, JSON_UNESCAPED_UNICODE)
                ]);

                echo "<div class='success'>";
                echo "<strong>✅ PHASE konfigurace vytvořena!</strong><br>";
                echo "ID nové konfigurace: " . $pdo->lastInsertId();
                echo "</div>";
            }

            $pdo->commit();

            // Zobrazit výsledek
            echo "<h2>📊 Výsledná konfigurace:</h2>";
            $stmt = $pdo->prepare("SELECT * FROM wgs_pdf_parser_configs WHERE zdroj = 'phase'");
            $stmt->execute();
            $vysledek = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($vysledek) {
                echo "<table>";
                echo "<tr><th>Položka</th><th>Hodnota</th></tr>";
                echo "<tr><td>Config ID</td><td>" . $vysledek['config_id'] . "</td></tr>";
                echo "<tr><td>Název</td><td>" . htmlspecialchars($vysledek['nazev']) . "</td></tr>";
                echo "<tr><td>Zdroj</td><td>" . htmlspecialchars($vysledek['zdroj']) . "</td></tr>";
                echo "<tr><td>Aktivní</td><td>" . ($vysledek['aktivni'] ? '✅ ANO' : '❌ NE') . "</td></tr>";
                echo "<tr><td>Priorita</td><td>" . $vysledek['priorita'] . "</td></tr>";
                echo "<tr><td>Detekční pattern</td><td><code>" . htmlspecialchars($vysledek['detekce_pattern']) . "</code></td></tr>";
                echo "</table>";

                echo "<h3>Regex Patterns:</h3>";
                echo "<pre><code>" . json_encode(json_decode($vysledek['regex_patterns']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</code></pre>";

                echo "<h3>Pole Mapping:</h3>";
                echo "<pre><code>" . json_encode(json_decode($vysledek['pole_mapping']), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</code></pre>";
            }

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
        echo "<strong>Aktualizace PHASE patterns:</strong><br><br>";
        echo "✅ <strong>Regex patterns</strong> pro slovenský protokol (13 polí)<br>";
        echo "✅ <strong>Pole mapping</strong> (slovenské názvy → české SQL sloupce)<br>";
        echo "✅ <strong>Detekční pattern</strong> pro auto-detekci PHASE PDF<br>";
        echo "✅ <strong>Priorita</strong> = 10 (vyšší než NATUZZI)<br>";
        echo "✅ <strong>Aktivní</strong> = ANO<br>";
        echo "</div>";

        echo "<h3>Příklad patterns:</h3>";
        echo "<pre><code>";
        echo "ulice: /Adresa:\s+([^\n]+?)(?:\s+Meno|$)/ui\n";
        echo "jmeno: /Meno a priezvisko:\s+([A-ZÁČĎÉĚÍŇÓŘŠŤÚŮÝŽ]...)/ui\n";
        echo "telefon: /Telefón:\s+([\d\s]+)/ui\n";
        echo "email: /Email:\s+([a-zA-Z0-9._%+-]+@...)/ui\n";
        echo "...";
        echo "</code></pre>";

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
