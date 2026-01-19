<?php
/**
 * Admin Users API
 * API pro správu uživatelů (seznam, přidání, úprava, smazání)
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';
require_once __DIR__ . '/../includes/email_validator.php';

header('Content-Type: application/json');

try {
    // BEZPEČNOST: Pouze admin
    $isAdmin = isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
    if (!$isAdmin) {
        http_response_code(401);
        echo json_encode([
            'status' => 'error',
            'message' => 'Neautorizovaný přístup'
        ]);
        exit;
    }

    // PERFORMANCE FIX: Uvolnit session lock pro paralelní zpracování
    // Audit 2025-11-24: User management operations
    session_write_close();

    $pdo = getDbConnection();

    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    $data = [];

    // Pro POST operace načíst JSON data PŘED CSRF kontrolou
    if ($method === 'POST') {
        $jsonData = file_get_contents('php://input');
        if ($jsonData) {
            $data = json_decode($jsonData, true);
        }

        // BEZPEČNOST: CSRF ochrana pro POST operace
        $csrfToken = $data['csrf_token'] ?? $_POST['csrf_token'] ?? '';
        // SECURITY: Ensure CSRF token is a string, not an array
        if (is_array($csrfToken)) {
            $csrfToken = '';
        }
        if (!validateCSRFToken($csrfToken)) {
            http_response_code(403);
            echo json_encode([
                'status' => 'error',
                'message' => 'Neplatný CSRF token. Obnovte stránku a zkuste znovu.'
            ]);
            exit;
        }
    }

    if ($method === 'GET' && $action === 'list') {
        // Seznam všech uživatelů
        // BEZPEČNOST: Nejdřív zjistit jaké sloupce existují
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Základní sloupce které MUSÍ existovat
        $requiredColumns = ['id', 'email'];
        $optionalColumns = ['name', 'phone', 'address', 'role', 'status', 'is_active', 'created_at'];

        // Sestavit SELECT pouze z existujících sloupců
        $selectColumns = [];
        foreach (array_merge($requiredColumns, $optionalColumns) as $col) {
            if (in_array($col, $existingColumns)) {
                $selectColumns[] = $col;
            }
        }

        if (empty($selectColumns)) {
            throw new Exception('Tabulka wgs_users nemá požadované sloupce');
        }

        $sql = "SELECT " . implode(', ', $selectColumns) . "
                FROM wgs_users
                ORDER BY " . (in_array('created_at', $selectColumns) ? 'created_at DESC' : 'id DESC');

        $stmt = $pdo->query($sql);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Doplnit chybějící sloupce s defaultními hodnotami
        foreach ($users as &$user) {
            if (!isset($user['name'])) $user['name'] = $user['email'];
            if (!isset($user['phone'])) $user['phone'] = null;
            if (!isset($user['address'])) $user['address'] = null;
            if (!isset($user['role'])) $user['role'] = 'user';

            // Mapování is_active → status
            if (!isset($user['status']) && isset($user['is_active'])) {
                $user['status'] = $user['is_active'] ? 'active' : 'inactive';
                unset($user['is_active']); // Odstranit is_active z výstupu
            } elseif (!isset($user['status'])) {
                $user['status'] = 'active';
            }

            if (!isset($user['created_at'])) $user['created_at'] = null;
        }

        echo json_encode([
            'status' => 'success',
            'users' => $users
        ]);

    } elseif ($method === 'GET' && $action === 'online') {
        // Online uživatelé (aktivní za posledních 5 minut)
        // FIX: Používáme wgs_users.last_activity místo wgs_tokens.created_at
        // last_activity se aktualizuje v init.php při každém requestu (throttle 60s)
        try {
            // Nejdřív zkontrolovat zda sloupec last_activity existuje
            $checkCol = $pdo->query("SHOW COLUMNS FROM wgs_users LIKE 'last_activity'");
            $maSloupec = $checkCol->rowCount() > 0;

            if ($maSloupec) {
                // Nový způsob - používá last_activity z wgs_users
                $stmt = $pdo->query("
                    SELECT
                        u.user_id as id,
                        u.name,
                        u.email,
                        u.role,
                        u.last_activity
                    FROM wgs_users u
                    WHERE u.last_activity IS NOT NULL
                    AND u.last_activity >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
                    ORDER BY u.last_activity DESC
                ");
                $onlineUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            } else {
                // Fallback - starý způsob přes tokeny (pokud migrace ještě neproběhla)
                $stmt = $pdo->query("
                    SELECT DISTINCT
                        u.user_id as id,
                        u.name,
                        u.email,
                        u.role,
                        MAX(t.created_at) as last_activity
                    FROM wgs_tokens t
                    JOIN wgs_users u ON t.user_id = u.user_id
                    WHERE t.expires_at > NOW()
                    AND t.created_at >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
                    GROUP BY u.user_id, u.name, u.email, u.role
                    ORDER BY last_activity DESC
                ");
                $onlineUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        } catch (PDOException $e) {
            error_log("Online users query failed: " . $e->getMessage());
            $onlineUsers = [];
        }

        echo json_encode([
            'status' => 'success',
            'users' => $onlineUsers
        ]);

    } elseif ($method === 'POST' && $action === 'add') {
        // Přidání nového uživatele (data už načtena výše)
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $address = $data['address'] ?? '';
        $role = $data['role'] ?? 'prodejce';
        $password = $data['password'] ?? '';

        // Validace
        if (!$name || !$email || !$password) {
            throw new Exception('Jméno, email a heslo jsou povinné');
        }

        // SECURITY FIX: Posílená email validace
        $emailValidation = validateEmailStrong($email, false);
        if (!$emailValidation['valid']) {
            throw new Exception($emailValidation['error']);
        }
        // Použít normalizovaný email (lowercase)
        $email = $emailValidation['email'];

        if (strlen($password) < 8) {
            throw new Exception('Heslo musí mít alespoň 8 znaků');
        }

        // Validace telefonu (pokud je zadán)
        if (!empty($phone)) {
            // Regex pro české/slovenské telefony: +420/+421 nebo 00420/00421 nebo 9 číslic
            if (!preg_match('/^(\+420|\+421|00420|00421)?[0-9]{9}$/', preg_replace('/\s+/', '', $phone))) {
                throw new Exception('Neplatný formát telefonního čísla. Očekáván formát: +420123456789 nebo 123456789');
            }
            // Normalizace - odstranění mezer
            $phone = preg_replace('/\s+/', '', $phone);
        }

        // Validace adresy (pokud je zadána)
        if (!empty($address)) {
            if (strlen($address) > 255) {
                throw new Exception('Adresa je příliš dlouhá (max 255 znaků)');
            }
            // Sanitizace - odstranění nebezpečných znaků
            $address = strip_tags($address);
        }

        $allowedRoles = ['prodejce', 'technik', 'admin'];
        if (!in_array($role, $allowedRoles)) {
            throw new Exception('Neplatná role');
        }

        // Kontrola zda email už neexistuje - použít existující sloupec
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Zjistit správný název ID sloupce
        $idColumn = 'user_id'; // defaultně
        if (in_array('id', $existingColumns)) {
            $idColumn = 'id';
        } elseif (in_array('user_id', $existingColumns)) {
            $idColumn = 'user_id';
        }

        $stmt = $pdo->prepare("SELECT {$idColumn} FROM wgs_users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            throw new Exception('Email již existuje');
        }

        // Sestavit INSERT pouze pro existující sloupce
        $insertColumns = [];
        $insertValues = [];
        $params = [];

        if (in_array('name', $existingColumns)) {
            $insertColumns[] = 'name';
            $insertValues[] = ':name';
            $params[':name'] = $name;
        }

        if (in_array('email', $existingColumns)) {
            $insertColumns[] = 'email';
            $insertValues[] = ':email';
            $params[':email'] = $email;
        }

        if (in_array('phone', $existingColumns)) {
            $insertColumns[] = 'phone';
            $insertValues[] = ':phone';
            $params[':phone'] = $phone;
        }

        if (in_array('address', $existingColumns)) {
            $insertColumns[] = 'address';
            $insertValues[] = ':address';
            $params[':address'] = $address;
        }

        if (in_array('role', $existingColumns)) {
            $insertColumns[] = 'role';
            $insertValues[] = ':role';
            $params[':role'] = $role;
        }

        if (in_array('password_hash', $existingColumns)) {
            $insertColumns[] = 'password_hash';
            $insertValues[] = ':password_hash';
            $params[':password_hash'] = password_hash($password, PASSWORD_BCRYPT);
        }

        if (in_array('status', $existingColumns) || in_array('is_active', $existingColumns)) {
            if (in_array('status', $existingColumns)) {
                $insertColumns[] = 'status';
                $insertValues[] = ':status';
                $params[':status'] = 'active';
            } elseif (in_array('is_active', $existingColumns)) {
                $insertColumns[] = 'is_active';
                $insertValues[] = ':is_active';
                $params[':is_active'] = 1;
            }
        }

        if (in_array('created_at', $existingColumns)) {
            $insertColumns[] = 'created_at';
            $insertValues[] = 'NOW()';
        }

        if (empty($insertColumns)) {
            throw new Exception('Nelze vytvořit uživatele - tabulka nemá požadované sloupce');
        }

        // Vytvořit SQL dotaz
        $sql = "INSERT INTO wgs_users (" . implode(', ', $insertColumns) . ") VALUES (" . implode(', ', $insertValues) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        $userId = $pdo->lastInsertId();

        echo json_encode([
            'status' => 'success',
            'message' => 'Uživatel vytvořen',
            'user_id' => $userId
        ]);

    } elseif ($method === 'POST' && $action === 'delete') {
        // Smazání uživatele (data už načtena výše)
        $userId = $data['user_id'] ?? null;

        if (!$userId || !is_numeric($userId)) {
            throw new Exception('Neplatné ID uživatele');
        }

        // Zjistit správný název ID sloupce
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $idColumn = in_array('id', $existingColumns) ? 'id' : 'user_id';

        $stmt = $pdo->prepare("DELETE FROM wgs_users WHERE {$idColumn} = :id");
        $stmt->execute([':id' => $userId]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Uživatel smazán'
        ]);

    } elseif ($method === 'GET' && $action === 'get') {
        // Získání detailu konkrétního uživatele
        $userId = $_GET['user_id'] ?? null;

        if (!$userId || !is_numeric($userId)) {
            throw new Exception('Neplatné ID uživatele');
        }

        // BEZPEČNOST: Zjistit existující sloupce
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Sestavit SELECT pouze z existujících sloupců
        $selectColumns = array_intersect([
            'id', 'user_id', 'name', 'email', 'phone', 'address', 'role',
            'provize_procent', 'provize_poz_procent', 'status', 'is_active', 'created_at', 'updated_at', 'last_login'
        ], $existingColumns);

        if (empty($selectColumns)) {
            throw new Exception('Tabulka wgs_users nemá požadované sloupce');
        }

        // Zjistit správný název ID sloupce
        $idColumn = in_array('id', $existingColumns) ? 'id' : 'user_id';

        $sql = "SELECT " . implode(', ', $selectColumns) . "
                FROM wgs_users
                WHERE {$idColumn} = :id LIMIT 1";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            throw new Exception('Uživatel nenalezen');
        }

        // Doplnit chybějící sloupce s defaultními hodnotami
        if (!isset($user['name'])) $user['name'] = $user['email'];
        if (!isset($user['phone'])) $user['phone'] = '';
        if (!isset($user['address'])) $user['address'] = '';
        if (!isset($user['role'])) $user['role'] = 'user';

        // Mapování is_active → status
        if (!isset($user['status']) && isset($user['is_active'])) {
            $user['status'] = $user['is_active'] ? 'active' : 'inactive';
            unset($user['is_active']); // Odstranit is_active z výstupu
        } elseif (!isset($user['status'])) {
            $user['status'] = 'active';
        }

        // Nikdy nevrátit heslo!
        unset($user['password_hash']);
        unset($user['password']);

        echo json_encode([
            'status' => 'success',
            'user' => $user
        ]);

    } elseif ($method === 'POST' && $action === 'update') {
        // Úprava uživatele (data už načtena výše)
        $userId = $data['user_id'] ?? null;
        $name = $data['name'] ?? '';
        $email = $data['email'] ?? '';
        $phone = $data['phone'] ?? '';
        $address = $data['address'] ?? '';
        $role = $data['role'] ?? '';
        $provizeProcent = isset($data['provize_procent']) ? $data['provize_procent'] : null;
        $provizePozProcent = isset($data['provize_poz_procent']) ? $data['provize_poz_procent'] : null;

        if (!$userId || !is_numeric($userId)) {
            throw new Exception('Neplatné ID uživatele');
        }

        // Validace
        if (!$name || !$email) {
            throw new Exception('Jméno a email jsou povinné');
        }

        // SECURITY FIX: Posílená email validace
        $emailValidation = validateEmailStrong($email, false);
        if (!$emailValidation['valid']) {
            throw new Exception($emailValidation['error']);
        }
        // Použít normalizovaný email (lowercase)
        $email = $emailValidation['email'];

        // Validace telefonu (pokud je zadán)
        if (!empty($phone)) {
            if (!preg_match('/^(\+420|\+421|00420|00421)?[0-9]{9}$/', preg_replace('/\s+/', '', $phone))) {
                throw new Exception('Neplatný formát telefonního čísla');
            }
            $phone = preg_replace('/\s+/', '', $phone);
        }

        // Validace adresy
        if (!empty($address) && strlen($address) > 255) {
            throw new Exception('Adresa je příliš dlouhá (max 255 znaků)');
        }
        $address = strip_tags($address);

        $allowedRoles = ['prodejce', 'technik', 'admin'];
        if ($role && !in_array($role, $allowedRoles)) {
            throw new Exception('Neplatná role');
        }

        // Validace provize (pouze pro techniky)
        if ($provizeProcent !== null) {
            if (!is_numeric($provizeProcent) || $provizeProcent < 0 || $provizeProcent > 100) {
                throw new Exception('Provize musí být číslo mezi 0 a 100');
            }
        }

        // Validace provize POZ (pouze pro techniky)
        if ($provizePozProcent !== null) {
            if (!is_numeric($provizePozProcent) || $provizePozProcent < 0 || $provizePozProcent > 100) {
                throw new Exception('Provize POZ musí být číslo mezi 0 a 100');
            }
        }

        // Sestavit UPDATE pouze pro existující sloupce
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);

        // Zjistit správný název ID sloupce
        $idColumn = in_array('id', $existingColumns) ? 'id' : 'user_id';

        // Kontrola zda email už neexistuje (u jiného uživatele)
        $stmt = $pdo->prepare("SELECT {$idColumn} FROM wgs_users WHERE email = :email AND {$idColumn} != :id");
        $stmt->execute([':email' => $email, ':id' => $userId]);
        if ($stmt->fetch()) {
            throw new Exception('Email již používá jiný uživatel');
        }

        $updateParts = [];
        $params = [':id' => $userId];

        if (in_array('name', $existingColumns)) {
            $updateParts[] = "name = :name";
            $params[':name'] = $name;
        }
        if (in_array('email', $existingColumns)) {
            $updateParts[] = "email = :email";
            $params[':email'] = $email;
        }
        if (in_array('phone', $existingColumns)) {
            $updateParts[] = "phone = :phone";
            $params[':phone'] = $phone;
        }
        if (in_array('address', $existingColumns)) {
            $updateParts[] = "address = :address";
            $params[':address'] = $address;
        }
        if ($role && in_array('role', $existingColumns)) {
            $updateParts[] = "role = :role";
            $params[':role'] = $role;
        }
        if ($provizeProcent !== null && in_array('provize_procent', $existingColumns)) {
            $updateParts[] = "provize_procent = :provize_procent";
            $params[':provize_procent'] = $provizeProcent;
        }
        if ($provizePozProcent !== null && in_array('provize_poz_procent', $existingColumns)) {
            $updateParts[] = "provize_poz_procent = :provize_poz_procent";
            $params[':provize_poz_procent'] = $provizePozProcent;
        }
        if (in_array('updated_at', $existingColumns)) {
            $updateParts[] = "updated_at = NOW()";
        }

        if (empty($updateParts)) {
            throw new Exception('Žádné sloupce k aktualizaci');
        }

        $sql = "UPDATE wgs_users SET " . implode(', ', $updateParts) . " WHERE {$idColumn} = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);

        echo json_encode([
            'status' => 'success',
            'message' => 'Uživatel aktualizován'
        ]);

    } elseif ($method === 'POST' && $action === 'update_password') {
        // Změna hesla (data už načtena výše)
        $userId = $data['user_id'] ?? null;
        $newPassword = $data['new_password'] ?? '';

        if (!$userId || !is_numeric($userId)) {
            throw new Exception('Neplatné ID uživatele');
        }

        if (strlen($newPassword) < 8) {
            throw new Exception('Heslo musí mít alespoň 8 znaků');
        }

        // Zjistit správný název ID sloupce
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $idColumn = in_array('id', $existingColumns) ? 'id' : 'user_id';

        $stmt = $pdo->prepare("
            UPDATE wgs_users
            SET password_hash = :password_hash
            WHERE {$idColumn} = :id
        ");
        $stmt->execute([
            ':password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
            ':id' => $userId
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Heslo změněno'
        ]);

    } elseif ($method === 'POST' && $action === 'update_status') {
        // Změna statusu uživatele (active/inactive - data už načtena výše)
        $userId = $data['user_id'] ?? null;
        $status = $data['status'] ?? '';

        if (!$userId || !is_numeric($userId)) {
            throw new Exception('Neplatné ID uživatele');
        }

        if (!in_array($status, ['active', 'inactive'])) {
            throw new Exception('Neplatný status');
        }

        // Zjistit správný název ID sloupce a status sloupce
        $stmt = $pdo->query("SHOW COLUMNS FROM wgs_users");
        $existingColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
        $idColumn = in_array('id', $existingColumns) ? 'id' : 'user_id';

        // Zjistit zda tabulka má 'status' nebo 'is_active'
        if (in_array('status', $existingColumns)) {
            // Tabulka používá status (varchar)
            $stmt = $pdo->prepare("
                UPDATE wgs_users
                SET status = :status
                WHERE {$idColumn} = :id
            ");
            $stmt->execute([
                ':status' => $status,
                ':id' => $userId
            ]);
        } elseif (in_array('is_active', $existingColumns)) {
            // Tabulka používá is_active (tinyint)
            $isActive = ($status === 'active') ? 1 : 0;
            $stmt = $pdo->prepare("
                UPDATE wgs_users
                SET is_active = :is_active
                WHERE {$idColumn} = :id
            ");
            $stmt->execute([
                ':is_active' => $isActive,
                ':id' => $userId
            ]);
        } else {
            throw new Exception('Tabulka nemá sloupec status ani is_active');
        }

        echo json_encode([
            'status' => 'success',
            'message' => 'Status změněn'
        ]);

    } else {
        throw new Exception('Neplatná akce nebo metoda');
    }

} catch (PDOException $e) {
    // Logovat databázovou chybu pro debugging
    error_log("Admin Users API DB Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Databázová chyba: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Logovat obecnou chybu
    error_log("Admin Users API Error: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());

    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
