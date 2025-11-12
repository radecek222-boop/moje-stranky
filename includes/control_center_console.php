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

        <!-- System Info -->
        <div class="console-stats" id="console-system-info" style="display: none; margin-bottom: 1rem;">
            <div class="console-stat-card">
                <div class="console-stat-label">PHP Verze</div>
                <div class="console-stat-value success" id="stat-php-version">—</div>
            </div>
            <div class="console-stat-card">
                <div class="console-stat-label">Disk Space</div>
                <div class="console-stat-value success" id="stat-disk">—</div>
            </div>
            <div class="console-stat-card">
                <div class="console-stat-label">Memory Limit</div>
                <div class="console-stat-value success" id="stat-memory">—</div>
            </div>
            <div class="console-stat-card">
                <div class="console-stat-label">Max Upload</div>
                <div class="console-stat-value success" id="stat-upload">—</div>
            </div>
        </div>

        <!-- Statistics -->
        <div class="console-stats" id="console-stats" style="display: none;">
            <div class="console-stat-card">
                <div class="console-stat-label">HTML/PHP Stránky</div>
                <div class="console-stat-value success" id="stat-html">—</div>
            </div>
            <div class="console-stat-card">
                <div class="console-stat-label">PHP Backend</div>
                <div class="console-stat-value success" id="stat-php">—</div>
            </div>
            <div class="console-stat-card">
                <div class="console-stat-label">JavaScript</div>
                <div class="console-stat-value success" id="stat-js">—</div>
            </div>
            <div class="console-stat-card">
                <div class="console-stat-label">CSS/Assets</div>
                <div class="console-stat-value success" id="stat-css">—</div>
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
                    <button class="console-btn" onclick="clearCacheMaintenance()" title="Vymazat cache">
                        🔄 Cache
                    </button>
                    <button class="console-btn" onclick="optimizeDatabaseMaintenance()" title="Optimalizovat databázi">
                        ⚡ Optimize DB
                    </button>
                    <button class="console-btn" onclick="archiveLogsMaintenance()" title="Archivovat staré logy">
                        📜 Archive
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
let totalErrors = 0;
let totalWarnings = 0;

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
    document.getElementById('console-system-info').style.display = 'grid';

    // Reset stats
    ['html', 'php', 'js', 'css', 'sql', 'api', 'errors', 'warnings', 'php-version', 'disk', 'memory', 'upload'].forEach(stat => {
        document.getElementById('stat-' + stat).textContent = '—';
    });

    // Reset error counters
    totalErrors = 0;
    totalWarnings = 0;

    logHeader('═══════════════════════════════════════════════════');
    logHeader('WGS SERVICE - KOMPLETNÍ DIAGNOSTIKA SYSTÉMU');
    logHeader('═══════════════════════════════════════════════════');
    log('');

    try {
        // 0. System Info
        await checkSystemInfo();

        // 1. HTML/PHP Pages Check
        await checkHtmlPages();

        // 2. PHP Backend Files Check
        await checkPhpFiles();

        // 3. JavaScript Files Check
        await checkJavaScriptFiles();

        // 4. CSS/Assets Check
        await checkAssets();

        // 5. SQL Database Check
        await checkDatabase();

        // 6. API Endpoints Check
        await checkApiEndpoints();

        // 7. Error Logs Check
        await checkErrorLogs();

        // 8. File Permissions
        await checkFilePermissions();

        // 9. Security Check
        await checkSecurity();

        // 10. Dependencies Check
        await checkDependencies();

        // 11. Configuration Check
        await checkConfiguration();

        // 12. Git Status
        await checkGitStatus();

        log('');
        logHeader('═══════════════════════════════════════════════════');
        logSuccess('DIAGNOSTIKA DOKONČENA');
        logHeader('═══════════════════════════════════════════════════');

        // Update final statistics
        document.getElementById('stat-errors').textContent = totalErrors;
        document.getElementById('stat-warnings').textContent = totalWarnings;

    } catch (error) {
        logError('Kritická chyba diagnostiky: ' + error.message);
        console.error(error);
        totalErrors++;
        document.getElementById('stat-errors').textContent = totalErrors;
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
        const contentType = response.headers.get('content-type');
        let data;
        let isJson = false;

        // Pokusit se přečíst jako JSON
        try {
            const text = await response.text();

            if (text && (contentType?.includes('application/json') || text.trim().startsWith('{'))) {
                data = JSON.parse(text);
                isJson = true;
            } else {
                // Není JSON - zobrazit raw text
                if (!response.ok) {
                    logError(`API vrátilo chybu (HTTP ${response.status} ${response.statusText}):`);
                    logError(`   📍 URL: /api/control_center_api.php?action=check_php_files`);

                    // Extrahovat chybovou zprávu z HTML pokud je to HTML
                    if (text.includes('<b>Fatal error</b>') || text.includes('<b>Parse error</b>')) {
                        // PHP Fatal error v HTML formátu
                        const match = text.match(/<b>(.*?)<\/b>:\s*(.*?)\s+in\s+<b>(.*?)<\/b>\s+on line\s+<b>(\d+)<\/b>/);
                        if (match) {
                            logError(`   💬 ${match[1]}: ${match[2]}`);
                            logError(`   📄 ${match[3]}:${match[4]}`);
                        } else {
                            // Fallback - zobrazit začátek raw HTML
                            const stripped = text.replace(/<[^>]*>/g, '').substring(0, 300);
                            logError(`   💬 ${stripped}`);
                        }
                    } else {
                        // Plain text error
                        logError(`   💬 ${text.substring(0, 300)}`);
                    }

                    log('');
                    return;
                }
            }
        } catch (e) {
            logError(`Nelze parsovat odpověď od API (HTTP ${response.status})`);
            logError('Parse error: ' + e.message);
            log('');
            return;
        }

        // Zkontrolovat HTTP status
        if (!response.ok && isJson) {
            logError(`API vrátilo chybu (HTTP ${response.status}):`);
            logError(data.message || data.error || response.statusText || 'Neznámá chyba');
            if (data.debug) {
                logError('Debug: ' + JSON.stringify(data.debug).substring(0, 200));
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
                totalErrors += errors.length;
            } else {
                logSuccess('✓ Žádné PHP syntax errors');
            }

            if (warnings.length > 0) {
                logWarning(`${warnings.length} upozornění`);
                totalWarnings += warnings.length;
            }
        } else {
            logError('Nepodařilo se zkontrolovat PHP soubory: ' + (data.message || 'Unknown error'));
            totalErrors++;
        }
    } catch (error) {
        logError('Chyba při kontrole PHP:');
        logError(`   📍 URL: /api/control_center_api.php?action=check_php_files`);
        logError(`   💬 ${error.message}`);
        logError(`   ℹ️  Zkontrolujte, zda API soubor existuje a je dostupný`);
        totalErrors++;
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
        const contentType = response.headers.get('content-type');
        let data;
        let isJson = false;

        try {
            const text = await response.text();

            if (text && (contentType?.includes('application/json') || text.trim().startsWith('{'))) {
                data = JSON.parse(text);
                isJson = true;
            } else if (!response.ok) {
                logError(`API vrátilo chybu (HTTP ${response.status} ${response.statusText}):`);
                logError(`   📍 URL: /api/control_center_api.php?action=check_js_errors`);
                // Extrahovat chybu z HTML nebo zobrazit raw
                if (text.includes('<b>Fatal error</b>') || text.includes('<b>Parse error</b>')) {
                    const match = text.match(/<b>(.*?)<\/b>:\s*(.*?)\s+in\s+<b>(.*?)<\/b>\s+on line\s+<b>(\d+)<\/b>/);
                    if (match) {
                        logError(`   💬 ${match[1]}: ${match[2]}`);
                        logError(`   📄 ${match[3]}:${match[4]}`);
                    } else {
                        logError(`   💬 ${text.replace(/<[^>]*>/g, '').substring(0, 300)}`);
                    }
                } else {
                    logError(`   💬 ${text.substring(0, 300)}`);
                }
                log('');
                return;
            }
        } catch (e) {
            logError(`Nelze parsovat odpověď (HTTP ${response.status}): ${e.message}`);
            log('');
            return;
        }

        if (!response.ok && isJson) {
            logError(`API vrátilo chybu (HTTP ${response.status}):`);
            logError(data.message || data.error || 'Neznámá chyba');
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
                totalWarnings += error_count;
            } else {
                logSuccess('✓ Žádné nedávné JavaScript errors');
            }
        } else {
            logError('Nepodařilo se zkontrolovat JavaScript: ' + (data.message || 'Unknown error'));
            totalErrors++;
        }
    } catch (error) {
        logError('Chyba při kontrole JS:');
        logError(`   📍 URL: /api/control_center_api.php?action=check_js_errors`);
        logError(`   💬 ${error.message}`);
        totalErrors++;
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
                totalErrors += corrupted.length;
            } else {
                logSuccess('Všechny tabulky v pořádku (CHECK TABLE)');
            }

            if (missing_indexes && missing_indexes.length > 0) {
                logWarning(`${missing_indexes.length} doporučených indexů chybí`);
                totalWarnings += missing_indexes.length;
            }
        } else {
            logError('Nepodařilo se zkontrolovat databázi');
            totalErrors++;
        }
    } catch (error) {
        logError('Chyba při kontrole DB: ' + error.message);
        totalErrors++;
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
                logError(`   📍 URL: ${endpoint}?action=ping`);
                if (responseText && responseText.length > 0) {
                    // Try to extract meaningful error from HTML or show raw text
                    if (responseText.includes('<b>Fatal error</b>') || responseText.includes('<b>Parse error</b>')) {
                        const match = responseText.match(/<b>(.*?)<\/b>:\s*(.*?)\s+in\s+<b>(.*?)<\/b>/);
                        if (match) {
                            logError(`   💬 ${match[1]}: ${match[2]}`);
                            logError(`   📄 ${match[3]}`);
                        } else {
                            logError(`   💬 ${responseText.replace(/<[^>]*>/g, '').substring(0, 200)}`);
                        }
                    } else {
                        logError(`   💬 ${responseText.substring(0, 200)}`);
                    }
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
            logError(`   📍 URL: ${endpoint}?action=ping`);
            logError(`   💬 ${error.message}`);
            logError(`   ℹ️  Možné příčiny: CORS policy, timeout, nebo server neběží`);
            failedCount++;
        }
    }

    document.getElementById('stat-api').textContent = workingCount;

    log('');
    if (failedCount === 0) {
        logSuccess('✓ Všechny API endpointy fungují správně');
    } else {
        logWarning(`⚠ ${failedCount} API endpointů nefunguje - viz detaily výše`);
        totalWarnings += failedCount;
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

            // Add errors from logs to total count
            const logErrors = (php_errors?.length || 0) + (js_errors?.length || 0);
            totalErrors += logErrors;

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
                totalWarnings += security_logs.length;
            } else {
                logSuccess('✓ Žádné security events (24h)');
            }
        }
    } catch (error) {
        logError('Nepodařilo se načíst logy: ' + error.message);
        totalErrors++;
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
                totalErrors += not_writable.length;
            } else {
                logSuccess('Všechna oprávnění v pořádku');
            }
        }
    } catch (error) {
        logWarning('Nepodařilo se zkontrolovat oprávnění: ' + error.message);
        totalWarnings++;
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
                totalErrors++;
            }

            if (checks.csrf_protection) {
                logSuccess('CSRF ochrana aktivní');
            } else {
                totalWarnings++;
            }

            if (checks.rate_limiting) {
                logSuccess('Rate limiting aktivní');
            } else {
                totalWarnings++;
            }

            if (checks.strong_passwords) {
                logSuccess('Silná hesla vynucena');
            } else {
                logWarning('Doporučujeme silnější hesla');
                totalWarnings++;
            }

            if (checks.admin_keys_secure) {
                logSuccess('Admin klíče zabezpečené');
            } else {
                logError('Admin klíče NEJSOU zabezpečené!');
                totalErrors++;
            }
        }
    } catch (error) {
        logWarning('Nepodařilo se zkontrolovat bezpečnost: ' + error.message);
        totalWarnings++;
    }

    log('');
}

