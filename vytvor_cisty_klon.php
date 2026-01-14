<?php
/**
 * Vytvoření čistého klonu projektu WGS
 *
 * Tento skript vytvoří čistou verzi projektu bez legacy kódu:
 * - Zkopíruje POUZE aktivní PHP stránky
 * - Zkopíruje kritické API endpointy
 * - Zkopíruje pouze minifikované JS/CSS
 * - Přeskočí archiv/, migrace, testy
 * - Vytvoří README s informacemi o změnách
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
    <title>Vytvoření čistého klonu WGS</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               padding: 20px; background: #1a1a1a; color: #0f0; }
        .container { max-width: 1200px; margin: 0 auto; background: #000;
                     padding: 30px; border-radius: 10px; border: 2px solid #0f0; }
        h1, h2, h3 { color: #0f0; text-shadow: 0 0 10px #0f0; }
        .btn { display: inline-block; padding: 15px 30px; background: #0f0;
               color: #000; text-decoration: none; border-radius: 5px; margin: 10px;
               font-weight: bold; border: none; cursor: pointer; }
        .btn:hover { background: #0a0; box-shadow: 0 0 20px #0f0; }
        .btn-danger { background: #f00; color: #fff; }
        .btn-danger:hover { background: #a00; box-shadow: 0 0 20px #f00; }
        .progress { background: #333; height: 30px; border-radius: 5px;
                    margin: 20px 0; overflow: hidden; border: 1px solid #0f0; }
        .progress-bar { background: #0f0; height: 100%; line-height: 30px;
                        text-align: center; color: #000; font-weight: bold;
                        transition: width 0.3s; }
        .log { background: #000; padding: 15px; border-radius: 5px;
               font-family: monospace; font-size: 0.9em; max-height: 400px;
               overflow-y: auto; border: 1px solid #0f0; margin: 20px 0; }
        .success { color: #0f0; }
        .warning { color: #ff0; }
        .error { color: #f00; }
        .info { color: #0ff; }
        pre { background: #111; padding: 15px; border-radius: 5px;
              overflow-x: auto; border-left: 4px solid #0f0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 10px; border: 1px solid #0f0; text-align: left; }
        th { background: #0f0; color: #000; font-weight: bold; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🚀 Vytvoření čistého klonu projektu WGS</h1>";

$projektRoot = __DIR__;
$ciloveSlozka = $projektRoot . '/../wgs-clean';

// =====================================================
// DEFINICE SOUBORŮ K KOPÍROVÁNÍ
// =====================================================

$aktivniStranky = [
    'index.php',
    'seznam.php',
    'novareklamace.php',
    'statistiky.php',
    'admin.php',
    'login.php',
    'logout.php',
    'protokol.php',
    'cenik.php',
    'cenova-nabidka.php',
    'analytics.php',
    'aktuality.php',
    'registration.php',
    'password_reset.php',
    'init.php',
    '.htaccess',
    'manifest.json',
    'sw.php',
    'offline.php',
    'health.php'
];

$kritickeAPI = [
    'control_center_api.php',
    'protokol_api.php',
    'statistiky_api.php',
    'notes_api.php',
    'send_contact_attempt_email.php',
    'notification_api.php',
    'admin_api.php',
    'admin_users_api.php',
    'admin_stats_api.php',
    'delete_reklamace.php',
    'get_photos_api.php',
    'delete_photo.php',
    'geocode_proxy.php',
    'video_api.php',
    'video_download.php',
    'pricing_api.php',
    'nabidka_api.php',
    'zakaznici_api.php',
    'zmenit_stav.php',
    'translate_api.php',
    'analytics_api.php',
    'heartbeat.php',
    'log_js_error.php',
    'track_pageview.php'
];

$slozkyKeKopirovani = [
    'config',
    'app',
    'includes',
    'uploads',
    'logs',
    'backups'
];

// =====================================================
// NÁHLED ZMĚN
// =====================================================

if (!isset($_GET['execute'])) {
    echo "<h2>📋 Náhled změn</h2>";

    echo "<div class='info'>";
    echo "<h3>✅ Co bude zkopírováno:</h3>";
    echo "<ul>";
    echo "<li><strong>" . count($aktivniStranky) . " hlavních stránek</strong> (index.php, seznam.php, admin.php, atd.)</li>";
    echo "<li><strong>" . count($kritickeAPI) . " API endpointů</strong> (pouze kritické a používané)</li>";
    echo "<li><strong>Složky:</strong> " . implode(', ', $slozkyKeKopirovani) . "</li>";
    echo "<li><strong>Assety:</strong> Pouze .min.js a .min.css verze</li>";
    echo "<li><strong>Databáze:</strong> .env soubor (hesla ZŮSTANOU)</li>";
    echo "</ul>";
    echo "</div>";

    echo "<div class='warning'>";
    echo "<h3>🗑️ Co bude VYNECHÁNO:</h3>";
    echo "<ul>";
    echo "<li><strong>Složka archiv/</strong> - 160 souborů, 1.55 MB</li>";
    echo "<li><strong>Migrační skripty</strong> (pridej_*, oprav_*) - 27 souborů</li>";
    echo "<li><strong>Testovací skripty</strong> (test_*, diagnostika_*) - 6 souborů</li>";
    echo "<li><strong>Nepoužívané stránky</strong> (cookies.php, gdpr.php, hry.php, atd.)</li>";
    echo "<li><strong>Source JS/CSS</strong> (ponechány pouze .min verze)</li>";
    echo "<li><strong>Složka migrations/</strong></li>";
    echo "</ul>";
    echo "</div>";

    echo "<div class='error'>";
    echo "<h3>⚠️ VAROVÁNÍ:</h3>";
    echo "<ul>";
    echo "<li>Vytvoří se nová složka <code>wgs-clean/</code> MIMO aktuální projekt</li>";
    echo "<li>Původní projekt zůstane nedotčený</li>";
    echo "<li>Po zkopírování MUSÍTE otestovat, že vše funguje!</li>";
    echo "<li>Databáze se NEKOPÍRUJE - bude sdílená nebo vytvoříte novou</li>";
    echo "</ul>";
    echo "</div>";

    echo "<h3>📊 Odhadovaná úspora:</h3>";
    echo "<table>";
    echo "<tr><th>Položka</th><th>Před</th><th>Po</th><th>Úspora</th></tr>";
    echo "<tr><td>PHP soubory</td><td>460</td><td>~80</td><td>~380 souborů</td></tr>";
    echo "<tr><td>Velikost projektu</td><td>14.1 MB</td><td>~8 MB</td><td>~6 MB</td></tr>";
    echo "<tr><td>Archiv</td><td>1.55 MB</td><td>0 MB</td><td>1.55 MB</td></tr>";
    echo "</table>";

    echo "<br><br>";
    echo "<a href='?execute=1' class='btn'>🚀 SPUSTIT VYTVOŘENÍ ČISTÉHO KLONU</a>";
    echo "<a href='/admin.php' class='btn btn-danger'>Zrušit</a>";
    echo "<br><br>";

    echo "</div></body></html>";
    exit;
}

// =====================================================
// SPUŠTĚNÍ KOPÍROVÁNÍ
// =====================================================

echo "<h2>🚀 Vytváření čistého klonu...</h2>";
echo "<div class='log' id='log'>";

$stats = [
    'zkopirovaneSoubory' => 0,
    'preskooceneSoubory' => 0,
    'chyby' => 0,
    'celkovaVelikost' => 0
];

function logMessage($message, $type = 'info') {
    $colors = [
        'success' => '#0f0',
        'error' => '#f00',
        'warning' => '#ff0',
        'info' => '#0ff'
    ];
    $color = $colors[$type] ?? '#0ff';
    echo "<div style='color: {$color};'>" . date('H:i:s') . " - " . htmlspecialchars($message) . "</div>";
    flush();
    ob_flush();
}

try {
    // 1. Vytvořit cílovou složku
    logMessage("Vytváření cílové složky: {$ciloveSlozka}", 'info');

    if (file_exists($ciloveSlozka)) {
        logMessage("VAROVÁNÍ: Složka {$ciloveSlozka} již existuje!", 'warning');
        logMessage("Prosím smažte ji ručně nebo zvolte jiné umístění.", 'error');
        throw new Exception("Cílová složka již existuje");
    }

    mkdir($ciloveSlozka, 0755, true);
    logMessage("Složka vytvořena", 'success');

    // 2. Kopírovat hlavní stránky
    logMessage("Kopírování hlavních stránek...", 'info');
    foreach ($aktivniStranky as $soubor) {
        $zdrojCesta = $projektRoot . '/' . $soubor;
        $cilovaCesta = $ciloveSlozka . '/' . $soubor;

        if (file_exists($zdrojCesta)) {
            if (copy($zdrojCesta, $cilovaCesta)) {
                $velikost = filesize($zdrojCesta);
                $stats['zkopirovaneSoubory']++;
                $stats['celkovaVelikost'] += $velikost;
                logMessage("✓ {$soubor} (" . round($velikost/1024, 1) . " KB)", 'success');
            } else {
                logMessage("✗ Chyba při kopírování {$soubor}", 'error');
                $stats['chyby']++;
            }
        } else {
            logMessage("⊗ {$soubor} neexistuje, přeskakuji", 'warning');
            $stats['preskooceneSoubory']++;
        }
    }

    // 3. Kopírovat API endpointy
    logMessage("Kopírování API endpointů...", 'info');
    mkdir($ciloveSlozka . '/api', 0755, true);

    foreach ($kritickeAPI as $soubor) {
        $zdrojCesta = $projektRoot . '/api/' . $soubor;
        $cilovaCesta = $ciloveSlozka . '/api/' . $soubor;

        if (file_exists($zdrojCesta)) {
            if (copy($zdrojCesta, $cilovaCesta)) {
                $velikost = filesize($zdrojCesta);
                $stats['zkopirovaneSoubory']++;
                $stats['celkovaVelikost'] += $velikost;
                logMessage("✓ api/{$soubor} (" . round($velikost/1024, 1) . " KB)", 'success');
            }
        }
    }

    // 4. Kopírovat složky (rekurzivně)
    logMessage("Kopírování složek...", 'info');

    function kopirovatSlozku($zdroj, $cil, &$stats) {
        if (!file_exists($cil)) {
            mkdir($cil, 0755, true);
        }

        $dir = opendir($zdroj);
        while (($file = readdir($dir)) !== false) {
            if ($file === '.' || $file === '..') continue;

            $zdrojCesta = $zdroj . '/' . $file;
            $cilovaCesta = $cil . '/' . $file;

            if (is_dir($zdrojCesta)) {
                kopirovatSlozku($zdrojCesta, $cilovaCesta, $stats);
            } else {
                if (copy($zdrojCesta, $cilovaCesta)) {
                    $stats['zkopirovaneSoubory']++;
                    $stats['celkovaVelikost'] += filesize($zdrojCesta);
                }
            }
        }
        closedir($dir);
    }

    foreach ($slozkyKeKopirovani as $slozka) {
        $zdrojCesta = $projektRoot . '/' . $slozka;
        $cilovaCesta = $ciloveSlozka . '/' . $slozka;

        if (file_exists($zdrojCesta) && is_dir($zdrojCesta)) {
            logMessage("Kopírování složky: {$slozka}/", 'info');
            kopirovatSlozku($zdrojCesta, $cilovaCesta, $stats);
            logMessage("✓ Složka {$slozka}/ zkopírována", 'success');
        }
    }

    // 5. Kopírovat assets (pouze .min verze)
    logMessage("Kopírování assetů (pouze minifikované)...", 'info');
    mkdir($ciloveSlozka . '/assets', 0755, true);
    mkdir($ciloveSlozka . '/assets/js', 0755, true);
    mkdir($ciloveSlozka . '/assets/css', 0755, true);
    mkdir($ciloveSlozka . '/assets/images', 0755, true);
    mkdir($ciloveSlozka . '/assets/fonts', 0755, true);

    // JS soubory (pouze .min.js)
    $jsFiles = glob($projektRoot . '/assets/js/*.min.js');
    foreach ($jsFiles as $soubor) {
        $basename = basename($soubor);
        $cilovaCesta = $ciloveSlozka . '/assets/js/' . $basename;
        if (copy($soubor, $cilovaCesta)) {
            $stats['zkopirovaneSoubory']++;
            $stats['celkovaVelikost'] += filesize($soubor);
        }
    }
    logMessage("✓ " . count($jsFiles) . " JS souborů zkopírováno", 'success');

    // CSS soubory (pouze .min.css)
    $cssFiles = glob($projektRoot . '/assets/css/*.min.css');
    foreach ($cssFiles as $soubor) {
        $basename = basename($soubor);
        $cilovaCesta = $ciloveSlozka . '/assets/css/' . $basename;
        if (copy($soubor, $cilovaCesta)) {
            $stats['zkopirovaneSoubory']++;
            $stats['celkovaVelikost'] += filesize($soubor);
        }
    }
    logMessage("✓ " . count($cssFiles) . " CSS souborů zkopírováno", 'success');

    // Images
    if (file_exists($projektRoot . '/assets/images')) {
        kopirovatSlozku($projektRoot . '/assets/images', $ciloveSlozka . '/assets/images', $stats);
        logMessage("✓ Obrázky zkopírovány", 'success');
    }

    // Fonts
    if (file_exists($projektRoot . '/assets/fonts')) {
        kopirovatSlozku($projektRoot . '/assets/fonts', $ciloveSlozka . '/assets/fonts', $stats);
        logMessage("✓ Fonty zkopírovány", 'success');
    }

    // 6. Vytvořit README
    logMessage("Vytváření README...", 'info');

    $readme = "# WGS Clean - Čistá verze projektu

Tento projekt je čistý klon původního WGS systému bez legacy kódu.

## Vytořeno: " . date('d.m.Y H:i:s') . "

## Co bylo zkopírováno:

- " . count($aktivniStranky) . " hlavních stránek
- " . count($kritickeAPI) . " API endpointů
- Složky: " . implode(', ', $slozkyKeKopirovani) . "
- Pouze minifikované JS/CSS soubory
- Obrázky a fonty

## Co bylo vynecháno:

- Složka archiv/ (160 souborů, 1.55 MB)
- Migrační skripty (27 souborů)
- Testovací skripty (6 souborů)
- Nepoužívané stránky
- Source JS/CSS (ponechány pouze .min verze)
- Složka migrations/

## Statistiky:

- Zkopírováno souborů: {$stats['zkopirovaneSoubory']}
- Celková velikost: " . round($stats['celkovaVelikost'] / 1024 / 1024, 2) . " MB
- Chyby: {$stats['chyby']}

## Další kroky:

1. Zkopírujte .env soubor z původního projektu
2. Nastavte databázové připojení
3. Otestujte, že vše funguje
4. Nasaďte na server

## Poznámky:

- Databáze nebyla zkopírována - použijte existující nebo vytvořte novou
- Uploads složka je prázdná - zkopírujte nahraná data ručně pokud potřeba
- Logs a backups složky jsou prázdné

---

Vytvořeno automaticky skriptem vytvor_cisty_klon.php
";

    file_put_contents($ciloveSlozka . '/README.md', $readme);
    logMessage("✓ README.md vytvořen", 'success');

    // 7. Výsledek
    logMessage("", 'info');
    logMessage("═══════════════════════════════════", 'success');
    logMessage("🎉 HOTOVO! Čistý klon byl vytvořen", 'success');
    logMessage("═══════════════════════════════════", 'success');
    logMessage("", 'info');
    logMessage("📊 Statistiky:", 'info');
    logMessage("  - Zkopírováno souborů: {$stats['zkopirovaneSoubory']}", 'success');
    logMessage("  - Celková velikost: " . round($stats['celkovaVelikost'] / 1024 / 1024, 2) . " MB", 'success');
    logMessage("  - Přeskočeno souborů: {$stats['preskooceneSoubory']}", 'warning');
    logMessage("  - Chyby: {$stats['chyby']}", $stats['chyby'] > 0 ? 'error' : 'success');
    logMessage("", 'info');
    logMessage("📁 Umístění: {$ciloveSlozka}", 'info');
    logMessage("", 'info');
    logMessage("✅ Další kroky:", 'info');
    logMessage("  1. Zkopírujte .env soubor", 'warning');
    logMessage("  2. Otestujte projekt lokálně", 'warning');
    logMessage("  3. Nasaďte na server", 'warning');

} catch (Exception $e) {
    logMessage("", 'error');
    logMessage("═══════════════════════════════════", 'error');
    logMessage("❌ CHYBA: " . $e->getMessage(), 'error');
    logMessage("═══════════════════════════════════", 'error');
}

echo "</div>";

echo "<br><br>";
echo "<a href='/admin.php' class='btn'>← Zpět do Admin panelu</a>";
echo "<a href='/analyzuj_projekt.php' class='btn'>Zobrazit analýzu</a>";

echo "</div></body></html>";
?>
