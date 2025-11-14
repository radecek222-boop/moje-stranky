<?php
/**
 * Přidání dnešních optimalizačních úkolů do Akce & Úkoly
 * BEZPEČNOST: Vyžaduje POST + CSRF token pro ochranu před CSRF útoky
 */

require_once __DIR__ . '/init.php';

// Admin only
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();

// BEZPEČNOST: CSRF ochrana - akce mění databázi, musí být POST s tokenem
$isPostRequest = $_SERVER['REQUEST_METHOD'] === 'POST';
$csrfValid = false;

if ($isPostRequest) {
    $csrfToken = $_POST['csrf_token'] ?? null;
    $csrfValid = $csrfToken && validateCSRFToken($csrfToken);

    if (!$csrfValid) {
        http_response_code(403);
        die('CSRF token validation failed. Refresh the page and try again.');
    }
}

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
        .warning { color: #856404; padding: 10px; margin: 10px 0; background: #fff3cd; border-left: 4px solid #ffc107; }
        .task { margin: 15px 0; padding: 15px; background: #f8f9fa; border-radius: 4px; }
        .task-title { font-weight: bold; font-size: 1.1em; margin-bottom: 5px; }
        .task-desc { color: #666; }
        .btn { padding: 12px 24px; background: #667eea; color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 1em; }
        .btn:hover { background: #5a67d8; }
        .task-preview { background: #f8f9fa; padding: 10px; margin: 10px 0; border-left: 3px solid #667eea; }
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
        'action_title' => '🗜️ Minifikovat JS/CSS soubory',
        'action_description' => 'Spustit /minify_assets.php pro optimalizaci rychlosti. Úspora: ~68KB (30-40% redukce velikosti souborů)',
        'action_type' => 'optimize_assets',
        'action_url' => '/minify_assets.php',
        'priority' => 'high'
    ],
    [
        'action_title' => '📊 Přidat chybějící DB indexy',
        'action_description' => 'Spustit /add_indexes.php pro přidání 21 indexů. Zrychlí queries s WHERE/JOIN/ORDER BY.',
        'action_type' => 'add_db_indexes',
        'action_url' => '/add_indexes.php',
        'priority' => 'high'
    ],
    [
        'action_title' => '💾 Vytvořit první backup',
        'action_description' => 'Spustit /backup_system.php pro vytvoření zálohy databáze a důležitých souborů.',
        'action_type' => 'create_backup',
        'action_url' => '/backup_system.php',
        'priority' => 'medium'
    ],
    [
        'action_title' => '🧹 Vyčistit selhavší emaily',
        'action_description' => 'Spustit /cleanup_failed_emails.php pro odstranění selhavších emailů z fronty.',
        'action_type' => 'cleanup_emails',
        'action_url' => '/cleanup_failed_emails.php',
        'priority' => 'low'
    ],
    [
        'action_title' => '⚙️ Povolit Gzip kompresi',
        'action_description' => 'Přidat Gzip do .htaccess pro 60-70% redukci transfer size. Zkopírovat konfiguraci z OPTIMIZATION_ANALYSIS.md',
        'action_type' => 'enable_gzip',
        'action_url' => '/OPTIMIZATION_ANALYSIS.md',
        'priority' => 'high'
    ],
    [
        'action_title' => '📦 Nastavit Browser Cache',
        'action_description' => 'Přidat cache headers do .htaccess pro rychlejší repeat visits (0 KB staženo). Návod v OPTIMIZATION_ANALYSIS.md',
        'action_type' => 'browser_cache',
        'action_url' => '/OPTIMIZATION_ANALYSIS.md',
        'priority' => 'high'
    ]
];

// Pokud není POST request, zobrazit potvrzovací formulář
if (!$isPostRequest) {
    echo "<div class='warning'>
        <strong>⚠️ BEZPEČNOSTNÍ UPOZORNĚNÍ</strong><br>
        Tento skript přidá úkoly do databáze. Pro ochranu před CSRF útoky je vyžadováno potvrzení.
    </div>";

    echo "<h2>📋 Úkoly k přidání:</h2>";
    foreach ($tasks as $task) {
        echo "<div class='task-preview'>
            <div class='task-title'>{$task['action_title']}</div>
            <div class='task-desc'>{$task['action_description']}</div>
            <small>Priorita: <strong>{$task['priority']}</strong></small>
        </div>";
    }

    echo "<form method='POST'>
        <input type='hidden' name='csrf_token' value='" . htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') . "'>
        <br>
        <button type='submit' class='btn'>✅ Potvrdit a přidat všech " . count($tasks) . " úkolů</button>
    </form>";

    echo "</div></body></html>";
    exit;
}

// POST request s platným CSRF tokenem - provést přidání úkolů
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
            <div class='task-title'>⏭️ {$task['action_title']}</div>
            <div class='task-desc'>Již existuje v Akce & Úkoly</div>
        </div>";
        $skipped++;
        continue;
    }

    // Přidat úkol
    try {
        $stmt = $pdo->prepare("
            INSERT INTO wgs_pending_actions (
                action_title,
                action_description,
                action_type,
                action_url,
                priority,
                status,
                created_at
            ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");

        $stmt->execute([
            $task['action_title'],
            $task['action_description'],
            $task['action_type'],
            $task['action_url'],
            $task['priority']
        ]);

        echo "<div class='task'>
            <div class='task-title'>✅ {$task['action_title']}</div>
            <div class='task-desc'>{$task['action_description']}</div>
        </div>";

        $added++;

    } catch (PDOException $e) {
        echo "<div class='task'>
            <div class='task-title'>❌ {$task['action_title']}</div>
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
