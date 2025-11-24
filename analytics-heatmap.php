<?php
/**
 * Analytics Heatmap Viewer - Admin UI pro zobrazení heatmap
 *
 * Admin stránka pro vizualizaci agregovaných click a scroll heatmap.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #6 - Heatmap Engine
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

// Vygenerovat nebo získat CSRF token pro API calls
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Heatmap Viewer - WGS Analytics</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
        }

        h1 {
            color: #333333;
            margin-bottom: 20px;
        }

        .controls {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .control-group {
            display: inline-block;
            margin-right: 20px;
            margin-bottom: 10px;
        }

        .control-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
        }

        .control-group select,
        .control-group input {
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
            min-width: 200px;
        }

        .btn {
            padding: 10px 20px;
            background: #333333;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            margin-right: 10px;
        }

        .btn:hover {
            background: #1a300d;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        #heatmap-container {
            position: relative;
            max-width: 1200px;
            margin: 20px auto;
            background: white;
            border: 1px solid #ccc;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        #heatmap-canvas {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: 10;
            opacity: 0.7;
            box-sizing: border-box;
        }

        #page-iframe {
            width: 100%;
            height: 800px;
            border: none;
            display: block;
        }

        #page-mockup {
            width: 100%;
            min-height: 800px;
            background: #f5f5f5;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #666;
        }

        .legend {
            position: fixed;
            right: 40px;
            top: 120px;
            background: white;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .legend h3 {
            font-size: 14px;
            margin-bottom: 10px;
            color: #333;
        }

        .stats {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .stats h2 {
            font-size: 18px;
            color: #333333;
            margin-bottom: 15px;
        }

        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }

        .stat-item {
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #333333;
            margin-top: 5px;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ANALYTICS TABS NAVIGACE -->
        <?php require_once __DIR__ . '/includes/analytics_tabs.php'; ?>

        <h1>Heatmap Viewer</h1>

        <div class="controls">
            <div class="control-group">
                <label>Stránka:</label>
                <select id="page-selector">
                    <option value="https://www.wgs-service.cz/" data-path="/">DOMŮ</option>
                    <option value="https://www.wgs-service.cz/novareklamace.php" data-path="/novareklamace.php">OBJEDNAT SERVIS</option>
                    <option value="https://www.wgs-service.cz/nasesluzby.php" data-path="/nasesluzby.php">NAŠE SLUŽBY</option>
                    <option value="https://www.wgs-service.cz/cenik.php" data-path="/cenik.php">CENÍK</option>
                    <option value="https://www.wgs-service.cz/onas.php" data-path="/onas.php">O NÁS</option>
                    <option value="https://www.wgs-service.cz/aktuality.php" data-path="/aktuality.php">AKTUALITY</option>
                    <option value="https://www.wgs-service.cz/login.php" data-path="/login.php">PŘIHLÁŠENÍ</option>
                </select>
            </div>

            <div class="control-group">
                <label>Zařízení:</label>
                <select id="device-selector">
                    <option value="">Všechna zařízení</option>
                    <option value="desktop">Desktop</option>
                    <option value="mobile">Mobile</option>
                    <option value="tablet">Tablet</option>
                </select>
            </div>

            <div class="control-group">
                <label>Typ heatmap:</label>
                <select id="type-selector">
                    <option value="click">Click Heatmap</option>
                    <option value="scroll">Scroll Heatmap</option>
                </select>
            </div>

            <div class="control-group" style="display: block; margin-top: 15px;">
                <button id="load-heatmap" class="btn">Načíst Heatmap</button>
                <button id="load-demo" class="btn btn-secondary">Načíst Demo Data</button>
                <button id="export-png" class="btn btn-secondary">Export PNG</button>
            </div>
        </div>

        <div id="stats-container" class="stats" style="display: none;">
            <h2>Statistiky</h2>
            <div class="stat-grid">
                <div class="stat-item">
                    <div class="stat-label">Celkem kliků / zobrazení</div>
                    <div class="stat-value" id="stat-total">-</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Maximální intenzita</div>
                    <div class="stat-value" id="stat-max">-</div>
                </div>
                <div class="stat-item">
                    <div class="stat-label">Počet bodů</div>
                    <div class="stat-value" id="stat-points">-</div>
                </div>
            </div>
        </div>

        <div id="geo-stats-container" class="stats" style="display: none;">
            <h2>Geolokace návštěvníků</h2>
            <div id="geo-stats-table" style="margin-top: 15px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <thead>
                        <tr style="background: #333; color: white;">
                            <th style="padding: 10px; text-align: left;">Země</th>
                            <th style="padding: 10px; text-align: left;">Město</th>
                            <th style="padding: 10px; text-align: right;">Kliků/Zobrazení</th>
                        </tr>
                    </thead>
                    <tbody id="geo-stats-body">
                        <!-- Dynamicky plněno JavaScriptem -->
                    </tbody>
                </table>
            </div>
        </div>

        <div id="heatmap-container">
            <div id="page-mockup">
                <div>Vyberte stránku a klikněte na "Načíst Heatmap"</div>
            </div>
            <iframe id="page-iframe" style="display: none;"></iframe>
            <canvas id="heatmap-canvas"></canvas>
        </div>

        <div class="legend" id="legend-container"></div>
    </div>

    <script src="/assets/js/heatmap-renderer.js"></script>
    <script>
        const csrfToken = '<?php echo htmlspecialchars($csrfToken); ?>';
        let currentData = null;

        // Inicializace HeatmapRenderer
        HeatmapRenderer.init('heatmap-canvas');

        // Vykreslit legendu
        HeatmapRenderer.renderLegend('legend-container');

        // Resize canvas podle iframe nebo mockup
        function resizeCanvas() {
            const iframe = document.getElementById('page-iframe');
            const mockup = document.getElementById('page-mockup');
            const canvas = document.getElementById('heatmap-canvas');

            // Použít iframe pokud je viditelný, jinak mockup
            const container = iframe.style.display !== 'none' ? iframe : mockup;

            const width = container.offsetWidth;
            const height = container.offsetHeight || 800;

            console.log('[Heatmap] Resize canvas:', width, 'x', height, 'px');

            canvas.width = width;
            canvas.height = height;

            // Znovu vykreslit heatmap po resize
            if (currentData) {
                const type = document.getElementById('type-selector').value;
                if (type === 'click') {
                    HeatmapRenderer.renderClickHeatmap(currentData);
                } else {
                    HeatmapRenderer.renderScrollHeatmap(currentData);
                }
            }
        }

        // Načíst stránku do iframe
        function nacistStranku(url) {
            const iframe = document.getElementById('page-iframe');
            const mockup = document.getElementById('page-mockup');

            // Použít relativní cestu pro iframe (stejná doména)
            const selectedOption = document.querySelector('#page-selector option:checked');
            const relativePath = selectedOption.dataset.path || '/';

            console.log('[Heatmap] Načítám stránku:', relativePath);

            // Skrýt mockup, zobrazit iframe
            mockup.style.display = 'none';
            iframe.style.display = 'block';

            // Přidat parametr pro zakázání trackingu v iframe
            iframe.src = relativePath + (relativePath.includes('?') ? '&' : '?') + '_heatmap_preview=1';

            iframe.onload = function() {
                console.log('[Heatmap] Stránka načtena');
                resizeCanvas();
            };
        }

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        // Načtení heatmap
        document.getElementById('load-heatmap').addEventListener('click', async () => {
            const pageUrl = document.getElementById('page-selector').value;
            const deviceType = document.getElementById('device-selector').value;
            const type = document.getElementById('type-selector').value;

            // Nejprve načíst stránku do iframe
            nacistStranku(pageUrl);

            document.getElementById('stats-container').style.display = 'none';

            try {
                const url = `/api/analytics_heatmap.php?page_url=${encodeURIComponent(pageUrl)}&device_type=${deviceType}&type=${type}&csrf_token=${csrfToken}`;

                const response = await fetch(url, {
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                });

                const result = await response.json();

                if (result.status === 'success') {
                    // API vrací data v top-level (array_merge pattern)
                    currentData = result;

                    // Zobrazit statistiky
                    document.getElementById('stats-container').style.display = 'block';

                    if (type === 'click') {
                        document.getElementById('stat-total').textContent = currentData.total_clicks.toLocaleString();
                        document.getElementById('stat-max').textContent = currentData.max_intensity;
                        document.getElementById('stat-points').textContent = currentData.points_count.toLocaleString();

                        // Počkat na načtení iframe a pak vykreslit
                        setTimeout(() => {
                            resizeCanvas();
                            HeatmapRenderer.renderClickHeatmap(currentData);
                        }, 500);
                    } else {
                        document.getElementById('stat-total').textContent = currentData.total_views.toLocaleString();
                        document.getElementById('stat-max').textContent = '100%';
                        document.getElementById('stat-points').textContent = currentData.buckets_count;

                        setTimeout(() => {
                            resizeCanvas();
                            HeatmapRenderer.renderScrollHeatmap(currentData);
                        }, 500);
                    }

                    // Zobrazit geolokační statistiky
                    zobrazitGeoStats(currentData.geo_stats, type);
                } else {
                    // Zobrazit chybu - ale stránka se stále zobrazí v iframe
                    console.warn('[Heatmap] API chyba:', result.message);
                    document.getElementById('geo-stats-container').style.display = 'none';
                }
            } catch (error) {
                console.error('Chyba při načítání heatmap:', error);
            }
        });

        // Načtení demo dat pro testování
        document.getElementById('load-demo').addEventListener('click', () => {
            const type = document.getElementById('type-selector').value;
            const pageUrl = document.getElementById('page-selector').value;

            console.log('[Heatmap] Generuji demo data pro typ:', type);

            // Načíst stránku do iframe
            nacistStranku(pageUrl);

            if (type === 'click') {
                // Generovat náhodná click data
                const demoPoints = [];
                const numPoints = 50;

                for (let i = 0; i < numPoints; i++) {
                    demoPoints.push({
                        x: Math.random() * 80 + 10,  // 10-90% rozsah
                        y: Math.random() * 80 + 10,
                        count: Math.floor(Math.random() * 100) + 1
                    });
                }

                currentData = {
                    points: demoPoints,
                    total_clicks: demoPoints.reduce((sum, p) => sum + p.count, 0),
                    max_intensity: Math.max(...demoPoints.map(p => p.count)),
                    points_count: demoPoints.length
                };

                // Zobrazit statistiky
                document.getElementById('stats-container').style.display = 'block';
                document.getElementById('stat-total').textContent = currentData.total_clicks.toLocaleString();
                document.getElementById('stat-max').textContent = currentData.max_intensity;
                document.getElementById('stat-points').textContent = currentData.points_count.toLocaleString();

                // Počkat na načtení iframe a pak vykreslit
                setTimeout(() => {
                    resizeCanvas();
                    HeatmapRenderer.renderClickHeatmap(currentData);
                }, 500);

            } else {
                // Generovat scroll buckets (0, 10, 20, ..., 100)
                const demoBuckets = [];
                for (let depth = 0; depth <= 100; depth += 10) {
                    demoBuckets.push({
                        depth: depth,
                        count: Math.max(100 - depth + Math.random() * 20, 10) // Více na začátku, méně dole
                    });
                }

                currentData = {
                    buckets: demoBuckets,
                    total_views: demoBuckets[0].count,
                    max_reach: Math.max(...demoBuckets.map(b => b.count)),
                    buckets_count: demoBuckets.length
                };

                // Zobrazit statistiky
                document.getElementById('stats-container').style.display = 'block';
                document.getElementById('stat-total').textContent = currentData.total_views.toLocaleString();
                document.getElementById('stat-max').textContent = Math.round(currentData.max_reach);
                document.getElementById('stat-points').textContent = currentData.buckets_count;

                setTimeout(() => {
                    resizeCanvas();
                    HeatmapRenderer.renderScrollHeatmap(currentData);
                }, 500);
            }

            console.log('[Heatmap] Demo data načtena:', currentData);
        });

        // Export PNG
        document.getElementById('export-png').addEventListener('click', () => {
            const pageUrl = document.getElementById('page-selector').value;
            const type = document.getElementById('type-selector').value;
            const filename = `heatmap_${type}_${Date.now()}.png`;

            HeatmapRenderer.exportToPNG(filename);
        });

        /**
         * Zobrazí geolokační statistiky v tabulce
         */
        function zobrazitGeoStats(geoStats, type) {
            const container = document.getElementById('geo-stats-container');
            const tbody = document.getElementById('geo-stats-body');

            // Vyčistit předchozí data
            tbody.innerHTML = '';

            // Pokud nejsou žádná data
            if (!geoStats || geoStats.length === 0) {
                container.style.display = 'block';
                tbody.innerHTML = '<tr><td colspan="3" style="padding: 20px; text-align: center; color: #666;">Žádná geolokační data</td></tr>';
                return;
            }

            // Zobrazit container
            container.style.display = 'block';

            // Mapování kódů zemí na názvy
            const zemeNazvy = {
                'CZ': 'Česká republika',
                'SK': 'Slovensko',
                'DE': 'Německo',
                'AT': 'Rakousko',
                'PL': 'Polsko',
                'US': 'USA',
                'GB': 'Velká Británie',
                'IT': 'Itálie',
                'FR': 'Francie',
                'NL': 'Nizozemsko',
                'BE': 'Belgie',
                'CH': 'Švýcarsko',
                'HU': 'Maďarsko',
                'UA': 'Ukrajina',
                'RU': 'Rusko'
            };

            // Vykreslit řádky
            geoStats.forEach((stat, index) => {
                const tr = document.createElement('tr');
                tr.style.cssText = index % 2 === 0 ? 'background: #f9f9f9;' : 'background: white;';

                const zemeKod = stat.country_code || '-';
                const zemeNazev = zemeNazvy[zemeKod] || zemeKod;
                const mesto = stat.city || '-';
                const pocet = type === 'click'
                    ? (stat.total_clicks || 0).toLocaleString()
                    : (stat.total_views || 0).toLocaleString();

                tr.innerHTML = `
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">
                        <strong>${zemeKod}</strong> ${zemeNazev}
                    </td>
                    <td style="padding: 10px; border-bottom: 1px solid #eee;">${mesto}</td>
                    <td style="padding: 10px; text-align: right; border-bottom: 1px solid #eee; font-weight: bold;">${pocet}</td>
                `;

                tbody.appendChild(tr);
            });

            console.log('[Heatmap] Zobrazeno', geoStats.length, 'geolokačních záznamů');
        }
    </script>
</body>
</html>
