<?php
/**
 * Nastavení SPRÁVNÉ SMTP konfigurace podle cPanel dokumentace
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
    <title>Nastavení správné SMTP konfigurace</title>
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
        .info { background: #d1ecf1; color: #0c5460; padding: 15px;
                border-radius: 5px; margin: 15px 0; border-left: 4px solid #17a2b8; }
        .btn { padding: 12px 24px; background: #2D5016; color: white;
               border: none; border-radius: 5px; cursor: pointer; margin: 5px;
               font-size: 16px; }
        .btn:hover { background: #1a300d; }
        code { background: #f4f4f4; padding: 3px 8px; border-radius: 3px;
               font-family: monospace; color: #c7254e; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>🔧 Nastavení SPRÁVNÉ SMTP konfigurace</h1>";

echo "<div class='info'>";
echo "<strong>📖 Podle cPanel dokumentace Českého Hostingu:</strong><br>";
echo "Server pro odchozí poštu (SMTP): <code>smtp.cesky-hosting.cz</code><br>";
echo "Uživatel: <strong>celá e-mailová adresa</strong> (např. reklamace@wgs-service.cz)<br>";
echo "Heslo: heslo k dané schránce";
echo "</div>";

try {
    $pdo = getDbConnection();

    // Načíst aktuální konfiguraci
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1 LIMIT 1");
    $current = $stmt->fetch(PDO::FETCH_ASSOC);

    // SPRÁVNÁ konfigurace podle cPanel
    $correctConfig = [
        'smtp_host' => 'smtp.cesky-hosting.cz',
        'smtp_port' => 587,
        'smtp_username' => 'reklamace@wgs-service.cz',  // CELÁ adresa!
        'smtp_password' => 'p7u.s13mR2018',
        'smtp_encryption' => 'tls',
        'smtp_from_email' => 'reklamace@wgs-service.cz',
        'smtp_from_name' => 'White Glove Service'
    ];

    echo "<h2>📊 Porovnání konfigurace:</h2>";
    echo "<table>";
    echo "<tr><th>Parametr</th><th>Aktuální</th><th>Správně</th><th>Status</th></tr>";

    $needsUpdate = false;

    foreach ($correctConfig as $key => $correctValue) {
        $currentValue = $current[$key] ?? 'N/A';
        $match = ($currentValue == $correctValue);

        if (!$match && $key !== 'smtp_password') {
            $needsUpdate = true;
        }

        $displayKey = ucfirst(str_replace(['smtp_', '_'], ['', ' '], $key));
        $displayCurrent = ($key === 'smtp_password') ? '***' : $currentValue;
        $displayCorrect = ($key === 'smtp_password') ? '***' : $correctValue;
        $status = $match ? '✅' : '❌';

        // Zvýraznit důležité změny
        $rowStyle = '';
        if (!$match) {
            if ($key === 'smtp_username') {
                $rowStyle = " style='background: #fff3cd; font-weight: bold;'";
            } elseif ($key === 'smtp_host') {
                $rowStyle = " style='background: #fff3cd;'";
            }
        }

        echo "<tr{$rowStyle}>";
        echo "<td>{$displayKey}</td>";
        echo "<td><code>{$displayCurrent}</code></td>";
        echo "<td><code>{$displayCorrect}</code></td>";
        echo "<td>{$status}</td>";
        echo "</tr>";
    }

    echo "</table>";

    if (!$needsUpdate) {
        echo "<div class='success'>";
        echo "✅ <strong>Konfigurace je již SPRÁVNÁ!</strong><br><br>";
        echo "Všechny parametry odpovídají dokumentaci Českého Hostingu.";
        echo "</div>";

        echo "<a href='/smtp_test.php' class='btn'>Test SMTP</a>";

    } else {
        echo "<div class='warning'>";
        echo "⚠️ <strong>KRITICKÉ CHYBY NALEZENY:</strong><br><br>";
        echo "1. <strong>Username</strong> musí být CELÁ emailová adresa (<code>reklamace@wgs-service.cz</code>), ne doména!<br>";
        echo "2. <strong>Host</strong> by měl být <code>smtp.cesky-hosting.cz</code> podle cPanel dokumentace<br>";
        echo "</div>";

        if (isset($_POST['apply_fix'])) {
            // CSRF ochrana
            if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
                die("<div class='error'>❌ Neplatný CSRF token</div>");
            }

            $stmt = $pdo->prepare("
                UPDATE wgs_smtp_settings
                SET smtp_host = :host,
                    smtp_port = :port,
                    smtp_username = :username,
                    smtp_password = :password,
                    smtp_encryption = :encryption,
                    smtp_from_email = :from_email,
                    smtp_from_name = :from_name
                WHERE is_active = 1
            ");

            $result = $stmt->execute($correctConfig);

            if ($result) {
                echo "<div class='success'>";
                echo "✅ <strong>SMTP konfigurace ÚSPĚŠNĚ OPRAVENA!</strong><br><br>";
                echo "<strong>Nové nastavení:</strong><br>";
                echo "• Host: <code>{$correctConfig['smtp_host']}:{$correctConfig['smtp_port']}</code><br>";
                echo "• Username: <code>{$correctConfig['smtp_username']}</code> ← CELÁ adresa!<br>";
                echo "• Encryption: <code>{$correctConfig['smtp_encryption']}</code> (STARTTLS)<br>";
                echo "• FROM: <code>{$correctConfig['smtp_from_email']}</code><br><br>";
                echo "<strong>🎯 Toto je konfigurace podle oficiální cPanel dokumentace!</strong>";
                echo "</div>";

                echo "<a href='/smtp_test.php' class='btn'>🧪 Otestovat SMTP</a>";
                echo "<a href='/protokol.php' class='btn' style='background: #28a745;'>✉️ Zkusit odeslat email</a>";

            } else {
                echo "<div class='error'>❌ Chyba při ukládání do databáze</div>";
            }

        } else {
            echo "<form method='POST'>";
            $csrfToken = generateCSRFToken();
            echo "<input type='hidden' name='csrf_token' value='{$csrfToken}'>";
            echo "<button type='submit' name='apply_fix' class='btn' style='font-size: 18px; padding: 15px 30px;'>";
            echo "✓ APLIKOVAT SPRÁVNOU KONFIGURACI";
            echo "</button>";
            echo "</form>";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ Chyba: " . htmlspecialchars($e->getMessage()) . "</div>";
}

echo "</div></body></html>";
?>
