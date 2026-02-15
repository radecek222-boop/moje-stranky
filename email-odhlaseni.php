<?php
/**
 * Email Unsubscribe Page
 * Stránka pro odhlášení z emailových notifikací
 */

require_once "init.php";
require_once __DIR__ . '/includes/email_footer.php';

$zprava = '';
$typ = 'info';
$email = '';

// Zpracovat token z URL
if (isset($_GET['token'])) {
    $tokenData = verifikovatUnsubscribeToken($_GET['token']);

    if ($tokenData) {
        $email = $tokenData['email'];

        // Pokud je POST request, provést odhlášení
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && validateCSRFToken($_POST['csrf_token'] ?? '')) {
            try {
                $pdo = getDbConnection();

                // Odstranit push subscriptions
                $stmt = $pdo->prepare("DELETE FROM wgs_push_subscriptions WHERE email = :email");
                $stmt->execute(['email' => $email]);
                $pushesSmazano = $stmt->rowCount();

                // Zalogovat
                if (function_exists('auditLog')) {
                    auditLog('EMAIL_UNSUBSCRIBE', "Uživatel se odhlásil z notifikací: {$email}", [
                        'push_smazano' => $pushesSmazano
                    ]);
                }

                $zprava = 'Byli jste úspěšně odhlášeni z emailových notifikací.';
                $typ = 'success';

            } catch (PDOException $e) {
                error_log("Unsubscribe error: " . $e->getMessage());
                $zprava = 'Při odhlašování došlo k chybě. Zkuste to prosím později.';
                $typ = 'error';
            }
        }
    } else {
        $zprava = 'Neplatný nebo expirovaný odkaz. Pro odhlášení kontaktujte reklamace@wgs-service.cz.';
        $typ = 'error';
    }
} else {
    $zprava = 'Chybí odkaz pro odhlášení.';
    $typ = 'error';
}

$csrfToken = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="cs">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=5.0, user-scalable=yes">
  <meta name="theme-color" content="#000000">
  <meta name="robots" content="noindex, nofollow">
  <title>Odhlášení z emailů | White Glove Service</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="assets/css/styles.min.css">
  <style>
    .unsubscribe-container {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #f9fafb;
      padding: 2rem;
    }

    .unsubscribe-card {
      background: #fff;
      border-radius: 16px;
      padding: 3rem;
      max-width: 480px;
      width: 100%;
      box-shadow: 0 25px 60px rgba(15, 23, 42, 0.08);
      border: 1px solid rgba(148, 163, 184, 0.15);
      text-align: center;
    }

    .unsubscribe-card h1 {
      font-size: 1.5rem;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      margin-bottom: 1rem;
      color: #111827;
    }

    .unsubscribe-card p {
      font-size: 0.95rem;
      line-height: 1.7;
      color: #374151;
      margin-bottom: 1.5rem;
    }

    .unsubscribe-email {
      background: #f3f4f6;
      padding: 0.75rem 1rem;
      border-radius: 8px;
      font-family: monospace;
      font-size: 0.9rem;
      color: #111827;
      margin-bottom: 1.5rem;
    }

    .unsubscribe-btn {
      display: inline-block;
      padding: 12px 24px;
      background: #dc3545;
      color: #fff;
      border: none;
      border-radius: 8px;
      font-size: 1rem;
      font-weight: 600;
      font-family: 'Poppins', sans-serif;
      cursor: pointer;
      transition: background 0.2s;
    }

    .unsubscribe-btn:hover {
      background: #c82333;
    }

    .unsubscribe-btn:disabled {
      background: #9ca3af;
      cursor: not-allowed;
    }

    .message-box {
      padding: 1rem 1.5rem;
      border-radius: 8px;
      margin-bottom: 1.5rem;
    }

    .message-box.success {
      background: rgba(40, 167, 69, 0.1);
      border: 1px solid #28a745;
      color: #155724;
    }

    .message-box.error {
      background: rgba(220, 53, 69, 0.1);
      border: 1px solid #dc3545;
      color: #721c24;
    }

    .message-box.info {
      background: rgba(17, 24, 39, 0.05);
      border: 1px solid #d1d5db;
      color: #374151;
    }

    .back-link {
      display: inline-block;
      margin-top: 1.5rem;
      color: #6b7280;
      text-decoration: underline;
      font-size: 0.9rem;
    }

    .back-link:hover {
      color: #111827;
    }
  </style>
</head>
<body>
<div class="unsubscribe-container">
  <div class="unsubscribe-card">
    <h1>Odhlášení z notifikací</h1>

    <?php if ($typ === 'success'): ?>
      <div class="message-box success">
        <?php echo htmlspecialchars($zprava); ?>
      </div>
      <p>Již nebudete dostávat emailové notifikace na tuto adresu.</p>
      <a href="index.php" class="back-link">Zpět na hlavní stránku</a>

    <?php elseif ($typ === 'error'): ?>
      <div class="message-box error">
        <?php echo htmlspecialchars($zprava); ?>
      </div>
      <p>Pokud potřebujete pomoc, kontaktujte nás na <a href="mailto:reklamace@wgs-service.cz">reklamace@wgs-service.cz</a>.</p>
      <a href="index.php" class="back-link">Zpět na hlavní stránku</a>

    <?php else: ?>
      <p>Kliknutím na tlačítko níže se odhlásíte z emailových notifikací pro adresu:</p>
      <div class="unsubscribe-email"><?php echo htmlspecialchars($email); ?></div>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($csrfToken); ?>">
        <button type="submit" class="unsubscribe-btn">Odhlásit se</button>
      </form>

      <a href="index.php" class="back-link">Zrušit a vrátit se</a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
