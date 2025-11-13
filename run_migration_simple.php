<?php
/**
 * Simple Migration Trigger - must be accessed via web browser/curl
 * Access via: http://your-domain.com/run_migration_simple.php
 */

session_start();

// SECURITY: Check admin or use secret key
$secretKey = $_GET['key'] ?? '';
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// Allow if admin OR secret key matches
if (!$isAdmin && $secretKey !== 'wgs-migration-2025') {
    die('Access denied. Login as admin or provide correct key.');
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Database Migration</title>
    <style>
        body {
            font-family: monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            line-height: 1.6;
        }
        .log { padding: 10px; margin: 5px 0; }
        .success { color: #4ec9b0; }
        .error { color: #f48771; }
        .info { color: #569cd6; }
        button {
            padding: 10px 20px;
            font-size: 16px;
            background: #007acc;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        button:disabled { background: #666; }
    </style>
</head>
<body>
    <h1>Admin Control Center - Database Migration</h1>
    <button id="checkBtn" onclick="checkStatus()">Check Status</button>
    <button id="runBtn" onclick="runMigration()" style="display:none">Run Migration</button>
    <div id="output"></div>

    <script>
        const output = document.getElementById('output');

        function log(message, type = 'info') {
            const div = document.createElement('div');
            div.className = `log ${type}`;
            div.textContent = message;
            output.appendChild(div);
        }

        async function checkStatus() {
            log('Checking migration status...', 'info');

            try {
                const response = await fetch('/api/migration_executor.php?action=check_migration_status');
                const data = await response.json();

                if (data.status === 'success') {
                    log('✓ Status check complete', 'success');

                    data.data.tables_status.forEach(table => {
                        if (table.exists) {
                            log(`  ✓ ${table.table}: ${table.rows} rows`, 'success');
                        } else {
                            log(`  ✗ ${table.table}: MISSING`, 'error');
                        }
                    });

                    if (data.data.migration_needed) {
                        log('\nMigration IS NEEDED', 'info');
                        document.getElementById('runBtn').style.display = 'inline-block';
                    } else {
                        log('\nAll tables exist - migration NOT needed', 'success');
                    }
                } else {
                    log('✗ Error: ' + data.message, 'error');
                }
            } catch (error) {
                log('✗ Error: ' + error.message, 'error');
            }
        }

        async function runMigration() {
            const btn = document.getElementById('runBtn');
            btn.disabled = true;
            btn.textContent = 'Running...';

            log('\nStarting migration...', 'info');

            try {
                const formData = new FormData();
                formData.append('action', 'run_migration');
                formData.append('migration_file', 'migration_admin_control_center.sql');

                const response = await fetch('/api/migration_executor.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'success') {
                    log('✓ Migration completed successfully!', 'success');
                    log(`  Statements executed: ${data.data.statements_executed}`, 'success');
                    log(`  Execution time: ${data.data.execution_time_ms}ms`, 'success');
                    log(`  Tables created: ${data.data.tables_created}`, 'success');

                    data.data.details.forEach(detail => {
                        if (detail.table) {
                            log(`    ${detail.table}: ${detail.rows} rows`, 'success');
                        }
                    });

                    log('\n✓ MIGRATION COMPLETE', 'success');
                    btn.textContent = 'Migration Complete';
                } else {
                    log('✗ Migration failed: ' + data.message, 'error');
                    btn.disabled = false;
                    btn.textContent = 'Retry Migration';
                }
            } catch (error) {
                log('✗ Error: ' + error.message, 'error');
                btn.disabled = false;
                btn.textContent = 'Retry Migration';
            }
        }

        // Auto-check on load
        window.addEventListener('load', checkStatus);
    </script>
</body>
</html>
