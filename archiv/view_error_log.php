<?php
/**
 * Error Log Viewer
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("Pouze pro admins");
}

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Error Log Viewer</title>";
echo "<style>body{font-family:monospace;padding:20px;background:#1e1e1e;color:#d4d4d4;} pre{background:#2d2d2d;padding:15px;border-radius:5px;overflow-x:auto;} .realtime{background:#004d00;color:#0f0;} h1{color:#4ec9b0;}</style>";
echo "</head><body>";
echo "<h1>PHP Error Log - Last 100 Lines</h1>";
echo "<p>Filtering for REALTIME API entries...</p>";

// Najít PHP error log
$possibleLogs = [
    '/var/log/php_errors.log',
    '/var/log/php/error.log',
    __DIR__ . '/logs/php_errors.log',
    ini_get('error_log')
];

$logFile = null;
foreach ($possibleLogs as $log) {
    if ($log && file_exists($log) && is_readable($log)) {
        $logFile = $log;
        break;
    }
}

if (!$logFile) {
    echo "<p>Error log not found. Searched:</p><pre>" . print_r($possibleLogs, true) . "</pre>";
} else {
    echo "<p><strong>Log file:</strong> " . htmlspecialchars($logFile) . "</p>";

    // Načíst posledních 100 řádků
    $lines = [];
    $file = new SplFileObject($logFile);
    $file->seek(PHP_INT_MAX);
    $lastLine = $file->key();
    $startLine = max(0, $lastLine - 100);

    $file->seek($startLine);
    while (!$file->eof()) {
        $line = $file->current();
        if (stripos($line, 'REALTIME') !== false || stripos($line, 'analytics_realtime') !== false) {
            $lines[] = "<div class='realtime'>" . htmlspecialchars($line) . "</div>";
        } else if (!empty(trim($line))) {
            $lines[] = htmlspecialchars($line);
        }
        $file->next();
    }

    echo "<h2>Last " . count($lines) . " log entries (REALTIME highlighted):</h2>";
    echo "<pre>" . implode("\n", array_reverse(array_slice($lines, -100))) . "</pre>";
}

echo "<p><a href='analytics-realtime.php' style='color:#4ec9b0;'>← Back to Dashboard</a></p>";
echo "</body></html>";
?>
