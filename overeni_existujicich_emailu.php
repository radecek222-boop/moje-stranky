<?php
/**
 * Aktivní ověření existence emailových adres
 * Pomocí SMTP verifikace zkontroluje které emaily skutečně existují
 */

require_once __DIR__ . '/init.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Unauthorized - pouze pro administrátora');
}

set_time_limit(300); // 5 minut timeout

$pdo = getDbConnection();

// Funkce pro ověření existence emailu pomocí SMTP
function overitExistenciEmailu($email) {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return [
            'valid' => false,
            'status' => 'invalid_format',
            'message' => 'Neplatný formát emailu'
        ];
    }

    $domain = substr(strrchr($email, "@"), 1);

    // 1. Kontrola MX záznamů
    if (!getmxrr($domain, $mxhosts, $mxweight)) {
        return [
            'valid' => false,
            'status' => 'no_mx',
            'message' => 'Doména nemá MX záznam'
        ];
    }

    array_multisort($mxweight, $mxhosts);
    $mxHost = $mxhosts[0];

    // 2. SMTP verifikace
    $timeout = 10;
    $errno = 0;
    $errstr = '';

    // Pokus o připojení k mail serveru
    $socket = @fsockopen($mxHost, 25, $errno, $errstr, $timeout);

    if (!$socket) {
        return [
            'valid' => false,
            'status' => 'connection_failed',
            'message' => "Nelze se připojit k mail serveru: $errstr"
        ];
    }

    stream_set_timeout($socket, $timeout);
    $response = fgets($socket);

    // HELO
    fputs($socket, "HELO wgs-service.cz\r\n");
    $response = fgets($socket);

    // MAIL FROM
    fputs($socket, "MAIL FROM: <verify@wgs-service.cz>\r\n");
    $response = fgets($socket);

    // RCPT TO - tady zjistíme jestli email existuje
    fputs($socket, "RCPT TO: <$email>\r\n");
    $response = fgets($socket);

    // QUIT
    fputs($socket, "QUIT\r\n");
    fclose($socket);

    // Vyhodnocení odpovědi
    // 250 = OK, email existuje
    // 550, 551, 553 = Email neexistuje / mailbox unavailable
    if (preg_match("/^250/", $response)) {
        return [
            'valid' => true,
            'status' => 'exists',
            'message' => 'Email existuje',
            'smtp_response' => trim($response)
        ];
    } elseif (preg_match("/^(550|551|553)/", $response)) {
        return [
            'valid' => false,
            'status' => 'mailbox_not_found',
            'message' => 'Email neexistuje',
            'smtp_response' => trim($response)
        ];
    } else {
        return [
            'valid' => null,
            'status' => 'uncertain',
            'message' => 'Nelze ověřit (server neodpověděl jednoznačně)',
            'smtp_response' => trim($response)
        ];
    }
}

// Zpracování POST requestu (ověření jednotlivého emailu)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_email'])) {
    header('Content-Type: application/json');
    $email = $_POST['verify_email'];
    $result = overitExistenciEmailu($email);
    echo json_encode($result);
    exit;
}

// Načtení všech unikátních emailů z fronty
$uniqueEmails = [];
$emailStats = [];

try {
    // Načíst všechny unikátní emaily (sent, pending, failed)
    $stmt = $pdo->query("
        SELECT
            recipient_email,
            status,
            COUNT(*) as count,
            MAX(created_at) as last_sent
        FROM wgs_email_queue
        GROUP BY recipient_email, status
        ORDER BY recipient_email, status
    ");

    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $row) {
        $email = $row['recipient_email'];
        if (!isset($uniqueEmails[$email])) {
            $uniqueEmails[$email] = [
                'email' => $email,
                'statuses' => [],
                'total_count' => 0,
                'last_sent' => $row['last_sent']
            ];
        }
        $uniqueEmails[$email]['statuses'][$row['status']] = $row['count'];
        $uniqueEmails[$email]['total_count'] += $row['count'];
    }

} catch (PDOException $e) {
    $uniqueEmails = [];
}

