<?php
/**
 * Vyčištění selhavších emailů z email queue
 * Smaže emaily starší než 7 dní se stavem 'failed'
 */

require_once __DIR__ . '/../init.php';

echo "=== ČIŠTĚNÍ SELHAVŠÍCH EMAILŮ ===\n\n";

try {
    $pdo = getDbConnection();

    // Kontrola jestli tabulka wgs_email_queue existuje
    $tableCheck = $pdo->query("SHOW TABLES LIKE 'wgs_email_queue'");
    if ($tableCheck->rowCount() === 0) {
        echo "ℹ️  Tabulka wgs_email_queue neexistuje - není co čistit\n";
        echo "OK\n";
        exit(0);
    }

    // Spočítat selhavší emaily
    $countStmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM wgs_email_queue
        WHERE status = 'failed'
    ");
    $totalFailed = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];

    echo "📊 Celkem selhavších emailů: {$totalFailed}\n\n";

    if ($totalFailed === 0) {
        echo "Žádné selhavší emaily ke smazání!\n";
        exit(0);
    }

    // Spočítat staré selhavší emaily (> 7 dní)
    $oldCountStmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM wgs_email_queue
        WHERE status = 'failed'
        AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $oldFailed = $oldCountStmt->fetch(PDO::FETCH_ASSOC)['count'];

    echo "🗑️  Selhavší emaily starší než 7 dní: {$oldFailed}\n";

    if ($oldFailed === 0) {
        echo "ℹ️  Všechny selhavší emaily jsou mladší než 7 dní - ponecháváme je\n";
        echo "OK\n";
        exit(0);
    }

    // Smazat staré selhavší emaily
    $deleteStmt = $pdo->prepare("
        DELETE FROM wgs_email_queue
        WHERE status = 'failed'
        AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $deleteStmt->execute();
    $deleted = $deleteStmt->rowCount();

    echo "Smazáno: {$deleted} selhavších emailů\n";

    // Kontrola zbývajících selhavších emailů
    $remainingStmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM wgs_email_queue
        WHERE status = 'failed'
    ");
    $remaining = $remainingStmt->fetch(PDO::FETCH_ASSOC)['count'];

    echo "📊 Zbývá selhavších emailů: {$remaining}\n";

    if ($remaining > 0) {
        echo "\n💡 TIP: Zkontrolujte SMTP nastavení pokud selhávání pokračuje\n";
    }

    echo "\nCLEANUP DOKONČEN!\n";

} catch (Exception $e) {
    echo "KRITICKÁ CHYBA: " . $e->getMessage() . "\n";
    exit(1);
}
