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

        // Read SQL migration file
        $sqlFile = __DIR__ . '/../migrations/create_email_queue.sql';

        if (!file_exists($sqlFile)) {
            throw new Exception("Migration file not found");
        }

        $sql = file_get_contents($sqlFile);

        // Remove comments and split by semicolons
        $statements = array_filter(
            array_map('trim', preg_split('/;(?=(?:[^\'"]|[\'"][^\'"]*[\'"])*$)/', $sql)),
            function($stmt) {
                return !empty($stmt) &&
                       !preg_match('/^--/', $stmt) &&
                       !preg_match('/^SELECT/', $stmt);
            }
        );

        foreach ($statements as $statement) {
            if (trim($statement)) {
                $pdo->exec($statement);
            }
        }

        $status = 'success';

    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Check if already installed
$pdo = getDbConnection();
$tablesExist = false;
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'wgs_email_queue'");
    $tablesExist = $stmt->rowCount() > 0;
} catch (Exception $e) {
    // Ignore
}

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
