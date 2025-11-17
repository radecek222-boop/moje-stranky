<?php
/**
 * KOMPLEXNÍ SPRÁVA EMAILŮ
 * Historie, fronta, selhavší emaily + možnost znovu odeslat
 * BEZPEČNOST: Pouze pro přihlášené administrátory
 */

require_once __DIR__ . '/init.php';

// KRITICKÉ: Vyžadovat admin session
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins; background: #fff; color: #000; padding: 40px; text-align: center;"><h1>❌ PŘÍSTUP ODEPŘEN</h1><p>Pouze pro administrátory!</p></body></html>');
}

try {
    $pdo = getDbConnection();

    // Filter podle statusu
    $filterStatus = $_GET['status'] ?? 'all';
    $whereClause = '';
    if ($filterStatus !== 'all') {
        $whereClause = "WHERE status = :status";
    }

    // Získat všechny emaily
    $sql = "
        SELECT
            id,
            to_email,
            subject,
            body,
            status,
            retry_count,
            last_error,
            created_at,
            updated_at,
            sent_at
        FROM wgs_email_queue
        $whereClause
        ORDER BY created_at DESC
        LIMIT 200
    ";

    $stmt = $pdo->prepare($sql);
    if ($filterStatus !== 'all') {
        $stmt->execute(['status' => $filterStatus]);
    } else {
        $stmt->execute();
    }
    $emaily = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Statistiky
    $stats = [
        'all' => 0,
        'sent' => 0,
        'pending' => 0,
        'failed' => 0
    ];

    $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM wgs_email_queue GROUP BY status");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $stats[$row['status']] = (int)$row['count'];
        $stats['all'] += (int)$row['count'];
    }

} catch (Exception $e) {
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Chyba</title></head><body style="font-family: Poppins; padding: 40px;"><h1 style="color: #cc0000;">❌ CHYBA</h1><p>' . htmlspecialchars($e->getMessage()) . '</p></body></html>');
}

