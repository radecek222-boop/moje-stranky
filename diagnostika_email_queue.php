<?php
/**
 * Diagnostika selhavších emailů
 * Zobrazí přesné chybové zprávy z email fronty
 */
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Diagnostika Email Queue</title>
<style>
body{font-family:'Segoe UI',monospace;padding:20px;background:#f5f5f5;max-width:1400px;margin:0 auto;}
h1{color:#2D5016;border-bottom:3px solid #2D5016;padding-bottom:10px;}
h2{color:#2D5016;margin-top:30px;}
.error{background:#f8d7da;border:1px solid #f5c6cb;color:#721c24;padding:15px;border-radius:5px;margin:15px 0;}
.success{background:#d4edda;border:1px solid #c3e6cb;color:#155724;padding:15px;border-radius:5px;margin:15px 0;}
.info{background:#d1ecf1;border:1px solid #bee5eb;color:#0c5460;padding:15px;border-radius:5px;margin:15px 0;}
table{width:100%;border-collapse:collapse;margin:20px 0;background:white;}
th,td{border:1px solid #ddd;padding:10px;text-align:left;font-size:13px;}
th{background:#2D5016;color:white;position:sticky;top:0;}
tr:hover{background:#f5f5f5;}
code{background:#f4f4f4;padding:2px 6px;border-radius:3px;font-family:monospace;font-size:12px;}
.btn{display:inline-block;padding:10px 20px;background:#2D5016;color:white;text-decoration:none;border-radius:5px;margin:10px 5px 10px 0;}
.btn:hover{background:#1a300d;}
pre{background:#f4f4f4;padding:15px;border-radius:5px;overflow-x:auto;font-size:12px;}
.status-pending{color:#856404;font-weight:bold;}
.status-failed{color:#721c24;font-weight:bold;}
.status-sent{color:#155724;font-weight:bold;}
</style></head><body>";

try {
    $pdo = getDbConnection();

    echo "<h1>🔍 Diagnostika Email Queue</h1>";

    // SMTP konfigurace
    echo "<h2>1️⃣ Aktuální SMTP konfigurace</h2>";
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1 LIMIT 1");
    $smtp = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($smtp) {
        echo "<div class='info'>";
        echo "<strong>SMTP nastavení:</strong><br>";
        echo "Host: <code>{$smtp['smtp_host']}</code><br>";
        echo "Port: <code>{$smtp['smtp_port']}</code><br>";
        echo "Encryption: <code>{$smtp['smtp_encryption']}</code><br>";
        echo "Username: <code>{$smtp['smtp_username']}</code><br>";
        echo "Password: <code>" . (strlen($smtp['smtp_password']) > 0 ? str_repeat('*', 10) : 'PRÁZDNÉ!') . "</code><br>";
        echo "From Email: <code>{$smtp['smtp_from_email']}</code><br>";
        echo "From Name: <code>{$smtp['smtp_from_name']}</code><br>";
        echo "</div>";
    } else {
        echo "<div class='error'>❌ Nenalezena žádná aktivní SMTP konfigurace!</div>";
    }

    // Statistiky
    echo "<h2>2️⃣ Statistiky email fronty</h2>";
    $stmt = $pdo->query("
        SELECT status, COUNT(*) as count
        FROM wgs_email_queue
        GROUP BY status
    ");
    $stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table>";
    echo "<tr><th>Status</th><th>Počet emailů</th></tr>";
    foreach ($stats as $stat) {
        $class = "status-" . $stat['status'];
        echo "<tr><td class='{$class}'>{$stat['status']}</td><td><strong>{$stat['count']}</strong></td></tr>";
    }
    echo "</table>";

    // Selhavší emaily
    echo "<h2>3️⃣ Poslední selhavší emaily (failed/pending s chybou)</h2>";
    $stmt = $pdo->query("
        SELECT
            id,
            recipient_email,
            subject,
            status,
            attempts,
            max_attempts,
            error_message,
            created_at,
            updated_at
        FROM wgs_email_queue
        WHERE status IN ('failed', 'pending')
          AND (error_message IS NOT NULL OR attempts > 0)
        ORDER BY updated_at DESC
        LIMIT 10
    ");
    $failed = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($failed) > 0) {
        echo "<table>";
        echo "<tr>";
        echo "<th>ID</th>";
        echo "<th>Příjemce</th>";
        echo "<th>Předmět</th>";
        echo "<th>Status</th>";
        echo "<th>Pokusy</th>";
        echo "<th>Chybová zpráva</th>";
        echo "<th>Aktualizováno</th>";
        echo "</tr>";

        foreach ($failed as $email) {
            $statusClass = "status-" . $email['status'];
            echo "<tr>";
            echo "<td><code>#{$email['id']}</code></td>";
            echo "<td><code>{$email['recipient_email']}</code></td>";
            echo "<td>" . htmlspecialchars(substr($email['subject'], 0, 50)) . "...</td>";
            echo "<td class='{$statusClass}'>{$email['status']}</td>";
            echo "<td><strong>{$email['attempts']}/{$email['max_attempts']}</strong></td>";
            echo "<td><pre style='margin:0;'>" . htmlspecialchars($email['error_message'] ?: 'N/A') . "</pre></td>";
            echo "<td>" . date('d.m.Y H:i:s', strtotime($email['updated_at'])) . "</td>";
            echo "</tr>";
        }

        echo "</table>";
    } else {
        echo "<div class='success'>✅ Žádné selhavší emaily!</div>";
    }

    // Úspěšně odeslané
    echo "<h2>4️⃣ Poslední úspěšně odeslané emaily</h2>";
    $stmt = $pdo->query("
        SELECT
            id,
            recipient_email,
            subject,
            sent_at
        FROM wgs_email_queue
        WHERE status = 'sent'
        ORDER BY sent_at DESC
        LIMIT 5
    ");
    $sent = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (count($sent) > 0) {
        echo "<table>";
        echo "<tr><th>ID</th><th>Příjemce</th><th>Předmět</th><th>Odesláno</th></tr>";
        foreach ($sent as $email) {
            echo "<tr>";
            echo "<td><code>#{$email['id']}</code></td>";
            echo "<td><code>{$email['recipient_email']}</code></td>";
            echo "<td>" . htmlspecialchars(substr($email['subject'], 0, 60)) . "</td>";
            echo "<td>" . date('d.m.Y H:i:s', strtotime($email['sent_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "<div class='info'>ℹ️ Zatím žádné úspěšně odeslané emaily.</div>";
    }

    // Test PHPMailer
    echo "<h2>5️⃣ Test PHPMailer</h2>";

    if (file_exists(__DIR__ . '/vendor/autoload.php')) {
        require_once __DIR__ . '/vendor/autoload.php';

        if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
            echo "<div class='success'>✅ PHPMailer je nainstalován a dostupný</div>";

            // Zkusit vytvořit instanci
            try {
                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                echo "<div class='success'>✅ PHPMailer instance vytvořena úspěšně</div>";

                // Test SMTP připojení
                echo "<div class='info'>";
                echo "<strong>Test SMTP připojení:</strong><br>";
                echo "Pokouším se připojit na <code>{$smtp['smtp_host']}:{$smtp['smtp_port']}</code>...<br>";

                $mail->isSMTP();
                $mail->Host = $smtp['smtp_host'];
                $mail->Port = $smtp['smtp_port'];
                $mail->SMTPAuth = !empty($smtp['smtp_username']);
                $mail->Username = $smtp['smtp_username'];
                $mail->Password = $smtp['smtp_password'];

                if ($smtp['smtp_encryption'] === 'ssl') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
                } elseif ($smtp['smtp_encryption'] === 'tls') {
                    $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                }

                $mail->Timeout = 10;
                $mail->SMTPDebug = 2; // Verbose debug output

                // Capture debug output
                ob_start();

                try {
                    $mail->smtpConnect();
                    echo "✅ SMTP připojení ÚSPĚŠNÉ!<br>";
                } catch (Exception $e) {
                    echo "❌ SMTP připojení SELHALO!<br>";
                    echo "Chyba: " . htmlspecialchars($e->getMessage()) . "<br>";
                }

                $debug = ob_get_clean();
                echo "<pre>" . htmlspecialchars($debug) . "</pre>";
                echo "</div>";

            } catch (Exception $e) {
                echo "<div class='error'>❌ Chyba při vytváření PHPMailer instance: " . htmlspecialchars($e->getMessage()) . "</div>";
            }

        } else {
            echo "<div class='error'>❌ PHPMailer třída nebyla nalezena!</div>";
        }
    } else {
        echo "<div class='error'>❌ Vendor autoload.php neexistuje - PHPMailer není nainstalován!</div>";
        echo "<div class='info'>";
        echo "<strong>Jak nainstalovat PHPMailer:</strong><br>";
        echo "1. Přes SSH: <code>composer require phpmailer/phpmailer</code><br>";
        echo "2. Nebo manuálně viz: <code>INSTALACE_PHPMAILER.md</code>";
        echo "</div>";
    }

    // PHP Error Log
    echo "<h2>6️⃣ Poslední PHP chyby</h2>";
    $logFile = __DIR__ . '/logs/php_errors.log';
    if (file_exists($logFile)) {
        $lines = file($logFile);
        $lastLines = array_slice($lines, -20); // Posledních 20 řádků

        echo "<pre style='max-height:400px;overflow-y:auto;'>";
        echo htmlspecialchars(implode('', $lastLines));
        echo "</pre>";
    } else {
        echo "<div class='info'>ℹ️ Log soubor neexistuje: <code>{$logFile}</code></div>";
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ CHYBA: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "<hr>";
echo "<a href='/admin.php' class='btn'>← Zpět na Admin</a> ";
echo "<a href='/scripts/process_email_queue.php' class='btn'>Spustit Queue Worker</a>";
echo "</div></body></html>";
?>
