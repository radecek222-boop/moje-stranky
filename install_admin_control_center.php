<?php
/**
 * Admin Control Center - Installation Script
 * Automatické spuštění migrace migration_admin_control_center.sql
 */

session_start();

// Safari compatibility headers - must be BEFORE any output
header("Cross-Origin-Opener-Policy: unsafe-none");
header("Cross-Origin-Embedder-Policy: unsafe-none");
header("X-Frame-Options: SAMEORIGIN");

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: /prihlaseni.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Installation - Admin Control Center</title>
    <link rel="stylesheet" href="/assets/css/control-center.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
        }

        .install-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 700px;
            width: 100%;
            overflow: hidden;
        }

        .install-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 30px;
            text-align: center;
            color: white;
        }

        .install-header h1 {
            margin: 0 0 10px 0;
            font-size: 28px;
            font-weight: 600;
        }

        .install-header p {
            margin: 0;
            opacity: 0.9;
            font-size: 14px;
        }

        .install-body {
            padding: 30px;
        }

        .install-info {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 4px;
        }

        .install-info h3 {
            margin: 0 0 10px 0;
            font-size: 16px;
            color: #333;
        }

        .install-info ul {
            margin: 0;
            padding-left: 20px;
            color: #666;
            font-size: 14px;
            line-height: 1.8;
        }

        .install-status {
            display: none;
            margin: 20px 0;
        }

        .install-status.active {
            display: block;
        }

        .status-item {
            display: flex;
            align-items: center;
            padding: 12px;
            margin-bottom: 8px;
            background: #f8f9fa;
            border-radius: 8px;
            font-size: 14px;
        }

        .status-item.success {
            background: #d4edda;
            color: #155724;
        }

        .status-item.error {
            background: #f8d7da;
            color: #721c24;
        }

        .status-item.info {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-icon {
            margin-right: 10px;
            font-size: 18px;
        }

        .install-log {
            background: #1e1e1e;
            color: #d4d4d4;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 13px;
            max-height: 400px;
            overflow-y: auto;
            margin-bottom: 20px;
            display: none;
        }

        .install-log.active {
            display: block;
        }

        .log-line {
            margin-bottom: 4px;
            line-height: 1.5;
        }

        .log-time {
            color: #858585;
        }

        .log-success {
            color: #4ec9b0;
        }

        .log-error {
            color: #f48771;
        }

        .log-info {
            color: #569cd6;
        }

        .install-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn-success {
            background: #28a745;
            color: white;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 20px;
            display: none;
        }

        .progress-bar.active {
            display: block;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            width: 0%;
            transition: width 0.5s;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <h1>🚀 Admin Control Center</h1>
            <p>Instalace databázových tabulek</p>
        </div>

        <div class="install-body">
            <div class="install-info">
                <h3>📋 Co bude nainstalováno:</h3>
                <ul>
                    <li><strong>wgs_theme_settings</strong> - Barvy, fonty, logo</li>
                    <li><strong>wgs_content_texts</strong> - Editovatelné texty stránek</li>
                    <li><strong>wgs_system_config</strong> - SMTP, API klíče, bezpečnost</li>
                    <li><strong>wgs_pending_actions</strong> - Systém akcí a úkolů</li>
                    <li><strong>wgs_action_history</strong> - Historie provedených akcí</li>
                    <li><strong>wgs_github_webhooks</strong> - GitHub integrace</li>
                </ul>
            </div>

            <div class="progress-bar" id="progressBar">
                <div class="progress-fill" id="progressFill"></div>
            </div>

            <div class="install-status" id="installStatus"></div>

            <div class="install-log" id="installLog"></div>

            <div class="install-actions">
                <button class="btn btn-primary" id="installBtn" onclick="startInstallation()">
                    ▶️ Spustit instalaci
                </button>
                <button class="btn btn-secondary" onclick="window.close()" style="display: none;" id="closeBtn">
                    Zavřít
                </button>
                <button class="btn btn-success" onclick="window.location.href='/admin.php?tab=control_center'" style="display: none;" id="goToAdminBtn">
                    ✅ Přejít do Control Center
                </button>
            </div>
        </div>
    </div>

    <script>
        let logLines = [];

        function addLog(message, type = 'info') {
            const timestamp = new Date().toLocaleTimeString('cs-CZ');
            const colorClass = type === 'success' ? 'log-success' : (type === 'error' ? 'log-error' : 'log-info');

            logLines.push(`<div class="log-line"><span class="log-time">[${timestamp}]</span> <span class="${colorClass}">${message}</span></div>`);

            const logElement = document.getElementById('installLog');
            logElement.innerHTML = logLines.join('');
            logElement.scrollTop = logElement.scrollHeight;
        }

        function addStatus(message, type = 'info') {
            const statusElement = document.getElementById('installStatus');
            const icon = type === 'success' ? '✅' : (type === 'error' ? '❌' : 'ℹ️');

            const statusItem = document.createElement('div');
            statusItem.className = `status-item ${type}`;
            statusItem.innerHTML = `<span class="status-icon">${icon}</span><span>${message}</span>`;

            statusElement.appendChild(statusItem);
        }

        function setProgress(percent) {
            document.getElementById('progressFill').style.width = percent + '%';
        }

        async function startInstallation() {
            const installBtn = document.getElementById('installBtn');
            const progressBar = document.getElementById('progressBar');
            const installLog = document.getElementById('installLog');
            const installStatus = document.getElementById('installStatus');

            // Disable tlačítko
            installBtn.disabled = true;
            installBtn.textContent = '⏳ Probíhá instalace...';

            // Zobrazit prvky
            progressBar.classList.add('active');
            installLog.classList.add('active');
            installStatus.classList.add('active');

            try {
                // KROK 1: Kontrola stavu
                addLog('🔍 Kontroluji aktuální stav databáze...', 'info');
                setProgress(10);

                const statusResponse = await fetch('/api/migration_executor.php?action=check_migration_status');
                const statusData = await statusResponse.json();

                if (statusData.status === 'success') {
                    addLog('✓ Stav databáze načten', 'success');
                    addStatus(`Nalezeno ${statusData.data.tables_status.filter(t => t.exists).length}/6 tabulek`, 'info');

                    if (!statusData.data.migration_needed) {
                        addLog('ℹ️ Všechny tabulky již existují!', 'info');
                        addStatus('Migrace není potřeba - všechny tabulky jsou vytvořeny', 'success');
                        setProgress(100);

                        installBtn.style.display = 'none';
                        document.getElementById('goToAdminBtn').style.display = 'inline-block';
                        return;
                    }
                }

                setProgress(25);

                // KROK 2: Spuštění migrace
                addLog('🚀 Spouštím migraci migration_admin_control_center.sql...', 'info');
                setProgress(40);

                const formData = new FormData();
                formData.append('action', 'run_migration');
                formData.append('migration_file', 'migration_admin_control_center.sql');

                const migrationResponse = await fetch('/api/migration_executor.php', {
                    method: 'POST',
                    body: formData
                });

                setProgress(70);

                const migrationData = await migrationResponse.json();

                if (migrationData.status === 'success') {
                    addLog('✅ Migrace úspěšně dokončena!', 'success');
                    addLog(`📊 Vykonáno ${migrationData.data.statements_executed} SQL příkazů za ${migrationData.data.execution_time_ms}ms`, 'success');

                    setProgress(90);

                    // Zobrazit výsledky
                    addStatus(`Vytvořeno ${migrationData.data.tables_created} tabulek`, 'success');

                    migrationData.data.details.forEach(detail => {
                        if (detail.table) {
                            addLog(`  ✓ ${detail.table}: ${detail.rows} záznamů`, 'success');
                        }
                    });

                    setProgress(100);

                    addLog('🎉 Instalace kompletně dokončena!', 'success');
                    addStatus('Admin Control Center je připraven k použití', 'success');

                    // Zobrazit tlačítko
                    installBtn.style.display = 'none';
                    document.getElementById('goToAdminBtn').style.display = 'inline-block';
                    document.getElementById('closeBtn').style.display = 'inline-block';

                } else {
                    throw new Error(migrationData.message);
                }

            } catch (error) {
                addLog('❌ CHYBA: ' + error.message, 'error');
                addStatus('Instalace selhala: ' + error.message, 'error');

                installBtn.disabled = false;
                installBtn.textContent = '🔄 Zkusit znovu';

                setProgress(0);
            }
        }

        // Auto-check při načtení stránky
        window.addEventListener('load', async () => {
            try {
                const response = await fetch('/api/migration_executor.php?action=check_migration_status');
                const data = await response.json();

                if (data.status === 'success' && !data.data.migration_needed) {
                    document.querySelector('.install-info').innerHTML = `
                        <h3>✅ Instalace již proběhla</h3>
                        <p style="margin: 10px 0 0 0; color: #666;">Všechny tabulky Admin Control Center jsou již vytvořeny.</p>
                    `;

                    document.getElementById('installBtn').textContent = '✅ Již nainstalováno';
                    document.getElementById('installBtn').disabled = true;
                    document.getElementById('goToAdminBtn').style.display = 'inline-block';
                }
            } catch (error) {
                console.error('Auto-check failed:', error);
            }
        });
    </script>
</body>
</html>
