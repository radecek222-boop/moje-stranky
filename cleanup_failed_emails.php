<?php
/**
 * Cleanup Failed Emails
 * Vyčistí staré failed emaily z email queue
 */

require_once __DIR__ . '/init.php';

// Admin only
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized - Admin access required');
}

$pdo = getDbConnection();

try {
    // Zjistit počet failed emails
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_email_queue WHERE status = 'failed'");
    $beforeCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Cleanup Failed Emails</title>\n<style>body { font-family: monospace; padding: 2rem; background: #1e1e1e; color: #d4d4d4; }</style>\n</head>\n<body>\n";
    echo "<h1>🧹 Cleanup Failed Emails</h1>\n";
    echo "<p>Nalezeno <strong>{$beforeCount}</strong> failed emailů</p>\n";

    if ($beforeCount > 0) {
        // Smazat failed emaily starší než 7 dní
        $stmt = $pdo->prepare("
            DELETE FROM wgs_email_queue
            WHERE status = 'failed'
            AND created_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
        ");
        $stmt->execute();
        $deleted = $stmt->rowCount();

        echo "<p>✓ Smazáno <strong>{$deleted}</strong> failed emailů (starších než 7 dní)</p>\n";

        // Zkontrolovat kolik jich zbylo
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_email_queue WHERE status = 'failed'");
        $afterCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

        if ($afterCount > 0) {
            echo "<p>⚠️  Zbývá <strong>{$afterCount}</strong> failed emailů (mladších než 7 dní)</p>\n";
            echo "<p><a href='?force=1' style='color: #4ec9b0'>Smazat všechny failed emaily</a></p>\n";

            if (isset($_GET['force']) && $_GET['force'] == '1') {
                $stmt = $pdo->query("DELETE FROM wgs_email_queue WHERE status = 'failed'");
                $forcedeleted = $stmt->rowCount();
                echo "<p>✓ Force delete: Smazáno <strong>{$forcedeleted}</strong> failed emailů</p>\n";
                echo "<p><strong>✅ Email queue vyčištěna!</strong></p>\n";
            }
        } else {
            echo "<p><strong>✅ Email queue vyčištěna!</strong></p>\n";
        }
    } else {
        echo "<p><strong>✅ Žádné failed emaily k vyčištění</strong></p>\n";
    }

    echo "<p><a href='/admin.php?tab=console' style='color: #569cd6'>← Zpět na Developer Console</a></p>\n";
    echo "</body>\n</html>";

} catch (Exception $e) {
    echo "<p style='color: #f48771'>❌ Chyba: " . htmlspecialchars($e->getMessage()) . "</p>\n";
}
