<?php
/**
 * RAW výpis emailové fronty - bez analýzy
 * Zobrazí přesně co je v databázi
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized - pouze pro administrátora');
}

$pdo = getDbConnection();

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
    <title>RAW výpis emailové fronty</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', monospace;
            background: #1a1a1a;
            color: #39ff14;
            padding: 20px;
            line-height: 1.5;
        }
        .container {
            max-width: 100%;
            margin: 0 auto;
        }
        h1 {
            font-size: 20px;
            margin-bottom: 20px;
            border-bottom: 2px solid #39ff14;
            padding-bottom: 10px;
        }
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #39ff14;
            color: #000;
            text-decoration: none;
            font-weight: 600;
            margin-bottom: 20px;
        }
        .stats {
            background: #000;
            border: 2px solid #39ff14;
            padding: 20px;
            margin-bottom: 30px;
        }
        .stat-line {
            margin: 5px 0;
            font-size: 14px;
        }
        .record {
            background: #000;
            border: 1px solid #39ff14;
            padding: 15px;
            margin-bottom: 20px;
        }
        .record-header {
            font-size: 16px;
            font-weight: 700;
            color: #fff;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #39ff14;
        }
        .field {
            margin: 8px 0;
            display: grid;
            grid-template-columns: 200px 1fr;
            gap: 10px;
        }
        .field-label {
            color: #888;
        }
        .field-value {
            color: #39ff14;
            word-break: break-all;
        }
        .error-message {
            background: #2a0000;
            border: 1px solid #ff3333;
            padding: 10px;
            margin-top: 10px;
            color: #ff6666;
            font-size: 12px;
            white-space: pre-wrap;
        }
        .status-sent { color: #28a745; }
        .status-pending { color: #ffc107; }
        .status-failed { color: #dc3545; }
        .status-sending { color: #17a2b8; }
        select, input {
            background: #000;
            color: #39ff14;
            border: 1px solid #39ff14;
            padding: 8px;
            font-family: 'Courier New', monospace;
            margin-right: 10px;
        }
        button {
            background: #39ff14;
            color: #000;
            border: none;
            padding: 10px 20px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Courier New', monospace;
        }
        button:hover {
            background: #2dd50d;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin.php?tab=notifications&section=management" class="back-btn">‹ ZPĚT</a>

        <h1>RAW VÝPIS EMAILOVÉ FRONTY - BEZ ANALÝZY</h1>

        <?php
        // Získat filtry z URL
        $filterStatus = $_GET['status'] ?? 'all';
        $limit = isset($_GET['limit']) ? max(10, min(500, (int)$_GET['limit'])) : 50;
        $search = $_GET['search'] ?? '';

        // Statistiky podle statusu
        $statsStmt = $pdo->query("
            SELECT status, COUNT(*) as count
            FROM wgs_email_queue
            GROUP BY status
        ");
        $statusStats = [];
        $totalCount = 0;
        while ($row = $statsStmt->fetch(PDO::FETCH_ASSOC)) {
            $statusStats[$row['status']] = $row['count'];
            $totalCount += $row['count'];
        }

        // Statistiky error_message
        $errorStmt = $pdo->query("
            SELECT
                COUNT(*) as with_error,
                COUNT(CASE WHEN error_message IS NULL OR error_message = '' THEN 1 END) as without_error
            FROM wgs_email_queue
        ");
        $errorStats = $errorStmt->fetch(PDO::FETCH_ASSOC);
        ?>

        <div class="stats">
            <div class="stat-line"><strong>CELKEM ZÁZNAMŮ:</strong> <?= $totalCount ?></div>
            <div class="stat-line">
                <?php foreach ($statusStats as $status => $count): ?>
                    <span class="status-<?= $status ?>"><?= strtoupper($status) ?>: <?= $count ?></span>
                    &nbsp;&nbsp;
                <?php endforeach; ?>
            </div>
            <div class="stat-line"><strong>S ERROR_MESSAGE:</strong> <?= $errorStats['with_error'] ?></div>
            <div class="stat-line"><strong>BEZ ERROR_MESSAGE:</strong> <?= $errorStats['without_error'] ?></div>
        </div>

        <!-- Filtry -->
        <form method="GET" style="margin-bottom: 20px; background: #000; border: 1px solid #39ff14; padding: 15px;">
            <label>STATUS:
                <select name="status">
                    <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>VŠE</option>
                    <option value="sent" <?= $filterStatus === 'sent' ? 'selected' : '' ?>>SENT</option>
                    <option value="pending" <?= $filterStatus === 'pending' ? 'selected' : '' ?>>PENDING</option>
                    <option value="failed" <?= $filterStatus === 'failed' ? 'selected' : '' ?>>FAILED</option>
                    <option value="sending" <?= $filterStatus === 'sending' ? 'selected' : '' ?>>SENDING</option>
                </select>
            </label>

            <label>LIMIT:
                <input type="number" name="limit" value="<?= $limit ?>" min="10" max="500" style="width: 80px;">
            </label>

            <label>HLEDAT EMAIL:
                <input type="text" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="@example.com" style="width: 200px;">
            </label>

            <button type="submit">FILTROVAT</button>
        </form>

        <?php
        // Sestavit SQL dotaz
        $whereConditions = [];
        $params = [];

        if ($filterStatus !== 'all') {
            $whereConditions[] = "status = :status";
            $params[':status'] = $filterStatus;
        }

        if ($search !== '') {
            $whereConditions[] = "recipient_email LIKE :search";
            $params[':search'] = '%' . $search . '%';
        }

        $whereClause = count($whereConditions) > 0 ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

        $sql = "
            SELECT
                id,
                recipient_email,
                recipient_name,
                subject,
                body,
                status,
                attempts,
                max_attempts,
                error_message,
                created_at,
                scheduled_at,
                sent_at,
                last_attempt_at,
                notification_id,
                cc_emails,
                bcc_emails
            FROM wgs_email_queue
            $whereClause
            ORDER BY created_at DESC
            LIMIT :limit
        ";

        $stmt = $pdo->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<h2 style='margin: 20px 0; color: #fff;'>ZÁZNAMY (ZOBRAZENO: " . count($records) . ")</h2>";

        foreach ($records as $record):
        ?>

        <div class="record">
            <div class="record-header">
                ID: <?= $record['id'] ?> |
                STATUS: <span class="status-<?= $record['status'] ?>"><?= strtoupper($record['status']) ?></span> |
                POKUSY: <?= $record['attempts'] ?>/<?= $record['max_attempts'] ?>
            </div>

            <div class="field">
                <div class="field-label">RECIPIENT_EMAIL:</div>
                <div class="field-value"><?= htmlspecialchars($record['recipient_email']) ?></div>
            </div>

            <div class="field">
                <div class="field-label">RECIPIENT_NAME:</div>
                <div class="field-value"><?= htmlspecialchars($record['recipient_name'] ?? 'NULL') ?></div>
            </div>

            <div class="field">
                <div class="field-label">SUBJECT:</div>
                <div class="field-value"><?= htmlspecialchars($record['subject']) ?></div>
            </div>

            <div class="field">
                <div class="field-label">NOTIFICATION_ID:</div>
                <div class="field-value"><?= htmlspecialchars($record['notification_id'] ?? 'NULL') ?></div>
            </div>

            <div class="field">
                <div class="field-label">CREATED_AT:</div>
                <div class="field-value"><?= $record['created_at'] ?></div>
            </div>

            <div class="field">
                <div class="field-label">SENT_AT:</div>
                <div class="field-value"><?= $record['sent_at'] ?? 'NULL' ?></div>
            </div>

            <div class="field">
                <div class="field-label">LAST_ATTEMPT_AT:</div>
                <div class="field-value"><?= $record['last_attempt_at'] ?? 'NULL' ?></div>
            </div>

            <?php if ($record['cc_emails']): ?>
            <div class="field">
                <div class="field-label">CC_EMAILS:</div>
                <div class="field-value"><?= htmlspecialchars($record['cc_emails']) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($record['bcc_emails']): ?>
            <div class="field">
                <div class="field-label">BCC_EMAILS:</div>
                <div class="field-value"><?= htmlspecialchars($record['bcc_emails']) ?></div>
            </div>
            <?php endif; ?>

            <?php if ($record['error_message']): ?>
            <div class="field" style="grid-template-columns: 1fr;">
                <div class="field-label">ERROR_MESSAGE:</div>
                <div class="error-message"><?= htmlspecialchars($record['error_message']) ?></div>
            </div>
            <?php else: ?>
            <div class="field">
                <div class="field-label">ERROR_MESSAGE:</div>
                <div class="field-value" style="color: #888;">NULL (žádná chyba)</div>
            </div>
            <?php endif; ?>

            <details style="margin-top: 10px;">
                <summary style="cursor: pointer; color: #888;">ZOBRAZIT TĚLO EMAILU (BODY)</summary>
                <div class="error-message" style="border-color: #39ff14; color: #39ff14; max-height: 300px; overflow-y: auto;">
                    <?= htmlspecialchars(substr($record['body'], 0, 2000)) ?>
                    <?php if (strlen($record['body']) > 2000): ?>
                        <div style="color: #888; margin-top: 10px;">[... zkráceno, celkem <?= strlen($record['body']) ?> znaků]</div>
                    <?php endif; ?>
                </div>
            </details>
        </div>

        <?php endforeach; ?>

        <?php if (count($records) === 0): ?>
        <div style="background: #2a0000; border: 2px solid #ff3333; padding: 30px; text-align: center; color: #ff6666; font-size: 18px;">
            ŽÁDNÉ ZÁZNAMY NEBYLY NALEZENY
        </div>
        <?php endif; ?>

    </div>
</body>
</html>
