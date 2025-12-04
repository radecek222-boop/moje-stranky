<?php
/**
 * Vycisteni duplicitnich aktualit
 *
 * Ponecha pouze zaznam ID 9 s kvalitnim obsahem
 * Smaze zaznamy ID 10-14 (duplicitni stuby)
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Vycisteni aktualit</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #1a1a1a; border-bottom: 3px solid #1a1a1a; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffc107; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #1a1a1a; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; font-size: 14px; }
        .btn:hover { background: #333; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; border: 1px solid #ddd; text-align: left; }
        th { background: #f5f5f5; }
        .delete-row { background: #ffe6e6; }
        .keep-row { background: #e6ffe6; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Vycisteni duplicitnich aktualit</h1>";

    // Nacist vsechny zaznamy
    $stmt = $pdo->query("SELECT id, datum, LENGTH(obsah_cz) as delka FROM wgs_natuzzi_aktuality ORDER BY id DESC");
    $zaznamy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<h2>Aktualni stav databaze</h2>";
    echo "<table>";
    echo "<tr><th>ID</th><th>Datum</th><th>Delka obsahu</th><th>Akce</th></tr>";

    foreach ($zaznamy as $z) {
        $class = ($z['id'] == 9) ? 'keep-row' : 'delete-row';
        $akce = ($z['id'] == 9) ? 'PONECHAT' : 'SMAZAT';
        echo "<tr class='{$class}'>";
        echo "<td><strong>{$z['id']}</strong></td>";
        echo "<td>{$z['datum']}</td>";
        echo "<td>{$z['delka']} znaku</td>";
        echo "<td>{$akce}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Spustit mazani
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUSTIM MAZANI...</strong></div>";

        $pdo->beginTransaction();

        try {
            // Smazat vsechny zaznamy krome ID 9
            $stmt = $pdo->prepare("DELETE FROM wgs_natuzzi_aktuality WHERE id != 9");
            $stmt->execute();
            $smazano = $stmt->rowCount();

            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>USPESNE DOKONCENO</strong><br>";
            echo "Smazano zaznamu: {$smazano}<br>";
            echo "Ponechan zaznam ID 9 s kvalitnim obsahem.";
            echo "</div>";

            // Overit vysledek
            $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_natuzzi_aktuality");
            $pocet = $stmt->fetchColumn();
            echo "<div class='info'>Aktualni pocet zaznamu v databazi: <strong>{$pocet}</strong></div>";

            echo "<a href='aktuality.php' class='btn'>Zobrazit aktuality</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>CHYBA:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    } else {
        // Nahled - cekame na potvrzeni
        echo "<div class='warning'>";
        echo "<strong>POZOR:</strong> Tato akce smaze " . (count($zaznamy) - 1) . " zaznamu a ponecha pouze ID 9.";
        echo "</div>";

        echo "<a href='?execute=1' class='btn btn-danger' onclick=\"return confirm('Opravdu smazat duplicitni zaznamy?')\">SMAZAT DUPLICITY</a>";
        echo "<a href='diagnostika_aktuality.php' class='btn'>Zpet na diagnostiku</a>";
    }

} catch (Exception $e) {
    echo "<div class='error'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
