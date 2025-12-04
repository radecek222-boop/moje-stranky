<?php
/**
 * Migrace: Přidání sloupce kalkulace_data do tabulky wgs_reklamace
 *
 * Tento skript BEZPEČNĚ přidá nový sloupec 'kalkulace_data' typu TEXT
 * do tabulky wgs_reklamace pro ukládání JSON dat z kalkulačky.
 *
 * Můžete jej spustit vícekrát - pokud sloupec již existuje, nic se nestane.
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
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1000px;
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
            color: #333333;
            border-bottom: 3px solid #333333;
            padding-bottom: 10px;
        }
        .success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .warning {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            color: #856404;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #333333;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
            border: none;
            cursor: pointer;
            font-size: 16px;
        }
        .btn:hover {
            background: #1a300d;
        }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover {
            background: #545b62;
        }
        pre {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #333333;
            overflow-x: auto;
        }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "<h1>Migrace: Přidání sloupce kalkulace_data</h1>";

    // 1. Kontrolní fáze - zjistit, zda sloupec již existuje
    echo "<div class='info'><strong>KROK 1: KONTROLA AKTUÁLNÍHO STAVU...</strong></div>";

    $stmt = $pdo->query("DESCRIBE wgs_reklamace");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $sloupecExistuje = false;
    foreach ($columns as $column) {
        if ($column['Field'] === 'kalkulace_data') {
            $sloupecExistuje = true;
            break;
        }
    }

    if ($sloupecExistuje) {
        echo "<div class='success'>";
        echo "<strong>SLOUPEC 'kalkulace_data' JIŽ EXISTUJE</strong><br>";
        echo "Migrace není potřeba. Databáze je v pořádku.";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ SLOUPEC 'kalkulace_data' NEEXISTUJE</strong><br>";
        echo "Je potřeba jej přidat do tabulky wgs_reklamace.";
        echo "</div>";

        // 2. Pokud je nastaveno ?execute=1, provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>KROK 2: SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                // SQL pro přidání sloupce
                $sql = "ALTER TABLE wgs_reklamace
                        ADD COLUMN kalkulace_data TEXT NULL
                        COMMENT 'JSON data z kalkulačky (ceník)'
                        AFTER updated_at";

                echo "<div class='info'><strong>SQL příkaz:</strong></div>";
                echo "<pre>$sql</pre>";

                $pdo->exec($sql);
                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>MIGRACE ÚSPĚŠNĚ DOKONČENA</strong><br><br>";
                echo "Sloupec 'kalkulace_data' byl úspěšně přidán do tabulky wgs_reklamace.<br>";
                echo "Typ: TEXT (pro JSON data)<br>";
                echo "Pozice: Po sloupci 'updated_at'<br>";
                echo "Nullable: ANO (NULL je povoleno)<br>";
                echo "</div>";

                echo "<div class='info'>";
                echo "<strong>📋 Co tento sloupec ukládá:</strong><br>";
                echo "• Kompletní JSON data z kalkulačky (ceníku)<br>";
                echo "• Položky služeb, náhradní díly, dopravné<br>";
                echo "• Celkovou částku a jednotlivé položky<br>";
                echo "• Data se používají pro generování PDF PRICELIST v protokolu<br>";
                echo "</div>";

                // Ověření
                echo "<div class='info'><strong>KROK 3: OVĚŘENÍ...</strong></div>";
                $stmt = $pdo->query("DESCRIBE wgs_reklamace kalkulace_data");
                $newColumn = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($newColumn) {
                    echo "<div class='success'>";
                    echo "<strong>OVĚŘENÍ ÚSPĚŠNÉ</strong><br>";
                    echo "<pre>";
                    echo "Field: " . $newColumn['Field'] . "\n";
                    echo "Type: " . $newColumn['Type'] . "\n";
                    echo "Null: " . $newColumn['Null'] . "\n";
                    echo "Default: " . ($newColumn['Default'] ?? 'NULL') . "\n";
                    echo "</pre>";
                    echo "</div>";
                }

            } catch (PDOException $e) {
                $pdo->rollBack();
                echo "<div class='error'>";
                echo "<strong>CHYBA PŘI MIGRACI:</strong><br>";
                echo htmlspecialchars($e->getMessage());
                echo "</div>";
            }

            echo "<a href='/admin.php' class='btn btn-secondary'>← Zpět do Admin Panelu</a>";
            echo "<a href='/protokol.php' class='btn'>Otevřít Protokol</a>";

        } else {
            // Náhled co bude provedeno
            echo "<div class='info'>";
            echo "<strong>📋 CO SE PROVEDE:</strong><br><br>";
            echo "1. Přidá se nový sloupec <code>kalkulace_data</code> do tabulky <code>wgs_reklamace</code><br>";
            echo "2. Typ sloupce: <strong>TEXT</strong> (pro ukládání JSON dat)<br>";
            echo "3. Sloupec bude <strong>nullable</strong> (může být NULL)<br>";
            echo "4. Pozice: Po sloupci <code>updated_at</code><br>";
            echo "5. Použití: Ukládání dat z kalkulačky pro PDF PRICELIST<br>";
            echo "</div>";

            echo "<div class='warning'>";
            echo "<strong>⚠️ DŮLEŽITÉ:</strong><br>";
            echo "• Migrace je <strong>BEZPEČNÁ</strong> a nemění existující data<br>";
            echo "• Pouze přidá nový sloupec do tabulky<br>";
            echo "• Můžete ji spustit vícekrát bez problémů<br>";
            echo "</div>";

            echo "<a href='?execute=1' class='btn'>▶ SPUSTIT MIGRACI</a>";
            echo "<a href='/admin.php' class='btn btn-secondary'>← Zpět bez změn</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
