<?php
/**
 * Kontrola duplicitních emailů v Natuzzi kampani
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Kontrola duplicit - Natuzzi kampaň</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1200px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .section { margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 5px; }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        h2 { color: #555; border-bottom: 2px solid #555; padding-bottom: 5px; margin-top: 30px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: #fff; font-weight: 600; }
        .counter { font-size: 48px; font-weight: 700; color: #333; text-align: center; margin: 20px 0; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; }
    </style>
</head>
<body>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Kontrola duplicit - Natuzzi kampaň</h1>";

    // 1. Celkové statistiky Natuzzi kampaně
    echo "<div class='section'>";
    echo "<h2>📊 Celkové statistiky kampaně</h2>";

    $stmt = $pdo->query("
        SELECT
            COUNT(*) as celkem,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as odeslano,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as ceka,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as selhalo,
            MIN(created_at) as prvni,
            MAX(sent_at) as posledni_odeslany
        FROM wgs_email_queue
        WHERE notification_id = 'marketing_natuzzi_pozarucni'
           OR subject LIKE '%NATUZZI%'
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Metrika</th><th>Hodnota</th></tr>";
    echo "<tr><td><strong>Celkem v queue</strong></td><td><strong>{$stats['celkem']}</strong></td></tr>";
    echo "<tr><td>Odesláno</td><td style='color: #155724;'>{$stats['odeslano']}</td></tr>";
    echo "<tr><td>Čeká na odeslání</td><td style='color: #856404;'>{$stats['ceka']}</td></tr>";
    echo "<tr><td>Selhalo</td><td style='color: #721c24;'>{$stats['selhalo']}</td></tr>";
    echo "<tr><td>První vložení</td><td>{$stats['prvni']}</td></tr>";
    echo "<tr><td>Poslední odeslaný</td><td>{$stats['posledni_odeslany']}</td></tr>";
    echo "</table>";
    echo "</div>";

    // 2. Kontrola duplicitních příjemců
    echo "<div class='section'>";
    echo "<h2>🚨 Kontrola duplicitních příjemců</h2>";

    $stmt = $pdo->query("
        SELECT
            recipient_email,
            COUNT(*) as pocet,
            GROUP_CONCAT(id ORDER BY id) as queue_ids,
            GROUP_CONCAT(status ORDER BY id) as statusy,
            MIN(created_at) as prvni_vlozeni,
            MAX(created_at) as posledni_vlozeni
        FROM wgs_email_queue
        WHERE notification_id = 'marketing_natuzzi_pozarucni'
           OR subject LIKE '%NATUZZI%'
        GROUP BY recipient_email
        HAVING COUNT(*) > 1
        ORDER BY pocet DESC
    ");

    $duplicity = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($duplicity)) {
        echo "<div class='success'>";
        echo "<strong>✅ ŽÁDNÉ DUPLICITY!</strong><br>";
        echo "Každý email je v queue pouze jednou.";
        echo "</div>";
    } else {
        $celkemDuplicitu = count($duplicity);
        $celkemNadbytek = array_sum(array_column($duplicity, 'pocet')) - $celkemDuplicitu;

        echo "<div class='error'>";
        echo "<strong>❌ NALEZENY DUPLICITY!</strong><br>";
        echo "Počet emailů s duplicitami: <strong>$celkemDuplicitu</strong><br>";
        echo "Celkový nadbytečný počet: <strong>$celkemNadbytek</strong> emailů";
        echo "</div>";

        echo "<table>";
        echo "<tr><th>Email</th><th>Počet</th><th>Queue IDs</th><th>Statusy</th><th>První vložení</th><th>Poslední vložení</th></tr>";

        foreach ($duplicity as $dup) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($dup['recipient_email']) . "</td>";
            echo "<td><strong style='color: #721c24;'>{$dup['pocet']}×</strong></td>";
            echo "<td>" . htmlspecialchars($dup['queue_ids']) . "</td>";
            echo "<td>" . htmlspecialchars($dup['statusy']) . "</td>";
            echo "<td>" . htmlspecialchars($dup['prvni_vlozeni']) . "</td>";
            echo "<td>" . htmlspecialchars($dup['posledni_vlozeni']) . "</td>";
            echo "</tr>";
        }

        echo "</table>";

        // Nabídnout odstranění duplicit
        echo "<div class='warning'>";
        echo "<strong>⚠️ DOPORUČENÍ:</strong><br>";
        echo "Pokud chceš odstranit duplicitní záznamy (ponechat jen nejstarší), klikni na tlačítko níže.<br>";
        echo "<form method='post' style='margin-top: 10px;'>";
        echo "<button type='submit' name='odstranit_duplicity' value='1' style='padding: 10px 20px; background: #dc3545; color: #fff; border: none; cursor: pointer; font-weight: 600;'>ODSTRANIT DUPLICITY</button>";
        echo "</form>";
        echo "</div>";
    }
    echo "</div>";

    // 3. Kontrola CSV souboru
    echo "<div class='section'>";
    echo "<h2>📄 Kontrola CSV souboru</h2>";

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

        $csvCelkem = count($csvEmails);
        $csvUnikatni = count(array_unique($csvEmails));
        $csvDuplicity = $csvCelkem - $csvUnikatni;

        echo "<table>";
        echo "<tr><th>Metrika</th><th>Hodnota</th></tr>";
        echo "<tr><td><strong>Celkem emailů v CSV</strong></td><td><strong>$csvCelkem</strong></td></tr>";
        echo "<tr><td>Unikátní emailů</td><td>$csvUnikatni</td></tr>";
        echo "<tr><td>Duplicity v CSV</td><td style='color: " . ($csvDuplicity > 0 ? '#721c24' : '#155724') . ";'><strong>$csvDuplicity</strong></td></tr>";
        echo "</table>";

        if ($csvDuplicity > 0) {
            echo "<div class='warning'>⚠️ CSV soubor obsahuje duplicitní emailové adresy!</div>";
        } else {
            echo "<div class='success'>✅ CSV soubor neobsahuje duplicity</div>";
        }
    } else {
        echo "<div class='error'>❌ CSV soubor nenalezen: contacts_all.csv</div>";
    }
    echo "</div>";

    // 4. Zpracování odstranění duplicit
    if (isset($_POST['odstranit_duplicity'])) {
        echo "<div class='section'>";
        echo "<h2>🗑️ Odstranění duplicit</h2>";

        $pdo->beginTransaction();
        try {
            // Pro každý duplicitní email ponechat jen nejstarší záznam
            $stmt = $pdo->query("
                DELETE eq1 FROM wgs_email_queue eq1
                INNER JOIN wgs_email_queue eq2
                ON eq1.recipient_email = eq2.recipient_email
                AND eq1.id > eq2.id
                WHERE (eq1.notification_id = 'marketing_natuzzi_pozarucni'
                   OR eq1.subject LIKE '%NATUZZI%')
                AND (eq2.notification_id = 'marketing_natuzzi_pozarucni'
                   OR eq2.subject LIKE '%NATUZZI%')
            ");

            $odstranenoRadku = $stmt->rowCount();
            $pdo->commit();

            echo "<div class='success'>";
            echo "<strong>✅ DUPLICITY ODSTRANĚNY!</strong><br>";
            echo "Odstraněno: <strong>$odstranenoRadku</strong> duplicitních záznamů<br>";
            echo "<a href='kontrola_duplicit_natuzzi.php'>Znovu načíst stránku</a>";
            echo "</div>";

        } catch (PDOException $e) {
            $pdo->rollBack();
            echo "<div class='error'>";
            echo "<strong>❌ CHYBA PŘI ODSTRANĚNÍ:</strong><br>";
            echo htmlspecialchars($e->getMessage());
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
