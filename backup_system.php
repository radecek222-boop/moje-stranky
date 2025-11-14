<?php
/**
 * Simple Backup System
 * Vytvoří zálohu databáze a důležitých souborů
 */

require_once __DIR__ . '/init.php';

// Admin only
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized - Admin access required');
}

// Konfigurace
$backupDir = __DIR__ . '/backups';
$timestamp = date('Y-m-d_H-i-s');
$backupName = "wgs_backup_{$timestamp}";
$tempDir = sys_get_temp_dir() . '/' . $backupName;

// HTML header
?>
<!DOCTYPE html>
<html>
<head>
    <title>Backup System</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; padding: 2rem; background: #f5f5f7; }
        .container { max-width: 800px; margin: 0 auto; background: white; padding: 2rem; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        h1 { color: #1d1d1f; margin-top: 0; }
        .step { padding: 1rem; margin: 1rem 0; background: #f5f5f7; border-radius: 8px; }
        .success { color: #28a745; }
        .error { color: #dc3545; }
        .info { color: #667eea; }
        .btn { display: inline-block; padding: 0.75rem 1.5rem; background: #667eea; color: white; text-decoration: none; border-radius: 8px; margin-top: 1rem; }
        .btn:hover { background: #5a67d8; }
    </style>
</head>
<body>
<div class="container">
    <h1>🔄 Backup System</h1>

<?php

try {
    // Vytvořit backup adresář
    if (!file_exists($backupDir)) {
        mkdir($backupDir, 0755, true);
        echo "<div class='step success'>✓ Vytvořen backup adresář: {$backupDir}</div>";
    }

    // Vytvořit dočasný adresář
    if (!file_exists($tempDir)) {
        mkdir($tempDir, 0755, true);
    }

    echo "<div class='step info'>📦 Vytvářím backup: {$backupName}</div>";

    // 1. BACKUP DATABÁZE
    echo "<div class='step'><strong>1. Záloha databáze...</strong><br>";

    $pdo = getDbConnection();
    $dbName = DB_NAME;
    $sqlFile = $tempDir . '/database.sql';

    // Export databáze do SQL souboru
    $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
    $sql = "-- WGS Service Database Backup\n";
    $sql .= "-- Date: " . date('Y-m-d H:i:s') . "\n\n";
    $sql .= "SET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        // CREATE TABLE
        $createTable = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
        $sql .= "DROP TABLE IF EXISTS `{$table}`;\n";
        $sql .= $createTable['Create Table'] . ";\n\n";

        // INSERT DATA
        $rows = $pdo->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
        if (count($rows) > 0) {
            foreach ($rows as $row) {
                $values = array_map(function($val) use ($pdo) {
                    return $val === null ? 'NULL' : $pdo->quote($val);
                }, array_values($row));
                $sql .= "INSERT INTO `{$table}` VALUES (" . implode(', ', $values) . ");\n";
            }
            $sql .= "\n";
        }
    }

    $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
    file_put_contents($sqlFile, $sql);

    $sqlSize = filesize($sqlFile) / 1024 / 1024;
    echo "✓ Databáze exportována: " . number_format($sqlSize, 2) . " MB<br>";
    echo "✓ Tabulek: " . count($tables) . "</div>";

    // 2. BACKUP SOUBORŮ
    echo "<div class='step'><strong>2. Záloha důležitých souborů...</strong><br>";

    $filesToBackup = [
        'init.php',
        '.env',
        '.htaccess',
        'uploads/' => 'uploads',
        'logs/' => 'logs'
    ];

    foreach ($filesToBackup as $source => $dest) {
        $sourcePath = is_numeric($source) ? __DIR__ . '/' . $dest : __DIR__ . '/' . $source;
        $destPath = $tempDir . '/' . (is_numeric($source) ? $dest : $dest);

        if (file_exists($sourcePath)) {
            if (is_dir($sourcePath)) {
                // Zkopírovat adresář
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($sourcePath, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::SELF_FIRST
                );
                foreach ($files as $file) {
                    $destFile = $tempDir . '/' . substr($file, strlen(__DIR__) + 1);
                    if ($file->isDir()) {
                        if (!is_dir($destFile, 0755, true)) {
    if (!mkdir($destFile, 0755, true) && !is_dir($destFile, 0755, true)) {
        error_log('Failed to create directory: ' . $destFile, 0755, true);
    }
}
                    } else {
                        @copy($file, $destFile);
                    }
                }
                echo "✓ Zkopírován adresář: {$dest}<br>";
            } else {
                @copy($sourcePath, $destPath);
                echo "✓ Zkopírován soubor: {$dest}<br>";
            }
        }
    }
    echo "</div>";

    // 3. KOMPRIMOVAT
    echo "<div class='step'><strong>3. Komprimace...</strong><br>";

    $zipFile = $backupDir . '/' . $backupName . '.zip';
    $zip = new ZipArchive();

    if ($zip->open($zipFile, ZipArchive::CREATE) === true) {
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($files as $file) {
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($tempDir) + 1);

            if ($file->isDir()) {
                $zip->addEmptyDir($relativePath);
            } else {
                $zip->addFile($filePath, $relativePath);
            }
        }

        $zip->close();

        $zipSize = filesize($zipFile) / 1024 / 1024;
        echo "✓ Backup zkomprimován: " . number_format($zipSize, 2) . " MB<br>";
        echo "✓ Umístění: {$zipFile}</div>";
    } else {
        throw new Exception('Nepodařilo se vytvořit ZIP archiv');
    }

    // 4. VYČISTIT TEMP
    echo "<div class='step'><strong>4. Úklid...</strong><br>";

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($tempDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $file) {
        if ($file->isDir()) {
            rmdir($file->getRealPath());
        } else {
            unlink($file->getRealPath());
        }
    }
    rmdir($tempDir);

    echo "✓ Dočasné soubory vymazány</div>";

    // 5. STARÝCH BACKUPŮ (starší než 30 dní)
    $oldBackups = glob($backupDir . '/wgs_backup_*.zip');
    $deleted = 0;
    foreach ($oldBackups as $oldBackup) {
        if (filemtime($oldBackup) < strtotime('-30 days')) {
            unlink($oldBackup);
            $deleted++;
        }
    }
    if ($deleted > 0) {
        echo "<div class='step info'>🗑️ Vymazáno {$deleted} starých backupů (> 30 dní)</div>";
    }

    // HOTOVO
    echo "<div class='step success'><strong>✅ BACKUP DOKONČEN!</strong><br>";
    echo "Soubor: {$backupName}.zip<br>";
    echo "Velikost: " . number_format($zipSize, 2) . " MB<br>";
    echo "Čas: " . date('Y-m-d H:i:s') . "</div>";

    echo "<a href='/admin.php?tab=console' class='btn'>← Zpět na Developer Console</a>";

} catch (Exception $e) {
    echo "<div class='step error'><strong>❌ CHYBA:</strong><br>{$e->getMessage()}</div>";
    echo "<a href='/admin.php?tab=console' class='btn'>← Zpět na Developer Console</a>";
}

?>
</div>
</body>
</html>
