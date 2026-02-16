<?php
/**
 * Migrace: Přidání sloupce polozky_json do wgs_nabidky
 *
 * Tento skript BEZPEČNĚ přidá sloupec polozky_json pokud neexistuje.
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
    <title>Migrace: Přidání polozky_json do wgs_nabidky</title>
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
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #f5f5f5; font-weight: bold; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Přidání sloupce polozky_json</h1>";

    // 1. Kontrola existence tabulky
    echo "<div class='info'><strong>KONTROLA TABULKY...</strong></div>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_nabidky'");
    $tabulkaExistuje = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tabulkaExistuje) {
        echo "<div class='error'><strong>CHYBA:</strong> Tabulka wgs_nabidky neexistuje!</div>";
        echo "<p>Nejdřív vytvořte tabulku pomocí API nebo manuálně.</p>";
        echo "</div></body></html>";
        exit;
    }

    echo "<div class='success'>Tabulka wgs_nabidky existuje</div>";

    // 2. Kontrola existence sloupce polozky_json
    echo "<div class='info'><strong>KONTROLA SLOUPCE polozky_json...</strong></div>";
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky LIKE 'polozky_json'");
    $sloupecExistuje = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sloupecExistuje) {
        echo "<div class='success'>Sloupec polozky_json již existuje</div>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
        echo "<tr>
            <td>{$sloupecExistuje['Field']}</td>
            <td>{$sloupecExistuje['Type']}</td>
            <td>{$sloupecExistuje['Null']}</td>
            <td>" . ($sloupecExistuje['Default'] ?? 'NULL') . "</td>
        </tr>";
        echo "</table>";
    } else {
        echo "<div class='warning'>Sloupec polozky_json NEEXISTUJE - bude přidán</div>";
    }

    // 3. Kontrola dat v tabulce
    echo "<div class='info'><strong>KONTROLA DAT...</strong></div>";
    $stmt = $pdo->query("SELECT COUNT(*) as pocet FROM wgs_nabidky");
    $pocet = $stmt->fetch(PDO::FETCH_ASSOC)['pocet'];
    echo "<p>Počet záznamů v tabulce: <strong>{$pocet}</strong></p>";

    if ($pocet > 0) {
        $stmt = $pdo->query("SELECT * FROM wgs_nabidky ORDER BY vytvoreno_at DESC LIMIT 3");
        $nabidky = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>";
        echo "<tr><th>ID</th><th>Číslo</th><th>Zákazník</th><th>Cena</th><th>polozky_json?</th></tr>";
        foreach ($nabidky as $n) {
            $maPolozky = isset($n['polozky_json']) && !empty($n['polozky_json']) ? 'ANO ✓' : 'NE ✗';
            echo "<tr>
                <td>{$n['id']}</td>
                <td>" . ($n['cislo_nabidky'] ?? 'N/A') . "</td>
                <td>{$n['zakaznik_jmeno']}</td>
                <td>{$n['celkova_cena']} {$n['mena']}</td>
                <td>{$maPolozky}</td>
            </tr>";
        }
        echo "</table>";
    }

    // 4. Pokud je nastaveno ?execute=1, provést migraci
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        if (!$sloupecExistuje) {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                // Přidat sloupec polozky_json
                $pdo->exec("
                    ALTER TABLE wgs_nabidky
                    ADD COLUMN polozky_json TEXT NULL AFTER zakaznik_adresa
                ");

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
                echo "Sloupec polozky_json byl přidán do tabulky wgs_nabidky";
                echo "</div>";

                // Zobrazit novou strukturu
                $stmt = $pdo->query("SHOW COLUMNS FROM wgs_nabidky LIKE 'polozky_json'");
                $novySloupec = $stmt->fetch(PDO::FETCH_ASSOC);
                echo "<table>";
                echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Default</th></tr>";
                echo "<tr>
                    <td>{$novySloupec['Field']}</td>
                    <td>{$novySloupec['Type']}</td>
                    <td>{$novySloupec['Null']}</td>
                    <td>" . ($novySloupec['Default'] ?? 'NULL') . "</td>
                </tr>";
                echo "</table>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            echo "<div class='warning'>Sloupec již existuje - migrace není potřeba</div>";
        }
    } else {
        // Náhled - zobrazit tlačítko pro spuštění
        if (!$sloupecExistuje) {
            echo "<div class='warning'>";
            echo "<strong>PŘIPRAVENO K MIGRACI</strong><br>";
            echo "Sloupec polozky_json bude přidán do tabulky wgs_nabidky.<br>";
            echo "Tato operace je BEZPEČNÁ a REVERZIBILNÍ.";
            echo "</div>";
            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        } else {
            echo "<div class='success'>Vše je v pořádku - migrace není potřeba</div>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
