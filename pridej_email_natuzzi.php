<?php
/**
 * Přidání emailu do Natuzzi kampaně
 * Použití: https://www.wgs-service.cz/pridej_email_natuzzi.php?email=adresa@example.com
 */

require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

$email = $_GET['email'] ?? '';

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die("Chybí nebo neplatný email. Použití: ?email=adresa@example.com");
}

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Přidat email do fronty</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .section { margin: 20px 0; padding: 20px; background: #fff; border: 1px solid #ddd; border-radius: 5px; }
        h1 { color: #333; border-bottom: 3px solid #333; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404; padding: 15px; border-radius: 5px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #333; color: #fff; font-weight: 600; }
        .btn { display: inline-block; padding: 10px 20px; background: #333; color: #fff; text-decoration: none; border-radius: 5px; margin: 10px 5px 10px 0; }
        .btn:hover { background: #000; }
    </style>
</head>
<body>";

try {
    $pdo = getDbConnection();

    echo "<h1>➕ Přidat email do Natuzzi kampaně</h1>";
    echo "<div class='section'>";

    // Kontrola zda email už není v queue
    $stmt = $pdo->prepare("
        SELECT id, status, scheduled_at 
        FROM wgs_email_queue 
        WHERE recipient_email = :email 
        AND notification_id = 'marketing_natuzzi_pozarucni'
    ");
    $stmt->execute(['email' => $email]);
    $existujici = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existujici) {
        echo "<div class='warning'>";
        echo "<strong>⚠️ Email již je v queue!</strong><br><br>";
        echo "<table>";
        echo "<tr><th>Pole</th><th>Hodnota</th></tr>";
        echo "<tr><td>Email</td><td>" . htmlspecialchars($email) . "</td></tr>";
        echo "<tr><td>Status</td><td>" . htmlspecialchars($existujici['status']) . "</td></tr>";
        echo "<tr><td>Naplánováno na</td><td>" . htmlspecialchars($existujici['scheduled_at']) . "</td></tr>";
        echo "</table>";
        echo "<a href='admin.php?tab=notifications' class='btn'>Zobrazit EMAIL & SMS Management</a>";
        echo "</div>";
    } else {
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
            
            // Přidat 1 minutu k poslednímu času
            $scheduledAt = $lastScheduled 
                ? date('Y-m-d H:i:s', strtotime($lastScheduled) + 60)
                : date('Y-m-d H:i:s', time() + 60);

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

            $stmt->execute([
                'notification_id' => 'marketing_natuzzi_pozarucni',
                'recipient_email' => $email,
                'subject' => $subject,
                'body' => $emailBody,
                'scheduled_at' => $scheduledAt
            ]);

            $insertedId = $pdo->lastInsertId();

            echo "<div class='success'>";
            echo "<strong>✅ EMAIL PŘIDÁN DO FRONTY!</strong><br><br>";
            echo "<table>";
            echo "<tr><th>Pole</th><th>Hodnota</th></tr>";
            echo "<tr><td>Queue ID</td><td><strong>#{$insertedId}</strong></td></tr>";
            echo "<tr><td>Email</td><td><strong>" . htmlspecialchars($email) . "</strong></td></tr>";
            echo "<tr><td>Kampaň</td><td>Natuzzi pozáruční servis</td></tr>";
            echo "<tr><td>Naplánováno na</td><td><strong>" . htmlspecialchars($scheduledAt) . "</strong></td></tr>";
            echo "<tr><td>Předmět</td><td>" . htmlspecialchars($subject) . "</td></tr>";
            echo "</table>";
            echo "<a href='admin.php?tab=notifications' class='btn'>Zobrazit EMAIL & SMS Management</a>";
            echo "</div>";
        }
    }

    // Zobrazit celkový počet emailů v kampani
    $stmt = $pdo->query("
        SELECT 
            COUNT(*) as celkem,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as odeslano,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as ceka
        FROM wgs_email_queue
        WHERE notification_id = 'marketing_natuzzi_pozarucni'
    ");
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    echo "<div class='section'>";
    echo "<h2>📊 Stav Natuzzi kampaně</h2>";
    echo "<table>";
    echo "<tr><th>Metrika</th><th>Hodnota</th></tr>";
    echo "<tr><td>Celkem emailů</td><td><strong>{$stats['celkem']}</strong></td></tr>";
    echo "<tr><td>Odesláno</td><td>{$stats['odeslano']}</td></tr>";
    echo "<tr><td>Čeká na odeslání</td><td>{$stats['ceka']}</td></tr>";
    echo "</table>";
    echo "</div>";

    echo "</div>";

} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<strong>CHYBA:</strong><br>";
    echo htmlspecialchars($e->getMessage());
    echo "</div>";
}

echo "</body></html>";
?>
