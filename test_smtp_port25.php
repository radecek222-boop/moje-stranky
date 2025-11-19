<?php
/**
 * Test smtp.ceskyhosting.cz na PORTU 25 (ne 587)
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getDbConnection();
    $stmt = $pdo->query("SELECT smtp_password FROM wgs_smtp_settings WHERE is_active = 1 LIMIT 1");
    $password = $stmt->fetchColumn();

    echo "=== TEST SMTP.CESKYHOSTING.CZ:25 (ne 587!) ===\n\n";

    $variants = [
        [
            'name' => 'smtp.ceskyhosting.cz:25 BEZ autentizace',
            'host' => 'smtp.ceskyhosting.cz',
            'port' => 25,
            'auth' => false,
            'username' => '',
            'password' => '',
            'from' => 'reklamace@wgs-service.cz'
        ],
        [
            'name' => 'smtp.ceskyhosting.cz:25 S autentizací (reklamace@)',
            'host' => 'smtp.ceskyhosting.cz',
            'port' => 25,
            'auth' => true,
            'username' => 'reklamace@wgs-service.cz',
            'password' => $password,
            'from' => 'reklamace@wgs-service.cz'
        ],
        [
            'name' => 'smtp.ceskyhosting.cz:25 S autentizací (wgs-service.cz)',
            'host' => 'smtp.ceskyhosting.cz',
            'port' => 25,
            'auth' => true,
            'username' => 'wgs-service.cz',
            'password' => $password,
            'from' => 'reklamace@wgs-service.cz'
        ]
    ];

    $success = null;

    foreach ($variants as $i => $v) {
        echo str_repeat('=', 60) . "\n";
        echo "TEST #{$i}: {$v['name']}\n";
        echo str_repeat('=', 60) . "\n";

        try {
            $mail = new PHPMailer(true);
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = 'echo';
            $mail->isSMTP();
            $mail->Host = $v['host'];
            $mail->Port = $v['port'];
            $mail->SMTPAuth = $v['auth'];

            if ($v['auth']) {
                $mail->Username = $v['username'];
                $mail->Password = $v['password'];
            }

            $mail->CharSet = 'UTF-8';
            $mail->setFrom($v['from'], 'WGS Test');
            $mail->addAddress('reklamace@wgs-service.cz');
            $mail->Subject = 'Test #' . $i . ' - ' . date('H:i:s');
            $mail->Body = 'Test: ' . $v['name'];

            $mail->send();

            echo "\n\n✅✅✅ ÚSPĚCH! Tato konfigurace funguje! ✅✅✅\n";
            $success = $v;
            $_SESSION['successful_smtp_variant'] = $v;
            $_SESSION['successful_smtp_variant']['encryption'] = 'none';
            break;

        } catch (Exception $e) {
            echo "\n\n❌ SELHALO: " . $e->getMessage() . "\n\n";
        }
    }

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "VÝSLEDEK\n";
    echo str_repeat('=', 60) . "\n";

    if ($success) {
        echo "✅ NALEZENA FUNGUJÍCÍ KONFIGURACE!\n\n";
        echo "Host: {$success['host']}:{$success['port']}\n";
        echo "Auth: " . ($success['auth'] ? 'YES' : 'NO') . "\n";
        if ($success['auth']) {
            echo "User: {$success['username']}\n";
        }
        echo "FROM: {$success['from']}\n\n";
        echo "Pro aplikaci změn otevřete:\n";
        echo "https://www.wgs-service.cz/aplikuj_smtp_fix.php\n";
    } else {
        echo "❌ Ani smtp.ceskyhosting.cz:25 nefunguje.\n\n";
        echo "KRITICKÁ SITUACE:\n";
        echo "1. WebSMTP má cached špatný SPF\n";
        echo "2. smtp.ceskyhosting.cz nefunguje ani na 25 ani na 587\n";
        echo "3. Musíte kontaktovat Český Hosting SUPPORT:\n\n";
        echo "Email: podpora@cesky-hosting.cz\n";
        echo "Telefon: +420 222 746 151\n\n";
        echo "Text supportu:\n";
        echo "---\n";
        echo "Doména: wgs-service.cz\n";
        echo "Problém: WebSMTP odmítá MAIL FROM s chybou '550 5.7.1 SPF Policy Error'\n";
        echo "SPF záznam: v=spf1 a mx include:spf.cesky-hosting.cz include:websmtp.cesky-hosting.cz ~all\n";
        echo "SPF je ověřený jako správný (MXToolbox)\n";
        echo "Prosím vyčistěte DNS cache na websmtp.cesky-hosting.cz\n";
        echo "---\n";
    }

} catch (Exception $e) {
    echo "❌ CHYBA: " . $e->getMessage() . "\n";
}
?>
