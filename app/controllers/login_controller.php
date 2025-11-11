<?php
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/csrf_helper.php';
require_once __DIR__ . '/../../includes/db_metadata.php';
require_once __DIR__ . '/../../includes/audit_logger.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new RuntimeException('Povolena je pouze metoda POST.');
    }

    requireCSRF();

    $pdo = getDbConnection();

    $adminKey = trim($_POST['admin_key'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $highKey = trim($_POST['high_key'] ?? '');
    $action = $_POST['action'] ?? '';

    if ($adminKey !== '') {
        handleAdminLogin($adminKey);
        exit;
    }

    if ($email !== '' && $password !== '') {
        handleUserLogin($pdo, $email, $password);
        exit;
    }

    if ($highKey !== '') {
        handleHighKeyVerification($highKey);
        exit;
    }

    if ($action === 'create_new_admin_key') {
        handleAdminKeyRotation($_POST['new_admin_key'] ?? '', $_POST['new_admin_key_confirm'] ?? '');
        exit;
    }

    throw new InvalidArgumentException('Neplatný požadavek.');
} catch (InvalidArgumentException $e) {
    respondError($e->getMessage(), 422);
} catch (RuntimeException $e) {
    respondError($e->getMessage(), 405);
} catch (Throwable $e) {
    error_log('Login controller error: ' . $e->getMessage());
    respondError('Došlo k neočekávané chybě. Zkuste to prosím znovu.', 500);
}

function handleAdminLogin(string $adminKey): void
{
    $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $rate = checkRateLimit('admin_login_' . $identifier, 5, 900);
    if (!$rate['allowed']) {
        respondError('Příliš mnoho pokusů. Zkuste to znovu za ' . ceil($rate['retry_after'] / 60) . ' minut.', 429, ['retry_after' => $rate['retry_after']]);
    }

    $providedHash = hash('sha256', $adminKey);
    if (!defined('ADMIN_KEY_HASH') || !hash_equals(ADMIN_KEY_HASH, $providedHash)) {
        recordLoginAttempt('admin_login_' . $identifier);
        respondError('Neplatný administrátorský klíč.', 401);
    }

    resetRateLimit('admin_login_' . $identifier);

    $_SESSION['is_admin'] = true;
    $_SESSION['admin_id'] = $_SESSION['admin_id'] ?? 'WGS_ADMIN';
    $_SESSION['user_id'] = $_SESSION['user_id'] ?? 0;
    $_SESSION['user_name'] = 'Administrátor';
    $_SESSION['user_email'] = 'admin@wgs-service.cz';
    $_SESSION['role'] = 'admin';

    // Audit log
    auditLog('admin_login', [
        'method' => 'admin_key',
        'ip' => $identifier
    ]);

    respondSuccess([
        'message' => 'Přihlášení úspěšné.',
        'user' => [
            'name' => 'Administrátor',
            'role' => 'admin',
            'email' => 'admin@wgs-service.cz'
        ]
    ]);
}

function handleUserLogin(PDO $pdo, string $email, string $password): void
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Zadejte platný email.');
    }

    $stmt = $pdo->prepare('SELECT * FROM wgs_users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        respondError('Uživatel nenalezen.', 401);
    }

    $hashField = null;
    foreach (['password_hash', 'password', 'heslo'] as $field) {
        if (array_key_exists($field, $user) && !empty($user[$field])) {
            $hashField = $field;
            break;
        }
    }

    if ($hashField === null || !password_verify($password, $user[$hashField])) {
        respondError('Neplatné přihlašovací údaje.', 401);
    }

    if (array_key_exists('is_active', $user) && (int) $user['is_active'] === 0) {
        respondError('Účet byl deaktivován. Kontaktujte administrátora.', 403);
    }

    $userId = $user['id'] ?? $user['user_id'] ?? null;
    if ($userId === null) {
        $userId = $user['email'];
    }

    $_SESSION['user_id'] = $userId;
    $_SESSION['user_name'] = $user['name'] ?? ($user['email'] ?? 'Uživatel');
    $_SESSION['user_email'] = $user['email'] ?? '';

    $rawRole = $user['role'] ?? 'user';
    $_SESSION['role'] = $rawRole;

    $normalizedRole = strtolower(trim((string) $rawRole));
    $adminRoles = ['admin', 'administrator', 'superadmin'];
    $isAdminColumn = false;

    if (array_key_exists('is_admin', $user)) {
        $isAdminColumn = in_array($user['is_admin'], [1, '1', true, 'true', 'yes', 'on'], true);
    }

    $isAdminUser = $isAdminColumn || in_array($normalizedRole, $adminRoles, true);

    $_SESSION['is_admin'] = $isAdminUser;

    if ($isAdminUser) {
        $_SESSION['admin_id'] = $_SESSION['admin_id'] ?? $userId;
        $_SESSION['admin_name'] = $_SESSION['user_name'];
    }

    if (db_table_has_column($pdo, 'wgs_users', 'last_login_at')) {
        $update = $pdo->prepare('UPDATE wgs_users SET last_login_at = NOW() WHERE email = :email');
        $update->execute([':email' => $email]);
    }

    // Audit log
    auditLog('user_login', [
        'email' => $email,
        'role' => $_SESSION['role']
    ], $userId);

    respondSuccess([
        'message' => 'Přihlášení úspěšné.',
        'user' => [
            'id' => $userId,
            'name' => $_SESSION['user_name'],
            'email' => $_SESSION['user_email'],
            'role' => $_SESSION['role']
        ]
    ]);
}

