<?php
/**
 * Email System Installer - Web Interface
 * Instaluje email queue systém jedním kliknutím
 */

require_once __DIR__ . '/../init.php';

// Security: Admin only
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    header('Location: /login.php');
    exit;
}

$status = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install'])) {
    try {
        $pdo = getDbConnection();

        // KROK 1: Stáhnout PHPMailer pokud neexistuje
        $vendorDir = __DIR__ . '/../vendor';
        $phpmailerDir = $vendorDir . '/phpmailer';

        if (!file_exists($phpmailerDir)) {
            if (!file_exists($vendorDir)) {
                mkdir($vendorDir, 0755, true);
            }

            // Použít ZIP místo tar.gz kvůli open_basedir restrikci
            $phpmailerUrl = 'https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip';
            $zipFile = $vendorDir . '/phpmailer.zip';

            // Stáhnout
            $ch = curl_init($phpmailerUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            $data = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if (!$data || $httpCode !== 200) {
                throw new Exception("Nepodařilo se stáhnout PHPMailer (HTTP $httpCode)");
            }

            file_put_contents($zipFile, $data);

            // Rozbalit pomocí ZipArchive (bez open_basedir problémů)
            $zip = new ZipArchive;
            if ($zip->open($zipFile) === TRUE) {
                $zip->extractTo($vendorDir);
                $zip->close();
                unlink($zipFile);
            } else {
                unlink($zipFile);
                throw new Exception("Nepodařilo se rozbalit PHPMailer");
            }

            // Přejmenovat
            if (file_exists($vendorDir . '/PHPMailer-6.9.1')) {
                rename($vendorDir . '/PHPMailer-6.9.1', $phpmailerDir);
            }

            // Vytvořit autoload.php
            $autoloadContent = <<<'PHP'
<?php
/**
 * Simple autoloader for PHPMailer
 * (No Composer required)
 */

spl_autoload_register(function ($class) {
    // PHPMailer namespace
    $prefix = 'PHPMailer\\PHPMailer\\';
    $base_dir = __DIR__ . '/phpmailer/src/';

    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }

    $relative_class = substr($class, $len);
    $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';

    if (file_exists($file)) {
        require $file;
    }
});
PHP;
            file_put_contents($vendorDir . '/autoload.php', $autoloadContent);
        }

        // KROK 2: Vytvořit databázové tabulky
        $sqlFile = __DIR__ . '/../migrations/create_email_queue.sql';

        if (!file_exists($sqlFile)) {
            throw new Exception("Migration file not found");
        }

        $sql = file_get_contents($sqlFile);

        // Odstranit komentáře
        $sql = preg_replace('/--[^\n]*\n/', "\n", $sql);

        // Rozdělit podle středníků, ale respektovat stringy
        $statements = [];
        $currentStatement = '';
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < strlen($sql); $i++) {
            $char = $sql[$i];

            if (!$inString && ($char === '"' || $char === "'")) {
                $inString = true;
                $stringChar = $char;
            } elseif ($inString && $char === $stringChar && $sql[$i-1] !== '\\') {
                $inString = false;
            }

            if (!$inString && $char === ';') {
                $stmt = trim($currentStatement);
                if (!empty($stmt) && !preg_match('/^SELECT/', $stmt)) {
                    $statements[] = $stmt;
                }
                $currentStatement = '';
            } else {
                $currentStatement .= $char;
            }
        }

        // Execute each statement
        foreach ($statements as $statement) {
            try {
                $pdo->exec($statement);
            } catch (PDOException $e) {
                // Pokračovat i když selhání (např. tabulka už existuje)
                error_log("SQL Warning: " . $e->getMessage());
            }
        }

        // Počkat na dokončení všech SQL operací
        usleep(100000); // 100ms

        // Ověřit, že tabulky byly vytvořeny
        $tablesCreated = true;
        try {
            $pdo->query("SELECT 1 FROM wgs_email_queue LIMIT 0");
            $pdo->query("SELECT 1 FROM wgs_smtp_settings LIMIT 0");
        } catch (PDOException $e) {
            $tablesCreated = false;
            throw new Exception("Tabulky nebyly vytvořeny: " . $e->getMessage());
        }

        // KROK 3: Automaticky nastavit SMTP z existující konfigurace
        $smtpHost = '';
        $smtpPort = 587;
        $smtpUsername = '';
        $smtpPassword = '';
        $smtpFrom = 'reklamace@wgs-service.cz';
        $smtpFromName = 'White Glove Service';

        // Zkusit načíst z wgs_system_config
        try {
            $configStmt = $pdo->query("SELECT config_key, config_value FROM wgs_system_config WHERE config_group = 'email'");
            if ($configStmt) {
                $config = $configStmt->fetchAll(PDO::FETCH_KEY_PAIR);
                $smtpHost = $config['smtp_host'] ?? $smtpHost;
                $smtpPort = $config['smtp_port'] ?? $smtpPort;
                $smtpUsername = $config['smtp_username'] ?? $smtpUsername;
                $smtpPassword = $config['smtp_password'] ?? $smtpPassword;
                $smtpFrom = $config['smtp_from'] ?? $smtpFrom;
                $smtpFromName = $config['smtp_from_name'] ?? $smtpFromName;
            }
        } catch (Exception $e) {
            // Tabulka neexistuje, použít env
        }

        // Fallback na environment variables
        if (empty($smtpHost)) {
            $smtpHost = getenv('SMTP_HOST') ?: 'smtp.example.com';
        }
        if (empty($smtpUsername)) {
            $smtpUsername = getenv('SMTP_USER') ?: '';
        }
        if (empty($smtpPassword)) {
            $smtpPassword = getenv('SMTP_PASS') ?: '';
        }
        if (empty($smtpFrom)) {
            $smtpFrom = getenv('SMTP_FROM') ?: 'reklamace@wgs-service.cz';
        }

        // Určit šifrování podle portu
        $encryption = 'tls';
        if ($smtpPort == 465) {
            $encryption = 'ssl';
        } elseif ($smtpPort == 25) {
            $encryption = 'none';
        }

        // Použít INSERT ... ON DUPLICATE KEY UPDATE
        // Novější syntaxe bez VALUES() pro kompatibilitu s MySQL 8.0.20+
        $smtpStmt = $pdo->prepare("
            INSERT INTO wgs_smtp_settings (
                id, smtp_host, smtp_port, smtp_encryption,
                smtp_username, smtp_password,
                smtp_from_email, smtp_from_name, is_active
            ) VALUES (1, :host, :port, :encryption, :username, :password, :from_email, :from_name, 1)
            ON DUPLICATE KEY UPDATE
                smtp_host = :host,
                smtp_port = :port,
                smtp_encryption = :encryption,
                smtp_username = :username,
                smtp_password = :password,
                smtp_from_email = :from_email,
                smtp_from_name = :from_name,
                is_active = 1
        ");
        $smtpStmt->execute([
            ':host' => $smtpHost,
            ':port' => $smtpPort,
            ':encryption' => $encryption,
            ':username' => $smtpUsername,
            ':password' => $smtpPassword,
            ':from_email' => $smtpFrom,
            ':from_name' => $smtpFromName
        ]);

        $status = 'success';

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check if already installed
$pdo = getDbConnection();
$tablesExist = false;
$phpmailerInstalled = false;

try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_email_queue'");
    $tablesExist = $stmt->rowCount() > 0;
} catch (Exception $e) {
    // Ignore
}

