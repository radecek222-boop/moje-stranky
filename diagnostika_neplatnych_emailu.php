<?php
/**
 * Diagnostika neplatných emailů ve frontě
 * Zkontroluje kolik failed emailů má neplatnou emailovou adresu
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostika neplatných emailů</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border: 2px solid #000;
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 3px solid #000;
            padding-bottom: 10px;
        }
        h2 {
            font-size: 18px;
            font-weight: 600;
            margin: 30px 0 15px 0;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-box {
            background: #fff;
            border: 2px solid #000;
            padding: 20px;
            text-align: center;
        }
        .stat-number {
            font-size: 36px;
            font-weight: 700;
            color: #000;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 5px;
        }
        .stat-box.warning {
            border-color: #dc3545;
            background: #fff5f5;
        }
        .stat-box.warning .stat-number {
            color: #dc3545;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        thead {
            background: #000;
            color: #fff;
        }
        th {
            padding: 12px;
            text-align: left;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        td {
            padding: 10px 12px;
            border: 1px solid #ddd;
            font-size: 13px;
        }
        tbody tr:nth-child(even) {
            background: #f9f9f9;
        }
        .invalid-email {
            background: #fff5f5 !important;
        }
        .email-error {
            color: #dc3545;
            font-weight: 600;
            font-size: 12px;
        }
        .code {
            font-family: 'Courier New', monospace;
            background: #f0f0f0;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
        }
        .back-btn {
            display: inline-block;
            padding: 10px 20px;
            background: #000;
            color: #fff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 20px;
        }
        .back-btn:hover {
            background: #333;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin.php?tab=notifications&section=management&filter=failed" class="back-btn">‹ Zpět do Email Management</a>

        <h1>Diagnostika neplatných emailů</h1>

        <?php
        // Statistiky emailové fronty
        $stats = [
            'total_failed' => 0,
            'invalid_format' => 0,
            'mailbox_not_found' => 0,
            'other_errors' => 0
        ];

        // Načíst všechny failed emaily
        $stmt = $pdo->query("
            SELECT
                id,
                recipient_email,
                recipient_name,
                subject,
                error_message,
                attempts,
                created_at,
                scheduled_at,
                sent_at
            FROM wgs_email_queue
            WHERE status = 'failed'
            ORDER BY created_at DESC
        ");

        $failedEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['total_failed'] = count($failedEmails);

        // Analýza emailů
        $invalidEmails = [];
        $notFoundEmails = [];
        $otherErrors = [];

        foreach ($failedEmails as $email) {
            $emailAddr = $email['recipient_email'];
            $error = $email['error_message'] ?? '';

            // 1. Kontrola formátu emailu
            if (!filter_var($emailAddr, FILTER_VALIDATE_EMAIL)) {
                $stats['invalid_format']++;
                $email['diagnosis'] = 'Neplatný formát emailu';
                $invalidEmails[] = $email;
                continue;
            }

            // 2. Kontrola chyb "mailbox not found"
            if (stripos($error, 'not found') !== false ||
                stripos($error, 'does not exist') !== false ||
                stripos($error, 'unknown user') !== false ||
                stripos($error, 'user unknown') !== false ||
                stripos($error, 'mailbox unavailable') !== false ||
                stripos($error, 'no such user') !== false ||
                stripos($error, 'recipient address rejected') !== false) {
                $stats['mailbox_not_found']++;
                $email['diagnosis'] = 'Email neexistuje (mailbox not found)';
                $notFoundEmails[] = $email;
                continue;
            }

            // 3. Ostatní chyby
            $stats['other_errors']++;
            $email['diagnosis'] = 'Jiná chyba';
            $otherErrors[] = $email;
        }

        ?>

        <!-- Statistiky -->
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_failed'] ?></div>
                <div class="stat-label">Celkem failed</div>
            </div>
            <div class="stat-box warning">
                <div class="stat-number"><?= $stats['invalid_format'] ?></div>
                <div class="stat-label">Neplatný formát</div>
            </div>
            <div class="stat-box warning">
                <div class="stat-number"><?= $stats['mailbox_not_found'] ?></div>
                <div class="stat-label">Email neexistuje</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['other_errors'] ?></div>
                <div class="stat-label">Ostatní chyby</div>
            </div>
        </div>

        <!-- Tabulka: Neplatný formát emailu -->
        <?php if (count($invalidEmails) > 0): ?>
        <h2>Neplatný formát emailu (<?= count($invalidEmails) ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Příjemce</th>
                    <th>Předmět</th>
                    <th>Pokusy</th>
                    <th>Vytvořeno</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($invalidEmails as $email): ?>
                <tr class="invalid-email">
                    <td><?= $email['id'] ?></td>
                    <td class="code"><?= htmlspecialchars($email['recipient_email']) ?></td>
                    <td><?= htmlspecialchars($email['recipient_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars(substr($email['subject'], 0, 50)) ?><?= strlen($email['subject']) > 50 ? '...' : '' ?></td>
                    <td><?= $email['attempts'] ?></td>
                    <td><?= date('d.m.Y H:i', strtotime($email['created_at'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Tabulka: Email neexistuje -->
        <?php if (count($notFoundEmails) > 0): ?>
        <h2>Email neexistuje - Mailbox not found (<?= count($notFoundEmails) ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Příjemce</th>
                    <th>Předmět</th>
                    <th>Chybová zpráva</th>
                    <th>Pokusy</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notFoundEmails as $email): ?>
                <tr class="invalid-email">
                    <td><?= $email['id'] ?></td>
                    <td class="code"><?= htmlspecialchars($email['recipient_email']) ?></td>
                    <td><?= htmlspecialchars($email['recipient_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars(substr($email['subject'], 0, 40)) ?><?= strlen($email['subject']) > 40 ? '...' : '' ?></td>
                    <td><span class="email-error"><?= htmlspecialchars(substr($email['error_message'], 0, 80)) ?><?= strlen($email['error_message']) > 80 ? '...' : '' ?></span></td>
                    <td><?= $email['attempts'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Tabulka: Ostatní chyby -->
        <?php if (count($otherErrors) > 0): ?>
        <h2>Ostatní chyby (<?= count($otherErrors) ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Předmět</th>
                    <th>Chybová zpráva</th>
                    <th>Pokusy</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($otherErrors, 0, 50) as $email): ?>
                <tr>
                    <td><?= $email['id'] ?></td>
                    <td class="code"><?= htmlspecialchars($email['recipient_email']) ?></td>
                    <td><?= htmlspecialchars(substr($email['subject'], 0, 40)) ?><?= strlen($email['subject']) > 40 ? '...' : '' ?></td>
                    <td><?= htmlspecialchars(substr($email['error_message'], 0, 100)) ?><?= strlen($email['error_message']) > 100 ? '...' : '' ?></td>
                    <td><?= $email['attempts'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($otherErrors) > 50): ?>
        <p style="margin-top: 10px; font-size: 13px; color: #666;">Zobrazeno prvních 50 z <?= count($otherErrors) ?> záznamů</p>
        <?php endif; ?>
        <?php endif; ?>

        <h2>Shrnutí</h2>
        <ul style="margin-left: 20px; margin-top: 10px;">
            <li><strong>Celkem failed emailů:</strong> <?= $stats['total_failed'] ?></li>
            <li style="color: #dc3545;"><strong>Neplatný formát emailové adresy:</strong> <?= $stats['invalid_format'] ?></li>
            <li style="color: #dc3545;"><strong>Email neexistuje (mailbox not found):</strong> <?= $stats['mailbox_not_found'] ?></li>
            <li><strong>Ostatní chyby (SMTP, síť, atd.):</strong> <?= $stats['other_errors'] ?></li>
        </ul>

        <p style="margin-top: 20px; padding: 15px; background: #f0f0f0; border-left: 3px solid #000;">
            <strong>Celkem neplatných/neexistujících emailů:</strong>
            <span style="font-size: 20px; font-weight: 700;"><?= $stats['invalid_format'] + $stats['mailbox_not_found'] ?></span>
        </p>
    </div>
</body>
</html>
