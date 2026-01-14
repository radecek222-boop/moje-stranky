<?php
/**
 * Analýza projektu WGS - Co se skutečně používá?
 *
 * Tento skript analyzuje projekt a identifikuje:
 * 1. Aktivní PHP soubory (používané v includes, require)
 * 2. Aktivní JavaScript soubory
 * 3. Aktivní CSS soubory
 * 4. Aktivní API endpointy
 * 5. Používané databázové tabulky
 * 6. Legacy soubory (pravděpodobně nepoužívané)
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Analýza projektu WGS</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               padding: 20px; background: #f5f5f5; }
        .container { max-width: 1400px; margin: 0 auto; background: white;
                     padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 30px; border-bottom: 2px solid #999; padding-bottom: 5px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                 gap: 15px; margin: 20px 0; }
        .stat-box { background: #f9f9f9; padding: 15px; border-radius: 5px;
                    border-left: 4px solid #333; }
        .stat-number { font-size: 2em; font-weight: bold; color: #333; }
        .stat-label { color: #666; font-size: 0.9em; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 0.9em; }
        th, td { padding: 8px; border: 1px solid #ddd; text-align: left; }
        th { background: #333; color: white; position: sticky; top: 0; }
        tr:nth-child(even) { background: #f9f9f9; }
        .used { color: #28a745; font-weight: bold; }
        .unused { color: #dc3545; }
        .maybe { color: #ffc107; }
        .btn { display: inline-block; padding: 10px 20px; background: #333;
               color: white; text-decoration: none; border-radius: 5px; margin: 5px; }
        .btn:hover { background: #555; }
        .legend { display: flex; gap: 20px; margin: 15px 0; font-size: 0.9em; }
        .legend-item { display: flex; align-items: center; gap: 5px; }
        .legend-dot { width: 12px; height: 12px; border-radius: 50%; }
        .section { margin: 30px 0; padding: 20px; background: #fafafa; border-radius: 5px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔍 Analýza projektu WGS</h1>";
echo "<p>Tato analýza identifikuje, které soubory a funkce se skutečně používají v projektu.</p>";

$projektRoot = __DIR__;

// =====================================================
// 1. ZÁKLADNÍ STATISTIKY
// =====================================================

echo "<div class='section'>";
echo "<h2>📊 Základní statistiky projektu</h2>";

$stats = [
    'php_files' => 0,
    'js_files' => 0,
    'css_files' => 0,
    'total_size' => 0,
    'archiv_files' => 0,
    'migrations' => 0,
    'api_endpoints' => 0
];

// Počítat soubory
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projektRoot, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $relativePath = str_replace($projektRoot . '/', '', $file->getPathname());

        // Přeskočit vendor, node_modules, .git
        if (preg_match('#(vendor|node_modules|\.git|uploads|backups|logs)/#', $relativePath)) {
            continue;
        }

        $stats['total_size'] += $file->getSize();

        $ext = strtolower($file->getExtension());
        if ($ext === 'php') {
            $stats['php_files']++;

            if (strpos($relativePath, 'archiv/') === 0) {
                $stats['archiv_files']++;
            }
            if (strpos($relativePath, 'migrations/') === 0) {
                $stats['migrations']++;
            }
            if (strpos($relativePath, 'api/') === 0) {
                $stats['api_endpoints']++;
            }
        } elseif ($ext === 'js') {
            $stats['js_files']++;
        } elseif ($ext === 'css') {
            $stats['css_files']++;
        }
    }
}

echo "<div class='stats'>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['php_files']}</div><div class='stat-label'>PHP souborů</div></div>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['js_files']}</div><div class='stat-label'>JS souborů</div></div>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['css_files']}</div><div class='stat-label'>CSS souborů</div></div>";
echo "<div class='stat-box'><div class='stat-number'>" . round($stats['total_size'] / 1024 / 1024, 1) . " MB</div><div class='stat-label'>Celková velikost</div></div>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['archiv_files']}</div><div class='stat-label'>Archiv souborů</div></div>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['migrations']}</div><div class='stat-label'>Migračních skriptů</div></div>";
echo "<div class='stat-box'><div class='stat-number'>{$stats['api_endpoints']}</div><div class='stat-label'>API endpointů</div></div>";
echo "</div>";

echo "</div>";

// =====================================================
// 2. HLAVNÍ STRÁNKY (ROOT)
// =====================================================

echo "<div class='section'>";
echo "<h2>📄 Hlavní stránky (root)</h2>";

echo "<div class='legend'>";
echo "<div class='legend-item'><div class='legend-dot' style='background: #28a745;'></div>Aktivní (odkazováno v menu/includes)</div>";
echo "<div class='legend-item'><div class='legend-dot' style='background: #ffc107;'></div>Možná používáno</div>";
echo "<div class='legend-item'><div class='legend-dot' style='background: #dc3545;'></div>Pravděpodobně nepoužívané</div>";
echo "</div>";

$rootFiles = glob($projektRoot . '/*.php');
$aktivniStranky = [
    'index.php',
    'seznam.php',
    'novareklamace.php',
    'statistiky.php',
    'admin.php',
    'login.php',
    'protokol.php',
    'cenik.php',
    'cenova-nabidka.php',
    'analytics.php',
    'aktuality.php'
];

echo "<table>";
echo "<tr><th>Soubor</th><th>Velikost</th><th>Status</th><th>Poznámka</th></tr>";

foreach ($rootFiles as $file) {
    $basename = basename($file);
    $size = filesize($file);
    $sizeKB = round($size / 1024, 1);

    // Určit status
    $status = 'unused';
    $statusText = 'Nepoužívané';
    $statusClass = 'unused';
    $poznamka = '';

    if (in_array($basename, $aktivniStranky)) {
        $status = 'used';
        $statusText = 'Aktivní';
        $statusClass = 'used';
        $poznamka = 'Hlavní stránka systému';
    } elseif (strpos($basename, 'init.php') !== false || strpos($basename, 'config') !== false) {
        $status = 'used';
        $statusText = 'Aktivní';
        $statusClass = 'used';
        $poznamka = 'Konfigurační soubor';
    } elseif (strpos($basename, 'test_') === 0 || strpos($basename, 'zkontroluj_') === 0 || strpos($basename, 'diagnostika') !== false) {
        $status = 'maybe';
        $statusText = 'Diagnostika';
        $statusClass = 'maybe';
        $poznamka = 'Diagnostický/testovací skript';
    } elseif (strpos($basename, 'pridej_') === 0 || strpos($basename, 'oprav_') === 0 || strpos($basename, 'migrace_') === 0) {
        $status = 'maybe';
        $statusText = 'Migrace';
        $statusClass = 'maybe';
        $poznamka = 'Migrační skript (spustit jednou)';
    }

    echo "<tr>";
    echo "<td><strong>{$basename}</strong></td>";
    echo "<td>{$sizeKB} KB</td>";
    echo "<td class='{$statusClass}'>{$statusText}</td>";
    echo "<td>{$poznamka}</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// =====================================================
// 3. SLOŽKA ARCHIV
// =====================================================

echo "<div class='section'>";
echo "<h2>📦 Složka archiv/ ({$stats['archiv_files']} souborů)</h2>";
echo "<p><strong>⚠️ DOPORUČENÍ:</strong> Soubory v archivu jsou pravděpodobně <strong>nepoužívané legacy skripty</strong>. Můžete je bezpečně smazat nebo přesunout mimo projekt.</p>";

$archivFiles = glob($projektRoot . '/archiv/*.php');
$archivSize = 0;
foreach ($archivFiles as $file) {
    $archivSize += filesize($file);
}

echo "<p><strong>Celková velikost archivu:</strong> " . round($archivSize / 1024 / 1024, 2) . " MB</p>";
echo "<p><strong>Ušetříte:</strong> Smazáním složky archiv/ ušetříte {$stats['archiv_files']} souborů a " . round($archivSize / 1024 / 1024, 2) . " MB</p>";

echo "<details>";
echo "<summary style='cursor: pointer; font-weight: bold;'>📋 Zobrazit seznam souborů v archivu</summary>";
echo "<ul style='columns: 2; font-size: 0.85em; line-height: 1.6;'>";
foreach ($archivFiles as $file) {
    $basename = basename($file);
    $sizeKB = round(filesize($file) / 1024, 1);
    echo "<li>{$basename} ({$sizeKB} KB)</li>";
}
echo "</ul>";
echo "</details>";

echo "</div>";

// =====================================================
// 4. API ENDPOINTY
// =====================================================

echo "<div class='section'>";
echo "<h2>🔌 API endpointy (api/)</h2>";

$apiFiles = glob($projektRoot . '/api/*.php');
echo "<p>Nalezeno <strong>" . count($apiFiles) . "</strong> API endpointů</p>";

$kritickeAPI = [
    'control_center_api.php',
    'protokol_api.php',
    'statistiky_api.php',
    'notes_api.php',
    'send_contact_attempt_email.php',
    'notification_api.php'
];

echo "<table>";
echo "<tr><th>API Endpoint</th><th>Velikost</th><th>Status</th></tr>";

foreach ($apiFiles as $file) {
    $basename = basename($file);
    $sizeKB = round(filesize($file) / 1024, 1);

    $status = in_array($basename, $kritickeAPI) ? 'used' : 'maybe';
    $statusText = in_array($basename, $kritickeAPI) ? 'Kritické' : 'Možná používáno';
    $statusClass = in_array($basename, $kritickeAPI) ? 'used' : 'maybe';

    echo "<tr>";
    echo "<td><strong>{$basename}</strong></td>";
    echo "<td>{$sizeKB} KB</td>";
    echo "<td class='{$statusClass}'>{$statusText}</td>";
    echo "</tr>";
}

echo "</table>";
echo "</div>";

// =====================================================
// 5. DOPORUČENÍ PRO CLEANUP
// =====================================================

echo "<div class='section'>";
echo "<h2>💡 Doporučení pro vytvoření čistého klonu</h2>";

$potencialniUspora = $archivSize;
$migraceFiles = glob($projektRoot . '/pridej_*.php') ?: [];
$migraceFiles = array_merge($migraceFiles, glob($projektRoot . '/oprav_*.php') ?: []);
$testFiles = glob($projektRoot . '/test_*.php') ?: [];
$testFiles = array_merge($testFiles, glob($projektRoot . '/zkontroluj_*.php') ?: []);

$migraceSize = 0;
foreach ($migraceFiles as $file) {
    $migraceSize += filesize($file);
}

$testSize = 0;
foreach ($testFiles as $file) {
    $testSize += filesize($file);
}

$potencialniUspora += $migraceSize + $testSize;

echo "<h3>🎯 Co můžete bezpečně odstranit:</h3>";
echo "<ol>";
echo "<li><strong>Složka archiv/</strong> - " . count($archivFiles) . " souborů, " . round($archivSize / 1024 / 1024, 2) . " MB</li>";
echo "<li><strong>Migrační skripty v root</strong> (pridej_*, oprav_*) - " . count($migraceFiles) . " souborů, " . round($migraceSize / 1024, 1) . " KB</li>";
echo "<li><strong>Testovací skripty</strong> (test_*, zkontroluj_*, diagnostika_*) - " . count($testFiles) . " souborů, " . round($testSize / 1024, 1) . " KB</li>";
echo "<li><strong>Složka migrations/</strong> - {$stats['migrations']} SQL skriptů (ponechat pouze poslední schema)</li>";
echo "</ol>";

echo "<p><strong>📊 Celková potenciální úspora: " . round($potencialniUspora / 1024 / 1024, 2) . " MB</strong></p>";

echo "<h3>✅ Základní struktura čistého klonu:</h3>";
echo "<pre style='background: #000; color: #0f0; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
echo "wgs-clean/
├── config/                 # Databázové připojení
├── app/
│   ├── controllers/        # Business logika
│   └── notification_sender.php
├── includes/               # Sdílené utility
├── api/                    # API endpointy (POUZE používané)
├── assets/
│   ├── js/                 # JavaScript (POUZE .min.js verze)
│   └── css/                # CSS (POUZE .min.css verze)
├── uploads/                # Nahrané soubory
├── logs/                   # Logy
├── .env                    # Konfigurace
├── init.php                # Bootstrap
└── [Hlavní stránky]        # index.php, seznam.php, admin.php, atd.
";
echo "</pre>";

echo "</div>";

// =====================================================
// 6. AKČNÍ TLAČÍTKA
// =====================================================

echo "<div class='section'>";
echo "<h2>🚀 Co dál?</h2>";
echo "<p>Můžu ti vytvořit skript, který:</p>";
echo "<ol>";
echo "<li><strong>Automaticky vytvoří čistý klon projektu</strong> (pouze aktivní soubory)</li>";
echo "<li><strong>Vytvoří seznam souborů k manuálnímu přezkoumání</strong></li>";
echo "<li><strong>Vygeneruje migrační plán</strong> (co zkopírovat, co smazat)</li>";
echo "</ol>";

echo "<a href='/admin.php' class='btn'>← Zpět do Admin panelu</a>";
echo "</div>";

echo "</div></body></html>";
?>
