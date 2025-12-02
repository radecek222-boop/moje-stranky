<?php
/**
 * GDPR Portal - Public + Admin UI
 *
 * Public část: Consent management, data export/deletion requests
 * Admin část: Request processing, audit log viewer
 *
 * @version 1.0.0
 * @date 2025-11-23
 * @module Module #13 - GDPR Compliance Tools
 */

require_once __DIR__ . '/init.php';

$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$csrfToken = $_SESSION['csrf_token'] ?? '';
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GDPR Portal - WGS Analytics</title>
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
            max-width: 1200px;
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
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
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
        .btn-danger {
            background: #dc3545;
        }
        .btn-danger:hover {
            background: #c82333;
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
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Alert */
        .alert {
            padding: 12px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
        }
        .alert-info {
            background: #d1ecf1;
            color: #0c5460;
        }

        /* Checkbox */
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-bottom: 10px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
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

        <h1>GDPR Portal</h1>
        <p class="subtitle">Správa souhlasů a práva subjektů údajů</p>

        <!-- Tabs -->
        <div class="tabs" role="tablist" aria-label="GDPR sekce">
            <button class="tab active" data-tab="consent" role="tab" id="tab-btn-consent" aria-selected="true" aria-controls="tab-consent">Správa souhlasů</button>
            <button class="tab" data-tab="requests" role="tab" id="tab-btn-requests" aria-selected="false" aria-controls="tab-requests">Žádosti o data</button>
            <?php if ($isAdmin): ?>
            <button class="tab" data-tab="admin" role="tab" id="tab-btn-admin" aria-selected="false" aria-controls="tab-admin">Admin (Zpracování)</button>
            <button class="tab" data-tab="audit" role="tab" id="tab-btn-audit" aria-selected="false" aria-controls="tab-audit">Audit Log</button>
            <?php endif; ?>
        </div>

        <!-- Tab: Consent Management -->
        <div class="tab-content active" id="tab-consent" role="tabpanel" aria-labelledby="tab-btn-consent">
            <div class="card">
                <h3>Správa souhlasů</h3>
                <p>Můžete upravit své preference ohledně zpracování osobních údajů.</p>

                <div class="checkbox-group">
                    <input type="checkbox" id="consent-analytics" value="1">
                    <label for="consent-analytics">Analytické cookies (tracking návštěvnosti)</label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="consent-marketing" value="1">
                    <label for="consent-marketing">Marketingové cookies</label>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="consent-functional" value="1" checked disabled>
                    <label for="consent-functional">Funkční cookies (nutné pro provoz webu)</label>
                </div>

                <button class="btn" onclick="saveConsent()">Uložit preference</button>
                <button class="btn btn-danger" onclick="withdrawConsent()">Odvolat všechny souhlasy</button>

                <div id="consent-status"></div>
            </div>
        </div>

        <!-- Tab: Data Requests -->
        <div class="tab-content" id="tab-requests" role="tabpanel" aria-labelledby="tab-btn-requests">
            <div class="card">
                <h3>Žádost o export dat</h3>
                <p>Vyžádejte si kopii všech vašich osobních údajů (GDPR Článek 15).</p>

                <form id="export-form">
                    <div class="form-group">
                        <label for="export-email">Email pro zaslání exportu</label>
                        <input type="email" id="export-email" name="email" required>
                    </div>
                    <button type="submit" class="btn">Požádat o export</button>
                </form>

                <div id="export-status"></div>
            </div>

            <div class="card">
                <h3>Žádost o smazání dat</h3>
                <p>Vyžádejte si smazání všech vašich osobních údajů (GDPR Článek 17 - Právo být zapomenut).</p>

                <form id="deletion-form">
                    <div class="form-group">
                        <label for="deletion-email">Email pro potvrzení</label>
                        <input type="email" id="deletion-email" name="email" required>
                    </div>
                    <button type="submit" class="btn btn-danger">Požádat o smazání</button>
                </form>

                <div id="deletion-status"></div>
            </div>
        </div>

        <?php if ($isAdmin): ?>
        <!-- Tab: Admin Processing -->
        <div class="tab-content" id="tab-admin" role="tabpanel" aria-labelledby="tab-btn-admin">
            <div class="card">
                <h3>Zpracování žádostí (Admin)</h3>
                <div id="admin-requests-list" class="loading">Načítám žádosti...</div>
            </div>
        </div>

        <!-- Tab: Audit Log -->
        <div class="tab-content" id="tab-audit" role="tabpanel" aria-labelledby="tab-btn-audit">
            <div class="card">
                <h3>Audit Log</h3>
                <div id="audit-log-list" class="loading">Načítám audit log...</div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <script>
        const csrfToken = document.querySelector('[name="csrf_token"]').value;
        const isAdmin = <?php echo $isAdmin ? 'true' : 'false'; ?>;

        // Get fingerprint from localStorage (generated by tracker-v2.js)
        const fingerprintId = localStorage.getItem('wgs_fingerprint_id') || 'unknown';

        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const targetTab = tab.dataset.tab;

                // Update tabs - visual and ARIA
                document.querySelectorAll('.tab').forEach(t => {
                    t.classList.remove('active');
                    t.setAttribute('aria-selected', 'false');
                });
                tab.classList.add('active');
                tab.setAttribute('aria-selected', 'true');

                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                document.getElementById('tab-' + targetTab).classList.add('active');

                // Load data for admin tabs
                if (isAdmin && targetTab === 'admin') {
                    loadAdminRequests();
                } else if (isAdmin && targetTab === 'audit') {
                    loadAuditLog();
                }
            });
        });

        // Save consent
        async function saveConsent() {
            const analytics = document.getElementById('consent-analytics').checked ? 1 : 0;
            const marketing = document.getElementById('consent-marketing').checked ? 1 : 0;

            const formData = new FormData();
            formData.append('action', 'record_consent');
            formData.append('csrf_token', csrfToken);
            formData.append('fingerprint_id', fingerprintId);
            formData.append('consent_analytics', analytics);
            formData.append('consent_marketing', marketing);
            formData.append('consent_functional', 1);

            try {
                const response = await fetch('/api/gdpr_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    document.getElementById('consent-status').innerHTML = '<div class="alert alert-success" role="status">Preference uloženy!</div>';
                } else {
                    document.getElementById('consent-status').innerHTML = '<div class="alert alert-error" role="alert">Chyba: ' + result.message + '</div>';
                }
            } catch (error) {
                console.error(error);
                document.getElementById('consent-status').innerHTML = '<div class="alert alert-error" role="alert">Chyba při ukládání</div>';
            }
        }

        // Withdraw consent
        async function withdrawConsent() {
            if (!confirm('Opravdu chcete odvolat všechny souhlasy?')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'withdraw_consent');
            formData.append('csrf_token', csrfToken);
            formData.append('fingerprint_id', fingerprintId);

            try {
                const response = await fetch('/api/gdpr_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    document.getElementById('consent-status').innerHTML = '<div class="alert alert-success" role="status">Souhlasy odvolány</div>';
                    document.getElementById('consent-analytics').checked = false;
                    document.getElementById('consent-marketing').checked = false;
                } else {
                    document.getElementById('consent-status').innerHTML = '<div class="alert alert-error" role="alert">Chyba: ' + result.message + '</div>';
                }
            } catch (error) {
                console.error(error);
            }
        }

        // Export form
        document.getElementById('export-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            const formData = new FormData(e.target);
            formData.append('action', 'request_export');
            formData.append('csrf_token', csrfToken);
            formData.append('fingerprint_id', fingerprintId);

            try {
                const response = await fetch('/api/gdpr_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    document.getElementById('export-status').innerHTML = '<div class="alert alert-success" role="status">Žádost odeslána! Request ID: ' + result.request_id + '</div>';
                    e.target.reset();
                } else {
                    document.getElementById('export-status').innerHTML = '<div class="alert alert-error" role="alert">Chyba: ' + result.message + '</div>';
                }
            } catch (error) {
                console.error(error);
            }
        });

        // Deletion form
        document.getElementById('deletion-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            if (!confirm('VAROVÁNÍ: Tato akce je nevratná. Opravdu chcete smazat všechna data?')) {
                return;
            }

            const formData = new FormData(e.target);
            formData.append('action', 'request_deletion');
            formData.append('csrf_token', csrfToken);
            formData.append('fingerprint_id', fingerprintId);

            try {
                const response = await fetch('/api/gdpr_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    document.getElementById('deletion-status').innerHTML = '<div class="alert alert-success" role="status">Žádost odeslána! Request ID: ' + result.request_id + '</div>';
                    e.target.reset();
                } else {
                    document.getElementById('deletion-status').innerHTML = '<div class="alert alert-error" role="alert">Chyba: ' + result.message + '</div>';
                }
            } catch (error) {
                console.error(error);
            }
        });

        // Admin: Load requests
        async function loadAdminRequests() {
            if (!isAdmin) return;

            try {
                const params = new URLSearchParams({
                    action: 'list_requests',
                    csrf_token: csrfToken,
                    limit: 50
                });

                const response = await fetch(`/api/gdpr_api.php?${params.toString()}`);
                const result = await response.json();

                if (result.status === 'success') {
                    displayAdminRequests(result.requests);
                }
            } catch (error) {
                console.error(error);
            }
        }

        // Display admin requests
        function displayAdminRequests(requests) {
            const container = document.getElementById('admin-requests-list');

            if (!requests || requests.length === 0) {
                container.innerHTML = '<div class="no-data">Žádné requests</div>';
                return;
            }

            let html = '<table><thead><tr>';
            html += '<th scope="col">ID</th><th scope="col">Email</th><th scope="col">Typ</th><th scope="col">Status</th><th scope="col">Vytvořeno</th><th scope="col">Akce</th>';
            html += '</tr></thead><tbody>';

            requests.forEach(req => {
                const statusBadge = req.status === 'completed' ? 'badge-success' : (req.status === 'processing' ? 'badge-warning' : 'badge-info');

                html += '<tr>';
                html += `<td>${req.request_id}</td>`;
                html += `<td>${req.email}</td>`;
                html += `<td>${req.request_type}</td>`;
                html += `<td><span class="badge ${statusBadge}">${req.status}</span></td>`;
                html += `<td>${new Date(req.created_at).toLocaleString('cs-CZ')}</td>`;
                html += '<td>';

                if (req.status === 'pending') {
                    if (req.request_type === 'export') {
                        html += `<button class="btn btn-secondary" onclick="processExport(${req.request_id})">Zpracovat export</button>`;
                    } else if (req.request_type === 'delete') {
                        html += `<button class="btn btn-danger" onclick="processDeletion(${req.request_id})">Zpracovat smazání</button>`;
                    }
                } else if (req.status === 'completed' && req.request_type === 'export') {
                    html += `<a href="/api/gdpr_api.php?action=download_export&request_id=${req.request_id}&csrf_token=${csrfToken}" class="btn">Stáhnout</a>`;
                }

                html += '</td></tr>';
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Process export
        async function processExport(requestId) {
            const formData = new FormData();
            formData.append('action', 'process_export');
            formData.append('csrf_token', csrfToken);
            formData.append('request_id', requestId);

            try {
                const response = await fetch('/api/gdpr_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    alert('Export zpracován!');
                    loadAdminRequests();
                } else {
                    alert('Chyba: ' + result.message);
                }
            } catch (error) {
                console.error(error);
            }
        }

        // Process deletion
        async function processDeletion(requestId) {
            if (!confirm('Opravdu smazat všechna data? Tato akce je nevratná!')) {
                return;
            }

            const formData = new FormData();
            formData.append('action', 'process_deletion');
            formData.append('csrf_token', csrfToken);
            formData.append('request_id', requestId);

            try {
                const response = await fetch('/api/gdpr_api.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.status === 'success') {
                    alert('Data smazána!');
                    loadAdminRequests();
                } else {
                    alert('Chyba: ' + result.message);
                }
            } catch (error) {
                console.error(error);
            }
        }

        // Load audit log
        async function loadAuditLog() {
            if (!isAdmin) return;

            try {
                const params = new URLSearchParams({
                    action: 'audit_log',
                    csrf_token: csrfToken,
                    limit: 100
                });

                const response = await fetch(`/api/gdpr_api.php?${params.toString()}`);
                const result = await response.json();

                if (result.status === 'success') {
                    displayAuditLog(result.logs);
                }
            } catch (error) {
                console.error(error);
            }
        }

        // Display audit log
        function displayAuditLog(logs) {
            const container = document.getElementById('audit-log-list');

            if (!logs || logs.length === 0) {
                container.innerHTML = '<div class="no-data">Žádné záznamy</div>';
                return;
            }

            let html = '<table><thead><tr>';
            html += '<th scope="col">Datum</th><th scope="col">Akce</th><th scope="col">Fingerprint</th><th scope="col">IP</th>';
            html += '</tr></thead><tbody>';

            logs.forEach(log => {
                html += '<tr>';
                html += `<td>${new Date(log.created_at).toLocaleString('cs-CZ')}</td>`;
                html += `<td>${log.action_type}</td>`;
                html += `<td>${log.fingerprint_id || 'N/A'}</td>`;
                html += `<td>${log.user_ip}</td>`;
                html += '</tr>';
            });

            html += '</tbody></table>';
            container.innerHTML = html;
        }

        // Load current consent on page load
        window.addEventListener('DOMContentLoaded', async () => {
            try {
                const params = new URLSearchParams({
                    action: 'check_consent',
                    fingerprint_id: fingerprintId
                });

                const response = await fetch(`/api/gdpr_api.php?${params.toString()}`);
                const result = await response.json();

                if (result.status === 'success' && result.has_consent) {
                    document.getElementById('consent-analytics').checked = result.has_analytics;
                    document.getElementById('consent-marketing').checked = result.has_marketing;
                }
            } catch (error) {
                console.error(error);
            }
        });
    </script>
</body>
</html>
