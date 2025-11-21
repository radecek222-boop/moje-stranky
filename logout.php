<?php
/**
 * LOGOUT ENDPOINT
 *
 * ✅ SECURITY FIX: CSRF protection pro logout
 * ✅ OKAMŽITÉ ODHLÁŠENÍ bez potvrzovací stránky
 */

require_once __DIR__ . '/init.php';
require_once __DIR__ . '/includes/csrf_helper.php';

// ✅ SECURITY: Ochrana proti CSRF útokům
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // POST request → validovat CSRF token
    requireCSRF();
} elseif ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // GET request → zkontrolovat že request je z naší domény (ochrana před CSRF)
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['HTTP_HOST'] ?? '';

    // Pokud Referer není z naší domény → zamítnout
    if (!empty($referer) && strpos($referer, $host) === false) {
        http_response_code(403);
        die('CSRF protection: Logout request musí být z této domény');
    }

    // Pokud je uživatel nepřihlášen → redirect na login
    if (!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) {
        header('Location: login.php');
        exit;
    }
}

// ✅ OPRAVA: Audit log PRVNÍ (před smazáním session dat)
if (function_exists('auditLog')) {
    auditLog('user_logout', [
        'user_id' => $_SESSION['user_id'] ?? 'unknown',
        'login_method' => $_SESSION['login_method'] ?? 'unknown'
    ]);
}

// ✅ OPRAVA: Získat cookie params PŘED smazáním session
$sessionCookieParams = null;
if (ini_get("session.use_cookies")) {
    $sessionCookieParams = session_get_cookie_params();
}

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

        // ✅ OPRAVA: Smazat Remember Me cookie UVNITŘ try-catch
        setcookie('remember_me', '', time() - 3600, '/', '', true, true);

    } catch (Exception $e) {
        error_log("Logout: Remember Me cleanup failed: " . $e->getMessage());
        // I v případě chyby DB smazat cookie (bezpečnější než nechat dead token)
        setcookie('remember_me', '', time() - 3600, '/', '', true, true);
    }
}

// Smazat session data
$_SESSION = [];

// Destroy session
session_destroy();

// Smazat session cookie (použít params získané PŘED destroy)
if ($sessionCookieParams !== null) {
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $sessionCookieParams["path"],
        $sessionCookieParams["domain"],
        $sessionCookieParams["secure"],
        $sessionCookieParams["httponly"]
    );
}

// Redirect na login
header('Location: login.php?logged_out=1');
exit;
?>
