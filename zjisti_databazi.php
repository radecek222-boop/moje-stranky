<?php
/**
 * DIAGNOSTIKA: Zjistí kterou databázi aplikace skutečně používá
 */

require_once 'init.php';

// Musíme načíst env_loader aby se načetly DB konstanty
if (file_exists(__DIR__ . '/includes/env_loader.php')) {
    require_once __DIR__ . '/includes/env_loader.php';
}

// Pak config
if (file_exists(__DIR__ . '/config/config.php')) {
    require_once __DIR__ . '/config/config.php';
}

echo "<!DOCTYPE html><html><head><meta charset='utf-8'><title>Databázová diagnostika</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;}";
echo "h1{color:#4ec9b0;}pre{background:#2d2d2d;padding:15px;border-left:3px solid #4ec9b0;}</style>";
echo "</head><body>";

echo "<h1>🔍 DATABÁZOVÁ DIAGNOSTIKA</h1>";

try {
    $pdo = getDbConnection();

    // 1. Zjistit jméno databáze
    $stmt = $pdo->query("SELECT DATABASE() as db_name");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $dbName = $result['db_name'];

    echo "<h2>1. PŘIPOJENÁ DATABÁZE:</h2>";
    echo "<pre><strong style='color:#ce9178;font-size:20px;'>{$dbName}</strong></pre>";

    // 2. Zjistit hostname
    $stmt = $pdo->query("SELECT @@hostname as hostname");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $hostname = $result['hostname'];

    echo "<h2>2. DATABÁZOVÝ SERVER:</h2>";
    echo "<pre>{$hostname}</pre>";

    // 3. Spočítat záznamy
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_reklamace");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'];

    echo "<h2>3. POČET REKLAMACÍ:</h2>";
    echo "<pre style='color:#4ec9b0;font-size:18px;'>{$count} záznamů</pre>";

    // 4. Zjistit sloupce v tabulce
    echo "<h2>4. STRUKTURA TABULKY wgs_reklamace:</h2>";
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    foreach ($columns as $col) {
        $key = $col['Key'] ? " <span style='color:#ce9178;'>[{$col['Key']}]</span>" : "";
        echo "{$col['Field']} ({$col['Type']}){$key}\n";
    }
    echo "</pre>";

    // 5. Ukázat 2 záznamy
    echo "<h2>5. UKÁZKA DAT (2 záznamy):</h2>";
    $stmt = $pdo->query("SELECT * FROM wgs_reklamace LIMIT 2");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<pre>";
    print_r($rows);
    echo "</pre>";

    echo "<h2 style='color:#4ec9b0;'>✅ ÚSPĚCH!</h2>";
    echo "<p>Aplikace používá databázi: <strong style='color:#ce9178;font-size:18px;'>{$dbName}</strong></p>";

} catch (Exception $e) {
    echo "<h2 style='color:#f48771;'>❌ CHYBA:</h2>";
    echo "<pre style='border-left-color:#f48771;'>{$e->getMessage()}</pre>";
}

echo "</body></html>";
