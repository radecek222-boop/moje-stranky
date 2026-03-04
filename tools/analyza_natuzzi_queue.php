<?php
/**
 * Detailní analýza Natuzzi queue - zjistit proč je tam 1519 emailů místo 1497
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Analýza Natuzzi Queue</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1400px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .section { margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 5px; }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #555; border-bottom: 2px solid #555; padding-bottom: 5px; margin-top: 30px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; font-size: 0.85rem; }
        th { background: #333; color: #fff; font-weight: 600; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; }
    </style>
</head>
<body>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔬 Detailní analýza Natuzzi Queue</h1>";

    // 1. Skupiny podle notification_id
    echo "<div class='section'>";
    echo "<h2>1️⃣ Skupiny podle notification_id</h2>";

    $stmt = $pdo->query("
        SELECT
            notification_id,
            COUNT(*) as pocet,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as odeslano,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as ceka,
            MIN(created_at) as nejstarsi,
            MAX(created_at) as nejmladsi
        FROM wgs_email_queue
        WHERE notification_id = 'marketing_natuzzi_pozarucni'
           OR subject LIKE '%NATUZZI%'
        GROUP BY notification_id
        ORDER BY pocet DESC
    ");

    echo "<table>";
    echo "<tr><th>Notification ID</th><th>Počet</th><th>Odesláno</th><th>Čeká</th><th>Nejstarší</th><th>Nejmladší</th></tr>";

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['notification_id'] ?: '-') . "</td>";
        echo "<td><strong>{$row['pocet']}</strong></td>";
        echo "<td>{$row['odeslano']}</td>";
        echo "<td>{$row['ceka']}</td>";
        echo "<td>" . htmlspecialchars($row['nejstarsi']) . "</td>";
        echo "<td>" . htmlspecialchars($row['nejmladsi']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    // 2. Skupiny podle předmětu
    echo "<div class='section'>";
    echo "<h2>2️⃣ Skupiny podle předmětu</h2>";

    $stmt = $pdo->query("
        SELECT
            subject,
            COUNT(*) as pocet,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as odeslano,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as ceka
        FROM wgs_email_queue
        WHERE notification_id = 'marketing_natuzzi_pozarucni'
           OR subject LIKE '%NATUZZI%'
        GROUP BY subject
        ORDER BY pocet DESC
    ");

    echo "<table>";
    echo "<tr><th>Předmět</th><th>Počet</th><th>Odesláno</th><th>Čeká</th></tr>";

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['subject']) . "</td>";
        echo "<td><strong>{$row['pocet']}</strong></td>";
        echo "<td>{$row['odeslano']}</td>";
        echo "<td>{$row['ceka']}</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    // 3. Časová osa vkládání
    echo "<div class='section'>";
    echo "<h2>3️⃣ Časová osa vkládání</h2>";

    $stmt = $pdo->query("
        SELECT
            DATE(created_at) as datum,
            COUNT(*) as pocet,
            MIN(created_at) as prvni,
            MAX(created_at) as posledni
        FROM wgs_email_queue
        WHERE notification_id = 'marketing_natuzzi_pozarucni'
           OR subject LIKE '%NATUZZI%'
        GROUP BY DATE(created_at)
        ORDER BY datum DESC
    ");

    echo "<table>";
    echo "<tr><th>Datum</th><th>Počet vložených</th><th>První</th><th>Poslední</th></tr>";

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td><strong>" . htmlspecialchars($row['datum']) . "</strong></td>";
        echo "<td>{$row['pocet']}</td>";
        echo "<td>" . htmlspecialchars($row['prvni']) . "</td>";
        echo "<td>" . htmlspecialchars($row['posledni']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

    // 4. Porovnání s CSV
    echo "<div class='section'>";
    echo "<h2>4️⃣ Porovnání s CSV souborem</h2>";

    $csvFile = __DIR__ . '/contacts_all.csv';
    if (file_exists($csvFile)) {
        $handle = fopen($csvFile, 'r');
        fgetcsv($handle, 1000, ';'); // skip header

        $csvEmails = [];
        while (($data = fgetcsv($handle, 1000, ';')) !== FALSE) {
            if (!empty($data[0]) && filter_var($data[0], FILTER_VALIDATE_EMAIL)) {
                $csvEmails[] = strtolower(trim($data[0]));
            }
        }
        fclose($handle);

        // Získat emaily z queue
        $stmt = $pdo->query("
            SELECT LOWER(recipient_email) as email
            FROM wgs_email_queue
            WHERE notification_id = 'marketing_natuzzi_pozarucni'
               OR subject LIKE '%NATUZZI%'
        ");
        $queueEmails = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $csvSet = array_flip($csvEmails);
        $queueSet = array_flip($queueEmails);

        $vQueueNeVCsv = array_diff_key($queueSet, $csvSet);
        $vCsvNeVQueue = array_diff_key($csvSet, $queueSet);

        echo "<table>";
        echo "<tr><th>Metrika</th><th>Hodnota</th></tr>";
        echo "<tr><td>Emailů v CSV</td><td><strong>" . count($csvEmails) . "</strong></td></tr>";
        echo "<tr><td>Emailů v Queue</td><td><strong>" . count($queueEmails) . "</strong></td></tr>";
        echo "<tr><td>V Queue ale NE v CSV</td><td style='color: " . (count($vQueueNeVCsv) > 0 ? '#856404' : '#155724') . ";'><strong>" . count($vQueueNeVCsv) . "</strong></td></tr>";
        echo "<tr><td>V CSV ale NE v Queue</td><td style='color: " . (count($vCsvNeVQueue) > 0 ? '#856404' : '#155724') . ";'><strong>" . count($vCsvNeVQueue) . "</strong></td></tr>";
        echo "</table>";

        if (count($vQueueNeVCsv) > 0) {
            echo "<div class='warning'>";
            echo "<strong>⚠️ Emaily v Queue ale NE v CSV:</strong><br>";
            echo "<ul>";
            $count = 0;
            foreach (array_keys($vQueueNeVCsv) as $email) {
                if ($count++ < 10) {
                    echo "<li>" . htmlspecialchars($email) . "</li>";
                }
            }
            if (count($vQueueNeVCsv) > 10) {
                echo "<li><em>... a dalších " . (count($vQueueNeVCsv) - 10) . " emailů</em></li>";
            }
            echo "</ul>";
            echo "</div>";
        }

        if (count($vCsvNeVQueue) > 0) {
            echo "<div class='warning'>";
            echo "<strong>⚠️ Emaily v CSV ale NE v Queue:</strong><br>";
            echo "<ul>";
            $count = 0;
            foreach (array_keys($vCsvNeVQueue) as $email) {
                if ($count++ < 10) {
                    echo "<li>" . htmlspecialchars($email) . "</li>";
                }
            }
            if (count($vCsvNeVQueue) > 10) {
                echo "<li><em>... a dalších " . (count($vCsvNeVQueue) - 10) . " emailů</em></li>";
            }
            echo "</ul>";
            echo "</div>";
        }

    } else {
        echo "<div class='error'>❌ CSV soubor nenalezen</div>";
    }
    echo "</div>";

    // 5. Ukázka prvních 20 záznamů
    echo "<div class='section'>";
    echo "<h2>5️⃣ Prvních 20 záznamů (nejstarší)</h2>";

    $stmt = $pdo->query("
        SELECT id, recipient_email, subject, status, notification_id, created_at, sent_at
        FROM wgs_email_queue
        WHERE notification_id = 'marketing_natuzzi_pozarucni'
           OR subject LIKE '%NATUZZI%'
        ORDER BY created_at ASC
        LIMIT 20
    ");

    echo "<table>";
    echo "<tr><th>ID</th><th>Příjemce</th><th>Předmět</th><th>Status</th><th>Notification ID</th><th>Vytvořeno</th><th>Odesláno</th></tr>";

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>{$row['id']}</td>";
        echo "<td>" . htmlspecialchars($row['recipient_email']) . "</td>";
        echo "<td>" . htmlspecialchars(substr($row['subject'], 0, 40)) . "...</td>";
        echo "<td>" . htmlspecialchars($row['status']) . "</td>";
        echo "<td>" . htmlspecialchars($row['notification_id'] ?: '-') . "</td>";
        echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
        echo "<td>" . htmlspecialchars($row['sent_at'] ?: '-') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</body></html>";
?>
