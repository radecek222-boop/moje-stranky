<?php
/**
 * DIAGNOSTIKA: Sledování typ_zakaznika
 * Kompletní analýza toku dat od formuláře po protokol
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit diagnostiku.");
}

$pdo = getDbConnection();

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika: typ_zakaznika</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 20px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #1e1e1e; color: #d4d4d4; padding: 15px; border-radius: 5px;
              overflow-x: auto; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        .step { background: #333; color: white; padding: 5px 12px; border-radius: 15px;
                font-weight: bold; margin-right: 10px; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; cursor: pointer; border: none; }
        .btn:hover { background: #555; }
    </style>
</head>
<body>
<div class='container'>
    <h1>Diagnostika: typ_zakaznika</h1>
    <p>Kompletní analýza toku dat od formuláře po protokol</p>
</div>";

// =============================================================================
// KROK 1: Kontrola sloupce v databázi
// =============================================================================
echo "<div class='container'>";
echo "<h2><span class='step'>1</span> Kontrola databázového sloupce</h2>";

$stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'typ_zakaznika'");
$column = $stmt->fetch(PDO::FETCH_ASSOC);

if ($column) {
    echo "<div class='success'>";
    echo "<strong>SLOUPEC EXISTUJE</strong><br>";
    echo "Typ: <code>{$column['Type']}</code><br>";
    echo "Null: <code>{$column['Null']}</code><br>";
    echo "Default: <code>" . ($column['Default'] ?? 'NULL') . "</code>";
    echo "</div>";
} else {
    echo "<div class='error'><strong>SLOUPEC NEEXISTUJE!</strong> Spusťte migraci: pridej_typ_zakaznika.php</div>";
}
echo "</div>";

// =============================================================================
// KROK 2: Kontrola save.php
// =============================================================================
echo "<div class='container'>";
echo "<h2><span class='step'>2</span> Kontrola save.php</h2>";

$savePhpPath = __DIR__ . '/app/controllers/save.php';
$savePhpContent = file_get_contents($savePhpPath);

// Hledat čtení typ_zakaznika z POST
$hasPostRead = strpos($savePhpContent, "sanitizeInput(\$_POST['typ_zakaznika']") !== false;
$hasColumnsEntry = strpos($savePhpContent, "'typ_zakaznika' =>") !== false;

echo "<table>";
echo "<tr><th>Kontrola</th><th>Stav</th><th>Detail</th></tr>";

if ($hasPostRead) {
    echo "<tr><td>Čtení z \$_POST</td><td class='success'>OK</td><td><code>\$typZakaznika = sanitizeInput(\$_POST['typ_zakaznika'] ?? '')</code></td></tr>";
} else {
    echo "<tr><td>Čtení z \$_POST</td><td class='error'>CHYBÍ!</td><td>Přidejte řádek do save.php</td></tr>";
}

if ($hasColumnsEntry) {
    echo "<tr><td>Zápis do \$columns</td><td class='success'>OK</td><td><code>'typ_zakaznika' => \$typZakaznika</code></td></tr>";
} else {
    echo "<tr><td>Zápis do \$columns</td><td class='error'>CHYBÍ!</td><td>Přidejte do \$columns pole</td></tr>";
}
echo "</table>";
echo "</div>";

// =============================================================================
// KROK 3: Kontrola novareklamace.js
// =============================================================================
echo "<div class='container'>";
echo "<h2><span class='step'>3</span> Kontrola novareklamace.js</h2>";

$jsPath = __DIR__ . '/assets/js/novareklamace.js';
$jsContent = file_get_contents($jsPath);

$hasCheckboxRead = strpos($jsContent, "objednavkaICO") !== false;
$hasFormDataAppend = strpos($jsContent, "formData.append('typ_zakaznika'") !== false;

echo "<table>";
echo "<tr><th>Kontrola</th><th>Stav</th><th>Detail</th></tr>";

if ($hasCheckboxRead) {
    echo "<tr><td>Čtení checkboxu</td><td class='success'>OK</td><td><code>document.getElementById('objednavkaICO')</code></td></tr>";
} else {
    echo "<tr><td>Čtení checkboxu</td><td class='error'>CHYBÍ!</td><td>Přidejte čtení checkboxu</td></tr>";
}

if ($hasFormDataAppend) {
    echo "<tr><td>Odeslání v FormData</td><td class='success'>OK</td><td><code>formData.append('typ_zakaznika', typZakaznika)</code></td></tr>";
} else {
    echo "<tr><td>Odeslání v FormData</td><td class='error'>CHYBÍ!</td><td>Přidejte append do FormData</td></tr>";
}
echo "</table>";
echo "</div>";

// =============================================================================
// KROK 4: Kontrola protokol.php
// =============================================================================
echo "<div class='container'>";
echo "<h2><span class='step'>4</span> Kontrola protokol.php</h2>";

$protokolPath = __DIR__ . '/protokol.php';
$protokolContent = file_get_contents($protokolPath);

$hasTypZakaznikaInPhp = strpos($protokolContent, "typ_zakaznika") !== false;
$hasInitialReklamaceData = strpos($protokolContent, "initialReklamaceData") !== false;

echo "<table>";
echo "<tr><th>Kontrola</th><th>Stav</th><th>Detail</th></tr>";

if ($hasTypZakaznikaInPhp) {
    echo "<tr><td>Čtení typ_zakaznika</td><td class='success'>OK</td><td>Pole je použito v PHP</td></tr>";
} else {
    echo "<tr><td>Čtení typ_zakaznika</td><td class='warning'>NEPOUŽITO</td><td>Pole není v PHP kódu</td></tr>";
}

if ($hasInitialReklamaceData) {
    echo "<tr><td>initialReklamaceData</td><td class='success'>DEFINOVÁNO</td><td>Proměnná existuje</td></tr>";
} else {
    echo "<tr><td>initialReklamaceData</td><td class='error'>CHYBÍ!</td><td>Proměnná není definována</td></tr>";
}
echo "</table>";
echo "</div>";

// =============================================================================
// KROK 5: Reálná data z databáze
// =============================================================================
echo "<div class='container'>";
echo "<h2><span class='step'>5</span> Posledních 10 reklamací - hodnoty typ_zakaznika</h2>";

$stmt = $pdo->query("
    SELECT
        id,
        reklamace_id,
        jmeno,
        typ_zakaznika,
        created_at
    FROM wgs_reklamace
    ORDER BY id DESC
    LIMIT 10
");
$reklamace = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "<table>";
echo "<tr><th>ID</th><th>Reklamace ID</th><th>Jméno</th><th>typ_zakaznika</th><th>Vytvořeno</th></tr>";

$emptyCount = 0;
foreach ($reklamace as $r) {
    $typZak = $r['typ_zakaznika'];
    $typClass = empty($typZak) ? "style='background:#fff3cd;'" : "style='background:#d4edda;'";
    if (empty($typZak)) $emptyCount++;

    echo "<tr>";
    echo "<td>{$r['id']}</td>";
    echo "<td><code>{$r['reklamace_id']}</code></td>";
    echo "<td>{$r['jmeno']}</td>";
    echo "<td $typClass><strong>" . (empty($typZak) ? '(prázdné)' : htmlspecialchars($typZak)) . "</strong></td>";
    echo "<td>{$r['created_at']}</td>";
    echo "</tr>";
}
echo "</table>";

if ($emptyCount === count($reklamace)) {
    echo "<div class='warning'><strong>VŠECHNY ZÁZNAMY MAJÍ PRÁZDNÝ typ_zakaznika!</strong><br>
    Buď oprava save.php ještě nebyla nasazena, nebo nebyly vytvořeny nové reklamace po opravě.</div>";
}
echo "</div>";

// =============================================================================
// KROK 6: Kontrola protokol.php - jak načítá data
// =============================================================================
echo "<div class='container'>";
echo "<h2><span class='step'>6</span> Analýza protokol.php - načítání dat</h2>";

// Najít jak protokol.php získává data
preg_match('/\$record\s*=\s*(.+?);/s', $protokolContent, $recordMatch);
preg_match('/initialReklamaceData\s*=\s*(.+?);/s', $protokolContent, $initMatch);

echo "<div class='info'>";
echo "<strong>Hledám způsob načítání dat v protokol.php...</strong><br><br>";

// Zkontrolovat zda používá GET parametr
if (strpos($protokolContent, '$_GET[') !== false) {
    preg_match_all('/\$_GET\[[\'"](.*?)[\'"]\]/', $protokolContent, $getParams);
    if (!empty($getParams[1])) {
        echo "GET parametry: <code>" . implode(', ', array_unique($getParams[1])) . "</code><br>";
    }
}

// Najít SQL dotaz
if (preg_match('/SELECT.*FROM\s+wgs_reklamace/is', $protokolContent, $sqlMatch)) {
    echo "SQL dotaz nalezen<br>";
}

echo "</div>";

// Zobrazit relevantní část kódu
echo "<h3>Klíčová část kódu protokol.php:</h3>";

// Najít definici initialReklamaceData
if (preg_match('/(.*initialReklamaceData.*=.*\[[\s\S]*?\];)/m', $protokolContent, $codeMatch)) {
    echo "<pre>" . htmlspecialchars(substr($codeMatch[1], 0, 2000)) . "</pre>";
}
echo "</div>";

// =============================================================================
// KROK 7: Test konkrétního záznamu
// =============================================================================
echo "<div class='container'>";
echo "<h2><span class='step'>7</span> Test načtení konkrétního záznamu</h2>";

$testId = $_GET['test_id'] ?? null;

echo "<form method='get' style='margin-bottom:20px;'>";
echo "<label>Zadejte reklamace_id pro test: </label>";
echo "<input type='text' name='test_id' value='" . htmlspecialchars($testId ?? '') . "' placeholder='např. R2025-0001' style='padding:8px; width:200px;'>";
echo "<button type='submit' class='btn'>Načíst</button>";
echo "</form>";

if ($testId) {
    $stmt = $pdo->prepare("SELECT * FROM wgs_reklamace WHERE reklamace_id = :id");
    $stmt->execute(['id' => $testId]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($record) {
        echo "<div class='success'><strong>Záznam nalezen!</strong></div>";

        echo "<h3>Hodnota typ_zakaznika:</h3>";
        $typZak = $record['typ_zakaznika'] ?? null;
        if (empty($typZak)) {
            echo "<div class='warning'><strong>typ_zakaznika je PRÁZDNÉ nebo NULL</strong></div>";
        } else {
            echo "<div class='success'><strong>typ_zakaznika = \"" . htmlspecialchars($typZak) . "\"</strong></div>";
        }

        echo "<h3>Kompletní záznam (JSON):</h3>";
        echo "<pre>" . htmlspecialchars(json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";

        echo "<h3>URL pro protokol.php:</h3>";
        echo "<a href='protokol.php?id={$record['id']}' target='_blank' class='btn'>Otevřít protokol.php?id={$record['id']}</a>";
        echo "<a href='protokol.php?reklamace_id=" . urlencode($testId) . "' target='_blank' class='btn'>Otevřít protokol.php?reklamace_id=$testId</a>";

    } else {
        echo "<div class='error'><strong>Záznam nenalezen!</strong></div>";
    }
}
echo "</div>";

// =============================================================================
// KROK 8: JavaScript konzolový test
// =============================================================================
echo "<div class='container'>";
echo "<h2><span class='step'>8</span> JavaScript konzolový test</h2>";
echo "<div class='info'>";
echo "<strong>Vložte tento kód do konzole na stránce protokol.php:</strong>";
echo "</div>";
echo "<pre>
// Test: Zkontrolovat initialReklamaceData
console.log('=== DIAGNOSTIKA typ_zakaznika ===');

if (typeof initialReklamaceData !== 'undefined') {
    console.log('initialReklamaceData NALEZENO:');
    console.log('typ_zakaznika:', initialReklamaceData.typ_zakaznika);
    console.log('Kompletní objekt:', initialReklamaceData);
} else {
    console.error('initialReklamaceData NENÍ DEFINOVÁNO!');

    // Zkusit najít v window
    for (let key in window) {
        if (key.toLowerCase().includes('reklamace') || key.toLowerCase().includes('data')) {
            console.log('Nalezeno:', key, '=', window[key]);
        }
    }
}

// Zkontrolovat checkboxy
const icoCheck = document.getElementById('typZakaznikaICO');
const fyzCheck = document.getElementById('typZakaznikaFyzicka');
console.log('Checkbox IČO element:', icoCheck);
console.log('Checkbox Fyzická element:', fyzCheck);
</pre>";
echo "</div>";

// =============================================================================
// KROK 9: Přímý SQL test - stejný jako protokol.php
// =============================================================================
echo "<div class='container'>";
echo "<h2><span class='step'>9</span> Přímý SQL test (stejný jako protokol.php)</h2>";

$testValue = $_GET['sql_test'] ?? 'WGS/2025/02-12/00002';

echo "<form method='get' style='margin-bottom:20px;'>";
echo "<label>Hodnota pro SQL test: </label>";
echo "<input type='text' name='sql_test' value='" . htmlspecialchars($testValue) . "' style='padding:8px; width:400px;'>";
echo "<button type='submit' class='btn'>Spustit SQL</button>";
echo "</form>";

echo "<div class='info'>";
echo "<strong>SQL dotaz (stejný jako v protokol.php):</strong><br>";
echo "<code>SELECT * FROM wgs_reklamace WHERE reklamace_id = :value OR cislo = :value OR id = :value2</code><br>";
echo "<strong>Hodnota:</strong> <code>" . htmlspecialchars($testValue) . "</code>";
echo "</div>";

try {
    $stmt = $pdo->prepare(
        "SELECT id, reklamace_id, cislo, jmeno, typ_zakaznika
         FROM wgs_reklamace
         WHERE reklamace_id = :val1 OR cislo = :val2 OR id = :val3
         LIMIT 1"
    );
    $stmt->execute([':val1' => $testValue, ':val2' => $testValue, ':val3' => $testValue]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($result) {
        echo "<div class='success'>";
        echo "<strong>ZÁZNAM NALEZEN!</strong><br>";
        echo "<pre>" . htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . "</pre>";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<strong>ZÁZNAM NENALEZEN!</strong><br>";
        echo "SQL dotaz nevrátil žádné výsledky.";
        echo "</div>";

        // Zkusit najít podobné záznamy
        echo "<h3>Hledám podobné záznamy...</h3>";
        $stmt2 = $pdo->prepare("SELECT id, reklamace_id, cislo FROM wgs_reklamace WHERE reklamace_id LIKE :pattern LIMIT 5");
        $stmt2->execute([':pattern' => '%' . substr($testValue, 0, 20) . '%']);
        $similar = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        if ($similar) {
            echo "<table>";
            echo "<tr><th>ID</th><th>reklamace_id (HEX)</th><th>cislo</th></tr>";
            foreach ($similar as $row) {
                echo "<tr>";
                echo "<td>{$row['id']}</td>";
                echo "<td>" . htmlspecialchars($row['reklamace_id']) . "<br><small>HEX: " . bin2hex($row['reklamace_id']) . "</small></td>";
                echo "<td>" . htmlspecialchars($row['cislo']) . "</td>";
                echo "</tr>";
            }
            echo "</table>";

            echo "<div class='warning'>";
            echo "<strong>Porovnání HEX:</strong><br>";
            echo "Hledaná hodnota HEX: <code>" . bin2hex($testValue) . "</code>";
            echo "</div>";
        }
    }
} catch (PDOException $e) {
    echo "<div class='error'>SQL Error: " . htmlspecialchars($e->getMessage()) . "</div>";
}
echo "</div>";

echo "<div class='container'>";
echo "<a href='admin.php' class='btn' style='background:#666;'>Zpět do administrace</a>";
echo "<a href='?test_id=" . urlencode($reklamace[0]['reklamace_id'] ?? '') . "' class='btn'>Testovat nejnovější záznam</a>";
echo "<a href='?sql_test=" . urlencode($reklamace[0]['reklamace_id'] ?? '') . "' class='btn'>SQL test nejnovějšího</a>";
echo "</div>";

echo "</body></html>";
?>
