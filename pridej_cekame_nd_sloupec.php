<?php
/**
 * Migrace: Pridani sloupce cekame_nd_at do tabulky wgs_nabidky
 *
 * Tento skript BEZPECNE prida sloupec pro sledovani workflow kroku "Cekame ND" (nahradni dily).
 * Muzete jej spustit vicekrat - neudela duplicitni zmeny.
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator muze spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Pridani sloupce cekame_nd_at</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #555; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Pridani sloupce cekame_nd_at</h1>";

    // Kontrola zda sloupec existuje
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky LIKE 'cekame_nd_at'");
    $existuje = $stmt->fetch();

    if ($existuje) {
        echo "<div class='warning'>";
        echo "<strong>SLOUPEC JIZ EXISTUJE</strong><br>";
        echo "Sloupec <code>cekame_nd_at</code> jiz v tabulce <code>wgs_nabidky</code> existuje.";
        echo "</div>";
    } else {
        // Pridat sloupec
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUSTIM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                // Pridat sloupec cekame_nd_at za potvrzeno_at (pokud existuje) nebo na konec
                $pdo->exec("ALTER TABLE wgs_nabidky ADD COLUMN cekame_nd_at DATETIME NULL DEFAULT NULL COMMENT 'Cas nastaveni stavu Cekame ND (nahradni dily)'");

                // Pridat index pro rychlejsi vyhledavani
                $pdo->exec("ALTER TABLE wgs_nabidky ADD INDEX idx_cekame_nd (cekame_nd_at)");

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>MIGRACE USPESNE DOKONCENA</strong><br>";
                echo "Sloupec <code>cekame_nd_at</code> byl pridan do tabulky <code>wgs_nabidky</code>.";
                echo "</div>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            echo "<div class='info'>";
            echo "<strong>NAHLED ZMENY:</strong><br>";
            echo "Bude pridan sloupec <code>cekame_nd_at DATETIME NULL</code> do tabulky <code>wgs_nabidky</code>.<br>";
            echo "Tento sloupec bude sledovat, kdy admin aktivoval stav 'Cekame ND' (cekame na nahradni dily).";
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
            echo "<a href='/admin.php' class='btn'>Zpet do administrace</a>";
        }
    }

    // Zobrazit aktualni strukturu sloupcu workflow
    echo "<h2>Aktualni workflow sloupce v wgs_nabidky:</h2>";
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky WHERE Field LIKE '%_at' OR Field = 'stav'");
    $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table style='width:100%; border-collapse: collapse; margin-top: 10px;'>";
    echo "<tr style='background:#333; color:white;'>";
    echo "<th style='padding:8px; text-align:left;'>Sloupec</th>";
    echo "<th style='padding:8px; text-align:left;'>Typ</th>";
    echo "<th style='padding:8px; text-align:left;'>Null</th>";
    echo "<th style='padding:8px; text-align:left;'>Vychozi</th>";
    echo "</tr>";

    foreach ($sloupce as $s) {
        echo "<tr style='border-bottom:1px solid #ddd;'>";
        echo "<td style='padding:8px;'><code>{$s['Field']}</code></td>";
        echo "<td style='padding:8px;'>{$s['Type']}</td>";
        echo "<td style='padding:8px;'>{$s['Null']}</td>";
        echo "<td style='padding:8px;'>" . ($s['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
