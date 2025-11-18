#!/usr/bin/env php
<?php
/**
 * WGS Service - SMTP Configuration Test
 * Testuje SMTP nastavení a posílá testovací email
 */

require_once __DIR__ . '/../init.php';

// Barvy pro CLI output
define('RED', "\033[0;31m");
define('GREEN', "\033[0;32m");
define('YELLOW', "\033[1;33m");
define('BLUE', "\033[0;34m");
define('NC', "\033[0m"); // No Color

echo "==========================================\n";
echo "WGS Service - SMTP Configuration Test\n";
echo "==========================================\n\n";

// Načíst SMTP konfiguraci z databáze (preferováno) nebo fallback na .env
$smtpHost = '';
$smtpPort = 587;
$smtpUser = '';
$smtpPass = '';
$smtpFrom = '';
$smtpFromName = 'WGS Service';
$smtpEncryption = 'tls';

try {
    $pdo = getDbConnection();

    // Zkusit načíst z wgs_smtp_settings
    $stmt = $pdo->query("
        SELECT * FROM wgs_smtp_settings
        WHERE is_active = 1
        ORDER BY id DESC
        LIMIT 1
    ");
    $config = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($config) {
        echo BLUE . "ℹ Using SMTP config from DATABASE (wgs_smtp_settings)\n" . NC;
        $smtpHost = $config['smtp_host'];
        $smtpPort = $config['smtp_port'];
        $smtpUser = $config['smtp_username'];
        $smtpPass = $config['smtp_password'];
        $smtpFrom = $config['smtp_from_email'];
        $smtpFromName = $config['smtp_from_name'] ?? 'WGS Service';
        $smtpEncryption = $config['smtp_encryption'] ?? '';
    } else {
        echo YELLOW . "⚠ No active config in database, using .env\n" . NC;
        $smtpHost = getenv('SMTP_HOST') ?: '';
        $smtpPort = getenv('SMTP_PORT') ?: 587;
        $smtpUser = getenv('SMTP_USER') ?: '';
        $smtpPass = getenv('SMTP_PASS') ?: '';
        $smtpFrom = getenv('SMTP_FROM') ?: '';
        $smtpFromName = getenv('SMTP_FROM_NAME') ?: 'WGS Service';
    }
} catch (Exception $e) {
    echo YELLOW . "⚠ Database error, using .env: " . $e->getMessage() . "\n" . NC;
    $smtpHost = getenv('SMTP_HOST') ?: '';
    $smtpPort = getenv('SMTP_PORT') ?: 587;
    $smtpUser = getenv('SMTP_USER') ?: '';
    $smtpPass = getenv('SMTP_PASS') ?: '';
    $smtpFrom = getenv('SMTP_FROM') ?: '';
    $smtpFromName = getenv('SMTP_FROM_NAME') ?: 'WGS Service';
}

echo "Checking SMTP configuration...\n";
echo "------------------------------\n";

$missingConfig = [];

if (empty($smtpHost)) {
    echo RED . "✗ SMTP_HOST not configured\n" . NC;
    $missingConfig[] = 'SMTP_HOST';
} else {
    echo GREEN . "✓ SMTP_HOST: $smtpHost\n" . NC;
}

if (empty($smtpPort)) {
    echo RED . "✗ SMTP_PORT not configured\n" . NC;
    $missingConfig[] = 'SMTP_PORT';
} else {
    echo GREEN . "✓ SMTP_PORT: $smtpPort\n" . NC;
}

if (empty($smtpUser)) {
    echo RED . "✗ SMTP_USER not configured\n" . NC;
    $missingConfig[] = 'SMTP_USER';
} else {
    echo GREEN . "✓ SMTP_USER: $smtpUser\n" . NC;
}

if (empty($smtpPass)) {
    echo RED . "✗ SMTP_PASS not configured\n" . NC;
    $missingConfig[] = 'SMTP_PASS';
} else {
    echo GREEN . "✓ SMTP_PASS: " . str_repeat('*', min(strlen($smtpPass), 8)) . "\n" . NC;
}

if (empty($smtpFrom)) {
    echo RED . "✗ SMTP_FROM not configured\n" . NC;
    $missingConfig[] = 'SMTP_FROM';
} else {
    echo GREEN . "✓ SMTP_FROM: $smtpFrom\n" . NC;
}

echo "\n";

// Pokud chybí konfigurace, ukončit
if (!empty($missingConfig)) {
    echo RED . "ERROR: Missing SMTP configuration!\n" . NC;
    echo "\nPlease add the following to your .env file:\n\n";

    foreach ($missingConfig as $key) {
        echo "  $key=your_value_here\n";
    }

    echo "\nExample configuration:\n";
    echo "  SMTP_HOST=smtp.gmail.com\n";
    echo "  SMTP_PORT=587\n";
    echo "  SMTP_USER=your-email@gmail.com\n";
    echo "  SMTP_PASS=your-app-password\n";
    echo "  SMTP_FROM=noreply@your-domain.cz\n";
    echo "  SMTP_FROM_NAME=WGS Service\n";
    echo "\n";
    exit(1);
}

// Test konektivitu
echo "Testing SMTP connectivity...\n";
echo "----------------------------\n";

$errno = 0;
$errstr = '';

echo "Connecting to $smtpHost:$smtpPort... ";

$socket = @fsockopen($smtpHost, $smtpPort, $errno, $errstr, 10);

if (!$socket) {
    echo RED . "✗ Failed\n" . NC;
    echo RED . "Error: $errstr ($errno)\n" . NC;
    exit(1);
} else {
    echo GREEN . "✓ Connected\n" . NC;

    // Přečíst úvodní zprávu
    $response = fgets($socket);
    echo BLUE . "Server: " . trim($response) . "\n" . NC;

    fclose($socket);
}

echo "\n";

// Zjistit testovací email
echo "Enter recipient email for test message: ";
$testEmail = trim(fgets(STDIN));

if (empty($testEmail) || !filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
    echo RED . "Invalid email address\n" . NC;
    exit(1);
}

echo "\n";
echo "Sending test email...\n";
echo "---------------------\n";

// Vytvořit testovací zprávu
$subject = "WGS Service - SMTP Test [" . date('Y-m-d H:i:s') . "]";
$body = "Toto je testovací email z WGS Service.\n\n";
$body .= "Pokud jste tento email obdrželi, znamená to, že SMTP konfigurace funguje správně.\n\n";
$body .= "---\n";
$body .= "Datum odeslání: " . date('Y-m-d H:i:s') . "\n";
$body .= "SMTP Server: $smtpHost:$smtpPort\n";
$body .= "Odesílatel: $smtpFrom\n";

// Příprava emailu pomocí PHPMailer nebo nativního mail()
if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    echo "Using PHPMailer...\n";

    $mail = new PHPMailer\PHPMailer\PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = $smtpHost;
        $mail->SMTPAuth = true;
        $mail->Username = $smtpUser;
        $mail->Password = $smtpPass;
        $mail->SMTPSecure = $smtpPort == 465 ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = $smtpPort;
        $mail->CharSet = 'UTF-8';

        // Recipients
        $mail->setFrom($smtpFrom, $smtpFromName);
        $mail->addAddress($testEmail);

        // Content
        $mail->isHTML(false);
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();

        echo GREEN . "✓ Test email sent successfully!\n" . NC;
        echo "\nCheck your inbox at: $testEmail\n";

    } catch (Exception $e) {
        echo RED . "✗ Failed to send email\n" . NC;
        echo RED . "Error: {$mail->ErrorInfo}\n" . NC;
        exit(1);
    }

} else {
    echo "Using PHP mail() function...\n";

    $headers = "From: $smtpFromName <$smtpFrom>\r\n";
    $headers .= "Reply-To: $smtpFrom\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";

    if (mail($testEmail, $subject, $body, $headers)) {
        echo GREEN . "✓ Test email sent successfully!\n" . NC;
        echo "\nCheck your inbox at: $testEmail\n";
        echo YELLOW . "\nNote: PHP mail() doesn't use SMTP credentials from .env\n" . NC;
        echo YELLOW . "Consider installing PHPMailer for full SMTP support\n" . NC;
    } else {
        echo RED . "✗ Failed to send email\n" . NC;
        exit(1);
    }
}

echo "\n";
echo "==========================================\n";
echo GREEN . "SMTP test completed successfully!\n" . NC;
echo "==========================================\n";

exit(0);
