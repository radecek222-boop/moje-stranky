<?php
/**
 * DEBUG: Zobrazení posledních chyb z PHP logu
 * SMAZAT PO DEBUGOVÁNÍ!
 */

require_once __DIR__ . '/init.php';

// Pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html>
<head>
    <title>Debug Video Log</title>
    <style>
        body { font-family: monospace; background: #1a1a1a; color: #0f0; padding: 20px; }
        pre { background: #000; padding: 15px; border: 1px solid #333; overflow-x: auto; }
        h2 { color: #fff; }
        .error { color: #f55; }
        .video { color: #5ff; }
    </style>
</head>
<body>
<h1>Debug: Video API Log</h1>";

// Možné umístění PHP error logů
$logPaths = [
    '/var/log/php-fpm/error.log',
    '/var/log/php8.2-fpm.log',
    '/var/log/php8.1-fpm.log',
    '/var/log/php8.0-fpm.log',
    '/var/log/php/error.log',
    '/var/log/apache2/error.log',
    '/var/log/nginx/error.log',
    '/var/log/httpd/error_log',
    __DIR__ . '/logs/php_errors.log',
    __DIR__ . '/logs/error.log',
    ini_get('error_log')
];

echo "<h2>Hledám log soubory...</h2>";
echo "<pre>";

$foundLog = null;
foreach ($logPaths as $path) {
    if ($path && file_exists($path) && is_readable($path)) {
        echo "NALEZEN: $path\n";
        $foundLog = $path;
    } else {
        echo "Neexistuje: $path\n";
    }
}

echo "</pre>";

// Test zápisu do error_log
echo "<h2>Test error_log()</h2>";
$testMsg = "DEBUG_TEST_" . date('Y-m-d_H:i:s');
error_log($testMsg);
echo "<p>Zkusil jsem zapsat: <code>$testMsg</code></p>";

// Pokud máme log, zobrazit poslední řádky
if ($foundLog) {
    echo "<h2>Poslední záznamy z: $foundLog</h2>";
    echo "<pre>";

    // Přečíst posledních 100 řádků
    $lines = [];
    $fp = fopen($foundLog, 'r');
    if ($fp) {
        // Jít na konec a číst zpětně
        fseek($fp, -50000, SEEK_END); // Posledních ~50KB
        fgets($fp); // Zahodit neúplný řádek

        while (!feof($fp)) {
            $line = fgets($fp);
            if ($line !== false) {
                $lines[] = $line;
            }
        }
        fclose($fp);

        // Zobrazit posledních 100 řádků
        $lines = array_slice($lines, -100);

        foreach ($lines as $line) {
            // Zvýraznit video_api.php
            if (strpos($line, 'video_api') !== false) {
                echo "<span class='video'>" . htmlspecialchars($line) . "</span>";
            } elseif (stripos($line, 'error') !== false || stripos($line, 'fatal') !== false) {
                echo "<span class='error'>" . htmlspecialchars($line) . "</span>";
            } else {
                echo htmlspecialchars($line);
            }
        }
    }
    echo "</pre>";
} else {
    echo "<h2>Alternativní metoda: Přímý test API</h2>";
    echo "<p>Žádný standardní log nalezen. Zkuste:</p>";

    // Zkusit zavolat API a zachytit chybu
    echo "<pre>";

    // Test databázového připojení
    echo "Test DB připojení: ";
    try {
        $pdo = getDbConnection();
        echo "OK\n";

        // Test tabulky wgs_videos
        echo "Test tabulky wgs_videos: ";
        $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_videos'");
        if ($stmt->rowCount() > 0) {
            echo "EXISTUJE\n";

            // Struktura
            echo "\nStruktura tabulky:\n";
            $stmt = $pdo->query("DESCRIBE wgs_videos");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "  - {$row['Field']}: {$row['Type']}\n";
            }
        } else {
            echo "NEEXISTUJE!\n";
        }

        // Test složky uploads/videos
        echo "\nTest složky /uploads/videos/: ";
        $videosDir = __DIR__ . '/uploads/videos';
        if (is_dir($videosDir)) {
            echo "EXISTUJE";
            echo " (writable: " . (is_writable($videosDir) ? 'ano' : 'NE!') . ")\n";
        } else {
            echo "NEEXISTUJE\n";
            echo "  Vytvářím... ";
            if (mkdir($videosDir, 0755, true)) {
                echo "OK\n";
            } else {
                echo "CHYBA!\n";
            }
        }

    } catch (Exception $e) {
        echo "CHYBA: " . $e->getMessage() . "\n";
    }

    echo "</pre>";
}

echo "<h2>PHP Info</h2>";
echo "<pre>";
echo "PHP verze: " . phpversion() . "\n";
echo "error_log: " . ini_get('error_log') . "\n";
echo "log_errors: " . ini_get('log_errors') . "\n";
echo "display_errors: " . ini_get('display_errors') . "\n";
echo "upload_max_filesize: " . ini_get('upload_max_filesize') . "\n";
echo "post_max_size: " . ini_get('post_max_size') . "\n";
echo "max_execution_time: " . ini_get('max_execution_time') . "\n";
echo "</pre>";

echo "<p><a href='admin.php' style='color: #5f5;'>Zpět do Admin Panelu</a></p>";
echo "<p style='color: #f55;'><strong>NEZAPOMEŇ TENTO SOUBOR SMAZAT!</strong></p>";
echo "</body></html>";
?>