// Statistiky
$totalEmails = count($uniqueEmails);

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ověření existence emailů - SMTP verifikace</title>
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
        .warning {
            background: #fff3cd;
            border-left: 3px solid #ffc107;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
        }
        .info {
            background: #d1ecf1;
            border-left: 3px solid #0c5460;
            padding: 15px;
            margin: 20px 0;
            font-size: 14px;
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
        .verify-all-btn {
            display: inline-block;
            padding: 12px 30px;
            background: #28a745;
            color: #fff;
            border: none;
            font-size: 14px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            cursor: pointer;
            margin: 20px 0;
        }
        .verify-all-btn:hover {
            background: #218838;
        }
        .verify-all-btn:disabled {
            background: #999;
            cursor: not-allowed;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
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
        .email-cell {
            font-family: 'Courier New', monospace;
            font-size: 12px;
        }
        .status-pending {
            background: #fff3cd;
            color: #856404;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-valid {
            background: #d4edda;
            color: #155724;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-invalid {
            background: #f8d7da;
            color: #721c24;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
        .status-uncertain {
            background: #e2e3e5;
            color: #383d41;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
        .verify-btn {
            background: #000;
            color: #fff;
            border: none;
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            cursor: pointer;
        }
        .verify-btn:hover {
            background: #333;
        }
        .verify-btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .progress {
            margin: 20px 0;
            padding: 15px;
            background: #f0f0f0;
            border: 2px solid #000;
            display: none;
        }
        .progress.active {
            display: block;
        }
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #fff;
            border: 1px solid #000;
            position: relative;
        }
        .progress-fill {
            height: 100%;
            background: #28a745;
            transition: width 0.3s;
        }
        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: 600;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="admin.php?tab=notifications&section=management" class="back-btn">‹ Zpět do Email Management</a>

        <h1>Aktivní ověření existence emailů (SMTP verifikace)</h1>

        <div class="warning">
            <strong>⚠️ UPOZORNĚNÍ:</strong> Tento nástroj provádí aktivní SMTP verifikaci emailových adres.
            Proces může trvat několik minut a může být detekován některými anti-spam systémy.
            Doporučujeme spustit ověření mimo špičku.
        </div>

        <div class="info">
            <strong>ℹ️ Jak to funguje:</strong><br>
            1. Načte všechny unikátní emaily z wgs_email_queue<br>
            2. Pro každý email provede:<br>
            &nbsp;&nbsp;&nbsp;• DNS MX record check (existuje mail server pro doménu?)<br>
            &nbsp;&nbsp;&nbsp;• SMTP připojení k mail serveru<br>
            &nbsp;&nbsp;&nbsp;• RCPT TO test (přijme server tohoto příjemce?)<br>
            3. Zobrazí výsledek: <span class="status-valid">EXISTUJE</span>, <span class="status-invalid">NEEXISTUJE</span>, nebo <span class="status-uncertain">NEJISTÉ</span>
        </div>

        <p style="margin: 20px 0; font-size: 16px;">
            <strong>Celkem unikátních emailů ve frontě:</strong> <?= $totalEmails ?>
        </p>

        <button class="verify-all-btn" id="verifyAllBtn" onclick="verifyAllEmails()">
            Spustit ověření všech emailů
        </button>

        <div class="progress" id="progressBar">
            <div style="margin-bottom: 10px; font-weight: 600;">Probíhá ověřování...</div>
            <div class="progress-bar">
                <div class="progress-fill" id="progressFill" style="width: 0%;"></div>
                <div class="progress-text" id="progressText">0 / <?= $totalEmails ?></div>
            </div>
            <div style="margin-top: 10px; font-size: 12px;" id="progressStatus">Načítání...</div>
        </div>

        <table id="emailTable">
            <thead>
                <tr>
                    <th style="width: 50px;">#</th>
                    <th>Email</th>
                    <th style="width: 120px;">Status ve frontě</th>
                    <th style="width: 100px;">Počet v DB</th>
                    <th style="width: 150px;">Stav verifikace</th>
                    <th style="width: 300px;">Zpráva</th>
                    <th style="width: 100px;">Akce</th>
                </tr>
            </thead>
            <tbody>
                <?php $index = 1; foreach ($uniqueEmails as $emailData): ?>
                <tr id="row-<?= $index ?>">
                    <td><?= $index ?></td>
                    <td class="email-cell"><?= htmlspecialchars($emailData['email']) ?></td>
                    <td>
                        <?php foreach ($emailData['statuses'] as $status => $count): ?>
                            <span style="font-size: 11px;"><?= $status ?>: <?= $count ?></span><br>
                        <?php endforeach; ?>
                    </td>
                    <td><?= $emailData['total_count'] ?></td>
                    <td id="status-<?= $index ?>">
                        <span class="status-pending">ČEKÁ NA OVĚŘENÍ</span>
                    </td>
                    <td id="message-<?= $index ?>">-</td>
                    <td>
                        <button class="verify-btn" onclick="verifyEmail('<?= htmlspecialchars($emailData['email'], ENT_QUOTES) ?>', <?= $index ?>)">
                            Ověřit
                        </button>
                    </td>
                </tr>
                <?php $index++; endforeach; ?>
            </tbody>
        </table>

        <div style="margin-top: 30px; padding: 20px; background: #f0f0f0; border-left: 3px solid #000;">
            <h3 style="margin-bottom: 10px;">Výsledky budou zobrazeny po ověření</h3>
            <div id="results" style="font-size: 14px;">
                <div>✅ Platné emaily: <strong id="validCount">0</strong></div>
                <div>❌ Neplatné/neexistující: <strong id="invalidCount">0</strong></div>
                <div>❓ Nejisté: <strong id="uncertainCount">0</strong></div>
            </div>
        </div>
    </div>

    <script>
    let verifiedCount = 0;
    let validCount = 0;
    let invalidCount = 0;
    let uncertainCount = 0;
    const totalEmails = <?= $totalEmails ?>;

    async function verifyEmail(email, index) {
        const statusCell = document.getElementById('status-' + index);
        const messageCell = document.getElementById('message-' + index);

        statusCell.innerHTML = '<span class="status-pending">OVĚŘUJI...</span>';
        messageCell.textContent = 'Připojování k mail serveru...';

        try {
            const formData = new FormData();
            formData.append('verify_email', email);

            const response = await fetch('', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.valid === true) {
                statusCell.innerHTML = '<span class="status-valid">EXISTUJE ✓</span>';
                validCount++;
            } else if (result.valid === false) {
                statusCell.innerHTML = '<span class="status-invalid">NEEXISTUJE ✗</span>';
                invalidCount++;
            } else {
                statusCell.innerHTML = '<span class="status-uncertain">NEJISTÉ ?</span>';
                uncertainCount++;
            }

            messageCell.textContent = result.message;
            if (result.smtp_response) {
                messageCell.title = result.smtp_response;
            }

            verifiedCount++;
            updateResults();

        } catch (error) {
            statusCell.innerHTML = '<span class="status-invalid">CHYBA</span>';
            messageCell.textContent = 'Chyba: ' + error.message;
            uncertainCount++;
            verifiedCount++;
            updateResults();
        }
    }

    async function verifyAllEmails() {
        const btn = document.getElementById('verifyAllBtn');
        const progress = document.getElementById('progressBar');

        btn.disabled = true;
        progress.classList.add('active');

        const emails = <?= json_encode(array_values($uniqueEmails)) ?>;

        for (let i = 0; i < emails.length; i++) {
            const email = emails[i].email;
            const index = i + 1;

            // Update progress
            const percentage = Math.round((i / emails.length) * 100);
            document.getElementById('progressFill').style.width = percentage + '%';
            document.getElementById('progressText').textContent = i + ' / ' + totalEmails;
            document.getElementById('progressStatus').textContent = 'Ověřuji: ' + email;

            await verifyEmail(email, index);

            // Malá pauza mezi požadavky (aby se nespamovaly mail servery)
            await new Promise(resolve => setTimeout(resolve, 500));
        }

        // Dokončeno
        document.getElementById('progressFill').style.width = '100%';
        document.getElementById('progressText').textContent = totalEmails + ' / ' + totalEmails;
        document.getElementById('progressStatus').textContent = 'Hotovo!';
        btn.textContent = 'Ověření dokončeno';
    }

    function updateResults() {
        document.getElementById('validCount').textContent = validCount;
        document.getElementById('invalidCount').textContent = invalidCount;
        document.getElementById('uncertainCount').textContent = uncertainCount;
    }
    </script>
</body>
</html>
