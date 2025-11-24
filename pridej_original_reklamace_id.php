<?php
/**
 * Migrace: Přidání sloupce original_reklamace_id pro klonování zakázek
 *
 * Tento skript BEZPEČNĚ přidá sloupec `original_reklamace_id` do tabulky `wgs_reklamace`.
 * Sloupec slouží k propojení klonované zakázky s původní zakázkou.
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
    <title>Migrace: Přidání original_reklamace_id</title>
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
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 14px; }
        .btn:hover { background: #1a300d; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px;
              overflow-x: auto; font-size: 12px; }
        code { background: #f4f4f4; padding: 2px 5px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Migrace: Přidání sloupce original_reklamace_id</h1>";

    // 1. KONTROLA PŘED MIGRACÍ
    echo "<div class='info'><strong>KONTROLA...</strong></div>";

    // Zkontrolovat, zda sloupec již existuje
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace LIKE 'original_reklamace_id'");
    $columnExists = $stmt->rowCount() > 0;

    if ($columnExists) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ SLOUPEC JIŽ EXISTUJE</strong><br>";
        echo "Sloupec <code>original_reklamace_id</code> již existuje v tabulce <code>wgs_reklamace</code>.<br>";
        echo "Migrace není potřeba.";
        echo "</div>";

        // Zobrazit info o sloupci
        $stmt = $pdo->query("SHOW FULL COLUMNS FROM wgs_reklamace LIKE 'original_reklamace_id'");
        $columnInfo = $stmt->fetch(PDO::FETCH_ASSOC);

        echo "<div class='info'>";
        echo "<strong>Informace o sloupci:</strong><br>";
        echo "Typ: <code>{$columnInfo['Type']}</code><br>";
        echo "Null: <code>{$columnInfo['Null']}</code><br>";
        echo "Default: <code>" . ($columnInfo['Default'] ?? 'NULL') . "</code><br>";
        echo "Extra: <code>{$columnInfo['Extra']}</code><br>";
        echo "Comment: <code>{$columnInfo['Comment']}</code>";
        echo "</div>";

        // Zkontrolovat index
        $stmt = $pdo->query("SHOW INDEX FROM wgs_reklamace WHERE Column_name = 'original_reklamace_id'");
        $indexExists = $stmt->rowCount() > 0;

        if ($indexExists) {
            echo "<div class='success'>✓ Index <code>idx_original_reklamace_id</code> existuje</div>";
        } else {
            echo "<div class='warning'>⚠️ Index <code>idx_original_reklamace_id</code> NEEXISTUJE</div>";
        }

    } else {
        // 2. POKUD JE NASTAVENO ?execute=1, PROVÉST MIGRACI
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                // Přidat sloupec original_reklamace_id
                $sql = "ALTER TABLE wgs_reklamace
                        ADD COLUMN original_reklamace_id VARCHAR(50) NULL
                        COMMENT 'ID původní zakázky při znovuotevření (klonování)'
                        AFTER reklamace_id";

                $pdo->exec($sql);

                echo "<div class='success'>✓ Sloupec <code>original_reklamace_id</code> přidán</div>";

                // Přidat index pro rychlejší vyhledávání
                $sql = "ALTER TABLE wgs_reklamace
                        ADD INDEX idx_original_reklamace_id (original_reklamace_id)";

                $pdo->exec($sql);

                echo "<div class='success'>✓ Index <code>idx_original_reklamace_id</code> vytvořen</div>";

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>✓ MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
                echo "Struktura tabulky <code>wgs_reklamace</code> byla aktualizována:<br>";
                echo "• Přidán sloupec <code>original_reklamace_id VARCHAR(50) NULL</code><br>";
                echo "• Přidán index <code>idx_original_reklamace_id</code><br><br>";
                echo "<strong>Co to znamená:</strong><br>";
                echo "• Při kliknutí na 'Znovu otevřít' se vytvoří KLON zakázky<br>";
                echo "• Klon bude mít nové číslo a začne jako NOVÁ zakázka<br>";
                echo "• Původní zakázka zůstane HOTOVO (pro správné statistiky)<br>";
                echo "• Tento sloupec propojuje klon s původní zakázkou<br>";
                echo "</div>";

                echo "<a href='vsechny_tabulky.php' class='btn'>Zobrazit strukturu DB</a>";

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>❌ CHYBA:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }
        } else {
            // NÁHLED CO BUDE PROVEDENO
            echo "<div class='warning'>";
            echo "<strong>SLOUPEC NEEXISTUJE</strong><br>";
            echo "Migrace je potřeba. Budou provedeny následující změny:";
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>SQL příkazy k provedení:</strong><br><br>";
            echo "<pre>ALTER TABLE wgs_reklamace
ADD COLUMN original_reklamace_id VARCHAR(50) NULL
COMMENT 'ID původní zakázky při znovuotevření (klonování)'
AFTER reklamace_id;

ALTER TABLE wgs_reklamace
ADD INDEX idx_original_reklamace_id (original_reklamace_id);</pre>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<strong>🎯 Účel migrace:</strong><br>";
            echo "Když uživatel klikne na 'Znovu otevřít zakázku', systém:<br>";
            echo "1. Vytvoří KLON původní zakázky s novým číslem<br>";
            echo "2. Zkopíruje všechny údaje zákazníka<br>";
            echo "3. Nová zakázka začne jako NOVÁ (stav = ČEKÁ)<br>";
            echo "4. Původní zakázka zůstane HOTOVO<br>";
            echo "5. Statistiky budou správné (dvě samostatné zakázky)<br><br>";
            echo "<strong>Tento sloupec slouží k propojení:</strong><br>";
            echo "• Pokud je NULL → původní zakázka<br>";
            echo "• Pokud obsahuje ID → je to klon (znovu otevřená zakázka)<br>";
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>SPUSTIT MIGRACI</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
