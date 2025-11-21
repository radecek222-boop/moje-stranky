<?php
/**
 * LOGOUT ENDPOINT
 *
 * ✅ SECURITY FIX: CSRF protection pro logout
 * Chrání proti force-logout útokům z malicious sites
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// ✅ SECURITY: Pokud GET request, zobrazit potvrzovací formulář
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    // Zobrazit potvrzovací stránku s POST formulářem
    ?>
    <!DOCTYPE html>
    <html lang="cs">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Odhlášení - WGS Service</title>
        <style>
            body {
                font-family: 'Poppins', sans-serif;
                background: #f5f5f5;
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
            }
            .logout-container {
                background: white;
                padding: 40px;
                border-radius: 10px;
                box-shadow: 0 2px 20px rgba(0,0,0,0.1);
                max-width: 400px;
                text-align: center;
            }
            h1 {
                color: #2D5016;
                margin-bottom: 20px;
                font-size: 24px;
            }
            p {
                color: #666;
                margin-bottom: 30px;
                line-height: 1.6;
            }
            .btn {
                background: #2D5016;
                color: white;
                border: none;
                padding: 12px 30px;
                font-size: 16px;
                border-radius: 5px;
                cursor: pointer;
                text-transform: uppercase;
                font-weight: 600;
                letter-spacing: 0.05em;
                transition: background 0.3s ease;
            }
            .btn:hover {
                background: #1a300d;
            }
            .btn-cancel {
                background: #999;
                margin-left: 10px;
            }
            .btn-cancel:hover {
                background: #666;
            }
        </style>
    </head>
    <body>
        <div class="logout-container">
            <h1>🔒 Odhlášení</h1>
            <p>Opravdu se chcete odhlásit z WGS Service?</p>
            <form method="POST" action="/logout.php">
                <?php
                // Vygenerovat CSRF token
                $token = generateCSRFToken();
                ?>
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit" class="btn">Ano, odhlásit</button>
                <button type="button" class="btn btn-cancel" id="cancelLogout">Zrušit</button>
            </form>
        </div>
        <script>
            // Event listener pro tlačítko Zrušit (bez inline onclick - best practice)
            document.getElementById('cancelLogout').addEventListener('click', function() {
                history.back();
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

// ✅ SECURITY: CSRF validace
requireCSRF();

// Smazat Remember Me token z databáze pokud existuje
if (isset($_COOKIE['remember_me'])) {
    try {
        $cookieValue = $_COOKIE['remember_me'] ?? '';
        if (strpos($cookieValue, ':') !== false) {
            [$selector, $validator] = explode(':', $cookieValue, 2);

            $pdo = getDbConnection();
            $stmt = $pdo->prepare("DELETE FROM wgs_remember_tokens WHERE selector = :selector");
            $stmt->execute([':selector' => $selector]);
        }
    } catch (Exception $e) {
        error_log("Logout: Remember Me token deletion failed: " . $e->getMessage());
    }

    // Smazat Remember Me cookie
    setcookie('remember_me', '', time() - 3600, '/', '', true, true);
}

// Audit log před smazáním session
if (function_exists('auditLog')) {
    auditLog('user_logout', [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'login_method' => $_SESSION['login_method'] ?? 'unknown'
    ]);
}

// Smazat session data
$_SESSION = [];

// Smazat session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect na login
header('Location: login.php?logged_out=1');
exit;
?>
