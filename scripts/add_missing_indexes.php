<?php
/**
 * Přidání chybějících DB indexů
 * Podle diagnostiky Control Center - 13 chybějících indexů
 */

require_once __DIR__ . '/../init.php';

echo "=== PŘIDÁVÁNÍ CHYBĚJÍCÍCH DB INDEXŮ ===\n\n";

try {
    $pdo = getDbConnection();

    $indexes = [
        // Tabulka wgs_photos
        ['table' => 'wgs_photos', 'column' => 'created_at', 'name' => 'idx_photos_created'],
        ['table' => 'wgs_photos', 'column' => 'updated_at', 'name' => 'idx_photos_updated'],

        // Tabulka wgs_registration_keys
        ['table' => 'wgs_registration_keys', 'column' => 'created_at', 'name' => 'idx_regkeys_created'],

        // Tabulka wgs_reklamace
        ['table' => 'wgs_reklamace', 'column' => 'email', 'name' => 'idx_reklamace_email'],
        ['table' => 'wgs_reklamace', 'column' => 'updated_at', 'name' => 'idx_reklamace_updated'],

        // Tabulka wgs_sessions
        ['table' => 'wgs_sessions', 'column' => 'created_at', 'name' => 'idx_sessions_created'],
        ['table' => 'wgs_sessions', 'column' => 'last_activity', 'name' => 'idx_sessions_activity'],

        // Tabulka wgs_settings
        ['table' => 'wgs_settings', 'column' => 'updated_at', 'name' => 'idx_settings_updated'],

        // Tabulka wgs_smtp_settings
        ['table' => 'wgs_smtp_settings', 'column' => 'created_at', 'name' => 'idx_smtp_created'],
        ['table' => 'wgs_smtp_settings', 'column' => 'updated_at', 'name' => 'idx_smtp_updated'],

        // Tabulka wgs_technici
        ['table' => 'wgs_technici', 'column' => 'created_at', 'name' => 'idx_technici_created'],

        // Tabulka wgs_action_history
        ['table' => 'wgs_action_history', 'column' => 'executed_at', 'name' => 'idx_action_executed'],

        // Tabulka wgs_pending_actions
        ['table' => 'wgs_pending_actions', 'column' => 'created_at', 'name' => 'idx_pending_created'],
    ];

    $added = 0;
    $skipped = 0;
    $errors = 0;

    foreach ($indexes as $index) {
        $table = $index['table'];
        $column = $index['column'];
        $indexName = $index['name'];

        // Kontrola jestli tabulka existuje
        $tableCheck = $pdo->query("SHOW TABLES LIKE '{$table}'");
        if ($tableCheck->rowCount() === 0) {
            echo "⚠️  Tabulka {$table} neexistuje - SKIP\n";
            $skipped++;
            continue;
        }

        // Kontrola jestli sloupec existuje
        $columnCheck = $pdo->query("SHOW COLUMNS FROM {$table} LIKE '{$column}'");
        if ($columnCheck->rowCount() === 0) {
            echo "⚠️  Sloupec {$table}.{$column} neexistuje - SKIP\n";
            $skipped++;
            continue;
        }

        // Kontrola jestli index již existuje
        $indexCheck = $pdo->query("SHOW INDEX FROM {$table} WHERE Column_name = '{$column}'");
        if ($indexCheck->rowCount() > 0) {
            echo "✓ Index na {$table}.{$column} již existuje - SKIP\n";
            $skipped++;
            continue;
        }

        // Přidat index
        try {
            $sql = "ALTER TABLE {$table} ADD INDEX {$indexName} ({$column})";
            $pdo->exec($sql);
            echo "✅ Přidán index: {$indexName} na {$table}.{$column}\n";
            $added++;
        } catch (PDOException $e) {
            echo "❌ Chyba při přidávání {$indexName}: " . $e->getMessage() . "\n";
            $errors++;
        }
    }

    echo "\n=== SHRNUTÍ ===\n";
    echo "Přidáno: {$added}\n";
    echo "Přeskočeno: {$skipped}\n";
    echo "Chyby: {$errors}\n";

    if ($added > 0) {
        echo "\n✅ SUCCESS: Indexy byly úspěšně přidány!\n";
    } else if ($errors > 0) {
        echo "\n⚠️  WARNING: Některé indexy se nepodařilo přidat!\n";
    } else {
        echo "\nℹ️  INFO: Všechny indexy již existují.\n";
    }

} catch (Exception $e) {
    echo "❌ KRITICKÁ CHYBA: " . $e->getMessage() . "\n";
    exit(1);
}
