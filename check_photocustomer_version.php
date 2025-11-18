<?php
/**
 * CHECK PHOTOCUSTOMER VERSION
 *
 * Zkontroluje, jestli je na produkci NOVÁ verze photocustomer.php
 * s kontrolou user_id
 */

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html lang='cs'><head><meta charset='UTF-8'><title>Check Photocustomer Version</title>";
echo "<style>
body { font-family: monospace; background: #1a1a1a; color: #00ff88; padding: 20px; line-height: 1.6; }
.ok { color: #00ff88; font-weight: bold; }
.error { color: #ff4444; font-weight: bold; }
.warning { color: #ffaa00; font-weight: bold; }
h1 { color: #00ff88; }
pre { background: #000; padding: 15px; border-radius: 5px; overflow-x: auto; }
.code-block { background: #2a2a2a; padding: 15px; margin: 10px 0; border-left: 4px solid #00ff88; border-radius: 5px; }
</style></head><body>";

echo "<h1>🔍 KONTROLA VERZE photocustomer.php NA PRODUKCI</h1>";
echo "<p>Datum: " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

$photoCustomerPath = __DIR__ . '/photocustomer.php';

if (!file_exists($photoCustomerPath)) {
    echo "<p class='error'>❌ CHYBA: photocustomer.php NEEXISTUJE!</p>";
    echo "<p>Cesta: " . htmlspecialchars($photoCustomerPath) . "</p>";
    exit;
}

echo "<p class='ok'>✅ Soubor photocustomer.php nalezen</p>";
echo "<p>Cesta: <code>" . htmlspecialchars($photoCustomerPath) . "</code></p>";
echo "<p>Velikost: " . filesize($photoCustomerPath) . " bytů</p>";
echo "<p>Poslední změna: " . date('Y-m-d H:i:s', filemtime($photoCustomerPath)) . "</p>";

echo "<hr>";

// Přečíst první 50 řádků
echo "<h2>📄 PRVNÍCH 50 ŘÁDKŮ SOUBORU:</h2>";
$lines = file($photoCustomerPath, FILE_IGNORE_NEW_LINES);
$first50 = array_slice($lines, 0, 50);

echo "<pre>";
foreach ($first50 as $i => $line) {
    $lineNum = $i + 1;
    echo sprintf("%3d: %s\n", $lineNum, htmlspecialchars($line));
}
echo "</pre>";

echo "<hr>";

// Hledat klíčové řetězce
echo "<h2>🔍 KONTROLA KLÍČOVÝCH ŘETĚZCŮ:</h2>";

$checks = [
    'KROK 1: Kontrola, zda je uživatel vůbec přihlášen' => false,
    'if (!isset($_SESSION[\'user_id\']))' => false,
    'KROK 2: Kontrola přístupu - POUZE admin a technik' => false,
    'technikKeywords' => false,
];

$content = file_get_contents($photoCustomerPath);

echo "<div class='code-block'>";
foreach ($checks as $search => $found) {
    $found = (strpos($content, $search) !== false);
    $checks[$search] = $found;

    echo "<p>";
    echo ($found ? "<span class='ok'>✅</span>" : "<span class='error'>❌</span>") . " ";
    echo "<code>" . htmlspecialchars($search) . "</code>";

    if ($found) {
        // Najít číslo řádku
        $lines = explode("\n", $content);
        foreach ($lines as $num => $line) {
            if (strpos($line, $search) !== false) {
                echo " <span class='warning'>(řádek " . ($num + 1) . ")</span>";
                break;
            }
        }
    }
    echo "</p>";
}
echo "</div>";

echo "<hr>";

// VÝSLEDEK
$allFound = !in_array(false, $checks, true);

if ($allFound) {
    echo "<h2 class='ok'>✅ VÝSLEDEK: NOVÝ KÓD JE NA PRODUKCI!</h2>";
    echo "<p>Všechny klíčové řetězce byly nalezeny.</p>";
    echo "<p><strong>Photocustomer.php obsahuje NOVOU logiku s kontrolou user_id.</strong></p>";

    echo "<h3 class='warning'>❓ PROČ TEDY STÁLE REDIRECTUJE NA LOGIN?</h3>";
    echo "<div class='code-block'>";
    echo "<p>Možné příčiny:</p>";
    echo "<ul>";
    echo "<li><strong>Session se ztrácí:</strong> Mezi kliknutím na 'Zahájit návštěvu' a otevřením photocustomer.php se ztratí session</li>";
    echo "<li><strong>Cookie problém:</strong> Browser neuloží session cookie správně</li>";
    echo "<li><strong>Redirect loop:</strong> Některý redirect resetuje session</li>";
    echo "<li><strong>Cache:</strong> Browser má v cache starou verzi stránky</li>";
    echo "</ul>";

    echo "<p><strong>CO ZKUSIT:</strong></p>";
    echo "<ol>";
    echo "<li>Vymazat cache prohlížeče (Ctrl+Shift+Del)</li>";
    echo "<li>Zkusit v Incognito režimu</li>";
    echo "<li>Otevřít <code>photocustomer.php</code> PŘÍMO (ne přes 'Zahájit návštěvu')</li>";
    echo "<li>Zkontrolovat Network tab v Developer Tools (F12) - sledovat redirecty</li>";
    echo "</ol>";
    echo "</div>";
} else {
    echo "<h2 class='error'>❌ VÝSLEDEK: STARÁ VERZE JE NA PRODUKCI!</h2>";
    echo "<p>Některé klíčové řetězce NEBYLY nalezeny.</p>";
    echo "<p><strong>Deployment SELHAL nebo se soubor nenahrál správně!</strong></p>";

    echo "<h3>🔧 ŘEŠENÍ:</h3>";
    echo "<div class='code-block'>";
    echo "<ol>";
    echo "<li>Mergni Pull Request (pokud ještě není)</li>";
    echo "<li>Počkej na GitHub Actions deployment (zelená fajfka ✅)</li>";
    echo "<li>Zkontroluj tuto stránku znovu</li>";
    echo "</ol>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='debug_photocustomer_access.php' style='color: #00ff88;'>→ Debug Photocustomer Access</a></p>";
echo "<p><a href='test_photocustomer_session.php' style='color: #00ff88;'>→ Test Photocustomer Session</a></p>";
echo "<p><a href='photocustomer.php' style='color: #00ff88;'>→ Zkusit otevřít photocustomer.php</a></p>";

echo "</body></html>";
?>
