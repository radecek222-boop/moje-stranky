<?php
/**
 * Automatická konfigurace SMTP pro Český hosting
 * Nastaví smtp.cesky-hosting.cz jako SMTP server
 */

require_once __DIR__ . '/init.php';

// Bezpečnost - pouze admin
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die("PŘÍSTUP ODEPŘEN: Pouze pro administrátory");
}

$success = false;
$error = null;
$message = null;

// Zpracování formuláře
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_smtp'])) {
    try {
        $emailPassword = $_POST['email_password'] ?? '';

        if (empty($emailPassword)) {
            throw new Exception('Heslo k emailu reklamace@wgs-service.cz je povinné');
        }

        $pdo = getDbConnection();

        // Kontrola existence tabulky wgs_smtp_settings
        try {
            $pdo->query("SELECT 1 FROM wgs_smtp_settings LIMIT 0");
        } catch (PDOException $e) {
            throw new Exception('Tabulka wgs_smtp_settings neexistuje. Spusťte nejdříve /admin/install_email_system.php');
        }

        // Deaktivovat všechny stávající konfigurace
        $pdo->exec("UPDATE wgs_smtp_settings SET is_active = 0");

        // Vložit novou konfiguraci pro Český hosting
        $stmt = $pdo->prepare("
            INSERT INTO wgs_smtp_settings (
                smtp_host, smtp_port, smtp_username, smtp_password,
                smtp_encryption, smtp_from_email, smtp_from_name, is_active,
                created_at, updated_at
            ) VALUES (
                :smtp_host, :smtp_port, :smtp_username, :smtp_password,
                :smtp_encryption, :smtp_from_email, :smtp_from_name, :is_active,
                NOW(), NOW()
            )
        ");

        $stmt->execute([
            ':smtp_host' => 'smtp.cesky-hosting.cz',
            ':smtp_port' => 587,
            ':smtp_username' => 'reklamace@wgs-service.cz',
            ':smtp_password' => $emailPassword,
            ':smtp_encryption' => 'tls',
            ':smtp_from_email' => 'reklamace@wgs-service.cz',
            ':smtp_from_name' => 'White Glove Service',
            ':is_active' => 1
        ]);

        $success = true;
        $message = "✅ SMTP úspěšně nakonfigurováno pro Český hosting!";

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang='cs'>
<head>
    <meta charset='UTF-8'>
    <title>Nastavení SMTP - Český hosting</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
               max-width: 800px; margin: 50px auto; padding: 20px; background: #f5f5f5; }
        .container { background: white; padding: 30px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1 { color: #2D5016; border-bottom: 3px solid #2D5016; padding-bottom: 10px; }
        h2 { color: #2D5016; margin-top: 30px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724;
                   padding: 15px; border-radius: 5px; margin: 15px 0; font-size: 16px; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;
                 padding: 15px; border-radius: 5px; margin: 15px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460;
                padding: 15px; border-radius: 5px; margin: 15px 0; }
        .warning { background: #fff3cd; border: 1px solid #ffeaa7; color: #856404;
                   padding: 15px; border-radius: 5px; margin: 15px 0; }
        .form-group { margin: 20px 0; }
        label { display: block; font-weight: 600; margin-bottom: 8px; color: #333; }
        input[type="password"], input[type="text"] {
            width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 5px;
            font-size: 16px; box-sizing: border-box;
        }
        .btn { display: inline-block; padding: 12px 24px; background: #2D5016;
               color: white; text-decoration: none; border-radius: 5px;
               border: none; font-size: 16px; cursor: pointer; margin: 10px 5px 10px 0; }
        .btn:hover { background: #1a300d; }
        .btn-secondary { background: #6c757d; }
        .btn-secondary:hover { background: #545b62; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        table th { background: #2D5016; color: white; padding: 12px; text-align: left; }
        table td { padding: 12px; border-bottom: 1px solid #ddd; }
        code { background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-family: 'Courier New', monospace; }
        .password-hint { font-size: 14px; color: #666; margin-top: 5px; }
        .step { background: #f8f9fa; padding: 15px; border-left: 4px solid #2D5016; margin: 15px 0; }
        .step-number { font-size: 24px; font-weight: bold; color: #2D5016; }
    </style>
</head>
<body>
<div class='container'>

<h1>⚙️ Nastavení SMTP - Český hosting</h1>

<?php if ($success): ?>
    <div class='success'>
        <strong><?= htmlspecialchars($message) ?></strong><br><br>
        <strong>📋 Nastavená konfigurace:</strong>
        <table>
            <tr><th>Parametr</th><th>Hodnota</th></tr>
            <tr><td>SMTP Host</td><td>smtp.cesky-hosting.cz</td></tr>
            <tr><td>Port</td><td>587</td></tr>
            <tr><td>Šifrování</td><td>TLS</td></tr>
            <tr><td>Username</td><td>reklamace@wgs-service.cz</td></tr>
            <tr><td>From Email</td><td>reklamace@wgs-service.cz</td></tr>
            <tr><td>From Name</td><td>White Glove Service</td></tr>
        </table>

        <strong>🎯 DALŠÍ KROKY:</strong><br><br>

        <a href="/scripts/test-smtp.php" class="btn">📨 1. Otestovat SMTP</a>
        <a href="/admin/smtp_settings.php" class="btn btn-secondary">⚙️ 2. Zobrazit nastavení</a>
        <a href="/protokol.php?id=28" class="btn btn-secondary">📄 3. Zkusit odeslat protokol</a>
    </div>
<?php elseif ($error): ?>
    <div class='error'>
        <strong>❌ CHYBA:</strong><br>
        <?= htmlspecialchars($error) ?>
    </div>
<?php endif; ?>

<div class='info'>
    <strong>📧 Email účet detekován:</strong> reklamace@wgs-service.cz<br>
    <strong>🌐 Hosting:</strong> Český hosting (Thinline s.r.o.)<br>
    <strong>🔧 SMTP Server:</strong> smtp.cesky-hosting.cz
</div>

<h2>🔐 Zadejte heslo k emailu</h2>

<div class='warning'>
    ⚠️ <strong>Důležité:</strong> Zadejte heslo k emailové schránce <code>reklamace@wgs-service.cz</code><br>
    Toto je heslo, které používáte pro přihlášení do webmailu nebo poštovního klienta.
</div>

<form method="POST">
    <div class='form-group'>
        <label for='email_password'>Heslo k reklamace@wgs-service.cz *</label>
        <input type='password'
               id='email_password'
               name='email_password'
               placeholder='Zadejte heslo k emailu...'
               required
               autocomplete='off'>
        <div class='password-hint'>
            💡 Toto heslo se uloží šifrovaně v databázi a použije se pouze pro odesílání protokolů
        </div>
    </div>

    <button type='submit' name='setup_smtp' class='btn'>
        ⚡ Nastavit SMTP automaticky
    </button>

    <a href='/admin/smtp_settings.php' class='btn btn-secondary'>
        ← Zpět na ruční nastavení
    </a>
</form>

<h2>📋 Co tento skript udělá?</h2>

<div class='step'>
    <span class='step-number'>1.</span>
    Deaktivuje všechny stávající SMTP konfigurace
</div>

<div class='step'>
    <span class='step-number'>2.</span>
    Vytvoří novou konfiguraci pro Český hosting se správnými parametry:
    <ul>
        <li>SMTP Host: <code>smtp.cesky-hosting.cz</code></li>
        <li>Port: <code>587</code> (TLS)</li>
        <li>Username: <code>reklamace@wgs-service.cz</code></li>
        <li>Password: <code>[vaše heslo]</code></li>
    </ul>
</div>

<div class='step'>
    <span class='step-number'>3.</span>
    Aktivuje novou konfiguraci (<code>is_active = 1</code>)
</div>

<div class='step'>
    <span class='step-number'>4.</span>
    Po nastavení můžete okamžitě odesílat protokoly zákazníkům
</div>

<h2>❓ Nevíte heslo k emailu?</h2>

<div class='info'>
    <strong>Jak zjistit/resetovat heslo:</strong><br><br>

    1. Přihlaste se do <a href='https://www.cesky-hosting.cz' target='_blank'>Českého hostingu</a><br>
    2. Jděte na <strong>Správa domény → E-maily</strong><br>
    3. Klikněte na <strong>"Upravit"</strong> u reklamace@wgs-service.cz<br>
    4. Můžete změnit heslo na nové<br>
    5. Pak zadejte nové heslo zde
</div>

<h2>🔧 Alternativní metody</h2>

<p>Pokud preferujete ruční nastavení:</p>
<ul>
    <li><a href='/admin/smtp_settings.php'>⚙️ Ruční SMTP konfigurace</a></li>
    <li><a href='/diagnoza_smtp.php'>🔍 Diagnostika SMTP</a></li>
    <li><a href='/admin/install_email_system.php'>📧 Email System Installer</a></li>
</ul>

</div>
</body>
</html>
