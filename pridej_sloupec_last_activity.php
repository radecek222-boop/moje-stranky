<?php
/**
 * Migrace: Pridani sloupce last_activity do tabulky wgs_users
 *
 * Tento sloupec je potrebny pro spravne sledovani online uzivatelu.
 * Aktualizuje se automaticky v init.php pri kazdem requestu (throttle 60s).
 *
 * Muzete spustit vicekrat - neprovede duplicitni operace.
 *
 * @date 2025-12-05
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
    <title>Migrace: Pridani sloupce last_activity</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #333;
            padding-bottom: 10px;
        }
        .success {
            background: #e8e8e8;
            border: 1px solid #999;
            color: #333;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f0f0f0;
            border: 1px solid #666;
            color: #333;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #f5f5f5;
            border: 1px solid #999;
            color: #333;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #eee;
            border: 1px solid #aaa;
            color: #333;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
        }
        .btn:hover {
            background: #555;
        }
        code {
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-family: monospace;
        }
        pre {
            background: #f0f0f0;
            padding: 15px;
            border-radius: 5px;
            overflow-x: auto;
            font-family: monospace;
        }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Pridani sloupce last_activity</h1>";

    // 1. Kontrola zda sloupec uz existuje
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    $checkCol = $pdo->query("SHOW COLUMNS FROM wgs_users LIKE 'last_activity'");
    $sloupecExistuje = $checkCol->rowCount() > 0;

    if ($sloupecExistuje) {
        echo "<div class='success'>";
        echo "<strong>Sloupec <code>last_activity</code> jiz existuje!</strong><br>";
        echo "Migrace neni potreba.";
        echo "</div>";

        // Zobrazit aktualni strukturu
        $stmt = $pdo->query("DESCRIBE wgs_users");
        $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h3>Aktualni struktura tabulky wgs_users:</h3>";
        echo "<pre>";
        foreach ($sloupce as $sloupec) {
            echo htmlspecialchars($sloupec['Field']) . " - " . htmlspecialchars($sloupec['Type']);
            if ($sloupec['Field'] === 'last_activity') {
                echo " <-- TENTO SLOUPEC";
            }
            echo "\n";
        }
        echo "</pre>";

    } else {
        // Sloupec neexistuje - nabidnout migraci
        echo "<div class='warning'>";
        echo "<strong>Sloupec <code>last_activity</code> neexistuje.</strong><br>";
        echo "Pro spravne fungovani sledovani online uzivatelu je potreba pridat tento sloupec.";
        echo "</div>";

        echo "<h3>SQL prikaz ktery bude proveden:</h3>";
        echo "<pre>ALTER TABLE wgs_users
ADD COLUMN last_activity DATETIME NULL DEFAULT NULL
COMMENT 'Cas posledni aktivity uzivatele (aktualizuje se automaticky)'
AFTER last_login;</pre>";

        // Pokud je nastaveno ?execute=1, provest migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUSTIM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                // Pridat sloupec last_activity
                $pdo->exec("
                    ALTER TABLE wgs_users
                    ADD COLUMN last_activity DATETIME NULL DEFAULT NULL
                    COMMENT 'Cas posledni aktivity uzivatele (aktualizuje se automaticky)'
                ");

                // Pridat index pro rychle vyhledavani online uzivatelu
                $pdo->exec("
                    ALTER TABLE wgs_users
                    ADD INDEX idx_last_activity (last_activity)
                ");

                // Nastavit last_activity pro vsechny uzivatele kteri maji last_login
                $pdo->exec("
                    UPDATE wgs_users
                    SET last_activity = last_login
                    WHERE last_login IS NOT NULL
                ");

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>MIGRACE USPESNE DOKONCENA!</strong><br><br>";
                echo "Zmeny:<br>";
                echo "- Pridan sloupec <code>last_activity</code><br>";
                echo "- Pridan index <code>idx_last_activity</code><br>";
                echo "- Nastavena pocatecni hodnota z <code>last_login</code>";
                echo "</div>";

                // Zobrazit upravenou strukturu
                $stmt = $pdo->query("DESCRIBE wgs_users");
                $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo "<h3>Nova struktura tabulky wgs_users:</h3>";
                echo "<pre>";
                foreach ($sloupce as $sloupec) {
                    echo htmlspecialchars($sloupec['Field']) . " - " . htmlspecialchars($sloupec['Type']);
                    if ($sloupec['Field'] === 'last_activity') {
                        echo " <-- NOVY SLOUPEC";
                    }
                    echo "\n";
                }
                echo "</pre>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA PRI MIGRACI:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            // Zobrazit tlacitko pro spusteni
            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
            echo "<a href='/admin.php?tab=online' class='btn' style='background:#666;'>Zpet do admin panelu</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>KRITICKA CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<hr style='margin-top: 30px;'>";
echo "<p><small>Migracni skript vytvoreny pro WGS Service - " . date('Y-m-d H:i:s') . "</small></p>";
echo "</div></body></html>";
?>
