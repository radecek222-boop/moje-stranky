<?php
/**
 * Analytics Reports - Admin UI
 *
 * Admin interface pro správu AI-generated reportů.
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #12 - AI Reports Engine
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
    <title>AI Reports - WGS Analytics</title>
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
            margin-bottom: 20px;
        }

        /* Tabs */
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
            border-bottom: 3px solid transparent;
            cursor: pointer;
            font-size: 14px;
            font-weight: 500;
            color: #666;
            transition: all 0.2s;
        }
        .tab:hover {
            color: #333333;
        }
        .tab.active {
            color: #333333;
            border-bottom-color: #333333;
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }
        .tab-content.active {
            display: block;
        }

        /* Card */
        .card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .card h3 {
            margin-bottom: 15px;
            color: #333;
        }

        /* Form */
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-size: 13px;
            font-weight: 500;
            color: #555;
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
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
            font-weight: 500;
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

        /* Table */
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            text-align: left;
            padding: 12px;
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-size: 13px;
            font-weight: 600;
            color: #495057;
        }
        td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
            font-size: 14px;
        }
        tr:hover {
            background: #f8f9fa;
        }

        /* Badge */
        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
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

        /* Loading */
        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        /* No data */
        .no-data {
            text-align: center;
            padding: 40px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- ANALYTICS TABS NAVIGACE -->
        <?php require_once __DIR__ . '/includes/analytics_tabs.php'; ?>

        <h1>AI Reports</h1>
        <p class="subtitle">Automatické generování analytických reportů s AI insights</p>

        <!-- Tabs -->
        <div class="tabs">
            <button class="tab active" data-tab="reports">Reporty</button>
            <button class="tab" data-tab="generate">Generovat nový</button>
            <button class="tab" data-tab="schedules">Naplánované</button>
        </div>

        <!-- Tab: Reports -->
        <div class="tab-content active" id="tab-reports">
            <div class="card">
                <h3>Vygenerované reporty</h3>
                <div id="reports-list" class="loading">Načítám reporty...</div>
            </div>
        </div>

        <!-- Tab: Generate -->
        <div class="tab-content" id="tab-generate">
            <div class="card">
                <h3>Vygenerovat nový report</h3>
                <form id="generate-form">
                    <div class="form-group">
                        <label>Typ reportu</label>
                        <select name="report_type" required>
                            <option value="daily">Daily (denní)</option>
                            <option value="weekly">Weekly (týdenní)</option>
                            <option value="monthly">Monthly (měsíční)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Od data</label>
                        <input type="date" name="date_from" required>
                    </div>
                    <div class="form-group">
                        <label>Do data</label>
                        <input type="date" name="date_to" required>
                    </div>
                    <button type="submit" class="btn">Generovat report</button>
                </form>
                <div id="generate-status"></div>
            </div>
        </div>

        <!-- Tab: Schedules -->
        <div class="tab-content" id="tab-schedules">
            <div class="card">
                <h3>Naplánované reporty</h3>
                <div id="schedules-list" class="loading">Načítám schedules...</div>
            </div>
        </div>
    </div>

    <script>
        const csrfToken = document.querySelector('[name="csrf_token"]').value;

        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;

                // Update tabs
                document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
                tab.classList.add('active');

                // Update content
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                document.getElementById('tab-' + targetTab).classList.add('active');

                // Load data for tab
                if (targetTab === 'reports') {
                    loadReports();
                } else if (targetTab === 'schedules') {
                    loadSchedules();
                }
            });
        });

        // Load reports
        async function loadReports() {
            try {
                const params = new URLSearchParams({
                    action: 'list',
                    csrf_token: csrfToken,
                    limit: 50
                });

                const response = await fetch(`/api/analytics_reports.php?${params.toString()}`);
                const result = await response.json();

                if (result.status === 'success') {
                    displayReports(result.reports);
                } else {
                    document.getElementById('reports-list').innerHTML = '<div class="no-data">Chyba: ' + result.message + '</div>';
                }
            } catch (error) {
                console.error('[Reports] Chyba:', error);
                document.getElementById('reports-list').innerHTML = '<div class="no-data">Chyba při načítání reportů</div>';
            }
        }

        // Display reports
        function displayReports(reports) {
            const container = document.getElementById('reports-list');

            if (!reports || reports.length === 0) {
                container.innerHTML = '<div class="no-data">Žádné reporty</div>';
                return;
            }

            let html = '<table><thead><tr>';
            html += '<th scope="col">ID</th>';
            html += '<th scope="col">Typ</th>';
            html += '<th scope="col">Perioda</th>';
            html += '<th scope="col">Status</th>';
            html += '<th scope="col">Vygenerováno</th>';
            html += '<th scope="col">Akce</th>';
            html += '</tr></thead><tbody>';

            reports.forEach(report => {
                const statusBadge = report.status === 'completed' ? 'badge-success' : 'badge-warning';
                html += '<tr>';
                html += `<td>${report.report_id}</td>`;
                html += `<td>${report.report_type}</td>`;
                html += `<td>${report.report_period_start} - ${report.report_period_end}</td>`;
                html += `<td><span class="badge ${statusBadge}">${report.status}</span></td>`;
                html += `<td>${new Date(report.generated_at).toLocaleString('cs-CZ')}</td>`;
                html += `<td><button class="btn btn-secondary" onclick="downloadReport(${report.report_id})">Stáhnout</button></td>`;
                html += '</tr>';
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Download report
        function downloadReport(reportId) {
            const url = `/api/analytics_reports.php?action=download&report_id=${reportId}&csrf_token=${csrfToken}`;
            window.open(url, '_blank');
        }

        // Generate form submit
        document.getElementById('generate-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            formData.append('action', 'generate');
            formData.append('csrf_token', csrfToken);

            const statusDiv = document.getElementById('generate-status');
            statusDiv.innerHTML = '<div class="loading">Generuji report...</div>';

            try {
                const response = await fetch('/api/analytics_reports.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    statusDiv.innerHTML = '<div style="color: green; padding: 10px;">Report úspěšně vygenerován! ID: ' + result.report_id + '</div>';
                    e.target.reset();

                    // Přepnout na tab Reports
                    setTimeout(() => {
                        document.querySelector('[data-tab="reports"]').click();
                    }, 2000);
                } else {
                    statusDiv.innerHTML = '<div style="color: red; padding: 10px;">Chyba: ' + result.message + '</div>';
                }
            } catch (error) {
                console.error('[Generate] Chyba:', error);
                statusDiv.innerHTML = '<div style="color: red; padding: 10px;">Chyba při generování reportu</div>';
            }
        });

        // Load schedules
        async function loadSchedules() {
            try {
                const params = new URLSearchParams({
                    action: 'schedule_list',
                    csrf_token: csrfToken
                });

                const response = await fetch(`/api/analytics_reports.php?${params.toString()}`);
                const result = await response.json();

                if (result.status === 'success') {
                    displaySchedules(result.schedules);
                } else {
                    document.getElementById('schedules-list').innerHTML = '<div class="no-data">Chyba: ' + result.message + '</div>';
                }
            } catch (error) {
                console.error('[Schedules] Chyba:', error);
                document.getElementById('schedules-list').innerHTML = '<div class="no-data">Chyba při načítání schedules</div>';
            }
        }

        // Display schedules
        function displaySchedules(schedules) {
            const container = document.getElementById('schedules-list');

            if (!schedules || schedules.length === 0) {
                container.innerHTML = '<div class="no-data">Žádné naplánované reporty</div>';
                return;
            }

            let html = '<table><thead><tr>';
            html += '<th scope="col">Název</th>';
            html += '<th scope="col">Typ</th>';
            html += '<th scope="col">Frekvence</th>';
            html += '<th scope="col">Next Run</th>';
            html += '<th scope="col">Status</th>';
            html += '</tr></thead><tbody>';

            schedules.forEach(schedule => {
                const statusBadge = schedule.is_active ? 'badge-success' : 'badge-danger';
                html += '<tr>';
                html += `<td>${schedule.schedule_name}</td>`;
                html += `<td>${schedule.report_type}</td>`;
                html += `<td>${schedule.frequency}</td>`;
                html += `<td>${schedule.next_run_at || 'N/A'}</td>`;
                html += `<td><span class="badge ${statusBadge}">${schedule.is_active ? 'Active' : 'Inactive'}</span></td>`;
                html += '</tr>';
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Initialize - load reports on page load
        window.addEventListener('DOMContentLoaded', () => {
            loadReports();
        });
    </script>
</body>
</html>
