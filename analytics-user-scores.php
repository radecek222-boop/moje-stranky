<?php
/**
 * Analytics User Scores - Admin UI
 *
 * Admin stránka pro zobrazení engagement, frustration a interest scores.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #10 - User Interest AI Scoring
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
    <title>User Scores - WGS Analytics</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background: #f5f7fa;
            padding: 20px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
        }
        h1 {
            color: #333333;
            margin-bottom: 10px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
        }

        /* Stats Cards */
        .stats-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
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
            text-transform: uppercase;
        }
        .stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #333333;
        }
        .stat-card .label {
            font-size: 12px;
            color: #999;
            margin-top: 5px;
        }

        /* Filters */
        .filters {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .filter-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            align-items: end;
        }
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        .filter-group label {
            font-size: 13px;
            color: #666;
            margin-bottom: 5px;
        }
        .filter-group input,
        .filter-group select {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .btn {
            background: #333333;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .btn:hover {
            background: #1a300d;
        }

        /* Distribution Charts */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .chart-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .chart-card h3 {
            margin-bottom: 15px;
            color: #333;
        }
        .chart-bars {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .bar-row {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .bar-label {
            width: 70px;
            font-size: 12px;
            color: #666;
        }
        .bar-container {
            flex: 1;
            height: 20px;
            background: #f0f0f0;
            border-radius: 4px;
            overflow: hidden;
        }
        .bar-fill {
            height: 100%;
            background: linear-gradient(to right, #4caf50, #333333);
            transition: width 0.3s ease;
        }
        .bar-value {
            width: 50px;
            text-align: right;
            font-size: 12px;
            color: #666;
        }

        /* Table */
        .table-container {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            overflow-x: auto;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th,
        table td {
            text-align: left;
            padding: 12px;
            border-bottom: 1px solid #eee;
        }
        table th {
            background: #f9f9f9;
            font-weight: 600;
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
        }
        table tbody tr:hover {
            background: #f9f9f9;
        }
        .score-badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: bold;
        }
        .score-high {
            background: #d4edda;
            color: #155724;
        }
        .score-medium {
            background: #fff3cd;
            color: #856404;
        }
        .score-low {
            background: #f8d7da;
            color: #721c24;
        }
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ANALYTICS TABS NAVIGACE -->
        <?php require_once __DIR__ . '/includes/analytics_tabs.php'; ?>

        <h1>User Interest AI Scoring</h1>
        <p class="subtitle">Engagement, Frustration a Interest skóre pro každou session</p>

        <!-- Stats Cards -->
        <div class="stats-cards" id="stats-cards" style="display: none;">
            <div class="stat-card">
                <h3>Průměrné Engagement</h3>
                <div class="value" id="avg-engagement">0</div>
                <div class="label">0-100 skóre</div>
            </div>
            <div class="stat-card">
                <h3>Průměrná Frustrace</h3>
                <div class="value" id="avg-frustration">0</div>
                <div class="label">0-100 skóre</div>
            </div>
            <div class="stat-card">
                <h3>Průměrný zájem</h3>
                <div class="value" id="avg-interest">0</div>
                <div class="label">0-100 skóre</div>
            </div>
            <div class="stat-card">
                <h3>Celkem sessions</h3>
                <div class="value" id="total-sessions">0</div>
                <div class="label">se scores</div>
            </div>
        </div>

        <!-- Filters -->
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
                    <label>&nbsp;</label>
                    <button id="load-btn" class="btn">Načíst data</button>
                </div>
            </div>
        </div>

        <!-- Distribution Charts -->
        <div class="charts-grid">
            <div class="chart-card">
                <h3>Distribuce Engagement Scores</h3>
                <div class="chart-bars" id="engagement-chart"></div>
            </div>
            <div class="chart-card">
                <h3>Distribuce Frustration Scores</h3>
                <div class="chart-bars" id="frustration-chart"></div>
            </div>
            <div class="chart-card">
                <h3>Distribuce Interest Scores</h3>
                <div class="chart-bars" id="interest-chart"></div>
            </div>
        </div>

        <!-- Sessions Table -->
        <div class="table-container" aria-live="polite">
            <h3 style="margin-bottom: 15px;">Sessions se Scores</h3>
            <div id="loading-message" class="loading" role="status">Načítám data...</div>
            <table id="scores-table" style="display: none;">
                <thead>
                    <tr>
                        <th scope="col">Session ID</th>
                        <th scope="col">Datum</th>
                        <th scope="col">Engagement</th>
                        <th scope="col">Frustration</th>
                        <th scope="col">Interest</th>
                        <th scope="col">Clicks</th>
                        <th scope="col">Pageviews</th>
                        <th scope="col">Zařízení</th>
                    </tr>
                </thead>
                <tbody id="scores-tbody"></tbody>
            </table>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('[name="csrf_token"]').value;
        let scoresData = [];

        // Load data on button click
        document.getElementById('load-btn').addEventListener('click', () => {
            loadStats();
            loadDistributions();
            loadScores();
        });

        // Auto-load on page load
        window.addEventListener('DOMContentLoaded', () => {
            loadStats();
            loadDistributions();
            loadScores();
        });

        // Load stats
        async function loadStats() {
            const dateFrom = document.getElementById('date-from').value;
            const dateTo = document.getElementById('date-to').value;

            try {
                const params = new URLSearchParams({
                    action: 'stats',
                    date_from: dateFrom,
                    date_to: dateTo,
                    csrf_token: csrfToken
                });

                const response = await fetch(`/api/analytics_user_scores.php?${params.toString()}`);
                const result = await response.json();

                if (result.status === 'success' && result.stats) {
                    const stats = result.stats;
                    document.getElementById('stats-cards').style.display = 'grid';
                    document.getElementById('avg-engagement').textContent = stats.avg_engagement || 0;
                    document.getElementById('avg-frustration').textContent = stats.avg_frustration || 0;
                    document.getElementById('avg-interest').textContent = stats.avg_interest || 0;
                    document.getElementById('total-sessions').textContent = stats.total_sessions || 0;
                }
            } catch (error) {
                console.error('[Stats] Chyba:', error);
            }
        }

        // Load distribution charts
        async function loadDistributions() {
            const dateFrom = document.getElementById('date-from').value;
            const dateTo = document.getElementById('date-to').value;

            const scoreTypes = ['engagement', 'frustration', 'interest'];

            for (const scoreType of scoreTypes) {
                try {
                    const params = new URLSearchParams({
                        action: 'distribution',
                        score_type: scoreType,
                        date_from: dateFrom,
                        date_to: dateTo,
                        csrf_token: csrfToken
                    });

                    const response = await fetch(`/api/analytics_user_scores.php?${params.toString()}`);
                    const result = await response.json();

                    if (result.status === 'success' && result.distribution) {
                        renderChart(scoreType, result.distribution);
                    }
                } catch (error) {
                    console.error(`[Distribution ${scoreType}] Chyba:`, error);
                }
            }
        }

        // Render distribution chart
        function renderChart(scoreType, distribution) {
            const chartId = `${scoreType}-chart`;
            const chartEl = document.getElementById(chartId);
            chartEl.innerHTML = '';

            const maxCount = Math.max(...distribution.map(b => b.count));

            distribution.forEach(bucket => {
                const widthPercent = maxCount > 0 ? (bucket.count / maxCount) * 100 : 0;

                const row = document.createElement('div');
                row.className = 'bar-row';
                row.innerHTML = `
                    <div class="bar-label">${bucket.min}-${bucket.max}</div>
                    <div class="bar-container">
                        <div class="bar-fill" style="width: ${widthPercent}%"></div>
                    </div>
                    <div class="bar-value">${bucket.count}</div>
                `;
                chartEl.appendChild(row);
            });
        }

        // Load scores table
        async function loadScores() {
            const dateFrom = document.getElementById('date-from').value;
            const dateTo = document.getElementById('date-to').value;

            document.getElementById('scores-table').style.display = 'none';
            document.getElementById('loading-message').style.display = 'block';

            try {
                const params = new URLSearchParams({
                    action: 'list',
                    date_from: dateFrom,
                    date_to: dateTo,
                    limit: 100,
                    csrf_token: csrfToken
                });

                const response = await fetch(`/api/analytics_user_scores.php?${params.toString()}`);
                const result = await response.json();

                if (result.status === 'success' && result.scores) {
                    scoresData = result.scores;
                    displayScores(result.scores);
                } else {
                    document.getElementById('loading-message').textContent = 'Chyba: ' + (result.message || 'Neznámá chyba');
                }
            } catch (error) {
                console.error('[Scores] Chyba:', error);
                document.getElementById('loading-message').textContent = 'Síťová chyba: ' + error.message;
            }
        }

        // Display scores table
        function displayScores(scores) {
            document.getElementById('loading-message').style.display = 'none';

            if (scores.length === 0) {
                document.getElementById('loading-message').style.display = 'block';
                document.getElementById('loading-message').textContent = 'Žádné scores pro zadané filtry.';
                return;
            }

            document.getElementById('scores-table').style.display = 'table';
            const tbody = document.getElementById('scores-tbody');
            tbody.innerHTML = '';

            scores.forEach(score => {
                const row = document.createElement('tr');
                row.innerHTML = `
                    <td><code>${score.session_id.substring(0, 16)}...</code></td>
                    <td>${new Date(score.created_at).toLocaleDateString('cs-CZ')}</td>
                    <td>${getScoreBadge(score.engagement_score)}</td>
                    <td>${getScoreBadge(score.frustration_score)}</td>
                    <td>${getScoreBadge(score.interest_score)}</td>
                    <td>${score.total_clicks || 0}</td>
                    <td>${score.total_pageviews || 0}</td>
                    <td>${score.device_type || '-'}</td>
                `;
                tbody.appendChild(row);
            });
        }

        // Get score badge HTML
        function getScoreBadge(score) {
            const val = parseFloat(score) || 0;
            let cssClass = 'score-low';

            if (val >= 70) {
                cssClass = 'score-high';
            } else if (val >= 40) {
                cssClass = 'score-medium';
            }

            return `<span class="score-badge ${cssClass}">${val.toFixed(1)}</span>`;
        }
    </script>
</body>
</html>
