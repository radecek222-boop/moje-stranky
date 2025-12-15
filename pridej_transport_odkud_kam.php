<?php
/**
 * Migrace: Pridani sloupcu odkud a kam do wgs_transport_events
 *
 * Techmission styl - jednodussi struktura transportu
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Sloupce odkud a kam</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 800px; margin: 50px auto; padding: 20px; background: #1a1a1a; color: #fff; }
        .container { background: #222; padding: 30px; border-radius: 10px; border: 1px solid #333; }
        h1 { color: #fff; border-bottom: 2px solid #444; padding-bottom: 10px; }
        .success { background: #1a3d1a; border: 1px solid #2d5016; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #1a2a3d; border: 1px solid #2d4a6d; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #3d3d1a; border: 1px solid #6d6d2d; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #333; color: #fff; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; border: 1px solid #555; }
        .btn:hover { background: #444; }
        code { background: #111; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Sloupce odkud a kam</h1>";

    // Kontrola zda sloupce existuji
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_transport_events LIKE 'odkud'");
    $odkudExists = $stmt->fetch();

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_transport_events LIKE 'kam'");
    $kamExists = $stmt->fetch();

    if ($odkudExists && $kamExists) {
        echo "<div class='success'><strong>Sloupce jiz existuji!</strong><br>odkud a kam jsou jiz v tabulce.</div>";
    } else {
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            // Pridat sloupce
            if (!$odkudExists) {
                $pdo->exec("ALTER TABLE wgs_transport_events ADD COLUMN odkud VARCHAR(255) NULL AFTER jmeno_prijmeni");
                echo "<div class='success'>Sloupec <code>odkud</code> pridan.</div>";
            }

            if (!$kamExists) {
                $pdo->exec("ALTER TABLE wgs_transport_events ADD COLUMN kam VARCHAR(255) NULL AFTER odkud");
                echo "<div class='success'>Sloupec <code>kam</code> pridan.</div>";
            }

            echo "<div class='success'><strong>MIGRACE DOKONCENA</strong></div>";
        } else {
            echo "<div class='info'><strong>Nasledujici sloupce budou pridany:</strong><br>";
            if (!$odkudExists) echo "- <code>odkud</code> VARCHAR(255)<br>";
            if (!$kamExists) echo "- <code>kam</code> VARCHAR(255)<br>";
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        }
    }

    echo "<br><br><a href='admin.php?tab=transport' class='btn'>Zpet na Transport</a>";

} catch (Exception $e) {
    echo "<div style='background:#3d1a1a;border:1px solid #6d2d2d;padding:12px;border-radius:5px;'>";
    echo "<strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
