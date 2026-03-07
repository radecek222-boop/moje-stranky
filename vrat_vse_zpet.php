<?php
/**
 * VRÁTIT VŠE ZPĚT - Obnova všech smazaných souborů
 *
 * Tento skript vrátí všechny soubory co jsme smazali během čištění projektu:
 * 1. PR #1194 - Smazání archiv/ složky (186 souborů)
 * 2. PR #1195 - Smazání test/migrace skriptů (57 souborů)
 * 3. První cleanup - Root soubory (69 souborů)
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit obnovu.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Vrátit vše zpět</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1200px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #d32f2f; border-bottom: 3px solid #d32f2f;
             padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px;
                 margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px;
                   margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px;
                margin: 10px 0; }
        .btn { display: inline-block; padding: 12px 24px;
               background: #d32f2f; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 16px; }
        .btn:hover { background: #b71c1c; }
        .file-list { max-height: 400px; overflow-y: auto;
                     background: #f9f9f9; padding: 15px;
                     border-radius: 5px; margin: 10px 0; }
        .file-item { padding: 5px; border-bottom: 1px solid #eee; }
    </style>
</head>
<body>
<div class='container'>";

try {
    echo "<h1>VRÁTIT VŠE ZPĚT</h1>";

    // Zkontrolovat dostupné backupy
    $backupDir = __DIR__ . '/backups';
    $archivBackup = $backupDir . '/archiv_backup_2026-01-14_22-50-10';
    $cleanupBackup = $backupDir . '/cleanup_scripts_2026-01-14_23-01-08';

    if (isset($_GET['execute']) && $_GET['execute'] === '1') {
        echo "<div class='warning'><strong>POZOR: SPOUŠTÍM OBNOVU...</strong></div>";

        $restored = 0;
        $errors = 0;

        // 1. Vrátit archiv složku (186 souborů)
        echo "<div class='info'><strong>📁 OBNOVA SLOŽKY ARCHIV/</strong></div>";

        if (is_dir($archivBackup)) {
            // Vytvořit archiv složku
            if (!is_dir(__DIR__ . '/archiv')) {
                mkdir(__DIR__ . '/archiv', 0755, true);
            }

            // Kopírovat všechny soubory z backupu
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($archivBackup, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $targetPath = __DIR__ . '/archiv/' . $iterator->getSubPathName();

                if ($item->isDir()) {
                    if (!is_dir($targetPath)) {
                        mkdir($targetPath, 0755, true);
                    }
                } else {
                    if (copy($item, $targetPath)) {
                        $restored++;
                    } else {
                        $errors++;
                    }
                }
            }

            echo "<div class='success'>OK: Obnoveno: {$restored} souborů ze složky archiv/</div>";
        } else {
            echo "<div class='error'>CHYBA: Backup archiv/ nenalezen!</div>";
        }

        // 2. Vrátit cleanup skripty (57 souborů)
        echo "<div class='info'><strong>📝 OBNOVA CLEANUP SKRIPTŮ</strong></div>";

        if (is_dir($cleanupBackup)) {
            $files = scandir($cleanupBackup);
            $restored2 = 0;

            foreach ($files as $file) {
                if ($file === '.' || $file === '..') continue;

                $source = $cleanupBackup . '/' . $file;
                $target = __DIR__ . '/' . $file;

                if (copy($source, $target)) {
                    $restored2++;
                } else {
                    $errors++;
                }
            }

            echo "<div class='success'>OK: Obnoveno: {$restored2} cleanup skriptů</div>";
        } else {
            echo "<div class='error'>CHYBA: Backup cleanup skriptů nenalezen!</div>";
        }

        // 3. Vrátit soubory z Git historie (první cleanup)
        echo "<div class='info'><strong>🔀 OBNOVA Z GIT HISTORIE</strong></div>";

        // Najít commit před prvním cleanup
        $output = [];
        $return = 0;
        exec('cd ' . escapeshellarg(__DIR__) . ' && git log --oneline --all | grep -B 1 "CLEANUP"', $output, $return);

        // Vrátit soubory z commitu před cleanupem
        $commitBeforeCleanup = '1bf5ce2'; // Commit před prvním cleanup

        exec('cd ' . escapeshellarg(__DIR__) . ' && git diff --name-only ' . escapeshellarg($commitBeforeCleanup) . '..HEAD 2>&1', $diffOutput, $diffReturn);

        if ($diffReturn === 0 && !empty($diffOutput)) {
            $gitRestored = 0;
            foreach ($diffOutput as $deletedFile) {
                // Přeskočit soubory co už existují nebo jsou ve složkách co jsme vrátili
                if (file_exists(__DIR__ . '/' . $deletedFile)) continue;
                if (strpos($deletedFile, 'archiv/') === 0) continue;

                // Vrátit soubor z git historie
                $cmd = 'cd ' . escapeshellarg(__DIR__) . ' && git checkout ' . escapeshellarg($commitBeforeCleanup) . ' -- ' . escapeshellarg($deletedFile) . ' 2>&1';
                exec($cmd, $checkoutOutput, $checkoutReturn);

                if ($checkoutReturn === 0) {
                    $gitRestored++;
                }
            }

            echo "<div class='success'>OK: Obnoveno z Gitu: {$gitRestored} souborů</div>";
        }

        // Shrnutí
        $total = $restored + $restored2 + ($gitRestored ?? 0);

        echo "<div class='success'>";
        echo "<h3>OK: OBNOVA DOKONČENA</h3>";
        echo "<p><strong>Celkem obnoveno:</strong> {$total} souborů</p>";
        if ($errors > 0) {
            echo "<p><strong>Chyby:</strong> {$errors}</p>";
        }
        echo "</div>";

        echo "<div class='warning'>";
        echo "<p><strong>POZOR: DŮLEŽITÉ:</strong> Soubory byly obnoveny, ale ještě nejsou commitnuty do Gitu.</p>";
        echo "<p>Pokud chceš zachovat změny, proveď:</p>";
        echo "<pre>git add -A\ngit commit -m \"REVERT: Vrácení všech smazaných souborů\"\ngit push</pre>";
        echo "</div>";

    } else {
        // Náhled co bude obnoveno
        echo "<div class='warning'>";
        echo "<h3>POZOR: Tato akce vrátí VŠECHNY smazané soubory!</h3>";
        echo "<p>Budou obnoveny:</p>";
        echo "<ul>";
        echo "<li><strong>Složka archiv/</strong> - 186 souborů (1.85 MB)</li>";
        echo "<li><strong>Cleanup skripty</strong> - 57 souborů</li>";
        echo "<li><strong>Root soubory</strong> - Další smazané soubory z Git historie</li>";
        echo "</ul>";
        echo "</div>";

        // Zobrazit dostupné backupy
        echo "<div class='info'>";
        echo "<h4>📦 Dostupné backupy:</h4>";

        if (is_dir($archivBackup)) {
            $size = 0;
            $count = 0;
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($archivBackup, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    $size += $item->getSize();
                    $count++;
                }
            }
            $sizeMB = round($size / 1024 / 1024, 2);
            echo "<p>OK: <strong>Archiv backup:</strong> {$count} souborů ({$sizeMB} MB)</p>";
        } else {
            echo "<p>CHYBA: <strong>Archiv backup:</strong> Nenalezen</p>";
        }

        if (is_dir($cleanupBackup)) {
            $files = array_diff(scandir($cleanupBackup), ['.', '..']);
            $count2 = count($files);
            echo "<p>OK: <strong>Cleanup backup:</strong> {$count2} souborů</p>";
        } else {
            echo "<p>CHYBA: <strong>Cleanup backup:</strong> Nenalezen</p>";
        }

        echo "</div>";

        echo "<form method='get'>";
        echo "<input type='hidden' name='execute' value='1'>";
        echo "<button type='submit' class='btn'>VRÁTIT VŠE ZPĚT</button>";
        echo "</form>";

        echo "<div class='info'>";
        echo "<p><strong>Co se stane:</strong></p>";
        echo "<ol>";
        echo "<li>Obnoví se složka <code>archiv/</code> z backupu</li>";
        echo "<li>Obnoví se všechny cleanup skripty</li>";
        echo "<li>Obnoví se smazané soubory z Git historie</li>";
        echo "<li>Soubory budou dostupné k použití</li>";
        echo "<li>Budeš muset commitnout změny do Gitu</li>";
        echo "</ol>";
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
