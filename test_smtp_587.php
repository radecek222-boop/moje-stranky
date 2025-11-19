<?php
/**
 * Test SMTP na portu 587 (submission port) s různými konfiguracemi
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

    echo "=== TEST SMTP PORT 587 (Submission Port) ===\n\n";

    $variants = [
        [
            'name' => 'smtp.ceskyhosting.cz:587 + TLS + reklamace@',
            'host' => 'smtp.ceskyhosting.cz',
            'port' => 587,
            'auth' => true,
            'username' => 'reklamace@wgs-service.cz',
            'password' => $password,
            'from' => 'reklamace@wgs-service.cz',
            'encryption' => 'tls'
        ],
        [
            'name' => 'smtp.ceskyhosting.cz:587 + TLS + doména',
            'host' => 'smtp.ceskyhosting.cz',
            'port' => 587,
            'auth' => true,
            'username' => 'wgs-service.cz',
            'password' => $password,
            'from' => 'reklamace@wgs-service.cz',
            'encryption' => 'tls'
        ],
        [
            'name' => 'smtp.ceskyhosting.cz:587 BEZ TLS (plain)',
            'host' => 'smtp.ceskyhosting.cz',
            'port' => 587,
            'auth' => true,
            'username' => 'reklamace@wgs-service.cz',
            'password' => $password,
            'from' => 'reklamace@wgs-service.cz',
            'encryption' => 'none'
        ],
        [
            'name' => 'websmtp.cesky-hosting.cz:587 + TLS',
            'host' => 'websmtp.cesky-hosting.cz',
            'port' => 587,
            'auth' => true,
            'username' => 'wgs-service.cz',
            'password' => $password,
            'from' => 'reklamace@wgs-service.cz',
            'encryption' => 'tls'
        ],
        [
            'name' => 'mail.wgs-service.cz:587 + TLS',
            'host' => 'mail.wgs-service.cz',
            'port' => 587,
            'auth' => true,
            'username' => 'reklamace@wgs-service.cz',
            'password' => $password,
            'from' => 'reklamace@wgs-service.cz',
            'encryption' => 'tls'
        ]
    ];

    $success = null;

    foreach ($variants as $i => $v) {
        echo str_repeat('=', 60) . "\n";
        echo "TEST #{$i}: {$v['name']}\n";
        echo str_repeat('=', 60) . "\n";
        echo "Host: {$v['host']}:{$v['port']}\n";
        echo "Auth: YES\n";
        echo "User: {$v['username']}\n";
        echo "Pass: ***\n";
        echo "FROM: {$v['from']}\n";
        echo "Encryption: {$v['encryption']}\n\n";

        try {
            $mail = new PHPMailer(true);
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
            $mail->Debugoutput = 'echo';
            $mail->Timeout = 10; // 10 sekund timeout

            $mail->isSMTP();
            $mail->Host = $v['host'];
            $mail->Port = $v['port'];
            $mail->SMTPAuth = true;
            $mail->Username = $v['username'];
            $mail->Password = $v['password'];

            if ($v['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($v['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->CharSet = 'UTF-8';
            $mail->setFrom($v['from'], 'WGS Test');
            $mail->addAddress('reklamace@wgs-service.cz');
            $mail->Subject = 'Test Port 587 #' . $i . ' - ' . date('H:i:s');
            $mail->Body = "Test konfigurace:\n" . $v['name'] . "\nČas: " . date('Y-m-d H:i:s');

            $mail->send();

            echo "\n\n✅✅✅ ÚSPĚCH! FUNGUJÍCÍ KONFIGURACE NALEZENA! ✅✅✅\n";
            $success = $v;
            $_SESSION['successful_smtp_variant'] = $v;
            break;

        } catch (Exception $e) {
            echo "\n\n❌ SELHALO\n";
            echo "Chyba: " . $e->getMessage() . "\n";

            if (strpos($e->getMessage(), 'connect') !== false) {
                echo "-> Problém s připojením k serveru\n";
            } elseif (strpos($e->getMessage(), 'AUTH') !== false) {
                echo "-> Autentizační problém (špatné heslo nebo username)\n";
            } elseif (strpos($e->getMessage(), 'SPF') !== false) {
                echo "-> SPF Policy Error\n";
            } elseif (strpos($e->getMessage(), 'timed out') !== false) {
                echo "-> Timeout - server neodpovídá\n";
            }
            echo "\n";
        }
    }

    echo "\n" . str_repeat('=', 60) . "\n";
    echo "VÝSLEDEK TESTOVÁNÍ PORT 587\n";
    echo str_repeat('=', 60) . "\n";

    if ($success) {
        echo "✅ NALEZENA FUNGUJÍCÍ KONFIGURACE!\n\n";
        echo "Host: {$success['host']}:{$success['port']}\n";
        echo "Username: {$success['username']}\n";
        echo "FROM: {$success['from']}\n";
        echo "Encryption: {$success['encryption']}\n\n";
        echo "Pro aplikaci změn otevřete:\n";
        echo "https://www.wgs-service.cz/aplikuj_smtp_fix.php\n";
    } else {
        echo "❌ ŽÁDNÁ KONFIGURACE NA PORTU 587 NEFUNGOVALA\n\n";
        echo "Možné příčiny:\n";
        echo "1. Špatné heslo pro email účet reklamace@wgs-service.cz\n";
        echo "2. Port 587 je blokován firewallem na serveru\n";
        echo "3. SMTP servery jsou dočasně nedostupné\n";
        echo "4. Email účet reklamace@wgs-service.cz neexistuje nebo je deaktivován\n\n";

        echo "DOPORUČENÍ:\n";
        echo "1. Zkontrolujte heslo v cPanel pro reklamace@wgs-service.cz\n";
        echo "2. Zkontrolujte, že email účet je aktivní\n";
        echo "3. Kontaktujte Český Hosting support:\n";
        echo "   Email: podpora@cesky-hosting.cz\n";
        echo "   Tel: +420 222 746 151\n";
    }

} catch (Exception $e) {
    echo "❌ KRITICKÁ CHYBA: " . $e->getMessage() . "\n";
}
?>
