<?php
/**
 * SMTP Settings - Admin Interface
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/EmailQueue.php';

// Security: Admin only
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    header('Location: /login.php');
    exit;
}

$pdo = getDbConnection();
$message = null;
$error = null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['save'])) {
            // Save SMTP settings
            $stmt = $pdo->prepare("
                INSERT INTO wgs_smtp_settings (
                    smtp_host, smtp_port, smtp_encryption,
                    smtp_username, smtp_password,
                    smtp_from_email, smtp_from_name, is_active
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 1)
                ON DUPLICATE KEY UPDATE
                    smtp_host = VALUES(smtp_host),
                    smtp_port = VALUES(smtp_port),
                    smtp_encryption = VALUES(smtp_encryption),
                    smtp_username = VALUES(smtp_username),
                    smtp_password = VALUES(smtp_password),
                    smtp_from_email = VALUES(smtp_from_email),
                    smtp_from_name = VALUES(smtp_from_name)
            ");

            $stmt->execute([
                $_POST['smtp_host'],
                $_POST['smtp_port'],
                $_POST['smtp_encryption'],
                $_POST['smtp_username'],
                $_POST['smtp_password'],
                $_POST['smtp_from_email'],
                $_POST['smtp_from_name']
            ]);

            $message = 'SMTP nastavení bylo úspěšně uloženo';

        } elseif (isset($_POST['test'])) {
            // Test SMTP connection
            $queue = new EmailQueue();
            $testEmail = $_POST['test_email'] ?? '';

            if (!filter_var($testEmail, FILTER_VALIDATE_EMAIL)) {
                throw new Exception('Neplatná emailová adresa');
            }

            // Enqueue test email
            $queue->enqueue([
                'notification_id' => 'test',
                'to' => $testEmail,
                'to_name' => 'Test',
                'subject' => 'WGS Test Email - ' . date('Y-m-d H:i:s'),
                'body' => "Toto je testovací email z WGS Email Queue systému.\n\nPokud jste tento email obdrželi, znamená to, že SMTP konfigurace funguje správně.",
                'priority' => 'high'
            ]);

            $message = "Testovací email byl přidán do fronty. Zkontrolujte doručenou poštu na: $testEmail";
        }

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Load current settings
$settings = null;
try {
    $stmt = $pdo->query("SELECT * FROM wgs_smtp_settings WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    $settings = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Table doesn't exist yet
}

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SMTP Nastavení | WGS Admin</title>
    <link rel="stylesheet" href="/assets/css/styles.min.css">
    <link rel="stylesheet" href="/assets/css/admin-header.min.css">
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Poppins', Arial, sans-serif;
        }
        .container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #333;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1rem;
        }
        .form-group small {
            display: block;
            margin-top: 0.25rem;
            color: #666;
            font-size: 0.85rem;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            margin-right: 0.5rem;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-success {
            background: #28a745;
            color: white;
        }
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        .alert-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        .alert-danger {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        .test-section {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 4px;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/hamburger-menu.php'; ?>

    <div class="container">
        <h1>⚙️ SMTP Nastavení</h1>

        <?php if ($message): ?>
            <div class="alert alert-success" role="status" aria-live="polite"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert" aria-live="assertive"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if (!$settings): ?>
            <div class="alert alert-danger" role="alert">
                <strong>Email queue systém není nainstalován!</strong><br>
                <a href="/admin/install_email_system.php" style="color: #721c24; text-decoration: underline;">Klikněte zde pro instalaci</a>
            </div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label for="smtp_host">SMTP Server (Host)</label>
                <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host'] ?? ''); ?>" required>
                <small>Např: smtp.gmail.com, smtp.seznam.cz, smtp.office365.com</small>
            </div>

            <div class="form-group">
                <label for="smtp_port">Port</label>
                <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($settings['smtp_port'] ?? '587'); ?>" required>
                <small>Obvykle 587 (TLS) nebo 465 (SSL)</small>
            </div>

            <div class="form-group">
                <label for="smtp_encryption">Šifrování</label>
                <select id="smtp_encryption" name="smtp_encryption" required>
                    <option value="tls" <?php echo ($settings['smtp_encryption'] ?? 'tls') === 'tls' ? 'selected' : ''; ?>>TLS (doporučeno)</option>
                    <option value="ssl" <?php echo ($settings['smtp_encryption'] ?? '') === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                    <option value="none" <?php echo ($settings['smtp_encryption'] ?? '') === 'none' ? 'selected' : ''; ?>>Bez šifrování</option>
                </select>
            </div>

            <div class="form-group">
                <label for="smtp_username">Uživatelské jméno (Username)</label>
                <input type="text" id="smtp_username" name="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username'] ?? ''); ?>" required>
                <small>Obvykle vaše emailová adresa</small>
            </div>

            <div class="form-group">
                <label for="smtp_password">Heslo (Password)</label>
                <input type="password" id="smtp_password" name="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password'] ?? ''); ?>" required>
                <small>Pro Gmail použijte "App Password"</small>
            </div>

            <div class="form-group">
                <label for="smtp_from_email">Odesílatel - Email</label>
                <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($settings['smtp_from_email'] ?? WGS_EMAIL_REKLAMACE); ?>" required>
            </div>

            <div class="form-group">
                <label for="smtp_from_name">Odesílatel - Jméno</label>
                <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name'] ?? 'White Glove Service'); ?>" required>
            </div>

            <button type="submit" name="save" class="btn btn-primary">💾 Uložit nastavení</button>
            <a href="/admin.php" class="btn btn-secondary">← Zpět</a>
        </form>

        <div class="test-section">
            <h3>🧪 Otestovat SMTP připojení</h3>
            <p>Odešle testovací email pro ověření konfigurace.</p>
            <form method="POST">
                <div class="form-group">
                    <label for="test_email">Testovací emailová adresa</label>
                    <input type="email" id="test_email" name="test_email" placeholder="vas-email@example.com" required>
                </div>
                <button type="submit" name="test" class="btn btn-success">📧 Odeslat testovací email</button>
            </form>
        </div>
    </div>
</body>
</html>
