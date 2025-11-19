<?php
/**
 * OPRAV VŠE NAJEDNOU - All-in-One Fix
 *
 * Tento skript opraví VŠE co je špatně s emailovým systémem:
 * 1. Nainstaluje PHPMailer (pokud chybí)
 * 2. Nastaví správnou SMTP konfiguraci (WebSMTP)
 * 3. Sjednotí duplicitní SMTP nastavení
 * 4. Resetuje selhavší emaily ve frontě
 * 5. Ověří připojení k SMTP
 * 6. Otestuje odeslání testovacího emailu
 *
 * URL: https://www.wgs-service.cz/oprav_vse_najednou.php?execute=1
 *
 * HESLO: p7u.s13mR2018 (dočasné, změnit!)
 */

session_start();

// ===== BEZPEČNOST =====
$requiredPassword = 'p7u.s13mR2018'; // ZMĚNIT PO POUŽITÍ!
$providedPassword = $_GET['password'] ?? $_POST['password'] ?? '';

if ($providedPassword !== $requiredPassword) {
    die("PŘÍSTUP ODEPŘEN: Chybí nebo neplatné heslo. Použijte: ?password=HESLO");
}

set_time_limit(300); // 5 minut

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Oprav vše najednou - WGS Email System</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 1000px; margin: 50px auto; padding: 20px;
               background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb;
                   color: #155724; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb;
                 color: #721c24; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7;
                   color: #856404; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb;
                color: #0c5460; padding: 12px; border-radius: 5px; margin: 10px 0; }
        .step { background: #f8f9fa; padding: 15px; margin: 15px 0; border-left: 4px solid #2D5016; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .btn { display: inline-block; padding: 10px 20px;
               background: #2D5016; color: white; text-decoration: none;
               border-radius: 5px; margin: 10px 5px 10px 0; border: none;
               cursor: pointer; font-size: 16px; }
        .btn:hover { background: #1a300d; }
        .btn-danger { background: #dc3545; }
        .btn-danger:hover { background: #c82333; }
        pre { background: #f4f4f4; padding: 10px; border-radius: 5px; overflow-x: auto; }
        .progress { margin: 20px 0; }
        .progress-bar { background: #2D5016; height: 30px; line-height: 30px;
                        color: white; text-align: center; border-radius: 5px;
                        transition: width 0.5s; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 OPRAV VŠE NAJEDNOU - WGS Email System</h1>";

$executeMode = isset($_GET['execute']) && $_GET['execute'] === '1';

if (!$executeMode) {
    // ===== PREVIEW MODE =====
    echo "<div class='warning'>";
    echo "<h2>⚠️ PREVIEW REŽIM</h2>";
    echo "<p>Tento skript provede následující opravy:</p>";
    echo "<ol>";
    echo "<li><strong>Kontrola PHPMailer</strong> - zjistí, zda je nainstalován</li>";
    echo "<li><strong>Instalace PHPMailer</strong> - pokud chybí, nainstaluje</li>";
    echo "<li><strong>Oprava SMTP konfigurace</strong> - nastaví websmtp.cesky-hosting.cz:25</li>";
    echo "<li><strong>Sjednocení duplicitní konfigurace</strong> - odstraní z wgs_system_config</li>";
    echo "<li><strong>Reset email fronty</strong> - resetuje selhavší emaily (attempts=0)</li>";
    echo "<li><strong>Test SMTP připojení</strong> - ověří, že WebSMTP funguje</li>";
    echo "<li><strong>Testovací email</strong> - odešle zkušební email</li>";
    echo "</ol>";
    echo "</div>";

    echo "<form method='GET'>";
    echo "<input type='hidden' name='password' value='" . htmlspecialchars($requiredPassword) . "'>";
    echo "<input type='hidden' name='execute' value='1'>";
    echo "<button type='submit' class='btn'>▶️ SPUSTIT OPRAVU</button>";
    echo "</form>";

    echo "</div></body></html>";
    exit;
}

// ===== EXECUTION MODE =====
echo "<div class='info'><strong>SPOUŠTÍM OPRAVY...</strong></div>";

require_once __DIR__ . '/init.php';

$results = [];
$totalSteps = 7;
$currentStep = 0;

/**
 * LogStep
 *
 * @param mixed $title Title
 * @param mixed $status Status
 * @param mixed $message Message
 */
function logStep($title, $status, $message) {
    global $results, $currentStep, $totalSteps;
    $currentStep++;

    $results[] = [
        'title' => $title,
        'status' => $status,
        'message' => $message
    ];

    $progress = round(($currentStep / $totalSteps) * 100);

    echo "<div class='step'>";
    echo "<h3>KROK {$currentStep}/{$totalSteps}: {$title}</h3>";

    if ($status === 'success') {
        echo "<div class='success'>✓ {$message}</div>";
    } elseif ($status === 'error') {
        echo "<div class='error'>✗ {$message}</div>";
    } elseif ($status === 'warning') {
        echo "<div class='warning'>⚠ {$message}</div>";
    } else {
        echo "<div class='info'>ℹ {$message}</div>";
    }

    echo "</div>";

    echo "<div class='progress'>";
    echo "<div class='progress-bar' style='width: {$progress}%'>{$progress}%</div>";
    echo "</div>";

    flush();
    ob_flush();
}

// ============================================
// KROK 1: Kontrola PHPMailer
// ============================================
$vendorAutoload = __DIR__ . '/vendor/autoload.php';
$phpmailerExists = file_exists($vendorAutoload);

if ($phpmailerExists) {
    require_once $vendorAutoload;
    $phpmailerClassExists = class_exists('PHPMailer\\PHPMailer\\PHPMailer');

    if ($phpmailerClassExists) {
        $version = \PHPMailer\PHPMailer\PHPMailer::VERSION;
        logStep('Kontrola PHPMailer', 'success', "PHPMailer je nainstalován (verze {$version})");
    } else {
        logStep('Kontrola PHPMailer', 'error', "vendor/autoload.php existuje, ale PHPMailer class chybí");
        $phpmailerExists = false;
    }
} else {
    logStep('Kontrola PHPMailer', 'warning', "PHPMailer NENÍ nainstalován - bude nainstalován");
    $phpmailerExists = false;
}

// ============================================
// KROK 2: Instalace PHPMailer (pokud chybí)
// ============================================
if (!$phpmailerExists) {
    // Zkusit Composer install
    $composerPath = trim(shell_exec('which composer 2>/dev/null'));
    if (empty($composerPath)) {
        $composerPath = 'composer';
    }

    $output = [];
    $returnCode = 0;

    chdir(__DIR__);
    exec("$composerPath require phpmailer/phpmailer --no-interaction 2>&1", $output, $returnCode);

    if ($returnCode === 0) {
        logStep('Instalace PHPMailer', 'success', "PHPMailer úspěšně nainstalován přes Composer");
        require_once $vendorAutoload;
    } else {
        logStep('Instalace PHPMailer', 'error', "Composer install selhal. Ruční instalace potřebná: https://www.wgs-service.cz/install_phpmailer_quick.php");
    }
} else {
    logStep('Instalace PHPMailer', 'info', "PHPMailer již je nainstalován, přeskakuji");
}

// ============================================
// KROK 3: Oprava SMTP konfigurace
// ============================================
try {
    $pdo = getDbConnection();

    // Získat aktuální konfiguraci
    $stmt = $pdo->query("
        SELECT * FROM wgs_smtp_settings
        WHERE is_active = 1
        ORDER BY id DESC
        LIMIT 1
    ");
    $currentConfig = $stmt->fetch(PDO::FETCH_ASSOC);

    $needsUpdate = false;

    if (!$currentConfig) {
        $needsUpdate = true;
        $action = 'Vytvoření nové konfigurace';
    } else {
        // Zkontrolovat, zda je konfigurace správná
        if ($currentConfig['smtp_host'] !== 'websmtp.cesky-hosting.cz' ||
            $currentConfig['smtp_port'] != 25 ||
            $currentConfig['smtp_encryption'] !== 'none' ||
            $currentConfig['smtp_username'] !== 'wgs-service.cz') {

            $needsUpdate = true;
            $action = 'Aktualizace na WebSMTP';
        }
    }

    if ($needsUpdate) {
        // Vytvořit nebo aktualizovat konfiguraci
        $stmt = $pdo->prepare("
            INSERT INTO wgs_smtp_settings (
                smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password,
                smtp_from_email, smtp_from_name, is_active
            ) VALUES (
                'websmtp.cesky-hosting.cz', 25, 'none', 'wgs-service.cz', :password,
                'reklamace@wgs-service.cz', 'White Glove Service', 1
            )
        ");

        $stmt->execute([
            'password' => $requiredPassword // Použít stejné heslo
        ]);

        // Deaktivovat staré konfigurace
        $pdo->exec("UPDATE wgs_smtp_settings SET is_active = 0 WHERE id != LAST_INSERT_ID()");

        logStep('Oprava SMTP konfigurace', 'success', "{$action} - nastaveno websmtp.cesky-hosting.cz:25");
    } else {
        logStep('Oprava SMTP konfigurace', 'info', "SMTP konfigurace je již správná");
    }

} catch (PDOException $e) {
    logStep('Oprava SMTP konfigurace', 'error', "Chyba: " . $e->getMessage());
}

// ============================================
// KROK 4: Sjednocení duplicitní konfigurace
// ============================================
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM wgs_system_config
        WHERE config_key IN ('smtp_host', 'smtp_port', 'smtp_username')
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $duplicateCount = $result['count'];

    if ($duplicateCount > 0) {
        $pdo->exec("
            DELETE FROM wgs_system_config
            WHERE config_key IN ('smtp_host', 'smtp_port', 'smtp_username', 'smtp_password', 'smtp_encryption')
        ");

        logStep('Sjednocení duplicitní konfigurace', 'success', "Odstraněno {$duplicateCount} duplicitních SMTP záznamů z wgs_system_config");
    } else {
        logStep('Sjednocení duplicitní konfigurace', 'info', "Žádné duplicity nenalezeny");
    }

} catch (PDOException $e) {
    logStep('Sjednocení duplicitní konfigurace', 'error', "Chyba: " . $e->getMessage());
}

// ============================================
// KROK 5: Reset email fronty
// ============================================
try {
    $stmt = $pdo->query("
        SELECT COUNT(*) as count
        FROM wgs_email_queue
        WHERE status = 'pending' AND attempts >= max_attempts
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $failedCount = $result['count'];

    if ($failedCount > 0) {
        $pdo->exec("
            UPDATE wgs_email_queue
            SET attempts = 0, status = 'pending', error_message = NULL
            WHERE status = 'pending' AND attempts >= max_attempts
        ");

        logStep('Reset email fronty', 'success', "Resetováno {$failedCount} selhavších emailů (attempts → 0)");
    } else {
        logStep('Reset email fronty', 'info', "Žádné selhavší emaily ve frontě");
    }

} catch (PDOException $e) {
    logStep('Reset email fronty', 'error', "Chyba: " . $e->getMessage());
}

// ============================================
// KROK 6: Test SMTP připojení
// ============================================
if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'websmtp.cesky-hosting.cz';
        $mail->Port = 25;
        $mail->SMTPAuth = true;
        $mail->Username = 'wgs-service.cz';
        $mail->Password = $requiredPassword;
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
        $mail->Timeout = 10;

        // Zkusit připojení
        if ($mail->smtpConnect()) {
            $mail->smtpClose();
            logStep('Test SMTP připojení', 'success', "Připojení k WebSMTP úspěšné!");
        } else {
            logStep('Test SMTP připojení', 'error', "Nelze se připojit k WebSMTP");
        }

    } catch (Exception $e) {
        logStep('Test SMTP připojení', 'error', "Chyba: " . $e->getMessage());
    }
} else {
    logStep('Test SMTP připojení', 'warning', "PHPMailer není dostupný, přeskakuji test");
}

// ============================================
// KROK 7: Testovací email
// ============================================
$testEmail = 'reklamace@wgs-service.cz'; // Poslat test sám sobě

if (class_exists('PHPMailer\\PHPMailer\\PHPMailer')) {
    try {
        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'websmtp.cesky-hosting.cz';
        $mail->Port = 25;
        $mail->SMTPAuth = true;
        $mail->Username = 'wgs-service.cz';
        $mail->Password = $requiredPassword;
        $mail->SMTPSecure = false;
        $mail->SMTPAutoTLS = false;
        $mail->CharSet = 'UTF-8';

        $mail->setFrom('reklamace@wgs-service.cz', 'WGS Email System');
        $mail->addAddress($testEmail);
        $mail->Subject = 'TEST: Email systém opraven - ' . date('Y-m-d H:i:s');
        $mail->Body = "Tento testovací email potvrzuje, že emailový systém WGS byl úspěšně opraven.\n\nČas: " . date('Y-m-d H:i:s') . "\nServer: " . gethostname();

        $mail->send();

        logStep('Testovací email', 'success', "Testovací email úspěšně odeslán na {$testEmail}");

    } catch (Exception $e) {
        logStep('Testovací email', 'error', "Chyba při odesílání: " . $e->getMessage());
    }
} else {
    logStep('Testovací email', 'warning', "PHPMailer není dostupný, přeskakuji test");
}

// ============================================
// FINÁLNÍ SHRNUTÍ
// ============================================
echo "<div class='step'>";
echo "<h2>📊 FINÁLNÍ SHRNUTÍ</h2>";

$successCount = count(array_filter($results, fn($r) => $r['status'] === 'success'));
$errorCount = count(array_filter($results, fn($r) => $r['status'] === 'error'));
$warningCount = count(array_filter($results, fn($r) => $r['status'] === 'warning'));

echo "<p><strong>Dokončeno kroků:</strong> {$totalSteps}</p>";
echo "<p><strong>Úspěšné:</strong> {$successCount}</p>";
echo "<p><strong>Chyby:</strong> {$errorCount}</p>";
echo "<p><strong>Varování:</strong> {$warningCount}</p>";

if ($errorCount === 0) {
    echo "<div class='success'>";
    echo "<h3>🎉 VŠECHNY OPRAVY BYLY ÚSPĚŠNÉ!</h3>";
    echo "<p>Emailový systém by měl nyní fungovat správně.</p>";
    echo "</div>";

    echo "<div class='info'>";
    echo "<h3>Další kroky:</h3>";
    echo "<ol>";
    echo "<li>Zkontrolujte email frontu: <a href='/diagnostika_email_queue.php'>diagnostika_email_queue.php</a></li>";
    echo "<li>Nastavte cron job pro zpracování fronty (každou minutu)</li>";
    echo "<li>Změňte dočasné heslo: <code>{$requiredPassword}</code></li>";
    echo "<li>SMAŽTE tento soubor po použití! <code>oprav_vse_najednou.php</code></li>";
    echo "</ol>";
    echo "</div>";

} else {
    echo "<div class='error'>";
    echo "<h3>⚠️ NĚKTERÉ OPRAVY SELHALY</h3>";
    echo "<p>Zkontrolujte chybové hlášky výše a proveďte ruční opravu.</p>";
    echo "</div>";
}

echo "</div>";

echo "<div class='warning'>";
echo "<h3>🔐 BEZPEČNOSTNÍ UPOZORNĚNÍ</h3>";
echo "<p><strong>DŮLEŽITÉ:</strong> Po dokončení oprav:</p>";
echo "<ul>";
echo "<li>ZMĚŇTE heslo: <code>{$requiredPassword}</code> ve všech službách</li>";
echo "<li>SMAŽTE tento soubor: <code>rm oprav_vse_najednou.php</code></li>";
echo "<li>SMAŽTE remote_audit_api.php pokud byl vytvořen</li>";
echo "</ul>";
echo "</div>";

echo "</div></body></html>";
