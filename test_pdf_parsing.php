<?php
/**
 * DEBUG: Testování PDF parsování
 *
 * Tento skript ukazuje co API vrací pro NATUZZI PDF
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>DEBUG: PDF Parsování</title>
    <style>
        body { font-family: 'Courier New', monospace; max-width: 1400px;
               margin: 20px auto; padding: 20px; background: #1e1e1e; color: #d4d4d4; }
        h1 { color: #4ec9b0; border-bottom: 2px solid #4ec9b0; padding-bottom: 10px; }
        h2 { color: #dcdcaa; margin-top: 30px; }
        .section { background: #252526; padding: 20px; border-radius: 5px;
                   margin: 20px 0; border-left: 4px solid #007acc; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        pre { background: #1e1e1e; padding: 15px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #3e3e3e; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #3e3e3e; }
        th { background: #264f78; color: white; }
        tr:hover { background: #2d2d30; }
        .highlight { background: #264f78; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 DEBUG: PDF Parsování - NATUZZI</h1>";

    // 1. ZOBRAZIT AKTUÁLNÍ KONFIGURACE
    echo "<div class='section'>";
    echo "<h2>📊 1. Konfigurace v databázi</h2>";

    $stmt = $pdo->query("
        SELECT
            config_id,
            nazev,
            zdroj,
            aktivni,
            priorita,
            regex_patterns,
            pole_mapping,
            detekce_pattern
        FROM wgs_pdf_parser_configs
        WHERE zdroj IN ('natuzzi', 'phase')
        ORDER BY zdroj
    ");
    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($configs as $config) {
        echo "<h3 class='success'>► {$config['nazev']} (ID: {$config['config_id']})</h3>";
        echo "<p><strong>Aktivní:</strong> " . ($config['aktivni'] ? '✓ ANO' : '✗ NE') . "</p>";
        echo "<p><strong>Priorita:</strong> {$config['priorita']}</p>";

        $patterns = json_decode($config['regex_patterns'], true);
        $mapping = json_decode($config['pole_mapping'], true);

        echo "<h4>Pattern pro ulici:</h4>";
        echo "<pre class='highlight'>" . htmlspecialchars($patterns['ulice'] ?? 'NENÍ') . "</pre>";

        echo "<h4>Pole mapping pro ulici:</h4>";
        echo "<pre class='highlight'>" . htmlspecialchars($mapping['ulice'] ?? 'NENÍ') . "</pre>";

        echo "<details><summary>Všechny patterns (klikněte pro rozbalení)</summary>";
        echo "<pre>" . htmlspecialchars(json_encode($patterns, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        echo "</details>";

        echo "<details><summary>Pole mapping (klikněte pro rozbalení)</summary>";
        echo "<pre>" . htmlspecialchars(json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        echo "</details>";
    }
    echo "</div>";

    // 2. TESTOVACÍ TEXT Z NATUZZI PDF
    echo "<div class='section'>";
    echo "<h2>📄 2. Testovací text z NATUZZI PDF</h2>";

    $testText = "Čislo reklamace: NCE25-00002444-39 NCE25-00002444-39/CZ785-2025 Datum podání: 12.11.2025 Číslo objednávky: Číslo faktury: Datum vyhotovení: 25250206 12.11.2025 0 Jméno a příjmení: Stát: Česko PSČ: 25242 Město: Osnice Adresa: Na Blatech 396 Jméno společnosti: Petr Kmoch Poschodí: Rodinný dům Panelový dům Místo reklamace kmochova@petrisk.cz Telefon: 725 387 868";

    echo "<pre>" . htmlspecialchars($testText) . "</pre>";
    echo "</div>";

    // 3. TESTOVAT PATTERN NA TOMTO TEXTU
    echo "<div class='section'>";
    echo "<h2>🧪 3. Test patternu na textu</h2>";

    foreach ($configs as $config) {
        echo "<h3>{$config['nazev']}</h3>";

        $patterns = json_decode($config['regex_patterns'], true);
        $ulicePattern = $patterns['ulice'] ?? null;

        if (!$ulicePattern) {
            echo "<p class='error'>❌ Pattern pro ulici NEEXISTUJE!</p>";
            continue;
        }

        echo "<p><strong>Pattern:</strong> <code>" . htmlspecialchars($ulicePattern) . "</code></p>";

        if (preg_match($ulicePattern, $testText, $matches)) {
            echo "<p class='success'>✅ PATTERN NAŠEL MATCH!</p>";
            echo "<p><strong>Zachycená hodnota:</strong> <span class='highlight'>" . htmlspecialchars($matches[1] ?? '') . "</span></p>";
            echo "<details><summary>Všechny matches</summary>";
            echo "<pre>" . print_r($matches, true) . "</pre>";
            echo "</details>";
        } else {
            echo "<p class='error'>❌ PATTERN NENAŠEL ŽÁDNÝ MATCH!</p>";
            echo "<p class='warning'>Možné příčiny:</p>";
            echo "<ul>";
            echo "<li>Pattern je špatně napsaný</li>";
            echo "<li>Text neobsahuje očekávaný formát</li>";
            echo "<li>Escapování je chybné (\\s vs \\\\s v JSON)</li>";
            echo "</ul>";
        }
    }
    echo "</div>";

    // 4. SIMULOVAT CO API VRÁTÍ
    echo "<div class='section'>";
    echo "<h2>📡 4. Simulace API odpovědi</h2>";

    require_once __DIR__ . '/api/parse_povereni_pdf.php';

    // Načíst konfigurace
    $configs = nactiAktivniKonfigurace($pdo);

    if (!empty($configs)) {
        $nejlepsiConfig = $configs[0]; // Použít první (nejvyšší priorita)
        echo "<p><strong>Použitá konfigurace:</strong> {$nejlepsiConfig['nazev']}</p>";

        $extrahovanaData = parsujPodleKonfigurace($testText, $nejlepsiConfig);

        echo "<h3>Data která API vrátí:</h3>";
        echo "<pre>" . json_encode($extrahovanaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

        if (isset($extrahovanaData['ulice']) && $extrahovanaData['ulice']) {
            echo "<p class='success'>✅ Pole 'ulice' je vyplněno: <strong>" . htmlspecialchars($extrahovanaData['ulice']) . "</strong></p>";
        } else {
            echo "<p class='error'>❌ Pole 'ulice' NENÍ vyplněno!</p>";
        }
    }
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='section'><p class='error'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</p></div>";
}

echo "</body></html>";
?>
