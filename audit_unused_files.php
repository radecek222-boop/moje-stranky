<?php
/**
 * Audit nevyužívaných souborů v projektu
 * 
 * Tento skript:
 * 1. Projde všechny .php soubory v root složce
 * 2. Zkontroluje zda je někdo includuje/requireuje
 * 3. Kategorizuje podle důležitosti (CRITICAL, TEST, MIGRATION, OLD)
 * 4. Vygeneruje seznam kandidátů na archivaci
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit audit.");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Audit nevyužívaných souborů</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1400px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; }
        h2 { color: #555; margin-top: 2rem; border-bottom: 2px solid #ddd;
             padding-bottom: 5px; }
        h3 { color: #666; margin-top: 1.5rem; }
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
        table { width: 100%; border-collapse: collapse; margin: 20px 0; font-size: 0.9rem; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #333; color: white; font-weight: 600; position: sticky; top: 0; }
        tr:hover { background: #f5f5f5; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 4px;
                 font-size: 0.8rem; font-weight: 600; margin-right: 5px; }
        .badge-safe { background: #28a745; color: white; }
        .badge-review { background: #ffc107; color: #000; }
        .badge-critical { background: #dc3545; color: white; }
        .badge-test { background: #17a2b8; color: white; }
        .badge-migration { background: #6c757d; color: white; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px;
               font-family: monospace; font-size: 0.85rem; }
        .script-box { background: #f8f9fa; border: 1px solid #dee2e6;
                      padding: 15px; border-radius: 5px; margin: 15px 0;
                      font-family: monospace; font-size: 0.85rem;
                      white-space: pre-wrap; overflow-x: auto; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 1rem; }
        .btn:hover { background: #000; }
        .btn-download { background: #28a745; }
        .btn-download:hover { background: #218838; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                 gap: 15px; margin: 20px 0; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                     color: white; padding: 20px; border-radius: 10px;
                     text-align: center; }
        .stat-card h3 { margin: 0 0 10px 0; font-size: 0.9rem; opacity: 0.9; }
        .stat-card .number { font-size: 2.5rem; font-weight: bold; margin: 0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    echo "<h1>🧹 Audit nevyužívaných souborů</h1>";

    echo "<div class='info'>";
    echo "<strong>Co tento audit dělá:</strong><br>";
    echo "1. Skenuje všechny .php soubory v root složce<br>";
    echo "2. Kontroluje zda je někdo includuje/requireuje<br>";
    echo "3. Kategorizuje podle typu (TEST, MIGRATION, OLD, CRITICAL)<br>";
    echo "4. Generuje bezpečný archivační skript<br>";
    echo "</div>";

    $projectRoot = __DIR__;

    // KROK 1: Najít všechny PHP soubory v root
    echo "<h2>1. Skenování PHP souborů v root složce</h2>";

    $rootPhpFiles = glob($projectRoot . '/*.php');
    $rootPhpFiles = array_filter($rootPhpFiles, function($file) {
        return !is_dir($file);
    });

    echo "<div class='info'>";
    echo "Nalezeno <strong>" . count($rootPhpFiles) . "</strong> PHP souborů v root složce.";
    echo "</div>";

    // KRITICKÉ SOUBORY - NIKDY NESMAZAT
    $criticalFiles = [
        'init.php',
        'index.php',
        'login.php',
        'registration.php',
        'novareklamace.php',
        'seznam.php',
        'protokol.php',
        'admin.php',
        'statistiky.php',
        'cenik.php',
        'logout.php',
    ];

    // Kategorizace souborů
    $categories = [
        'CRITICAL' => [],
        'TEST' => [],
        'MIGRATION' => [],
        'DIAGNOSTIC' => [],
        'TABLE_VIEWER' => [],
        'OLD_BACKUP' => [],
        'UNKNOWN' => []
    ];

    foreach ($rootPhpFiles as $filePath) {
        $fileName = basename($filePath);

        // Kritické
        if (in_array($fileName, $criticalFiles)) {
            $categories['CRITICAL'][] = $fileName;
            continue;
        }

        // Testovací
        if (preg_match('/^test_/', $fileName)) {
            $categories['TEST'][] = $fileName;
            continue;
        }

        // Migrační
        if (preg_match('/^(pridej|kontrola|migrace|vycisti)_/', $fileName)) {
            $categories['MIGRATION'][] = $fileName;
            continue;
        }

        // Diagnostické
        if (preg_match('/^(diagnose|system_check|debug)/', $fileName)) {
            $categories['DIAGNOSTIC'][] = $fileName;
            continue;
        }

        // Table viewer skripty
        if (preg_match('/(vsechny_tabulky|show_table|struktura)/', $fileName)) {
            $categories['TABLE_VIEWER'][] = $fileName;
            continue;
        }

        // Staré/backup soubory
        if (preg_match('/_(old|backup|v2|copy|temp)\.php$/', $fileName)) {
            $categories['OLD_BACKUP'][] = $fileName;
            continue;
        }

        // Neznámé
        $categories['UNKNOWN'][] = $fileName;
    }

    // KROK 2: Statistiky
    echo "<h2>2. Statistiky podle kategorií</h2>";

    echo "<div class='stats'>";
    echo "<div class='stat-card' style='background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);'>";
    echo "<h3>CRITICAL</h3>";
    echo "<p class='number'>" . count($categories['CRITICAL']) . "</p>";
    echo "</div>";

    echo "<div class='stat-card' style='background: linear-gradient(135deg, #17a2b8 0%, #117a8b 100%);'>";
    echo "<h3>TEST</h3>";
    echo "<p class='number'>" . count($categories['TEST']) . "</p>";
    echo "</div>";

    echo "<div class='stat-card' style='background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);'>";
    echo "<h3>MIGRATION</h3>";
    echo "<p class='number'>" . count($categories['MIGRATION']) . "</p>";
    echo "</div>";

    echo "<div class='stat-card' style='background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);'>";
    echo "<h3>DIAGNOSTIC</h3>";
    echo "<p class='number'>" . count($categories['DIAGNOSTIC']) . "</p>";
    echo "</div>";

    echo "<div class='stat-card' style='background: linear-gradient(135deg, #28a745 0%, #218838 100%);'>";
    echo "<h3>TABLE VIEWER</h3>";
    echo "<p class='number'>" . count($categories['TABLE_VIEWER']) . "</p>";
    echo "</div>";

    echo "<div class='stat-card' style='background: linear-gradient(135deg, #fd7e14 0%, #e8590c 100%);'>";
    echo "<h3>OLD/BACKUP</h3>";
    echo "<p class='number'>" . count($categories['OLD_BACKUP']) . "</p>";
    echo "</div>";
    echo "</div>";

    // KROK 3: Detailní tabulky
    echo "<h2>3. Detailní analýza souborů</h2>";

    // CRITICAL - nezobrazovat, jen pro info
    echo "<h3>❌ CRITICAL soubory (NIKDY NESMAZAT)</h3>";
    echo "<div class='success'>";
    echo "Těchto <strong>" . count($categories['CRITICAL']) . "</strong> souborů je nezbytných pro fungování aplikace.";
    echo "</div>";

    // TEST soubory
    if (!empty($categories['TEST'])) {
        echo "<h3>🧪 TEST soubory (BEZPEČNĚ ARCHIVOVATELNÉ)</h3>";
        echo "<table>";
        echo "<tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['TEST'] as $file) {
            echo "<tr>";
            echo "<td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-safe'>BEZPEČNÉ</span> Po ověření funkčnosti lze archivovat</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // MIGRATION soubory
    if (!empty($categories['MIGRATION'])) {
        echo "<h3>📦 MIGRATION soubory (ARCHIVOVAT PO SPUŠTĚNÍ)</h3>";
        echo "<table>";
        echo "<tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['MIGRATION'] as $file) {
            echo "<tr>";
            echo "<td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-migration'>ARCHIVOVAT</span> Pokud už migrace proběhla, lze archivovat</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // DIAGNOSTIC soubory
    if (!empty($categories['DIAGNOSTIC'])) {
        echo "<h3>🔍 DIAGNOSTIC soubory (VOLITELNÉ)</h3>";
        echo "<table>";
        echo "<tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['DIAGNOSTIC'] as $file) {
            echo "<tr>";
            echo "<td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-review'>ZKONTROLOVAT</span> Pokud nepoužíváš, lze archivovat</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // TABLE VIEWER soubory
    if (!empty($categories['TABLE_VIEWER'])) {
        echo "<h3>📊 TABLE VIEWER soubory (NAHRAZENO SQL KARTOU)</h3>";
        echo "<table>";
        echo "<tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['TABLE_VIEWER'] as $file) {
            echo "<tr>";
            echo "<td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-safe'>BEZPEČNÉ</span> Funkce existuje v SQL kartě admin panelu</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // OLD/BACKUP soubory
    if (!empty($categories['OLD_BACKUP'])) {
        echo "<h3>🗑️ OLD/BACKUP soubory (PRAVDĚPODOBNĚ ZBYTEČNÉ)</h3>";
        echo "<table>";
        echo "<tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['OLD_BACKUP'] as $file) {
            echo "<tr>";
            echo "<td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-safe'>BEZPEČNÉ</span> Staré backup soubory</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // UNKNOWN soubory - vyžaduje manuální kontrolu
    if (!empty($categories['UNKNOWN'])) {
        echo "<h3>❓ UNKNOWN soubory (VYŽADUJE MANUÁLNÍ KONTROLU)</h3>";
        echo "<div class='warning'>";
        echo "<strong>Varování:</strong> Tyto soubory nebyly automaticky kategorizovány. ";
        echo "Zkontroluj manuálně zda jsou používané.";
        echo "</div>";
        echo "<table>";
        echo "<tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['UNKNOWN'] as $file) {
            echo "<tr>";
            echo "<td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-review'>ZKONTROLOVAT</span> Manuální kontrola nutná</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // KROK 4: Generování archivačního skriptu
    echo "<h2>4. Archivační skript</h2>";

    $archiveScript = "#!/bin/bash\n";
    $archiveScript .= "# Archivační skript - vygenerováno " . date('Y-m-d H:i:s') . "\n";
    $archiveScript .= "# PŘED SPUŠTĚNÍM: Zkontroluj seznam souborů!\n\n";
    $archiveScript .= "# Vytvoření archivních složek\n";
    $archiveScript .= "mkdir -p _archive/test-scripts\n";
    $archiveScript .= "mkdir -p _archive/migrations\n";
    $archiveScript .= "mkdir -p _archive/diagnostic\n";
    $archiveScript .= "mkdir -p _archive/table-viewers\n";
    $archiveScript .= "mkdir -p _archive/old-backups\n\n";

    $totalToArchive = 0;

    if (!empty($categories['TEST'])) {
        $archiveScript .= "# TEST soubory\n";
        foreach ($categories['TEST'] as $file) {
            $archiveScript .= "git mv {$file} _archive/test-scripts/\n";
            $totalToArchive++;
        }
        $archiveScript .= "\n";
    }

    if (!empty($categories['MIGRATION'])) {
        $archiveScript .= "# MIGRATION soubory\n";
        foreach ($categories['MIGRATION'] as $file) {
            $archiveScript .= "git mv {$file} _archive/migrations/\n";
            $totalToArchive++;
        }
        $archiveScript .= "\n";
    }

    if (!empty($categories['DIAGNOSTIC'])) {
        $archiveScript .= "# DIAGNOSTIC soubory\n";
        foreach ($categories['DIAGNOSTIC'] as $file) {
            $archiveScript .= "git mv {$file} _archive/diagnostic/\n";
            $totalToArchive++;
        }
        $archiveScript .= "\n";
    }

    if (!empty($categories['TABLE_VIEWER'])) {
        $archiveScript .= "# TABLE VIEWER soubory\n";
        foreach ($categories['TABLE_VIEWER'] as $file) {
            $archiveScript .= "git mv {$file} _archive/table-viewers/\n";
            $totalToArchive++;
        }
        $archiveScript .= "\n";
    }

    if (!empty($categories['OLD_BACKUP'])) {
        $archiveScript .= "# OLD/BACKUP soubory\n";
        foreach ($categories['OLD_BACKUP'] as $file) {
            $archiveScript .= "git mv {$file} _archive/old-backups/\n";
            $totalToArchive++;
        }
        $archiveScript .= "\n";
    }

    $archiveScript .= "# Commit archivace\n";
    $archiveScript .= "git add _archive/\n";
    $archiveScript .= "git commit -m \"CLEANUP: Archivovat {$totalToArchive} nevyužívaných souborů\n\n";
    $archiveScript .= "Archivované kategorie:\n";
    $archiveScript .= "- TEST: " . count($categories['TEST']) . " souborů\n";
    $archiveScript .= "- MIGRATION: " . count($categories['MIGRATION']) . " souborů\n";
    $archiveScript .= "- DIAGNOSTIC: " . count($categories['DIAGNOSTIC']) . " souborů\n";
    $archiveScript .= "- TABLE_VIEWER: " . count($categories['TABLE_VIEWER']) . " souborů\n";
    $archiveScript .= "- OLD_BACKUP: " . count($categories['OLD_BACKUP']) . " souborů\n";
    $archiveScript .= "\"\n\n";
    $archiveScript .= "echo \"✅ Archivace dokončena. Testuj 7 dní, pak spusť cleanup_archive.sh\"\n";

    echo "<div class='info'>";
    echo "<strong>Celkem k archivaci:</strong> {$totalToArchive} souborů<br>";
    echo "<strong>Doporučený postup:</strong><br>";
    echo "1. Zkopíruj skript níže do <code>archive_files.sh</code><br>";
    echo "2. Zkontroluj seznam souborů<br>";
    echo "3. Spusť: <code>bash archive_files.sh</code><br>";
    echo "4. Testuj aplikaci 7 dní<br>";
    echo "5. Pokud vše funguje, smaž <code>_archive/</code>";
    echo "</div>";

    echo "<div class='script-box'>" . htmlspecialchars($archiveScript) . "</div>";

    // Tlačítko pro stažení skriptu
    echo "<form method='post' action='?download=1' style='display: inline;'>";
    echo "<button type='submit' class='btn btn-download'>📥 Stáhnout archivační skript</button>";
    echo "</form>";

    // Generování cleanup skriptu (pro smazání _archive/ po testování)
    $cleanupScript = "#!/bin/bash\n";
    $cleanupScript .= "# Cleanup skript - smazání archivu po ověření\n";
    $cleanupScript .= "# SPUSŤ PO 7 DNECH TESTOVÁNÍ!\n\n";
    $cleanupScript .= "if [ ! -d \"_archive\" ]; then\n";
    $cleanupScript .= "    echo \"Složka _archive/ neexistuje.\"\n";
    $cleanupScript .= "    exit 1\n";
    $cleanupScript .= "fi\n\n";
    $cleanupScript .= "echo \"Opravdu chcete SMAZAT složku _archive/? (ano/ne)\"\n";
    $cleanupScript .= "read odpoved\n\n";
    $cleanupScript .= "if [ \"\$odpoved\" = \"ano\" ]; then\n";
    $cleanupScript .= "    git rm -rf _archive/\n";
    $cleanupScript .= "    git commit -m \"CLEANUP: Smazat archivované soubory po ověření\"\n";
    $cleanupScript .= "    echo \"✅ Archiv smazán a commitnut.\"\n";
    $cleanupScript .= "else\n";
    $cleanupScript .= "    echo \"Cleanup zrušen.\"\n";
    $cleanupScript .= "fi\n";

    echo "<h3>Cleanup skript (použij po 7 dnech)</h3>";
    echo "<div class='script-box'>" . htmlspecialchars($cleanupScript) . "</div>";

    // Download logika
    if (isset($_GET['download']) && $_GET['download'] === '1') {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="archive_files.sh"');
        echo $archiveScript;
        exit;
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='/admin.php' class='btn'>Zpět do admin</a>";
echo "</div></body></html>";
?>
