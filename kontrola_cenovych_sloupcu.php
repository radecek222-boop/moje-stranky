<?php
/**
 * Rychlá kontrola, jestli byly cenové sloupce přidány
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Kontrola cenových sloupců</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .success { color: green; font-weight: bold; }
        .error { color: red; font-weight: bold; }
        table { border-collapse: collapse; background: white; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #2D5016; color: white; }
    </style>
</head>
<body>
<h1>Kontrola cenových sloupců v tabulce wgs_reklamace</h1>
";

try {
    $pdo = getDbConnection();

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace WHERE Field IN ('pocet_dilu', 'cena_prace', 'cena_material', 'cena_druhy_technik', 'cena_doprava', 'cena_celkem')");
    $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Nalezené sloupce: " . count($sloupce) . " / 6</h2>";

    if (count($sloupce) === 6) {
        echo "<p class='success'>✅ VŠECH 6 SLOUPCŮ BYLO ÚSPĚŠNĚ PŘIDÁNO!</p>";
    } else {
        echo "<p class='error'>❌ Bylo nalezeno pouze " . count($sloupce) . " sloupců z 6!</p>";
    }

    if (count($sloupce) > 0) {
        echo "<table>";
        echo "<tr><th>Název</th><th>Typ</th><th>Null</th><th>Default</th></tr>";
        foreach ($sloupce as $sl) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($sl['Field']) . "</td>";
            echo "<td>" . htmlspecialchars($sl['Type']) . "</td>";
            echo "<td>" . htmlspecialchars($sl['Null']) . "</td>";
            echo "<td>" . htmlspecialchars($sl['Default'] ?? 'NULL') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Zkontrolovat jaké sloupce chybí
    $pozadovaneSloupce = ['pocet_dilu', 'cena_prace', 'cena_material', 'cena_druhy_technik', 'cena_doprava', 'cena_celkem'];
    $nalezenesloupce = array_column($sloupce, 'Field');
    $chybejiciSloupce = array_diff($pozadovaneSloupce, $nalezenesloupce);

    if (count($chybejiciSloupce) > 0) {
        echo "<h2 class='error'>❌ Chybějící sloupce:</h2>";
        echo "<ul>";
        foreach ($chybejiciSloupce as $chybi) {
            echo "<li>" . htmlspecialchars($chybi) . "</li>";
        }
        echo "</ul>";
        echo "<p><a href='/pridej_cenove_udaje_protokolu.php'>→ Spustit migraci znovu</a></p>";
    }

} catch (Exception $e) {
    echo "<p class='error'>CHYBA: " . htmlspecialchars($e->getMessage()) . "</p>";
}

echo "<hr>";
echo "<a href='/admin.php'>← Zpět na Admin</a>";
echo "</body></html>";
?>
