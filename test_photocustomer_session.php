<?php
/**
 * TEST PHOTOCUSTOMER SESSION
 * Diagnostika proč photocustomer.php redirectuje i když session je OK
 */

require_once "init.php";

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html lang='cs'><head><meta charset='UTF-8'><title>Test Photocustomer Session</title>";
echo "<style>body { font-family: monospace; background: #1a1a1a; color: #00ff88; padding: 20px; }";
echo ".ok { color: #00ff88; } .error { color: #ff4444; } .warning { color: #ffaa00; }";
echo "pre { background: #000; padding: 15px; border-radius: 5px; }</style></head><body>";

echo "<h1>🔍 TEST PHOTOCUSTOMER SESSION</h1>";
echo "<p>Datum: " . date('Y-m-d H:i:s') . "</p>";
echo "<hr>";

// KROK 1: Zkontroluj user_id
echo "<h2>✅ KROK 1: Kontrola user_id</h2>";
$hasUserId = isset($_SESSION['user_id']);
echo "<p>isset(\$_SESSION['user_id']): " . ($hasUserId ? '<span class="ok">✅ TRUE</span>' : '<span class="error">❌ FALSE</span>') . "</p>";

if ($hasUserId) {
    echo "<p>\$_SESSION['user_id'] = " . htmlspecialchars($_SESSION['user_id']) . "</p>";
    echo "<p class='ok'>→ KROK 1 PROJDE (uživatel je přihlášen)</p>";
} else {
    echo "<p class='error'>→ KROK 1 SELŽE (photocustomer.php redirectuje na login)</p>";
}

// KROK 2: Zkontroluj roli
echo "<hr><h2>✅ KROK 2: Kontrola role (admin nebo technik)</h2>";

$rawRole = (string) ($_SESSION['role'] ?? '');
$normalizedRole = strtolower(trim($rawRole));
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

echo "<p>\$rawRole: '" . htmlspecialchars($rawRole) . "'</p>";
echo "<p>\$normalizedRole: '" . htmlspecialchars($normalizedRole) . "'</p>";
echo "<p>\$isAdmin: " . ($isAdmin ? '<span class="ok">✅ TRUE</span>' : '<span class="error">❌ FALSE</span>') . "</p>";

// Kontrola technika
$technikKeywords = ['technik', 'technician'];
$isTechnik = in_array($normalizedRole, $technikKeywords, true);

echo "<p>\$isTechnik (exact match): " . ($isTechnik ? '<span class="ok">✅ TRUE</span>' : '<span class="error">❌ FALSE</span>') . "</p>";

if (!$isTechnik) {
    echo "<p class='warning'>→ Zkouším partial match...</p>";
    foreach ($technikKeywords as $keyword) {
        echo "<p>  - strpos('$normalizedRole', '$keyword'): ";
        $pos = strpos($normalizedRole, $keyword);
        if ($pos !== false) {
            echo "<span class='ok'>✅ FOUND at position $pos</span></p>";
            $isTechnik = true;
            break;
        } else {
            echo "<span class='error'>❌ NOT FOUND</span></p>";
        }
    }
}

echo "<p><strong>\$isTechnik (final): " . ($isTechnik ? '<span class="ok">✅ TRUE</span>' : '<span class="error">❌ FALSE</span>') . "</strong></p>";

// FINÁLNÍ VÝSLEDEK
echo "<hr><h2>🎯 FINÁLNÍ VÝSLEDEK</h2>";

$passedStep1 = $hasUserId;
$passedStep2 = $isAdmin || $isTechnik;
$accessGranted = $passedStep1 && $passedStep2;

echo "<p><strong>Krok 1 (user_id):</strong> " . ($passedStep1 ? '<span class="ok">✅ PROJDE</span>' : '<span class="error">❌ NEPROJDE</span>') . "</p>";
echo "<p><strong>Krok 2 (role):</strong> " . ($passedStep2 ? '<span class="ok">✅ PROJDE</span>' : '<span class="error">❌ NEPROJDE</span>') . "</p>";

echo "<hr>";
if ($accessGranted) {
    echo "<h1 class='ok'>✅ PŘÍSTUP K PHOTOCUSTOMER.PHP POVOLEN</h1>";
    echo "<p>Uživatel MŮŽE přistoupit k photocustomer.php</p>";
} else {
    echo "<h1 class='error'>❌ PŘÍSTUP K PHOTOCUSTOMER.PHP ODEPŘEN</h1>";
    echo "<p>Uživatel BUDE přesměrován na login.php</p>";

    if (!$passedStep1) {
        echo "<p class='error'>→ Důvod: Chybí \$_SESSION['user_id']</p>";
    }
    if (!$passedStep2) {
        echo "<p class='error'>→ Důvod: Uživatel není admin ani technik</p>";
        echo "<p class='warning'>→ Řešení: V databázi wgs_users musí mít roli obsahující 'technik' nebo 'technician'</p>";
    }
}

// Dump celé $_SESSION
echo "<hr><h2>📊 Celá \$_SESSION</h2>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

echo "</body></html>";
?>
