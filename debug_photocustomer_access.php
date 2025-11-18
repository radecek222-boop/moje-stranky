<?php
/**
 * DEBUG PHOTOCUSTOMER ACCESS
 *
 * Tento skript SIMULUJE přístup k photocustomer.php a ZOBRAZÍ KAŽDÝ KROK
 * Ukáže přesně, kde a proč dochází k redirectu
 */

require_once "init.php";

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html lang='cs'><head><meta charset='UTF-8'><title>Debug Photocustomer Access</title>";
echo "<style>
body { font-family: monospace; background: #1a1a1a; color: #00ff88; padding: 20px; line-height: 1.6; }
.step { background: #2a2a2a; padding: 15px; margin: 10px 0; border-left: 4px solid #00ff88; border-radius: 5px; }
.ok { color: #00ff88; font-weight: bold; }
.error { color: #ff4444; font-weight: bold; }
.warning { color: #ffaa00; font-weight: bold; }
h1 { color: #00ff88; }
h2 { color: #ffaa00; margin-top: 30px; }
pre { background: #000; padding: 10px; border-radius: 5px; overflow-x: auto; }
.code { background: #000; padding: 2px 5px; border-radius: 3px; }
</style></head><body>";

echo "<h1>🔍 DEBUG: Simulace přístupu k photocustomer.php</h1>";
echo "<p>Datum: " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// ========================================
// KROK 1: Zkontrolovat aktuální session
// ========================================
echo "<h2>KROK 1: Kontrola aktuální session</h2>";
echo "<div class='step'>";
echo "<strong>$_SESSION obsah:</strong><pre>";
print_r($_SESSION);
echo "</pre>";

$hasUserId = isset($_SESSION['user_id']);
$userId = $_SESSION['user_id'] ?? null;
$rawRole = (string) ($_SESSION['role'] ?? '');
$normalizedRole = strtolower(trim($rawRole));
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

echo "<p><span class='code'>user_id isset:</span> " . ($hasUserId ? "<span class='ok'>✅ TRUE</span>" : "<span class='error'>❌ FALSE</span>") . "</p>";
echo "<p><span class='code'>user_id hodnota:</span> " . ($userId !== null ? htmlspecialchars($userId) : "<span class='error'>NULL</span>") . "</p>";
echo "<p><span class='code'>role:</span> '" . htmlspecialchars($rawRole) . "'</p>";
echo "<p><span class='code'>is_admin:</span> " . ($isAdmin ? "<span class='ok'>TRUE</span>" : "<span class='error'>FALSE</span>") . "</p>";
echo "</div>";

// ========================================
// KROK 2: Simulace kontroly z photocustomer.php
// ========================================
echo "<h2>KROK 2: Simulace logiky photocustomer.php</h2>";

// KROK 2.1: Kontrola user_id
echo "<div class='step'>";
echo "<strong>2.1: Kontrola isset(\$_SESSION['user_id'])</strong><br>";
if (!$hasUserId) {
    echo "<span class='error'>❌ FAIL: user_id není nastaveno!</span><br>";
    echo "<span class='warning'>→ REDIRECT NA: login.php?redirect=photocustomer.php</span>";
    echo "</div>";

    echo "<h2 class='error'>❌ VÝSLEDEK: REDIRECT NA LOGIN (chybí user_id)</h2>";
    echo "<p>Photocustomer.php by mělo redirectovat na login už na řádku 6-9.</p>";
    goto end;
}
echo "<span class='ok'>✅ PASS: user_id je nastaveno (hodnota: {$userId})</span>";
echo "</div>";

// KROK 2.2: Kontrola role
echo "<div class='step'>";
echo "<strong>2.2: Kontrola role (admin nebo technik)</strong><br>";

$technikKeywords = ['technik', 'technician'];
$isTechnik = in_array($normalizedRole, $technikKeywords, true);

echo "<p>Zkouším exact match: " . ($isTechnik ? "<span class='ok'>✅ MATCH</span>" : "<span class='warning'>❌ NO MATCH</span>") . "</p>";

if (!$isTechnik) {
    echo "<p>Zkouším partial match...</p>";
    foreach ($technikKeywords as $keyword) {
        $pos = strpos($normalizedRole, $keyword);
        echo "<p>  - strpos('{$normalizedRole}', '{$keyword}'): ";
        if ($pos !== false) {
            echo "<span class='ok'>✅ FOUND at position {$pos}</span></p>";
            $isTechnik = true;
            break;
        } else {
            echo "<span class='error'>❌ NOT FOUND</span></p>";
        }
    }
}

echo "<p><strong>Finální hodnoty:</strong></p>";
echo "<p><span class='code'>\$isAdmin:</span> " . ($isAdmin ? "<span class='ok'>TRUE</span>" : "<span class='error'>FALSE</span>") . "</p>";
echo "<p><span class='code'>\$isTechnik:</span> " . ($isTechnik ? "<span class='ok'>TRUE</span>" : "<span class='error'>FALSE</span>") . "</p>";
echo "<p><span class='code'>(!isAdmin && !isTechnik):</span> " . ((!$isAdmin && !$isTechnik) ? "<span class='error'>TRUE (redirect!)</span>" : "<span class='ok'>FALSE (pass)</span>") . "</p>";

if (!$isAdmin && !$isTechnik) {
    echo "<span class='error'>❌ FAIL: Uživatel není admin ani technik!</span><br>";
    echo "<span class='warning'>→ REDIRECT NA: login.php?redirect=photocustomer.php</span>";
    echo "</div>";

    echo "<h2 class='error'>❌ VÝSLEDEK: REDIRECT NA LOGIN (uživatel není admin/technik)</h2>";
    echo "<p>Photocustomer.php by mělo redirectovat na login na řádku 33-42.</p>";
    goto end;
}

echo "<span class='ok'>✅ PASS: Uživatel je " . ($isAdmin ? "admin" : "technik") . "</span>";
echo "</div>";

// ========================================
// VÝSLEDEK
// ========================================
echo "<h2 class='ok'>✅ VÝSLEDEK: PŘÍSTUP BY MĚL BÝT POVOLEN!</h2>";
echo "<div class='step'>";
echo "<p>Všechny kontroly prošly úspěšně.</p>";
echo "<p><strong>Uživatel by měl mít přístup k photocustomer.php!</strong></p>";
echo "</div>";

echo "<h2>❓ CO DĚLAT TERAZ?</h2>";
echo "<div class='step'>";
echo "<p>1. Pokud photocustomer.php <strong>STÁLE redirectuje na login</strong>, znamená to:</p>";
echo "<ul>";
echo "<li><span class='error'>MOŽNOST A:</span> Na produkci je <strong>STARÁ VERZE</strong> photocustomer.php (deployment selhal)</li>";
echo "<li><span class='error'>MOŽNOST B:</span> Mezi touto diagnostikou a photocustomer.php se <strong>ZTRATÍ SESSION</strong></li>";
echo "<li><span class='error'>MOŽNOST C:</span> V photocustomer.php je <strong>JINÁ KONTROLA</strong>, kterou jsme přehlédli</li>";
echo "</ul>";
echo "<p>2. <strong>DALŠÍ KROKY:</strong></p>";
echo "<ul>";
echo "<li>Zkontroluj soubor na produkci: <code>check_photocustomer_version.php</code></li>";
echo "<li>Zkus otevřít: <code>photocustomer.php?debug=1</code> (pokud přidám debug režim)</li>";
echo "</ul>";
echo "</div>";

end:

echo "<hr>";
echo "<p><a href='test_photocustomer_session.php' style='color: #00ff88;'>← Zpět na test_photocustomer_session.php</a></p>";
echo "<p><a href='photocustomer.php' style='color: #00ff88;'>→ Zkusit otevřít photocustomer.php</a></p>";
echo "<p><a href='seznam.php' style='color: #00ff88;'>→ Otevřít seznam.php</a></p>";

echo "</body></html>";
?>
