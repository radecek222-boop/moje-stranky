<?php
/**
 * Debug: Obsah email_queue
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<h1>Debug: Email Queue</h1>";

try {
    $pdo = getDbConnection();

    // 1. Vsechny zaznamy v email_queue
    echo "<h2>Vsechny zaznamy v wgs_email_queue</h2>";
    $stmt = $pdo->query("SELECT * FROM wgs_email_queue ORDER BY created_at DESC LIMIT 100");
    $emaily = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Celkem zaznamu: <strong>" . count($emaily) . "</strong></p>";

    if (count($emaily) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;font-size:12px;'>";
        echo "<tr style='background:#eee;'>";
        foreach (array_keys($emaily[0]) as $col) {
            echo "<th>" . htmlspecialchars($col) . "</th>";
        }
        echo "</tr>";

        foreach ($emaily as $email) {
            echo "<tr>";
            foreach ($email as $key => $hodnota) {
                $zobraz = htmlspecialchars(substr($hodnota ?? '', 0, 100));
                if (strlen($hodnota ?? '') > 100) $zobraz .= '...';
                echo "<td>" . $zobraz . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div style='background:#fff3cd;padding:10px;'>Tabulka je prazdna!</div>";
    }

    // 2. Statistika podle statusu
    echo "<h2>Statistika podle statusu</h2>";
    $stmt = $pdo->query("SELECT status, COUNT(*) as pocet FROM wgs_email_queue GROUP BY status");
    $statistika = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($statistika) > 0) {
        echo "<ul>";
        foreach ($statistika as $row) {
            echo "<li><strong>" . htmlspecialchars($row['status']) . "</strong>: " . $row['pocet'] . "</li>";
        }
        echo "</ul>";
    } else {
        echo "<p>Zadna data</p>";
    }

    // 3. Hledat pozvanky podle notification_id
    echo "<h2>Pozvanky (notification_id LIKE '%invitation%')</h2>";
    $stmt = $pdo->query("
        SELECT id, notification_id, recipient_email, subject, status, created_at, sent_at
        FROM wgs_email_queue
        WHERE notification_id LIKE '%invitation%'
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $pozvanky = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Nalezeno pozvanek: <strong>" . count($pozvanky) . "</strong></p>";

    if (count($pozvanky) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;font-size:12px;'>";
        echo "<tr style='background:#eee;'><th>ID</th><th>Notification</th><th>Email</th><th>Subject</th><th>Status</th><th>Vytvoreno</th><th>Odeslano</th></tr>";
        foreach ($pozvanky as $p) {
            echo "<tr>";
            echo "<td>" . $p['id'] . "</td>";
            echo "<td>" . htmlspecialchars($p['notification_id']) . "</td>";
            echo "<td>" . htmlspecialchars($p['recipient_email']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($p['subject'], 0, 50)) . "</td>";
            echo "<td>" . htmlspecialchars($p['status']) . "</td>";
            echo "<td>" . htmlspecialchars($p['created_at']) . "</td>";
            echo "<td>" . htmlspecialchars($p['sent_at'] ?? '-') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // 4. Vsechny emaily s klici v body
    echo "<h2>Emaily obsahujici 'PRO2025' nebo 'TEC2025' v body</h2>";
    $stmt = $pdo->query("
        SELECT id, recipient_email, subject, status, created_at
        FROM wgs_email_queue
        WHERE body LIKE '%PRO2025%' OR body LIKE '%TEC2025%'
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $sKlici = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Nalezeno: <strong>" . count($sKlici) . "</strong></p>";

    if (count($sKlici) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse:collapse;'>";
        echo "<tr style='background:#d4edda;'><th>ID</th><th>Email</th><th>Subject</th><th>Status</th><th>Vytvoreno</th></tr>";
        foreach ($sKlici as $e) {
            echo "<tr>";
            echo "<td>" . $e['id'] . "</td>";
            echo "<td>" . htmlspecialchars($e['recipient_email']) . "</td>";
            echo "<td>" . htmlspecialchars($e['subject']) . "</td>";
            echo "<td>" . htmlspecialchars($e['status']) . "</td>";
            echo "<td>" . htmlspecialchars($e['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<div style='background:#f8d7da;padding:10px;'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php?tab=keys'>Zpet</a>";
?>
