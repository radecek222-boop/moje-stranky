<?php
/**
 * Audit nevyužívaných souborů v2 - VYLEPŠENÁ VERZE
 * 
 * Vylepšení oproti v1:
 * - Lepší kategorizace UNKNOWN souborů
 * - Automatická detekce landing pages, PWA, fix skriptů
 * - Detekce špatně umístěných API souborů
 * - Rozšířené doporučení pro archivaci
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
    <title>Audit v2 - Vylepšená verze</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1600px; margin: 30px auto; padding: 20px;
               background: #f5f5f5; font-size: 14px; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333;
             padding-bottom: 10px; font-size: 1.8rem; }
        h2 { color: #555; margin-top: 2rem; border-bottom: 2px solid #ddd;
             padding-bottom: 5px; font-size: 1.4rem; }
        h3 { color: #666; margin-top: 1.5rem; font-size: 1.1rem; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 10px; border-radius: 5px;
                   margin: 10px 0; font-size: 0.9rem; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 10px; border-radius: 5px;
                 margin: 10px 0; font-size: 0.9rem; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 10px; border-radius: 5px;
                   margin: 10px 0; font-size: 0.9rem; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 10px; border-radius: 5px;
                margin: 10px 0; font-size: 0.9rem; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.85rem; }
        th, td { padding: 8px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #333; color: white; font-weight: 600; position: sticky; top: 0; }
        tr:hover { background: #f9f9f9; }
        .badge { display: inline-block; padding: 3px 7px; border-radius: 3px;
                 font-size: 0.75rem; font-weight: 600; margin-right: 4px; }
        .badge-safe { background: #28a745; color: white; }
        .badge-review { background: #ffc107; color: #000; }
        .badge-critical { background: #dc3545; color: white; }
        .badge-keep { background: #17a2b8; color: white; }
        code { background: #f8f9fa; padding: 2px 5px; border-radius: 3px;
               font-family: 'Courier New', monospace; font-size: 0.8rem; }
        .script-box { background: #f8f9fa; border: 1px solid #dee2e6;
                      padding: 12px; border-radius: 5px; margin: 12px 0;
                      font-family: 'Courier New', monospace; font-size: 0.75rem;
                      white-space: pre-wrap; overflow-x: auto; max-height: 500px;
                      overflow-y: auto; }
        .btn { display: inline-block; padding: 8px 16px;
               background: #333; color: white; text-decoration: none;
               border-radius: 5px; margin: 8px 4px 8px 0; border: none;
               cursor: pointer; font-size: 0.9rem; }
        .btn:hover { background: #000; }
        .btn-download { background: #28a745; }
        .btn-download:hover { background: #218838; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
                 gap: 10px; margin: 15px 0; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                     color: white; padding: 12px; border-radius: 8px;
                     text-align: center; }
        .stat-card h3 { margin: 0 0 6px 0; font-size: 0.75rem; opacity: 0.9;
                        text-transform: uppercase; }
        .stat-card .number { font-size: 1.8rem; font-weight: bold; margin: 0; }
        .category-section { margin-bottom: 2rem; }
        .collapsible { cursor: pointer; user-select: none; }
        .collapsible:hover { background: #f0f0f0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    echo "<h1>🧹 Audit nevyužívaných souborů v2 - VYLEPŠENÁ VERZE</h1>";

    echo "<div class='info'>";
    echo "<strong>Nové v2:</strong><br>";
    echo "✨ Automatická detekce landing pages (aktuality.php, onas.php...)<br>";
    echo "✨ Detekce PWA souborů (sw.php, pwa-splash.php...)<br>";
    echo "✨ Kategorizace fix skriptů (oprav_*.php, check_*.php...)<br>";
    echo "✨ Detekce email operací (odeslat_*.php, vlozit_*.php...)<br>";
    echo "✨ Identifikace špatně umístěných API souborů";
    echo "</div>";

    $projectRoot = __DIR__;
    $rootPhpFiles = glob($projectRoot . '/*.php');
    $rootPhpFiles = array_filter($rootPhpFiles, function($file) {
        return !is_dir($file);
    });

    echo "<h2>1. Skenování PHP souborů v root složce</h2>";
    echo "<div class='info'>Nalezeno <strong>" . count($rootPhpFiles) . "</strong> PHP souborů.</div>";

    // DEFINICE KATEGORIÍ
    $criticalFiles = [
        'init.php', 'index.php', 'login.php', 'registration.php',
        'novareklamace.php', 'seznam.php', 'protokol.php', 'admin.php',
        'statistiky.php', 'cenik.php', 'logout.php'
    ];

    $landingPages = [
        'aktuality.php', 'onas.php', 'nasesluzby.php', 'podminky.php',
        'gdpr.php', 'gdpr-zadost.php', 'cenova-nabidka.php',
        'oprava-kresla.php', 'oprava-sedacky.php', 'servis-natuzzi.php',
        'transport.php', 'psa-kalkulator.php', 'qr-kontakt.php',
        'neuznana-reklamace.php', 'potvrzeni-nabidky.php', 'mimozarucniceny.php'
    ];

    $pwaFiles = [
        'sw.php', 'pwa-splash.php', 'pwa-splash-test.php',
        'pwa-emergency-splash.php', 'hry.php'
    ];

    $criticalUtility = [
        'health.php', 'password_reset.php', 'analytics.php'
    ];

    // KATEGORIZACE
    $categories = [
        'CRITICAL' => [],
        'LANDING_PAGES' => [],
        'PWA' => [],
        'CRITICAL_UTILITY' => [],
        'TEST' => [],
        'MIGRATION' => [],
        'DIAGNOSTIC' => [],
        'FIX_SCRIPTS' => [],
        'EMAIL_OPERATIONS' => [],
        'ADMIN_TOOLS' => [],
        'CLEANUP_SCRIPTS' => [],
        'TABLE_VIEWER' => [],
        'MISPLACED_API' => [],
        'OLD_BACKUP' => [],
        'UNKNOWN' => []
    ];

    foreach ($rootPhpFiles as $filePath) {
        $fileName = basename($filePath);

        // CRITICAL
        if (in_array($fileName, $criticalFiles)) {
            $categories['CRITICAL'][] = $fileName;
            continue;
        }

        // LANDING PAGES
        if (in_array($fileName, $landingPages)) {
            $categories['LANDING_PAGES'][] = $fileName;
            continue;
        }

        // PWA
        if (in_array($fileName, $pwaFiles)) {
            $categories['PWA'][] = $fileName;
            continue;
        }

        // CRITICAL UTILITY
        if (in_array($fileName, $criticalUtility)) {
            $categories['CRITICAL_UTILITY'][] = $fileName;
            continue;
        }

        // TEST
        if (preg_match('/^test_/', $fileName)) {
            $categories['TEST'][] = $fileName;
            continue;
        }

        // MIGRATION
        if (preg_match('/^(pridej|kontrola|migrace|vycisti)_/', $fileName)) {
            $categories['MIGRATION'][] = $fileName;
            continue;
        }

        // DIAGNOSTIC
        if (preg_match('/^debug_/', $fileName)) {
            $categories['DIAGNOSTIC'][] = $fileName;
            continue;
        }

        // FIX SCRIPTS
        if (preg_match('/^(oprav|check|najdi|prehod|zkontroluj)_/', $fileName)) {
            $categories['FIX_SCRIPTS'][] = $fileName;
            continue;
        }

        // EMAIL OPERATIONS
        if (preg_match('/^(odeslat|vlozit|nahled)_/', $fileName)) {
            $categories['EMAIL_OPERATIONS'][] = $fileName;
            continue;
        }

        // ADMIN TOOLS
        if (preg_match('/^(nova_|photo|analyza_|diagnoza_|diagnostika_)/', $fileName)) {
            $categories['ADMIN_TOOLS'][] = $fileName;
            continue;
        }

        // CLEANUP SCRIPTS
        if (preg_match('/^(vymaz|smaz|obnov|vrat|overeni)_/', $fileName)) {
            $categories['CLEANUP_SCRIPTS'][] = $fileName;
            continue;
        }

        // TABLE VIEWER
        if (preg_match('/(vsechny_tabulky|show_table|show_reklamace)/', $fileName)) {
            $categories['TABLE_VIEWER'][] = $fileName;
            continue;
        }

        // MISPLACED API
        if (preg_match('/_api\.php$/', $fileName)) {
            $categories['MISPLACED_API'][] = $fileName;
            continue;
        }

        // OLD/BACKUP
        if (preg_match('/_(old|backup|v2|copy|temp)\.php$/', $fileName)) {
            $categories['OLD_BACKUP'][] = $fileName;
            continue;
        }

        // Utility skripty
        if (preg_match('/^(quick_|raw_|show_|vytvor_)/', $fileName)) {
            $categories['ADMIN_TOOLS'][] = $fileName;
            continue;
        }

        // UNKNOWN (zbylé)
        $categories['UNKNOWN'][] = $fileName;
    }

    // STATISTIKY
    echo "<h2>2. Statistiky podle kategorií</h2>";
    echo "<div class='stats'>";

    $colorMap = [
        'CRITICAL' => '#dc3545',
        'LANDING_PAGES' => '#28a745',
        'PWA' => '#17a2b8',
        'CRITICAL_UTILITY' => '#6f42c1',
        'TEST' => '#17a2b8',
        'MIGRATION' => '#6c757d',
        'DIAGNOSTIC' => '#ffc107',
        'FIX_SCRIPTS' => '#fd7e14',
        'EMAIL_OPERATIONS' => '#20c997',
        'ADMIN_TOOLS' => '#6610f2',
        'CLEANUP_SCRIPTS' => '#e83e8c',
        'TABLE_VIEWER' => '#28a745',
        'MISPLACED_API' => '#dc3545',
        'OLD_BACKUP' => '#fd7e14'
    ];

    foreach ($categories as $cat => $files) {
        if ($cat === 'UNKNOWN') continue;
        if (empty($files)) continue;
        
        $color = $colorMap[$cat] ?? '#667eea';
        echo "<div class='stat-card' style='background: linear-gradient(135deg, {$color} 0%, {$color}dd 100%);'>";
        echo "<h3>" . str_replace('_', ' ', $cat) . "</h3>";
        echo "<p class='number'>" . count($files) . "</p>";
        echo "</div>";
    }

    echo "</div>";

    // DETAILNÍ TABULKY
    echo "<h2>3. Detailní analýza souborů</h2>";

    // CRITICAL
    if (!empty($categories['CRITICAL'])) {
        echo "<div class='category-section'>";
        echo "<h3>❌ CRITICAL - NIKDY NESMAZAT</h3>";
        echo "<div class='success'>Těchto <strong>" . count($categories['CRITICAL']) . "</strong> souborů je nezbytných pro fungování aplikace.</div>";
        echo "</div>";
    }

    // LANDING PAGES
    if (!empty($categories['LANDING_PAGES'])) {
        echo "<div class='category-section'>";
        echo "<h3>🌐 LANDING PAGES - PONECHAT (veřejné stránky)</h3>";
        echo "<div class='info'><strong>" . count($categories['LANDING_PAGES']) . "</strong> veřejných stránek pro zákazníky a SEO.</div>";
        echo "<table><tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['LANDING_PAGES'] as $file) {
            echo "<tr><td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-keep'>PONECHAT</span> Veřejná marketingová stránka</td></tr>";
        }
        echo "</table></div>";
    }

    // PWA
    if (!empty($categories['PWA'])) {
        echo "<div class='category-section'>";
        echo "<h3>📱 PWA - KONTROLA POTŘEBY</h3>";
        echo "<table><tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['PWA'] as $file) {
            if ($file === 'sw.php') {
                echo "<tr><td><code>{$file}</code></td>";
                echo "<td><span class='badge badge-critical'>CRITICAL</span> Service Worker - musí zůstat!</td></tr>";
            } elseif (preg_match('/test|emergency/', $file)) {
                echo "<tr><td><code>{$file}</code></td>";
                echo "<td><span class='badge badge-review'>ZKONTROLOVAT</span> Testovací/fallback verze - možná archivovat</td></tr>";
            } else {
                echo "<tr><td><code>{$file}</code></td>";
                echo "<td><span class='badge badge-keep'>PONECHAT</span> Součást PWA funkcionality</td></tr>";
            }
        }
        echo "</table></div>";
    }

    // CRITICAL UTILITY
    if (!empty($categories['CRITICAL_UTILITY'])) {
        echo "<div class='category-section'>";
        echo "<h3>🔧 CRITICAL UTILITY - PONECHAT</h3>";
        echo "<table><tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['CRITICAL_UTILITY'] as $file) {
            echo "<tr><td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-critical'>CRITICAL</span> Nutné pro fungování (health check, reset hesla, analytics)</td></tr>";
        }
        echo "</table></div>";
    }

    // TEST
    if (!empty($categories['TEST'])) {
        echo "<div class='category-section'>";
        echo "<h3>🧪 TEST - BEZPEČNĚ ARCHIVOVAT</h3>";
        echo "<table><tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['TEST'] as $file) {
            echo "<tr><td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-safe'>ARCHIVOVAT</span> Po ověření funkčnosti</td></tr>";
        }
        echo "</table></div>";
    }

    // MIGRATION
    if (!empty($categories['MIGRATION'])) {
        echo "<div class='category-section'>";
        echo "<h3>📦 MIGRATION - ARCHIVOVAT PO SPUŠTĚNÍ</h3>";
        echo "<table><tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['MIGRATION'] as $file) {
            echo "<tr><td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-safe'>ARCHIVOVAT</span> Pokud migrace proběhla</td></tr>";
        }
        echo "</table></div>";
    }

    // FIX SCRIPTS
    if (!empty($categories['FIX_SCRIPTS'])) {
        echo "<div class='category-section'>";
        echo "<h3>🔨 FIX SCRIPTS - ARCHIVOVAT PO POUŽITÍ</h3>";
        echo "<div class='warning'><strong>" . count($categories['FIX_SCRIPTS']) . "</strong> jednorázových fix skriptů. Pokud opravy proběhly, archivovat.</div>";
        echo "<table><tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['FIX_SCRIPTS'] as $file) {
            echo "<tr><td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-safe'>ARCHIVOVAT</span> Jednorázová oprava - po použití archivovat</td></tr>";
        }
        echo "</table></div>";
    }

    // EMAIL OPERATIONS
    if (!empty($categories['EMAIL_OPERATIONS'])) {
        echo "<div class='category-section'>";
        echo "<h3>📧 EMAIL OPERATIONS - ARCHIVOVAT PO ODESLÁNÍ</h3>";
        echo "<table><tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['EMAIL_OPERATIONS'] as $file) {
            echo "<tr><td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-safe'>ARCHIVOVAT</span> Hromadné emaily - po odeslání archivovat</td></tr>";
        }
        echo "</table></div>";
    }

    // ADMIN TOOLS
    if (!empty($categories['ADMIN_TOOLS'])) {
        echo "<div class='category-section'>";
        echo "<h3>🛠️ ADMIN TOOLS - ZKONTROLOVAT POUŽÍVÁNÍ</h3>";
        echo "<table><tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['ADMIN_TOOLS'] as $file) {
            echo "<tr><td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-review'>ZKONTROLOVAT</span> Pokud nepoužíváš pravidelně, archivovat</td></tr>";
        }
        echo "</table></div>";
    }

    // CLEANUP SCRIPTS
    if (!empty($categories['CLEANUP_SCRIPTS'])) {
        echo "<div class='category-section'>";
        echo "<h3>🧽 CLEANUP SCRIPTS - ARCHIVOVAT PO POUŽITÍ</h3>";
        echo "<table><tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['CLEANUP_SCRIPTS'] as $file) {
            echo "<tr><td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-safe'>ARCHIVOVAT</span> Jednorázový cleanup - po použití archivovat</td></tr>";
        }
        echo "</table></div>";
    }

    // DIAGNOSTIC
    if (!empty($categories['DIAGNOSTIC'])) {
        echo "<div class='category-section'>";
        echo "<h3>🔍 DIAGNOSTIC - VOLITELNÉ</h3>";
        echo "<table><tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['DIAGNOSTIC'] as $file) {
            echo "<tr><td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-safe'>ARCHIVOVAT</span> Pokud nepoužíváš</td></tr>";
        }
        echo "</table></div>";
    }

    // TABLE VIEWER
    if (!empty($categories['TABLE_VIEWER'])) {
        echo "<div class='category-section'>";
        echo "<h3>📊 TABLE VIEWER - NAHRAZENO SQL KARTOU</h3>";
        echo "<table><tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['TABLE_VIEWER'] as $file) {
            echo "<tr><td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-safe'>ARCHIVOVAT</span> Nahrazeno SQL kartou v admin</td></tr>";
        }
        echo "</table></div>";
    }

    // MISPLACED API
    if (!empty($categories['MISPLACED_API'])) {
        echo "<div class='category-section'>";
        echo "<h3>⚠️ MISPLACED API - PŘESUNOUT DO /api/</h3>";
        echo "<div class='warning'><strong>Varování:</strong> API soubory by měly být v <code>/api/</code> složce!</div>";
        echo "<table><tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['MISPLACED_API'] as $file) {
            echo "<tr><td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-review'>PŘESUNOUT</span> Přesunout do /api/ nebo archivovat pokud nepoužívané</td></tr>";
        }
        echo "</table></div>";
    }

    // UNKNOWN (zbytek)
    if (!empty($categories['UNKNOWN'])) {
        echo "<div class='category-section'>";
        echo "<h3>❓ UNKNOWN - ZBÝVÁ " . count($categories['UNKNOWN']) . " SOUBORŮ</h3>";
        echo "<div class='info'>Tyto soubory nebyly automaticky kategorizovány ani ve v2. Manuální kontrola nutná.</div>";
        echo "<table><tr><th>Soubor</th><th>Doporučení</th></tr>";
        foreach ($categories['UNKNOWN'] as $file) {
            echo "<tr><td><code>{$file}</code></td>";
            echo "<td><span class='badge badge-review'>ZKONTROLOVAT</span> Manuální kontrola</td></tr>";
        }
        echo "</table></div>";
    }

    // GENEROVÁNÍ ARCHIVAČNÍHO SKRIPTU
    echo "<h2>4. Rozšířený archivační skript</h2>";

    $totalToArchive = 0;
    $archiveScript = "#!/bin/bash\n";
    $archiveScript .= "# Archivační skript v2 - vygenerováno " . date('Y-m-d H:i:s') . "\n";
    $archiveScript .= "# PŘED SPUŠTĚNÍM: Zkontroluj seznam souborů!\n\n";
    $archiveScript .= "# Vytvoření archivních složek\n";
    $archiveScript .= "mkdir -p _archive/test-scripts\n";
    $archiveScript .= "mkdir -p _archive/migrations\n";
    $archiveScript .= "mkdir -p _archive/diagnostic\n";
    $archiveScript .= "mkdir -p _archive/fix-scripts\n";
    $archiveScript .= "mkdir -p _archive/email-operations\n";
    $archiveScript .= "mkdir -p _archive/admin-tools\n";
    $archiveScript .= "mkdir -p _archive/cleanup-scripts\n";
    $archiveScript .= "mkdir -p _archive/table-viewers\n";
    $archiveScript .= "mkdir -p _archive/old-backups\n\n";

    $categoriesToArchive = [
        'TEST' => 'test-scripts',
        'MIGRATION' => 'migrations',
        'DIAGNOSTIC' => 'diagnostic',
        'FIX_SCRIPTS' => 'fix-scripts',
        'EMAIL_OPERATIONS' => 'email-operations',
        'ADMIN_TOOLS' => 'admin-tools',
        'CLEANUP_SCRIPTS' => 'cleanup-scripts',
        'TABLE_VIEWER' => 'table-viewers',
        'OLD_BACKUP' => 'old-backups'
    ];

    foreach ($categoriesToArchive as $cat => $folder) {
        if (!empty($categories[$cat])) {
            $archiveScript .= "# " . str_replace('_', ' ', $cat) . " soubory\n";
            foreach ($categories[$cat] as $file) {
                $archiveScript .= "git mv {$file} _archive/{$folder}/\n";
                $totalToArchive++;
            }
            $archiveScript .= "\n";
        }
    }

    $archiveScript .= "# Commit archivace\n";
    $archiveScript .= "git add _archive/\n";
    $archiveScript .= "git commit -m \"CLEANUP: Archivovat {$totalToArchive} nevyužívaných souborů (v2)\n\n";
    $archiveScript .= "Archivované kategorie:\n";
    foreach ($categoriesToArchive as $cat => $folder) {
        if (!empty($categories[$cat])) {
            $archiveScript .= "- " . str_replace('_', ' ', $cat) . ": " . count($categories[$cat]) . " souborů\n";
        }
    }
    $archiveScript .= "\"\n\n";
    $archiveScript .= "echo \"✅ Archivace dokončena. Testuj 7 dní, pak spusť cleanup_archive.sh\"\n";

    echo "<div class='info'>";
    echo "<strong>Celkem k archivaci:</strong> {$totalToArchive} souborů<br>";
    echo "<strong>Ponecháno:</strong> " . (count($categories['CRITICAL']) + count($categories['LANDING_PAGES']) + count($categories['PWA']) + count($categories['CRITICAL_UTILITY'])) . " souborů<br>";
    echo "<strong>Vyžaduje kontrolu:</strong> " . count($categories['UNKNOWN']) . " souborů";
    echo "</div>";

    echo "<div class='script-box'>" . htmlspecialchars($archiveScript) . "</div>";

    echo "<form method='post' action='?download=1' style='display: inline;'>";
    echo "<button type='submit' class='btn btn-download'>📥 Stáhnout archivační skript v2</button>";
    echo "</form>";

    // Download handling
    if (isset($_GET['download']) && $_GET['download'] === '1') {
        header('Content-Type: text/plain');
        header('Content-Disposition: attachment; filename="archive_files_v2.sh"');
        echo $archiveScript;
        exit;
    }

} catch (Exception $e) {
    echo "<div class='error'>" . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='/admin.php' class='btn'>Zpět do admin</a>";
echo "<a href='/audit_unused_files.php' class='btn'>Audit v1</a>";
echo "</div></body></html>";
?>
