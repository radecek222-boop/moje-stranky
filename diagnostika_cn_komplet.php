<?php
/**
 * KOMPLETNÍ DIAGNOSTIKA CN LOGIKY
 * Simuluje přesně to, co dělá JavaScript v seznam.js
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze admin");
}

$pdo = getDbConnection();

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika CN - Kompletní</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #eee; padding: 20px; }
        .box { background: #222; border: 1px solid #444; padding: 15px; margin: 10px 0; border-radius: 5px; }
        .success { border-color: #39ff14; }
        .error { border-color: #ff4444; }
        .warning { border-color: #ff9800; }
        h2 { color: #39ff14; border-bottom: 1px solid #39ff14; padding-bottom: 5px; }
        h3 { color: #ff9800; }
        pre { background: #111; padding: 10px; overflow-x: auto; }
        .match { color: #39ff14; font-weight: bold; }
        .nomatch { color: #ff4444; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #444; padding: 8px; text-align: left; }
        th { background: #333; }
    </style>
</head>
<body>
<h1>Diagnostika CN - Kompletní analýza</h1>
";

// ============================================
// KROK 1: Simulace API emaily_s_nabidkou
// ============================================
echo "<h2>KROK 1: API emaily_s_nabidkou</h2>";
echo "<div class='box'>";

$stmt = $pdo->query("
    SELECT DISTINCT LOWER(zakaznik_email) as email
    FROM wgs_nabidky
    WHERE stav IN ('potvrzena', 'odeslana')
    AND zakaznik_email IS NOT NULL
    AND zakaznik_email != ''
");
$emailySCN = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "<p><strong>SQL dotaz:</strong></p>";
echo "<pre>SELECT DISTINCT LOWER(zakaznik_email) as email
FROM wgs_nabidky
WHERE stav IN ('potvrzena', 'odeslana')
AND zakaznik_email IS NOT NULL
AND zakaznik_email != ''</pre>";

echo "<p><strong>Výsledek (emailySCN pole):</strong></p>";
echo "<pre>" . json_encode($emailySCN, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";

if (empty($emailySCN)) {
    echo "<p class='error'>POZOR: Pole emailySCN je PRÁZDNÉ! Žádná karta nebude oranžová.</p>";
} else {
    echo "<p class='success'>Nalezeno " . count($emailySCN) . " emailů s aktivní CN.</p>";
}
echo "</div>";

// ============================================
// KROK 2: Seznam nabídek - detail
// ============================================
echo "<h2>KROK 2: Všechny nabídky v databázi</h2>";
echo "<div class='box'>";

$stmt = $pdo->query("
    SELECT id, zakaznik_email, stav, created_at
    FROM wgs_nabidky
    ORDER BY created_at DESC
    LIMIT 20
");
$nabidky = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>ID</th><th>Email zákazníka</th><th>Stav</th><th>Vytvořeno</th><th>V emailySCN?</th></tr>";
foreach ($nabidky as $n) {
    $emailLower = strtolower(trim($n['zakaznik_email'] ?? ''));
    $jeVPoli = in_array($emailLower, $emailySCN) ? '<span class="match">ANO</span>' : '<span class="nomatch">NE</span>';
    echo "<tr>";
    echo "<td>{$n['id']}</td>";
    echo "<td>" . htmlspecialchars($n['zakaznik_email']) . "</td>";
    echo "<td>{$n['stav']}</td>";
    echo "<td>{$n['created_at']}</td>";
    echo "<td>{$jeVPoli}</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// ============================================
// KROK 3: Simulace load.php
// ============================================
echo "<h2>KROK 3: Reklamace ze seznam.php (load.php)</h2>";
echo "<div class='box'>";

$stmt = $pdo->query("
    SELECT id, reklamace_id, jmeno, email, stav, termin, cas_navstevy
    FROM wgs_reklamace
    ORDER BY created_at DESC
    LIMIT 20
");
$reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>ID</th><th>Reklamace ID</th><th>Jméno</th><th>Email</th><th>Stav</th><th>Termín</th><th>MA CN?</th><th>BARVA</th></tr>";
foreach ($reklamace as $rec) {
    // Simulace JavaScript logiky
    $zakaznikEmail = strtolower(trim($rec['email'] ?? ''));
    $maCenovouNabidku = $zakaznikEmail && in_array($zakaznikEmail, $emailySCN);

    // appointmentText logika
    $status = $rec['stav'];
    $isDomluvena = in_array($status, ['open', 'DOMLUVENÁ']);
    $appointmentText = '';
    if ($isDomluvena && $rec['termin'] && $rec['cas_navstevy']) {
        $appointmentText = $rec['termin'] . ' ' . $rec['cas_navstevy'];
    }

    // cnClass logika - PŘESNĚ jako v JS
    $cnClass = ($maCenovouNabidku && !$appointmentText) ? 'ma-cenovou-nabidku' : '';

    // Určení barvy
    if ($cnClass) {
        $barva = '<span class="match">ORANŽOVÁ (CN)</span>';
    } elseif ($isDomluvena) {
        $barva = '<span style="color: #2196F3;">MODRÁ (Domluvená)</span>';
    } elseif (in_array($status, ['done', 'HOTOVO'])) {
        $barva = '<span style="color: #4CAF50;">ZELENÁ (Hotovo)</span>';
    } else {
        $barva = '<span style="color: #FFEB3B;">ŽLUTÁ (Čeká)</span>';
    }

    $maCN = $maCenovouNabidku ? '<span class="match">ANO</span>' : '<span class="nomatch">NE</span>';

    echo "<tr>";
    echo "<td>{$rec['id']}</td>";
    echo "<td>" . htmlspecialchars($rec['reklamace_id']) . "</td>";
    echo "<td>" . htmlspecialchars($rec['jmeno']) . "</td>";
    echo "<td>" . htmlspecialchars($rec['email']) . " <small style='color:#666'>(" . htmlspecialchars($zakaznikEmail) . ")</small></td>";
    echo "<td>{$rec['stav']}</td>";
    echo "<td>" . ($rec['termin'] ? $rec['termin'] : '-') . "</td>";
    echo "<td>{$maCN}</td>";
    echo "<td>{$barva}</td>";
    echo "</tr>";
}
echo "</table>";
echo "</div>";

// ============================================
// KROK 4: Simulace JavaScript includes()
// ============================================
echo "<h2>KROK 4: Simulace JavaScript includes()</h2>";
echo "<div class='box'>";

echo "<h3>Test: emailySCN.includes(zakaznikEmail)</h3>";
echo "<pre>";
echo "emailySCN = " . json_encode($emailySCN) . "\n\n";

foreach ($reklamace as $rec) {
    $zakaznikEmail = strtolower(trim($rec['email'] ?? ''));
    $vysledek = in_array($zakaznikEmail, $emailySCN);
    $znak = $vysledek ? '✓' : '✗';
    echo "Reklamace {$rec['id']}: '{$zakaznikEmail}' in emailySCN = " . ($vysledek ? 'TRUE' : 'FALSE') . " {$znak}\n";
}
echo "</pre>";
echo "</div>";

// ============================================
// KROK 5: Kontrola JS souboru
// ============================================
echo "<h2>KROK 5: Kontrola seznam.js</h2>";
echo "<div class='box'>";

$jsPath = __DIR__ . '/assets/js/seznam.js';
if (file_exists($jsPath)) {
    $jsContent = file_get_contents($jsPath);

    // Najít relevantní řádky
    if (preg_match('/const cnClass = .*/', $jsContent, $matches)) {
        echo "<p><strong>cnClass definice v JS:</strong></p>";
        echo "<pre>" . htmlspecialchars($matches[0]) . "</pre>";
    }

    if (preg_match('/emailySCN\.includes.*/', $jsContent, $matches)) {
        echo "<p><strong>emailySCN.includes v JS:</strong></p>";
        echo "<pre>" . htmlspecialchars($matches[0]) . "</pre>";
    }

    // Kontrola zda se načítá API
    if (strpos($jsContent, "api/nabidka_api.php?action=emaily_s_nabidkou") !== false) {
        echo "<p class='success'>API volání emaily_s_nabidkou NALEZENO v JS</p>";
    } else {
        echo "<p class='error'>API volání emaily_s_nabidkou NENALEZENO v JS!</p>";
    }

    // Kontrola zda se aplikuje třída
    if (strpos($jsContent, 'ma-cenovou-nabidku') !== false) {
        echo "<p class='success'>Třída ma-cenovou-nabidku NALEZENA v JS</p>";
    } else {
        echo "<p class='error'>Třída ma-cenovou-nabidku NENALEZENA v JS!</p>";
    }

} else {
    echo "<p class='error'>Soubor seznam.js NEEXISTUJE!</p>";
}
echo "</div>";

