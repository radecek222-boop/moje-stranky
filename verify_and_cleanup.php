<?php
/**
 * Cleanup Script - Ověření tabulky a vymazání starého záznamu
 */

require_once __DIR__ . '/init.php';

// SECURITY: Check admin access
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Access Denied</title>
    <style>
        body { font-family: Arial; text-align: center; padding: 50px; }
        h1 { color: #dc3545; }
    </style>
</head>
<body>
    <h2>🔒 Access Denied</h2>
    <p>This page is only accessible to administrators.</p>
    <p><a href="/prihlaseni.php">Login as Admin</a></p>
</body>
</html>');
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Database Cleanup - Verification</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        .section {
            background: #2d2d2d;
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            border-left: 4px solid #007acc;
        }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .warning { color: #ce9178; }
        .info { color: #569cd6; }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
        }
        th, td {
            padding: 8px;
            border: 1px solid #444;
            text-align: left;
        }
        th {
            background: #333;
            color: #569cd6;
        }
        button {
            padding: 10px 20px;
            margin: 10px 5px;
            background: #007acc;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        button:hover { background: #005a9e; }
        button.danger { background: #dc3545; }
        button.danger:hover { background: #c82333; }
        button:disabled { background: #666; cursor: not-allowed; }
    </style>
</head>
<body>
    <h1>🔍 Database Cleanup & Verification</h1>

    <?php
    try {
        $pdo = getDbConnection();

        // ========================================
        // KROK 1: Ověření tabulky wgs_system_config
        // ========================================
        echo '<div class="section">';
        echo '<h2>1. Ověření tabulky wgs_system_config</h2>';

        // Zkontrolovat existenci
        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_system_config'");
        if ($stmt->rowCount() > 0) {
            echo '<p class="success">✅ Tabulka wgs_system_config EXISTUJE</p>';

            // Spočítat záznamy
            $countStmt = $pdo->query("SELECT COUNT(*) as count FROM wgs_system_config");
            $count = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
            echo '<p class="success">✅ Počet záznamů: ' . $count . '</p>';

            // Zobrazit všechny záznamy
            echo '<h3>Obsah tabulky:</h3>';
            $dataStmt = $pdo->query("
                SELECT config_key, config_value, config_group, is_sensitive, updated_at
                FROM wgs_system_config
                ORDER BY config_group, config_key
            ");
            $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);

            echo '<table>';
            echo '<tr><th>Key</th><th>Value</th><th>Group</th><th>Sensitive</th><th>Updated</th></tr>';
            foreach ($rows as $row) {
                $value = $row['is_sensitive'] ? '••••••••' : htmlspecialchars($row['config_value']);
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['config_key']) . '</td>';
                echo '<td>' . $value . '</td>';
                echo '<td>' . htmlspecialchars($row['config_group']) . '</td>';
                echo '<td>' . ($row['is_sensitive'] ? '🔒' : '🔓') . '</td>';
                echo '<td>' . htmlspecialchars($row['updated_at']) . '</td>';
                echo '</tr>';
            }
            echo '</table>';

            echo '<p class="info">ℹ️ Tabulka je v pořádku a obsahuje SMTP konfiguraci!</p>';

        } else {
            echo '<p class="error">❌ Tabulka wgs_system_config NEEXISTUJE!</p>';
            echo '<p class="warning">⚠️ To je problém - tabulka by měla existovat!</p>';
        }

        echo '</div>';

        // ========================================
        // KROK 2: Kontrola selhavších záznamů
        // ========================================
        echo '<div class="section">';
        echo '<h2>2. Selhavší záznamy v historii</h2>';

        $failedStmt = $pdo->query("
            SELECT id, action_title, error_message, executed_at, status
            FROM wgs_action_history
            WHERE status = 'failed'
            ORDER BY executed_at DESC
            LIMIT 10
        ");
        $failedRecords = $failedStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($failedRecords)) {
            echo '<p class="warning">⚠️ Nalezeno ' . count($failedRecords) . ' selhavších záznamů:</p>';

            echo '<table>';
            echo '<tr><th>ID</th><th>Akce</th><th>Chyba</th><th>Datum</th><th>Akce</th></tr>';
            foreach ($failedRecords as $record) {
                echo '<tr>';
                echo '<td>' . $record['id'] . '</td>';
                echo '<td>' . htmlspecialchars($record['action_title']) . '</td>';
                echo '<td style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">' .
                     htmlspecialchars(substr($record['error_message'], 0, 100)) . '</td>';
                echo '<td>' . $record['executed_at'] . '</td>';
                echo '<td><button class="danger" onclick="deleteRecord(' . $record['id'] . ')">Smazat</button></td>';
                echo '</tr>';
            }
            echo '</table>';

            // Filtrovat specificky ten SMTP záznam
            $smtpRecords = array_filter($failedRecords, function($r) {
                return strpos($r['error_message'], 'wgs_system_config') !== false &&
                       strpos($r['error_message'], "doesn't exist") !== false;
            });

            if (!empty($smtpRecords)) {
                echo '<p class="warning">⚠️ Nalezen STARÝ záznam o chybějící tabulce wgs_system_config</p>';
                echo '<p class="info">ℹ️ Tento záznam je z doby, kdy tabulka skutečně neexistovala</p>';
                echo '<p class="success">✅ NYNí tabulka EXISTUJE - tento záznam lze bezpečně smazat</p>';

                $smtpRecord = reset($smtpRecords);
                echo '<button class="danger" onclick="deleteRecord(' . $smtpRecord['id'] . ')">
                        🗑️ Smazat starý SMTP záznam (ID: ' . $smtpRecord['id'] . ')
                      </button>';
            }

        } else {
            echo '<p class="success">✅ Žádné selhavší záznamy v historii</p>';
        }

        echo '</div>';

        // ========================================
        // KROK 3: Pending Actions
        // ========================================
        echo '<div class="section">';
        echo '<h2>3. Nevyřešené úkoly (Pending Actions)</h2>';

        $pendingStmt = $pdo->query("
            SELECT id, action_type, action_title, priority, status, created_at
            FROM wgs_pending_actions
            WHERE status IN ('pending', 'failed')
            ORDER BY
                FIELD(priority, 'critical', 'high', 'medium', 'low'),
                created_at DESC
            LIMIT 10
        ");
        $pendingRecords = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($pendingRecords)) {
            echo '<p class="warning">⚠️ Nalezeno ' . count($pendingRecords) . ' nevyřešených úkolů:</p>';

            echo '<table>';
            echo '<tr><th>ID</th><th>Typ</th><th>Název</th><th>Priorita</th><th>Status</th><th>Vytvořeno</th></tr>';
            foreach ($pendingRecords as $record) {
                echo '<tr>';
                echo '<td>' . $record['id'] . '</td>';
                echo '<td>' . htmlspecialchars($record['action_type']) . '</td>';
                echo '<td>' . htmlspecialchars($record['action_title']) . '</td>';
                echo '<td>' . $record['priority'] . '</td>';
                echo '<td>' . $record['status'] . '</td>';
                echo '<td>' . $record['created_at'] . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p class="success">✅ Žádné nevyřešené úkoly</p>';
        }

        echo '</div>';

    } catch (Exception $e) {
        echo '<div class="section">';
        echo '<p class="error">❌ Chyba: ' . htmlspecialchars($e->getMessage()) . '</p>';
        echo '</div>';
    }
    ?>

    <div class="section">
        <h2>4. Akce</h2>
        <button onclick="window.location.reload()">🔄 Obnovit</button>
        <button onclick="deleteAllFailedRecords()" class="danger">🗑️ Smazat všechny selhavší záznamy</button>
        <a href="/admin.php"><button>← Zpět do Admin Panel</button></a>
    </div>

    <script>
                /**
         * DeleteRecord
         */
function deleteRecord(id) {
            if (!confirm('Opravdu chcete smazat tento záznam z historie?')) {
                return;
            }

            fetch('cleanup_history_record.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ record_id: id })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('✅ Záznam byl úspěšně smazán');
                    window.location.reload();
                } else {
                    alert('❌ Chyba: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Chyba: ' + error.message);
            });
        }

                /**
         * DeleteAllFailedRecords
         */
function deleteAllFailedRecords() {
            if (!confirm('Opravdu chcete smazat VŠECHNY selhavší záznamy z historie?')) {
                return;
            }

            fetch('cleanup_history_record.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({ delete_all_failed: true })
            })
            .then(response => response.json())
            .then(data => {
                if (data.status === 'success') {
                    alert('✅ Všechny selhavší záznamy byly smazány (' + data.deleted_count + ' záznamů)');
                    window.location.reload();
                } else {
                    alert('❌ Chyba: ' + data.message);
                }
            })
            .catch(error => {
                alert('❌ Chyba: ' + error.message);
            });
        }
    </script>
</body>
</html>
