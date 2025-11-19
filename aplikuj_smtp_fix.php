<?php
/**
 * Aplikace úspěšné SMTP konfigurace z test_smtp_varianty.php
 */
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// Bezpečnostní kontrola
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

header('Content-Type: text/html; charset=utf-8');

echo "<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Aplikace SMTP Fix</title>
    <style>
        body { font-family: 'Segoe UI', sans-serif; max-width: 800px;
               margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px;
                     box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; }
        .section { background: #f8f9fa; padding: 15px; border-radius: 5px;
                   margin: 15px 0; border-left: 4px solid #2D5016; }
        .success { background: #d4edda; border-left-color: #28a745; color: #155724; }
        .error { background: #f8d7da; border-left-color: #dc3545; color: #721c24; }
        .warning { background: #fff3cd; border-left-color: #ffc107; color: #856404; }
        table { width: 100%; border-collapse: collapse; margin: 10px 0; }
        th, td { padding: 10px; text-align: left; border-bottom: 1px solid #dee2e6; }
        th { background: #2D5016; color: white; }
        code { background: #f4f4f4; padding: 2px 6px; border-radius: 3px; }
        .btn { padding: 10px 20px; background: #2D5016; color: white;
               border: none; border-radius: 5px; cursor: pointer; margin: 5px;
               text-decoration: none; display: inline-block; }
        .btn:hover { background: #1a300d; }
        .btn-danger { background: #dc3545; }
    </style>
</head>
<body>
<div class='container'>";

echo "<h1>Aplikace SMTP Fix</h1>";

// Načíst úspěšnou variantu ze session
if (!isset($_SESSION['successful_smtp_variant'])) {
    echo "<div class='section error'>";
    echo "❌ Žádná úspěšná konfigurace nebyla nalezena.<br><br>";
    echo "Prosím, nejdříve spusťte: <a href='/test_smtp_varianty.php'>test_smtp_varianty.php</a>";
    echo "</div>";
    echo "</div></body></html>";
    exit;
}

$variant = $_SESSION['successful_smtp_variant'];

// Zobrazit konfiguraci
echo "<div class='section'>";
echo "<h3>Nalezená fungující konfigurace:</h3>";
echo "<table>";
echo "<tr><th>Parametr</th><th>Hodnota</th></tr>";
echo "<tr><td>SMTP Host</td><td><code>{$variant['host']}</code></td></tr>";
echo "<tr><td>SMTP Port</td><td><code>{$variant['port']}</code></td></tr>";
echo "<tr><td>Autentizace</td><td><code>" . ($variant['auth'] ? 'ANO' : 'NE') . "</code></td></tr>";
if ($variant['auth']) {
    echo "<tr><td>Username</td><td><code>{$variant['username']}</code></td></tr>";
    echo "<tr><td>Password</td><td><code>" . (empty($variant['password']) ? '(prázdné)' : '***') . "</code></td></tr>";
}
echo "<tr><td><strong>FROM Email</strong></td><td><code><strong>{$variant['from']}</strong></code></td></tr>";
echo "<tr><td>Encryption</td><td><code>{$variant['encryption']}</code></td></tr>";
echo "</table>";
echo "</div>";

// Aplikovat změny při potvrzení
if (isset($_POST['apply_fix'])) {
    // CSRF ochrana
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        die("<div class='section error'>❌ Neplatný CSRF token. Obnovte stránku a zkuste znovu.</div>");
    }

    try {
        $pdo = getDbConnection();

        // Mapování encryption
        $encryptionMap = [
            'none' => 'none',
            'tls' => 'tls',
            'ssl' => 'ssl'
        ];

        $encryption = $encryptionMap[$variant['encryption']] ?? 'none';

        // Update SMTP nastavení
        $stmt = $pdo->prepare("
            UPDATE wgs_smtp_settings
            SET smtp_host = :host,
                smtp_port = :port,
                smtp_username = :username,
                smtp_password = :password,
                smtp_encryption = :encryption,
                smtp_from_email = :from_email
            WHERE is_active = 1
        ");

        $stmt->execute([
            ':host' => $variant['host'],
            ':port' => $variant['port'],
            ':username' => $variant['auth'] ? $variant['username'] : '',
            ':password' => $variant['auth'] ? $variant['password'] : '',
            ':encryption' => $encryption,
            ':from_email' => $variant['from']
        ]);

        echo "<div class='section success'>";
        echo "✅ <strong>SMTP konfigurace úspěšně změněna!</strong><br><br>";
        echo "Nové nastavení:<br>";
        echo "• Host: <code>{$variant['host']}:{$variant['port']}</code><br>";
        echo "• FROM: <code>{$variant['from']}</code><br>";
        echo "• Encryption: <code>{$encryption}</code><br><br>";
        echo "<a href='/smtp_test.php' class='btn'>Otestovat znovu</a>";
        echo "<a href='/protokol.php' class='btn'>Zpět na protokol</a>";
        echo "</div>";

        // Vyčistit session
        unset($_SESSION['successful_smtp_variant']);

    } catch (Exception $e) {
        echo "<div class='section error'>";
        echo "❌ Chyba při ukládání: " . htmlspecialchars($e->getMessage());
        echo "</div>";
    }

} else {
    // Zobrazit potvrzovací formulář
    echo "<div class='section warning'>";
    echo "<h3>⚠️ Potvrzení změny</h3>";
    echo "Opravdu chcete aplikovat tuto konfiguraci?<br>";
    echo "Tím se přepíše aktuální SMTP nastavení v databázi.";
    echo "</div>";

    echo "<form method='POST'>";
    $csrfToken = generateCSRFToken();
    echo "<input type='hidden' name='csrf_token' value='{$csrfToken}'>";
    echo "<button type='submit' name='apply_fix' class='btn'>✓ Ano, aplikovat změny</button>";
    echo "<a href='/test_smtp_varianty.php' class='btn btn-danger'>✗ Zrušit</a>";
    echo "</form>";
}

echo "</div></body></html>";
?>
