<?php
/**
 * Kontrola odeslaných emailů - zjištění kolik se odeslalo před timeoutem
 */

require_once __DIR__ . '/init.php';

// BEZPEČNOSTNÍ KONTROLA
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Kontrola odeslaných emailů</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1200px;
               margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460;
                padding: 15px; border-radius: 5px; margin: 15px 0; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724;
                   padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404;
                   padding: 15px; border-radius: 5px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #f8f9fa; font-weight: 600; }
        .counter { font-size: 48px; font-weight: 700; color: #333;
                   text-align: center; margin: 20px 0; }
    </style>
</head>
<body>
<div class='container'>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Kontrola odeslaných emailů - Natuzzi kampaň</h1>";

    // 1. Zkontrolovat email queue
    echo "<h2>📊 Email Queue</h2>";

    $stmt = $pdo->query("
        SELECT
            status,
            COUNT(*) as pocet
        FROM wgs_email_queue
        WHERE subject LIKE '%NATUZZI%'
           OR subject LIKE '%Pozáruční servis%'
        GROUP BY status
    ");

    $queueStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($queueStats)) {
        echo "<div class='info'>V email queue nejsou žádné záznamy s předmětem obsahujícím 'NATUZZI'.</div>";
    } else {
        echo "<table>";
        echo "<tr><th>Status</th><th>Počet</th></tr>";
        foreach ($queueStats as $stat) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($stat['status']) . "</td>";
            echo "<td>" . htmlspecialchars($stat['pocet']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // 2. Posledních 50 emailů z queue
    echo "<h2>📧 Posledních 50 emailů v queue</h2>";

    $stmt = $pdo->query("
        SELECT
            id,
            to_email,
            subject,
            status,
            created_at,
            sent_at,
            error_message
        FROM wgs_email_queue
        ORDER BY created_at DESC
        LIMIT 50
    ");

    $emails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($emails)) {
        echo "<div class='info'>Email queue je prázdná nebo neobsahuje záznamy.</div>";
    } else {
        echo "<table>";
        echo "<tr><th>Příjemce</th><th>Předmět</th><th>Status</th><th>Vytvořeno</th><th>Odesláno</th><th>Chyba</th></tr>";

        foreach ($emails as $email) {
            $statusColor = '';
            if ($email['status'] === 'sent') {
                $statusColor = 'color: #155724;';
            } elseif ($email['status'] === 'failed') {
                $statusColor = 'color: #721c24;';
            }

            echo "<tr>";
            echo "<td>" . htmlspecialchars($email['to_email']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($email['subject'], 0, 40)) . "...</td>";
            echo "<td style='{$statusColor}'>" . htmlspecialchars($email['status']) . "</td>";
            echo "<td>" . htmlspecialchars($email['created_at']) . "</td>";
            echo "<td>" . htmlspecialchars($email['sent_at'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($email['error_message'] ?? '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // 3. Zjistit kolik emailů se odeslalo z CSV
    echo "<h2>📈 Statistika z CSV kampaně</h2>";

    $stmt = $pdo->query("
        SELECT
            COUNT(*) as celkem,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as odeslano,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as ceka,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as chyby,
            MIN(created_at) as prvni,
            MAX(sent_at) as posledni
        FROM wgs_email_queue
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
    ");

    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($stats['celkem'] > 0) {
        echo "<div class='counter'>{$stats['odeslano']} / {$stats['celkem']}</div>";

        echo "<div class='success'>";
        echo "<strong>✅ Úspěšně odesláno:</strong> {$stats['odeslano']} emailů<br>";
        echo "<strong>⏳ Čeká na odeslání:</strong> {$stats['ceka']} emailů<br>";
        echo "<strong>❌ Chyby:</strong> {$stats['chyby']} emailů<br>";
        echo "<strong>🕐 První email:</strong> {$stats['prvni']}<br>";
        echo "<strong>🕐 Poslední email:</strong> " . ($stats['posledni'] ?? 'ještě žádný') . "<br>";
        echo "</div>";
    } else {
        echo "<div class='warning'>";
        echo "V poslední hodině nebyly zaznamenány žádné emaily v queue.";
        echo "</div>";
    }

    // 4. CSV kontrola - kolik emailů celkem
    echo "<h2>📄 CSV soubor</h2>";

    $csvFile = __DIR__ . '/contacts_all.csv';
    if (file_exists($csvFile)) {
        $handle = fopen($csvFile, 'r');
        fgetcsv($handle, 1000, ';'); // header

        $csvCount = 0;
        while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
            if (!empty($data[0]) && filter_var($data[0], FILTER_VALIDATE_EMAIL)) {
                $csvCount++;
            }
        }
        fclose($handle);

        echo "<div class='info'>";
        echo "<strong>📊 Celkem v CSV:</strong> {$csvCount} validních emailů<br>";

        if ($stats['celkem'] > 0) {
            $zbyvajici = $csvCount - $stats['odeslano'];
            $procento = round(($stats['odeslano'] / $csvCount) * 100, 1);

            echo "<strong>✅ Odesláno:</strong> {$stats['odeslano']} / {$csvCount} ({$procento}%)<br>";
            echo "<strong>📬 Zbývá odeslat:</strong> {$zbyvajici} emailů";
        }
        echo "</div>";
    }

    // 5. PHP error log - poslední chyby
    echo "<h2>🔴 Poslední chyby (PHP log)</h2>";

    $logFile = __DIR__ . '/logs/php_errors.log';
    if (file_exists($logFile)) {
        $logLines = array_slice(file($logFile), -20);

        if (!empty($logLines)) {
            echo "<pre style='background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto;'>";
            foreach (array_reverse($logLines) as $line) {
                echo htmlspecialchars($line);
            }
            echo "</pre>";
        } else {
            echo "<div class='success'>Žádné chyby v logu.</div>";
        }
    } else {
        echo "<div class='info'>Log soubor neexistuje.</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</div></body></html>";
?>
