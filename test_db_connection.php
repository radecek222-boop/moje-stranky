<?php
/**
 * Test databázového připojení
 * BEZPEČNOST: Pouze pro přihlášené uživatele
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Kontrola přihlášení
if (!isset($_SESSION['user_id']) && !(isset($_SESSION['is_admin']) && $_SESSION['is_admin'])) {
    http_response_code(401);
    die("PŘÍSTUP ODEPŘEN\nMusíte být přihlášeni pro zobrazení této stránky.\n");
}

echo "=== TEST PŘIPOJENÍ K DATABÁZI ===\n\n";

try {
    echo "Připojuji se k databázi...\n";
    echo "Host: " . DB_HOST . "\n";
    echo "Databáze: " . DB_NAME . "\n";
    echo "Uživatel: " . DB_USER . "\n\n";

    $pdo = getDbConnection();
    echo "[OK] Připojení k databázi úspěšné!\n\n";

    // Získání seznamu tabulek
    echo "=== SEZNAM TABULEK V DATABÁZI ===\n";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

    if (empty($tables)) {
        echo "[VAROVÁNÍ] Databáze neobsahuje žádné tabulky!\n";
    } else {
        echo "Nalezeno tabulek: " . count($tables) . "\n\n";
        foreach ($tables as $table) {
            echo "  - " . $table . "\n";
        }
    }

    echo "\n[OK] Test dokončen úspěšně!\n";

} catch (Exception $e) {
    echo "[CHYBA] " . $e->getMessage() . "\n";
    exit(1);
}
