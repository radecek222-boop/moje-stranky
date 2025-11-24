<?php
/**
 * Diagnostika Heatmap - Analýza struktury a vrstev stránky
 *
 * Diagnostický nástroj pro ladění heatmap vizualizace.
 *
 * @date 2025-11-24
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze administrátor může spustit diagnostiku.");
}

$pdo = getDbConnection();

// Získat statistiky z databáze
$clickStats = [];
$scrollStats = [];
$pageviewStats = [];

try {
    // Click heatmap data
    $stmtClicks = $pdo->query("
        SELECT
            page_url,
            device_type,
            COUNT(*) as pocet_zaznamu,
            SUM(click_count) as celkem_kliku,
            MAX(click_count) as max_kliku,
            MIN(first_click) as prvni_klik,
            MAX(last_click) as posledni_klik
        FROM wgs_analytics_heatmap_clicks
        GROUP BY page_url, device_type
        ORDER BY celkem_kliku DESC
        LIMIT 20
    ");
    $clickStats = $stmtClicks->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $clickStats = ['error' => $e->getMessage()];
}

try {
    // Scroll heatmap data
    $stmtScrolls = $pdo->query("
        SELECT
            page_url,
            device_type,
            COUNT(*) as pocet_bucketu,
            SUM(reach_count) as celkem_views,
            MIN(first_reach) as prvni_reach,
            MAX(last_reach) as posledni_reach
        FROM wgs_analytics_heatmap_scroll
        GROUP BY page_url, device_type
        ORDER BY celkem_views DESC
        LIMIT 20
    ");
    $scrollStats = $stmtScrolls->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $scrollStats = ['error' => $e->getMessage()];
}

try {
    // Pageviews pro porovnání
    $stmtPageviews = $pdo->query("
        SELECT
            page_url,
            COUNT(*) as pocet_pageviews,
            COUNT(DISTINCT session_id) as unikatni_sessions,
            MIN(created_at) as prvni_navsteva,
            MAX(created_at) as posledni_navsteva
        FROM wgs_pageviews
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY page_url
        ORDER BY pocet_pageviews DESC
        LIMIT 20
    ");
    $pageviewStats = $stmtPageviews->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $pageviewStats = ['error' => $e->getMessage()];
}

// Kontrola struktury tabulek
$tabulky = [];
try {
    $stmtTables = $pdo->query("SHOW TABLES LIKE 'wgs_analytics%'");
    while ($row = $stmtTables->fetch(PDO::FETCH_NUM)) {
        $tableName = $row[0];
        $stmtCount = $pdo->query("SELECT COUNT(*) FROM `{$tableName}`");
        $count = $stmtCount->fetchColumn();
        $tabulky[$tableName] = $count;
    }
} catch (Exception $e) {
    $tabulky = ['error' => $e->getMessage()];
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostika Heatmap - WGS Analytics</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            max-width: 1400px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #333;
            padding-bottom: 10px;
        }
        h2 {
            color: #333;
            margin-top: 0;
            border-bottom: 1px solid #ddd;
            padding-bottom: 10px;
        }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 14px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #333; color: white; }
        tr:nth-child(even) { background: #f9f9f9; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; font-family: monospace; }
        pre { background: #1e1e1e; color: #dcdcdc; padding: 15px; border-radius: 5px; overflow-x: auto; font-size: 13px; }
        .btn { display: inline-block; padding: 10px 20px; background: #333; color: white; text-decoration: none; border-radius: 5px; margin: 5px 5px 5px 0; cursor: pointer; border: none; font-size: 14px; }
        .btn:hover { background: #555; }
        .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        @media (max-width: 900px) { .grid-2 { grid-template-columns: 1fr; } }
        #diagnostika-vysledek { margin-top: 20px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Diagnostika Heatmap</h1>

        <div class="info">
            <strong>Tento nástroj analyzuje:</strong>
            <ul style="margin: 10px 0 0 20px;">
                <li>Strukturu databázových tabulek pro analytics</li>
                <li>Počet záznamů v každé tabulce</li>
                <li>Data v heatmap tabulkách (clicks, scroll)</li>
                <li>Strukturu HTML elementů na stránce heatmap</li>
            </ul>
        </div>
    </div>

    <div class="grid-2">
        <!-- DATABÁZOVÉ TABULKY -->
        <div class="container">
            <h2>Databázové tabulky</h2>
            <?php if (isset($tabulky['error'])): ?>
                <div class="error">Chyba: <?= htmlspecialchars($tabulky['error']) ?></div>
            <?php else: ?>
                <table>
                    <tr><th>Tabulka</th><th>Počet záznamů</th></tr>
                    <?php foreach ($tabulky as $nazev => $pocet): ?>
                        <tr>
                            <td><code><?= htmlspecialchars($nazev) ?></code></td>
                            <td><?= number_format($pocet) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
                <?php if (empty($tabulky)): ?>
                    <div class="warning">Žádné analytics tabulky nenalezeny!</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- CLICK HEATMAP DATA -->
        <div class="container">
            <h2>Click Heatmap Data</h2>
            <?php if (isset($clickStats['error'])): ?>
                <div class="error">Chyba: <?= htmlspecialchars($clickStats['error']) ?></div>
            <?php elseif (empty($clickStats)): ?>
                <div class="warning">Žádná click data v databázi!</div>
            <?php else: ?>
                <table>
                    <tr>
                        <th>URL</th>
                        <th>Device</th>
                        <th>Záznamů</th>
                        <th>Kliků</th>
                    </tr>
                    <?php foreach ($clickStats as $row): ?>
                        <tr>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                <code><?= htmlspecialchars(basename(parse_url($row['page_url'], PHP_URL_PATH) ?: '/')) ?></code>
                            </td>
                            <td><?= htmlspecialchars($row['device_type']) ?></td>
                            <td><?= number_format($row['pocet_zaznamu']) ?></td>
                            <td><?= number_format($row['celkem_kliku']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div class="grid-2">
        <!-- SCROLL HEATMAP DATA -->
        <div class="container">
            <h2>Scroll Heatmap Data</h2>
            <?php if (isset($scrollStats['error'])): ?>
                <div class="error">Chyba: <?= htmlspecialchars($scrollStats['error']) ?></div>
            <?php elseif (empty($scrollStats)): ?>
                <div class="warning">Žádná scroll data v databázi!</div>
            <?php else: ?>
                <table>
                    <tr>
                        <th>URL</th>
                        <th>Device</th>
                        <th>Bucketů</th>
                        <th>Views</th>
                    </tr>
                    <?php foreach ($scrollStats as $row): ?>
                        <tr>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                <code><?= htmlspecialchars(basename(parse_url($row['page_url'], PHP_URL_PATH) ?: '/')) ?></code>
                            </td>
                            <td><?= htmlspecialchars($row['device_type']) ?></td>
                            <td><?= number_format($row['pocet_bucketu']) ?></td>
                            <td><?= number_format($row['celkem_views']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>

        <!-- PAGEVIEWS PRO POROVNÁNÍ -->
        <div class="container">
            <h2>Pageviews (posledních 7 dní)</h2>
            <?php if (isset($pageviewStats['error'])): ?>
                <div class="error">Chyba: <?= htmlspecialchars($pageviewStats['error']) ?></div>
            <?php elseif (empty($pageviewStats)): ?>
                <div class="warning">Žádné pageviews v databázi!</div>
            <?php else: ?>
                <table>
                    <tr>
                        <th>URL</th>
                        <th>Pageviews</th>
                        <th>Sessions</th>
                    </tr>
                    <?php foreach ($pageviewStats as $row): ?>
                        <tr>
                            <td style="max-width: 200px; overflow: hidden; text-overflow: ellipsis;">
                                <code><?= htmlspecialchars(basename(parse_url($row['page_url'], PHP_URL_PATH) ?: '/')) ?></code>
                            </td>
                            <td><?= number_format($row['pocet_pageviews']) ?></td>
                            <td><?= number_format($row['unikatni_sessions']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <!-- JAVASCRIPT DIAGNOSTIKA -->
    <div class="container">
        <h2>JavaScript Diagnostika (pro browser konzoli)</h2>

        <p>Zkopírujte tento kód do konzole prohlížeče na stránce <code>/analytics-heatmap.php</code>:</p>

        <pre id="js-diagnostika">
// === HEATMAP DIAGNOSTIKA ===
(function() {
    console.log('%c=== HEATMAP DIAGNOSTIKA ===', 'font-size: 16px; font-weight: bold; color: #333;');

    // 1. CANVAS ELEMENT
    const canvas = document.getElementById('heatmap-canvas');
    console.log('\n%c1. CANVAS ELEMENT:', 'font-weight: bold;');
    if (canvas) {
        console.log('   Nalezen: ANO');
        console.log('   Rozměry:', canvas.width, 'x', canvas.height, 'px');
        console.log('   CSS display:', getComputedStyle(canvas).display);
        console.log('   CSS visibility:', getComputedStyle(canvas).visibility);
        console.log('   CSS opacity:', getComputedStyle(canvas).opacity);
        console.log('   CSS z-index:', getComputedStyle(canvas).zIndex);
        console.log('   CSS position:', getComputedStyle(canvas).position);

        const rect = canvas.getBoundingClientRect();
        console.log('   Bounding rect:', rect.width, 'x', rect.height, 'at', rect.left, ',', rect.top);
    } else {
        console.log('   %cNENALEZEN!', 'color: red; font-weight: bold;');
    }

    // 2. PAGE MOCKUP
    const mockup = document.getElementById('page-mockup');
    console.log('\n%c2. PAGE MOCKUP:', 'font-weight: bold;');
    if (mockup) {
        console.log('   Nalezen: ANO');
        console.log('   Rozměry:', mockup.offsetWidth, 'x', mockup.offsetHeight, 'px');
        console.log('   Inner HTML length:', mockup.innerHTML.length);
    } else {
        console.log('   %cNENALEZEN!', 'color: red;');
    }

    // 3. HEATMAP CONTAINER
    const container = document.getElementById('heatmap-container');
    console.log('\n%c3. HEATMAP CONTAINER:', 'font-weight: bold;');
    if (container) {
        console.log('   Nalezen: ANO');
        console.log('   Rozměry:', container.offsetWidth, 'x', container.offsetHeight, 'px');
        console.log('   Position:', getComputedStyle(container).position);
    } else {
        console.log('   %cNENALEZEN!', 'color: red;');
    }

    // 4. HEATMAP RENDERER
    console.log('\n%c4. HEATMAP RENDERER:', 'font-weight: bold;');
    if (typeof HeatmapRenderer !== 'undefined') {
        console.log('   Načten: ANO');
        console.log('   Canvas ref:', HeatmapRenderer.canvas ? 'OK' : 'NULL');
        console.log('   Context ref:', HeatmapRenderer.ctx ? 'OK' : 'NULL');
        console.log('   Config:', HeatmapRenderer.config);
    } else {
        console.log('   %cNENAČTEN!', 'color: red; font-weight: bold;');
    }

    // 5. CURRENT DATA
    console.log('\n%c5. CURRENT DATA:', 'font-weight: bold;');
    if (typeof currentData !== 'undefined' && currentData) {
        console.log('   Data načtena: ANO');
        console.log('   Typ:', currentData.type || 'neznámý');
        if (currentData.points) {
            console.log('   Počet bodů:', currentData.points.length);
        }
        if (currentData.buckets) {
            console.log('   Počet bucketů:', currentData.buckets.length);
        }
        console.log('   Celý objekt:', currentData);
    } else {
        console.log('   %cŽÁDNÁ DATA', 'color: orange;');
    }

    // 6. PAGE SELECTOR
    console.log('\n%c6. PAGE SELECTOR:', 'font-weight: bold;');
    const pageSelector = document.getElementById('page-selector');
    if (pageSelector) {
        console.log('   Vybraná URL:', pageSelector.value);
        console.log('   Počet možností:', pageSelector.options.length);
    }

    // 7. CANVAS CONTENT CHECK
    console.log('\n%c7. CANVAS CONTENT CHECK:', 'font-weight: bold;');
    if (canvas) {
        const ctx = canvas.getContext('2d');
        const imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
        let nonZeroPixels = 0;
        for (let i = 0; i < imageData.data.length; i += 4) {
            if (imageData.data[i+3] > 0) nonZeroPixels++;
        }
        console.log('   Celkem pixelů:', canvas.width * canvas.height);
        console.log('   Pixelů s barvou:', nonZeroPixels);
        console.log('   Canvas prázdný:', nonZeroPixels === 0 ? '%cANO' : '%cNE', nonZeroPixels === 0 ? 'color: red;' : 'color: green;');
    }

    // 8. Z-INDEX VRSTVY
    console.log('\n%c8. Z-INDEX VRSTVY:', 'font-weight: bold;');
    const elements = ['heatmap-container', 'page-mockup', 'heatmap-canvas', 'stats-container', 'geo-stats-container'];
    elements.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            const style = getComputedStyle(el);
            console.log(`   ${id}: z-index=${style.zIndex}, position=${style.position}, display=${style.display}`);
        }
    });

    console.log('\n%c=== KONEC DIAGNOSTIKY ===', 'font-size: 16px; font-weight: bold; color: #333;');

    // AUTOMATICKÝ TEST - načíst heatmap
    console.log('\n%c=== AUTOMATICKÝ TEST ===', 'font-size: 14px; font-weight: bold; color: blue;');
    console.log('Pro test klikněte na tlačítko "Načíst Heatmap" nebo "Načíst Demo Data"');
})();
        </pre>

        <button class="btn" onclick="navigator.clipboard.writeText(document.getElementById('js-diagnostika').textContent).then(() => alert('Zkopírováno!'))">Kopírovat do schránky</button>
    </div>

    <!-- RYCHLÉ ODKAZY -->
    <div class="container">
        <h2>Rychlé odkazy</h2>
        <a href="analytics-heatmap.php" class="btn">Heatmap Viewer</a>
        <a href="sprava_ip_blacklist.php" class="btn">Správa IP Blacklistu</a>
        <a href="vycisti_analytics_data.php" class="btn">Vyčistit Analytics</a>
        <a href="admin.php" class="btn" style="background: #666;">Zpět na Admin</a>
    </div>
</body>
</html>
