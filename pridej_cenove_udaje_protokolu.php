<?php
/**
 * Migrace: Přidání cenových údajů protokolu do tabulky wgs_reklamace
 *
 * Tento skript BEZPEČNĚ přidá sloupce pro cenové údaje z protokolu:
 * - pocet_dilu (Počet dílů)
 * - cena_prace (Práce)
 * - cena_material (Materiál)
 * - cena_druhy_technik (2. technik)
 * - cena_doprava (Doprava)
 * - cena_celkem (Celkem)
 *
 * Můžete jej spustit vícekrát - neprovede duplicitní operace.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Cenové údaje protokolu</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
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
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background: #2D5016; color: white; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Cenové údaje protokolu</h1>";

    // Kontrola před migrací
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    // Zkontrolovat které sloupce už existují
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'cena_%'");
    $existujiciSloupce = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt2 = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'pocet_dilu'");
    if ($stmt2->rowCount() > 0) {
        $existujiciSloupce[] = 'pocet_dilu';
    }

    echo "<h3>Existující cenové sloupce:</h3>";
    if (count($existujiciSloupce) > 0) {
        echo "<ul>";
        foreach ($existujiciSloupce as $sloupec) {
            echo "<li><code>{$sloupec}</code> - již existuje ✅</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>Žádné cenové sloupce nebyly nalezeny.</p>";
    }

    $sloupceKPridani = [
        'pocet_dilu' => 'INT DEFAULT 0 COMMENT \'Počet dílů použitých při opravě\'',
        'cena_prace' => 'DECIMAL(10,2) DEFAULT 0.00 COMMENT \'Cena za práci\'',
        'cena_material' => 'DECIMAL(10,2) DEFAULT 0.00 COMMENT \'Cena za materiál\'',
        'cena_druhy_technik' => 'DECIMAL(10,2) DEFAULT 0.00 COMMENT \'Cena za druhého technika\'',
        'cena_doprava' => 'DECIMAL(10,2) DEFAULT 0.00 COMMENT \'Cena za dopravu\'',
        'cena_celkem' => 'DECIMAL(10,2) DEFAULT 0.00 COMMENT \'Celková cena opravy\''
    ];

    $sloupceKPridaniFiltrované = [];
    foreach ($sloupceKPridani as $nazev => $definice) {
        if (!in_array($nazev, $existujiciSloupce)) {
            $sloupceKPridaniFiltrované[$nazev] = $definice;
        }
    }

    echo "<h3>Sloupce k přidání:</h3>";
    if (count($sloupceKPridaniFiltrované) > 0) {
        echo "<table>";
        echo "<tr><th>Název sloupce</th><th>Definice</th></tr>";
        foreach ($sloupceKPridaniFiltrované as $nazev => $definice) {
            echo "<tr><td><code>{$nazev}</code></td><td><code>{$definice}</code></td></tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='success'><strong>✅ Všechny sloupce již existují!</strong> Migrace není potřeba.</div>";
    }

    // Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        if (count($sloupceKPridaniFiltrované) === 0) {
            echo "<div class='warning'>Žádné sloupce k přidání.</div>";
        } else {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                foreach ($sloupceKPridaniFiltrované as $nazev => $definice) {
                    $sql = "ALTER TABLE wgs_reklamace ADD COLUMN {$nazev} {$definice}";
                    echo "<p>Přidávám sloupec <code>{$nazev}</code>...</p>";
                    $pdo->exec($sql);
                }

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>✅ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
                echo "Přidáno " . count($sloupceKPridaniFiltrované) . " sloupců.";
                echo "</div>";

                echo "<div class='info'>";
                echo "<h3>📋 CO DĚLAT DÁL:</h3>";
                echo "<ol>";
                echo "<li>Upravit <code>protokol.js</code> aby ukládal tyto hodnoty do databáze</li>";
                echo "<li>Upravit <code>protokol_api.php</code> funkci <code>saveProtokolData()</code></li>";
                echo "<li>Upravit <code>admin_reklamace_management.php</code> pro zobrazení v detailu</li>";
                echo "</ol>";
                echo "</div>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>❌ CHYBA:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        }
    } else {
        // Náhled co bude provedeno
        if (count($sloupceKPridaniFiltrované) > 0) {
            echo "<div class='warning'>";
            echo "<strong>⚠️ POZOR:</strong><br>";
            echo "Tato operace přidá " . count($sloupceKPridaniFiltrované) . " sloupců do tabulky <code>wgs_reklamace</code>.";
            echo "</div>";
            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr style='margin: 30px 0;'>";
echo "<a href='/admin.php' class='btn'>Zpět na Admin</a>";
echo "<a href='/vsechny_tabulky.php' class='btn'>Zobrazit SQL strukturu</a>";

echo "</div></body></html>";
?>
