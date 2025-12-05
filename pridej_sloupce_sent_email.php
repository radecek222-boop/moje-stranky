<?php
/**
 * Migrace: Pridani sloupcu pro sledovani odeslanych pozvanek
 *
 * Tento skript BEZPECNE prida sloupce:
 * - sent_to_email: email, na ktery byl klic odeslan
 * - sent_at: cas odeslani
 *
 * Muzete jej spustit vicekrat - neprovede duplicitni operace.
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
    <title>Migrace: Sloupce pro odeslane pozvanky</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #000; border-bottom: 3px solid #000;
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
               background: #000; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none; cursor: pointer; }
        .btn:hover { background: #333; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
        pre { background: #f5f5f5; padding: 15px; border-radius: 5px; overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f5f5f5; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Sloupce pro odeslane pozvanky</h1>";

    // Zkontrolovat aktualni strukturu
    $stmt = $pdo->query("DESCRIBE wgs_registration_keys");
    $sloupce = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $existujiciSloupce = array_column($sloupce, 'Field');

    $maSentToEmail = in_array('sent_to_email', $existujiciSloupce);
    $maSentAt = in_array('sent_at', $existujiciSloupce);

    echo "<h2>Aktualni stav</h2>";
    echo "<table>";
    echo "<tr><th>Sloupec</th><th>Status</th></tr>";
    echo "<tr><td><code>sent_to_email</code></td><td>" . ($maSentToEmail ? '<span style="color:green">Existuje</span>' : '<span style="color:red">Chybi</span>') . "</td></tr>";
    echo "<tr><td><code>sent_at</code></td><td>" . ($maSentAt ? '<span style="color:green">Existuje</span>' : '<span style="color:red">Chybi</span>') . "</td></tr>";
    echo "</table>";

    if ($maSentToEmail && $maSentAt) {
        echo "<div class='success'><strong>Vsechny sloupce jiz existuji!</strong> Migrace neni potreba.</div>";
        echo "<a href='admin.php?tab=keys' class='btn'>Zpet do admin panelu</a>";
    } else {
        echo "<h2>Co bude provedeno</h2>";
        echo "<pre>";
        if (!$maSentToEmail) {
            echo "ALTER TABLE wgs_registration_keys\n";
            echo "ADD COLUMN sent_to_email VARCHAR(255) NULL DEFAULT NULL\n";
            echo "COMMENT 'Email na ktery byl klic odeslan';\n\n";
        }
        if (!$maSentAt) {
            echo "ALTER TABLE wgs_registration_keys\n";
            echo "ADD COLUMN sent_at DATETIME NULL DEFAULT NULL\n";
            echo "COMMENT 'Cas odeslani pozvanky';\n";
        }
        echo "</pre>";

        // Pokud je nastaveno ?execute=1, provest migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUSTIM MIGRACI...</strong></div>";

            try {
                if (!$maSentToEmail) {
                    $pdo->exec("
                        ALTER TABLE wgs_registration_keys
                        ADD COLUMN sent_to_email VARCHAR(255) NULL DEFAULT NULL
                        COMMENT 'Email na ktery byl klic odeslan'
                    ");
                    echo "<div class='info'>Sloupec <code>sent_to_email</code> pridan.</div>";
                }

                if (!$maSentAt) {
                    $pdo->exec("
                        ALTER TABLE wgs_registration_keys
                        ADD COLUMN sent_at DATETIME NULL DEFAULT NULL
                        COMMENT 'Cas odeslani pozvanky'
                    ");
                    echo "<div class='info'>Sloupec <code>sent_at</code> pridan.</div>";
                }

                echo "<div class='success'>";
                echo "<strong>MIGRACE USPESNE DOKONCENA!</strong><br><br>";
                echo "Pridane sloupce:<br>";
                if (!$maSentToEmail) echo "- <code>sent_to_email</code> - email prijemce<br>";
                if (!$maSentAt) echo "- <code>sent_at</code> - cas odeslani<br>";
                echo "</div>";

                // Zobrazit novou strukturu
                echo "<h2>Nova struktura tabulky</h2>";
                echo "<pre>";
                $stmt = $pdo->query("DESCRIBE wgs_registration_keys");
                while ($sloupec = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo sprintf("%-20s %-20s %s\n", $sloupec['Field'], $sloupec['Type'], $sloupec['Null'] === 'YES' ? 'NULL' : 'NOT NULL');
                }
                echo "</pre>";

                echo "<a href='admin.php?tab=keys' class='btn'>Zpet do admin panelu</a>";

            } catch (PDOException $e) {
                echo "<div class='error'>";
                echo "<strong>CHYBA PRI MIGRACI:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            // Zobrazit tlacitko pro spusteni
            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
            echo "<a href='admin.php?tab=keys' class='btn' style='background:#666;'>Zrusit</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
