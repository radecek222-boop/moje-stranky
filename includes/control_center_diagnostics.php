<?php
/**
 * Control Center - Diagnostika
 * System health, logy, výkon
 */

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized');
}

$pdo = getDbConnection();

// === SYSTEM HEALTH CHECKS ===

// 1. Database
$dbStatus = 'healthy';
$dbMessage = 'Připojeno';
try {
    $pdo->query("SELECT 1");
} catch (Exception $e) {
    $dbStatus = 'error';
    $dbMessage = 'Chyba připojení';
}

// 2. File Permissions
$permissions = [
    'logs' => is_writable(__DIR__ . '/../logs'),
    'uploads' => is_writable(__DIR__ . '/../uploads'),
    'temp' => is_writable(__DIR__ . '/../temp')
];
$permissionsStatus = array_sum($permissions) === count($permissions) ? 'healthy' : 'warning';

// 3. PHP Version
$phpVersion = phpversion();
$phpStatus = version_compare($phpVersion, '7.4.0', '>=') ? 'healthy' : 'warning';

// 4. Required Extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'mbstring', 'json', 'gd'];
$extensions = [];
foreach ($requiredExtensions as $ext) {
    $extensions[$ext] = extension_loaded($ext);
}
$extensionsStatus = array_sum($extensions) === count($extensions) ? 'healthy' : 'error';

// 5. Disk Space
$diskFree = disk_free_space(__DIR__ . '/..');
$diskTotal = disk_total_space(__DIR__ . '/..');
$diskUsedPercent = round((($diskTotal - $diskFree) / $diskTotal) * 100, 1);
$diskStatus = $diskUsedPercent < 80 ? 'healthy' : ($diskUsedPercent < 90 ? 'warning' : 'error');

// 6. Recent Errors
$errorCount = 0;
$errorLogPath = __DIR__ . '/../logs/php_errors.log';
if (file_exists($errorLogPath)) {
    $errors = file($errorLogPath);
    $errorCount = count($errors);
}
$errorStatus = $errorCount < 50 ? 'healthy' : ($errorCount < 100 ? 'warning' : 'error');

// 7. Security Log
$securityCount = 0;
$securityLogPath = __DIR__ . '/../logs/security.log';
if (file_exists($securityLogPath)) {
    $securityLog = file($securityLogPath);
    $securityCount = count($securityLog);
}

// Overall status
$overallStatus = 'healthy';
if ($dbStatus === 'error' || $extensionsStatus === 'error' || $diskStatus === 'error') {
    $overallStatus = 'error';
} elseif ($permissionsStatus === 'warning' || $phpStatus === 'warning' || $diskStatus === 'warning') {
    $overallStatus = 'warning';
}
?>

<link rel="stylesheet" href="/assets/css/control-center.css">

