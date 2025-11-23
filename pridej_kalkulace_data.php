<?php
/**
 * Migrace: Přidání sloupce kalkulace_data do tabulky wgs_reklamace
 *
 * Tento skript BEZPEČNĚ přidá sloupec pro ukládání dat z kalkulačky.
 * Můžete jej spustit vícekrát - nepřidá duplicitní sloupec.
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
    <title>Migrace: Přidání kalkulace_data</title>
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
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px;
               font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Přidání sloupce kalkulace_data</h1>";

    // Kontrola existence sloupce
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'kalkulace_data'");
    $sloupecExistuje = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($sloupecExistuje) {
        echo "<div class='warning'>";
        echo "<strong>Sloupec již existuje</strong><br>";
        echo "Sloupec <code>kalkulace_data</code> již v tabulce <code>wgs_reklamace</code> existuje.<br>";
        echo "Migrace není potřeba.";
        echo "</div>";

        // Zobrazit strukturu sloupce
        echo "<div class='info'>";
        echo "<strong>Aktuální struktura sloupce:</strong><br>";
        echo "<code>Pole:</code> " . htmlspecialchars($sloupecExistuje['Field']) . "<br>";
        echo "<code>Typ:</code> " . htmlspecialchars($sloupecExistuje['Type']) . "<br>";
        echo "<code>Null:</code> " . htmlspecialchars($sloupecExistuje['Null']) . "<br>";
        echo "<code>Default:</code> " . htmlspecialchars($sloupecExistuje['Default'] ?? 'NULL') . "<br>";
        echo "</div>";

    } else {
        echo "<div class='warning'>";
        echo "<strong>Sloupec neexistuje</strong><br>";
        echo "Sloupec <code>kalkulace_data</code> v tabulce <code>wgs_reklamace</code> nebyl nalezen.<br>";
        echo "Je nutné přidat sloupec pro ukládání dat z kalkulačky.";
        echo "</div>";

        // Zobrazit SQL, který bude proveden
        echo "<div class='info'>";
        echo "<strong>SQL příkaz, který bude proveden:</strong><br>";
        echo "<code style='display: block; padding: 10px; background: #f9f9f9; margin: 10px 0;'>";
        echo htmlspecialchars("ALTER TABLE wgs_reklamace ADD COLUMN kalkulace_data JSON NULL COMMENT 'Data z kalkulačky ceny (JSON)'");
        echo "</code>";
        echo "</div>";

        // Pokud je nastaveno ?execute=1, provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                // Přidat sloupec kalkulace_data
                $pdo->exec("
                    ALTER TABLE wgs_reklamace
                    ADD COLUMN kalkulace_data JSON NULL
                    COMMENT 'Data z kalkulačky ceny (JSON)'
                ");

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>✓ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br>";
                echo "Sloupec <code>kalkulace_data</code> byl úspěšně přidán do tabulky <code>wgs_reklamace</code>.";
                echo "</div>";

                // Zobrazit novou strukturu
                $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'kalkulace_data'");
                $novySloupec = $stmt->fetch(PDO::FETCH_ASSOC);

                echo "<div class='info'>";
                echo "<strong>Struktura nového sloupce:</strong><br>";
                echo "<code>Pole:</code> " . htmlspecialchars($novySloupec['Field']) . "<br>";
                echo "<code>Typ:</code> " . htmlspecialchars($novySloupec['Type']) . "<br>";
                echo "<code>Null:</code> " . htmlspecialchars($novySloupec['Null']) . "<br>";
                echo "<code>Default:</code> " . htmlspecialchars($novySloupec['Default'] ?? 'NULL') . "<br>";
                echo "</div>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA při přidávání sloupce:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            // Tlačítko pro spuštění migrace
            echo "<a href='?execute=1' class='btn'>▶ SPUSTIT MIGRACI</a>";
        }
    }

    echo "<br><br>";
    echo "<a href='vsechny_tabulky.php' class='btn'>← Zpět na přehled tabulek</a>";
    echo "<a href='admin.php' class='btn'>← Zpět do Admin panelu</a>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
