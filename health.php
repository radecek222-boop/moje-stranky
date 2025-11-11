<?php
/**
 * Health Check Endpoint
 * Rychlá kontrola stavu aplikace pro monitoring nástroje
 *
 * Použití:
 *   GET /health.php
 *
 * Response:
 *   200 OK - Všechno funguje
 *   503 Service Unavailable - Něco nefunguje
 */

require_once __DIR__ . '/init.php';

header('Content-Type: application/json; charset=utf-8');

// Disable error output for health check
ini_set('display_errors', 0);

$checks = [
    'status' => 'unknown',
    'timestamp' => date('c'),
    'environment' => APP_ENV ?? 'unknown',
    'checks' => []
];

// 1. Session Check
try {
    $checks['checks']['session'] = [
        'status' => session_status() === PHP_SESSION_ACTIVE ? 'ok' : 'fail',
        'session_id' => session_status() === PHP_SESSION_ACTIVE
    ];
} catch (Exception $e) {
    $checks['checks']['session'] = [
        'status' => 'fail',
        'error' => $e->getMessage()
    ];
}

// 2. Database Check
try {
    $pdo = getDbConnection();
    $pdo->query('SELECT 1');
    $checks['checks']['database'] = [
        'status' => 'ok',
        'connected' => true
    ];
} catch (Exception $e) {
    $checks['checks']['database'] = [
        'status' => 'fail',
        'connected' => false,
        'error' => 'Database connection failed'
    ];
}

// 3. Uploads Directory Writable
$uploadsDir = __DIR__ . '/uploads';
try {
    $writable = is_dir($uploadsDir) && is_writable($uploadsDir);
    $checks['checks']['uploads'] = [
        'status' => $writable ? 'ok' : 'fail',
        'writable' => $writable,
        'path' => $uploadsDir
    ];
} catch (Exception $e) {
    $checks['checks']['uploads'] = [
        'status' => 'fail',
        'writable' => false,
        'error' => $e->getMessage()
    ];
}

// 4. Logs Directory Writable
$logsDir = LOGS_PATH ?? __DIR__ . '/logs';
try {
    $writable = is_dir($logsDir) && is_writable($logsDir);
    $checks['checks']['logs'] = [
        'status' => $writable ? 'ok' : 'fail',
        'writable' => $writable,
        'path' => $logsDir
    ];
} catch (Exception $e) {
    $checks['checks']['logs'] = [
        'status' => 'fail',
        'writable' => false,
        'error' => $e->getMessage()
    ];
}

// 5. Temp Directory Writable
$tempDir = TEMP_PATH ?? __DIR__ . '/temp';
try {
    $writable = is_dir($tempDir) && is_writable($tempDir);
    $checks['checks']['temp'] = [
        'status' => $writable ? 'ok' : 'fail',
        'writable' => $writable,
        'path' => $tempDir
    ];
} catch (Exception $e) {
    $checks['checks']['temp'] = [
        'status' => 'fail',
        'writable' => false,
        'error' => $e->getMessage()
    ];
}

// 6. PHP Version Check
try {
    $phpVersion = PHP_VERSION;
    $minVersion = '7.4.0';
    $versionOk = version_compare($phpVersion, $minVersion, '>=');

    $checks['checks']['php'] = [
        'status' => $versionOk ? 'ok' : 'warn',
        'version' => $phpVersion,
        'minimum_required' => $minVersion
    ];
} catch (Exception $e) {
    $checks['checks']['php'] = [
        'status' => 'fail',
        'error' => $e->getMessage()
    ];
}

// 7. Required Extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'curl', 'json', 'mbstring', 'fileinfo'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (!extension_loaded($ext)) {
        $missingExtensions[] = $ext;
    }
}

$checks['checks']['extensions'] = [
    'status' => empty($missingExtensions) ? 'ok' : 'fail',
    'required' => $requiredExtensions,
    'missing' => $missingExtensions
];

// 8. Disk Space Check
try {
    $diskFree = disk_free_space(__DIR__);
    $diskTotal = disk_total_space(__DIR__);
    $diskUsedPercent = round((($diskTotal - $diskFree) / $diskTotal) * 100, 2);

    $checks['checks']['disk_space'] = [
        'status' => $diskUsedPercent < 90 ? 'ok' : 'warn',
        'free_bytes' => $diskFree,
        'total_bytes' => $diskTotal,
        'used_percent' => $diskUsedPercent
    ];
} catch (Exception $e) {
    $checks['checks']['disk_space'] = [
        'status' => 'fail',
        'error' => $e->getMessage()
    ];
}

// Určení celkového stavu
$allOk = true;
$hasWarnings = false;

foreach ($checks['checks'] as $checkName => $checkResult) {
    if ($checkResult['status'] === 'fail') {
        $allOk = false;
    }
    if ($checkResult['status'] === 'warn') {
        $hasWarnings = true;
    }
}

if ($allOk) {
    $checks['status'] = $hasWarnings ? 'degraded' : 'healthy';
    http_response_code(200);
} else {
    $checks['status'] = 'unhealthy';
    http_response_code(503);
}

// Output
echo json_encode($checks, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
