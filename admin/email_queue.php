<?php
/**
 * Email Queue Management - Admin Interface
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/EmailQueue.php';

// Security: Admin only
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
if (!$isAdmin) {
    header('Location: /login.php');
    exit;
}

$queue = new EmailQueue();
$message = null;
$error = null;

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (isset($_POST['retry'])) {
            $queue->retry($_POST['id']);
            $message = 'Email byl znovu přidán do fronty';
        } elseif (isset($_POST['delete'])) {
            $queue->delete($_POST['id']);
            $message = 'Email byl smazán z fronty';
        } elseif (isset($_POST['process_now'])) {
            $results = $queue->processQueue(10);
            $message = "Zpracováno: {$results['processed']}, Odesláno: {$results['sent']}, Selhalo: {$results['failed']}";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get filter
$filter = $_GET['status'] ?? null;

// Get queue items
$emails = $queue->getQueue($filter, 100);
$stats = $queue->getStats();

?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Fronta | WGS Admin</title>
    <link rel="stylesheet" href="/assets/css/styles.min.css">
    <link rel="stylesheet" href="/assets/css/admin-header.min.css">
    <style>
        body {
            background: #f5f5f5;
            font-family: 'Poppins', Arial, sans-serif;
        }
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        .header {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            margin: 0;
            color: #666;
            font-size: 0.9rem;
            text-transform: uppercase;
        }
        .stat-card .number {
            font-size: 2.5rem;
            font-weight: bold;
            margin: 0.5rem 0;
        }
        .stat-card.pending .number { color: #ffc107; }
        .stat-card.sent .number { color: #28a745; }
        .stat-card.failed .number { color: #dc3545; }
        .stat-card.sending .number { color: #17a2b8; }
        .filters {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .filter-btn {
            padding: 0.5rem 1rem;
            border: 2px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
            text-decoration: none;
            color: #333;
        }
        .filter-btn.active {
            background: #007bff;
            border-color: #007bff;
            color: white;
        }
        .email-list {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .email-item {
            padding: 1rem;
            border-bottom: 1px solid #eee;
        }
        .email-item:last-child {
            border-bottom: none;
        }
        .email-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 0.5rem;
        }
        .email-subject {
            font-weight: 600;
            color: #333;
        }
        .email-status {
            padding: 0.25rem 0.75rem;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending { background: #fff3cd; color: #856404; }
        .status-sending { background: #d1ecf1; color: #0c5460; }
        .status-sent { background: #d4edda; color: #155724; }
        .status-failed { background: #f8d7da; color: #721c24; }
        .email-details {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        .email-actions {
            display: flex;
            gap: 0.5rem;
        }
        .btn-small {
            padding: 0.25rem 0.75rem;
            font-size: 0.85rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }
        .btn-retry {
            background: #ffc107;
            color: #000;
        }
        .btn-delete {
            background: #dc3545;
            color: white;
        }
        .btn-primary {
            background: #007bff;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
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
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #999;
        }
    </style>
</head>
<body>
    <?php require_once __DIR__ . '/../includes/hamburger-menu.php'; ?>

    <div class="container">
        <div class="header">
            <h1>📧 Email Fronta</h1>
            <p>Správa asynchronního odesílání emailů</p>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" style="margin-top: 1rem;">
                <button type="submit" name="process_now" class="btn-primary">
                    🚀 Zpracovat frontu nyní
                </button>
                <a href="/admin/smtp_settings.php" class="btn-primary" style="display: inline-block; text-decoration: none; background: #6c757d;">
                    ⚙️ SMTP Nastavení
                </a>
                <a href="/admin.php" class="btn-primary" style="display: inline-block; text-decoration: none; background: #6c757d;">
                    ← Zpět
                </a>
            </form>
        </div>

        <div class="stats">
            <div class="stat-card pending">
                <h3>Čekající</h3>
                <div class="number"><?php echo $stats['pending'] ?? 0; ?></div>
            </div>
            <div class="stat-card sending">
                <h3>Odesílá se</h3>
                <div class="number"><?php echo $stats['sending'] ?? 0; ?></div>
            </div>
            <div class="stat-card sent">
                <h3>Odesláno</h3>
                <div class="number"><?php echo $stats['sent'] ?? 0; ?></div>
            </div>
            <div class="stat-card failed">
                <h3>Selhalo</h3>
                <div class="number"><?php echo $stats['failed'] ?? 0; ?></div>
            </div>
        </div>

        <div class="filters">
            <a href="?status=" class="filter-btn <?php echo !$filter ? 'active' : ''; ?>">Všechny</a>
            <a href="?status=pending" class="filter-btn <?php echo $filter === 'pending' ? 'active' : ''; ?>">Čekající</a>
            <a href="?status=sending" class="filter-btn <?php echo $filter === 'sending' ? 'active' : ''; ?>">Odesílá se</a>
            <a href="?status=sent" class="filter-btn <?php echo $filter === 'sent' ? 'active' : ''; ?>">Odesláno</a>
            <a href="?status=failed" class="filter-btn <?php echo $filter === 'failed' ? 'active' : ''; ?>">Selhalo</a>
        </div>

        <div class="email-list">
            <?php if (empty($emails)): ?>
                <div class="empty-state">
                    <p>📭 Fronta je prázdná</p>
                </div>
            <?php else: ?>
                <?php foreach ($emails as $email): ?>
                    <div class="email-item">
                        <div class="email-header">
                            <div>
                                <div class="email-subject"><?php echo htmlspecialchars($email['subject']); ?></div>
                                <div class="email-details">
                                    Komu: <?php echo htmlspecialchars($email['recipient_email']); ?> |
                                    Vytvořeno: <?php echo date('d.m.Y H:i', strtotime($email['created_at'])); ?>
                                    <?php if ($email['status'] === 'failed'): ?>
                                        | Pokusů: <?php echo $email['attempts']; ?>/<?php echo $email['max_attempts']; ?>
                                    <?php endif; ?>
                                </div>
                                <?php if ($email['error_message']): ?>
                                    <div class="email-details" style="color: #dc3545;">
                                        Chyba: <?php echo htmlspecialchars($email['error_message']); ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <span class="email-status status-<?php echo $email['status']; ?>">
                                <?php echo strtoupper($email['status']); ?>
                            </span>
                        </div>

                        <div class="email-actions">
                            <?php if ($email['status'] === 'failed'): ?>
                                <form method="POST" style="display: inline;">
                                    <input type="hidden" name="id" value="<?php echo $email['id']; ?>">
                                    <button type="submit" name="retry" class="btn-small btn-retry">🔄 Zkusit znovu</button>
                                </form>
                            <?php endif; ?>

                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="id" value="<?php echo $email['id']; ?>">
                                <button type="submit" name="delete" class="btn-small btn-delete" onclick="return confirm('Opravdu smazat?')">🗑️ Smazat</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
