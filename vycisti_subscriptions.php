<?php
/**
 * Vycisteni starych push subscriptions bez user_id
 *
 * Odstrani subscriptions ktere nemaji prirazeného uzivatele.
 * Uzivatele se pak musi znovu prihlasit a povolit notifikace.
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN: Pouze administrator.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Vycisteni Push Subscriptions</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 900px;
               margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h3 { margin-top: 25px; color: #444; }
        .success { background: #d4edda; color: #155724; padding: 12px;
                   border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; color: #721c24; padding: 12px;
                 border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; color: #0c5460; padding: 12px;
                border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; color: #856404; padding: 12px;
                   border-radius: 5px; margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #333;
               color: white; text-decoration: none; border-radius: 5px;
               margin: 15px 5px 10px 0; border: none; cursor: pointer;
               font-size: 1rem; }
        .btn:hover { background: #555; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 8px 12px; border: 1px solid #ddd; text-align: left;
                 font-size: 13px; }
        th { background: #f0f0f0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Vycisteni Push Subscriptions</h1>";

    // Statistiky
    $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_push_subscriptions WHERE user_id IS NULL OR user_id = ''");
    $bezUserId = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_push_subscriptions WHERE user_id IS NOT NULL AND user_id != ''");
    $sUserId = $stmt->fetchColumn();

    echo "<h3>Aktualni stav:</h3>";
    echo "<div class='info'>";
    echo "Subscriptions s user_id: <strong>{$sUserId}</strong><br>";
    echo "Subscriptions BEZ user_id: <strong>{$bezUserId}</strong>";
    echo "</div>";

    if ($bezUserId == 0) {
        echo "<div class='success'>Vsechny subscriptions maji user_id - neni co cistit.</div>";
    } else {
        // Zobrazit subscriptions bez user_id
        echo "<h3>Subscriptions bez user_id (budou smazany):</h3>";
        $stmt = $pdo->query("
            SELECT id, email, platforma, datum_vytvoreni, LEFT(endpoint, 60) as endpoint_short
            FROM wgs_push_subscriptions
            WHERE user_id IS NULL OR user_id = ''
        ");
        $subscriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<table>";
        echo "<tr><th>ID</th><th>Email</th><th>Platforma</th><th>Vytvoreno</th><th>Endpoint</th></tr>";
        foreach ($subscriptions as $sub) {
            echo "<tr>";
            echo "<td>{$sub['id']}</td>";
            echo "<td>" . htmlspecialchars($sub['email'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($sub['platforma'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($sub['datum_vytvoreni'] ?? '-') . "</td>";
            echo "<td><code>" . htmlspecialchars($sub['endpoint_short']) . "...</code></td>";
            echo "</tr>";
        }
        echo "</table>";

        if (isset($_GET['execute']) && $_GET['execute'] === '1') {
            // Smazat subscriptions bez user_id
            $stmt = $pdo->prepare("DELETE FROM wgs_push_subscriptions WHERE user_id IS NULL OR user_id = ''");
            $stmt->execute();
            $deleted = $stmt->rowCount();

            echo "<div class='success'>";
            echo "<strong>SMAZANO {$deleted} subscriptions bez user_id!</strong><br><br>";
            echo "Uzivatele se nyni musi:<br>";
            echo "1. Odhlasit a znovu prihlasit<br>";
            echo "2. Povolit notifikace (v PWA/prohlizeci)";
            echo "</div>";
        } else {
            echo "<div class='warning'>";
            echo "<strong>Pozor:</strong> Tato akce smaze {$bezUserId} subscriptions bez user_id.<br>";
            echo "Uzivatele se pak budou muset znovu prihlasit a povolit notifikace.";
            echo "</div>";

            echo "<a href='?execute=1' class='btn btn-danger'>SMAZAT SUBSCRIPTIONS BEZ USER_ID</a>";
        }
    }

    echo "<br><a href='diagnostika_push.php' class='btn' style='background:#666;'>Zpet na diagnostiku</a>";
    echo "<a href='admin.php' class='btn' style='background:#666;'>Zpet do Admin</a>";

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
