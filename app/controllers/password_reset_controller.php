<?php
/**
 * Password Reset Controller
 * Reset hesla pomocí registračního klíče
 */

require_once __DIR__ . '/../../init.php';

header('Content-Type: application/json');

try {
    // Kontrola metody
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Povolena pouze POST metoda');
    }

    // BEZPEČNOST: Rate limiting - ochrana proti brute-force
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rateLimit = checkRateLimit("password_reset_$ip", 5, 1800); // 5 pokusů za 30 minut

    if (!$rateLimit['allowed']) {
        http_response_code(429);
        echo json_encode([
            'status' => 'error',
            'message' => 'Příliš mnoho pokusů o reset hesla. Zkuste to za ' . ceil($rateLimit['retry_after'] / 60) . ' minut.'
        ]);
        exit;
    }

    // Získání akce
    $action = $_POST['action'] ?? '';

    if ($action === 'verify') {
        handleVerifyIdentity($_POST, $ip);
    } elseif ($action === 'change_password') {
        handleChangePassword($_POST, $ip);
    } else {
        throw new Exception('Neplatná akce');
    }

} catch (Exception $e) {
    // Zaznamenat neúspěšný pokus
    if (isset($ip)) {
        recordLoginAttempt("password_reset_$ip");
    }

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}

/**
 * STEP 1: Ověření identity pomocí emailu a registračního klíče
 */
function handleVerifyIdentity($data, $ip) {
    $email = trim($data['email'] ?? '');
    $registrationKey = $data['registration_key'] ?? '';

    // Validace
    if (empty($email) || empty($registrationKey)) {
        throw new Exception('Email a registrační klíč jsou povinné');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Neplatný formát emailu');
    }

    // Získat registrační klíč hash z .env
    $registrationKeyHash = getenv('REGISTRATION_KEY_HASH') ?: $_ENV['REGISTRATION_KEY_HASH'] ?? null;

    if (!$registrationKeyHash) {
        throw new Exception('Reset hesla není momentálně dostupný. Kontaktujte administrátora.');
    }

    // Ověření registračního klíče
    $providedHash = hash('sha256', $registrationKey);

    if (!hash_equals($registrationKeyHash, $providedHash)) {
        recordLoginAttempt("password_reset_$ip");
        throw new Exception('Neplatný registrační klíč');
    }

    // Databázové připojení
    $pdo = getDbConnection();

    // Najít uživatele podle emailu
    $stmt = $pdo->prepare("SELECT id, name, email, role FROM wgs_users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Uživatel s tímto emailem nebyl nalezen');
    }

    // Úspěšné ověření
    echo json_encode([
        'status' => 'success',
        'message' => 'Identita ověřena',
        'user' => [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role']
        ]
    ]);
}

/**
 * STEP 2: Změna hesla po ověření
 */
function handleChangePassword($data, $ip) {
    $email = trim($data['email'] ?? '');
    $registrationKey = $data['registration_key'] ?? '';
    $newPassword = $data['new_password'] ?? '';
    $newPasswordConfirm = $data['new_password_confirm'] ?? '';

    // Validace
    if (empty($email) || empty($registrationKey) || empty($newPassword) || empty($newPasswordConfirm)) {
        throw new Exception('Všechna pole jsou povinná');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Neplatný formát emailu');
    }

    if ($newPassword !== $newPasswordConfirm) {
        throw new Exception('Hesla se neshodují');
    }

    if (strlen($newPassword) < 8) {
        throw new Exception('Heslo musí mít alespoň 8 znaků');
    }

    // BEZPEČNOST: Kontrola síly hesla
    if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[a-z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        throw new Exception('Heslo musí obsahovat velké písmeno, malé písmeno a číslo');
    }

    // Znovu ověřit registrační klíč (bezpečnostní opatření)
    $registrationKeyHash = getenv('REGISTRATION_KEY_HASH') ?: $_ENV['REGISTRATION_KEY_HASH'] ?? null;

    if (!$registrationKeyHash) {
        throw new Exception('Reset hesla není momentálně dostupný. Kontaktujte administrátora.');
    }

    $providedHash = hash('sha256', $registrationKey);

    if (!hash_equals($registrationKeyHash, $providedHash)) {
        recordLoginAttempt("password_reset_$ip");
        throw new Exception('Neplatný registrační klíč');
    }

    // Databázové připojení
    $pdo = getDbConnection();

    // Najít uživatele
    $stmt = $pdo->prepare("SELECT id FROM wgs_users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception('Uživatel nenalezen');
    }

    // Hash nového hesla
    $passwordHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    // Aktualizace hesla
    $updateStmt = $pdo->prepare("UPDATE wgs_users SET password = :password, updated_at = NOW() WHERE id = :id");
    $result = $updateStmt->execute([
        ':password' => $passwordHash,
        ':id' => $user['id']
    ]);

    if (!$result) {
        throw new Exception('Chyba při aktualizaci hesla');
    }

    // Log změny hesla
    error_log("Password reset successful for user ID: {$user['id']}, IP: {$ip}");

    // Úspěch
    echo json_encode([
        'status' => 'success',
        'message' => 'Heslo bylo úspěšně změněno. Nyní se můžete přihlásit.'
    ]);
}
?>
