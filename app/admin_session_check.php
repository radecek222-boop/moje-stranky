<?php
/**
 * ADMIN SESSION CHECK
 * Kontrola přihlášení admina NEBO technika
 * Vrací JSON pro AJAX kontroly
 *
 * FIX: Přidána kontrola pro techniky (photocustomer.php vyžaduje admin NEBO technik)
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../init.php';

// Kontrola zda je uživatel přihlášen
$isLoggedIn = isset($_SESSION['user_id']) || isset($_SESSION['admin_id']);

if (!$isLoggedIn) {
    echo json_encode([
        'authenticated' => false,
        'logged_in' => false
    ]);
    exit;
}

// Kontrola admin session
$isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;

// FIX: Kontrola technik session (photocustomer.php vyžaduje přístup i pro techniky)
$rawRole = (string) ($_SESSION['role'] ?? '');
$normalizedRole = strtolower(trim($rawRole));

$technikKeywords = ['technik', 'technician'];
$isTechnik = in_array($normalizedRole, $technikKeywords, true);

if (!$isTechnik) {
    foreach ($technikKeywords as $keyword) {
        if (strpos($normalizedRole, $keyword) !== false) {
            $isTechnik = true;
            break;
        }
    }
}

// Admin NEBO Technik má přístup
if ($isAdmin || $isTechnik) {
    echo json_encode([
        'authenticated' => true,
        'logged_in' => true,  // For backward compatibility
        'username' => $_SESSION['user_name'] ?? $_SESSION['admin_name'] ?? 'Admin',
        'email' => $_SESSION['user_email'] ?? $_SESSION['admin_email'] ?? 'admin@wgs-service.cz',
        'role' => $isAdmin ? 'admin' : 'technik'
    ]);
} else {
    echo json_encode([
        'authenticated' => false,
        'logged_in' => false,
        'reason' => 'Přístup povolen pouze pro adminy a techniky'
    ]);
}
?>
