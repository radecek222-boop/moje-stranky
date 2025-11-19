<?php
/**
 * Kontrola a oprava SMTP konfigurace na správné hodnoty
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Oprava SMTP konfigurace</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 900px;
               margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; }
        table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #2D5016; color: white; }
        .success { background: #d4edda; color: #155724; padding: 15px;
                   border-radius: 5px; margin: 15px 0; border-left: 4px solid #28a745; }
        .error { background: #f8d7da; color: #721c24; padding: 15px;
                 border-radius: 5px; margin: 15px 0; border-left: 4px solid #dc3545; }
        .warning { background: #fff3cd; color: #856404; padding: 15px;
                   border-radius: 5px; margin: 15px 0; border-left: 4px solid #ffc107; }
        .btn { padding: 12px 24px; background: #2D5016; color: white;
               border: none; border-radius: 5px; cursor: pointer; margin: 5px;
               font-size: 16px; text-decoration: none; display: inline-block; }
        .btn:hover { background: #1a300d; }
        code { background: #f4f4f4; padding: 3px 8px; border-radius: 3px;
               font-family: monospace; color: #c7254e; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 Oprava SMTP konfigurace</h1>";

try {
    $pdo = getDbConnection();

    // Načíst aktuální konfiguraci
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1 LIMIT 1");
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$current) {
        throw new Exception('SMTP nastavení nenalezeno v databázi');
    }

    echo "<h2>📋 Aktuální konfigurace:</h2>";
    echo "<table>";
    echo "<tr><th>Parametr</th><th>Aktuální hodnota</th><th>Správná hodnota</th></tr>";

    $changes = [];

    // Host
    $correctHost = 'websmtp.cesky-hosting.cz';
    echo "<tr>";
    echo "<td>SMTP Host</td>";
    echo "<td><code>{$current['smtp_host']}</code></td>";
    echo "<td><code>{$correctHost}</code></td>";
    echo "</tr>";
    if ($current['smtp_host'] !== $correctHost) {
        $changes[] = 'smtp_host';
    }

    // Port
    $correctPort = 25;
    echo "<tr>";
    echo "<td>SMTP Port</td>";
    echo "<td><code>{$current['smtp_port']}</code></td>";
    echo "<td><code>{$correctPort}</code></td>";
    echo "</tr>";
    if ($current['smtp_port'] != $correctPort) {
        $changes[] = 'smtp_port';
    }

    // Encryption - KRITICKÉ!
    $correctEncryption = 'tls';
    echo "<tr>";
    echo "<td><strong>SMTP Encryption</strong></td>";
    echo "<td><code><strong>{$current['smtp_encryption']}</strong></code></td>";
    echo "<td><code><strong>{$correctEncryption}</strong></code></td>";
    echo "</tr>";
    if ($current['smtp_encryption'] !== $correctEncryption) {
        $changes[] = 'smtp_encryption';
    }

    // Username
    $correctUsername = 'wgs-service.cz';
    echo "<tr>";
    echo "<td>SMTP Username</td>";
    echo "<td><code>{$current['smtp_username']}</code></td>";
    echo "<td><code>{$correctUsername}</code></td>";
    echo "</tr>";
    if ($current['smtp_username'] !== $correctUsername) {
        $changes[] = 'smtp_username';
    }

    // FROM Email
    $correctFrom = 'reklamace@wgs-service.cz';
    echo "<tr>";
    echo "<td>FROM Email</td>";
    echo "<td><code>{$current['smtp_from_email']}</code></td>";
    echo "<td><code>{$correctFrom}</code></td>";
    echo "</tr>";
    if ($current['smtp_from_email'] !== $correctFrom) {
        $changes[] = 'smtp_from_email';
    }

    echo "</table>";

    if (empty($changes)) {
        echo "<div class='success'>";
        echo "✅ <strong>Konfigurace je SPRÁVNÁ!</strong><br><br>";
        echo "Všechny parametry odpovídají požadované konfiguraci.<br>";
        echo "Problém s emailem je způsoben cached SPF na WebSMTP serveru.<br><br>";
        echo "<strong>Doporučení:</strong><br>";
        echo "• Počkat 10-30 minut na DNS propagaci<br>";
        echo "• Nebo kontaktovat support Českého Hostingu pro vyčištění cache";
        echo "</div>";

        echo "<a href='/smtp_test.php' class='btn'>Test SMTP</a>";

    } else {
        echo "<div class='warning'>";
        echo "⚠️ <strong>Nalezeny rozdíly:</strong><br>";
        echo "Tyto parametry je třeba opravit: <code>" . implode(', ', $changes) . "</code>";
        echo "</div>";

        // Formulář pro opravu
        if (isset($_POST['apply_fix'])) {
            // CSRF ochrana
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                die("<div class='error'>❌ Neplatný CSRF token</div>");
            }

            $stmt = $pdo->prepare("
                UPDATE wgs_smtp_settings
                SET smtp_host = :host,
                    smtp_port = :port,
                    smtp_encryption = :encryption,
                    smtp_username = :username,
                    smtp_from_email = :from_email
                WHERE is_active = 1
            ");

            $stmt->execute([
                ':host' => $correctHost,
                ':port' => $correctPort,
                ':encryption' => $correctEncryption,
                ':username' => $correctUsername,
                ':from_email' => $correctFrom
            ]);

            echo "<div class='success'>";
            echo "✅ <strong>Konfigurace úspěšně opravena!</strong><br><br>";
            echo "Nové nastavení:<br>";
            echo "• Host: <code>{$correctHost}:{$correctPort}</code><br>";
            echo "• Encryption: <code>{$correctEncryption}</code> (STARTTLS)<br>";
            echo "• Username: <code>{$correctUsername}</code><br>";
            echo "• FROM: <code>{$correctFrom}</code><br><br>";
            echo "<strong>Další kroky:</strong><br>";
            echo "1. Počkat 10-30 minut na DNS propagaci SPF<br>";
            echo "2. Zkusit test emailu<br>";
            echo "3. Pokud stále selže, kontaktovat support";
            echo "</div>";

            echo "<a href='/smtp_test.php' class='btn'>Test SMTP</a>";
            echo "<a href='/protokol.php' class='btn'>Zpět na protokol</a>";

        } else {
            echo "<form method='POST'>";
            $csrfToken = generateCSRFToken();
            echo "<input type='hidden' name='csrf_token' value='{$csrfToken}'>";
            echo "<button type='submit' name='apply_fix' class='btn'>✓ Opravit konfiguraci</button>";
            echo "</form>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
