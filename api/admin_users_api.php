<?php
/**
 * Admin Users API
 * API pro správu uživatelů (seznam, přidání, úprava, smazání)
 */

require_once __DIR__ . '/../init.php';
require_once __DIR__ . '/../includes/csrf_helper.php';

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
        $optionalColumns = ['name', 'phone', 'address', 'role', 'status', 'created_at'];

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
            if (!isset($user['status'])) $user['status'] = 'active';
            if (!isset($user['created_at'])) $user['created_at'] = null;
        }

        echo json_encode([
            'status' => 'success',
            'users' => $users
        ]);

    } elseif ($method === 'GET' && $action === 'online') {
        // Online uživatelé (aktivní tokeny za posledních 15 minut)
        // POZNÁMKA: wgs_sessions tabulka byla odstraněna, používáme wgs_tokens
        try {
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
        } catch (PDOException $e) {
            error_log("wgs_tokens query failed: " . $e->getMessage());
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

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception('Neplatný formát emailu');
        }

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

        // Kontrola zda email už neexistuje
        $stmt = $pdo->prepare("SELECT id FROM wgs_users WHERE email = :email");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            throw new Exception('Email již existuje');
        }

        // Vložení uživatele
        $stmt = $pdo->prepare("
            INSERT INTO wgs_users (
                name, email, phone, address, role, password_hash, status, created_at
            ) VALUES (
                :name, :email, :phone, :address, :role, :password_hash, 'active', NOW()
            )
        ");
        $stmt->execute([
            ':name' => $name,
            ':email' => $email,
            ':phone' => $phone,
            ':address' => $address,
            ':role' => $role,
            ':password_hash' => password_hash($password, PASSWORD_BCRYPT)
        ]);

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

        $stmt = $pdo->prepare("DELETE FROM wgs_users WHERE id = :id");
        $stmt->execute([':id' => $userId]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Uživatel smazán'
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

        $stmt = $pdo->prepare("
            UPDATE wgs_users
            SET status = :status
            WHERE id = :id
        ");
        $stmt->execute([
            ':status' => $status,
            ':id' => $userId
        ]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Status změněn'
        ]);

    } else {
        throw new Exception('Neplatná akce nebo metoda');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => $e->getMessage()
    ]);
}
