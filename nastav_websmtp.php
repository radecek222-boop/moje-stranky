<?php
/**
 * Automatická konfigurace WebSMTP pro Český hosting
 */

require_once __DIR__ . '/init.php';

// Bezpečnost - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN");
}

$success = false;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup'])) {
    try {
        $websmtpPassword = $_POST['websmtp_password'] ?? '';

        if (empty($websmtpPassword)) {
            throw new Exception('WebSMTP heslo je povinné');
        }

        $pdo = getDbConnection();

        // Deaktivovat všechny konfigurace
        $pdo->exec("UPDATE wgs_smtp_settings SET is_active = 0");

        // Najít a aktualizovat nebo vytvořit novou
        $stmt = $pdo->prepare("
            SELECT id FROM wgs_smtp_settings
            WHERE smtp_host = 'websmtp.cesky-hosting.cz'
            LIMIT 1
        ");
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($existing) {
            // Aktualizovat existující
            $stmt = $pdo->prepare("
                UPDATE wgs_smtp_settings
                SET smtp_port = 25,
                    smtp_username = 'wgs-service.cz',
                    smtp_password = :password,
                    smtp_encryption = NULL,
                    smtp_from_email = 'reklamace@wgs-service.cz',
                    smtp_from_name = 'White Glove Service',
                    is_active = 1,
                    updated_at = NOW()
                WHERE id = :id
            ");
            $stmt->execute([
                ':password' => $websmtpPassword,
                ':id' => $existing['id']
            ]);
        } else {
            // Vytvořit novou
            $stmt = $pdo->prepare("
                INSERT INTO wgs_smtp_settings (
                    smtp_host, smtp_port, smtp_username, smtp_password,
                    smtp_encryption, smtp_from_email, smtp_from_name, is_active,
                    created_at, updated_at
                ) VALUES (
                    'websmtp.cesky-hosting.cz', 25, 'wgs-service.cz', :password,
                    NULL, 'reklamace@wgs-service.cz', 'White Glove Service', 1,
                    NOW(), NOW()
                )
            ");
            $stmt->execute([':password' => $websmtpPassword]);
        }

        $success = true;

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>WebSMTP Setup</title>
    <style>
        body { font-family: Arial; max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 15px 0; }
        .form-group { margin: 20px 0; }
        label { display: block; font-weight: 600; margin-bottom: 8px; }
        input[type="password"] { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px; font-size: 16px; box-sizing: border-box; }
        .btn { display: inline-block; padding: 12px 24px; background: #2D5016; color: white; text-decoration: none; border-radius: 5px; border: none; font-size: 16px; cursor: pointer; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th { background: #2D5016; color: white; padding: 10px; text-align: left; }
        table td { padding: 10px; border-bottom: 1px solid #ddd; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; }
    </style>
</head>
<body>
<div class='container'>

<h1>⚙️ WebSMTP Setup - Český hosting</h1>

<?php if ($success): ?>
    <div class='success'>
        <strong>✅ WebSMTP ÚSPĚŠNĚ NAKONFIGUROVÁNO!</strong><br><br>

        <table>
            <tr><th>Parametr</th><th>Hodnota</th></tr>
            <tr><td>SMTP Host</td><td>websmtp.cesky-hosting.cz</td></tr>
            <tr><td>Port</td><td>25</td></tr>
            <tr><td>Šifrování</td><td>Žádné (lokální síť)</td></tr>
            <tr><td>Username</td><td>wgs-service.cz</td></tr>
            <tr><td>Password</td><td>••••••••</td></tr>
            <tr><td>Status</td><td>✅ Aktivní</td></tr>
        </table>

        <h2>🎯 Další kroky</h2>
        <a href='/scripts/test-smtp.php' class='btn'>📨 Otestovat SMTP</a>
        <a href='/protokol.php?id=28' class='btn'>📄 Odeslat protokol</a>
    </div>
<?php elseif ($error): ?>
    <div class='error'><strong>❌ CHYBA:</strong> <?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class='info'>
    <strong>📧 WebSMTP detekováno v Českém hostingu</strong><br><br>

    <table>
        <tr><td>Jméno serveru</td><td><code>websmtp.cesky-hosting.cz</code></td></tr>
        <tr><td>Uživatelské jméno</td><td><code>wgs-service.cz</code></td></tr>
        <tr><td>Port</td><td><code>25</code></td></tr>
    </table>
</div>

<h2>🔐 Zadejte WebSMTP heslo</h2>

<div class='info'>
    <strong>⚠️ Kde najít/změnit WebSMTP heslo?</strong><br><br>

    1. Jděte na <a href='https://www.cesky-hosting.cz' target='_blank'>Český hosting panel</a><br>
    2. <strong>Správa domény → Webserver</strong><br>
    3. Sekce <strong>"WebSMTP"</strong><br>
    4. Klikněte <strong>"Změnit heslo"</strong><br>
    5. Nastavte nové heslo<br>
    6. Zadejte ho níže
</div>

<form method='POST'>
    <div class='form-group'>
        <label for='websmtp_password'>WebSMTP heslo *</label>
        <input type='password'
               id='websmtp_password'
               name='websmtp_password'
               placeholder='Zadejte WebSMTP heslo...'
               required>
    </div>

    <button type='submit' name='setup' class='btn'>
        ⚡ Nastavit WebSMTP
    </button>

    <a href='/diagnoza_smtp.php' class='btn' style='background: #6c757d;'>
        🔍 Diagnostika
    </a>
</form>

<h2>📋 Co je WebSMTP?</h2>

<p>
<strong>WebSMTP</strong> je sdílený odesílací SMTP server pro odesílání e-mailů z PHP aplikací na Českém hostingu.
Je to preferované řešení, protože:
</p>

<ul>
    <li>✅ Není blokován firewallem</li>
    <li>✅ Nevyžaduje šifrování (běží v lokální síti)</li>
    <li>✅ Je optimalizován pro webhosting</li>
    <li>✅ Má lepší deliverability než běžné SMTP</li>
</ul>

</div>
</body>
</html>
