<?php
/**
 * Migrace: Pridani sloupce assigned_to do tabulky wgs_reklamace
 *
 * Tento sloupec je potrebny pro prirazeni technika k reklamaci.
 * load.php na nej odkazuje v LEFT JOIN dotazu.
 *
 * Bezpecne spusteni - kontroluje zda sloupec jiz existuje.
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
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>Migrace: Pridani sloupce assigned_to</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 800px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; margin-top: 0; }
        .success { background: #e8e8e8; border: 1px solid #ccc;
                   color: #222; padding: 15px; border-radius: 5px;
                   margin: 15px 0; }
        .error { background: #f5f5f5; border: 2px solid #333;
                 color: #222; padding: 15px; border-radius: 5px;
                 margin: 15px 0; }
        .warning { background: #fafafa; border-left: 4px solid #666;
                   color: #333; padding: 15px; border-radius: 0 5px 5px 0;
                   margin: 15px 0; }
        .info { background: #f9f9f9; border: 1px solid #ddd;
                color: #444; padding: 15px; border-radius: 5px;
                margin: 15px 0; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0;
               border: none; cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #555; }
        .btn-secondary { background: #777; }
        .btn-secondary:hover { background: #999; }
        pre { background: #1a1a1a; color: #eee; padding: 15px;
              border-radius: 5px; overflow-x: auto; }
        code { background: #eee; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Migrace: Pridani sloupce assigned_to</h1>";

try {
    $pdo = getDbConnection();

    // Kontrola zda sloupec jiz existuje pomoci SHOW COLUMNS
    echo "<div class='info'>Kontroluji zda sloupec <code>assigned_to</code> jiz existuje...</div>";

    $checkStmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'assigned_to'");
    $sloupcekExistuje = $checkStmt->rowCount() > 0;

    if ($sloupcekExistuje) {
        echo "<div class='success'>";
        echo "<strong>Sloupec <code>assigned_to</code> jiz existuje!</strong><br>";
        echo "Migrace neni potreba. Tabulka je v poradku.";
        echo "</div>";

        // Zobrazit info o indexu
        $indexStmt = $pdo->query("SHOW INDEX FROM wgs_reklamace WHERE Key_name = 'idx_assigned_to'");
        $indexExistuje = $indexStmt->rowCount() > 0;
        echo "<div class='info'>";
        echo "Index <code>idx_assigned_to</code>: " . ($indexExistuje ? "Existuje" : "Neexistuje");
        echo "</div>";

    } else {
        // Sloupec neexistuje - nabidnout pridani
        echo "<div class='warning'>";
        echo "<strong>Sloupec <code>assigned_to</code> NEEXISTUJE!</strong><br>";
        echo "Tento sloupec je potrebny pro prirazeni technika k reklamaci.";
        echo "</div>";

        // Pokud je pozadavek na spusteni migrace
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'>Spoustim migraci...</div>";

            // POZNAMKA: DDL prikazy (ALTER TABLE) v MySQL automaticky commituji
            // Proto nepouzivame transakce - kazdy prikaz je atomicky sam o sobe

            try {
                // Pridat sloupec assigned_to
                $sql = "ALTER TABLE wgs_reklamace
                        ADD COLUMN assigned_to INT(11) NULL DEFAULT NULL
                        COMMENT 'ID prirazeneho technika (FK na wgs_users.id)'
                        AFTER created_by";

                echo "<pre>$sql</pre>";

                $pdo->exec($sql);
                echo "<div class='success'>Sloupec <code>assigned_to</code> pridan.</div>";

                // Pridat index pro rychlejsi vyhledavani
                $sqlIndex = "ALTER TABLE wgs_reklamace ADD INDEX idx_assigned_to (assigned_to)";
                echo "<pre>$sqlIndex</pre>";
                $pdo->exec($sqlIndex);
                echo "<div class='success'>Index <code>idx_assigned_to</code> vytvoren.</div>";

                echo "<div class='success' style='margin-top: 20px;'>";
                echo "<strong>MIGRACE USPESNE DOKONCENA!</strong><br><br>";
                echo "Sloupec <code>assigned_to</code> byl pridan do tabulky <code>wgs_reklamace</code>.<br>";
                echo "Index <code>idx_assigned_to</code> byl vytvoren.";
                echo "</div>";

                echo "<div class='info'>";
                echo "<strong>Nyni muzete:</strong><br>";
                echo "1. Otevrit <a href='/seznam.php'>seznam.php</a> - mel by fungovat bez chyby 500<br>";
                echo "2. Prirazovat techniky k reklamacim";
                echo "</div>";

            } catch (PDOException $e) {
                echo "<div class='error'>";
                echo "<strong>CHYBA PRI MIGRACI:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }

        } else {
            // Zobrazit navrh a tlacitko pro spusteni
            echo "<div class='info'>";
            echo "<strong>SQL prikaz ktery bude spusten:</strong>";
            echo "</div>";

            echo "<pre>ALTER TABLE wgs_reklamace
ADD COLUMN assigned_to INT(11) NULL DEFAULT NULL
COMMENT 'ID prirazeneho technika (FK na wgs_users.id)'
AFTER created_by;

ALTER TABLE wgs_reklamace ADD INDEX idx_assigned_to (assigned_to);</pre>";

            echo "<p>";
            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
            echo "<a href='/admin.php' class='btn btn-secondary'>Zrusit</a>";
            echo "</p>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<p style='margin-top: 30px;'>";
echo "<a href='/admin.php' class='btn'>Zpet do Admin</a>";
echo "<a href='/diagnoza_load_php.php' class='btn'>Spustit diagnostiku</a>";
echo "</p>";

echo "</div></body></html>";
