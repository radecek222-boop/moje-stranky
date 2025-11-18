<?php
/**
 * Diagnostika: Zjištění chybějících tabulek Admin Control Center
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor.");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Diagnostika tabulek ACC</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1000px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; text-align: left; border: 1px solid #ddd; }
        th { background: #2D5016; color: white; }
        .exists { color: #28a745; font-weight: bold; }
        .missing { color: #dc3545; font-weight: bold; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Diagnostika Admin Control Center Tabulek</h1>";

    $requiredTables = [
        'wgs_theme_settings' => 'Nastavení vzhledu (barvy, fonty)',
        'wgs_content_texts' => 'Upravitelné texty',
        'wgs_system_config' => 'Systémová konfigurace',
        'wgs_pending_actions' => 'Čekající úkoly',
        'wgs_action_history' => 'Historie provedených akcí',
        'wgs_github_webhooks' => 'GitHub události'
    ];

    $existingTables = [];
    $missingTables = [];

    echo "<table>";
    echo "<tr><th>Tabulka</th><th>Popis</th><th>Status</th></tr>";

    foreach ($requiredTables as $table => $description) {
        $exists = false;
        try {
            $stmt = $pdo->query("SHOW TABLES LIKE " . $pdo->quote($table));
            if ($stmt->rowCount() > 0) {
                $exists = true;
                $existingTables[] = $table;
            } else {
                $missingTables[] = $table;
            }
        } catch (PDOException $e) {
            $missingTables[] = $table;
        }

        $statusClass = $exists ? 'exists' : 'missing';
        $statusText = $exists ? '✅ Existuje' : '❌ Chybí';

        echo "<tr>";
        echo "<td><code>{$table}</code></td>";
        echo "<td>{$description}</td>";
        echo "<td class='{$statusClass}'>{$statusText}</td>";
        echo "</tr>";
    }

    echo "</table>";

    if (empty($missingTables)) {
        echo "<div class='success'>";
        echo "<strong>✅ VŠECHNY TABULKY EXISTUJÍ</strong><br>";
        echo "Admin Control Center je plně nainstalován.";
        echo "</div>";
    } else {
        echo "<div class='error'>";
        echo "<strong>❌ CHYBÍ " . count($missingTables) . " TABULEK:</strong><br>";
        echo "<ul>";
        foreach ($missingTables as $table) {
            echo "<li><code>{$table}</code> - {$requiredTables[$table]}</li>";
        }
        echo "</ul>";
        echo "</div>";

        echo "<div class='warning'>";
        echo "<strong>⚠️ DŮSLEDEK:</strong><br>";
        echo "Kvůli chybějícím tabulkám nefungují tlačítka v kartě <strong>Akce a úkoly</strong> (HTTP 503 chyba).";
        echo "</div>";

        echo "<div class='success'>";
        echo "<strong>🔧 ŘEŠENÍ:</strong><br>";
        echo "Spusťte instalaci Admin Control Center, která vytvoří všechny chybějící tabulky:<br><br>";
        echo "<a href='/setup/install_admin_control_center.php' style='display: inline-block; padding: 10px 20px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px;'>";
        echo "🚀 Spustit instalaci ACC";
        echo "</a>";
        echo "</div>";
    }

    echo "<div style='margin-top: 2rem; padding: 15px; background: #e9ecef; border-radius: 5px;'>";
    echo "<strong>📊 Souhrn:</strong><br>";
    echo "✅ Existující tabulky: " . count($existingTables) . " z " . count($requiredTables) . "<br>";
    echo "❌ Chybějící tabulky: " . count($missingTables) . " z " . count($requiredTables);
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>❌ KRITICKÁ CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "<div style='margin-top: 2rem; text-align: center;'>";
echo "<a href='admin.php?tab=admin_actions' style='display: inline-block; padding: 10px 20px; background: #6c757d; color: white; text-decoration: none; border-radius: 5px;'>";
echo "← Zpět do Admin Panelu";
echo "</a>";
echo "</div>";

echo "</div></body></html>";
?>