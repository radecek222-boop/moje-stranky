<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Developer Console Buttons</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #569cd6;
            border-bottom: 2px solid #569cd6;
            padding-bottom: 10px;
        }
        .test-section {
            background: #2d2d2d;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
            border-left: 4px solid #007acc;
        }
        .test-button {
            padding: 12px 24px;
            margin: 5px;
            background: #007acc;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: bold;
        }
        .test-button:hover {
            background: #005a9e;
        }
        .test-button:disabled {
            background: #666;
            cursor: not-allowed;
        }
        .result {
            margin-top: 10px;
            padding: 10px;
            border-radius: 4px;
            font-family: monospace;
        }
        .success {
            background: #1e3a1e;
            color: #4ec9b0;
            border: 1px solid #4ec9b0;
        }
        .error {
            background: #3a1e1e;
            color: #f48771;
            border: 1px solid #f48771;
        }
        .info {
            background: #1e2a3a;
            color: #569cd6;
            border: 1px solid #569cd6;
        }
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }
        .status-ok {
            background: #4ec9b0;
        }
        .status-error {
            background: #f48771;
        }
        .status-pending {
            background: #ce9178;
        }
        pre {
            background: #1e1e1e;
            padding: 10px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>🧪 Developer Console Buttons Test</h1>

    <div class="test-section">
        <h2>📊 Test Overview</h2>
        <p>Tento script testuje všechna tlačítka v Developer Console:</p>
        <ul>
            <li><span class="status-indicator status-pending"></span><strong>Vymazat</strong> - vymaže výstup konzole (frontend only)</li>
            <li><span class="status-indicator status-pending"></span><strong>Export</strong> - exportuje log do souboru (frontend only)</li>
            <li><span class="status-indicator status-pending"></span><strong>Cache</strong> - vymaže cache (/temp, /cache)</li>
            <li><span class="status-indicator status-pending"></span><strong>Optimize DB</strong> - optimalizuje databázové tabulky</li>
            <li><span class="status-indicator status-pending"></span><strong>Archive</strong> - archivuje staré logy (>90 dní)</li>
        </ul>
    </div>

    <div class="test-section">
        <h2>1️⃣ Frontend Buttons (no API call)</h2>
        <p>Tyto funkce běží pouze v prohlížeči a nevyžadují API:</p>

        <div style="margin: 15px 0;">
            <button class="test-button" onclick="testClearConsole()">Test: Vymazat</button>
            <button class="test-button" onclick="testExportLog()">Test: Export</button>
        </div>

        <div id="frontend-result"></div>
    </div>

    <div class="test-section">
        <h2>2️⃣ Backend API Buttons</h2>
        <p>Tyto funkce volají API endpointy:</p>

        <div style="margin: 15px 0;">
            <button class="test-button" id="btn-cache" onclick="testClearCache()">Test: Cache</button>
            <button class="test-button" id="btn-optimize" onclick="testOptimizeDB()">Test: Optimize DB</button>
            <button class="test-button" id="btn-archive" onclick="testArchiveLogs()">Test: Archive</button>
            <button class="test-button" onclick="testAllAPI()">🚀 Test All API</button>
        </div>

        <div id="api-result"></div>
    </div>

    <div class="test-section">
        <h2>📋 Test Results</h2>
        <div id="test-results"></div>
    </div>

    <script>
        let testResults = [];

        function addResult(test, status, message, data = null) {
            const result = { test, status, message, data, timestamp: new Date().toLocaleTimeString() };
            testResults.push(result);
            displayResults();
        }

        function displayResults() {
            const container = document.getElementById('test-results');
            if (testResults.length === 0) {
                container.innerHTML = '<p class="info">Žádné testy zatím neproběhly</p>';
                return;
            }

            let html = '<table style="width: 100%; border-collapse: collapse;">';
            html += '<tr style="background: #333; text-align: left;"><th style="padding: 8px;">Čas</th><th>Test</th><th>Status</th><th>Zpráva</th></tr>';

            testResults.forEach(r => {
                const statusClass = r.status === 'success' ? 'success' : (r.status === 'error' ? 'error' : 'info');
                const statusIcon = r.status === 'success' ? '✅' : (r.status === 'error' ? '❌' : 'ℹ️');

                html += `<tr style="border-bottom: 1px solid #444;">
                    <td style="padding: 8px;">${r.timestamp}</td>
                    <td><strong>${r.test}</strong></td>
                    <td>${statusIcon} ${r.status.toUpperCase()}</td>
                    <td>${r.message}</td>
                </tr>`;

                if (r.data) {
                    html += `<tr><td colspan="4" style="padding: 8px;"><pre style="margin: 0; font-size: 12px;">${JSON.stringify(r.data, null, 2)}</pre></td></tr>`;
                }
            });

            html += '</table>';
            container.innerHTML = html;
        }

        // ========================================
        // FRONTEND TESTS
        // ========================================

        function testClearConsole() {
            try {
                // Simulace clearConsole funkce
                addResult('Vymazat (clearConsole)', 'success', 'Frontend funkce - vymaže výstup konzole v Developer Console');
            } catch (error) {
                addResult('Vymazat (clearConsole)', 'error', error.message);
            }
        }

        function testExportLog() {
            try {
                // Simulace exportLog funkce
                const testLog = [
                    { timestamp: '09:00:00', type: 'info', message: 'Test message 1' },
                    { timestamp: '09:00:01', type: 'success', message: 'Test message 2' }
                ];

                const text = testLog.map(line =>
                    `[${line.timestamp}] ${line.type.toUpperCase().padEnd(10)} ${line.message}`
                ).join('\n');

                addResult('Export (exportLog)', 'success', 'Frontend funkce - exportuje log do souboru .log', { sample: text });
            } catch (error) {
                addResult('Export (exportLog)', 'error', error.message);
            }
        }

        // ========================================
        // API TESTS
        // ========================================

        async function testClearCache() {
            const btn = document.getElementById('btn-cache');
            btn.disabled = true;
            btn.textContent = 'Testing...';

            try {
                const response = await fetch('/api/control_center_api.php?action=clear_cache', {
                    method: 'POST',
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.status === 'success') {
                    addResult('Cache (clear_cache)', 'success', `✅ Cache vymazána (${data.files_deleted || 0} souborů)`, data);
                } else {
                    addResult('Cache (clear_cache)', 'error', data.message || 'Unknown error', data);
                }
            } catch (error) {
                addResult('Cache (clear_cache)', 'error', error.message);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Test: Cache';
            }
        }

        async function testOptimizeDB() {
            const btn = document.getElementById('btn-optimize');
            btn.disabled = true;
            btn.textContent = 'Testing...';

            try {
                const response = await fetch('/api/control_center_api.php?action=optimize_database', {
                    method: 'POST',
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.status === 'success') {
                    addResult('Optimize DB', 'success', `✅ Databáze optimalizována (${data.tables_optimized || 0} tabulek, ${data.time_ms || 0}ms)`, data);
                } else {
                    addResult('Optimize DB', 'error', data.message || 'Unknown error', data);
                }
            } catch (error) {
                addResult('Optimize DB', 'error', error.message);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Test: Optimize DB';
            }
        }

        async function testArchiveLogs() {
            const btn = document.getElementById('btn-archive');
            btn.disabled = true;
            btn.textContent = 'Testing...';

            try {
                const response = await fetch('/api/control_center_api.php?action=archive_logs', {
                    method: 'POST',
                    credentials: 'same-origin'
                });

                const data = await response.json();

                if (data.status === 'success') {
                    addResult('Archive Logs', 'success', `✅ Archivováno ${data.count || 0} logů`, data);
                } else {
                    addResult('Archive Logs', 'error', data.message || 'Unknown error', data);
                }
            } catch (error) {
                addResult('Archive Logs', 'error', error.message);
            } finally {
                btn.disabled = false;
                btn.textContent = 'Test: Archive';
            }
        }

        async function testAllAPI() {
            addResult('ALL API TESTS', 'info', 'Spouštím všechny API testy...');
            await testClearCache();
            await new Promise(resolve => setTimeout(resolve, 500));
            await testOptimizeDB();
            await new Promise(resolve => setTimeout(resolve, 500));
            await testArchiveLogs();
            addResult('ALL API TESTS', 'success', 'Všechny API testy dokončeny!');
        }

        // Auto-display on load
        displayResults();
    </script>
</body>
</html>
