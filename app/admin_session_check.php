<?php
/**
 * ADMIN SESSION CHECK
 * Kontrola přihlášení admina
 * Vrací JSON pro AJAX kontroly
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../init.php';

// Kontrola admin session
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);

// Admin musí být přihlášen jako admin
if ($isAdmin && $isLoggedIn) {
    echo json_encode([
        'authenticated' => true,
        'username' => $_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Admin',
        'email' => $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? 'admin@wgs-service.cz',
        'role' => 'admin'
    ]);
} else {
    echo json_encode([
        'authenticated' => false
    ]);
}
?>
