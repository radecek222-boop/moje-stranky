<?php
/**
 * Test databázového připojení
 */

require_once __DIR__ . '/config/config.php';

echo "=== Test připojení k databázi ===\n\n";

try {
    echo "Připojuji se k databázi...\n";
    echo "Host: " . DB_HOST . "\n";
    echo "Databáze: " . DB_NAME . "\n";
    echo "Uživatel: " . DB_USER . "\n\n";

    $pdo = getDbConnection();
    echo "✅ Připojení k databázi úspěšné!\n\n";

    // Získání seznamu tabulek
    echo "=== Seznam tabulek v databázi ===\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "⚠️  Databáze neobsahuje žádné tabulky!\n";
    } else {
        echo "Nalezeno tabulek: " . count($tables) . "\n\n";
        foreach ($tables as $table) {
            echo "  - " . $table . "\n";
        }
    }

    echo "\n✅ Test dokončen úspěšně!\n";

} catch (Exception $e) {
    echo "❌ CHYBA: " . $e->getMessage() . "\n";
    exit(1);
}
