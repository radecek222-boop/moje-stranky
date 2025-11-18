<?php
/**
 * WGS Service - SMTP Test (Web verze)
 * Testuje SMTP a posílá testovací email
 */

require_once __DIR__ . '/init.php';

// Bezpečnost - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

$testResult = null;
$error = null;
$smtpConfig = null;

// Načíst SMTP konfiguraci
try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("
        SELECT * FROM wgs_smtp_settings
        WHERE is_active = 1
        ORDER BY id DESC
        LIMIT 1
    ");
    $smtpConfig = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $error = "Chyba při načítání konfigurace: " . $e->getMessage();
}

// Odeslání testovacího emailu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_test'])) {
    try {
        $recipientEmail = $_POST['recipient_email'] ?? '';

        if (!filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Neplatná emailová adresa');
        }

        if (!$smtpConfig) {
            throw new Exception('SMTP není nakonfigurováno');
        }

        // Načíst PHPMailer
        require_once __DIR__ . '/vendor/autoload.php';

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);

        // SMTP konfigurace
        $mail->isSMTP();
        $mail->Host = $smtpConfig['smtp_host'];
        $mail->Port = $smtpConfig['smtp_port'];
        $mail->SMTPAuth = !empty($smtpConfig['smtp_username']);

        if ($mail->SMTPAuth) {
            $mail->Username = $smtpConfig['smtp_username'];
            $mail->Password = $smtpConfig['smtp_password'];
        }

        $mail->CharSet = 'UTF-8';

        // Šifrování
        if ($smtpConfig['smtp_encryption'] === 'ssl') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($smtpConfig['smtp_encryption'] === 'tls') {
            $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        }

        // Debug
        $mail->SMTPDebug = 0;

        // Odesílatel a příjemce
        $mail->setFrom($smtpConfig['smtp_from_email'], $smtpConfig['smtp_from_name'] ?? 'WGS Service');
        $mail->addAddress($recipientEmail);

        // Obsah
        $mail->isHTML(true);
        $mail->Subject = 'Test email z WGS Service';
        $mail->Body = "
            <h2>Testovací email</h2>
            <p>Tento email byl odeslán z WGS Service pro otestování SMTP konfigurace.</p>
            <hr>
            <p><strong>SMTP Server:</strong> {$smtpConfig['smtp_host']}:{$smtpConfig['smtp_port']}</p>
            <p><strong>Čas:</strong> " . date('Y-m-d H:i:s') . "</p>
        ";

        // Odeslat
        $mail->send();

        $testResult = [
            'success' => true,
            'message' => "✅ Email byl úspěšně odeslán na $recipientEmail"
        ];

    } catch (\PHPMailer\PHPMailer\Exception $e) {
        $error = "Chyba při odesílání: " . $mail->ErrorInfo;
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>SMTP Test - WGS Service</title>
    <style>
        body { font-family: Arial; max-width: 900px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 15px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th { background: #2D5016; color: white; padding: 10px; text-align: left; }
        table td { padding: 10px; border-bottom: 1px solid #ddd; }
        .form-group { margin: 20px 0; }
        label { display: block; font-weight: 600; margin-bottom: 8px; }
        input[type="email"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; box-sizing: border-box; }
        .btn { display: inline-block; padding: 12px 24px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px; border: none; font-size: 16px; cursor: pointer; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>

<h1>📨 SMTP Test - WGS Service</h1>

<?php if ($testResult && $testResult['success']): ?>
    <div class='success'>
        <?= htmlspecialchars($testResult['message']) ?>
    </div>
<?php elseif ($error): ?>
    <div class='error'>
        <strong>❌ CHYBA:</strong><br>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<?php if ($smtpConfig): ?>
    <h2>📋 Aktuální SMTP konfigurace</h2>

    <table>
        <tr><th>Parametr</th><th>Hodnota</th></tr>
        <tr>
            <td>SMTP Host</td>
            <td><code><?= htmlspecialchars($smtpConfig['smtp_host']) ?></code></td>
        </tr>
        <tr>
            <td>Port</td>
            <td><code><?= htmlspecialchars($smtpConfig['smtp_port']) ?></code></td>
        </tr>
        <tr>
            <td>Username</td>
            <td><code><?= htmlspecialchars($smtpConfig['smtp_username']) ?></code></td>
        </tr>
        <tr>
            <td>Šifrování</td>
            <td><code><?= htmlspecialchars($smtpConfig['smtp_encryption'] ?? 'žádné') ?></code></td>
        </tr>
        <tr>
            <td>From Email</td>
            <td><code><?= htmlspecialchars($smtpConfig['smtp_from_email']) ?></code></td>
        </tr>
        <tr>
            <td>From Name</td>
            <td><?= htmlspecialchars($smtpConfig['smtp_from_name'] ?? 'N/A') ?></td>
        </tr>
    </table>

    <h2>🧪 Odeslat testovací email</h2>

    <form method='POST'>
        <div class='form-group'>
            <label for='recipient_email'>Emailová adresa příjemce *</label>
            <input type='email'
                   id='recipient_email'
                   name='recipient_email'
                   placeholder='vas@email.cz'
                   required
                   value='<?= htmlspecialchars($_SESSION['user_email'] ?? '') ?>'>
        </div>

        <button type='submit' name='send_test' class='btn'>
            📧 Odeslat testovací email
        </button>

        <a href='/diagnoza_smtp.php' class='btn' style='background: #6c757d;'>
            🔍 Diagnostika SMTP
        </a>

        <a href='/admin.php' class='btn' style='background: #6c757d;'>
            ← Zpět do admin
        </a>
    </form>

<?php else: ?>
    <div class='error'>
        <strong>❌ SMTP není nakonfigurováno!</strong><br><br>
        Prosím nastavte SMTP konfiguraci:
        <ul>
            <li><a href='/nastav_websmtp.php'>WebSMTP konfigurátor</a></li>
            <li><a href='/admin/install_email_system.php'>Email System Installer</a></li>
        </ul>
    </div>
<?php endif; ?>

<h2>ℹ️ Informace</h2>

<div class='info'>
    <strong>Co tento test dělá?</strong><br><br>

    1. Načte aktivní SMTP konfiguraci z databáze<br>
    2. Připojí se k SMTP serveru<br>
    3. Odešle testovací email na zadanou adresu<br>
    4. Zobrazí výsledek (úspěch nebo chyba)<br><br>

    Pokud email dorazí, SMTP je správně nakonfigurováno a můžete odesílat protokoly zákazníkům.
</div>

</div>
</body>
</html>
