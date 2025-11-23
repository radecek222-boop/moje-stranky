<?php
/**
 * Analytics Conversions - Admin UI pro conversion tracking
 *
 * Admin stránka pro zobrazení konverzí a funnel analýzy.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #9 - Conversion Funnels
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

$csrfToken = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Conversion Tracking & Funnels - WGS Analytics</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
            padding: 20px;
        }
        .container { max-width: 1600px; margin: 0 auto; }
        h1 { color: #333333; margin-bottom: 20px; }
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #ddd;
        }
        .tab {
            padding: 12px 24px;
            background: white;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            color: #666;
            border-bottom: 3px solid transparent;
        }
        .tab.active {
            color: #333333;
            border-bottom-color: #333333;
        }
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: flex-end;
            margin-bottom: 15px;
        }
        .filter-group {
            flex: 1;
            min-width: 150px;
        }
        .filter-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 5px;
            color: #333;
            font-size: 14px;
        }
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ccc;
            border-radius: 4px;
            font-size: 14px;
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
        .btn:hover { background: #1a300d; }
        .btn-secondary {
            background: #6c757d;
        }
        .btn-secondary:hover { background: #545b62; }
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stat-card h3 {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        .stat-card .value {
            font-size: 28px;
            font-weight: 700;
            color: #333333;
        }
        .conversions-table {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow: hidden;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            background: #333333;
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            font-size: 14px;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }
        tr:hover { background: #f8f9fa; }
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-primary { background: #cce5ff; color: #004085; }
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        .funnel-viz {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .funnel-step {
            margin: 20px 0;
            padding: 20px;
            background: #f8f9fa;
            border-left: 4px solid #333333;
            border-radius: 4px;
        }
        .funnel-step-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        .funnel-step-label {
            font-weight: 700;
            font-size: 16px;
            color: #333;
        }
        .funnel-step-count {
            font-size: 24px;
            font-weight: 700;
            color: #333333;
        }
        .funnel-step-bar {
            height: 30px;
            background: linear-gradient(90deg, #333333 0%, #5a9a2e 100%);
            border-radius: 4px;
            position: relative;
            overflow: hidden;
        }
        .funnel-drop-off {
            color: #721c24;
            font-weight: 600;
            margin-top: 5px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ANALYTICS TABS NAVIGACE -->
        <?php require_once __DIR__ . '/includes/analytics_tabs.php'; ?>

        <h1>Conversion Tracking & Funnels</h1>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('conversions')">Konverze</button>
            <button class="tab" onclick="switchTab('funnels')">Funnels</button>
        </div>

        <!-- TAB 1: Conversions List -->
        <div id="conversions-tab" class="tab-content active">
            <div class="filters">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Datum od:</label>
                        <input type="date" id="date-from" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Datum do:</label>
                        <input type="date" id="date-to" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Typ konverze:</label>
                        <select id="conversion-type">
                            <option value="">Všechny</option>
                            <option value="form_submit">Formulář</option>
                            <option value="login">Login</option>
                            <option value="contact">Kontakt</option>
                            <option value="purchase">Nákup</option>
                            <option value="registration">Registrace</option>
                            <option value="download">Stažení</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button id="load-conversions-btn" class="btn">Načíst data</button>
                        <a href="admin.php" class="btn btn-secondary" style="text-decoration: none;">← Zpět</a>
                    </div>
                </div>
            </div>

            <div class="stats-cards" id="stats-cards" style="display: none;">
                <div class="stat-card">
                    <h3>Celkem Konverzí</h3>
                    <div class="value" id="total-conversions">0</div>
                </div>
                <div class="stat-card">
                    <h3>Celková Hodnota</h3>
                    <div class="value" id="total-value">0 Kč</div>
                </div>
                <div class="stat-card">
                    <h3>Průměrná Hodnota</h3>
                    <div class="value" id="avg-value">0 Kč</div>
                </div>
                <div class="stat-card">
                    <h3>Průměrný Čas</h3>
                    <div class="value" id="avg-time">0s</div>
                </div>
            </div>

            <div class="conversions-table">
                <div id="loading-message" class="loading">Klikněte na "Načíst data" pro zobrazení konverzí</div>

                <table id="conversions-table" style="display: none;">
                    <thead>
                        <tr>
                            <th>Datum</th>
                            <th>Typ</th>
                            <th>Label</th>
                            <th>Hodnota</th>
                            <th>Čas</th>
                            <th>Kroky</th>
                            <th>UTM Campaign</th>
                            <th>Device</th>
                        </tr>
                    </thead>
                    <tbody id="conversions-tbody"></tbody>
                </table>
            </div>
        </div>

        <!-- TAB 2: Funnels -->
        <div id="funnels-tab" class="tab-content">
            <div class="filters">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Vybrat Funnel:</label>
                        <select id="funnel-select">
                            <option value="">-- Načtěte funnely --</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Datum od:</label>
                        <input type="date" id="funnel-date-from" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Datum do:</label>
                        <input type="date" id="funnel-date-to" value="<?php echo date('Y-m-d'); ?>">
                    </div>
                    <div class="filter-group">
                        <label>&nbsp;</label>
                        <button id="analyze-funnel-btn" class="btn">Analyzovat</button>
                    </div>
                </div>
            </div>

            <div id="funnel-loading" class="loading" style="display: none;">Načítám funnel analýzu...</div>
            <div id="funnel-viz" class="funnel-viz" style="display: none;"></div>
        </div>
    </div>

    <script>
        let conversionsData = [];
        const csrfToken = document.querySelector('[name="csrf_token"]').value;

        // Tab switching
        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(tc => tc.classList.remove('active'));

            document.querySelector(`button[onclick="switchTab('${tabName}')"]`).classList.add('active');
            document.getElementById(`${tabName}-tab`).classList.add('active');

            if (tabName === 'funnels') {
                loadFunnels();
            }
        }

        // Load conversions
        document.getElementById('load-conversions-btn').addEventListener('click', () => {
            loadConversions();
        });

        async function loadConversions() {
            const dateFrom = document.getElementById('date-from').value;
            const dateTo = document.getElementById('date-to').value;
            const conversionType = document.getElementById('conversion-type').value;

            document.getElementById('conversions-table').style.display = 'none';
            document.getElementById('loading-message').style.display = 'block';
            document.getElementById('loading-message').textContent = 'Načítám data...';
            document.getElementById('stats-cards').style.display = 'none';

            try {
                const params = new URLSearchParams({
                    action: 'list',
                    date_from: dateFrom,
                    date_to: dateTo,
                    csrf_token: csrfToken
                });

                if (conversionType) params.append('conversion_type', conversionType);

                const response = await fetch(`/api/analytics_conversions.php?${params.toString()}`);
                const result = await response.json();

                if (result.status === 'success' && result.conversions) {
                    conversionsData = result.conversions;
                    displayConversions(result);
                } else {
                    const errorMsg = result.message || 'Neznámá chyba';
                    console.error('[Conversions] Chyba:', errorMsg);
                    document.getElementById('loading-message').style.display = 'block';
                    document.getElementById('loading-message').textContent = 'Chyba: ' + errorMsg;
                    document.getElementById('stats-cards').style.display = 'none';
                    document.getElementById('conversions-table').style.display = 'none';
                }
            } catch (error) {
                console.error('[Conversions] Síťová chyba:', error);
                document.getElementById('loading-message').textContent = 'Síťová chyba: ' + error.message;
            }
        }

        function displayConversions(data) {
            document.getElementById('loading-message').style.display = 'none';

            if (data.conversions.length === 0) {
                document.getElementById('loading-message').style.display = 'block';
                document.getElementById('loading-message').textContent = 'Žádné konverze pro zadané filtry.';
                return;
            }

            // Display stats
            document.getElementById('stats-cards').style.display = 'grid';
            document.getElementById('total-conversions').textContent = formatNumber(data.stats.total_conversions);
            document.getElementById('total-value').textContent = formatNumber(data.stats.total_value) + ' Kč';
            document.getElementById('avg-value').textContent = formatNumber(data.stats.avg_value) + ' Kč';
            document.getElementById('avg-time').textContent = formatTime(data.stats.avg_time_to_conversion);

            // Display table
            document.getElementById('conversions-table').style.display = 'table';
            const tbody = document.getElementById('conversions-tbody');
            tbody.innerHTML = '';

            data.conversions.forEach(conv => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td>${new Date(conv.created_at).toLocaleDateString('cs-CZ')}</td>
                    <td><span class="badge badge-primary">${conv.conversion_type}</span></td>
                    <td>${conv.conversion_label || '-'}</td>
                    <td><strong>${formatNumber(conv.conversion_value)} Kč</strong></td>
                    <td>${formatTime(conv.time_to_conversion)}</td>
                    <td>${conv.steps_to_conversion || 0}</td>
                    <td>${conv.utm_campaign || '-'}</td>
                    <td>${conv.device_type || '-'}</td>
                `;
                tbody.appendChild(row);
            });
        }

        // Load funnels
        async function loadFunnels() {
            try {
                const response = await fetch(`/api/analytics_conversions.php?action=list_funnels&csrf_token=${csrfToken}`);
                const result = await response.json();

                if (result.status === 'success' && result.funnels) {
                    const select = document.getElementById('funnel-select');
                    select.innerHTML = '<option value="">-- Vyberte funnel --</option>';

                    result.funnels.forEach(funnel => {
                        const option = document.createElement('option');
                        option.value = funnel.id;
                        option.textContent = funnel.funnel_name;
                        select.appendChild(option);
                    });
                } else {
                    console.error('[Funnels] Chyba při načítání funnelů:', result.message || 'Neznámá chyba');
                }
            } catch (error) {
                console.error('[Funnels] Chyba:', error);
            }
        }

        // Analyze funnel
        document.getElementById('analyze-funnel-btn').addEventListener('click', async () => {
            const funnelId = document.getElementById('funnel-select').value;
            if (!funnelId) {
                alert('Vyberte funnel');
                return;
            }

            const dateFrom = document.getElementById('funnel-date-from').value;
            const dateTo = document.getElementById('funnel-date-to').value;

            document.getElementById('funnel-loading').style.display = 'block';
            document.getElementById('funnel-viz').style.display = 'none';

            try {
                const params = new URLSearchParams({
                    action: 'funnel_analysis',
                    funnel_id: funnelId,
                    date_from: dateFrom,
                    date_to: dateTo,
                    csrf_token: csrfToken
                });

                const response = await fetch(`/api/analytics_conversions.php?${params.toString()}`);
                const result = await response.json();

                if (result.status === 'success' && result.funnel_analysis) {
                    displayFunnelAnalysis(result.funnel_analysis);
                } else {
                    document.getElementById('funnel-loading').style.display = 'block';
                    document.getElementById('funnel-loading').textContent = 'Chyba: ' + (result.message || 'Neznámá chyba');
                }
            } catch (error) {
                console.error('[Funnel Analysis] Chyba:', error);
                document.getElementById('funnel-loading').style.display = 'block';
                document.getElementById('funnel-loading').textContent = 'Síťová chyba: ' + error.message;
            } finally {
                document.getElementById('funnel-loading').style.display = 'none';
            }
        });

        function displayFunnelAnalysis(analysis) {
            const viz = document.getElementById('funnel-viz');
            viz.style.display = 'block';

            let html = `<h2>${analysis.funnel_name}</h2>`;
            html += `<p>${analysis.funnel_description}</p>`;
            html += `<p><strong>Overall Conversion Rate: ${analysis.overall_conversion_rate}%</strong></p><hr>`;

            const maxCount = Math.max(...analysis.steps.map(s => s.users_count));

            analysis.steps.forEach(step => {
                const widthPercent = (step.users_count / maxCount) * 100;

                html += `<div class="funnel-step">`;
                html += `<div class="funnel-step-header">`;
                html += `<span class="funnel-step-label">Step ${step.step}: ${step.label}</span>`;
                html += `<span class="funnel-step-count">${formatNumber(step.users_count)} users</span>`;
                html += `</div>`;
                html += `<div class="funnel-step-bar" style="width: ${widthPercent}%"></div>`;
                if (step.drop_off_rate > 0) {
                    html += `<div class="funnel-drop-off">Drop-off: ${step.drop_off_rate}%</div>`;
                }
                html += `</div>`;
            });

            viz.innerHTML = html;
        }

        function formatNumber(num) {
            return new Intl.NumberFormat('cs-CZ').format(num || 0);
        }

        function formatTime(seconds) {
            if (!seconds) return '0s';
            const mins = Math.floor(seconds / 60);
            const secs = Math.floor(seconds % 60);
            return mins > 0 ? `${mins}m ${secs}s` : `${secs}s`;
        }

        // Auto-load on page load
        window.addEventListener('DOMContentLoaded', () => {
            loadConversions();
        });
    </script>
</body>
</html>
