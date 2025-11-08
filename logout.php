<?php
/**
 * LOGOUT ENDPOINT
 */

session_start();

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
header('Location: login.php');
exit;
?>