<div class="control-detail active">
    <!-- Header -->
    <div class="control-detail-header">
        <button class="control-detail-back" onclick="window.location.href='admin.php?tab=control_center'">
            <span>‹</span>
            <span>Zpět</span>
        </button>
        <h2 class="control-detail-title">🏥 Diagnostika systému</h2>
    </div>

    <div class="control-detail-content">

        <!-- Overall Status -->
        <div class="cc-alert <?= $overallStatus === 'healthy' ? 'success' : ($overallStatus === 'warning' ? 'warning' : 'danger') ?>">
            <div class="cc-alert-icon">
                <?= $overallStatus === 'healthy' ? '✅' : ($overallStatus === 'warning' ? '⚠️' : '❌') ?>
            </div>
            <div class="cc-alert-content">
                <div class="cc-alert-title">
                    Stav systému: <?= $overallStatus === 'healthy' ? 'Zdravý' : ($overallStatus === 'warning' ? 'Upozornění' : 'Chyba') ?>
                </div>
                <div class="cc-alert-message">
                    <?php if ($overallStatus === 'healthy'): ?>
                        Všechny komponenty fungují správně.
                    <?php elseif ($overallStatus === 'warning'): ?>
                        Některé komponenty vyžadují pozornost.
                    <?php else: ?>
                        Kritické problémy detekované! Vyžaduje okamžitou akci.
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- SYSTEM COMPONENTS -->
        <div class="setting-group">
            <h3 class="setting-group-title">Komponenty systému</h3>

            <!-- Database -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">🗄️ Databáze</div>
                    <div class="setting-item-description"><?= $dbMessage ?></div>
                </div>
                <div class="setting-item-right">
                    <div class="control-card-status">
                        <span class="control-card-status-dot <?= $dbStatus === 'healthy' ? 'green' : 'red' ?>"></span>
                        <span><?= ucfirst($dbStatus) ?></span>
                    </div>
                </div>
            </div>

            <!-- PHP Version -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">🐘 PHP Verze</div>
                    <div class="setting-item-description">Aktuální: <?= $phpVersion ?></div>
                </div>
                <div class="setting-item-right">
                    <div class="control-card-status">
                        <span class="control-card-status-dot <?= $phpStatus === 'healthy' ? 'green' : 'yellow' ?>"></span>
                        <span><?= ucfirst($phpStatus) ?></span>
                    </div>
                </div>
            </div>

            <!-- Extensions -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">🧩 PHP Extensions</div>
                    <div class="setting-item-description">
                        <?= array_sum($extensions) ?>/<?= count($extensions) ?> nainstalováno
                    </div>
                </div>
                <div class="setting-item-right">
                    <div class="control-card-status">
                        <span class="control-card-status-dot <?= $extensionsStatus === 'healthy' ? 'green' : 'red' ?>"></span>
                        <span><?= ucfirst($extensionsStatus) ?></span>
                    </div>
                </div>
            </div>

            <!-- File Permissions -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">📁 Oprávnění souborů</div>
                    <div class="setting-item-description">
                        Logs: <?= $permissions['logs'] ? '✓' : '✗' ?> |
                        Uploads: <?= $permissions['uploads'] ? '✓' : '✗' ?> |
                        Temp: <?= $permissions['temp'] ? '✓' : '✗' ?>
                    </div>
                </div>
                <div class="setting-item-right">
                    <div class="control-card-status">
                        <span class="control-card-status-dot <?= $permissionsStatus === 'healthy' ? 'green' : 'yellow' ?>"></span>
                        <span><?= ucfirst($permissionsStatus) ?></span>
                    </div>
                </div>
            </div>

            <!-- Disk Space -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">💾 Diskový prostor</div>
                    <div class="setting-item-description">
                        Použito: <?= $diskUsedPercent ?>%
                        (<?= round(($diskTotal - $diskFree) / 1024 / 1024 / 1024, 1) ?> GB / <?= round($diskTotal / 1024 / 1024 / 1024, 1) ?> GB)
                    </div>
                </div>
                <div class="setting-item-right">
                    <div class="control-card-status">
                        <span class="control-card-status-dot <?= $diskStatus === 'healthy' ? 'green' : ($diskStatus === 'warning' ? 'yellow' : 'red') ?>"></span>
                        <span><?= ucfirst($diskStatus) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- LOGS -->
        <div class="setting-group">
            <h3 class="setting-group-title">Logy a události</h3>

            <!-- PHP Errors -->
            <div class="setting-item" onclick="viewLog('php_errors')">
                <div class="setting-item-left">
                    <div class="setting-item-label">🐛 PHP Error Log</div>
                    <div class="setting-item-description"><?= $errorCount ?> záznamů</div>
                </div>
                <div class="setting-item-right">
                    <div class="control-card-status">
                        <span class="control-card-status-dot <?= $errorStatus === 'healthy' ? 'green' : ($errorStatus === 'warning' ? 'yellow' : 'red') ?>"></span>
                        <span>Zobrazit</span>
                    </div>
                </div>
            </div>

            <!-- Security Log -->
            <div class="setting-item" onclick="viewLog('security')">
                <div class="setting-item-left">
                    <div class="setting-item-label">🔒 Security Log</div>
                    <div class="setting-item-description"><?= $securityCount ?> událostí</div>
                </div>
                <div class="setting-item-right">
                    <div class="control-card-status">
                        <span class="control-card-status-dot green"></span>
                        <span>Zobrazit</span>
                    </div>
                </div>
            </div>

            <!-- Audit Log -->
            <div class="setting-item" onclick="viewLog('audit')">
                <div class="setting-item-left">
                    <div class="setting-item-label">📋 Audit Log</div>
                    <div class="setting-item-description">Historie akcí adminů</div>
                </div>
                <div class="setting-item-right">
                    <div class="control-card-status">
                        <span class="control-card-status-dot green"></span>
                        <span>Zobrazit</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ACTIONS -->
        <div class="setting-group">
            <h3 class="setting-group-title">Údržbové akce</h3>

            <!-- Clear Cache -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">🗑️ Vymazat cache</div>
                    <div class="setting-item-description">Smazat dočasné soubory a session data</div>
                </div>
                <div class="setting-item-right">
                    <button class="cc-btn cc-btn-sm cc-btn-secondary" onclick="clearCache()">Vymazat</button>
                </div>
            </div>

            <!-- Clear Old Logs -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">📜 Archivovat staré logy</div>
                    <div class="setting-item-description">Přesunout logy starší než 90 dní</div>
                </div>
                <div class="setting-item-right">
                    <button class="cc-btn cc-btn-sm cc-btn-secondary" onclick="archiveLogs()">Archivovat</button>
                </div>
            </div>

            <!-- Database Optimize -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">⚡ Optimalizovat databázi</div>
                    <div class="setting-item-description">OPTIMIZE TABLE pro všechny tabulky</div>
                </div>
                <div class="setting-item-right">
                    <button class="cc-btn cc-btn-sm cc-btn-secondary" onclick="optimizeDatabase()">Optimalizovat</button>
                </div>
            </div>

            <!-- Health Check -->
            <div class="setting-item">
                <div class="setting-item-left">
                    <div class="setting-item-label">🔄 Obnovit health check</div>
                    <div class="setting-item-description">Znovu spustit diagnostiku</div>
                </div>
                <div class="setting-item-right">
                    <button class="cc-btn cc-btn-sm cc-btn-primary" onclick="location.reload()">Obnovit</button>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
