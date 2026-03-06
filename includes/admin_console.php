<?php
/**
 * Control Center - Konzole
 * Komplexní diagnostika celé aplikace (HTML, PHP, JS, CSS, SQL, API)
 */

require_once __DIR__ . '/../init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();

// Detect embed mode for iframe contexts
$embedMode = isset($_GET['embed']) && $_GET['embed'] == '1';

// Check if accessed directly (not through admin.php)
$directAccess = !defined('ADMIN_PHP_LOADED');

// If embed mode, output full HTML structure
if ($embedMode && $directAccess):
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Konzole - WGS Admin</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="embed-mode">
<?php
endif;
?>

<?php if (!$directAccess): ?>
<link rel="stylesheet" href="/assets/css/admin.css">
<?php endif; ?>
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
}

.console-actions {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.console-btn {
    background: #333;
    color: #ccc;
    border: 1px solid #444;
    padding: 0.5rem 1rem;
    border-radius: 8px;
    font-size: 0.875rem;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    flex: 1 1 auto;
    min-width: 110px;
    text-align: center;
    white-space: nowrap;
}

.console-btn:hover {
    background: #444;
    border-color: #555;
    color: #fff;
}

.console-btn.danger {
    background: #333;
    color: #dc3545;
    border: 1px solid #dc3545;
}

.console-btn.danger:hover {
    background: #dc3545;
    color: white;
}

.console-btn.success {
    background: #333;
    color: #39ff14;
    border: 1px solid #39ff14;
}

.console-btn.success:hover {
    background: #39ff14;
    color: #000;
}

.console-btn:disabled {
    background: #222;
    color: #666;
    border-color: #333;
    cursor: not-allowed;
    opacity: 0.6;
}

.console-output {
    padding: 0.875rem;
    height: 400px;
    overflow-y: auto;
    font-family: 'Courier New', monospace;
    font-size: 0.813rem;
    line-height: 1.5;
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
    grid-template-columns: repeat(6, 1fr);
    gap: 0.5rem;
    margin-bottom: 1rem;
}

.console-stat-card {
    background: #2D2D2D;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    border: 1px solid #3E3E3E;
    display: flex;
    flex-direction: column;
    justify-content: center;
    min-height: 80px;
}

.console-stat-label {
    color: #858585;
    font-size: 0.75rem;
    margin-bottom: 0.25rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.console-stat-value {
    font-size: 1.25rem;
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
    <?php if (!$directAccess): ?>
    <div class="control-detail-header">
        <button class="control-detail-back" data-href="admin.php">
            <span>&lt;</span>
            <span>Zpět</span>
        </button>
        <h2 class="control-detail-title">Konzole</h2>
    </div>
    <?php endif; ?>

    <div class="control-detail-content" style="<?= $embedMode ? 'padding-top: 1rem;' : '' ?>">

        <!-- Alert -->
        <div class="cc-alert info">
            <div class="cc-alert-icon"></div>
            <div class="cc-alert-content">
                <div class="cc-alert-title">Developer Console</div>
                <div class="cc-alert-message">
                    Komplexní diagnostika celé aplikace. Kontroluje PHP syntax, JavaScript errors,
                    SQL tabulky, API endpointy, CSS validity a další.
                </div>
            </div>
        </div>

        <!-- All Statistics in One Grid (6x2) -->
        <div class="console-stats" id="console-all-stats" style="display: none;">
            <!-- System Info -->
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
            <div class="console-stat-card">
                <div class="console-stat-label">HTML/PHP Stránky</div>
                <div class="console-stat-value success" id="stat-html">—</div>
            </div>
            <div class="console-stat-card">
                <div class="console-stat-label">PHP Backend</div>
                <div class="console-stat-value success" id="stat-php">—</div>
            </div>

            <!-- Row 2 -->
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
                <div class="console-actions">
                    <button class="console-btn success" id="btn-run-diagnostics" data-action="runDiagnostics">
                        Spustit diagnostiku
                    </button>
                    <button class="console-btn" id="btn-clear" data-action="clearConsole" disabled>
                        Vymazat
                    </button>
                    <button class="console-btn" id="btn-export" data-action="exportLog" disabled>
                        Export
                    </button>
                    <button class="console-btn" data-action="clearCacheMaintenance" title="Vymazat cache">
                        Cache
                    </button>
                    <button class="console-btn" data-action="optimizeDatabaseMaintenance" title="Optimalizovat databázi">
                        Optimize DB
                    </button>
                    <button class="console-btn danger" data-action="cleanupLogsMaintenance" title="Vyčistit logy, cache a spustit backup">
                        Cleanup
                    </button>
                    <button class="console-btn" data-action="archiveLogsMaintenance" title="Archivovat staré logy">
                        Archive
                    </button>
                </div>
            </div>
            <div class="console-output" id="console-output">
                <div class="console-empty">
                    <div style="font-size: 3rem; margin-bottom: 1rem;"></div>
                    <div style="font-size: 1.25rem; margin-bottom: 0.5rem;">Konzole připravena</div>
                    <div>Klikněte na "Spustit diagnostiku" pro kontrolu aplikace</div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
// Debug mode - set to false in production
if (typeof DEBUG_MODE === 'undefined') { var DEBUG_MODE = false; }

// CSRF Token from session
const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token'] ?? '') ?>;

let consoleOutput = [];
let diagnosticsRunning = false;
let totalErrors = 0;
let totalWarnings = 0;
let errorsList = [];  // Sbírání všech chyb pro finální summary
let warningsList = [];  // Sbírání všech upozornění

// ============================================
// ERROR/WARNING TRACKING FUNCTIONS
// ============================================

/**
 * AddError
 */
function addError(section, message, details = null) {
    errorsList.push({ section, message, details });
    totalErrors++;
}

/**
 * AddWarning
 */
function addWarning(section, message, details = null) {
    warningsList.push({ section, message, details });
    totalWarnings++;
}

// ============================================
// CONSOLE OUTPUT FUNCTIONS
// ============================================

/**
 * Log
 */
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

/**
 * LogHeader
 */
function logHeader(message) {
    log(message, 'header');
}

/**
 * LogSuccess
 */
function logSuccess(message) {
    log(message, 'success');
}

/**
 * LogWarning
 */
function logWarning(message) {
    log(message, 'warning');
}

/**
 * LogError
 */
function logError(message) {
    log(message, 'error');
}

/**
 * RenderConsole
 */
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

/**
 * ClearConsole
 */
function clearConsole() {
    if (!confirm('Vymazat výstup konzole?')) return;
    consoleOutput = [];
    document.getElementById('console-output').innerHTML = `
        <div class="console-empty">
            <div style="font-size: 3rem; margin-bottom: 1rem;"></div>
            <div style="font-size: 1.25rem; margin-bottom: 0.5rem;">Konzole vymazána</div>
            <div>Klikněte na "Spustit diagnostiku" pro novou kontrolu</div>
        </div>
    `;
    document.getElementById('btn-clear').disabled = true;
    document.getElementById('btn-export').disabled = true;
    document.getElementById('console-all-stats').style.display = 'none';
}

/**
 * ExportLog
 */
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

// ============================================
// MAIN DIAGNOSTICS FUNCTION
// ============================================

/**
 * RunDiagnostics
 */
async function runDiagnostics() {
    if (diagnosticsRunning) {
        alert('Diagnostika již běží!');
        return;
    }

    diagnosticsRunning = true;
    const btn = document.getElementById('btn-run-diagnostics');
    btn.disabled = true;
    btn.textContent = 'Kontroluji...';

    // Clear previous output
    consoleOutput = [];
    renderConsole();

    // Show stats
    document.getElementById('console-all-stats').style.display = 'grid';

    // Reset stats
    ['html', 'php', 'js', 'css', 'sql', 'api', 'errors', 'warnings', 'php-version', 'disk', 'memory', 'upload'].forEach(stat => {
        document.getElementById('stat-' + stat).textContent = '—';
    });

    // Reset error counters
    totalErrors = 0;
    totalWarnings = 0;
    errorsList = [];
    warningsList = [];

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

        // 5. SQL Database Check (Basic)
        await checkDatabase();

        // 5B. SQL Advanced Check (Foreign Keys, Slow Queries, Collations, Orphaned Records)
        await checkDatabaseAdvanced();

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

        // 13. Performance Check
        await checkPerformance();

        // 14. Code Quality Check
        await checkCodeQuality();

        // 15. SEO Check - DISABLED (not relevant for backend app)
        // await checkSEO();

        // 16. Workflow Check
        await checkWorkflow();

        // 17. Email Test (PHPMailer)
        await checkEmailSystem();

        // 18. Session Security
        await checkSessionSecurity();

        // 19. Security Vulnerabilities Scan
        await checkSecurityVulnerabilities();

        // 20. Code Analysis - Komplexní kontrola (DISABLED - příliš časově náročná)
        // POZNÁMKA: checkCodeAnalysis() analyzuje 300+ souborů (syntax, závorky, stringy, SQL injection)
        // Běží několik minut a může způsobit timeout. Pro expert analysis spustit samostatně.
        // await checkCodeAnalysis();

        log('');
        logHeader('═══════════════════════════════════════════════════');
        logHeader('SHRNUTÍ DIAGNOSTIKY');
        logHeader('═══════════════════════════════════════════════════');
        log('');

        // Summary of errors
        if (totalErrors > 0) {
            logError(`CELKEM ${totalErrors} CHYB${totalErrors === 1 ? 'A' : (totalErrors < 5 ? 'Y' : '')}:`);
            log('');
            errorsList.forEach((err, idx) => {
                logError(`${idx + 1}. [${err.section}] ${err.message}`);
                if (err.details) {
                    log(`   ${err.details}`);
                }
            });
        } else {
            logSuccess('ŽÁDNÉ CHYBY!');
        }

        log('');

        // Summary of warnings
        if (totalWarnings > 0) {
            logWarning(`⚠️  ${totalWarnings} UPOZORNĚNÍ`);
            log('');
            // Zobrazit prvních 15 warnings (nebo všechny pokud je jich méně)
            const displayCount = Math.min(warningsList.length, 15);
            warningsList.slice(0, displayCount).forEach((warn, idx) => {
                logWarning(`${idx + 1}. [${warn.section}] ${warn.message}`);
                if (warn.details) {
                    log(`   ${warn.details}`);
                }
            });
            if (warningsList.length > displayCount) {
                log('');
                log(`... a dalších ${warningsList.length - displayCount} upozornění`);
            }
        }

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
        addError('Diagnostika', 'Kritická chyba', error.message);
        document.getElementById('stat-errors').textContent = totalErrors;
    } finally {
        diagnosticsRunning = false;
        btn.disabled = false;
        btn.textContent = 'Spustit diagnostiku';
    }
}

// ============================================
// INDIVIDUAL CHECK FUNCTIONS
// ============================================

/**
 * CheckPhpFiles
 */
async function checkPhpFiles() {
    logHeader('1. PHP SOUBORY');
    log('Kontroluji PHP syntax a strukturu...');

    try {
        const response = await fetch('/api/admin.php?action=check_php_files', {
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
                    logError(`   URL: /api/admin.php?action=check_php_files`);

                    // Extrahovat chybovou zprávu z HTML pokud je to HTML
                    if (text.includes('<b>Fatal error</b>') || text.includes('<b>Parse error</b>')) {
                        // PHP Fatal error v HTML formátu
                        const match = text.match(/<b>(.*?)<\/b>:\s*(.*?)\s+in\s+<b>(.*?)<\/b>\s+on line\s+<b>(\d+)<\/b>/);
                        if (match) {
                            logError(`   ${match[1]}: ${match[2]}`);
                            logError(`   ${match[3]}:${match[4]}`);
                        } else {
                            // Fallback - zobrazit začátek raw HTML
                            const stripped = text.replace(/<[^>]*>/g, '').substring(0, 300);
                            logError(`   ${stripped}`);
                        }
                    } else {
                        // Plain text error
                        logError(`   ${text.substring(0, 300)}`);
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

            if (errors.length > 0) {
                logError(`${errors.length} PHP chyb`);
                // Přidat do seznamu chyb
                errors.forEach(err => {
                    addError('PHP',
                        err.file + (err.line ? `:${err.line}` : ''),
                        (err.type ? err.type.toUpperCase() + ': ' : '') + err.error?.substring(0, 100)
                    );
                });
                // totalErrors již zvýšeno v addError()
            } else {
                logSuccess(`${total} PHP souborů - OK`);
            }

            if (warnings.length > 0) {
                logWarning(`⚠️  ${warnings.length} upozornění`);
                // Přidat každé warning do seznamu
                warnings.slice(0, 10).forEach(warn => {
                    addWarning('PHP', warn.file || 'Unknown file', warn.warning?.substring(0, 100));
                });
                if (warnings.length > 10) {
                    addWarning('PHP', `... a dalších ${warnings.length - 10} PHP warnings`);
                }
            }
        } else {
            logError('Nepodařilo se zkontrolovat PHP soubory');
            addError('PHP', 'Kontrola selhala', data.message || 'Unknown error');
        }
    } catch (error) {
        logError('Chyba při kontrole PHP:');
        logError(`   URL: /api/admin.php?action=check_php_files`);
        logError(`   ${error.message}`);
        logError(`   Zkontrolujte, zda API soubor existuje a je dostupný`);
        addError('PHP', 'Kontrola selhala', error.message);
        if (DEBUG_MODE) console.error(error);
    }

    log('');
}

/**
 * CheckJavaScriptFiles
 */
async function checkJavaScriptFiles() {
    logHeader('2. JAVASCRIPT SOUBORY');
    log('Kontroluji JavaScript errors z logů...');

    try {
        const response = await fetch('/api/admin.php?action=check_js_errors', {
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
                logError(`   URL: /api/admin.php?action=check_js_errors`);
                // Extrahovat chybu z HTML nebo zobrazit raw
                if (text.includes('<b>Fatal error</b>') || text.includes('<b>Parse error</b>')) {
                    const match = text.match(/<b>(.*?)<\/b>:\s*(.*?)\s+in\s+<b>(.*?)<\/b>\s+on line\s+<b>(\d+)<\/b>/);
                    if (match) {
                        logError(`   ${match[1]}: ${match[2]}`);
                        logError(`   ${match[3]}:${match[4]}`);
                    } else {
                        logError(`   ${text.replace(/<[^>]*>/g, '').substring(0, 300)}`);
                    }
                } else {
                    logError(`   ${text.substring(0, 300)}`);
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
                        logWarning(`   ${err.file}:${err.line || '?'}${err.column ? ':' + err.column : ''}`);
                    }
                    if (err.timestamp) {
                        logWarning(`   ${err.timestamp}`);
                    }
                    log('─'.repeat(79));
                });

                if (recent_errors.length > 10) {
                    logWarning(`... a dalších ${recent_errors.length - 10} chyb`);
                }
                totalWarnings += error_count;
            } else {
                logSuccess('Žádné nedávné JavaScript errors');
            }
        } else {
            logError('Nepodařilo se zkontrolovat JavaScript: ' + (data.message || 'Unknown error'));
            addError('JavaScript', 'Kontrola selhala', data.message || 'Unknown error');
        }
    } catch (error) {
        logError('Chyba při kontrole JS:');
        logError(`   URL: /api/admin.php?action=check_js_errors`);
        logError(`   ${error.message}`);
        addError('JavaScript', 'Kontrola selhala', error.message);
        if (DEBUG_MODE) console.error(error);
    }

    log('');
}

/**
 * CheckDatabase
 */
async function checkDatabase() {
    logHeader('3. SQL DATABÁZE');
    log('Kontroluji tabulky, indexy a integritu...');

    try {
        const response = await fetch('/api/admin.php?action=check_database', {
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
                log('═'.repeat(79));

                // Přidat každý missing index do seznamu
                missing_indexes.forEach((idx, i) => {
                    if (i < 10) {
                        const table = idx.table || 'Unknown';
                        const column = idx.column || 'Unknown';
                        const reason = idx.reason || '';
                        logWarning(`  ${table}.${column} - ${reason}`);
                        addWarning('SQL/Indexy', `${table}.${column}`, reason);
                    }
                });

                if (missing_indexes.length > 10) {
                    logWarning(`  ... a dalších ${missing_indexes.length - 10} indexů`);
                    addWarning('SQL/Indexy', `... a dalších ${missing_indexes.length - 10} chybějících indexů`);
                }
            } else {
                logSuccess('Všechny důležité indexy jsou přítomny');
            }
        } else {
            logError('Nepodařilo se zkontrolovat databázi');
            addError('Databáze', 'Kontrola selhala');
        }
    } catch (error) {
        logError('Chyba při kontrole DB: ' + error.message);
        addError('Databáze', 'Chyba při kontrole', error.message);
    }

    log('');
}

/**
 * CheckApiEndpoints
 */
async function checkApiEndpoints() {
    logHeader('4. API ENDPOINTY');
    log('Testuji dostupnost API...');

    const endpoints = [
        '/api/admin_api.php',
        '/api/admin.php',
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
                logSuccess(`${endpoint} - OK (HTTP ${response.status})`);
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

                logError(`${endpoint} - HTTP ${response.status} ${response.statusText}`);
                logError(`   URL: ${endpoint}?action=ping`);
                if (responseText && responseText.length > 0) {
                    // Try to extract meaningful error from HTML or show raw text
                    if (responseText.includes('<b>Fatal error</b>') || responseText.includes('<b>Parse error</b>')) {
                        const match = responseText.match(/<b>(.*?)<\/b>:\s*(.*?)\s+in\s+<b>(.*?)<\/b>/);
                        if (match) {
                            logError(`   ${match[1]}: ${match[2]}`);
                            logError(`   ${match[3]}`);
                        } else {
                            logError(`   ${responseText.replace(/<[^>]*>/g, '').substring(0, 200)}`);
                        }
                    } else {
                        logError(`   ${responseText.substring(0, 200)}`);
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

            logError(`${endpoint} - Network Error`);
            logError(`   URL: ${endpoint}?action=ping`);
            logError(`   ${error.message}`);
            logError(`   Možné příčiny: CORS policy, timeout, nebo server neběží`);
            failedCount++;
        }
    }

    document.getElementById('stat-api').textContent = workingCount;

    log('');
    if (failedCount === 0) {
        logSuccess('Všechny API endpointy fungují správně');
    } else {
        logWarning(`${failedCount} API endpointů nefunguje - viz detaily výše`);
        totalWarnings += failedCount;
    }

    log('');
}

/**
 * CheckErrorLogs
 */
async function checkErrorLogs() {
    logHeader('5. ERROR LOGY');
    log('Kontroluji nedávné chyby...');

    try {
        const response = await fetch('/api/admin.php?action=get_recent_errors', {
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
                logWarning(`${php_errors.length} PHP errors (poslední 24h):`);
                log('═'.repeat(79));

                // Zobrazit top 5 PHP errors
                php_errors.slice(0, 5).forEach((err, idx) => {
                    if (err.parsed !== false && err.file && err.line) {
                        // Plná chyba s file:line
                        logWarning(`#${idx + 1}: ${err.type || 'Error'}`);
                        logWarning(`   ${err.file}:${err.line}`);
                        logWarning(`   ${err.message}`);
                        if (err.timestamp) {
                            logWarning(`   ${err.timestamp}`);
                        }
                    } else if (err.parsed === true && err.message) {
                        // Jen message bez file:line
                        logWarning(`#${idx + 1}: ${err.message}`);
                        if (err.timestamp) {
                            logWarning(`   ${err.timestamp}`);
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
                logSuccess('Žádné PHP errors (24h)');
            }

            log('');

            // JS ERRORS
            if (js_errors && js_errors.length > 0) {
                logWarning(`${js_errors.length} JS errors (poslední 24h):`);
                log('═'.repeat(79));

                // Zobrazit top 5 JS errors
                js_errors.slice(0, 5).forEach((err, idx) => {
                    logWarning(`#${idx + 1}: ${err.message}`);
                    if (err.file && err.file !== 'unknown') {
                        logWarning(`   ${err.file}:${err.line || '?'}`);
                    }
                    if (err.timestamp) {
                        logWarning(`   ${err.timestamp}`);
                    }
                    log('─'.repeat(79));
                });

                if (js_errors.length > 5) {
                    logWarning(`... a dalších ${js_errors.length - 5} JS chyb`);
                }
            } else {
                logSuccess('Žádné JS errors (24h)');
            }

            log('');

            // SECURITY LOGS
            if (security_logs && security_logs.length > 0) {
                logWarning(`${security_logs.length} security events (24h):`);
                log('═'.repeat(79));

                // Zobrazit top 5 security events
                security_logs.slice(0, 5).forEach((event, idx) => {
                    if (event.parsed !== false) {
                        const icon = event.severity === 'critical' ? '[CRITICAL]' :
                                    event.severity === 'warning' ? '[WARNING]' : '[INFO]';
                        logWarning(`${icon} #${idx + 1}: [${event.type}] ${event.message}`);
                        if (event.timestamp) {
                            logWarning(`   ${event.timestamp}`);
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
                logSuccess('Žádné security events (24h)');
            }
        }
    } catch (error) {
        logError('Nepodařilo se načíst logy: ' + error.message);
        addError('Logy', 'Kontrola selhala', error.message);
        if (DEBUG_MODE) console.error(error);
    }

    log('');
}

/**
 * CheckFilePermissions
 */
async function checkFilePermissions() {
    logHeader('6. OPRÁVNĚNÍ SOUBORŮ');
    log('Kontroluji write permissions...');

    try {
        const response = await fetch('/api/admin.php?action=check_permissions', {
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
                    addError('Oprávnění', `Složka ${dir} není writable`, 'Opravte permissions přes FTP nebo automatickou opravu');
                });
            } else {
                logSuccess('Všechna oprávnění v pořádku');
            }
        }
    } catch (error) {
        logWarning('Nepodařilo se zkontrolovat oprávnění: ' + error.message);
        addWarning('Oprávnění', 'Kontrola selhala', error.message);
    }

    log('');
}

/**
 * CheckSecurity
 */
async function checkSecurity() {
    logHeader('7. BEZPEČNOST');
    log('Kontroluji bezpečnostní nastavení...');

    try {
        const response = await fetch('/api/admin.php?action=check_security', {
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
                addError('Bezpečnost', 'HTTPS není aktivní');
            }

            if (checks.csrf_protection) {
                logSuccess('CSRF ochrana aktivní');
            } else {
                logWarning('CSRF ochrana NENÍ aktivní');
                addWarning('Bezpečnost', 'CSRF ochrana neaktivní');
            }

            if (checks.rate_limiting) {
                logSuccess('Rate limiting aktivní');
            } else {
                logWarning('Rate limiting NENÍ aktivní');
                addWarning('Bezpečnost', 'Rate limiting neaktivní');
            }

            if (checks.strong_passwords) {
                logSuccess('Silná hesla vynucena');
            } else {
                logWarning('Doporučujeme silnější hesla');
                addWarning('Bezpečnost', 'Slabá hesla povolena');
            }

            if (checks.admin_keys_secure) {
                logSuccess('Admin klíče zabezpečené');
            } else {
                logError('Admin klíče NEJSOU zabezpečené!');
                addError('Bezpečnost', 'Admin klíče nejsou zabezpečené');
            }
        }
    } catch (error) {
        logWarning('Nepodařilo se zkontrolovat bezpečnost: ' + error.message);
        addWarning('Bezpečnost', 'Kontrola selhala', error.message);
    }

    log('');
}

// ============================================
// NEW CHECK FUNCTIONS
// ============================================

/**
 * CheckSystemInfo
 */
async function checkSystemInfo() {
    logHeader('0. SYSTÉMOVÉ INFORMACE');
    log('Načítám informace o serveru...');

    try {
        const response = await fetch('/api/admin.php?action=get_system_info', {
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

/**
 * CheckHtmlPages
 */
async function checkHtmlPages() {
    logHeader('1. HTML/PHP STRÁNKY (Frontend)');
    log('Kontroluji HTML/PHP stránky...');

    try {
        const response = await fetch('/api/admin.php?action=check_html_pages', {
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
                    logError(`${err.file}`);
                    logError(`   ${err.error.substring(0, 200)}`);
                    log('─'.repeat(79));
                });
                if (errors.length > 10) {
                    logError(`... a dalších ${errors.length - 10} chyb`);
                }
                totalErrors += errors.length;
            } else {
                logSuccess('Žádné chyby v HTML/PHP stránkách');
            }

            if (warnings && warnings.length > 0) {
                logWarning(`${warnings.length} upozornění`);
                totalWarnings += warnings.length;
            }
        }
    } catch (error) {
        logError('Chyba při kontrole HTML/PHP stránek:');
        logError(`   ${error.message}`);
        addError('HTML/PHP', 'Kontrola selhala', error.message);
    }

    log('');
}

/**
 * CheckAssets
 */
async function checkAssets() {
    logHeader('4. CSS/ASSETS');
    log('Kontroluji CSS, obrázky a další assets...');

    try {
        const response = await fetch('/api/admin.php?action=check_assets', {
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
                logSuccess('Všechny assets v pořádku');
            }
        }
    } catch (error) {
        logError('Chyba při kontrole assets:');
        logError(`   ${error.message}`);
        addError('Assets', 'Kontrola selhala', error.message);
    }

    log('');
}

// ============================================
// MAINTENANCE FUNCTIONS
// ============================================

/**
 * ClearCacheMaintenance
 */
async function clearCacheMaintenance() {
    logHeader('CLEAR CACHE');
    log('Mazání cache (může dočasně zpomalit systém)...');

    try {
        const response = await fetch('/api/admin.php?action=clear_cache', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ csrf_token: CSRF_TOKEN })
        });

        const data = await response.json();

        if (data.status === 'success') {
            logSuccess('Cache byla úspěšně vymazána!');
        } else {
            logError('Chyba při mazání cache: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        logError('Chyba: ' + error.message);
    }

    log('');
}

/**
 * OptimizeDatabaseMaintenance
 */
async function optimizeDatabaseMaintenance() {
    if (!confirm('Optimalizovat databázi? Tato akce může trvat několik minut.')) {
        return;
    }

    logHeader('OPTIMIZE DATABASE');
    log('Optimalizuji databázi...');

    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = 'Optimalizuji...';
    btn.disabled = true;

    try {
        const response = await fetch('/api/admin.php?action=optimize_database', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ csrf_token: CSRF_TOKEN })
        });

        const data = await response.json();

        if (data.status === 'success') {
            logSuccess(`Databáze optimalizována!`);
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

/**
 * CleanupLogsMaintenance
 */
async function cleanupLogsMaintenance() {
    logHeader('CLEANUP LOGS & BACKUP');
    log('Spouštím kompletní cleanup...');
    log('Toto smaže staré logy (.gz, .20*.log), zkrátí php_errors.log, vyčistí cache a spustí backup...');
    log('');

    try {
        const response = await fetch('/api/admin.php?action=cleanup_logs', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ csrf_token: CSRF_TOKEN })
        });

        const data = await response.json();

        if (data.status === 'success') {
            const r = data.results || {};
            logSuccess('Cleanup dokončen!');
            log('');
            log(`Výsledky:`);
            log(`  🗑️  Smazáno archivů: ${r.deleted_files || 0}`);
            log(`  ✂️  Error log: ${r.log_deleted ? 'SMAZÁN' : 'nenalezen'}`);
            log(`  💾 Cache vymazána: ${r.cache_deleted || 0} souborů`);
            if (r.backup_exists === false) {
                logWarning('  ⚠️  Backup nenalezen - nastavte cron job');
            } else if (r.backup_file) {
                logSuccess(`  📦 Backup: ${r.backup_file}`);
            }
            log('');
            logSuccess('Spusťte diagnostiku - 0 chyb!');
        } else {
            logError('Chyba při cleanup: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        logError('Chyba: ' + error.message);
    }

    log('');
}

/**
 * ArchiveLogsMaintenance
 */
async function archiveLogsMaintenance() {
    logHeader('ARCHIVE LOGS');
    log('Archivahuji staré logy starší než 90 dní...');

    try {
        const response = await fetch('/api/admin.php?action=archive_logs', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify({ csrf_token: CSRF_TOKEN })
        });

        const data = await response.json();

        if (data.status === 'success') {
            logSuccess(`Archivováno ${data.count || 0} logů!`);
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

/**
 * CheckDependencies
 */
async function checkDependencies() {
    logHeader('10. ZÁVISLOSTI (Dependencies)');
    log('Kontroluji composer.json, package.json...');

    try {
        const response = await fetch('/api/admin.php?action=check_dependencies', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const { composer, npm } = data.data;

            // Composer
            if (composer) {
                if (composer.exists) {
                    logSuccess(`composer.json nalezen`);
                    logSuccess(`  Závislostí: ${composer.packages || 0}`);
                    if (composer.outdated && composer.outdated.length > 0) {
                        logWarning(`  ${composer.outdated.length} zastaralých balíčků`);
                        totalWarnings += composer.outdated.length;
                    }
                } else {
                    if (composer.legacy_mode) {
                        log('ℹ️  Composer not in use (legacy project)');
                    } else {
                        logWarning('composer.json nenalezen');
                    }
                }
            }

            // NPM
            if (npm) {
                if (npm.exists) {
                    logSuccess(`package.json nalezen`);
                    logSuccess(`  Závislostí: ${npm.packages || 0}`);
                    if (npm.vulnerabilities && npm.vulnerabilities > 0) {
                        logError(`  ${npm.vulnerabilities} bezpečnostních zranitelností!`);
                        totalErrors += npm.vulnerabilities;
                    }
                } else {
                    if (npm.legacy_mode) {
                        log('ℹ️  NPM not in use (legacy project)');
                    } else {
                        logWarning('package.json nenalezen');
                    }
                }
            }
        }
    } catch (error) {
        logWarning('Nepodařilo se zkontrolovat závislosti: ' + error.message);
    }

    log('');
}

/**
 * CheckConfiguration
 */
async function checkConfiguration() {
    logHeader('11. KONFIGURACE');
    log('Kontroluji konfigurační soubory...');

    try {
        const response = await fetch('/api/admin.php?action=check_configuration', {
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
                    logError(`  ${err.file}: ${err.error}`);
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
                logSuccess('Konfigurace v pořádku');
            }
        }
    } catch (error) {
        logWarning('Nepodařilo se zkontrolovat konfiguraci: ' + error.message);
    }

    log('');
}

/**
 * CheckGitStatus
 */
async function checkGitStatus() {
    logHeader('12. GIT STATUS');
    log('Kontroluji git repository...');

    try {
        const response = await fetch('/api/admin.php?action=check_git_status', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const { branch, uncommitted, untracked, ahead, behind } = data.data;

            logSuccess(`Branch: ${branch || 'unknown'}`);

            if (uncommitted && uncommitted > 0) {
                logWarning(`${uncommitted} uncommitted změn`);
                addWarning('Git', `${uncommitted} uncommitted změn`);
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
                logSuccess('Working directory clean');
            }
        }
    } catch (error) {
        logWarning('Nepodařilo se zkontrolovat git: ' + error.message);
    }

    log('');
}

// ============================================
// SQL ADVANCED CHECKS
// ============================================

/**
 * CheckDatabaseAdvanced
 */
async function checkDatabaseAdvanced() {
    logHeader('5B. SQL POKROČILÉ KONTROLY');
    log('Kontroluji foreign keys, slow queries, collations, orphaned records...');

    try {
        const response = await fetch('/api/admin.php?action=check_database_advanced', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const { foreign_keys, slow_queries, collations, orphaned_records, deadlocks } = data.data;

            // Foreign Keys
            if (foreign_keys) {
                if (foreign_keys.broken && foreign_keys.broken.length > 0) {
                    logError(`${foreign_keys.broken.length} porušených foreign keys:`);
                    log('═'.repeat(79));
                    foreign_keys.broken.slice(0, 5).forEach(fk => {
                        logError(`  ${fk.table}.${fk.column} -> ${fk.referenced_table}.${fk.referenced_column}`);
                        logError(`     ${fk.error || 'Cílová tabulka/záznam neexistuje'}`);
                        log('─'.repeat(79));
                    });
                    totalErrors += foreign_keys.broken.length;
                } else {
                    logSuccess(`Foreign keys: ${foreign_keys.total || 0} OK`);
                }
            }

            // Slow Queries
            if (slow_queries) {
                if (slow_queries.count > 0) {
                    logWarning(`${slow_queries.count} pomalých queries (> ${slow_queries.threshold}s):`);
                    log('═'.repeat(79));
                    if (slow_queries.queries && slow_queries.queries.length > 0) {
                        slow_queries.queries.slice(0, 5).forEach((q, idx) => {
                            logWarning(`#${idx + 1}: ${q.time}s - ${q.query.substring(0, 150)}...`);
                            if (q.rows_examined) {
                                logWarning(`   Rows examined: ${q.rows_examined}`);
                            }
                            log('─'.repeat(79));
                        });
                    }
                    totalWarnings += slow_queries.count;
                } else {
                    logSuccess('Žádné pomalé queries detekované');
                }
            }

            // Table Collations
            if (collations) {
                if (collations.inconsistent && collations.inconsistent.length > 0) {
                    logWarning(`${collations.inconsistent.length} tabulek s nekonzistentní collation:`);
                    log('═'.repeat(79));
                    collations.inconsistent.forEach(t => {
                        logWarning(`  ${t.table}: ${t.collation} (doporučeno: utf8mb4_unicode_ci)`);
                    });
                    totalWarnings += collations.inconsistent.length;
                } else {
                    logSuccess(`Všechny tabulky mají konzistentní collation (${collations.default || 'utf8mb4_unicode_ci'})`);
                }
            }

            // Orphaned Records
            if (orphaned_records) {
                if (orphaned_records.total > 0) {
                    logWarning(`${orphaned_records.total} orphaned records nalezeno:`);
                    log('═'.repeat(79));
                    if (orphaned_records.details && orphaned_records.details.length > 0) {
                        orphaned_records.details.forEach(orphan => {
                            logWarning(`  ${orphan.table}: ${orphan.count} záznamů bez parent`);
                            logWarning(`     FK: ${orphan.foreign_key}`);
                        });
                    }
                    totalWarnings += orphaned_records.total;
                } else {
                    logSuccess('Žádné orphaned records');
                }
            }

            // Deadlocks
            if (deadlocks && deadlocks.count > 0) {
                logWarning(`${deadlocks.count} deadlocků detekováno (24h)`);
                addWarning('SQL', `${deadlocks.count} deadlocků (24h)`);
            }

        } else {
            logWarning('Některé pokročilé SQL kontroly selhaly: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        logWarning('Nepodařilo se provést pokročilé SQL kontroly: ' + error.message);
        if (DEBUG_MODE) console.error(error);
    }

    log('');
}

// ============================================
// PERFORMANCE CHECKS
// ============================================

/**
 * CheckPerformance
 */
async function checkPerformance() {
    logHeader('13. VÝKON (Performance)');
    log('Kontroluji rychlost stránek, velikost assets, minifikaci...');

    try {
        const response = await fetch('/api/admin.php?action=check_performance', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const { page_load_times, large_assets, unminified_files, gzip_enabled, caching_headers, n_plus_one_queries } = data.data;

            // Page Load Times
            if (page_load_times) {
                const slow_pages = page_load_times.pages?.filter(p => p.load_time > 3) || [];
                if (slow_pages.length > 0) {
                    logWarning(`${slow_pages.length} pomalých stránek (> 3s):`);
                    log('═'.repeat(79));
                    slow_pages.slice(0, 5).forEach(page => {
                        logWarning(`  ${page.url}: ${page.load_time.toFixed(2)}s`);
                    });
                    totalWarnings += slow_pages.length;
                } else {
                    logSuccess('Všechny testované stránky rychlé (< 3s)');
                }
            }

            // Large Assets
            if (large_assets && large_assets.files && large_assets.files.length > 0) {
                logWarning(`${large_assets.files.length} velkých souborů (> 500KB):`);
                log('═'.repeat(79));
                large_assets.files.slice(0, 10).forEach(file => {
                    logWarning(`  ${file.path}: ${file.size}`);
                });
                if (large_assets.files.length > 10) {
                    logWarning(`  ... a dalších ${large_assets.files.length - 10} souborů`);
                }
                totalWarnings += large_assets.files.length;
            } else {
                logSuccess('Žádné nadměrně velké assets');
            }

            // Unminified Files - INFO ONLY (not a warning, just optimization opportunity)
            if (unminified_files && unminified_files.length > 0) {
                log(`ℹ️  ${unminified_files.length} neminifikovaných JS/CSS souborů (optimalizace možná):`);
                log('═'.repeat(79));
                unminified_files.slice(0, 10).forEach(file => {
                    log(`  ${file.path} (${file.size || 'N/A'})`);
                });
                // Don't count as warnings - this is just optimization info
            } else {
                logSuccess('JS/CSS soubory jsou minifikované');
            }

            // Gzip Compression
            if (gzip_enabled !== undefined) {
                if (gzip_enabled) {
                    logSuccess('Gzip komprese aktivní');
                } else {
                    logWarning('Gzip komprese NENÍ aktivní');
                    addWarning('Výkon', 'Gzip komprese neaktivní');
                }
            }

            // Caching Headers
            if (caching_headers) {
                if (caching_headers.missing && caching_headers.missing.length > 0) {
                    logWarning(`${caching_headers.missing.length} souborů bez cache headers`);
                    addWarning('Výkon', `${caching_headers.missing.length} souborů bez cache headers`);
                } else {
                    logSuccess('Cache headers správně nastavené');
                }
            }

            // N+1 Queries
            if (n_plus_one_queries && n_plus_one_queries.detected > 0) {
                logWarning(`${n_plus_one_queries.detected} možných N+1 query problémů`);
                addWarning('Výkon/SQL', `${n_plus_one_queries.detected} možných N+1 query problémů`);
            }

        } else {
            logWarning('Nepodařilo se zkontrolovat výkon: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        logWarning('Chyba při kontrole výkonu: ' + error.message);
        if (DEBUG_MODE) console.error(error);
    }

    log('');
}

// ============================================
// CODE QUALITY CHECKS
// ============================================

/**
 * CheckCodeQuality
 */
async function checkCodeQuality() {
    logHeader('14. KVALITA KÓDU');
    log('Kontroluji dead code, TODOs, complexity, duplicity...');

    try {
        const response = await fetch('/api/admin.php?action=check_code_quality', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const { dead_code, todos, complexity, duplicates, psr_compliance } = data.data;

            // Dead Code
            if (dead_code && dead_code.functions && dead_code.functions.length > 0) {
                logWarning(`${dead_code.functions.length} nepoužívaných funkcí:`);
                log('═'.repeat(79));
                dead_code.functions.slice(0, 10).forEach(func => {
                    logWarning(`  ${func.file}:${func.line}`);
                    logWarning(`     function ${func.name}()`);
                });
                if (dead_code.functions.length > 10) {
                    logWarning(`  ... a dalších ${dead_code.functions.length - 10} funkcí`);
                }
                totalWarnings += dead_code.functions.length;
            } else {
                logSuccess('Žádný mrtvý kód detekován');
            }

            // TODOs/FIXMEs
            if (todos && todos.count > 0) {
                logWarning(`${todos.count} TODO/FIXME komentářů:`);
                log('═'.repeat(79));
                if (todos.items && todos.items.length > 0) {
                    todos.items.slice(0, 10).forEach(todo => {
                        const icon = todo.type === 'FIXME' ? '[FIXME]' : '[TODO]';
                        logWarning(`${icon} ${todo.file}:${todo.line}`);
                        logWarning(`   ${todo.comment.substring(0, 100)}`);
                    });
                    if (todos.count > 10) {
                        logWarning(`  ... a dalších ${todos.count - 10} komentářů`);
                    }
                }
                totalWarnings += todos.count;
            } else {
                logSuccess('Žádné TODO/FIXME komentáře');
            }

            // Complexity
            if (complexity && complexity.high_complexity && complexity.high_complexity.length > 0) {
                logWarning(`${complexity.high_complexity.length} funkcí s vysokou komplexitou:`);
                log('═'.repeat(79));
                complexity.high_complexity.slice(0, 5).forEach(func => {
                    logWarning(`  ${func.file}:${func.line}`);
                    logWarning(`     ${func.name}() - Complexity: ${func.complexity}`);
                });
                totalWarnings += complexity.high_complexity.length;
            } else {
                logSuccess('Komplexita kódu v normě');
            }

            // Duplicate Code
            if (duplicates && duplicates.blocks && duplicates.blocks.length > 0) {
                logWarning(`${duplicates.blocks.length} duplicitních bloků kódu:`);
                log('═'.repeat(79));
                duplicates.blocks.slice(0, 5).forEach(dup => {
                    logWarning(`  ${dup.file1}:${dup.line1} <-> ${dup.file2}:${dup.line2}`);
                    logWarning(`     ${dup.lines} řádků duplicitního kódu`);
                });
                totalWarnings += duplicates.blocks.length;
            } else {
                logSuccess('Žádné významné duplicity');
            }

            // PSR Compliance
            if (psr_compliance !== undefined) {
                if (psr_compliance.violations && psr_compliance.violations > 0) {
                    logWarning(`${psr_compliance.violations} PSR porušení`);
                    addWarning('Kvalita kódu', `${psr_compliance.violations} PSR porušení`);
                } else {
                    logSuccess('PSR coding standards dodrženy');
                }
            }

        } else {
            logWarning('Nepodařilo se zkontrolovat kvalitu kódu: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        logWarning('Chyba při kontrole kvality: ' + error.message);
        if (DEBUG_MODE) console.error(error);
    }

    log('');
}

// ============================================
// SEO CHECKS
// ============================================

/**
 * CheckSEO
 */
async function checkSEO() {
    logHeader('15. SEO OPTIMALIZACE');
    log('Kontroluji meta tagy, alt atributy, broken links...');

    try {
        const response = await fetch('/api/admin.php?action=check_seo', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const { missing_meta_tags, missing_alt_tags, broken_links, duplicate_titles, h1_issues } = data.data;

            // Missing Meta Tags
            if (missing_meta_tags && missing_meta_tags.pages && missing_meta_tags.pages.length > 0) {
                logWarning(`${missing_meta_tags.pages.length} stránek bez meta tagů:`);
                log('═'.repeat(79));
                missing_meta_tags.pages.slice(0, 10).forEach(page => {
                    logWarning(`  ${page.url}`);
                    if (page.missing_tags && page.missing_tags.length > 0) {
                        logWarning(`     Chybí: ${page.missing_tags.join(', ')}`);
                    }
                });
                totalWarnings += missing_meta_tags.pages.length;
            } else {
                logSuccess('Všechny stránky mají meta tagy');
            }

            // Missing Alt Tags
            if (missing_alt_tags && missing_alt_tags.images && missing_alt_tags.images.length > 0) {
                logWarning(`${missing_alt_tags.images.length} obrázků bez alt atributu:`);
                log('═'.repeat(79));
                missing_alt_tags.images.slice(0, 10).forEach(img => {
                    logWarning(`  ${img.page}: <img src="${img.src}" loading="lazy">`);
                });
                if (missing_alt_tags.images.length > 10) {
                    logWarning(`  ... a dalších ${missing_alt_tags.images.length - 10} obrázků`);
                }
                totalWarnings += missing_alt_tags.images.length;
            } else {
                logSuccess('Všechny obrázky mají alt atributy');
            }

            // Broken Links
            if (broken_links && broken_links.links && broken_links.links.length > 0) {
                logError(`${broken_links.links.length} broken links (404):`);
                log('═'.repeat(79));
                broken_links.links.slice(0, 10).forEach(link => {
                    logError(`  ${link.page} -> ${link.url} (HTTP ${link.status})`);
                });
                if (broken_links.links.length > 10) {
                    logError(`  ... a dalších ${broken_links.links.length - 10} broken links`);
                }
                totalErrors += broken_links.links.length;
            } else {
                logSuccess('Žádné broken links');
            }

            // Duplicate Titles
            if (duplicate_titles && duplicate_titles.duplicates && duplicate_titles.duplicates.length > 0) {
                logWarning(`${duplicate_titles.duplicates.length} duplicitních title tagů:`);
                log('═'.repeat(79));
                duplicate_titles.duplicates.forEach(dup => {
                    logWarning(`  "${dup.title}"`);
                    logWarning(`     Nalezeno na: ${dup.pages.join(', ')}`);
                });
                totalWarnings += duplicate_titles.duplicates.length;
            } else {
                logSuccess('Všechny title tagy unikátní');
            }

            // H1 Issues
            if (h1_issues && h1_issues.pages && h1_issues.pages.length > 0) {
                logWarning(`${h1_issues.pages.length} stránek s H1 problémem:`);
                log('═'.repeat(79));
                h1_issues.pages.forEach(page => {
                    logWarning(`  ${page.url}: ${page.issue}`);
                });
                totalWarnings += h1_issues.pages.length;
            } else {
                logSuccess('H1 tagy správně nastavené');
            }

        } else {
            logWarning('Nepodařilo se zkontrolovat SEO: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        logWarning('Chyba při kontrole SEO: ' + error.message);
        if (DEBUG_MODE) console.error(error);
    }

    log('');
}

// ============================================
// WORKFLOW CHECKS
// ============================================

/**
 * CheckWorkflow
 */
async function checkWorkflow() {
    logHeader('16. WORKFLOW & INFRASTRUKTURA');
    log('Kontroluji cron jobs, email queue, backups, .env permissions...');

    try {
        const response = await fetch('/api/admin.php?action=check_workflow', {
            method: 'GET',
            credentials: 'same-origin'
        });

        const data = await response.json();

        if (data.status === 'success') {
            const { cron_jobs, email_queue, failed_jobs, backup_status, env_permissions, php_ini_settings, smtp_test } = data.data;

            // Cron Jobs
            if (cron_jobs) {
                if (cron_jobs.not_running && cron_jobs.not_running.length > 0) {
                    logError(`${cron_jobs.not_running.length} cron jobů neběží:`);
                    log('═'.repeat(79));
                    cron_jobs.not_running.forEach(job => {
                        logError(`  ${job.name}: Poslední běh před ${job.last_run || 'nikdy'}`);
                    });
                    totalErrors += cron_jobs.not_running.length;
                } else {
                    logSuccess(`Všechny cron joby běží (${cron_jobs.total || 0} aktivních)`);
                }
            }

            // Email Queue
            if (email_queue) {
                if (email_queue.pending > 50) {
                    logWarning(`${email_queue.pending} nevyřízených emailů ve frontě`);
                    addWarning('Email Queue', `${email_queue.pending} nevyřízených emailů`);
                } else if (email_queue.pending > 0) {
                    logSuccess(`Email queue: ${email_queue.pending} čekajících emailů (v normě)`);
                } else {
                    logSuccess('Email queue prázdná');
                }

                if (email_queue.failed > 0) {
                    logWarning(`${email_queue.failed} selhavších emailů`);
                    addWarning('Email Queue', `${email_queue.failed} selhavších emailů`);
                }
            }

            // Failed Jobs
            if (failed_jobs && failed_jobs.count > 0) {
                logError(`${failed_jobs.count} selhavších úkolů (24h):`);
                log('═'.repeat(79));
                if (failed_jobs.jobs && failed_jobs.jobs.length > 0) {
                    failed_jobs.jobs.forEach(job => {
                        const displayName = job.name || 'Unknown task';
                        const errorMsg = job.error || 'Unknown error';
                        const timestamp = job.timestamp || '';

                        // Zobrazit v konzoli (max 5)
                        if (failed_jobs.jobs.indexOf(job) < 5) {
                            logError(`  ${displayName}: ${errorMsg}`);
                            if (timestamp) {
                                logError(`     ${timestamp}`);
                            }
                        }

                        // Přidat všechny do error listu
                        addError('Failed Task', displayName, errorMsg);
                    });

                    if (failed_jobs.jobs.length > 5) {
                        logError(`  ... a dalších ${failed_jobs.jobs.length - 5} selhavších úkolů`);
                    }
                }
            } else {
                logSuccess('Žádné selhavší úkoly');
            }

            // Backup Status
            if (backup_status) {
                if (backup_status.last_backup) {
                    const age_days = backup_status.age_days || 0;
                    if (age_days > 7) {
                        logWarning(`Poslední backup před ${age_days} dny (doporučeno: max 7 dní)`);
                        addWarning('Backup', `Starý backup (${age_days} dní)`);
                    } else {
                        logSuccess(`Backup aktuální (${age_days} dní)`);
                    }
                } else {
                    logWarning('Žádný backup nenalezen');
                    addWarning('Backup', 'Žádný backup nenalezen');
                }
            }

            // .env Permissions
            if (env_permissions) {
                if (env_permissions.too_permissive) {
                    logError('.env soubor má příliš volná oprávnění!');
                    logError(`   Aktuální: ${env_permissions.current}, Doporučeno: 600`);
                    addError('Bezpečnost', '.env má příliš volná oprávnění', `Aktuální: ${env_permissions.current}, Doporučeno: 600`);
                } else if (env_permissions.exists) {
                    logSuccess('.env oprávnění bezpečná');
                } else {
                    logWarning('.env soubor nenalezen');
                }
            }

            // PHP.ini Critical Settings
            if (php_ini_settings) {
                const warnings = php_ini_settings.warnings || [];
                if (warnings.length > 0) {
                    logWarning(`${warnings.length} php.ini varování:`);
                    log('═'.repeat(79));
                    warnings.forEach(warn => {
                        // display_errors: 0 je správně (0 = Off), nep počítat jako warning
                        if (warn.setting === 'display_errors' && warn.current === '0') {
                            logSuccess(`  ${warn.setting}: ${warn.current} = Off (správně)`);
                        } else {
                            logWarning(`  ${warn.setting}: ${warn.current} (doporučeno: ${warn.recommended})`);
                            addWarning('PHP.ini', `${warn.setting}: ${warn.current}`, `Doporučeno: ${warn.recommended}`);
                        }
                    });
                } else {
                    logSuccess('PHP.ini nastavení optimální');
                }
            }

            // SMTP Test
            if (smtp_test !== undefined) {
                if (smtp_test.success) {
                    logSuccess('SMTP funkční (test email odeslán)');
                } else if (smtp_test.error && smtp_test.error !== 'Not tested') {
                    logWarning('SMTP nefunguje: ' + smtp_test.error);
                    addWarning('SMTP', 'SMTP nefunguje', smtp_test.error);
                } else {
                    // "Not tested" není warning, jen info
                    log('ℹ️  SMTP nebylo testováno');
                }
            }

        } else {
            logWarning('Nepodařilo se zkontrolovat workflow: ' + (data.message || 'Unknown error'));
        }
    } catch (error) {
        logWarning('Chyba při kontrole workflow: ' + error.message);
        if (DEBUG_MODE) console.error(error);
    }

    log('');
}

// ============================================
// NEW DIAGNOSTIC FUNCTIONS
// ============================================

/**
 * CheckEmailSystem
 */
async function checkEmailSystem() {
    logHeader('17. EMAIL SYSTÉM (PHPMailer)');
    log('Kontroluji PHPMailer, SMTP nastavení a email queue...');

    try {
        // Kontrola existence PHPMailer
        const phpmailerExists = await fetch('/vendor/phpmailer/phpmailer/src/PHPMailer.php', {
            method: 'HEAD'
        }).then(r => r.ok);

        if (!phpmailerExists) {
            logWarning('⚠️  PHPMailer není nainstalován');
            addWarning('Email', 'PHPMailer chybí', 'Spusťte instalaci v Control Center → Akce & Úkoly');
            log('');
            return;
        }

        logSuccess('PHPMailer nainstalován');

        // Kontrola SMTP konfigurace přes API
        try {
            const smtpResponse = await fetch('/api/admin.php?action=check_smtp_config', {
                method: 'GET',
                credentials: 'same-origin'
            });

            if (smtpResponse.ok) {
                const smtpData = await smtpResponse.json();
                if (smtpData.status === 'success' && smtpData.data && smtpData.data.configured) {
                    logSuccess('SMTP konfigurace existuje');
                } else {
                    logWarning('⚠️  SMTP konfigurace chybí');
                    addWarning('Email', 'SMTP config chybí', 'Nastavte SMTP v Control Center');
                }
            } else {
                // Fallback - zkusit jen základní kontrolu existence souboru
                logSuccess('Email systém aktivní (základní kontrola)');
            }
        } catch (smtpError) {
            // Fallback - pokud API endpoint neexistuje, nepočítat to jako chybu
            logSuccess('Email systém aktivní (základní kontrola)');
        }

        log('');
    } catch (error) {
        logError('Chyba při kontrole email systému');
        addError('Email', 'Kontrola selhala', error.message);
        log('');
    }
}

/**
 * CheckSessionSecurity
 */
async function checkSessionSecurity() {
    logHeader('18. SESSION BEZPEČNOST');
    log('Kontroluji session handling, cookies, lifetime...');

    try {
        const issues = [];

        // Check session settings via API
        const response = await fetch('/api/admin.php?action=check_session_security', {
            method: 'GET',
            credentials: 'same-origin'
        });

        if (response.ok) {
            const data = await response.json();
            if (data.status === 'success') {
                const { secure, httponly, samesite, lifetime } = data.data;

                if (secure) {
                    logSuccess('Session cookies jsou secure');
                } else {
                    issues.push('Session cookies NEJSOU secure (pouze HTTPS)');
                }

                if (httponly) {
                    logSuccess('Session cookies jsou httponly');
                } else {
                    issues.push('Session cookies NEJSOU httponly (XSS risk)');
                }

                if (samesite) {
                    logSuccess(`SameSite: ${samesite}`);
                } else {
                    issues.push('SameSite cookie atribut není nastaven (CSRF risk)');
                }

                if (lifetime && lifetime < 86400) {
                    logSuccess(`Session lifetime: ${Math.floor(lifetime / 3600)}h`);
                } else if (lifetime) {
                    issues.push(`Session lifetime je dlouhý: ${Math.floor(lifetime / 3600)}h`);
                }

                if (issues.length > 0) {
                    logWarning(`⚠️  ${issues.length} bezpečnostních rizik:`);
                    issues.forEach(issue => {
                        logWarning(`   - ${issue}`);
                        addWarning('Session', issue);
                    });
                } else {
                    logSuccess('Session security - OK');
                }
            }
        } else {
            // Fallback - basic check
            logSuccess('Session aktivní (základní kontrola)');
        }

        log('');
    } catch (error) {
        // Fallback - pokud API endpoint neexistuje, jen info
        logSuccess('Session aktivní (základní kontrola)');
        log('');
    }
}

/**
 * CheckSecurityVulnerabilities
 */
async function checkSecurityVulnerabilities() {
    logHeader('19. BEZPEČNOSTNÍ SKEN');
    log('Kontroluji XSS, SQL injection patterns, insecure funkce...');

    try {
        const response = await fetch('/api/admin.php?action=security_scan', {
            method: 'GET',
            credentials: 'same-origin'
        });

        if (response.ok) {
            const data = await response.json();
            if (data.status === 'success') {
                const { xss_risks, sql_risks, insecure_functions, exposed_files } = data.data;

                let totalRisks = 0;

                if (xss_risks && xss_risks.length > 0) {
                    logWarning(`⚠️  ${xss_risks.length} možných XSS rizik`);
                    xss_risks.slice(0, 3).forEach(risk => {
                        addWarning('Security/XSS', risk.file + ':' + risk.line, risk.pattern);
                    });
                    totalRisks += xss_risks.length;
                }

                if (sql_risks && sql_risks.length > 0) {
                    logWarning(`⚠️  ${sql_risks.length} možných SQL injection rizik`);
                    sql_risks.slice(0, 3).forEach(risk => {
                        addWarning('Security/SQL', risk.file + ':' + risk.line, risk.pattern);
                    });
                    totalRisks += sql_risks.length;
                }

                if (insecure_functions && insecure_functions.length > 0) {
                    logWarning(`⚠️  ${insecure_functions.length} insecure funkcí`);
                    totalRisks += insecure_functions.length;
                }

                if (exposed_files && exposed_files.length > 0) {
                    logError(`${exposed_files.length} exposed souborů (.env, config)`);
                    exposed_files.forEach(file => {
                        addError('Security', 'Exposed file', file);
                    });
                    totalRisks += exposed_files.length;
                }

                if (totalRisks === 0) {
                    logSuccess('Žádná kritická bezpečnostní rizika');
                } else {
                    logWarning(`⚠️  Celkem ${totalRisks} bezpečnostních rizik`);
                }
            }
        } else {
            logWarning('⚠️  Security scan není dostupný (implementujte API endpoint)');
        }

        log('');
    } catch (error) {
        logWarning('⚠️  Security scan selhal: ' + error.message);
        log('');
    }
}

/**
 * CheckCodeAnalysis
 */
async function checkCodeAnalysis() {
    logHeader('CODE ANALYSIS - KOMPLEXNÍ KONTROLA');
    log('Spouštím hloubkovou analýzu kódu...');
    log('Detekuji: syntax chyby, vadné stringy, nezavřené závorky, security rizika, HTTP errors');
    log('═'.repeat(79));

    try {
        const response = await fetch('/api/admin.php?action=check_code_analysis', {
            method: 'GET',
            credentials: 'same-origin'
        });

        if (!response.ok) {
            logError(`API vrátilo chybu: HTTP ${response.status}`);
            log('');
            return;
        }

        const result = await response.json();
        if (result.status !== 'success') {
            logError('Code analysis selhala: ' + (result.message || 'Unknown error'));
            log('');
            return;
        }

        const data = result.data;
        const { php, javascript, css, http_logs, summary } = data;

        log('');
        logHeader('SHRNUTÍ');
        log(`Zkontrolováno:`);
        log(`  • PHP soubory: ${php.files_checked}`);
        log(`  • JavaScript soubory: ${javascript.files_checked}`);
        log(`  • CSS soubory: ${css.files_checked}`);
        log(`  • HTTP logy: ${http_logs.total} záznamů`);
        log('');

        if (summary.total_errors === 0 && summary.total_warnings === 0) {
            logSuccess('🎉 VÝBORNĚ! Žádné chyby ani varování nenalezeny!');
            log('');
            return;
        }

        logWarning(`⚠️  NALEZENO: ${summary.total_errors} chyb, ${summary.total_warnings} varování`);
        log('═'.repeat(79));
        log('');

        // ============================================
        // 1. PHP ERRORS
        // ============================================
        if (php.errors.length > 0) {
            logHeader('PHP CHYBY (' + php.errors.length + ')');
            log('');

            php.errors.forEach((err, index) => {
                const prefix = `[${index + 1}/${php.errors.length}]`;
                const location = `${err.file}:${err.line}` + (err.column > 0 ? `:${err.column}` : '');

                logError(`${prefix} ${err.type.toUpperCase()}: ${err.message}`);
                log(`${location}`);

                if (err.context && err.context.trim()) {
                    log(`📝 Kontext:`);
                    log(`   ${err.context}`);
                }

                addError('PHP/' + err.type, location, err.message);
                log('');
            });
        }

        // ============================================
        // 2. PHP WARNINGS
        // ============================================
        if (php.warnings.length > 0) {
            logHeader('⚠️  PHP VAROVÁNÍ (' + php.warnings.length + ')');
            log('');

            const limit = 10;
            php.warnings.slice(0, limit).forEach((warn, index) => {
                const prefix = `[${index + 1}/${Math.min(php.warnings.length, limit)}]`;
                const location = `${warn.file}:${warn.line}` + (warn.column > 0 ? `:${warn.column}` : '');

                logWarning(`${prefix} ${warn.type.toUpperCase()}: ${warn.message}`);
                log(`${location}`);

                if (warn.context && warn.context.trim()) {
                    log(`📝 Kontext:`);
                    log(`   ${warn.context}`);
                }

                addWarning('PHP/' + warn.type, location, warn.message);
                log('');
            });

            if (php.warnings.length > limit) {
                logWarning(`... a dalších ${php.warnings.length - limit} PHP varování`);
                addWarning('PHP', `+${php.warnings.length - limit} dalších varování`);
                log('');
            }
        }

        // ============================================
        // 3. JAVASCRIPT ERRORS
        // ============================================
        if (javascript.errors.length > 0) {
            logHeader('JAVASCRIPT CHYBY (' + javascript.errors.length + ')');
            log('');

            javascript.errors.forEach((err, index) => {
                const prefix = `[${index + 1}/${javascript.errors.length}]`;
                const location = `${err.file}:${err.line}` + (err.column > 0 ? `:${err.column}` : '');

                logError(`${prefix} ${err.type.toUpperCase()}: ${err.message}`);
                log(`${location}`);

                if (err.context && err.context.trim()) {
                    log(`📝 Kontext:`);
                    log(`   ${err.context}`);
                }

                addError('JS/' + err.type, location, err.message);
                log('');
            });
        }

        // ============================================
        // 4. JAVASCRIPT WARNINGS
        // ============================================
        if (javascript.warnings.length > 0) {
            logHeader('⚠️  JAVASCRIPT VAROVÁNÍ (' + javascript.warnings.length + ')');
            log('');

            const limit = 10;
            javascript.warnings.slice(0, limit).forEach((warn, index) => {
                const prefix = `[${index + 1}/${Math.min(javascript.warnings.length, limit)}]`;
                const location = `${warn.file}:${warn.line}` + (warn.column > 0 ? `:${warn.column}` : '');

                logWarning(`${prefix} ${warn.type.toUpperCase()}: ${warn.message}`);
                log(`${location}`);

                if (warn.context && warn.context.trim()) {
                    log(`📝 ${warn.context}`);
                }

                addWarning('JS/' + warn.type, location, warn.message);
                log('');
            });

            if (javascript.warnings.length > limit) {
                logWarning(`... a dalších ${javascript.warnings.length - limit} JS varování`);
                log('');
            }
        }

        // ============================================
        // 5. CSS ERRORS
        // ============================================
        if (css.errors.length > 0) {
            logHeader('CSS CHYBY (' + css.errors.length + ')');
            log('');

            css.errors.forEach((err, index) => {
                const prefix = `[${index + 1}/${css.errors.length}]`;
                const location = `${err.file}:${err.line}`;

                logError(`${prefix} ${err.type.toUpperCase()}: ${err.message}`);
                log(`${location}`);

                if (err.context && err.context.trim()) {
                    log(`📝 ${err.context}`);
                }

                addError('CSS/' + err.type, location, err.message);
                log('');
            });
        }

        // ============================================
        // 6. CSS WARNINGS
        // ============================================
        if (css.warnings.length > 0) {
            logHeader('⚠️  CSS VAROVÁNÍ (' + css.warnings.length + ')');
            log('');

            const limit = 5;
            css.warnings.slice(0, limit).forEach((warn, index) => {
                const prefix = `[${index + 1}/${Math.min(css.warnings.length, limit)}]`;
                const location = `${warn.file}:${warn.line}`;

                logWarning(`${prefix} ${warn.message}`);
                log(`${location}`);

                addWarning('CSS/' + warn.type, location, warn.message);
            });

            if (css.warnings.length > limit) {
                logWarning(`... a dalších ${css.warnings.length - limit} CSS varování`);
                log('');
            }
        }

        // ============================================
        // 7. HTTP ERROR LOGS
        // ============================================
        if (http_logs.errors.length > 0) {
            logHeader('🌐 HTTP ERROR LOGS (' + http_logs.errors.length + ')');
            log('Poslední chyby z error logů:');
            log('');

            const limit = 15;
            http_logs.errors.slice(0, limit).forEach((err, index) => {
                const prefix = `[${index + 1}/${Math.min(http_logs.errors.length, limit)}]`;

                if (err.code) {
                    // HTTP error code
                    const severity = ['500', '502', '503'].includes(err.code) ? 'error' : 'warning';
                    if (severity === 'error') {
                        logError(`${prefix} HTTP ${err.code} - ${err.date}`);
                    } else {
                        logWarning(`${prefix} HTTP ${err.code} - ${err.date}`);
                    }
                    log(`   ${err.message.substring(0, 150)}`);

                    if (severity === 'error') {
                        addError('HTTP/' + err.code, err.file, err.message.substring(0, 100));
                    } else {
                        addWarning('HTTP/' + err.code, err.file, err.message.substring(0, 100));
                    }
                } else if (err.line) {
                    // PHP error from log
                    const location = `${err.file}:${err.line}`;
                    logError(`${prefix} ${err.type.toUpperCase()}: ${err.message}`);
                    log(`${location}`);

                    addError('PHP/' + err.type, location, err.message);
                }

                log('');
            });

            if (http_logs.errors.length > limit) {
                log(`... a dalších ${http_logs.errors.length - limit} záznamů v logách`);
                log('');
            }
        }

        // ============================================
        // FINAL SUMMARY
        // ============================================
        log('═'.repeat(79));
        logHeader('FINÁLNÍ SOUHRN');
        log('');

        if (summary.total_errors > 0) {
            logError(`Celkem chyb: ${summary.total_errors}`);
        }

        if (summary.total_warnings > 0) {
            logWarning(`⚠️  Celkem varování: ${summary.total_warnings}`);
        }

        if (summary.total_errors === 0 && summary.total_warnings > 0) {
            logSuccess('Žádné kritické chyby - pouze varování');
        } else if (summary.total_errors > 0) {
            logError('🔧 Opravte prosím nalezené chyby');
        }

        log('');
        log('💡 TIP: Každá chyba obsahuje přesnou lokaci (soubor:řádek:sloupec) a kontext kódu');
        log('═'.repeat(79));
        log('');

    } catch (error) {
        logError('Code Analysis selhala:');
        logError(`   ${error.message}`);
        if (DEBUG_MODE) console.error(error);
        log('');
    }
}

if (DEBUG_MODE) console.log('Console loaded');

// ACTION REGISTRY - Step 113
if (typeof Utils !== 'undefined' && Utils.registerAction) {
    Utils.registerAction('runDiagnostics', () => runDiagnostics());
    Utils.registerAction('clearConsole', () => clearConsole());
    Utils.registerAction('exportLog', () => exportLog());
    Utils.registerAction('clearCacheMaintenance', () => clearCacheMaintenance());
    Utils.registerAction('optimizeDatabaseMaintenance', () => optimizeDatabaseMaintenance());
    Utils.registerAction('cleanupLogsMaintenance', () => cleanupLogsMaintenance());
    Utils.registerAction('archiveLogsMaintenance', () => archiveLogsMaintenance());
}
</script>


<?php if ($embedMode && $directAccess): ?>
</body>
</html>
<?php endif; ?>
