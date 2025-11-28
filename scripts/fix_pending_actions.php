<?php
/**
 * Oprava action_url v wgs_pending_actions
 * Aktualizuje cesty skriptů aby ukazovaly na scripts/ adresář
 */

require_once __DIR__ . '/../init.php';

echo "=== OPRAVA ACTION_URL V PENDING ACTIONS ===\n\n";

try {
    $pdo = getDbConnection();

    // Mapping starých cest na nové
    $urlMappings = [
        // Staré neexistující cesty → Nové správné cesty
        'scripts/add_database_indexes.php' => 'scripts/add_missing_indexes.php',
        'add_database_indexes.php' => 'scripts/add_missing_indexes.php',
        'add_missing_indexes.php' => 'scripts/add_missing_indexes.php',

        'scripts/create_db_backup.php' => 'scripts/automated_backup.php',
        'create_db_backup.php' => 'scripts/automated_backup.php',
        'automated_backup.php' => 'scripts/automated_backup.php',

        'scripts/cleanup_emails.php' => 'scripts/cleanup_failed_emails.php',
        'cleanup_emails.php' => 'scripts/cleanup_failed_emails.php',
        'cleanup_failed_emails.php' => 'scripts/cleanup_failed_emails.php',

        'scripts/minify.php' => 'scripts/minify_assets.php',
        'minify.php' => 'scripts/minify_assets.php',
        'minify_assets.php' => 'scripts/minify_assets.php',

        'scripts/secure_setup.php' => 'scripts/secure_setup_directory.php',
        'secure_setup.php' => 'scripts/secure_setup_directory.php',
        'secure_setup_directory.php' => 'scripts/secure_setup_directory.php',
    ];

    $updated = 0;

    foreach ($urlMappings as $oldUrl => $newUrl) {
        // OPRAVA: Update pro PENDING i FAILED actions
        // Opravujeme URL bez ohledu na status, protože failed actions mají špatné URL
        $stmt = $pdo->prepare("
            UPDATE wgs_pending_actions
            SET action_url = :new_url
            WHERE action_url = :old_url
            AND status IN ('pending', 'failed')
        ");

        $stmt->execute([
            'old_url' => $oldUrl,
            'new_url' => $newUrl
        ]);

        $rowsAffected = $stmt->rowCount();
        if ($rowsAffected > 0) {
            echo "✅ Aktualizováno {$rowsAffected}×: {$oldUrl} → {$newUrl}\n";
            $updated += $rowsAffected;
        }
    }

    // Resetovat failed actions na pending (nyní už mají správné URL)
    echo "\n📝 Resetuji failed actions na pending...\n";

    $resetStmt = $pdo->prepare("
        UPDATE wgs_pending_actions
        SET status = 'pending', error_message = NULL
        WHERE status = 'failed'
        AND action_type IN ('add_db_indexes', 'create_backup', 'cleanup_emails', 'optimize_assets')
        AND action_url LIKE '%scripts/%'
    ");
    $resetStmt->execute();
    $reset = $resetStmt->rowCount();

    if ($reset > 0) {
        echo "✅ Resetováno {$reset} failed actions na pending\n";
    }

    echo "\n=== SHRNUTÍ ===\n";
    echo "Aktualizováno URL: {$updated}\n";
    echo "Resetováno failed: {$reset}\n";

    if ($updated > 0 || $reset > 0) {
        echo "\n✅ SUCCESS: Pending actions opraveny!\n";
        echo "💡 TIP: Nyní můžete spustit úkoly v Control Center → Pending Actions\n";
    } else {
        echo "\nℹ️  INFO: Žádné akce k opravě\n";
    }

} catch (Exception $e) {
    echo "❌ KRITICKÁ CHYBA: " . $e->getMessage() . "\n";
    exit(1);
}
