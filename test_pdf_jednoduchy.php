<?php
/**
 * JEDNODUCHÝ test patterns - PURE PHP
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

// Extrahovat text z PDF pomocí PHP
function extrahovatTextZPDF($pdfCesta) {
    $pdfBinary = file_get_contents($pdfCesta);
    $base64 = base64_encode($pdfBinary);

    // Spustit Python nebo jiný nástroj? NE - použiju existující test_pdf_parsing.php funkci
    // Nebo použiju Base64 textový soubor pokud existuje

    return null; // Vrátím null, použiju přímo Base64 text soubory
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test Patterns</title>";
echo "<style>
body { font-family: monospace; background: #1e1e1e; color: #d4d4d4; padding: 20px; }
h1 { color: #4ec9b0; }
h2 { color: #dcdcaa; }
table { width: 100%; border-collapse: collapse; margin: 10px 0; }
th, td { padding: 8px; border: 1px solid #3e3e3e; text-align: left; }
th { background: #264f78; }
.ok { color: #4ec9b0; }
.err { color: #f48771; }
pre { background: #252526; padding: 10px; overflow-x: auto; max-height: 200px; }
</style></head><body>";

echo "<h1>🔍 Test Patterns - Jednoduchý</h1>";

try {
    $pdo = getDbConnection();

    // Načíst text přímo z kopíruj-vlož testu (víme že tam fungoval!)
    // NATUZZI PROTOKOL text
    $testTexty = [
        'NATUZZI PROTOKOL (Osnice)' => 'Čislo reklamace: NCE25-00002444-39 NCE25-00002444-39/CZ785-2025 Datum podání: 12.11.2025 Číslo objednávky: Číslo faktury: Datum vyhotovení: 25250206 12.11.2025 0 Jméno a příjmení: Stát: Česko PSČ: 25242 Město: Osnice Adresa: Na Blatech 396 Jméno společnosti: Petr Kmoch Poschodí: Rodinný dům Panelový dům Místo reklamace kmochova@petrisk.cz Telefon: 725 387 868',
    ];

    // Načíst konfigurace
    $stmt = $pdo->query("SELECT * FROM wgs_pdf_parser_configs WHERE aktivni = 1 AND nazev LIKE '%NATUZZI%' LIMIT 1");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$config) {
        die("Konfigurace nenalezena!");
    }

    echo "<h2>Konfigurace: " . htmlspecialchars($config['nazev']) . "</h2>";

    $patterns = json_decode($config['regex_patterns'], true);
    $mapping = json_decode($config['pole_mapping'], true);

    if (!$patterns) {
        die("Chyba JSON decode: " . json_last_error_msg());
    }

    foreach ($testTexty as $nazev => $text) {
        echo "<h2>📄 {$nazev}</h2>";
        echo "<h3>Text:</h3><pre>" . htmlspecialchars(substr($text, 0, 500)) . "</pre>";

        echo "<table>";
        echo "<tr><th>Klíč</th><th>Pattern</th><th>Výsledek</th></tr>";

        foreach ($patterns as $klic => $pattern) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($klic) . "</strong></td>";
            echo "<td><code>" . htmlspecialchars(substr($pattern, 0, 60)) . "...</code></td>";

            try {
                $match = @preg_match($pattern, $text, $matches);

                if ($match === false) {
                    echo "<td class='err'>❌ REGEX ERROR: " . error_get_last()['message'] . "</td>";
                } elseif ($match === 1) {
                    $hodnota = isset($matches[1]) ? htmlspecialchars(trim($matches[1])) : '';
                    echo "<td class='ok'>✅ \"" . substr($hodnota, 0, 50) . "\"</td>";
                } else {
                    echo "<td class='err'>❌ NENALEZENO</td>";
                }
            } catch (Exception $e) {
                echo "<td class='err'>❌ " . htmlspecialchars($e->getMessage()) . "</td>";
            }

            echo "</tr>";
        }

        echo "</table>";
    }

} catch (Exception $e) {
    echo "<p class='err'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
