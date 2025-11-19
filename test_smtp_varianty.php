<?php
/**
 * Test různých SMTP konfigurací pro řešení SPF problému
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/plain; charset=utf-8');

try {
    $pdo = getDbConnection();

    // Načíst aktuální heslo
    $stmt = $pdo->query("SELECT smtp_password FROM wgs_smtp_settings WHERE is_active = 1 LIMIT 1");
    $currentPassword = $stmt->fetchColumn();

    echo "=== TEST RŮZNÝCH SMTP KONFIGURACÍ ===\n\n";

    // Varianty pro testování
    $variants = [
        [
            'name' => 'Varianta 1: Bez autentizace (port 25)',
            'host' => 'websmtp.cesky-hosting.cz',
            'port' => 25,
            'auth' => false,
            'username' => '',
            'password' => '',
            'from' => 'reklamace@wgs-service.cz',
            'encryption' => 'none'
        ],
        [
            'name' => 'Varianta 2: Autentizace s doménou (aktuální)',
            'host' => 'websmtp.cesky-hosting.cz',
            'port' => 25,
            'auth' => true,
            'username' => 'wgs-service.cz',
            'password' => $currentPassword,
            'from' => 'reklamace@wgs-service.cz',
            'encryption' => 'none'
        ],
        [
            'name' => 'Varianta 3: Jiný FROM email (info@)',
            'host' => 'websmtp.cesky-hosting.cz',
            'port' => 25,
            'auth' => true,
            'username' => 'wgs-service.cz',
            'password' => $currentPassword,
            'from' => 'info@wgs-service.cz',
            'encryption' => 'none'
        ],
        [
            'name' => 'Varianta 4: Jiný FROM email (admin@)',
            'host' => 'websmtp.cesky-hosting.cz',
            'port' => 25,
            'auth' => true,
            'username' => 'wgs-service.cz',
            'password' => $currentPassword,
            'from' => 'admin@wgs-service.cz',
            'encryption' => 'none'
        ],
        [
            'name' => 'Varianta 5: Běžný SMTP smtp.ceskyhosting.cz',
            'host' => 'smtp.ceskyhosting.cz',
            'port' => 587,
            'auth' => true,
            'username' => 'reklamace@wgs-service.cz',
            'password' => $currentPassword,
            'from' => 'reklamace@wgs-service.cz',
            'encryption' => 'tls'
        ]
    ];

    $successVariant = null;

    foreach ($variants as $i => $variant) {
        echo "\n" . str_repeat('=', 60) . "\n";
        echo "TEST #{$i}: {$variant['name']}\n";
        echo str_repeat('=', 60) . "\n";
        echo "Host: {$variant['host']}:{$variant['port']}\n";
        echo "Auth: " . ($variant['auth'] ? 'YES' : 'NO') . "\n";
        if ($variant['auth']) {
            echo "User: {$variant['username']}\n";
            echo "Pass: " . (empty($variant['password']) ? '(prázdné)' : '***') . "\n";
        }
        echo "From: {$variant['from']}\n";
        echo "Encryption: {$variant['encryption']}\n\n";

        try {
            $mail = new PHPMailer(true);
            $mail->SMTPDebug = 0; // Vypnout debug pro přehlednost
            $mail->isSMTP();
            $mail->Host = $variant['host'];
            $mail->Port = $variant['port'];

            if ($variant['auth']) {
                $mail->SMTPAuth = true;
                $mail->Username = $variant['username'];
                $mail->Password = $variant['password'];
            } else {
                $mail->SMTPAuth = false;
            }

            if ($variant['encryption'] === 'tls') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($variant['encryption'] === 'ssl') {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            }

            $mail->CharSet = 'UTF-8';
            $mail->setFrom($variant['from'], 'WGS Test');
            $mail->addAddress('reklamace@wgs-service.cz');
            $mail->Subject = 'Test varianta #' . $i . ' - ' . date('H:i:s');
            $mail->Body = "Testovací email - varianta #{$i}\n" . $variant['name'];

            $mail->send();

            echo "✅ ÚSPĚCH! Email odeslán.\n";
            echo ">>> TATO KONFIGURACE FUNGUJE! <<<\n";

            $successVariant = $variant;
            break; // Přerušit po prvním úspěchu

        } catch (Exception $e) {
            echo "❌ SELHALO\n";
            echo "Chyba: " . $e->getMessage() . "\n";

            if (strpos($e->getMessage(), 'SPF') !== false) {
                echo "-> Stále SPF Policy Error\n";
            } elseif (strpos($e->getMessage(), 'AUTH') !== false) {
                echo "-> Autentizační problém\n";
            } elseif (strpos($e->getMessage(), 'connect') !== false) {
                echo "-> Problém s připojením\n";
            }
        }
    }

    echo "\n\n" . str_repeat('=', 60) . "\n";
    echo "VÝSLEDEK TESTOVÁNÍ\n";
    echo str_repeat('=', 60) . "\n";

    if ($successVariant) {
        echo "✅ NALEZENA FUNGUJÍCÍ KONFIGURACE!\n\n";
        echo "Host: {$successVariant['host']}:{$successVariant['port']}\n";
        echo "Auth: " . ($successVariant['auth'] ? 'YES' : 'NO') . "\n";
        if ($successVariant['auth']) {
            echo "Username: {$successVariant['username']}\n";
        }
        echo "FROM: {$successVariant['from']}\n";
        echo "Encryption: {$successVariant['encryption']}\n\n";

        // Uložit úspěšnou variantu do session (BEZPEČNĚ bez hesla v URL)
        $_SESSION['successful_smtp_variant'] = $successVariant;

        echo "Pro aplikaci změn otevřete:\n";
        echo "https://www.wgs-service.cz/aplikuj_smtp_fix.php\n";

    } else {
        echo "❌ ŽÁDNÁ KONFIGURACE NEFUNGOVALA\n\n";
        echo "Doporučení:\n";
        echo "1. Kontaktujte support Českého Hostingu\n";
        echo "2. Zkontrolujte, že email účet existuje v cPanel\n";
        echo "3. Zkontrolujte SPF záznam v cPanel DNS Zone Editor\n";
        echo "4. Požádejte o vymazání cache na websmtp.cesky-hosting.cz\n";
    }

} catch (Exception $e) {
    echo "\n\n❌ KRITICKÁ CHYBA: " . $e->getMessage() . "\n";
}
?>