// Check PHPMailer
$phpmailerInstalled = file_exists(__DIR__ . '/../vendor/phpmailer/src/PHPMailer.php');

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email System Installer | WGS Admin</title>
    <link rel="stylesheet" href="/assets/css/styles.min.css">
    <style>
        body {
            background: #f5f5f5;
            font-family: Arial, sans-serif;
            padding: 2rem;
        }
        .installer {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .installer h1 {
            margin-top: 0;
            color: #333;
        }
        .status {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .status.success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .status.error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .status.info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-primary:hover {
            background: #0056b3;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
            text-decoration: none;
            display: inline-block;
        }
        .feature-list {
            list-style: none;
            padding: 0;
        }
        .feature-list li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
        }
        .feature-list li:before {
            content: "✓";
            color: #28a745;
            font-weight: bold;
            margin-right: 0.5rem;
            margin-left: -1.5rem;
        }
    </style>
</head>
<body>
    <div class="installer">
        <h1>📧 Email System Installer</h1>

        <?php if ($status === 'success'): ?>
            <div class="status success">
                <strong>✅ Instalace dokončena!</strong><br>
                Email queue systém byl úspěšně nainstalován.
            </div>
            <a href="/admin/email_queue.php" class="btn btn-primary">Přejít na správu email fronty</a>
            <a href="/admin/smtp_settings.php" class="btn btn-secondary">Nastavit SMTP</a>

        <?php elseif ($error): ?>
            <div class="status error">
                <strong>❌ Chyba při instalaci:</strong><br>
                <?php echo htmlspecialchars($error); ?>
            </div>

        <?php elseif ($tablesExist): ?>
            <div class="status info">
                <strong>ℹ️ Systém je již nainstalován</strong><br>
                Email queue tabulky již existují v databázi.
            </div>
            <a href="/admin/email_queue.php" class="btn btn-primary">Přejít na správu email fronty</a>
            <a href="/admin/smtp_settings.php" class="btn btn-secondary">Nastavit SMTP</a>

        <?php else: ?>
            <p>Tento instalátor vytvoří potřebné tabulky pro email queue systém.</p>

            <h3>Co bude nainstalováno:</h3>
            <ul class="feature-list">
                <li><strong>Email Queue</strong> - Fronta pro asynchronní odesílání emailů</li>
                <li><strong>SMTP Settings</strong> - Konfigurace SMTP serveru</li>
                <li><strong>PHPMailer</strong> - Spolehlivé odesílání emailů</li>
                <li><strong>Cron Worker</strong> - Automatické zpracování fronty</li>
            </ul>

            <h3>Výhody:</h3>
            <ul class="feature-list">
                <li>Rychlost: Ukládání termínu 3s místo 15s</li>
                <li>Spolehlivost: Automatické opakování při selhání</li>
                <li>Přehled: Sledování stavu všech emailů</li>
                <li>Flexibilita: Snadná konfigurace SMTP</li>
            </ul>

            <form method="POST">
                <button type="submit" name="install" class="btn btn-primary">
                    🚀 Nainstalovat Email Queue
                </button>
            </form>
        <?php endif; ?>

        <br><br>
        <a href="/admin.php" class="btn btn-secondary">← Zpět do admin panelu</a>
    </div>
</body>
</html>
