<?php
/**
 * Instalátor Admin Control Center
 * 
 * Tento skript vytvoří databázové tabulky pro iOS-style admin panel
 * PO SPUŠTĚNÍ TENTO SOUBOR SMAŽTE!
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOST: Pouze admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    http_response_code(403);
    die('❌ PŘÍSTUP ODEPŘEN: Pouze admin může spustit instalaci.');
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalace Admin Control Center - WGS</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            max-width: 800px;
            width: 100%;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        .header h1 {
            font-size: 2rem;
            margin-bottom: 10px;
            font-weight: 700;
        }
        .header p {
            opacity: 0.9;
            font-size: 1.1rem;
        }
        .content {
            padding: 40px;
        }
        .status {
            padding: 20px;
            margin: 20px 0;
            border-radius: 12px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .status-icon {
            font-size: 2rem;
            flex-shrink: 0;
        }
        .success { background: #d4edda; color: #155724; border: 2px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 2px solid #f5c6cb; }
        .warning { background: #fff3cd; color: #856404; border: 2px solid #ffeeba; }
        .info { background: #d1ecf1; color: #0c5460; border: 2px solid #bee5eb; }
        .progress {
            background: #f0f0f0;
            border-radius: 10px;
            height: 40px;
            overflow: hidden;
            margin: 20px 0;
        }
        .progress-bar {
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            transition: width 0.3s ease;
        }
        .table-list {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
        }
        .table-list h3 {
            margin-bottom: 15px;
            color: #333;
        }
        .table-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 15px;
            background: white;
            border-radius: 8px;
            margin-bottom: 10px;
            border: 1px solid #dee2e6;
        }
        .table-name {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            color: #495057;
        }
        .table-count {
            background: #667eea;
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-decoration: none;
            border-radius: 10px;
            font-weight: 600;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }
        .btn-danger {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        .btn-success {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        .actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
            flex-wrap: wrap;
        }
        .icon-large {
            font-size: 4rem;
            text-align: center;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎨 Admin Control Center</h1>
            <p>iOS-style centrální řídicí panel</p>
        </div>
        <div class="content">
<?php
try {
    $pdo = getDbConnection();
    $startTime = microtime(true);

    // Kontrola zda tabulky již existují
    $existingTables = [];
    $requiredTables = [
        'wgs_theme_settings',
        'wgs_content_texts',
        'wgs_system_config',
        'wgs_pending_actions',
        'wgs_action_history',
        'wgs_github_webhooks'
    ];

    foreach ($requiredTables as $table) {
        try {
            $pdo->query("SELECT 1 FROM $table LIMIT 1");
            $existingTables[] = $table;
        } catch (PDOException $e) {
            // Tabulka neexistuje
        }
    }

    if (count($existingTables) === count($requiredTables)) {
        echo '<div class="icon-large">✅</div>';
        echo '<div class="status success">';
        echo '<span class="status-icon">✅</span>';
        echo '<div><strong>Admin Control Center je již nainstalován!</strong><br>Všechny tabulky existují.</div>';
        echo '</div>';

        // Zobrazit počty záznamů
        echo '<div class="table-list">';
        echo '<h3>📊 Stav databáze:</h3>';
        
        foreach ($requiredTables as $table) {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['count'];
            
            echo '<div class="table-item">';
            echo '<span class="table-name">' . $table . '</span>';
            echo '<span class="table-count">' . $count . ' záznamů</span>';
            echo '</div>';
        }
        echo '</div>';

        echo '<div class="actions">';
        echo '<a href="admin.php?tab=control_center" class="btn btn-success">Otevřít Control Center</a>';
        echo '<a href="?force_reinstall=1" class="btn btn-danger">Přeinstalovat (VAROVÁNÍ: smaže data!)</a>';
        echo '</div>';

        if (isset($_GET['force_reinstall'])) {
            echo '<div class="status warning">';
            echo '<span class="status-icon">⚠️</span>';
            echo '<div>Probíhá přeinstalace...</div>';
            echo '</div>';
            foreach ($requiredTables as $table) {
                $pdo->exec("DROP TABLE IF EXISTS $table");
            }
            header('Refresh: 2');
            exit;
        } else {
            exit;
        }
    }

    // Spustit instalaci
    echo '<div class="status info">';
    echo '<span class="status-icon">🚀</span>';
    echo '<div><strong>Spouštím instalaci...</strong></div>';
    echo '</div>';

    // Progress bar
    echo '<div class="progress">';
    echo '<div class="progress-bar" style="width: 20%">20%</div>';
    echo '</div>';

    // Načtení SQL souboru
    $sqlFile = __DIR__ . '/migration_admin_control_center.sql';
    if (!file_exists($sqlFile)) {
        throw new Exception('SQL migrace nenalezena: migration_admin_control_center.sql');
    }

    $sql = file_get_contents($sqlFile);
    
    // Rozdělení na jednotlivé příkazy
    $statements = array_filter(
        array_map('trim', explode(';', $sql)),
        function($stmt) {
            return !empty($stmt) && 
                   !preg_match('/^--/', $stmt) && 
                   !preg_match('/^SELECT.*status/', $stmt);
        }
    );

    $totalSteps = count($statements);
    $currentStep = 0;

    foreach ($statements as $statement) {
        if (!empty(trim($statement))) {
            $pdo->exec($statement);
            $currentStep++;
            $progress = round(($currentStep / $totalSteps) * 100);
        }
    }

    $endTime = microtime(true);
    $executionTime = round(($endTime - $startTime) * 1000);

    echo '<div class="progress">';
    echo '<div class="progress-bar" style="width: 100%">100% - Hotovo!</div>';
    echo '</div>';

    echo '<div class="icon-large">🎉</div>';

    echo '<div class="status success">';
    echo '<span class="status-icon">✅</span>';
    echo '<div><strong>Instalace úspěšně dokončena!</strong><br>';
    echo 'Čas vykonání: ' . $executionTime . ' ms</div>';
    echo '</div>';

    // Zobrazit vytvořené tabulky
    echo '<div class="table-list">';
    echo '<h3>📦 Vytvořené tabulky:</h3>';
    
    foreach ($requiredTables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $count = $result['count'];
            
            echo '<div class="table-item">';
            echo '<span class="table-name">' . $table . '</span>';
            echo '<span class="table-count">' . $count . ' záznamů</span>';
            echo '</div>';
        } catch (Exception $e) {
            echo '<div class="table-item">';
            echo '<span class="table-name">' . $table . '</span>';
            echo '<span class="table-count" style="background: #dc3545;">CHYBA</span>';
            echo '</div>';
        }
    }
    echo '</div>';

    echo '<div class="status info">';
    echo '<span class="status-icon">💡</span>';
    echo '<div>';
    echo '<strong>Co teď?</strong><br>';
    echo '1. Otevřete Admin Control Center<br>';
    echo '2. Projděte si jednotlivé sekce<br>';
    echo '3. <strong>DŮLEŽITÉ:</strong> Smažte tento soubor (install_admin_control_center.php)';
    echo '</div>';
    echo '</div>';

    echo '<div class="actions">';
    echo '<a href="admin.php?tab=control_center" class="btn btn-success">🚀 Otevřít Control Center</a>';
    echo '<a href="admin.php" class="btn">← Zpět do adminu</a>';
    echo '</div>';

    echo '<div class="status warning">';
    echo '<span class="status-icon">⚠️</span>';
    echo '<div><strong>BEZPEČNOST:</strong> Smažte soubor <code>install_admin_control_center.php</code> z webu!</div>';
    echo '</div>';

} catch (PDOException $e) {
    echo '<div class="icon-large">❌</div>';
    echo '<div class="status error">';
    echo '<span class="status-icon">❌</span>';
    echo '<div><strong>CHYBA DATABÁZE:</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '</div>';
} catch (Exception $e) {
    echo '<div class="icon-large">❌</div>';
    echo '<div class="status error">';
    echo '<span class="status-icon">❌</span>';
    echo '<div><strong>CHYBA:</strong><br>' . htmlspecialchars($e->getMessage()) . '</div>';
    echo '</div>';
}
?>
        </div>
    </div>
</body>
</html>
