<?php
/**
 * Migrace: Přidání jazykových sloupců do tabulky wgs_pricing
 *
 * Tento skript BEZPEČNĚ přidá sloupce pro EN a IT verze textů ceníku.
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
    <title>Migrace: Jazykové sloupce ceníku</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
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
               border-radius: 5px; margin: 10px 5px 10px 0;
               cursor: pointer; border: none; font-size: 14px; }
        .btn:hover { background: #000; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #333; color: white; }
        .exists { color: #28a745; font-weight: bold; }
        .missing { color: #dc3545; font-weight: bold; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px;
              overflow-x: auto; border-left: 4px solid #333; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Přidání jazykových sloupců do wgs_pricing</h1>";

    // Kontrola struktury tabulky
    echo "<div class='info'><strong>KONTROLA AKTUÁLNÍ STRUKTURY...</strong></div>";

    $stmt = $pdo->query("DESCRIBE wgs_pricing");
    $existujiciSloupce = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Definice požadovaných sloupců
    $pozadovaneSloupce = [
        'service_name_en' => "VARCHAR(255) DEFAULT NULL COMMENT 'Název služby - anglicky'",
        'service_name_it' => "VARCHAR(255) DEFAULT NULL COMMENT 'Název služby - italsky'",
        'description_en' => "TEXT DEFAULT NULL COMMENT 'Popis služby - anglicky'",
        'description_it' => "TEXT DEFAULT NULL COMMENT 'Popis služby - italsky'",
        'category_en' => "VARCHAR(100) DEFAULT NULL COMMENT 'Kategorie - anglicky'",
        'category_it' => "VARCHAR(100) DEFAULT NULL COMMENT 'Kategorie - italsky'"
    ];

    // Zjistit, které sloupce chybí
    $chybejiciSloupce = [];
    foreach ($pozadovaneSloupce as $nazevSloupce => $definice) {
        if (!in_array($nazevSloupce, $existujiciSloupce)) {
            $chybejiciSloupce[$nazevSloupce] = $definice;
        }
    }

    // Zobrazit tabulku se stavem
    echo "<table>";
    echo "<tr><th>Sloupec</th><th>Definice</th><th>Stav</th></tr>";
    foreach ($pozadovaneSloupce as $nazevSloupce => $definice) {
        $existuje = in_array($nazevSloupce, $existujiciSloupce);
        $stav = $existuje ? "<span class='exists'>✓ Existuje</span>" : "<span class='missing'>✗ Chybí</span>";
        echo "<tr>";
        echo "<td><code>{$nazevSloupce}</code></td>";
        echo "<td><code>{$definice}</code></td>";
        echo "<td>{$stav}</td>";
        echo "</tr>";
    }
    echo "</table>";

    if (empty($chybejiciSloupce)) {
        echo "<div class='success'>";
        echo "<strong>✓ VŠECHNY JAZYKOVÉ SLOUPCE JIŽ EXISTUJÍ</strong><br>";
        echo "Tabulka <code>wgs_pricing</code> má kompletní strukturu pro vícejazyčný obsah.";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<strong>Nalezeno " . count($chybejiciSloupce) . " chybějících sloupců</strong>";
        echo "</div>";

        // Pokud je nastaveno ?execute=1, provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                $pocetPridanych = 0;

                foreach ($chybejiciSloupce as $nazevSloupce => $definice) {
                    // Určit pozici sloupce (přidat za odpovídající český sloupec)
                    $pozice = '';
                    if (strpos($nazevSloupce, 'service_name') !== false) {
                        $pozice = 'AFTER service_name';
                    } elseif (strpos($nazevSloupce, 'description') !== false) {
                        $pozice = 'AFTER description';
                    } elseif (strpos($nazevSloupce, 'category') !== false) {
                        $pozice = 'AFTER category';
                    }

                    $sql = "ALTER TABLE wgs_pricing ADD COLUMN {$nazevSloupce} {$definice} {$pozice}";

                    echo "<pre>Provádím: {$sql}</pre>";

                    $pdo->exec($sql);
                    $pocetPridanych++;

                    echo "<div class='success'>✓ Sloupec <code>{$nazevSloupce}</code> úspěšně přidán</div>";
                }

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>✓ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
                echo "Přidáno <strong>{$pocetPridanych}</strong> nových sloupců do tabulky <code>wgs_pricing</code>.<br><br>";
                echo "<strong>Další kroky:</strong><br>";
                echo "1. Upravit admin formulář pro zadávání 3 jazyků (CS/EN/IT)<br>";
                echo "2. Rozšířit API pro ukládání všech jazykových verzí<br>";
                echo "3. Upravit frontend pro načítání správného jazyka<br>";
                echo "4. Importovat stávající překlady z JS souborů";
                echo "</div>";

                echo "<a href='vsechny_tabulky.php' class='btn'>Zobrazit novou strukturu DB</a>";
                echo "<a href='cenik.php' class='btn'>Otevřít ceník</a>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA PŘI MIGRACI:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            // Náhled co bude provedeno
            echo "<h2>SQL příkazy, které budou provedeny:</h2>";

            foreach ($chybejiciSloupce as $nazevSloupce => $definice) {
                $pozice = '';
                if (strpos($nazevSloupce, 'service_name') !== false) {
                    $pozice = 'AFTER service_name';
                } elseif (strpos($nazevSloupce, 'description') !== false) {
                    $pozice = 'AFTER description';
                } elseif (strpos($nazevSloupce, 'category') !== false) {
                    $pozice = 'AFTER category';
                }

                $sql = "ALTER TABLE wgs_pricing ADD COLUMN {$nazevSloupce} {$definice} {$pozice};";
                echo "<pre>{$sql}</pre>";
            }

            echo "<div class='warning'>";
            echo "<strong>⚠️ DŮLEŽITÉ:</strong><br>";
            echo "• Migrace přidá " . count($chybejiciSloupce) . " nových sloupců do tabulky <code>wgs_pricing</code><br>";
            echo "• Stávající data NEBUDOU smazána ani změněna<br>";
            echo "• Operace je BEZPEČNÁ a REVERZIBILNÍ<br>";
            echo "• Po spuštění bude potřeba upravit admin rozhraní";
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>✓ SPUSTIT MIGRACI</a>";
            echo "<a href='vsechny_tabulky.php' class='btn'>Zrušit a vrátit se</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
