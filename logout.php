<?php
/**
 * LOGOUT ENDPOINT
 *
 * ✅ SECURITY FIX: CSRF protection pro logout
 * Chrání proti force-logout útokům z malicious sites
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// ✅ SECURITY: Pouze POST metoda
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Povolena je pouze metoda POST.');
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
