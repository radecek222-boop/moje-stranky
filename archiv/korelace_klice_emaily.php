<?php
/**
 * Korelace klicu a emailu podle casu
 * Pokusi se najit souvislosti mezi casem vytvoreni klice a odeslanymi emaily
 */

require_once __DIR__ . '/init.php';

// Bezpecnostni kontrola - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PRISTUP ODEPREN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Korelace klicu a emailu</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 1400px; margin: 20px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #000; border-bottom: 2px solid #000; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; font-size: 12px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #333; color: white; }
        .match { background: #d4edda; }
        .close { background: #fff3cd; }
        .klic-row { background: #e3f2fd; font-weight: bold; }
        code { background: #f5f5f5; padding: 2px 6px; border-radius: 3px; }
        .time-diff { color: #666; font-size: 11px; }
        .btn { display: inline-block; padding: 8px 16px; background: #000; color: white;
               text-decoration: none; border-radius: 5px; margin: 5px; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Korelace klicu a emailu podle casu</h1>";

try {
    $pdo = getDbConnection();

    // 1. Nacist klice bez sent_to_email
    $stmt = $pdo->query("
        SELECT key_code, key_type, created_at
        FROM wgs_registration_keys
        WHERE sent_to_email IS NULL
        ORDER BY created_at DESC
    ");
    $klice = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<p>Klicu bez emailu: <strong>" . count($klice) . "</strong></p>";

    if (count($klice) === 0) {
        echo "<div style='background:#d4edda;padding:10px;'>Vsechny klice maji vyplneny email!</div>";
        echo "</div></body></html>";
        exit;
    }

    // 2. Pro kazdy klic najit emaily odeslane v podobnem case (+-30 minut)
    echo "<h2>Casova korelace</h2>";
    echo "<p>Hledame emaily odeslane +-30 minut od vytvoreni klice:</p>";

    echo "<table>";
    echo "<tr><th>Typ</th><th>Klic</th><th>Vytvoren</th><th>Emaily v okne +-30 min</th><th>Mozny prijemce?</th></tr>";

    $potencialniPrirazeni = [];

    foreach ($klice as $klic) {
        $keyCode = $klic['key_code'];
        $created = $klic['created_at'];

        // Hledat emaily v casovem okne
        $stmt = $pdo->prepare("
            SELECT id, recipient_email, subject, created_at, sent_at,
                   TIMESTAMPDIFF(MINUTE, :cas1, created_at) as rozdil_minut
            FROM wgs_email_queue
            WHERE created_at BETWEEN DATE_SUB(:cas2, INTERVAL 30 MINUTE)
                                 AND DATE_ADD(:cas3, INTERVAL 30 MINUTE)
            ORDER BY ABS(TIMESTAMPDIFF(MINUTE, :cas4, created_at))
            LIMIT 10
        ");
        $stmt->execute([
            ':cas1' => $created,
            ':cas2' => $created,
            ':cas3' => $created,
            ':cas4' => $created
        ]);
        $emailyVOkne = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo "<tr class='klic-row'>";
        echo "<td>" . htmlspecialchars(strtoupper($klic['key_type'])) . "</td>";
        echo "<td><code>" . htmlspecialchars($keyCode) . "</code></td>";
        echo "<td>" . htmlspecialchars($created) . "</td>";
        echo "<td colspan='2'><strong>" . count($emailyVOkne) . " emailu v okne</strong></td>";
        echo "</tr>";

        if (count($emailyVOkne) > 0) {
            foreach ($emailyVOkne as $email) {
                $rozdil = abs($email['rozdil_minut']);
                $trida = $rozdil <= 5 ? 'match' : ($rozdil <= 15 ? 'close' : '');

                echo "<tr class='{$trida}'>";
                echo "<td></td><td></td>";
                echo "<td>" . htmlspecialchars($email['created_at']) . " <span class='time-diff'>(";
                echo ($email['rozdil_minut'] >= 0 ? '+' : '') . $email['rozdil_minut'] . " min)</span></td>";
                echo "<td>" . htmlspecialchars($email['recipient_email']) . "<br><small>" . htmlspecialchars(substr($email['subject'], 0, 50)) . "</small></td>";

                // Pokud je rozdil <= 5 minut, je to pravdepodobny kandidat
                if ($rozdil <= 5) {
                    echo "<td><strong style='color:green;'>VELMI PRAVDEPODOBNE</strong></td>";
                    $potencialniPrirazeni[$keyCode] = $email['recipient_email'];
                } elseif ($rozdil <= 15) {
                    echo "<td style='color:orange;'>Mozne</td>";
                    if (!isset($potencialniPrirazeni[$keyCode])) {
                        $potencialniPrirazeni[$keyCode] = $email['recipient_email'] . ' (?)';
                    }
                } else {
                    echo "<td style='color:#999;'>Malo pravdepodobne</td>";
                }
                echo "</tr>";
            }
        } else {
            echo "<tr><td></td><td colspan='4' style='color:#999;'>Zadne emaily v casovem okne</td></tr>";
        }
    }
    echo "</table>";

    // 3. Souhrn potencialnich prirazeni
    if (count($potencialniPrirazeni) > 0) {
        echo "<h2>Potencialni prirazeni</h2>";
        echo "<p>Na zaklade casove korelace (rozdil max 5 minut):</p>";
        echo "<table>";
        echo "<tr><th>Klic</th><th>Predpokladany email</th><th>Akce</th></tr>";
        foreach ($potencialniPrirazeni as $klic => $email) {
            echo "<tr>";
            echo "<td><code>" . htmlspecialchars($klic) . "</code></td>";
            echo "<td>" . htmlspecialchars($email) . "</td>";
            echo "<td>";
            if (strpos($email, '(?)') === false) {
                echo "<a href='?prirad=" . urlencode($klic) . "&email=" . urlencode($email) . "' class='btn'>Priradit</a>";
            }
            echo "</td>";
            echo "</tr>";
        }
        echo "</table>";

        // Zpracovat prirazeni
        if (isset($_GET['prirad']) && isset($_GET['email'])) {
            $klicPrirad = $_GET['prirad'];
            $emailPrirad = $_GET['email'];

            $stmt = $pdo->prepare("
                UPDATE wgs_registration_keys
                SET sent_to_email = :email, sent_at = NOW()
                WHERE key_code = :key_code AND sent_to_email IS NULL
            ");
            $stmt->execute([':email' => $emailPrirad, ':key_code' => $klicPrirad]);

            if ($stmt->rowCount() > 0) {
                echo "<div style='background:#d4edda;padding:10px;margin:10px 0;'>";
                echo "<strong>PRIRAZENO:</strong> Klic " . htmlspecialchars($klicPrirad);
                echo " -> " . htmlspecialchars($emailPrirad);
                echo "</div>";
                echo "<script>setTimeout(function(){ window.location.href = window.location.pathname; }, 2000);</script>";
            }
        }
    }

    // 4. Vsechny emaily z dnesniho dne pro kontext
    echo "<h2>Vsechny emaily z dneska (pro kontext)</h2>";
    $stmt = $pdo->query("
        SELECT recipient_email, subject, created_at, sent_at, status
        FROM wgs_email_queue
        WHERE DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
        ORDER BY created_at DESC
        LIMIT 50
    ");
    $dnesniEmaily = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($dnesniEmaily) > 0) {
        echo "<table>";
        echo "<tr><th>Email</th><th>Predmet</th><th>Vytvoreno</th><th>Odeslano</th><th>Status</th></tr>";
        foreach ($dnesniEmaily as $e) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($e['recipient_email']) . "</td>";
            echo "<td>" . htmlspecialchars(substr($e['subject'], 0, 40)) . "...</td>";
            echo "<td>" . htmlspecialchars($e['created_at']) . "</td>";
            echo "<td>" . htmlspecialchars($e['sent_at'] ?? '-') . "</td>";
            echo "<td>" . htmlspecialchars($e['status']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

} catch (Exception $e) {
    echo "<div style='background:#f8d7da;padding:10px;'><strong>CHYBA:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<br><a href='admin.php?tab=keys' class='btn'>Zpet do admin panelu</a>";
echo "</div></body></html>";
?>
