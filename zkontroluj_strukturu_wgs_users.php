<?php
/**
 * Diagnostika: Zobrazit strukturu tabulky wgs_users
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Struktura wgs_users</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #00ff00; }
        table { border-collapse: collapse; width: 100%; background: #000; }
        th, td { border: 1px solid #00ff00; padding: 8px; text-align: left; }
        th { background: #003300; }
        .error { color: #ff0000; }
        .success { color: #00ff00; }
    </style>
</head>
<body>";

try {
    $pdo = getDbConnection();

    echo "<h1>Struktura tabulky wgs_users</h1>";

    // SHOW COLUMNS
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
    $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

    foreach ($sloupce as $sloupec) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($sloupec['Field']) . "</strong></td>";
        echo "<td>" . htmlspecialchars($sloupec['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($sloupec['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($sloupec['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($sloupec['Default'] ?? 'NULL') . "</td>";
        echo "<td>" . htmlspecialchars($sloupec['Extra']) . "</td>";
        echo "</tr>";
    }

    echo "</table>";

    // Zkontrolovat Milana Kolína
    echo "<h2>Data Milana Kolína:</h2>";

    $stmt = $pdo->prepare("SELECT * FROM wgs_users WHERE id = 9 LIMIT 1");
    $stmt->execute();
    $milan = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($milan) {
        echo "<table>";
        echo "<tr><th>Sloupec</th><th>Hodnota</th></tr>";
        foreach ($milan as $klic => $hodnota) {
            echo "<tr>";
            echo "<td><strong>" . htmlspecialchars($klic) . "</strong></td>";
            echo "<td>" . ($hodnota ? htmlspecialchars($hodnota) : '<span class="error">PRÁZDNÉ</span>') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<p class='error'>Milan Kolín (ID 9) nenalezen!</p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "</body></html>";
?>
