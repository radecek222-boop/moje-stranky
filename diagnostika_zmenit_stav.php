<?php
/**
 * Diagnostika pro API zmenit_stav.php
 * Zjistí přesně které sloupce chybí a opraví je
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika zmenit_stav API</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #fff; padding: 20px; }
        .ok { color: #39ff14; }
        .error { color: #ff4444; }
        .warn { color: #ffaa00; }
        pre { background: #2a2a2a; padding: 15px; border-radius: 5px; overflow-x: auto; }
        h2 { border-bottom: 1px solid #444; padding-bottom: 10px; }
        .btn { display: inline-block; padding: 10px 20px; background: #39ff14; color: #000;
               text-decoration: none; border-radius: 5px; margin: 10px 0; font-weight: bold; }
        .btn:hover { background: #2dd10f; }
    </style>
</head>
<body>
<h1>Diagnostika zmenit_stav API</h1>";

try {
    $pdo = getDbConnection();
    echo "<p class='ok'>Pripojeni k databazi OK</p>";

    // === TABULKA wgs_nabidky ===
    echo "<h2>Tabulka: wgs_nabidky</h2>";

    $pozadovaneNabidky = ['id', 'stav', 'cekame_nd_at', 'zakaznik_email', 'vytvoreno_at'];
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky");
    $existujiciNabidky = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existujiciNabidky[] = $row['Field'];
    }

    echo "<pre>";
    echo "Pozadovane sloupce:\n";
    $chybiNabidky = [];
    foreach ($pozadovaneNabidky as $col) {
        $exists = in_array($col, $existujiciNabidky);
        echo "  {$col}: " . ($exists ? "<span class='ok'>OK</span>" : "<span class='error'>CHYBI!</span>") . "\n";
        if (!$exists) {
            $chybiNabidky[] = $col;
        }
    }
    echo "</pre>";

    // === TABULKA wgs_reklamace ===
    echo "<h2>Tabulka: wgs_reklamace</h2>";

    $pozadovaneReklamace = ['id', 'stav', 'cislo', 'email', 'updated_at'];
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    $existujiciReklamace = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $existujiciReklamace[] = $row['Field'];
    }

    echo "<pre>";
    echo "Pozadovane sloupce:\n";
    $chybiReklamace = [];
    foreach ($pozadovaneReklamace as $col) {
        $exists = in_array($col, $existujiciReklamace);
        echo "  {$col}: " . ($exists ? "<span class='ok'>OK</span>" : "<span class='error'>CHYBI!</span>") . "\n";
        if (!$exists) {
            $chybiReklamace[] = $col;
        }
    }
    echo "</pre>";

    // === KONTROLA ENUM wgs_nabidky.stav ===
    echo "<h2>ENUM hodnoty: wgs_nabidky.stav</h2>";

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky WHERE Field = 'stav'");
    $stavCol = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($stavCol) {
        echo "<pre>";
        echo "Typ: {$stavCol['Type']}\n";
        // Extrahovat ENUM hodnoty
        preg_match("/enum\((.+)\)/i", $stavCol['Type'], $matches);
        if ($matches) {
            $enumValues = str_getcsv($matches[1], ',', "'");
            echo "Povolene hodnoty:\n";
            $potrebneStavy = ['odeslana', 'potvrzena'];
            foreach ($enumValues as $val) {
                echo "  - {$val}\n";
            }
            echo "\nKontrola potrebnych hodnot:\n";
            foreach ($potrebneStavy as $stav) {
                $exists = in_array($stav, $enumValues);
                echo "  '{$stav}': " . ($exists ? "<span class='ok'>OK</span>" : "<span class='error'>CHYBI v ENUM!</span>") . "\n";
            }
        }
        echo "</pre>";
    }

    // === KONTROLA ENUM wgs_reklamace.stav ===
    echo "<h2>ENUM hodnoty: wgs_reklamace.stav</h2>";

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace WHERE Field = 'stav'");
    $stavCol = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($stavCol) {
        echo "<pre>";
        echo "Typ: {$stavCol['Type']}\n";
        preg_match("/enum\((.+)\)/i", $stavCol['Type'], $matches);
        if ($matches) {
            $enumValues = str_getcsv($matches[1], ',', "'");
            echo "Povolene hodnoty:\n";
            foreach ($enumValues as $val) {
                echo "  - {$val}\n";
            }
            $potrebneStavy = ['wait', 'open', 'done'];
            echo "\nKontrola potrebnych hodnot:\n";
            foreach ($potrebneStavy as $stav) {
                $exists = in_array($stav, $enumValues);
                echo "  '{$stav}': " . ($exists ? "<span class='ok'>OK</span>" : "<span class='error'>CHYBI v ENUM!</span>") . "\n";
            }
        }
        echo "</pre>";
    }

    // === TEST DOTAZU ===
    echo "<h2>Test SQL dotazu</h2>";
    echo "<pre>";

    // Test 1: SELECT z wgs_nabidky
    echo "Test 1: SELECT id, stav FROM wgs_nabidky LIMIT 1\n";
    try {
        $stmt = $pdo->query("SELECT id, stav FROM wgs_nabidky LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<span class='ok'>OK</span> - " . json_encode($row) . "\n\n";
    } catch (PDOException $e) {
        echo "<span class='error'>CHYBA: {$e->getMessage()}</span>\n\n";
    }

    // Test 2: SELECT s cekame_nd_at
    echo "Test 2: SELECT id, stav, cekame_nd_at FROM wgs_nabidky LIMIT 1\n";
    try {
        $stmt = $pdo->query("SELECT id, stav, cekame_nd_at FROM wgs_nabidky LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<span class='ok'>OK</span> - " . json_encode($row) . "\n\n";
    } catch (PDOException $e) {
        echo "<span class='error'>CHYBA: {$e->getMessage()}</span>\n\n";
    }

    // Test 3: UPDATE wgs_nabidky stav
    echo "Test 3: UPDATE wgs_nabidky SET stav = 'potvrzena' WHERE 1=0 (bez zmeny dat)\n";
    try {
        $stmt = $pdo->exec("UPDATE wgs_nabidky SET stav = 'potvrzena' WHERE 1=0");
        echo "<span class='ok'>OK</span>\n\n";
    } catch (PDOException $e) {
        echo "<span class='error'>CHYBA: {$e->getMessage()}</span>\n\n";
    }

    // Test 4: UPDATE wgs_reklamace
    echo "Test 4: UPDATE wgs_reklamace SET stav = 'wait' WHERE 1=0 (bez zmeny dat)\n";
    try {
        $stmt = $pdo->exec("UPDATE wgs_reklamace SET stav = 'wait' WHERE 1=0");
        echo "<span class='ok'>OK</span>\n\n";
    } catch (PDOException $e) {
        echo "<span class='error'>CHYBA: {$e->getMessage()}</span>\n\n";
    }

    // Test 5: UPDATE s updated_at
    echo "Test 5: UPDATE wgs_reklamace SET stav = 'wait', updated_at = NOW() WHERE 1=0\n";
    try {
        $stmt = $pdo->exec("UPDATE wgs_reklamace SET stav = 'wait', updated_at = NOW() WHERE 1=0");
        echo "<span class='ok'>OK</span>\n\n";
    } catch (PDOException $e) {
        echo "<span class='error'>CHYBA: {$e->getMessage()}</span>\n\n";
    }

    echo "</pre>";

    // === SOUHRN ===
    echo "<h2>Souhrn</h2>";
    if (empty($chybiNabidky) && empty($chybiReklamace)) {
        echo "<p class='ok'>Vsechny potrebne sloupce existuji. Problem musi byt nekde jinde.</p>";
    } else {
        echo "<p class='error'>Chybi sloupce!</p>";
        if (!empty($chybiNabidky)) {
            echo "<p class='error'>wgs_nabidky: " . implode(', ', $chybiNabidky) . "</p>";
        }
        if (!empty($chybiReklamace)) {
            echo "<p class='error'>wgs_reklamace: " . implode(', ', $chybiReklamace) . "</p>";
        }
    }

    echo "<p><a href='/seznam.php' class='btn'>Zpet na Seznam</a></p>";

} catch (Exception $e) {
    echo "<p class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
