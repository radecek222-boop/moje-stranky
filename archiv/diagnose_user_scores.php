<?php
/**
 * DIAGNOSTIKA USER SCORES API
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre style='background:#1a1a1a;color:#0f0;padding:20px;font-family:monospace;'>";
echo "=== DIAGNOSTIKA USER SCORES API ===\n\n";

require_once __DIR__ . '/init.php';

try {
    $pdo = getDbConnection();
    echo "✓ DB připojení OK\n\n";

    // Kontrola admin session
    $_SESSION['is_admin'] = true;
    echo "✓ Admin session nastavena\n";

    // Kontrola CSRF
    require_once __DIR__ . '/includes/csrf_helper.php';
    $csrfToken = generateCSRFToken();
    echo "✓ CSRF token: {$csrfToken}\n\n";

    // Kontrola tabulek
    echo "KONTROLA TABULEK:\n";
    echo str_repeat('-', 60) . "\n";

    $tables = [
        'wgs_analytics_user_scores',
        'wgs_analytics_sessions',
        'wgs_analytics_events',
        'wgs_pageviews'
    ];

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '{$table}'");
        $exists = $stmt->rowCount() > 0;

        if ($exists) {
            // Zjistit počet záznamů
            $countStmt = $pdo->query("SELECT COUNT(*) as cnt FROM {$table}");
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
            echo "✓ {$table}: EXISTUJE ({$count} záznamů)\n";
        } else {
            echo "❌ {$table}: NEEXISTUJE\n";
        }
    }

    echo "\n" . str_repeat('-', 60) . "\n\n";

    // Kontrola UserScoreCalculator třídy
    echo "KONTROLA TŘÍD:\n";
    echo str_repeat('-', 60) . "\n";

    if (file_exists(__DIR__ . '/includes/UserScoreCalculator.php')) {
        echo "✓ UserScoreCalculator.php existuje\n";
        require_once __DIR__ . '/includes/UserScoreCalculator.php';

        if (class_exists('UserScoreCalculator')) {
            echo "✓ UserScoreCalculator třída načtena\n";

            $calculator = new UserScoreCalculator($pdo);
            echo "✓ UserScoreCalculator instance vytvořena\n";
        } else {
            echo "❌ UserScoreCalculator třída neexistuje\n";
        }
    } else {
        echo "❌ UserScoreCalculator.php soubor neexistuje\n";
    }

    echo "\n" . str_repeat('-', 60) . "\n\n";

    // Test API volání
    echo "TEST API VOLÁNÍ (action=list):\n";
    echo str_repeat('-', 60) . "\n";

    $_GET['action'] = 'list';
    $_GET['csrf_token'] = $csrfToken;
    $_GET['limit'] = 10;

    echo "Nastavené parametry:\n";
    echo "  - action: " . $_GET['action'] . "\n";
    echo "  - csrf_token: " . $_GET['csrf_token'] . "\n";
    echo "  - limit: " . $_GET['limit'] . "\n\n";

    echo "Zachytávám output z API...\n";
    echo str_repeat('-', 60) . "\n";

    ob_start();
    try {
        include __DIR__ . '/api/analytics_user_scores.php';
        $output = ob_get_clean();

        echo "\n✅ API PROBĚHLO BEZ CHYBY\n\n";
        echo "OUTPUT:\n";
        echo $output . "\n";

        // Parse JSON
        $json = json_decode($output, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo "\n✅ Validní JSON\n";
            echo "Status: " . ($json['status'] ?? 'N/A') . "\n";

            if (isset($json['message'])) {
                echo "Message: " . $json['message'] . "\n";
            }

            if (isset($json['scores'])) {
                echo "Scores count: " . count($json['scores']) . "\n";
            }
        } else {
            echo "\n❌ JSON Parse Error: " . json_last_error_msg() . "\n";
        }

    } catch (Throwable $e) {
        ob_end_clean();
        echo "\n❌ CHYBA ZACHYCENA:\n";
        echo "Type: " . get_class($e) . "\n";
        echo "Message: " . $e->getMessage() . "\n";
        echo "File: " . $e->getFile() . "\n";
        echo "Line: " . $e->getLine() . "\n";
        echo "\nStack Trace:\n";
        echo $e->getTraceAsString() . "\n";
    }

} catch (Exception $e) {
    echo "\n❌ FATAL ERROR:\n";
    echo $e->getMessage() . "\n";
    echo $e->getTraceAsString() . "\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "</pre>";
?>
