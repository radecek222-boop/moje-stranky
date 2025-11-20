<?php
/**
 * Migrace: Přidání PHASE CZ konfigurace
 *
 * PHASE má DVĚ VERZE protokolů:
 * 1. PHASE SK - slovenská terminologie ("Reklamačný list", "Dátum podania")
 * 2. PHASE CZ - česká terminologie ("Servisní list", "Datum podání")
 *
 * Tento skript:
 * - Přidá novou konfiguraci pro PHASE CZ
 * - Aktualizuje detekční patterny pro správné rozpoznání všech tří typů PDF
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: PHASE CZ konfigurace</title>
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
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; font-size: 0.9em; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🇨🇿 Migrace: PHASE CZ konfigurace</h1>";

    echo "<div class='info'>";
    echo "<strong>📋 ZJIŠTĚNÍ:</strong><br><br>";
    echo "PHASE protokoly existují ve <strong>DVOU VERZÍCH</strong>:<br><br>";
    echo "<strong>1. PHASE SK</strong> (slovensky):<br>";
    echo "• Hlavička: <code>Reklamačný list</code><br>";
    echo "• Termíny: <code>Dátum podania</code>, <code>Miesto reklamácie</code><br><br>";
    echo "<strong>2. PHASE CZ</strong> (česky):<br>";
    echo "• Hlavička: <code>Servisní list</code><br>";
    echo "• Termíny: <code>Datum podání</code>, <code>Místo servisní opravy</code>, <code>Číslo serv. opravy</code><br><br>";
    echo "Obě verze mají stejné logo: <code>pohodlie a phase</code><br>";
    echo "</div>";

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
        echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

        $pdo->beginTransaction();

        try {
            // 1. Zkontrolovat jestli phase_cz už neexistuje
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM wgs_pdf_parser_configs WHERE zdroj = 'phase_cz'");
            $stmt->execute();
            $exists = $stmt->fetchColumn();

            if ($exists > 0) {
                echo "<div class='warning'>⚠️ PHASE CZ konfigurace již existuje - bude aktualizována</div>";
            }

            // 2. PHASE CZ regex patterns (česká terminologie)
            $phaseCzPatterns = [
                'cislo_reklamace' => '/Číslo serv\\. opravy:\\s+[A-Z0-9\\-]+\\s+([A-Z0-9\\-\\/S]+)/ui',
                'jmeno' => '/Místo servisní opravy.*?Jméno a příjmení:\\s+([^\\n]+)/uis',
                'email' => '/Jméno společnosti:\\s+([a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\\.[a-zA-Z]{2,})/ui',
                'telefon' => '/Místo servisní opravy.*?Telefon:\\s+([0-9\\s\\+]+)/uis',
                'ulice' => '/Místo servisní opravy.*?Adresa:\\s+([^\\n]+)/uis',
                'mesto' => '/Místo servisní opravy.*?Město:\\s+([^\\n]+)/uis',
                'psc' => '/Místo servisní opravy.*?PSČ:\\s+(\\d{3}\\s?\\d{2}|\\d{5})/uis'
            ];

            $phaseCzMapping = [
                'cislo_reklamace' => 'cislo',
                'jmeno' => 'jmeno',
                'email' => 'email',
                'telefon' => 'telefon',
                'ulice' => 'ulice',
                'mesto' => 'mesto',
                'psc' => 'psc'
            ];

            if ($exists > 0) {
                // Aktualizovat existující
                $stmt = $pdo->prepare("
                    UPDATE wgs_pdf_parser_configs
                    SET
                        nazev = 'PHASE CZ Parser (česká terminologie)',
                        detekce_pattern = '/(Servisní list.*Místo servisní opravy|sezení.*spaní|Číslo serv\\\\. opravy)/i',
                        priorita = 95,
                        regex_patterns = :patterns,
                        pole_mapping = :mapping,
                        aktivni = 1
                    WHERE zdroj = 'phase_cz'
                ");
            } else {
                // Vložit novou konfiguraci
                $stmt = $pdo->prepare("
                    INSERT INTO wgs_pdf_parser_configs (
                        zdroj, nazev, detekce_pattern, priorita,
                        regex_patterns, pole_mapping, aktivni
                    ) VALUES (
                        'phase_cz',
                        'PHASE CZ Parser (česká terminologie)',
                        '/(Servisní list.*Místo servisní opravy|sezení.*spaní|Číslo serv\\\\. opravy)/i',
                        95,
                        :patterns,
                        :mapping,
                        1
                    )
                ");
            }

            $stmt->execute([
                'patterns' => json_encode($phaseCzPatterns, JSON_UNESCAPED_UNICODE),
                'mapping' => json_encode($phaseCzMapping, JSON_UNESCAPED_UNICODE)
            ]);

            echo "<div class='success'>";
            echo "✅ PHASE CZ konfigurace " . ($exists > 0 ? 'aktualizována' : 'přidána') . "!<br><br>";
            echo "<strong>Detekční pattern:</strong> <code>/(Servisní list.*Místo servisní opravy|sezení.*spaní|Číslo serv\\. opravy)/i</code><br>";
            echo "<strong>Priorita:</strong> 95 (mezi NATUZZI a PHASE SK)";
            echo "</div>";

            // 3. Aktualizovat PHASE SK detekční pattern a prioritu
            $stmt = $pdo->prepare("
                UPDATE wgs_pdf_parser_configs
                SET
                    nazev = 'PHASE SK Parser (slovenská terminologie)',
                    detekce_pattern = '/(Reklamačný list|Dátum podania|sedenie.*spanie|Miesto reklamácie)/i',
                    priorita = 90
                WHERE zdroj = 'phase'
            ");
            $stmt->execute();

            echo "<div class='success'>";
            echo "✅ PHASE SK detekční pattern a priorita aktualizovány<br>";
            echo "<strong>Nový pattern:</strong> <code>/(Reklamačný list|Dátum podania|sedenie.*spanie|Miesto reklamácie)/i</code><br>";
            echo "<strong>Priorita:</strong> 90";
            echo "</div>";

            // 4. Aktualizovat NATUZZI detekční pattern (přidat Reklamační list pro odlišení)
            $stmt = $pdo->prepare("
                UPDATE wgs_pdf_parser_configs
                SET detekce_pattern = '/(NATUZZI|EDITIONS|THE NAME OF COMFORT|Reklamační list.*Místo reklamace)/i'
                WHERE zdroj = 'natuzzi'
            ");
            $stmt->execute();

            echo "<div class='success'>";
            echo "✅ NATUZZI detekční pattern vylepšen<br>";
            echo "<strong>Nový pattern:</strong> <code>/(NATUZZI|EDITIONS|THE NAME OF COMFORT|Reklamační list.*Místo reklamace)/i</code>";
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
            echo "<strong>🧪 JAK DETEKCE FUNGUJE:</strong><br><br>";
            echo "<strong>1. NATUZZI</strong> (priorita 100):<br>";
            echo "Pokud text obsahuje: <code>NATUZZI</code> nebo <code>EDITIONS</code> nebo <code>THE NAME OF COMFORT</code> nebo <code>Reklamační list + Místo reklamace</code><br><br>";
            echo "<strong>2. PHASE CZ</strong> (priorita 95):<br>";
            echo "Pokud text obsahuje: <code>Servisní list + Místo servisní opravy</code> nebo <code>sezení a spaní</code> nebo <code>Číslo serv. opravy</code><br><br>";
            echo "<strong>3. PHASE SK</strong> (priorita 90):<br>";
            echo "Pokud text obsahuje: <code>Reklamačný list</code> nebo <code>Dátum podania</code> nebo <code>sedenie a spanie</code> nebo <code>Miesto reklamácie</code>";
            echo "</div>";

            echo "<div class='success'>";
            echo "<strong>🎉 MIGRACE ÚSPĚŠNĚ DOKONČENA!</strong><br><br>";
            echo "<strong>TESTUJ:</strong><br>";
            echo "• <a href='live_test_pdf.html' class='btn'>🔍 NATUZZI PROTOKOL.pdf</a><br>";
            echo "• <a href='live_test_pdf.html' class='btn'>🔍 PHASE CZ.pdf (český)</a><br>";
            echo "• <a href='live_test_pdf.html' class='btn'>🔍 PHASE PROTOKOL.pdf (slovenský)</a>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        echo "<div class='warning'>";
        echo "<strong>📋 Co bude provedeno:</strong><br><br>";
        echo "1. Přidá novou konfiguraci <strong>PHASE CZ</strong> s českou terminologií<br>";
        echo "2. Aktualizuje detekční pattern pro <strong>PHASE SK</strong> (slovenský)<br>";
        echo "3. Vylepší detekční pattern pro <strong>NATUZZI</strong><br>";
        echo "4. Nastaví priority: NATUZZI=100, PHASE CZ=95, PHASE SK=90";
        echo "</div>";

        echo "<h3>🔍 Srovnání verzí:</h3>";

        echo "<table>";
        echo "<tr><th>Pole</th><th>NATUZZI</th><th>PHASE CZ</th><th>PHASE SK</th></tr>";
        echo "<tr><td><strong>Hlavička</strong></td><td>Reklamační list</td><td>Servisní list</td><td>Reklamačný list</td></tr>";
        echo "<tr><td><strong>Logo</strong></td><td>NATUZZI EDITIONS</td><td>pohodlie a phase</td><td>pohodlie a phase</td></tr>";
        echo "<tr><td><strong>Slogan</strong></td><td>THE NAME OF COMFORT</td><td>sezení a spaní</td><td>sedenie a spanie</td></tr>";
        echo "<tr><td><strong>Číslo</strong></td><td>Číslo reklamace:</td><td>Číslo serv. opravy:</td><td>Číslo reklamácie:</td></tr>";
        echo "<tr><td><strong>Místo</strong></td><td>Místo reklamace</td><td>Místo servisní opravy</td><td>Miesto reklamácie</td></tr>";
        echo "</table>";

        echo "<a href='?execute=1' class='btn'>▶️ SPUSTIT MIGRACI</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
