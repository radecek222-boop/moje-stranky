<?php
/**
 * Kontrola správné databáze: wgs-servicecz01
 */

echo "=== STRUKTURA DATABÁZE wgs-servicecz01 ===\n\n";

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=wgs-servicecz01;charset=utf8mb4',
        'root',
        '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    echo "✅ Připojeno k databázi: wgs-servicecz01\n\n";

    // Zjistit sloupce v wgs_reklamace
    echo "STRUKTURA wgs_reklamace:\n";
    echo str_repeat("=", 80) . "\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_reklamace");
    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = $col['Key'] ? " [{$col['Key']}]" : "";
        $null = $col['Null'] === 'YES' ? ' NULL' : ' NOT NULL';
        $default = $col['Default'] !== null ? " DEFAULT '{$col['Default']}'" : "";
        echo sprintf("  %-30s %-20s%s%s%s\n",
            $col['Field'],
            $col['Type'],
            $key,
            $null,
            $default
        );
    }

    echo "\n\nSTRUKTURA wgs_photos:\n";
    echo str_repeat("=", 80) . "\n";
    $stmt = $pdo->query("SHOW COLUMNS FROM wgs_photos");
    while ($col = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $key = $col['Key'] ? " [{$col['Key']}]" : "";
        $null = $col['Null'] === 'YES' ? ' NULL' : ' NOT NULL';
        $default = $col['Default'] !== null ? " DEFAULT '{$col['Default']}'" : "";
        echo sprintf("  %-30s %-20s%s%s%s\n",
            $col['Field'],
            $col['Type'],
            $key,
            $null,
            $default
        );
    }

    echo "\n\nPOČET ZÁZNAMŮ:\n";
    echo str_repeat("=", 80) . "\n";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_reklamace");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  wgs_reklamace: {$count} záznamů\n";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_photos");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  wgs_photos: {$count} záznamů\n";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_documents");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  wgs_documents: {$count} záznamů\n";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_users");
    $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
    echo "  wgs_users: {$count} záznamů\n";

} catch (PDOException $e) {
    echo "❌ CHYBA: " . $e->getMessage() . "\n";
}
