<?php
/**
 * Načíst Geoapify API klíč z databáze
 */

require_once __DIR__ . '/init.php';

try {
    $pdo = getDbConnection();

    // Hledat v settings tabulce
    $stmt = $pdo->query("SHOW TABLES LIKE '%settings%'");
    $settingsTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    echo "=== HLEDÁNÍ GEOAPIFY KLÍČE V DATABÁZI ===\n\n";

    if (empty($settingsTables)) {
        echo "❌ Žádná settings tabulka nenalezena\n";

        // Zkusit najít všechny tabulky
        $stmt = $pdo->query("SHOW TABLES");
        $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        echo "\nVšechny tabulky v DB:\n";
        foreach ($allTables as $table) {
            echo "  - $table\n";
        }
    } else {
        echo "Settings tabulky nalezeny:\n";
        foreach ($settingsTables as $table) {
            echo "\n📁 Tabulka: $table\n";

            // Zobrazit strukturu
            $stmt = $pdo->query("DESCRIBE `$table`");
            $structure = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo "  Sloupce: " . implode(', ', array_column($structure, 'Field')) . "\n";

            // Hledat geoapify
            $stmt = $pdo->query("SELECT * FROM `$table` WHERE `key` LIKE '%geoapify%' OR `name` LIKE '%geoapify%'");
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($results)) {
                echo "\n  ✅ NALEZENO:\n";
                foreach ($results as $row) {
                    echo "  " . print_r($row, true) . "\n";
                }
            } else {
                echo "  ❌ Žádný geoapify záznam\n";

                // Zobrazit všechny záznamy pro kontrolu
                $stmt = $pdo->query("SELECT * FROM `$table` LIMIT 10");
                $allRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);
                echo "\n  První záznamy v tabulce:\n";
                foreach ($allRecords as $record) {
                    echo "    - " . ($record['key'] ?? $record['name'] ?? json_encode($record)) . "\n";
                }
            }
        }
    }

    // Pokusit se najít v jakékoliv tabulce
    echo "\n\n=== HLEDÁNÍ VE VŠECH TABULKÁCH ===\n";
    $stmt = $pdo->query("SHOW TABLES");
    $allTables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    foreach ($allTables as $table) {
        $stmt = $pdo->query("DESCRIBE `$table`");
        $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Hledat textové sloupce
        $textColumns = [];
        foreach ($columns as $col) {
            $stmt = $pdo->query("SHOW COLUMNS FROM `$table` LIKE '$col'");
            $colInfo = $stmt->fetch(PDO::FETCH_ASSOC);
            if (strpos(strtolower($colInfo['Type']), 'char') !== false ||
                strpos(strtolower($colInfo['Type']), 'text') !== false) {
                $textColumns[] = $col;
            }
        }

        // Hledat geoapify v textových sloupcích
        foreach ($textColumns as $col) {
            try {
                $stmt = $pdo->query("SELECT * FROM `$table` WHERE `$col` LIKE '%geoapify%' LIMIT 5");
                $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (!empty($results)) {
                    echo "\n✅ Nalezeno v tabulce: $table, sloupec: $col\n";
                    foreach ($results as $row) {
                        echo "  " . print_r($row, true) . "\n";
                    }
                }
            } catch (Exception $e) {
                // Ignorovat chyby
            }
        }
    }

} catch (Exception $e) {
    echo "❌ Chyba: " . $e->getMessage() . "\n";
}
