<?php
/**
 * Přidání dnešních optimalizačních úkolů do Akce & Úkoly
 */

require_once __DIR__ . '/init.php';

// Admin only
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();

echo "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <title>Přidání úkolů</title>
    <style>
        body { font-family: Arial, sans-serif; max-width: 1000px; margin: 40px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #333; }
        .success { color: #28a745; padding: 10px; margin: 10px 0; background: #d4edda; border-left: 4px solid #28a745; }
        .info { color: #0c5460; padding: 10px; margin: 10px 0; background: #d1ecf1; border-left: 4px solid #17a2b8; }
        .task { margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; }
        .task-title { font-weight: bold; font-size: 1.1em; margin-bottom: 5px; }
        .task-desc { color: #666; }
    </style>
</head>
<body>
<div class='container'>
<h1>📋 Přidání optimalizačních úkolů</h1>
";

// Zkontrolovat jestli tabulka existuje
try {
    $stmt = $pdo->query("SELECT 1 FROM wgs_pending_actions LIMIT 1");
} catch (PDOException $e) {
    echo "<div class='info'>❌ Tabulka wgs_pending_actions neexistuje. Nejprve spusťte instalaci Admin Control Center.</div>";
    echo "<p><a href='/install_admin_control_center.php'>→ Instalovat Admin Control Center</a></p>";
    echo "</div></body></html>";
    exit;
}

// Úkoly k přidání
$tasks = [
    [
        'title' => '🗜️ Minifikovat JS/CSS soubory',
        'description' => 'Spustit /minify_assets.php pro optimalizaci rychlosti. Úspora: ~68KB (30-40% redukce velikosti souborů)',
        'action_type' => 'optimize_assets',
        'priority' => 'high',
        'button_text' => 'Minifikovat nyní',
        'button_url' => '/minify_assets.php'
    ],
    [
        'title' => '📊 Přidat chybějící DB indexy',
        'description' => 'Spustit /add_indexes.php pro přidání 21 indexů. Zrychlí queries s WHERE/JOIN/ORDER BY.',
        'action_type' => 'add_db_indexes',
        'priority' => 'high',
        'button_text' => 'Přidat indexy',
        'button_url' => '/add_indexes.php'
    ],
    [
        'title' => '💾 Vytvořit první backup',
        'description' => 'Spustit /backup_system.php pro vytvoření zálohy databáze a důležitých souborů.',
        'action_type' => 'create_backup',
        'priority' => 'medium',
        'button_text' => 'Vytvořit backup',
        'button_url' => '/backup_system.php'
    ],
    [
        'title' => '🧹 Vyčistit selhavší emaily',
        'description' => 'Spustit /cleanup_failed_emails.php pro odstranění selhavších emailů z fronty.',
        'action_type' => 'cleanup_emails',
        'priority' => 'low',
        'button_text' => 'Vyčistit',
        'button_url' => '/cleanup_failed_emails.php'
    ],
    [
        'title' => '⚙️ Povolit Gzip kompresi',
        'description' => 'Přidat Gzip do .htaccess pro 60-70% redukci transfer size. Zkopírovat konfiguraci z OPTIMIZATION_ANALYSIS.md',
        'action_type' => 'enable_gzip',
        'priority' => 'high',
        'button_text' => 'Zobrazit návod',
        'button_url' => '/OPTIMIZATION_ANALYSIS.md'
    ],
    [
        'title' => '📦 Nastavit Browser Cache',
        'description' => 'Přidat cache headers do .htaccess pro rychlejší repeat visits (0 KB staženo). Návod v OPTIMIZATION_ANALYSIS.md',
        'action_type' => 'browser_cache',
        'priority' => 'high',
        'button_text' => 'Zobrazit návod',
        'button_url' => '/OPTIMIZATION_ANALYSIS.md'
    ]
];

$added = 0;
$skipped = 0;

foreach ($tasks as $task) {
    // Zkontrolovat jestli úkol už existuje
    $stmt = $pdo->prepare("
        SELECT id FROM wgs_pending_actions
        WHERE action_type = ? AND status IN ('pending', 'in_progress')
    ");
    $stmt->execute([$task['action_type']]);

    if ($stmt->rowCount() > 0) {
        echo "<div class='task'>
            <div class='task-title'>⏭️ {$task['title']}</div>
            <div class='task-desc'>Již existuje v Akce & Úkoly</div>
        </div>";
        $skipped++;
        continue;
    }

    // Přidat úkol
    try {
        $stmt = $pdo->prepare("
            INSERT INTO wgs_pending_actions (
                title,
                description,
                action_type,
                priority,
                button_text,
                button_url,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $stmt->execute([
            $task['title'],
            $task['description'],
            $task['action_type'],
            $task['priority'],
            $task['button_text'],
            $task['button_url']
        ]);

        echo "<div class='task'>
            <div class='task-title'>✅ {$task['title']}</div>
            <div class='task-desc'>{$task['description']}</div>
        </div>";

        $added++;

    } catch (PDOException $e) {
        echo "<div class='task'>
            <div class='task-title'>❌ {$task['title']}</div>
            <div class='task-desc'>Chyba: " . htmlspecialchars($e->getMessage()) . "</div>
        </div>";
    }
}

echo "<div class='success'>
    <h3>📊 Souhrn</h3>
    <ul>
        <li><strong>✅ Přidáno:</strong> {$added} úkolů</li>
        <li><strong>⏭️ Přeskočeno:</strong> {$skipped} úkolů (již existují)</li>
    </ul>
    <p><strong>Hotovo!</strong> Úkoly jsou nyní viditelné v Admin Control Center.</p>
    <p><a href='/admin.php?tab=control_center_actions'><strong>→ Otevřít Akce & Úkoly</strong></a></p>
</div>";

echo "</div></body></html>";
