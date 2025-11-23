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
            color: #2D5016;
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
            background: #2D5016;
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
            pointer-events: none;
            z-index: 10;
            opacity: 0.7;
            min-width: 100%;
            min-height: 600px;
            border: 2px dashed rgba(45, 80, 22, 0.3); /* Debug border */
        }

        #page-mockup {
            width: 100%;
            min-height: 600px;
            background: linear-gradient(180deg, #f0f0f0 0%, #ffffff 100%);
            padding: 20px;
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
            color: #2D5016;
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
            color: #2D5016;
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
                    <option value="https://www.wgs-service.cz/">Hlavní stránka</option>
                    <option value="https://www.wgs-service.cz/novareklamace.php">Nová reklamace</option>
                    <option value="https://www.wgs-service.cz/seznam.php">Seznam reklamací</option>
                    <option value="https://www.wgs-service.cz/admin.php">Admin</option>
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

        <div id="heatmap-container">
            <div id="page-mockup">
                <div class="loading">Vyberte parametry a klikněte na "Načíst Heatmap"</div>
            </div>
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

        // Resize canvas podle page-mockup
        function resizeCanvas() {
            const container = document.getElementById('page-mockup');
            const canvas = document.getElementById('heatmap-canvas');

            canvas.width = container.offsetWidth;
            canvas.height = container.offsetHeight;

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

        window.addEventListener('resize', resizeCanvas);
        resizeCanvas();

        // Načtení heatmap
        document.getElementById('load-heatmap').addEventListener('click', async () => {
            const pageUrl = document.getElementById('page-selector').value;
            const deviceType = document.getElementById('device-selector').value;
            const type = document.getElementById('type-selector').value;

            document.getElementById('page-mockup').innerHTML = '<div class="loading">Načítám data...</div>';
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

                        document.getElementById('page-mockup').innerHTML = '<div style="min-height: 600px; padding: 40px;">Mockup stránky (Click Heatmap)<br>Klikněte na různá místa...</div>';
                        resizeCanvas();
                        HeatmapRenderer.renderClickHeatmap(currentData);
                    } else {
                        document.getElementById('stat-total').textContent = currentData.total_views.toLocaleString();
                        document.getElementById('stat-max').textContent = '100%';
                        document.getElementById('stat-points').textContent = currentData.buckets_count;

                        document.getElementById('page-mockup').innerHTML = '<div style="min-height: 1000px; padding: 40px;">Mockup stránky (Scroll Heatmap)<br>Scrollujte dolů...</div>';
                        resizeCanvas();
                        HeatmapRenderer.renderScrollHeatmap(currentData);
                    }
                } else {
                    document.getElementById('page-mockup').innerHTML = `<div class="error">Chyba: ${result.message}</div>`;
                }
            } catch (error) {
                console.error('Chyba při načítání heatmap:', error);
                document.getElementById('page-mockup').innerHTML = `<div class="error">Síťová chyba: ${error.message}</div>`;
            }
        });

        // Export PNG
        document.getElementById('export-png').addEventListener('click', () => {
            const pageUrl = document.getElementById('page-selector').value;
            const type = document.getElementById('type-selector').value;
            const filename = `heatmap_${type}_${Date.now()}.png`;

            HeatmapRenderer.exportToPNG(filename);
        });
    </script>
</body>
</html>
