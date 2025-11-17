<?php
/**
 * VYČIŠTĚNÍ SELHAVŠÍCH EMAILŮ Z FRONTY
 * Smaže všechny emaily se statusem 'failed' z wgs_email_queue
 * BEZPEČNOST: Pouze pro přihlášené administrátory
 */

require_once __DIR__ . '/init.php';

// KRITICKÉ: Vyžadovat admin session
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    http_response_code(403);
    die('<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Přístup odepřen</title></head><body style="font-family: Poppins; background: #fff; color: #000; padding: 40px; text-align: center;"><h1>❌ PŘÍSTUP ODEPŘEN</h1><p>Pouze pro administrátory!</p></body></html>');
}

$vysledek = null;
$failedEmails = [];

try {
    $pdo = getDbConnection();

    // Načíst selhavší emaily před smazáním
    $stmt = $pdo->query("
        SELECT
            id,
            to_email,
            subject,
            status,
            retry_count,
            last_error,
            created_at,
            updated_at
        FROM wgs_email_queue
        WHERE status = 'failed'
        ORDER BY updated_at DESC
    ");
    $failedEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Pokud uživatel potvrdil smazání
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['potvrdit_smazani'])) {
        // CSRF ochrana
        require_once __DIR__ . '/includes/csrf_helper.php';
        if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
            die('Neplatný CSRF token');
        }

        // Smazat všechny failed emaily
        $stmt = $pdo->prepare("DELETE FROM wgs_email_queue WHERE status = 'failed'");
        $stmt->execute();
        $pocetSmazanych = $stmt->rowCount();

        $vysledek = [
            'status' => 'success',
            'pocet' => $pocetSmazanych,
            'zprava' => "Úspěšně smazáno $pocetSmazanych selhavších emailů"
        ];

        // Znovu načíst data
        $stmt = $pdo->query("SELECT * FROM wgs_email_queue WHERE status = 'failed'");
        $failedEmails = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

} catch (Exception $e) {
    $vysledek = [
        'status' => 'error',
        'zprava' => 'Chyba: ' . $e->getMessage()
    ];
}

