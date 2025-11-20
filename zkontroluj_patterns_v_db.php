<?php
/**
 * Diagnostic: Zobrazení aktuálních patterns v databázi
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
    <title>Kontrola Patterns v DB</title>
    <style>
        body { font-family: 'Courier New', monospace; background: #1e1e1e;
               color: #d4d4d4; padding: 20px; max-width: 1400px; margin: 0 auto; }
        h1 { color: #4ec9b0; }
        h2 { color: #dcdcaa; margin-top: 30px; }
        .section { background: #252526; padding: 20px; border-radius: 5px;
                   margin: 20px 0; border-left: 4px solid #007acc; }
        pre { background: #1e1e1e; padding: 15px; border-radius: 5px;
              overflow-x: auto; border: 1px solid #3e3e3e; white-space: pre-wrap; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #dcdcaa; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #3e3e3e; }
        th { background: #264f78; }
    </style>
</head>
<body>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Kontrola Patterns v Databázi</h1>";

    // Načíst všechny konfigurace
    $stmt = $pdo->query("
        SELECT config_id, nazev, priorita, aktivni, regex_patterns, pole_mapping
        FROM wgs_pdf_parser_configs
        ORDER BY priorita DESC
    ");

    $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($configs as $config) {
        $aktivni = $config['aktivni'] ? '✅ ANO' : '❌ NE';

        echo "<div class='section'>";
        echo "<h2>{$config['nazev']} (ID: {$config['config_id']})</h2>";
        echo "<p><strong>Aktivní:</strong> {$aktivni} | <strong>Priorita:</strong> {$config['priorita']}</p>";

        // Regex patterns - RAW
        echo "<h3>📋 Regex Patterns (RAW z databáze):</h3>";
        echo "<pre>" . htmlspecialchars(substr($config['regex_patterns'], 0, 1000)) . "...</pre>";

        // Regex patterns - DECODED
        echo "<h3>📋 Regex Patterns (dekódované JSON):</h3>";
        $patterns = json_decode($config['regex_patterns'], true);

        if ($patterns) {
            echo "<table>";
            echo "<tr><th>Klíč</th><th>Pattern</th><th>Test</th></tr>";

            // Test na vzorkovém textu
            $testText = "Čislo reklamace: NCE25-00002444-39 NCE25-00002444-39/CZ785-2025 Datum podání: 12.11.2025 Číslo objednávky: Číslo faktury: Datum vyhotovení: 25250206 12.11.2025 0 Jméno a příjmení: Stát: Česko PSČ: 25242 Město: Osnice Adresa: Na Blatech 396 Jméno společnosti: Petr Kmoch Poschodí: Rodinný dům Panelový dům Místo reklamace kmochova@petrisk.cz Telefon: 725 387 868";

            foreach ($patterns as $key => $pattern) {
                $testResult = '<span class="error">❌ NEFUNGUJE</span>';
                $matchValue = '';

                try {
                    if (preg_match($pattern, $testText, $matches)) {
                        $matchValue = isset($matches[1]) ? htmlspecialchars($matches[1]) : '';
                        $testResult = '<span class="success">✅ "' . $matchValue . '"</span>';
                    }
                } catch (Exception $e) {
                    $testResult = '<span class="error">⚠️ CHYBA: ' . htmlspecialchars($e->getMessage()) . '</span>';
                }

                echo "<tr>";
                echo "<td><strong>{$key}</strong></td>";
                echo "<td><code>" . htmlspecialchars(substr($pattern, 0, 100)) . "...</code></td>";
                echo "<td>{$testResult}</td>";
                echo "</tr>";
            }

            echo "</table>";
        } else {
            echo "<p class='error'>❌ CHYBA: Nelze dekódovat JSON! " . json_last_error_msg() . "</p>";
        }

        // Pole mapping
        echo "<h3>🔗 Pole Mapping:</h3>";
        $mapping = json_decode($config['pole_mapping'], true);

        if ($mapping) {
            echo "<pre>" . print_r($mapping, true) . "</pre>";
        } else {
            echo "<p class='error'>❌ CHYBA: Nelze dekódovat JSON! " . json_last_error_msg() . "</p>";
        }

        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='section'>";
    echo "<p class='error'>❌ CHYBA: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "</div>";
}

echo "<p><a href='admin.php' style='color: #4ec9b0;'>← Zpět do Admin</a></p>";
echo "</body></html>";
?>
