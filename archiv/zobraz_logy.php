<?php
/**
 * Zobrazení PHP error logu
 *
 * Použití: https://www.wgs-service.cz/zobraz_logy.php
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může zobrazit logy.");
}

$pocetRadku = isset($_GET['rows']) ? (int)$_GET['rows'] : 50;

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>PHP Error Log</title>
    <style>
        body { font-family: 'Courier New', monospace;
               max-width: 1400px; margin: 20px auto; padding: 20px;
               background: #1e1e1e; color: #d4d4d4; }
        .container { background: #252526; padding: 20px; border-radius: 8px;
                     box-shadow: 0 4px 15px rgba(0,0,0,0.5); }
        h1 { color: #4ec9b0; border-bottom: 2px solid #4ec9b0;
             padding-bottom: 10px; margin-bottom: 20px; }
        .log-entry { margin: 5px 0; padding: 8px; border-radius: 4px;
                     font-size: 13px; line-height: 1.5; }
        .log-error { background: #5a1e1e; border-left: 3px solid #f48771; }
        .log-warning { background: #5a4e1e; border-left: 3px solid #dcdcaa; }
        .log-debug { background: #1e3a5a; border-left: 3px solid #4fc1ff; }
        .log-success { background: #1e5a1e; border-left: 3px solid #4ec9b0; }
        .log-info { background: #2d2d30; border-left: 3px solid #808080; }
        .highlight { background: #3e3e42; font-weight: bold; }
        .controls { margin-bottom: 20px; padding: 15px; background: #2d2d30;
                    border-radius: 6px; }
        .btn { display: inline-block; padding: 8px 16px; margin: 5px;
               background: #0e639c; color: white; text-decoration: none;
               border-radius: 4px; border: none; cursor: pointer; }
        .btn:hover { background: #1177bb; }
        .btn-clear { background: #a1260d; }
        .btn-clear:hover { background: #c72e0d; }
        input[type="number"] { padding: 8px; width: 100px; background: #3c3c3c;
                               border: 1px solid #555; color: #d4d4d4;
                               border-radius: 4px; }
        .timestamp { color: #858585; margin-right: 10px; }
        .search-box { padding: 8px; width: 300px; background: #3c3c3c;
                      border: 1px solid #555; color: #d4d4d4;
                      border-radius: 4px; margin-right: 10px; }
    </style>
    <script>
        function filterLogs() {
            const searchText = document.getElementById('searchBox').value.toLowerCase();
            const entries = document.querySelectorAll('.log-entry');

            entries.forEach(entry => {
                const text = entry.textContent.toLowerCase();
                if (text.includes(searchText)) {
                    entry.style.display = 'block';
                } else {
                    entry.style.display = 'none';
                }
            });
        }

        function autoRefresh() {
            setTimeout(() => location.reload(), 5000);
        }
    </script>
</head>
<body>
<div class='container'>";

echo "<h1>PHP Error Log - Posledních {$pocetRadku} řádků</h1>";

// Zkusit různé možné umístění log souboru
$mozneUmisteni = [
    __DIR__ . '/logs/php_errors.log',
    __DIR__ . '/logs/error.log',
    __DIR__ . '/error.log',
    '/var/log/php_errors.log',
    ini_get('error_log')
];

$logFile = null;
foreach ($mozneUmisteni as $cesta) {
    if ($cesta && file_exists($cesta)) {
        $logFile = $cesta;
        break;
    }
}

if (!$logFile) {
    echo "<div class='log-warning'>";
    echo "<strong>⚠️ Log soubor nebyl nalezen</strong><br>";
    echo "Zkoušel jsem následující umístění:<br><ul>";
    foreach ($mozneUmisteni as $cesta) {
        echo "<li><code>" . htmlspecialchars($cesta) . "</code></li>";
    }
    echo "</ul>";
    echo "<br><strong>Možná řešení:</strong><br>";
    echo "1. Vytvořte složku <code>logs/</code> v kořenovém adresáři<br>";
    echo "2. Vytvořte soubor <code>logs/php_errors.log</code> s právy 666<br>";
    echo "3. Nebo zkontrolujte <code>php.ini</code> nastavení <code>error_log</code>";
    echo "</div>";
    echo "</div></body></html>";
    exit;
}

echo "<div class='controls'>";
echo "<input type='text' id='searchBox' class='search-box' placeholder='Hledat v logu...' onkeyup='filterLogs()'>";
echo "<a href='?rows=50' class='btn'>50 řádků</a>";
echo "<a href='?rows=100' class='btn'>100 řádků</a>";
echo "<a href='?rows=200' class='btn'>200 řádků</a>";
echo "<a href='?rows=500' class='btn'>500 řádků</a>";
echo "<button onclick='location.reload()' class='btn'>Obnovit</button>";
echo "<button onclick='autoRefresh()' class='btn'>Auto-refresh (5s)</button>";
echo "</div>";

// Přečíst poslední N řádků
$lines = [];
$file = new SplFileObject($logFile, 'r');
$file->seek(PHP_INT_MAX);
$totalLines = $file->key();

$startLine = max(0, $totalLines - $pocetRadku);
$file->seek($startLine);

while (!$file->eof()) {
    $line = $file->fgets();
    if (trim($line) !== '') {
        $lines[] = $line;
    }
}

// Zobrazit logy s barevným zvýrazněním
$protokolDebugFound = false;

foreach (array_reverse($lines) as $line) {
    $class = 'log-info';

    // Detekce typu logu
    if (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
        $class = 'log-error';
    } elseif (stripos($line, 'warning') !== false) {
        $class = 'log-warning';
    } elseif (stripos($line, 'DEBUG') !== false || stripos($line, '===') !== false) {
        $class = 'log-debug';
    } elseif (stripos($line, '✅') !== false || stripos($line, 'NALEZEN') !== false) {
        $class = 'log-success';
    }

    // Zvýraznit protokol.php debug
    if (stripos($line, 'PROTOKOL.PHP DEBUG') !== false) {
        $class .= ' highlight';
        $protokolDebugFound = true;
    }

    // Zvýraznit initialReklamaceData
    if (stripos($line, 'initialReklamaceData') !== false) {
        $class .= ' highlight';
    }

    // Extrahovat timestamp
    $displayLine = htmlspecialchars($line);
    if (preg_match('/^\[(.*?)\]/', $line, $matches)) {
        $timestamp = $matches[1];
        $rest = substr($line, strlen($matches[0]));
        $displayLine = "<span class='timestamp'>[{$timestamp}]</span>" . htmlspecialchars($rest);
    }

    echo "<div class='{$class}'>{$displayLine}</div>";
}

echo "<hr style='margin: 30px 0; border: 1px solid #3e3e42;'>";

if ($protokolDebugFound) {
    echo "<div class='log-success'>";
    echo "<strong>✅ DEBUG LOGY Z PROTOKOL.PHP NALEZENY</strong><br>";
    echo "Zkontrolujte výše zvýrazněné řádky pro diagnostiku problému.";
    echo "</div>";
} else {
    echo "<div class='log-warning'>";
    echo "<strong>⚠️ DEBUG LOGY Z PROTOKOL.PHP NENALEZENY</strong><br>";
    echo "To znamená, že protokol.php nebyl otevřen s parametrem ?id=... nebo se logy nezapisují.<br>";
    echo "<br><strong>Co udělat?</strong><br>";
    echo "1. Otevřete protokol.php s parametrem: <code>protokol.php?id=WGS/2025/02-12/00003</code><br>";
    echo "2. Poté obnovte tuto stránku (tlačítko Obnovit nahoře)";
    echo "</div>";
}

echo "<div style='margin-top: 20px; padding: 15px; background: #2d2d30; border-radius: 6px;'>";
echo "<strong>Informace o log souboru:</strong><br>";
echo "Cesta: {$logFile}<br>";
echo "Velikost: " . number_format(filesize($logFile) / 1024, 2) . " KB<br>";
echo "Celkem řádků: ~{$totalLines}<br>";
echo "Zobrazeno posledních: {$pocetRadku} řádků<br>";
echo "Poslední úprava: " . date('Y-m-d H:i:s', filemtime($logFile));
echo "</div>";

echo "</div></body></html>";
?>
