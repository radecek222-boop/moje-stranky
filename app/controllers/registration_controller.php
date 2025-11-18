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

    $registrationKey = trim($_POST['registration_key'] ?? '');
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($registrationKey === '') {
        throw new InvalidArgumentException('Registrační klíč je povinný.');
    }
    if ($name === '') {
        throw new InvalidArgumentException('Zadejte jméno.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Zadejte platný email.');
    }
    if (strlen($password) < 8) {
        throw new InvalidArgumentException('Heslo musí mít alespoň 8 znaků.');
    }

    $strength = isStrongPassword($password);
    if ($strength !== true) {
        throw new InvalidArgumentException('Heslo nesplňuje požadavky: ' . implode(', ', (array) $strength));
    }

    $pdo = getDbConnection();
    $pdo->beginTransaction();

    // CRITICAL FIX: FOR UPDATE lock pro ochranu proti race condition (max_usage bypass)
    $keyStmt = $pdo->prepare('SELECT * FROM wgs_registration_keys WHERE key_code = :code LIMIT 1 FOR UPDATE');
    $keyStmt->execute([':code' => $registrationKey]);
    $keyRow = $keyStmt->fetch(PDO::FETCH_ASSOC);

    if (!$keyRow) {
        throw new InvalidArgumentException('Registrační klíč nebyl nalezen.');
    }
    if (isset($keyRow['is_active']) && (int) $keyRow['is_active'] === 0) {
        throw new InvalidArgumentException('Registrační klíč byl deaktivován.');
    }
    if (isset($keyRow['max_usage']) && $keyRow['max_usage'] !== null) {
        $max = (int) $keyRow['max_usage'];
        $used = (int) ($keyRow['usage_count'] ?? 0);
        if ($max > 0 && $used >= $max) {
            throw new InvalidArgumentException('Registrační klíč již byl vyčerpán.');
        }
    }

    $role = $keyRow['key_type'] ?? 'user';

    // CRITICAL FIX: FOR UPDATE lock pro ochranu proti race condition (duplicate email)
    $existingStmt = $pdo->prepare('SELECT 1 FROM wgs_users WHERE email = :email LIMIT 1 FOR UPDATE');
    $existingStmt->execute([':email' => $email]);
    if ($existingStmt->fetchColumn()) {
        throw new InvalidArgumentException('Uživatel s tímto emailem již existuje.');
    }

    $columns = db_get_table_columns($pdo, 'wgs_users');
    $now = date('Y-m-d H:i:s');

    // CRITICAL: Vygenerovat user_id podle role
    $rolePrefix = ($role === 'technik') ? 'TCH' : 'PRT';
    $currentYear = date('Y');

    // Najít maximální číslo pro danou roli v tomto roce
    $maxIdStmt = $pdo->prepare("
        SELECT user_id
        FROM wgs_users
        WHERE user_id LIKE :prefix
        ORDER BY user_id DESC
        LIMIT 1
    ");
    $maxIdStmt->execute([':prefix' => $rolePrefix . $currentYear . '%']);
    $maxIdRow = $maxIdStmt->fetch(PDO::FETCH_ASSOC);

    if ($maxIdRow && preg_match('/' . $rolePrefix . $currentYear . '(\d+)/', $maxIdRow['user_id'], $matches)) {
        $nextNumber = (int)$matches[1] + 1;
    } else {
        $nextNumber = 1;
    }

    $generatedUserId = $rolePrefix . $currentYear . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);

    $userData = [];

    // CRITICAL: Přidat vygenerované user_id
    if (in_array('user_id', $columns, true)) {
        $userData['user_id'] = $generatedUserId;
    }

    if (in_array('name', $columns, true)) {
        $userData['name'] = $name;
    }
    if (in_array('email', $columns, true)) {
        $userData['email'] = $email;
    }
    if (in_array('phone', $columns, true)) {
        $userData['phone'] = $phone;
    } elseif (in_array('telefon', $columns, true)) {
        $userData['telefon'] = $phone;
    }

    $passwordHash = password_hash($password, PASSWORD_DEFAULT);
    if (in_array('password_hash', $columns, true)) {
        $userData['password_hash'] = $passwordHash;
    } elseif (in_array('password', $columns, true)) {
        $userData['password'] = $passwordHash;
    } elseif (in_array('heslo', $columns, true)) {
        $userData['heslo'] = $passwordHash;
    }

    if (in_array('role', $columns, true)) {
        $userData['role'] = $role;
    }
    if (in_array('status', $columns, true)) {
        $userData['status'] = 'active';
    }
    if (in_array('is_active', $columns, true)) {
        $userData['is_active'] = 1;
    }
    if (in_array('registration_key_code', $columns, true)) {
        $userData['registration_key_code'] = $registrationKey;
    }
    if (in_array('registration_key_type', $columns, true)) {
        $userData['registration_key_type'] = $role;
    }
    if (in_array('must_change_password', $columns, true)) {
        $userData['must_change_password'] = 0;
    }
    if (in_array('created_at', $columns, true)) {
        $userData['created_at'] = $now;
    }
    if (in_array('updated_at', $columns, true)) {
        $userData['updated_at'] = $now;
    }
    if (in_array('password_changed_at', $columns, true)) {
        $userData['password_changed_at'] = $now;
    }

    $hasPassword = isset($userData['password_hash']) || isset($userData['password']) || isset($userData['heslo']);
    if (empty($userData['email']) || !$hasPassword) {
        throw new RuntimeException('Struktura tabulky wgs_users neobsahuje očekávané sloupce.');
    }

    $fieldNames = array_keys($userData);
    $placeholders = array_map(fn($name) => ':' . $name, $fieldNames);
    $insertSql = 'INSERT INTO wgs_users (' . implode(', ', $fieldNames) . ') VALUES (' . implode(', ', $placeholders) . ')';

    $insertStmt = $pdo->prepare($insertSql);
    $params = [];
    foreach ($userData as $column => $value) {
        $params[':' . $column] = $value;
    }

    if (!$insertStmt->execute($params)) {
        throw new RuntimeException('Nepodařilo se vytvořit uživatele.');
    }

    $userId = $pdo->lastInsertId();

    if (isset($keyRow['id']) && in_array('usage_count', db_get_table_columns($pdo, 'wgs_registration_keys'), true)) {
        $updateKey = $pdo->prepare('UPDATE wgs_registration_keys SET usage_count = COALESCE(usage_count, 0) + 1 WHERE id = :id');
        $updateKey->execute([':id' => $keyRow['id']]);

        if (isset($keyRow['max_usage']) && $keyRow['max_usage'] !== null) {
            $max = (int) $keyRow['max_usage'];
            $used = ((int) ($keyRow['usage_count'] ?? 0)) + 1;
            if ($max > 0 && $used >= $max) {
                $deactivate = $pdo->prepare('UPDATE wgs_registration_keys SET is_active = 0 WHERE id = :id');
                $deactivate->execute([':id' => $keyRow['id']]);
            }
        }
    }

    $pdo->commit();

    echo json_encode([
        'status' => 'success',
        'message' => 'Registrace byla úspěšně dokončena.',
        'user_id' => $userId
    ], JSON_UNESCAPED_UNICODE);
} catch (InvalidArgumentException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(422);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (RuntimeException $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Registration controller error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Došlo k neočekávané chybě. Zkuste to prosím znovu.'], JSON_UNESCAPED_UNICODE);
}