// Získat CSRF token pro formulář
require_once __DIR__ . '/includes/csrf_helper.php';
$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vyčištění emailové fronty | WGS</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Poppins', sans-serif;
            background: #fff;
            color: #000;
            padding: 2rem;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            border: 2px solid #000;
        }
        .header {
            background: #000;
            color: #fff;
            padding: 2rem;
        }
        h1 {
            font-size: 1.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        .content {
            padding: 2rem;
        }
        .stat-box {
            border: 2px solid #000;
            padding: 2rem;
            text-align: center;
            margin: 2rem 0;
        }
        .stat-value {
            font-size: 3rem;
            font-weight: 700;
            color: #ef4444;
            margin-bottom: 0.5rem;
        }
        .stat-label {
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #555;
        }
        .alert {
            padding: 1.5rem;
            margin: 2rem 0;
            border: 2px solid;
        }
        .alert.success {
            background: #f0fdf4;
            border-color: #22c55e;
            color: #15803d;
        }
        .alert.error {
            background: #fef2f2;
            border-color: #ef4444;
            color: #991b1b;
        }
        .alert.warning {
            background: #fffbeb;
            border-color: #f59e0b;
            color: #92400e;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 2rem 0;
            border: 2px solid #000;
        }
        th, td {
            padding: 1rem;
            text-align: left;
            border: 1px solid #ddd;
        }
        th {
            background: #000;
            color: #fff;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-size: 0.85rem;
        }
        tr:hover {
            background: #f5f5f5;
        }
        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            background: #000;
            color: #fff;
            text-decoration: none;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            border: 2px solid #000;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn:hover {
            background: #fff;
            color: #000;
        }
        .btn.danger {
            background: #ef4444;
            border-color: #ef4444;
        }
        .btn.danger:hover {
            background: #fff;
            color: #ef4444;
        }
        .footer {
            margin-top: 2rem;
            padding: 1.5rem 2rem;
            border-top: 2px solid #ddd;
            text-align: center;
            color: #555;
            font-size: 0.85rem;
        }
        .footer a {
            color: #000;
            text-decoration: none;
            border-bottom: 2px solid #000;
        }
        .error-details {
            background: #fef2f2;
            border: 1px solid #ef4444;
            padding: 0.5rem;
            margin-top: 0.5rem;
            font-size: 0.85rem;
            font-family: monospace;
            color: #991b1b;
            white-space: pre-wrap;
            word-wrap: break-word;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📧 VYČIŠTĚNÍ EMAILOVÉ FRONTY</h1>
            <p style="margin-top: 0.5rem; opacity: 0.9;">Smazání selhavších emailů z wgs_email_queue</p>
        </div>

        <div class="content">
            <?php if ($vysledek): ?>
                <div class="alert <?php echo $vysledek['status']; ?>">
                    <strong><?php echo $vysledek['status'] === 'success' ? '✅ ÚSPĚCH' : '❌ CHYBA'; ?></strong><br>
                    <?php echo htmlspecialchars($vysledek['zprava']); ?>
                </div>
            <?php endif; ?>

            <!-- STATISTIKA -->
            <div class="stat-box">
                <div class="stat-value"><?php echo count($failedEmails); ?></div>
                <div class="stat-label">Selhavších emailů v frontě</div>
            </div>

            <?php if (count($failedEmails) > 0): ?>
                <!-- VAROVÁNÍ -->
                <div class="alert warning">
                    <strong>⚠️ UPOZORNĚNÍ</strong><br>
                    V emailové frontě je <?php echo count($failedEmails); ?> selhavších emailů. Tyto emaily již nebudou znovu odeslány.
                    <br><br>
                    Můžete je buď:
                    <ul style="margin-top: 1rem; margin-left: 1.5rem;">
                        <li>Nechat ve frontě (pro debug účely)</li>
                        <li>Smazat pro vyčištění databáze</li>
                    </ul>
                </div>

                <!-- TABULKA SELHAVŠÍCH EMAILŮ -->
                <h2 style="margin: 2rem 0 1rem 0; padding-bottom: 0.75rem; border-bottom: 2px solid #000; font-size: 1.1rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.08em;">📋 Seznam selhavších emailů</h2>

                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Příjemce</th>
                            <th>Předmět</th>
                            <th>Počet pokusů</th>
                            <th>Poslední chyba</th>
                            <th>Datum</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($failedEmails as $email): ?>
                        <tr>
                            <td><?php echo $email['id']; ?></td>
                            <td><?php echo htmlspecialchars($email['to_email']); ?></td>
                            <td><?php echo htmlspecialchars(substr($email['subject'], 0, 50)); ?></td>
                            <td><?php echo $email['retry_count']; ?></td>
                            <td>
                                <?php if ($email['last_error']): ?>
                                    <details>
                                        <summary style="cursor: pointer;">Zobrazit chybu</summary>
                                        <div class="error-details"><?php echo htmlspecialchars($email['last_error']); ?></div>
                                    </details>
                                <?php else: ?>
                                    <em>Žádná chyba</em>
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('d.m.Y H:i', strtotime($email['updated_at'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <!-- FORMULÁŘ PRO SMAZÁNÍ -->
                <form method="POST" onsubmit="return confirm('Opravdu chcete smazat všechny selhavší emaily? Tato akce je nevratná!');" style="margin: 2rem 0;">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                    <input type="hidden" name="potvrdit_smazani" value="1">
                    <button type="submit" class="btn danger">
                        🗑️ SMAZAT VŠECHNY SELHAVŠÍ EMAILY (<?php echo count($failedEmails); ?>)
                    </button>
                </form>

            <?php else: ?>
                <!-- ŽÁDNÉ SELHAVŠÍ EMAILY -->
                <div class="alert success">
                    <strong>✅ PERFEKTNÍ!</strong><br>
                    V emailové frontě nejsou žádné selhavší emaily. Vše funguje správně.
                </div>
            <?php endif; ?>
        </div>

        <div class="footer">
            <a href="admin.php">← Zpět do Admin Panelu</a>
        </div>
    </div>
</body>
</html>
