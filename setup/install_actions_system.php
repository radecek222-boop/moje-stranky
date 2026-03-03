<?php
/**
 * INSTALAČNÍ SCRIPT: Systém akcí a úkolů
 *
 * Tento script vytvoří kompletní infrastrukturu pro systém akcí v Control Center:
 * - Tabulka wgs_pending_actions (nevyřešené úlohy)
 * - Tabulka wgs_action_history (audit trail)
 * - Přidá iniciální SMTP instalační úlohu
 *
 * POUŽITÍ: Otevřete v prohlížeči jako admin
 * URL: https://your-domain.com/install_actions_system.php
 */

require_once __DIR__ . '/../init.php';

// BEZPEČNOST: Pouze admin
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    http_response_code(403);
    die('
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <title>Přístup odepřen</title>
        <style>
            body { font-family: Arial; text-align: center; padding: 3rem; background: #f5f5f5; }
            .error { background: white; padding: 2rem; border-radius: 8px; max-width: 500px; margin: 0 auto; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
            h1 { color: #dc3545; }
        </style>
    </head>
    <body>
        <div class="error">
            <h1>⛔ Přístup odepřen</h1>
            <p>Tento script může spustit pouze administrátor.</p>
            <p><a href="admin.php">Přihlásit se jako admin</a></p>
        </div>
    </body>
    </html>
    ');
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalace systému akcí</title>
    <style>
        * { box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 2rem;
            min-height: 100vh;
        }
        .container {
            max-width: 900px;
            margin: 0 auto;
        }
        .card {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
        }
        h1 {
            color: #333;
            font-size: 1.8rem;
            margin: 0 0 0.5rem 0;
        }
        h2 {
            color: #667eea;
            font-size: 1.2rem;
            margin: 1.5rem 0 0.75rem 0;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 0.5rem;
        }
        .subtitle {
            color: #666;
            font-size: 0.95rem;
            margin: 0 0 1rem 0;
        }
        .step {
            padding: 1rem;
            margin: 0.75rem 0;
            border-radius: 8px;
            border-left: 4px solid #ddd;
        }
        .step-success {
            background: #d4edda;
            border-left-color: #28a745;
        }
        .step-error {
            background: #f8d7da;
            border-left-color: #dc3545;
        }
        .step-info {
            background: #d1ecf1;
            border-left-color: #17a2b8;
        }
        .step-warning {
            background: #fff3cd;
            border-left-color: #ffc107;
        }
        .icon {
            font-size: 1.2rem;
            margin-right: 0.5rem;
        }
        .step-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
        }
        .step-detail {
            font-size: 0.9rem;
            color: #555;
            margin: 0;
        }
        pre {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 6px;
            overflow-x: auto;
            font-size: 0.85rem;
            border: 1px solid #e0e0e0;
        }
        .btn {
            display: inline-block;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0.875rem 1.75rem;
            border-radius: 6px;
            text-decoration: none;
            font-weight: 600;
            font-size: 1rem;
            border: none;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
        }
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(102, 126, 234, 0.5);
        }
        .final-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1.5rem;
            border-radius: 8px;
            text-align: center;
            margin-top: 1.5rem;
        }
        .final-box h3 {
            margin: 0 0 1rem 0;
            font-size: 1.4rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 1rem 0;
            font-size: 0.9rem;
        }
        table th, table td {
            padding: 0.6rem;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        table th {
            background: #f8f9fa;
            font-weight: 600;
            color: #555;
        }
        .progress {
            text-align: center;
            color: #666;
            font-style: italic;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <h1>🚀 Instalace systému akcí a úkolů</h1>
            <p class="subtitle">Vytváření databázové struktury pro Control Center</p>
            <p class="subtitle">Datum: <?= date('Y-m-d H:i:s') ?> | Uživatel: <?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin') ?></p>
        </div>

<?php

$installationSteps = [];
$hasErrors = false;

try {
    $pdo = getDbConnection();

    // KROK 1: Ověření připojení
    $installationSteps[] = [
        'type' => 'success',
        'title' => 'Krok 1: Databázové připojení',
        'detail' => 'Připojení k databázi úspěšné'
    ];

    // KROK 2: Kontrola existence tabulek
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_pending_actions'");
    $pendingActionsExists = $stmt->rowCount() > 0;

    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_action_history'");
    $actionHistoryExists = $stmt->rowCount() > 0;

    if ($pendingActionsExists && $actionHistoryExists) {
        $installationSteps[] = [
            'type' => 'warning',
            'title' => 'Krok 2: Kontrola existujících tabulek',
            'detail' => 'Obě tabulky již existují. Budou přeskočeny (IF NOT EXISTS).'
        ];
    } else {
        $installationSteps[] = [
            'type' => 'info',
            'title' => 'Krok 2: Kontrola existujících tabulek',
            'detail' => sprintf(
                'wgs_pending_actions: %s | wgs_action_history: %s',
                $pendingActionsExists ? 'EXISTUJE' : 'NEEXISTUJE',
                $actionHistoryExists ? 'EXISTUJE' : 'NEEXISTUJE'
            )
        ];
    }

    // KROK 3: Spuštění SQL migrace
    $executedCount = 0;
    $errors = [];

    // SQL příkazy přímo v kódu (spolehlivější než parsování souboru)
    $sqlStatements = [
        // Tabulka wgs_pending_actions
        "CREATE TABLE IF NOT EXISTS wgs_pending_actions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            action_type VARCHAR(50) NOT NULL COMMENT 'Typ akce: install_smtp, migration, update, etc.',
            action_title VARCHAR(255) NOT NULL COMMENT 'Název úlohy zobrazený v UI',
            action_description TEXT DEFAULT NULL COMMENT 'Detailní popis úlohy',
            action_url VARCHAR(255) DEFAULT NULL COMMENT 'URL scriptu k vykonání (pro migrations)',
            priority ENUM('critical', 'high', 'medium', 'low') DEFAULT 'medium' COMMENT 'Priorita úlohy',
            status ENUM('pending', 'in_progress', 'completed', 'failed', 'dismissed') DEFAULT 'pending' COMMENT 'Aktuální stav úlohy',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            completed_at TIMESTAMP NULL DEFAULT NULL,
            completed_by INT DEFAULT NULL COMMENT 'ID uživatele, který úlohu dokončil',
            dismissed_at TIMESTAMP NULL DEFAULT NULL,
            dismissed_by INT DEFAULT NULL COMMENT 'ID uživatele, který úlohu zrušil',

            INDEX idx_status (status),
            INDEX idx_priority (priority),
            INDEX idx_created_at (created_at),
            INDEX idx_action_type (action_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Nevyřešené úlohy a plánované akce pro administrátory'",

        // Tabulka wgs_action_history
        "CREATE TABLE IF NOT EXISTS wgs_action_history (
            id INT PRIMARY KEY AUTO_INCREMENT,
            action_id INT DEFAULT NULL COMMENT 'Reference na původní akci (pokud existovala)',
            action_type VARCHAR(50) NOT NULL,
            action_title VARCHAR(255) NOT NULL,
            status ENUM('completed', 'failed') NOT NULL,
            executed_by INT DEFAULT NULL COMMENT 'ID uživatele, který akci spustil',
            execution_time INT DEFAULT NULL COMMENT 'Čas vykonávání v milisekundách',
            error_message TEXT DEFAULT NULL COMMENT 'Chybová zpráva (pokud failed)',
            executed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

            INDEX idx_action_id (action_id),
            INDEX idx_status (status),
            INDEX idx_executed_at (executed_at),
            INDEX idx_action_type (action_type)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        COMMENT='Historie všech vykonaných akcí (audit trail)'",

        // Přidat SMTP instalační úlohu
        "INSERT INTO wgs_pending_actions (
            action_type,
            action_title,
            action_description,
            priority,
            status
        )
        VALUES (
            'install_smtp',
            'Instalovat SMTP konfiguraci',
            'Přidá smtp_password a smtp_encryption klíče do system_config a vytvoří tabulku wgs_notification_history pro sledování odeslaných emailů a SMS.',
            'high',
            'pending'
        )"
    ];

    foreach ($sqlStatements as $statement) {
        try {
            $pdo->exec($statement);
            $executedCount++;
        } catch (PDOException $e) {
            // Ignorovat "already exists" a "Duplicate entry" chyby
            if (strpos($e->getMessage(), 'already exists') === false &&
                strpos($e->getMessage(), 'Duplicate entry') === false) {
                $errors[] = $e->getMessage();
            } else {
                // Počítat i přeskočené příkazy jako úspěšné
                $executedCount++;
            }
        }
    }

    if (count($errors) > 0) {
        $installationSteps[] = [
            'type' => 'error',
            'title' => 'Krok 3: Spuštění SQL migrace',
            'detail' => 'Některé příkazy selhaly: ' . implode('; ', $errors)
        ];
        $hasErrors = true;
    } else {
        $installationSteps[] = [
            'type' => 'success',
            'title' => 'Krok 3: Spuštění SQL migrace',
            'detail' => sprintf('Vykonáno %d SQL příkazů úspěšně', $executedCount)
        ];
    }

    // KROK 4: Ověření struktury tabulek
    $stmt = $pdo->query("DESCRIBE wgs_pending_actions");
    $pendingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->query("DESCRIBE wgs_action_history");
    $historyColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $installationSteps[] = [
        'type' => 'success',
        'title' => 'Krok 4: Ověření struktury tabulek',
        'detail' => sprintf(
            'wgs_pending_actions: %d sloupců | wgs_action_history: %d sloupců',
            count($pendingColumns),
            count($historyColumns)
        )
    ];

    // KROK 5: Kontrola iniciální SMTP úlohy
    $stmt = $pdo->query("SELECT COUNT(*) FROM wgs_pending_actions WHERE action_type = 'install_smtp' AND status = 'pending'");
    $smtpTaskCount = $stmt->fetchColumn();

    if ($smtpTaskCount > 0) {
        $installationSteps[] = [
            'type' => 'success',
            'title' => 'Krok 5: Iniciální SMTP úloha',
            'detail' => sprintf('SMTP instalační úloha přidána (celkem %d pending)', $smtpTaskCount)
        ];
    } else {
        $installationSteps[] = [
            'type' => 'warning',
            'title' => 'Krok 5: Iniciální SMTP úloha',
            'detail' => 'SMTP úloha nebyla přidána (možná již existuje jako completed)'
        ];
    }

    // KROK 6: Výpis všech pending akcí
    $stmt = $pdo->query("SELECT * FROM wgs_pending_actions WHERE status = 'pending' ORDER BY priority, created_at");
    $pendingActions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $installationSteps[] = [
        'type' => 'error',
        'title' => 'KRITICKÁ CHYBA',
        'detail' => $e->getMessage()
    ];
    $hasErrors = true;
}

// Zobrazení výsledků
echo '<div class="card">';
echo '<h2>📊 Výsledky instalace</h2>';

foreach ($installationSteps as $step) {
    echo '<div class="step step-' . $step['type'] . '">';
    echo '<div class="step-title">';

    $icons = [
        'success' => '',
        'error' => '',
        'warning' => '⚠️',
        'info' => 'ℹ️'
    ];

    echo '<span class="icon">' . $icons[$step['type']] . '</span>';
    echo $step['title'];
    echo '</div>';
    echo '<p class="step-detail">' . htmlspecialchars($step['detail']) . '</p>';
    echo '</div>';
}

echo '</div>';

// Zobrazení pending akcí
if (!$hasErrors && isset($pendingActions) && count($pendingActions) > 0) {
    echo '<div class="card">';
    echo '<h2>📋 Aktuální nevyřešené úlohy</h2>';
    echo '<table>';
    echo '<thead><tr>';
    echo '<th>ID</th><th>Typ</th><th>Název</th><th>Priorita</th><th>Vytvořeno</th>';
    echo '</tr></thead>';
    echo '<tbody>';

    foreach ($pendingActions as $action) {
        echo '<tr>';
        echo '<td>#' . $action['id'] . '</td>';
        echo '<td>' . htmlspecialchars($action['action_type']) . '</td>';
        echo '<td><strong>' . htmlspecialchars($action['action_title']) . '</strong></td>';
        echo '<td>' . strtoupper($action['priority']) . '</td>';
        echo '<td>' . date('Y-m-d H:i', strtotime($action['created_at'])) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '</div>';
}

// Finální zpráva
if (!$hasErrors) {
    echo '<div class="card">';
    echo '<div class="final-box">';
    echo '<h3>Instalace dokončena úspěšně!</h3>';
    echo '<p style="margin-bottom: 1.5rem;">Systém akcí a úkolů je nyní plně funkční.</p>';
    echo '<a href="admin.php" class="btn">→ Přejít do Control Center</a>';
    echo '</div>';
    echo '</div>';
} else {
    echo '<div class="card">';
    echo '<div class="final-box" style="background: #dc3545;">';
    echo '<h3>Instalace se nezdařila</h3>';
    echo '<p>Kontaktujte vývojáře nebo zkontrolujte logy.</p>';
    echo '</div>';
    echo '</div>';
}

?>

    </div>
</body>
</html>