function viewLog(logType) {
    window.open(`/admin.php?tab=tools&view_log=${logType}`, '_blank');
}

async function clearCache() {
    if (!confirm('Opravdu chcete vymazat cache? Tato akce může dočasně zpomalit systém.')) {
        return;
    }

    try {
        const response = await fetch('/api/control_center_api.php?action=clear_cache', {
            method: 'POST'
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert('✅ Cache byla úspěšně vymazána!');
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        alert('❌ Chyba: ' + error.message);
    }
}

async function archiveLogs() {
    if (!confirm('Archivovat logy starší než 90 dní?')) {
        return;
    }

    try {
        const response = await fetch('/api/control_center_api.php?action=archive_logs', {
            method: 'POST'
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert(`✅ Archivováno ${result.count} logů!`);
            location.reload();
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        alert('❌ Chyba: ' + error.message);
    }
}

async function optimizeDatabase() {
    if (!confirm('Optimalizovat databázi? Tato akce může trvat několik minut.')) {
        return;
    }

    const btn = event.target;
    const originalText = btn.textContent;
    btn.textContent = 'Optimalizuji...';
    btn.disabled = true;

    try {
        const response = await fetch('/api/control_center_api.php?action=optimize_database', {
            method: 'POST'
        });

        const result = await response.json();

        if (result.status === 'success') {
            alert(`✅ Databáze optimalizována!\n\nOptimalizováno ${result.tables_optimized} tabulek za ${result.time_ms}ms`);
        } else {
            throw new Error(result.message);
        }
    } catch (error) {
        alert('❌ Chyba: ' + error.message);
    } finally {
        btn.textContent = originalText;
        btn.disabled = false;
    }
}

console.log('✅ Diagnostics section loaded');
</script>