// ============================================
// NEW CHECK FUNCTIONS
// ============================================

async function checkSystemInfo() {
    logHeader('0. SYSTÉMOVÉ INFORMACE');
    log('Načítám informace o serveru...');

    try {
        const response = await fetch('/api/control_center_api.php?action=get_system_info', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const info = data.data;

            // Update stat cards
            document.getElementById('stat-php-version').textContent = info.php_version || '—';
            document.getElementById('stat-disk').textContent = info.disk_usage || '—';
            document.getElementById('stat-memory').textContent = info.memory_limit || '—';
            document.getElementById('stat-upload').textContent = info.max_upload || '—';

            logSuccess(`PHP: ${info.php_version}`);
            logSuccess(`Disk Space: ${info.disk_usage}`);
            logSuccess(`Memory Limit: ${info.memory_limit}`);
            logSuccess(`Max Upload: ${info.max_upload}`);

            if (info.extensions) {
                logSuccess(`PHP Extensions: ${info.extensions.length} loaded`);
            }
        }
    } catch (error) {
        logWarning('Nepodařilo se načíst systémové informace: ' + error.message);
    }

    log('');
}

async function checkHtmlPages() {
    logHeader('1. HTML/PHP STRÁNKY (Frontend)');
    log('Kontroluji HTML/PHP stránky...');

    try {
        const response = await fetch('/api/control_center_api.php?action=check_html_pages', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const { total, errors, warnings } = data.data;
            document.getElementById('stat-html').textContent = total;

            logSuccess(`Nalezeno ${total} HTML/PHP stránek`);

            if (errors && errors.length > 0) {
                logError(`Nalezeno ${errors.length} chyb:`);
                log('═'.repeat(79));
                errors.slice(0, 10).forEach(err => {
                    logError(`📄 ${err.file}`);
                    logError(`   💬 ${err.error.substring(0, 200)}`);
                    log('─'.repeat(79));
                });
                if (errors.length > 10) {
                    logError(`... a dalších ${errors.length - 10} chyb`);
                }
                totalErrors += errors.length;
            } else {
                logSuccess('✓ Žádné chyby v HTML/PHP stránkách');
            }

            if (warnings && warnings.length > 0) {
                logWarning(`${warnings.length} upozornění`);
                totalWarnings += warnings.length;
            }
        }
    } catch (error) {
        logError('Chyba při kontrole HTML/PHP stránek:');
        logError(`   💬 ${error.message}`);
        totalErrors++;
    }

    log('');
}

