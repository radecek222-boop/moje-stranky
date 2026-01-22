<?php
/**
 * Analýza bounce zpráv z emailové fronty
 * Detekuje neexistující emaily podle skutečných error zpráv z PHPMailer
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized - pouze pro administrátora');
}

$pdo = getDbConnection();

// Bounce patterns - typické chybové zprávy pro neexistující emaily
$bouncePatterns = [
    'user unknown' => 'Uživatel neexistuje',
    'user not found' => 'Uživatel nenalezen',
    'mailbox unavailable' => 'Schránka nedostupná',
    'recipient address rejected' => 'Adresa odmítnuta',
    'no such user' => 'Žádný takový uživatel',
    'invalid recipient' => 'Neplatný příjemce',
    'does not exist' => 'Neexistuje',
    'unknown user' => 'Neznámý uživatel',
    'address rejected' => 'Adresa odmítnuta',
    'mailbox not found' => 'Schránka nenalezena',
    '550 5.1.1' => 'Mailbox neexistuje (SMTP 550)',
    '551 5.1.1' => 'Mailbox neexistuje (SMTP 551)',
    '553 5.1.1' => 'Mailbox neexistuje (SMTP 553)',
    'delivery failed' => 'Doručení selhalo',
    'undeliverable' => 'Nedoručitelné'
];

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analýza bounce zpráv - Neexistující emaily</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f5f5f5;
            padding: 20px;
            line-height: 1.6;
        }
        .container {
            max-width: 1600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border: 2px solid #000;
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
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
            color: #dc3545;
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
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0 30px 0;
        }
        .stat-box {
            background: #fff;
            border: 2px solid #000;
            padding: 20px;
            text-align: center;
        }
        .stat-box.danger {
            border-color: #dc3545;
            background: #fff5f5;
        }
        .stat-number {
            font-size: 42px;
            font-weight: 700;
            color: #000;
        }
        .stat-box.danger .stat-number {
            color: #dc3545;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-top: 5px;
        }
        .info {
            background: #d1ecf1;
            border-left: 3px solid #0c5460;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
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
        .bounced-email {
            background: #fff5f5 !important;
        }
        .email-cell {
            font-family: 'Courier New', monospace;
            font-size: 12px;
            font-weight: 600;
        }
        .error-text {
            color: #dc3545;
            font-size: 12px;
            font-family: 'Courier New', monospace;
        }
        .pattern-match {
            background: #ffc107;
            padding: 2px 4px;
            border-radius: 2px;
            font-weight: 600;
        }
        .summary-box {
            margin-top: 30px;
            padding: 25px;
            background: #f8d7da;
            border: 3px solid #dc3545;
        }
        .summary-box h3 {
            font-size: 20px;
            color: #dc3545;
            margin-bottom: 15px;
        }
        .summary-number {
            font-size: 48px;
            font-weight: 700;
            color: #dc3545;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin.php?tab=notifications&section=management" class="back-btn">‹ Zpět do Email Management</a>

        <h1>Analýza bounce zpráv - Neexistující emaily</h1>

        <div class="info">
            <strong>ℹ️ Jak to funguje:</strong><br>
            Tento nástroj analyzuje error_message sloupec z wgs_email_queue a hledá typické bounce patterns
            pro neexistující emailové schránky (user unknown, mailbox unavailable, 550 5.1.1, atd.).
            <br><br>
            <strong>Na rozdíl od SMTP verifikace</strong>, tento nástroj používá SKUTEČNÉ chybové zprávy
            z pokusů o odeslání emailů přes PHPMailer.
        </div>

        <?php
        // Statistiky
        $stats = [
            'total_emails' => 0,
            'total_attempts' => 0,
            'bounced_emails' => 0,
            'unique_bounced' => 0,
            'other_errors' => 0
        ];

        // Načíst VŠECHNY emaily s error_message (nejen failed)
        $stmt = $pdo->query("
            SELECT
                id,
                recipient_email,
                recipient_name,
                subject,
                status,
                error_message,
                attempts,
                created_at,
                sent_at,
                last_attempt_at
            FROM wgs_email_queue
            WHERE error_message IS NOT NULL
               AND error_message != ''
            ORDER BY last_attempt_at DESC
        ");

        $allEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $stats['total_emails'] = count($allEmails);

        // Analýza bounce zpráv
        $bouncedEmails = [];
        $otherErrors = [];
        $uniqueBouncedAddresses = [];

        foreach ($allEmails as $email) {
            $stats['total_attempts']++;
            $errorMsg = strtolower($email['error_message']);
            $isBounce = false;
            $matchedPattern = '';

            // Kontrola bounce patterns
            foreach ($bouncePatterns as $pattern => $description) {
                if (stripos($errorMsg, $pattern) !== false) {
                    $isBounce = true;
                    $matchedPattern = $description;
                    break;
                }
            }

            if ($isBounce) {
                $stats['bounced_emails']++;
                $email['matched_pattern'] = $matchedPattern;
                $bouncedEmails[] = $email;

                // Počítat unikátní adresy
                $emailAddr = strtolower($email['recipient_email']);
                if (!in_array($emailAddr, $uniqueBouncedAddresses)) {
                    $uniqueBouncedAddresses[] = $emailAddr;
                }
            } else {
                $stats['other_errors']++;
                $otherErrors[] = $email;
            }
        }

        $stats['unique_bounced'] = count($uniqueBouncedAddresses);

        // Statistiky podle bounce patternů
        $patternStats = [];
        foreach ($bouncedEmails as $email) {
            $pattern = $email['matched_pattern'];
            if (!isset($patternStats[$pattern])) {
                $patternStats[$pattern] = 0;
            }
            $patternStats[$pattern]++;
        }
        arsort($patternStats);

        ?>

        <!-- Statistiky -->
        <div class="stats">
            <div class="stat-box">
                <div class="stat-number"><?= $stats['total_emails'] ?></div>
                <div class="stat-label">Celkem s chybou</div>
            </div>
            <div class="stat-box danger">
                <div class="stat-number"><?= $stats['bounced_emails'] ?></div>
                <div class="stat-label">Bounce (neexistuje)</div>
            </div>
            <div class="stat-box danger">
                <div class="stat-number"><?= $stats['unique_bounced'] ?></div>
                <div class="stat-label">Unikátní bounced</div>
            </div>
            <div class="stat-box">
                <div class="stat-number"><?= $stats['other_errors'] ?></div>
                <div class="stat-label">Ostatní chyby</div>
            </div>
        </div>

        <!-- Rozdělení podle bounce patterns -->
        <?php if (count($patternStats) > 0): ?>
        <h2>Rozdělení podle typu bounce (<?= count($patternStats) ?> typů)</h2>
        <table style="max-width: 600px;">
            <thead>
                <tr>
                    <th>Typ chyby</th>
                    <th style="width: 100px;">Počet</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($patternStats as $pattern => $count): ?>
                <tr>
                    <td><?= htmlspecialchars($pattern) ?></td>
                    <td style="font-weight: 600;"><?= $count ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Tabulka: Bounced emaily -->
        <?php if (count($bouncedEmails) > 0): ?>
        <h2>Neexistující emaily - Bounced (<?= count($bouncedEmails) ?> záznamů)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Příjemce</th>
                    <th>Status</th>
                    <th>Bounce typ</th>
                    <th>Chybová zpráva</th>
                    <th>Pokusy</th>
                    <th>Datum</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($bouncedEmails as $email): ?>
                <tr class="bounced-email">
                    <td><?= $email['id'] ?></td>
                    <td class="email-cell"><?= htmlspecialchars($email['recipient_email']) ?></td>
                    <td><?= htmlspecialchars($email['recipient_name'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($email['status']) ?></td>
                    <td><span class="pattern-match"><?= htmlspecialchars($email['matched_pattern']) ?></span></td>
                    <td class="error-text"><?= htmlspecialchars(substr($email['error_message'], 0, 100)) ?><?= strlen($email['error_message']) > 100 ? '...' : '' ?></td>
                    <td><?= $email['attempts'] ?></td>
                    <td><?= $email['last_attempt_at'] ? date('d.m.Y H:i', strtotime($email['last_attempt_at'])) : '-' ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- Seznam unikátních bounced emailů -->
        <?php if (count($uniqueBouncedAddresses) > 0): ?>
        <h2>Unikátní neexistující emailové adresy (<?= count($uniqueBouncedAddresses) ?>)</h2>
        <div style="background: #f8f9fa; padding: 20px; border: 1px solid #ddd; max-height: 400px; overflow-y: auto;">
            <?php foreach ($uniqueBouncedAddresses as $addr): ?>
                <div style="padding: 5px 0; font-family: 'Courier New', monospace; font-size: 13px;">
                    <?= htmlspecialchars($addr) ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Ostatní chyby (prvních 50) -->
        <?php if (count($otherErrors) > 0): ?>
        <h2>Ostatní chyby (prvních 50 z <?= count($otherErrors) ?>)</h2>
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Chybová zpráva</th>
                    <th>Pokusy</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach (array_slice($otherErrors, 0, 50) as $email): ?>
                <tr>
                    <td><?= $email['id'] ?></td>
                    <td class="email-cell"><?= htmlspecialchars($email['recipient_email']) ?></td>
                    <td><?= htmlspecialchars($email['status']) ?></td>
                    <td><?= htmlspecialchars(substr($email['error_message'], 0, 120)) ?><?= strlen($email['error_message']) > 120 ? '...' : '' ?></td>
                    <td><?= $email['attempts'] ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>

        <!-- VÝSLEDNÉ SHRNUTÍ -->
        <div class="summary-box">
            <h3>📊 VÝSLEDEK ANALÝZY</h3>
            <p style="font-size: 16px; margin-bottom: 10px;">
                <strong>Celkem emailů s chybovou zprávou:</strong> <?= $stats['total_emails'] ?>
            </p>
            <p style="font-size: 16px; margin-bottom: 10px;">
                <strong>Z toho bounce (neexistující schránka):</strong> <?= $stats['bounced_emails'] ?>
            </p>
            <div style="border-top: 2px solid #dc3545; margin: 20px 0; padding-top: 20px;">
                <p style="font-size: 18px; font-weight: 600; margin-bottom: 10px;">
                    POČET NEEXISTUJÍCÍCH EMAILOVÝCH ADRES:
                </p>
                <div class="summary-number"><?= $stats['unique_bounced'] ?></div>
                <p style="font-size: 14px; color: #666; margin-top: 10px;">
                    (Unikátní emailové adresy které vrátily bounce zprávu)
                </p>
            </div>
        </div>
    </div>
</body>
</html>
