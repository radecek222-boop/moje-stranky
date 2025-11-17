<?php
/**
 * Add Missing Database Indexes
 * Spustí SQL příkazy pro přidání chybějících indexů
 */

require_once __DIR__ . '/init.php';

// Admin only
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized - Admin access required');
}

$pdo = getDbConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Adding Missing Indexes</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1200px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; margin: 10px 0; background: #d4edda; border-left: 4px solid #28a745; }
        .error { color: #dc3545; padding: 10px; margin: 10px 0; background: #f8d7da; border-left: 4px solid #dc3545; }
        .warning { color: #856404; padding: 10px; margin: 10px 0; background: #fff3cd; border-left: 4px solid #ffc107; }
        .info { color: #0c5460; padding: 10px; margin: 10px 0; background: #d1ecf1; border-left: 4px solid #17a2b8; }
        .summary { margin-top: 30px; padding: 20px; background: #e7f3ff; border-radius: 4px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    </style>
</head>
<body>
<div class='container'>
<h1>🔧 Adding Missing Database Indexes</h1>
";

// Indexy které chceme přidat
$indexes = [
    ['table' => 'notification_templates', 'column' => 'created_at'],
    ['table' => 'notification_templates', 'column' => 'updated_at'],
    ['table' => 'registration_keys', 'column' => 'created_at'],
    ['table' => 'registration_keys', 'column' => 'updated_at'],
    ['table' => 'users', 'column' => 'created_at'],
    ['table' => 'wgs_claims', 'column' => 'updated_at'],
    ['table' => 'wgs_content_texts', 'column' => 'updated_at'],
    ['table' => 'wgs_email_queue', 'column' => 'created_at'],
    ['table' => 'wgs_notifications', 'column' => 'created_at'],
    ['table' => 'wgs_notifications', 'column' => 'updated_at'],
    ['table' => 'wgs_customers', 'column' => 'email'],
    ['table' => 'wgs_customers', 'column' => 'created_at'],
    ['table' => 'wgs_customers', 'column' => 'updated_at'],
    ['table' => 'wgs_action_history', 'column' => 'created_at'],
    ['table' => 'wgs_github_webhooks', 'column' => 'created_at'],
    ['table' => 'wgs_pending_actions', 'column' => 'created_at'],
    ['table' => 'wgs_pending_actions', 'column' => 'status'],
    ['table' => 'wgs_security_events', 'column' => 'created_at'],
    ['table' => 'wgs_session_security', 'column' => 'created_at'],
    ['table' => 'wgs_system_config', 'column' => 'updated_at'],
    ['table' => 'wgs_theme_settings', 'column' => 'updated_at'],
];

$added = 0;
$skipped = 0;
$errors = 0;

echo "<div class='info'>Celkem " . count($indexes) . " indexů ke zpracování...</div>";

foreach ($indexes as $idx) {
    $table = $idx['table'];
    $column = $idx['column'];
    $indexName = "idx_{$column}";

    try {
        // Zkontrolovat jestli tabulka existuje
        $tableCheck = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($tableCheck->rowCount() === 0) {
            echo "<div class='warning'>⏭️ Tabulka `{$table}` neexistuje - přeskakuji</div>";
            $skipped++;
            continue;
        }

        // Zkontrolovat jestli sloupec existuje
        $colCheck = $pdo->query("SHOW COLUMNS FROM `$table` WHERE Field = '$column'");
        if ($colCheck->rowCount() === 0) {
            echo "<div class='warning'>⏭️ Sloupec `{$table}`.`{$column}` neexistuje - přeskakuji</div>";
            $skipped++;
            continue;
        }

        // Zkontrolovat jestli index už existuje
        $indexCheck = $pdo->query("SHOW INDEX FROM `$table` WHERE Column_name = '$column'");
        if ($indexCheck->rowCount() > 0) {
            echo "<div class='warning'>⏭️ Index na `{$table}`.`{$column}` už existuje - přeskakuji</div>";
            $skipped++;
            continue;
        }

        // Přidat index
        $sql = "ALTER TABLE `{$table}` ADD INDEX `{$indexName}` (`{$column}`)";
        $pdo->exec($sql);

        echo "<div class='success'>✅ Přidán index: `{$table}`.`{$indexName}` (`{$column}`)</div>";
        $added++;

    } catch (PDOException $e) {
        echo "<div class='error'>❌ Chyba při přidávání indexu `{$table}`.`{$column}`: " . htmlspecialchars($e->getMessage()) . "</div>";
        $errors++;
    }
}

echo "<div class='summary'>
    <h2>📊 Souhrn</h2>
    <ul>
        <li><strong>✅ Přidáno:</strong> {$added} indexů</li>
        <li><strong>⏭️ Přeskočeno:</strong> {$skipped} indexů</li>
        <li><strong>❌ Chyby:</strong> {$errors} indexů</li>
    </ul>
    <p><strong>Hotovo!</strong> Databázové indexy byly aktualizovány.</p>
    <p><a href='/admin.php'>← Zpět do Control Center</a></p>
</div>";

echo "</div></body></html>";
