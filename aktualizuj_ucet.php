<?php
/**
 * Jednorázový skript pro aktualizaci čísla účtu zaměstnance
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Aktualizace účtu</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; }
        .btn:hover { background: #555; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        td, th { padding: 8px; border: 1px solid #ddd; text-align: left; }
    </style>
</head>
<body>
<div class='container'>
<h1>Aktualizace účtu zaměstnance</h1>";

try {
    $pdo = getDbConnection();

    // Marek má ID 19
    $id = 19;
    $ucet = '123-3235060247';
    $banka = '0100';

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        // Provést aktualizaci
        $stmt = $pdo->prepare("UPDATE psa_zamestnanci SET ucet = ?, banka = ? WHERE id = ?");
        $stmt->execute([$ucet, $banka, $id]);

        echo "<div class='success'><strong>ÚSPĚCH!</strong><br>Účet pro Marka aktualizován na: {$ucet}/{$banka}</div>";
    }

    // Zobrazit aktuální stav
    $stmt = $pdo->prepare("SELECT id, jmeno, ucet, banka FROM psa_zamestnanci WHERE id = ?");
    $stmt->execute([$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        echo "<h3>Aktuální stav:</h3>";
        echo "<table>";
        echo "<tr><th>ID</th><td>{$row['id']}</td></tr>";
        echo "<tr><th>Jméno</th><td>{$row['jmeno']}</td></tr>";
        echo "<tr><th>Účet</th><td>" . ($row['ucet'] ?: '<em>prázdný</em>') . "</td></tr>";
        echo "<tr><th>Banka</th><td>" . ($row['banka'] ?: '<em>prázdný</em>') . "</td></tr>";
        echo "</table>";

        if (!isset($_GET['execute'])) {
            echo "<h3>Nové hodnoty:</h3>";
            echo "<table>";
            echo "<tr><th>Účet</th><td>{$ucet}</td></tr>";
            echo "<tr><th>Banka</th><td>{$banka}</td></tr>";
            echo "</table>";
            echo "<a href='?execute=1' class='btn'>ULOŽIT ZMĚNY</a>";
        }
    } else {
        echo "<div class='error'>Zaměstnanec s ID {$id} nenalezen!</div>";
    }

    echo "<br><a href='/psa-kalkulator.php' class='btn'>Zpět na kalkulátor</a>";

} catch (Exception $e) {
    echo "<div class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
