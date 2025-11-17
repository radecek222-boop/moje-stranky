<?php
/**
 * Migrace: Přidání chybějících sloupců do wgs_reklamace
 *
 * Tento skript BEZPEČNĚ přidá chybějící sloupce pokud neexistují.
 * Můžete jej spustit vícekrát - nepřidá duplicitní sloupce.
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("❌ PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit migraci.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Migrace: Přidání chybějících sloupců</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; max-width: 1000px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .sql-code { background: #f4f4f4; border-left: 4px solid #2D5016; padding: 10px; margin: 10px 0; font-family: 'Courier New', monospace; font-size: 12px; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; }
        tr:hover { background: #f5f5f5; }
        .status-ok { color: #28a745; font-weight: bold; }
        .status-missing { color: #dc3545; font-weight: bold; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 Migrace: Přidání chybějících sloupců do wgs_reklamace</h1>";

try {
    $pdo = getDbConnection();

    // Definice všech sloupců které potřebujeme
    $requiredColumns = [
        'ulice' => [
            'definition' => "VARCHAR(255) DEFAULT NULL COMMENT 'Ulice a číslo popisné'",
            'after' => 'adresa',
            'description' => 'Ulice a číslo popisné (součást adresy)'
        ],
        'mesto' => [
            'definition' => "VARCHAR(255) DEFAULT NULL COMMENT 'Město'",
            'after' => 'ulice',
            'description' => 'Město (součást adresy)'
        ],
        'psc' => [
            'definition' => "VARCHAR(20) DEFAULT NULL COMMENT 'PSČ'",
            'after' => 'mesto',
            'description' => 'PSČ (součást adresy)'
        ],
        'prodejce' => [
            'definition' => "VARCHAR(255) DEFAULT NULL COMMENT 'Jméno prodejce'",
            'after' => 'zpracoval_id',
            'description' => 'Jméno prodejce (legacy)'
        ],
        'technik' => [
            'definition' => "VARCHAR(255) DEFAULT NULL COMMENT 'Jméno technika přiřazeného k zakázce'",
            'after' => 'prodejce',
            'description' => 'Jméno technika - NOVÝ SPRÁVNÝ SLOUPEC!'
        ],
        'castka' => [
            'definition' => "DECIMAL(10,2) DEFAULT NULL COMMENT 'Částka (duplikát ceny)'",
            'after' => 'cena',
            'description' => 'Částka (duplikát ceny pro zpětnou kompatibilitu)'
        ],
        'zeme' => [
            'definition' => "VARCHAR(2) DEFAULT NULL COMMENT 'Země (duplikát fakturace_firma)'",
            'after' => 'fakturace_firma',
            'description' => 'Země (duplikát fakturace_firma pro zpětnou kompatibilitu)'
        ]
    ];

    echo "<div class='info'><strong>📋 KONTROLA STRUKTURY TABULKY wgs_reklamace</strong></div>";

    // Načíst existující sloupce
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "<table>";
    echo "<thead><tr><th>Sloupec</th><th>Status</th><th>Popis</th></tr></thead>";
    echo "<tbody>";

    $missingColumns = [];

    foreach ($requiredColumns as $column => $config) {
        $exists = in_array($column, $existingColumns);
        $statusClass = $exists ? 'status-ok' : 'status-missing';
        $statusText = $exists ? '✅ EXISTUJE' : '❌ CHYBÍ';

        echo "<tr>";
        echo "<td><strong>{$column}</strong></td>";
        echo "<td class='{$statusClass}'>{$statusText}</td>";
        echo "<td>{$config['description']}</td>";
        echo "</tr>";

        if (!$exists) {
            $missingColumns[$column] = $config;
        }
    }

    echo "</tbody></table>";

    if (count($missingColumns) === 0) {
        echo "<div class='success'>";
        echo "<strong>✅ PERFEKTNÍ!</strong><br>";
        echo "Všechny požadované sloupce již existují v databázi.<br>";
        echo "Není potřeba provádět žádnou migraci.";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "<strong>⚠️ NALEZENY CHYBĚJÍCÍ SLOUPCE: " . count($missingColumns) . "</strong><br>";
        echo "Následující sloupce budou přidány:";
        echo "</div>";

        // Pokud je nastaveno GET parameter 'execute', provést migraci
        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            echo "<div class='info'><strong>🚀 SPOUŠTÍM MIGRACI...</strong></div>";

            $pdo->beginTransaction();

            try {
                $successCount = 0;

                foreach ($missingColumns as $column => $config) {
                    $sql = "ALTER TABLE wgs_reklamace ADD COLUMN `{$column}` {$config['definition']}";

                    if (!empty($config['after'])) {
                        $sql .= " AFTER `{$config['after']}`";
                    }

                    echo "<div class='sql-code'><strong>SQL:</strong> {$sql}</div>";

                    $pdo->exec($sql);
                    $successCount++;

                    echo "<div class='success'>✅ Sloupec <strong>{$column}</strong> úspěšně přidán</div>";
                }

                $pdo->commit();

                echo "<div class='success'>";
                echo "<strong>🎉 MIGRACE ÚSPĚŠNĚ DOKONČENA!</strong><br>";
                echo "Přidáno sloupců: <strong>{$successCount}</strong><br>";
                echo "Tabulka wgs_reklamace je nyní kompletní.";
                echo "</div>";

                echo "<a href='pridej_chybejici_sloupce.php' class='btn'>🔄 Znovu zkontrolovat</a>";

            } catch (PDOException $e) {
                $pdo->rollBack();

                echo "<div class='error'>";
                echo "<strong>❌ CHYBA PŘI MIGRACI</strong><br>";
                echo "Popis chyby: " . htmlspecialchars($e->getMessage()) . "<br>";
                echo "Všechny změny byly vráceny zpět (rollback).";
                echo "</div>";
            }

        } else {
            // Zobrazit náhled co bude provedeno
            echo "<h2>📝 Náhled SQL příkazů:</h2>";

            foreach ($missingColumns as $column => $config) {
                $sql = "ALTER TABLE wgs_reklamace ADD COLUMN `{$column}` {$config['definition']}";

                if (!empty($config['after'])) {
                    $sql .= " AFTER `{$config['after']}`";
                }

                echo "<div class='sql-code'>{$sql};</div>";
            }

            echo "<div class='warning'>";
            echo "<strong>⚠️ DŮLEŽITÉ UPOZORNĚNÍ</strong><br>";
            echo "Před spuštěním migrace doporučujeme:<br>";
            echo "1. Vytvořit zálohu databáze<br>";
            echo "2. Zkontrolovat že máte správná přístupová práva<br>";
            echo "3. Ujistit se že na stránky nepřistupují jiní uživatelé<br>";
            echo "</div>";

            echo "<a href='pridej_chybejici_sloupce.php?execute=1' class='btn' onclick='return confirm(\"Opravdu chcete spustit migraci a přidat " . count($missingColumns) . " sloupců?\")'>▶️ SPUSTIT MIGRACI</a>";
            echo "<a href='admin.php' class='btn' style='background: #6c757d;'>← Zpět na admin</a>";
        }
    }

    // Zobrazit kompletní informace o tabulce
    echo "<h2>📊 Kompletní struktura tabulky wgs_reklamace:</h2>";

    $stmt = $pdo->query("SHOW FULL COLUMNS FROM wgs_reklamace");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<thead><tr><th>#</th><th>Sloupec</th><th>Typ</th><th>Null</th><th>Default</th><th>Komentář</th></tr></thead>";
    echo "<tbody>";

    foreach ($columns as $i => $col) {
        $highlight = array_key_exists($col['Field'], $requiredColumns) ? ' style="background: #ffffcc;"' : '';
        echo "<tr{$highlight}>";
        echo "<td>" . ($i + 1) . "</td>";
        echo "<td><strong>{$col['Field']}</strong></td>";
        echo "<td>{$col['Type']}</td>";
        echo "<td>{$col['Null']}</td>";
        echo "<td>" . ($col['Default'] ?? 'NULL') . "</td>";
        echo "<td>{$col['Comment']}</td>";
        echo "</tr>";
    }

    echo "</tbody></table>";

    echo "<div class='info'>";
    echo "<strong>ℹ️ INFO:</strong> Žluté zvýraznění = klíčový sloupec kontrolovaný touto migrací";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ CHYBA PŘIPOJENÍ K DATABÁZI</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