// CSRF token
require_once __DIR__ . '/includes/csrf_helper.php';
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Management | WGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <meta name="csrf-token" content="<?php echo $csrfToken; ?>">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #fff;
            color: #000;
            padding: 2rem;
        }
        .container {
            max-width: 1600px;
            margin: 0 auto;
            border: 2px solid #000;
        }
        .header {
            background: #000;
            color: #fff;
            padding: 2rem;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .content {
            padding: 2rem;
        }

        /* STATISTIKY */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin: 2rem 0;
        }
        .stat-box {
            border: 2px solid #000;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s;
        }
        .stat-box:hover {
            background: #f5f5f5;
        }
        .stat-box.active {
            background: #000;
            color: #fff;
        }
        .stat-value {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .stat-value.sent { color: #22c55e; }
        .stat-value.pending { color: #f59e0b; }
        .stat-value.failed { color: #ef4444; }
        .stat-box.active .stat-value { color: #fff; }
        .stat-label {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #555;
        }
        .stat-box.active .stat-label { color: #fff; }

        /* TOOLBAR */
        .toolbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.5rem;
            background: #f5f5f5;
            border: 2px solid #ddd;
            margin: 2rem 0;
        }
        .toolbar-left {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            background: #000;
            color: #fff;
            border: 2px solid #000;
            font-family: 'Poppins', sans-serif;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 0.85rem;
        }
        .btn:hover:not(:disabled) {
            background: #fff;
            color: #000;
        }
        .btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .btn.success {
            background: #22c55e;
            border-color: #22c55e;
        }
        .btn.success:hover:not(:disabled) {
            background: #fff;
            color: #22c55e;
        }
        .selected-count {
            font-size: 0.9rem;
            color: #555;
        }

        /* TABULKA */
        .table-wrapper {
            overflow-x: auto;
            border: 2px solid #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #000;
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.75rem;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .badge {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            border-radius: 2px;
        }
        .badge.sent {
            background: #22c55e;
            color: #fff;
        }
        .badge.pending {
            background: #f59e0b;
            color: #fff;
        }
        .badge.failed {
            background: #ef4444;
            color: #fff;
        }

        /* CHECKBOX */
        input[type="checkbox"] {
            width: 18px;
            height: 18px;
            cursor: pointer;
        }

        /* DETAIL TOGGLE */
        .detail-toggle {
            cursor: pointer;
            color: #000;
            text-decoration: underline;
            font-size: 0.85rem;
        }
        .detail-toggle:hover {
            font-weight: 600;
        }
        .email-body {
            background: #f5f5f5;
            border: 1px solid #ddd;
            padding: 1rem;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            max-height: 200px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        .error-detail {
            background: #fef2f2;
            border: 1px solid #ef4444;
            padding: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            font-family: monospace;
            color: #991b1b;
            white-space: pre-wrap;
            word-wrap: break-word;
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #888;
        }
        .empty-state svg {
            width: 64px;
            height: 64px;
            margin-bottom: 1rem;
        }

        /* FOOTER */
        .footer {
            margin-top: 2rem;
            padding: 1.5rem 2rem;
            border-top: 2px solid #ddd;
            text-align: center;
            color: #555;
            font-size: 0.85rem;
        }
        .footer a {
            color: #000;
            text-decoration: none;
            border-bottom: 2px solid #000;
        }

        /* ALERT */
        .alert {
            padding: 1rem;
            margin: 1rem 0;
            border: 2px solid;
            display: none;
        }
        .alert.success {
            background: #f0fdf4;
            border-color: #22c55e;
            color: #15803d;
        }
        .alert.error {
            background: #fef2f2;
            border-color: #ef4444;
            color: #991b1b;
        }
        .alert.show {
            display: block;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 EMAIL MANAGEMENT</h1>
            <p style="margin-top: 0.5rem; opacity: 0.9; font-size: 0.95rem;">Kompletní správa emailové fronty - historie, čekající, selhavší</p>
        </div>

        <div class="content">
            <!-- ALERT -->
            <div id="alert" class="alert"></div>

            <!-- STATISTIKY -->
            <div class="stats-grid">
                <div class="stat-box <?php echo $filterStatus === 'all' ? 'active' : ''; ?>" onclick="filterByStatus('all')">
                    <div class="stat-value"><?php echo $stats['all']; ?></div>
                    <div class="stat-label">Celkem emailů</div>
                </div>
                <div class="stat-box <?php echo $filterStatus === 'sent' ? 'active' : ''; ?>" onclick="filterByStatus('sent')">
                    <div class="stat-value sent"><?php echo $stats['sent']; ?></div>
                    <div class="stat-label">✅ Odesláno</div>
                </div>
                <div class="stat-box <?php echo $filterStatus === 'pending' ? 'active' : ''; ?>" onclick="filterByStatus('pending')">
                    <div class="stat-value pending"><?php echo $stats['pending']; ?></div>
                    <div class="stat-label">⏳ Čeká ve frontě</div>
                </div>
                <div class="stat-box <?php echo $filterStatus === 'failed' ? 'active' : ''; ?>" onclick="filterByStatus('failed')">
                    <div class="stat-value failed"><?php echo $stats['failed']; ?></div>
                    <div class="stat-label">❌ Selhalo</div>
                </div>
            </div>

            <!-- TOOLBAR -->
            <div class="toolbar">
                <div class="toolbar-left">
                    <label>
                        <input type="checkbox" id="select-all" onchange="toggleSelectAll()">
                        <span style="margin-left: 0.5rem; font-size: 0.9rem;">Vybrat vše</span>
                    </label>
                    <span class="selected-count">Vybráno: <strong id="selected-count">0</strong></span>
                </div>
                <button class="btn success" id="resend-btn" onclick="resendSelected()" disabled>
                    🔄 ZNOVU ODESLAT VYBRANÉ
                </button>
            </div>

            <!-- TABULKA -->
            <?php if (count($emaily) > 0): ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 40px;">
                                <input type="checkbox" id="select-all-header" onchange="toggleSelectAll()">
                            </th>
                            <th>ID</th>
                            <th>Status</th>
                            <th>Příjemce</th>
                            <th>Předmět</th>
                            <th>Pokusy</th>
                            <th>Vytvořeno</th>
                            <th>Odesláno</th>
                            <th>Detail</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($emaily as $email): ?>
                        <tr data-email-id="<?php echo $email['id']; ?>">
                            <td>
                                <input type="checkbox" class="email-checkbox" value="<?php echo $email['id']; ?>" onchange="updateSelectedCount()">
                            </td>
                            <td><?php echo $email['id']; ?></td>
                            <td>
                                <span class="badge <?php echo $email['status']; ?>">
                                    <?php
                                        if ($email['status'] === 'sent') echo '✅ SENT';
                                        elseif ($email['status'] === 'pending') echo '⏳ PENDING';
                                        else echo '❌ FAILED';
                                    ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($email['to_email']); ?></td>
                            <td><?php echo htmlspecialchars(substr($email['subject'], 0, 50)); ?><?php echo strlen($email['subject']) > 50 ? '...' : ''; ?></td>
                            <td><?php echo $email['retry_count']; ?> / 3</td>
                            <td><?php echo date('d.m.Y H:i', strtotime($email['created_at'])); ?></td>
                            <td><?php echo $email['sent_at'] ? date('d.m.Y H:i', strtotime($email['sent_at'])) : '-'; ?></td>
                            <td>
                                <span class="detail-toggle" onclick="toggleDetail(<?php echo $email['id']; ?>)">
                                    Zobrazit
                                </span>
                                <div id="detail-<?php echo $email['id']; ?>" style="display: none;">
                                    <div class="email-body">
                                        <strong>Tělo emailu:</strong><br><br>
                                        <?php echo htmlspecialchars($email['body']); ?>
                                    </div>
                                    <?php if ($email['last_error']): ?>
                                    <div class="error-detail">
                                        <strong>Chyba:</strong><br>
                                        <?php echo htmlspecialchars($email['last_error']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                </svg>
                <h3>Žádné emaily nenalezeny</h3>
                <p style="margin-top: 0.5rem;">Pro vybraný filtr neexistují žádné emaily.</p>
            </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <a href="admin.php">← Zpět do Admin Panelu</a>
        </div>
    </div>

    <script>
        // Filter podle statusu
        function filterByStatus(status) {
            window.location.href = `email_management.php?status=${status}`;
        }

        // Toggle select all
        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('select-all');
            const checkboxes = document.querySelectorAll('.email-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
            updateSelectedCount();
        }

        // Update selected count
        function updateSelectedCount() {
            const checkboxes = document.querySelectorAll('.email-checkbox:checked');
            const count = checkboxes.length;
            document.getElementById('selected-count').textContent = count;
            document.getElementById('resend-btn').disabled = count === 0;

            // Sync select-all checkbox
            const allCheckboxes = document.querySelectorAll('.email-checkbox');
            const selectAll = document.getElementById('select-all');
            selectAll.checked = count === allCheckboxes.length && count > 0;
        }

        // Toggle detail
        function toggleDetail(id) {
            const detail = document.getElementById(`detail-${id}`);
            detail.style.display = detail.style.display === 'none' ? 'block' : 'none';
        }

        // Resend selected emails
        async function resendSelected() {
            const checkboxes = document.querySelectorAll('.email-checkbox:checked');
            const ids = Array.from(checkboxes).map(cb => cb.value);

            if (ids.length === 0) {
                showAlert('Nejsou vybrány žádné emaily', 'error');
                return;
            }

            if (!confirm(`Opravdu chcete znovu odeslat ${ids.length} emailů?`)) {
                return;
            }

            const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

            try {
                const response = await fetch('/api/email_resend_api.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        csrf_token: csrfToken,
                        email_ids: ids
                    })
                });

                const data = await response.json();

                if (data.status === 'success') {
                    showAlert(`✅ Úspěch! ${data.count} emailů bylo přesunuto zpět do fronty.`, 'success');
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showAlert(`❌ Chyba: ${data.message}`, 'error');
                }
            } catch (error) {
                showAlert(`❌ Síťová chyba: ${error.message}`, 'error');
            }
        }

        // Show alert
        function showAlert(message, type) {
            const alert = document.getElementById('alert');
            alert.textContent = message;
            alert.className = `alert ${type} show`;
            setTimeout(() => {
                alert.classList.remove('show');
            }, 5000);
        }

        // Initialize
        updateSelectedCount();
    </script>
</body>
</html>
