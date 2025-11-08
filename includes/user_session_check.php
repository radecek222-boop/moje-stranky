<?php
/**
 * USER SESSION CHECK
 * Kontrola přihlášení uživatele (ne admina)
 * Vrací JSON pro AJAX kontroly
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../init.php';

// Kontrola user session
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['role']);
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// User musí být přihlášen, ale NE jako admin
$isUser = $isLoggedIn && !$isAdmin;

if ($isUser) {
    echo json_encode([
        'logged_in' => true,
        'user_id' => $_SESSION['user_id'],
        'name' => $_SESSION['user_name'] ?? '',
        'email' => $_SESSION['user_email'] ?? '',
        'role' => $_SESSION['role'] ?? 'prodejce'
    ]);
} else {
    echo json_encode([
        'logged_in' => false
    ]);
}
?>

