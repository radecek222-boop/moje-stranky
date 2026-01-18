<?php
require_once __DIR__ . '/init.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    die('Admin only');
}

try {
    $pdo = getDbConnection();

    $stmt = $pdo->prepare("SELECT user_id, email, jmeno, prijmeni, telefon, role, is_active, last_login FROM wgs_users WHERE user_id = 'TCH20250002'");
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        echo "<pre>";
        echo "USER: TCH20250002\n";
        echo "==================\n";
        echo "Email: " . ($user['email'] ?? 'N/A') . "\n";
        echo "Jméno: " . ($user['jmeno'] ?? '') . " " . ($user['prijmeni'] ?? '') . "\n";
        echo "Telefon: " . ($user['telefon'] ?? 'N/A') . "\n";
        echo "Role: " . ($user['role'] ?? 'N/A') . "\n";
        echo "Aktivní: " . ($user['is_active'] ? 'Ano' : 'Ne') . "\n";
        echo "Poslední přihlášení: " . ($user['last_login'] ?? 'Nikdy') . "\n";
        echo "</pre>";
    } else {
        echo "Uživatel TCH20250002 nebyl nalezen v databázi.";
    }

} catch (Exception $e) {
    echo "Chyba: " . $e->getMessage();
}
?>
