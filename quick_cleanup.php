<?php
/**
 * QUICK FIX - Direct SQL cleanup for failed history records
 * Smaže všechny selhavší záznamy přímo bez JavaScriptu
 */

require_once __DIR__ . '/init.php';

// SECURITY: Check admin access
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('Access Denied - Admin only');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Quick Cleanup</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            max-width: 800px;
            margin: 0 auto;
        }
        .box {
            background: #2d2d2d;
            padding: 20px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 4px solid #007acc;
        }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #ce9178; }
        button {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            margin: 5px;
        }
        button:hover { background: #c82333; }
        button.safe { background: #28a745; }
        button.safe:hover { background: #218838; }
    </style>
</head>
<body>
    <h1>🧹 Quick Database Cleanup</h1>

    <?php
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        echo '<div class="box">';

        try {
            // SECURITY: CSRF token validation
            if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
                http_response_code(403);
                echo '<p class="error">❌ CSRF token validation failed</p>';
                echo '<p class="warning">⚠️ Security check failed. Please refresh the page and try again.</p>';
                echo '</div>';
                echo '<div class="box">';
                echo '<a href="/quick_cleanup.php"><button class="safe">← Refresh Page</button></a>';
                echo '</div>';
                echo '</body></html>';
                exit;
            }

            $pdo = getDbConnection();

            if ($_POST['action'] === 'delete_failed') {
                // Nejdřív zobrazit, co se smaže
                $stmt = $pdo->query("
                    SELECT id, action_title, error_message, executed_at
                    FROM wgs_action_history
                    WHERE status = 'failed'
                    ORDER BY executed_at DESC
                ");
                $failedRecords = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (empty($failedRecords)) {
                    echo '<p class="warning">⚠️ Žádné selhavší záznamy k vymazání</p>';
                } else {
                    echo '<p class="success">✅ Mazání ' . count($failedRecords) . ' selhavších záznamů:</p>';
                    echo '<ul>';
                    foreach ($failedRecords as $record) {
                        echo '<li>' . htmlspecialchars($record['action_title']) . ' (' . $record['executed_at'] . ')</li>';
                    }
                    echo '</ul>';

                    // Smazat
                    $deleteStmt = $pdo->query("DELETE FROM wgs_action_history WHERE status = 'failed'");
                    $deletedCount = $deleteStmt->rowCount();

                    echo '<p class="success">✅ Úspěšně smazáno: ' . $deletedCount . ' záznamů</p>';
                    echo '<p class="success">✅ Developer Console bude nyní čistý!</p>';
                }

            } elseif ($_POST['action'] === 'delete_old_smtp') {
                // Smazat pouze starý SMTP záznam
                $stmt = $pdo->prepare("
                    DELETE FROM wgs_action_history
                    WHERE status = 'failed'
                    AND error_message LIKE '%wgs_system_config%'
                    AND error_message LIKE '%doesn''t exist%'
                ");
                $stmt->execute();
                $deletedCount = $stmt->rowCount();

                if ($deletedCount > 0) {
                    echo '<p class="success">✅ Úspěšně smazán starý SMTP záznam (' . $deletedCount . ' záznamů)</p>';
                    echo '<p class="success">✅ Developer Console bude nyní čistý!</p>';
                } else {
                    echo '<p class="warning">⚠️ Starý SMTP záznam již neexistuje</p>';
                }
            }

        } catch (Exception $e) {
            echo '<p class="error">❌ Chyba: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }

        echo '</div>';
        echo '<div class="box">';
        echo '<a href="/verify_and_cleanup.php"><button class="safe">← Zpět na Verification</button></a>';
        echo '<a href="/admin.php?tab=control_center"><button class="safe">← Zpět do Admin Panel</button></a>';
        echo '</div>';

    } else {
        // Zobrazit informace a formulář
        try {
            $pdo = getDbConnection();

            // SECURITY: Generate CSRF token for forms
            $csrfToken = generateCSRFToken();

            // Spočítat selhavší záznamy
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_action_history WHERE status = 'failed'");
            $failedCount = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Najít starý SMTP záznam
            $smtpStmt = $pdo->query("
                SELECT id, action_title, error_message, executed_at
                FROM wgs_action_history
                WHERE status = 'failed'
                AND error_message LIKE '%wgs_system_config%'
                AND error_message LIKE '%doesn''t exist%'
                LIMIT 1
            ");
            $smtpRecord = $smtpStmt->fetch(PDO::FETCH_ASSOC);

            echo '<div class="box">';
            echo '<h2>📊 Aktuální stav</h2>';
            echo '<p>Celkem selhavších záznamů: <strong>' . $failedCount . '</strong></p>';

            if ($smtpRecord) {
                echo '<p class="warning">⚠️ Nalezen starý SMTP záznam:</p>';
                echo '<ul>';
                echo '<li><strong>Akce:</strong> ' . htmlspecialchars($smtpRecord['action_title']) . '</li>';
                echo '<li><strong>Datum:</strong> ' . $smtpRecord['executed_at'] . '</li>';
                echo '<li><strong>Chyba:</strong> ' . htmlspecialchars(substr($smtpRecord['error_message'], 0, 100)) . '...</li>';
                echo '</ul>';
                echo '<p class="success">✅ Tabulka wgs_system_config NYNí existuje - tento záznam lze bezpečně smazat</p>';
            } else {
                echo '<p class="success">✅ Žádný starý SMTP záznam nenalezen</p>';
            }

            echo '</div>';

            if ($failedCount > 0) {
                echo '<div class="box">';
                echo '<h2>🗑️ Cleanup akce</h2>';

                if ($smtpRecord) {
                    echo '<form method="POST" style="display: inline;">';
                    echo '<input type="hidden" name="action" value="delete_old_smtp">';
                    echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
                    echo '<button type="submit">🗑️ Smazat pouze starý SMTP záznam</button>';
                    echo '</form>';
                }

                echo '<form method="POST" style="display: inline;" onsubmit="return confirm(\'Opravdu chcete smazat VŠECHNY selhavší záznamy?\');">';
                echo '<input type="hidden" name="action" value="delete_failed">';
                echo '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrfToken) . '">';
                echo '<button type="submit">🗑️ Smazat všechny selhavší záznamy (' . $failedCount . ')</button>';
                echo '</form>';

                echo '</div>';
            }

            echo '<div class="box">';
            echo '<a href="/verify_and_cleanup.php"><button class="safe">📋 Zobrazit detailní přehled</button></a>';
            echo '<a href="/admin.php?tab=control_center"><button class="safe">← Zpět do Admin Panel</button></a>';
            echo '</div>';

        } catch (Exception $e) {
            echo '<div class="box">';
            echo '<p class="error">❌ Chyba: ' . htmlspecialchars($e->getMessage()) . '</p>';
            echo '</div>';
        }
    }
    ?>
</body>
</html>