function handleHighKeyVerification(string $highKey): void
{
    if ($highKey === '') {
        throw new InvalidArgumentException('Zadejte prosím high key.');
    }

    $hash = hash('sha256', $highKey);
    $expected = defined('ADMIN_HIGH_KEY_HASH') ? ADMIN_HIGH_KEY_HASH : null;
    if ($expected === null) {
        respondError('High key není na serveru nakonfigurován.', 503);
    }

    if (!hash_equals($expected, $hash)) {
        respondError('Neplatný high key.', 401);
    }

    $_SESSION['admin_high_key_verified_at'] = time();

    // Audit log
    auditLog('high_key_verified', [
        'timestamp' => time()
    ]);

    respondSuccess([
        'message' => 'High key ověřen. Můžete vytvořit nový admin klíč.'
    ]);
}

function handleAdminKeyRotation(string $newKey, string $confirmation): void
{
    if (!isset($_SESSION['admin_high_key_verified_at']) || (time() - (int) $_SESSION['admin_high_key_verified_at']) > 600) {
        respondError('Ověřte prosím nejprve high key.', 403);
    }

    if ($newKey === '' || $confirmation === '') {
        throw new InvalidArgumentException('Vyplňte nový klíč i jeho potvrzení.');
    }

    if (!hash_equals($newKey, $confirmation)) {
        throw new InvalidArgumentException('Zadané klíče se neshodují.');
    }

    $strength = isStrongPassword($newKey);
    if ($strength !== true) {
        throw new InvalidArgumentException('Nový klíč nesplňuje požadavky: ' . implode(', ', (array) $strength));
    }

    $hash = hash('sha256', $newKey);
    $_SESSION['pending_admin_key_hash'] = $hash;
    unset($_SESSION['admin_high_key_verified_at']);

    // Audit log
    auditLog('admin_key_rotated', [
        'new_hash' => $hash,
        'timestamp' => time()
    ]);

    respondSuccess([
        'message' => 'Nový klíč byl vygenerován. Aktualizujte prosím konfigurační soubor (.env).',
        'hash' => $hash
    ]);
}

function respondSuccess(array $payload = []): void
{
    echo json_encode(array_merge(['status' => 'success'], $payload), JSON_UNESCAPED_UNICODE);
}

function respondError(string $message, int $code = 400, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(array_merge([
        'status' => 'error',
        'message' => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
}