// ============================================
// KROK 6: Kontrola session
// ============================================
echo "<h2>KROK 6: Session informace</h2>";
echo "<div class='box'>";
echo "<pre>";
echo "is_admin: " . (isset($_SESSION['is_admin']) ? ($_SESSION['is_admin'] ? 'TRUE' : 'FALSE') : 'NENASTAVENO') . "\n";
echo "user_id: " . ($_SESSION['user_id'] ?? 'NENASTAVENO') . "\n";
echo "user_email: " . ($_SESSION['user_email'] ?? 'NENASTAVENO') . "\n";
echo "role: " . ($_SESSION['role'] ?? 'NENASTAVENO') . "\n";
echo "</pre>";
echo "<p><strong>DŮLEŽITÉ:</strong> API emaily_s_nabidkou vyžaduje is_admin = TRUE!</p>";
echo "<p>Pokud uživatel není admin, API vrátí 403 a emailySCN bude prázdné.</p>";
echo "</div>";

// ============================================
// KROK 7: Test API přímo
// ============================================
echo "<h2>KROK 7: Test API odpovědi</h2>";
echo "<div class='box'>";
echo "<p>Pro test API otevřete v prohlížeči:</p>";
echo "<pre>/api/nabidka_api.php?action=emaily_s_nabidkou</pre>";
echo "<p>Očekávaná odpověď:</p>";
echo "<pre>" . json_encode([
    'status' => 'success',
    'message' => 'Emaily načteny',
    'data' => ['emaily' => $emailySCN]
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
echo "</div>";

echo "<hr>";
echo "<p style='color: #666;'>Diagnostika dokončena: " . date('Y-m-d H:i:s') . "</p>";
echo "</body></html>";
?>
