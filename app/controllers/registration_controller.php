<?php
/**
 * Registration Controller
 * Registrace nových uživatelů (prodejci, technici) s registračním klíčem
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
    $rateLimit = checkRateLimit("registration_$ip", 5, 3600); // 5 pokusů za hodinu

    if (!$rateLimit['allowed']) {
        http_response_code(429);
        echo json_encode([
            'status' => 'error',
            'message' => 'Příliš mnoho pokusů o registraci. Zkuste to za ' . ceil($rateLimit['retry_after'] / 60) . ' minut.'
        ]);
        exit;
    }

    // Získání dat z formuláře
    $registrationKey = $_POST['registration_key'] ?? '';
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    // Validace povinných polí
    if (empty($registrationKey)) {
        throw new Exception('Registrační klíč je povinný');
    }
    if (empty($name)) {
        throw new Exception('Jméno je povinné');
    }
    if (empty($email)) {
        throw new Exception('Email je povinný');
    }
    if (empty($password)) {
        throw new Exception('Heslo je povinné');
    }

    // Validace emailu
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Neplatný formát emailu');
    }

    // Validace hesla
    if (strlen($password) < 8) {
        throw new Exception('Heslo musí mít alespoň 8 znaků');
    }

    // BEZPEČNOST: Kontrola síly hesla
    if (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password)) {
        throw new Exception('Heslo musí obsahovat velké písmeno, malé písmeno a číslo');
    }

    // Ověření registračního klíče
    // POZNÁMKA: Nyní používáme hash z .env, v budoucnu lze rozšířit o databázové klíče
    $registrationKeyHash = getenv('REGISTRATION_KEY_HASH') ?: $_ENV['REGISTRATION_KEY_HASH'] ?? null;

    if (!$registrationKeyHash) {
        throw new Exception('Registrace není momentálně povolena. Kontaktujte administrátora.');
    }

    // Kontrola hash
    $providedHash = hash('sha256', $registrationKey);

    if (!hash_equals($registrationKeyHash, $providedHash)) {
        // Zaznamenat neúspěšný pokus
        recordLoginAttempt("registration_$ip");
        throw new Exception('Neplatný registrační klíč');
    }

    // Databázové připojení
    $pdo = getDbConnection();

    // Kontrola, zda email již není použit
    $stmt = $pdo->prepare("SELECT id FROM wgs_users WHERE email = :email LIMIT 1");
    $stmt->execute([':email' => $email]);
    if ($stmt->fetch()) {
        throw new Exception('Tento email je již zaregistrován');
    }

    // Hash hesla pomocí BCrypt
    $passwordHash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);

    // Určení role (výchozí: prodejce, admin může změnit)
    $role = 'prodejce';

    // Vložení nového uživatele
    $insertStmt = $pdo->prepare("
        INSERT INTO wgs_users (
            name, email, phone, password, role, is_active, created_at, last_login
        ) VALUES (
            :name, :email, :phone, :password, :role, 1, NOW(), NULL
        )
    ");

    $result = $insertStmt->execute([
        ':name' => $name,
        ':email' => $email,
        ':phone' => $phone,
        ':password' => $passwordHash,
        ':role' => $role
    ]);

    if (!$result) {
        throw new Exception('Chyba při vytváření účtu');
    }

    // Získat ID nového uživatele
    $newUserId = $pdo->lastInsertId();

    // Úspěch
    http_response_code(201);
    echo json_encode([
        'status' => 'success',
        'message' => 'Registrace úspěšná! Nyní se můžete přihlásit.',
        'user_id' => $newUserId
    ]);

} catch (Exception $e) {
    // Zaznamenat neúspěšný pokus (pokud ještě nebyl zaznamenán)
    if (isset($ip) && !isset($providedHash)) {
        recordLoginAttempt("registration_$ip");
    }

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
?>
