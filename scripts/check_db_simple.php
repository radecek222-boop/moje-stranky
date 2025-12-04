<?php
/**
 * Jednoduchá kontrola databáze
 */

echo "=== DATABÁZOVÁ DIAGNOSTIKA ===\n\n";

try {
    // Připojení k databázi (změň hodnoty podle potřeby)
    $pdo = new PDO(
        'mysql:host=localhost;dbname=wgs-servicecz;charset=utf8mb4',
        'root',  // změň na své uživatelské jméno
        '',      // změň na své heslo
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "Připojeno k databázi: wgs-servicecz\n\n";

    // Zjistit sloupce v wgs_reklamace
    echo "STRUKTURA wgs_reklamace:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }

    echo "\n\nSTRUKTURA wgs_photos:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_photos");
    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "  " . $col['Field'] . " (" . $col['Type'] . ")\n";
    }

} catch (PDOException $e) {
    echo "CHYBA: " . $e->getMessage() . "\n";
    echo "\nNAPOVĚDA: Změň přihlašovací údaje v souboru check_db_simple.php\n";
}
