<?php
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/csrf_helper.php';
require_once __DIR__ . '/../../includes/db_metadata.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Povolena je pouze metoda POST.');
    }

    requireCSRF();

    $action = $_POST['action'] ?? '';
    $email = trim($_POST['email'] ?? '');
    $registrationKey = trim($_POST['registration_key'] ?? '');

    if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Zadejte platný email.');
    }
    if ($registrationKey === '') {
        throw new InvalidArgumentException('Zadejte registrační klíč.');
    }

    $pdo = getDbConnection();

    switch ($action) {
        case 'verify':
            handleVerification($pdo, $email, $registrationKey);
            break;
        case 'change_password':
            $newPassword = $_POST['new_password'] ?? '';
            $newPasswordConfirm = $_POST['new_password_confirm'] ?? '';
            handlePasswordChange($pdo, $email, $registrationKey, $newPassword, $newPasswordConfirm);
            break;
        default:
            throw new InvalidArgumentException('Neznámá akce.');
    }
} catch (InvalidArgumentException $e) {
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    error_log('Password reset controller error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Došlo k neočekávané chybě. Zkuste to prosím znovu.'], JSON_UNESCAPED_UNICODE);
}

function handleVerification(PDO $pdo, string $email, string $registrationKey): void
{
    $user = findUserByEmail($pdo, $email);
    if (!$user) {
        respondUserNotFound();
        return;
    }

    if (!userMatchesRegistrationKey($pdo, $user, $registrationKey)) {
        respondUserNotFound();
        return;
    }

    echo json_encode([
        'status' => 'success',
        'message' => 'Identita ověřena. Můžete nastavit nové heslo.',
        'user' => [
            'id' => $user['id'] ?? $user['user_id'] ?? null,
            'name' => $user['name'] ?? '',
            'role' => $user['role'] ?? 'user'
        ]
    ], JSON_UNESCAPED_UNICODE);
}

function handlePasswordChange(PDO $pdo, string $email, string $registrationKey, string $newPassword, string $confirm): void
{
    if ($newPassword === '' || $confirm === '') {
        throw new InvalidArgumentException('Vyplňte nové heslo i potvrzení.');
    }
    if (!hash_equals($newPassword, $confirm)) {
        throw new InvalidArgumentException('Zadaná hesla se neshodují.');
    }
    if (strlen($newPassword) < 8) {
        throw new InvalidArgumentException('Heslo musí mít alespoň 8 znaků.');
    }
    $strength = isStrongPassword($newPassword);
    if ($strength !== true) {
        throw new InvalidArgumentException('Heslo nesplňuje požadavky: ' . implode(', ', (array) $strength));
    }

    $user = findUserByEmail($pdo, $email);
    if (!$user) {
        respondUserNotFound();
        return;
    }

    if (!userMatchesRegistrationKey($pdo, $user, $registrationKey)) {
        respondUserNotFound();
        return;
    }

    $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $columns = db_get_table_columns($pdo, 'wgs_users');

    $fields = [];
    if (in_array('password_hash', $columns, true)) {
        $fields['password_hash'] = $passwordHash;
    } elseif (in_array('password', $columns, true)) {
        $fields['password'] = $passwordHash;
    } elseif (in_array('heslo', $columns, true)) {
        $fields['heslo'] = $passwordHash;
    } else {
        throw new RuntimeException('Tabulka wgs_users neobsahuje sloupec pro heslo.');
    }

    $now = date('Y-m-d H:i:s');
    if (in_array('updated_at', $columns, true)) {
        $fields['updated_at'] = $now;
    }
    if (in_array('password_changed_at', $columns, true)) {
        $fields['password_changed_at'] = $now;
    }
    if (in_array('must_change_password', $columns, true)) {
        $fields['must_change_password'] = 0;
    }

    $setParts = [];
    $params = [':email' => $email];
    foreach ($fields as $column => $value) {
        $setParts[] = $column . ' = :' . $column;
        $params[':' . $column] = $value;
    }

    $updateSql = 'UPDATE wgs_users SET ' . implode(', ', $setParts) . ' WHERE email = :email LIMIT 1';
    $stmt = $pdo->prepare($updateSql);
    $stmt->execute($params);

    echo json_encode([
        'status' => 'success',
        'message' => 'Heslo bylo úspěšně změněno.'
    ], JSON_UNESCAPED_UNICODE);
}

function findUserByEmail(PDO $pdo, string $email): ?array
{
    $stmt = $pdo->prepare('SELECT * FROM wgs_users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    return $user ?: null;
}

function userMatchesRegistrationKey(PDO $pdo, array $user, string $registrationKey): bool
{
    $columns = db_get_table_columns($pdo, 'wgs_users');

    if (in_array('registration_key_code', $columns, true) && isset($user['registration_key_code'])) {
        return hash_equals($user['registration_key_code'], $registrationKey);
    }

    if (in_array('registration_key', $columns, true) && isset($user['registration_key'])) {
        return hash_equals($user['registration_key'], $registrationKey);
    }

    if (in_array('registration_key_hash', $columns, true) && isset($user['registration_key_hash'])) {
        $hash = hash('sha256', $registrationKey);
        return hash_equals($user['registration_key_hash'], $hash);
    }

    return false;
}

function respondUserNotFound(): void
{
    http_response_code(404);
    echo json_encode([
        'status' => 'error',
        'message' => 'Uživatel nebo registrační klíč nebyl nalezen.'
    ], JSON_UNESCAPED_UNICODE);
}
