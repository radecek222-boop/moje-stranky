<?php
/**
 * Vyčištění karty "Akce & Úkoly"
 * Smaže všechny dismissed a completed akce
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit tento skript.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Vyčištění Akcí & Úkolů</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016;
             padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; }
        .btn:hover { background: #1a300d; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #2D5016; color: white; }
        tr:nth-child(even) { background: #f8f9fa; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>Vyčištění Akcí & Úkolů</h1>";

    // Zobrazení aktuálního stavu
    $stmt = $pdo->query("
        SELECT
            status,
            COUNT(*) as pocet
        FROM wgs_pending_actions
        GROUP BY status
        ORDER BY
            FIELD(status, 'pending', 'in_progress', 'completed', 'failed', 'dismissed')
    ");
    $stavy = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<div class='info'>";
    echo "<strong>📊 Aktuální stav:</strong><br>";
    foreach ($stavy as $stav) {
        $ikona = [
            'pending' => '⏳',
            'in_progress' => '🔄',
            'completed' => '✅',
            'failed' => '❌',
            'dismissed' => '🚫'
        ][$stav['status']] ?? '❓';

        echo "{$ikona} {$stav['status']}: <strong>{$stav['pocet']}</strong> úkolů<br>";
    }
    echo "</div>";

    // Pokud je nastaveno ?execute=1, provést cleanup
    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='info'><strong>SPOUŠTÍM VYČIŠTĚNÍ...</strong></div>";

        // Načíst akce ke smazání
        $stmt = $pdo->query("
            SELECT id, action_title, status, created_at
            FROM wgs_pending_actions
            WHERE status IN ('completed', 'dismissed', 'failed')
            ORDER BY created_at DESC
        ");
        $keSmazani = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($keSmazani) > 0) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Název</th><th>Status</th><th>Vytvořeno</th></tr>";
            foreach ($keSmazani as $akce) {
                echo "<tr>";
                echo "<td>{$akce['id']}</td>";
                echo "<td>{$akce['action_title']}</td>";
                echo "<td>{$akce['status']}</td>";
                echo "<td>{$akce['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";

            // Smazat
            $stmt = $pdo->prepare("
                DELETE FROM wgs_pending_actions
                WHERE status IN ('completed', 'dismissed', 'failed')
            ");
            $stmt->execute();
            $smazano = $stmt->rowCount();

            echo "<div class='success'>";
            echo "<strong>✅ VYČIŠTĚNÍ DOKONČENO!</strong><br>";
            echo "Smazáno: <strong>{$smazano}</strong> dokončených/odmítnutých/selhavších úkolů<br>";
            echo "</div>";

            // Zobrazit zbývající úkoly
            $stmt = $pdo->query("
                SELECT id, action_title, status, priority, created_at
                FROM wgs_pending_actions
                WHERE status IN ('pending', 'in_progress')
                ORDER BY
                    FIELD(priority, 'critical', 'high', 'medium', 'low'),
                    created_at DESC
            ");
            $zbyvajici = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (count($zbyvajici) > 0) {
                echo "<div class='info'>";
                echo "<strong>📋 Zbývající úkoly ({count($zbyvajici)}):</strong><br>";
                echo "<table>";
                echo "<tr><th>Priorita</th><th>Název</th><th>Status</th></tr>";
                foreach ($zbyvajici as $ukol) {
                    $ikony = [
                        'critical' => '🔴',
                        'high' => '🟠',
                        'medium' => '🟡',
                        'low' => '🟢'
                    ];
                    $ikona = $ikony[$ukol['priority']] ?? '⚪';

                    echo "<tr>";
                    echo "<td>{$ikona} {$ukol['priority']}</td>";
                    echo "<td>{$ukol['action_title']}</td>";
                    echo "<td>{$ukol['status']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
                echo "</div>";
            } else {
                echo "<div class='warning'>";
                echo "⚠️ <strong>POZOR:</strong> Po vyčištění nezůstal žádný úkol!<br>";
                echo "Spusťte <a href='aktualizuj_akce_ukoly.php'>aktualizuj_akce_ukoly.php</a> pro přidání úkolu 'Instalace PHPMailer'.";
                echo "</div>";
            }

        } else {
            echo "<div class='info'>";
            echo "ℹ️ Žádné dokončené/odmítnuté úkoly k odstranění.";
            echo "</div>";
        }

        echo "<div style='margin: 30px 0;'>";
        echo "<a href='admin.php' class='btn'>← Zpět do Admin Panelu</a>";
        echo "</div>";

    } else {
        // Náhled co bude smazáno
        $stmt = $pdo->query("
            SELECT id, action_title, status, created_at
            FROM wgs_pending_actions
            WHERE status IN ('completed', 'dismissed', 'failed')
            ORDER BY created_at DESC
        ");
        $keSmazani = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($keSmazani) > 0) {
            echo "<div class='warning'>";
            echo "<strong>⚠️ NÁHLED VYČIŠTĚNÍ</strong><br><br>";
            echo "Budou smazány následující úkoly ({count($keSmazani)}):<br>";
            echo "</div>";

            echo "<table>";
            echo "<tr><th>ID</th><th>Název</th><th>Status</th><th>Vytvořeno</th></tr>";
            foreach ($keSmazani as $akce) {
                echo "<tr>";
                echo "<td>{$akce['id']}</td>";
                echo "<td>{$akce['action_title']}</td>";
                echo "<td>{$akce['status']}</td>";
                echo "<td>{$akce['created_at']}</td>";
                echo "</tr>";
            }
            echo "</table>";

            echo "<div style='margin: 30px 0;'>";
            echo "<a href='?execute=1' class='btn btn-danger'>🗑️ SMAZAT VŠECHNY DOKONČENÉ ÚKOLY</a>";
            echo "<a href='admin.php' class='btn' style='background: #6c757d;'>← Zpět do Admin Panelu</a>";
            echo "</div>";
        } else {
            echo "<div class='success'>";
            echo "✅ Karta 'Akce & Úkoly' je již vyčištěna!<br>";
            echo "Žádné dokončené/odmítnuté úkoly k odstranění.";
            echo "</div>";

            echo "<div style='margin: 30px 0;'>";
            echo "<a href='admin.php' class='btn'>← Zpět do Admin Panelu</a>";
            echo "</div>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
