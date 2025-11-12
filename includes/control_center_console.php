<?php
/**
 * Control Center - Konzole
 * Komplexní diagnostika celé aplikace (HTML, PHP, JS, CSS, SQL, API)
 */

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();

// Detect embed mode for iframe contexts
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';
?>

<link rel="stylesheet" href="/assets/css/control-center.css">
<style>
/* Console-specific styles */
.console-container {
    background: #1E1E1E;
    border-radius: 8px;
    overflow: hidden;
    box-shadow: 0 4px 16px rgba(0,0,0,0.3);
}

.console-header {
    background: #2D2D2D;
    padding: 1rem 1.5rem;
    border-bottom: 1px solid #3E3E3E;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.console-title {
    color: #FFFFFF;
    font-size: 1rem;
    font-weight: 600;
    font-family: 'Courier New', monospace;
}

.console-actions {
    display: flex;
    gap: 0.5rem;
}

.console-btn {
    background: #0E639C;
    color: white;
    border: none;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    font-size: 0.875rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.console-btn:hover {
    background: #1177BB;
}

.console-btn.danger {
    background: #DC3545;
}

.console-btn.danger:hover {
    background: #C82333;
}

.console-btn.success {
    background: #28A745;
}

.console-btn.success:hover {
    background: #218838;
}

.console-btn:disabled {
    background: #6C757D;
    cursor: not-allowed;
    opacity: 0.6;
}

.console-output {
    padding: 1.5rem;
    height: 600px;
    overflow-y: auto;
    font-family: 'Courier New', monospace;
    font-size: 0.875rem;
    line-height: 1.6;
    color: #D4D4D4;
}

.console-line {
    margin-bottom: 0.5rem;
    padding: 0.25rem 0;
}

.console-line.info {
    color: #4EC9B0;
}

.console-line.success {
    color: #4EC9B0;
}

.console-line.warning {
    color: #DCDCAA;
}

.console-line.error {
    color: #F48771;
}

.console-line.header {
    color: #569CD6;
    font-weight: 600;
    margin-top: 1rem;
    padding-top: 1rem;
    border-top: 1px solid #3E3E3E;
}

.console-line.header:first-child {
    margin-top: 0;
    padding-top: 0;
    border-top: none;
}

.console-timestamp {
    color: #858585;
    margin-right: 0.5rem;
}

.console-icon {
    margin-right: 0.5rem;
}

.console-empty {
    text-align: center;
    padding: 4rem 2rem;
    color: #858585;
}

.console-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.console-stat-card {
    background: #2D2D2D;
    padding: 1.5rem;
    border-radius: 8px;
    border: 1px solid #3E3E3E;
}

.console-stat-label {
    color: #858585;
    font-size: 0.875rem;
    margin-bottom: 0.5rem;
}

.console-stat-value {
    font-size: 2rem;
    font-weight: 700;
    font-family: 'Courier New', monospace;
}

.console-stat-value.success {
    color: #4EC9B0;
}

.console-stat-value.error {
    color: #F48771;
}

.console-stat-value.warning {
    color: #DCDCAA;
}

.console-loading {
    text-align: center;
    padding: 2rem;
    color: #569CD6;
}

.console-loading::after {
    content: '...';
    animation: dots 1.5s steps(4, end) infinite;
}

@keyframes dots {
    0%, 20% { content: '.'; }
    40% { content: '..'; }
    60%, 100% { content: '...'; }
}

/* Blue status dot */
.control-card-status-dot.blue {
    background-color: #0E639C;
}
</style>

<div class="control-detail active">
    <!-- Header -->
    <?php if (!$embedMode): ?>
    <div class="control-detail-header">
        <button class="control-detail-back" onclick="window.location.href='admin.php?tab=control_center'">
            <span>‹</span>
            <span>Zpět</span>
        </button>
        <h2 class="control-detail-title">💻 Konzole</h2>
    </div>
    <?php endif; ?>

    <div class="control-detail-content" style="<?= $embedMode ? 'padding-top: 1rem;' : '' ?>">

        <!-- Alert -->
        <div class="cc-alert info">
            <div class="cc-alert-icon">⚡</div>
            <div class="cc-alert-content">
                <div class="cc-alert-title">Developer Console</div>
                <div class="cc-alert-message">
                    Komplexní diagnostika celé aplikace. Kontroluje PHP syntax, JavaScript errors,
                    SQL tabulky, API endpointy, CSS validity a další.
                </div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="console-stats" id="console-stats" style="display: none;">
            <div class="console-stat-card">
                <div class="console-stat-label">PHP Soubory</div>
                <div class="console-stat-value success" id="stat-php">—</div>
            </div>
            <div class="console-stat-card">
                <div class="console-stat-label">JavaScript Soubory</div>
                <div class="console-stat-value success" id="stat-js">—</div>
            </div>
            <div class="console-stat-card">
                <div class="console-stat-label">SQL Tabulky</div>
                <div class="console-stat-value success" id="stat-sql">—</div>
            </div>
            <div class="console-stat-card">
                <div class="console-stat-label">API Endpointy</div>
                <div class="console-stat-value success" id="stat-api">—</div>
            </div>
            <div class="console-stat-card">
                <div class="console-stat-label">Celkem Chyb</div>
                <div class="console-stat-value error" id="stat-errors">—</div>
            </div>
            <div class="console-stat-card">
                <div class="console-stat-label">Upozornění</div>
                <div class="console-stat-value warning" id="stat-warnings">—</div>
            </div>
        </div>

        <!-- Console -->
        <div class="console-container">
            <div class="console-header">
                <div class="console-title">$ wgs-service diagnostics</div>
                <div class="console-actions">
                    <button class="console-btn success" id="btn-run-diagnostics" onclick="runDiagnostics()">
                        ▶ Spustit diagnostiku
                    </button>
                    <button class="console-btn" id="btn-clear" onclick="clearConsole()" disabled>
                        🗑 Vymazat
                    </button>
                    <button class="console-btn" id="btn-export" onclick="exportLog()" disabled>
                        💾 Export
                    </button>
                </div>
            </div>
            <div class="console-output" id="console-output">
                <div class="console-empty">
                    <div style="font-size: 3rem; margin-bottom: 1rem;">💻</div>
                    <div style="font-size: 1.25rem; margin-bottom: 0.5rem;">Konzole připravena</div>
                    <div>Klikněte na "Spustit diagnostiku" pro kontrolu aplikace</div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// Debug mode - set to false in production
const DEBUG_MODE = false;

let consoleOutput = [];
let diagnosticsRunning = false;

// ============================================
// CONSOLE OUTPUT FUNCTIONS
// ============================================

function log(message, type = 'info') {
    const timestamp = new Date().toLocaleTimeString('cs-CZ');
    const line = {
        timestamp,
        message,
        type
    };
    consoleOutput.push(line);
    renderConsole();
}

function logHeader(message) {
    log(message, 'header');
}

function logSuccess(message) {
    log('✓ ' + message, 'success');
}

function logWarning(message) {
    log('⚠ ' + message, 'warning');
}

function logError(message) {
    log('✗ ' + message, 'error');
}

function renderConsole() {
    const output = document.getElementById('console-output');
    output.innerHTML = consoleOutput.map(line => {
        return `<div class="console-line ${line.type}">
            <span class="console-timestamp">[${line.timestamp}]</span>
            <span>${escapeHtml(line.message)}</span>
        </div>`;
    }).join('');

    // Auto-scroll to bottom
    output.scrollTop = output.scrollHeight;

    // Enable buttons
    document.getElementById('btn-clear').disabled = false;
    document.getElementById('btn-export').disabled = false;
}

function clearConsole() {
    if (!confirm('Vymazat výstup konzole?')) return;
    consoleOutput = [];
    document.getElementById('console-output').innerHTML = `
        <div class="console-empty">
            <div style="font-size: 3rem; margin-bottom: 1rem;">💻</div>
            <div style="font-size: 1.25rem; margin-bottom: 0.5rem;">Konzole vymazána</div>
            <div>Klikněte na "Spustit diagnostiku" pro novou kontrolu</div>
        </div>
    `;
    document.getElementById('btn-clear').disabled = true;
    document.getElementById('btn-export').disabled = true;
    document.getElementById('console-stats').style.display = 'none';
}

function exportLog() {
    const text = consoleOutput.map(line =>
        `[${line.timestamp}] ${line.type.toUpperCase().padEnd(10)} ${line.message}`
    ).join('\n');

    const blob = new Blob([text], { type: 'text/plain' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `wgs-diagnostics-${new Date().toISOString().slice(0,10)}.log`;
    a.click();
    URL.revokeObjectURL(url);

    logSuccess('Log exportován');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

// ============================================
// MAIN DIAGNOSTICS FUNCTION
// ============================================

async function runDiagnostics() {
    if (diagnosticsRunning) {
        alert('Diagnostika již běží!');
        return;
    }

    diagnosticsRunning = true;
    const btn = document.getElementById('btn-run-diagnostics');
    btn.disabled = true;
    btn.textContent = '⏳ Kontroluji...';

    // Clear previous output
    consoleOutput = [];
    renderConsole();

    // Show stats
    document.getElementById('console-stats').style.display = 'grid';

    // Reset stats
    ['php', 'js', 'sql', 'api', 'errors', 'warnings'].forEach(stat => {
        document.getElementById('stat-' + stat).textContent = '—';
    });

    logHeader('═══════════════════════════════════════════════════');
    logHeader('WGS SERVICE - KOMPLETNÍ DIAGNOSTIKA SYSTÉMU');
    logHeader('═══════════════════════════════════════════════════');
    log('');

    try {
        // 1. PHP Files Check
        await checkPhpFiles();

        // 2. JavaScript Files Check
        await checkJavaScriptFiles();

        // 3. SQL Database Check
        await checkDatabase();

        // 4. API Endpoints Check
        await checkApiEndpoints();

        // 5. Error Logs Check
        await checkErrorLogs();

        // 6. File Permissions
        await checkFilePermissions();

        // 7. Security Check
        await checkSecurity();

        log('');
        logHeader('═══════════════════════════════════════════════════');
        logSuccess('DIAGNOSTIKA DOKONČENA');
        logHeader('═══════════════════════════════════════════════════');

    } catch (error) {
        logError('Kritická chyba diagnostiky: ' + error.message);
        console.error(error);
    } finally {
        diagnosticsRunning = false;
        btn.disabled = false;
        btn.textContent = '▶ Spustit diagnostiku';
    }
}

// ============================================
// INDIVIDUAL CHECK FUNCTIONS
// ============================================

async function checkPhpFiles() {
    logHeader('1. PHP SOUBORY');
    log('Kontroluji PHP syntax a strukturu...');

    try {
        const response = await fetch('/api/control_center_api.php?action=check_php_files', {
            method: 'GET',
            credentials: 'same-origin'
        });

        // Vždy zkusit přečíst response body (i při chybě)
        let data;
        const contentType = response.headers.get('content-type');

        if (contentType && contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const text = await response.text();
            logError(`Neplatná odpověď od API (HTTP ${response.status}):`);
            logError(text.substring(0, 300));
            log('');
            return;
        }

        if (!response.ok) {
            logError(`API vrátilo chybu (HTTP ${response.status}):`);
            logError(data.message || response.statusText || 'Neznámá chyba');
            if (data.debug) {
                logError('Debug info: ' + JSON.stringify(data.debug).substring(0, 200));
            }
            log('');
            return;
        }

        if (data.status === 'success') {
            const { total, errors, warnings } = data.data;
            document.getElementById('stat-php').textContent = total;

            logSuccess(`Nalezeno ${total} PHP souborů`);

            if (errors.length > 0) {
                logError(`Nalezeno ${errors.length} chyb v PHP souborech:`);
                log('═'.repeat(79));
                errors.forEach(err => {
                    if (err.line) {
                        logError(`📄 ${err.file}:${err.line}`);
                        logError(`   ${err.type.toUpperCase()}: ${err.error.substring(0, 150)}`);
                    } else {
                        logError(`📄 ${err.file}`);
                        logError(`   ${err.error.substring(0, 150)}`);
                    }
                    log('─'.repeat(79));
                });
            } else {
                logSuccess('✓ Žádné PHP syntax errors');
            }

            if (warnings.length > 0) {
                logWarning(`${warnings.length} upozornění`);
            }
        } else {
            logError('Nepodařilo se zkontrolovat PHP soubory: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        logError('Chyba při kontrole PHP:');
        logError('  ' + error.message);
        logError('  Zkontrolujte, zda API soubor existuje a je dostupný');
        if (DEBUG_MODE) console.error(error);
    }

    log('');
}

async function checkJavaScriptFiles() {
    logHeader('2. JAVASCRIPT SOUBORY');
    log('Kontroluji JavaScript errors z logů...');

    try {
        const response = await fetch('/api/control_center_api.php?action=check_js_errors', {
            method: 'GET',
            credentials: 'same-origin'
        });

        // Vždy zkusit přečíst response body (i při chybě)
        let data;
        const contentType = response.headers.get('content-type');

        if (contentType && contentType.includes('application/json')) {
            data = await response.json();
        } else {
            const text = await response.text();
            logError(`Neplatná odpověď od API (HTTP ${response.status}):`);
            logError(text.substring(0, 300));
            log('');
            return;
        }

        if (!response.ok) {
            logError(`API vrátilo chybu (HTTP ${response.status}):`);
            logError(data.message || response.statusText || 'Neznámá chyba');
            log('');
            return;
        }

        if (data.status === 'success') {
            const { total, recent_errors, error_count } = data.data;
            document.getElementById('stat-js').textContent = total;

            logSuccess(`${total} JavaScript souborů detekováno`);

            if (recent_errors && recent_errors.length > 0) {
                logWarning(`Nalezeno ${error_count} nedávných JS errors:`);
                log('═'.repeat(79));

                // Zobrazit top 10 errors
                recent_errors.slice(0, 10).forEach((err, idx) => {
                    logWarning(`#${idx + 1}: ${err.message}`);
                    if (err.file !== 'unknown') {
                        logWarning(`   📄 ${err.file}:${err.line || '?'}${err.column ? ':' + err.column : ''}`);
                    }
                    if (err.timestamp) {
                        logWarning(`   🕐 ${err.timestamp}`);
                    }
                    log('─'.repeat(79));
                });

                if (recent_errors.length > 10) {
                    logWarning(`... a dalších ${recent_errors.length - 10} chyb`);
                }
            } else {
                logSuccess('✓ Žádné nedávné JavaScript errors');
            }
        } else {
            logError('Nepodařilo se zkontrolovat JavaScript: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        logError('Chyba při kontrole JS:');
        logError('  ' + error.message);
        if (DEBUG_MODE) console.error(error);
    }

    log('');
}

async function checkDatabase() {
    logHeader('3. SQL DATABÁZE');
    log('Kontroluji tabulky, indexy a integritu...');

    try {
        const response = await fetch('/api/control_center_api.php?action=check_database', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const { tables, corrupted, missing_indexes, size } = data.data;
            document.getElementById('stat-sql').textContent = tables.length;

            logSuccess(`${tables.length} tabulek nalezeno`);
            logSuccess(`Celková velikost: ${size}`);

            if (corrupted.length > 0) {
                logError(`${corrupted.length} poškozených tabulek:`);
                corrupted.forEach(table => {
                    logError(`  ${table}`);
                });
            } else {
                logSuccess('Všechny tabulky v pořádku (CHECK TABLE)');
            }

            if (missing_indexes && missing_indexes.length > 0) {
                logWarning(`${missing_indexes.length} doporučených indexů chybí`);
            }
        } else {
            logError('Nepodařilo se zkontrolovat databázi');
        }
    } catch (error) {
        logError('Chyba při kontrole DB: ' + error.message);
    }

    log('');
}

async function checkApiEndpoints() {
    logHeader('4. API ENDPOINTY');
    log('Testuji dostupnost API...');

    const endpoints = [
        '/api/admin_api.php',
        '/api/control_center_api.php',
        '/api/notification_api.php',
        '/api/protokol_api.php',
        '/api/statistiky_api.php'
    ];

    let workingCount = 0;
    let failedCount = 0;
    const failedEndpoints = [];

    for (const endpoint of endpoints) {
        try {
            const response = await fetch(endpoint + '?action=ping', {
                method: 'GET',
                credentials: 'same-origin'
            });

            if (response.ok || response.status === 400) {
                // 400 je OK - znamená že API běží, jen ping action neexistuje
                logSuccess(`✓ ${endpoint} - OK (HTTP ${response.status})`);
                workingCount++;
            } else {
                const responseText = await response.text().catch(() => 'N/A');
                const errorInfo = {
                    endpoint,
                    status: response.status,
                    statusText: response.statusText,
                    responsePreview: responseText.substring(0, 200)
                };
                failedEndpoints.push(errorInfo);

                logError(`✗ ${endpoint} - HTTP ${response.status} ${response.statusText}`);
                if (responseText && responseText.length > 0) {
                    logError(`   Response: ${responseText.substring(0, 150)}`);
                }
                failedCount++;
            }
        } catch (error) {
            const errorInfo = {
                endpoint,
                error: error.message
            };
            failedEndpoints.push(errorInfo);

            logError(`✗ ${endpoint} - Network Error`);
            logError(`   Důvod: ${error.message}`);
            logError(`   (Možná CORS, timeout, nebo server neběží)`);
            failedCount++;
        }
    }

    document.getElementById('stat-api').textContent = workingCount;

    log('');
    if (failedCount === 0) {
        logSuccess('✓ Všechny API endpointy fungují správně');
    } else {
        logWarning(`⚠ ${failedCount} API endpointů nefunguje - viz detaily výše`);
    }

    log('');
}

async function checkErrorLogs() {
    logHeader('5. ERROR LOGY');
    log('Kontroluji nedávné chyby...');

    try {
        const response = await fetch('/api/control_center_api.php?action=get_recent_errors', {
            method: 'GET',
            credentials: 'same-origin'
        });

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        const data = await response.json();

        if (data.status === 'success') {
            const { php_errors, js_errors, security_logs } = data.data;

            let totalErrors = (php_errors?.length || 0) + (js_errors?.length || 0);
            document.getElementById('stat-errors').textContent = totalErrors;

            // PHP ERRORS
            if (php_errors && php_errors.length > 0) {
                logWarning(`📋 ${php_errors.length} PHP errors (poslední 24h):`);
                log('═'.repeat(79));

                // Zobrazit top 5 PHP errors
                php_errors.slice(0, 5).forEach((err, idx) => {
                    if (err.parsed !== false && err.file && err.line) {
                        // Plná chyba s file:line
                        logWarning(`#${idx + 1}: ${err.type || 'Error'}`);
                        logWarning(`   📄 ${err.file}:${err.line}`);
                        logWarning(`   💬 ${err.message}`);
                        if (err.timestamp) {
                            logWarning(`   🕐 ${err.timestamp}`);
                        }
                    } else if (err.parsed === true && err.message) {
                        // Jen message bez file:line
                        logWarning(`#${idx + 1}: ${err.message}`);
                        if (err.timestamp) {
                            logWarning(`   🕐 ${err.timestamp}`);
                        }
                    } else if (err.raw) {
                        // Neparsované - zobrazit raw
                        logWarning(`#${idx + 1}: ${err.raw.substring(0, 150)}`);
                    }
                    log('─'.repeat(79));
                });

                if (php_errors.length > 5) {
                    logWarning(`... a dalších ${php_errors.length - 5} PHP chyb`);
                }
            } else {
                logSuccess('✓ Žádné PHP errors (24h)');
            }

            log('');

            // JS ERRORS
            if (js_errors && js_errors.length > 0) {
                logWarning(`📋 ${js_errors.length} JS errors (poslední 24h):`);
                log('═'.repeat(79));

                // Zobrazit top 5 JS errors
                js_errors.slice(0, 5).forEach((err, idx) => {
                    logWarning(`#${idx + 1}: ${err.message}`);
                    if (err.file && err.file !== 'unknown') {
                        logWarning(`   📄 ${err.file}:${err.line || '?'}`);
                    }
                    if (err.timestamp) {
                        logWarning(`   🕐 ${err.timestamp}`);
                    }
                    log('─'.repeat(79));
                });

                if (js_errors.length > 5) {
                    logWarning(`... a dalších ${js_errors.length - 5} JS chyb`);
                }
            } else {
                logSuccess('✓ Žádné JS errors (24h)');
            }

            log('');

            // SECURITY LOGS
            if (security_logs && security_logs.length > 0) {
                logWarning(`🔒 ${security_logs.length} security events (24h):`);
                log('═'.repeat(79));

                // Zobrazit top 5 security events
                security_logs.slice(0, 5).forEach((event, idx) => {
                    if (event.parsed !== false) {
                        const icon = event.severity === 'critical' ? '🔴' :
                                    event.severity === 'warning' ? '🟡' : '🟢';
                        logWarning(`${icon} #${idx + 1}: [${event.type}] ${event.message}`);
                        if (event.timestamp) {
                            logWarning(`   🕐 ${event.timestamp}`);
                        }
                    } else {
                        logWarning(`#${idx + 1}: ${event.raw.substring(0, 150)}`);
                    }
                    log('─'.repeat(79));
                });

                if (security_logs.length > 5) {
                    logWarning(`... a dalších ${security_logs.length - 5} security events`);
                }
            } else {
                logSuccess('✓ Žádné security events (24h)');
            }
        }
    } catch (error) {
        logError('Nepodařilo se načíst logy: ' + error.message);
        if (DEBUG_MODE) console.error(error);
    }

    log('');
}

async function checkFilePermissions() {
    logHeader('6. OPRÁVNĚNÍ SOUBORŮ');
    log('Kontroluji write permissions...');

    try {
        const response = await fetch('/api/control_center_api.php?action=check_permissions', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const { writable, not_writable } = data.data;

            writable.forEach(dir => {
                logSuccess(`${dir} - writable`);
            });

            if (not_writable.length > 0) {
                logError(`${not_writable.length} složek není writable:`);
                not_writable.forEach(dir => {
                    logError(`  ${dir}`);
                });
            } else {
                logSuccess('Všechna oprávnění v pořádku');
            }
        }
    } catch (error) {
        logWarning('Nepodařilo se zkontrolovat oprávnění: ' + error.message);
    }

    log('');
}

async function checkSecurity() {
    logHeader('7. BEZPEČNOST');
    log('Kontroluji bezpečnostní nastavení...');

    try {
        const response = await fetch('/api/control_center_api.php?action=check_security', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const checks = data.data;

            if (checks.https) {
                logSuccess('HTTPS aktivní');
            } else {
                logError('HTTPS NENÍ aktivní');
            }

            if (checks.csrf_protection) {
                logSuccess('CSRF ochrana aktivní');
            }

            if (checks.rate_limiting) {
                logSuccess('Rate limiting aktivní');
            }

            if (checks.strong_passwords) {
                logSuccess('Silná hesla vynucena');
            } else {
                logWarning('Doporučujeme silnější hesla');
            }

            if (checks.admin_keys_secure) {
                logSuccess('Admin klíče zabezpečené');
            } else {
                logError('Admin klíče NEJSOU zabezpečené!');
            }
        }
    } catch (error) {
        logWarning('Nepodařilo se zkontrolovat bezpečnost: ' + error.message);
    }

    log('');
}

if (DEBUG_MODE) console.log('✅ Console loaded');
</script>
