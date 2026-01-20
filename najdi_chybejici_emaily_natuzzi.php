<?php
/**
 * Najít chybějící emaily - které jsou v CSV ale NE v queue
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Chybějící emaily - Natuzzi</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .section { margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 5px; }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #555; margin-top: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: #fff; font-weight: 600; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .counter { font-size: 48px; font-weight: 700; text-align: center; margin: 20px 0; }
        .btn { display: inline-block; padding: 12px 24px; background: #28a745; color: #fff; border: none; cursor: pointer; font-weight: 600; text-decoration: none; border-radius: 5px; margin: 10px 5px; }
        .btn:hover { background: #218838; }
    </style>
</head>
<body>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Chybějící emaily v Natuzzi kampani</h1>";

    // Načíst CSV
    $csvFile = __DIR__ . '/contacts_all.csv';
    if (!file_exists($csvFile)) {
        echo "<div class='error'>❌ CSV soubor nenalezen: contacts_all.csv</div>";
        exit;
    }

    $handle = fopen($csvFile, 'r');
    fgetcsv($handle, 1000, ';'); // skip header

    $csvEmails = [];
    while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
        if (!empty($data[0]) && filter_var($data[0], FILTER_VALIDATE_EMAIL)) {
            $csvEmails[] = strtolower(trim($data[0]));
        }
    }
    fclose($handle);

    // Načíst queue
    $stmt = $pdo->query("
        SELECT LOWER(recipient_email) as email
        FROM wgs_email_queue
        WHERE notification_id = 'marketing_natuzzi_pozarucni'
    ");
    $queueEmails = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $csvSet = array_flip($csvEmails);
    $queueSet = array_flip($queueEmails);

    $chybejici = array_diff_key($csvSet, $queueSet);

    echo "<div class='section'>";
    echo "<h2>📊 Shrnutí</h2>";

    echo "<table>";
    echo "<tr><th>Metrika</th><th>Hodnota</th></tr>";
    echo "<tr><td><strong>Emailů v CSV</strong></td><td><strong>" . count($csvEmails) . "</strong></td></tr>";
    echo "<tr><td><strong>Emailů v Queue</strong></td><td><strong>" . count($queueEmails) . "</strong></td></tr>";
    echo "<tr><td><strong>Chybí v Queue</strong></td><td style='color: " . (count($chybejici) > 0 ? '#721c24' : '#155724') . ";'><strong>" . count($chybejici) . "</strong></td></tr>";
    echo "</table>";

    if (count($chybejici) == 0) {
        echo "<div class='success'>";
        echo "<strong>✅ ŽÁDNÉ CHYBĚJÍCÍ EMAILY!</strong><br>";
        echo "Všech " . count($csvEmails) . " emailů z CSV je v queue.";
        echo "</div>";
    } else {
        echo "<div class='counter' style='color: #721c24;'>" . count($chybejici) . "</div>";

        echo "<div class='error'>";
        echo "<strong>❌ CHYBĚJÍCÍ EMAILY V QUEUE:</strong><br>";
        echo "Tyto emaily jsou v CSV ale NEBYLY vloženy do queue:";
        echo "</div>";

        echo "<table>";
        echo "<tr><th>#</th><th>Email</th></tr>";
        $index = 1;
        foreach (array_keys($chybejici) as $email) {
            echo "<tr>";
            echo "<td>{$index}</td>";
            echo "<td>" . htmlspecialchars($email) . "</td>";
            echo "</tr>";
            $index++;
        }
        echo "</table>";

        // Nabídnout doplnění
        echo "<div class='warning'>";
        echo "<strong>⚠️ MOŽNOSTI:</strong><br>";
        echo "1. <strong>Doplnit chybějící emaily do queue</strong> - vloží tyto emaily s původním intervalem<br>";
        echo "2. Ignorovat - možná byly vyřazeny záměrně (invalid, duplicity, atd.)<br><br>";

        echo "<form method='post'>";
        echo "<input type='hidden' name='doplnit_chybejici' value='1'>";
        echo "<label>Interval mezi emaily (minuty): <input type='number' name='interval' value='1' min='1' max='60' style='width: 60px;'></label><br><br>";
        echo "<button type='submit' class='btn'>DOPLNIT CHYBĚJÍCÍ EMAILY DO QUEUE</button>";
        echo "</form>";
        echo "</div>";
    }
    echo "</div>";

    // Zpracování doplnění
    if (isset($_POST['doplnit_chybejici'])) {
        echo "<div class='section'>";
        echo "<h2>📥 Doplňování chybějících emailů</h2>";

        $interval = isset($_POST['interval']) ? max(1, (int)$_POST['interval']) : 1;

        // Načíst šablonu
        $stmt = $pdo->prepare("SELECT * FROM wgs_notifications WHERE id = ?");
        $stmt->execute(['marketing_natuzzi_pozarucni']);
        $template = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$template) {
            echo "<div class='error'>❌ Šablona 'marketing_natuzzi_pozarucni' nenalezena!</div>";
        } else {
            // Najít poslední scheduled_at v queue
            $stmt = $pdo->query("
                SELECT MAX(scheduled_at) as last_scheduled
                FROM wgs_email_queue
                WHERE notification_id = 'marketing_natuzzi_pozarucni'
            ");
            $lastScheduled = $stmt->fetchColumn();
            $startTime = $lastScheduled ? strtotime($lastScheduled) + ($interval * 60) : time();

            $emailBody = $template['template'];
            $subject = $template['subject'];

            $stmt = $pdo->prepare("
                INSERT INTO wgs_email_queue (
                    notification_id, recipient_email, subject, body,
                    status, scheduled_at, created_at, attempts, max_attempts, priority
                ) VALUES (
                    :notification_id, :recipient_email, :subject, :body,
                    'pending', :scheduled_at, NOW(), 0, 3, 1
                )
            ");

            $uspesne = 0;
            $chyby = 0;

            echo "<table>";
            echo "<tr><th>Email</th><th>Scheduled At</th><th>Status</th></tr>";

            $index = 0;
            foreach (array_keys($chybejici) as $email) {
                $scheduledOffset = $index * $interval * 60;
                $scheduledAt = date('Y-m-d H:i:s', $startTime + $scheduledOffset);

                try {
                    $stmt->execute([
                        'notification_id' => 'marketing_natuzzi_pozarucni',
                        'recipient_email' => $email,
                        'subject' => $subject,
                        'body' => $emailBody,
                        'scheduled_at' => $scheduledAt
                    ]);

                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($email) . "</td>";
                    echo "<td>{$scheduledAt}</td>";
                    echo "<td style='color: #155724;'>✅ Vloženo</td>";
                    echo "</tr>";

                    $uspesne++;
                    $index++;
                } catch (PDOException $e) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($email) . "</td>";
                    echo "<td>-</td>";
                    echo "<td style='color: #721c24;'>❌ " . htmlspecialchars($e->getMessage()) . "</td>";
                    echo "</tr>";
                    $chyby++;
                }
            }

            echo "</table>";

            echo "<div class='success'>";
            echo "<strong>✅ DOPLNĚNÍ DOKONČENO!</strong><br>";
            echo "Úspěšně vloženo: <strong>{$uspesne}</strong> emailů<br>";
            if ($chyby > 0) {
                echo "Chyby: <strong>{$chyby}</strong><br>";
            }
            echo "První nový email bude odeslán: <strong>" . date('d.m.Y H:i', $startTime) . "</strong><br>";
            echo "<a href='najdi_chybejici_emaily_natuzzi.php' class='btn'>Znovu načíst</a>";
            echo "</div>";
        }
        echo "</div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</body></html>";
?>
