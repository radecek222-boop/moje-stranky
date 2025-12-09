<?php
/**
 * TEST: Ověření že seznam.js existuje a je čitelný
 */

$jsPath = __DIR__ . '/assets/js/seznam.js';

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Test JS</title></head><body style='background:#1a1a1a;color:#eee;font-family:monospace;padding:20px;'>";

echo "<h1>Test načtení seznam.js</h1>";

// 1. Existuje soubor?
echo "<h2>1. Existence souboru</h2>";
if (file_exists($jsPath)) {
    echo "<p style='color:#39ff14;'>Soubor EXISTUJE: {$jsPath}</p>";
    echo "<p>Velikost: " . filesize($jsPath) . " bytes</p>";
    echo "<p>Poslední změna: " . date('Y-m-d H:i:s', filemtime($jsPath)) . "</p>";
} else {
    echo "<p style='color:#ff4444;'>Soubor NEEXISTUJE!</p>";
}

// 2. Je čitelný?
echo "<h2>2. Čitelnost</h2>";
if (is_readable($jsPath)) {
    echo "<p style='color:#39ff14;'>Soubor je ČITELNÝ</p>";
} else {
    echo "<p style='color:#ff4444;'>Soubor NENÍ čitelný!</p>";
}

// 3. První řádky souboru
echo "<h2>3. Prvních 5 řádků souboru</h2>";
$lines = file($jsPath);
echo "<pre style='background:#111;padding:10px;'>";
for ($i = 0; $i < min(5, count($lines)); $i++) {
    echo htmlspecialchars($lines[$i]);
}
echo "</pre>";

// 4. Test inline JS
echo "<h2>4. Test inline JS (měl by zobrazit alert)</h2>";
echo "<script>console.log('[TEST] Inline JS funguje');</script>";

// 5. Test načtení seznam.js
echo "<h2>5. Test načtení seznam.js</h2>";
echo "<script src='/assets/js/seznam.js?v=" . time() . "'></script>";
echo "<p>Pokud v konzoli vidíte '[SEZNAM.JS] VERZE:...' = funguje</p>";
echo "<p>Pokud ne = soubor se nenačítá</p>";

// 6. Přímý odkaz
echo "<h2>6. Přímý odkaz na soubor</h2>";
echo "<p><a href='/assets/js/seznam.js?v=" . time() . "' target='_blank' style='color:#39ff14;'>Otevřít seznam.js v novém okně</a></p>";
echo "<p>Pokud se otevře JS kód = cesta je správná</p>";
echo "<p>Pokud 404 = cesta je špatná</p>";

echo "</body></html>";
?>
