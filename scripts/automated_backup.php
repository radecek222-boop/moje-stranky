<?php
/**
 * Automatické zálohy databáze
 * Vytvoří SQL dump databáze a uloží do backups/
 */

require_once __DIR__ . '/../init.php';

echo "=== AUTOMATICKÉ ZÁLOHOVÁNÍ DATABÁZE ===\n\n";

try {
    // Získat DB credentials
    $dbHost = DB_HOST;
    $dbName = DB_NAME;
    $dbUser = DB_USER;
    $dbPass = DB_PASS;

    // Vytvořit backup adresář pokud neexistuje
    $backupDir = __DIR__ . '/../backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
        echo "✅ Vytvořen adresář: {$backupDir}\n";
    }

    // Název backup souboru s timestampem
    $timestamp = date('Y-m-d_His');
    $backupFile = "{$backupDir}/database_backup_{$timestamp}.sql";

    echo "📦 Vytvářím zálohu databáze: {$dbName}\n";
    echo "📁 Výstupní soubor: " . basename($backupFile) . "\n\n";

    // Vytvořit mysqldump command
    $command = sprintf(
        "mysqldump --host=%s --user=%s %s %s > %s 2>&1",
        escapeshellarg($dbHost),
        escapeshellarg($dbUser),
        $dbPass ? '--password=' . escapeshellarg($dbPass) : '',
        escapeshellarg($dbName),
        escapeshellarg($backupFile)
    );

    // Spustit mysqldump
    exec($command, $output, $returnCode);

    if ($returnCode === 0 && file_exists($backupFile)) {
        $fileSize = filesize($backupFile);
        $fileSizeMB = round($fileSize / 1024 / 1024, 2);

        echo "✅ Záloha vytvořena úspěšně!\n";
        echo "   Soubor: " . basename($backupFile) . "\n";
        echo "   Velikost: {$fileSizeMB} MB\n\n";

        // Komprimovat soubor pomocí gzip
        echo "🗜️  Komprimuji zálohu...\n";
        exec("gzip " . escapeshellarg($backupFile), $gzipOutput, $gzipCode);

        if ($gzipCode === 0 && file_exists($backupFile . '.gz')) {
            $gzSize = filesize($backupFile . '.gz');
            $gzSizeMB = round($gzSize / 1024 / 1024, 2);
            $ratio = round((1 - ($gzSize / $fileSize)) * 100, 1);

            echo "✅ Komprese dokončena!\n";
            echo "   Soubor: " . basename($backupFile) . ".gz\n";
            echo "   Velikost: {$gzSizeMB} MB\n";
            echo "   Kompresní poměr: {$ratio}%\n\n";
        } else {
            echo "⚠️  Komprese selhala, ponecháván nekomprimovaný SQL soubor\n\n";
        }

        // Vymazat staré zálohy (starší než 30 dní)
        echo "🧹 Mažu staré zálohy (> 30 dní)...\n";
        $files = glob($backupDir . '/database_backup_*.sql*');
        $deleted = 0;

        foreach ($files as $file) {
            if (filemtime($file) < strtotime('-30 days')) {
                unlink($file);
                echo "   Smazán: " . basename($file) . "\n";
                $deleted++;
            }
        }

        if ($deleted === 0) {
            echo "   Žádné staré zálohy ke smazání\n";
        }

        echo "\n✅ BACKUP DOKONČEN!\n";

    } else {
        echo "❌ Záloha selhala!\n";
        echo "Return code: {$returnCode}\n";
        if (!empty($output)) {
            echo "Output: " . implode("\n", $output) . "\n";
        }
        exit(1);
    }

} catch (Exception $e) {
    echo "❌ KRITICKÁ CHYBA: " . $e->getMessage() . "\n";
    exit(1);
}
