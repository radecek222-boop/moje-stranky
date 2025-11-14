<?php
/**
 * Cleanup Script - Smaže staré logy a spustí první backup
 *
 * Použití: Spustit jednou přes webový prohlížeč
 * Bezpečnost: Admin přístup vyžadován
 */

require_once 'init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// SECURITY FIX: Konzistentní admin check (používá is_admin jako všechny ostatní soubory)
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('<h1>🔒 Access Denied</h1><p>Admin login required.</p>');
}

// SECURITY FIX: CSRF ochrana pro POST operace
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        die('<h1>🔒 CSRF Protection</h1><p>Invalid or missing CSRF token.</p>');
    }
}

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <title>Cleanup & Backup | WGS Service</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #1a1a1a; color: #0f0; }
        .success { color: #0f0; }
        .error { color: #f00; }
        .warning { color: #ff0; }
        pre { background: #000; padding: 10px; border: 1px solid #333; }
    </style>
</head>
<body>
<h1>🧹 WGS Service - Cleanup & Backup</h1>
<pre>
<?php

$results = [];

// ═══════════════════════════════════════════════════════════════════
// 1. ČIŠTĚNÍ STARÝCH LOGŮ
// ═══════════════════════════════════════════════════════════════════

echo "🧹 ČIŠTĚNÍ STARÝCH LOGŮ\n";
echo str_repeat("═", 70) . "\n\n";

$logsDir = __DIR__ . '/logs';
$deletedFiles = [];
$deletedSize = 0;

// Smazat .gz soubory
$gzFiles = glob($logsDir . '/*.gz');
foreach ($gzFiles as $file) {
    $size = filesize($file);
    if (unlink($file)) {
        $deletedFiles[] = basename($file);
        $deletedSize += $size;
    }
}

// Smazat .20*.log soubory (archivované)
$archivedLogs = glob($logsDir . '/*.20*.log');
foreach ($archivedLogs as $file) {
    if (basename($file) === 'php_errors.log') continue; // Nepřeskakovat hlavní log
    $size = filesize($file);
    if (unlink($file)) {
        $deletedFiles[] = basename($file);
        $deletedSize += $size;
    }
}

echo "✅ Smazáno souborů: " . count($deletedFiles) . "\n";
echo "💾 Uvolněno místa: " . number_format($deletedSize / 1024, 2) . " KB\n";
if (!empty($deletedFiles)) {
    echo "📄 Soubory: " . implode(', ', array_slice($deletedFiles, 0, 5));
    if (count($deletedFiles) > 5) {
        echo " ... a " . (count($deletedFiles) - 5) . " dalších";
    }
    echo "\n";
}
echo "\n";

// ═══════════════════════════════════════════════════════════════════
// 2. ZKRÁCENÍ php_errors.log
// ═══════════════════════════════════════════════════════════════════

echo "✂️  ZKRÁCENÍ php_errors.log\n";
echo str_repeat("═", 70) . "\n\n";

$errorLog = $logsDir . '/php_errors.log';
if (file_exists($errorLog)) {
    $originalSize = filesize($errorLog);
    $lines = file($errorLog, FILE_IGNORE_NEW_LINES);
    $totalLines = count($lines);

    if ($totalLines > 100) {
        $last100 = array_slice($lines, -100);
        file_put_contents($errorLog, implode("\n", $last100) . "\n");
        $newSize = filesize($errorLog);

        echo "✅ Původní velikost: " . number_format($originalSize / 1024, 2) . " KB ({$totalLines} řádků)\n";
        echo "✅ Nová velikost: " . number_format($newSize / 1024, 2) . " KB (100 řádků)\n";
        echo "💾 Ušetřeno: " . number_format(($originalSize - $newSize) / 1024, 2) . " KB\n";
    } else {
        echo "ℹ️  Log je již malý ({$totalLines} řádků) - nezkracuji\n";
    }
} else {
    echo "⚠️  php_errors.log neexistuje\n";
}
echo "\n";

// ═══════════════════════════════════════════════════════════════════
// 3. VYČIŠTĚNÍ CACHE
// ═══════════════════════════════════════════════════════════════════

echo "💾 VYČIŠTĚNÍ CACHE\n";
echo str_repeat("═", 70) . "\n\n";

$cacheDir = __DIR__ . '/temp/cache';
if (is_dir($cacheDir)) {
    $cacheFiles = glob($cacheDir . '/*');
    $deletedCache = 0;
    foreach ($cacheFiles as $file) {
        if (is_file($file) && unlink($file)) {
            $deletedCache++;
        }
    }
    echo "✅ Vymazáno cache souborů: {$deletedCache}\n";
} else {
    echo "ℹ️  Cache adresář neexistuje\n";
}
echo "\n";

// ═══════════════════════════════════════════════════════════════════
// 4. VYTVOŘENÍ BACKUP ADRESÁŘŮ
// ═══════════════════════════════════════════════════════════════════

echo "📁 VYTVOŘENÍ BACKUP ADRESÁŘŮ\n";
echo str_repeat("═", 70) . "\n\n";

$backupDirs = [
    __DIR__ . '/backups',
    __DIR__ . '/backups/daily',
    __DIR__ . '/backups/weekly',
    __DIR__ . '/backups/monthly'
];

foreach ($backupDirs as $dir) {
    if (!is_dir($dir)) {
        if (mkdir($dir, 0755, true)) {
            echo "✅ Vytvořen: " . basename(dirname($dir)) . '/' . basename($dir) . "\n";
        } else {
            echo "❌ Chyba při vytváření: " . basename($dir) . "\n";
        }
    } else {
        echo "ℹ️  Již existuje: " . basename($dir) . "\n";
    }
}
echo "\n";

// ═══════════════════════════════════════════════════════════════════
// 5. SPUŠTĚNÍ PRVNÍHO BACKUPU
// ═══════════════════════════════════════════════════════════════════

echo "📦 SPUŠTĚNÍ PRVNÍHO BACKUPU\n";
echo str_repeat("═", 70) . "\n\n";

$backupScript = __DIR__ . '/scripts/backup-database.sh';
$dailyBackups = glob(__DIR__ . '/backups/daily/*.sql.gz');

if (empty($dailyBackups)) {
    echo "ℹ️  Žádný backup dosud neexistuje, spouštím...\n\n";

    if (file_exists($backupScript)) {
        // Nastavit oprávnění
        chmod($backupScript, 0755);

        // Spustit backup
        // SECURITY FIX: Escapovat shell argument proti command injection
        $output = [];
        $returnCode = 0;
        exec('bash ' . escapeshellarg($backupScript) . ' 2>&1', $output, $returnCode);

        if ($returnCode === 0) {
            echo "✅ Backup úspěšně vytvořen!\n";
            echo "📄 Výstup:\n";
            echo implode("\n", array_slice($output, -10)) . "\n";

            // Zkontrolovat zda backup existuje
            $dailyBackups = glob(__DIR__ . '/backups/daily/*.sql.gz');
            if (!empty($dailyBackups)) {
                $backupFile = end($dailyBackups);
                $backupSize = filesize($backupFile);
                echo "\n✅ Backup soubor: " . basename($backupFile) . "\n";
                echo "💾 Velikost: " . number_format($backupSize / 1024, 2) . " KB\n";
            }
        } else {
            echo "❌ Backup selhal (exit code: {$returnCode})\n";
            echo "📄 Chyba:\n";
            echo implode("\n", $output) . "\n";
            echo "\n⚠️  Zkontrolujte .env konfiguraci (DB_HOST, DB_NAME, DB_USER, DB_PASS)\n";
        }
    } else {
        echo "❌ Backup script nenalezen: {$backupScript}\n";
    }
} else {
    echo "ℹ️  Backup již existuje: " . basename(end($dailyBackups)) . "\n";
    echo "💾 Velikost: " . number_format(filesize(end($dailyBackups)) / 1024, 2) . " KB\n";
}
echo "\n";

// ═══════════════════════════════════════════════════════════════════
// ZÁVĚR
// ═══════════════════════════════════════════════════════════════════

echo str_repeat("═", 70) . "\n";
echo "✅ CLEANUP DOKONČEN\n";
echo str_repeat("═", 70) . "\n";
echo "\n";
echo "📊 Zkontrolujte diagnostiku - chyby by měly být vyřešeny!\n";
echo "🔗 <a href='/admin.php' style='color: #0ff;'>Zpět do Admin</a>\n";

?>
</pre>
</body>
</html>
