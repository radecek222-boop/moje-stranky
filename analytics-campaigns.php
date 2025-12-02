<?php
/**
 * Analytics Campaigns - Admin UI pro campaign performance
 *
 * Admin stránka pro zobrazení UTM campaign statistik.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #8 - UTM Campaign Tracking
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit;
}

// Získat CSRF token
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UTM Campaign Performance - WGS Analytics</title>
    <meta name="csrf-token" content="<?php echo htmlspecialchars($csrfToken); ?>">
    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
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
            max-width: 1600px;
            margin: 0 auto;
        }

        h1 {
            color: #333333;
            margin-bottom: 20px;
        }

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

        .btn:hover {
            background: #1a300d;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #545b62;
        }

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

        .stat-card .change {
            font-size: 12px;
            color: #28a745;
            margin-top: 5px;
        }

        .campaigns-table {
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
            cursor: pointer;
            user-select: none;
        }

        th:hover {
            background: #1a300d;
        }

        th.sortable::after {
            content: ' ⇅';
            opacity: 0.5;
        }

        th.sort-asc::after {
            content: ' ↑';
            opacity: 1;
        }

        th.sort-desc::after {
            content: ' ↓';
            opacity: 1;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }

        tr:hover {
            background: #f8f9fa;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 600;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .empty-state h3 {
            margin-bottom: 10px;
            color: #333;
        }

        #timeline-chart {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            height: 300px;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ANALYTICS TABS NAVIGACE -->
        <?php require_once __DIR__ . '/includes/analytics_tabs.php'; ?>

        <h1>UTM Campaign Performance</h1>

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
                    <label>UTM Source:</label>
                    <select id="utm-source">
                        <option value="">Všechny</option>
                        <!-- Dynamically filled -->
                    </select>
                </div>

                <div class="filter-group">
                    <label>UTM Medium:</label>
                    <select id="utm-medium">
                        <option value="">Všechny</option>
                        <!-- Dynamically filled -->
                    </select>
                </div>

                <div class="filter-group">
                    <label>Device Type:</label>
                    <select id="device-type">
                        <option value="">Všechny</option>
                        <option value="desktop">Desktop</option>
                        <option value="mobile">Mobile</option>
                        <option value="tablet">Tablet</option>
                    </select>
                </div>
            </div>

            <div class="filter-row">
                <div class="filter-group">
                    <label>Seskupit podle:</label>
                    <select id="group-by">
                        <option value="campaign">Campaign</option>
                        <option value="source">Source</option>
                        <option value="medium">Medium</option>
                        <option value="full">Vše (source + medium + campaign)</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>Řadit podle:</label>
                    <select id="order-by">
                        <option value="sessions">Sessions</option>
                        <option value="conversions">Conversions</option>
                        <option value="conversion_rate">Conversion Rate</option>
                        <option value="revenue">Revenue</option>
                    </select>
                </div>

                <div class="filter-group">
                    <label>&nbsp;</label>
                    <button id="load-campaigns-btn" class="btn">Načíst data</button>
                    <button id="export-csv-btn" class="btn btn-secondary">Export CSV</button>
                    <a href="admin.php" class="btn btn-secondary" style="text-decoration: none;">← Zpět</a>
                </div>
            </div>
        </div>

        <div class="stats-cards" id="stats-cards" style="display: none;">
            <div class="stat-card">
                <h3>Celkem Sessions</h3>
                <div class="value" id="total-sessions">0</div>
            </div>
            <div class="stat-card">
                <h3>Celkem Conversions</h3>
                <div class="value" id="total-conversions">0</div>
            </div>
            <div class="stat-card">
                <h3>Conversion Rate</h3>
                <div class="value" id="avg-conversion-rate">0%</div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="value" id="total-revenue">0 Kč</div>
            </div>
            <div class="stat-card">
                <h3>Bounce Rate</h3>
                <div class="value" id="avg-bounce-rate">0%</div>
            </div>
        </div>

        <div id="timeline-chart" style="display: none;">
            <canvas id="timeline-canvas"></canvas>
        </div>

        <div class="campaigns-table">
            <div id="loading-message" class="loading">Klikněte na "Načíst data" pro zobrazení campaign statistik</div>

            <table id="campaigns-table" style="display: none;">
                <thead>
                    <tr>
                        <th scope="col" class="sortable" data-sort="utm_campaign">Campaign</th>
                        <th scope="col" class="sortable" data-sort="utm_source">Source</th>
                        <th scope="col" class="sortable" data-sort="utm_medium">Medium</th>
                        <th scope="col" class="sortable" data-sort="device_type">Device</th>
                        <th scope="col" class="sortable" data-sort="total_sessions">Sessions</th>
                        <th scope="col" class="sortable" data-sort="total_conversions">Conversions</th>
                        <th scope="col" class="sortable" data-sort="avg_conversion_rate">Conv. Rate</th>
                        <th scope="col" class="sortable" data-sort="total_revenue">Revenue</th>
                        <th scope="col" class="sortable" data-sort="avg_bounce_rate">Bounce %</th>
                    </tr>
                </thead>
                <tbody id="campaigns-tbody">
                    <!-- Dynamically filled -->
                </tbody>
            </table>

            <div id="empty-state" class="empty-state" style="display: none;">
                <h3>Žádná data</h3>
                <p>Pro zadané filtry nebyla nalezena žádná campaign data.</p>
            </div>
        </div>
    </div>

    <script>
        let campaignsData = [];

        // Event listener pro tlačítko "Načíst data"
        document.getElementById('load-campaigns-btn').addEventListener('click', () => {
            nactiCampaignData();
        });

        // Event listener pro export CSV
        document.getElementById('export-csv-btn').addEventListener('click', () => {
            exportToCSV();
        });

        // Funkce pro načtení campaign data
        async function nactiCampaignData() {
            const dateFrom = document.getElementById('date-from').value;
            const dateTo = document.getElementById('date-to').value;
            const utmSource = document.getElementById('utm-source').value;
            const utmMedium = document.getElementById('utm-medium').value;
            const deviceType = document.getElementById('device-type').value;
            const groupBy = document.getElementById('group-by').value;
            const orderBy = document.getElementById('order-by').value;

            const csrfToken = document.querySelector('[name="csrf_token"]').value;

            // Skrýt tabulku, zobrazit loading
            document.getElementById('campaigns-table').style.display = 'none';
            document.getElementById('empty-state').style.display = 'none';
            document.getElementById('loading-message').style.display = 'block';
            document.getElementById('loading-message').textContent = 'Načítám campaign data...';
            document.getElementById('stats-cards').style.display = 'none';

            try {
                const params = new URLSearchParams({
                    date_from: dateFrom,
                    date_to: dateTo,
                    group_by: groupBy,
                    order_by: orderBy,
                    csrf_token: csrfToken
                });

                if (utmSource) params.append('utm_source', utmSource);
                if (utmMedium) params.append('utm_medium', utmMedium);
                if (deviceType) params.append('device_type', deviceType);

                const response = await fetch(`/api/analytics_campaigns.php?${params.toString()}`);
                const result = await response.json();

                if (result.status === 'success') {
                    // API vrací data v top-level (array_merge pattern)
                    campaignsData = result.campaigns;
                    zobrazitCampaignData(result);
                } else {
                    console.error('[Campaign Dashboard] Chyba:', result.message);
                    document.getElementById('loading-message').textContent = 'Chyba: ' + result.message;
                }
            } catch (error) {
                console.error('[Campaign Dashboard] Síťová chyba:', error);
                document.getElementById('loading-message').textContent = 'Síťová chyba: ' + error.message;
            }
        }

        // Funkce pro zobrazení campaign data
        function zobrazitCampaignData(data) {
            document.getElementById('loading-message').style.display = 'none';

            if (data.campaigns.length === 0) {
                document.getElementById('empty-state').style.display = 'block';
                return;
            }

            // Zobrazit stats cards
            document.getElementById('stats-cards').style.display = 'grid';
            document.getElementById('total-sessions').textContent = formatNumber(data.totals.total_sessions);
            document.getElementById('total-conversions').textContent = formatNumber(data.totals.total_conversions);
            document.getElementById('avg-conversion-rate').textContent = data.totals.avg_conversion_rate.toFixed(2) + '%';
            document.getElementById('total-revenue').textContent = formatNumber(data.totals.total_revenue) + ' Kč';
            document.getElementById('avg-bounce-rate').textContent = data.totals.avg_bounce_rate.toFixed(2) + '%';

            // Zobrazit tabulku
            document.getElementById('campaigns-table').style.display = 'table';
            const tbody = document.getElementById('campaigns-tbody');
            tbody.innerHTML = '';

            data.campaigns.forEach(campaign => {
                const row = document.createElement('tr');

                const conversionRate = campaign.avg_conversion_rate || 0;
                const bounceRate = campaign.avg_bounce_rate || 0;

                const convRateBadge = conversionRate >= 3 ? 'badge-success' : (conversionRate >= 1 ? 'badge-warning' : 'badge-danger');
                const bounceBadge = bounceRate <= 40 ? 'badge-success' : (bounceRate <= 60 ? 'badge-warning' : 'badge-danger');

                row.innerHTML = `
                    <td><strong>${campaign.utm_campaign || '-'}</strong></td>
                    <td>${campaign.utm_source || '-'}</td>
                    <td>${campaign.utm_medium || '-'}</td>
                    <td>${campaign.device_type || '-'}</td>
                    <td>${formatNumber(campaign.total_sessions)}</td>
                    <td>${formatNumber(campaign.total_conversions)}</td>
                    <td><span class="badge ${convRateBadge}">${conversionRate.toFixed(2)}%</span></td>
                    <td>${formatNumber(campaign.total_revenue)} Kč</td>
                    <td><span class="badge ${bounceBadge}">${bounceRate.toFixed(2)}%</span></td>
                `;

                tbody.appendChild(row);
            });
        }

        // Formátování čísel
        function formatNumber(num) {
            return new Intl.NumberFormat('cs-CZ').format(num);
        }

        // Export to CSV
        function exportToCSV() {
            if (campaignsData.length === 0) {
                alert('Nejprve načtěte data');
                return;
            }

            const headers = ['Campaign', 'Source', 'Medium', 'Device', 'Sessions', 'Conversions', 'Conv. Rate %', 'Revenue', 'Bounce %'];
            const rows = campaignsData.map(c => [
                c.utm_campaign || '-',
                c.utm_source || '-',
                c.utm_medium || '-',
                c.device_type || '-',
                c.total_sessions,
                c.total_conversions,
                c.avg_conversion_rate.toFixed(2),
                c.total_revenue.toFixed(2),
                c.avg_bounce_rate.toFixed(2)
            ]);

            let csv = headers.join(',') + '\n';
            rows.forEach(row => {
                csv += row.map(cell => `"${cell}"`).join(',') + '\n';
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = `campaigns_${new Date().toISOString().slice(0, 10)}.csv`;
            link.click();
        }

        // Auto-load on page load
        window.addEventListener('DOMContentLoaded', () => {
            nactiCampaignData();
        });
    </script>
</body>
</html>