async function checkAssets() {
    logHeader('4. CSS/ASSETS');
    log('Kontroluji CSS, obrázky a další assets...');

    try {
        const response = await fetch('/api/control_center_api.php?action=check_assets', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const { css_files, images, total_size, errors } = data.data;
            document.getElementById('stat-css').textContent = css_files || 0;

            logSuccess(`CSS souborů: ${css_files || 0}`);
            logSuccess(`Obrázků: ${images || 0}`);
            logSuccess(`Celková velikost: ${total_size || '0 B'}`);

            if (errors && errors.length > 0) {
                logError(`Nalezeno ${errors.length} chyb v assets:`);
                errors.forEach(err => {
                    logError(`  ${err}`);
                });
                totalErrors += errors.length;
            } else {
                logSuccess('✓ Všechny assets v pořádku');
            }
        }
    } catch (error) {
        logError('Chyba při kontrole assets:');
        logError(`   💬 ${error.message}`);
        totalErrors++;
    }

    log('');
}

// ============================================
// MAINTENANCE FUNCTIONS
// ============================================

async function clearCacheMaintenance() {
    if (!confirm('Vymazat cache? Tato akce může dočasně zpomalit systém.')) {
        return;
    }

    logHeader('🔄 CLEAR CACHE');
    log('Mazání cache...');

    try {
        const response = await fetch('/api/control_center_api.php?action=clear_cache', {
            method: 'POST',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            logSuccess('✓ Cache byla úspěšně vymazána!');
        } else {
            logError('Chyba při mazání cache: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        logError('Chyba: ' + error.message);
    }

    log('');
}

async function optimizeDatabaseMaintenance() {
    if (!confirm('Optimalizovat databázi? Tato akce může trvat několik minut.')) {
        return;
    }

    logHeader('⚡ OPTIMIZE DATABASE');
    log('Optimalizuji databázi...');

    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = 'Optimalizuji...';
    btn.disabled = true;

    try {
        const response = await fetch('/api/control_center_api.php?action=optimize_database', {
            method: 'POST',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            logSuccess(`✓ Databáze optimalizována!`);
            logSuccess(`  Optimalizováno ${data.tables_optimized || '?'} tabulek`);
            logSuccess(`  Čas: ${data.time_ms || '?'}ms`);
        } else {
            logError('Chyba při optimalizaci: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        logError('Chyba: ' + error.message);
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }

    log('');
}

async function archiveLogsMaintenance() {
    if (!confirm('Archivovat logy starší než 90 dní?')) {
        return;
    }

    logHeader('📜 ARCHIVE LOGS');
    log('Archivahuji staré logy...');

    try {
        const response = await fetch('/api/control_center_api.php?action=archive_logs', {
            method: 'POST',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            logSuccess(`✓ Archivováno ${data.count || 0} logů!`);
        } else {
            logError('Chyba při archivaci: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        logError('Chyba: ' + error.message);
    }

    log('');
}

// ============================================
// ADDITIONAL COMPREHENSIVE CHECKS
// ============================================

async function checkDependencies() {
    logHeader('10. ZÁVISLOSTI (Dependencies)');
    log('Kontroluji composer.json, package.json...');

    try {
        const response = await fetch('/api/control_center_api.php?action=check_dependencies', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const { composer, npm } = data.data;

            // Composer
            if (composer) {
                if (composer.exists) {
                    logSuccess(`✓ composer.json nalezen`);
                    logSuccess(`  Závislostí: ${composer.packages || 0}`);
                    if (composer.outdated && composer.outdated.length > 0) {
                        logWarning(`  ${composer.outdated.length} zastaralých balíčků`);
                        totalWarnings += composer.outdated.length;
                    }
                } else {
                    logWarning('composer.json nenalezen');
                }
            }

            // NPM
            if (npm) {
                if (npm.exists) {
                    logSuccess(`✓ package.json nalezen`);
                    logSuccess(`  Závislostí: ${npm.packages || 0}`);
                    if (npm.vulnerabilities && npm.vulnerabilities > 0) {
                        logError(`  ${npm.vulnerabilities} bezpečnostních zranitelností!`);
                        totalErrors += npm.vulnerabilities;
                    }
                } else {
                    logWarning('package.json nenalezen');
                }
            }
        }
    } catch (error) {
        logWarning('Nepodařilo se zkontrolovat závislosti: ' + error.message);
    }

    log('');
}

async function checkConfiguration() {
    logHeader('11. KONFIGURACE');
    log('Kontroluji konfigurační soubory...');

    try {
        const response = await fetch('/api/control_center_api.php?action=check_configuration', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const { config_files, errors, warnings } = data.data;

            logSuccess(`Nalezeno ${config_files || 0} konfiguračních souborů`);

            if (errors && errors.length > 0) {
                logError(`${errors.length} chyb v konfiguraci:`);
                errors.forEach(err => {
                    logError(`  📄 ${err.file}: ${err.error}`);
                });
                totalErrors += errors.length;
            }

            if (warnings && warnings.length > 0) {
                logWarning(`${warnings.length} upozornění:`);
                warnings.forEach(warn => {
                    logWarning(`  ${warn}`);
                });
                totalWarnings += warnings.length;
            }

            if (!errors || errors.length === 0) {
                logSuccess('✓ Konfigurace v pořádku');
            }
        }
    } catch (error) {
        logWarning('Nepodařilo se zkontrolovat konfiguraci: ' + error.message);
    }

    log('');
}

async function checkGitStatus() {
    logHeader('12. GIT STATUS');
    log('Kontroluji git repository...');

    try {
        const response = await fetch('/api/control_center_api.php?action=check_git_status', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const { branch, uncommitted, untracked, ahead, behind } = data.data;

            logSuccess(`Branch: ${branch || 'unknown'}`);

            if (uncommitted && uncommitted > 0) {
                logWarning(`${uncommitted} uncommitted změn`);
                totalWarnings++;
            }

            if (untracked && untracked > 0) {
                logWarning(`${untracked} untracked souborů`);
            }

            if (ahead && ahead > 0) {
                logSuccess(`${ahead} commits ahead of origin`);
            }

            if (behind && behind > 0) {
                logWarning(`${behind} commits behind origin`);
            }

            if (!uncommitted && !untracked) {
                logSuccess('✓ Working directory clean');
            }
        }
    } catch (error) {
        logWarning('Nepodařilo se zkontrolovat git: ' + error.message);
    }

    log('');
}

if (DEBUG_MODE) console.log('✅ Console loaded');
</script>
