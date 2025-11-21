<?php
require_once __DIR__ . '/../../init.php';
require_once __DIR__ . '/../../includes/csrf_helper.php';
require_once __DIR__ . '/../../includes/db_metadata.php';
require_once __DIR__ . '/../../includes/audit_logger.php';
require_once __DIR__ . '/../../includes/rate_limiter.php';

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

/**
 * HandleAdminLogin
 *
 * @param string $adminKey AdminKey
 */
function handleAdminLogin(string $adminKey): void
{
    // ✅ FIX 9: Databázový rate limiting místo file-based
    $pdo = getDbConnection();
    $rateLimiter = new RateLimiter($pdo);
    $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $rateCheck = $rateLimiter->checkLimit(
        $identifier,
        'admin_login',
        ['max_attempts' => 5, 'window_minutes' => 15, 'block_minutes' => 60]
    );

    if (!$rateCheck['allowed']) {
        respondError($rateCheck['message'], 429, ['retry_after' => strtotime($rateCheck['reset_at']) - time()]);
    }

    $providedHash = hash('sha256', $adminKey);
    if (!defined('ADMIN_KEY_HASH') || !hash_equals(ADMIN_KEY_HASH, $providedHash)) {
        // ✅ FIX 9: RateLimiter již zaznamenal pokus automaticky v checkLimit()
        respondError('Neplatný administrátorský klíč.', 401);
    }

    // ✅ FIX 9: Reset rate limit při úspěšném přihlášení
    $rateLimiter->reset($identifier, 'admin_login');

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

/**
 * HandleUserLogin
 *
 * @param PDO $pdo Pdo
 * @param string $email Email
 * @param string $password Password
 */
function handleUserLogin(PDO $pdo, string $email, string $password): void
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Zadejte platný email.');
    }

    // ✅ FIX 9: Databázový rate limiting místo file-based
    $rateLimiter = new RateLimiter($pdo);
    $identifier = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

    $rateCheck = $rateLimiter->checkLimit(
        $identifier,
        'user_login',
        ['max_attempts' => 5, 'window_minutes' => 5, 'block_minutes' => 30]
    );

    if (!$rateCheck['allowed']) {
        respondError($rateCheck['message'], 429, ['retry_after' => strtotime($rateCheck['reset_at']) - time()]);
    }

    $stmt = $pdo->prepare('SELECT * FROM wgs_users WHERE email = :email LIMIT 1');
    $stmt->execute([':email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        // ✅ FIX 9: RateLimiter již zaznamenal pokus automaticky v checkLimit()
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
        // ✅ FIX 9: RateLimiter již zaznamenal pokus automaticky v checkLimit()
        respondError('Neplatné přihlašovací údaje.', 401);
    }

    // ✅ FIX 9: Reset rate limit při úspěšném přihlášení
    $rateLimiter->reset($identifier, 'user_login');

    if (array_key_exists('is_active', $user) && (int) $user['is_active'] === 0) {
        respondError('Účet byl deaktivován. Kontaktujte administrátora.', 403);
    }

    // SECURITY FIX: Regenerovat session ID pro ochranu proti session fixation
    session_regenerate_id(true);

    $userId = $user['id'] ?? $user['user_id'] ?? null;
    if ($userId === null) {
        $userId = $user['email'];
    }

    // ✅ FIX 11: Remember Me token
    $rememberMe = $_POST['remember_me'] ?? '0';
    if ($rememberMe === '1' || $rememberMe === 'true') {
        createRememberToken($pdo, $userId);
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

/**
 * HandleHighKeyVerification
 *
 * @param string $highKey HighKey
 */
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

/**
 * HandleAdminKeyRotation
 *
 * @param string $newKey NewKey
 * @param string $confirmation Confirmation
 */
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

/**
 * Vytvoří Remember Me token pro uživatele
 *
 * @param PDO $pdo Databázové připojení
 * @param string $userId ID uživatele
 */
function createRememberToken(PDO $pdo, string $userId): void
{
    // Generovat náhodný selector (veřejný identifikátor)
    $selector = bin2hex(random_bytes(16)); // 32 chars

    // Generovat náhodný validator (tajný klíč)
    $validator = bin2hex(random_bytes(32)); // 64 chars

    // Hashovat validator před uložením do DB
    $hashedValidator = hash('sha256', $validator);

    // Expirace za 30 dní
    $expiresAt = date('Y-m-d H:i:s', strtotime('+30 days'));

    // Uložit do databáze
    $stmt = $pdo->prepare("
        INSERT INTO wgs_remember_tokens
        (user_id, selector, hashed_validator, expires_at, ip_address, user_agent)
        VALUES (:user_id, :selector, :hashed_validator, :expires_at, :ip, :user_agent)
    ");

    $stmt->execute([
        ':user_id' => $userId,
        ':selector' => $selector,
        ':hashed_validator' => $hashedValidator,
        ':expires_at' => $expiresAt,
        ':ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);

    // Nastavit cookie (selector:validator)
    $cookieValue = $selector . ':' . $validator;

    setcookie(
        'remember_me',
        $cookieValue,
        [
            'expires' => strtotime('+30 days'),
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax'
        ]
    );

    auditLog('remember_token_created', [
        'user_id' => $userId,
        'expires_at' => $expiresAt
    ], $userId);
}

/**
 * RespondSuccess
 *
 * @param array $payload Payload
 */
function respondSuccess(array $payload = []): void
{
    echo json_encode(array_merge(['status' => 'success'], $payload), JSON_UNESCAPED_UNICODE);
}

/**
 * RespondError
 *
 * @param string $message Message
 * @param int $code Code
 * @param array $extra Extra
 */
function respondError(string $message, int $code = 400, array $extra = []): void
{
    http_response_code($code);
    echo json_encode(array_merge([
        'status' => 'error',
        'message' => $message
    ], $extra), JSON_UNESCAPED_UNICODE);
}
