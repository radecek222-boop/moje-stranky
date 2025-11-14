<?php
/**
 * Diagnostický skript - zjistí skutečnou databázi a strukturu
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../config/config.php';

echo "=== DATABÁZOVÁ DIAGNOSTIKA ===\n\n";

try {
    // Zkusit definovat DB konstanty z environment proměnných
    if (!defined('DB_HOST')) define('DB_HOST', getenv('DB_HOST') ?: $_SERVER['DB_HOST'] ?? 'localhost');
    if (!defined('DB_NAME')) define('DB_NAME', getenv('DB_NAME') ?: $_SERVER['DB_NAME'] ?? 'wgs-servicecz');
    if (!defined('DB_USER')) define('DB_USER', getenv('DB_USER') ?: $_SERVER['DB_USER'] ?? 'root');
    if (!defined('DB_PASS')) define('DB_PASS', getenv('DB_PASS') ?: $_SERVER['DB_PASS'] ?? '');

    echo "DEBUG: Pokus o připojení k databázi...\n";
    echo "  Host: " . DB_HOST . "\n";
    echo "  Database: " . DB_NAME . "\n";
    echo "  User: " . DB_USER . "\n\n";

    $pdo = getDbConnection();

    // 1. Zjistit jméno databáze
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $dbName = $result['db_name'];

    echo "1. PŘIPOJENÁ DATABÁZE: {$dbName}\n\n";

    // 2. Zjistit sloupce v tabulce wgs_reklamace
    echo "2. STRUKTURA TABULKY wgs_reklamace:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        echo "   - {$col['Field']} ({$col['Type']})\n";
    }

    echo "\n3. STRUKTURA TABULKY wgs_photos:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_photos");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($columns as $col) {
        echo "   - {$col['Field']} ({$col['Type']})\n";
    }

    echo "\n4. KONTROLA KLíčOVÝCH SLOUPCŮ:\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace WHERE Field IN ('reklamace_id', 'cislo', 'ticket_number', 'stav', 'status')");
    $keyColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "   Nalezené sloupce:\n";
    foreach ($keyColumns as $col) {
        echo "   ✓ {$col['Field']}\n";
    }

    echo "\n=== KONEC DIAGNOSTIKY ===\n";

} catch (Exception $e) {
    echo "CHYBA: " . $e->getMessage() . "\n";
}
