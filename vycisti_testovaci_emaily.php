<?php
/**
 * Vyčištění testovacích emailů z fronty
 * Smaže všechny pending/failed emaily
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Vyčistit testovací emaily</title>
<style>
body{font-family:'Segoe UI',sans-serif;max-width:800px;margin:50px auto;padding:20px;background:#f5f5f5;}
.container{background:white;padding:30px;border-radius:10px;box-shadow:0 2px 10px rgba(0,0,0,0.1);}
h1{color:#2D5016;border-bottom:3px solid #2D5016;padding-bottom:10px;}
.success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:15px;border-radius:5px;margin:15px 0;}
.error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:5px;margin:15px 0;}
.warning{background:#fff3cd;border:1px solid #ffeaa7;color:#856404;padding:15px;border-radius:5px;margin:15px 0;}
.info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:5px;margin:15px 0;}
.btn{display:inline-block;padding:12px 24px;background:#2D5016;color:white;text-decoration:none;border-radius:5px;margin:10px 5px 10px 0;font-weight:bold;}
.btn:hover{background:#1a300d;}
.btn-danger{background:#dc3545;}
.btn-danger:hover{background:#c82333;}
table{width:100%;border-collapse:collapse;margin:15px 0;}
th,td{border:1px solid #ddd;padding:10px;text-align:left;}
th{background:#2D5016;color:white;}
code{background:#f4f4f4;padding:3px 8px;border-radius:3px;}
</style></head><body><div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🗑️ Vyčištění testovacích emailů</h1>";

    // Statistiky PŘED
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM wgs_email_queue
        GROUP BY status
    ");
    $statsBefore = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $statsBefore[$row['status']] = $row['count'];
    }

    echo "<div class='info'>";
    echo "<strong>📊 AKTUÁLNÍ STAV FRONTY:</strong><br>";
    echo "Pending: <strong>" . ($statsBefore['pending'] ?? 0) . " emailů</strong><br>";
    echo "Failed: <strong>" . ($statsBefore['failed'] ?? 0) . " emailů</strong><br>";
    echo "Sent: <strong>" . ($statsBefore['sent'] ?? 0) . " emailů</strong><br>";
    echo "</div>";

    if (isset($_GET['confirm']) && $_GET['confirm'] === '1') {
        echo "<div class='info'><strong>MAZÁNÍ...</strong></div>";

        $pdo->beginTransaction();

        try {
            // Smazat všechny pending a failed emaily
            $stmt = $pdo->prepare("DELETE FROM wgs_email_queue WHERE status IN ('pending', 'failed')");
            $stmt->execute();
            $deletedCount = $stmt->rowCount();

            $pdo->commit();

            echo "<div class='success'>";
            echo "<h2>✅ VYČIŠTĚNÍ DOKONČENO!</h2>";
            echo "<p>Smazáno <strong>{$deletedCount} testovacích emailů</strong> (pending + failed)</p>";
            echo "</div>";

            echo "<div class='info'>";
            echo "<h3>📋 DALŠÍ KROKY:</h3>";
            echo "<ol>";
            echo "<li><strong>Aplikuj VARIANTU 1:</strong> <a href='/test_3_smtp_varianty.php'>test_3_smtp_varianty.php</a></li>";
            echo "<li><strong>Otestuj nový email:</strong> Vytvoř novou reklamaci nebo protokol a odešli email</li>";
            echo "<li><strong>Zkontroluj výsledek:</strong> <a href='/diagnostika_email_queue.php'>diagnostika_email_queue.php</a></li>";
            echo "</ol>";
            echo "</div>";

            echo "<a href='/test_3_smtp_varianty.php' class='btn'>→ Test SMTP variant</a> ";
            echo "<a href='/admin.php' class='btn'>Zpět na Admin</a>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'><strong>❌ CHYBA:</strong><br>" . htmlspecialchars($e->getMessage()) . "</div>";
        }

    } else {
        // Zobrazit náhled
        echo "<div class='warning'>";
        echo "<strong>⚠️ CO SE SMAŽE:</strong><br>";
        echo "Tato akce NENÁVRATNĚ smaže:<br>";
        echo "• <strong>" . ($statsBefore['pending'] ?? 0) . " pending emailů</strong><br>";
        echo "• <strong>" . ($statsBefore['failed'] ?? 0) . " failed emailů</strong><br>";
        echo "<br>";
        echo "Sent emaily (úspěšně odeslané) ZŮSTANOU zachované.";
        echo "</div>";

        echo "<div class='info'>";
        echo "<strong>💡 POZNÁMKA:</strong><br>";
        echo "Toto jsou pouze testovací emaily. Po vyčištění budete moci otestovat, zda nová SMTP konfigurace funguje.";
        echo "</div>";

        $totalToDelete = ($statsBefore['pending'] ?? 0) + ($statsBefore['failed'] ?? 0);

        if ($totalToDelete > 0) {
            echo "<a href='?confirm=1' class='btn btn-danger'>🗑️ SMAZAT {$totalToDelete} TESTOVACÍCH EMAILŮ</a> ";
            echo "<a href='/admin.php' class='btn'>Zrušit</a>";
        } else {
            echo "<div class='success'>✅ Fronta je prázdná - není co mazat!</div>";
            echo "<a href='/admin.php' class='btn'>Zpět na Admin</a>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
