<?php
/**
 * Diagnostika: Zobrazení logu CRON připomínek
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může zobrazit log.");
}

$logFile = __DIR__ . '/logs/cron_reminders.log';

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>CRON Log - Připomenutí návštěv</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            max-width: 1400px;
            margin: 20px auto;
            padding: 20px;
            background: #1e1e1e;
            color: #d4d4d4;
        }
        .container {
            background: #252526;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.3);
        }
        h1 {
            color: #4EC9B0;
            border-bottom: 2px solid #4EC9B0;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        .log-content {
            background: #1e1e1e;
            padding: 20px;
            border-radius: 5px;
            border: 1px solid #3e3e42;
            font-size: 13px;
            line-height: 1.6;
            white-space: pre-wrap;
            word-wrap: break-word;
            max-height: 600px;
            overflow-y: auto;
        }
        .success { color: #4EC9B0; }
        .error { color: #f48771; }
        .warning { color: #ce9178; }
        .info { color: #569cd6; }
        .timestamp { color: #808080; }
        .btn {
            display: inline-block;
            padding: 10px 20px;
            background: #0e639c;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 10px 5px 10px 0;
            border: none;
            cursor: pointer;
        }
        .btn:hover { background: #1177bb; }
        .btn-danger { background: #c5262d; }
        .btn-danger:hover { background: #e81123; }
        .stats {
            background: #2d2d30;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            border-left: 4px solid #4EC9B0;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .stat-item {
            background: #1e1e1e;
            padding: 10px;
            border-radius: 4px;
        }
        .stat-label {
            font-size: 11px;
            color: #858585;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 24px;
            color: #4EC9B0;
            font-weight: bold;
        }
        .no-log {
            text-align: center;
            padding: 40px;
            color: #858585;
        }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>📋 CRON Log - Připomenutí návštěv</h1>";

if (!file_exists($logFile)) {
    echo "<div class='no-log'>";
    echo "⚠️ Log soubor neexistuje<br>";
    echo "<small>Cesta: " . htmlspecialchars($logFile) . "</small><br><br>";
    echo "CRON skript ještě nikdy neběžel nebo má problém s oprávněními.";
    echo "</div>";
} else {
    $logSize = filesize($logFile);
    $lastModified = filemtime($logFile);

    // Statistiky
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", $logContent);
    $totalRuns = substr_count($logContent, '=== START:');
    $totalSent = 0;
    $totalErrors = 0;

    foreach ($lines as $line) {
        if (preg_match('/Úspěšně přidáno do fronty: (\d+)/', $line, $matches)) {
            $totalSent += (int)$matches[1];
        }
        if (preg_match('/Chyby: (\d+)/', $line, $matches)) {
            $totalErrors += (int)$matches[1];
        }
    }

    echo "<div class='stats'>";
    echo "<div class='stats-grid'>";
    echo "<div class='stat-item'>";
    echo "<div class='stat-label'>Velikost logu</div>";
    echo "<div class='stat-value'>" . number_format($logSize / 1024, 2) . " KB</div>";
    echo "</div>";
    echo "<div class='stat-item'>";
    echo "<div class='stat-label'>Poslední běh</div>";
    echo "<div class='stat-value'>" . date('d.m.Y H:i', $lastModified) . "</div>";
    echo "</div>";
    echo "<div class='stat-item'>";
    echo "<div class='stat-label'>Celkem spuštění</div>";
    echo "<div class='stat-value'>{$totalRuns}</div>";
    echo "</div>";
    echo "<div class='stat-item'>";
    echo "<div class='stat-label'>Odesláno emailů</div>";
    echo "<div class='stat-value'>{$totalSent}</div>";
    echo "</div>";
    echo "<div class='stat-item'>";
    echo "<div class='stat-label'>Chyby celkem</div>";
    echo "<div class='stat-value'>" . ($totalErrors > 0 ? "<span class='error'>{$totalErrors}</span>" : "0") . "</div>";
    echo "</div>";
    echo "</div>";
    echo "</div>";

    // Tlačítka
    echo "<div style='margin-bottom: 20px;'>";
    echo "<a href='?refresh=1' class='btn'>🔄 Obnovit</a>";
    echo "<a href='?download=1' class='btn'>💾 Stáhnout celý log</a>";
    echo "<a href='?clear=1' class='btn btn-danger' onclick='return confirm(\"Opravdu smazat celý log?\")'>🗑️ Vymazat log</a>";
    echo "<a href='admin.php' class='btn'>← Zpět do admin</a>";
    echo "</div>";

    // Zpracování akcí
    if (isset($_GET['clear'])) {
        file_put_contents($logFile, '');
        echo "<div class='success' style='padding: 15px; margin-bottom: 20px; background: #1a472a; border-radius: 5px;'>";
        echo "✓ Log byl vymazán";
        echo "</div>";
        echo "<meta http-equiv='refresh' content='2;url=zobraz_cron_log.php'>";
    }

    if (isset($_GET['download'])) {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="cron_reminders_' . date('Y-m-d_H-i-s') . '.log"');
        readfile($logFile);
        exit;
    }

    // Zobrazení logu (posledních 200 řádků)
    echo "<h2 style='color: #569cd6; margin-bottom: 15px;'>📄 Obsah logu (posledních 200 řádků)</h2>";
    echo "<div class='log-content'>";

    $lastLines = array_slice($lines, -200);

    foreach ($lastLines as $line) {
        if (empty(trim($line))) continue;

        // Zvýraznění
        $line = htmlspecialchars($line);

        // Timestamp
        $line = preg_replace('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/', '<span class="timestamp">[$1]</span>', $line);

        // Success
        if (strpos($line, '✓') !== false || strpos($line, 'ÚSPĚCH') !== false || strpos($line, 'OK:') !== false) {
            $line = '<span class="success">' . $line . '</span>';
        }
        // Errors
        elseif (strpos($line, '✗') !== false || strpos($line, 'CHYBA') !== false || strpos($line, 'ERROR') !== false) {
            $line = '<span class="error">' . $line . '</span>';
        }
        // Warnings
        elseif (strpos($line, '⚠') !== false || strpos($line, 'WARNING') !== false || strpos($line, 'Nenalezeny') !== false) {
            $line = '<span class="warning">' . $line . '</span>';
        }
        // Info
        elseif (strpos($line, 'START') !== false || strpos($line, 'KONEC') !== false || strpos($line, 'SOUHRN') !== false) {
            $line = '<span class="info">' . $line . '</span>';
        }

        echo $line . "\n";
    }

    echo "</div>";
}

echo "</div></body></html>";
?>
